<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: complaints.php");
    exit;
}

// Auto-create attachments table
try {
    $pdo->query("SELECT 1 FROM service_complaint_attachments LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_complaint_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            file_path VARCHAR(500),
            description VARCHAR(255),
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (complaint_id) REFERENCES service_complaints(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Auto-add service_complaint_id column to qc_quality_issues
try {
    $pdo->query("SELECT service_complaint_id FROM qc_quality_issues LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE qc_quality_issues ADD COLUMN service_complaint_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE qc_quality_issues ADD INDEX idx_service_complaint (service_complaint_id)");
    } catch (Exception $e2) {}
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $newStatus = $_POST['new_status'];
        $remarks = $_POST['remarks'] ?? '';

        $oldStatus = $pdo->prepare("SELECT status FROM service_complaints WHERE id = ?");
        $oldStatus->execute([$id]);
        $old = $oldStatus->fetchColumn();

        $updateFields = ["status = ?"];
        $updateParams = [$newStatus];

        if ($newStatus === 'Resolved' || $newStatus === 'Closed') {
            $updateFields[] = "resolution_date = NOW()";
            if (!empty($_POST['resolution_notes'])) {
                $updateFields[] = "resolution_notes = ?";
                $updateParams[] = $_POST['resolution_notes'];
            }
        }

        $updateParams[] = $id;
        $pdo->prepare("UPDATE service_complaints SET " . implode(", ", $updateFields) . " WHERE id = ?")->execute($updateParams);

        $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)")
            ->execute([$id, $old, $newStatus, $remarks]);

        setModal("Success", "Status updated to '$newStatus'");
        header("Location: complaint_view.php?id=$id");
        exit;
    }

    if ($action === 'assign') {
        $techId = $_POST['technician_id'];
        $visitDate = $_POST['visit_date'] ?: null;
        $visitTime = $_POST['visit_time'] ?: null;

        $pdo->prepare("UPDATE service_complaints SET assigned_technician_id = ?, assigned_date = NOW(), scheduled_visit_date = ?, scheduled_visit_time = ?, status = 'Assigned' WHERE id = ?")
            ->execute([$techId, $visitDate, $visitTime, $id]);

        $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, remarks) VALUES (?, 'Open', 'Assigned', 'Technician assigned')")
            ->execute([$id]);

        setModal("Success", "Technician assigned successfully!");
        header("Location: complaint_view.php?id=$id");
        exit;
    }

    if ($action === 'upload_attachment') {
        if (!empty($_FILES['attachments'])) {
            $uploadDir = dirname(__DIR__) . '/uploads/service_complaints/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedTypes = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','mp4','mov'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            $uploaded = 0;

            $files = $_FILES['attachments'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $fileCount; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($error !== UPLOAD_ERR_OK || empty($name)) continue;

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedTypes)) continue;
                if ($size > $maxSize) continue;

                $newFileName = 'SC-' . $id . '_' . time() . '_' . $i . '.' . $ext;
                $filePath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $description = trim($_POST['attachment_description'] ?? '');
                    $pdo->prepare("
                        INSERT INTO service_complaint_attachments (complaint_id, file_name, original_name, file_type, file_size, file_path, description, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $id, $newFileName, $name, $ext, $size,
                        'uploads/service_complaints/' . $newFileName,
                        $description,
                        $_SESSION['user_id'] ?? null
                    ]);
                    $uploaded++;
                }
            }

            if ($uploaded > 0) {
                setModal("Success", "$uploaded file(s) uploaded successfully!");
            } else {
                setModal("Error", "No files were uploaded. Check file type and size (max 10MB).");
            }
        }
        header("Location: complaint_view.php?id=$id&tab=attachments");
        exit;
    }

    if ($action === 'delete_attachment') {
        $attId = (int)$_POST['attachment_id'];
        $att = $pdo->prepare("SELECT * FROM service_complaint_attachments WHERE id = ? AND complaint_id = ?");
        $att->execute([$attId, $id]);
        $attRow = $att->fetch(PDO::FETCH_ASSOC);

        if ($attRow) {
            $filePath = dirname(__DIR__) . '/' . $attRow['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $pdo->prepare("DELETE FROM service_complaint_attachments WHERE id = ?")->execute([$attId]);
            setModal("Success", "Attachment deleted.");
        }
        header("Location: complaint_view.php?id=$id&tab=attachments");
        exit;
    }

    if ($action === 'raise_quality_issue') {
        try {
            // Generate issue number: QI-YYYYMM-XXXX
            $year = date('Y');
            $month = date('m');
            $prefix = 'QI-' . $year . $month;
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(issue_no, 11) AS UNSIGNED)) FROM qc_quality_issues WHERE issue_no LIKE '$prefix-%'")->fetchColumn();
            $issue_no = $prefix . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $issueType = $_POST['issue_type'] ?? 'Customer Complaint';
            $issueTitle = trim($_POST['issue_title'] ?? '');
            $issueDesc = trim($_POST['issue_description'] ?? '');
            $issuePriority = $_POST['issue_priority'] ?? 'Medium';
            $detectionStage = $_POST['detection_stage'] ?? 'Customer Use';

            if (empty($issueTitle)) {
                $issueTitle = 'Service Complaint: ' . substr($complaint['complaint_description'] ?? '', 0, 100);
            }

            $stmt = $pdo->prepare("
                INSERT INTO qc_quality_issues (
                    issue_no, issue_type, issue_source, title, description, category,
                    part_no, serial_no, customer_name,
                    detection_stage, priority, severity,
                    issue_date, reported_by,
                    status, service_complaint_id, created_by
                ) VALUES (?, ?, 'Service', ?, ?, 'Functional',
                    ?, ?, ?,
                    ?, ?, 'Major',
                    CURDATE(), ?,
                    'Open', ?, ?)
            ");

            // Fetch complaint data for pre-fill (we need it before the redirect)
            $cStmt = $pdo->prepare("SELECT * FROM service_complaints WHERE id = ?");
            $cStmt->execute([$id]);
            $cData = $cStmt->fetch(PDO::FETCH_ASSOC);

            $stmt->execute([
                $issue_no,
                $issueType,
                $issueTitle,
                $issueDesc ?: ($cData['complaint_description'] ?? ''),
                $cData['product_name'] ?? null,
                $cData['serial_number'] ?? null,
                $cData['customer_name'] ?? null,
                $detectionStage,
                $issuePriority,
                $cData['customer_name'] ?? 'Service Module',
                $id,
                $_SESSION['user_id'] ?? null
            ]);

            $newIssueId = $pdo->lastInsertId();

            // Log in complaint history
            $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)")
                ->execute([$id, $cData['status'], $cData['status'], "Quality Issue raised: $issue_no"]);

            setModal("Success", "Quality Issue '$issue_no' created successfully! <a href='../quality_control/issue_view.php?id=$newIssueId' style='color:#155724;text-decoration:underline;'>View Issue</a>");
            header("Location: complaint_view.php?id=$id&tab=quality");
            exit;

        } catch (Exception $e) {
            setModal("Error", "Failed to create quality issue: " . $e->getMessage());
            header("Location: complaint_view.php?id=$id&tab=quality");
            exit;
        }
    }
}

