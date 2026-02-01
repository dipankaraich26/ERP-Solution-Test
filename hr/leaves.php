<?php
/**
 * Leave Requests List
 * View all leave requests with filters and quick actions
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$currentYear = date('Y');
$tableError = false;

// Check if required tables exist with proper structure
$leaveTypesOk = false;
$leaveRequestsOk = false;

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
} catch (PDOException $e) {
    // Tables don't exist or error
}

$tableError = !$leaveTypesOk || !$leaveRequestsOk;

// Filters
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';
$month = $_GET['month'] ?? '';
$leave_type = $_GET['leave_type'] ?? '';

// Handle quick approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tableError) {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);

    if ($request_id && in_array($action, ['approve', 'reject', 'cancel'])) {
        $request = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
        $request->execute([$request_id]);
        $req = $request->fetch(PDO::FETCH_ASSOC);

        if ($req && $req['status'] === 'Pending') {
            $newStatus = $action === 'approve' ? 'Approved' : ($action === 'reject' ? 'Rejected' : 'Cancelled');

            $pdo->prepare("
                UPDATE leave_requests
                SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
                WHERE id = ?
            ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $_POST['remarks'] ?? '', $request_id]);

            // Update balance if approved
            if ($newStatus === 'Approved') {
                $pdo->prepare("
                    UPDATE leave_balances
                    SET used = used + ?, balance = balance - ?
                    WHERE employee_id = ? AND leave_type_id = ? AND year = ?
                ")->execute([
                    $req['total_days'],
                    $req['total_days'],
                    $req['employee_id'],
                    $req['leave_type_id'],
                    date('Y', strtotime($req['start_date']))
                ]);
            }

            setModal("Success", "Leave request " . strtolower($newStatus) . " successfully!");
            header("Location: leaves.php?" . http_build_query($_GET));
            exit;
        }
    }
}

// Initialize variables
$requests = [];
$stats = [
    'pending' => 0,
    'approved_today' => 0,
    'on_leave_today' => 0,
    'this_month' => 0
];
$departments = [];
$employees = [];
$leaveTypes = [];

if (!$tableError) {
    // Build query
    $where = ["1=1"];
    $params = [];

    if ($status) {
        $where[] = "lr.status = ?";
        $params[] = $status;
    }
    if ($department) {
        $where[] = "e.department = ?";
        $params[] = $department;
    }
    if ($employee_id) {
        $where[] = "lr.employee_id = ?";
        $params[] = $employee_id;
    }
    if ($leave_type) {
        $where[] = "lr.leave_type_id = ?";
        $params[] = $leave_type;
    }
    if ($month) {
        $where[] = "(DATE_FORMAT(lr.start_date, '%Y-%m') = ? OR DATE_FORMAT(lr.end_date, '%Y-%m') = ?)";
        $params[] = $month;
        $params[] = $month;
    }

    $whereClause = implode(" AND ", $where);

    // Fetch leave requests
    try {
        $requestsStmt = $pdo->prepare("
            SELECT lr.*,
                   e.emp_id, e.first_name, e.last_name, e.department,
                   lt.leave_code, lt.leave_type_name,
                   a.first_name as approver_first, a.last_name as approver_last
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN employees a ON lr.approved_by = a.id
            WHERE $whereClause
            ORDER BY lr.created_at DESC
        ");
        $requestsStmt->execute($params);
        $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableError = true;
    }

    // Stats
    try {
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
        $stats['approved_today'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND DATE(approval_date) = CURDATE()")->fetchColumn();
        $stats['on_leave_today'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date")->fetchColumn();
        $stats['this_month'] = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
    } catch (PDOException $e) {}

    // Fetch filter options
    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $employees = $pdo->query("SELECT id, emp_id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
    try {
        $leaveTypes = $pdo->query("SELECT id, leave_code, leave_type_name FROM leave_types WHERE is_active = 1 ORDER BY leave_code")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Requests - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #30cfd0;
        }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .leaves-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .leaves-table th, .leaves-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .leaves-table th {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            font-weight: 600;
        }
        .leaves-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e9ecef; color: #495057; }

        .action-btns { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; }

        .leave-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Leave Requests</h1>
        <div style="display: flex; gap: 10px;">
            <a href="leave_apply.php" class="btn btn-primary">+ Apply Leave</a>
            <a href="leave_balance.php" class="btn btn-secondary">View Balances</a>
            <a href="leave_types.php" class="btn btn-secondary">Leave Types</a>
        </div>
    </div>

    <?php if ($tableError): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404; margin-bottom: 20px;">
            <strong>Setup Required</strong><br>
            Leave management tables are not properly configured.<br><br>
            <a href="/admin/setup_leave_management.php" class="btn btn-primary">Run Setup Script</a>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card warning">
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $stats['approved_today'] ?></div>
            <div class="stat-label">Approved Today</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= $stats['on_leave_today'] ?></div>
            <div class="stat-label">On Leave Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['this_month'] ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters">
        <select name="status">
            <option value="">All Status</option>
            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Approved" <?= $status === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <select name="department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="leave_type">
            <option value="">All Leave Types</option>
            <?php foreach ($leaveTypes as $lt): ?>
                <option value="<?= $lt['id'] ?>" <?= $leave_type == $lt['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lt['leave_code'] . ' - ' . $lt['leave_type_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" placeholder="Month">

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="leaves.php" class="btn btn-secondary">Reset</a>
    </form>

    <!-- Leave Requests Table -->
    <div style="overflow-x: auto;">
        <table class="leaves-table">
            <thead>
                <tr>
                    <th>Request #</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Applied On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #666; padding: 30px;">
                            No leave requests found.
                            <?php if (empty($status) && empty($department)): ?>
                                <a href="leave_apply.php">Apply for leave</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><a href="leave_view.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['leave_request_no']) ?></a></td>
                        <td>
                            <a href="employee_view.php?id=<?= $r['employee_id'] ?>">
                                <?= htmlspecialchars($r['emp_id'] . ' - ' . $r['first_name'] . ' ' . $r['last_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($r['department'] ?? '-') ?></td>
                        <td>
                            <span class="leave-type-badge"><?= htmlspecialchars($r['leave_code']) ?></span>
                            <?= $r['is_half_day'] ? '<small>(Half Day)</small>' : '' ?>
                        </td>
                        <td><?= date('d M Y', strtotime($r['start_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($r['end_date'])) ?></td>
                        <td><?= number_format($r['total_days'], 1) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($r['status']) ?>">
                                <?= $r['status'] ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="leave_view.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if ($r['status'] === 'Pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                                onclick="return confirm('Approve this leave request?')">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Reject this leave request?')">
                                            Reject
                                        </button>
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
