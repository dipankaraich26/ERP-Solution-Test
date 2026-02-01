<?php
/**
 * API to get all released invoices for a customer
 * Used by installations form to select invoice for installation
 */
header('Content-Type: application/json');
include "../db.php";

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customer_id) {
    echo json_encode(['success' => false, 'error' => 'Customer ID required']);
    exit;
}

try {
    // Get all released invoices for this customer with their details
    $stmt = $pdo->prepare("
        SELECT
            im.id as invoice_id,
            im.invoice_no,
            im.invoice_date,
            im.so_no,
            im.status as invoice_status,
            so.linked_quote_id,
            qm.id as quote_id,
            qm.quote_no,
            qm.pi_no,
            (SELECT SUM(qi.total_amount) FROM quote_items qi WHERE qi.quote_id = qm.id) as total_value,
            (SELECT COUNT(*) FROM quote_items qi WHERE qi.quote_id = qm.id) as item_count
        FROM invoice_master im
        LEFT JOIN (
            SELECT DISTINCT so_no, customer_id, linked_quote_id
            FROM sales_orders
        ) so ON im.so_no = so.so_no
        LEFT JOIN quote_master qm ON qm.id = so.linked_quote_id
        WHERE im.customer_id = ?
        AND im.status = 'released'
        ORDER BY im.invoice_date DESC
    ");
    $stmt->execute([$customer_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'invoices' => $invoices,
        'customer_id' => $customer_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