// Fetch complaint
$stmt = $pdo->prepare("
    SELECT c.*,
           cat.name AS category_name,
           t.name AS technician_name,
           t.phone AS technician_phone,
           s.state_name
    FROM service_complaints c
    LEFT JOIN service_issue_categories cat ON c.issue_category_id = cat.id
    LEFT JOIN service_technicians t ON c.assigned_technician_id = t.id
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: complaints.php");
    exit;
}

// Get visit history
$visits = $pdo->prepare("
    SELECT v.*, t.name AS technician_name
    FROM service_visits v
    LEFT JOIN service_technicians t ON v.technician_id = t.id
    WHERE v.complaint_id = ?
    ORDER BY v.visit_date DESC
");
$visits->execute([$id]);
$visitHistory = $visits->fetchAll(PDO::FETCH_ASSOC);

// Get status history
$history = $pdo->prepare("SELECT * FROM complaint_status_history WHERE complaint_id = ? ORDER BY changed_at DESC");
$history->execute([$id]);
$statusHistory = $history->fetchAll(PDO::FETCH_ASSOC);

// Get technicians for assignment
$technicians = $pdo->query("SELECT id, tech_code, name FROM service_technicians WHERE status = 'Active' ORDER BY name")->fetchAll();

// Get attachments
$attachments = [];
try {
    $attStmt = $pdo->prepare("SELECT * FROM service_complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at DESC");
    $attStmt->execute([$id]);
    $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get linked QC issues
$qcIssues = [];
try {
    $qcStmt = $pdo->prepare("SELECT id, issue_no, issue_type, title, priority, severity, status, issue_date, assigned_to FROM qc_quality_issues WHERE service_complaint_id = ? ORDER BY created_at DESC");
    $qcStmt->execute([$id]);
    $qcIssues = $qcStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$activeTab = $_GET['tab'] ?? 'overview';

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complaint <?= htmlspecialchars($complaint['complaint_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .complaint-view { max-width: 1100px; }

        /* Header */
        .complaint-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 0;
            position: relative;
        }
        .complaint-header h1 { margin: 0 0 8px 0; font-size: 1.5em; }
        .complaint-header .complaint-no { font-size: 1.1em; opacity: 0.9; font-weight: 500; }
        .complaint-header .meta { margin-top: 15px; display: flex; gap: 30px; flex-wrap: wrap; }
        .complaint-header .meta-item label { opacity: 0.8; font-size: 0.8em; display: block; }
        .complaint-header .meta-item .value { font-weight: bold; font-size: 0.95em; }

        /* Status & Priority Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85em;
        }
        .status-Open { background: #ffeaa7; color: #d68910; }
        .status-Assigned { background: #dfe6e9; color: #636e72; }
        .status-In-Progress { background: #e8daef; color: #8e44ad; }
        .status-On-Hold { background: #fad7a0; color: #d35400; }
        .status-Resolved { background: #d5f4e6; color: #27ae60; }
        .status-Closed { background: #d4e6f1; color: #2980b9; }
        .status-Cancelled { background: #fadbd8; color: #c0392b; }

        .priority-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85em;
        }
        .priority-Critical { background: #e74c3c; color: white; }
        .priority-High { background: #e67e22; color: white; }
        .priority-Medium { background: #f1c40f; color: #333; }
        .priority-Low { background: #95a5a6; color: white; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .action-buttons .btn { font-size: 0.9em; }

        /* Tabs */
        .tab-nav {
            display: flex;
            background: white;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-top: 2px solid #c0392b;
        }
        .tab-nav a {
            flex: 1;
            text-align: center;
            padding: 14px 10px;
            text-decoration: none;
            color: #555;
            font-weight: 600;
            font-size: 0.95em;
            border-right: 1px solid #eee;
            transition: all 0.2s;
            position: relative;
        }
        .tab-nav a:last-child { border-right: none; }
        .tab-nav a:hover { background: #f8f9fa; color: #333; }
        .tab-nav a.active {
            background: #f8f9fa;
            color: #e74c3c;
        }
        .tab-nav a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #e74c3c;
        }
        .tab-nav .badge-count {
            display: inline-block;
            background: #e74c3c;
            color: white;
            font-size: 0.75em;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: 5px;
            font-weight: bold;
        }
        .tab-nav .badge-count.green { background: #27ae60; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
        }

        .info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #eee;
        }
        .info-card h3 {
            margin: 0;
            padding: 12px 18px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
            color: #2c3e50;
        }
        .info-card .card-content { padding: 18px; }
        .info-card .item { margin-bottom: 10px; }
        .info-card .item:last-child { margin-bottom: 0; }
        .info-card .item label { display: block; color: #7f8c8d; font-size: 0.8em; margin-bottom: 2px; }
        .info-card .item .value { font-weight: 500; color: #2c3e50; }

        .description-box {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 4px solid #e74c3c;
            white-space: pre-wrap;
            line-height: 1.6;
            font-size: 0.95em;
        }

        /* Forms */
        .assign-form, .status-form {
            background: white;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .assign-form h4, .status-form h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .assign-form .form-row, .status-form .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #495057; font-size: 0.9em; }
        .form-group select, .form-group input, .form-group textarea {
            padding: 9px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            width: 100%;
            box-sizing: border-box;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231,76,60,0.1);
        }

        /* Attachment Section */
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 20px;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #e74c3c;
            background: #fff5f5;
        }
        .upload-area .upload-icon {
            font-size: 2.5em;
            color: #ccc;
            margin-bottom: 10px;
        }
        .upload-area p { color: #666; margin: 5px 0; }
        .upload-area .file-types { font-size: 0.8em; color: #999; }

        .attachment-list {
            width: 100%;
            border-collapse: collapse;
        }
        .attachment-list th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85em;
            color: #555;
            border-bottom: 2px solid #eee;
        }
        .attachment-list td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
        }
        .attachment-list tr:hover { background: #f8f9fa; }
        .file-icon {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            text-align: center;
            line-height: 32px;
            font-size: 0.75em;
            font-weight: bold;
            color: white;
            margin-right: 8px;
            vertical-align: middle;
        }
        .file-icon.pdf { background: #e74c3c; }
        .file-icon.img { background: #3498db; }
        .file-icon.doc { background: #2980b9; }
        .file-icon.xls { background: #27ae60; }
        .file-icon.vid { background: #8e44ad; }
        .file-icon.other { background: #95a5a6; }

        .file-size { color: #999; font-size: 0.85em; }

        /* QC Issues Section */
        .qc-form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .qc-form-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        .qc-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .qc-form-grid .full-width { grid-column: 1 / -1; }

        .qc-issue-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: box-shadow 0.2s;
        }
        .qc-issue-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .qc-issue-card .issue-info { flex: 1; }
        .qc-issue-card .issue-no { font-weight: bold; color: #e74c3c; }
        .qc-issue-card .issue-title { color: #333; margin: 4px 0; }
        .qc-issue-card .issue-meta { font-size: 0.85em; color: #888; }

        .qc-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .qc-status-Open { background: #ffeaa7; color: #d68910; }
        .qc-status-Analysis { background: #dfe6e9; color: #636e72; }
        .qc-status-In-Progress { background: #e8daef; color: #8e44ad; }
        .qc-status-Closed { background: #d5f4e6; color: #27ae60; }

        /* History Section */
        .history-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            border: 1px solid #eee;
            overflow: hidden;
        }
        .history-section h3 {
            margin: 0;
            padding: 14px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
            color: #2c3e50;
        }
        .history-section .hist-content { padding: 20px; }

        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f5f5f5;
        }
        .timeline-item:last-child { margin-bottom: 0; border-bottom: none; padding-bottom: 0; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #3498db;
        }
        .timeline-item .date { color: #7f8c8d; font-size: 0.82em; }
        .timeline-item .status-change { font-weight: bold; margin: 4px 0; font-size: 0.95em; }
        .timeline-item .remarks { color: #555; font-size: 0.88em; }

        /* Visit History Table */
        .visit-table {
            width: 100%;
            border-collapse: collapse;
        }
        .visit-table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85em;
            color: #555;
            border-bottom: 2px solid #eee;
        }
        .visit-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
        }
        .visit-table tr:hover { background: #f8f9fa; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state .empty-icon { font-size: 2em; margin-bottom: 10px; opacity: 0.5; }
        .empty-state p { margin: 5px 0; }

        /* Notes Card */
        .notes-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            border: 1px solid #eee;
            overflow: hidden;
        }
        .notes-card h3 {
            margin: 0;
            padding: 12px 18px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
            color: #2c3e50;
        }
        .notes-card .card-content { padding: 18px; }

        .btn-danger {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
        }
        .btn-danger:hover { background: #c0392b; }

        .required { color: #e74c3c; }
    </style>
</head>
<body>

<div class="content">
    <div class="complaint-view">

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="complaints.php" class="btn btn-secondary">Back to Complaints</a>
            <a href="complaint_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit Complaint</a>
            <?php if (!in_array($complaint['status'], ['Resolved', 'Closed', 'Cancelled'])): ?>
                <a href="#status-update" class="btn btn-warning" onclick="switchTab('overview')">Update Status</a>
            <?php endif; ?>
        </div>

        <!-- Complaint Header -->
        <div class="complaint-header">
            <div class="complaint-no"><?= htmlspecialchars($complaint['complaint_no']) ?></div>
            <h1><?= htmlspecialchars($complaint['customer_name']) ?></h1>
            <div class="meta">
                <div class="meta-item">
                    <label>Status</label>
                    <div class="value"><span class="status-badge status-<?= str_replace(' ', '-', $complaint['status']) ?>"><?= $complaint['status'] ?></span></div>
                </div>
                <div class="meta-item">
                    <label>Priority</label>
                    <div class="value"><span class="priority-badge priority-<?= $complaint['priority'] ?>"><?= $complaint['priority'] ?></span></div>
                </div>
                <div class="meta-item">
                    <label>Registered</label>
                    <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['registered_date'])) ?></div>
                </div>
                <?php if ($complaint['scheduled_visit_date']): ?>
                <div class="meta-item">
                    <label>Scheduled Visit</label>
                    <div class="value"><?= date('d M Y', strtotime($complaint['scheduled_visit_date'])) ?> <?= $complaint['scheduled_visit_time'] ? date('h:i A', strtotime($complaint['scheduled_visit_time'])) : '' ?></div>
                </div>
                <?php endif; ?>
                <?php if ($complaint['resolution_date']): ?>
                <div class="meta-item">
                    <label>Resolved On</label>
                    <div class="value"><?= date('d M Y', strtotime($complaint['resolution_date'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="#" class="<?= $activeTab === 'overview' ? 'active' : '' ?>" onclick="switchTab('overview'); return false;">Overview</a>
            <a href="#" class="<?= $activeTab === 'attachments' ? 'active' : '' ?>" onclick="switchTab('attachments'); return false;">
                Attachments
                <?php if (count($attachments) > 0): ?>
                    <span class="badge-count"><?= count($attachments) ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="<?= $activeTab === 'quality' ? 'active' : '' ?>" onclick="switchTab('quality'); return false;">
                Quality Issues
                <?php if (count($qcIssues) > 0): ?>
                    <span class="badge-count green"><?= count($qcIssues) ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="<?= $activeTab === 'history' ? 'active' : '' ?>" onclick="switchTab('history'); return false;">History</a>
        </div>

        <!-- ============ OVERVIEW TAB ============ -->
        <div class="tab-content <?= $activeTab === 'overview' ? 'active' : '' ?>" id="tab-overview">

            <?php if ($complaint['status'] === 'Open' && empty($complaint['assigned_technician_id'])): ?>
            <div class="assign-form">
                <h4>Assign Technician</h4>
                <form method="post">
                    <input type="hidden" name="action" value="assign">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Technician <span class="required">*</span></label>
                            <select name="technician_id" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?> (<?= $tech['tech_code'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Visit Date</label>
                            <input type="date" name="visit_date">
                        </div>
                        <div class="form-group">
                            <label>Visit Time</label>
                            <input type="time" name="visit_time">
                        </div>
                        <button type="submit" class="btn btn-success">Assign</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-card">
                    <h3>Customer Details</h3>
                    <div class="card-content">
                        <div class="item">
                            <label>Name</label>
                            <div class="value"><?= htmlspecialchars($complaint['customer_name']) ?></div>
                        </div>
                        <div class="item">
                            <label>Phone</label>
                            <div class="value"><a href="tel:<?= $complaint['customer_phone'] ?>"><?= htmlspecialchars($complaint['customer_phone']) ?></a></div>
                        </div>
                        <?php if ($complaint['customer_email']): ?>
                        <div class="item">
                            <label>Email</label>
                            <div class="value"><a href="mailto:<?= $complaint['customer_email'] ?>"><?= htmlspecialchars($complaint['customer_email']) ?></a></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($complaint['customer_address']): ?>
                        <div class="item">
                            <label>Address</label>
                            <div class="value">
                                <?= nl2br(htmlspecialchars($complaint['customer_address'])) ?>
                                <?php if ($complaint['city']): ?><br><?= htmlspecialchars($complaint['city']) ?><?php endif; ?>
                                <?php if ($complaint['state_name']): ?>, <?= htmlspecialchars($complaint['state_name']) ?><?php endif; ?>
                                <?php if ($complaint['pincode']): ?> - <?= htmlspecialchars($complaint['pincode']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Product Details</h3>
                    <div class="card-content">
                        <div class="item">
                            <label>Product</label>
                            <div class="value"><?= htmlspecialchars($complaint['product_name'] ?? 'Not specified') ?></div>
                        </div>
                        <?php if ($complaint['product_model']): ?>
                        <div class="item">
                            <label>Model</label>
                            <div class="value"><?= htmlspecialchars($complaint['product_model']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($complaint['serial_number']): ?>
                        <div class="item">
                            <label>Serial Number</label>
                            <div class="value"><?= htmlspecialchars($complaint['serial_number']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($complaint['purchase_date']): ?>
                        <div class="item">
                            <label>Purchase Date</label>
                            <div class="value"><?= date('d M Y', strtotime($complaint['purchase_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="item">
                            <label>Warranty Status</label>
                            <div class="value"><?= htmlspecialchars($complaint['warranty_status']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Issue Details</h3>
                    <div class="card-content">
                        <div class="item">
                            <label>Category</label>
                            <div class="value"><?= htmlspecialchars($complaint['category_name'] ?? 'Not categorized') ?></div>
                        </div>
                        <div class="item">
                            <label>Description</label>
                            <div class="description-box"><?= htmlspecialchars($complaint['complaint_description']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Assignment</h3>
                    <div class="card-content">
                        <div class="item">
                            <label>Assigned Technician</label>
                            <div class="value"><?= htmlspecialchars($complaint['technician_name'] ?? 'Not assigned') ?></div>
                        </div>
                        <?php if ($complaint['technician_phone']): ?>
                        <div class="item">
                            <label>Technician Phone</label>
                            <div class="value"><a href="tel:<?= $complaint['technician_phone'] ?>"><?= htmlspecialchars($complaint['technician_phone']) ?></a></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($complaint['assigned_date']): ?>
                        <div class="item">
                            <label>Assigned On</label>
                            <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['assigned_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($complaint['resolution_date']): ?>
                        <div class="item">
                            <label>Resolved On</label>
                            <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['resolution_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($complaint['resolution_notes']): ?>
            <div class="notes-card">
                <h3>Resolution Notes</h3>
                <div class="card-content">
                    <div class="description-box"><?= nl2br(htmlspecialchars($complaint['resolution_notes'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($complaint['internal_notes'])): ?>
            <div class="notes-card">
                <h3>Internal Notes</h3>
                <div class="card-content">
                    <div class="description-box" style="border-left-color: #3498db;"><?= nl2br(htmlspecialchars($complaint['internal_notes'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!in_array($complaint['status'], ['Resolved', 'Closed', 'Cancelled'])): ?>
            <div class="status-form" id="status-update">
                <h4>Update Status</h4>
                <form method="post">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Status <span class="required">*</span></label>
                            <select name="new_status" required onchange="toggleResolutionNotes(this.value)">
                                <option value="">-- Select --</option>
                                <option value="In Progress">In Progress</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Remarks</label>
                            <input type="text" name="remarks" placeholder="Optional remarks...">
                        </div>
                    </div>
                    <div class="form-group" id="resolution-notes-group" style="display: none; margin-top: 15px;">
                        <label>Resolution Notes</label>
                        <textarea name="resolution_notes" rows="3" placeholder="Describe how the issue was resolved..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning" style="margin-top: 15px;">Update Status</button>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <!-- ============ ATTACHMENTS TAB ============ -->
        <div class="tab-content <?= $activeTab === 'attachments' ? 'active' : '' ?>" id="tab-attachments">

            <!-- Upload Form -->
            <div class="qc-form-section">
                <h3>Upload Attachments</h3>
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload_attachment">

                    <div class="upload-area" id="dropZone" onclick="document.getElementById('fileInput').click()">
                        <div class="upload-icon">&#128206;</div>
                        <p><strong>Click to upload or drag & drop files here</strong></p>
                        <p class="file-types">Allowed: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, MP4, MOV (Max 10MB each)</p>
                        <input type="file" name="attachments[]" id="fileInput" multiple style="display:none" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.mov" onchange="showSelectedFiles(this)">
                    </div>

                    <div id="selectedFiles" style="display:none; margin-bottom: 15px;">
                        <strong>Selected files:</strong>
                        <ul id="fileList" style="margin: 5px 0; padding-left: 20px;"></ul>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Description (optional)</label>
                        <input type="text" name="attachment_description" placeholder="Brief description of attachments...">
                    </div>

                    <button type="submit" class="btn btn-primary" id="uploadBtn" style="display:none;">Upload Files</button>
                </form>
            </div>

            <!-- Attachment List -->
            <?php if (!empty($attachments)): ?>
            <div class="qc-form-section">
                <h3>Uploaded Files (<?= count($attachments) ?>)</h3>
                <table class="attachment-list">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Size</th>
                            <th>Description</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attachments as $att):
                            $ext = strtolower($att['file_type']);
                            $iconClass = 'other';
                            $iconText = strtoupper($ext);
                            if (in_array($ext, ['jpg','jpeg','png','gif'])) { $iconClass = 'img'; $iconText = 'IMG'; }
                            elseif ($ext === 'pdf') { $iconClass = 'pdf'; $iconText = 'PDF'; }
                            elseif (in_array($ext, ['doc','docx'])) { $iconClass = 'doc'; $iconText = 'DOC'; }
                            elseif (in_array($ext, ['xls','xlsx'])) { $iconClass = 'xls'; $iconText = 'XLS'; }
                            elseif (in_array($ext, ['mp4','mov'])) { $iconClass = 'vid'; $iconText = 'VID'; }

                            $sizeKB = round($att['file_size'] / 1024, 1);
                            $sizeDisplay = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';
                        ?>
                        <tr>
                            <td>
                                <span class="file-icon <?= $iconClass ?>"><?= $iconText ?></span>
                                <a href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank"><?= htmlspecialchars($att['original_name']) ?></a>
                            </td>
                            <td class="file-size"><?= $sizeDisplay ?></td>
                            <td><?= htmlspecialchars($att['description'] ?: '-') ?></td>
                            <td><?= date('d M Y, h:i A', strtotime($att['uploaded_at'])) ?></td>
                            <td>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this attachment?')">
                                    <input type="hidden" name="action" value="delete_attachment">
                                    <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="qc-form-section">
                <div class="empty-state">
                    <div class="empty-icon">&#128193;</div>
                    <p><strong>No attachments yet</strong></p>
                    <p>Upload photos, documents, or videos related to this complaint.</p>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ============ QUALITY ISSUES TAB ============ -->
        <div class="tab-content <?= $activeTab === 'quality' ? 'active' : '' ?>" id="tab-quality">

            <!-- Raise New QC Issue -->
            <div class="qc-form-section">
                <h3>Raise Quality Issue</h3>
                <p style="color: #666; margin: 0 0 20px 0; font-size: 0.9em;">
                    Create a quality issue linked to this service complaint. The issue will be tracked in the Quality Control module.
                </p>
                <form method="post">
                    <input type="hidden" name="action" value="raise_quality_issue">
                    <div class="qc-form-grid">
                        <div class="form-group">
                            <label>Issue Type <span class="required">*</span></label>
                            <select name="issue_type" required>
                                <option value="Customer Complaint">Customer Complaint</option>
                                <option value="Field Issue">Field Issue</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority <span class="required">*</span></label>
                            <select name="issue_priority">
                                <option value="Critical" <?= $complaint['priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
                                <option value="High" <?= $complaint['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                                <option value="Medium" <?= ($complaint['priority'] === 'Medium' || !$complaint['priority']) ? 'selected' : '' ?>>Medium</option>
                                <option value="Low" <?= $complaint['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Detection Stage</label>
                            <select name="detection_stage">
                                <option value="Customer Use" selected>Customer Use</option>
                                <option value="Field">Field</option>
                                <option value="Installation">Installation</option>
                                <option value="Shipping">Shipping</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Issue Title <span class="required">*</span></label>
                            <input type="text" name="issue_title" required
                                   value="Service Complaint: <?= htmlspecialchars(substr($complaint['complaint_description'] ?? '', 0, 100)) ?>"
                                   placeholder="Brief title describing the quality issue">
                        </div>
                        <div class="form-group full-width">
                            <label>Issue Description</label>
                            <textarea name="issue_description" rows="4" placeholder="Additional details about the quality issue..."><?= htmlspecialchars(
                                "Complaint No: " . $complaint['complaint_no'] . "\n" .
                                "Customer: " . $complaint['customer_name'] . "\n" .
                                "Product: " . ($complaint['product_name'] ?? 'N/A') . " | Model: " . ($complaint['product_model'] ?? 'N/A') . " | S/N: " . ($complaint['serial_number'] ?? 'N/A') . "\n" .
                                "Warranty: " . ($complaint['warranty_status'] ?? 'N/A') . "\n\n" .
                                "Issue Description:\n" . ($complaint['complaint_description'] ?? '')
                            ) ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <strong>Auto-filled from complaint:</strong>
                        <span style="color:#666; font-size:0.9em;">
                            Customer: <?= htmlspecialchars($complaint['customer_name']) ?> |
                            Product: <?= htmlspecialchars($complaint['product_name'] ?? 'N/A') ?> |
                            Serial: <?= htmlspecialchars($complaint['serial_number'] ?? 'N/A') ?>
                        </span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 15px;" onclick="return confirm('Create a new Quality Issue linked to this complaint?')">
                        Raise Quality Issue
                    </button>
                </form>
            </div>

            <!-- Linked QC Issues -->
            <?php if (!empty($qcIssues)): ?>
            <div class="qc-form-section">
                <h3>Linked Quality Issues (<?= count($qcIssues) ?>)</h3>
                <?php foreach ($qcIssues as $qi): ?>
                <div class="qc-issue-card">
                    <div class="issue-info">
                        <div class="issue-no"><?= htmlspecialchars($qi['issue_no']) ?></div>
                        <div class="issue-title"><?= htmlspecialchars($qi['title']) ?></div>
                        <div class="issue-meta">
                            <?= htmlspecialchars($qi['issue_type']) ?> |
                            Priority: <?= htmlspecialchars($qi['priority']) ?> |
                            Date: <?= date('d M Y', strtotime($qi['issue_date'])) ?>
                            <?php if ($qi['assigned_to']): ?> | Assigned: <?= htmlspecialchars($qi['assigned_to']) ?><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="qc-status-badge qc-status-<?= str_replace(' ', '-', $qi['status']) ?>"><?= $qi['status'] ?></span>
                        <a href="../quality_control/issue_view.php?id=<?= $qi['id'] ?>" class="btn btn-primary" style="margin-left: 10px; font-size: 0.85em;">View</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="qc-form-section">
                <div class="empty-state">
                    <div class="empty-icon">&#128270;</div>
                    <p><strong>No quality issues linked</strong></p>
                    <p>Use the form above to raise a quality issue from this complaint.</p>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- ============ HISTORY TAB ============ -->
        <div class="tab-content <?= $activeTab === 'history' ? 'active' : '' ?>" id="tab-history">

            <!-- Visit History -->
            <div class="history-section">
                <h3>Visit History</h3>
                <div class="hist-content">
                    <?php if (!empty($visitHistory)): ?>
                    <table class="visit-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Technician</th>
                                <th>Status</th>
                                <th>Findings</th>
                                <th>Action Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitHistory as $v): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($v['visit_date'])) ?></td>
                                <td><?= htmlspecialchars($v['technician_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($v['visit_status'] ?? $v['status'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($v['findings'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($v['action_taken'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">&#128666;</div>
                        <p><strong>No visits recorded</strong></p>
                        <p>Visit history will appear here once technician visits are logged.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status History Timeline -->
            <?php if (!empty($statusHistory)): ?>
            <div class="history-section">
                <h3>Status History</h3>
                <div class="hist-content">
                    <div class="timeline">
                        <?php foreach ($statusHistory as $h): ?>
                        <div class="timeline-item">
                            <div class="date"><?= date('d M Y, h:i A', strtotime($h['changed_at'])) ?></div>
                            <div class="status-change">
                                <?php if ($h['old_status']): ?>
                                    <?= $h['old_status'] ?> &rarr; <?= $h['new_status'] ?>
                                <?php else: ?>
                                    <?= $h['new_status'] ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($h['remarks']): ?>
                            <div class="remarks"><?= htmlspecialchars($h['remarks']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="history-section">
                <h3>Status History</h3>
                <div class="hist-content">
                    <div class="empty-state">
                        <div class="empty-icon">&#128337;</div>
                        <p><strong>No status changes recorded</strong></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
    // Update tab nav
    document.querySelectorAll('.tab-nav a').forEach(a => a.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

    // Activate selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    // Find and activate the nav link
    const navLinks = document.querySelectorAll('.tab-nav a');
    const tabNames = ['overview', 'attachments', 'quality', 'history'];
    const idx = tabNames.indexOf(tabName);
    if (idx >= 0 && navLinks[idx]) {
        navLinks[idx].classList.add('active');
    }

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);
}

// Status form toggle
function toggleResolutionNotes(status) {
    const notesGroup = document.getElementById('resolution-notes-group');
    if (notesGroup) {
        notesGroup.style.display = (status === 'Resolved' || status === 'Closed') ? 'block' : 'none';
    }
}

// File upload - show selected files
function showSelectedFiles(input) {
    const container = document.getElementById('selectedFiles');
    const list = document.getElementById('fileList');
    const btn = document.getElementById('uploadBtn');
    list.innerHTML = '';

    if (input.files.length > 0) {
        container.style.display = 'block';
        btn.style.display = 'inline-block';
        for (let i = 0; i < input.files.length; i++) {
            const li = document.createElement('li');
            const sizeKB = (input.files[i].size / 1024).toFixed(1);
            li.textContent = input.files[i].name + ' (' + (sizeKB >= 1024 ? (sizeKB/1024).toFixed(1) + ' MB' : sizeKB + ' KB') + ')';
            list.appendChild(li);
        }
    } else {
        container.style.display = 'none';
        btn.style.display = 'none';
    }
}

// Drag & drop
const dropZone = document.getElementById('dropZone');
if (dropZone) {
    ['dragenter', 'dragover'].forEach(ev => {
        dropZone.addEventListener(ev, function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        dropZone.addEventListener(ev, function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
    });
    dropZone.addEventListener('drop', function(e) {
        const fileInput = document.getElementById('fileInput');
        fileInput.files = e.dataTransfer.files;
        showSelectedFiles(fileInput);
    });
}
</script>

</body>
</html>
