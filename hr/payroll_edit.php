<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Ensure performance_allowance and food_allowance columns exist in payroll table
try {
    $columns = $pdo->query("SHOW COLUMNS FROM payroll LIKE 'performance_allowance'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE payroll ADD COLUMN performance_allowance DECIMAL(10,2) DEFAULT 0 AFTER other_allowance");
        $pdo->exec("ALTER TABLE payroll ADD COLUMN food_allowance DECIMAL(10,2) DEFAULT 0 AFTER performance_allowance");
    }
} catch (Exception $e) {
    // Columns may already exist
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: payroll.php");
    exit;
}

// Get payroll record
$stmt = $pdo->prepare("
    SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation
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

if ($payroll['status'] !== 'Draft') {
    header("Location: payroll_view.php?id=$id");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $days_present = (int)$_POST['days_present'];
        $working_days = (int)$_POST['working_days'];

        // Earnings
        $basic = (float)$_POST['basic_salary'];
        $hra = (float)$_POST['hra'];
        $conveyance = (float)$_POST['conveyance'];
        $medical = (float)$_POST['medical_allowance'];
        $special = (float)$_POST['special_allowance'];
        $other = (float)$_POST['other_allowance'];
        $performance = (float)$_POST['performance_allowance'];
        $food = (float)$_POST['food_allowance'];

        // Deductions
        $pf = (float)$_POST['pf_employee'];
        $esi = (float)$_POST['esi_employee'];
        $tax = (float)$_POST['professional_tax'];

        // Calculate totals
        $gross = $basic + $hra + $conveyance + $medical + $special + $other + $performance + $food;
        $total_deductions = $pf + $esi + $tax;
        $net_pay = $gross - $total_deductions;

        // Check if performance_allowance and food_allowance columns exist
        $columns = $pdo->query("SHOW COLUMNS FROM payroll LIKE 'performance_allowance'")->fetch();
        if (!$columns) {
            // Columns don't exist - add them
            $pdo->exec("ALTER TABLE payroll ADD COLUMN performance_allowance DECIMAL(10,2) DEFAULT 0 AFTER other_allowance");
            $pdo->exec("ALTER TABLE payroll ADD COLUMN food_allowance DECIMAL(10,2) DEFAULT 0 AFTER performance_allowance");
        }

        $stmt = $pdo->prepare("
            UPDATE payroll SET
                days_present = ?, working_days = ?,
                basic_salary = ?, hra = ?, conveyance = ?, medical_allowance = ?, special_allowance = ?, other_allowance = ?, performance_allowance = ?, food_allowance = ?,
                pf_employee = ?, esi_employee = ?, professional_tax = ?,
                gross_earnings = ?, total_deductions = ?, net_pay = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $days_present, $working_days,
            $basic, $hra, $conveyance, $medical, $special, $other, $performance, $food,
            $pf, $esi, $tax,
            $gross, $total_deductions, $net_pay,
            $id
        ]);

        header("Location: payroll_view.php?id=$id&success=1");
        exit;
    } catch (Exception $e) {
        $error = "Error updating payroll: " . $e->getMessage();
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Payroll - <?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?></title>
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: #495057;
            font-size: 0.85em;
        }
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        .form-group input[readonly] {
            background: #f8f9fa;
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
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
        body.dark .form-card { background: #2c3e50; }
        body.dark .form-card h3 { color: #ecf0f1; }
        body.dark .header-bar { background: #2c3e50; }
        body.dark .header-bar h1 { color: #ecf0f1; }
        body.dark .form-actions { background: #2c3e50; }

        @media (max-width: 1200px) {
            .landscape-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .landscape-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div class="header-bar">
            <div>
                <h1>Edit Payroll</h1>
                <p>
                    <?= htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']) ?>
                    (<?= htmlspecialchars($payroll['emp_id']) ?>) -
                    <?= date('F Y', strtotime($payroll['payroll_month'])) ?>
                </p>
            </div>
            <a href="payroll_view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="payrollForm">
            <div class="landscape-grid">
                <!-- Left Column: Attendance -->
                <div class="form-card">
                    <h3>Attendance</h3>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Working Days</label>
                        <input type="number" name="working_days" value="<?= $payroll['working_days'] ?>" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Days Present</label>
                        <input type="number" name="days_present" value="<?= $payroll['days_present'] ?>" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Days Absent</label>
                        <input type="number" value="<?= $payroll['days_absent'] ?? 0 ?>" readonly style="background: #f8f9fa;">
                    </div>
                    <div class="form-group">
                        <label>Leaves Taken</label>
                        <input type="number" value="<?= $payroll['leaves_taken'] ?? 0 ?>" readonly style="background: #f8f9fa;">
                    </div>
                </div>

                <!-- Middle Column: Earnings -->
                <div class="form-card">
                    <h3>Earnings</h3>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Basic Salary</label>
                            <input type="number" name="basic_salary" id="basic_salary" step="0.01" value="<?= $payroll['basic_salary'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>HRA</label>
                            <input type="number" name="hra" id="hra" step="0.01" value="<?= $payroll['hra'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Conveyance</label>
                            <input type="number" name="conveyance" id="conveyance" step="0.01" value="<?= $payroll['conveyance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Medical Allowance</label>
                            <input type="number" name="medical_allowance" id="medical_allowance" step="0.01" value="<?= $payroll['medical_allowance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Special Allowance</label>
                            <input type="number" name="special_allowance" id="special_allowance" step="0.01" value="<?= $payroll['special_allowance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Other Allowance</label>
                            <input type="number" name="other_allowance" id="other_allowance" step="0.01" value="<?= $payroll['other_allowance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Performance Allowance</label>
                            <input type="number" name="performance_allowance" id="performance_allowance" step="0.01" value="<?= $payroll['performance_allowance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                        <div class="form-group">
                            <label>Food Allowance</label>
                            <input type="number" name="food_allowance" id="food_allowance" step="0.01" value="<?= $payroll['food_allowance'] ?? 0 ?>" onchange="calculatePayroll()">
                        </div>
                    </div>
                </div>

                <!-- Right Column: Deductions & Summary -->
                <div class="form-card">
                    <h3>Deductions</h3>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>PF (Employee)</label>
                        <input type="number" name="pf_employee" id="pf_employee" step="0.01" value="<?= $payroll['pf_employee'] ?? 0 ?>" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>ESI (Employee)</label>
                        <input type="number" name="esi_employee" id="esi_employee" step="0.01" value="<?= $payroll['esi_employee'] ?? 0 ?>" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group">
                        <label>Professional Tax</label>
                        <input type="number" name="professional_tax" id="professional_tax" step="0.01" value="<?= $payroll['professional_tax'] ?? 0 ?>" onchange="calculatePayroll()">
                    </div>

                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Gross Earnings</span>
                            <span id="grossDisplay">₹<?= number_format($payroll['gross_earnings'] ?? 0, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Total Deductions</span>
                            <span id="deductionsDisplay">₹<?= number_format($payroll['total_deductions'] ?? 0, 2) ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Net Pay</span>
                            <span id="netPayDisplay">₹<?= number_format($payroll['net_pay'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="payroll_view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function calculatePayroll() {
    // Earnings
    const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
    const hra = parseFloat(document.getElementById('hra').value) || 0;
    const conveyance = parseFloat(document.getElementById('conveyance').value) || 0;
    const medical = parseFloat(document.getElementById('medical_allowance').value) || 0;
    const special = parseFloat(document.getElementById('special_allowance').value) || 0;
    const other = parseFloat(document.getElementById('other_allowance').value) || 0;
    const performance = parseFloat(document.getElementById('performance_allowance').value) || 0;
    const food = parseFloat(document.getElementById('food_allowance').value) || 0;

    // Deductions
    const pf = parseFloat(document.getElementById('pf_employee').value) || 0;
    const esi = parseFloat(document.getElementById('esi_employee').value) || 0;
    const tax = parseFloat(document.getElementById('professional_tax').value) || 0;

    const gross = basic + hra + conveyance + medical + special + other + performance + food;
    const deductions = pf + esi + tax;
    const netPay = gross - deductions;

    document.getElementById('grossDisplay').textContent = '₹' + gross.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('deductionsDisplay').textContent = '₹' + deductions.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('netPayDisplay').textContent = '₹' + netPay.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>

</body>
</html>
