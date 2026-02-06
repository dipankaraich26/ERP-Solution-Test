<?php
include "../db.php";
include "../includes/dialog.php";

if (!isset($_GET['id'])) {
    setModal("Error", "BOM ID is required.");
    header("Location: index.php");
    exit;
}

$sourceId = (int)$_GET['id'];

// Fetch the source BOM
$sourceBom = $pdo->prepare("SELECT * FROM bom_master WHERE id = ?");
$sourceBom->execute([$sourceId]);
$sourceBom = $sourceBom->fetch(PDO::FETCH_ASSOC);

if (!$sourceBom) {
    setModal("Error", "Source BOM not found.");
    header("Location: index.php");
    exit;
}

// Fetch the source BOM items
$sourceItems = $pdo->prepare("SELECT * FROM bom_items WHERE bom_id = ?");
$sourceItems->execute([$sourceId]);
$sourceItems = $sourceItems->fetchAll(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    // Generate new BOM number
    // Format: BOM-YYYYMM-XXXX
    $yearMonth = date('Ym');
    $lastBom = $pdo->query("SELECT bom_no FROM bom_master ORDER BY id DESC LIMIT 1")->fetchColumn();

    if ($lastBom && preg_match('/BOM-(\d{6})-(\d+)/', $lastBom, $matches)) {
        $lastYearMonth = $matches[1];
        $lastSeq = (int)$matches[2];

        if ($lastYearMonth === $yearMonth) {
            $newSeq = $lastSeq + 1;
        } else {
            $newSeq = 1;
        }
    } else {
        $newSeq = 1;
    }

    $newBomNo = 'BOM-' . $yearMonth . '-' . str_pad($newSeq, 4, '0', STR_PAD_LEFT);

    // Create new BOM record (as Draft/Inactive)
    $insertBom = $pdo->prepare("
        INSERT INTO bom_master (bom_no, parent_part_no, description, status)
        VALUES (?, ?, ?, 'inactive')
    ");
    $insertBom->execute([
        $newBomNo,
        $sourceBom['parent_part_no'],
        $sourceBom['description'] . ' (Copy of ' . $sourceBom['bom_no'] . ')'
    ]);

    $newBomId = $pdo->lastInsertId();

    // Copy all BOM items
    $insertItem = $pdo->prepare("
        INSERT INTO bom_items (bom_id, component_part_no, qty, rate)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($sourceItems as $item) {
        $insertItem->execute([
            $newBomId,
            $item['component_part_no'],
            $item['qty'],
            $item['rate']
        ]);
    }

    $pdo->commit();

    setModal("Success", "BOM duplicated successfully! New BOM: $newBomNo");
    header("Location: edit.php?id=" . $newBomId);
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to duplicate BOM: " . $e->getMessage());
    header("Location: view.php?id=" . $sourceId);
    exit;
}
