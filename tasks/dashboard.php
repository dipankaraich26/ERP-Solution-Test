<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('tasks');

// Get company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Safe count function
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Safe query function
function safeQuery($pdo, $query) {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Task Stats
$stats = [];

// Total tasks
$stats['total'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks");
$stats['not_started'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 'Not Started'");
$stats['in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'");
$stats['on_hold'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 'On Hold'");
$stats['completed'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 'Completed'");
$stats['cancelled'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE status = 'Cancelled'");

// Overdue tasks
$stats['overdue'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('Completed', 'Cancelled')");

// Due today
$stats['due_today'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date = CURDATE() AND status NOT IN ('Completed', 'Cancelled')");

// Due this week
$stats['due_week'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('Completed', 'Cancelled')");

// Priority breakdown
$stats['critical'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE priority = 'Critical' AND status NOT IN ('Completed', 'Cancelled')");
$stats['high'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE priority = 'High' AND status NOT IN ('Completed', 'Cancelled')");

// Recent tasks
$recent_tasks = safeQuery($pdo, "
    SELECT t.*, tc.category_name, tc.color_code,
           CONCAT(e.first_name, ' ', e.last_name) as assigned_name
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    ORDER BY t.created_at DESC
    LIMIT 10
");

// Overdue tasks list
$overdue_tasks = safeQuery($pdo, "
    SELECT t.*, tc.category_name, tc.color_code,
           CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
           DATEDIFF(CURDATE(), t.due_date) as days_overdue
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE t.due_date < CURDATE() AND t.status NOT IN ('Completed', 'Cancelled')
    ORDER BY t.due_date ASC
    LIMIT 10
");

// Tasks by category
$tasks_by_category = safeQuery($pdo, "
    SELECT tc.category_name, tc.color_code, COUNT(t.id) as count
    FROM task_categories tc
    LEFT JOIN tasks t ON tc.id = t.category_id AND t.status NOT IN ('Completed', 'Cancelled')
    WHERE tc.is_active = 1
    GROUP BY tc.id
    ORDER BY count DESC
    LIMIT 8
");

// My tasks (assigned to current user)
$my_tasks = safeQuery($pdo, "
    SELECT t.*, tc.category_name, tc.color_code
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    WHERE t.assigned_to = " . ($_SESSION['employee_id'] ?? 0) . "
      AND t.status NOT IN ('Completed', 'Cancelled')
    ORDER BY
        CASE t.priority
            WHEN 'Critical' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            ELSE 4
        END,
        t.due_date ASC
    LIMIT 10
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Management - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .module-header img {
            max-height: 60px;
            max-width: 150px;
            background: white;
            padding: 8px;
            border-radius: 8px;
            object-fit: contain;
        }
        .module-header h1 { margin: 0; font-size: 1.8em; }
        .module-header p { margin: 5px 0 0; opacity: 0.9; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.purple { border-left-color: #9b59b6; }

        .stat-icon { font-size: 2em; margin-bottom: 10px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .quick-action-btn .action-icon { font-size: 1.6em; margin-bottom: 8px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-not-started { background: #e0e0e0; color: #616161; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }
        .status-on-hold { background: #fff3e0; color: #ef6c00; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }

        .priority-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .priority-critical { background: #c62828; color: white; }
        .priority-high { background: #ef6c00; color: white; }
        .priority-medium { background: #fbc02d; color: #333; }
        .priority-low { background: #90a4ae; color: white; }

        .category-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            color: white;
        }

        .overdue-badge {
            background: #ffebee;
            color: #c62828;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }

        .section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alerts-panel.danger {
            background: #ffebee;
            border-left-color: #e74c3c;
        }
        .alerts-panel h4 { margin: 0 0 10px 0; color: #856404; }
        .alerts-panel.danger h4 { color: #c62828; }
        .alerts-panel ul { list-style: none; padding: 0; margin: 0; }
        .alerts-panel li { padding: 5px 0; }
        .alerts-panel a { color: #004085; font-weight: 600; }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.3s;
        }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

<div class="content">
    <!-- Module Header -->
    <div class="module-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php
                $logo_path = $settings['logo_path'];
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1>Task Management</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($stats['overdue'] > 0 || $stats['critical'] > 0): ?>
    <div class="alerts-panel <?= $stats['overdue'] > 0 ? 'danger' : '' ?>">
        <h4><?= $stats['overdue'] > 0 ? '⚠️ Urgent Attention Required' : '📋 Task Alerts' ?></h4>
        <ul>
            <?php if ($stats['overdue'] > 0): ?>
            <li><a href="/tasks/index.php?filter=overdue"><?= $stats['overdue'] ?> overdue task<?= $stats['overdue'] > 1 ? 's' : '' ?></a> need immediate attention</li>
            <?php endif; ?>
            <?php if ($stats['critical'] > 0): ?>
            <li><a href="/tasks/index.php?priority=Critical"><?= $stats['critical'] ?> critical priority task<?= $stats['critical'] > 1 ? 's' : '' ?></a> pending</li>
            <?php endif; ?>
            <?php if ($stats['due_today'] > 0): ?>
            <li><a href="/tasks/index.php?filter=today"><?= $stats['due_today'] ?> task<?= $stats['due_today'] > 1 ? 's' : '' ?></a> due today</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/tasks/add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New Task
        </a>
        <a href="/tasks/index.php?filter=my" class="quick-action-btn">
            <div class="action-icon">👤</div>
            My Tasks
        </a>
        <a href="/tasks/index.php?filter=today" class="quick-action-btn">
            <div class="action-icon">📅</div>
            Due Today
        </a>
        <a href="/tasks/index.php?filter=overdue" class="quick-action-btn">
            <div class="action-icon">⚠️</div>
            Overdue
        </a>
        <a href="/tasks/categories.php" class="quick-action-btn">
            <div class="action-icon">🏷️</div>
            Categories
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Task Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= $stats['not_started'] ?></div>
            <div class="stat-label">Not Started</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">🔄</div>
            <div class="stat-value"><?= $stats['in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏸️</div>
            <div class="stat-value"><?= $stats['on_hold'] ?></div>
            <div class="stat-label">On Hold</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['completed'] ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <?php if ($stats['overdue'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?= $stats['overdue'] ?></div>
            <div class="stat-label">Overdue</div>
        </div>
        <?php endif; ?>
        <div class="stat-card warning">
            <div class="stat-icon">📆</div>
            <div class="stat-value"><?= $stats['due_week'] ?></div>
            <div class="stat-label">Due This Week</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Overdue Tasks -->
        <?php if (!empty($overdue_tasks)): ?>
        <div class="dashboard-panel">
            <h3>🚨 Overdue Tasks</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Days</th>
                        <th>Priority</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue_tasks as $task): ?>
                    <tr>
                        <td>
                            <a href="/tasks/view.php?id=<?= $task['id'] ?>"><?= htmlspecialchars($task['task_name']) ?></a>
                            <br><small style="color: #999;"><?= htmlspecialchars($task['task_no']) ?></small>
                        </td>
                        <td><span class="overdue-badge"><?= $task['days_overdue'] ?> day<?= $task['days_overdue'] > 1 ? 's' : '' ?></span></td>
                        <td><span class="priority-badge priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Tasks by Category -->
        <div class="dashboard-panel">
            <h3>🏷️ Tasks by Category</h3>
            <?php if (empty($tasks_by_category)): ?>
                <p style="color: #7f8c8d;">No category data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Active Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks_by_category as $cat): ?>
                        <tr>
                            <td>
                                <span class="category-badge" style="background: <?= htmlspecialchars($cat['color_code']) ?>">
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </span>
                            </td>
                            <td><?= $cat['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Tasks -->
        <div class="dashboard-panel">
            <h3>📋 Recent Tasks</h3>
            <?php if (empty($recent_tasks)): ?>
                <p style="color: #7f8c8d;">No tasks found. <a href="/tasks/add.php">Create your first task</a></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tasks as $task): ?>
                        <tr>
                            <td>
                                <a href="/tasks/view.php?id=<?= $task['id'] ?>"><?= htmlspecialchars($task['task_name']) ?></a>
                                <?php if ($task['category_name']): ?>
                                <br><span class="category-badge" style="background: <?= htmlspecialchars($task['color_code']) ?>; font-size: 0.7em;">
                                    <?= htmlspecialchars($task['category_name']) ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $task['status'])) ?>"><?= $task['status'] ?></span></td>
                            <td>
                                <?php if ($task['due_date']): ?>
                                    <?= date('d M Y', strtotime($task['due_date'])) ?>
                                    <?php if ($task['due_date'] < date('Y-m-d') && !in_array($task['status'], ['Completed', 'Cancelled'])): ?>
                                        <span class="overdue-badge">Overdue</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- My Tasks -->
        <div class="dashboard-panel">
            <h3>👤 My Tasks</h3>
            <?php if (empty($my_tasks)): ?>
                <p style="color: #7f8c8d;">No tasks assigned to you.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Priority</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_tasks as $task): ?>
                        <tr>
                            <td>
                                <a href="/tasks/view.php?id=<?= $task['id'] ?>"><?= htmlspecialchars($task['task_name']) ?></a>
                            </td>
                            <td><span class="priority-badge priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?></span></td>
                            <td>
                                <?php if ($task['due_date']): ?>
                                    <?= date('d M', strtotime($task['due_date'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/tasks/index.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            All Tasks
        </a>
        <a href="/tasks/index.php?status=In Progress" class="quick-action-btn">
            <div class="action-icon">🔄</div>
            In Progress
        </a>
        <a href="/tasks/index.php?status=Completed" class="quick-action-btn">
            <div class="action-icon">✅</div>
            Completed
        </a>
        <a href="/tasks/categories.php" class="quick-action-btn">
            <div class="action-icon">🏷️</div>
            Categories
        </a>
    </div>
</div>

</body>
</html>
