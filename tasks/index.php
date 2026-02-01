<?php
include "../db.php";
include "../includes/dialog.php";

// Filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category = $_GET['category'] ?? '';
$assigned = $_GET['assigned'] ?? '';
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "t.status = ?";
    $params[] = $status;
}
if ($priority) {
    $where[] = "t.priority = ?";
    $params[] = $priority;
}
if ($category) {
    $where[] = "t.category_id = ?";
    $params[] = $category;
}
if ($assigned) {
    $where[] = "t.assigned_to = ?";
    $params[] = $assigned;
}
if ($search) {
    $where[] = "(t.task_no LIKE ? OR t.task_name LIKE ? OR t.task_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Special filters
if ($filter === 'overdue') {
    $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'today') {
    $where[] = "t.due_date = CURDATE() AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'week') {
    $where[] = "t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND t.status NOT IN ('Completed', 'Cancelled')";
} elseif ($filter === 'my') {
    $where[] = "t.assigned_to = ?";
    $params[] = $_SESSION['employee_id'] ?? 0;
}

$whereClause = implode(" AND ", $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Get tasks
$stmt = $pdo->prepare("
    SELECT t.*,
           tc.category_name, tc.color_code,
           CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
           CONCAT(e2.first_name, ' ', e2.last_name) as assigned_by_name,
           c.customer_name,
           p.project_name
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    LEFT JOIN employees e2 ON t.assigned_by = e2.id
    LEFT JOIN customers c ON t.customer_id = c.customer_id
    LEFT JOIN projects p ON t.project_id = p.id
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
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT id, category_name FROM task_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Get employees for filter
$employees = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'not_started' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Not Started'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'")->fetchColumn(),
    'overdue' => $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('Completed', 'Cancelled')")->fetchColumn(),
];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Tasks - Task Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; }

        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 100px;
        }
        .stat-box .number {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-box .label { color: #7f8c8d; font-size: 0.85em; }
        .stat-box.progress .number { color: #3498db; }
        .stat-box.success .number { color: #27ae60; }
        .stat-box.danger .number { color: #e74c3c; }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .filters input[type="text"] { width: 200px; }
        .filters select { min-width: 130px; }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .task-table th, .task-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .task-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .task-table tr:hover { background: #fafafa; }
        .task-table tr:last-child td { border-bottom: none; }

        .task-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .task-name a { color: inherit; text-decoration: none; }
        .task-name a:hover { color: #667eea; }
        .task-id { color: #999; font-size: 0.85em; }

        .status-badge {
            display: inline-block;
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
            display: inline-block;
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
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            color: white;
        }

        .due-date {
            font-size: 0.9em;
        }
        .due-date.overdue {
            color: #c62828;
            font-weight: bold;
        }
        .due-date.today {
            color: #ef6c00;
            font-weight: bold;
        }
        .due-date.upcoming {
            color: #2e7d32;
        }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 6px;
            width: 60px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }
        .progress-bar-fill {
            height: 100%;
            background: #667eea;
            border-radius: 10px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            color: #999;
        }
        .empty-state h3 { color: #666; margin-bottom: 10px; }

        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .quick-filter {
            padding: 6px 15px;
            border-radius: 20px;
            background: white;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #666;
            font-size: 0.85em;
            transition: all 0.2s;
        }
        .quick-filter:hover, .quick-filter.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .quick-filter.danger { border-color: #e74c3c; color: #e74c3c; }
        .quick-filter.danger:hover, .quick-filter.danger.active {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Task Management</h1>
            <p style="color: #666; margin: 5px 0 0;">
                <?php
                if ($filter === 'overdue') echo 'Overdue Tasks';
                elseif ($filter === 'today') echo 'Due Today';
                elseif ($filter === 'week') echo 'Due This Week';
                elseif ($filter === 'my') echo 'My Tasks';
                else echo 'All Tasks';
                ?>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="add.php" class="btn btn-success">+ New Task</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-box">
            <div class="number"><?= $stats['not_started'] ?></div>
            <div class="label">Not Started</div>
        </div>
        <div class="stat-box progress">
            <div class="number"><?= $stats['in_progress'] ?></div>
            <div class="label">In Progress</div>
        </div>
        <div class="stat-box success">
            <div class="number"><?= $stats['completed'] ?></div>
            <div class="label">Completed</div>
        </div>
        <?php if ($stats['overdue'] > 0): ?>
        <div class="stat-box danger">
            <div class="number"><?= $stats['overdue'] ?></div>
            <div class="label">Overdue</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="index.php" class="quick-filter <?= empty($filter) && empty($status) ? 'active' : '' ?>">All</a>
        <a href="index.php?filter=my" class="quick-filter <?= $filter === 'my' ? 'active' : '' ?>">My Tasks</a>
        <a href="index.php?filter=today" class="quick-filter <?= $filter === 'today' ? 'active' : '' ?>">Due Today</a>
        <a href="index.php?filter=week" class="quick-filter <?= $filter === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="index.php?status=In Progress" class="quick-filter <?= $status === 'In Progress' ? 'active' : '' ?>">In Progress</a>
        <a href="index.php?status=Completed" class="quick-filter <?= $status === 'Completed' ? 'active' : '' ?>">Completed</a>
        <?php if ($stats['overdue'] > 0): ?>
        <a href="index.php?filter=overdue" class="quick-filter danger <?= $filter === 'overdue' ? 'active' : '' ?>">Overdue (<?= $stats['overdue'] ?>)</a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="get" class="filters">
        <input type="text" name="search" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>">

        <select name="status">
            <option value="">All Status</option>
            <option value="Not Started" <?= $status === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
            <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="On Hold" <?= $status === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <select name="priority">
            <option value="">All Priority</option>
            <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
            <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High</option>
            <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low</option>
        </select>

        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['category_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="assigned">
            <option value="">All Assignees</option>
            <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= $assigned == $emp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="index.php" class="btn btn-secondary">Reset</a>
    </form>

    <?php if (empty($tasks)): ?>
    <div class="empty-state">
        <h3>No tasks found</h3>
        <p>Try adjusting your filters or <a href="add.php">create a new task</a></p>
    </div>
    <?php else: ?>
    <table class="task-table">
        <thead>
            <tr>
                <th>Task</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Assigned To</th>
                <th>Due Date</th>
                <th>Progress</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
            <?php
                $isOverdue = $task['due_date'] && $task['due_date'] < date('Y-m-d') && !in_array($task['status'], ['Completed', 'Cancelled']);
                $isToday = $task['due_date'] === date('Y-m-d');
            ?>
            <tr>
                <td>
                    <div class="task-name">
                        <a href="view.php?id=<?= $task['id'] ?>"><?= htmlspecialchars($task['task_name']) ?></a>
                    </div>
                    <div class="task-id"><?= htmlspecialchars($task['task_no']) ?></div>
                </td>
                <td>
                    <?php if ($task['category_name']): ?>
                    <span class="category-badge" style="background: <?= htmlspecialchars($task['color_code'] ?: '#95a5a6') ?>">
                        <?= htmlspecialchars($task['category_name']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="priority-badge priority-<?= strtolower($task['priority']) ?>">
                        <?= $task['priority'] ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $task['status'])) ?>">
                        <?= $task['status'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($task['assigned_name'] ?: '-') ?></td>
                <td>
                    <?php if ($task['due_date']): ?>
                    <span class="due-date <?= $isOverdue ? 'overdue' : ($isToday ? 'today' : 'upcoming') ?>">
                        <?= date('d M Y', strtotime($task['due_date'])) ?>
                        <?php if ($isOverdue): ?>
                            <br><small>Overdue</small>
                        <?php elseif ($isToday): ?>
                            <br><small>Today</small>
                        <?php endif; ?>
                    </span>
                    <?php else: ?>
                    <span style="color: #999;">No due date</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: <?= $task['progress_percent'] ?>%"></div>
                    </div>
                    <span style="font-size: 0.85em;"><?= $task['progress_percent'] ?>%</span>
                </td>
                <td>
                    <a href="view.php?id=<?= $task['id'] ?>" class="btn btn-sm">View</a>
                    <a href="edit.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        ?>

        <?php if ($page > 1): ?>
        <a href="?<?= $queryString ?>&page=1">First</a>
        <a href="?<?= $queryString ?>&page=<?= $page - 1 ?>">Prev</a>
        <?php endif; ?>

        <span class="current">Page <?= $page ?> of <?= $total_pages ?></span>

        <?php if ($page < $total_pages): ?>
        <a href="?<?= $queryString ?>&page=<?= $page + 1 ?>">Next</a>
        <a href="?<?= $queryString ?>&page=<?= $total_pages ?>">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="text-align: center; color: #999; margin-top: 10px;">
        Showing <?= count($tasks) ?> of <?= $total ?> tasks
    </p>
    <?php endif; ?>
</div>

</body>
</html>
