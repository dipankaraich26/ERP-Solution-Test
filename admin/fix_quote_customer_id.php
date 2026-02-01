<?php
/**
 * Fix script to allow NULL customer_id in quote_master table
 * This is needed because quotations are created for WARM leads
 * which may not have an associated customer yet.
 * Run this once to fix the database structure.
 */
include "../db.php";

$messages = [];
$errors = [];

try {
    // Check current column definition
    $result = $pdo->query("SHOW COLUMNS FROM quote_master LIKE 'customer_id'");
    $column = $result->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        $isNullable = ($column['Null'] === 'YES');

        if (!$isNullable) {
            // Alter the column to allow NULL
            $pdo->exec("ALTER TABLE quote_master MODIFY COLUMN customer_id VARCHAR(50) NULL");
            $messages[] = "Modified 'customer_id' column to allow NULL values";
        } else {
            $messages[] = "'customer_id' column already allows NULL values";
        }
    } else {
        $errors[] = "Column 'customer_id' not found in quote_master table";
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Quote Customer ID</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .info { background: #cce5ff; border: 1px solid #b8daff; padding: 15px; border-radius: 5px; margin: 10px 0; color: #004085; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Fix Quote Customer ID</h1>

    <div class="info">
        <strong>Purpose:</strong> This script modifies the quote_master table to allow NULL values
        for customer_id. This is required because quotations are created for WARM leads which
        may not have an associated customer yet (customers are created when leads become HOT).
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

    <p><a href="/quotes/add.php">Go to Add Quotation</a></p>
    <p><a href="/quotes/index.php">Go to Quotations List</a></p>
</body>
</html>
