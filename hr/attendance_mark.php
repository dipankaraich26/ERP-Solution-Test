<?php
include "../db.php";
include "../includes/dialog.php";

$date = $_GET['date'] ?? date('Y-m-d');
$empFilter = $_GET['emp'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance = $_POST['attendance'] ?? [];

    foreach ($attendance as $empId => $data) {
        $status = $data['status'] ?? '';
        $checkIn = $data['check_in'] ?? null;
        $checkOut = $data['check_out'] ?? null;

        if ($status === '') continue;

        // Calculate working hours
        $workingHours = 0;
        if ($checkIn && $checkOut) {
            $inTime = strtotime($checkIn);
            $outTime = strtotime($checkOut);
            if ($outTime > $inTime) {
                $workingHours = round(($outTime - $inTime) / 3600, 2);
            }
        }

        // Insert or update
        $stmt = $pdo->prepare("
            INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, working_hours)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                check_in = VALUES(check_in),
                check_out = VALUES(check_out),
                working_hours = VALUES(working_hours)
        ");
        $stmt->execute([
            $empId,
            $date,
            $status,
            $checkIn ?: null,
            $checkOut ?: null,
            $workingHours
        ]);
    }

    setModal("Success", "Attendance saved for " . date('d M Y', strtotime($date)));
    header("Location: attendance_mark.php?date=$date");
    exit;
}

// Get employees
$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY department, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get existing attendance for this date
$existingAtt = [];
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE attendance_date = ?");
$stmt->execute([$date]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingAtt[$row['employee_id']] = $row;
}

// Check if it's a holiday
$holiday = $pdo->prepare("SELECT name FROM holidays WHERE holiday_date = ?");
$holiday->execute([$date]);
$holidayName = $holiday->fetchColumn();

$isSunday = date('w', strtotime($date)) == 0;

// Get approved leaves for this date
$approvedLeaves = [];
try {
    $leaveStmt = $pdo->prepare("
        SELECT lr.employee_id, lr.total_days, lr.is_half_day, lr.half_day_type, lt.leave_code, lt.leave_type_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.status = 'Approved'
          AND ? BETWEEN lr.start_date AND lr.end_date
    ");
    $leaveStmt->execute([$date]);
    while ($leave = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
        $approvedLeaves[$leave['employee_id']] = $leave;
    }
} catch (PDOException $e) {
    // Leave tables may not exist yet
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mark Attendance - <?= date('d M Y', strtotime($date)) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .date-nav {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        .date-nav h2 { margin: 0; }
        .date-nav a { font-size: 1.5em; color: #3498db; text-decoration: none; }

        .holiday-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .att-table { width: 100%; border-collapse: collapse; }
        .att-table th, .att-table td { padding: 12px; border-bottom: 1px solid #ddd; }
        .att-table th { background: #f5f5f5; text-align: left; }
        .att-table tr:hover { background: #fafafa; }

        .att-table select, .att-table input[type="time"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .att-table select { min-width: 120px; }

        .status-Present { color: #27ae60; }
        .status-Absent { color: #e74c3c; }
        .status-Half-Day { color: #f39c12; }
        .status-On-Leave { color: #3498db; }

        .leave-indicator {
            display: inline-block;
            padding: 3px 8px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            margin-left: 8px;
        }
        .leave-indicator.half-day {
            background: #fff3e0;
            color: #e65100;
        }
        .has-leave { background: #e3f2fd; }

        .quick-fill {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .quick-fill button { margin-right: 10px; }
    </style>
</head>
<body>

<div class="content">
    <div class="date-nav">
        <?php
        $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
        ?>
        <a href="?date=<?= $prevDate ?>">&larr;</a>
        <h2><?= date('l, d F Y', strtotime($date)) ?></h2>
        <a href="?date=<?= $nextDate ?>">&rarr;</a>

        <form method="get" style="margin-left: 20px;">
            <input type="date" name="date" value="<?= $date ?>" onchange="this.form.submit()">
        </form>
    </div>

    <p>
        <a href="attendance.php?month=<?= substr($date, 0, 7) ?>" class="btn btn-secondary">Back to Calendar</a>
    </p>

    <?php if ($holidayName): ?>
        <div class="holiday-notice">
            <strong>Holiday:</strong> <?= htmlspecialchars($holidayName) ?>
        </div>
    <?php elseif ($isSunday): ?>
        <div class="holiday-notice">
            <strong>Week Off:</strong> Sunday
        </div>
    <?php endif; ?>

    <div class="quick-fill">
        <strong>Quick Fill:</strong>
        <button type="button" onclick="fillAll('Present')" class="btn btn-sm btn-success">All Present</button>
        <button type="button" onclick="fillAll('Absent')" class="btn btn-sm btn-danger">All Absent</button>
        <button type="button" onclick="fillAll('Holiday')" class="btn btn-sm btn-secondary">All Holiday</button>
        <button type="button" onclick="fillAll('Week Off')" class="btn btn-sm btn-secondary">All Week Off</button>
    </div>

    <form method="post">
        <table class="att-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp):
                    $att = $existingAtt[$emp['id']] ?? null;
                    $hasLeave = isset($approvedLeaves[$emp['id']]);
                    $leaveInfo = $hasLeave ? $approvedLeaves[$emp['id']] : null;

                    // Determine default status - prioritize approved leave
                    $currentStatus = $att['status'] ?? '';
                    if ($hasLeave && empty($currentStatus)) {
                        $currentStatus = $leaveInfo['is_half_day'] ? 'Half Day' : 'On Leave';
                    }
                ?>
                <tr class="<?= $hasLeave ? 'has-leave' : '' ?>">
                    <td>
                        <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                        <?php if ($hasLeave): ?>
                            <span class="leave-indicator <?= $leaveInfo['is_half_day'] ? 'half-day' : '' ?>">
                                <?= htmlspecialchars($leaveInfo['leave_code']) ?>
                                <?= $leaveInfo['is_half_day'] ? ' (' . $leaveInfo['half_day_type'] . ')' : '' ?>
                            </span>
                        <?php endif; ?>
                        <br>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($emp['emp_id']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                    <td>
                        <select name="attendance[<?= $emp['id'] ?>][status]" class="status-select">
                            <option value="">-- Select --</option>
                            <option value="Present" <?= $currentStatus === 'Present' ? 'selected' : '' ?>>Present</option>
                            <option value="Absent" <?= $currentStatus === 'Absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="Half Day" <?= $currentStatus === 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                            <option value="Late" <?= $currentStatus === 'Late' ? 'selected' : '' ?>>Late</option>
                            <option value="On Leave" <?= $currentStatus === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                            <option value="Holiday" <?= $currentStatus === 'Holiday' ? 'selected' : '' ?>>Holiday</option>
                            <option value="Week Off" <?= $currentStatus === 'Week Off' ? 'selected' : '' ?>>Week Off</option>
                        </select>
                    </td>
                    <td>
                        <input type="time" name="attendance[<?= $emp['id'] ?>][check_in]"
                               value="<?= $att['check_in'] ?? '' ?>">
                    </td>
                    <td>
                        <input type="time" name="attendance[<?= $emp['id'] ?>][check_out]"
                               value="<?= $att['check_out'] ?? '' ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Save Attendance</button>
        </div>
    </form>
</div>

<script>
function fillAll(status) {
    document.querySelectorAll('.status-select').forEach(select => {
        select.value = status;
    });
}
</script>

</body>
</html>
