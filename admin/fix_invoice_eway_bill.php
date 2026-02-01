<?php
/**
 * Fix script to add E-Way Bill columns to invoice_master table
 * These columns store the E-Way Bill number and attachment path.
 * Run this once to fix the database structure.
 */
include "../db.php";

$messages = [];
$errors = [];

try {
    // Check if 'eway_bill_no' column exists
    $result = $pdo->query("SHOW COLUMNS FROM invoice_master LIKE 'eway_bill_no'");
    $column = $result->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        $pdo->exec("ALTER TABLE invoice_master ADD COLUMN eway_bill_no VARCHAR(50) NULL AFTER status");
        $messages[] = "Added 'eway_bill_no' column to invoice_master table";
    } else {
        $messages[] = "'eway_bill_no' column already exists";
    }

    // Check if 'eway_bill_attachment' column exists
    $result2 = $pdo->query("SHOW COLUMNS FROM invoice_master LIKE 'eway_bill_attachment'");
    $column2 = $result2->fetch(PDO::FETCH_ASSOC);

    if (!$column2) {
        $pdo->exec("ALTER TABLE invoice_master ADD COLUMN eway_bill_attachment VARCHAR(255) NULL AFTER eway_bill_no");
        $messages[] = "Added 'eway_bill_attachment' column to invoice_master table";
    } else {
        $messages[] = "'eway_bill_attachment' column already exists";
    }

    // Check if 'released_at' column exists (for tracking release timestamp)
    $result3 = $pdo->query("SHOW COLUMNS FROM invoice_master LIKE 'released_at'");
    $column3 = $result3->fetch(PDO::FETCH_ASSOC);

    if (!$column3) {
        $pdo->exec("ALTER TABLE invoice_master ADD COLUMN released_at DATETIME NULL");
        $messages[] = "Added 'released_at' column to invoice_master table";
    } else {
        $messages[] = "'released_at' column already exists";
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Invoice E-Way Bill Columns</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .info { background: #cce5ff; border: 1px solid #b8daff; padding: 15px; border-radius: 5px; margin: 10px 0; color: #004085; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Fix Invoice E-Way Bill Columns</h1>

    <div class="info">
        <strong>Purpose:</strong> This script adds the E-Way Bill columns to the invoice_master table:
        <ul>
            <li><strong>eway_bill_no</strong> - Stores the 16-digit E-Way Bill number</li>
            <li><strong>eway_bill_attachment</strong> - Stores the path to uploaded E-Way Bill document</li>
            <li><strong>released_at</strong> - Stores when the invoice was released</li>
        </ul>
    </div>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <div class="error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p><a href="/invoices/index.php">Go to Invoices</a></p>
</body>
</html>
