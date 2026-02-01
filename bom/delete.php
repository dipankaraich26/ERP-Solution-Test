<?php
include "../db.php";
include "../includes/dialog.php";

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();

    // First delete BOM items (child records)
    $stmt = $pdo->prepare("DELETE FROM bom_items WHERE bom_id = ?");
    $stmt->execute([$id]);

    // Then delete BOM master (parent record)
    $stmt = $pdo->prepare("DELETE FROM bom_master WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    setModal("Success", "BOM deleted successfully.");
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to delete BOM: " . $e->getMessage());
    header("Location: index.php");
    exit;
}
