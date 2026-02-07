<?php
/**
 * View TADA Claim
 * View details, approve/reject, mark as paid
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: tada.php"); exit; }

// Check table
$tableError = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'tada_claims'")->fetch();
    if (!$check) $tableError = true;
} catch (PDOException $e) { $tableError = true; }

if ($tableError) {
    setModal("Setup Required", "TADA tables not found.");
    header("Location: tada.php");
    exit;
}

// Fetch claim
$stmt = $pdo->prepare("
    SELECT tc.*,
           e.emp_id, e.first_name, e.last_name, e.department, e.phone, e.email, e.designation,
           a.first_name as approver_first, a.last_name as approver_last, a.emp_id as approver_emp_id
    FROM tada_claims tc
    JOIN employees e ON tc.employee_id = e.id
    LEFT JOIN employees a ON tc.approved_by = a.id
    WHERE tc.id = ?
");
$stmt->execute([$id]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$claim) {
    setModal("Error", "TADA claim not found");
    header("Location: tada.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject']) && $claim['status'] === 'Pending') {
        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $remarks = trim($_POST['remarks'] ?? '');

        $pdo->prepare("
            UPDATE tada_claims SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
            WHERE id = ?
        ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $remarks, $id]);

        setModal("Success", "TADA claim " . strtolower($newStatus) . " successfully!");
        header("Location: tada_view.php?id=$id");
        exit;
    }

    if ($action === 'mark_paid' && $claim['status'] === 'Approved') {
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_mode = $_POST['payment_mode'] ?? '';
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');

        $pdo->prepare("
            UPDATE tada_claims SET status = 'Paid', payment_date = ?, payment_mode = ?, transaction_ref = ?
            WHERE id = ?
        ")->execute([$payment_date, $payment_mode, $transaction_ref, $id]);

        setModal("Success", "TADA claim marked as paid!");
        header("Location: tada_view.php?id=$id");
        exit;
    }
}

$statusColors = [
    'Pending' => '#fff3cd', 'Approved' => '#d4edda', 'Rejected' => '#f8d7da', 'Paid' => '#d1ecf1'
];
$statusTextColors = [
    'Pending' => '#856404', 'Approved' => '#155724', 'Rejected' => '#721c24', 'Paid' => '#0c5460'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>View TADA Claim - <?= htmlspecialchars($claim['claim_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .tada-view-container {
            display: grid; grid-template-columns: 2fr 1fr; gap: 20px;
        }
        @media (max-width: 900px) { .tada-view-container { grid-template-columns: 1fr; } }
        .panel {
            background: white; border-radius: 10px; padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .panel h3 {
            margin: 0 0 20px 0; padding-bottom: 10px;
            border-bottom: 2px solid #30cfd0; color: #2c3e50;
        }
        .detail-row {
            display: flex; padding: 12px 0; border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { width: 160px; font-weight: 600; color: #666; flex-shrink: 0; }
        .detail-value { flex: 1; color: #2c3e50; }
        .status-large {
            display: inline-block; padding: 8px 20px; border-radius: 20px;
            font-weight: 600; font-size: 1.1em;
        }
        .amount-table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        .amount-table td {
            padding: 10px 15px; border-bottom: 1px solid #eee;
        }
        .amount-table tr:last-child td {
            border-top: 2px solid #30cfd0; font-weight: 700; font-size: 1.1em;
            border-bottom: none;
        }
        .amount-table .label { color: #666; }
        .amount-table .value { text-align: right; color: #2c3e50; }
        .mode-badge {
            display: inline-block; padding: 5px 12px; border-radius: 5px;
            background: #e3f2fd; color: #1565c0; font-weight: 600;
        }
        .total-card {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white; padding: 20px; border-radius: 10px;
            text-align: center; margin-bottom: 20px;
        }
        .total-value { font-size: 2.5em; font-weight: bold; }
        .total-label { opacity: 0.9; }
        .approval-form {
            margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee;
        }
        .approval-form textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px;
            resize: vertical; min-height: 80px; margin-bottom: 15px;
        }
        .approval-btns { display: flex; gap: 10px; flex-wrap: wrap; }
        .reason-box {
            background: #f8f9fa; padding: 15px; border-radius: 5px;
            border-left: 4px solid #30cfd0; margin-top: 10px;
        }
        .payment-form { margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee; }
        .payment-form input, .payment-form select {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;
        }
        .receipt-viewer { margin-top: 10px; }
        .receipt-viewer img { max-width: 100%; border-radius: 8px; cursor: pointer; }
        .timeline { margin-top: 20px; }
        .timeline-item {
            display: flex; gap: 15px; padding: 15px 0;
            border-left: 2px solid #30cfd0; margin-left: 10px; padding-left: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: ''; position: absolute; left: -6px; top: 20px;
            width: 10px; height: 10px; border-radius: 50%; background: #30cfd0;
        }
        .timeline-date { font-size: 0.85em; color: #666; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>TADA Claim: <?= htmlspecialchars($claim['claim_no']) ?></h1>
        <a href="tada.php" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="tada-view-container">
        <!-- Main Details -->
        <div>
            <div class="panel">
                <h3>Claim Details</h3>

                <div class="detail-row">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-large" style="background: <?= $statusColors[$claim['status']] ?>; color: <?= $statusTextColors[$claim['status']] ?>;">
                            <?= $claim['status'] ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Travel Date</div>
                    <div class="detail-value">
                        <strong><?= date('d M Y', strtotime($claim['travel_date'])) ?></strong>
                        <?php if ($claim['return_date']): ?>
                            to <strong><?= date('d M Y', strtotime($claim['return_date'])) ?></strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Route</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($claim['from_location']) ?> &rarr; <?= htmlspecialchars($claim['to_location']) ?>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Travel Mode</div>
                    <div class="detail-value"><span class="mode-badge"><?= htmlspecialchars($claim['travel_mode']) ?></span></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Purpose</div>
                    <div class="detail-value">
                        <div class="reason-box"><?= nl2br(htmlspecialchars($claim['purpose'])) ?></div>
                    </div>
                </div>

                <?php if ($claim['description']): ?>
                <div class="detail-row">
                    <div class="detail-label">Description</div>
                    <div class="detail-value"><?= nl2br(htmlspecialchars($claim['description'])) ?></div>
                </div>
                <?php endif; ?>

                <!-- Amount Breakdown -->
                <h3 style="margin-top: 25px;">Amount Breakdown</h3>
                <table class="amount-table">
                    <tr><td class="label">Travel Amount</td><td class="value">Rs <?= number_format($claim['travel_amount'], 2) ?></td></tr>
                    <tr><td class="label">DA Amount</td><td class="value">Rs <?= number_format($claim['da_amount'], 2) ?></td></tr>
                    <tr><td class="label">Accommodation</td><td class="value">Rs <?= number_format($claim['accommodation_amount'], 2) ?></td></tr>
                    <tr><td class="label">Other Expenses</td><td class="value">Rs <?= number_format($claim['other_amount'], 2) ?></td></tr>
                    <tr><td class="label">Total Amount</td><td class="value" style="color: #27ae60;">Rs <?= number_format($claim['total_amount'], 2) ?></td></tr>
                </table>

                <!-- Receipt -->
                <?php if ($claim['receipt_path']): ?>
                <h3 style="margin-top: 25px;">Receipt</h3>
                <?php
                    $ext = strtolower(pathinfo($claim['receipt_path'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                ?>
                <div class="receipt-viewer">
                    <?php if ($isImage): ?>
                        <a href="../<?= htmlspecialchars($claim['receipt_path']) ?>" target="_blank">
                            <img src="../<?= htmlspecialchars($claim['receipt_path']) ?>" alt="Receipt" style="max-height: 300px;">
                        </a>
                    <?php else: ?>
                        <a href="../<?= htmlspecialchars($claim['receipt_path']) ?>" target="_blank" class="btn btn-secondary">
                            View/Download Receipt (PDF)
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Approval Info -->
                <?php if ($claim['status'] !== 'Pending' && $claim['approver_first']): ?>
                <div class="detail-row" style="margin-top: 20px;">
                    <div class="detail-label"><?= $claim['status'] === 'Rejected' ? 'Rejected' : 'Approved' ?> By</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($claim['approver_first'] . ' ' . $claim['approver_last']) ?>
                        (<?= htmlspecialchars($claim['approver_emp_id']) ?>)
                        <br><span style="color: #666; font-size: 0.9em;"><?= date('d M Y h:i A', strtotime($claim['approval_date'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($claim['approval_remarks']): ?>
                <div class="detail-row">
                    <div class="detail-label">Remarks</div>
                    <div class="detail-value">
                        <div class="reason-box" style="border-left-color: #f39c12;"><?= nl2br(htmlspecialchars($claim['approval_remarks'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Info -->
                <?php if ($claim['status'] === 'Paid'): ?>
                <h3 style="margin-top: 25px;">Payment Details</h3>
                <div class="detail-row">
                    <div class="detail-label">Payment Date</div>
                    <div class="detail-value"><?= $claim['payment_date'] ? date('d M Y', strtotime($claim['payment_date'])) : '-' ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Payment Mode</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['payment_mode'] ?: '-') ?></div>
                </div>
                <?php if ($claim['transaction_ref']): ?>
                <div class="detail-row">
                    <div class="detail-label">Transaction Ref</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['transaction_ref']) ?></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Approval Form -->
                <?php if ($claim['status'] === 'Pending'): ?>
                <div class="approval-form">
                    <h4 style="margin-bottom: 15px;">Take Action</h4>
                    <form method="POST">
                        <textarea name="remarks" placeholder="Add remarks (optional)..."></textarea>
                        <div class="approval-btns">
                            <button type="submit" name="action" value="approve" class="btn btn-success"
                                    onclick="return confirm('Approve this TADA claim?')">Approve Claim</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="return confirm('Reject this TADA claim?')">Reject Claim</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Mark Paid Form -->
                <?php if ($claim['status'] === 'Approved'): ?>
                <div class="payment-form">
                    <h4 style="margin-bottom: 15px;">Mark as Paid</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_paid">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Payment Date</label>
                                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Payment Mode</label>
                                <select name="payment_mode" required>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="UPI">UPI</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Transaction Ref</label>
                                <input type="text" name="transaction_ref" placeholder="Optional">
                            </div>
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this claim as paid?')">Mark as Paid</button>
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
                            <strong>Claim Submitted</strong>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($claim['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($claim['approval_date']): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Claim <?= $claim['status'] === 'Rejected' ? 'Rejected' : 'Approved' ?></strong>
                            <?php if ($claim['approver_first']): ?>
                                by <?= htmlspecialchars($claim['approver_first'] . ' ' . $claim['approver_last']) ?>
                            <?php endif; ?>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($claim['approval_date'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($claim['status'] === 'Paid' && $claim['payment_date']): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Payment Processed</strong>
                            via <?= htmlspecialchars($claim['payment_mode']) ?>
                            <div class="timeline-date"><?= date('d M Y', strtotime($claim['payment_date'])) ?></div>
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
                        <?= htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']) ?>
                    </div>
                    <div style="color: #666;"><?= htmlspecialchars($claim['emp_id']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['department'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Designation</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['designation'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['phone'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?= htmlspecialchars($claim['email'] ?? '-') ?></div>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="employee_view.php?id=<?= $claim['employee_id'] ?>" class="btn btn-secondary btn-sm">View Profile</a>
                </div>
            </div>

            <!-- Claim Total -->
            <div class="panel" style="margin-top: 20px;">
                <h3>Claim Summary</h3>
                <div class="total-card">
                    <div class="total-value">Rs <?= number_format($claim['total_amount'], 2) ?></div>
                    <div class="total-label">Total Claim Amount</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
