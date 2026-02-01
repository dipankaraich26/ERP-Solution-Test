<?php
/**
 * AJAX endpoint to get suppliers mapped to a specific part
 * Returns JSON array of suppliers with their rates and details
 */
header('Content-Type: application/json');

include "../db.php";

$part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';

if (empty($part_no)) {
    echo json_encode(['success' => false, 'error' => 'Part number required', 'suppliers' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            psm.supplier_id,
            s.supplier_name,
            s.supplier_code,
            psm.supplier_rate,
            psm.lead_time_days,
            psm.min_order_qty,
            psm.supplier_sku,
            psm.is_preferred
        FROM part_supplier_mapping psm
        JOIN suppliers s ON psm.supplier_id = s.id
        WHERE psm.part_no = ?
        AND psm.active = 1
        ORDER BY psm.is_preferred DESC, psm.supplier_rate ASC
    ");
    $stmt->execute([$part_no]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'part_no' => $part_no,
        'suppliers' => $suppliers
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'suppliers' => []
    ]);
}
