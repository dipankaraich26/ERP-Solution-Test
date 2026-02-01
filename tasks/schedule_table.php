<?php
/**
 * Task Schedule Table
 * Tabular view of tasks with time duration and booking
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/sidebar.php";

// Filter parameters
$employee_id = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? '';
$showAllEmployees = isset($_GET['all']) || $employee_id === 0;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch employees
$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$where = ["(t.start_date BETWEEN ? AND ? OR t.due_date BETWEEN ? AND ?)"];
$params = [$startDate, $endDate, $startDate, $endDate];

if (!$showAllEmployees && $employee_id > 0) {
    $where[] = "t.assigned_to = ?";
    $params[] = $employee_id;
}

if ($status_filter) {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(" AND ", $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereClause");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Fetch tasks with pagination
$sql = "
    SELECT t.*,
           tc.category_name, tc.color_code as category_color,
           e.first_name, e.last_name, e.emp_id as employee_emp_id, e.department
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE $whereClause
    ORDER BY t.start_date, t.start_time, e.first_name
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total hours by employee
$hoursParams = [$startDate, $endDate, $startDate, $endDate];
$hoursSql = "
    SELECT t.assigned_to, e.first_name, e.last_name, e.department,
           COUNT(*) as task_count,
           SUM(COALESCE(t.estimated_hours, 0)) as total_estimated,
           SUM(COALESCE(t.actual_hours, 0)) as total_actual,
           SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
           SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_count
    FROM tasks t
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE (t.start_date BETWEEN ? AND ? OR t.due_date BETWEEN ? AND ?)
";
if (!$showAllEmployees && $employee_id > 0) {
    $hoursSql .= " AND t.assigned_to = ?";
    $hoursParams[] = $employee_id;
}
$hoursSql .= " GROUP BY t.assigned_to ORDER BY total_estimated DESC";

$hoursStmt = $pdo->prepare($hoursSql);
$hoursStmt->execute($hoursParams);
$hoursSummary = $hoursStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$grandTotalEstimated = array_sum(array_column($hoursSummary, 'total_estimated'));
$grandTotalActual = array_sum(array_column($hoursSummary, 'total_actual'));
$grandTotalTasks = array_sum(array_column($hoursSummary, 'task_count'));

// Selected employee info
$selectedEmployee = null;
if ($employee_id > 0) {
    foreach ($employees as $e) {
        if ($e['id'] == $employee_id) {
            $selectedEmployee = $e;
            break;
        }
    }
}

// Calculate working days in period (Mon-Fri)
$workingDays = 0;
$current = strtotime($startDate);
$end = strtotime($endDate);
while ($current <= $end) {
    $dayOfWeek = date('N', $current);
    if ($dayOfWeek < 6) $workingDays++;
    $current = strtotime('+1 day', $current);
}
$availableHours = $workingDays * 8;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Schedule Table</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .summary-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }
        .summary-card .label {
            color: #666;
            font-size: 0.9em;
        }
        .summary-card.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .summary-card.highlight .value {
            color: white;
        }
        .summary-card.highlight .label {
            color: rgba(255,255,255,0.8);
        }
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 0.85em;
            color: #666;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .hours-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .hours-table th {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        .hours-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .hours-table tr:hover {
            background: #f8f9fa;
        }
        .hours-table .utilization {
            width: 100px;
        }
        .utilization-bar {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .utilization-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }
        .utilization-fill.low { background: #28a745; }
        .utilization-fill.medium { background: #ffc107; }
        .utilization-fill.high { background: #fd7e14; }
        .utilization-fill.overload { background: #dc3545; }
        .utilization-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.75em;
            font-weight: bold;
            color: #333;
        }
        .task-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-table th {
            background: #3498db;
            color: white;
            padding: 12px 15px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        .task-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .task-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-badge.Not-Started { background: #e9ecef; color: #495057; }
        .status-badge.In-Progress { background: #cce5ff; color: #004085; }
        .status-badge.On-Hold { background: #fff3cd; color: #856404; }
        .status-badge.Completed { background: #d4edda; color: #155724; }
        .status-badge.Cancelled { background: #f8d7da; color: #721c24; }
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .priority-badge.Critical { background: #e74c3c; color: white; }
        .priority-badge.High { background: #f39c12; color: white; }
        .priority-badge.Medium { background: #3498db; color: white; }
        .priority-badge.Low { background: #95a5a6; color: white; }
        .duration-cell {
            white-space: nowrap;
        }
        .time-range {
            font-size: 0.85em;
            color: #666;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover { background: #f8f9fa; }
        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .export-btn {
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .export-btn:hover {
            background: #218838;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">Task Schedule Table</h1>
        <a href="calendar.php?employee=<?= $employee_id ?>" class="btn btn-secondary">Back to Calendar</a>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card highlight">
            <div class="value"><?= $grandTotalTasks ?></div>
            <div class="label">Total Tasks</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= number_format($grandTotalEstimated, 1) ?>h</div>
            <div class="label">Total Estimated Hours</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= number_format($grandTotalActual, 1) ?>h</div>
            <div class="label">Actual Hours Logged</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= $workingDays ?></div>
            <div class="label">Working Days</div>
        </div>
        <div class="summary-card">
            <div class="value"><?= $availableHours ?>h</div>
            <div class="label">Available Hours (8h/day)</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="get" class="filter-bar">
        <div class="filter-group">
            <label>Employee</label>
            <select name="employee">
                <option value="0">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Start Date</label>
            <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="filter-group">
            <label>End Date</label>
            <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="Not Started" <?= $status_filter === 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                <option value="In Progress" <?= $status_filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="On Hold" <?= $status_filter === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filter</button>
        <button type="button" class="export-btn" onclick="exportTable()">Export Excel</button>
    </form>

    <!-- Hours Summary by Employee -->
    <?php if (!empty($hoursSummary) && count($hoursSummary) > 1): ?>
    <div class="section-header">
        <h2 style="margin: 0;">Time Allocation by Employee</h2>
    </div>
    <table class="hours-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Tasks</th>
                <th>Estimated Hours</th>
                <th>Actual Hours</th>
                <th>Completed</th>
                <th style="width: 150px;">Utilization</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hoursSummary as $hs):
                $utilization = $availableHours > 0 ? ($hs['total_estimated'] / $availableHours) * 100 : 0;
                $utilizationClass = $utilization <= 70 ? 'low' : ($utilization <= 90 ? 'medium' : ($utilization <= 100 ? 'high' : 'overload'));
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($hs['first_name'] . ' ' . $hs['last_name']) ?></strong>
                </td>
                <td><?= htmlspecialchars($hs['department'] ?: '-') ?></td>
                <td><?= $hs['task_count'] ?></td>
                <td><strong><?= number_format($hs['total_estimated'], 1) ?>h</strong></td>
                <td><?= number_format($hs['total_actual'], 1) ?>h</td>
                <td>
                    <?= $hs['completed_count'] ?>/<?= $hs['task_count'] ?>
                    <span style="color: #666;">(<?= $hs['task_count'] > 0 ? round($hs['completed_count'] / $hs['task_count'] * 100) : 0 ?>%)</span>
                </td>
                <td class="utilization">
                    <div class="utilization-bar">
                        <div class="utilization-fill <?= $utilizationClass ?>" style="width: <?= min(100, $utilization) ?>%;"></div>
                        <span class="utilization-text"><?= round($utilization) ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Task Table -->
    <div class="section-header">
        <h2 style="margin: 0;">Task Details (<?= $totalCount ?> tasks)</h2>
    </div>

    <?php if (empty($tasks)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 8px;">
        <h3>No Tasks Found</h3>
        <p>Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x: auto;">
    <table class="task-table" id="taskTable">
        <thead>
            <tr>
                <th>Task #</th>
                <th>Task Name</th>
                <th>Assigned To</th>
                <th>Start Date</th>
                <th>Due Date</th>
                <th>Duration</th>
                <th>Time</th>
                <th>Est. Hours</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $t):
                $statusClass = str_replace(' ', '-', $t['status']);
                // Calculate duration in days
                $durationDays = null;
                if ($t['start_date'] && $t['due_date']) {
                    $start = new DateTime($t['start_date']);
                    $end = new DateTime($t['due_date']);
                    $durationDays = $end->diff($start)->days + 1;
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($t['task_no']) ?></strong></td>
                <td>
                    <a href="view.php?id=<?= $t['id'] ?>" style="color: #2c3e50;">
                        <?= htmlspecialchars($t['task_name']) ?>
                    </a>
                    <?php if ($t['category_name']): ?>
                    <div style="font-size: 0.8em; color: <?= $t['category_color'] ?: '#666' ?>;">
                        <?= htmlspecialchars($t['category_name']) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($t['first_name']): ?>
                    <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                    <div style="font-size: 0.8em; color: #666;"><?= htmlspecialchars($t['department'] ?: '') ?></div>
                    <?php else: ?>
                    <span style="color: #999;">Unassigned</span>
                    <?php endif; ?>
                </td>
                <td><?= $t['start_date'] ? date('d M Y', strtotime($t['start_date'])) : '-' ?></td>
                <td>
                    <?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '-' ?>
                    <?php if ($t['due_date'] && $t['status'] !== 'Completed' && strtotime($t['due_date']) < time()): ?>
                    <div style="color: #dc3545; font-size: 0.8em;">Overdue!</div>
                    <?php endif; ?>
                </td>
                <td class="duration-cell">
                    <?php if ($durationDays !== null): ?>
                    <strong><?= $durationDays ?></strong> day<?= $durationDays !== 1 ? 's' : '' ?>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td class="duration-cell">
                    <?php if ($t['start_time'] || $t['end_time']): ?>
                    <div class="time-range">
                        <?= $t['start_time'] ? date('g:i A', strtotime($t['start_time'])) : '' ?>
                        <?= ($t['start_time'] && $t['end_time']) ? ' - ' : '' ?>
                        <?= $t['end_time'] ? date('g:i A', strtotime($t['end_time'])) : '' ?>
                    </div>
                    <?php else: ?>
                    <span style="color: #999;">All day</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($t['estimated_hours']): ?>
                    <strong><?= number_format($t['estimated_hours'], 1) ?>h</strong>
                    <?php if ($t['actual_hours']): ?>
                    <div style="font-size: 0.8em; color: #666;">
                        Actual: <?= number_format($t['actual_hours'], 1) ?>h
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td><span class="status-badge <?= $statusClass ?>"><?= $t['status'] ?></span></td>
                <td><span class="priority-badge <?= $t['priority'] ?>"><?= $t['priority'] ?></span></td>
                <td>
                    <a href="view.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">View</a>
                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">First</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function exportTable() {
    const table = document.getElementById('taskTable');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);

    // Set column widths
    ws['!cols'] = [
        { wch: 12 }, // Task #
        { wch: 35 }, // Task Name
        { wch: 20 }, // Assigned To
        { wch: 12 }, // Start Date
        { wch: 12 }, // Due Date
        { wch: 10 }, // Duration
        { wch: 15 }, // Time
        { wch: 10 }, // Est Hours
        { wch: 12 }, // Status
        { wch: 10 }, // Priority
    ];

    XLSX.utils.book_append_sheet(wb, ws, 'Task Schedule');
    XLSX.writeFile(wb, 'task_schedule_<?= date('Y-m-d') ?>.xlsx');
}
</script>

</body>
</html>
