<?php
/**
 * Quality Issues Module - Database Setup
 * Creates tables for tracking Field and Internal quality issues with action items
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // 1. Quality Issues Master Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_quality_issues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_no VARCHAR(50) NOT NULL UNIQUE,
            issue_type ENUM('Field Issue', 'Internal Issue', 'Customer Complaint', 'Supplier Issue', 'Process Issue') NOT NULL,
            issue_source ENUM('Customer', 'Internal Inspection', 'Production', 'Warehouse', 'Shipping', 'Installation', 'Service', 'Audit', 'Other') DEFAULT 'Internal Inspection',

            -- Issue Details
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category ENUM('Dimensional', 'Visual', 'Functional', 'Material', 'Packaging', 'Documentation', 'Process', 'Safety', 'Other') DEFAULT 'Other',

            -- References
            part_no VARCHAR(100),
            lot_no VARCHAR(100),
            serial_no VARCHAR(100),
            work_order_no VARCHAR(100),
            customer_id INT,
            customer_name VARCHAR(255),
            supplier_id INT,
            supplier_name VARCHAR(255),
            project_id INT,

            -- Location and Detection
            location VARCHAR(255) COMMENT 'Where issue was found',
            detection_stage ENUM('Incoming', 'In-Process', 'Final Inspection', 'Packing', 'Shipping', 'Installation', 'Field', 'Customer Use') DEFAULT 'In-Process',

            -- Quantity
            qty_affected INT DEFAULT 0,
            qty_scrapped INT DEFAULT 0,
            qty_reworked INT DEFAULT 0,

            -- Priority and Severity
            priority ENUM('Critical', 'High', 'Medium', 'Low') DEFAULT 'Medium',
            severity ENUM('Critical', 'Major', 'Minor', 'Observation') DEFAULT 'Major',

            -- Cost Impact
            cost_impact DECIMAL(12,2) DEFAULT 0,
            cost_of_quality DECIMAL(12,2) DEFAULT 0 COMMENT 'Total cost including rework, scrap, customer claims',

            -- Dates
            issue_date DATE NOT NULL,
            target_closure_date DATE,
            actual_closure_date DATE,

            -- Assignment
            reported_by VARCHAR(100),
            reported_by_id INT,
            assigned_to VARCHAR(100),
            assigned_to_id INT,
            department VARCHAR(100),

            -- Status Workflow
            status ENUM('Open', 'Analysis', 'Action Required', 'In Progress', 'Verification', 'Closed', 'Cancelled') DEFAULT 'Open',

            -- Root Cause Analysis
            root_cause TEXT,
            root_cause_category ENUM('Man', 'Machine', 'Method', 'Material', 'Measurement', 'Environment', 'Other'),
            why_analysis TEXT COMMENT '5 Why analysis',

            -- Containment
            containment_action TEXT,
            containment_date DATE,
            containment_verified TINYINT(1) DEFAULT 0,

            -- Linked Issues
            parent_issue_id INT,
            related_ncr_no VARCHAR(50),

            -- Audit
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_by INT,

            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_issue_type (issue_type),
            INDEX idx_issue_date (issue_date),
            INDEX idx_assigned_to (assigned_to_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: qc_quality_issues";

    // Add supplier columns if they don't exist (for existing installations)
    try {
        $pdo->query("SELECT supplier_id FROM qc_quality_issues LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE qc_quality_issues ADD COLUMN supplier_id INT AFTER customer_name");
        $pdo->exec("ALTER TABLE qc_quality_issues ADD COLUMN supplier_name VARCHAR(255) AFTER supplier_id");
        $messages[] = "Added supplier columns to qc_quality_issues";
    }

    // 2. Action Items Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_issue_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            action_no INT NOT NULL,

            -- Action Details
            action_type ENUM('Containment', 'Corrective', 'Preventive', 'Verification', 'Investigation', 'Other') NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,

            -- Assignment
            assigned_to VARCHAR(100),
            assigned_to_id INT,
            department VARCHAR(100),

            -- Priority
            priority ENUM('Critical', 'High', 'Medium', 'Low') DEFAULT 'Medium',

            -- Timeline
            start_date DATE,
            target_date DATE NOT NULL,
            actual_completion_date DATE,

            -- Status
            status ENUM('Pending', 'In Progress', 'Completed', 'Verified', 'Overdue', 'Cancelled') DEFAULT 'Pending',
            completion_percentage INT DEFAULT 0,

            -- Verification
            verification_required TINYINT(1) DEFAULT 1,
            verified_by VARCHAR(100),
            verification_date DATE,
            verification_remarks TEXT,

            -- Notes
            remarks TEXT,
            evidence_attached TINYINT(1) DEFAULT 0,

            -- Audit
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            FOREIGN KEY (issue_id) REFERENCES qc_quality_issues(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_target_date (target_date),
            INDEX idx_assigned_to (assigned_to_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: qc_issue_actions";

    // 3. Issue Comments/Updates Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_issue_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            comment_type ENUM('Update', 'Status Change', 'Escalation', 'Note', 'Question', 'Answer') DEFAULT 'Update',
            comment TEXT NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50),
            created_by VARCHAR(100),
            created_by_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (issue_id) REFERENCES qc_quality_issues(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: qc_issue_comments";

    // 4. Issue Attachments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_issue_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            action_id INT,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            file_path VARCHAR(500),
            description VARCHAR(255),
            uploaded_by VARCHAR(100),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (issue_id) REFERENCES qc_quality_issues(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: qc_issue_attachments";

    // 5. Issue Categories Master (for quick selection)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_issue_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            category_type ENUM('Field', 'Internal', 'Both') DEFAULT 'Both',
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: qc_issue_categories";

    // Insert default categories
    $pdo->exec("
        INSERT IGNORE INTO qc_issue_categories (category_name, category_type, sort_order) VALUES
        ('Dimensional Out of Spec', 'Both', 1),
        ('Surface Defect', 'Both', 2),
        ('Functional Failure', 'Both', 3),
        ('Material Defect', 'Both', 4),
        ('Assembly Error', 'Internal', 5),
        ('Packaging Damage', 'Both', 6),
        ('Missing Parts', 'Both', 7),
        ('Wrong Part', 'Both', 8),
        ('Documentation Error', 'Both', 9),
        ('Installation Issue', 'Field', 10),
        ('Performance Issue', 'Field', 11),
        ('Premature Failure', 'Field', 12),
        ('Noise/Vibration', 'Field', 13),
        ('Cosmetic Issue', 'Both', 14),
        ('Process Deviation', 'Internal', 15)
    ");
    $messages[] = "Inserted default issue categories";

    // Create upload directory
    $uploadDir = dirname(__DIR__) . '/uploads/qc_issues';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            $messages[] = "Created upload directory: /uploads/qc_issues";
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
    <title>Setup Quality Issues Module</title>
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
    <h1>Quality Issues Module Setup</h1>
    <a href="issues.php" class="btn btn-secondary" style="margin-bottom: 20px;">Go to Quality Issues</a>

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
            <h4>Quality Issues Module Features</h4>
            <ul>
                <li><strong>Issue Types:</strong> Field Issues, Internal Issues, Customer Complaints, Supplier Issues, Process Issues</li>
                <li><strong>Priority Levels:</strong> Critical, High, Medium, Low</li>
                <li><strong>Severity Levels:</strong> Critical, Major, Minor, Observation</li>
                <li><strong>Action Items:</strong> Containment, Corrective, Preventive, Verification actions</li>
                <li><strong>Root Cause Analysis:</strong> 5-Why analysis, Ishikawa categories</li>
                <li><strong>Timeline Tracking:</strong> Start date, target date, completion date</li>
                <li><strong>Assignment:</strong> Assign to specific employees with department tracking</li>
                <li><strong>Status Workflow:</strong> Open → Analysis → Action Required → In Progress → Verification → Closed</li>
                <li><strong>Cost Tracking:</strong> Cost impact, cost of quality</li>
                <li><strong>Attachments:</strong> Upload evidence, photos, documents</li>
            </ul>
        </div>

        <p style="margin-top: 20px;">
            <a href="issues.php" class="btn btn-primary">Go to Quality Issues</a>
            <a href="issue_add.php" class="btn btn-primary" style="margin-left: 10px;">Add New Issue</a>
        </p>
    </div>
</div>

</body>
</html>
