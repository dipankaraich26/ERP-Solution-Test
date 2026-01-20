<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ? AND status = 'draft'");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    setModal("Error", "Invoice not found or already released");
    header("Location: index.php");
    exit;
}

// Get parts from Sales Order
$partsStmt = $pdo->prepare("
    SELECT part_no, qty
    FROM sales_orders
    WHERE so_no = ?
");
$partsStmt->execute([$invoice['so_no']]);
$parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($parts)) {
    setModal("Error", "No parts found in associated Sales Order");
    header("Location: index.php");
    exit;
}

// Begin transaction for atomic operation
$pdo->beginTransaction();
try {
    // Track depletion results
    $depleted = [];
    $warnings = [];

    foreach ($parts as $part) {
        $part_no = $part['part_no'];
        $qty_needed = (float)$part['qty'];

        // Check current stock
        $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) as current_qty FROM inventory WHERE part_no = ?");
        $stockStmt->execute([$part_no]);
        $currentStock = (float)($stockStmt->fetchColumn() ?: 0);

        // Deplete inventory (allow negative for tracking purposes, or cap at 0)
        $newQty = $currentStock - $qty_needed;

        // Update or insert inventory record
        $invCheck = $pdo->prepare("SELECT id FROM inventory WHERE part_no = ?");
        $invCheck->execute([$part_no]);

        if ($invCheck->fetch()) {
            // Update existing record
            $updateStmt = $pdo->prepare("UPDATE inventory SET qty = ? WHERE part_no = ?");
            $updateStmt->execute([$newQty, $part_no]);
        } else {
            // Insert new record with negative qty (or 0)
            $insertStmt = $pdo->prepare("INSERT INTO inventory (part_no, qty) VALUES (?, ?)");
            $insertStmt->execute([$part_no, $newQty]);
        }

        // Log the depletion in depletion table
        $depletionStmt = $pdo->prepare("
            INSERT INTO depletion (part_no, qty, reason, issue_no, issue_date)
            VALUES (?, ?, ?, ?, CURDATE())
        ");
        $depletionStmt->execute([
            $part_no,
            $qty_needed,
            'Invoice Release: ' . $invoice['invoice_no'],
            $invoice['invoice_no']
        ]);

        $depleted[] = $part_no . ' (-' . $qty_needed . ')';

        if ($newQty < 0) {
            $warnings[] = $part_no . ' is now negative (' . $newQty . ')';
        }
    }

    // Update invoice status and released_at timestamp
    $releaseStmt = $pdo->prepare("
        UPDATE invoice_master
        SET status = 'released', released_at = NOW()
        WHERE id = ?
    ");
    $releaseStmt->execute([$id]);

    // Also update sales order status to completed if it exists
    $soUpdateStmt = $pdo->prepare("
        UPDATE sales_orders
        SET status = 'completed'
        WHERE so_no = ?
    ");
    $soUpdateStmt->execute([$invoice['so_no']]);

    $pdo->commit();

    // Prepare success message
    $message = "Invoice " . $invoice['invoice_no'] . " released successfully.\n\n";
    $message .= "Inventory depleted for: " . implode(", ", $depleted);

    if (!empty($warnings)) {
        $message .= "\n\nWarnings:\n" . implode("\n", $warnings);
    }

    setModal("Success", $message);
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to release invoice: " . $e->getMessage());
    header("Location: index.php");
    exit;
}
