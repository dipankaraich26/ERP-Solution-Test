<?php
require "../db.php";
include "../includes/dialog.php";

$so_no = $_GET['so_no'] ?? '';
if (!$so_no) {
    header("Location: index.php");
    exit;
}

$pdo->beginTransaction();

try {
    /* Fetch SO lines */
    $stmt = $pdo->prepare("
        SELECT part_no, qty
        FROM sales_orders
        WHERE so_no = ? AND status IN ('open', 'pending')
    ");
    $stmt->execute([$so_no]);
    $lines = $stmt->fetchAll();

    if (!$lines) {
        throw new Exception("Invalid or already released SO");
    }

    /* Stock check */
    $insufficientParts = [];
    foreach ($lines as $l) {
        $s = $pdo->prepare("
            SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?
        ");
        $s->execute([$l['part_no']]);
        $available = (int)$s->fetchColumn();

        if ($available < $l['qty']) {
            $insufficientParts[] = $l['part_no'] . " (Available: $available, Required: {$l['qty']})";
        }
    }

    if (!empty($insufficientParts)) {
        throw new Exception(
            "Cannot release SO. Insufficient stock for:\n" . implode("\n", $insufficientParts)
        );
    }

    /* Deduct inventory */
    foreach ($lines as $l) {
        $pdo->prepare("
            UPDATE inventory
            SET qty = qty - ?
            WHERE part_no = ?
        ")->execute([$l['qty'], $l['part_no']]);
    }

    /* Mark SO released */
    $pdo->prepare("
        UPDATE sales_orders
        SET status = 'released'
        WHERE so_no = ?
    ")->execute([$so_no]);

    $pdo->commit();
    setModal("Success", "Sales Order $so_no released successfully. Inventory has been deducted.");

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", $e->getMessage());
}

header("Location: index.php");
exit;
