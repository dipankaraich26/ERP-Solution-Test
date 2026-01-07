<?php
$host = "localhost";
$db   = "erp_system";
$user = "root";
$port = "3307";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
