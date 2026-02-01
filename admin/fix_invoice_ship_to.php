<?php
/**
 * Fix script to add Ship-to Address columns to invoice_master table
 * This allows invoices to have a separate shipping address from the billing address.
 * Run this once to fix the database structure.
 */
include "../db.php";

$messages = [];
$errors = [];

$columns = [
    'ship_to_company_name' => "VARCHAR(255) NULL",
    'ship_to_contact_name' => "VARCHAR(255) NULL",
    'ship_to_address1' => "VARCHAR(255) NULL",
    'ship_to_address2' => "VARCHAR(255) NULL",
    'ship_to_city' => "VARCHAR(100) NULL",
    'ship_to_pincode' => "VARCHAR(20) NULL",
    'ship_to_state' => "VARCHAR(100) NULL",
    'ship_to_gstin' => "VARCHAR(50) NULL"
];

try {
    foreach ($columns as $column => $definition) {
        $result = $pdo->query("SHOW COLUMNS FROM invoice_master LIKE '$column'");
        $exists = $result->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $pdo->exec("ALTER TABLE invoice_master ADD COLUMN $column $definition");
            $messages[] = "Added '$column' column to invoice_master table";
        } else {
            $messages[] = "'$column' column already exists";
        }
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Invoice Ship-to Address Columns</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 700px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .info { background: #cce5ff; border: 1px solid #b8daff; padding: 15px; border-radius: 5px; margin: 10px 0; color: #004085; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Fix Invoice Ship-to Address Columns</h1>

    <div class="info">
        <strong>Purpose:</strong> This script adds Ship-to Address columns to the invoice_master table:
        <ul>
            <li><strong>ship_to_company_name</strong> - Shipping destination company name</li>
            <li><strong>ship_to_contact_name</strong> - Contact person at shipping address</li>
            <li><strong>ship_to_address1</strong> - Shipping address line 1</li>
            <li><strong>ship_to_address2</strong> - Shipping address line 2</li>
            <li><strong>ship_to_city</strong> - Shipping city</li>
            <li><strong>ship_to_pincode</strong> - Shipping pincode</li>
            <li><strong>ship_to_state</strong> - Shipping state</li>
            <li><strong>ship_to_gstin</strong> - GSTIN at shipping location (if applicable)</li>
        </ul>
        <p style="margin-top: 10px;"><strong>Note:</strong> Bill-to address comes from the customer record (via PI/quotation), while Ship-to address can be set separately for each invoice.</p>
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
