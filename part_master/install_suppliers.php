<?php
include "../db.php";

$messages = [];
$errors = [];

// Create Part Supplier Mapping Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS part_supplier_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_no VARCHAR(50) NOT NULL,
            supplier_id INT NOT NULL,
            supplier_sku VARCHAR(100),
            supplier_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            lead_time_days INT DEFAULT 5,
            min_order_qty INT DEFAULT 1,
            is_preferred TINYINT(1) DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_part_supplier (part_no, supplier_id),
            INDEX idx_part_no (part_no),
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'part_supplier_mapping' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating part_supplier_mapping: " . $e->getMessage();
}

// Create Part Min Stock Table (for reorder points)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS part_min_stock (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_no VARCHAR(50) NOT NULL UNIQUE,
            min_stock_qty INT DEFAULT 0,
            reorder_qty INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_part_no (part_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'part_min_stock' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating part_min_stock: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Part Supplier Setup - Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2ecc71;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #27ae60;
        }
        .btn-secondary {
            background: #3498db;
        }
        .btn-secondary:hover {
            background: #2980b9;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 5px;
        }
        .feature-list {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .feature-list h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .feature-list ul {
            margin-bottom: 0;
        }
        .feature-list li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Part Supplier Mapping - Installation</h1>

        <h3>Installation Results:</h3>

        <?php foreach ($messages as $msg): ?>
            <div class="success">✓ <?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="error">✗ <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <div class="summary">
                <strong>Installation Complete!</strong><br>
                The part supplier mapping tables have been created. You can now assign multiple suppliers to each part.
            </div>

            <div class="feature-list">
                <h3>Features Now Available:</h3>
                <ul>
                    <li><strong>Multiple Suppliers per Part</strong> - Assign different suppliers with unique pricing</li>
                    <li><strong>Supplier-Specific Rates</strong> - Track different costs from each supplier</li>
                    <li><strong>Lead Time Tracking</strong> - Set delivery lead times per supplier</li>
                    <li><strong>Minimum Order Quantities</strong> - Configure MOQ for each supplier</li>
                    <li><strong>Preferred Supplier</strong> - Mark your preferred supplier for each part</li>
                    <li><strong>Active/Inactive Status</strong> - Enable or disable supplier relationships</li>
                    <li><strong>Supplier SKU</strong> - Store supplier's internal part numbers</li>
                </ul>
            </div>

            <a href="list.php" class="btn">Go to Part Master</a>
            <a href="suppliers.php" class="btn btn-secondary">Manage Suppliers (select a part first)</a>
        <?php else: ?>
            <div class="summary" style="background: #fff3cd;">
                <strong>Some errors occurred.</strong><br>
                Please check the error messages above and try again.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
