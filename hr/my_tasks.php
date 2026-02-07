<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

include "../db.php";

if (!isset($_SESSION['emp_attendance_id'])) {
    header("Location: attendance_login.php");
    exit;
}

$empId = $_SESSION['emp_attendance_id'];
$empName = $_SESSION['emp_attendance_name'];
$empDept = $_SESSION['emp_attendance_dept'];
$empDesignation = $_SESSION['emp_attendance_designation'];
$empPhoto = $_SESSION['emp_attendance_photo'];
$empCode = $_SESSION['emp_attendance_emp_id'];

// Filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$filter = $_GET['filter'] ?? '';

// Build WHERE clause - always filter by this employee
$where = ["t.assigned_to = ?"];
$params = [$empId];

if ($status) {
    $where[] = "t.status = ?";
    $params[] = $status;
}
if ($priority) {
    $where[] = "t.priority = ?";
    $params[] = $priority;
}

if ($filter === 'overdue') {
    $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'today') {
    $where[] = "t.due_date = CURDATE() AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'week') {
    $where[] = "t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'active') {
    $where[] = "t.status IN ('Not Started', 'In Progress', 'On Hold')";
}

$whereClause = implode(" AND ", $where);

// Stats for this employee only
$statParams = [$empId];
$myStats = [
    'total' => 0, 'not_started' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0
];
try {
    $myStats['total'] = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $myStats['total']->execute($statParams);
    $myStats['total'] = $myStats['total']->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'Not Started'");
    $s->execute($statParams); $myStats['not_started'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'In Progress'");
    $s->execute($statParams); $myStats['in_progress'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'Completed'");
    $s->execute($statParams); $myStats['completed'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status NOT IN ('Completed', 'Cancelled')");
    $s->execute($statParams); $myStats['overdue'] = $s->fetchColumn();
} catch (PDOException $e) {}

// Get tasks
$tasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*,
               tc.category_name, tc.color_code,
               CONCAT(e2.first_name, ' ', e2.last_name) as assigned_by_name
        FROM tasks t
        LEFT JOIN task_categories tc ON t.category_id = tc.id
        LEFT JOIN employees e2 ON t.assigned_by = e2.id
        WHERE $whereClause
        ORDER BY
            CASE t.status
                WHEN 'In Progress' THEN 1
                WHEN 'Not Started' THEN 2
                WHEN 'On Hold' THEN 3
                WHEN 'Completed' THEN 4
                ELSE 5
            END,
            CASE t.priority
                WHEN 'Critical' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                ELSE 4
            END,
            t.due_date ASC
        LIMIT 50
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: attendance_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Tasks - <?= htmlspecialchars($empName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px;
        }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-photo {
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2em; border: 3px solid rgba(255,255,255,0.3);
            overflow: hidden;
        }
        .user-photo img { width: 100%; height: 100%; object-fit: cover; }
        .user-details h2 { font-size: 1.2em; margin-bottom: 2px; }
        .user-details p { opacity: 0.9; font-size: 0.85em; }
        .header-links { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .header-btn {
            background: rgba(255,255,255,0.2); color: white; border: none;
            padding: 10px 18px; border-radius: 8px; cursor: pointer;
            font-size: 0.9em; text-decoration: none; display: inline-block;
        }
        .header-btn:hover { background: rgba(255,255,255,0.3); }

        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .stat-card {
            background: white; padding: 18px; border-radius: 12px;
            text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .stat-card .number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-card .label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }
        .stat-card.progress { border-left: 4px solid #3498db; }
        .stat-card.progress .number { color: #3498db; }
        .stat-card.success { border-left: 4px solid #27ae60; }
        .stat-card.success .number { color: #27ae60; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .stat-card.danger .number { color: #e74c3c; }

        .quick-filters {
            display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .quick-filter {
            padding: 8px 18px; border-radius: 20px; background: white;
            border: 1px solid #ddd; text-decoration: none; color: #666;
            font-size: 0.9em; transition: all 0.2s;
        }
        .quick-filter:hover, .quick-filter.active {
            background: #667eea; color: white; border-color: #667eea;
        }
        .quick-filter.danger { border-color: #e74c3c; color: #e74c3c; }
        .quick-filter.danger:hover, .quick-filter.danger.active {
            background: #e74c3c; color: white;
        }

        .task-list { display: flex; flex-direction: column; gap: 12px; }
        .task-card {
            background: white; border-radius: 12px; padding: 18px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #95a5a6;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .task-card:hover {
            transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        .task-card.priority-Critical { border-left-color: #e74c3c; }
        .task-card.priority-High { border-left-color: #f39c12; }
        .task-card.priority-Medium { border-left-color: #3498db; }
        .task-card.priority-Low { border-left-color: #95a5a6; }

        .task-card-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 10px; flex-wrap: wrap; gap: 8px;
        }
        .task-name { font-weight: 600; font-size: 1.05em; color: #2c3e50; }
        .task-no { font-size: 0.8em; color: #999; margin-top: 2px; }
        .task-badges { display: flex; gap: 6px; flex-wrap: wrap; }

        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 0.75em; font-weight: 600;
        }
        .badge-status { }
        .status-not-started { background: #e0e0e0; color: #616161; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }
        .status-on-hold { background: #fff3e0; color: #ef6c00; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }

        .badge-priority { color: white; }
        .priority-critical { background: #c62828; }
        .priority-high { background: #ef6c00; }
        .priority-medium { background: #fbc02d; color: #333; }
        .priority-low { background: #90a4ae; }

        .badge-category { color: white; }

        .task-card-meta {
            display: flex; gap: 20px; font-size: 0.85em; color: #7f8c8d;
            flex-wrap: wrap; align-items: center;
        }
        .task-card-meta .overdue { color: #c62828; font-weight: 600; }
        .task-card-meta .today { color: #ef6c00; font-weight: 600; }

        .progress-bar {
            background: #e0e0e0; border-radius: 10px; height: 6px;
            width: 80px; display: inline-block; vertical-align: middle; margin-right: 5px;
        }
        .progress-bar-fill {
            height: 100%; background: #667eea; border-radius: 10px;
        }

        .task-description {
            font-size: 0.9em; color: #666; margin-top: 8px;
            line-height: 1.4;
        }

        .empty-state {
            background: white; border-radius: 12px; padding: 50px;
            text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .empty-state .icon { font-size: 3em; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; margin-bottom: 10px; }
        .empty-state p { color: #7f8c8d; }

        @media (max-width: 600px) {
            .portal-header { padding: 15px; }
            .user-details h2 { font-size: 1em; }
            .header-links { flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .task-card-header { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="portal-header">
    <div class="user-info">
        <div class="user-photo">
            <?php if ($empPhoto): ?>
                <img src="../<?= htmlspecialchars($empPhoto) ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($empName, 0, 2)) ?>
            <?php endif; ?>
        </div>
        <div class="user-details">
            <h2><?= htmlspecialchars($empName) ?></h2>
            <p><?= htmlspecialchars($empCode) ?> | <?= htmlspecialchars($empDesignation ?: $empDept) ?></p>
        </div>
    </div>
    <div class="header-links">
        <a href="attendance_portal.php" class="header-btn">Attendance</a>
        <a href="my_payslip.php" class="header-btn">Payslips</a>
        <a href="my_calendar.php" class="header-btn">Calendar</a>
        <a href="?logout=1" class="header-btn">Logout</a>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 20px; color: #2c3e50;">My Tasks</h2>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= $myStats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-card progress">
            <div class="number"><?= $myStats['in_progress'] ?></div>
            <div class="label">In Progress</div>
        </div>
        <div class="stat-card success">
            <div class="number"><?= $myStats['completed'] ?></div>
            <div class="label">Completed</div>
        </div>
        <?php if ($myStats['overdue'] > 0): ?>
        <div class="stat-card danger">
            <div class="number"><?= $myStats['overdue'] ?></div>
            <div class="label">Overdue</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="my_tasks.php" class="quick-filter <?= empty($filter) && empty($status) && empty($priority) ? 'active' : '' ?>">All</a>
        <a href="my_tasks.php?filter=active" class="quick-filter <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
        <a href="my_tasks.php?filter=today" class="quick-filter <?= $filter === 'today' ? 'active' : '' ?>">Due Today</a>
        <a href="my_tasks.php?filter=week" class="quick-filter <?= $filter === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="my_tasks.php?status=Completed" class="quick-filter <?= $status === 'Completed' ? 'active' : '' ?>">Completed</a>
        <?php if ($myStats['overdue'] > 0): ?>
        <a href="my_tasks.php?filter=overdue" class="quick-filter danger <?= $filter === 'overdue' ? 'active' : '' ?>">Overdue (<?= $myStats['overdue'] ?>)</a>
        <?php endif; ?>
    </div>

    <!-- Task List -->
    <?php if (empty($tasks)): ?>
    <div class="empty-state">
        <div class="icon">📋</div>
        <h3>No Tasks Found</h3>
        <p>You don't have any tasks matching this filter.</p>
    </div>
    <?php else: ?>
    <div class="task-list">
        <?php foreach ($tasks as $task):
            $isOverdue = $task['due_date'] && $task['due_date'] < date('Y-m-d') && !in_array($task['status'], ['Completed', 'Cancelled']);
            $isToday = $task['due_date'] === date('Y-m-d');
        ?>
        <div class="task-card priority-<?= $task['priority'] ?>">
            <div class="task-card-header">
                <div>
                    <div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
                    <div class="task-no"><?= htmlspecialchars($task['task_no']) ?></div>
                </div>
                <div class="task-badges">
                    <span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $task['status'])) ?>">
                        <?= $task['status'] ?>
                    </span>
                    <span class="badge badge-priority priority-<?= strtolower($task['priority']) ?>">
                        <?= $task['priority'] ?>
                    </span>
                    <?php if ($task['category_name']): ?>
                    <span class="badge badge-category" style="background: <?= htmlspecialchars($task['color_code'] ?: '#95a5a6') ?>">
                        <?= htmlspecialchars($task['category_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($task['task_description']): ?>
            <div class="task-description">
                <?= htmlspecialchars(substr($task['task_description'], 0, 150)) ?><?= strlen($task['task_description']) > 150 ? '...' : '' ?>
            </div>
            <?php endif; ?>

            <div class="task-card-meta">
                <?php if ($task['due_date']): ?>
                <span class="<?= $isOverdue ? 'overdue' : ($isToday ? 'today' : '') ?>">
                    Due: <?= date('d M Y', strtotime($task['due_date'])) ?>
                    <?= $isOverdue ? ' (Overdue)' : ($isToday ? ' (Today)' : '') ?>
                </span>
                <?php endif; ?>

                <?php if ($task['start_date']): ?>
                <span>Start: <?= date('d M Y', strtotime($task['start_date'])) ?></span>
                <?php endif; ?>

                <?php if ($task['estimated_hours']): ?>
                <span>Est: <?= number_format($task['estimated_hours'], 1) ?>h</span>
                <?php endif; ?>

                <?php if ($task['progress_percent'] > 0): ?>
                <span>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: <?= $task['progress_percent'] ?>%"></div>
                    </div>
                    <?= $task['progress_percent'] ?>%
                </span>
                <?php endif; ?>

                <?php if ($task['assigned_by_name']): ?>
                <span>By: <?= htmlspecialchars($task['assigned_by_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
