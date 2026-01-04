<?php
include "../db.php";

$id = $_GET['id'];

$pdo->beginTransaction();

/* Fetch entry */
$e = $pdo->query("
    SELECT * FROM stock_entries
    WHERE id=$id AND status='posted'
")->fetch();

if (!$e) die("Invalid entry");

/* Reverse inventory */
$pdo->prepare("
    UPDATE inventory SET qty = qty - ?
    WHERE part_no=?
")->execute([$e['received_qty'], $e['part_no']]);

/* Mark cancelled */
$pdo->prepare("
    UPDATE stock_entries SET status='cancelled'
    WHERE id=?
")->execute([$id]);

$pdo->commit();
header("Location: index.php");
exit;
