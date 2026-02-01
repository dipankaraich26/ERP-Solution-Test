<?php
/**
 * Sales Order Release Checklist - Database Setup
 * Creates tables for release checklist items and attachments
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // 1. SO Release Checklist Master Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_release_checklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            so_no VARCHAR(50) NOT NULL,

            -- Machine Performance
            machine_performance_ok TINYINT(1) DEFAULT 0,
            machine_performance_remarks TEXT,

            -- Functional Performance
            functional_performance_ok TINYINT(1) DEFAULT 0,
            functional_performance_remarks TEXT,

            -- Quality Check Points
            quality_visual_inspection TINYINT(1) DEFAULT 0,
            quality_dimensional_check TINYINT(1) DEFAULT 0,
            quality_safety_check TINYINT(1) DEFAULT 0,
            quality_packaging_ok TINYINT(1) DEFAULT 0,
            quality_remarks TEXT,

            -- Government Compliance
            govt_compliance_checked TINYINT(1) DEFAULT 0,
            govt_compliance_remarks TEXT,

            -- Overall Status
            checklist_completed TINYINT(1) DEFAULT 0,
            completed_by INT,
            completed_by_name VARCHAR(100),
            completed_at DATETIME,

            -- Audit
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY idx_so_no (so_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: so_release_checklist";

    // 2. Checklist Attachments Table (for government docs, test reports, etc.)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_release_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            so_no VARCHAR(50) NOT NULL,
            attachment_type ENUM('Test Report', 'Quality Certificate', 'Government Document', 'Inspection Report', 'Warranty Card', 'User Manual', 'Calibration Certificate', 'Packing List', 'Other') NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            file_path VARCHAR(500),
            description VARCHAR(255),
            uploaded_by VARCHAR(100),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_so_no (so_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: so_release_attachments";

    // Add new attachment types for existing installations
    try {
        $pdo->exec("ALTER TABLE so_release_attachments MODIFY COLUMN attachment_type ENUM('Test Report', 'Quality Certificate', 'Government Document', 'Inspection Report', 'Warranty Card', 'User Manual', 'Calibration Certificate', 'Packing List', 'Other') NOT NULL");
        $messages[] = "Updated attachment types in so_release_attachments";
    } catch (PDOException $e) {
        // Column already has correct types or table doesn't exist yet
    }

    // 3. Checklist Items Master (configurable checklist items)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category ENUM('Machine Performance', 'Functional Performance', 'Quality Check', 'Government Compliance', 'Other') NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            is_mandatory TINYINT(1) DEFAULT 1,
            requires_attachment TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: so_checklist_items";

    // Insert default checklist items
    $pdo->exec("
        INSERT IGNORE INTO so_checklist_items (category, item_name, description, is_mandatory, requires_attachment, sort_order) VALUES
        ('Machine Performance', 'Overall Machine Performance Test', 'Verify all machine functions are working as per specifications', 1, 0, 1),
        ('Machine Performance', 'Noise Level Check', 'Ensure machine noise is within acceptable limits', 1, 0, 2),
        ('Machine Performance', 'Vibration Test', 'Check for abnormal vibrations during operation', 1, 0, 3),
        ('Functional Performance', 'Operational Test', 'Complete operational cycle test performed', 1, 0, 4),
        ('Functional Performance', 'Safety Features Test', 'All safety interlocks and features verified', 1, 0, 5),
        ('Functional Performance', 'Performance Parameters', 'Output/performance parameters meet specifications', 1, 0, 6),
        ('Quality Check', 'Visual Inspection', 'No visible defects, scratches, or damage', 1, 0, 7),
        ('Quality Check', 'Dimensional Verification', 'Critical dimensions verified against drawings', 1, 0, 8),
        ('Quality Check', 'Packaging Inspection', 'Packaging is adequate for safe transportation', 1, 0, 9),
        ('Quality Check', 'Documentation Complete', 'All required documents included (manual, warranty, etc.)', 1, 0, 10),
        ('Government Compliance', 'BIS Certificate', 'Bureau of Indian Standards certification if applicable', 0, 1, 11),
        ('Government Compliance', 'CE Marking', 'CE marking verification for export orders', 0, 1, 12),
        ('Government Compliance', 'Test Certificate', 'Factory test certificate attached', 1, 1, 13),
        ('Government Compliance', 'Warranty Card', 'Warranty card prepared and included', 1, 0, 14)
    ");
    $messages[] = "Inserted default checklist items";

    // Create upload directory
    $uploadDir = dirname(__DIR__) . '/uploads/so_release';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $messages[] = "Created upload directory: /uploads/so_release";
        }
    }

} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup SO Release Checklist</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .setup-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 900px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .message-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 5px 0;
        }
        .message-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 5px 0;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .feature-list h4 { margin-top: 0; color: #667eea; }
        .feature-list ul { margin: 0; padding-left: 20px; }
        .feature-list li { margin: 8px 0; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>SO Release Checklist Setup</h1>
    <a href="../sales_orders/index.php" class="btn btn-secondary" style="margin-bottom: 20px;">Go to Sales Orders</a>

    <div class="setup-container">
        <h3>Setup Results</h3>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="message-error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="feature-list">
            <h4>Release Checklist Features</h4>
            <ul>
                <li><strong>Machine Performance:</strong> Overall performance test, noise level, vibration check</li>
                <li><strong>Functional Performance:</strong> Operational test, safety features, performance parameters</li>
                <li><strong>Quality Check Points:</strong> Visual inspection, dimensional verification, packaging, documentation</li>
                <li><strong>Government Compliance:</strong> BIS Certificate, CE Marking, Test Certificate, Warranty Card</li>
                <li><strong>Attachments:</strong> Upload government documents, test reports, certificates</li>
                <li><strong>Mandatory vs Optional:</strong> Some items are mandatory for release, others optional</li>
            </ul>
        </div>

        <p style="margin-top: 20px;">
            <a href="../sales_orders/index.php" class="btn btn-primary">Go to Sales Orders</a>
        </p>
    </div>
</div>

</body>
</html>
