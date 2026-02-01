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
    SELECT w.*, p.part_name, b.bom_no, b.description as bom_desc,
           e.first_name, e.last_name, e.emp_id as assigned_emp_id
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    LEFT JOIN bom_master b ON w.bom_id = b.id
    LEFT JOIN employees e ON w.assigned_to = e.id
    WHERE w.id = ?
");
$woStmt->execute([$wo_id]);
$wo = $woStmt->fetch();

if (!$wo) {
    die("Work Order not found");
}

// Check if checklist already exists
$existingChecklist = $pdo->prepare("SELECT * FROM wo_quality_checklists WHERE work_order_id = ? ORDER BY id DESC LIMIT 1");
$existingChecklist->execute([$wo_id]);
$checklist = $existingChecklist->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Generate new checklist
    if ($action === 'generate') {
        try {
            $pdo->beginTransaction();

            // Generate checklist number
            $maxNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(checklist_no, 4) AS UNSIGNED)) FROM wo_quality_checklists WHERE checklist_no LIKE 'QC-%'")->fetchColumn();
            $checklistNo = 'QC-' . str_pad(($maxNum ?: 0) + 1, 6, '0', STR_PAD_LEFT);

            // Create checklist header
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare("
                INSERT INTO wo_quality_checklists (work_order_id, checklist_no, created_by, status)
                VALUES (?, ?, ?, 'Draft')
            ");
            $stmt->execute([$wo_id, $checklistNo, $userId]);
            $checklistId = $pdo->lastInsertId();

            // Copy template items to checklist
            $templates = $pdo->query("SELECT item_no, checkpoint, specification FROM wo_quality_checkpoint_templates WHERE is_active = 1 ORDER BY item_no")->fetchAll();

            $itemStmt = $pdo->prepare("
                INSERT INTO wo_quality_checklist_items (checklist_id, item_no, checkpoint, specification, result)
                VALUES (?, ?, ?, ?, 'Pending')
            ");

            foreach ($templates as $tpl) {
                $itemStmt->execute([$checklistId, $tpl['item_no'], $tpl['checkpoint'], $tpl['specification']]);
            }

            $pdo->commit();
            $success = "Quality checklist $checklistNo generated successfully!";

            // Refresh checklist data
            $existingChecklist->execute([$wo_id]);
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
                UPDATE wo_quality_checklists
                SET inspector_name = ?, inspection_date = ?, remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['inspector_name'] ?? null,
                $_POST['inspection_date'] ?? null,
                $_POST['overall_remarks'] ?? null,
                $checklist['id']
            ]);

            // Update items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $updateItem = $pdo->prepare("
                    UPDATE wo_quality_checklist_items
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
            $existingChecklist->execute([$wo_id]);
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
                UPDATE wo_quality_checklists
                SET inspector_name = ?, inspection_date = ?, remarks = ?, status = 'Submitted', submitted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['inspector_name'] ?? null,
                $_POST['inspection_date'] ?? null,
                $_POST['overall_remarks'] ?? null,
                $checklist['id']
            ]);

            // Update items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $updateItem = $pdo->prepare("
                    UPDATE wo_quality_checklist_items
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
            $results = $pdo->prepare("SELECT result FROM wo_quality_checklist_items WHERE checklist_id = ?");
            $results->execute([$checklist['id']]);
            $allResults = $results->fetchAll(PDO::FETCH_COLUMN);

            $overallResult = 'Pass';
            if (in_array('Not OK', $allResults)) {
                $overallResult = 'Fail';
            } elseif (in_array('Pending', $allResults)) {
                $overallResult = 'Pending';
            }

            $pdo->prepare("UPDATE wo_quality_checklists SET overall_result = ? WHERE id = ?")->execute([$overallResult, $checklist['id']]);

            $pdo->commit();
            $success = "Checklist submitted successfully! Overall Result: $overallResult";

            // Refresh checklist
            $existingChecklist->execute([$wo_id]);
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
    $itemsStmt = $pdo->prepare("SELECT * FROM wo_quality_checklist_items WHERE checklist_id = ? ORDER BY item_no");
    $itemsStmt->execute([$checklist['id']]);
    $checklistItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch BOM items for display
