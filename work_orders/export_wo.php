<?php
include "../db.php";

// Filter parameters (same as index.php)
$filter_part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';
$filter_part_id = isset($_GET['part_id']) ? trim($_GET['part_id']) : '';
$filter_assigned_to = isset($_GET['assigned_to']) ? trim($_GET['assigned_to']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE clause
$where = [];
$params = [];

if ($filter_part_no !== '') {
    $where[] = "w.part_no LIKE ?";
    $params[] = "%$filter_part_no%";
}
if ($filter_part_id !== '') {
    $where[] = "p.part_id LIKE ?";
    $params[] = "%$filter_part_id%";
}
if ($filter_assigned_to !== '') {
    $where[] = "w.assigned_to = ?";
    $params[] = $filter_assigned_to;
}
if ($filter_status !== '') {
    $where[] = "w.status = ?";
    $params[] = $filter_status;
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

// Fetch work orders with filters
$stmt = $pdo->prepare("
    SELECT w.wo_no, w.part_no, p.part_id, COALESCE(p.part_name, w.part_no) as part_name,
           b.bom_no, w.qty, w.status, w.created_at,
           e.first_name, e.last_name, w.plan_id
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    LEFT JOIN bom_master b ON w.bom_id = b.id
    LEFT JOIN employees e ON w.assigned_to = e.id
    $whereSQL
    ORDER BY w.id DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    die("No work orders found for export");
}

// Set headers for CSV download
$filename = 'Work_Orders_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'WO Number',
    'Part No',
    'Part ID',
    'Product Name',
    'BOM',
    'Quantity',
    'Assigned To',
    'Status',
    'Source',
    'Date'
]);

// Write data rows
foreach ($records as $row) {
    $assignedTo = '';
    if ($row['first_name']) {
        $assignedTo = $row['first_name'] . ' ' . $row['last_name'];
    }

    fputcsv($output, [
        $row['wo_no'],
        $row['part_no'],
        $row['part_id'] ?? '',
        $row['part_name'],
        $row['bom_no'] ?? '',
        $row['qty'],
        $assignedTo,
        ucfirst(str_replace('_', ' ', $row['status'])),
        $row['plan_id'] ? 'Procurement' : 'Manual',
        date('Y-m-d', strtotime($row['created_at']))
    ]);
}

// Summary
fputcsv($output, []);
fputcsv($output, ['Total Work Orders:', count($records)]);

fclose($output);
exit;
