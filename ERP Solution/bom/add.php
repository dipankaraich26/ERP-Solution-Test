<?php
include "../db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$product = $_POST['product_part_no'];
$parts   = $_POST['parts'];
$qtys    = $_POST['qty'];

$pdo->beginTransaction();

try {
    /* =========================
       AUTO BOM NO
    ========================== */
    $nextId = $pdo->query("
        SELECT IFNULL(MAX(id),0)+1 FROM bom_master
    ")->fetchColumn();

    $bomNo = 'BOM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    /* =========================
       INSERT BOM MASTER
    ========================== */
    $stmt = $pdo->prepare("
        INSERT INTO bom_master (bom_no, product_part_no)
        VALUES (?, ?)
    ");
    $stmt->execute([$bomNo, $product]);

    $bomId = $pdo->lastInsertId();

    /* =========================
       INSERT BOM ITEMS
    ========================== */
    $stmtItem = $pdo->prepare("
        INSERT INTO bom_items (bom_id, part_no, qty)
        VALUES (?, ?, ?)
    ");

    foreach ($parts as $i => $partNo) {
        if ($partNo && $qtys[$i] > 0) {
            $stmtItem->execute([$bomId, $partNo, $qtys[$i]]);
        }
    }

    $pdo->commit();
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("BOM creation failed");
}
