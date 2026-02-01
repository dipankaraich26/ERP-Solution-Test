<?php
include "../db.php";

$messages = [];
$errors = [];

// Create Task Categories Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS task_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            category_code VARCHAR(20) NOT NULL UNIQUE,
            color_code VARCHAR(7) DEFAULT '#3498db',
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'task_categories' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating task_categories: " . $e->getMessage();
}

// Insert default categories
try {
    $pdo->exec("
        INSERT IGNORE INTO task_categories (category_name, category_code, color_code, description) VALUES
        ('General', 'GEN', '#95a5a6', 'General tasks not specific to any department'),
        ('Sales', 'SALES', '#e74c3c', 'Sales and CRM related tasks'),
        ('Marketing', 'MKT', '#f39c12', 'Marketing campaigns and activities'),
        ('HR', 'HR', '#9b59b6', 'Human resources related tasks'),
        ('Operations', 'OPS', '#1abc9c', 'Operations and manufacturing tasks'),
        ('Purchase', 'PUR', '#3498db', 'Purchase and procurement tasks'),
        ('Inventory', 'INV', '#2ecc71', 'Inventory management tasks'),
        ('Service', 'SVC', '#e67e22', 'Customer service and support tasks'),
        ('Finance', 'FIN', '#34495e', 'Finance and accounting tasks'),
        ('IT', 'IT', '#8e44ad', 'IT and technical tasks'),
        ('Admin', 'ADMIN', '#7f8c8d', 'Administrative tasks')
    ");
    $messages[] = "Default categories inserted";
} catch (PDOException $e) {
    $errors[] = "Error inserting categories: " . $e->getMessage();
}

// Create Main Tasks Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_no VARCHAR(20) NOT NULL UNIQUE,
            task_name VARCHAR(255) NOT NULL,
            task_description TEXT,
            category_id INT,
            priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
            status ENUM('Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Not Started',
            assigned_to INT NULL,
            assigned_by INT NULL,
            start_date DATE,
            due_date DATE,
            completed_date DATE,
            progress_percent INT DEFAULT 0,
            related_module VARCHAR(50),
            related_id INT,
            related_reference VARCHAR(100),
            customer_id INT NULL,
            project_id INT NULL,
            estimated_hours DECIMAL(8,2),
            actual_hours DECIMAL(8,2),
            remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_task_status (status),
            INDEX idx_task_priority (priority),
            INDEX idx_task_assigned (assigned_to),
            INDEX idx_task_due_date (due_date),
            INDEX idx_task_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'tasks' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating tasks: " . $e->getMessage();
}

// Create Task Comments Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS task_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            comment TEXT NOT NULL,
            commented_by INT NULL,
            comment_type ENUM('comment', 'status_change', 'progress_update', 'assignment') DEFAULT 'comment',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_comments (task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'task_comments' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating task_comments: " . $e->getMessage();
}

// Create Task Attachments Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS task_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100),
            file_size INT,
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_attachments (task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'task_attachments' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating task_attachments: " . $e->getMessage();
}

// Create Task Checklist Table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS task_checklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            item_text VARCHAR(255) NOT NULL,
            is_completed TINYINT(1) DEFAULT 0,
            completed_at DATETIME,
            completed_by INT,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task_checklist (task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'task_checklist' created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating task_checklist: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Task Management - Installation</title>
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
            color: #667eea;
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
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5a6fd6;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Task Management Module - Installation</h1>

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
                All tables have been created successfully. You can now use the Task Management module.
            </div>
            <a href="dashboard.php" class="btn">Go to Task Dashboard</a>
        <?php else: ?>
            <div class="summary" style="background: #fff3cd;">
                <strong>Some errors occurred.</strong><br>
                Please check the error messages above and try again.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
