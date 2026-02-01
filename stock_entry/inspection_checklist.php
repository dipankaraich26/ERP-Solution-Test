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
           s.supplier_name, s.contact_person, s.phone as supplier_phone
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

// Fetch PO line items
$linesStmt = $pdo->prepare("
    SELECT po.id, po.part_no, po.qty, po.rate, pm.part_name,
           COALESCE((SELECT SUM(se.received_qty) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted'),0) AS received
    FROM purchase_orders po
    LEFT JOIN part_master pm ON po.part_no = pm.part_no
    WHERE po.po_no = ?
");
$linesStmt->execute([$po_no]);
$poLines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if checklist already exists
$existingChecklist = $pdo->prepare("SELECT * FROM po_inspection_checklists WHERE po_no = ? ORDER BY id DESC LIMIT 1");
$existingChecklist->execute([$po_no]);
$checklist = $existingChecklist->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Generate new checklist
    if ($action === 'generate') {
        try {
            $pdo->beginTransaction();

            // Generate checklist number
            $maxNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(checklist_no, 4) AS UNSIGNED)) FROM po_inspection_checklists WHERE checklist_no LIKE 'IQC%'")->fetchColumn();
            $checklistNo = 'IQC' . str_pad(($maxNum ?: 0) + 1, 6, '0', STR_PAD_LEFT);

            // Create checklist header
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare("
                INSERT INTO po_inspection_checklists (po_no, checklist_no, created_by, status)
                VALUES (?, ?, ?, 'Draft')
            ");
            $stmt->execute([$po_no, $checklistNo, $userId]);
            $checklistId = $pdo->lastInsertId();

            // Copy template items to checklist
            $templates = $pdo->query("SELECT item_no, checkpoint, specification FROM po_inspection_checkpoint_templates WHERE is_active = 1 ORDER BY item_no")->fetchAll();

            $itemStmt = $pdo->prepare("
                INSERT INTO po_inspection_checklist_items (checklist_id, item_no, checkpoint, specification, result)
                VALUES (?, ?, ?, ?, 'Pending')
            ");

            foreach ($templates as $tpl) {
                $itemStmt->execute([$checklistId, $tpl['item_no'], $tpl['checkpoint'], $tpl['specification']]);
            }

            $pdo->commit();
            $success = "Inspection checklist $checklistNo generated successfully!";

            // Refresh checklist data
            $existingChecklist->execute([$po_no]);
            $checklist = $existingChecklist->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to generate checklist: " . $e->getMessage();
        }
    }

    // Save checklist (draft)
    if ($action === 'save' && $checklist) {
        try {
            $pdo->beginTransaction();

            // Update header
            $stmt = $pdo->prepare("
                UPDATE po_inspection_checklists
                SET inspector_name = ?, inspection_date = ?, supplier_invoice_no = ?, remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['inspector_name'] ?? null,
                $_POST['inspection_date'] ?? null,
                $_POST['supplier_invoice_no'] ?? null,
                $_POST['overall_remarks'] ?? null,
                $checklist['id']
            ]);

            // Update items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $updateItem = $pdo->prepare("
                    UPDATE po_inspection_checklist_items
                    SET result = ?, actual_value = ?, remarks = ?
                    WHERE id = ?
                ");

                foreach ($_POST['items'] as $itemId => $item) {
                    $updateItem->execute([
                        $item['result'] ?? 'Pending',
                        $item['actual_value'] ?? null,
                        $item['remarks'] ?? null,
                        $itemId
                    ]);
                }
            }

            $pdo->commit();
            $success = "Checklist saved successfully!";

            // Refresh checklist
            $existingChecklist->execute([$po_no]);
            $checklist = $existingChecklist->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to save checklist: " . $e->getMessage();
        }
    }

    // Submit checklist
    if ($action === 'submit' && $checklist) {
        try {
            $pdo->beginTransaction();

            // Update header
            $stmt = $pdo->prepare("
                UPDATE po_inspection_checklists
                SET inspector_name = ?, inspection_date = ?, supplier_invoice_no = ?, remarks = ?, status = 'Submitted', submitted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['inspector_name'] ?? null,
                $_POST['inspection_date'] ?? null,
                $_POST['supplier_invoice_no'] ?? null,
                $_POST['overall_remarks'] ?? null,
                $checklist['id']
            ]);

            // Update items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $updateItem = $pdo->prepare("
                    UPDATE po_inspection_checklist_items
                    SET result = ?, actual_value = ?, remarks = ?
                    WHERE id = ?
                ");

                foreach ($_POST['items'] as $itemId => $item) {
                    $updateItem->execute([
                        $item['result'] ?? 'Pending',
                        $item['actual_value'] ?? null,
                        $item['remarks'] ?? null,
                        $itemId
                    ]);
                }
            }

            // Calculate overall result
            $results = $pdo->prepare("SELECT result FROM po_inspection_checklist_items WHERE checklist_id = ?");
            $results->execute([$checklist['id']]);
            $allResults = $results->fetchAll(PDO::FETCH_COLUMN);

            $overallResult = 'Pass';
            if (in_array('Not OK', $allResults)) {
                $overallResult = 'Fail';
            } elseif (in_array('Conditional', $allResults)) {
                $overallResult = 'Conditional';
            } elseif (in_array('Pending', $allResults)) {
                $overallResult = 'Pending';
            }

            $pdo->prepare("UPDATE po_inspection_checklists SET overall_result = ? WHERE id = ?")->execute([$overallResult, $checklist['id']]);

            $pdo->commit();
            $success = "Checklist submitted successfully! Overall Result: $overallResult";

            // Refresh checklist
            $existingChecklist->execute([$po_no]);
            $checklist = $existingChecklist->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to submit checklist: " . $e->getMessage();
        }
    }
}

