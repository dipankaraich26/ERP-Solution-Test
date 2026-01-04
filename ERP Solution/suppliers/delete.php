<?php
include "../db.php";
include "../includes/dialog.php";

$id = $_GET['id'] ?? 0;

/* Prevent delete if used in purchases */
$check = $pdo->prepare("
    SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=?
");
$check->execute([$id]);

if ($check->fetchColumn() > 0) {
    setModal("Failed to remove supplier", "Supplier is in use");
    header("Location: index.php"); 
    exit;
}

$pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);
header("Location: index.php");
