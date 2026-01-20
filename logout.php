<?php
session_start();
include "db.php";

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    $pdo->prepare("
        INSERT INTO activity_log (user_id, action, module, ip_address)
        VALUES (?, 'logout', 'auth', ?)
    ")->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
}

// Destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit;
