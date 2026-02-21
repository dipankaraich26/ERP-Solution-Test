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

// Auto-create SO inspection items table for matrix-based checkpoints
try {
    $pdo->query("SELECT 1 FROM so_inspection_checklist_items LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_inspection_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            so_no VARCHAR(50) NOT NULL,
            item_no INT NOT NULL,
            checkpoint VARCHAR(255) NOT NULL,
            specification TEXT,
            result ENUM('Pending','OK','Not OK','NA','Conditional') DEFAULT 'Pending',
            actual_value VARCHAR(255),
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_so_no (so_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
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

// Resolve part_id for this SO's part and load matrix-based inspection items
$soPartId = null;
$soInspectionItems = [];
$matrixCheckpoints = [];
$matrixAvailable = false;

try {
    $pidStmt = $pdo->prepare("SELECT part_id FROM part_master WHERE part_no = ? AND part_id IS NOT NULL AND part_id != '' LIMIT 1");
    $pidStmt->execute([$order['part_no']]);
    $soPartId = $pidStmt->fetchColumn();
} catch (Exception $e) {}

// Load existing SO inspection items
try {
    $insItemStmt = $pdo->prepare("SELECT * FROM so_inspection_checklist_items WHERE so_no = ? ORDER BY item_no");
    $insItemStmt->execute([$so_no]);
    $soInspectionItems = $insItemStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Check if matrix checkpoints exist for this part
if ($soPartId) {
    try {
        $mcStmt = $pdo->prepare("
            SELECT c.id, c.checkpoint_name, c.specification, c.category, c.sort_order
            FROM qc_part_inspection_matrix m
            JOIN qc_inspection_checkpoints c ON m.checkpoint_id = c.id
            WHERE m.part_id = ? AND m.stage = 'so_release' AND c.is_active = 1
            ORDER BY c.sort_order, c.id
        ");
        $mcStmt->execute([$soPartId]);
        $matrixCheckpoints = $mcStmt->fetchAll(PDO::FETCH_ASSOC);
        $matrixAvailable = !empty($matrixCheckpoints);
    } catch (Exception $e) {}
}

// SO Approval tables auto-create
try {
    $pdo->query("SELECT 1 FROM so_release_approvals LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_release_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            so_no VARCHAR(50) NOT NULL,
            requested_by INT DEFAULT NULL,
            approver_id INT NOT NULL,
            status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            remarks TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME DEFAULT NULL
        )
    ");
}
try {
    $pdo->query("SELECT 1 FROM so_approvers LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_approvers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL UNIQUE,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// Fetch SO approvers
$soApprovers = $pdo->query("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.designation, e.department
    FROM so_approvers a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.is_active = 1
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing approval
$existingApprovalStmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.emp_id as approver_emp_id
    FROM so_release_approvals a
    JOIN employees e ON a.approver_id = e.id
    WHERE a.so_no = ?
    ORDER BY a.id DESC LIMIT 1
");
$existingApprovalStmt->execute([$so_no]);
$soApproval = $existingApprovalStmt->fetch(PDO::FETCH_ASSOC);

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

    // Handle approval request
    if ($action === 'request_approval' && !empty($_POST['approver_id'])) {
        $approverId = (int)$_POST['approver_id'];
        // Verify checklist is completed
        $stmt = $pdo->prepare("SELECT checklist_completed FROM so_release_checklist WHERE so_no = ?");
        $stmt->execute([$so_no]);
        $cl = $stmt->fetch();
        if (!$cl || !$cl['checklist_completed']) {
            $errors[] = "Complete the checklist before requesting approval.";
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO so_release_approvals (so_no, requested_by, approver_id, status)
                    VALUES (?, ?, ?, 'Pending')
                ")->execute([$so_no, $_SESSION['user_id'] ?? null, $approverId]);
                $success = "Approval request sent successfully!";

                // Refresh approval data
                $existingApprovalStmt->execute([$so_no]);
                $soApproval = $existingApprovalStmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errors[] = "Failed to request approval: " . $e->getMessage();
            }
        }
    }

    // Generate inspection checkpoints from Part Inspection Matrix
    if ($action === 'generate_inspection') {
        if ($matrixAvailable && empty($soInspectionItems)) {
            try {
                $insStmt = $pdo->prepare("
                    INSERT INTO so_inspection_checklist_items (so_no, item_no, checkpoint, specification, result)
                    VALUES (?, ?, ?, ?, 'Pending')
                ");
                $itemNo = 1;
                foreach ($matrixCheckpoints as $cp) {
                    $insStmt->execute([$so_no, $itemNo, $cp['checkpoint_name'], $cp['specification']]);
                    $itemNo++;
                }
                $success = "Inspection checkpoints generated from Part Inspection Matrix.";
                // Refresh
                $insItemStmt = $pdo->prepare("SELECT * FROM so_inspection_checklist_items WHERE so_no = ? ORDER BY item_no");
                $insItemStmt->execute([$so_no]);
                $soInspectionItems = $insItemStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $errors[] = "Error generating inspection items: " . $e->getMessage();
            }
        }
    }

    // Save inspection checklist items
    if ($action === 'save_inspection') {
        try {
            if (isset($_POST['insp_items']) && is_array($_POST['insp_items'])) {
                $updateStmt = $pdo->prepare("
                    UPDATE so_inspection_checklist_items
                    SET result = ?, actual_value = ?, remarks = ?
                    WHERE id = ? AND so_no = ?
                ");
                foreach ($_POST['insp_items'] as $itemId => $item) {
                    $updateStmt->execute([
                        $item['result'] ?? 'Pending',
                        $item['actual_value'] ?? null,
                        $item['remarks'] ?? null,
                        (int)$itemId,
                        $so_no
                    ]);
                }
            }
            $success = "Inspection items saved successfully.";
            // Refresh
            $insItemStmt = $pdo->prepare("SELECT * FROM so_inspection_checklist_items WHERE so_no = ? ORDER BY item_no");
            $insItemStmt->execute([$so_no]);
            $soInspectionItems = $insItemStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $errors[] = "Error saving inspection items: " . $e->getMessage();
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

        <!-- Part-Specific Inspection Checkpoints (from Matrix) -->
        <?php if ($matrixAvailable || !empty($soInspectionItems)): ?>
        <div class="checklist-section">
            <h3>
                <span class="section-icon" style="background: #ede9fe;">&#128270;</span>
                Part-Specific Inspection Checkpoints
                <?php if ($soPartId): ?>
                    <span style="font-size: 0.7em; background: #e0e7ff; color: #3730a3; padding: 3px 10px; border-radius: 12px; font-weight: 500;">Part ID: <?= htmlspecialchars($soPartId) ?></span>
                <?php endif; ?>
            </h3>

            <?php if (empty($soInspectionItems) && $matrixAvailable): ?>
                <!-- Generate button -->
                <div style="text-align: center; padding: 25px; background: #fef3c7; border-radius: 8px; border: 1px solid #fbbf24;">
                    <p style="margin: 0 0 15px 0; color: #92400e; font-weight: 500;">
                        <?= count($matrixCheckpoints) ?> inspection checkpoint<?= count($matrixCheckpoints) != 1 ? 's' : '' ?> configured for this Part ID (SO Release stage).
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_inspection">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">
                            Generate Inspection Checkpoints
                        </button>
                    </form>
                </div>
            <?php elseif (!empty($soInspectionItems)): ?>
                <!-- Render inspection table -->
                <?php
                    $totalItems = count($soInspectionItems);
                    $okItems = count(array_filter($soInspectionItems, fn($i) => $i['result'] === 'OK'));
                    $notOkItems = count(array_filter($soInspectionItems, fn($i) => $i['result'] === 'Not OK'));
                    $pendingItems = count(array_filter($soInspectionItems, fn($i) => $i['result'] === 'Pending'));
                ?>
                <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                    <div style="background: #f0fdf4; padding: 8px 15px; border-radius: 6px; border: 1px solid #bbf7d0;">
                        <span style="color: #16a34a; font-weight: 600;"><?= $okItems ?></span> <span style="color: #666; font-size: 0.85em;">OK</span>
                    </div>
                    <div style="background: #fef2f2; padding: 8px 15px; border-radius: 6px; border: 1px solid #fecaca;">
                        <span style="color: #dc2626; font-weight: 600;"><?= $notOkItems ?></span> <span style="color: #666; font-size: 0.85em;">Not OK</span>
                    </div>
                    <div style="background: #fffbeb; padding: 8px 15px; border-radius: 6px; border: 1px solid #fed7aa;">
                        <span style="color: #d97706; font-weight: 600;"><?= $pendingItems ?></span> <span style="color: #666; font-size: 0.85em;">Pending</span>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_inspection">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                            <thead>
                                <tr style="background: #f3f4f6;">
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151; white-space: nowrap;">#</th>
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151;">Checkpoint</th>
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151;">Specification</th>
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151; white-space: nowrap;">Result</th>
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151;">Actual Value</th>
                                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #374151;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soInspectionItems as $item):
                                    $rowBg = match($item['result']) {
                                        'OK' => '#f0fdf4',
                                        'Not OK' => '#fef2f2',
                                        'NA' => '#f8fafc',
                                        default => 'white'
                                    };
                                ?>
                                <tr style="border-bottom: 1px solid #e5e7eb; background: <?= $rowBg ?>;">
                                    <td style="padding: 10px 12px; color: #9ca3af;"><?= $item['item_no'] ?></td>
                                    <td style="padding: 10px 12px; font-weight: 500;"><?= htmlspecialchars($item['checkpoint']) ?></td>
                                    <td style="padding: 10px 12px; color: #6b7280; font-size: 0.9em;"><?= htmlspecialchars($item['specification'] ?? '-') ?></td>
                                    <td style="padding: 10px 12px;">
                                        <select name="insp_items[<?= $item['id'] ?>][result]" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9em;">
                                            <option value="Pending" <?= $item['result'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="OK" <?= $item['result'] === 'OK' ? 'selected' : '' ?>>OK</option>
                                            <option value="Not OK" <?= $item['result'] === 'Not OK' ? 'selected' : '' ?>>Not OK</option>
                                            <option value="NA" <?= $item['result'] === 'NA' ? 'selected' : '' ?>>N/A</option>
                                            <option value="Conditional" <?= $item['result'] === 'Conditional' ? 'selected' : '' ?>>Conditional</option>
                                        </select>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <input type="text" name="insp_items[<?= $item['id'] ?>][actual_value]"
                                               value="<?= htmlspecialchars($item['actual_value'] ?? '') ?>"
                                               style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 5px; width: 100%; min-width: 80px; font-size: 0.9em;"
                                               placeholder="Measured">
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <input type="text" name="insp_items[<?= $item['id'] ?>][remarks]"
                                               value="<?= htmlspecialchars($item['remarks'] ?? '') ?>"
                                               style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 5px; width: 100%; min-width: 100px; font-size: 0.9em;"
                                               placeholder="Notes">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">
                            Save Inspection Items
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php elseif ($soPartId && !$matrixAvailable): ?>
        <div class="checklist-section" style="opacity: 0.7;">
            <h3>
                <span class="section-icon" style="background: #f3f4f6;">&#128270;</span>
                Part-Specific Inspection
            </h3>
            <p style="color: #666; text-align: center; padding: 15px;">
                No part-specific inspection checkpoints configured for Part ID: <strong><?= htmlspecialchars($soPartId) ?></strong> (SO Release stage).
                <br><a href="/quality_control/inspection_matrix_edit.php?part_id=<?= urlencode($soPartId) ?>">Configure in Inspection Matrix</a>
            </p>
        </div>
        <?php endif; ?>

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
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="file" name="attachment" id="so_attachment_file" required
                                       style="display: none;"
                                       onchange="var n=document.getElementById('so_att_fname'); n.textContent=this.files[0]?.name||''; n.style.display=this.files[0]?'block':'none';">
                                <button type="button" onclick="document.getElementById('so_attachment_file').removeAttribute('capture'); document.getElementById('so_attachment_file').setAttribute('accept','*/*'); document.getElementById('so_attachment_file').click();"
                                        style="flex: 1; padding: 10px; border: 2px dashed #ddd; border-radius: 6px; background: #f8f9fa; cursor: pointer; font-size: 0.85em; color: #555;">
                                    &#128193; Choose File
                                </button>
                                <button type="button" onclick="document.getElementById('so_attachment_file').setAttribute('accept','image/*'); document.getElementById('so_attachment_file').setAttribute('capture','environment'); document.getElementById('so_attachment_file').click();"
                                        style="flex: 1; padding: 10px; border: 2px dashed #0d6efd; border-radius: 6px; background: #e8f0fe; cursor: pointer; font-size: 0.85em; color: #0d6efd; font-weight: 600;">
                                    &#128247; Camera
                                </button>
                            </div>
                            <small id="so_att_fname" style="display:none; margin-top:4px; color:#27ae60; font-size:0.8em;"></small>
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

        <!-- Release Approval Section -->
        <?php if ($checklist && $checklist['checklist_completed']): ?>
        <div class="checklist-section">
            <h3>
                <span class="section-icon" style="background: #fce4ec;">&#128274;</span>
                Release Approval
            </h3>

            <?php if ($soApproval): ?>
                <!-- Show approval status -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 0.85em; color: #666;">Approval Status</div>
                        <div style="font-weight: 600; margin-top: 4px;">
                            <span class="status-badge status-<?= strtolower($soApproval['status']) ?>" style="padding: 4px 12px; border-radius: 12px; font-size: 0.9em;">
                                <?= $soApproval['status'] ?>
                            </span>
                        </div>
                    </div>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 0.85em; color: #666;">Approver</div>
                        <div style="font-weight: 600; margin-top: 4px;">
                            <?= htmlspecialchars($soApproval['first_name'] . ' ' . $soApproval['last_name']) ?>
                            (<?= htmlspecialchars($soApproval['approver_emp_id']) ?>)
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 0.85em; color: #666;">Requested At</div>
                        <div style="font-weight: 600; margin-top: 4px;"><?= date('d-M-Y H:i', strtotime($soApproval['requested_at'])) ?></div>
                    </div>
                    <?php if ($soApproval['approved_at']): ?>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 0.85em; color: #666;"><?= $soApproval['status'] === 'Approved' ? 'Approved At' : 'Rejected At' ?></div>
                        <div style="font-weight: 600; margin-top: 4px;"><?= date('d-M-Y H:i', strtotime($soApproval['approved_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($soApproval['remarks']): ?>
                <div style="padding: 12px; background: #f8f9fa; border-radius: 6px; margin-top: 12px;">
                    <div style="font-size: 0.85em; color: #666;">Remarks</div>
                    <div style="font-weight: 500; margin-top: 4px;"><?= htmlspecialchars($soApproval['remarks']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($soApproval['status'] === 'Approved'): ?>
                <div style="margin-top: 15px; background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                    <strong>Approved!</strong> This Sales Order can now be released.
                </div>
                <?php elseif ($soApproval['status'] === 'Rejected'): ?>
                <div style="margin-top: 15px; background: #fee2e2; padding: 15px; border-radius: 8px; color: #991b1b;">
                    <strong>Rejected.</strong> Address the issues and request approval again.
                </div>
                <!-- Allow re-request -->
                <?php if (!empty($soApprovers)): ?>
                <form method="POST" style="margin-top: 15px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="action" value="request_approval">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9em;">Select Approver</label>
                        <select name="approver_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="">-- Select Approver --</option>
                            <?php foreach ($soApprovers as $app): ?>
                                <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> (<?= htmlspecialchars($app['emp_id']) ?>)<?php if ($app['designation']): ?> - <?= htmlspecialchars($app['designation']) ?><?php endif; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Re-request Approval</button>
                </form>
                <?php endif; ?>
                <?php elseif ($soApproval['status'] === 'Pending'): ?>
                <div style="margin-top: 15px; background: #fff3cd; padding: 15px; border-radius: 8px; color: #856404;">
                    <strong>Waiting for approval</strong> from <?= htmlspecialchars($soApproval['first_name'] . ' ' . $soApproval['last_name']) ?>.
                    The approver can approve/reject from the <a href="/approvals/index.php">My Approvals</a> page.
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- No approval yet - show request form -->
                <?php if (empty($soApprovers)): ?>
                    <div style="background: #fee2e2; padding: 15px; border-radius: 6px; color: #991b1b;">
                        No SO approvers configured. Please ask an administrator to add SO approvers in
                        <a href="/admin/so_approvers.php">SO Approvers Settings</a>.
                    </div>
                <?php else: ?>
                    <p style="color: #666; margin-bottom: 15px;">Checklist is complete. Select an approver to request release approval.</p>
                    <form method="POST" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                        <input type="hidden" name="action" value="request_approval">
                        <div style="flex: 1; min-width: 250px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9em;">Select Approver</label>
                            <select name="approver_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">-- Select Approver --</option>
                                <?php foreach ($soApprovers as $app): ?>
                                    <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> (<?= htmlspecialchars($app['emp_id']) ?>)<?php if ($app['designation']): ?> - <?= htmlspecialchars($app['designation']) ?><?php endif; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Request Approval</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
                <?php if ($checklist && $checklist['checklist_completed'] && $soApproval && $soApproval['status'] === 'Approved'): ?>
                    <a href="release.php?so_no=<?= urlencode($so_no) ?>" class="btn btn-success"
                       onclick="return confirm('Release this Sales Order?\n\nInventory will be deducted.')">
                        Proceed to Release
                    </a>
                <?php elseif ($checklist && $checklist['checklist_completed']): ?>
                    <button class="btn" style="background: #ccc; cursor: not-allowed;" disabled>
                        Approval Required
                    </button>
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
