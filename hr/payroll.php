<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get selected month
$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthName = date('F Y', strtotime($monthStart));

// Get department filter
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where = ["p.payroll_month = ?"];
$params = [$monthStart];

if ($department) {
    $where[] = "e.department = ?";
    $params[] = $department;
}
if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$whereClause = implode(" AND ", $where);

// Fetch payroll records
$stmt = $pdo->prepare("
    SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    WHERE $whereClause
    ORDER BY e.department, e.first_name
");
$stmt->execute($params);
$payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Calculate totals
$totalGross = 0;
$totalDeductions = 0;
$totalNet = 0;
foreach ($payrolls as $p) {
    $totalGross += $p['gross_earnings'];
    $totalDeductions += $p['total_deductions'];
    $totalNet += $p['net_pay'];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payroll - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .payroll-header {
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
        .month-nav a { font-size: 1.5em; color: #3498db; text-decoration: none; }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .summary-card .label { color: #7f8c8d; font-size: 0.9em; }
        .summary-card .value { font-size: 1.8em; font-weight: bold; margin-top: 5px; }
        .summary-card.gross .value { color: #27ae60; }
        .summary-card.deductions .value { color: #e74c3c; }
        .summary-card.net .value { color: #2c3e50; }

        .payroll-table { width: 100%; border-collapse: collapse; }
        .payroll-table th, .payroll-table td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .payroll-table th { background: #f5f5f5; font-weight: bold; }
        .payroll-table tr:hover { background: #fafafa; }
        .payroll-table .number { text-align: right; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-Draft { background: #fff3cd; color: #856404; }
        .status-Processed { background: #cce5ff; color: #004085; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Paid { background: #d1ecf1; color: #0c5460; }

        .quick-actions { margin-bottom: 20px; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="payroll-header">
        <div class="month-nav">
            <?php
            $prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
            $nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
            ?>
            <a href="?month=<?= $prevMonth ?>">&larr;</a>
            <h2>Payroll: <?= $monthName ?></h2>
            <a href="?month=<?= $nextMonth ?>">&rarr;</a>
        </div>

        <div class="filters">
            <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="month" name="month" value="<?= $selectedMonth ?>">
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Processed" <?= $status === 'Processed' ? 'selected' : '' ?>>Processed</option>
                    <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

    <div class="quick-actions">
        <a href="payroll_generate.php?month=<?= $selectedMonth ?>" class="btn btn-success">Generate Payroll</a>
        <a href="payroll_process.php?month=<?= $selectedMonth ?>" class="btn btn-secondary">Process All</a>
    </div>

    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">Total Employees</div>
            <div class="value"><?= count($payrolls) ?></div>
        </div>
        <div class="summary-card gross">
            <div class="label">Total Gross</div>
            <div class="value"><?= number_format($totalGross, 0) ?></div>
        </div>
        <div class="summary-card deductions">
            <div class="label">Total Deductions</div>
            <div class="value"><?= number_format($totalDeductions, 0) ?></div>
        </div>
        <div class="summary-card net">
            <div class="label">Total Net Pay</div>
            <div class="value"><?= number_format($totalNet, 0) ?></div>
        </div>
    </div>

    <table class="payroll-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Days Present</th>
                <th class="number">Gross</th>
                <th class="number">Deductions</th>
                <th class="number">Net Pay</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payrolls)): ?>
                <tr><td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    No payroll records for <?= $monthName ?>
                    <br><br>
                    <a href="payroll_generate.php?month=<?= $selectedMonth ?>" class="btn btn-success">Generate Payroll</a>
                </td></tr>
            <?php else: ?>
                <?php foreach ($payrolls as $p): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong><br>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($p['emp_id']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($p['department'] ?? '-') ?></td>
                    <td><?= $p['days_present'] ?> / <?= $p['working_days'] ?></td>
                    <td class="number"><?= number_format($p['gross_earnings'], 2) ?></td>
                    <td class="number"><?= number_format($p['total_deductions'], 2) ?></td>
                    <td class="number"><strong><?= number_format($p['net_pay'], 2) ?></strong></td>
                    <td>
                        <span class="status-badge status-<?= $p['status'] ?>"><?= $p['status'] ?></span>
                    </td>
                    <td>
                        <a href="payroll_view.php?id=<?= $p['id'] ?>" class="btn btn-sm">View</a>
                        <?php if ($p['status'] === 'Draft'): ?>
                            <a href="payroll_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
