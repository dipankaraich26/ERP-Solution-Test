<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

// Auto-create table
try {
    $pdo->query("SELECT 1 FROM auto_task_rules LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auto_task_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_name VARCHAR(150) NOT NULL,
            rule_type ENUM('event','recurring') NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            trigger_module VARCHAR(50) DEFAULT NULL,
            trigger_event VARCHAR(50) DEFAULT NULL,
            frequency VARCHAR(20) DEFAULT NULL,
            day_of_week TINYINT DEFAULT NULL,
            day_of_month TINYINT DEFAULT NULL,
            recurring_time TIME DEFAULT '09:00:00',
            task_name_template VARCHAR(255) NOT NULL,
            task_description_template TEXT,
            category_id INT DEFAULT NULL,
            priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
            due_days INT DEFAULT NULL,
            assign_type ENUM('employee','department') DEFAULT 'employee',
            assign_employee_id INT DEFAULT NULL,
            assign_department VARCHAR(100) DEFAULT NULL,
            last_run_at DATETIME DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}

$success = '';
$error = '';

// Lookups
$categories = $pdo->query("SELECT id, category_name, color_code FROM task_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT id, emp_id, CONCAT(first_name, ' ', last_name) as name, department, designation FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

$departments = [];
try {
    $departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Fallback: get distinct departments from employees
    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
}

$modules = [
    'sales_order' => 'Sales Order',
    'work_order' => 'Work Order',
    'purchase_order' => 'Purchase Order',
    'stock_entry' => 'Stock Entry',
    'installation' => 'Installation',
    'invoice' => 'Invoice',
];
$events = ['created' => 'Created', 'released' => 'Released', 'completed' => 'Completed', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];
$frequencies = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$daysOfWeek = ['0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['rule_id'] ?? 0);
        $data = [
            'rule_name' => trim($_POST['rule_name'] ?? ''),
            'rule_type' => $_POST['rule_type'] ?? 'event',
            'trigger_module' => $_POST['trigger_module'] ?: null,
            'trigger_event' => $_POST['trigger_event'] ?: null,
            'frequency' => $_POST['frequency'] ?: null,
            'day_of_week' => $_POST['day_of_week'] !== '' ? (int)$_POST['day_of_week'] : null,
            'day_of_month' => $_POST['day_of_month'] !== '' ? (int)$_POST['day_of_month'] : null,
            'recurring_time' => $_POST['recurring_time'] ?: '09:00:00',
            'task_name_template' => trim($_POST['task_name_template'] ?? ''),
            'task_description_template' => trim($_POST['task_description_template'] ?? ''),
            'category_id' => $_POST['category_id'] ?: null,
            'priority' => $_POST['priority'] ?? 'Medium',
            'due_days' => $_POST['due_days'] !== '' ? (int)$_POST['due_days'] : null,
            'assign_type' => $_POST['assign_type'] ?? 'employee',
            'assign_employee_id' => $_POST['assign_employee_id'] ?: null,
            'assign_department' => $_POST['assign_department'] ?: null,
        ];

        if (empty($data['rule_name'])) {
            $error = "Rule name is required.";
        } elseif (empty($data['task_name_template'])) {
            $error = "Task name template is required.";
        } else {
            try {
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("
                        UPDATE auto_task_rules SET
                            rule_name = ?, rule_type = ?, trigger_module = ?, trigger_event = ?,
                            frequency = ?, day_of_week = ?, day_of_month = ?, recurring_time = ?,
                            task_name_template = ?, task_description_template = ?, category_id = ?,
                            priority = ?, due_days = ?, assign_type = ?, assign_employee_id = ?, assign_department = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $data['rule_name'], $data['rule_type'], $data['trigger_module'], $data['trigger_event'],
                        $data['frequency'], $data['day_of_week'], $data['day_of_month'], $data['recurring_time'],
                        $data['task_name_template'], $data['task_description_template'], $data['category_id'],
                        $data['priority'], $data['due_days'], $data['assign_type'], $data['assign_employee_id'], $data['assign_department'],
                        $id
                    ]);
                    $success = "Rule updated successfully!";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO auto_task_rules (
                            rule_name, rule_type, trigger_module, trigger_event,
                            frequency, day_of_week, day_of_month, recurring_time,
                            task_name_template, task_description_template, category_id,
                            priority, due_days, assign_type, assign_employee_id, assign_department, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['rule_name'], $data['rule_type'], $data['trigger_module'], $data['trigger_event'],
                        $data['frequency'], $data['day_of_week'], $data['day_of_month'], $data['recurring_time'],
                        $data['task_name_template'], $data['task_description_template'], $data['category_id'],
                        $data['priority'], $data['due_days'], $data['assign_type'], $data['assign_employee_id'], $data['assign_department'],
                        $_SESSION['user_id'] ?? null
                    ]);
                    $success = "Rule created successfully!";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle' && !empty($_POST['rule_id'])) {
        $pdo->prepare("UPDATE auto_task_rules SET is_active = NOT is_active WHERE id = ?")->execute([$_POST['rule_id']]);
        $success = "Rule status toggled.";
    }

    if ($action === 'delete' && !empty($_POST['rule_id'])) {
        $pdo->prepare("DELETE FROM auto_task_rules WHERE id = ?")->execute([$_POST['rule_id']]);
        $success = "Rule deleted.";
    }

    if ($action === 'run_recurring') {
        require_once '../includes/auto_task_engine.php';
        $result = runRecurringTasks($pdo);
        $success = "Recurring tasks executed: {$result['tasks_created']} task(s) created out of {$result['total_checked']} rule(s) checked.";
        if (!empty($result['details'])) {
            foreach ($result['details'] as $d) {
                $success .= "\n- {$d['rule']}: {$d['status']}" . (isset($d['reason']) ? " ({$d['reason']})" : '');
            }
        }
    }
}

// Fetch all rules
$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'event') $where = "WHERE rule_type = 'event'";
elseif ($filter === 'recurring') $where = "WHERE rule_type = 'recurring'";

