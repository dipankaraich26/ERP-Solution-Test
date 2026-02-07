<?php
/**
 * Advance Payments List
 * View all advance payment requests with filters and quick actions
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$tableError = false;

// Auto-create tables if they don't exist
try {
    $check = $pdo->query("SHOW TABLES LIKE 'advance_payments'")->fetch();
    if (!$check) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS advance_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                advance_no VARCHAR(20) NOT NULL UNIQUE,
                employee_id INT NOT NULL,
                advance_type ENUM('Salary','Travel','Project','Medical','Other') DEFAULT 'Salary',
                amount DECIMAL(12,2) NOT NULL,
                purpose TEXT NOT NULL,
                repayment_months INT DEFAULT 1,
                monthly_deduction DECIMAL(12,2) DEFAULT 0.00,
                balance_remaining DECIMAL(12,2) DEFAULT 0.00,
                status ENUM('Pending','Approved','Rejected','Disbursed','Closed') DEFAULT 'Pending',
                approved_by INT,
                approval_date DATETIME,
                approval_remarks TEXT,
                disbursement_date DATE,
                payment_mode VARCHAR(50),
                transaction_ref VARCHAR(100),
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id),
                INDEX idx_status (status),
                INDEX idx_type (advance_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS advance_repayments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                advance_id INT NOT NULL,
                repayment_date DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payroll_id INT,
                remarks VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_advance (advance_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    $cols = $pdo->query("SHOW COLUMNS FROM advance_payments")->fetchAll(PDO::FETCH_COLUMN);
    $tableError = !in_array('advance_no', $cols) || !in_array('status', $cols);
} catch (PDOException $e) {
    $tableError = true;
}

// Filters
$status = $_GET['status'] ?? '';
$advance_type = $_GET['advance_type'] ?? '';
$department = $_GET['department'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';

// Handle quick approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tableError) {
    $action = $_POST['action'] ?? '';
    $adv_id = intval($_POST['advance_id'] ?? 0);

    if ($adv_id && in_array($action, ['approve', 'reject'])) {
        $adv = $pdo->prepare("SELECT * FROM advance_payments WHERE id = ?");
        $adv->execute([$adv_id]);
        $a = $adv->fetch(PDO::FETCH_ASSOC);

        if ($a && $a['status'] === 'Pending') {
            $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
            $pdo->prepare("
                UPDATE advance_payments SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
                WHERE id = ?
            ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $_POST['remarks'] ?? '', $adv_id]);

            setModal("Success", "Advance request " . strtolower($newStatus) . " successfully!");
            header("Location: advance_payment.php?" . http_build_query($_GET));
            exit;
        }
    }
}

// Initialize
$advances = [];
$stats = ['pending' => 0, 'active' => 0, 'total_outstanding' => 0, 'closed_month' => 0];
$departments = [];
$employees = [];

if (!$tableError) {
    $where = ["1=1"];
    $params = [];

    if ($status) { $where[] = "ap.status = ?"; $params[] = $status; }
    if ($advance_type) { $where[] = "ap.advance_type = ?"; $params[] = $advance_type; }
    if ($department) { $where[] = "e.department = ?"; $params[] = $department; }
    if ($employee_id) { $where[] = "ap.employee_id = ?"; $params[] = $employee_id; }

    $whereClause = implode(" AND ", $where);

    try {
        $stmt = $pdo->prepare("
            SELECT ap.*,
                   e.emp_id, e.first_name, e.last_name, e.department,
                   a.first_name as approver_first, a.last_name as approver_last
            FROM advance_payments ap
            JOIN employees e ON ap.employee_id = e.id
            LEFT JOIN employees a ON ap.approved_by = a.id
            WHERE $whereClause
            ORDER BY ap.created_at DESC
        ");
        $stmt->execute($params);
        $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableError = true;
    }

    try {
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM advance_payments WHERE status = 'Pending'")->fetchColumn();
        $stats['active'] = $pdo->query("SELECT COUNT(*) FROM advance_payments WHERE status = 'Disbursed'")->fetchColumn();
        $stats['total_outstanding'] = $pdo->query("SELECT COALESCE(SUM(balance_remaining), 0) FROM advance_payments WHERE status = 'Disbursed'")->fetchColumn();
        $stats['closed_month'] = $pdo->query("SELECT COUNT(*) FROM advance_payments WHERE status = 'Closed' AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
    } catch (PDOException $e) {}

    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $employees = $pdo->query("SELECT id, emp_id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Advance Payments - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 10px;
            text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #30cfd0;
        }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .filters {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
            background: white; padding: 15px; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filters select, .filters input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; }

        .adv-table {
            width: 100%; border-collapse: collapse; background: white;
            border-radius: 10px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .adv-table th, .adv-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .adv-table th {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white; font-weight: 600;
        }
        .adv-table tr:hover { background: #f8f9fa; }

        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-disbursed { background: #d1ecf1; color: #0c5460; }
        .status-closed { background: #e9ecef; color: #495057; }

        .type-badge {
            display: inline-block; padding: 3px 8px; border-radius: 4px;
            font-size: 0.85em; font-weight: 600;
        }
        .type-salary { background: #e8f5e9; color: #2e7d32; }
        .type-travel { background: #e3f2fd; color: #1565c0; }
        .type-project { background: #fff3e0; color: #ef6c00; }
        .type-medical { background: #fce4ec; color: #c62828; }
        .type-other { background: #f3e5f5; color: #7b1fa2; }

        .action-btns { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Advance Payments</h1>
        <div style="display: flex; gap: 10px;">
            <a href="advance_add.php" class="btn btn-primary">+ New Advance</a>
        </div>
    </div>

    <?php if ($tableError): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404; margin-bottom: 20px;">
            <strong>Setup Required</strong><br>
            Advance payment tables are not properly configured. Please refresh the page to auto-create tables.
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card warning">
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= $stats['active'] ?></div>
            <div class="stat-label">Active Advances</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-value"><?= number_format($stats['total_outstanding']) ?></div>
            <div class="stat-label">Outstanding (Rs)</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $stats['closed_month'] ?></div>
            <div class="stat-label">Closed This Month</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All Status</option>
            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="Disbursed" <?= $status === 'Disbursed' ? 'selected' : '' ?>>Disbursed</option>
            <option value="Closed" <?= $status === 'Closed' ? 'selected' : '' ?>>Closed</option>
        </select>

        <select name="advance_type">
            <option value="">All Types</option>
            <?php foreach (['Salary','Travel','Project','Medical','Other'] as $t): ?>
                <option value="<?= $t ?>" <?= $advance_type === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>

        <select name="department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="employee_id">
            <option value="">All Employees</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['emp_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="advance_payment.php" class="btn btn-secondary">Reset</a>
    </form>

    <!-- Advance Payments Table -->
    <div style="overflow-x: auto;">
        <table class="adv-table">
            <thead>
                <tr>
                    <th>Advance No</th>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Amount (Rs)</th>
                    <th>Monthly Ded.</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($advances)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #666; padding: 30px;">
                            No advance payments found. <a href="advance_add.php">Create new advance</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($advances as $a): ?>
                    <tr>
                        <td><a href="advance_view.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['advance_no']) ?></a></td>
                        <td>
                            <a href="employee_view.php?id=<?= $a['employee_id'] ?>">
                                <?= htmlspecialchars($a['emp_id'] . ' - ' . $a['first_name'] . ' ' . $a['last_name']) ?>
                            </a>
                        </td>
                        <td><span class="type-badge type-<?= strtolower($a['advance_type']) ?>"><?= $a['advance_type'] ?></span></td>
                        <td style="font-weight: 600;"><?= number_format($a['amount'], 2) ?></td>
                        <td><?= number_format($a['monthly_deduction'], 2) ?></td>
                        <td style="font-weight: 600; color: <?= $a['balance_remaining'] > 0 ? '#e74c3c' : '#27ae60' ?>;">
                            <?= number_format($a['balance_remaining'], 2) ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= strtolower($a['status']) ?>"><?= $a['status'] ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="advance_view.php?id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if ($a['status'] === 'Pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="advance_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this advance?')">Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="advance_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this advance?')">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
