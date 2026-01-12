<?php
require "../db.php";

$so_no = $_GET['so_no'] ?? '';
if (!$so_no) exit;

$pdo->beginTransaction();
try {
    $rows = $pdo->prepare("
        SELECT part_no, qty
        FROM sales_orders
        WHERE so_no=? AND status='open'
    ");
    $rows->execute([$so_no]);
    $lines = $rows->fetchAll();

    foreach ($lines as $l) {
        $pdo->prepare("
            UPDATE inventory
            SET qty = qty + ?
            WHERE part_no = ?
        ")->execute([$l['qty'], $l['part_no']]);
    }

    $pdo->prepare("
        UPDATE sales_orders
        SET status='cancelled'
        WHERE so_no=?
    ")->execute([$so_no]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

header("Location: index.php");
exit;
