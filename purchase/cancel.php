<?php
require "../db.php";

if (!isset($_GET['po_no'])) {
    header("Location: index.php");
    exit;
}

$po_no = $_GET['po_no'];

$pdo->beginTransaction();

try {
    /* 🔹 Fetch all ACTIVE lines under this PO */
    $stmt = $pdo->prepare("
        SELECT part_no, qty
        FROM purchase_orders
        WHERE po_no = ? AND status = 'active'
    ");
    $stmt->execute([$po_no]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        // Nothing to cancel
        $pdo->rollBack();
        header("Location: index.php");
        exit;
    }

    /* 🔹 Roll back inventory for each line */
    $updateInventory = $pdo->prepare("
        UPDATE inventory
        SET qty = qty - ?
        WHERE part_no = ?
    ");

    foreach ($rows as $row) {
        $updateInventory->execute([
            $row['qty'],
            $row['part_no']
        ]);
    }

    /* 🔹 Mark entire PO as cancelled */
    $stmt = $pdo->prepare("
        UPDATE purchase_orders
        SET status = 'cancelled'
        WHERE po_no = ?
    ");
    $stmt->execute([$po_no]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
}

header("Location: index.php");
exit;
