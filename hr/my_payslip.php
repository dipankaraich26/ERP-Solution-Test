<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

include "../db.php";

// Check employee portal auth
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

// Fetch available payslip months (only Approved/Paid)
$monthsStmt = $pdo->prepare("
    SELECT id, payroll_month, status
    FROM payroll
    WHERE employee_id = ? AND status IN ('Approved', 'Paid')
    ORDER BY payroll_month DESC
");
$monthsStmt->execute([$empId]);
$availableMonths = $monthsStmt->fetchAll(PDO::FETCH_ASSOC);

// Selected month
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$selectedId && !empty($availableMonths)) {
    $selectedId = $availableMonths[0]['id'];
}

$payroll = null;
$leaveBalances = [];
$settings = [];

if ($selectedId) {
    // Fetch payroll data - only if it belongs to this employee and is Approved/Paid
    $empCols = $pdo->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    $extraCols = '';
    foreach (['date_of_joining', 'uan_number', 'esi_number'] as $col) {
        if (in_array($col, $empCols)) {
            $extraCols .= ", e.$col";
        }
    }

    $stmt = $pdo->prepare("
        SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation,
               e.bank_name, e.bank_account, e.bank_ifsc $extraCols
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.id = ? AND p.employee_id = ? AND p.status IN ('Approved', 'Paid')
    ");
    $stmt->execute([$selectedId, $empId]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payroll) {
        $settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch leave balances
        try {
            $lbTableCheck = $pdo->query("SHOW TABLES LIKE 'leave_balances'")->fetch();
            $ltTableCheck = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();
            if ($lbTableCheck && $ltTableCheck) {
                $payrollYear = date('Y', strtotime($payroll['payroll_month']));
                $lbStmt = $pdo->prepare("
                    SELECT lt.leave_code, lt.leave_type_name, lb.allocated, lb.used, lb.balance
                    FROM leave_balances lb
                    JOIN leave_types lt ON lt.id = lb.leave_type_id
                    WHERE lb.employee_id = ? AND lb.year = ? AND lt.is_active = 1
                    ORDER BY lt.leave_code
                ");
                $lbStmt->execute([$empId, $payrollYear]);
                $leaveBalances = $lbStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {}
    }
}

$monthName = $payroll ? date('F Y', strtotime($payroll['payroll_month'])) : '';

// Number to words (Indian format)
function numberToWords($number) {
    $number = round($number, 2);
    $whole = (int)$number;
    $fraction = round(($number - $whole) * 100);

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $convert = function($n) use ($ones, $tens, &$convert) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        if ($n < 1000) return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' and ' . $convert($n % 100) : '');
        if ($n < 100000) return $convert((int)($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
        if ($n < 10000000) return $convert((int)($n / 100000)) . ' Lakh' . ($n % 100000 ? ' ' . $convert($n % 100000) : '');
        return $convert((int)($n / 10000000)) . ' Crore' . ($n % 10000000 ? ' ' . $convert($n % 10000000) : '');
    };

    $result = 'Rupees ' . $convert($whole);
    if ($fraction > 0) {
        $result .= ' and ' . $convert($fraction) . ' Paise';
    }
    return $result;
}

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
    <title>My Payslip<?= $monthName ? " - $monthName" : '' ?></title>
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
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
        .header-links { display: flex; gap: 10px; align-items: center; }
        .header-btn {
            background: rgba(255,255,255,0.2); color: white; border: none;
            padding: 10px 18px; border-radius: 8px; cursor: pointer;
            font-size: 0.9em; text-decoration: none; display: inline-block;
        }
        .header-btn:hover { background: rgba(255,255,255,0.3); }

        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }

        /* Month Selector */
        .month-selector {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08); margin-bottom: 20px;
            display: flex; align-items: center; gap: 15px; flex-wrap: wrap;
        }
        .month-selector label { font-weight: 600; color: #2c3e50; font-size: 1em; }
        .month-selector select {
            padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px;
            font-size: 1em; min-width: 250px; cursor: pointer;
        }
        .month-selector select:focus {
            border-color: #667eea; outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        /* Payslip Styles */
        .form-container { max-width: 100%; margin: 0; }
        .landscape-grid {
            display: grid; grid-template-columns: 1fr 2fr 1fr;
            gap: 20px; margin-bottom: 20px;
        }
        .form-card {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-card h3 {
            margin: 0 0 15px 0; color: #2c3e50;
            padding-bottom: 8px; border-bottom: 2px solid #667eea; font-size: 1.1em;
        }
        .form-card h3.earnings { border-bottom-color: #27ae60; }
        .form-card h3.deductions { border-bottom-color: #c0392b; }

        .val-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .val-group { margin-bottom: 0; }
        .val-group label {
            display: block; font-weight: 600; margin-bottom: 4px;
            color: #495057; font-size: 0.85em;
        }
        .val-group .value {
            padding: 8px 10px; background: #f8f9fa; border: 1px solid #e9ecef;
            border-radius: 6px; font-size: 0.95em;
            font-family: 'Consolas', monospace; color: #333;
        }

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px; border-radius: 8px; margin-top: 15px; color: white;
        }
        .summary-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.2); font-size: 0.95em;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row.total {
            font-weight: 700; font-size: 1.1em; color: #fff;
            padding-top: 10px; margin-top: 5px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        .emp-info-card {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px;
        }
        .emp-info-card h3 {
            margin: 0 0 15px 0; color: #2c3e50;
            padding-bottom: 8px; border-bottom: 2px solid #667eea; font-size: 1.1em;
        }
        .emp-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }

        .netpay-bar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white; padding: 15px 20px; border-radius: 10px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .netpay-bar .lbl { font-size: 14px; font-weight: 600; }
        .netpay-bar .amt { font-size: 22px; font-weight: bold; font-family: 'Consolas', monospace; }
        .netpay-bar .words { font-size: 11px; opacity: 0.8; font-style: italic; margin-top: 3px; }

        .payment-info {
            background: #eafaf1; padding: 12px 20px; border-radius: 10px;
            font-size: 0.9em; margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .status-badge {
            display: inline-block; padding: 3px 12px; border-radius: 12px;
            font-weight: bold; font-size: 11px;
        }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Paid { background: #d1ecf1; color: #0c5460; }

        .leave-balance-card {
            background: white; padding: 15px 20px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px;
        }
        .leave-balance-card h3 {
            margin: 0 0 12px 0; color: #2c3e50;
            padding-bottom: 8px; border-bottom: 2px solid #f39c12; font-size: 1.1em;
        }
        .leave-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .leave-chip {
            background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;
            padding: 8px 14px; font-size: 0.9em;
            display: flex; align-items: center; gap: 8px;
        }
        .leave-chip .lc-code { font-weight: 700; color: #2c3e50; }
        .leave-chip .lc-bal { font-family: 'Consolas', monospace; font-weight: 600; color: #27ae60; }
        .leave-chip .lc-bal.low { color: #e67e22; }
        .leave-chip .lc-bal.zero { color: #c0392b; }
        .leave-chip .lc-detail { font-size: 0.8em; color: #888; }

        .no-payslip {
            background: white; border-radius: 12px; padding: 40px;
            text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .no-payslip .icon { font-size: 3em; margin-bottom: 15px; }
        .no-payslip h3 { color: #2c3e50; margin-bottom: 10px; }
        .no-payslip p { color: #7f8c8d; }

        /* Print Styles */
        @media print {
            @page { size: landscape; margin: 6mm; }
            * { box-sizing: border-box; }
            .portal-header, .month-selector, .no-print { display: none !important; }
            body { background: white; font-size: 13px; }
            .container { max-width: 100%; padding: 0; }
            .form-container { max-width: 100%; }

            .form-card, .emp-info-card, .payment-info {
                box-shadow: none; border: 1px solid #ddd; border-radius: 8px;
            }

            .print-company-header {
                display: flex !important; align-items: center;
                padding: 8px 15px; border: 1px solid #ddd;
                border-radius: 8px; margin-bottom: 8px;
            }
            .print-company-header img { max-height: 40px; margin-right: 12px; }
            .print-company-header .name { font-size: 18px; font-weight: bold; }
            .print-company-header .addr { font-size: 11px; color: #555; }
            .print-company-header .title { font-size: 16px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
            .print-company-header .month { font-size: 12px; color: #555; }

            .emp-info-card { padding: 10px 15px; margin-bottom: 8px; }
            .emp-info-card h3 { margin-bottom: 8px; padding-bottom: 5px; font-size: 1.05em; }
            .emp-grid { gap: 8px; }
            .val-group label { font-size: 0.85em; margin-bottom: 2px; }
            .val-group .value {
                padding: 5px 8px; font-size: 0.95em; border-radius: 5px;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
                background: #f8f9fa; border: 1px solid #e9ecef;
            }

            .landscape-grid {
                grid-template-columns: 1fr 2fr 1fr; gap: 8px; margin-bottom: 8px;
            }
            .form-card { padding: 10px 12px; border-radius: 8px; }
            .form-card h3 {
                margin: 0 0 8px 0; padding-bottom: 5px; font-size: 1.05em;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .val-grid { gap: 8px; }

            .summary-box {
                padding: 8px 10px; margin-top: 8px; border-radius: 6px;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .summary-row { padding: 3px 0; font-size: 0.95em; }
            .summary-row.total { font-size: 1.05em; padding-top: 5px; margin-top: 3px; }

            .netpay-bar {
                padding: 8px 15px; margin-bottom: 8px; border-radius: 8px;
                box-shadow: none; border: none;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .netpay-bar .lbl { font-size: 14px; }
            .netpay-bar .amt { font-size: 20px; }
            .netpay-bar .words { font-size: 11px; }

            .payment-info { padding: 6px 15px; margin-bottom: 6px; font-size: 0.95em; }

            .leave-balance-card { padding: 8px 12px; margin-bottom: 6px; border: 1px solid #ddd; border-radius: 8px; box-shadow: none; }
            .leave-balance-card h3 { margin: 0 0 6px 0; padding-bottom: 4px; font-size: 1em; }
            .leave-grid { gap: 6px; }
            .leave-chip {
                padding: 5px 10px; font-size: 0.9em; border-radius: 4px;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }

            .print-gen-note { display: block !important; text-align: center; font-size: 10px; color: #999; margin-top: 4px; }
        }

        .print-company-header { display: none; }
        .print-gen-note { display: none; }

        @media (max-width: 1200px) {
            .landscape-grid { grid-template-columns: 1fr 1fr; }
            .emp-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .landscape-grid { grid-template-columns: 1fr; }
            .emp-grid { grid-template-columns: repeat(2, 1fr); }
            .month-selector { flex-direction: column; align-items: stretch; }
            .month-selector select { min-width: auto; width: 100%; }
        }
        @media (max-width: 600px) {
            .portal-header { padding: 15px; }
            .user-details h2 { font-size: 1em; }
            .header-links { flex-wrap: wrap; }
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
        <a href="my_calendar.php" class="header-btn">Calendar</a>
        <?php if ($payroll): ?>
            <button onclick="window.print()" class="header-btn">Print Payslip</button>
        <?php endif; ?>
        <a href="?logout=1" class="header-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- Month Selector -->
    <div class="month-selector no-print">
        <label>Select Month:</label>
        <?php if (empty($availableMonths)): ?>
            <span style="color: #7f8c8d;">No payslips available yet</span>
        <?php else: ?>
            <select onchange="if(this.value) window.location.href='?id='+this.value">
                <?php foreach ($availableMonths as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $selectedId ? 'selected' : '' ?>>
                        <?= date('F Y', strtotime($m['payroll_month'])) ?>
                        <?= $m['status'] === 'Paid' ? '(Paid)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>

    <?php if ($payroll): ?>
    <div class="form-container">

        <!-- Print-only Company Header -->
        <div class="print-company-header">
            <?php if (!empty($settings['logo_path'])): ?>
                <img src="/<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo">
            <?php endif; ?>
            <div style="flex: 1;">
                <div class="name"><?= htmlspecialchars($settings['company_name'] ?? 'Company Name') ?></div>
                <div class="addr">
                    <?= htmlspecialchars(implode(', ', array_filter([
                        $settings['address_line1'] ?? '',
                        $settings['city'] ?? '',
                        $settings['state'] ?? '',
                        $settings['pincode'] ?? ''
                    ]))) ?>
                    <?php if (!empty($settings['gstin'])): ?>
                        &nbsp;|&nbsp; GSTIN: <?= htmlspecialchars($settings['gstin']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div class="title">Payslip</div>
                <div class="month"><?= $monthName ?></div>
            </div>
        </div>

        <!-- Employee Info -->
        <div class="emp-info-card">
            <h3>Employee Information</h3>
            <div class="emp-grid">
                <div class="val-group">
                    <label>Employee ID</label>
                    <div class="value"><?= htmlspecialchars($payroll['emp_id']) ?></div>
                </div>
                <div class="val-group">
                    <label>Name</label>
                    <div class="value"><?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?></div>
                </div>
                <div class="val-group">
                    <label>Department</label>
                    <div class="value"><?= htmlspecialchars($payroll['department'] ?? '-') ?></div>
                </div>
                <div class="val-group">
                    <label>Designation</label>
                    <div class="value"><?= htmlspecialchars($payroll['designation'] ?? '-') ?></div>
                </div>
                <div class="val-group">
                    <label>Bank A/C</label>
                    <div class="value"><?= htmlspecialchars($payroll['bank_account'] ?? '-') ?></div>
                </div>
                <div class="val-group">
                    <label>Bank / IFSC</label>
                    <div class="value"><?= htmlspecialchars(($payroll['bank_name'] ?? '-') . (!empty($payroll['bank_ifsc']) ? ' / ' . $payroll['bank_ifsc'] : '')) ?></div>
                </div>
                <div class="val-group">
                    <label>UAN</label>
                    <div class="value"><?= htmlspecialchars($payroll['uan_number'] ?? '-') ?></div>
                </div>
                <div class="val-group">
                    <label>Date of Joining</label>
                    <div class="value"><?= !empty($payroll['date_of_joining']) ? date('d M Y', strtotime($payroll['date_of_joining'])) : '-' ?></div>
                </div>
            </div>
        </div>

        <!-- 3-Column Grid: Attendance | Earnings | Deductions+Summary -->
        <div class="landscape-grid">
            <!-- Left: Attendance -->
            <div class="form-card">
                <h3>Attendance</h3>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Working Days</label>
                    <div class="value"><?= $payroll['working_days'] ?></div>
                </div>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Days Present</label>
                    <div class="value"><?= $payroll['days_present'] ?></div>
                </div>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Days Absent</label>
                    <div class="value"><?= $payroll['days_absent'] ?? 0 ?></div>
                </div>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Leaves Taken</label>
                    <div class="value"><?= $payroll['leaves_taken'] ?? 0 ?></div>
                </div>
                <div class="val-group">
                    <label>Holidays</label>
                    <div class="value"><?= $payroll['holidays'] ?? 0 ?></div>
                </div>
            </div>

            <!-- Middle: Earnings -->
            <div class="form-card">
                <h3 class="earnings">Earnings</h3>
                <div class="val-grid">
                    <div class="val-group">
                        <label>Basic Salary</label>
                        <div class="value"><?= number_format($payroll['basic_salary'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>HRA</label>
                        <div class="value"><?= number_format($payroll['hra'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Conveyance</label>
                        <div class="value"><?= number_format($payroll['conveyance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Medical Allowance</label>
                        <div class="value"><?= number_format($payroll['medical_allowance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Special Allowance</label>
                        <div class="value"><?= number_format($payroll['special_allowance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Other Allowance</label>
                        <div class="value"><?= number_format($payroll['other_allowance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Performance Allowance</label>
                        <div class="value"><?= number_format($payroll['performance_allowance'] ?? 0, 2) ?></div>
                    </div>
                    <div class="val-group">
                        <label>Food Allowance</label>
                        <div class="value"><?= number_format($payroll['food_allowance'] ?? 0, 2) ?></div>
                    </div>
                    <?php if (($payroll['overtime_pay'] ?? 0) > 0): ?>
                    <div class="val-group">
                        <label>Overtime</label>
                        <div class="value"><?= number_format($payroll['overtime_pay'], 2) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (($payroll['bonus'] ?? 0) > 0): ?>
                    <div class="val-group">
                        <label>Bonus</label>
                        <div class="value"><?= number_format($payroll['bonus'], 2) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Deductions + Summary -->
            <div class="form-card">
                <h3 class="deductions">Deductions</h3>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>PF (Employee)</label>
                    <div class="value"><?= number_format($payroll['pf_employee'], 2) ?></div>
                </div>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>ESI (Employee)</label>
                    <div class="value"><?= number_format($payroll['esi_employee'], 2) ?></div>
                </div>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Professional Tax</label>
                    <div class="value"><?= number_format($payroll['professional_tax'], 2) ?></div>
                </div>
                <?php if (($payroll['tds'] ?? 0) > 0): ?>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>TDS</label>
                    <div class="value"><?= number_format($payroll['tds'], 2) ?></div>
                </div>
                <?php endif; ?>
                <?php if (($payroll['loan_deduction'] ?? 0) > 0): ?>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Loan Deduction</label>
                    <div class="value"><?= number_format($payroll['loan_deduction'], 2) ?></div>
                </div>
                <?php endif; ?>
                <?php if (($payroll['other_deduction'] ?? 0) > 0): ?>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Other Deductions</label>
                    <div class="value"><?= number_format($payroll['other_deduction'], 2) ?></div>
                </div>
                <?php endif; ?>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Gross Earnings</span>
                        <span><?= number_format($payroll['gross_earnings'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Total Deductions</span>
                        <span>- <?= number_format($payroll['total_deductions'], 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Net Pay</span>
                        <span><?= number_format($payroll['net_pay'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Pay Bar -->
        <div class="netpay-bar">
            <div>
                <div class="lbl">Net Pay</div>
                <div class="words"><?= numberToWords($payroll['net_pay']) ?> Only</div>
            </div>
            <div class="amt"><?= number_format($payroll['net_pay'], 2) ?></div>
        </div>

        <?php if ($payroll['status'] === 'Paid'): ?>
        <div class="payment-info">
            <strong>Payment:</strong>
            <?= $payroll['payment_date'] ? date('d M Y', strtotime($payroll['payment_date'])) : '-' ?> |
            <?= htmlspecialchars($payroll['payment_mode'] ?? '-') ?>
            <?php if ($payroll['transaction_ref']): ?>
                | Ref: <?= htmlspecialchars($payroll['transaction_ref']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Leave Balance -->
        <?php if (!empty($leaveBalances)): ?>
        <div class="leave-balance-card">
            <h3>Available Leaves (<?= date('Y', strtotime($payroll['payroll_month'])) ?>)</h3>
            <div class="leave-grid">
                <?php foreach ($leaveBalances as $lb):
                    $bal = (float)$lb['balance'];
                    $balClass = $bal <= 0 ? 'zero' : ($bal <= 3 ? 'low' : '');
                ?>
                <div class="leave-chip">
                    <span class="lc-code"><?= htmlspecialchars($lb['leave_code']) ?></span>
                    <span class="lc-bal <?= $balClass ?>"><?= number_format($bal, 1) ?></span>
                    <span class="lc-detail">/<?= number_format((float)$lb['allocated'], 0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="print-gen-note">This is a computer-generated payslip and does not require a signature.</div>

    </div>

    <?php elseif (empty($availableMonths)): ?>
    <!-- No payslips at all -->
    <div class="no-payslip">
        <div class="icon">📄</div>
        <h3>No Payslips Available</h3>
        <p>Your payslips will appear here once they are processed by HR.</p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
