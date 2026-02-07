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

// Get view and date parameters
$view = $_GET['view'] ?? 'month';
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

// Fetch employee's tasks for the date range
$tasks = [];
try {
    $taskStmt = $pdo->prepare("
        SELECT t.*,
               tc.category_name, tc.color_code as category_color
        FROM tasks t
        LEFT JOIN task_categories tc ON t.category_id = tc.id
        WHERE t.assigned_to = ?
          AND (t.start_date BETWEEN ? AND ? OR t.due_date BETWEEN ? AND ?)
          AND t.status != 'Cancelled'
        ORDER BY t.start_date, t.start_time, t.priority DESC
    ");
    $taskStmt->execute([$empId, $startDate, $endDate, $startDate, $endDate]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Group tasks by date
$tasksByDate = [];
foreach ($tasks as $task) {
    $date = $task['start_date'] ?: $task['due_date'];
    if (!isset($tasksByDate[$date])) {
        $tasksByDate[$date] = [];
    }
    $tasksByDate[$date][] = $task;
}

// Fetch holidays
$holidays = [];
try {
    $hStmt = $pdo->prepare("SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $hStmt->execute([$startDate, $endDate]);
    foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holidays[$h['holiday_date']] = $h['holiday_name'];
    }
} catch (PDOException $e) {}

// Fetch attendance for the date range
$attendance = [];
try {
    $attStmt = $pdo->prepare("SELECT attendance_date, status, check_in, check_out FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
    $attStmt->execute([$empId, $startDate, $endDate]);
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $attendance[$a['attendance_date']] = $a;
    }
} catch (PDOException $e) {}

// Build calendar days for month view
$calendarDays = [];
if ($view === 'month') {
    $firstDayOfMonth = date('N', strtotime("$year-$month-01"));
    $daysInMonth = date('t', strtotime("$year-$month-01"));

    for ($i = 1; $i < $firstDayOfMonth; $i++) {
        $d = date('Y-m-d', strtotime("$year-$month-01 -" . ($firstDayOfMonth - $i) . " days"));
        $calendarDays[] = ['date' => $d, 'current_month' => false];
    }
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $d = sprintf('%04d-%02d-%02d', $year, $month, $i);
        $calendarDays[] = ['date' => $d, 'current_month' => true];
    }
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

// Time slots for day/week view
$timeSlots = [];
for ($h = 8; $h <= 20; $h++) {
    $timeSlots[] = sprintf('%02d:00', $h);
}

// Summary stats
$periodTasks = count($tasks);
$periodHighPriority = count(array_filter($tasks, fn($t) => $t['priority'] === 'Critical' || $t['priority'] === 'High'));

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: attendance_login.php");
    exit;
}

// Build nav URLs
$navBase = "?view=$view&employee=$empId";
$prevUrl = $navBase . "&year=" . date('Y', strtotime($prevDate)) . "&month=" . date('m', strtotime($prevDate)) . "&day=" . date('d', strtotime($prevDate));
$nextUrl = $navBase . "&year=" . date('Y', strtotime($nextDate)) . "&month=" . date('m', strtotime($nextDate)) . "&day=" . date('d', strtotime($nextDate));
$todayUrl = "?view=$view";
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Calendar - <?= htmlspecialchars($empName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa; min-height: 100vh;
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

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* Calendar Controls */
        .calendar-controls {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap; gap: 15px;
        }
        .calendar-nav { display: flex; gap: 8px; align-items: center; }
        .nav-btn {
            padding: 8px 16px; background: white; border: 1px solid #ddd;
            border-radius: 8px; text-decoration: none; color: #333;
            font-size: 0.9em; transition: all 0.2s;
        }
        .nav-btn:hover { background: #f0f0f0; }
        .calendar-title { font-size: 1.4em; font-weight: bold; color: #2c3e50; }

        .view-tabs { display: flex; gap: 5px; }
        .view-tab {
            padding: 8px 16px; background: white; border: 1px solid #ddd;
            border-radius: 8px; text-decoration: none; color: #333;
            font-size: 0.9em; transition: all 0.2s;
        }
        .view-tab.active {
            background: #667eea; color: white; border-color: #667eea;
        }

        /* Month Grid */
        .month-grid {
            display: grid; grid-template-columns: repeat(7, 1fr);
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .month-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 12px; text-align: center;
            font-weight: 600; font-size: 0.9em;
        }
        .month-day {
            min-height: 110px; border: 1px solid #f0f0f0; padding: 6px;
            background: white; cursor: pointer; transition: background 0.2s;
        }
        .month-day:hover { background: #fafafe; }
        .month-day.other-month { background: #fafafa; }
        .month-day.other-month .day-number { color: #ccc; }
        .month-day.today { background: #e8eaf6; }
        .month-day.holiday { background: #fff8e1; }
        .day-number { font-weight: bold; color: #2c3e50; margin-bottom: 4px; font-size: 0.9em; }
        .day-holiday { font-size: 0.7em; color: #f57f17; margin-bottom: 3px; }
        .day-attendance {
            font-size: 0.65em; padding: 1px 6px; border-radius: 8px;
            display: inline-block; margin-bottom: 3px;
        }
        .att-Present, .att-Late { background: #e8f5e9; color: #2e7d32; }
        .att-Absent { background: #ffebee; color: #c62828; }
        .att-Half { background: #e3f2fd; color: #1565c0; }
        .att-On { background: #f3e5f5; color: #7b1fa2; }

        .day-tasks { font-size: 0.8em; }
        .day-task {
            padding: 2px 6px; margin-bottom: 2px; border-radius: 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            cursor: pointer; font-size: 0.85em;
        }
        .day-task:hover { opacity: 0.8; }
        .more-tasks { font-size: 0.75em; color: #666; cursor: pointer; }

        /* Week Grid */
        .week-grid {
            display: grid; grid-template-columns: 70px repeat(7, 1fr);
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .week-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 10px; text-align: center; font-weight: 500;
        }
        .week-header.today-col { background: linear-gradient(135deg, #5c6bc0 0%, #6a1b9a 100%); }
        .time-label {
            background: #f8f9fa; padding: 8px 5px; font-size: 0.8em;
            text-align: right; border-bottom: 1px solid #eee; color: #666;
        }
        .time-slot {
            min-height: 50px; border: 1px solid #f0f0f0; padding: 2px;
        }
        .time-slot.today-col { background: #f5f5ff; }

        /* Day Grid */
        .day-grid {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .day-grid-inner { display: grid; grid-template-columns: 80px 1fr; }
        .day-time-label {
            background: #f8f9fa; padding: 15px 10px; font-size: 0.9em;
            text-align: right; border-bottom: 1px solid #eee; color: #666;
        }
        .day-time-slot {
            min-height: 60px; border-bottom: 1px solid #eee; padding: 5px;
        }
        .day-time-slot:hover { background: #fafafe; }

        /* Task Cards in calendar */
        .task-card-cal {
            padding: 4px 8px; border-radius: 4px; margin-bottom: 3px;
            font-size: 0.85em; cursor: pointer; border-left: 3px solid;
        }
        .task-card-cal:hover { box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        .task-card-cal .task-time { font-size: 0.8em; color: #666; }
        .priority-Critical { border-left-color: #e74c3c !important; }
        .priority-High { border-left-color: #f39c12 !important; }
        .priority-Medium { border-left-color: #3498db !important; }
        .priority-Low { border-left-color: #95a5a6 !important; }

        /* Sidebar */
        .calendar-layout { display: grid; grid-template-columns: 1fr 280px; gap: 20px; }
        .sidebar-card {
            background: white; border-radius: 12px; padding: 18px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08); margin-bottom: 15px;
        }
        .sidebar-card h4 {
            margin: 0 0 12px 0; padding-bottom: 8px;
            border-bottom: 2px solid #667eea; color: #2c3e50; font-size: 1em;
        }
        .legend { display: flex; flex-wrap: wrap; gap: 10px; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.85em; }
        .legend-color { width: 12px; height: 12px; border-radius: 3px; }

        /* Task Popup */
        .task-popup-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 999;
        }
        .task-popup-overlay.active { display: block; }
        .task-popup {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); z-index: 1000;
            min-width: 350px; max-width: 450px;
        }
        .task-popup.active { display: block; }
        .popup-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 15px;
        }
        .popup-close {
            background: none; border: none; font-size: 1.5em; cursor: pointer; color: #666;
        }

        @media (max-width: 1000px) {
            .calendar-layout { grid-template-columns: 1fr; }
            .calendar-layout .calendar-sidebar { display: none; }
        }
        @media (max-width: 768px) {
            .month-day { min-height: 80px; padding: 4px; }
            .day-number { font-size: 0.8em; }
            .day-task { font-size: 0.7em; }
            .week-grid { grid-template-columns: 50px repeat(7, 1fr); }
        }
        @media (max-width: 600px) {
            .portal-header { padding: 15px; }
            .user-details h2 { font-size: 1em; }
            .calendar-controls { flex-direction: column; align-items: stretch; }
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
        <a href="my_tasks.php" class="header-btn">Tasks</a>
        <a href="my_payslip.php" class="header-btn">Payslips</a>
        <a href="my_tada.php" class="header-btn">TADA</a>
        <a href="my_advance.php" class="header-btn">Advances</a>
        <a href="?logout=1" class="header-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- Calendar Controls -->
    <div class="calendar-controls">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div class="calendar-nav">
                <a href="<?= $prevUrl ?>" class="nav-btn">&#9664; Prev</a>
                <a href="<?= $todayUrl ?>" class="nav-btn">Today</a>
                <a href="<?= $nextUrl ?>" class="nav-btn">Next &#9654;</a>
            </div>
            <span class="calendar-title"><?= $headerTitle ?></span>
        </div>
        <div class="view-tabs">
            <a href="?view=month&year=<?= $year ?>&month=<?= $month ?>" class="view-tab <?= $view === 'month' ? 'active' : '' ?>">Month</a>
            <a href="?view=week&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-tab <?= $view === 'week' ? 'active' : '' ?>">Week</a>
            <a href="?view=day&year=<?= $year ?>&month=<?= $month ?>&day=<?= $day ?>" class="view-tab <?= $view === 'day' ? 'active' : '' ?>">Day</a>
        </div>
    </div>

    <div class="calendar-layout">
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
                    $isHoliday = isset($holidays[$calDay['date']]);
                    $dayAtt = $attendance[$calDay['date']] ?? null;
                    $isSunday = date('w', strtotime($calDay['date'])) == 0;
                ?>
                <div class="month-day <?= $calDay['current_month'] ? '' : 'other-month' ?> <?= $isToday ? 'today' : '' ?> <?= $isHoliday ? 'holiday' : '' ?>"
                     onclick="window.location='?view=day&year=<?= date('Y', strtotime($calDay['date'])) ?>&month=<?= date('m', strtotime($calDay['date'])) ?>&day=<?= date('d', strtotime($calDay['date'])) ?>'">
                    <div class="day-number">
                        <?= date('j', strtotime($calDay['date'])) ?>
                        <?php if ($isSunday && $calDay['current_month']): ?>
                            <span style="font-size: 0.7em; color: #e74c3c;">Sun</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isHoliday && $calDay['current_month']): ?>
                        <div class="day-holiday"><?= htmlspecialchars($holidays[$calDay['date']]) ?></div>
                    <?php endif; ?>
                    <?php if ($dayAtt && $calDay['current_month']): ?>
                        <div class="day-attendance att-<?= explode(' ', $dayAtt['status'])[0] ?>">
                            <?= $dayAtt['status'] ?>
                        </div>
                    <?php endif; ?>
                    <div class="day-tasks">
                        <?php
                        $maxShow = 2;
                        foreach (array_slice($dayTasks, 0, $maxShow) as $t):
                            $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                        ?>
                        <div class="day-task" style="background: <?= $color ?>20; color: <?= $color ?>;"
                             onclick="event.stopPropagation(); showTaskPopup(<?= $t['id'] ?>)">
                            <?= htmlspecialchars(substr($t['task_name'], 0, 18)) ?><?= strlen($t['task_name']) > 18 ? '..' : '' ?>
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
                <div class="week-header <?= $isToday ? 'today-col' : '' ?>">
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
                <div class="time-slot <?= $isToday ? 'today-col' : '' ?>">
                    <?php foreach ($slotTasks as $t):
                        $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                    ?>
                    <div class="task-card-cal priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                         onclick="showTaskPopup(<?= $t['id'] ?>)">
                        <strong><?= htmlspecialchars(substr($t['task_name'], 0, 15)) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Day View -->
            <div class="day-grid">
                <div style="padding: 15px; border-bottom: 1px solid #eee;">
                    <h3><?= date('l, F j, Y', strtotime($currentDate)) ?></h3>
                    <?php if (isset($holidays[$currentDate])): ?>
                        <p style="color: #f57f17; margin-top: 5px;">Holiday: <?= htmlspecialchars($holidays[$currentDate]) ?></p>
                    <?php endif; ?>
                    <?php if (isset($attendance[$currentDate])): ?>
                        <p style="margin-top: 5px; font-size: 0.9em; color: #666;">
                            Attendance: <strong><?= $attendance[$currentDate]['status'] ?></strong>
                            <?php if ($attendance[$currentDate]['check_in']): ?>
                                | In: <?= date('h:i A', strtotime($attendance[$currentDate]['check_in'])) ?>
                            <?php endif; ?>
                            <?php if ($attendance[$currentDate]['check_out']): ?>
                                | Out: <?= date('h:i A', strtotime($attendance[$currentDate]['check_out'])) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="day-grid-inner">
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
                        <div class="task-card-cal priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                             onclick="showTaskPopup(<?= $t['id'] ?>)">
                            <div class="task-time">
                                <?= date('g:i A', strtotime($t['start_time'])) ?>
                                <?= $t['end_time'] ? ' - ' . date('g:i A', strtotime($t['end_time'])) : '' ?>
                            </div>
                            <strong><?= htmlspecialchars($t['task_name']) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php
                $allDayTasks = array_filter($tasksByDate[$currentDate] ?? [], function($t) {
                    return ($t['all_day'] ?? false) || !$t['start_time'];
                });
                if (!empty($allDayTasks)):
                ?>
                <div style="padding: 15px; background: #f8f9fa; border-top: 1px solid #eee;">
                    <strong style="color: #666;">All Day / No Time Set:</strong>
                    <div style="margin-top: 10px;">
                        <?php foreach ($allDayTasks as $t):
                            $color = $t['color_code'] ?: $t['category_color'] ?: '#3498db';
                        ?>
                        <div class="task-card-cal priority-<?= $t['priority'] ?>" style="background: <?= $color ?>15;"
                             onclick="showTaskPopup(<?= $t['id'] ?>)">
                            <strong><?= htmlspecialchars($t['task_name']) ?></strong>
                            <span style="font-size: 0.8em; color: #888;">
                                <?php if ($t['due_date']): ?>Due: <?= date('d M', strtotime($t['due_date'])) ?><?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="calendar-sidebar">
            <div class="sidebar-card">
                <h4>Period Summary</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="text-align: center; padding: 12px; background: #e3f2fd; border-radius: 8px;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #1565c0;"><?= $periodTasks ?></div>
                        <div style="font-size: 0.85em; color: #666;">Tasks</div>
                    </div>
                    <div style="text-align: center; padding: 12px; background: #fce4ec; border-radius: 8px;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #c2185b;"><?= $periodHighPriority ?></div>
                        <div style="font-size: 0.85em; color: #666;">High Priority</div>
                    </div>
                </div>
            </div>

            <div class="sidebar-card">
                <h4>Priority Legend</h4>
                <div class="legend">
                    <div class="legend-item"><div class="legend-color" style="background: #e74c3c;"></div><span>Critical</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #f39c12;"></div><span>High</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #3498db;"></div><span>Medium</span></div>
                    <div class="legend-item"><div class="legend-color" style="background: #95a5a6;"></div><span>Low</span></div>
                </div>
            </div>

            <div class="sidebar-card">
                <h4>Quick Links</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="my_tasks.php" style="padding: 10px 15px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #2c3e50; font-size: 0.9em;">
                        View All Tasks
                    </a>
                    <a href="my_tasks.php?filter=overdue" style="padding: 10px 15px; background: #ffebee; border-radius: 8px; text-decoration: none; color: #c62828; font-size: 0.9em;">
                        Overdue Tasks
                    </a>
                    <a href="attendance_portal.php" style="padding: 10px 15px; background: #e8f5e9; border-radius: 8px; text-decoration: none; color: #2e7d32; font-size: 0.9em;">
                        Attendance Portal
                    </a>
                </div>
            </div>
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
</div>

<script>
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
    `;
    if (task.category) content += `<p><strong>Category:</strong> ${task.category}</p>`;
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
    if (task.estimated_hours) content += `<p><strong>Estimated:</strong> ${task.estimated_hours} hours</p>`;
    if (task.task_description) {
        content += `<p><strong>Description:</strong><br>${task.task_description.substring(0, 300)}${task.task_description.length > 300 ? '...' : ''}</p>`;
    }

    document.getElementById('popupTaskContent').innerHTML = content;
    document.getElementById('taskPopupOverlay').classList.add('active');
    document.getElementById('taskPopup').classList.add('active');
}

function closeTaskPopup() {
    document.getElementById('taskPopupOverlay').classList.remove('active');
    document.getElementById('taskPopup').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTaskPopup();
});
</script>

</body>
</html>
