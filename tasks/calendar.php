<?php
/**
 * Task Calendar
 * Visual calendar showing tasks by day with employee views
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/sidebar.php";

// Get filter parameters
$view = $_GET['view'] ?? 'month';  // month, week, day
$employee_id = isset($_GET['employee']) ? intval($_GET['employee']) : 0;  // 0 = all employees
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get current date info
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$day = isset($_GET['day']) ? intval($_GET['day']) : date('d');

$currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

// Navigation dates
if ($view === 'month') {
    $prevDate = date('Y-m-d', strtotime("$year-$month-01 -1 month"));
    $nextDate = date('Y-m-d', strtotime("$year-$month-01 +1 month"));
    $startDate = date('Y-m-01', strtotime($currentDate));
    $endDate = date('Y-m-t', strtotime($currentDate));
    $headerTitle = date('F Y', strtotime($currentDate));
} elseif ($view === 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
    $endDate = date('Y-m-d', strtotime('sunday this week', strtotime($currentDate)));
    $prevDate = date('Y-m-d', strtotime($startDate . ' -1 week'));
    $nextDate = date('Y-m-d', strtotime($startDate . ' +1 week'));
    $headerTitle = date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate));
} else {
    $startDate = $currentDate;
    $endDate = $currentDate;
    $prevDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    $headerTitle = date('l, F d, Y', strtotime($currentDate));
}

// Fetch employees
$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories
$categories = $pdo->query("
    SELECT * FROM task_categories WHERE is_active = 1 ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

// Build task query
$where = ["(t.start_date BETWEEN ? AND ? OR t.due_date BETWEEN ? AND ?)"];
$params = [$startDate, $endDate, $startDate, $endDate];

if ($employee_id > 0) {
    $where[] = "t.assigned_to = ?";
    $params[] = $employee_id;
}

if ($category_id > 0) {
    $where[] = "t.category_id = ?";
    $params[] = $category_id;
}

$whereClause = implode(" AND ", $where);

$taskStmt = $pdo->prepare("
    SELECT t.*,
           tc.category_name, tc.color_code as category_color,
           e.first_name, e.last_name, e.emp_id as employee_emp_id
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE $whereClause AND t.status != 'Cancelled'
    ORDER BY t.start_date, t.start_time, t.priority DESC
");
$taskStmt->execute($params);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

// Group tasks by date
$tasksByDate = [];
foreach ($tasks as $task) {
    $date = $task['start_date'] ?: $task['due_date'];
    if (!isset($tasksByDate[$date])) {
        $tasksByDate[$date] = [];
    }
    $tasksByDate[$date][] = $task;
}

// Get time summary by employee for current view
$timeSummaryStmt = $pdo->prepare("
    SELECT t.assigned_to, e.first_name, e.last_name,
           COUNT(*) as task_count,
           SUM(t.estimated_hours) as total_estimated,
           SUM(t.actual_hours) as total_actual
    FROM tasks t
    LEFT JOIN employees e ON t.assigned_to = e.id
    WHERE (t.start_date BETWEEN ? AND ? OR t.due_date BETWEEN ? AND ?)
      AND t.status NOT IN ('Cancelled', 'Completed')
    GROUP BY t.assigned_to
    ORDER BY total_estimated DESC
");
$timeSummaryStmt->execute([$startDate, $endDate, $startDate, $endDate]);
$timeSummary = $timeSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

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

// Build calendar days array for month view
$calendarDays = [];
if ($view === 'month') {
    $firstDayOfMonth = date('N', strtotime("$year-$month-01")); // 1=Mon, 7=Sun
    $daysInMonth = date('t', strtotime("$year-$month-01"));

    // Previous month days
    for ($i = 1; $i < $firstDayOfMonth; $i++) {
        $d = date('Y-m-d', strtotime("$year-$month-01 -" . ($firstDayOfMonth - $i) . " days"));
        $calendarDays[] = ['date' => $d, 'current_month' => false];
    }

    // Current month days
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $d = sprintf('%04d-%02d-%02d', $year, $month, $i);
        $calendarDays[] = ['date' => $d, 'current_month' => true];
    }

    // Next month days to complete the grid
    $remaining = 7 - (count($calendarDays) % 7);
    if ($remaining < 7) {
        for ($i = 1; $i <= $remaining; $i++) {
            $d = date('Y-m-d', strtotime("$year-$month-$daysInMonth +$i days"));
            $calendarDays[] = ['date' => $d, 'current_month' => false];
        }
    }
}

// Week view days
$weekDays = [];
if ($view === 'week') {
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime($startDate . " +$i days"));
        $weekDays[] = $d;
    }
}

// Time slots for day/week view (8 AM to 8 PM)
$timeSlots = [];
for ($h = 8; $h <= 20; $h++) {
    $timeSlots[] = sprintf('%02d:00', $h);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Calendar</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .calendar-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .calendar-nav a {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .calendar-nav a:hover {
            background: #f8f9fa;
        }
        .view-tabs {
            display: flex;
            gap: 5px;
        }
        .view-tab {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .view-tab.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .filter-bar {
            background: white;
            padding: 15px;
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
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 180px;
        }
        .calendar-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }
        .calendar-main {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .calendar-sidebar {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        /* Month View */
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        .month-header {
            background: #3498db;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 500;
        }
        .month-day {
            min-height: 100px;
            border: 1px solid #eee;
            padding: 5px;
            background: white;
        }
        .month-day.other-month {
            background: #f8f9fa;
        }
        .month-day.today {
            background: #e3f2fd;
        }
        .month-day.selected {
            border: 2px solid #3498db;
        }
        .day-number {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .other-month .day-number {
            color: #aaa;
        }
        .day-tasks {
            font-size: 0.8em;
        }
        .day-task {
            padding: 2px 5px;
            margin-bottom: 2px;
            border-radius: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        .day-task:hover {
            opacity: 0.8;
        }
        .more-tasks {
            font-size: 0.75em;
            color: #666;
            cursor: pointer;
        }
        /* Week View */
        .week-grid {
            display: grid;
            grid-template-columns: 60px repeat(7, 1fr);
        }
        .week-header {
            background: #3498db;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 500;
        }
        .week-header.today {
            background: #2980b9;
        }
        .time-label {
            background: #f8f9fa;
            padding: 5px;
            font-size: 0.8em;
            text-align: right;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        .time-slot {
            min-height: 50px;
            border: 1px solid #eee;
            padding: 2px;
            position: relative;
        }
        .time-slot.today {
            background: #f0f7ff;
        }
        /* Day View */
        .day-grid {
            display: grid;
            grid-template-columns: 80px 1fr;
        }
        .day-time-label {
            background: #f8f9fa;
            padding: 15px 10px;
            font-size: 0.9em;
            text-align: right;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        .day-time-slot {
            min-height: 60px;
            border-bottom: 1px solid #eee;
            padding: 5px;
            position: relative;
        }
        .day-time-slot:hover {
            background: #f8f9fa;
        }
        /* Task Cards */
        .task-card {
            padding: 5px 8px;
            border-radius: 4px;
            margin-bottom: 3px;
            font-size: 0.85em;
            cursor: pointer;
            border-left: 3px solid;
        }
        .task-card:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .task-card .task-time {
            font-size: 0.8em;
            color: #666;
        }
        .task-card .task-assignee {
            font-size: 0.75em;
            color: #888;
        }
        /* Priority colors */
        .priority-Critical { border-left-color: #e74c3c !important; }
        .priority-High { border-left-color: #f39c12 !important; }
        .priority-Medium { border-left-color: #3498db !important; }
        .priority-Low { border-left-color: #95a5a6 !important; }
        /* Sidebar */
        .sidebar-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar-card h4 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .time-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .time-summary-item:last-child {
            border-bottom: none;
        }
        .time-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        .time-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 4px;
        }
        .quick-add-btn {
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .quick-add-btn:hover {
            background: #218838;
        }
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85em;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        /* Task popup */
        .task-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            z-index: 1000;
            min-width: 400px;
            max-width: 500px;
        }
        .task-popup.active {
            display: block;
        }
        .task-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .task-popup-overlay.active {
            display: block;
        }
        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .popup-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
        }
        @media (max-width: 1000px) {
            .calendar-container {
                grid-template-columns: 1fr;
            }
            .calendar-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="calendar-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <h1 style="margin: 0;">Task Calendar</h1>
            <div class="calendar-nav">
                <a href="?view=<?= $view ?>&year=<?= date('Y', strtotime($prevDate)) ?>&month=<?= date('m', strtotime($prevDate)) ?>&day=<?= date('d', strtotime($prevDate)) ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>">◀ Prev</a>
                <a href="?view=<?= $view ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>">Today</a>
                <a href="?view=<?= $view ?>&year=<?= date('Y', strtotime($nextDate)) ?>&month=<?= date('m', strtotime($nextDate)) ?>&day=<?= date('d', strtotime($nextDate)) ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>">Next ▶</a>
            </div>
            <span class="calendar-title"><?= $headerTitle ?></span>
        </div>
        <div class="view-tabs">
            <a href="?view=month&year=<?= $year ?>&month=<?= $month ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>" class="view-tab <?= $view === 'month' ? 'active' : '' ?>">Month</a>
            <a href="?view=week&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>" class="view-tab <?= $view === 'week' ? 'active' : '' ?>">Week</a>
            <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>" class="view-tab <?= $view === 'day' ? 'active' : '' ?>">Day</a>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
        <input type="hidden" name="view" value="<?= $view ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">
        <input type="hidden" name="day" value="<?= $day ?>">

        <div class="filter-group">
            <label>Employee</label>
            <select name="employee" onchange="this.form.submit()">
                <option value="0">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Category</label>
            <select name="category" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <a href="add.php" class="btn btn-success">+ New Task</a>
        <a href="schedule_table.php?employee=<?= $employee_id ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-secondary">Table View</a>
    </form>

    <div class="calendar-container">
        <div class="calendar-main">
            <?php if ($view === 'month'): ?>
            <!-- Month View -->
            <div class="month-grid">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
                <div class="month-header"><?= $d ?></div>
                <?php endforeach; ?>

                <?php foreach ($calendarDays as $calDay):
                    $isToday = $calDay['date'] === date('Y-m-d');
                    $dayTasks = $tasksByDate[$calDay['date']] ?? [];
                ?>
                <div class="month-day <?= $calDay['current_month'] ? '' : 'other-month' ?> <?= $isToday ? 'today' : '' ?>"
                     onclick="window.location='?view=day&year=<?= date('Y', strtotime($calDay['date'])) ?>&month=<?= date('m', strtotime($calDay['date'])) ?>&day=<?= date('d', strtotime($calDay['date'])) ?>&employee=<?= $employee_id ?>&category=<?= $category_id ?>'">
                    <div class="day-number"><?= date('j', strtotime($calDay['date'])) ?></div>
                    <div class="day-tasks">
                        <?php
                        $maxShow = 3;
                        foreach (array_slice($dayTasks, 0, $maxShow) as $t):
                            $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                        ?>
                        <div class="day-task" style="background: <?= $color ?>20; color: <?= $color ?>;"
                             onclick="event.stopPropagation(); showTaskPopup(<?= $t['id'] ?>)">
                            <?= htmlspecialchars(substr($t['task_name'], 0, 20)) ?><?= strlen($t['task_name']) > 20 ? '...' : '' ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($dayTasks) > $maxShow): ?>
                        <div class="more-tasks">+<?= count($dayTasks) - $maxShow ?> more</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($view === 'week'): ?>
            <!-- Week View -->
            <div class="week-grid">
                <div class="week-header"></div>
                <?php foreach ($weekDays as $wd):
                    $isToday = $wd === date('Y-m-d');
                ?>
                <div class="week-header <?= $isToday ? 'today' : '' ?>">
                    <?= date('D', strtotime($wd)) ?><br>
                    <strong><?= date('j', strtotime($wd)) ?></strong>
                </div>
                <?php endforeach; ?>

                <?php foreach ($timeSlots as $slot): ?>
                <div class="time-label"><?= date('g A', strtotime($slot)) ?></div>
                <?php foreach ($weekDays as $wd):
                    $isToday = $wd === date('Y-m-d');
                    $slotTasks = array_filter($tasksByDate[$wd] ?? [], function($t) use ($slot) {
                        if (!$t['start_time']) return false;
                        return substr($t['start_time'], 0, 2) == substr($slot, 0, 2);
                    });
                ?>
                <div class="time-slot <?= $isToday ? 'today' : '' ?>">
                    <?php foreach ($slotTasks as $t):
                        $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                    ?>
                    <div class="task-card priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                         onclick="showTaskPopup(<?= $t['id'] ?>)">
                        <strong><?= htmlspecialchars(substr($t['task_name'], 0, 15)) ?></strong>
                        <?php if ($t['first_name']): ?>
                        <div class="task-assignee"><?= htmlspecialchars($t['first_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Day View -->
            <div style="padding: 15px; border-bottom: 1px solid #eee;">
                <h3 style="margin: 0;"><?= date('l, F j, Y', strtotime($currentDate)) ?></h3>
                <?php if ($selectedEmployee): ?>
                <p style="margin: 5px 0 0 0; color: #666;">
                    <?= htmlspecialchars($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?>'s Schedule
                </p>
                <?php endif; ?>
            </div>
            <div class="day-grid">
                <?php foreach ($timeSlots as $slot):
                    $slotTasks = array_filter($tasksByDate[$currentDate] ?? [], function($t) use ($slot) {
                        if ($t['all_day'] || !$t['start_time']) return false;
                        return substr($t['start_time'], 0, 2) == substr($slot, 0, 2);
                    });
                ?>
                <div class="day-time-label"><?= date('g:i A', strtotime($slot)) ?></div>
                <div class="day-time-slot">
                    <?php foreach ($slotTasks as $t):
                        $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                    ?>
                    <div class="task-card priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                         onclick="showTaskPopup(<?= $t['id'] ?>)">
                        <div class="task-time">
                            <?= date('g:i A', strtotime($t['start_time'])) ?>
                            <?= $t['end_time'] ? ' - ' . date('g:i A', strtotime($t['end_time'])) : '' ?>
                        </div>
                        <strong><?= htmlspecialchars($t['task_name']) ?></strong>
                        <?php if ($t['first_name']): ?>
                        <div class="task-assignee"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- All Day Tasks -->
            <?php
            $allDayTasks = array_filter($tasksByDate[$currentDate] ?? [], function($t) {
                return $t['all_day'] || !$t['start_time'];
            });
            if (!empty($allDayTasks)):
            ?>
            <div style="padding: 15px; background: #f8f9fa; border-top: 1px solid #eee;">
                <strong style="color: #666;">All Day / No Time Set:</strong>
                <div style="margin-top: 10px;">
                    <?php foreach ($allDayTasks as $t):
                        $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                    ?>
                    <div class="task-card priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                         onclick="showTaskPopup(<?= $t['id'] ?>)">
                        <strong><?= htmlspecialchars($t['task_name']) ?></strong>
                        <?php if ($t['first_name']): ?>
                        <span class="task-assignee">— <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="calendar-sidebar">
            <!-- Time Summary -->
            <div class="sidebar-card">
                <h4>Time Booked (<?= date('M d', strtotime($startDate)) ?> - <?= date('M d', strtotime($endDate)) ?>)</h4>
                <?php if (empty($timeSummary)): ?>
                <p style="color: #666;">No tasks scheduled</p>
                <?php else: ?>
                    <?php foreach ($timeSummary as $ts): ?>
                    <div class="time-summary-item">
                        <div>
                            <strong><?= htmlspecialchars($ts['first_name'] . ' ' . ($ts['last_name'] ? $ts['last_name'][0] . '.' : '')) ?></strong>
                            <div style="font-size: 0.85em; color: #666;">
                                <?= $ts['task_count'] ?> task(s)
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <strong><?= number_format($ts['total_estimated'] ?? 0, 1) ?>h</strong>
                            <div style="font-size: 0.85em; color: #666;">estimated</div>
                        </div>
                    </div>
                    <?php
                    $maxHours = 40; // 8hrs * 5 days
                    $percent = min(100, (($ts['total_estimated'] ?? 0) / $maxHours) * 100);
                    ?>
                    <div class="time-bar">
                        <div class="time-bar-fill" style="width: <?= $percent ?>%;"></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Priority Legend -->
            <div class="sidebar-card">
                <h4>Priority Legend</h4>
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #e74c3c;"></div>
                        <span>Critical</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f39c12;"></div>
                        <span>High</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #3498db;"></div>
                        <span>Medium</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #95a5a6;"></div>
                        <span>Low</span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="sidebar-card">
                <h4>Period Summary</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="text-align: center; padding: 10px; background: #e3f2fd; border-radius: 4px;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #1565c0;">
                            <?= count($tasks) ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666;">Total Tasks</div>
                    </div>
                    <div style="text-align: center; padding: 10px; background: #fce4ec; border-radius: 4px;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #c2185b;">
                            <?= count(array_filter($tasks, fn($t) => $t['priority'] === 'Critical' || $t['priority'] === 'High')) ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666;">High Priority</div>
                    </div>
                </div>
            </div>

            <button class="quick-add-btn" onclick="window.location='add.php'">
                + Add New Task
            </button>
        </div>
    </div>
</div>

<!-- Task Popup -->
<div class="task-popup-overlay" id="taskPopupOverlay" onclick="closeTaskPopup()"></div>
<div class="task-popup" id="taskPopup">
    <div class="popup-header">
        <h3 id="popupTaskName" style="margin: 0;"></h3>
        <button class="popup-close" onclick="closeTaskPopup()">&times;</button>
    </div>
    <div id="popupTaskContent"></div>
    <div style="margin-top: 15px; display: flex; gap: 10px;">
        <a id="popupViewLink" href="#" class="btn btn-primary">View Details</a>
        <a id="popupEditLink" href="#" class="btn btn-secondary">Edit</a>
    </div>
</div>

<script>
// Task data for popup
const tasksData = <?= json_encode(array_map(function($t) {
    return [
        'id' => $t['id'],
        'task_no' => $t['task_no'],
        'task_name' => $t['task_name'],
        'task_description' => $t['task_description'],
        'priority' => $t['priority'],
        'status' => $t['status'],
        'start_date' => $t['start_date'],
        'due_date' => $t['due_date'],
        'start_time' => $t['start_time'],
        'end_time' => $t['end_time'],
        'estimated_hours' => $t['estimated_hours'],
        'assignee' => $t['first_name'] ? ($t['first_name'] . ' ' . $t['last_name']) : 'Unassigned',
        'category' => $t['category_name'] ?? ''
    ];
}, $tasks), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function showTaskPopup(taskId) {
    const task = tasksData.find(t => t.id === taskId);
    if (!task) return;

    document.getElementById('popupTaskName').textContent = task.task_name;

    let content = `
        <p><strong>Task #:</strong> ${task.task_no}</p>
        <p><strong>Status:</strong> ${task.status} | <strong>Priority:</strong> ${task.priority}</p>
        <p><strong>Assigned to:</strong> ${task.assignee}</p>
    `;

    if (task.start_date) {
        content += `<p><strong>Start:</strong> ${task.start_date}`;
        if (task.start_time) content += ` at ${task.start_time}`;
        content += `</p>`;
    }

    if (task.due_date) {
        content += `<p><strong>Due:</strong> ${task.due_date}`;
        if (task.end_time) content += ` at ${task.end_time}`;
        content += `</p>`;
    }

    if (task.estimated_hours) {
        content += `<p><strong>Estimated:</strong> ${task.estimated_hours} hours</p>`;
    }

    if (task.task_description) {
        content += `<p><strong>Description:</strong><br>${task.task_description.substring(0, 200)}${task.task_description.length > 200 ? '...' : ''}</p>`;
    }

    document.getElementById('popupTaskContent').innerHTML = content;
    document.getElementById('popupViewLink').href = `view.php?id=${taskId}`;
    document.getElementById('popupEditLink').href = `edit.php?id=${taskId}`;

    document.getElementById('taskPopupOverlay').classList.add('active');
    document.getElementById('taskPopup').classList.add('active');
}

function closeTaskPopup() {
    document.getElementById('taskPopupOverlay').classList.remove('active');
    document.getElementById('taskPopup').classList.remove('active');
}

// Keyboard shortcut to close popup
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTaskPopup();
    }
});
</script>

</body>
</html>
