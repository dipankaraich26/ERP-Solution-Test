<?php
require '../db.php';
require '../lib/SimpleXLSXGen.php';

// Search filter (same as list.php)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereClause = "WHERE p.status='active'";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (p.part_no LIKE :search
                    OR p.part_name LIKE :search
                    OR p.part_id LIKE :search
                    OR p.category LIKE :search
                    OR p.description LIKE :search
                    OR p.hsn_code LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Get all parts with stock info
$sql = "SELECT p.*,
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
        (SELECT GROUP_CONCAT(s.supplier_name SEPARATOR ', ')
         FROM part_supplier_mapping psm
         JOIN suppliers s ON psm.supplier_id = s.id
         WHERE psm.part_no = p.part_no AND psm.active = 1) as supplier_names
        FROM part_master p
        LEFT JOIN inventory i ON p.part_no = i.part_no
        $whereClause ORDER BY p.part_no";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build Excel data
$data = [];

// Headers
$data[] = [
    'Part ID',
    'Part No',
    'Part Name',
    'Category',
    'Description',
    'UOM',
    'Rate',
    'HSN Code',
    'GST %',
    'Min Stock',
    'Max Stock',
    'Reorder Level',
    'Current Stock',
    'On Order',
    'In Work Order',
    'Suppliers',
    'Status'
];

// Data rows
foreach ($parts as $part) {
    $data[] = [
        $part['part_id'] ?? '',
        $part['part_no'] ?? '',
        $part['part_name'] ?? '',
        $part['category'] ?? '',
        $part['description'] ?? '',
        $part['uom'] ?? '',
        $part['display_rate'] ?? $part['rate'] ?? 0,
        $part['hsn_code'] ?? '',
        $part['gst'] ?? '',
        $part['min_stock'] ?? 0,
        $part['max_stock'] ?? 0,
        $part['reorder_level'] ?? 0,
        $part['current_stock'] ?? 0,
        $part['on_order'] ?? 0,
        $part['in_wo'] ?? 0,
        $part['supplier_names'] ?? '',
        $part['status'] ?? 'active'
    ];
}

// Generate filename
$filename = 'parts_' . date('Y-m-d_His');
if ($search) $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', substr($search, 0, 20));
$filename .= '.xlsx';

// Generate and download Excel
$xlsx = SimpleXLSXGen::fromArray($data, 'Parts');
$xlsx->downloadAs($filename);
