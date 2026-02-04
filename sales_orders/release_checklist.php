<?php
/**
 * Sales Order Release Checklist
 * Complete checklist before releasing SO
 */
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$so_no = $_GET['so_no'] ?? '';

if (!$so_no) {
    header("Location: index.php");
    exit;
}

// Check if tables exist
try {
    $pdo->query("SELECT 1 FROM so_release_checklist LIMIT 1");
} catch (PDOException $e) {
    header("Location: ../admin/setup_so_release_checklist.php");
    exit;
}

// Fetch sales order
$stmt = $pdo->prepare("
    SELECT so.*, p.part_name,
           c.company_name, c.customer_name,
           cp.po_no as customer_po_no
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    WHERE so.so_no = ?
    ORDER BY so.id
    LIMIT 1
");
$stmt->execute([$so_no]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    setModal("Error", "Sales Order not found");
    header("Location: index.php");
    exit;
}

if ($order['status'] !== 'open') {
    setModal("Error", "This Sales Order has already been released or is not in open status");
    header("Location: view.php?so_no=" . urlencode($so_no));
    exit;
}

// Get existing checklist if any
$stmt = $pdo->prepare("SELECT * FROM so_release_checklist WHERE so_no = ?");
$stmt->execute([$so_no]);
$checklist = $stmt->fetch(PDO::FETCH_ASSOC);

// Get existing attachments
$stmt = $pdo->prepare("SELECT * FROM so_release_attachments WHERE so_no = ? ORDER BY uploaded_at DESC");
$stmt->execute([$so_no]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get checklist items
$checklistItems = [];
try {
    $checklistItems = $pdo->query("SELECT * FROM so_checklist_items WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_checklist') {
        // Collect all checkbox values
        $machine_performance_ok = isset($_POST['machine_performance_ok']) ? 1 : 0;
        $machine_performance_remarks = trim($_POST['machine_performance_remarks'] ?? '');

        $functional_performance_ok = isset($_POST['functional_performance_ok']) ? 1 : 0;
        $functional_performance_remarks = trim($_POST['functional_performance_remarks'] ?? '');

        $quality_visual_inspection = isset($_POST['quality_visual_inspection']) ? 1 : 0;
        $quality_dimensional_check = isset($_POST['quality_dimensional_check']) ? 1 : 0;
        $quality_safety_check = isset($_POST['quality_safety_check']) ? 1 : 0;
        $quality_packaging_ok = isset($_POST['quality_packaging_ok']) ? 1 : 0;
        $quality_remarks = trim($_POST['quality_remarks'] ?? '');

        $govt_compliance_checked = isset($_POST['govt_compliance_checked']) ? 1 : 0;
        $govt_compliance_remarks = trim($_POST['govt_compliance_remarks'] ?? '');

        // Check if all mandatory items are completed
        $all_mandatory_complete = $machine_performance_ok && $functional_performance_ok &&
                                   $quality_visual_inspection && $quality_dimensional_check &&
                                   $quality_safety_check && $quality_packaging_ok;

        $checklist_completed = $all_mandatory_complete ? 1 : 0;

        try {
            if ($checklist) {
                // Update existing
                $stmt = $pdo->prepare("
                    UPDATE so_release_checklist SET
                        machine_performance_ok = ?,
                        machine_performance_remarks = ?,
                        functional_performance_ok = ?,
                        functional_performance_remarks = ?,
                        quality_visual_inspection = ?,
                        quality_dimensional_check = ?,
                        quality_safety_check = ?,
                        quality_packaging_ok = ?,
                        quality_remarks = ?,
                        govt_compliance_checked = ?,
                        govt_compliance_remarks = ?,
                        checklist_completed = ?,
                        completed_by = ?,
                        completed_by_name = ?,
                        completed_at = NOW()
                    WHERE so_no = ?
                ");
                $stmt->execute([
                    $machine_performance_ok, $machine_performance_remarks,
                    $functional_performance_ok, $functional_performance_remarks,
                    $quality_visual_inspection, $quality_dimensional_check,
                    $quality_safety_check, $quality_packaging_ok, $quality_remarks,
                    $govt_compliance_checked, $govt_compliance_remarks,
                    $checklist_completed,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown',
                    $so_no
                ]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("
                    INSERT INTO so_release_checklist (
                        so_no, machine_performance_ok, machine_performance_remarks,
                        functional_performance_ok, functional_performance_remarks,
                        quality_visual_inspection, quality_dimensional_check,
                        quality_safety_check, quality_packaging_ok, quality_remarks,
                        govt_compliance_checked, govt_compliance_remarks,
                        checklist_completed, completed_by, completed_by_name, completed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $so_no,
                    $machine_performance_ok, $machine_performance_remarks,
                    $functional_performance_ok, $functional_performance_remarks,
                    $quality_visual_inspection, $quality_dimensional_check,
                    $quality_safety_check, $quality_packaging_ok, $quality_remarks,
                    $govt_compliance_checked, $govt_compliance_remarks,
                    $checklist_completed,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown'
                ]);
            }

            $success = "Checklist saved successfully!";

            // Refresh checklist data
            $stmt = $pdo->prepare("SELECT * FROM so_release_checklist WHERE so_no = ?");
            $stmt->execute([$so_no]);
            $checklist = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $errors[] = "Error saving checklist: " . $e->getMessage();
        }
    }

    // Handle file upload
    if ($action === 'upload_attachment' && isset($_FILES['attachment'])) {
        $file = $_FILES['attachment'];
        $attachment_type = $_POST['attachment_type'] ?? 'Other';
        $description = trim($_POST['attachment_description'] ?? '');

        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/so_release/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = 'SO_' . str_replace(['/', '\\', '-'], '_', $so_no) . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $filePath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $pdo->prepare("
                    INSERT INTO so_release_attachments
                    (so_no, attachment_type, file_name, original_name, file_type, file_size, file_path, description, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $so_no, $attachment_type, $newName, $file['name'],
                    $file['type'], $file['size'], 'uploads/so_release/' . $newName,
                    $description, $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown'
                ]);

                $success = "Attachment uploaded successfully!";

                // Refresh attachments
                $stmt = $pdo->prepare("SELECT * FROM so_release_attachments WHERE so_no = ? ORDER BY uploaded_at DESC");
                $stmt->execute([$so_no]);
                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $errors[] = "Failed to upload file";
            }
        } else {
            $errors[] = "File upload error";
        }
    }

    // Handle attachment deletion
    if ($action === 'delete_attachment') {
        $att_id = (int)$_POST['attachment_id'];
        $stmt = $pdo->prepare("SELECT file_path FROM so_release_attachments WHERE id = ? AND so_no = ?");
        $stmt->execute([$att_id, $so_no]);
        $att = $stmt->fetch();

        if ($att) {
            $fullPath = dirname(__DIR__) . '/' . $att['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            $pdo->prepare("DELETE FROM so_release_attachments WHERE id = ?")->execute([$att_id]);
            $success = "Attachment deleted";

            // Refresh attachments
            $stmt = $pdo->prepare("SELECT * FROM so_release_attachments WHERE so_no = ? ORDER BY uploaded_at DESC");
            $stmt->execute([$so_no]);
            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Release Checklist - <?= htmlspecialchars($so_no) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .checklist-container {
            max-width: 1000px;
        }
        .checklist-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .checklist-header h1 { margin: 0 0 10px 0; }
        .checklist-header p { margin: 5px 0; opacity: 0.9; }

        .checklist-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .checklist-section h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .section-icon.machine { background: #e3f2fd; }
        .section-icon.functional { background: #e8f5e9; }
        .section-icon.quality { background: #fff3e0; }
        .section-icon.govt { background: #fce4ec; }

        .checklist-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .checklist-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .checklist-item.checked {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .checklist-item input[type="checkbox"] {
            width: 22px;
            height: 22px;
            margin-right: 15px;
            cursor: pointer;
        }
        .checklist-item-content {
            flex: 1;
        }
        .checklist-item-content label {
            font-weight: 600;
            color: #333;
            cursor: pointer;
            display: block;
            margin-bottom: 5px;
        }
        .checklist-item-content small {
            color: #666;
            font-size: 0.85em;
        }
        .mandatory-badge {
            background: #dc3545;
            color: white;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 8px;
        }

        .remarks-field {
            margin-top: 15px;
        }
        .remarks-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        .remarks-field textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            min-height: 60px;
        }

        .attachment-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .attachment-list {
            margin-top: 15px;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e0e0e0;
        }
        .attachment-item .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .attachment-item .file-icon {
            font-size: 24px;
        }
        .attachment-item .file-details {
            font-size: 0.9em;
        }
        .attachment-item .file-type {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }

        .upload-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            margin-top: 15px;
        }
        .upload-form select, .upload-form input[type="file"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            transition: width 0.3s;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            bottom: 20px;
        }
        .completion-status {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-indicator {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .status-indicator.complete { background: #e8f5e9; }
        .status-indicator.incomplete { background: #fff3e0; }

        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Document table styles */
        .checklist-section table th,
        .checklist-section table td {
            border: none;
        }
        .checklist-section table tr:hover {
            background: #f1f3f4;
        }

        /* Upload area hover effect */
        .upload-area:hover {
            border-color: #764ba2;
            background: linear-gradient(135deg, #667eea25 0%, #764ba225 100%);
        }

        /* Document type badge colors */
        .doc-type-test { background: #e8f5e9; color: #2e7d32; }
        .doc-type-quality { background: #e3f2fd; color: #1565c0; }
        .doc-type-govt { background: #fce4ec; color: #c2185b; }
        .doc-type-other { background: #f5f5f5; color: #616161; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content" style="overflow-y: auto; height: 100vh; padding-bottom: 100px;">
    <div class="checklist-container">

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <div class="checklist-header">
            <div>
                <h1>Release Checklist</h1>
                <p><strong>Sales Order:</strong> <?= htmlspecialchars($so_no) ?></p>
                <p><strong>Customer:</strong> <?= htmlspecialchars($order['company_name'] ?? 'N/A') ?></p>
                <p><strong>PO:</strong> <?= htmlspecialchars($order['customer_po_no'] ?? 'N/A') ?></p>
            </div>
            <div style="text-align: right;">
                <a href="view.php?so_no=<?= urlencode($so_no) ?>" class="btn btn-secondary">Back to Order</a>
            </div>
        </div>

        <?php
        // Calculate progress for each section
        $sections = [
            'machine' => [
                'name' => 'Machine Performance',
                'icon' => '&#9881;',
                'complete' => ($checklist['machine_performance_ok'] ?? false) ? 1 : 0,
                'total' => 1
            ],
            'functional' => [
                'name' => 'Functional Performance',
                'icon' => '&#10004;',
                'complete' => ($checklist['functional_performance_ok'] ?? false) ? 1 : 0,
                'total' => 1
            ],
            'quality' => [
                'name' => 'Quality Check Points',
                'icon' => '&#9733;',
                'complete' => (($checklist['quality_visual_inspection'] ?? false) ? 1 : 0) +
                             (($checklist['quality_dimensional_check'] ?? false) ? 1 : 0) +
                             (($checklist['quality_safety_check'] ?? false) ? 1 : 0) +
                             (($checklist['quality_packaging_ok'] ?? false) ? 1 : 0),
                'total' => 4
            ],
            'govt' => [
                'name' => 'Government Compliance',
                'icon' => '&#128196;',
                'complete' => ($checklist['govt_compliance_checked'] ?? false) ? 1 : 0,
                'total' => 1,
                'optional' => true
            ]
        ];

        $totalMandatory = 6;
        $completed = $sections['machine']['complete'] + $sections['functional']['complete'] + $sections['quality']['complete'];
        $progressPercent = ($completed / $totalMandatory) * 100;
        $attachmentCount = count($attachments);
        ?>

        <!-- Progress Overview -->
        <div class="checklist-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <h3 style="border-bottom: none; margin-bottom: 25px;">
                <span style="font-size: 24px;">&#128202;</span>
                Completion Progress
            </h3>

            <!-- Overall Progress Bar -->
            <div style="margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 600;">
                    <span>Overall Mandatory Completion</span>
                    <span style="color: <?= $progressPercent == 100 ? '#28a745' : '#ff9800' ?>;"><?= round($progressPercent) ?>%</span>
                </div>
                <div class="progress-bar" style="height: 12px; background: #dee2e6;">
                    <div class="fill" style="width: <?= $progressPercent ?>%; background: <?= $progressPercent == 100 ? 'linear-gradient(90deg, #28a745, #20c997)' : 'linear-gradient(90deg, #ff9800, #ffc107)' ?>;"></div>
                </div>
                <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                    <?= $completed ?> of <?= $totalMandatory ?> mandatory items completed
                </div>
            </div>

            <!-- Section-wise Progress -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($sections as $key => $section):
                    $sectionPercent = ($section['complete'] / $section['total']) * 100;
                    $isComplete = $section['complete'] == $section['total'];
                    $isOptional = isset($section['optional']) && $section['optional'];
                ?>
                <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid <?= $isComplete ? '#28a745' : ($isOptional ? '#17a2b8' : '#ff9800') ?>;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <span style="font-size: 18px;"><?= $section['icon'] ?></span>
                        <span style="font-weight: 600; font-size: 0.9em;"><?= $section['name'] ?></span>
                        <?php if ($isOptional): ?>
                            <span style="background: #e3f2fd; color: #1565c0; font-size: 0.7em; padding: 2px 6px; border-radius: 3px;">Optional</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $sectionPercent ?>%; background: <?= $isComplete ? '#28a745' : '#ff9800' ?>;"></div>
                        </div>
                        <span style="font-size: 0.85em; font-weight: 600; color: <?= $isComplete ? '#28a745' : '#666' ?>;">
                            <?= $section['complete'] ?>/<?= $section['total'] ?>
                        </span>
                        <?php if ($isComplete): ?>
                            <span style="color: #28a745;">&#10004;</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Attachments Progress -->
                <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid <?= $attachmentCount > 0 ? '#28a745' : '#6c757d' ?>;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <span style="font-size: 18px;">&#128206;</span>
                        <span style="font-weight: 600; font-size: 0.9em;">Documents Attached</span>
                    </div>
                    <div style="font-size: 1.5em; font-weight: 700; color: <?= $attachmentCount > 0 ? '#28a745' : '#6c757d' ?>;">
                        <?= $attachmentCount ?>
                        <span style="font-size: 0.5em; font-weight: 400; color: #666;">file<?= $attachmentCount != 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="checklistForm">
            <input type="hidden" name="action" value="save_checklist">

            <!-- Machine Performance -->
            <div class="checklist-section">
                <h3>
                    <span class="section-icon machine">&#9881;</span>
                    Machine Performance
                </h3>

                <div class="checklist-item <?= ($checklist['machine_performance_ok'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="machine_performance_ok" id="machine_performance_ok"
                           <?= ($checklist['machine_performance_ok'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="machine_performance_ok">
                            Overall Machine Performance Test
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>Verify overall machine performance including noise levels, vibration, and operational parameters are within acceptable limits</small>
                    </div>
                </div>

                <div class="remarks-field">
                    <label>Machine Performance Remarks</label>
                    <textarea name="machine_performance_remarks" placeholder="Enter any observations or notes..."><?= htmlspecialchars($checklist['machine_performance_remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Functional Performance -->
            <div class="checklist-section">
                <h3>
                    <span class="section-icon functional">&#10004;</span>
                    Functional Performance
                </h3>

                <div class="checklist-item <?= ($checklist['functional_performance_ok'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="functional_performance_ok" id="functional_performance_ok"
                           <?= ($checklist['functional_performance_ok'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="functional_performance_ok">
                            Complete Functional Test
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>All functional operations tested and verified. Safety features, interlocks, and emergency stops working correctly</small>
                    </div>
                </div>

                <div class="remarks-field">
                    <label>Functional Performance Remarks</label>
                    <textarea name="functional_performance_remarks" placeholder="Enter test results or observations..."><?= htmlspecialchars($checklist['functional_performance_remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Quality Check Points -->
            <div class="checklist-section">
                <h3>
                    <span class="section-icon quality">&#9733;</span>
                    Quality Check Points
                </h3>

                <div class="checklist-item <?= ($checklist['quality_visual_inspection'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="quality_visual_inspection" id="quality_visual_inspection"
                           <?= ($checklist['quality_visual_inspection'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="quality_visual_inspection">
                            Visual Inspection
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>No visible defects, scratches, dents, or cosmetic damage</small>
                    </div>
                </div>

                <div class="checklist-item <?= ($checklist['quality_dimensional_check'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="quality_dimensional_check" id="quality_dimensional_check"
                           <?= ($checklist['quality_dimensional_check'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="quality_dimensional_check">
                            Dimensional Verification
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>Critical dimensions verified against drawings/specifications</small>
                    </div>
                </div>

                <div class="checklist-item <?= ($checklist['quality_safety_check'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="quality_safety_check" id="quality_safety_check"
                           <?= ($checklist['quality_safety_check'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="quality_safety_check">
                            Safety Check
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>All safety labels, guards, and warning signs in place</small>
                    </div>
                </div>

                <div class="checklist-item <?= ($checklist['quality_packaging_ok'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="quality_packaging_ok" id="quality_packaging_ok"
                           <?= ($checklist['quality_packaging_ok'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="quality_packaging_ok">
                            Packaging Verification
                            <span class="mandatory-badge">Required</span>
                        </label>
                        <small>Packaging adequate for safe transportation, all accessories included</small>
                    </div>
                </div>

                <div class="remarks-field">
                    <label>Quality Remarks</label>
                    <textarea name="quality_remarks" placeholder="Enter quality inspection notes..."><?= htmlspecialchars($checklist['quality_remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Government Compliance -->
            <div class="checklist-section">
                <h3>
                    <span class="section-icon govt">&#128196;</span>
                    Government Compliance & Documentation
                </h3>

                <div class="checklist-item <?= ($checklist['govt_compliance_checked'] ?? false) ? 'checked' : '' ?>">
                    <input type="checkbox" name="govt_compliance_checked" id="govt_compliance_checked"
                           <?= ($checklist['govt_compliance_checked'] ?? false) ? 'checked' : '' ?>
                           onchange="updateItemStyle(this)">
                    <div class="checklist-item-content">
                        <label for="govt_compliance_checked">
                            Government Compliance Verified
                        </label>
                        <small>All applicable government certifications, test certificates, and compliance documents are available</small>
                    </div>
                </div>

                <div class="remarks-field">
                    <label>Compliance Remarks</label>
                    <textarea name="govt_compliance_remarks" placeholder="List certificates: BIS, CE, Test Reports, etc."><?= htmlspecialchars($checklist['govt_compliance_remarks'] ?? '') ?></textarea>
                </div>

            </div>

            <!-- Save Button -->
            <div style="text-align: center; margin: 25px 0;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">
                    Save Checklist
                </button>
            </div>
        </form>

        <!-- Document Management Section -->
        <div class="checklist-section">
            <h3>
                <span class="section-icon" style="background: #e8f5e9;">&#128206;</span>
                Required Documents & Attachments
            </h3>

            <?php
            // Define required document types
            $requiredDocs = [
                'Test Report' => [
                    'description' => 'Factory acceptance test report / Performance test certificate',
                    'icon' => '&#128203;',
                    'required' => true
                ],
                'Quality Certificate' => [
                    'description' => 'Quality inspection certificate / QC release document',
                    'icon' => '&#9989;',
                    'required' => true
                ],
                'Government Document' => [
                    'description' => 'BIS Certificate, CE Marking, FSSAI, or other regulatory certificates',
                    'icon' => '&#127963;',
                    'required' => false
                ],
                'Inspection Report' => [
                    'description' => 'Pre-dispatch inspection report with dimensional check',
                    'icon' => '&#128270;',
                    'required' => false
                ],
                'Warranty Card' => [
                    'description' => 'Warranty certificate / Terms and conditions document',
                    'icon' => '&#128195;',
                    'required' => false
                ],
                'User Manual' => [
                    'description' => 'Operation manual / Installation guide',
                    'icon' => '&#128214;',
                    'required' => false
                ],
                'Calibration Certificate' => [
                    'description' => 'Calibration certificate for measuring equipment used',
                    'icon' => '&#128207;',
                    'required' => false
                ],
                'Packing List' => [
                    'description' => 'Detailed packing list with all items and accessories',
                    'icon' => '&#128230;',
                    'required' => false
                ],
                'Other' => [
                    'description' => 'Any other supporting document',
                    'icon' => '&#128196;',
                    'required' => false
                ]
            ];

            // Group attachments by type
            $attachmentsByType = [];
            foreach ($attachments as $att) {
                $attachmentsByType[$att['attachment_type']][] = $att;
            }
            ?>

            <!-- Document Checklist -->
            <div style="margin-bottom: 25px;">
                <h4 style="margin: 0 0 15px 0; color: #555;">Document Checklist</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                    <?php foreach ($requiredDocs as $docType => $docInfo):
                        $hasDoc = isset($attachmentsByType[$docType]) && count($attachmentsByType[$docType]) > 0;
                        $docCount = isset($attachmentsByType[$docType]) ? count($attachmentsByType[$docType]) : 0;
                    ?>
                    <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: <?= $hasDoc ? '#e8f5e9' : ($docInfo['required'] ? '#fff3e0' : '#f8f9fa') ?>; border-radius: 8px; border: 1px solid <?= $hasDoc ? '#c8e6c9' : ($docInfo['required'] ? '#ffe0b2' : '#e0e0e0') ?>;">
                        <div style="font-size: 24px; line-height: 1;">
                            <?php if ($hasDoc): ?>
                                <span style="color: #28a745;">&#10004;</span>
                            <?php else: ?>
                                <span style="opacity: 0.5;"><?= $docInfo['icon'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 0.9em; display: flex; align-items: center; gap: 6px;">
                                <?= htmlspecialchars($docType) ?>
                                <?php if ($docInfo['required']): ?>
                                    <span style="background: #dc3545; color: white; font-size: 0.65em; padding: 2px 5px; border-radius: 3px;">Required</span>
                                <?php endif; ?>
                                <?php if ($docCount > 0): ?>
                                    <span style="background: #28a745; color: white; font-size: 0.65em; padding: 2px 5px; border-radius: 3px;"><?= $docCount ?> file<?= $docCount > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.8em; color: #666; margin-top: 3px;"><?= htmlspecialchars($docInfo['description']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Uploaded Documents List -->
            <?php if (!empty($attachments)): ?>
            <div style="margin-bottom: 25px;">
                <h4 style="margin: 0 0 15px 0; color: #555;">Uploaded Documents (<?= count($attachments) ?>)</h4>
                <div style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #e9ecef;">
                                <th style="padding: 12px 15px; text-align: left; font-size: 0.85em; color: #495057;">Document Type</th>
                                <th style="padding: 12px 15px; text-align: left; font-size: 0.85em; color: #495057;">File Name</th>
                                <th style="padding: 12px 15px; text-align: left; font-size: 0.85em; color: #495057;">Description</th>
                                <th style="padding: 12px 15px; text-align: left; font-size: 0.85em; color: #495057;">Uploaded</th>
                                <th style="padding: 12px 15px; text-align: center; font-size: 0.85em; color: #495057;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $att):
                                $fileExt = strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                                $fileIcon = match($fileExt) {
                                    'pdf' => '&#128196;',
                                    'doc', 'docx' => '&#128195;',
                                    'xls', 'xlsx' => '&#128202;',
                                    'jpg', 'jpeg', 'png', 'gif' => '&#128247;',
                                    default => '&#128206;'
                                };
                                $fileSize = $att['file_size'] > 1048576
                                    ? round($att['file_size'] / 1048576, 1) . ' MB'
                                    : round($att['file_size'] / 1024, 1) . ' KB';
                            ?>
                            <tr style="border-top: 1px solid #dee2e6;">
                                <td style="padding: 12px 15px;">
                                    <span style="background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 4px; font-size: 0.85em;">
                                        <?= htmlspecialchars($att['attachment_type']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 15px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 20px;"><?= $fileIcon ?></span>
                                        <div>
                                            <div style="font-weight: 500; font-size: 0.9em;"><?= htmlspecialchars($att['original_name']) ?></div>
                                            <div style="font-size: 0.75em; color: #888;"><?= $fileSize ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px 15px; font-size: 0.85em; color: #666;">
                                    <?= $att['description'] ? htmlspecialchars($att['description']) : '<em style="opacity: 0.5;">No description</em>' ?>
                                </td>
                                <td style="padding: 12px 15px; font-size: 0.85em; color: #666;">
                                    <div><?= htmlspecialchars($att['uploaded_by'] ?? 'Unknown') ?></div>
                                    <div style="font-size: 0.85em; color: #999;"><?= date('d-M-Y H:i', strtotime($att['uploaded_at'])) ?></div>
                                </td>
                                <td style="padding: 12px 15px; text-align: center;">
                                    <div style="display: flex; justify-content: center; gap: 8px;">
                                        <a href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary" title="View Document">
                                            &#128065; View
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this attachment?')">
                                            <input type="hidden" name="action" value="delete_attachment">
                                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #dc3545; color: white;" title="Delete">
                                                &#128465;
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); padding: 20px; border-radius: 8px; border: 2px dashed #667eea;">
                <h4 style="margin: 0 0 15px 0; color: #667eea;">&#128228; Upload New Document</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_attachment">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9em;">Document Type <span style="color: #dc3545;">*</span></label>
                            <select name="attachment_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95em;">
                                <option value="">-- Select Document Type --</option>
                                <?php foreach ($requiredDocs as $docType => $docInfo): ?>
                                    <option value="<?= htmlspecialchars($docType) ?>">
                                        <?= htmlspecialchars($docType) ?><?= $docInfo['required'] ? ' (Required)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9em;">Select File <span style="color: #dc3545;">*</span></label>
                            <input type="file" name="attachment" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: white;">
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9em;">Description / Notes</label>
                        <input type="text" name="attachment_description" placeholder="Brief description of the document (e.g., Certificate No., Validity, etc.)" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 0.8em; color: #666;">
                            Supported formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Max: 10MB)
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">
                            &#128228; Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="completion-status">
                <?php if ($checklist && $checklist['checklist_completed']): ?>
                    <div class="status-indicator complete">&#10004;</div>
                    <div>
                        <strong style="color: #28a745;">Checklist Complete</strong>
                        <div style="font-size: 0.9em; color: #666;">
                            Completed by <?= htmlspecialchars($checklist['completed_by_name'] ?? 'Unknown') ?>
                            on <?= date('d-M-Y H:i', strtotime($checklist['completed_at'])) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="status-indicator incomplete">&#9888;</div>
                    <div>
                        <strong style="color: #ff9800;">Checklist Incomplete</strong>
                        <div style="font-size: 0.9em; color: #666;">Complete all mandatory items to release</div>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <a href="view.php?so_no=<?= urlencode($so_no) ?>" class="btn btn-secondary">Back to Order</a>
                <?php if ($checklist && $checklist['checklist_completed']): ?>
                    <a href="release.php?so_no=<?= urlencode($so_no) ?>" class="btn btn-success"
                       onclick="return confirm('Release this Sales Order?\n\nInventory will be deducted.')">
                        Proceed to Release
                    </a>
                <?php else: ?>
                    <button class="btn" style="background: #ccc; cursor: not-allowed;" disabled>
                        Complete Checklist First
                    </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function updateItemStyle(checkbox) {
    const item = checkbox.closest('.checklist-item');
    if (checkbox.checked) {
        item.classList.add('checked');
    } else {
        item.classList.remove('checked');
    }
}
</script>

</body>
</html>
