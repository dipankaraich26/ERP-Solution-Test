<?php
// Download Excel template for Employee bulk import
require '../lib/SimpleXLSXGen.php';

// Define headers matching the employees table structure
$headers = [
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'marital_status',
    'blood_group',
    'phone',
    'alt_phone',
    'email',
    'personal_email',
    'address_line1',
    'address_line2',
    'city',
    'state',
    'pincode',
    'country',
    'emergency_contact_name',
    'emergency_contact_relation',
    'emergency_contact_phone',
    'department',
    'designation',
    'employment_type',
    'date_of_joining',
    'work_location',
    'aadhar_no',
    'pan_no',
    'uan_no',
    'pf_no',
    'esi_no',
    'bank_name',
    'bank_account',
    'bank_ifsc',
    'bank_branch',
    'basic_salary',
    'hra',
    'conveyance',
    'medical_allowance',
    'special_allowance',
    'other_allowance',
    'status',
    'notes'
];

// Sample data rows with realistic employee examples
$sample_data = [
    [
        'Rajesh',
        'Kumar',
        '15-06-1990',
        'Male',
        'Married',
        'O+',
        '9876543210',
        '9876543211',
        'rajesh.kumar@company.com',
        'rajesh.personal@gmail.com',
        '123, MG Road',
        'Near Metro Station',
        'Mumbai',
        'Maharashtra',
        '400001',
        'India',
        'Priya Kumar',
        'Spouse',
        '9876543212',
        'Engineering',
        'Senior Engineer',
        'Full-time',
        '15-01-2020',
        'Head Office',
        '234567891234',
        'ABCDE1234F',
        '100012345678',
        'MHPUN00001234',
        '1234567890',
        'HDFC Bank',
        '12345678901234',
        'HDFC0001234',
        'Andheri West',
        '35000',
        '14000',
        '1600',
        '1250',
        '5000',
        '2000',
        'Active',
        'Team lead for pump assembly'
    ],
    [
        'Priya',
        'Sharma',
        '22-08-1992',
        'Female',
        'Single',
        'B+',
        '9123456789',
        '',
        'priya.sharma@company.com',
        'priya92@gmail.com',
        '456, Linking Road',
        'Bandra West',
        'Mumbai',
        'Maharashtra',
        '400050',
        'India',
        'Amit Sharma',
        'Brother',
        '9123456780',
        'Quality Control',
        'QC Inspector',
        'Full-time',
        '10-03-2021',
        'Factory Unit 1',
        '345678912345',
        'BCDEF2345G',
        '100023456789',
        'MHPUN00002345',
        '2345678901',
        'ICICI Bank',
        '23456789012345',
        'ICIC0002345',
        'Bandra',
        '28000',
        '11200',
        '1600',
        '1250',
        '3500',
        '1500',
        'Active',
        'Quality inspection specialist'
    ],
    [
        'Amit',
        'Patel',
        '10-12-1988',
        'Male',
        'Married',
        'A+',
        '8765432109',
        '8765432108',
        'amit.patel@company.com',
        '',
        '789, Industrial Area',
        'Phase 2',
        'Ahmedabad',
        'Gujarat',
        '380015',
        'India',
        'Meena Patel',
        'Spouse',
        '8765432107',
        'Production',
        'Production Manager',
        'Full-time',
        '01-06-2018',
        'Factory Unit 2',
        '456789123456',
        'CDEFG3456H',
        '100034567890',
        'GJAHM00003456',
        '3456789012',
        'SBI',
        '34567890123456',
        'SBIN0003456',
        'Ashram Road',
        '45000',
        '18000',
        '2000',
        '1500',
        '8000',
        '3000',
        'Active',
        'Manages production line A and B'
    ],
    [
        'Sneha',
        'Reddy',
        '05-04-1995',
        'Female',
        'Single',
        'AB+',
        '7654321098',
        '',
        'sneha.reddy@company.com',
        'sneha.r95@gmail.com',
        '234, Jubilee Hills',
        'Road No. 10',
        'Hyderabad',
        'Telangana',
        '500033',
        'India',
        'Ravi Reddy',
        'Father',
        '7654321097',
        'HR',
        'HR Executive',
        'Full-time',
        '20-07-2022',
        'Head Office',
        '567891234567',
        'DEFGH4567I',
        '100045678901',
        'TSHYD00004567',
        '4567890123',
        'Axis Bank',
        '45678901234567',
        'UTIB0004567',
        'Jubilee Hills',
        '25000',
        '10000',
        '1600',
        '1250',
        '3000',
        '1200',
        'Active',
        'Handles recruitment and onboarding'
    ],
    [
        'Mohammed',
        'Ismail',
        '18-09-1985',
        'Male',
        'Married',
        'O-',
        '6543210987',
        '6543210986',
        'mohammed.ismail@company.com',
        '',
        '567, Residency Road',
        'Near Town Hall',
        'Bengaluru',
        'Karnataka',
        '560025',
        'India',
        'Fatima Ismail',
        'Spouse',
        '6543210985',
        'Maintenance',
        'Maintenance Supervisor',
        'Full-time',
        '05-02-2017',
        'Factory Unit 1',
        '678912345678',
        'EFGHI5678J',
        '100056789012',
        'KABAN00005678',
        '5678901234',
        'Canara Bank',
        '56789012345678',
        'CNRB0005678',
        'MG Road',
        '32000',
        '12800',
        '1800',
        '1250',
        '4500',
        '2200',
        'Active',
        'Equipment maintenance and repairs'
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
$excelData[] = ['2. phone must be UNIQUE - duplicates will be rejected'];
$excelData[] = ['3. first_name, phone, and date_of_joining are REQUIRED fields'];
$excelData[] = ['4. Employee IDs (emp_id) will be auto-generated during import'];
$excelData[] = [];
$excelData[] = ['DATE FORMAT:'];
$excelData[] = ['   - Use DD-MM-YYYY format (e.g., 15-06-1990)'];
$excelData[] = ['   - date_of_birth and date_of_joining must follow this format'];
$excelData[] = [];
$excelData[] = ['GENDER OPTIONS:'];
$excelData[] = ['   - Male'];
$excelData[] = ['   - Female'];
$excelData[] = ['   - Other'];
$excelData[] = [];
$excelData[] = ['MARITAL STATUS OPTIONS:'];
$excelData[] = ['   - Single'];
$excelData[] = ['   - Married'];
$excelData[] = ['   - Divorced'];
$excelData[] = ['   - Widowed'];
$excelData[] = [];
$excelData[] = ['BLOOD GROUP OPTIONS:'];
$excelData[] = ['   - A+, A-, B+, B-, AB+, AB-, O+, O-'];
$excelData[] = [];
$excelData[] = ['EMPLOYMENT TYPE OPTIONS:'];
$excelData[] = ['   - Full-time'];
$excelData[] = ['   - Part-time'];
$excelData[] = ['   - Contract'];
$excelData[] = ['   - Intern'];
$excelData[] = ['   - Trainee'];
$excelData[] = [];
$excelData[] = ['STATUS OPTIONS:'];
$excelData[] = ['   - Active'];
$excelData[] = ['   - On Leave'];
$excelData[] = ['   - Inactive'];
$excelData[] = [];
$excelData[] = ['ID DOCUMENT FORMATS:'];
$excelData[] = ['   - Aadhar Number: 12 digits (e.g., 234567891234)'];
$excelData[] = ['   - PAN Number: 10 characters alphanumeric (e.g., ABCDE1234F)'];
$excelData[] = ['   - UAN Number: 12 digits'];
$excelData[] = [];
$excelData[] = ['SALARY FIELDS:'];
$excelData[] = ['   - Enter numeric values only (no currency symbols)'];
$excelData[] = ['   - basic_salary, hra, conveyance, medical_allowance, special_allowance, other_allowance'];
$excelData[] = [];
$excelData[] = ['Do not modify the header row'];

// Generate and download the Excel file
$xlsx = SimpleXLSXGen::fromArray($excelData, 'Employee Template');
$xlsx->downloadAs('employee_import_template.xlsx');
