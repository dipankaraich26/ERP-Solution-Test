<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

$po_no = $_GET['po_no'] ?? null;
if (!$po_no) {
    die("Invalid PO Number");
}

$success = '';
$error = '';

// Fetch PO details
$poStmt = $pdo->prepare("
    SELECT po.po_no, po.supplier_id, po.status, po.purchase_date,
           s.supplier_name, s.contact_person
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.po_no = ?
    LIMIT 1
");
$poStmt->execute([$po_no]);
$po = $poStmt->fetch();

if (!$po) {
    die("Purchase Order not found");
}

// Fetch PO line items summary
$linesSummary = $pdo->prepare("
    SELECT COUNT(*) as total_lines,
           SUM(po.qty) as total_qty,
           SUM(po.qty * po.rate) as total_value
    FROM purchase_orders po
    WHERE po.po_no = ?
");
$linesSummary->execute([$po_no]);
$summary = $linesSummary->fetch();

// Check if checklist exists and is submitted
$checklistStmt = $pdo->prepare("
    SELECT * FROM po_inspection_checklists
    WHERE po_no = ? AND status IN ('Submitted', 'Approved')
    ORDER BY id DESC LIMIT 1
");
$checklistStmt->execute([$po_no]);
$checklist = $checklistStmt->fetch();

// Check existing approval request
$existingApproval = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.emp_id
    FROM po_inspection_approvals a
    JOIN employees e ON a.approver_id = e.id
    WHERE a.po_no = ?
    ORDER BY a.id DESC LIMIT 1
");
$existingApproval->execute([$po_no]);
$approval = $existingApproval->fetch();

// Fetch available approvers
$approvers = $pdo->query("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.designation, e.department
    FROM po_inspection_approvers a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.is_active = 1
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request' && !empty($_POST['approver_id'])) {
        if (!$checklist || $checklist['status'] !== 'Submitted') {
            $error = "Inspection checklist must be submitted before requesting approval.";
        } else {
            try {
                $userId = $_SESSION['user_id'] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO po_inspection_approvals (po_no, checklist_id, requested_by, approver_id, status)
                    VALUES (?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$po_no, $checklist['id'], $userId, $_POST['approver_id']]);
                $success = "Approval request sent successfully!";

                // Refresh approval data
                $existingApproval->execute([$po_no]);
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
                UPDATE po_inspection_approvals
                SET status = 'Approved', approved_at = NOW(), remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$_POST['remarks'] ?? null, $approval['id']]);

            // Update checklist status
            if ($checklist) {
                $pdo->prepare("UPDATE po_inspection_checklists SET status = 'Approved' WHERE id = ?")->execute([$checklist['id']]);
            }

            $pdo->commit();
            $success = "Inspection approved! The stock can now be received.";

            // Refresh
            $existingApproval->execute([$po_no]);
            $approval = $existingApproval->fetch();
            $checklistStmt->execute([$po_no]);
            $checklist = $checklistStmt->fetch();

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
                    UPDATE po_inspection_approvals
                    SET status = 'Rejected', approved_at = NOW(), remarks = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['remarks'], $approval['id']]);

                // Update checklist status
                if ($checklist) {
                    $pdo->prepare("UPDATE po_inspection_checklists SET status = 'Rejected' WHERE id = ?")->execute([$checklist['id']]);
                }

                $pdo->commit();
                $success = "Inspection rejected.";

                // Refresh
                $existingApproval->execute([$po_no]);
                $approval = $existingApproval->fetch();
                $checklistStmt->execute([$po_no]);
                $checklist = $checklistStmt->fetch();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to reject: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
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
        .status-pass { background: #d1fae5; color: #065f46; }
        .status-fail { background: #fee2e2; color: #991b1b; }
        .status-conditional { background: #dbeafe; color: #1e40af; }
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

    <h1>Incoming Inspection Approval</h1>

    <p style="margin-bottom: 20px;">
        <a href="receive_all.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary">Back to Receive</a>
        <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary">View Checklist</a>
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

    <!-- PO Summary -->
    <div class="approval-card">
        <h3>Purchase Order Details</h3>
        <div class="info-row">
            <span class="info-label">PO Number</span>
            <span class="info-value"><?= htmlspecialchars($po_no) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Supplier</span>
            <span class="info-value"><?= htmlspecialchars($po['supplier_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Contact Person</span>
            <span class="info-value"><?= htmlspecialchars($po['contact_person'] ?? '-') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Line Items</span>
            <span class="info-value"><?= $summary['total_lines'] ?? 0 ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Quantity</span>
            <span class="info-value"><?= number_format($summary['total_qty'] ?? 0, 2) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Value</span>
            <span class="info-value">₹<?= number_format($summary['total_value'] ?? 0, 2) ?></span>
        </div>
    </div>

    <!-- Checklist Status -->
    <div class="approval-card">
        <h3>Inspection Checklist Status</h3>
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
                <span class="info-label">Supplier Invoice</span>
                <span class="info-value"><?= htmlspecialchars($checklist['supplier_invoice_no'] ?? '-') ?></span>
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
            <?php if ($checklist['remarks']): ?>
            <div class="info-row">
                <span class="info-label">Remarks</span>
                <span class="info-value"><?= htmlspecialchars($checklist['remarks']) ?></span>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="background: #fef3c7; padding: 15px; border-radius: 6px; color: #92400e;">
                Inspection checklist has not been submitted yet.
                <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>">Generate/Fill Checklist</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approval Status / Request -->
    <div class="approval-card">
        <h3>Inspection Approval</h3>

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
                                onclick="return confirm('Approve this inspection? Stock can then be received.');">
                            Approve Inspection
                        </button>
                        <button type="submit" name="action" value="reject" class="btn" style="background: #ef4444; color: white;"
                                onclick="return confirm('Reject this inspection?');">
                            Reject
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($approval['status'] === 'Approved'): ?>
            <div style="margin-top: 20px; background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                <strong>Approved!</strong> The stock can now be received into inventory.
                <a href="receive_all.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-success" style="margin-left: 15px;">Receive Stock</a>
            </div>
            <?php elseif ($approval['status'] === 'Rejected'): ?>
            <div style="margin-top: 20px; background: #fee2e2; padding: 15px; border-radius: 8px; color: #991b1b;">
                <strong>Rejected!</strong> Review the remarks and address the issues. Contact the supplier if needed.
            </div>
            <?php endif; ?>

        <?php elseif ($checklist && $checklist['status'] === 'Submitted'): ?>
            <!-- Request Approval Form -->
            <?php if (empty($approvers)): ?>
                <div style="background: #fee2e2; padding: 15px; border-radius: 6px; color: #991b1b;">
                    No approvers configured. Please <a href="../admin/po_inspection_approvers.php">add approvers</a> first.
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
                Submit the inspection checklist first before requesting approval.
                <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-primary" style="margin-left: 10px;">Go to Checklist</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
