<?php
/**
 * One-time script to add missing module keys to the modules table
 */
include "../db.php";

try {
    $pdo->query("SELECT 1 FROM modules LIMIT 1");
} catch (Exception $e) {
    echo "modules table does not exist yet. Run setup_user_permissions.php first.<br>";
    echo '<a href="setup_user_permissions.php">Run Setup</a>';
    exit;
}

$modules = [
    ['quality_control', 'Quality Control', 'Quality Control', 55],
    ['qms', 'QMS (Quality Management System)', 'QMS', 57],
    ['accounts', 'Accounts & Finance', 'Accounts & Finance', 58],
    ['approvals', 'Approvals', 'Admin', 83],
    ['customer_portal', 'Customer Portal', 'Sales & CRM', 9],
];

$insertStmt = $pdo->prepare("INSERT IGNORE INTO modules (module_key, module_name, module_group, display_order) VALUES (?, ?, ?, ?)");
$count = 0;
foreach ($modules as $m) {
    $insertStmt->execute($m);
    if ($insertStmt->rowCount() > 0) {
        echo "Added: " . htmlspecialchars($m[0]) . "<br>";
        $count++;
    } else {
        echo "Already exists: " . htmlspecialchars($m[0]) . "<br>";
    }
}

echo "<br><strong>Done. $count new modules added.</strong><br>";
echo '<br><a href="user_permissions.php">Go to User Permissions</a>';
