<?php
$host = "localhost";
$db   = "yashka_erpsystem";
$user = "root";
$pass = "";
$port = "3306";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
