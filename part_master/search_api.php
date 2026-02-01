<?php
require '../db.php';
header('Content-Type: application/json');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$limit = min(50, max(1, $limit)); // Cap between 1-50

$results = [];

if (strlen($search) >= 1) {
    $sql = "SELECT p.id, p.part_id, p.part_no, p.part_name, p.category, p.description, p.uom, p.rate, p.hsn_code, p.gst, p.attachment_path,
                   COALESCE(i.qty, 0) as current_stock,
                   COALESCE((
                       SELECT SUM(po.qty) - COALESCE(SUM((SELECT COALESCE(SUM(se.received_qty),0) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted')),0)
                       FROM purchase_orders po
                       WHERE po.part_no = p.part_no AND po.status NOT IN ('closed', 'cancelled')
                   ), 0) as on_order,
                   COALESCE((
                       SELECT SUM(wo.qty)
                       FROM work_orders wo
                       WHERE wo.part_no = p.part_no AND wo.status NOT IN ('completed', 'cancelled', 'closed')
                   ), 0) as in_wo,
                   COALESCE((
                       SELECT psm.supplier_rate
                       FROM part_supplier_mapping psm
                       WHERE psm.part_no = p.part_no AND psm.active = 1
                       ORDER BY psm.is_preferred DESC, psm.id ASC
                       LIMIT 1
                   ), p.rate) as display_rate,
                   (SELECT COUNT(*) FROM part_supplier_mapping psm WHERE psm.part_no = p.part_no AND psm.active = 1) as supplier_count
            FROM part_master p
            LEFT JOIN inventory i ON p.part_no = i.part_no
            WHERE p.status = 'active'
            AND (p.part_no LIKE :search
                 OR p.part_name LIKE :search
                 OR p.part_id LIKE :search
                 OR p.category LIKE :search
                 OR p.description LIKE :search
                 OR p.hsn_code LIKE :search)
            ORDER BY p.part_no
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $searchParam = '%' . $search . '%';
    $stmt->bindValue(':search', $searchParam);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for this search
    $countSql = "SELECT COUNT(*) FROM part_master p
                 WHERE p.status = 'active'
                 AND (p.part_no LIKE :search
                      OR p.part_name LIKE :search
                      OR p.part_id LIKE :search
                      OR p.category LIKE :search
                      OR p.description LIKE :search
                      OR p.hsn_code LIKE :search)";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':search', $searchParam);
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'count' => count($results),
    'total' => isset($totalCount) ? (int)$totalCount : 0,
    'query' => $search
]);
