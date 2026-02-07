<?php
/**
 * View Advance Payment
 * View details, approve/reject, disburse, track repayments
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: advance_payment.php"); exit; }

// Check table
$tableError = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'advance_payments'")->fetch();
    if (!$check) $tableError = true;
} catch (PDOException $e) { $tableError = true; }

if ($tableError) {
    setModal("Setup Required", "Advance payment tables not found.");
    header("Location: advance_payment.php");
    exit;
}

// Fetch advance
$stmt = $pdo->prepare("
    SELECT ap.*,
           e.emp_id, e.first_name, e.last_name, e.department, e.phone, e.email, e.designation,
           a.first_name as approver_first, a.last_name as approver_last, a.emp_id as approver_emp_id
    FROM advance_payments ap
    JOIN employees e ON ap.employee_id = e.id
    LEFT JOIN employees a ON ap.approved_by = a.id
    WHERE ap.id = ?
");
$stmt->execute([$id]);
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advance) {
    setModal("Error", "Advance payment not found");
    header("Location: advance_payment.php");
    exit;
}

// Fetch repayment history
$repayments = [];
try {
    $repStmt = $pdo->prepare("
        SELECT ar.* FROM advance_repayments ar
        WHERE ar.advance_id = ?
        ORDER BY ar.repayment_date DESC
    ");
    $repStmt->execute([$id]);
    $repayments = $repStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$totalRepaid = array_sum(array_column($repayments, 'amount'));

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject']) && $advance['status'] === 'Pending') {
        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $remarks = trim($_POST['remarks'] ?? '');

        $pdo->prepare("
            UPDATE advance_payments SET status = ?, approved_by = ?, approval_date = NOW(), approval_remarks = ?
            WHERE id = ?
        ")->execute([$newStatus, $_SESSION['user_id'] ?? null, $remarks, $id]);

        setModal("Success", "Advance request " . strtolower($newStatus) . " successfully!");
        header("Location: advance_view.php?id=$id");
        exit;
    }

    if ($action === 'disburse' && $advance['status'] === 'Approved') {
        $disbursement_date = $_POST['disbursement_date'] ?? date('Y-m-d');
        $payment_mode = $_POST['payment_mode'] ?? '';
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');

        $pdo->prepare("
            UPDATE advance_payments SET status = 'Disbursed', disbursement_date = ?, payment_mode = ?, transaction_ref = ?
            WHERE id = ?
        ")->execute([$disbursement_date, $payment_mode, $transaction_ref, $id]);

        setModal("Success", "Advance disbursed successfully!");
        header("Location: advance_view.php?id=$id");
        exit;
    }

    if ($action === 'record_repayment' && $advance['status'] === 'Disbursed') {
        $rep_date = $_POST['repayment_date'] ?? date('Y-m-d');
        $rep_amount = floatval($_POST['repayment_amount'] ?? 0);
        $rep_remarks = trim($_POST['repayment_remarks'] ?? '');

        if ($rep_amount > 0) {
            $pdo->prepare("
                INSERT INTO advance_repayments (advance_id, repayment_date, amount, remarks, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$id, $rep_date, $rep_amount, $rep_remarks]);

            $newBalance = $advance['balance_remaining'] - $rep_amount;
            if ($newBalance <= 0) {
                $pdo->prepare("UPDATE advance_payments SET balance_remaining = 0, status = 'Closed' WHERE id = ?")->execute([$id]);
                setModal("Success", "Repayment recorded. Advance is now fully repaid and closed!");
            } else {
                $pdo->prepare("UPDATE advance_payments SET balance_remaining = ? WHERE id = ?")->execute([$newBalance, $id]);
                setModal("Success", "Repayment of Rs " . number_format($rep_amount, 2) . " recorded successfully!");
            }
            header("Location: advance_view.php?id=$id");
            exit;
        }
    }
}

$statusColors = [
    'Pending' => '#fff3cd', 'Approved' => '#d4edda', 'Rejected' => '#f8d7da',
    'Disbursed' => '#d1ecf1', 'Closed' => '#e9ecef'
];
$statusTextColors = [
    'Pending' => '#856404', 'Approved' => '#155724', 'Rejected' => '#721c24',
    'Disbursed' => '#0c5460', 'Closed' => '#495057'
];
$typeColors = [
    'Salary' => '#2e7d32', 'Travel' => '#1565c0', 'Project' => '#ef6c00',
    'Medical' => '#c62828', 'Other' => '#7b1fa2'
];

$progressPct = $advance['amount'] > 0 ? min(100, round(($totalRepaid / $advance['amount']) * 100)) : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <title>View Advance - <?= htmlspecialchars($advance['advance_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .adv-view-container { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .adv-view-container { grid-template-columns: 1fr; } }
        .panel {
            background: white; border-radius: 10px; padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .panel h3 {
            margin: 0 0 20px 0; padding-bottom: 10px;
            border-bottom: 2px solid #30cfd0; color: #2c3e50;
        }
        .detail-row { display: flex; padding: 12px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { width: 160px; font-weight: 600; color: #666; flex-shrink: 0; }
        .detail-value { flex: 1; color: #2c3e50; }
        .status-large {
            display: inline-block; padding: 8px 20px; border-radius: 20px;
            font-weight: 600; font-size: 1.1em;
        }
        .type-badge {
            display: inline-block; padding: 5px 12px; border-radius: 5px;
            font-weight: 600; color: white;
        }
        .summary-card {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white; padding: 20px; border-radius: 10px;
            text-align: center; margin-bottom: 20px;
        }
        .summary-value { font-size: 2.2em; font-weight: bold; }
        .summary-label { opacity: 0.9; font-size: 0.9em; }
        .progress-bar-lg {
            background: #e0e0e0; border-radius: 10px; height: 12px; margin: 15px 0;
            overflow: hidden;
        }
        .progress-bar-lg .fill {
            height: 100%; border-radius: 10px;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            transition: width 0.5s;
        }
        .repayment-table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        .repayment-table th, .repayment-table td {
            padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9em;
        }
        .repayment-table th { background: #f8f9fa; font-weight: 600; color: #555; }
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
        .disburse-form, .repayment-form {
            margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee;
        }
        .disburse-form input, .disburse-form select,
        .repayment-form input, .repayment-form select {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;
        }
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
        <h1>Advance: <?= htmlspecialchars($advance['advance_no']) ?></h1>
        <a href="advance_payment.php" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="adv-view-container">
        <!-- Main Details -->
        <div>
            <div class="panel">
                <h3>Advance Details</h3>

                <div class="detail-row">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-large" style="background: <?= $statusColors[$advance['status']] ?>; color: <?= $statusTextColors[$advance['status']] ?>;">
                            <?= $advance['status'] ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Type</div>
                    <div class="detail-value">
                        <span class="type-badge" style="background: <?= $typeColors[$advance['advance_type']] ?? '#666' ?>">
                            <?= htmlspecialchars($advance['advance_type']) ?>
                        </span>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Amount</div>
                    <div class="detail-value" style="font-size: 1.2em; font-weight: 700; color: #2c3e50;">
                        Rs <?= number_format($advance['amount'], 2) ?>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Purpose</div>
                    <div class="detail-value">
                        <div class="reason-box"><?= nl2br(htmlspecialchars($advance['purpose'])) ?></div>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Repayment Plan</div>
                    <div class="detail-value">
                        Rs <?= number_format($advance['monthly_deduction'], 2) ?> / month
                        x <?= $advance['repayment_months'] ?> months
                    </div>
                </div>

                <?php if (in_array($advance['status'], ['Disbursed', 'Closed'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Repayment Progress</div>
                    <div class="detail-value">
                        <div class="progress-bar-lg">
                            <div class="fill" style="width: <?= $progressPct ?>%"></div>
                        </div>
                        <span style="color: #27ae60; font-weight: 600;"><?= $progressPct ?>% repaid</span>
                        (Rs <?= number_format($totalRepaid, 2) ?> of Rs <?= number_format($advance['amount'], 2) ?>)
                    </div>
                </div>
                <?php endif; ?>

                <!-- Approval Info -->
                <?php if ($advance['status'] !== 'Pending' && $advance['approver_first']): ?>
                <div class="detail-row">
                    <div class="detail-label"><?= $advance['status'] === 'Rejected' ? 'Rejected' : 'Approved' ?> By</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($advance['approver_first'] . ' ' . $advance['approver_last']) ?>
                        (<?= htmlspecialchars($advance['approver_emp_id']) ?>)
                        <br><span style="color: #666; font-size: 0.9em;"><?= date('d M Y h:i A', strtotime($advance['approval_date'])) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($advance['approval_remarks']): ?>
                <div class="detail-row">
                    <div class="detail-label">Remarks</div>
                    <div class="detail-value">
                        <div class="reason-box" style="border-left-color: #f39c12;"><?= nl2br(htmlspecialchars($advance['approval_remarks'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Disbursement Info -->
                <?php if ($advance['disbursement_date']): ?>
                <div class="detail-row">
                    <div class="detail-label">Disbursed On</div>
                    <div class="detail-value"><?= date('d M Y', strtotime($advance['disbursement_date'])) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Payment Mode</div>
                    <div class="detail-value"><?= htmlspecialchars($advance['payment_mode'] ?: '-') ?></div>
                </div>
                <?php if ($advance['transaction_ref']): ?>
                <div class="detail-row">
                    <div class="detail-label">Transaction Ref</div>
                    <div class="detail-value"><?= htmlspecialchars($advance['transaction_ref']) ?></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Approval Form -->
                <?php if ($advance['status'] === 'Pending'): ?>
                <div class="approval-form">
                    <h4 style="margin-bottom: 15px;">Take Action</h4>
                    <form method="POST">
                        <textarea name="remarks" placeholder="Add remarks (optional)..."></textarea>
                        <div class="approval-btns">
                            <button type="submit" name="action" value="approve" class="btn btn-success"
                                    onclick="return confirm('Approve this advance request?')">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"
                                    onclick="return confirm('Reject this advance request?')">Reject</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Disburse Form -->
                <?php if ($advance['status'] === 'Approved'): ?>
                <div class="disburse-form">
                    <h4 style="margin-bottom: 15px;">Disburse Advance</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="disburse">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Date</label>
                                <input type="date" name="disbursement_date" value="<?= date('Y-m-d') ?>" required>
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
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Disburse Rs <?= number_format($advance['amount'], 2) ?>?')">Disburse</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Repayment History -->
            <?php if (in_array($advance['status'], ['Disbursed', 'Closed'])): ?>
            <div class="panel" style="margin-top: 20px;">
                <h3>Repayment History</h3>

                <?php if ($advance['status'] === 'Disbursed'): ?>
                <div class="repayment-form">
                    <h4 style="margin-bottom: 15px;">Record Repayment</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="record_repayment">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Date</label>
                                <input type="date" name="repayment_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Amount (Rs)</label>
                                <input type="number" name="repayment_amount" step="0.01" min="0.01"
                                       value="<?= number_format($advance['monthly_deduction'], 2, '.', '') ?>" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85em; color: #666; margin-bottom: 4px;">Remarks</label>
                                <input type="text" name="repayment_remarks" placeholder="Optional">
                            </div>
                            <button type="submit" class="btn btn-success" onclick="return confirm('Record this repayment?')">Record</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (empty($repayments)): ?>
                    <p style="color: #999; margin-top: 15px;">No repayments recorded yet.</p>
                <?php else: ?>
                    <table class="repayment-table" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount (Rs)</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repayments as $i => $rep): ?>
                            <tr>
                                <td><?= count($repayments) - $i ?></td>
                                <td><?= date('d M Y', strtotime($rep['repayment_date'])) ?></td>
                                <td style="font-weight: 600; color: #27ae60;"><?= number_format($rep['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($rep['remarks'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight: 700; background: #f8f9fa;">
                                <td colspan="2">Total Repaid</td>
                                <td style="color: #27ae60;"><?= number_format($totalRepaid, 2) ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="panel" style="margin-top: 20px;">
                <h3>Timeline</h3>
                <div class="timeline">
                    <div class="timeline-item">
                        <div>
                            <strong>Request Submitted</strong>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($advance['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($advance['approval_date']): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Request <?= $advance['status'] === 'Rejected' ? 'Rejected' : 'Approved' ?></strong>
                            <?php if ($advance['approver_first']): ?>
                                by <?= htmlspecialchars($advance['approver_first'] . ' ' . $advance['approver_last']) ?>
                            <?php endif; ?>
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($advance['approval_date'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($advance['disbursement_date']): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Amount Disbursed</strong> via <?= htmlspecialchars($advance['payment_mode']) ?>
                            <div class="timeline-date"><?= date('d M Y', strtotime($advance['disbursement_date'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($advance['status'] === 'Closed'): ?>
                    <div class="timeline-item">
                        <div>
                            <strong>Advance Closed</strong> - Fully Repaid
                            <div class="timeline-date"><?= date('d M Y h:i A', strtotime($advance['updated_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="panel">
                <h3>Employee</h3>
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 1.2em; font-weight: 600;">
                        <?= htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']) ?>
                    </div>
                    <div style="color: #666;"><?= htmlspecialchars($advance['emp_id']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department</div>
                    <div class="detail-value"><?= htmlspecialchars($advance['department'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Designation</div>
                    <div class="detail-value"><?= htmlspecialchars($advance['designation'] ?? '-') ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?= htmlspecialchars($advance['phone'] ?? '-') ?></div>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="employee_view.php?id=<?= $advance['employee_id'] ?>" class="btn btn-secondary btn-sm">View Profile</a>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="panel" style="margin-top: 20px;">
                <h3>Financial Summary</h3>
                <div class="summary-card">
                    <div class="summary-value">Rs <?= number_format($advance['balance_remaining'], 2) ?></div>
                    <div class="summary-label">Balance Remaining</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Total Amount</div>
                    <div class="detail-value">Rs <?= number_format($advance['amount'], 2) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Amount Repaid</div>
                    <div class="detail-value" style="color: #27ae60;">Rs <?= number_format($totalRepaid, 2) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Monthly Ded.</div>
                    <div class="detail-value">Rs <?= number_format($advance['monthly_deduction'], 2) ?></div>
                </div>
                <?php if ($advance['balance_remaining'] > 0 && $advance['monthly_deduction'] > 0): ?>
                <div class="detail-row">
                    <div class="detail-label">Months Left</div>
                    <div class="detail-value"><?= ceil($advance['balance_remaining'] / $advance['monthly_deduction']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
