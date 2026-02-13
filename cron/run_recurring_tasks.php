<?php
/**
 * Cron endpoint for running recurring auto-task rules.
 *
 * Usage:
 * - Browser: http://localhost/cron/run_recurring_tasks.php
 * - Windows Task Scheduler: php C:\xampp\htdocs\cron\run_recurring_tasks.php
 * - Linux cron: 0 9 * * * php /var/www/html/cron/run_recurring_tasks.php
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auto_task_engine.php';

$result = runRecurringTasks($pdo);

// Output results
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "Recurring Tasks Run Complete\n";
    echo "Rules checked: {$result['total_checked']}\n";
    echo "Tasks created: {$result['tasks_created']}\n";
    foreach ($result['details'] as $d) {
        echo "  - {$d['rule']}: {$d['status']}" . (isset($d['reason']) ? " ({$d['reason']})" : '') . "\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'rules_checked' => $result['total_checked'],
        'tasks_created' => $result['tasks_created'],
        'details' => $result['details'],
    ], JSON_PRETTY_PRINT);
}
