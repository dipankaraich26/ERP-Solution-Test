<?php
/**
 * Milestone Documents Handler
 * Handles upload and delete of documents attached to project milestones
 */
include "../db.php";
include "../includes/dialog.php";

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : (isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0);
$milestone_id = isset($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : (isset($_GET['milestone_id']) ? (int)$_GET['milestone_id'] : 0);

if (!$project_id) {
    die("Invalid project ID");
}

// Verify project exists
$project_check = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
$project_check->execute([$project_id]);
if (!$project_check->fetch()) {
    die("Project not found");
}

// Check if milestone_documents table exists
$tableExists = true;
try {
    $pdo->query("SELECT 1 FROM milestone_documents LIMIT 1");
} catch (PDOException $e) {
    $tableExists = false;
}

if (!$tableExists) {
    setModal("Error", "Milestone documents table not found. Please run admin/setup_project_management.php first.");
    header("Location: view.php?id=" . $project_id);
    exit;
}

// Upload directory
$uploadDir = dirname(__DIR__) . '/uploads/milestone_documents';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Allowed file types
$allowedTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'zip' => 'application/zip',
    'dwg' => 'application/acad',
    'dxf' => 'application/dxf'
];
$maxFileSize = 10 * 1024 * 1024; // 10 MB

if ($action === 'upload') {
    if (!$milestone_id) {
        setModal("Error", "Invalid milestone ID");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Verify milestone belongs to this project
    $milestone_check = $pdo->prepare("SELECT id, milestone_name FROM project_milestones WHERE id = ? AND project_id = ?");
    $milestone_check->execute([$milestone_id, $project_id]);
    $milestone = $milestone_check->fetch();
    if (!$milestone) {
        setModal("Error", "Milestone not found");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        setModal("Error", "No file selected");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    $file = $_FILES['document'];
    $document_type = trim($_POST['document_type'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds server limit",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form limit",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension"
        ];
        $errorMsg = $errorMessages[$file['error']] ?? "Unknown upload error";
        setModal("Error", $errorMsg);
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Check file size
    if ($file['size'] > $maxFileSize) {
        setModal("Error", "File size exceeds 10MB limit");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Get file extension
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Check file type
    if (!array_key_exists($extension, $allowedTypes)) {
        setModal("Error", "File type not allowed. Allowed: " . implode(', ', array_keys($allowedTypes)));
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Generate unique filename
    $newFilename = 'MS' . $milestone_id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . '/' . $newFilename;
    $relativePath = 'uploads/milestone_documents/' . $newFilename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        setModal("Error", "Failed to save file");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO milestone_documents
            (milestone_id, project_id, document_name, original_name, document_type, file_path, file_size, uploaded_by, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $milestone_id,
            $project_id,
            $newFilename,
            $originalName,
            $document_type ?: $extension,
            $relativePath,
            $file['size'],
            $_SESSION['user_id'] ?? 'System',
            $remarks ?: null
        ]);

        setModal("Success", "Document uploaded successfully to milestone: " . $milestone['milestone_name']);
    } catch (Exception $e) {
        // Delete the uploaded file if database insert fails
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        setModal("Error", "Failed to save document record: " . $e->getMessage());
    }

    header("Location: view.php?id=" . $project_id);
    exit;

} elseif ($action === 'delete') {
    $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$document_id) {
        setModal("Error", "Invalid document ID");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    // Get document info
    $doc_stmt = $pdo->prepare("SELECT * FROM milestone_documents WHERE id = ? AND project_id = ?");
    $doc_stmt->execute([$document_id, $project_id]);
    $document = $doc_stmt->fetch();

    if (!$document) {
        setModal("Error", "Document not found");
        header("Location: view.php?id=" . $project_id);
        exit;
    }

    try {
        // Delete from database
        $delete_stmt = $pdo->prepare("DELETE FROM milestone_documents WHERE id = ?");
        $delete_stmt->execute([$document_id]);

        // Delete physical file
        $filePath = dirname(__DIR__) . '/' . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        setModal("Success", "Document deleted successfully");
    } catch (Exception $e) {
        setModal("Error", "Failed to delete document: " . $e->getMessage());
    }

    header("Location: view.php?id=" . $project_id);
    exit;
}

// Invalid action
header("Location: view.php?id=" . $project_id);
exit;
?>