$rules = $pdo->query("
    SELECT r.*, tc.category_name, tc.color_code,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.emp_id as employee_emp_id
    FROM auto_task_rules r
    LEFT JOIN task_categories tc ON r.category_id = tc.id
    LEFT JOIN employees e ON r.assign_employee_id = e.id
    $where
    ORDER BY r.is_active DESC, r.rule_type, r.rule_name
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalRules = count($rules);
$activeEvent = count(array_filter($rules, fn($r) => $r['is_active'] && $r['rule_type'] === 'event'));
$activeRecurring = count(array_filter($rules, fn($r) => $r['is_active'] && $r['rule_type'] === 'recurring'));

// Edit mode
$editRule = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM auto_task_rules WHERE id = ?");
    $editStmt->execute([$_GET['edit']]);
    $editRule = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="content" style="overflow-y: auto; height: 100vh; padding-bottom: 60px;">
    <style>
        .stats-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 120px; padding: 15px 20px; border-radius: 10px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .stat-card .stat-value { font-size: 1.8em; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.85em; color: #666; margin-top: 4px; }

        .form-section { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .form-section h3 { margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #667eea; color: #2c3e50; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 0.9em; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 9px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95em;
        }
        .form-group textarea { min-height: 70px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group.full-width { grid-column: 1 / -1; }

        .rule-card { background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; }
        .rule-card.inactive { opacity: 0.5; }
        .rule-info { flex: 1; min-width: 250px; }
        .rule-info h4 { margin: 0 0 6px 0; color: #2c3e50; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .rule-info .meta { color: #666; font-size: 0.85em; line-height: 1.6; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 600; }
        .badge-event { background: #e3f2fd; color: #1565c0; }
        .badge-recurring { background: #f3e5f5; color: #7b1fa2; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #ffebee; color: #c62828; }
        .badge-priority-critical { background: #ffebee; color: #c62828; }
        .badge-priority-high { background: #fff3e0; color: #ef6c00; }
        .badge-priority-medium { background: #fffde7; color: #f9a825; }
        .badge-priority-low { background: #eceff1; color: #78909c; }

        .rule-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .rule-actions form { margin: 0; }

        .type-toggle { display: flex; gap: 10px; margin-bottom: 15px; }
        .type-toggle label { flex: 1; text-align: center; padding: 12px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .type-toggle input { display: none; }
        .type-toggle input:checked + label { border-color: #667eea; background: #f0f0ff; color: #667eea; }

        .conditional-fields { display: none; }
        .conditional-fields.visible { display: block; }

        .assign-toggle { display: flex; gap: 10px; margin-bottom: 10px; }
        .assign-toggle label { padding: 8px 16px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9em; }
        .assign-toggle input { display: none; }
        .assign-toggle input:checked + label { border-color: #667eea; background: #f0f0ff; }

        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .filter-tabs a { padding: 8px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9em; color: #666; background: #f1f3f5; }
        .filter-tabs a.active { background: #667eea; color: white; }

        .placeholder-hints { font-size: 0.8em; color: #888; margin-top: 4px; }
    </style>

    <h1>Auto-Task Rules</h1>
    <p style="color: #666; margin-bottom: 20px;">Configure rules to automatically create tasks when events happen or on a recurring schedule.</p>

    <?php if ($success): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; white-space: pre-line;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value" style="color: #2c3e50;"><?= $totalRules ?></div>
            <div class="stat-label">Total Rules</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #1565c0;"><?= $activeEvent ?></div>
            <div class="stat-label">Active Event Rules</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #7b1fa2;"><?= $activeRecurring ?></div>
            <div class="stat-label">Active Recurring Rules</div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="document.getElementById('runRecurringForm').submit();">
            <div class="stat-value" style="color: #e67e22;">&#9654;</div>
            <div class="stat-label">Run Recurring Now</div>
            <form id="runRecurringForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="run_recurring">
            </form>
        </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="form-section" id="ruleForm">
        <h3><?= $editRule ? 'Edit Rule' : 'Add New Rule' ?></h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= $editRule ? 'edit' : 'add' ?>">
            <?php if ($editRule): ?>
                <input type="hidden" name="rule_id" value="<?= $editRule['id'] ?>">
            <?php endif; ?>

            <!-- Rule Name -->
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Rule Name *</label>
                    <input type="text" name="rule_name" required placeholder="e.g., Auto-task on SO creation"
                           value="<?= htmlspecialchars($editRule['rule_name'] ?? '') ?>">
                </div>
            </div>

            <!-- Rule Type Toggle -->
            <div class="type-toggle">
                <input type="radio" name="rule_type" value="event" id="type_event"
                       <?= ($editRule['rule_type'] ?? 'event') === 'event' ? 'checked' : '' ?>
                       onchange="toggleRuleType()">
                <label for="type_event">Event-Based</label>

                <input type="radio" name="rule_type" value="recurring" id="type_recurring"
                       <?= ($editRule['rule_type'] ?? '') === 'recurring' ? 'checked' : '' ?>
                       onchange="toggleRuleType()">
                <label for="type_recurring">Recurring / Scheduled</label>
            </div>

            <!-- Event Fields -->
            <div id="eventFields" class="conditional-fields">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Trigger Module *</label>
                        <select name="trigger_module">
                            <option value="">-- Select Module --</option>
                            <?php foreach ($modules as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($editRule['trigger_module'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Trigger Event *</label>
                        <select name="trigger_event">
                            <option value="">-- Select Event --</option>
                            <?php foreach ($events as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($editRule['trigger_event'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Recurring Fields -->
            <div id="recurringFields" class="conditional-fields">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Frequency *</label>
                        <select name="frequency" id="frequencySelect" onchange="toggleFrequencyFields()">
                            <option value="">-- Select --</option>
                            <?php foreach ($frequencies as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($editRule['frequency'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="dayOfWeekGroup" style="display:none;">
                        <label>Day of Week</label>
                        <select name="day_of_week">
                            <option value="">-- Select --</option>
                            <?php foreach ($daysOfWeek as $key => $label): ?>
                                <option value="<?= $key ?>" <?= ($editRule['day_of_week'] ?? '') == $key && $editRule['day_of_week'] !== null ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="dayOfMonthGroup" style="display:none;">
                        <label>Day of Month (1-28)</label>
                        <input type="number" name="day_of_month" min="1" max="28" value="<?= htmlspecialchars($editRule['day_of_month'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="recurring_time" value="<?= htmlspecialchars($editRule['recurring_time'] ?? '09:00') ?>">
                    </div>
                </div>
            </div>

            <!-- Task Template -->
            <div style="margin-top: 15px;">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Task Name Template *</label>
                        <input type="text" name="task_name_template" required
                               placeholder="e.g., Follow up on {reference} - {date}"
                               value="<?= htmlspecialchars($editRule['task_name_template'] ?? '') ?>">
                        <div class="placeholder-hints">Placeholders: {reference}, {date}, {today}, {month}, {week}, {module}, {event}</div>
                    </div>
                    <div class="form-group full-width">
                        <label>Task Description Template</label>
                        <textarea name="task_description_template"
                                  placeholder="e.g., {module} {reference} was {event}. Please follow up."><?= htmlspecialchars($editRule['task_description_template'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">-- None --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editRule['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="Low" <?= ($editRule['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= ($editRule['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($editRule['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Critical" <?= ($editRule['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due in X Days</label>
                        <input type="number" name="due_days" min="0" placeholder="e.g., 7"
                               value="<?= htmlspecialchars($editRule['due_days'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Assignment -->
            <div style="margin-top: 15px;">
                <label style="font-weight: 600; color: #2c3e50; margin-bottom: 8px; display: block;">Assignment</label>
                <div class="assign-toggle">
                    <input type="radio" name="assign_type" value="employee" id="assign_emp"
                           <?= ($editRule['assign_type'] ?? 'employee') === 'employee' ? 'checked' : '' ?>
                           onchange="toggleAssignType()">
                    <label for="assign_emp">Specific Employee</label>

                    <input type="radio" name="assign_type" value="department" id="assign_dept"
                           <?= ($editRule['assign_type'] ?? '') === 'department' ? 'checked' : '' ?>
                           onchange="toggleAssignType()">
                    <label for="assign_dept">Department</label>
                </div>
                <div class="form-grid">
                    <div class="form-group" id="employeeField">
                        <select name="assign_employee_id">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($editRule['assign_employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                    <?= $emp['department'] ? '(' . htmlspecialchars($emp['department']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="departmentField" style="display:none;">
                        <select name="assign_department">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= ($editRule['assign_department'] ?? '') === $dept ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary"><?= $editRule ? 'Update Rule' : 'Create Rule' ?></button>
                <?php if ($editRule): ?>
                    <a href="auto_task_rules.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All (<?= $totalRules ?>)</a>
        <a href="?filter=event" class="<?= $filter === 'event' ? 'active' : '' ?>">Event Rules</a>
        <a href="?filter=recurring" class="<?= $filter === 'recurring' ? 'active' : '' ?>">Recurring Rules</a>
    </div>

    <!-- Rules List -->
    <?php if (empty($rules)): ?>
        <div style="text-align: center; padding: 40px; color: #9ca3af; background: white; border-radius: 10px; border: 1px dashed #d1d5db;">
            No auto-task rules configured yet. Create one above.
        </div>
    <?php else: ?>
        <?php foreach ($rules as $rule): ?>
        <div class="rule-card <?= $rule['is_active'] ? '' : 'inactive' ?>">
            <div class="rule-info">
                <h4>
                    <?= htmlspecialchars($rule['rule_name']) ?>
                    <span class="badge <?= $rule['rule_type'] === 'event' ? 'badge-event' : 'badge-recurring' ?>">
                        <?= ucfirst($rule['rule_type']) ?>
                    </span>
                    <span class="badge <?= $rule['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $rule['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <span class="badge badge-priority-<?= strtolower($rule['priority']) ?>">
                        <?= $rule['priority'] ?>
                    </span>
                </h4>
                <div class="meta">
                    <?php if ($rule['rule_type'] === 'event'): ?>
                        Trigger: <strong><?= htmlspecialchars($modules[$rule['trigger_module']] ?? $rule['trigger_module']) ?></strong>
                        &rarr; <strong><?= htmlspecialchars(ucfirst($rule['trigger_event'])) ?></strong>
                    <?php else: ?>
                        Schedule: <strong><?= ucfirst($rule['frequency']) ?></strong>
                        <?php if ($rule['frequency'] === 'weekly' && $rule['day_of_week'] !== null): ?>
                            on <?= $daysOfWeek[$rule['day_of_week']] ?? '' ?>
                        <?php elseif ($rule['frequency'] === 'monthly' && $rule['day_of_month']): ?>
                            on day <?= $rule['day_of_month'] ?>
                        <?php endif; ?>
                        at <?= $rule['recurring_time'] ? date('H:i', strtotime($rule['recurring_time'])) : '09:00' ?>
                        <?php if ($rule['last_run_at']): ?>
                            | Last ran: <?= date('d-M-Y H:i', strtotime($rule['last_run_at'])) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <br>
                    Task: <strong><?= htmlspecialchars($rule['task_name_template']) ?></strong>
                    <?php if ($rule['category_name']): ?>
                        | Category: <span style="color: <?= htmlspecialchars($rule['color_code']) ?>;"><?= htmlspecialchars($rule['category_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($rule['due_days']): ?>
                        | Due: <?= $rule['due_days'] ?> day<?= $rule['due_days'] > 1 ? 's' : '' ?>
                    <?php endif; ?>
                    <br>
                    Assign to:
                    <?php if ($rule['assign_type'] === 'employee' && $rule['employee_name']): ?>
                        <strong><?= htmlspecialchars($rule['employee_name']) ?></strong> (<?= htmlspecialchars($rule['employee_emp_id']) ?>)
                    <?php elseif ($rule['assign_type'] === 'department' && $rule['assign_department']): ?>
                        Department: <strong><?= htmlspecialchars($rule['assign_department']) ?></strong>
                    <?php else: ?>
                        <em>Unassigned</em>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rule-actions">
                <a href="?edit=<?= $rule['id'] ?>#ruleForm" class="btn btn-secondary btn-sm">Edit</a>
                <form method="post">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background: <?= $rule['is_active'] ? '#ef4444' : '#10b981' ?>; color: white;">
                        <?= $rule['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('Delete this rule permanently?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background: #6c757d; color: white;">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Info Box -->
    <div style="background: #e0e7ff; border: 1px solid #6366f1; border-radius: 8px; padding: 15px; margin-top: 25px;">
        <strong style="color: #4338ca;">How it works:</strong>
        <ul style="color: #3730a3; margin: 10px 0 0 20px; padding: 0; line-height: 1.8;">
            <li><strong>Event rules</strong> fire automatically when the specified action happens in a module (e.g., when a Sales Order is created).</li>
            <li><strong>Recurring rules</strong> create tasks on a schedule. Click "Run Recurring Now" to trigger them manually, or set up a cron job pointing to <code>/cron/run_recurring_tasks.php</code>.</li>
            <li><strong>Department assignment</strong> picks a random active employee from that department for each task.</li>
            <li><strong>Placeholders</strong> in task name/description: <code>{reference}</code>, <code>{date}</code>, <code>{module}</code>, <code>{event}</code>, <code>{month}</code>, <code>{week}</code></li>
        </ul>
    </div>
</div>

<script>
function toggleRuleType() {
    const isEvent = document.getElementById('type_event').checked;
    document.getElementById('eventFields').classList.toggle('visible', isEvent);
    document.getElementById('recurringFields').classList.toggle('visible', !isEvent);
}

function toggleFrequencyFields() {
    const freq = document.getElementById('frequencySelect').value;
    document.getElementById('dayOfWeekGroup').style.display = freq === 'weekly' ? 'block' : 'none';
    document.getElementById('dayOfMonthGroup').style.display = freq === 'monthly' ? 'block' : 'none';
}

function toggleAssignType() {
    const isEmployee = document.getElementById('assign_emp').checked;
    document.getElementById('employeeField').style.display = isEmployee ? 'block' : 'none';
    document.getElementById('departmentField').style.display = isEmployee ? 'none' : 'block';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRuleType();
    toggleFrequencyFields();
    toggleAssignType();
});
</script>

<?php include '../includes/footer.php'; ?>
