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

// Fetch payroll
$stmt = $pdo->prepare("
    SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation,
           e.bank_name, e.bank_account, e.bank_ifsc, e.date_of_joining, e.uan_number, e.esi_number
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
        .payslip-container { max-width: 1100px; margin: 0 auto; }

        .payslip {
            background: white;
            border: 2px solid #333;
            font-size: 13px;
            color: #333;
        }

        /* Header */
        .ps-header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #333;
            padding: 12px 20px;
        }
        .ps-logo { max-height: 50px; max-width: 120px; margin-right: 15px; }
        .ps-company { flex: 1; }
        .ps-company-name { font-size: 18px; font-weight: bold; color: #1a1a1a; margin: 0; }
        .ps-company-addr { font-size: 11px; color: #555; margin: 2px 0 0 0; }
        .ps-title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #1a1a1a;
        }
        .ps-month {
            text-align: right;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-top: 2px;
        }

        /* Employee Info Table */
        .ps-emp-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ps-emp-table td {
            padding: 6px 12px;
            border: 1px solid #ccc;
            font-size: 12px;
        }
        .ps-emp-table .lbl {
            background: #f0f4f8;
            font-weight: 600;
            color: #444;
            width: 14%;
            white-space: nowrap;
        }
        .ps-emp-table .val { width: 19%; }

        /* Salary Columns */
        .ps-salary-wrap {
            display: flex;
        }
        .ps-salary-col {
            flex: 1;
            border-right: 1px solid #ccc;
        }
        .ps-salary-col:last-child { border-right: none; }
        .ps-salary-col .col-header {
            text-align: center;
            padding: 8px;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 1px;
            color: white;
        }
        .ps-salary-col .col-header.earnings { background: #27ae60; }
        .ps-salary-col .col-header.deductions { background: #c0392b; }
        .ps-salary-col .col-header.summary { background: #2c3e50; }

        .ps-sal-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ps-sal-table td {
            padding: 5px 10px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        .ps-sal-table .amt { text-align: right; font-family: 'Consolas', monospace; }
        .ps-sal-table .total-row td {
            border-top: 2px solid #333;
            border-bottom: none;
            font-weight: bold;
            padding-top: 8px;
            font-size: 13px;
        }
        .ps-sal-table .total-row .amt.green { color: #27ae60; }
        .ps-sal-table .total-row .amt.red { color: #c0392b; }
        .ps-sal-table .spacer td { border-bottom: none; height: 5px; }

        /* Net Pay Bar */
        .ps-netpay {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1a1a2e;
            color: white;
            padding: 10px 20px;
            border-top: 2px solid #333;
        }
        .ps-netpay .lbl { font-size: 14px; font-weight: 600; }
        .ps-netpay .amt { font-size: 22px; font-weight: bold; font-family: 'Consolas', monospace; }
        .ps-netpay .words { font-size: 11px; opacity: 0.8; font-style: italic; }

        /* Footer / Signatures */
        .ps-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 8px 20px 10px;
            border-top: 1px solid #ccc;
            font-size: 11px;
        }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; }

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

        .action-buttons { margin-bottom: 20px; }

        .status-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
        }
        .status-form h3 { margin: 0 0 15px 0; }
        .status-form select, .status-form input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }

        @media print {
            @page { size: landscape; margin: 8mm; }
            .sidebar, .action-buttons, .status-form, .topbar { display: none !important; }
            .content { margin-left: 0 !important; padding: 0 !important; }
            .payslip-container { max-width: 100%; }
            .payslip { border-width: 1px; }
            body { background: white; }
            .ps-salary-col .col-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ps-netpay { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ps-emp-table .lbl { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        @media (max-width: 768px) {
            .ps-salary-wrap { flex-direction: column; }
            .ps-salary-col { border-right: none; border-bottom: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="payslip-container">

        <div class="action-buttons">
            <a href="payroll.php?month=<?= substr($payroll['payroll_month'], 0, 7) ?>" class="btn btn-secondary">Back to Payroll</a>
            <button onclick="window.print()" class="btn btn-secondary">Print Payslip</button>
            <?php if ($payroll['status'] === 'Draft'): ?>
                <a href="payroll_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
            <span class="status-badge status-<?= $payroll['status'] ?>" style="margin-left: 10px;"><?= $payroll['status'] ?></span>
        </div>

        <div class="payslip">

            <!-- Company Header -->
            <div class="ps-header">
                <?php if (!empty($settings['logo_path'])): ?>
                    <img src="/<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo" class="ps-logo">
                <?php endif; ?>
                <div class="ps-company">
                    <p class="ps-company-name"><?= htmlspecialchars($settings['company_name'] ?? 'Company Name') ?></p>
                    <p class="ps-company-addr">
                        <?= htmlspecialchars(implode(', ', array_filter([
                            $settings['address_line1'] ?? '',
                            $settings['city'] ?? '',
                            $settings['state'] ?? '',
                            $settings['pincode'] ?? ''
                        ]))) ?>
                        <?php if (!empty($settings['gstin'])): ?>
                            &nbsp;|&nbsp; GSTIN: <?= htmlspecialchars($settings['gstin']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <div class="ps-title">Payslip</div>
                    <div class="ps-month"><?= $monthName ?></div>
                </div>
            </div>

            <!-- Employee Info -->
            <table class="ps-emp-table">
                <tr>
                    <td class="lbl">Employee ID</td>
                    <td class="val"><?= htmlspecialchars($payroll['emp_id']) ?></td>
                    <td class="lbl">Name</td>
                    <td class="val"><?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?></td>
                    <td class="lbl">Department</td>
                    <td class="val"><?= htmlspecialchars($payroll['department'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td class="lbl">Designation</td>
                    <td class="val"><?= htmlspecialchars($payroll['designation'] ?? '-') ?></td>
                    <td class="lbl">Bank A/C</td>
                    <td class="val"><?= htmlspecialchars($payroll['bank_account'] ?? '-') ?></td>
                    <td class="lbl">Bank / IFSC</td>
                    <td class="val"><?= htmlspecialchars(($payroll['bank_name'] ?? '-') . (!empty($payroll['bank_ifsc']) ? ' / ' . $payroll['bank_ifsc'] : '')) ?></td>
                </tr>
                <tr>
                    <td class="lbl">UAN</td>
                    <td class="val"><?= htmlspecialchars($payroll['uan_number'] ?? '-') ?></td>
                    <td class="lbl">ESI No</td>
                    <td class="val"><?= htmlspecialchars($payroll['esi_number'] ?? '-') ?></td>
                    <td class="lbl">Date of Joining</td>
                    <td class="val"><?= !empty($payroll['date_of_joining']) ? date('d M Y', strtotime($payroll['date_of_joining'])) : '-' ?></td>
                </tr>
            </table>

            <!-- Earnings | Deductions | Summary -->
            <div class="ps-salary-wrap">
                <!-- Earnings Column -->
                <div class="ps-salary-col">
                    <div class="col-header earnings">EARNINGS</div>
                    <table class="ps-sal-table">
                        <tr><td>Basic Salary</td><td class="amt"><?= number_format($payroll['basic_salary'] ?? 0, 2) ?></td></tr>
                        <tr><td>HRA</td><td class="amt"><?= number_format($payroll['hra'] ?? 0, 2) ?></td></tr>
                        <tr><td>Conveyance</td><td class="amt"><?= number_format($payroll['conveyance'] ?? 0, 2) ?></td></tr>
                        <tr><td>Medical Allowance</td><td class="amt"><?= number_format($payroll['medical_allowance'] ?? 0, 2) ?></td></tr>
                        <tr><td>Special Allowance</td><td class="amt"><?= number_format($payroll['special_allowance'] ?? 0, 2) ?></td></tr>
                        <tr><td>Other Allowance</td><td class="amt"><?= number_format($payroll['other_allowance'] ?? 0, 2) ?></td></tr>
                        <tr><td>Performance Allowance</td><td class="amt"><?= number_format($payroll['performance_allowance'] ?? 0, 2) ?></td></tr>
                        <tr><td>Food Allowance</td><td class="amt"><?= number_format($payroll['food_allowance'] ?? 0, 2) ?></td></tr>
                        <?php if (($payroll['overtime_pay'] ?? 0) > 0): ?>
                        <tr><td>Overtime</td><td class="amt"><?= number_format($payroll['overtime_pay'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if (($payroll['bonus'] ?? 0) > 0): ?>
                        <tr><td>Bonus</td><td class="amt"><?= number_format($payroll['bonus'], 2) ?></td></tr>
                        <?php endif; ?>
                        <tr class="spacer"><td></td><td></td></tr>
                        <tr class="total-row">
                            <td>Gross Earnings</td>
                            <td class="amt green"><?= number_format($payroll['gross_earnings'], 2) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Deductions Column -->
                <div class="ps-salary-col">
                    <div class="col-header deductions">DEDUCTIONS</div>
                    <table class="ps-sal-table">
                        <tr><td>PF (Employee)</td><td class="amt"><?= number_format($payroll['pf_employee'], 2) ?></td></tr>
                        <tr><td>ESI (Employee)</td><td class="amt"><?= number_format($payroll['esi_employee'], 2) ?></td></tr>
                        <tr><td>Professional Tax</td><td class="amt"><?= number_format($payroll['professional_tax'], 2) ?></td></tr>
                        <?php if ($payroll['tds'] > 0): ?>
                        <tr><td>TDS</td><td class="amt"><?= number_format($payroll['tds'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($payroll['loan_deduction'] > 0): ?>
                        <tr><td>Loan Deduction</td><td class="amt"><?= number_format($payroll['loan_deduction'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($payroll['other_deduction'] > 0): ?>
                        <tr><td>Other Deductions</td><td class="amt"><?= number_format($payroll['other_deduction'], 2) ?></td></tr>
                        <?php endif; ?>
                        <tr class="spacer"><td></td><td></td></tr>
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="amt red"><?= number_format($payroll['total_deductions'], 2) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Summary Column -->
                <div class="ps-salary-col">
                    <div class="col-header summary">ATTENDANCE & SUMMARY</div>
                    <table class="ps-sal-table">
                        <tr><td>Working Days</td><td class="amt"><?= $payroll['working_days'] ?></td></tr>
                        <tr><td>Days Present</td><td class="amt"><?= $payroll['days_present'] ?></td></tr>
                        <tr><td>Days Absent</td><td class="amt"><?= $payroll['days_absent'] ?></td></tr>
                        <tr><td>Leaves Taken</td><td class="amt"><?= $payroll['leaves_taken'] ?></td></tr>
                        <tr><td>Holidays</td><td class="amt"><?= $payroll['holidays'] ?></td></tr>
                        <tr class="spacer"><td></td><td></td></tr>
                        <tr style="border-top: 1px solid #ddd;">
                            <td style="padding-top: 8px;"><strong>Gross Earnings</strong></td>
                            <td class="amt" style="padding-top: 8px; color: #27ae60; font-weight: bold;"><?= number_format($payroll['gross_earnings'], 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Deductions</strong></td>
                            <td class="amt" style="color: #c0392b; font-weight: bold;">- <?= number_format($payroll['total_deductions'], 2) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td>NET PAY</td>
                            <td class="amt" style="color: #1a1a2e; font-size: 15px;"><?= number_format($payroll['net_pay'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Net Pay Bar -->
            <div class="ps-netpay">
                <div>
                    <div class="lbl">Net Pay</div>
                    <div class="words"><?= numberToWords($payroll['net_pay']) ?> Only</div>
                </div>
                <div class="amt"><?= number_format($payroll['net_pay'], 2) ?></div>
            </div>

            <?php if ($payroll['status'] === 'Paid'): ?>
            <div style="padding: 8px 20px; background: #eafaf1; font-size: 11px; border-top: 1px solid #ccc;">
                <strong>Payment:</strong>
                <?= $payroll['payment_date'] ? date('d M Y', strtotime($payroll['payment_date'])) : '-' ?> |
                <?= htmlspecialchars($payroll['payment_mode'] ?? '-') ?>
                <?php if ($payroll['transaction_ref']): ?>
                    | Ref: <?= htmlspecialchars($payroll['transaction_ref']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Signatures -->
            <div class="ps-footer">
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

        </div>

        <!-- Status Update Form (screen only) -->
        <?php if ($payroll['status'] !== 'Paid'): ?>
        <div class="status-form">
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
