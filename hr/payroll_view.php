<?php
include "../db.php";
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
           e.bank_name, e.bank_account, e.bank_ifsc
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

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payslip - <?= htmlspecialchars($payroll['emp_id']) ?> - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .payslip { max-width: 800px; margin: 0 auto; }

        .payslip-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px;
            border-radius: 10px 10px 0 0;
        }
        .payslip-header h1 { margin: 0 0 5px 0; }
        .payslip-header p { margin: 5px 0; opacity: 0.9; }

        .payslip-body {
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 25px;
        }

        .emp-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .emp-info .item label { color: #7f8c8d; font-size: 0.85em; display: block; }
        .emp-info .item .value { font-weight: 500; }

        .salary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .salary-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        .earnings h3 { border-color: #27ae60; color: #27ae60; }
        .deductions h3 { border-color: #e74c3c; color: #e74c3c; }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .salary-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .salary-table .amount { text-align: right; }
        .salary-table .total-row {
            font-weight: bold;
            border-top: 2px solid #ddd;
            border-bottom: none;
        }
        .salary-table .total-row td { padding-top: 15px; }

        .net-pay-box {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .net-pay-box .label { font-size: 1.2em; }
        .net-pay-box .amount { font-size: 2em; font-weight: bold; }

        .attendance-summary {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        .att-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .att-item .number { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .att-item .label { font-size: 0.85em; color: #7f8c8d; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
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
            .sidebar, .action-buttons, .status-form { display: none !important; }
            .content { margin-left: 0 !important; }
            .payslip { max-width: 100%; }
        }

        @media (max-width: 600px) {
            .salary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="payslip">

        <div class="action-buttons">
            <a href="payroll.php?month=<?= substr($payroll['payroll_month'], 0, 7) ?>" class="btn btn-secondary">Back to Payroll</a>
            <button onclick="window.print()" class="btn btn-secondary">Print Payslip</button>
            <?php if ($payroll['status'] === 'Draft'): ?>
                <a href="payroll_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php endif; ?>
        </div>

        <div class="payslip-header">
            <h1>Payslip</h1>
            <p><?= $monthName ?></p>
            <p style="margin-top: 10px;">
                <span class="status-badge status-<?= $payroll['status'] ?>"><?= $payroll['status'] ?></span>
            </p>
        </div>

        <div class="payslip-body">
            <!-- Employee Info -->
            <div class="emp-info">
                <div class="item">
                    <label>Employee ID</label>
                    <div class="value"><?= htmlspecialchars($payroll['emp_id']) ?></div>
                </div>
                <div class="item">
                    <label>Name</label>
                    <div class="value"><?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?></div>
                </div>
                <div class="item">
                    <label>Department</label>
                    <div class="value"><?= htmlspecialchars($payroll['department'] ?? '-') ?></div>
                </div>
                <div class="item">
                    <label>Designation</label>
                    <div class="value"><?= htmlspecialchars($payroll['designation'] ?? '-') ?></div>
                </div>
                <div class="item">
                    <label>Bank Account</label>
                    <div class="value"><?= htmlspecialchars($payroll['bank_account'] ?? '-') ?></div>
                </div>
                <div class="item">
                    <label>Bank</label>
                    <div class="value"><?= htmlspecialchars($payroll['bank_name'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Salary Breakdown -->
            <div class="salary-grid">
                <div class="salary-section earnings">
                    <h3>Earnings</h3>
                    <table class="salary-table">
                        <tr>
                            <td>Basic Salary</td>
                            <td class="amount"><?= number_format($payroll['basic_salary'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>HRA</td>
                            <td class="amount"><?= number_format($payroll['hra'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Conveyance</td>
                            <td class="amount"><?= number_format($payroll['conveyance'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Medical Allowance</td>
                            <td class="amount"><?= number_format($payroll['medical_allowance'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Special Allowance</td>
                            <td class="amount"><?= number_format($payroll['special_allowance'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Other Allowance</td>
                            <td class="amount"><?= number_format($payroll['other_allowance'], 2) ?></td>
                        </tr>
                        <?php if ($payroll['overtime_pay'] > 0): ?>
                        <tr>
                            <td>Overtime</td>
                            <td class="amount"><?= number_format($payroll['overtime_pay'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($payroll['bonus'] > 0): ?>
                        <tr>
                            <td>Bonus</td>
                            <td class="amount"><?= number_format($payroll['bonus'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td>Gross Earnings</td>
                            <td class="amount" style="color: #27ae60;"><?= number_format($payroll['gross_earnings'], 2) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="salary-section deductions">
                    <h3>Deductions</h3>
                    <table class="salary-table">
                        <tr>
                            <td>PF (Employee)</td>
                            <td class="amount"><?= number_format($payroll['pf_employee'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>ESI (Employee)</td>
                            <td class="amount"><?= number_format($payroll['esi_employee'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Professional Tax</td>
                            <td class="amount"><?= number_format($payroll['professional_tax'], 2) ?></td>
                        </tr>
                        <?php if ($payroll['tds'] > 0): ?>
                        <tr>
                            <td>TDS</td>
                            <td class="amount"><?= number_format($payroll['tds'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($payroll['loan_deduction'] > 0): ?>
                        <tr>
                            <td>Loan Deduction</td>
                            <td class="amount"><?= number_format($payroll['loan_deduction'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($payroll['other_deduction'] > 0): ?>
                        <tr>
                            <td>Other Deductions</td>
                            <td class="amount"><?= number_format($payroll['other_deduction'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="amount" style="color: #e74c3c;"><?= number_format($payroll['total_deductions'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Net Pay -->
            <div class="net-pay-box">
                <span class="label">Net Pay</span>
                <span class="amount"><?= number_format($payroll['net_pay'], 2) ?></span>
            </div>

            <!-- Attendance Summary -->
            <div class="attendance-summary">
                <h3>Attendance Summary</h3>
                <div class="attendance-grid">
                    <div class="att-item">
                        <div class="number"><?= $payroll['working_days'] ?></div>
                        <div class="label">Working Days</div>
                    </div>
                    <div class="att-item">
                        <div class="number"><?= $payroll['days_present'] ?></div>
                        <div class="label">Days Present</div>
                    </div>
                    <div class="att-item">
                        <div class="number"><?= $payroll['days_absent'] ?></div>
                        <div class="label">Days Absent</div>
                    </div>
                    <div class="att-item">
                        <div class="number"><?= $payroll['leaves_taken'] ?></div>
                        <div class="label">Leaves Taken</div>
                    </div>
                    <div class="att-item">
                        <div class="number"><?= $payroll['holidays'] ?></div>
                        <div class="label">Holidays</div>
                    </div>
                </div>
            </div>

            <?php if ($payroll['status'] === 'Paid'): ?>
            <div style="margin-top: 25px; padding: 15px; background: #d1ecf1; border-radius: 8px;">
                <strong>Payment Details:</strong><br>
                Date: <?= $payroll['payment_date'] ? date('d M Y', strtotime($payroll['payment_date'])) : '-' ?> |
                Mode: <?= htmlspecialchars($payroll['payment_mode'] ?? '-') ?>
                <?php if ($payroll['transaction_ref']): ?>
                    | Ref: <?= htmlspecialchars($payroll['transaction_ref']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status Update Form -->
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
