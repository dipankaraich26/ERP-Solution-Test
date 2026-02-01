<?php
include "../db.php";
include "../includes/dialog.php";

$messages = [];
$errors = [];

try {
    // Create payment_terms table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_terms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            term_name VARCHAR(100) NOT NULL,
            term_description TEXT,
            days INT DEFAULT 0 COMMENT 'Payment due in X days',
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created/verified payment_terms table";

    // Check if table is empty and add default terms
    $count = $pdo->query("SELECT COUNT(*) FROM payment_terms")->fetchColumn();

    if ($count == 0) {
        // Insert default payment terms
        $defaultTerms = [
            ['100% Advance Payment', '100% payment to be made before dispatch of goods', 0, 1, 1],
            ['50% Advance, 50% Before Dispatch', '50% advance with order, remaining 50% before dispatch', 0, 0, 2],
            ['30% Advance, 70% on Delivery', '30% advance payment, 70% on delivery', 0, 0, 3],
            ['Net 15 Days', 'Payment due within 15 days from invoice date', 15, 0, 4],
            ['Net 30 Days', 'Payment due within 30 days from invoice date', 30, 0, 5],
            ['Net 45 Days', 'Payment due within 45 days from invoice date', 45, 0, 6],
            ['Net 60 Days', 'Payment due within 60 days from invoice date', 60, 0, 7],
            ['Against Delivery', 'Full payment at the time of delivery', 0, 0, 8],
            ['LC at Sight', 'Letter of Credit payable at sight', 0, 0, 9],
            ['LC 30 Days', 'Letter of Credit with 30 days credit', 30, 0, 10],
            ['LC 60 Days', 'Letter of Credit with 60 days credit', 60, 0, 11],
            ['LC 90 Days', 'Letter of Credit with 90 days credit', 90, 0, 12],
        ];

        $insertStmt = $pdo->prepare("
            INSERT INTO payment_terms (term_name, term_description, days, is_default, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($defaultTerms as $term) {
            $insertStmt->execute($term);
        }
        $messages[] = "Added " . count($defaultTerms) . " default payment terms";
    } else {
        $messages[] = "Payment terms table already has data ($count terms)";
    }

    // Add payment_terms_id column to quote_master if not exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM quote_master LIKE 'payment_terms_id'")->fetch();
    if (!$checkColumn) {
        $pdo->exec("ALTER TABLE quote_master ADD COLUMN payment_terms_id INT NULL AFTER payment_details");
        $messages[] = "Added payment_terms_id column to quote_master";
    } else {
        $messages[] = "payment_terms_id column already exists in quote_master";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Terms Setup</title>
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
    <h1>Payment Terms Setup</h1>
    <p>This setup creates the payment terms table and adds default options.</p>

    <h3>Setup Results:</h3>

    <?php foreach ($messages as $msg): ?>
        <div class="success"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
        <div class="error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
        <div class="info">
            <strong>Setup Complete!</strong><br>
            Payment terms can now be managed in Company Settings.<br>
            <br>
            <strong>Features enabled:</strong>
            <ul>
                <li>Multiple payment terms options</li>
                <li>Select payment terms in Quotations</li>
                <li>Payment terms displayed on Proforma Invoices</li>
                <li>Manage payment terms from Company Settings</li>
            </ul>
        </div>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="settings.php" class="btn btn-primary">Go to Company Settings</a>
        <a href="/" class="btn btn-secondary">Back to Dashboard</a>
    </p>
</div>
</body>
</html>
