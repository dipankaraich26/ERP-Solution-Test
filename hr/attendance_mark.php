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
$leaveErrors = [];
$currentYear = date('Y');

// Fetch active leave types
$leaveTypes = [];
try {
    $leaveTypes = $pdo->query("
        SELECT * FROM leave_types
        WHERE is_active = 1
        ORDER BY leave_code
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Leave tables may not exist
}

// Fetch holidays for the year
$holidayDates = [];
try {
    $holidays = $pdo->prepare("SELECT holiday_date FROM holidays WHERE YEAR(holiday_date) = ?");
    $holidays->execute([$currentYear]);
    $holidayDates = $holidays->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Holidays table may not exist
}

// Handle leave application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_submit'])) {
    $employee_id = intval($_POST['leave_employee_id'] ?? 0);
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    $start_date = $_POST['leave_start_date'] ?? '';
    $end_date = $_POST['leave_end_date'] ?? '';
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    $half_day_type = $_POST['half_day_type'] ?? null;
    $reason = trim($_POST['leave_reason'] ?? '');

    // Validation
    if (!$employee_id) $leaveErrors[] = "Please select an employee";
    if (!$leave_type_id) $leaveErrors[] = "Please select a leave type";
    if (!$start_date) $leaveErrors[] = "Start date is required";
    if (!$end_date) $leaveErrors[] = "End date is required";
    if (!$reason) $leaveErrors[] = "Reason is required";

    // Date validation and calculate days
    $totalDays = 0;
    if ($start_date && $end_date) {
        $startDt = new DateTime($start_date);
        $endDt = new DateTime($end_date);

        if ($endDt < $startDt) {
            $leaveErrors[] = "End date cannot be before start date";
        } else {
            // Calculate working days
            $current = clone $startDt;
            while ($current <= $endDt) {
                $dayOfWeek = $current->format('N');
                $dateStr = $current->format('Y-m-d');
                // Skip Sundays (7) and holidays
                if ($dayOfWeek != 7 && !in_array($dateStr, $holidayDates)) {
                    $totalDays++;
                }
                $current->modify('+1 day');
            }
        }

        if ($is_half_day) {
            $totalDays = 0.5;
            if (!$half_day_type) {
                $leaveErrors[] = "Please select First Half or Second Half";
            }
        }

        if ($totalDays <= 0 && empty($leaveErrors)) {
            $leaveErrors[] = "Selected dates have no working days";
        }
    }

    // Check leave balance and overlapping leaves
    if (empty($leaveErrors) && $employee_id && $leave_type_id) {
        // Get leave type info
        $leaveTypeInfo = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
        $leaveTypeInfo->execute([$leave_type_id]);
        $ltInfo = $leaveTypeInfo->fetch(PDO::FETCH_ASSOC);

        // Check balance for leave types with limits
        if ($ltInfo && $ltInfo['max_days_per_year'] > 0) {
            $balanceCheck = $pdo->prepare("
                SELECT balance FROM leave_balances
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
            ");
            $balanceCheck->execute([$employee_id, $leave_type_id, $currentYear]);
            $availableBalance = $balanceCheck->fetchColumn();
            if ($availableBalance === false) $availableBalance = $ltInfo['max_days_per_year'];

            if ($totalDays > $availableBalance) {
                $leaveErrors[] = "Insufficient leave balance. Available: $availableBalance days, Requested: $totalDays days";
            }
        }

        // Check for overlapping leaves
        $overlapCheck = $pdo->prepare("
            SELECT COUNT(*) FROM leave_requests
            WHERE employee_id = ?
              AND status IN ('Pending', 'Approved')
              AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
        ");
        $overlapCheck->execute([$employee_id, $end_date, $start_date, $start_date, $end_date]);

        if ($overlapCheck->fetchColumn() > 0) {
            $leaveErrors[] = "Leave request overlaps with an existing pending or approved leave";
        }
    }

    // Insert leave request
    if (empty($leaveErrors)) {
        try {
            // Generate leave request number
            $lastReq = $pdo->query("SELECT MAX(id) FROM leave_requests")->fetchColumn();
            $reqNo = 'LR-' . date('Y') . '-' . str_pad(($lastReq + 1), 4, '0', STR_PAD_LEFT);

            // Determine status based on leave type
            $status = $ltInfo['requires_approval'] ? 'Pending' : 'Approved';

            $stmt = $pdo->prepare("
                INSERT INTO leave_requests
                (leave_request_no, employee_id, leave_type_id, start_date, end_date, total_days, is_half_day, half_day_type, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $reqNo,
                $employee_id,
                $leave_type_id,
                $start_date,
                $end_date,
                $totalDays,
                $is_half_day,
                $is_half_day ? $half_day_type : null,
                $reason,
                $status
            ]);

            // If auto-approved, update balance
            if ($status === 'Approved') {
                $pdo->prepare("
                    UPDATE leave_balances
                    SET used = used + ?, balance = balance - ?
                    WHERE employee_id = ? AND leave_type_id = ? AND year = ?
                ")->execute([$totalDays, $totalDays, $employee_id, $leave_type_id, $currentYear]);
            }

            $message = $status === 'Approved'
                ? "Leave request $reqNo submitted and auto-approved!"
                : "Leave request $reqNo submitted successfully! Awaiting approval.";

            setModal("Success", $message);
            header("Location: attendance_mark.php?date=$date");
            exit;

        } catch (PDOException $e) {
            $leaveErrors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['leave_submit'])) {
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

        /* Leave Application Section */
        .leave-apply-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .leave-apply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
        }
        .leave-apply-header h3 {
            margin: 0;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .leave-apply-header .toggle-icon {
            transition: transform 0.3s;
        }
        .leave-apply-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .leave-apply-body {
            padding: 20px;
            display: block;
        }
        .leave-apply-body.collapsed {
            display: none;
        }
        .leave-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .leave-form-group {
            margin-bottom: 0;
        }
        .leave-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9em;
        }
        .leave-form-group input,
        .leave-form-group select,
        .leave-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .leave-form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .leave-form-group input:focus,
        .leave-form-group select:focus,
        .leave-form-group textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        .half-day-options {
            display: none;
            margin-top: 8px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .half-day-options.show { display: block; }
        .half-day-options label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 15px;
            font-weight: normal;
            cursor: pointer;
            font-size: 0.9em;
        }
        .leave-days-preview {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 15px;
            display: none;
            font-size: 0.95em;
        }
        .leave-days-preview.show { display: block; }
        .leave-error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            color: #721c24;
        }
        .leave-error-box ul {
            margin: 0;
            padding-left: 20px;
        }
        .leave-submit-row {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .date-nav {
                flex-wrap: wrap;
                gap: 10px;
            }
            .date-nav h2 {
                font-size: 1.2em;
                order: 1;
                width: 100%;
                text-align: center;
            }
            .date-nav a {
                order: 0;
            }
            .date-nav form {
                margin-left: 0 !important;
                order: 2;
                width: 100%;
            }
            .date-nav form input {
                width: 100%;
            }
            .att-table {
                display: block;
                overflow-x: auto;
            }
            .att-table th, .att-table td {
                padding: 8px;
                font-size: 0.9em;
            }
            .quick-fill {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .quick-fill button {
                margin-right: 0;
                flex: 1 1 45%;
            }
            .leave-form-grid {
                grid-template-columns: 1fr;
            }
            .leave-apply-header h3 {
                font-size: 1em;
            }
        }
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
        <a href="leaves.php" class="btn btn-secondary" style="margin-left: 10px;">View Leave Requests</a>
    </p>

    <!-- Leave Application Section -->
    <?php if (!empty($leaveTypes)): ?>
    <div class="leave-apply-section">
        <div class="leave-apply-header" onclick="toggleLeaveForm()">
            <h3>
                <span style="font-size: 1.2em;">+</span>
                Apply for Leave
            </h3>
            <span class="toggle-icon">▼</span>
        </div>
        <div class="leave-apply-body collapsed" id="leaveFormBody">
            <?php if (!empty($leaveErrors)): ?>
                <div class="leave-error-box">
                    <ul>
                        <?php foreach ($leaveErrors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="leaveApplicationForm">
                <input type="hidden" name="leave_submit" value="1">

                <div class="leave-form-grid">
                    <div class="leave-form-group">
                        <label>Employee *</label>
                        <select name="leave_employee_id" id="leaveEmployeeSelect" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"
                                        <?= (isset($_POST['leave_employee_id']) && $_POST['leave_employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                    (<?= htmlspecialchars($emp['emp_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="leave-form-group">
                        <label>Leave Type *</label>
                        <select name="leave_type_id" id="leaveTypeSelect" required>
                            <option value="">-- Select Type --</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= $lt['id'] ?>"
                                        data-max="<?= $lt['max_days_per_year'] ?>"
                                        <?= (isset($_POST['leave_type_id']) && $_POST['leave_type_id'] == $lt['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lt['leave_code'] . ' - ' . $lt['leave_type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="leave-form-group">
                        <label>Start Date *</label>
                        <input type="date" name="leave_start_date" id="leaveStartDate" required
                               value="<?= htmlspecialchars($_POST['leave_start_date'] ?? $date) ?>"
                               onchange="calculateLeaveDays()">
                    </div>

                    <div class="leave-form-group">
                        <label>End Date *</label>
                        <input type="date" name="leave_end_date" id="leaveEndDate" required
                               value="<?= htmlspecialchars($_POST['leave_end_date'] ?? $date) ?>"
                               onchange="calculateLeaveDays()">
                    </div>
                </div>

                <div class="leave-form-group" style="margin-top: 15px;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_half_day" id="halfDayCheck"
                               <?= isset($_POST['is_half_day']) ? 'checked' : '' ?>
                               onchange="toggleHalfDayOptions()">
                        Half Day Leave
                    </label>
                    <div id="halfDayOptions" class="half-day-options <?= isset($_POST['is_half_day']) ? 'show' : '' ?>">
                        <label>
                            <input type="radio" name="half_day_type" value="First Half"
                                   <?= (isset($_POST['half_day_type']) && $_POST['half_day_type'] === 'First Half') ? 'checked' : '' ?>>
                            First Half (Morning)
                        </label>
                        <label>
                            <input type="radio" name="half_day_type" value="Second Half"
                                   <?= (isset($_POST['half_day_type']) && $_POST['half_day_type'] === 'Second Half') ? 'checked' : '' ?>>
                            Second Half (Afternoon)
                        </label>
                    </div>
                </div>

                <div id="leaveDaysPreview" class="leave-days-preview">
                    Total Days: <strong id="leaveTotalDays">0</strong>
                    <span id="leaveDaysBreakdown" style="font-size: 0.9em; color: #666;"></span>
                </div>

                <div class="leave-form-group" style="margin-top: 15px;">
                    <label>Reason *</label>
                    <textarea name="leave_reason" required placeholder="Please provide a reason for your leave request..."><?= htmlspecialchars($_POST['leave_reason'] ?? '') ?></textarea>
                </div>

                <div class="leave-submit-row">
                    <button type="submit" class="btn btn-primary">Submit Leave Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

// Leave Application Functions
<?php if (!empty($leaveTypes)): ?>
const leaveHolidays = <?= json_encode($holidayDates) ?>;

function toggleLeaveForm() {
    const header = document.querySelector('.leave-apply-header');
    const body = document.getElementById('leaveFormBody');
    header.classList.toggle('collapsed');
    body.classList.toggle('collapsed');
}

function toggleHalfDayOptions() {
    const checked = document.getElementById('halfDayCheck').checked;
    document.getElementById('halfDayOptions').classList.toggle('show', checked);

    if (checked) {
        document.getElementById('leaveEndDate').value = document.getElementById('leaveStartDate').value;
    }

    calculateLeaveDays();
}

function calculateLeaveDays() {
    const startDateEl = document.getElementById('leaveStartDate');
    const endDateEl = document.getElementById('leaveEndDate');
    const halfDayCheck = document.getElementById('halfDayCheck');
    const preview = document.getElementById('leaveDaysPreview');

    if (!startDateEl || !endDateEl || !preview) return;

    const startVal = startDateEl.value;
    const endVal = endDateEl.value;
    const isHalfDay = halfDayCheck ? halfDayCheck.checked : false;

    if (!startVal || !endVal) {
        preview.classList.remove('show');
        return;
    }

    if (isHalfDay) {
        document.getElementById('leaveTotalDays').textContent = '0.5';
        document.getElementById('leaveDaysBreakdown').textContent = ' (Half day)';
        preview.classList.add('show');
        return;
    }

    const start = new Date(startVal);
    const end = new Date(endVal);

    if (end < start) {
        preview.classList.remove('show');
        return;
    }

    let workingDays = 0;
    let sundayCount = 0;
    let holidayCount = 0;
    const current = new Date(start);

    while (current <= end) {
        const dayOfWeek = current.getDay();
        const dateStr = current.toISOString().split('T')[0];

        if (dayOfWeek === 0) {
            sundayCount++;
        } else if (leaveHolidays.includes(dateStr)) {
            holidayCount++;
        } else {
            workingDays++;
        }

        current.setDate(current.getDate() + 1);
    }

    document.getElementById('leaveTotalDays').textContent = workingDays;

    let breakdown = '';
    if (sundayCount > 0 || holidayCount > 0) {
        breakdown = ' (excluding ';
        const parts = [];
        if (sundayCount > 0) parts.push(sundayCount + ' Sunday' + (sundayCount > 1 ? 's' : ''));
        if (holidayCount > 0) parts.push(holidayCount + ' holiday' + (holidayCount > 1 ? 's' : ''));
        breakdown += parts.join(', ') + ')';
    }
    document.getElementById('leaveDaysBreakdown').textContent = breakdown;

    preview.classList.add('show');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateLeaveDays();

    // If there are leave errors, expand the form
    <?php if (!empty($leaveErrors)): ?>
    const header = document.querySelector('.leave-apply-header');
    const body = document.getElementById('leaveFormBody');
    if (header && body) {
        header.classList.remove('collapsed');
        body.classList.remove('collapsed');
    }
    <?php endif; ?>
});
<?php endif; ?>
</script>

</body>
</html>
