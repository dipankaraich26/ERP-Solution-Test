<?php
/**
 * Install script to add user assignment feature to CRM leads
 * Run this once to add the assigned_user_id column
 */
include "../db.php";

$messages = [];
$errors = [];

try {
    // Check if column already exists
    $result = $pdo->query("SHOW COLUMNS FROM crm_leads LIKE 'assigned_user_id'");
    if ($result->rowCount() == 0) {
        // Add assigned_user_id column
        $pdo->exec("ALTER TABLE crm_leads ADD COLUMN assigned_user_id INT NULL AFTER assigned_to");
        $pdo->exec("ALTER TABLE crm_leads ADD INDEX idx_assigned_user (assigned_user_id)");
        $messages[] = "Added 'assigned_user_id' column to crm_leads table";

        // Try to map existing assigned_to names to user IDs
        $pdo->exec("
            UPDATE crm_leads cl
            JOIN users u ON cl.assigned_to = u.full_name
            SET cl.assigned_user_id = u.id
            WHERE cl.assigned_to IS NOT NULL
        ");
        $messages[] = "Mapped existing assignments to user IDs where possible";
    } else {
        $messages[] = "'assigned_user_id' column already exists";
    }

} catch (PDOException $e) {
    $errors[] = "Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Install Lead Assignment Feature</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Lead Assignment Feature Installation</h1>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <div class="error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p><a href="/crm/index.php">Go to CRM Lead Management</a></p>
</body>
</html>
