<?php
// Debug test file - upload this to your online server to diagnose issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Debug Info</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

echo "<h3>Testing Database Connection...</h3>";

// These are your LOCAL credentials - they won't work online!
$host = "localhost";
$db   = "yashka_erpsystem";
$user = "root";  // <-- This is wrong for online server
$pass = "";      // <-- This is wrong for online server
$port = "3306";

echo "<p>Attempting to connect with:</p>";
echo "<ul>";
echo "<li>Host: $host</li>";
echo "<li>Database: $db</li>";
echo "<li>User: $user</li>";
echo "<li>Password: " . (empty($pass) ? "(empty)" : "(hidden)") . "</li>";
echo "</ul>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green; font-weight: bold;'>✓ Database connection successful!</p>";

    // Test a simple query
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM attendance_settings")->fetch();
    echo "<p>attendance_settings rows: " . $result['cnt'] . "</p>";

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Testing match() syntax (PHP 8.0+ required)...</h3>";
try {
    $code = 'P';
    $result = match($code) {
        'P' => 'Present',
        'A' => 'Absent',
        default => 'Unknown'
    };
    echo "<p style='color: green;'>✓ match() syntax works: $result</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>✗ match() Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>File Paths Check...</h3>";
$files = [
    '../db.php' => __DIR__ . '/../db.php',
    '../includes/dialog.php' => __DIR__ . '/../includes/dialog.php',
    '../includes/sidebar.php' => __DIR__ . '/../includes/sidebar.php'
];
// Adjust paths for root location
$files = [
    'db.php' => __DIR__ . '/db.php',
    'includes/dialog.php' => __DIR__ . '/includes/dialog.php',
    'includes/sidebar.php' => __DIR__ . '/includes/sidebar.php'
];
foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ $name exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $name NOT FOUND</p>";
    }
}
?>
