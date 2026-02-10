<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

header('Content-Type: application/json');

if (getUserRole() !== 'admin') {
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$parentPartNo = trim($_GET['parent_part_no'] ?? '');
$multiplier = max(1, (int)($_GET['multiplier'] ?? 1));

if (empty($parentPartNo)) {
    echo json_encode(['error' => 'Parent part number is required']);
    exit;
}

// Find active BOM
$bomStmt = $pdo->prepare("
    SELECT b.id, b.bom_no, b.parent_part_no, p.part_name as parent_name
    FROM bom_master b
    JOIN part_master p ON p.part_no = b.parent_part_no
    WHERE b.parent_part_no = ? AND b.status = 'active'
    LIMIT 1
");
$bomStmt->execute([$parentPartNo]);
$bom = $bomStmt->fetch(PDO::FETCH_ASSOC);

if (!$bom) {
    echo json_encode(['error' => 'No active BOM found for this part']);
    exit;
}

// Get child parts
$childStmt = $pdo->prepare("
    SELECT bi.component_part_no as part_no, bi.qty as bom_qty,
           p.part_name, p.uom,
           COALESCE(inv.qty, 0) as current_stock
    FROM bom_items bi
    JOIN part_master p ON p.part_no = bi.component_part_no
    LEFT JOIN inventory inv ON inv.part_no = bi.component_part_no
    WHERE bi.bom_id = ?
    ORDER BY p.part_name
");
$childStmt->execute([$bom['id']]);
$children = $childStmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure numeric types
foreach ($children as &$c) {
    $c['bom_qty'] = (float)$c['bom_qty'];
    $c['current_stock'] = (int)$c['current_stock'];
}
unset($c);

echo json_encode([
    'bom_no' => $bom['bom_no'],
    'parent_part_no' => $bom['parent_part_no'],
    'parent_name' => $bom['parent_name'],
    'children' => $children
]);
