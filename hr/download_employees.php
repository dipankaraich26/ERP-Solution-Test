<?php
require '../db.php';
require '../lib/SimpleXLSXGen.php';

// Filters (same as employees.php)
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "e.status = ?";
    $params[] = $status;
}
if ($department) {
    $where[] = "e.department = ?";
    $params[] = $department;
}
if ($search) {
    $where[] = "(e.emp_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT e.*
    FROM employees e
    WHERE $whereClause
    ORDER BY e.emp_id
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build Excel data
$data = [];

// Headers
$data[] = [
    'Emp ID',
    'First Name',
    'Last Name',
    'Date of Birth',
    'Gender',
    'Marital Status',
    'Blood Group',
    'Phone',
    'Alt Phone',
    'Email',
    'Personal Email',
    'Address Line 1',
    'Address Line 2',
    'City',
    'State',
    'Pincode',
    'Country',
    'Emergency Contact Name',
    'Emergency Contact Relation',
    'Emergency Contact Phone',
    'Department',
    'Designation',
    'Employment Type',
    'Date of Joining',
    'Work Location',
    'Aadhar No',
    'PAN No',
    'UAN No',
    'PF No',
    'ESI No',
    'Bank Name',
    'Bank Account',
    'Bank IFSC',
    'Bank Branch',
    'Basic Salary',
    'HRA',
    'Conveyance',
    'Medical Allowance',
    'Special Allowance',
    'Other Allowance',
    'Status',
    'Notes'
];

// Data rows
foreach ($employees as $emp) {
    $data[] = [
        $emp['emp_id'] ?? '',
        $emp['first_name'] ?? '',
        $emp['last_name'] ?? '',
        $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '',
        $emp['gender'] ?? '',
        $emp['marital_status'] ?? '',
        $emp['blood_group'] ?? '',
        $emp['phone'] ?? '',
        $emp['alt_phone'] ?? '',
        $emp['email'] ?? '',
        $emp['personal_email'] ?? '',
        $emp['address_line1'] ?? '',
        $emp['address_line2'] ?? '',
        $emp['city'] ?? '',
        $emp['state'] ?? '',
        $emp['pincode'] ?? '',
        $emp['country'] ?? '',
        $emp['emergency_contact_name'] ?? '',
        $emp['emergency_contact_relation'] ?? '',
        $emp['emergency_contact_phone'] ?? '',
        $emp['department'] ?? '',
        $emp['designation'] ?? '',
        $emp['employment_type'] ?? '',
        $emp['date_of_joining'] ? date('d-m-Y', strtotime($emp['date_of_joining'])) : '',
        $emp['work_location'] ?? '',
        $emp['aadhar_no'] ?? '',
        $emp['pan_no'] ?? '',
        $emp['uan_no'] ?? '',
        $emp['pf_no'] ?? '',
        $emp['esi_no'] ?? '',
        $emp['bank_name'] ?? '',
        $emp['bank_account'] ?? '',
        $emp['bank_ifsc'] ?? '',
        $emp['bank_branch'] ?? '',
        $emp['basic_salary'] ?? 0,
        $emp['hra'] ?? 0,
        $emp['conveyance'] ?? 0,
        $emp['medical_allowance'] ?? 0,
        $emp['special_allowance'] ?? 0,
        $emp['other_allowance'] ?? 0,
        $emp['status'] ?? '',
        $emp['notes'] ?? ''
    ];
}

// Generate filename with date
$filename = 'employees_' . date('Y-m-d_His');
if ($status) $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', $status);
if ($department) $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', $department);
$filename .= '.xlsx';

// Generate and download Excel
$xlsx = SimpleXLSXGen::fromArray($data, 'Employees');
$xlsx->downloadAs($filename);
