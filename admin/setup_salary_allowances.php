<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$messages = [];

// Add performance_allowance column
try {
    $pdo->exec("ALTER TABLE employees ADD COLUMN performance_allowance DECIMAL(10,2) DEFAULT 0 AFTER other_allowance");
    $messages[] = ['success', 'Added performance_allowance column to employees table'];
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $messages[] = ['info', 'performance_allowance column already exists'];
    } else {
        $messages[] = ['error', 'Error adding performance_allowance: ' . $e->getMessage()];
    }
}

// Add food_allowance column
try {
    $pdo->exec("ALTER TABLE employees ADD COLUMN food_allowance DECIMAL(10,2) DEFAULT 0 AFTER performance_allowance");
    $messages[] = ['success', 'Added food_allowance column to employees table'];
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $messages[] = ['info', 'food_allowance column already exists'];
    } else {
        $messages[] = ['error', 'Error adding food_allowance: ' . $e->getMessage()];
    }
}

// Add columns to payroll table as well
try {
    $pdo->exec("ALTER TABLE payroll ADD COLUMN performance_allowance DECIMAL(10,2) DEFAULT 0 AFTER other_allowance");
    $messages[] = ['success', 'Added performance_allowance column to payroll table'];
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $messages[] = ['info', 'performance_allowance column already exists in payroll'];
    } else {
        $messages[] = ['error', 'Error adding performance_allowance to payroll: ' . $e->getMessage()];
    }
}

try {
    $pdo->exec("ALTER TABLE payroll ADD COLUMN food_allowance DECIMAL(10,2) DEFAULT 0 AFTER performance_allowance");
    $messages[] = ['success', 'Added food_allowance column to payroll table'];
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $messages[] = ['info', 'food_allowance column already exists in payroll'];
    } else {
        $messages[] = ['error', 'Error adding food_allowance to payroll: ' . $e->getMessage()];
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Salary Allowances</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .setup-container { max-width: 800px; margin: 0 auto; }
        .message { padding: 12px 15px; border-radius: 6px; margin-bottom: 10px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .summary { background: #e8f4fd; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .summary h3 { margin-top: 0; color: #2c3e50; }
    </style>
</head>
<body>

<div class="content">
    <div class="setup-container">
        <h1>Setup Salary Allowances</h1>
        <p>This script adds Performance Allowance and Food Allowance columns to the database.</p>

        <h2>Results</h2>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?= $msg[0] ?>">
                <?= htmlspecialchars($msg[1]) ?>
            </div>
        <?php endforeach; ?>

        <div class="summary">
            <h3>New Salary Fields Added</h3>
            <ul>
                <li><strong>Performance Allowance</strong> - Variable allowance based on performance</li>
                <li><strong>Food Allowance</strong> - Monthly food/meal allowance</li>
            </ul>
            <p>These fields have been added to both the <code>employees</code> table (base salary structure) and the <code>payroll</code> table (monthly payroll records).</p>
        </div>

        <p style="margin-top: 20px;">
            <a href="../hr/employees.php" class="btn btn-primary">Go to Employees</a>
            <a href="../hr/payroll.php" class="btn btn-secondary">Go to Payroll</a>
        </p>
    </div>
</div>

</body>
</html>
