<?php
/**
 * Setup script to add new departments to the departments table
 */

include "../db.php";

$messages = [];
$errors = [];

// New departments to add
$newDepartments = ['Accounts', 'Electrical', 'Electronics', 'Engineering', 'Fabrication', 'Kronos', 'Logistics', 'NPD', 'SCM'];

try {
    // Check if departments table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'departments'")->fetch();

    if ($tableCheck) {
        foreach ($newDepartments as $dept) {
            // Check if department already exists
            $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
            $checkStmt->execute([$dept]);

            if (!$checkStmt->fetch()) {
                // Insert new department
                $insertStmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
                $insertStmt->execute([$dept]);
                $messages[] = "Added department: " . $dept;
            } else {
                $messages[] = "Department already exists: " . $dept;
            }
        }
    } else {
        // Create departments table if it doesn't exist
        $pdo->exec("
            CREATE TABLE departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $messages[] = "Created departments table";

        // Insert all departments
        $allDepartments = [
            'Administration', 'Accounts', 'Assembly', 'Design', 'Electrical',
            'Electronics', 'Engineering', 'Fabrication', 'Finance', 'HR', 'IT',
            'Kronos', 'Maintenance', 'Manufacturing', 'Marketing', 'Operations',
            'Production', 'Purchase', 'Quality', 'R&D', 'Sales', 'Service',
            'Store', 'Testing', 'Welding'
        ];

        $insertStmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
        foreach ($allDepartments as $dept) {
            $insertStmt->execute([$dept]);
        }
        $messages[] = "Added " . count($allDepartments) . " departments";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Departments Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <h1>Departments Setup</h1>
    <p>This script adds new departments (Fabrication, Kronos) to the database.</p>

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

    <h3>Current Departments:</h3>
    <?php
    try {
        $depts = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        if ($depts) {
            echo '<ul>';
            foreach ($depts as $d) {
                $highlight = in_array($d, $newDepartments) ? ' style="font-weight: bold; color: #28a745;"' : '';
                echo '<li' . $highlight . '>' . htmlspecialchars($d) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No departments found in database.</p>';
        }
    } catch (PDOException $e) {
        echo '<p style="color: #721c24;">Could not fetch departments: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>

    <p style="margin-top: 20px;">
        <a href="/hr/employee_add.php" class="btn btn-primary">Add Employee</a>
        <a href="/hr/employees.php" class="btn btn-secondary">View Employees</a>
    </p>
</div>

</body>
</html>
