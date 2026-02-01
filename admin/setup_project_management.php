<?php
/**
 * Setup script for Project Management module
 * Creates the base projects table and project tasks table
 */
include "../db.php";
include "../includes/header.php";
include "../includes/sidebar.php";

$messages = [];
$errors = [];

// =============================================
// 1. Create Projects Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'projects'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_no VARCHAR(30) UNIQUE NOT NULL,
                project_name VARCHAR(255) NOT NULL,
                project_type ENUM('New Product Development', 'Product Improvement', 'Cost Reduction', 'Quality Improvement', 'Process Improvement', 'Compliance', 'Other') DEFAULT 'New Product Development',
                design_phase ENUM('Concept', 'Preliminary Design', 'Detailed Design', 'Prototype', 'Testing', 'Production', 'Released') DEFAULT 'Concept',
                part_no VARCHAR(50),
                project_manager VARCHAR(100),
                project_engineer VARCHAR(100),
                customer_id VARCHAR(50),
                description TEXT,
                start_date DATE,
                end_date DATE,
                budget DECIMAL(15,2),
                status ENUM('Planning', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Planning',
                priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
                progress_percentage INT DEFAULT 0,
                created_by VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_project_no (project_no),
                INDEX idx_status (status),
                INDEX idx_customer (customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'projects' table successfully.";
    } else {
        $messages[] = "'projects' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create projects table: " . $e->getMessage();
}

// Add progress_percentage column to projects if missing
try {
    $pdo->query("SELECT progress_percentage FROM projects LIMIT 1");
    $messages[] = "'progress_percentage' column already exists in projects.";
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE projects ADD COLUMN progress_percentage INT DEFAULT 0 AFTER priority");
        $messages[] = "Added 'progress_percentage' column to projects table.";
    } catch (PDOException $e2) {
        $errors[] = "Failed to add progress_percentage column: " . $e2->getMessage();
    }
}

// =============================================
// 2. Create Project Tasks Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'project_tasks'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE project_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                task_name VARCHAR(255) NOT NULL,
                task_start_date DATE,
                task_end_date DATE,
                status ENUM('Pending', 'In Progress', 'Completed', 'On Hold', 'Cancelled') DEFAULT 'Pending',
                assigned_to VARCHAR(100),
                remark TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                INDEX idx_project (project_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'project_tasks' table successfully.";
    } else {
        $messages[] = "'project_tasks' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create project_tasks table: " . $e->getMessage();
}

// =============================================
// 2b. Create Project Activities Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'project_activities'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE project_activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                activity_type VARCHAR(100) NOT NULL,
                activity_description TEXT,
                assigned_to VARCHAR(100),
                due_date DATE,
                status ENUM('Pending', 'In Progress', 'Completed', 'On Hold', 'Cancelled') DEFAULT 'Pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'project_activities' table successfully.";
    } else {
        $messages[] = "'project_activities' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create project_activities table: " . $e->getMessage();
}

// =============================================
// 3. Create Project Milestones Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'project_milestones'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE project_milestones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                milestone_name VARCHAR(255) NOT NULL,
                description TEXT,
                target_date DATE NOT NULL,
                completion_date DATE,
                status ENUM('Pending', 'In Progress', 'Completed', 'Missed') DEFAULT 'Pending',
                sequence_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                INDEX idx_project (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'project_milestones' table successfully.";
    } else {
        $messages[] = "'project_milestones' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create project_milestones table: " . $e->getMessage();
}

// Add description column to project_milestones if missing
try {
    $pdo->query("SELECT description FROM project_milestones LIMIT 1");
    $messages[] = "'description' column already exists in project_milestones.";
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE project_milestones ADD COLUMN description TEXT AFTER milestone_name");
        $messages[] = "Added 'description' column to project_milestones table.";
    } catch (PDOException $e2) {
        $errors[] = "Failed to add description column: " . $e2->getMessage();
    }
}

