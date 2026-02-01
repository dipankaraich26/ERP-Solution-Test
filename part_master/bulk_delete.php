<?php
include "../db.php";
include "../includes/dialog.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
    header("Location: list.php");
    exit;
}

$ids = array_map('intval', explode(',', $_POST['ids']));
$ids = array_filter($ids); // Remove zeros

if (empty($ids)) {
    setModal("Error", "No valid parts selected.");
    header("Location: list.php");
    exit;
}

// Get part details for all selected parts
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM part_master WHERE id IN ($placeholders)");
$stmt->execute($ids);
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($parts)) {
    setModal("Error", "No parts found.");
    header("Location: list.php");
    exit;
}

// Check usage for each part
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

$cannotDelete = [];
$canDelete = [];

foreach ($parts as $part) {
    $part_no = $part['part_no'];
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
                    $usedIn[] = $check['label'];
                }
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }
    }

    if (!empty($usedIn)) {
        $cannotDelete[] = "{$part['part_name']} ({$part_no}) - used in: " . implode(', ', $usedIn);
    } else {
        $canDelete[] = $part;
    }
}

// Delete parts that can be deleted
$deletedCount = 0;
$deletedNames = [];

if (!empty($canDelete)) {
    try {
        $pdo->beginTransaction();

        foreach ($canDelete as $part) {
            $part_no = $part['part_no'];
            $part_id = $part['id'];

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
            $stmt->execute([$part_id]);

            // Delete attachment file if exists
            if (!empty($part['attachment_path']) && file_exists("../" . $part['attachment_path'])) {
                unlink("../" . $part['attachment_path']);
            }

            $deletedCount++;
            $deletedNames[] = $part['part_name'];
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", "Failed to delete parts: " . $e->getMessage());
        header("Location: list.php");
        exit;
    }
}

// Build result message
$message = "";

if ($deletedCount > 0) {
    $message .= "Successfully deleted $deletedCount part(s):\n- " . implode("\n- ", $deletedNames);
}

if (!empty($cannotDelete)) {
    if ($message) $message .= "\n\n";
    $message .= "Could not delete " . count($cannotDelete) . " part(s) (in use):\n- " . implode("\n- ", $cannotDelete);
}

if ($deletedCount > 0 && empty($cannotDelete)) {
    setModal("Success", $message);
} elseif ($deletedCount > 0 && !empty($cannotDelete)) {
    setModal("Partial Success", $message);
} else {
    setModal("Cannot Delete", $message);
}

header("Location: list.php");
exit;
