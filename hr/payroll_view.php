<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: payroll.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $validStatuses = ['Draft', 'Processed', 'Approved', 'Paid'];

        if (in_array($newStatus, $validStatuses)) {
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'Paid') {
                $updateData['payment_date'] = $_POST['payment_date'] ?? date('Y-m-d');
                $updateData['payment_mode'] = $_POST['payment_mode'] ?? 'Bank Transfer';
                $updateData['transaction_ref'] = $_POST['transaction_ref'] ?? null;
            }

            $setClauses = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $setClauses[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $id;

            $pdo->prepare("UPDATE payroll SET " . implode(', ', $setClauses) . " WHERE id = ?")->execute($params);
            setModal("Success", "Status updated to $newStatus");
        }
        header("Location: payroll_view.php?id=$id");
        exit;
    }
}

// Fetch payroll - detect available employee columns
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
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payroll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payroll) {
    header("Location: payroll.php");
    exit;
}

$monthName = date('F Y', strtotime($payroll['payroll_month']));
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

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

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payslip - <?= htmlspecialchars($payroll['emp_id']) ?> - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 100%; margin: 0; }
        .landscape-grid {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            font-size: 1.1em;
        }
        .form-card h3.earnings { border-bottom-color: #27ae60; }
        .form-card h3.deductions { border-bottom-color: #c0392b; }

        .val-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .val-group { margin-bottom: 0; }
        .val-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: #495057;
            font-size: 0.85em;
        }
        .val-group .value {
            padding: 8px 10px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: 'Consolas', monospace;
            color: #333;
        }

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            color: white;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: 0.95em;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row.total {
            font-weight: 700;
            font-size: 1.1em;
            color: #fff;
            padding-top: 10px;
            margin-top: 5px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .header-bar h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.4em;
        }
        .header-bar p {
            color: #666;
            margin: 3px 0 0;
            font-size: 0.9em;
        }

        .emp-info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .emp-info-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            font-size: 1.1em;
        }
        .emp-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .netpay-bar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .netpay-bar .lbl { font-size: 14px; font-weight: 600; }
        .netpay-bar .amt { font-size: 22px; font-weight: bold; font-family: 'Consolas', monospace; }
        .netpay-bar .words { font-size: 11px; opacity: 0.8; font-style: italic; margin-top: 3px; }

        .payment-info {
            background: #eafaf1;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.9em;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-Draft { background: #fff3cd; color: #856404; }
        .status-Processed { background: #cce5ff; color: #004085; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Paid { background: #d1ecf1; color: #0c5460; }

        .status-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .status-form h3 { margin: 0 0 15px 0; color: #2c3e50; }
        .status-form select, .status-form input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }

        body.dark .form-card, body.dark .header-bar, body.dark .emp-info-card,
        body.dark .status-form { background: #2c3e50; }
        body.dark .form-card h3, body.dark .header-bar h1,
        body.dark .emp-info-card h3, body.dark .status-form h3 { color: #ecf0f1; }
        body.dark .val-group .value { background: #34495e; border-color: #4a6274; color: #ecf0f1; }

        /* Print Styles */
        @media print {
            @page { size: landscape; margin: 8mm; }
            .sidebar, .topbar, .status-form, .no-print { display: none !important; }
            .content { margin-left: 0 !important; padding: 0 !important; }
            body { background: white; }
            .header-bar, .form-card, .emp-info-card, .netpay-bar, .payment-info {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            .summary-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .netpay-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-company-header {
                display: flex !important;
                align-items: center;
                padding: 12px 20px;
                border: 1px solid #ddd;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            .print-company-header img { max-height: 50px; margin-right: 15px; }
            .print-company-header .name { font-size: 18px; font-weight: bold; }
            .print-company-header .addr { font-size: 11px; color: #555; }
            .print-company-header .title { font-size: 16px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }
            .print-company-header .month { font-size: 13px; color: #555; }
            .print-footer { display: flex !important; }
        }

        .print-company-header { display: none; }
        .print-footer { display: none; justify-content: space-between; align-items: flex-end; padding: 20px; margin-top: 20px; font-size: 11px; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; }

        @media (max-width: 1200px) {
            .landscape-grid { grid-template-columns: 1fr 1fr; }
            .emp-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .landscape-grid { grid-template-columns: 1fr; }
            .emp-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="content">
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

        <!-- Header Bar -->
        <div class="header-bar">
            <div>
                <h1>Payslip</h1>
                <p>
                    <?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?>
                    (<?= htmlspecialchars($payroll['emp_id']) ?>) -
                    <?= $monthName ?>
                    <span class="status-badge status-<?= $payroll['status'] ?>" style="margin-left: 10px;"><?= $payroll['status'] ?></span>
                </p>
            </div>
            <div class="no-print" style="display: flex; gap: 10px; align-items: center;">
                <a href="payroll.php?month=<?= substr($payroll['payroll_month'], 0, 7) ?>" class="btn btn-secondary">Back to Payroll</a>
                <?php if ($payroll['status'] === 'Draft'): ?>
                    <a href="payroll_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-secondary">Print</button>
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
                <?php if ($payroll['tds'] > 0): ?>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>TDS</label>
                    <div class="value"><?= number_format($payroll['tds'], 2) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($payroll['loan_deduction'] > 0): ?>
                <div class="val-group" style="margin-bottom: 12px;">
                    <label>Loan Deduction</label>
                    <div class="value"><?= number_format($payroll['loan_deduction'], 2) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($payroll['other_deduction'] > 0): ?>
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

        <!-- Print-only footer with signatures -->
        <div class="print-footer">
            <div style="font-size: 10px; color: #999; align-self: flex-end;">
                This is a computer-generated payslip.
            </div>
            <div class="sig-box">
                <div class="sig-line">Employer Signature</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">Employee Signature</div>
            </div>
        </div>

        <!-- Status Update Form (screen only) -->
        <?php if ($payroll['status'] !== 'Paid'): ?>
        <div class="status-form no-print">
            <h3>Update Status</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <select name="status">
                    <option value="Draft" <?= $payroll['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Processed" <?= $payroll['status'] === 'Processed' ? 'selected' : '' ?>>Processed</option>
                    <option value="Approved" <?= $payroll['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Paid" <?= $payroll['status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>

                <span id="paymentFields" style="display: none;">
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
                    <select name="payment_mode">
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cash">Cash</option>
                    </select>
                    <input type="text" name="transaction_ref" placeholder="Transaction Ref">
                </span>

                <button type="submit" class="btn btn-success">Update</button>
            </form>
        </div>

        <script>
        document.querySelector('select[name="status"]').addEventListener('change', function() {
            document.getElementById('paymentFields').style.display = this.value === 'Paid' ? 'inline' : 'none';
        });
        </script>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
