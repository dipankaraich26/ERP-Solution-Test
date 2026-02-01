<?php
/**
 * Apply for Leave
 * Form to submit leave request
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$errors = [];
$currentYear = date('Y');
$tableError = false;

// Check if required tables exist with proper structure
$leaveTypesOk = false;
$leaveRequestsOk = false;
$leaveBalancesOk = false;

try {
    // Check leave_types table
    $check = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
        $leaveTypesOk = in_array('leave_code', $cols) && in_array('is_active', $cols);
    }

    // Check leave_requests table
    $check = $pdo->query("SHOW TABLES LIKE 'leave_requests'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(PDO::FETCH_COLUMN);
        $leaveRequestsOk = in_array('start_date', $cols) && in_array('end_date', $cols) && in_array('status', $cols);
    }

    // Check leave_balances table
    $check = $pdo->query("SHOW TABLES LIKE 'leave_balances'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_balances")->fetchAll(PDO::FETCH_COLUMN);
        $leaveBalancesOk = in_array('balance', $cols) && in_array('employee_id', $cols);
    }
} catch (PDOException $e) {
    // Tables don't exist or error
}

$tableError = !$leaveTypesOk || !$leaveRequestsOk || !$leaveBalancesOk;

// Fetch active employees
$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active leave types only if table is OK
$leaveTypes = [];
if ($leaveTypesOk) {
    try {
        $leaveTypes = $pdo->query("
            SELECT * FROM leave_types
            WHERE is_active = 1
            ORDER BY leave_code
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableError = true;
    }
}

// Fetch holidays for the year
$holidays = $pdo->prepare("SELECT holiday_date FROM holidays WHERE year = ?");
$holidays->execute([$currentYear]);
$holidayDates = $holidays->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tableError) {
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    $half_day_type = $_POST['half_day_type'] ?? null;
    $reason = trim($_POST['reason'] ?? '');

    // Validation
    if (!$employee_id) $errors[] = "Please select an employee";
    if (!$leave_type_id) $errors[] = "Please select a leave type";
    if (!$start_date) $errors[] = "Start date is required";
    if (!$end_date) $errors[] = "End date is required";
    if (!$reason) $errors[] = "Reason is required";

    // Date validation
    if ($start_date && $end_date) {
        $startDt = new DateTime($start_date);
        $endDt = new DateTime($end_date);

        if ($endDt < $startDt) {
            $errors[] = "End date cannot be before start date";
        }

        // Calculate working days
        $totalDays = 0;
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

        if ($is_half_day) {
            $totalDays = 0.5;
            if (!$half_day_type) {
                $errors[] = "Please select First Half or Second Half";
            }
        }

        if ($totalDays <= 0) {
            $errors[] = "Selected dates have no working days";
        }
    }

    // Check leave balance
    if (empty($errors) && $employee_id && $leave_type_id) {
        $balanceCheck = $pdo->prepare("
            SELECT lb.balance, lt.leave_type_name, lt.max_days_per_year
            FROM leave_balances lb
            JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = ? AND lb.leave_type_id = ? AND lb.year = ?
        ");
        $balanceCheck->execute([$employee_id, $leave_type_id, $currentYear]);
        $balanceData = $balanceCheck->fetch(PDO::FETCH_ASSOC);

        // Get leave type info
        $leaveTypeInfo = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
        $leaveTypeInfo->execute([$leave_type_id]);
        $ltInfo = $leaveTypeInfo->fetch(PDO::FETCH_ASSOC);

        // Only check balance for leave types with limits
        if ($ltInfo && $ltInfo['max_days_per_year'] > 0) {
            $availableBalance = $balanceData['balance'] ?? $ltInfo['max_days_per_year'];

            if ($totalDays > $availableBalance) {
                $errors[] = "Insufficient leave balance. Available: $availableBalance days, Requested: $totalDays days";
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
            $errors[] = "Leave request overlaps with an existing pending or approved leave";
        }
    }

    // Insert leave request
    if (empty($errors)) {
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
            header("Location: leaves.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply for Leave - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .apply-form {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .half-day-options {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .half-day-options.show { display: block; }
        .half-day-options label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 20px;
            font-weight: normal;
            cursor: pointer;
        }
        .balance-info {
            background: #e8f4fd;
            border: 1px solid #b8daff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .balance-info.show { display: block; }
        .balance-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #0056b3;
        }
        .days-preview {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .days-preview.show { display: block; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Apply for Leave</h1>
        <a href="leaves.php" class="btn btn-secondary">View All Requests</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #721c24;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($tableError): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404;">
            <strong>Setup Required</strong><br>
            Leave management tables are not properly configured.<br><br>
            <a href="/admin/setup_leave_management.php" class="btn btn-primary">Run Setup Script</a>
        </div>
    <?php elseif (empty($leaveTypes)): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; color: #856404;">
            No leave types configured. <a href="/admin/setup_leave_management.php">Run setup</a> first.
        </div>
    <?php else: ?>
        <div class="apply-form">
            <form method="POST" id="leaveForm">
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="employee_id" id="employeeSelect" required onchange="loadBalance()">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"
                                    <?= (isset($_POST['employee_id']) && $_POST['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['emp_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                                <?= $emp['department'] ? ' (' . $emp['department'] . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Leave Type *</label>
                    <select name="leave_type_id" id="leaveTypeSelect" required onchange="loadBalance()">
                        <option value="">-- Select Leave Type --</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>"
                                    data-max="<?= $lt['max_days_per_year'] ?>"
                                    data-approval="<?= $lt['requires_approval'] ?>"
                                    <?= (isset($_POST['leave_type_id']) && $_POST['leave_type_id'] == $lt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lt['leave_code'] . ' - ' . $lt['leave_type_name']) ?>
                                <?= $lt['max_days_per_year'] ? ' (' . $lt['max_days_per_year'] . ' days/year)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="balanceInfo" class="balance-info">
                    <span>Available Balance: </span>
                    <span id="balanceValue" class="balance-value">-</span>
                    <span> days</span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" id="startDate" required
                               value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>"
                               onchange="calculateDays()">
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="end_date" id="endDate" required
                               value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>"
                               onchange="calculateDays()">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_half_day" id="halfDayCheck"
                               <?= isset($_POST['is_half_day']) ? 'checked' : '' ?>
                               onchange="toggleHalfDay()">
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

                <div id="daysPreview" class="days-preview">
                    Total Days: <strong id="totalDaysValue">0</strong>
                    <span id="daysBreakdown" style="font-size: 0.9em; color: #666;"></span>
                </div>

                <div class="form-group">
                    <label>Reason *</label>
                    <textarea name="reason" required placeholder="Please provide a reason for your leave request..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Submit Leave Request</button>
                    <a href="leaves.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
const holidays = <?= json_encode($holidayDates) ?>;
const balances = {};

// Pre-load balances via AJAX (simplified - using data attributes for now)
function loadBalance() {
    const empId = document.getElementById('employeeSelect').value;
    const leaveTypeId = document.getElementById('leaveTypeSelect').value;
    const balanceInfo = document.getElementById('balanceInfo');
    const balanceValue = document.getElementById('balanceValue');

    if (empId && leaveTypeId) {
        // For now, show max days from select option
        const option = document.querySelector('#leaveTypeSelect option:checked');
        const maxDays = option.dataset.max || 0;

        if (maxDays > 0) {
            balanceValue.textContent = maxDays;
            balanceInfo.classList.add('show');
        } else {
            balanceValue.textContent = 'Unlimited';
            balanceInfo.classList.add('show');
        }
    } else {
        balanceInfo.classList.remove('show');
    }

    calculateDays();
}

function toggleHalfDay() {
    const checked = document.getElementById('halfDayCheck').checked;
    document.getElementById('halfDayOptions').classList.toggle('show', checked);

    if (checked) {
        document.getElementById('endDate').value = document.getElementById('startDate').value;
    }

    calculateDays();
}

function calculateDays() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const isHalfDay = document.getElementById('halfDayCheck').checked;
    const preview = document.getElementById('daysPreview');

    if (!startDate || !endDate) {
        preview.classList.remove('show');
        return;
    }

    if (isHalfDay) {
        document.getElementById('totalDaysValue').textContent = '0.5';
        document.getElementById('daysBreakdown').textContent = ' (Half day)';
        preview.classList.add('show');
        return;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

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
        } else if (holidays.includes(dateStr)) {
            holidayCount++;
        } else {
            workingDays++;
        }

        current.setDate(current.getDate() + 1);
    }

    document.getElementById('totalDaysValue').textContent = workingDays;

    let breakdown = '';
    if (sundayCount > 0 || holidayCount > 0) {
        breakdown = ' (excluding ';
        const parts = [];
        if (sundayCount > 0) parts.push(sundayCount + ' Sunday' + (sundayCount > 1 ? 's' : ''));
        if (holidayCount > 0) parts.push(holidayCount + ' holiday' + (holidayCount > 1 ? 's' : ''));
        breakdown += parts.join(', ') + ')';
    }
    document.getElementById('daysBreakdown').textContent = breakdown;

    preview.classList.add('show');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadBalance();
    calculateDays();
});
</script>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
