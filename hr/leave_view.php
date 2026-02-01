<?php
/**
 * View Leave Request
 * View details and approve/reject leave requests
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header("Location: leaves.php");
    exit;
}

// Check if required tables exist with proper structure
$tableError = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'leave_requests'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(PDO::FETCH_COLUMN);
        $tableError = !in_array('start_date', $cols) || !in_array('status', $cols);
    } else {
        $tableError = true;
    }
} catch (PDOException $e) {
    $tableError = true;
}

if ($tableError) {
    setModal("Setup Required", "Leave management tables need to be configured. Please run the setup script.");
    header("Location: /admin/setup_leave_management.php");
    exit;
}

// Fetch leave request details
$stmt = $pdo->prepare("
    SELECT lr.*,
           e.emp_id, e.first_name, e.last_name, e.department, e.phone, e.email,
           lt.leave_code, lt.leave_type_name, lt.is_paid, lt.requires_approval,
           a.first_name as approver_first, a.last_name as approver_last, a.emp_id as approver_emp_id
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    LEFT JOIN employees a ON lr.approved_by = a.id
    WHERE lr.id = ?
");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    setModal("Error", "Leave request not found");
    header("Location: leaves.php");
    exit;
}

// Get employee's leave balance
$balanceStmt = $pdo->prepare("
    SELECT lb.allocated, lb.used, lb.balance
    FROM leave_balances lb
    WHERE lb.employee_id = ? AND lb.leave_type_id = ? AND lb.year = ?
");
$balanceStmt->execute([
    $request['employee_id'],
    $request['leave_type_id'],
    date('Y', strtotime($request['start_date']))
]);
$balance = $balanceStmt->fetch(PDO::FETCH_ASSOC);

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (in_array($action, ['approve', 'reject', 'cancel']) && $request['status'] === 'Pending') {
        $newStatus = $action === 'approve' ? 'Approved' : ($action === 'reject' ? 'Rejected' : 'Cancelled');

        $pdo->prepare("
            UPDATE leave_requests
            SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
            WHERE id = ?
        ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $remarks, $id]);

        // Update balance if approved
        if ($newStatus === 'Approved') {
            $pdo->prepare("
                UPDATE leave_balances
                SET used = used + ?, balance = balance - ?
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
            ")->execute([
                $request['total_days'],
                $request['total_days'],
                $request['employee_id'],
                $request['leave_type_id'],
                date('Y', strtotime($request['start_date']))
            ]);
        }

        setModal("Success", "Leave request " . strtolower($newStatus) . " successfully!");
        header("Location: leave_view.php?id=$id");
        exit;
    }
}

// Status colors
$statusColors = [
    'Pending' => '#fff3cd',
    'Approved' => '#d4edda',
    'Rejected' => '#f8d7da',
    'Cancelled' => '#e9ecef'
];
$statusTextColors = [
    'Pending' => '#856404',
    'Approved' => '#155724',
    'Rejected' => '#721c24',
    'Cancelled' => '#495057'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>View Leave Request - <?= htmlspecialchars($request['leave_request_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .leave-view-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .leave-view-container { grid-template-columns: 1fr; }
        }
        .panel {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .panel h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #30cfd0;
            color: #2c3e50;
        }
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            width: 140px;
            font-weight: 600;
            color: #666;
        }
        .detail-value { flex: 1; color: #2c3e50; }
        .status-large {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .leave-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 5px;
            background: #e3f2fd;
            color: #1565c0;
            font-weight: 600;
        }
        .balance-card {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .balance-value { font-size: 2.5em; font-weight: bold; }
        .balance-label { opacity: 0.9; }
        .approval-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        .approval-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 15px;
        }
        .approval-btns { display: flex; gap: 10px; }
        .reason-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #30cfd0;
            margin-top: 10px;
        }
        .timeline {
            margin-top: 20px;
        }
        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-left: 2px solid #30cfd0;
            margin-left: 10px;
            padding-left: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #30cfd0;
        }
        .timeline-date {
            font-size: 0.85em;
            color: #666;
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Leave Request: <?= htmlspecialchars($request['leave_request_no']) ?></h1>
        <a href="leaves.php" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="leave-view-container">
        <!-- Main Details -->
        <div>
            <div class="panel">
                <h3>Request Details</h3>

                <div class="detail-row">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-large" style="background: <?= $statusColors[$request['status']] ?>; color: <?= $statusTextColors[$request['status']] ?>;">
                            <?= $request['status'] ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Leave Type</div>
                    <div class="detail-value">
                        <span class="leave-type-badge"><?= htmlspecialchars($request['leave_code']) ?></span>
                        <?= htmlspecialchars($request['leave_type_name']) ?>
                        <?php if (!$request['is_paid']): ?>
                            <span style="color: #e74c3c; font-size: 0.85em;">(Unpaid)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value">
                        <strong><?= date('d M Y', strtotime($request['start_date'])) ?></strong>
                        <?php if ($request['start_date'] !== $request['end_date']): ?>
                            to <strong><?= date('d M Y', strtotime($request['end_date'])) ?></strong>
                        <?php endif; ?>
                        <br>
                        <span style="color: #666;">
                            <?= number_format($request['total_days'], 1) ?> day(s)
                            <?= $request['is_half_day'] ? ' - ' . $request['half_day_type'] : '' ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Reason</div>
                    <div class="detail-value">
                        <div class="reason-box">
                            <?= nl2br(htmlspecialchars($request['reason'])) ?>
                        </div>
                    </div>
                </div>

                <?php if ($request['status'] !== 'Pending' && $request['approver_first']): ?>
                <div class="detail-row">
                    <div class="detail-label"><?= $request['status'] === 'Approved' ? 'Approved' : 'Processed' ?> By</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($request['approver_first'] . ' ' . $request['approver_last']) ?>
                        (<?= htmlspecialchars($request['approver_emp_id']) ?>)
                        <br>
                        <span style="color: #666; font-size: 0.9em;">
                            <?= date('d M Y h:i A', strtotime($request['approval_date'])) ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($request['approval_remarks']): ?>
                <div class="detail-row">
                    <div class="detail-label">Remarks</div>
                    <div class="detail-value">
                        <div class="reason-box" style="border-left-color: #f39c12;">
                            <?= nl2br(htmlspecialchars($request['approval_remarks'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Approval Form (if pending) -->
                <?php if ($request['status'] === 'Pending'): ?>
                <div class="approval-form">
                    <h4 style="margin-bottom: 15px;">Take Action</h4>
                    <form method="POST">
                        <textarea name="remarks" placeholder="Add remarks (optional)..."></textarea>
                        <div class="approval-btns">
                            <button type="submit" name="action" value="approve" class="btn btn-success"
                                    onclick="return confirm('Approve this leave request?')">
                                Approve Leave
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="return confirm('Reject this leave request?')">
                                Reject Leave
                            </button>
                            <button type="submit" name="action" value="cancel" class="btn btn-secondary"
                                    onclick="return confirm('Cancel this leave request?')">
                                Cancel Request
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="panel" style="margin-top: 20px;">
                <h3>Timeline</h3>
                <div class="timeline">
                    <div class="timeline-item">
                        <div>
                            <strong>Request Submitted</strong>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($request['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($request['status'] !== 'Pending'): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Request <?= $request['status'] ?></strong>
                            <?php if ($request['approver_first']): ?>
                                by <?= htmlspecialchars($request['approver_first'] . ' ' . $request['approver_last']) ?>
                            <?php endif; ?>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($request['approval_date'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Employee Info -->
            <div class="panel">
                <h3>Employee</h3>
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 1.2em; font-weight: 600;">
                        <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                    </div>
                    <div style="color: #666;"><?= htmlspecialchars($request['emp_id']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department</div>
                    <div class="detail-value"><?= htmlspecialchars($request['department'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?= htmlspecialchars($request['phone'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?= htmlspecialchars($request['email'] ?? '-') ?></div>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="employee_view.php?id=<?= $request['employee_id'] ?>" class="btn btn-secondary btn-sm">
                        View Profile
                    </a>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="panel" style="margin-top: 20px;">
                <h3>Leave Balance</h3>
                <div class="balance-card">
                    <div class="balance-value"><?= number_format($balance['balance'] ?? 0, 1) ?></div>
                    <div class="balance-label">Available <?= htmlspecialchars($request['leave_code']) ?> Days</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Allocated</div>
                    <div class="detail-value"><?= number_format($balance['allocated'] ?? 0, 1) ?> days</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Used</div>
                    <div class="detail-value"><?= number_format($balance['used'] ?? 0, 1) ?> days</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">This Request</div>
                    <div class="detail-value"><?= number_format($request['total_days'], 1) ?> days</div>
                </div>
                <?php
                $afterApproval = ($balance['balance'] ?? 0) - $request['total_days'];
                if ($request['status'] === 'Pending'):
                ?>
                <div class="detail-row">
                    <div class="detail-label">After Approval</div>
                    <div class="detail-value" style="color: <?= $afterApproval < 0 ? '#e74c3c' : '#27ae60' ?>;">
                        <?= number_format($afterApproval, 1) ?> days
                        <?= $afterApproval < 0 ? '(Insufficient!)' : '' ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
