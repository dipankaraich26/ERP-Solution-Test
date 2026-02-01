<?php
include "../db.php";
include "../includes/dialog.php";

$messages = [];
$errors = [];

// Add attachment column to quote_master if it doesn't exist
try {
    // Check if column exists
    $checkStmt = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'quote_master' AND COLUMN_NAME = 'pi_attachment'
    ");
    $checkStmt->execute();
    $hasAttachmentColumn = $checkStmt->fetch();

    if (!$hasAttachmentColumn) {
        // Add pi_attachment column
        $pdo->exec("
            ALTER TABLE quote_master
            ADD COLUMN pi_attachment VARCHAR(500) NULL COMMENT 'Path to uploaded Proforma Invoice PDF attachment'
        ");
        $messages[] = "Added 'pi_attachment' column to quote_master";
    } else {
        $messages[] = "Column 'pi_attachment' already exists in quote_master";
    }

    // Create uploads/proforma directory if it doesn't exist
    $uploadDir = '../uploads/proforma';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $messages[] = "Created uploads/proforma directory";
        } else {
            $errors[] = "Failed to create uploads/proforma directory";
        }
    } else {
        $messages[] = "uploads/proforma directory already exists";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Proforma Invoice Attachment Setup</title>
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
    <h1>Proforma Invoice Attachment Setup</h1>
    <p>This setup adds PDF attachment capability to Proforma Invoices.</p>

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
            Proforma Invoices can now have PDF attachments.<br>
            <br>
            <strong>Features enabled:</strong>
            <ul>
                <li>Upload PDF attachment to Proforma Invoices</li>
                <li>View and download attached PDFs</li>
                <li>Replace attachments with newer versions</li>
            </ul>
        </div>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="index.php" class="btn btn-primary">Go to Proforma Invoices</a>
    </p>
</div>
</body>
</html>
