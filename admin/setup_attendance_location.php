<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Create attendance_settings table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Add location columns to attendance table
try {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_in_lat DECIMAL(10,8) NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_in_lng DECIMAL(11,8) NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_lat DECIMAL(10,8) NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_lng DECIMAL(11,8) NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_in_distance DECIMAL(10,2) NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_distance DECIMAL(10,2) NULL");
} catch (Exception $e) {
    // Columns may already exist
}

// Insert default settings
$defaults = [
    'office_latitude' => '0',
    'office_longitude' => '0',
    'allowed_radius' => '100', // meters
    'location_required' => '0', // 0 = disabled, 1 = enabled
    'office_name' => 'Main Office'
];

foreach ($defaults as $key => $value) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO attendance_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

header("Location: attendance_settings.php?setup=1");
exit;
