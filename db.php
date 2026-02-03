<?php
// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

$host = "localhost";
$db   = "yashka_erpsystem";
$user = "yashka_erpmaster";
$pass = "erp4236$@#^";
$port = "3306";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}