// Fetch checklist items if checklist exists
$checklistItems = [];
if ($checklist) {
    $itemsStmt = $pdo->prepare("SELECT * FROM po_inspection_checklist_items WHERE checklist_id = ? ORDER BY item_no");
    $itemsStmt->execute([$checklist['id']]);
    $checklistItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <style>
        @page {
            size: landscape;
            margin: 10mm;
        }
        @media print {
            .sidebar, .no-print { display: none !important; }
            .content { margin-left: 0 !important; padding: 10px !important; width: 100% !important; overflow: visible !important; height: auto !important; }
            body { background: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .checklist-table { font-size: 9pt; width: 100%; }
            .checklist-table th, .checklist-table td { padding: 5px 6px !important; }
            .po-header { padding: 10px !important; }
        }

        .po-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .po-header h1 { margin: 0 0 5px 0; font-size: 1.4em; }
        .po-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .po-detail-card {
            background: white;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .po-detail-card label {
            color: #6b7280;
            font-size: 0.75em;
            display: block;
            margin-bottom: 3px;
        }
        .po-detail-card span {
            font-weight: 600;
            font-size: 0.95em;
        }

        .table-scroll-container {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
        }

        .checklist-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            background: white;
        }
        .checklist-table th, .checklist-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .checklist-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .checklist-table tr:hover {
            background: #f0f7ff;
        }
        .result-select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.85em;
            min-width: 100px;
            cursor: pointer;
        }
        .result-select.ok { background: #d1fae5; border-color: #10b981; }
        .result-select.not-ok { background: #fee2e2; border-color: #ef4444; }
        .result-select.na { background: #f3f4f6; border-color: #9ca3af; }
        .result-select.pending { background: #fef3c7; border-color: #f59e0b; }
        .result-select.conditional { background: #dbeafe; border-color: #3b82f6; }
        .remarks-input {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            width: 100%;
            min-width: 120px;
            font-size: 0.85em;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.8em;
        }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-submitted { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .result-pass { background: #d1fae5; color: #065f46; }
        .result-fail { background: #fee2e2; color: #991b1b; }
        .result-pending { background: #fef3c7; color: #92400e; }
        .result-conditional { background: #dbeafe; color: #1e40af; }
        .section-title {
            background: #f3f4f6;
            padding: 8px 12px;
            margin: 15px 0 8px 0;
            border-radius: 6px;
            font-weight: 600;
            color: #374151;
            font-size: 0.95em;
        }
    </style>

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

    <!-- PO Header -->
    <div class="po-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h1>Incoming Inspection Checklist</h1>
                <p style="margin: 0; opacity: 0.9;">Purchase Order: <?= htmlspecialchars($po_no) ?></p>
            </div>
            <div class="no-print" style="display: flex; gap: 10px;">
                <button onclick="window.print()" class="btn btn-secondary">Print Checklist</button>
                <a href="receive_all.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary">Back to Receive</a>
            </div>
        </div>
    </div>

    <!-- PO Details -->
    <div class="po-details">
        <div class="po-detail-card">
            <label>PO Number</label>
            <span><?= htmlspecialchars($po_no) ?></span>
        </div>
        <div class="po-detail-card">
            <label>Supplier</label>
            <span><?= htmlspecialchars($po['supplier_name'] ?? 'N/A') ?></span>
        </div>
        <div class="po-detail-card">
            <label>Contact Person</label>
            <span><?= htmlspecialchars($po['contact_person'] ?? '-') ?></span>
        </div>
        <div class="po-detail-card">
            <label>PO Date</label>
            <span><?= $po['purchase_date'] ? date('d-M-Y', strtotime($po['purchase_date'])) : '-' ?></span>
        </div>
        <div class="po-detail-card">
            <label>Total Items</label>
            <span><?= count($poLines) ?></span>
        </div>
    </div>

    <!-- PO Line Items -->
    <div class="section-title">PO Line Items</div>
    <div class="table-scroll-container" style="margin-bottom: 15px;">
        <table class="checklist-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Ordered Qty</th>
                    <th>Already Received</th>
                    <th>Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($poLines as $idx => $line):
                    $pending = $line['qty'] - $line['received'];
                ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><strong><?= htmlspecialchars($line['part_no']) ?></strong></td>
                    <td><?= htmlspecialchars($line['part_name'] ?? '-') ?></td>
                    <td><?= number_format($line['qty'], 2) ?></td>
                    <td><?= number_format($line['received'], 2) ?></td>
                    <td style="color: <?= $pending > 0 ? '#059669' : '#dc2626' ?>; font-weight: 600;">
                        <?= number_format($pending, 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$checklist): ?>
        <!-- No Checklist - Generate Option -->
        <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 30px; border-radius: 10px; text-align: center;" class="no-print">
            <h3 style="margin: 0 0 15px 0; color: #92400e;">Inspection Checklist Not Generated</h3>
            <p style="color: #78350f; margin-bottom: 20px;">
                Generate an incoming inspection checklist to verify goods before receiving into inventory.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1em;">
                    Generate Inspection Checklist
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- Checklist exists -->
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Inspection Checklist: <?= htmlspecialchars($checklist['checklist_no']) ?></span>
            <div>
                <span class="status-badge status-<?= strtolower($checklist['status']) ?>"><?= $checklist['status'] ?></span>
                <?php if ($checklist['overall_result'] && $checklist['overall_result'] !== 'Pending'): ?>
                    <span class="status-badge result-<?= strtolower($checklist['overall_result']) ?>" style="margin-left: 10px;">
                        Result: <?= $checklist['overall_result'] ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" id="checklistForm">
            <?php $isEditable = ($checklist['status'] === 'Draft'); ?>

            <!-- Inspector Info -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e5e7eb;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Inspector Name</label>
                        <input type="text" name="inspector_name" value="<?= htmlspecialchars($checklist['inspector_name'] ?? '') ?>"
                               class="remarks-input" <?= $isEditable ? '' : 'readonly' ?> placeholder="Enter inspector name">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Inspection Date</label>
                        <input type="date" name="inspection_date" value="<?= htmlspecialchars($checklist['inspection_date'] ?? date('Y-m-d')) ?>"
                               class="remarks-input" <?= $isEditable ? '' : 'readonly' ?>>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;">Supplier Invoice No</label>
                        <input type="text" name="supplier_invoice_no" value="<?= htmlspecialchars($checklist['supplier_invoice_no'] ?? '') ?>"
                               class="remarks-input" <?= $isEditable ? '' : 'readonly' ?> placeholder="Supplier invoice number">
                    </div>
                </div>
            </div>

            <!-- Checklist Items -->
            <div class="table-scroll-container">
                <table class="checklist-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Checkpoint</th>
                            <th style="width: 200px;">Specification</th>
                            <th style="width: 130px;">Result</th>
                            <th style="width: 120px;">Actual Value</th>
                            <th style="width: 200px;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checklistItems as $item): ?>
                        <tr>
                            <td><?= $item['item_no'] ?></td>
                            <td><?= htmlspecialchars($item['checkpoint']) ?></td>
                            <td style="font-size: 0.9em; color: #6b7280;"><?= htmlspecialchars($item['specification'] ?? '-') ?></td>
                            <td>
                                <?php if ($isEditable): ?>
                                    <select name="items[<?= $item['id'] ?>][result]"
                                            class="result-select <?= strtolower(str_replace(' ', '-', $item['result'])) ?>"
                                            onchange="updateResultClass(this)">
                                        <option value="Pending" <?= $item['result'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="OK" <?= $item['result'] === 'OK' ? 'selected' : '' ?>>OK</option>
                                        <option value="Not OK" <?= $item['result'] === 'Not OK' ? 'selected' : '' ?>>Not OK</option>
                                        <option value="Conditional" <?= $item['result'] === 'Conditional' ? 'selected' : '' ?>>Conditional</option>
                                        <option value="NA" <?= $item['result'] === 'NA' ? 'selected' : '' ?>>N/A</option>
                                    </select>
                                <?php else: ?>
                                    <span class="status-badge <?php
                                        echo $item['result'] === 'OK' ? 'result-pass' :
                                            ($item['result'] === 'Not OK' ? 'result-fail' :
                                            ($item['result'] === 'Conditional' ? 'result-conditional' : 'result-pending'));
                                    ?>"><?= htmlspecialchars($item['result']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isEditable): ?>
                                    <input type="text" name="items[<?= $item['id'] ?>][actual_value]"
                                           value="<?= htmlspecialchars($item['actual_value'] ?? '') ?>"
                                           class="remarks-input" placeholder="Value">
                                <?php else: ?>
                                    <?= htmlspecialchars($item['actual_value'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isEditable): ?>
                                    <input type="text" name="items[<?= $item['id'] ?>][remarks]"
                                           value="<?= htmlspecialchars($item['remarks'] ?? '') ?>"
                                           class="remarks-input" placeholder="Remarks">
                                <?php else: ?>
                                    <?= htmlspecialchars($item['remarks'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Overall Remarks -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; border: 1px solid #e5e7eb;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Overall Remarks</label>
                <textarea name="overall_remarks" rows="3" class="remarks-input" <?= $isEditable ? '' : 'readonly' ?>
                          placeholder="Enter any overall remarks or observations..."><?= htmlspecialchars($checklist['remarks'] ?? '') ?></textarea>
            </div>

            <!-- Action Buttons -->
            <?php if ($isEditable): ?>
            <div class="no-print" style="margin-top: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                <button type="submit" name="action" value="save" class="btn btn-secondary" style="padding: 12px 30px;">
                    Save Draft
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-primary" style="padding: 12px 30px;"
                        onclick="return confirm('Submit this checklist? You will not be able to edit after submission.');">
                    Submit Checklist
                </button>
            </div>
            <?php elseif ($checklist['status'] === 'Submitted'): ?>
            <div class="no-print" style="margin-top: 20px; background: #e0e7ff; padding: 15px; border-radius: 8px; color: #3730a3;">
                <strong>Checklist Submitted!</strong> Proceed to request approval.
                <a href="request_inspection_approval.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-primary" style="margin-left: 15px;">Request Approval</a>
            </div>
            <?php elseif ($checklist['status'] === 'Approved'): ?>
            <div class="no-print" style="margin-top: 20px; background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                <strong>Inspection Approved!</strong> You can now receive the stock.
                <a href="receive_all.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-success" style="margin-left: 15px;">Receive Stock</a>
            </div>
            <?php endif; ?>
        </form>

        <!-- Signature Section for Print -->
        <div style="margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; page-break-inside: avoid;">
            <div style="text-align: center; padding-top: 40px; border-top: 1px solid #000;">
                <p style="margin: 5px 0;"><strong>Inspector Signature</strong></p>
                <p style="margin: 0; font-size: 0.9em;"><?= htmlspecialchars($checklist['inspector_name'] ?? '') ?></p>
            </div>
            <div style="text-align: center; padding-top: 40px; border-top: 1px solid #000;">
                <p style="margin: 5px 0;"><strong>QC Manager</strong></p>
                <p style="margin: 0; font-size: 0.9em;">Date: ___________</p>
            </div>
            <div style="text-align: center; padding-top: 40px; border-top: 1px solid #000;">
                <p style="margin: 5px 0;"><strong>Approved By</strong></p>
                <p style="margin: 0; font-size: 0.9em;">Date: ___________</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateResultClass(select) {
    select.className = 'result-select ' + select.value.toLowerCase().replace(' ', '-');
}
</script>

<?php include '../includes/footer.php'; ?>
