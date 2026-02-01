<?php
include "../db.php";
include "../includes/dialog.php";

$messages = [];
$errors = [];

// Add UAB number and PDF attachment columns to invoice_master if they don't exist
try {
    // Check if columns exist
    $checkStmt = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'invoice_master' AND COLUMN_NAME = 'uab_number'
    ");
    $checkStmt->execute();
    $hasUabColumn = $checkStmt->fetch();

    if (!$hasUabColumn) {
        // Add uab_number column
        $pdo->exec("
            ALTER TABLE invoice_master
            ADD COLUMN uab_number VARCHAR(100) NULL COMMENT 'Unique Authorization Book number'
        ");
        $messages[] = "Added 'uab_number' column to invoice_master";
    } else {
        $messages[] = "Column 'uab_number' already exists in invoice_master";
    }

    // Check if uab_pdf_path column exists
    $checkStmt2 = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'invoice_master' AND COLUMN_NAME = 'uab_pdf_path'
    ");
    $checkStmt2->execute();
    $hasUabPdfColumn = $checkStmt2->fetch();

    if (!$hasUabPdfColumn) {
        // Add uab_pdf_path column
        $pdo->exec("
            ALTER TABLE invoice_master
            ADD COLUMN uab_pdf_path VARCHAR(500) NULL COMMENT 'Path to uploaded UAB PDF file'
        ");
        $messages[] = "Added 'uab_pdf_path' column to invoice_master";
    } else {
        $messages[] = "Column 'uab_pdf_path' already exists in invoice_master";
    }

    // Create uploads/invoices directory if it doesn't exist
    $uploadDir = '../uploads/invoices';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $messages[] = "Created uploads/invoices directory";
        } else {
            $errors[] = "Failed to create uploads/invoices directory";
        }
    } else {
        $messages[] = "uploads/invoices directory already exists";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice UAB Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 10px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 10px; }
        .info { background: #e7f3ff; border: 1px solid #b3d9ff; color: #004085; padding: 12px; border-radius: 4px; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Invoice UAB Setup</h1>
    <p>This setup adds UAB (Unique Authorization Book) number and PDF attachment fields to the invoice system.</p>

    <h3>Setup Results:</h3>

    <?php foreach ($messages as $msg): ?>
        <div class="success">✓ <?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
        <div class="error">✗ <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
        <div class="info">
            <strong>Setup Complete!</strong><br>
            The invoice system is now ready to handle UAB number and PDF attachments.<br>
            <br>
            <strong>Features enabled:</strong>
            <ul>
                <li>UAB number field on invoices</li>
                <li>PDF file upload for UAB documentation</li>
                <li>Mandatory validation before invoice release</li>
            </ul>
        </div>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="index.php" class="btn btn-primary">Go to Invoices</a>
    </p>
</div>
</body>
</html>
