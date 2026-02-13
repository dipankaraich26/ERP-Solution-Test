<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

$wo_id = $_GET['id'] ?? null;
if (!$wo_id) {
    die("Invalid Work Order ID");
}

$success = '';
$error = '';

// Fetch work order details
$woStmt = $pdo->prepare("
    SELECT w.*, p.part_name
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    WHERE w.id = ?
");
$woStmt->execute([$wo_id]);
$wo = $woStmt->fetch();

if (!$wo) {
    die("Work Order not found");
}

// Check if checklist exists and is submitted
$checklistStmt = $pdo->prepare("
    SELECT * FROM wo_quality_checklists
    WHERE work_order_id = ? AND status IN ('Submitted', 'Approved')
    ORDER BY id DESC LIMIT 1
");
$checklistStmt->execute([$wo_id]);
$checklist = $checklistStmt->fetch();

// Check existing approval request
$existingApproval = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.emp_id
    FROM wo_closing_approvals a
    JOIN employees e ON a.approver_id = e.id
    WHERE a.work_order_id = ?
    ORDER BY a.id DESC LIMIT 1
");
$existingApproval->execute([$wo_id]);
$approval = $existingApproval->fetch();

// Fetch available approvers
$approvers = $pdo->query("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.designation, e.department
    FROM wo_approvers a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.is_active = 1 AND a.can_approve_wo_closing = 1
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request' && !empty($_POST['approver_id'])) {
        if (!$checklist || $checklist['status'] !== 'Submitted') {
            $error = "Quality checklist must be submitted before requesting approval.";
        } else {
            try {
                $userId = $_SESSION['user_id'] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO wo_closing_approvals (work_order_id, checklist_id, requested_by, approver_id, status)
                    VALUES (?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$wo_id, $checklist['id'], $userId, $_POST['approver_id']]);
                $success = "Approval request sent successfully!";

                // Refresh approval data
                $existingApproval->execute([$wo_id]);
                $approval = $existingApproval->fetch();

            } catch (PDOException $e) {
                $error = "Failed to request approval: " . $e->getMessage();
            }
        }
    }

    // Approver actions - any logged-in user can approve/reject
    if ($action === 'approve' && $approval && $approval['status'] === 'Pending') {
        try {
            $pdo->beginTransaction();

            // Update approval
            $stmt = $pdo->prepare("
                UPDATE wo_closing_approvals
                SET status = 'Approved', approved_at = NOW(), remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$_POST['remarks'] ?? null, $approval['id']]);

            // Update checklist status
            if ($checklist) {
                $pdo->prepare("UPDATE wo_quality_checklists SET status = 'Approved' WHERE id = ?")->execute([$checklist['id']]);
            }

            $pdo->commit();
            $success = "Work Order closing approved! The WO can now be closed.";

            // Refresh
            $existingApproval->execute([$wo_id]);
            $approval = $existingApproval->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to approve: " . $e->getMessage();
        }
    }

    if ($action === 'reject' && $approval && $approval['status'] === 'Pending') {
        if (empty($_POST['remarks'])) {
            $error = "Please provide a reason for rejection.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update approval
                $stmt = $pdo->prepare("
                    UPDATE wo_closing_approvals
                    SET status = 'Rejected', approved_at = NOW(), remarks = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['remarks'], $approval['id']]);

                // Update checklist status
                if ($checklist) {
                    $pdo->prepare("UPDATE wo_quality_checklists SET status = 'Rejected' WHERE id = ?")->execute([$checklist['id']]);
                }

                $pdo->commit();
                $success = "Work Order closing rejected.";

                // Refresh
                $existingApproval->execute([$wo_id]);
                $approval = $existingApproval->fetch();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to reject: " . $e->getMessage();
            }
        }
    }
}
?>

<style>
    html, body {
        height: auto !important;
        min-height: 100vh;
        overflow-y: auto !important;
    }
    .app-container {
        overflow: visible !important;
        height: auto !important;
        min-height: 100vh;
    }
</style>

