<?php
$host = "localhost";
$db   = "erp_system";
$user = "root";
$pass = "4236";

try {
    $pdo = new PDO("mysql:host=$host;port=3307;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
