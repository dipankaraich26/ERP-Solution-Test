<?php
/**
 * Setup script for Product Engineering module
 * Creates tables for Engineering Reviews and Change Requests (ECO)
 */
include "../db.php";

echo "<h2>Product Engineering Module Setup</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
    .success { color: green; }
    .info { color: blue; }
    .error { color: red; }
    table { border-collapse: collapse; margin: 20px 0; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f5f5f5; }
</style>";

try {
    // =============================================
    // 1. Engineering Reviews Table
    // =============================================
    $checkTable = $pdo->query("SHOW TABLES LIKE 'engineering_reviews'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE engineering_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_no VARCHAR(30) UNIQUE NOT NULL,
                project_id INT,
                review_type ENUM('Concept Review', 'Preliminary Design Review', 'Critical Design Review', 'Production Readiness Review', 'Post-Production Review', 'Other') NOT NULL,
                review_title VARCHAR(255) NOT NULL,
                review_date DATE NOT NULL,
                review_location VARCHAR(255),
                review_leader VARCHAR(100),

                -- Status and outcome
                status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
                outcome ENUM('Approved', 'Approved with Comments', 'Conditional Approval', 'Not Approved', 'Pending') DEFAULT 'Pending',

                -- Description and scope
                description TEXT,
                objectives TEXT,
                scope TEXT,

                -- Participants (JSON array)
                participants TEXT,

                -- Summary and notes
                summary TEXT,
                key_decisions TEXT,
                notes TEXT,

                -- Next steps
                next_review_date DATE,
                next_review_type VARCHAR(50),

                -- Audit fields
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Created 'engineering_reviews' table</p>";
    } else {
        echo "<p class='info'>ℹ 'engineering_reviews' table already exists</p>";
    }

    // =============================================
    // 2. Review Findings / Action Items Table
    // =============================================
    $checkTable = $pdo->query("SHOW TABLES LIKE 'review_findings'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE review_findings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_id INT NOT NULL,
                finding_no VARCHAR(30) NOT NULL,

                -- Finding details
                finding_type ENUM('Action Item', 'Observation', 'Concern', 'Risk', 'Recommendation') DEFAULT 'Action Item',
                severity ENUM('Critical', 'Major', 'Minor', 'Observation') DEFAULT 'Minor',
                category VARCHAR(100),

                -- Description
                title VARCHAR(255) NOT NULL,
                description TEXT,

                -- Assignment
                assigned_to VARCHAR(100),
                assigned_user_id INT,
                due_date DATE,

                -- Resolution
                status ENUM('Open', 'In Progress', 'Resolved', 'Verified', 'Closed', 'Cancelled') DEFAULT 'Open',
                resolution TEXT,
                resolution_date DATE,
                verified_by VARCHAR(100),
                verification_date DATE,

                -- Audit
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (review_id) REFERENCES engineering_reviews(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Created 'review_findings' table</p>";
    } else {
        echo "<p class='info'>ℹ 'review_findings' table already exists</p>";
    }

    // =============================================
    // 3. Engineering Change Requests (ECO) Table
    // =============================================
    $checkTable = $pdo->query("SHOW TABLES LIKE 'change_requests'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE change_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                eco_no VARCHAR(30) UNIQUE NOT NULL,
                project_id INT,

                -- Change request details
                title VARCHAR(255) NOT NULL,
                change_type ENUM('Design Change', 'Material Change', 'Process Change', 'Document Change', 'Supplier Change', 'Specification Change', 'Other') NOT NULL,
                priority ENUM('Critical', 'High', 'Medium', 'Low') DEFAULT 'Medium',

                -- Reason and description
                reason_for_change TEXT NOT NULL,
                description TEXT,
                current_state TEXT,
                proposed_change TEXT,

                -- Impact analysis
                impact_quality TEXT,
                impact_cost TEXT,
                impact_schedule TEXT,
                impact_other TEXT,
                estimated_cost DECIMAL(12,2),

                -- Status and workflow
                status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Implemented', 'Verified', 'Closed', 'Cancelled') DEFAULT 'Draft',

                -- Requestor info
                requested_by VARCHAR(100),
                requested_user_id INT,
                request_date DATE,

                -- Approval info
                reviewed_by VARCHAR(100),
                review_date DATE,
                review_comments TEXT,
                approved_by VARCHAR(100),
                approval_date DATE,
                approval_comments TEXT,

                -- Implementation
                implementation_plan TEXT,
                implementation_date DATE,
                implemented_by VARCHAR(100),

                -- Verification
                verification_required TINYINT(1) DEFAULT 1,
                verified_by VARCHAR(100),
                verification_date DATE,
                verification_notes TEXT,

                -- Closure
                closure_date DATE,
                closure_notes TEXT,

                -- Audit
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Created 'change_requests' table</p>";
    } else {
        echo "<p class='info'>ℹ 'change_requests' table already exists</p>";
    }

    // =============================================
    // 4. Affected Parts for Change Requests
    // =============================================
    $checkTable = $pdo->query("SHOW TABLES LIKE 'eco_affected_parts'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE eco_affected_parts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                eco_id INT NOT NULL,
                part_no VARCHAR(50) NOT NULL,
                part_description VARCHAR(255),
                current_revision VARCHAR(20),
                new_revision VARCHAR(20),
                change_description TEXT,
                disposition ENUM('Use As Is', 'Rework', 'Scrap', 'Return to Supplier', 'Other') DEFAULT 'Use As Is',
                stock_impact TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (eco_id) REFERENCES change_requests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Created 'eco_affected_parts' table</p>";
    } else {
        echo "<p class='info'>ℹ 'eco_affected_parts' table already exists</p>";
    }

    // =============================================
    // 5. ECO Approval Workflow
    // =============================================
    $checkTable = $pdo->query("SHOW TABLES LIKE 'eco_approvals'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE eco_approvals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                eco_id INT NOT NULL,
                approver_role VARCHAR(100) NOT NULL,
                approver_name VARCHAR(100),
                approver_user_id INT,
                sequence_order INT DEFAULT 1,
                status ENUM('Pending', 'Approved', 'Rejected', 'Skipped') DEFAULT 'Pending',
                comments TEXT,
                action_date DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (eco_id) REFERENCES change_requests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p class='success'>✓ Created 'eco_approvals' table</p>";
    } else {
        echo "<p class='info'>ℹ 'eco_approvals' table already exists</p>";
    }

    // =============================================
    // 6. Update Projects table for Product Engineering
    // =============================================
    // Add project_type column if not exists
    try {
        $pdo->query("SELECT project_type FROM projects LIMIT 1");
        echo "<p class='info'>ℹ 'project_type' column already exists in projects table</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN project_type ENUM('New Product Development', 'Product Improvement', 'Cost Reduction', 'Quality Improvement', 'Process Improvement', 'Compliance', 'Other') DEFAULT 'New Product Development' AFTER project_name");
        echo "<p class='success'>✓ Added 'project_type' column to projects table</p>";
    }

    // Add design_phase column if not exists
    try {
        $pdo->query("SELECT design_phase FROM projects LIMIT 1");
        echo "<p class='info'>ℹ 'design_phase' column already exists in projects table</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN design_phase ENUM('Concept', 'Preliminary Design', 'Detailed Design', 'Prototype', 'Testing', 'Production', 'Released') DEFAULT 'Concept' AFTER project_type");
        echo "<p class='success'>✓ Added 'design_phase' column to projects table</p>";
    }

    // Add part_no column if not exists (for product-specific projects)
    try {
        $pdo->query("SELECT part_no FROM projects LIMIT 1");
        echo "<p class='info'>ℹ 'part_no' column already exists in projects table</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN part_no VARCHAR(50) AFTER design_phase");
        echo "<p class='success'>✓ Added 'part_no' column to projects table</p>";
    }

    echo "<hr>";
    echo "<h3>Setup Complete!</h3>";
    echo "<p>The Product Engineering module is now ready to use.</p>";

    echo "<h4>Module Features:</h4>";
    echo "<table>";
    echo "<tr><th>Feature</th><th>Description</th></tr>";
    echo "<tr><td><strong>Projects</strong></td><td>Engineering projects with phases and milestones</td></tr>";
    echo "<tr><td><strong>Engineering Reviews</strong></td><td>Concept, PDR, CDR, PRR reviews with findings tracking</td></tr>";
    echo "<tr><td><strong>Change Requests (ECO)</strong></td><td>Engineering Change Orders with approval workflow</td></tr>";
    echo "</table>";

    echo "<p style='margin-top: 20px;'>";
    echo "<a href='/project_management/dashboard.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Product Engineering Dashboard</a>";
    echo "</p>";

} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>
