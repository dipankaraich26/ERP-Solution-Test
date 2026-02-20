<?php
ob_start();
include "../db.php";
require '../lib/SimpleXLSXGen.php';

// Get the selected PO numbers from POST
$po_numbers_json = $_POST['po_numbers'] ?? '[]';
$po_numbers = json_decode($po_numbers_json, true);

if (empty($po_numbers) || !is_array($po_numbers)) {
    die("No purchase orders selected for export");
}

// Prepare placeholders for SQL IN clause
$placeholders = str_repeat('?,', count($po_numbers) - 1) . '?';

// Fetch all purchase order details
$stmt = $pdo->prepare("
    SELECT
        po.po_no,
        po.part_no,
        p.part_name,
        p.hsn_code,
        po.qty,
        po.rate,
        (po.qty * po.rate) AS line_total,
        po.purchase_date,
        po.status,
        s.supplier_name,
        s.supplier_code,
        s.city,
        s.state,
        s.phone,
        s.email
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.po_no IN ($placeholders)
    ORDER BY po.po_no, po.id
");
$stmt->execute($po_numbers);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    die("No records found for selected purchase orders");
}

// Build Excel data
$data = [];

// Headers
$data[] = [
    'PO Number',
    'Purchase Date',
    'Status',
    'Supplier Code',
    'Supplier Name',
    'Supplier City',
    'Supplier State',
    'Supplier Phone',
    'Supplier Email',
    'Part Number',
    'Part Name',
    'HSN Code',
    'Quantity',
    'Rate',
    'Line Total',
    'Currency'
];

// Data rows
$po_totals = [];
foreach ($records as $row) {
    $data[] = [
        $row['po_no'],
        $row['purchase_date'],
        ucfirst($row['status']),
        $row['supplier_code'],
        $row['supplier_name'],
        $row['city'] ?? '',
        $row['state'] ?? '',
        $row['phone'] ?? '',
        $row['email'] ?? '',
        $row['part_no'],
        $row['part_name'],
        $row['hsn_code'] ?? '',
        (int)$row['qty'],
        (float)$row['rate'],
        (float)$row['line_total'],
        'INR'
    ];

    if (!isset($po_totals[$row['po_no']])) {
        $po_totals[$row['po_no']] = 0;
    }
    $po_totals[$row['po_no']] += $row['line_total'];
}

// Add empty row
$data[] = array_fill(0, 16, '');

// Summary section
$data[] = ['SUMMARY', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
$data[] = ['PO Number', 'Total Value (INR)', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
foreach ($po_totals as $po_no => $total) {
    $data[] = [$po_no, (float)$total, '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
}
$data[] = array_fill(0, 16, '');
$data[] = ['Grand Total', (float)array_sum($po_totals), '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

// Generate filename
$filename = 'Purchase_Orders_' . date('Y-m-d_His') . '.xlsx';

ob_end_clean();

$xlsx = SimpleXLSXGen::fromArray($data, 'Purchase Orders');
$xlsx->downloadAs($filename);