// =============================================
// 4. Create Project Documents Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'project_documents'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE project_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                document_type VARCHAR(100),
                file_path VARCHAR(500),
                file_size INT,
                version VARCHAR(20) DEFAULT '1.0',
                uploaded_by VARCHAR(100),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'project_documents' table successfully.";
    } else {
        $messages[] = "'project_documents' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create project_documents table: " . $e->getMessage();
}

// =============================================
// 5. Create Milestone Documents Table
// =============================================
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'milestone_documents'");
    if ($checkTable->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE milestone_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                milestone_id INT NOT NULL,
                project_id INT NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                document_type VARCHAR(100),
                file_path VARCHAR(500),
                file_size INT,
                uploaded_by VARCHAR(100),
                remarks TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (milestone_id) REFERENCES project_milestones(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                INDEX idx_milestone (milestone_id),
                INDEX idx_project (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created 'milestone_documents' table successfully.";
    } else {
        $messages[] = "'milestone_documents' table already exists.";
    }
} catch (PDOException $e) {
    $errors[] = "Failed to create milestone_documents table: " . $e->getMessage();
}

// Create upload directory for project documents
$uploadDir = dirname(__DIR__) . '/uploads/project_documents';
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        $messages[] = "Created upload directory: /uploads/project_documents";
    } else {
        $errors[] = "Failed to create upload directory.";
    }
} else {
    $messages[] = "Upload directory already exists.";
}

// Create upload directory for milestone documents
$milestoneUploadDir = dirname(__DIR__) . '/uploads/milestone_documents';
if (!file_exists($milestoneUploadDir)) {
    if (mkdir($milestoneUploadDir, 0755, true)) {
        $messages[] = "Created upload directory: /uploads/milestone_documents";
    } else {
        $errors[] = "Failed to create milestone documents upload directory.";
    }
} else {
    $messages[] = "Milestone documents upload directory already exists.";
}
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Setup Project Management Module</h1>

    <p style="margin-bottom: 20px;">
        <a href="settings.php" class="btn btn-secondary">Back to Admin</a>
    </p>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Setup Complete:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3>Project Management Module Features</h3>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background: #667eea; color: white;">
                    <th style="padding: 12px; text-align: left;">Table</th>
                    <th style="padding: 12px; text-align: left;">Purpose</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><strong>projects</strong></td>
                    <td style="padding: 12px;">Main project records with status, dates, budget, and assignments</td>
                </tr>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><strong>project_tasks</strong></td>
                    <td style="padding: 12px;">Tasks within each project with assignments and progress tracking</td>
                </tr>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><strong>project_milestones</strong></td>
                    <td style="padding: 12px;">Key milestones and checkpoints for project progress</td>
                </tr>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><strong>project_documents</strong></td>
                    <td style="padding: 12px;">Document attachments for each project</td>
                </tr>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 12px;"><strong>milestone_documents</strong></td>
                    <td style="padding: 12px;">Document attachments for project milestones</td>
                </tr>
            </tbody>
        </table>

        <h4>Project Number Format</h4>
        <p>Projects are automatically assigned numbers in the format: <strong>PROJ-0001</strong>, <strong>PROJ-0002</strong>, etc.</p>

        <h4 style="margin-top: 20px;">Next Steps:</h4>
        <ol>
            <li>Run <a href="/project_management/setup_product_engineering.php">Product Engineering Setup</a> for Engineering Reviews and ECO features</li>
            <li>Go to <a href="/project_management/index.php">Projects List</a> to view all projects</li>
            <li>Go to <a href="/project_management/add.php">Add Project</a> to create a new project</li>
        </ol>

        <p style="margin-top: 20px;">
            <a href="/project_management/index.php" class="btn btn-primary">Go to Projects</a>
            <a href="/project_management/add.php" class="btn btn-primary" style="margin-left: 10px;">Add New Project</a>
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
