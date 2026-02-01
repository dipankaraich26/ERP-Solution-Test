<?php
/**
 * Fix script to add 'rate' column to purchase_orders table
 * This column stores the unit rate/price for each part in the PO.
 * Run this once to fix the database structure.
 */
include "../db.php";

$messages = [];
$errors = [];

try {
    // Check if 'rate' column exists
    $result = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'rate'");
    $column = $result->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        // Add the 'rate' column
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN rate DECIMAL(12,2) DEFAULT 0 AFTER qty");
        $messages[] = "Added 'rate' column to purchase_orders table";
    } else {
        $messages[] = "'rate' column already exists in purchase_orders table";
    }

    // Also check for 'amount' column (rate * qty) which might be useful
    $result2 = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'amount'");
    $column2 = $result2->fetch(PDO::FETCH_ASSOC);

    if (!$column2) {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN amount DECIMAL(12,2) DEFAULT 0 AFTER rate");
        $messages[] = "Added 'amount' column to purchase_orders table";
    } else {
        $messages[] = "'amount' column already exists in purchase_orders table";
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Purchase Orders Rate Column</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .info { background: #cce5ff; border: 1px solid #b8daff; padding: 15px; border-radius: 5px; margin: 10px 0; color: #004085; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Fix Purchase Orders Rate Column</h1>

    <div class="info">
        <strong>Purpose:</strong> This script adds the 'rate' column to the purchase_orders table.
        This column is required to store the unit price for each part in a purchase order.
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

    <p><a href="/purchase/index.php">Go to Purchase Orders</a></p>
</body>
</html>
