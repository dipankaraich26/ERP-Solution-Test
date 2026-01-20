<?php
/**
 * Supplier Recommendation API
 * Suggests the best supplier for a part based on cost and availability
 *
 * Parameters:
 *   - part_no: Part number (required)
 *
 * Returns:
 *   JSON response with supplier details
 */

require '../../db.php';
require '../../includes/procurement_helper.php';

header('Content-Type: application/json');

$part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';

if (!$part_no) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'part_no parameter is required'
    ]);
    exit;
}

// Verify part exists
$partStmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no = ?");
$partStmt->execute([$part_no]);
$part = $partStmt->fetch(PDO::FETCH_ASSOC);

if (!$part) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Part not found'
    ]);
    exit;
}

// Get best supplier
$bestSupplier = getBestSupplier($pdo, $part_no);

if (!$bestSupplier) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'No suppliers configured for this part'
    ]);
    exit;
}

// Return success
http_response_code(200);
echo json_encode([
    'success' => true,
    'supplier' => [
        'supplier_id' => (int)$bestSupplier['supplier_id'],
        'supplier_name' => $bestSupplier['supplier_name'],
        'supplier_code' => $bestSupplier['supplier_code'],
        'supplier_rate' => (float)$bestSupplier['supplier_rate'],
        'lead_time_days' => (int)$bestSupplier['lead_time_days'],
        'min_order_qty' => (int)$bestSupplier['min_order_qty'],
        'supplier_sku' => $bestSupplier['supplier_sku']
    ]
]);
