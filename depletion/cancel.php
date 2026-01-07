<?php
include "../db.php";

$id = $_GET['id'] ?? 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

/* Fetch depletion record */
$stmt = $pdo->prepare("SELECT part_no, qty, status FROM depletion WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    header("Location: index.php");
    exit;
}

/* Only cancel active records */
if ($row['status'] !== 'active') {
    header("Location: index.php");
    exit;
}

$pdo->beginTransaction();
try {
    // Restore inventory
    $pdo->prepare("
        UPDATE inventory SET qty = qty + ?
        WHERE part_no = ?
    ")->execute([$row['qty'], $row['part_no']]);

    // Mark depletion as cancelled
    $pdo->prepare("
        UPDATE depletion SET status='cancelled'
        WHERE id=?
    ")->execute([$id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

header("Location: index.php");
exit;
