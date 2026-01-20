<?php
/**
 * Admin Password Reset Script
 * Run this once to reset the admin password, then DELETE this file!
 */

include "../db.php";

$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

// Check if admin user exists
$check = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();

if ($check) {
    // Update existing admin
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$hash]);
    echo "<h2>Admin password has been reset!</h2>";
} else {
    // Create admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, role, is_active)
        VALUES ('admin', ?, 'Administrator', 'admin', 1)
    ");
    $stmt->execute([$hash]);
    echo "<h2>Admin user created!</h2>";
}

echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($new_password) . "</p>";
echo "<p style='color: red; font-weight: bold;'>DELETE THIS FILE AFTER USE: admin/reset_admin.php</p>";
echo "<p><a href='/login.php'>Go to Login</a></p>";
