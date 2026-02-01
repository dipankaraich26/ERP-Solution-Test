<?php
include "../db.php";
include "../includes/dialog.php";

$messages = [];
$errors = [];

// Add E-Way Bill number and attachment columns to invoice_master if they don't exist
try {
    // Check if columns exist
    $checkStmt = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'invoice_master' AND COLUMN_NAME = 'eway_bill_no'
    ");
    $checkStmt->execute();
    $hasEwayColumn = $checkStmt->fetch();

    if (!$hasEwayColumn) {
        // Add eway_bill_no column
        $pdo->exec("
            ALTER TABLE invoice_master
            ADD COLUMN eway_bill_no VARCHAR(100) NULL COMMENT 'E-Way Bill Number'
        ");
        $messages[] = "Added 'eway_bill_no' column to invoice_master";
    } else {
        $messages[] = "Column 'eway_bill_no' already exists in invoice_master";
    }

    // Check if eway_bill_attachment column exists
    $checkStmt2 = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'invoice_master' AND COLUMN_NAME = 'eway_bill_attachment'
    ");
    $checkStmt2->execute();
    $hasEwayAttachmentColumn = $checkStmt2->fetch();

    if (!$hasEwayAttachmentColumn) {
        // Add eway_bill_attachment column
        $pdo->exec("
            ALTER TABLE invoice_master
            ADD COLUMN eway_bill_attachment VARCHAR(500) NULL COMMENT 'Path to uploaded E-Way Bill attachment'
        ");
        $messages[] = "Added 'eway_bill_attachment' column to invoice_master";
    } else {
        $messages[] = "Column 'eway_bill_attachment' already exists in invoice_master";
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
    <title>E-Way Bill Setup</title>
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
    <h1>E-Way Bill Setup</h1>
    <p>This setup adds E-Way Bill number and attachment fields to the invoice system.</p>

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
            The invoice system is now ready to handle E-Way Bill numbers and attachments.<br>
            <br>
            <strong>Features enabled:</strong>
            <ul>
                <li>E-Way Bill number field on invoices</li>
                <li>E-Way Bill document upload (PDF, image, or other formats)</li>
                <li>View uploaded E-Way Bill attachments</li>
            </ul>
        </div>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="index.php" class="btn btn-primary">Go to Invoices</a>
    </p>
</div>
</body>
</html>
