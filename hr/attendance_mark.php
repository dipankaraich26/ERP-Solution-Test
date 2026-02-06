<?php
// TEMPORARY: Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../db.php";
include "../includes/dialog.php";

$date = $_GET['date'] ?? date('Y-m-d');
$empFilter = $_GET['emp'] ?? '';
$debugInfo = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance = $_POST['attendance'] ?? [];
    $savedCount = 0;
    $errors = [];

    foreach ($attendance as $empId => $data) {
        $status = $data['status'] ?? '';
        $checkInInput = trim($data['check_in'] ?? '');
        $checkOutInput = trim($data['check_out'] ?? '');

        if ($status === '') continue;

        $debugInfo[] = "Processing Employee ID $empId: Status=$status, CheckIn=$checkInInput, CheckOut=$checkOutInput";

        // Format check-in and check-out times (store as HH:MM:SS to match portal format)
        $checkIn = null;
        $checkOut = null;

        if (!empty($checkInInput)) {
            if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $checkInInput)) {
                $checkIn = $checkInInput . ':00';
            }
        }

        if (!empty($checkOutInput)) {
            if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $checkOutInput)) {
                $checkOut = $checkOutInput . ':00';
            }
        }

        // Calculate working hours
        $workingHours = 0;
        if ($checkIn && $checkOut) {
            $inTime = strtotime($date . ' ' . $checkIn);
            $outTime = strtotime($date . ' ' . $checkOut);
            if ($outTime > $inTime) {
                $workingHours = round(($outTime - $inTime) / 3600, 2);
            }
        }

        // Insert or update
        try {
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
                $checkIn,
                $checkOut,
                $workingHours
            ]);
            $savedCount++;
            $debugInfo[] = "✓ Saved for Employee ID $empId";

            // COMP-OFF: If employee works on Holiday/Week Off for > 6 hours, add 1 EL
            if (in_array($status, ['Holiday', 'Week Off']) && $workingHours > 6) {
                try {
                    // Get EL (Earned Leave) type ID
                    $elTypeStmt = $pdo->prepare("SELECT id FROM leave_types WHERE leave_code = 'EL' AND is_active = 1 LIMIT 1");
                    $elTypeStmt->execute();
                    $elTypeId = $elTypeStmt->fetchColumn();

                    if ($elTypeId) {
                        $currentYear = date('Y', strtotime($date));

                        // Check if balance record exists
                        $balCheck = $pdo->prepare("SELECT id, allocated, balance FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                        $balCheck->execute([$empId, $elTypeId, $currentYear]);
                        $existingBal = $balCheck->fetch(PDO::FETCH_ASSOC);

                        if ($existingBal) {
                            // Add 1 day to existing balance
                            $pdo->prepare("UPDATE leave_balances SET allocated = allocated + 1, balance = balance + 1 WHERE id = ?")
                                ->execute([$existingBal['id']]);
                        } else {
                            // Create new balance with 1 day
                            $pdo->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated, used, balance) VALUES (?, ?, ?, 1, 0, 1)")
                                ->execute([$empId, $elTypeId, $currentYear]);
                        }
                        $debugInfo[] = "✓ Added 1 EL (Comp-Off) for Employee ID $empId - worked $workingHours hrs on $status";
                    }
                } catch (PDOException $e) {
                    // Leave tables may not exist - silently skip
                    $debugInfo[] = "Note: Could not add EL - " . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            // Log the error and continue with other employees
            $errorMsg = $e->getMessage();
            error_log("Attendance save error for employee $empId: " . $errorMsg);
            $errors[] = "Employee ID $empId: " . $errorMsg;
            $debugInfo[] = "✗ Error for Employee ID $empId: " . $errorMsg;
        }
    }

    if (!empty($errors)) {
        setModal("Error", "Some records failed to save:\n" . implode("\n", $errors));
    } else {
        setModal("Success", "Attendance saved for $savedCount employee(s) on " . date('d M Y', strtotime($date)));
    }
    header("Location: attendance_mark.php?date=$date");
    exit;
}

// Handle delete/reset attendance
if (isset($_GET['delete']) && isset($_GET['emp_id'])) {
    $deleteEmpId = (int)$_GET['emp_id'];
    $deleteDate = $_GET['date'] ?? date('Y-m-d');

    try {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$deleteEmpId, $deleteDate]);

        setModal("Success", "Attendance deleted for employee ID $deleteEmpId on " . date('d M Y', strtotime($deleteDate)));
        header("Location: attendance_mark.php?date=$deleteDate");
        exit;
    } catch (PDOException $e) {
        setModal("Error", "Failed to delete attendance: " . $e->getMessage());
        header("Location: attendance_mark.php?date=$deleteDate");
        exit;
    }
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

    <?php if (!empty($debugInfo)): ?>
        <div style="background: #e3f2fd; border: 1px solid #1976d2; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>DEBUG INFO:</strong>
            <pre style="margin: 10px 0 0 0; font-size: 0.85em;"><?= htmlspecialchars(implode("\n", $debugInfo)) ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($holidayName): ?>
        <div class="holiday-notice">
            <strong>Holiday:</strong> <?= htmlspecialchars($holidayName) ?>
        </div>
    <?php elseif ($isSunday): ?>
        <div class="holiday-notice">
            <strong>Week Off:</strong> Sunday
        </div>
    <?php endif; ?>

    <?php if ($holidayName || $isSunday): ?>
        <div style="background: #d4edda; border: 1px solid #28a745; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; color: #155724;">
            <strong>Comp-Off Policy:</strong> If an employee works on this <?= $holidayName ? 'Holiday' : 'Week Off' ?> for more than 6 hours,
            1 day of Earned Leave (EL) will be automatically added to their leave balance.
            <br><small>Mark status as "Holiday" or "Week Off" and enter check-in/check-out times to record the extra work.</small>
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
                    <th>Action</th>
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
                               value="<?= $att && $att['check_in'] ? date('H:i', strtotime($att['check_in'])) : '' ?>">
                    </td>
                    <td>
                        <input type="time" name="attendance[<?= $emp['id'] ?>][check_out]"
                               value="<?= $att && $att['check_out'] ? date('H:i', strtotime($att['check_out'])) : '' ?>">
                    </td>
                    <td>
                        <?php if ($att): ?>
                            <a href="?delete=1&emp_id=<?= $emp['id'] ?>&date=<?= $date ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete attendance for <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> on <?= date('d M Y', strtotime($date)) ?>?');"
                               style="padding: 4px 8px; font-size: 0.85em;">
                                Reset
                            </a>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
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
