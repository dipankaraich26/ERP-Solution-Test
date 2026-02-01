<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthName = date('F Y', strtotime($monthStart));

$error = '';
$success = '';

// Get draft payrolls for this month
$stmt = $pdo->prepare("
    SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    WHERE p.payroll_month = ? AND p.status = 'Draft'
    ORDER BY e.department, e.first_name
");
$stmt->execute([$monthStart]);
$drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selectedIds = $_POST['payroll_ids'] ?? [];

    if (empty($selectedIds)) {
        $error = "Please select at least one payroll record";
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'process') {
                $stmt = $pdo->prepare("UPDATE payroll SET status = 'Processed' WHERE id = ? AND status = 'Draft'");
                foreach ($selectedIds as $id) {
                    $stmt->execute([$id]);
                }
                $success = count($selectedIds) . " payroll(s) processed successfully";
            } elseif ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE payroll SET status = 'Approved' WHERE id = ? AND status IN ('Draft', 'Processed')");
                foreach ($selectedIds as $id) {
                    $stmt->execute([$id]);
                }
                $success = count($selectedIds) . " payroll(s) approved successfully";
            }

            $pdo->commit();

            // Refresh the list
            $stmt = $pdo->prepare("
                SELECT p.*, e.emp_id, e.first_name, e.last_name, e.department
                FROM payroll p
                JOIN employees e ON p.employee_id = e.id
                WHERE p.payroll_month = ? AND p.status = 'Draft'
                ORDER BY e.department, e.first_name
            ");
            $stmt->execute([$monthStart]);
            $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error processing: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Process Payroll - <?= $monthName ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .process-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payroll-table th, .payroll-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .payroll-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .payroll-table tr:hover { background: #f8f9fa; }
        .payroll-table .number { text-align: right; }

        .action-bar {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }

        body.dark .process-card { background: #2c3e50; }
        body.dark .payroll-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Process Payroll</h1>
            <p style="color: #666; margin: 5px 0 0;"><?= $monthName ?></p>
        </div>
        <a href="payroll.php?month=<?= $selectedMonth ?>" class="btn btn-secondary">Back to Payroll</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="process-card">
        <?php if (empty($drafts)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>No Draft Payrolls</h3>
                <p>All payrolls for <?= $monthName ?> have been processed.</p>
                <a href="payroll.php?month=<?= $selectedMonth ?>" class="btn btn-primary">View All Payrolls</a>
            </div>
        <?php else: ?>
            <form method="post">
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th class="number">Gross</th>
                            <th class="number">Deductions</th>
                            <th class="number">Net Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drafts as $p): ?>
                            <tr>
                                <td><input type="checkbox" name="payroll_ids[]" value="<?= $p['id'] ?>" class="payroll-checkbox"></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong><br>
                                    <small style="color: #666;"><?= htmlspecialchars($p['emp_id']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($p['department'] ?? '-') ?></td>
                                <td class="number">₹<?= number_format($p['gross_earnings'], 2) ?></td>
                                <td class="number">₹<?= number_format($p['total_deductions'], 2) ?></td>
                                <td class="number"><strong>₹<?= number_format($p['net_pay'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: 600;">
                            <td colspan="3">Total (<?= count($drafts) ?> employees)</td>
                            <td class="number">₹<?= number_format(array_sum(array_column($drafts, 'gross_earnings')), 2) ?></td>
                            <td class="number">₹<?= number_format(array_sum(array_column($drafts, 'total_deductions')), 2) ?></td>
                            <td class="number">₹<?= number_format(array_sum(array_column($drafts, 'net_pay')), 2) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="action-bar">
                    <button type="submit" name="action" value="process" class="btn btn-primary">Process Selected</button>
                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve Selected</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.payroll-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
});
</script>

</body>
</html>
