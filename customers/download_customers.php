<?php
/**
 * Download Customer Data to Excel (CSV)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple login check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include "../db.php";

// Get all customers
$stmt = $pdo->query("
    SELECT
        customer_id,
        company_name,
        customer_name,
        contact,
        email,
        address1,
        address2,
        city,
        pincode,
        state,
        gstin,
        status,
        created_at
    FROM customers
    ORDER BY company_name ASC
");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
$filename = 'customers_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel to properly recognize UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
$headers = [
    'Customer ID',
    'Company Name',
    'Contact Person',
    'Phone/Mobile',
    'Email',
    'Address Line 1',
    'Address Line 2',
    'City',
    'Pincode',
    'State',
    'GSTIN',
    'Status',
    'Created Date'
];
fputcsv($output, $headers);

// Write data rows
foreach ($customers as $customer) {
    $row = [
        $customer['customer_id'],
        $customer['company_name'],
        $customer['customer_name'],
        $customer['contact'],
        $customer['email'],
        $customer['address1'],
        $customer['address2'],
        $customer['city'],
        $customer['pincode'],
        $customer['state'],
        $customer['gstin'],
        $customer['status'],
        $customer['created_at'] ? date('d-m-Y', strtotime($customer['created_at'])) : ''
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
