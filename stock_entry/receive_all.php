<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/procurement_helper.php";

// This page shows a receive form for all lines under a PO and processes the POST to receive quantities.

if (!isset($_REQUEST['po_no'])) {
    header("Location: index.php");
    exit;
}

$po_no = $_REQUEST['po_no'];

// Fetch all lines under this PO with part names and remaining qty
$linesStmt = $pdo->prepare("SELECT po.id, po.part_no, po.qty AS ordered, pm.part_name,
    COALESCE((SELECT SUM(se.received_qty) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted'),0) AS received
    FROM purchase_orders po
    JOIN part_master pm ON po.part_no = pm.part_no
    WHERE po.po_no = ?");
$linesStmt->execute([$po_no]);
$lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$lines) {
    setModal('Receive Failed', 'PO not found');
    header("Location: index.php");
    exit;
}

// Check if inspection checklist tables exist
$inspectionEnabled = false;
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'po_inspection_checklists'");
    $inspectionEnabled = $tableCheck->rowCount() > 0;
} catch (Exception $e) {
    $inspectionEnabled = false;
}

// Fetch inspection checklist status if enabled
$checklist = null;
$checklistItems = [];
$approval = null;
$canReceive = true;

if ($inspectionEnabled) {
    // Check for existing checklist
    $checklistStmt = $pdo->prepare("SELECT * FROM po_inspection_checklists WHERE po_no = ? ORDER BY id DESC LIMIT 1");
    $checklistStmt->execute([$po_no]);
    $checklist = $checklistStmt->fetch(PDO::FETCH_ASSOC);

    if ($checklist) {
        // Fetch checklist items
        $itemsStmt = $pdo->prepare("SELECT * FROM po_inspection_checklist_items WHERE checklist_id = ? ORDER BY item_no");
        $itemsStmt->execute([$checklist['id']]);
        $checklistItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch approval if submitted
        if ($checklist['status'] === 'submitted' || $checklist['status'] === 'approved' || $checklist['status'] === 'rejected') {
            $approvalStmt = $pdo->prepare("
                SELECT a.*, e.first_name, e.last_name
                FROM po_inspection_approvals a
                LEFT JOIN employees e ON a.approver_id = e.id
                WHERE a.checklist_id = ?
                ORDER BY a.id DESC LIMIT 1
            ");
            $approvalStmt->execute([$checklist['id']]);
            $approval = $approvalStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Determine if receiving is allowed based on inspection status
    if (!$checklist) {
        $canReceive = false; // No checklist created yet
    } elseif ($checklist['status'] === 'draft') {
        $canReceive = false; // Checklist not submitted
    } elseif ($checklist['status'] === 'submitted') {
        $canReceive = false; // Awaiting approval
    } elseif ($checklist['status'] === 'rejected') {
        $canReceive = false; // Rejected - needs re-inspection
    } elseif ($checklist['status'] === 'approved') {
        $canReceive = true; // Approved - can receive
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check inspection approval before processing
    if ($inspectionEnabled && !$canReceive) {
        setModal('Receive Failed', 'Incoming inspection checklist must be approved before receiving stock.');
        header('Location: receive_all.php?po_no=' . urlencode($po_no));
        exit;
    }

    // expected inputs: line_id[], received_qty[], invoice_no (optional)
    $lineIds = $_POST['line_id'] ?? [];
    $recvQtys = $_POST['received_qty'] ?? [];
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // normalize
    if (!is_array($lineIds)) $lineIds = [$lineIds];
    if (!is_array($recvQtys)) $recvQtys = [$recvQtys];

    $pdo->beginTransaction();
    try {
        $insertStock = $pdo->prepare("INSERT INTO stock_entries (po_id, part_no, received_qty, invoice_no, status) VALUES (?, ?, ?, ?, 'posted')");
        $upInventory = $pdo->prepare("INSERT INTO inventory (part_no, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)");
        $updatePo = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");

        foreach ($lineIds as $i => $lid) {
            $lid = (int)$lid;
            $qty = isset($recvQtys[$i]) ? (float)$recvQtys[$i] : 0;
            if ($qty <= 0) continue; // skip zero entries

            // find matching line info
            $lineInfo = null;
            foreach ($lines as $ln) if ($ln['id'] == $lid) { $lineInfo = $ln; break; }
            if (!$lineInfo) throw new Exception('Invalid PO line: ' . $lid);

            $remaining = $lineInfo['ordered'] - $lineInfo['received'];
            if ($qty > $remaining) throw new Exception('Received qty for ' . $lineInfo['part_no'] . ' exceeds remaining');

            // insert stock entry
            $insertStock->execute([$lid, $lineInfo['part_no'], $qty, $invoiceNo]);

            // update inventory
            $upInventory->execute([$lineInfo['part_no'], $qty]);

            // update PO line status
            $newStatus = ($qty + $lineInfo['received']) >= $lineInfo['ordered'] ? 'closed' : 'partial';
            $updatePo->execute([$newStatus, $lid]);

            // Sync PO closure back to procurement plan tracking
            if ($newStatus === 'closed') {
                syncPoStatusToPlan($pdo, $lid, $lineInfo['part_no'], 'closed');
            }
        }

        $pdo->commit();
        setModal('Received', 'Stock received successfully');
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal('Receive Failed', $e->getMessage());
        header('Location: receive_all.php?po_no=' . urlencode($po_no));
        exit;
    }
}

// Include header and sidebar AFTER all redirects
include "../includes/header.php";
include "../includes/sidebar.php";
?>

<style>
    .receive-form {
        max-width: 900px;
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .receive-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .receive-table th, .receive-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .receive-table th {
        background: #4a90d9;
        color: #fff;
        font-weight: 600;
    }
    .receive-table input[type="number"] {
        width: 100px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .form-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }
    .form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    .form-row input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .btn-group {
        display: flex;
        gap: 10px;
    }
    body.dark .receive-form {
        background: #2c3e50;
    }
    body.dark .receive-table th {
        background: #2980b9;
        color: #fff;
    }
    body.dark .receive-table td {
        border-bottom-color: #4a6278;
    }
    body.dark .form-row input, body.dark .receive-table input {
        background: #34495e;
        border-color: #4a6278;
        color: #ecf0f1;
    }
</style>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Receive Stock for <?= htmlspecialchars($po_no) ?></h1>

    <a href="index.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Stock Entries</a>

    <?php if ($inspectionEnabled): ?>
    <!-- Inspection Workflow Status -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
        <h3 style="margin: 0 0 15px 0; color: #1e293b;">Incoming Inspection Workflow</h3>

        <!-- Workflow Steps -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php
            $step1Done = $checklist !== null;
            $step2Done = $checklist && $checklist['status'] !== 'draft';
            $step3Done = $checklist && in_array($checklist['status'], ['submitted', 'approved', 'rejected']);
            $step4Done = $checklist && $checklist['status'] === 'approved';
            ?>

            <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; border-radius: 8px;
                        background: <?= $step1Done ? '#d1fae5' : '#f1f5f9' ?>;
                        border: 2px solid <?= $step1Done ? '#10b981' : '#cbd5e1' ?>;">
                <div style="font-size: 1.5em; margin-bottom: 5px;"><?= $step1Done ? '✓' : '1' ?></div>
                <div style="font-weight: 600; color: <?= $step1Done ? '#065f46' : '#64748b' ?>;">Generate Checklist</div>
            </div>

            <div style="display: flex; align-items: center; color: #cbd5e1; font-size: 1.5em;">→</div>

            <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; border-radius: 8px;
                        background: <?= $step2Done ? '#d1fae5' : '#f1f5f9' ?>;
                        border: 2px solid <?= $step2Done ? '#10b981' : '#cbd5e1' ?>;">
                <div style="font-size: 1.5em; margin-bottom: 5px;"><?= $step2Done ? '✓' : '2' ?></div>
                <div style="font-weight: 600; color: <?= $step2Done ? '#065f46' : '#64748b' ?>;">Submit Checklist</div>
            </div>

            <div style="display: flex; align-items: center; color: #cbd5e1; font-size: 1.5em;">→</div>

            <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; border-radius: 8px;
                        background: <?= $step3Done ? ($checklist && $checklist['status'] === 'rejected' ? '#fee2e2' : '#d1fae5') : '#f1f5f9' ?>;
                        border: 2px solid <?= $step3Done ? ($checklist && $checklist['status'] === 'rejected' ? '#ef4444' : '#10b981') : '#cbd5e1' ?>;">
                <div style="font-size: 1.5em; margin-bottom: 5px;"><?= $step3Done ? ($checklist && $checklist['status'] === 'rejected' ? '✗' : '✓') : '3' ?></div>
                <div style="font-weight: 600; color: <?= $step3Done ? ($checklist && $checklist['status'] === 'rejected' ? '#991b1b' : '#065f46') : '#64748b' ?>;">Request Approval</div>
            </div>

            <div style="display: flex; align-items: center; color: #cbd5e1; font-size: 1.5em;">→</div>

            <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; border-radius: 8px;
                        background: <?= $step4Done ? '#d1fae5' : '#f1f5f9' ?>;
                        border: 2px solid <?= $step4Done ? '#10b981' : '#cbd5e1' ?>;">
                <div style="font-size: 1.5em; margin-bottom: 5px;"><?= $step4Done ? '✓' : '4' ?></div>
                <div style="font-weight: 600; color: <?= $step4Done ? '#065f46' : '#64748b' ?>;">Receive Stock</div>
            </div>
        </div>

        <!-- Current Status and Actions -->
        <?php if (!$checklist): ?>
            <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <strong style="color: #92400e;">Action Required:</strong>
                <span style="color: #78350f;">Complete incoming inspection checklist before receiving stock.</span>
            </div>
            <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-primary">
                Start Incoming Inspection
            </a>

        <?php elseif ($checklist['status'] === 'draft'): ?>
            <div style="background: #dbeafe; border: 1px solid #3b82f6; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <strong style="color: #1e40af;">Checklist Status:</strong>
                <span style="color: #1e3a8a;">Draft - Complete and submit the inspection checklist.</span>
            </div>
            <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-primary">
                Continue Inspection Checklist
            </a>

        <?php elseif ($checklist['status'] === 'submitted'): ?>
            <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <strong style="color: #92400e;">Checklist Status:</strong>
                <span style="color: #78350f;">Submitted - Awaiting approval. Request approval from an authorized approver.</span>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary">
                    View Checklist
                </a>
                <a href="request_inspection_approval.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-primary">
                    Request Approval
                </a>
            </div>

        <?php elseif ($checklist['status'] === 'rejected'): ?>
            <div style="background: #fee2e2; border: 1px solid #ef4444; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <strong style="color: #991b1b;">Checklist Status:</strong>
                <span style="color: #7f1d1d;">Rejected by <?= htmlspecialchars(($approval['first_name'] ?? '') . ' ' . ($approval['last_name'] ?? '')) ?>.</span>
                <?php if (!empty($approval['remarks'])): ?>
                    <br><strong style="color: #991b1b;">Reason:</strong> <?= htmlspecialchars($approval['remarks']) ?>
                <?php endif; ?>
            </div>
            <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-danger">
                Review & Re-submit Checklist
            </a>

        <?php elseif ($checklist['status'] === 'approved'): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <strong style="color: #065f46;">Checklist Status:</strong>
                <span style="color: #064e3b;">Approved by <?= htmlspecialchars(($approval['first_name'] ?? '') . ' ' . ($approval['last_name'] ?? '')) ?>
                on <?= date('d M Y H:i', strtotime($approval['approved_at'] ?? $approval['created_at'] ?? 'now')) ?>.</span>
                <?php if (!empty($approval['remarks'])): ?>
                    <br><strong style="color: #065f46;">Remarks:</strong> <?= htmlspecialchars($approval['remarks']) ?>
                <?php endif; ?>
            </div>
            <a href="inspection_checklist.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary" style="margin-bottom: 10px;">
                View Inspection Checklist
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$inspectionEnabled || $canReceive): ?>
    <!-- Receive Stock Form -->
    <div class="receive-form">
        <form method="post">
            <input type="hidden" name="po_no" value="<?= htmlspecialchars($po_no) ?>">

            <table class="receive-table">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Ordered</th>
                        <th>Received</th>
                        <th>Remaining</th>
                        <th>Receive Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $ln):
                        $remaining = $ln['ordered'] - $ln['received'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ln['part_no']) ?></strong></td>
                        <td><?= htmlspecialchars($ln['part_name']) ?></td>
                        <td><?= htmlspecialchars($ln['ordered']) ?></td>
                        <td><?= htmlspecialchars($ln['received']) ?></td>
                        <td style="color: <?= $remaining > 0 ? '#28a745' : '#dc3545' ?>;">
                            <strong><?= htmlspecialchars($remaining) ?></strong>
                        </td>
                        <td>
                            <input type="hidden" name="line_id[]" value="<?= $ln['id'] ?>">
                            <input type="number" step="0.001" name="received_qty[]" min="0" max="<?= $remaining ?>" value="<?= $remaining > 0 ? $remaining : 0 ?>" <?= $remaining <= 0 ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-row">
                <div>
                    <label>Invoice No</label>
                    <input type="text" name="invoice_no" placeholder="Supplier invoice number">
                </div>
                <div>
                    <label>Received Date</label>
                    <input type="date" name="received_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label>Remarks</label>
                    <input type="text" name="remarks" placeholder="Optional remarks">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-success">Confirm Receipt</button>
                <button type="button" class="btn btn-primary" onclick="fillAllRemaining()">Fill All Remaining</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php elseif ($inspectionEnabled && !$canReceive): ?>
    <!-- Blocked - Inspection Required -->
    <div class="receive-form" style="background: #fef3c7; border: 2px solid #f59e0b;">
        <h3 style="margin: 0 0 15px 0; color: #92400e;">Stock Receipt Blocked</h3>
        <p style="color: #78350f; margin: 0;">
            Stock cannot be received until the incoming inspection checklist is completed and approved.
            Please complete the inspection workflow above.
        </p>

        <!-- Show line items in read-only mode -->
        <table class="receive-table" style="margin-top: 20px; opacity: 0.7;">
            <thead>
                <tr>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Ordered</th>
                    <th>Received</th>
                    <th>Remaining</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $ln):
                    $remaining = $ln['ordered'] - $ln['received'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ln['part_no']) ?></strong></td>
                    <td><?= htmlspecialchars($ln['part_name']) ?></td>
                    <td><?= htmlspecialchars($ln['ordered']) ?></td>
                    <td><?= htmlspecialchars($ln['received']) ?></td>
                    <td style="color: <?= $remaining > 0 ? '#28a745' : '#dc3545' ?>;">
                        <strong><?= htmlspecialchars($remaining) ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function fillAllRemaining() {
    document.querySelectorAll('input[name="received_qty[]"]').forEach(input => {
        if (!input.disabled) {
            input.value = input.max;
        }
    });
}
</script>

</body>
</html>
