<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Only allow admin to delete all employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        // Delete all employees
        $result = $pdo->exec('DELETE FROM employees');

        echo "<!DOCTYPE html>
<html>
<head>
    <title>Delete All Employees - Success</title>
    <link rel='stylesheet' href='../assets/style.css'>
</head>
<body>
<div class='content'>
    <h1>Delete All Employees - Success</h1>
    <div class='alert success' style='padding: 20px; margin: 20px 0; border-radius: 4px;'>
        <strong>✓ Successfully deleted " . $result . " employee records from the database.</strong>
    </div>
    <p style='margin-top: 20px;'>All employee information has been permanently removed.</p>
    <a href='employees.php' class='btn btn-primary' style='margin-top: 20px;'>Go to Employees</a>
</div>
</body>
</html>";
        exit;
    } catch (Exception $e) {
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Delete All Employees - Error</title>
    <link rel='stylesheet' href='../assets/style.css'>
</head>
<body>
<div class='content'>
    <h1>Delete All Employees - Error</h1>
    <div class='alert error' style='padding: 20px; margin: 20px 0; border-radius: 4px;'>
        <strong>✗ Error deleting employees:</strong><br/>
        " . htmlspecialchars($e->getMessage()) . "
    </div>
    <a href='employees.php' class='btn btn-secondary' style='margin-top: 20px;'>Go Back</a>
</div>
</body>
</html>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delete All Employees</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="content">
    <h1>Delete All Employees</h1>

    <div class="alert error" style="padding: 20px; margin: 20px 0; border-radius: 4px; background: #fde5e5;">
        <strong>⚠️ WARNING: This action cannot be undone!</strong><br/>
        You are about to permanently delete ALL employee records from the database.
    </div>

    <form method="post" style="margin-top: 30px;">
        <div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 4px; background: #fff5f5;">
            <label>
                <input type="checkbox" name="confirm" value="yes" required>
                <strong>I confirm that I want to delete all employee records. This action is permanent and cannot be undone.</strong>
            </label>
        </div>

        <button type="submit" class="btn btn-danger" style="margin-top: 20px; padding: 12px 30px; font-size: 16px;">
            Delete All Employee Records
        </button>
        <a href="employees.php" class="btn btn-secondary" style="margin-top: 20px; margin-left: 10px;">Cancel</a>
    </form>
</div>
</body>
</html>
