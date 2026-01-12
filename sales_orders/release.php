<?php
require "../db.php";

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
        WHERE so_no = ? AND status = 'open'
    ");
    $stmt->execute([$so_no]);
    $lines = $stmt->fetchAll();

    if (!$lines) {
        throw new Exception("Invalid or already released SO");
    }

    /* Stock check */
    foreach ($lines as $l) {
        $s = $pdo->prepare("
            SELECT qty FROM inventory WHERE part_no = ?
        ");
        $s->execute([$l['part_no']]);
        $available = (int)$s->fetchColumn();

        if (($available - $l['qty']) < 0) {
            throw new Exception(
                "Cannot release SO. Part {$l['part_no']} will deplete stock."
            );
        }
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

} catch (Exception $e) {
    $pdo->rollBack();
}

header("Location: index.php");
exit;
