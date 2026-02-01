<?php
include "../db.php";
include "../includes/dialog.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// Get part details
$stmt = $pdo->prepare("SELECT * FROM part_master WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$part) {
    header("Location: list.php?error=invalid");
    exit;
}

$part_no = $part['part_no'];

// Check if part is used in various tables
$usageChecks = [
    ['table' => 'bom_master', 'column' => 'parent_part_no', 'label' => 'BOM (as parent)'],
    ['table' => 'bom_items', 'column' => 'component_part_no', 'label' => 'BOM (as component)'],
    ['table' => 'inventory', 'column' => 'part_no', 'label' => 'Inventory'],
    ['table' => 'stock_entries', 'column' => 'part_no', 'label' => 'Stock Entries'],
    ['table' => 'purchase_items', 'column' => 'part_no', 'label' => 'Purchase Orders'],
    ['table' => 'sales_order_items', 'column' => 'part_no', 'label' => 'Sales Orders'],
    ['table' => 'quote_items', 'column' => 'part_no', 'label' => 'Quotes'],
    ['table' => 'invoice_items', 'column' => 'part_no', 'label' => 'Invoices'],
    ['table' => 'work_order_items', 'column' => 'part_no', 'label' => 'Work Orders'],
];

$usedIn = [];

foreach ($usageChecks as $check) {
    try {
        // Check if table exists first
        $tableCheck = $pdo->query("SHOW TABLES LIKE '{$check['table']}'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$check['table']} WHERE {$check['column']} = ?");
            $stmt->execute([$part_no]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $usedIn[] = $check['label'] . " ($count records)";
            }
        }
    } catch (Exception $e) {
        // Table might not exist, skip
    }
}

if (!empty($usedIn)) {
    $message = "Cannot delete part '{$part['part_name']}' ({$part_no}). It is used in:\n- " . implode("\n- ", $usedIn);
    setModal("Cannot Delete", $message);
    header("Location: list.php");
    exit;
}

// Safe to delete
try {
    $pdo->beginTransaction();

    // Delete from part_suppliers if exists
    try {
        $pdo->prepare("DELETE FROM part_suppliers WHERE part_no = ?")->execute([$part_no]);
    } catch (Exception $e) {
        // Table might not exist
    }

    // Delete from part_min_stock if exists
    try {
        $pdo->prepare("DELETE FROM part_min_stock WHERE part_no = ?")->execute([$part_no]);
    } catch (Exception $e) {
        // Table might not exist
    }

    // Delete the part
    $stmt = $pdo->prepare("DELETE FROM part_master WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    // Delete attachment file if exists
    if (!empty($part['attachment_path']) && file_exists("../" . $part['attachment_path'])) {
        unlink("../" . $part['attachment_path']);
    }

    setModal("Success", "Part '{$part['part_name']}' deleted successfully.");
    header("Location: list.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to delete part: " . $e->getMessage());
    header("Location: list.php");
    exit;
}
