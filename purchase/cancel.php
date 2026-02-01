<?php
require "../db.php";
include "../includes/dialog.php";

if (!isset($_GET['po_no'])) {
    header("Location: index.php");
    exit;
}

$po_no = $_GET['po_no'];

$pdo->beginTransaction();

try {
    /* Check if PO exists and can be cancelled (not already cancelled or closed) */
    $stmt = $pdo->prepare("
        SELECT id, part_no, qty, status
        FROM purchase_orders
        WHERE po_no = ? AND status NOT IN ('cancelled', 'closed')
    ");
    $stmt->execute([$po_no]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        // Nothing to cancel - PO either doesn't exist or is already cancelled/closed
        $pdo->rollBack();
        setModal("Cannot Cancel", "Purchase Order not found or already cancelled/closed.");
        header("Location: index.php");
        exit;
    }

    /* Note: We do NOT rollback inventory here because POs don't add to inventory.
       Inventory is only updated when stock is received via stock_entry/receive_all.php.
       If partial stock was already received, that remains in inventory. */

    /* Mark entire PO as cancelled */
    $stmt = $pdo->prepare("
        UPDATE purchase_orders
        SET status = 'cancelled'
        WHERE po_no = ?
    ");
    $stmt->execute([$po_no]);

    $pdo->commit();
    setModal("Success", "Purchase Order $po_no has been cancelled.");

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to cancel: " . $e->getMessage());
}

header("Location: index.php");
exit;
