<?php
/**
 * API to get all products from an invoice
 * Used by installations form to select products for installation
 */
header('Content-Type: application/json');
include "../db.php";

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if (!$invoice_id) {
    echo json_encode(['success' => false, 'error' => 'Invoice ID required']);
    exit;
}

try {
    // Step 1: Get the invoice
    $invoiceStmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ?");
    $invoiceStmt->execute([$invoice_id]);
    $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    // Step 2: Get the chain using SO number (exact same pattern as invoices/view.php)
    $chainStmt = $pdo->prepare("
        SELECT
            so.so_no, so.linked_quote_id,
            q.id as pi_id, q.pi_no, q.quote_no
        FROM (
            SELECT DISTINCT so_no, sales_date, status, customer_id, customer_po_id, linked_quote_id
            FROM sales_orders
            WHERE so_no = ?
        ) so
        LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    ");
    $chainStmt->execute([$invoice['so_no']]);
    $chain = $chainStmt->fetch(PDO::FETCH_ASSOC);

    $products = [];
    $quote_id = null;

    // Step 3: Get products if PI exists
    if ($chain && $chain['pi_id']) {
        $quote_id = $chain['pi_id'];
        $productsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
        $productsStmt->execute([$quote_id]);
        $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'invoice' => [
            'invoice_no' => $invoice['invoice_no'],
            'invoice_date' => $invoice['invoice_date'],
            'so_no' => $invoice['so_no'],
            'quote_id' => $quote_id
        ],
        'products' => $products
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
