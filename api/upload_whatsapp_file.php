<?php
include "../db.php";

header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];

// Validate file size (10MB max)
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large. Maximum size is 10MB']);
    exit;
}

// Allowed file types
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'video/mp4', 'video/quicktime',
    'audio/mpeg', 'audio/wav'
];

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(415);
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

// Create uploads directory if not exists
$uploadDir = dirname(__DIR__) . '/uploads/whatsapp';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = uniqid('wa_', true) . '.' . $fileExtension;
$filePath = $uploadDir . '/' . $fileName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Generate download URL
$downloadUrl = '/uploads/whatsapp/' . $fileName;

// Return success response
echo json_encode([
    'success' => true,
    'fileName' => $file['name'],
    'url' => $downloadUrl,
    'storedName' => $fileName
]);
?>
