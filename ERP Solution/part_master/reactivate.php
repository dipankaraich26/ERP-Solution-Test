<?php
include "../db.php";

$id = $_GET['id'] ?? 0;

$pdo->prepare("
    UPDATE part_master
    SET status='active'
    WHERE id=?
")->execute([$id]);

header("Location: inactive.php");
exit;
