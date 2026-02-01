<?php
// Download Excel template for Customer bulk import
require '../lib/SimpleXLSXGen.php';

// Define headers matching the updated customers table structure
$headers = [
    'company_name',
    'customer_name',
    'designation',
    'contact',
    'email',
    'customer_type',
    'industry',
    'secondary_contact_name',
    'address1',
    'address2',
    'city',
    'pincode',
    'state',
    'gstin'
];

// Sample data rows to guide users
$sample_data = [
    [
        'ABC Hospital Pvt Ltd',
        'Dr. Rahul Sharma',
        'Director',
        '9876543210',
        'rahul@abchospital.com',
        'B2B',
        'Multi-Specialty Hospital',
        'Priya Verma',
        '123, Healthcare Complex',
        'Sector 5',
        'Mumbai',
        '400001',
        'Maharashtra',
        '27AABCU9603R1ZM'
    ],
    [
        'XYZ Medical Supplies',
        'Mr. Amit Patel',
        'Purchase Manager',
        '9123456789',
        'amit@xyzmedical.com',
        'B2B',
        'Medical Equipment Dealer',
        '',
        '456, Business Park',
        'Phase 2',
        'Ahmedabad',
        '380001',
        'Gujarat',
        '24AABCU9603R1ZN'
    ],
    [
        'City Eye Hospital',
        'Dr. Neha Kumar',
        'Managing Director',
        '8765432109',
        'neha@cityeye.in',
        'B2B',
        'Eye Hospital',
        'Suresh Reddy',
        '789, Medical Hub',
        'Tower B',
        'Bengaluru',
        '560001',
        'Karnataka',
        '29AABCU9603R1ZO'
    ]
];

// Build the Excel data array
$excelData = [];
$excelData[] = $headers;

foreach ($sample_data as $row) {
    $excelData[] = $row;
}

// Add empty rows then instructions
$excelData[] = [];
$excelData[] = ['INSTRUCTIONS:'];
$excelData[] = ['1. Delete the sample rows above before importing'];
$excelData[] = ['2. contact (phone number) must be unique - duplicates will be skipped'];
$excelData[] = ['3. company_name and customer_name are REQUIRED fields'];
$excelData[] = ['4. customer_type: Use B2B for business customers, B2C for consumers (default: B2B)'];
$excelData[] = ['5. designation: Dr., Mr., Mrs., Ms., Director, Manager, Purchase Manager, CEO, etc.'];
$excelData[] = ['6. industry: Multi-Specialty Hospital, Eye Hospital, Medical Equipment Dealer, etc.'];
$excelData[] = ['7. GSTIN should be 15 characters if provided'];
$excelData[] = ['8. Customer ID will be auto-generated (CUST-1, CUST-2, etc.)'];
$excelData[] = ['9. Do not modify the header row'];

// Generate and download the Excel file
$xlsx = SimpleXLSXGen::fromArray($excelData, 'Customer Template');
$xlsx->downloadAs('customer_template.xlsx');
