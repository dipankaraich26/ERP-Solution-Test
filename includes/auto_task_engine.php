<?php
/**
 * Auto-Task Engine
 * Handles event-based and recurring task auto-generation from admin-configured rules.
 * Uses createAutoTask() from includes/auto_task.php for actual task creation.
 */

require_once __DIR__ . '/auto_task.php';

/**
 * Fire all matching event rules for a module+event.
 * Call this from module pages when events happen.
 *
 * @param PDO $pdo
 * @param string $module  e.g. 'sales_order', 'work_order', 'purchase_order', 'stock_entry', 'installation', 'invoice'
 * @param string $event   e.g. 'created', 'released', 'completed', 'approved', 'cancelled'
 * @param array  $context ['reference' => 'SO-0001', 'record_id' => 5, 'customer_id' => 3, ...]
 * @return int Number of tasks created
 */
function fireAutoTaskEvent($pdo, $module, $event, $context = []) {
    try {
        $pdo->query("SELECT 1 FROM auto_task_rules LIMIT 1");
    } catch (PDOException $e) {
        return 0; // Table doesn't exist yet
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM auto_task_rules
            WHERE rule_type = 'event'
              AND is_active = 1
              AND trigger_module = ?
              AND trigger_event = ?
        ");
        $stmt->execute([$module, $event]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $created = 0;
        foreach ($rules as $rule) {
            $result = executeRule($pdo, $rule, $context);
            if ($result) $created++;
        }
        return $created;
    } catch (Exception $e) {
        error_log("fireAutoTaskEvent failed [{$module}.{$event}]: " . $e->getMessage());
        return 0;
    }
}

/**
 * Run all due recurring task rules.
 * Called from cron or manual trigger.
 *
 * @param PDO $pdo
 * @return array ['total_checked' => int, 'tasks_created' => int, 'details' => [...]]
 */
function runRecurringTasks($pdo) {
    $result = ['total_checked' => 0, 'tasks_created' => 0, 'details' => []];

    try {
        $pdo->query("SELECT 1 FROM auto_task_rules LIMIT 1");
    } catch (PDOException $e) {
        return $result;
    }

    try {
        $rules = $pdo->query("
            SELECT * FROM auto_task_rules
            WHERE rule_type = 'recurring' AND is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $dayOfWeek = (int)date('w'); // 0=Sun..6=Sat
        $dayOfMonth = (int)date('j'); // 1-31

        foreach ($rules as $rule) {
            $result['total_checked']++;
            $shouldRun = false;

            // Check if already ran today
            if ($rule['last_run_at'] && date('Y-m-d', strtotime($rule['last_run_at'])) === $today) {
                $result['details'][] = ['rule' => $rule['rule_name'], 'status' => 'skipped', 'reason' => 'Already ran today'];
                continue;
            }

            switch ($rule['frequency']) {
                case 'daily':
                    $shouldRun = true;
                    break;
                case 'weekly':
                    $shouldRun = ($rule['day_of_week'] !== null && (int)$rule['day_of_week'] === $dayOfWeek);
                    break;
                case 'monthly':
                    $shouldRun = ($rule['day_of_month'] !== null && (int)$rule['day_of_month'] === $dayOfMonth);
                    break;
            }

            if (!$shouldRun) {
                $result['details'][] = ['rule' => $rule['rule_name'], 'status' => 'skipped', 'reason' => 'Not scheduled today'];
                continue;
            }

            $context = [
                'date' => $today,
                'reference' => 'Recurring-' . $rule['id'],
            ];

            $taskId = executeRule($pdo, $rule, $context);
            if ($taskId) {
                $pdo->prepare("UPDATE auto_task_rules SET last_run_at = NOW() WHERE id = ?")->execute([$rule['id']]);
                $result['tasks_created']++;
                $result['details'][] = ['rule' => $rule['rule_name'], 'status' => 'created', 'task_id' => $taskId];
            } else {
                $result['details'][] = ['rule' => $rule['rule_name'], 'status' => 'failed'];
            }
        }
    } catch (Exception $e) {
        error_log("runRecurringTasks failed: " . $e->getMessage());
    }

    return $result;
}

/**
 * Execute a single rule and create the task.
 *
 * @param PDO   $pdo
 * @param array $rule    Row from auto_task_rules
 * @param array $context Contextual data for placeholder replacement
 * @return int|false Created task ID or false
 */
function executeRule($pdo, $rule, $context = []) {
    try {
        // Resolve assignment
        $assignTo = resolveAssignment($pdo, $rule);

        // Replace placeholders in templates
        $taskName = replacePlaceholders($rule['task_name_template'], $context);
        $taskDesc = replacePlaceholders($rule['task_description_template'] ?? '', $context);

        // Calculate due date
        $dueDate = null;
        if ($rule['due_days']) {
            $dueDate = date('Y-m-d', strtotime('+' . (int)$rule['due_days'] . ' days'));
        }

        return createAutoTask($pdo, [
            'task_name' => $taskName,
            'task_description' => $taskDesc ?: null,
            'priority' => $rule['priority'] ?? 'Medium',
            'assigned_to' => $assignTo,
            'start_date' => date('Y-m-d'),
            'due_date' => $dueDate,
            'related_module' => $context['module'] ?? $rule['trigger_module'] ?? null,
            'related_id' => $context['record_id'] ?? null,
            'related_reference' => $context['reference'] ?? null,
            'customer_id' => $context['customer_id'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("executeRule failed [rule #{$rule['id']}]: " . $e->getMessage());
        return false;
    }
}

/**
 * Resolve the employee to assign the task to.
 *
 * @param PDO   $pdo
 * @param array $rule
 * @return int|null Employee ID or null
 */
function resolveAssignment($pdo, $rule) {
    if ($rule['assign_type'] === 'employee' && $rule['assign_employee_id']) {
        return (int)$rule['assign_employee_id'];
    }

    if ($rule['assign_type'] === 'department' && $rule['assign_department']) {
        try {
            $employees = $pdo->prepare("
                SELECT id FROM employees
                WHERE department = ? AND status = 'Active'
                ORDER BY RAND()
                LIMIT 1
            ");
            $employees->execute([$rule['assign_department']]);
            $emp = $employees->fetchColumn();
            return $emp ? (int)$emp : null;
        } catch (Exception $e) {
            return null;
        }
    }

    return null;
}

/**
 * Replace placeholders in task name/description templates.
 * Supported: {reference}, {date}, {module}, {event}, {department}, and any key from context.
 *
 * @param string $template
 * @param array  $context
 * @return string
 */
function replacePlaceholders($template, $context) {
    if (!$template) return '';

    $replacements = [
        '{date}' => date('d-M-Y'),
        '{today}' => date('d-M-Y'),
        '{month}' => date('F Y'),
        '{week}' => 'Week ' . date('W'),
    ];

    // Add all context values as placeholders
    foreach ($context as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $replacements['{' . $key . '}'] = $value;
        }
    }

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}