<div class="content" style="overflow-y: auto; min-height: 100vh; padding-bottom: 60px;">
    <style>
        .approval-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .approval-card h3 {
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9em;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6b7280; }
        .info-value { font-weight: 500; }
        .action-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>

    <h1>Work Order Closing Approval</h1>

    <p style="margin-bottom: 20px;">
        <a href="view.php?id=<?= $wo_id ?>" class="btn btn-secondary">Back to Work Order</a>
        <a href="quality_checklist.php?id=<?= $wo_id ?>" class="btn btn-secondary">View Checklist</a>
    </p>

    <?php if ($success): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Work Order Summary -->
    <div class="approval-card">
        <h3>Work Order Details</h3>
        <div class="info-row">
            <span class="info-label">Work Order No</span>
            <span class="info-value"><?= htmlspecialchars($wo['wo_no']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Part</span>
            <span class="info-value"><?= htmlspecialchars($wo['part_no']) ?> - <?= htmlspecialchars($wo['part_name'] ?? '') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Quantity</span>
            <span class="info-value"><?= htmlspecialchars($wo['qty']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value"><?= ucfirst(str_replace('_', ' ', $wo['status'])) ?></span>
        </div>
    </div>

    <!-- Checklist Status -->
    <div class="approval-card">
        <h3>Quality Checklist Status</h3>
        <?php if ($checklist): ?>
            <div class="info-row">
                <span class="info-label">Checklist No</span>
                <span class="info-value"><?= htmlspecialchars($checklist['checklist_no']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Inspector</span>
                <span class="info-value"><?= htmlspecialchars($checklist['inspector_name'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Inspection Date</span>
                <span class="info-value"><?= $checklist['inspection_date'] ? date('d-M-Y', strtotime($checklist['inspection_date'])) : '-' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Overall Result</span>
                <span class="status-badge status-<?= strtolower($checklist['overall_result'] ?? 'pending') ?>">
                    <?= $checklist['overall_result'] ?? 'Pending' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="status-badge status-<?= strtolower($checklist['status']) ?>">
                    <?= $checklist['status'] ?>
                </span>
            </div>
        <?php else: ?>
            <div style="background: #fef3c7; padding: 15px; border-radius: 6px; color: #92400e;">
                Quality checklist has not been submitted yet.
                <a href="quality_checklist.php?id=<?= $wo_id ?>">Generate/Fill Checklist</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approval Status / Request -->
    <div class="approval-card">
        <h3>Closing Approval</h3>

        <?php if ($approval): ?>
            <!-- Show existing approval status -->
            <div class="info-row">
                <span class="info-label">Approval Status</span>
                <span class="status-badge status-<?= strtolower($approval['status']) ?>">
                    <?= $approval['status'] ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Approver</span>
                <span class="info-value">
                    <?= htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']) ?>
                    (<?= htmlspecialchars($approval['emp_id']) ?>)
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Requested At</span>
                <span class="info-value"><?= date('d-M-Y H:i', strtotime($approval['requested_at'])) ?></span>
            </div>
            <?php if ($approval['approved_at']): ?>
            <div class="info-row">
                <span class="info-label"><?= $approval['status'] === 'Approved' ? 'Approved At' : 'Rejected At' ?></span>
                <span class="info-value"><?= date('d-M-Y H:i', strtotime($approval['approved_at'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($approval['remarks']): ?>
            <div class="info-row">
                <span class="info-label">Remarks</span>
                <span class="info-value"><?= htmlspecialchars($approval['remarks']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Approver Action Form -->
            <?php if ($approval['status'] === 'Pending'): ?>
            <div class="action-section">
                <h4 style="margin: 0 0 15px 0;">Approver Actions</h4>
                <form method="post">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Remarks</label>
                        <textarea name="remarks" rows="3" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                                  placeholder="Enter remarks (required for rejection)"></textarea>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" name="action" value="approve" class="btn btn-primary"
                                onclick="return confirm('Approve this work order for closing?');">
                            Approve Closing
                        </button>
                        <button type="submit" name="action" value="reject" class="btn" style="background: #ef4444; color: white;"
                                onclick="return confirm('Reject this work order closing?');">
                            Reject
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($approval['status'] === 'Approved'): ?>
            <div style="margin-top: 20px; background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                <strong>Approved!</strong> This work order can now be closed.
                <a href="view.php?id=<?= $wo_id ?>" class="btn btn-primary" style="margin-left: 15px;">Go to Work Order</a>
            </div>
            <?php elseif ($approval['status'] === 'Rejected'): ?>
            <div style="margin-top: 20px; background: #fee2e2; padding: 15px; border-radius: 8px; color: #991b1b;">
                <strong>Rejected!</strong> Review the remarks and address the issues before requesting approval again.
            </div>
            <?php endif; ?>

        <?php elseif ($checklist && $checklist['status'] === 'Submitted'): ?>
            <!-- Request Approval Form -->
            <?php if (empty($approvers)): ?>
                <div style="background: #fee2e2; padding: 15px; border-radius: 6px; color: #991b1b;">
                    No approvers configured. Please <a href="../admin/wo_approvers.php">add approvers</a> first.
                </div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="request">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Select Approver</label>
                        <select name="approver_id" required style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                            <option value="">-- Select Approver --</option>
                            <?php foreach ($approvers as $app): ?>
                                <option value="<?= $app['id'] ?>">
                                    <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>
                                    (<?= htmlspecialchars($app['emp_id']) ?>)
                                    <?php if ($app['designation']): ?> - <?= htmlspecialchars($app['designation']) ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Request Approval</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div style="background: #fef3c7; padding: 15px; border-radius: 6px; color: #92400e;">
                Submit the quality checklist first before requesting approval.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
