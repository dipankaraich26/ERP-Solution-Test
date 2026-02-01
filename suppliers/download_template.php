<?php
// Download Excel template for Supplier bulk import
require '../lib/SimpleXLSXGen.php';

// Define headers matching the suppliers table structure
$headers = [
    'supplier_code',
    'supplier_name',
    'contact_person',
    'phone',
    'email',
    'address1',
    'address2',
    'city',
    'pincode',
    'state',
    'gstin'
];

// Sample data rows with realistic supplier examples
$sample_data = [
    [
        'SUP-001',
        'Precision Engineering Works',
        'Rajesh Kumar',
        '9876543210',
        'rajesh@precisioneng.com',
        '45, MIDC Industrial Area',
        'Phase 2, Sector 12',
        'Pune',
        '411019',
        'Maharashtra',
        '27AABCP1234R1ZM'
    ],
    [
        'SUP-002',
        'Gujarat Steel Traders',
        'Mehul Patel',
        '9123456789',
        'mehul@gujaratsteel.com',
        '789, Steel Market',
        'Ring Road',
        'Ahmedabad',
        '380015',
        'Gujarat',
        '24AABCG5678R1ZN'
    ],
    [
        'SUP-003',
        'South India Motors',
        'Venkatesh Reddy',
        '8765432109',
        'venkat@simotors.in',
        '123, Industrial Estate',
        'Peenya Layout',
        'Bengaluru',
        '560058',
        'Karnataka',
        '29AABCS9012R1ZO'
    ],
    [
        'SUP-004',
        'Delhi Auto Parts',
        'Amit Sharma',
        '9988776655',
        'amit@delhiautoparts.com',
        '56, Kashmere Gate',
        'Auto Market Complex',
        'New Delhi',
        '110006',
        'Delhi',
        '07AABCD3456R1ZP'
    ],
    [
        'SUP-005',
        'Chennai Hydraulics',
        'Suresh Iyer',
        '9876512340',
        'suresh@chennaihydraulics.in',
        '234, Ambattur Industrial Estate',
        'Near Railway Station',
        'Chennai',
        '600053',
        'Tamil Nadu',
        '33AABCH7890R1ZQ'
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
$excelData[] = ['1. Delete the sample rows above before importing your data'];
$excelData[] = ['2. supplier_code must be UNIQUE - duplicates will be rejected'];
$excelData[] = ['3. supplier_code will be converted to UPPERCASE automatically'];
$excelData[] = ['4. supplier_code and supplier_name are REQUIRED fields'];
$excelData[] = ['5. All other fields are optional'];
$excelData[] = [];
$excelData[] = ['GSTIN FORMAT:'];
$excelData[] = ['   - 15 characters: 2-digit state code + 10-char PAN + 1 entity code + 1 Z + 1 check digit'];
$excelData[] = ['   - Example: 27AABCP1234R1ZM'];
$excelData[] = [];
$excelData[] = ['Do not modify the header row'];

// Generate and download the Excel file
$xlsx = SimpleXLSXGen::fromArray($excelData, 'Supplier Template');
$xlsx->downloadAs('supplier_template.xlsx');
