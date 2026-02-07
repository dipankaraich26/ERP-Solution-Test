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
/**
 * Backfill tasks for released WOs that don't have tasks yet
 */
function syncMissingWoTasks($pdo) {
    try {
        $missing = $pdo->query("
            SELECT w.id, w.wo_no, w.part_no, w.qty, w.assigned_to,
                   COALESCE(p.part_name, w.part_no) as part_name
            FROM work_orders w
            LEFT JOIN part_master p ON w.part_no = p.part_no
            WHERE w.status IN ('released', 'in_progress')
              AND w.assigned_to IS NOT NULL
              AND w.id NOT IN (
                  SELECT related_id FROM tasks
                  WHERE related_module = 'Work Order' AND related_id IS NOT NULL
              )
        ")->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($missing as $wo) {
            $result = createAutoTask($pdo, [
                'task_name' => "Work Order {$wo['wo_no']} - Production",
                'task_description' => "Work Order {$wo['wo_no']} has been released. Complete production for Part: {$wo['part_no']} - {$wo['part_name']}, Qty: {$wo['qty']}",
                'priority' => 'High',
                'assigned_to' => $wo['assigned_to'],
                'start_date' => date('Y-m-d'),
                'related_module' => 'Work Order',
                'related_id' => $wo['id'],
                'related_reference' => $wo['wo_no'],
            ]);
            if ($result) $count++;
        }
        return $count;
    } catch (Exception $e) {
        error_log("syncMissingWoTasks failed: " . $e->getMessage());
        return 0;
    }
}

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
