<?php
/**
 * Setup script for Leave Management System
 * Creates tables: leave_types, leave_balances, leave_requests
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // 1. Create or update leave_types table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();

    if (!$tableCheck) {
        // Create new table
        $pdo->exec("
            CREATE TABLE leave_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                leave_type_name VARCHAR(100) NOT NULL,
                leave_code VARCHAR(20) NOT NULL UNIQUE,
                max_days_per_year INT DEFAULT 0 COMMENT '0 = unlimited',
                is_paid TINYINT(1) DEFAULT 1,
                requires_approval TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_code (leave_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created leave_types table";
    } else {
        // Table exists - check and add missing columns
        $columns = $pdo->query("SHOW COLUMNS FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);

        // Check for essential columns first
        if (!in_array('leave_type_name', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN leave_type_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id");
            $messages[] = "Added leave_type_name column to leave_types";
        }
        if (!in_array('leave_code', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN leave_code VARCHAR(20) NOT NULL DEFAULT '' AFTER leave_type_name");
            $messages[] = "Added leave_code column to leave_types";
            // Try to add unique index
            try {
                $pdo->exec("ALTER TABLE leave_types ADD UNIQUE INDEX idx_leave_code (leave_code)");
            } catch (PDOException $e) {
                // Index might already exist or duplicate values
            }
        }
        if (!in_array('max_days_per_year', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN max_days_per_year INT DEFAULT 0 AFTER leave_code");
            $messages[] = "Added max_days_per_year column to leave_types";
        }
        if (!in_array('is_paid', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN is_paid TINYINT(1) DEFAULT 1 AFTER max_days_per_year");
            $messages[] = "Added is_paid column to leave_types";
        }
        if (!in_array('requires_approval', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN requires_approval TINYINT(1) DEFAULT 1 AFTER is_paid");
            $messages[] = "Added requires_approval column to leave_types";
        }
        if (!in_array('is_active', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER requires_approval");
            $messages[] = "Added is_active column to leave_types";
        }
        if (!in_array('description', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN description TEXT AFTER is_active");
            $messages[] = "Added description column to leave_types";
        }
        if (!in_array('created_at', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $messages[] = "Added created_at column to leave_types";
        }
        $messages[] = "Verified leave_types table structure";
    }

    // Insert default leave types if empty or incomplete
    $count = $pdo->query("SELECT COUNT(*) FROM leave_types WHERE leave_code IS NOT NULL AND leave_code != ''")->fetchColumn();
    if ($count == 0) {
        // Delete any incomplete rows first
        $pdo->exec("DELETE FROM leave_types WHERE leave_code IS NULL OR leave_code = ''");

        $defaultTypes = [
            ['Casual Leave', 'CL', 12, 1, 1, 'Casual leave for personal matters'],
            ['Sick Leave', 'SL', 12, 1, 1, 'Leave for illness or medical reasons'],
            ['Earned Leave', 'EL', 15, 1, 1, 'Privilege/Earned leave accrued over time'],
            ['Compensatory Off', 'CO', 0, 1, 1, 'Leave in lieu of extra work on holidays/weekends'],
            ['Maternity Leave', 'ML', 180, 1, 1, 'Maternity leave for female employees'],
            ['Paternity Leave', 'PL', 15, 1, 1, 'Paternity leave for male employees'],
            ['Loss of Pay', 'LOP', 0, 0, 1, 'Unpaid leave when other leaves exhausted'],
            ['Work From Home', 'WFH', 0, 1, 0, 'Work from home - no approval needed']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO leave_types (leave_type_name, leave_code, max_days_per_year, is_paid, requires_approval, description)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                leave_type_name = VALUES(leave_type_name),
                max_days_per_year = VALUES(max_days_per_year),
                is_paid = VALUES(is_paid),
                requires_approval = VALUES(requires_approval),
                description = VALUES(description)
        ");

        $added = 0;
        foreach ($defaultTypes as $type) {
            try {
                $stmt->execute($type);
                $added++;
            } catch (PDOException $e) {
                // Skip if duplicate
            }
        }
        $messages[] = "Added/updated $added default leave types";
    } else {
        $messages[] = "Leave types already exist ($count types)";
    }

    // 2. Create or update leave_balances table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'leave_balances'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("
            CREATE TABLE leave_balances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                leave_type_id INT NOT NULL,
                year INT NOT NULL,
                allocated DECIMAL(5,2) DEFAULT 0,
                used DECIMAL(5,2) DEFAULT 0,
                balance DECIMAL(5,2) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_emp_leave_year (employee_id, leave_type_id, year),
                INDEX idx_employee (employee_id),
                INDEX idx_year (year),
                FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created leave_balances table";
    } else {
        $messages[] = "Verified leave_balances table";
    }

    // 3. Create or update leave_requests table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'leave_requests'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("
            CREATE TABLE leave_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                leave_request_no VARCHAR(20) NOT NULL UNIQUE,
                employee_id INT NOT NULL,
                leave_type_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                total_days DECIMAL(5,2) NOT NULL,
                is_half_day TINYINT(1) DEFAULT 0,
                half_day_type ENUM('First Half', 'Second Half') NULL,
                reason TEXT,
                status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
                approved_by INT NULL,
                approval_date DATETIME NULL,
                approval_remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id),
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date),
                FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $messages[] = "Created leave_requests table";
    } else {
        // Table exists - check and add missing columns
        $columns = $pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('leave_request_no', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN leave_request_no VARCHAR(20) NOT NULL DEFAULT '' AFTER id");
            // Generate leave_request_no for existing rows
            $pdo->exec("UPDATE leave_requests SET leave_request_no = CONCAT('LR-', YEAR(IFNULL(created_at, NOW())), '-', LPAD(id, 4, '0')) WHERE leave_request_no = ''");
            $messages[] = "Added leave_request_no column to leave_requests";
        }
        if (!in_array('employee_id', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN employee_id INT NOT NULL DEFAULT 0 AFTER leave_request_no");
            $messages[] = "Added employee_id column to leave_requests";
        }
        if (!in_array('leave_type_id', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN leave_type_id INT NOT NULL DEFAULT 1 AFTER employee_id");
            $messages[] = "Added leave_type_id column to leave_requests";
        }
        if (!in_array('start_date', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN start_date DATE NOT NULL DEFAULT '1970-01-01' AFTER leave_type_id");
            $messages[] = "Added start_date column to leave_requests";
        }
        if (!in_array('end_date', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN end_date DATE NOT NULL DEFAULT '1970-01-01' AFTER start_date");
            $messages[] = "Added end_date column to leave_requests";
        }
        if (!in_array('total_days', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN total_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER end_date");
            $messages[] = "Added total_days column to leave_requests";
        }
        if (!in_array('is_half_day', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN is_half_day TINYINT(1) DEFAULT 0 AFTER total_days");
            $messages[] = "Added is_half_day column to leave_requests";
        }
        if (!in_array('half_day_type', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN half_day_type ENUM('First Half', 'Second Half') NULL AFTER is_half_day");
            $messages[] = "Added half_day_type column to leave_requests";
        }
        if (!in_array('reason', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN reason TEXT AFTER half_day_type");
            $messages[] = "Added reason column to leave_requests";
        }
        if (!in_array('status', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending' AFTER reason");
            $messages[] = "Added status column to leave_requests";
        }
        if (!in_array('approved_by', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_by INT NULL AFTER status");
            $messages[] = "Added approved_by column to leave_requests";
        }
        if (!in_array('approval_date', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approval_date DATETIME NULL AFTER approved_by");
            $messages[] = "Added approval_date column to leave_requests";
        }
        if (!in_array('approval_remarks', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approval_remarks TEXT NULL AFTER approval_date");
            $messages[] = "Added approval_remarks column to leave_requests";
        }
        if (!in_array('created_at', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $messages[] = "Added created_at column to leave_requests";
        }
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            $messages[] = "Added updated_at column to leave_requests";
        }
        $messages[] = "Verified leave_requests table structure";
    }

    // Initialize leave balances for all active employees for current year
    $currentYear = date('Y');

    // Check if max_days_per_year column exists before querying
    $columns = $pdo->query("SHOW COLUMNS FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('max_days_per_year', $columns) && in_array('is_active', $columns)) {
        $leaveTypes = $pdo->query("SELECT id, max_days_per_year FROM leave_types WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $leaveTypes = $pdo->query("SELECT id, 0 as max_days_per_year FROM leave_types")->fetchAll(PDO::FETCH_ASSOC);
    }

    $employees = $pdo->query("SELECT id FROM employees WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);

    $insertBalance = $pdo->prepare("
        INSERT IGNORE INTO leave_balances (employee_id, leave_type_id, year, allocated, balance)
        VALUES (?, ?, ?, ?, ?)
    ");

    $balancesAdded = 0;
    foreach ($employees as $empId) {
        foreach ($leaveTypes as $lt) {
            $result = $insertBalance->execute([
                $empId,
                $lt['id'],
                $currentYear,
                $lt['max_days_per_year'],
                $lt['max_days_per_year']
            ]);
            if ($insertBalance->rowCount() > 0) {
                $balancesAdded++;
            }
        }
    }

    if ($balancesAdded > 0) {
        $messages[] = "Initialized $balancesAdded leave balances for " . count($employees) . " employees";
    } else {
        $messages[] = "Leave balances already initialized for current year";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Management Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <h1>Leave Management Setup</h1>
    <p>This script sets up the leave management system with tables and default leave types.</p>

    <h3>Setup Results:</h3>

    <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #721c24;">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #155724;">
            <strong>Success:</strong>
            <ul>
                <?php foreach ($messages as $m): ?>
                    <li><?= htmlspecialchars($m) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h3>Leave Types:</h3>
    <?php
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();
        if (!$tableCheck) {
            echo '<p style="color: #856404;">Leave types table not created yet. Please refresh the page.</p>';
        } else {
            $types = $pdo->query("SELECT * FROM leave_types ORDER BY leave_code")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!empty($types)): ?>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Code</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left;">Name</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Days/Year</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Paid</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Approval</th>
                        <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $t): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dee2e6;"><strong><?= htmlspecialchars($t['leave_code'] ?? '') ?></strong></td>
                        <td style="padding: 10px; border: 1px solid #dee2e6;"><?= htmlspecialchars($t['leave_type_name'] ?? '') ?></td>
                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?= ($t['max_days_per_year'] ?? 0) ?: 'Unlimited' ?></td>
                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?= ($t['is_paid'] ?? 1) ? 'Yes' : 'No' ?></td>
                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?= ($t['requires_approval'] ?? 1) ? 'Required' : 'Not Required' ?></td>
                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">
                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 0.85em; background: <?= ($t['is_active'] ?? 1) ? '#d4edda' : '#f8d7da' ?>; color: <?= ($t['is_active'] ?? 1) ? '#155724' : '#721c24' ?>;">
                                <?= ($t['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No leave types found.</p>
        <?php endif;
    } catch (PDOException $e) {
        echo '<p style="color: #721c24;">Could not fetch leave types: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>

    <p style="margin-top: 20px;">
        <a href="/hr/leave_types.php" class="btn btn-primary">Manage Leave Types</a>
        <a href="/hr/leave_balance.php" class="btn btn-secondary">View Leave Balances</a>
        <a href="/hr/leaves.php" class="btn btn-secondary">Leave Requests</a>
    </p>
</div>

</body>
</html>