$bomItems = [];
if ($wo['bom_id']) {
    $bomStmt = $pdo->prepare("
        SELECT bi.*, pm.part_name
        FROM bom_items bi
        LEFT JOIN part_master pm ON bi.component_part_no = pm.part_no
        WHERE bi.bom_id = ?
        ORDER BY bi.id
    ");
    $bomStmt->execute([$wo['bom_id']]);
    $bomItems = $bomStmt->fetchAll(PDO::FETCH_ASSOC);
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
            .wo-header { padding: 10px !important; }
            .wo-details { gap: 10px !important; }
            .wo-detail-card { padding: 8px !important; }
            .table-scroll-container { overflow: visible !important; max-height: none !important; }
        }

        /* Full width landscape layout */
        .quality-checklist-page {
            max-width: 100%;
            margin: 0;
        }
        .wo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .wo-header h1 { margin: 0 0 5px 0; font-size: 1.4em; }
        .wo-details {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .wo-detail-card {
            background: white;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .wo-detail-card label {
            color: #6b7280;
            font-size: 0.75em;
            display: block;
            margin-bottom: 3px;
        }
        .wo-detail-card span {
            font-weight: 600;
            font-size: 0.95em;
        }

        /* Scrollable table container - horizontal scroll only */
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f3f4f6;
        }
        .table-scroll-container::-webkit-scrollbar {
            height: 10px;
        }
        .table-scroll-container::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 5px;
        }
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }
        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .checklist-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            background: white;
        }
        .checklist-table th, .checklist-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .checklist-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .checklist-table th:first-child {
            width: 50px;
        }
        .checklist-table th:nth-child(2) {
            min-width: 250px;
            white-space: normal;
        }
        .checklist-table th:nth-child(3) {
            min-width: 180px;
            white-space: normal;
        }
        .checklist-table tr:hover {
            background: #f0f7ff;
        }
        .checklist-table tr:nth-child(even) {
            background: #fafafa;
        }
        .checklist-table tr:nth-child(even):hover {
            background: #f0f7ff;
        }
        .result-select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.85em;
            min-width: 90px;
            cursor: pointer;
        }
        .result-select.ok { background: #d1fae5; border-color: #10b981; }
        .result-select.not-ok { background: #fee2e2; border-color: #ef4444; }
        .result-select.na { background: #f3f4f6; border-color: #9ca3af; }
        .result-select.pending { background: #fef3c7; border-color: #f59e0b; }
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
        .section-title {
            background: #f3f4f6;
            padding: 8px 12px;
            margin: 15px 0 8px 0;
            border-radius: 6px;
            font-weight: 600;
            color: #374151;
            font-size: 0.95em;
        }

        /* Compact BOM table */
        .bom-table-container {
            overflow-x: auto;
            margin-bottom: 15px;
        }
        .bom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .bom-table th, .bom-table td {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
        }
        .bom-table th {
            background: #f3f4f6;
        }

        /* Floating action bar */
        .floating-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 12px 15px;
            border-top: 2px solid #667eea;
            margin-top: 15px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
            z-index: 100;
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

    <!-- Work Order Header -->
    <div class="wo-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h1>Quality Checklist</h1>
                <p style="margin: 0; opacity: 0.9;">Work Order: <?= htmlspecialchars($wo['wo_no']) ?></p>
            </div>
            <div class="no-print" style="display: flex; gap: 10px;">
                <button onclick="window.print()" class="btn btn-secondary">Print Checklist</button>
                <a href="view.php?id=<?= $wo_id ?>" class="btn btn-secondary">Back to WO</a>
            </div>
        </div>
    </div>

    <!-- Work Order Details -->
    <div class="wo-details">
        <div class="wo-detail-card">
            <label>Work Order No</label>
            <span><?= htmlspecialchars($wo['wo_no']) ?></span>
        </div>
        <div class="wo-detail-card">
            <label>Part No</label>
            <span><?= htmlspecialchars($wo['part_no']) ?></span>
        </div>
        <div class="wo-detail-card">
            <label>Part Name</label>
            <span><?= htmlspecialchars($wo['part_name'] ?? $wo['part_no']) ?></span>
        </div>
        <div class="wo-detail-card">
            <label>Quantity</label>
            <span><?= htmlspecialchars($wo['qty']) ?></span>
        </div>
        <div class="wo-detail-card">
            <label>BOM</label>
            <span><?= $wo['bom_no'] ? htmlspecialchars($wo['bom_no']) : '-' ?></span>
        </div>
        <div class="wo-detail-card">
            <label>Assigned To</label>
            <span><?= $wo['first_name'] ? htmlspecialchars($wo['first_name'] . ' ' . $wo['last_name']) : '-' ?></span>
        </div>
    </div>

    <!-- BOM Components (if exists) -->
    <?php if (!empty($bomItems)): ?>
    <div class="section-title">BOM Components</div>
    <div class="table-scroll-container" style="margin-bottom: 15px;">
        <table class="checklist-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part No</th>
                    <th>Component Name</th>
                    <th>Qty per Unit</th>
                    <th>Total Required</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bomItems as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($item['component_part_no']) ?></td>
                    <td><?= htmlspecialchars($item['part_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($item['qty']) ?></td>
                    <td><?= $item['qty'] * $wo['qty'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!$checklist): ?>
        <!-- No Checklist - Generate Option -->
        <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 30px; border-radius: 10px; text-align: center;" class="no-print">
            <h3 style="margin: 0 0 15px 0; color: #92400e;">Quality Checklist Not Generated</h3>
            <p style="color: #78350f; margin-bottom: 20px;">
                Generate a quality checklist to record inspection results before closing this work order.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1em;">
                    Generate Quality Checklist
                </button>
            </form>
        </div>
    <?php else: ?>
        <!-- Checklist exists -->
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Quality Checklist: <?= htmlspecialchars($checklist['checklist_no']) ?></span>
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
                            <th style="width: 120px;">Result</th>
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
                                        <option value="NA" <?= $item['result'] === 'NA' ? 'selected' : '' ?>>N/A</option>
                                    </select>
                                <?php else: ?>
                                    <span class="status-badge <?php
                                        echo $item['result'] === 'OK' ? 'result-pass' :
                                            ($item['result'] === 'Not OK' ? 'result-fail' : 'result-pending');
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
                <strong>Checklist Submitted!</strong> Proceed to request approval from the Work Order page.
                <a href="view.php?id=<?= $wo_id ?>" class="btn btn-primary" style="margin-left: 15px;">Go to Work Order</a>
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
