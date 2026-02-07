<?php
/**
 * TADA Claims List
 * View all TADA claims with filters and quick actions
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$tableError = false;

// Auto-create tables if they don't exist
try {
    $check = $pdo->query("SHOW TABLES LIKE 'tada_claims'")->fetch();
    if (!$check) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tada_claims (
                id INT AUTO_INCREMENT PRIMARY KEY,
                claim_no VARCHAR(20) NOT NULL UNIQUE,
                employee_id INT NOT NULL,
                travel_date DATE NOT NULL,
                return_date DATE,
                from_location VARCHAR(255) NOT NULL,
                to_location VARCHAR(255) NOT NULL,
                travel_mode ENUM('Bus','Train','Flight','Auto','Own Vehicle','Other') DEFAULT 'Bus',
                purpose VARCHAR(500) NOT NULL,
                description TEXT,
                travel_amount DECIMAL(12,2) DEFAULT 0.00,
                da_amount DECIMAL(12,2) DEFAULT 0.00,
                accommodation_amount DECIMAL(12,2) DEFAULT 0.00,
                other_amount DECIMAL(12,2) DEFAULT 0.00,
                total_amount DECIMAL(12,2) DEFAULT 0.00,
                receipt_path VARCHAR(500),
                status ENUM('Pending','Approved','Rejected','Paid') DEFAULT 'Pending',
                approved_by INT,
                approval_date DATETIME,
                approval_remarks TEXT,
                payment_date DATE,
                payment_mode VARCHAR(50),
                transaction_ref VARCHAR(100),
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id),
                INDEX idx_status (status),
                INDEX idx_travel_date (travel_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    $cols = $pdo->query("SHOW COLUMNS FROM tada_claims")->fetchAll(PDO::FETCH_COLUMN);
    $tableError = !in_array('claim_no', $cols) || !in_array('status', $cols);
} catch (PDOException $e) {
    $tableError = true;
}

// Filters
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Handle quick approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tableError) {
    $action = $_POST['action'] ?? '';
    $claim_id = intval($_POST['claim_id'] ?? 0);

    if ($claim_id && in_array($action, ['approve', 'reject'])) {
        $claim = $pdo->prepare("SELECT * FROM tada_claims WHERE id = ?");
        $claim->execute([$claim_id]);
        $c = $claim->fetch(PDO::FETCH_ASSOC);

        if ($c && $c['status'] === 'Pending') {
            $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

            $pdo->prepare("
                UPDATE tada_claims
                SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
                WHERE id = ?
            ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $_POST['remarks'] ?? '', $claim_id]);

            setModal("Success", "TADA claim " . strtolower($newStatus) . " successfully!");
            header("Location: tada.php?" . http_build_query($_GET));
            exit;
        }
    }
}

// Initialize
$claims = [];
$stats = ['pending' => 0, 'approved_month' => 0, 'total_paid' => 0, 'this_month' => 0];
$departments = [];
$employees = [];

if (!$tableError) {
    $where = ["1=1"];
    $params = [];

    if ($status) { $where[] = "tc.status = ?"; $params[] = $status; }
    if ($department) { $where[] = "e.department = ?"; $params[] = $department; }
    if ($employee_id) { $where[] = "tc.employee_id = ?"; $params[] = $employee_id; }
    if ($date_from) { $where[] = "tc.travel_date >= ?"; $params[] = $date_from; }
    if ($date_to) { $where[] = "tc.travel_date <= ?"; $params[] = $date_to; }

    $whereClause = implode(" AND ", $where);

    try {
        $stmt = $pdo->prepare("
            SELECT tc.*,
                   e.emp_id, e.first_name, e.last_name, e.department,
                   a.first_name as approver_first, a.last_name as approver_last
            FROM tada_claims tc
            JOIN employees e ON tc.employee_id = e.id
            LEFT JOIN employees a ON tc.approved_by = a.id
            WHERE $whereClause
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute($params);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableError = true;
    }

    try {
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM tada_claims WHERE status = 'Pending'")->fetchColumn();
        $stats['approved_month'] = $pdo->query("SELECT COUNT(*) FROM tada_claims WHERE status IN ('Approved','Paid') AND DATE_FORMAT(approval_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
        $stats['total_paid'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM tada_claims WHERE status = 'Paid'")->fetchColumn();
        $stats['this_month'] = $pdo->query("SELECT COUNT(*) FROM tada_claims WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
    } catch (PDOException $e) {}

    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $employees = $pdo->query("SELECT id, emp_id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TADA Claims - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .filters {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
            background: white; padding: 15px; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filters select, .filters input {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;
        }

        .tada-table {
            width: 100%; border-collapse: collapse; background: white;
            border-radius: 10px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .tada-table th, .tada-table td {
            padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee;
        }
        .tada-table th {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white; font-weight: 600;
        }
        .tada-table tr:hover { background: #f8f9fa; }

        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d1ecf1; color: #0c5460; }

        .mode-badge {
            display: inline-block; padding: 3px 8px; border-radius: 4px;
            font-size: 0.85em; font-weight: 600; background: #e3f2fd; color: #1565c0;
        }
        .action-btns { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>TADA Claims</h1>
        <div style="display: flex; gap: 10px;">
            <a href="tada_add.php" class="btn btn-primary">+ New TADA Claim</a>
        </div>
    </div>

    <?php if ($tableError): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404; margin-bottom: 20px;">
            <strong>Setup Required</strong><br>
            TADA tables are not properly configured. Please refresh the page to auto-create tables.
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card warning">
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $stats['approved_month'] ?></div>
            <div class="stat-label">Approved This Month</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= number_format($stats['total_paid']) ?></div>
            <div class="stat-label">Total Paid (Rs)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['this_month'] ?></div>
            <div class="stat-label">Claims This Month</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All Status</option>
            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
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

        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="tada.php" class="btn btn-secondary">Reset</a>
    </form>

    <!-- TADA Claims Table -->
    <div style="overflow-x: auto;">
        <table class="tada-table">
            <thead>
                <tr>
                    <th>Claim No</th>
                    <th>Employee</th>
                    <th>Travel Date</th>
                    <th>Route</th>
                    <th>Mode</th>
                    <th>Amount (Rs)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #666; padding: 30px;">
                            No TADA claims found.
                            <a href="tada_add.php">Create new claim</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($claims as $c): ?>
                    <tr>
                        <td><a href="tada_view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['claim_no']) ?></a></td>
                        <td>
                            <a href="employee_view.php?id=<?= $c['employee_id'] ?>">
                                <?= htmlspecialchars($c['emp_id'] . ' - ' . $c['first_name'] . ' ' . $c['last_name']) ?>
                            </a>
                        </td>
                        <td><?= date('d M Y', strtotime($c['travel_date'])) ?></td>
                        <td><?= htmlspecialchars($c['from_location']) ?> &rarr; <?= htmlspecialchars($c['to_location']) ?></td>
                        <td><span class="mode-badge"><?= htmlspecialchars($c['travel_mode']) ?></span></td>
                        <td style="font-weight: 600;"><?= number_format($c['total_amount'], 2) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($c['status']) ?>">
                                <?= $c['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="tada_view.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if ($c['status'] === 'Pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                                onclick="return confirm('Approve this TADA claim?')">Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Reject this TADA claim?')">Reject</button>
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
