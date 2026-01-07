<?php
include "../db.php";

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo "<script>alert('Invalid part'); window.location='list.php';</script>";
    exit;
}

/* Deactivate instead of delete */
$pdo->prepare("
    UPDATE part_master
    SET status='inactive'
    WHERE id=?
")->execute([$id]);

echo "<script>alert('Part deactivated'); window.location='list.php';</script>";
exit;
