<?php
include "../db.php";
include "../includes/dialog.php";

// Get selected month (default: current month)
$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthName = date('F Y', strtotime($monthStart));

// Get department filter
$department = $_GET['department'] ?? '';

// Get employees
$empWhere = "status = 'Active'";
$empParams = [];
if ($department) {
    $empWhere .= " AND department = ?";
    $empParams[] = $department;
}

$employees = $pdo->prepare("SELECT id, emp_id, first_name, last_name, department FROM employees WHERE $empWhere ORDER BY first_name");
$employees->execute($empParams);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

// Get days in month
$daysInMonth = date('t', strtotime($monthStart));

// Get attendance data for the month
$attendanceData = [];
$stmt = $pdo->prepare("
    SELECT employee_id, attendance_date, status, check_in, check_out
    FROM attendance
    WHERE attendance_date BETWEEN ? AND ?
");
$stmt->execute([$monthStart, $monthEnd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $attendanceData[$row['employee_id']][date('j', strtotime($row['attendance_date']))] = $row;
}

// Get holidays for the month
$holidays = [];
$holidayStmt = $pdo->prepare("SELECT DAY(holiday_date) as day, name FROM holidays WHERE holiday_date BETWEEN ? AND ?");
$holidayStmt->execute([$monthStart, $monthEnd]);
while ($h = $holidayStmt->fetch(PDO::FETCH_ASSOC)) {
    $holidays[$h['day']] = $h['name'];
}

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Calculate summary
$summary = [
    'present' => 0,
    'absent' => 0,
    'half_day' => 0,
    'leave' => 0,
    'holiday' => 0
];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .month-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .month-nav h2 { margin: 0; }
        .month-nav a { text-decoration: none; font-size: 1.5em; color: #3498db; }

        .filters {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .attendance-table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        .attendance-table th, .attendance-table td {
            padding: 8px 5px;
            text-align: center;
            border: 1px solid #e0e0e0;
            font-size: 0.85em;
        }
        .attendance-table th {
            background: #f5f5f5;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        .attendance-table th.emp-col {
            text-align: left;
            min-width: 180px;
            position: sticky;
            left: 0;
            background: #f5f5f5;
            z-index: 2;
        }
        .attendance-table td.emp-col {
            text-align: left;
            position: sticky;
            left: 0;
            background: white;
            z-index: 1;
        }
        .attendance-table tr:hover td { background: #fafafa; }
        .attendance-table tr:hover td.emp-col { background: #f0f0f0; }

        .day-header { font-size: 0.75em; color: #666; }
        .day-sun { background: #fff3e0 !important; }
        .day-holiday { background: #e8f5e9 !important; }

        .att-cell {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8em;
            cursor: pointer;
        }
        .att-P { background: #d4edda; color: #155724; }
        .att-A { background: #f8d7da; color: #721c24; }
        .att-H { background: #fff3cd; color: #856404; }
        .att-L { background: #cce5ff; color: #004085; }
        .att-WO { background: #e2e3e5; color: #383d41; }
        .att-HD { background: #d1ecf1; color: #0c5460; }
        .att-empty { background: #f8f9fa; color: #6c757d; }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        .legend-box {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8em;
        }

        .summary-row td {
            background: #f8f9fa !important;
            font-weight: bold;
        }

        .quick-actions {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="attendance-header">
        <div class="month-nav">
            <?php
            $prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
            $nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
            ?>
            <a href="?month=<?= $prevMonth ?>&department=<?= urlencode($department) ?>">&larr;</a>
            <h2><?= $monthName ?></h2>
            <a href="?month=<?= $nextMonth ?>&department=<?= urlencode($department) ?>">&rarr;</a>
        </div>

        <div class="filters">
            <form method="get" style="display: flex; gap: 10px;">
                <input type="month" name="month" value="<?= $selectedMonth ?>">
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Go</button>
            </form>
        </div>
    </div>

    <div class="quick-actions">
        <a href="attendance_mark.php?date=<?= date('Y-m-d') ?>" class="btn btn-success">Mark Today's Attendance</a>
        <a href="attendance_bulk.php?month=<?= $selectedMonth ?>" class="btn btn-secondary">Bulk Entry</a>
        <a href="holidays.php" class="btn btn-secondary">Manage Holidays</a>
    </div>

    <div class="attendance-table-wrapper">
        <table class="attendance-table">
            <thead>
                <tr>
                    <th class="emp-col">Employee</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $date = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $dayOfWeek = date('D', strtotime($date));
                        $isSunday = date('w', strtotime($date)) == 0;
                        $isHoliday = isset($holidays[$d]);
                        $class = $isSunday ? 'day-sun' : ($isHoliday ? 'day-holiday' : '');
                    ?>
                        <th class="<?= $class ?>" title="<?= $isHoliday ? $holidays[$d] : '' ?>">
                            <?= $d ?><br>
                            <span class="day-header"><?= $dayOfWeek ?></span>
                        </th>
                    <?php endfor; ?>
                    <th>P</th>
                    <th>A</th>
                    <th>L</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="<?= $daysInMonth + 4 ?>" style="padding: 40px; color: #7f8c8d;">No employees found</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp):
                        $present = 0;
                        $absent = 0;
                        $leave = 0;
                    ?>
                        <tr>
                            <td class="emp-col">
                                <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong><br>
                                <small style="color: #7f8c8d;"><?= htmlspecialchars($emp['emp_id']) ?></small>
                            </td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $date = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $dayOfWeek = date('w', strtotime($date));
                                $isSunday = $dayOfWeek == 0;
                                $isHoliday = isset($holidays[$d]);
                                $att = $attendanceData[$emp['id']][$d] ?? null;

                                $class = $isSunday ? 'day-sun' : ($isHoliday ? 'day-holiday' : '');

                                if ($att) {
                                    switch ($att['status']) {
                                        case 'Present': $code = 'P'; $present++; break;
                                        case 'Absent': $code = 'A'; $absent++; break;
                                        case 'Half Day': $code = 'H'; $present += 0.5; break;
                                        case 'Late': $code = 'P'; $present++; break;
                                        case 'On Leave': $code = 'L'; $leave++; break;
                                        case 'Holiday': $code = 'HO'; break;
                                        case 'Week Off': $code = 'WO'; break;
                                        default: $code = '-';
                                    }
                                } else {
                                    if ($isSunday) {
                                        $code = 'WO';
                                    } elseif ($isHoliday) {
                                        $code = 'HO';
                                    } elseif (strtotime($date) <= strtotime(date('Y-m-d'))) {
                                        $code = '-';
                                    } else {
                                        $code = '';
                                    }
                                }

                                $attClass = match($code) {
                                    'P' => 'att-P',
                                    'A' => 'att-A',
                                    'H' => 'att-H',
                                    'L' => 'att-L',
                                    'WO', 'HO' => 'att-WO',
                                    default => 'att-empty'
                                };
                            ?>
                                <td class="<?= $class ?>">
                                    <a href="attendance_mark.php?date=<?= $date ?>&emp=<?= $emp['id'] ?>"
                                       class="att-cell <?= $attClass ?>" title="<?= $date ?>">
                                        <?= $code ?>
                                    </a>
                                </td>
                            <?php endfor; ?>
                            <td><strong style="color: #27ae60;"><?= $present ?></strong></td>
                            <td><strong style="color: #e74c3c;"><?= $absent ?></strong></td>
                            <td><strong style="color: #3498db;"><?= $leave ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="legend">
        <div class="legend-item"><div class="legend-box att-P">P</div> Present</div>
        <div class="legend-item"><div class="legend-box att-A">A</div> Absent</div>
        <div class="legend-item"><div class="legend-box att-H">H</div> Half Day</div>
        <div class="legend-item"><div class="legend-box att-L">L</div> On Leave</div>
        <div class="legend-item"><div class="legend-box att-WO">WO</div> Week Off</div>
        <div class="legend-item"><div class="legend-box att-WO">HO</div> Holiday</div>
    </div>
</div>

</body>
</html>
