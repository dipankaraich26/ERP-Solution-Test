<?php
/**
 * Auto-create a task from other modules (Work Order, Installation, PI release)
 *
 * @param PDO $pdo Database connection
 * @param array $params Task parameters:
 *   - task_name (required)
 *   - task_description
 *   - priority (default: 'Medium')
 *   - assigned_to (employee id)
 *   - start_date (default: today)
 *   - due_date
 *   - related_module (e.g. 'Work Order', 'Installation', 'Proforma Invoice')
 *   - related_id
 *   - related_reference (e.g. WO-1, INS-0001, PI/1/25/26)
 *   - customer_id
 *   - created_by
 * @return int|false Created task ID or false on failure
 */
function createAutoTask($pdo, $params) {
    try {
        // Generate next task number
        $max = $pdo->query("SELECT MAX(CAST(SUBSTRING(task_no, 6) AS UNSIGNED)) FROM tasks WHERE task_no LIKE 'TASK-%'")->fetchColumn();
        $next = $max ? ((int)$max + 1) : 1;
        $task_no = 'TASK-' . str_pad($next, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                task_no, task_name, task_description, priority, status,
                assigned_to, start_date, due_date,
                related_module, related_id, related_reference,
                customer_id, created_by, created_at
            ) VALUES (
                ?, ?, ?, ?, 'Not Started',
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $task_no,
            $params['task_name'],
            $params['task_description'] ?? null,
            $params['priority'] ?? 'Medium',
            $params['assigned_to'] ?? null,
            $params['start_date'] ?? date('Y-m-d'),
            $params['due_date'] ?? null,
            $params['related_module'] ?? null,
            $params['related_id'] ?? null,
            $params['related_reference'] ?? null,
            $params['customer_id'] ?? null,
            $params['created_by'] ?? null
        ]);

        return $pdo->lastInsertId();
    } catch (Exception $e) {
        // Don't break the main flow if task creation fails
        error_log("Auto-task creation failed: " . $e->getMessage());
        return false;
    }
}
