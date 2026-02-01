<?php
include "../db.php";

$messages = [];
$errors = [];

// Create Installations Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS installations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            installation_no VARCHAR(20) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            installation_date DATE NOT NULL,
            installation_time TIME,
            engineer_type ENUM('internal', 'external') DEFAULT 'internal',
            engineer_id INT NULL COMMENT 'Reference to employees table if internal',
            external_engineer_name VARCHAR(100) NULL COMMENT 'Name if external engineer',
            external_engineer_phone VARCHAR(20) NULL,
            external_engineer_company VARCHAR(100) NULL,
            site_address TEXT,
            site_contact_person VARCHAR(100),
            site_contact_phone VARCHAR(20),
            product_details TEXT COMMENT 'Products/parts installed',
            installation_notes TEXT,
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'scheduled',
            completion_date DATE NULL,
            customer_signature TINYINT(1) DEFAULT 0,
            customer_feedback TEXT,
            rating INT NULL COMMENT '1-5 star rating',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_engineer (engineer_id),
            INDEX idx_status (status),
            INDEX idx_date (installation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'installations' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating installations: " . $e->getMessage();
}

// Create Installation Attachments Table (for reports, photos, documents)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS installation_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            installation_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            attachment_type ENUM('report', 'photo', 'document', 'signature', 'other') DEFAULT 'document',
            description VARCHAR(255),
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_installation (installation_id),
            FOREIGN KEY (installation_id) REFERENCES installations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'installation_attachments' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating installation_attachments: " . $e->getMessage();
}

// Create Installation Products Table (items installed)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS installation_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            installation_id INT NOT NULL,
            part_no VARCHAR(50),
            product_name VARCHAR(255) NOT NULL,
            serial_number VARCHAR(100),
            quantity INT DEFAULT 1,
            warranty_months INT DEFAULT 12,
            warranty_end_date DATE,
            notes TEXT,
            INDEX idx_installation (installation_id),
            FOREIGN KEY (installation_id) REFERENCES installations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'installation_products' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating installation_products: " . $e->getMessage();
}

// Create uploads directory for installation files
$uploadDir = '../uploads/installations';
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        $messages[] = "Upload directory created: uploads/installations";
    } else {
        $errors[] = "Failed to create upload directory";
    }
} else {
    $messages[] = "Upload directory already exists";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Installation Module - Setup</title>
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
            color: #e74c3c;
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
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #c0392b;
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
            background: #e8f5e9;
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
        <h1>Installation Module - Setup</h1>

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
                The Installation module has been set up successfully.
            </div>

            <div class="feature-list">
                <h3>Features Available:</h3>
                <ul>
                    <li><strong>Customer Integration</strong> - Pull customer details from customer database</li>
                    <li><strong>Engineer Assignment</strong> - Assign internal employees or external engineers</li>
                    <li><strong>Installation Scheduling</strong> - Set installation date and time</li>
                    <li><strong>Product Tracking</strong> - Track products/parts installed with serial numbers</li>
                    <li><strong>Report Attachments</strong> - Upload installation reports, photos, and documents</li>
                    <li><strong>Status Tracking</strong> - Track installation status (scheduled, in progress, completed)</li>
                    <li><strong>Warranty Management</strong> - Track warranty periods for installed products</li>
                    <li><strong>Customer Feedback</strong> - Capture customer signature and feedback</li>
                </ul>
            </div>

            <a href="index.php" class="btn">Go to Installations</a>
            <a href="add.php" class="btn btn-secondary">Add New Installation</a>
        <?php else: ?>
            <div class="summary" style="background: #fff3cd;">
                <strong>Some errors occurred.</strong><br>
                Please check the error messages above and try again.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
