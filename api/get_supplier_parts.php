<?php
/**
 * AJAX endpoint to get parts linked to a specific supplier
 * Returns JSON array of parts with their rates and details
 */
header('Content-Type: application/json');

include "../db.php";

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

if ($supplier_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Supplier ID required', 'parts' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            psm.part_no,
            pm.part_name,
            pm.hsn_code,
            pm.uom,
            COALESCE(psm.supplier_rate, pm.rate, 0) AS supplier_rate,
            pm.rate AS part_master_rate,
            psm.lead_time_days,
            psm.min_order_qty,
            psm.supplier_sku,
            psm.is_preferred
        FROM part_supplier_mapping psm
        JOIN part_master pm ON psm.part_no = pm.part_no
        WHERE psm.supplier_id = ?
        AND psm.active = 1
        AND pm.status = 'active'
        ORDER BY psm.is_preferred DESC, pm.part_name ASC
    ");
    $stmt->execute([$supplier_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'supplier_id' => $supplier_id,
        'parts' => $parts
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'parts' => []
    ]);
}
