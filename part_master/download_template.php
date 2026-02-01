<?php
// Download Excel template for Part Master bulk import
require '../lib/SimpleXLSXGen.php';

// Define headers matching the part_master table structure
$headers = [
    'part_no',
    'part_name',
    'part_id',
    'description',
    'category',
    'uom',
    'rate',
    'hsn_code',
    'gst'
];

// Sample data rows with realistic examples
$sample_data = [
    [
        'PUMP-001',
        'Centrifugal Pump 2HP',
        'CP-2HP-SS',
        'Stainless Steel Centrifugal Pump 2HP Single Phase',
        'Brought Out',
        'Nos',
        15000.00,
        '8413',
        18.00
    ],
    [
        'MOTOR-002',
        'Electric Motor 5HP',
        'EM-5HP-3P',
        'Three Phase Electric Motor 5HP 1440 RPM',
        'Brought Out',
        'Nos',
        25000.00,
        '8501',
        18.00
    ],
    [
        'VALVE-003',
        'Ball Valve 2 inch',
        'BV-2IN-SS',
        'Stainless Steel Ball Valve 2 inch Flanged',
        'Brought Out',
        'Nos',
        2500.00,
        '8481',
        18.00
    ],
    [
        'PIPE-004',
        'SS Pipe 2 inch',
        'SSP-2IN',
        'Stainless Steel 304 Pipe 2 inch Schedule 40',
        'Manufacturing',
        'Mtr',
        850.00,
        '7306',
        18.00
    ],
    [
        'ASSY-005',
        'Filter Assembly Complete',
        'FA-100L',
        'Complete Filter Assembly 100 LPM capacity',
        'Assembly',
        'Set',
        45000.00,
        '8421',
        18.00
    ],
    [
        'FG-006',
        'Water Purifier RO',
        'WP-RO-25',
        'Reverse Osmosis Water Purifier 25 LPH',
        'Finished Good',
        'Nos',
        75000.00,
        '8421',
        18.00
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
$excelData[] = ['2. part_no must be UNIQUE - duplicates will be rejected'];
$excelData[] = ['3. part_no will be converted to UPPERCASE automatically'];
$excelData[] = [];
$excelData[] = ['CATEGORY OPTIONS (use exactly as shown):'];
$excelData[] = ['   - Assembly'];
$excelData[] = ['   - Brought Out'];
$excelData[] = ['   - Finished Good'];
$excelData[] = ['   - Manufacturing'];
$excelData[] = ['   - Printing'];
$excelData[] = [];
$excelData[] = ['UOM OPTIONS (common units):'];
$excelData[] = ['   - Nos (Numbers)'];
$excelData[] = ['   - Pcs (Pieces)'];
$excelData[] = ['   - Mtr (Meter)'];
$excelData[] = ['   - Kg (Kilogram)'];
$excelData[] = ['   - Ltr (Litre)'];
$excelData[] = ['   - Set'];
$excelData[] = ['   - Box'];
$excelData[] = ['   - Roll'];
$excelData[] = ['   - Pair'];
$excelData[] = [];
$excelData[] = ['FIELD REQUIREMENTS:'];
$excelData[] = ['   - part_no, part_name, part_id, description, category, uom, rate, gst are REQUIRED'];
$excelData[] = ['   - hsn_code is OPTIONAL'];
$excelData[] = ['   - rate and gst must be numeric values'];
$excelData[] = [];
$excelData[] = ['Do not modify the header row'];

// Generate and download the Excel file
$xlsx = SimpleXLSXGen::fromArray($excelData, 'Part Master Template');
$xlsx->downloadAs('part_master_template.xlsx');
