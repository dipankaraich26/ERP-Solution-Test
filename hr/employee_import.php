<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';
require '../lib/SimpleXLSX.php';

$errors = [];
$success_count = 0;
$error_rows = [];
$imported = false;

// Valid options for validation
$valid_genders = ['Male', 'Female', 'Other'];
$valid_marital = ['Single', 'Married', 'Divorced', 'Widowed'];
$valid_employment = ['Full-time', 'Part-time', 'Contract', 'Intern', 'Trainee'];
$valid_status = ['Active', 'On Leave', 'Inactive'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {

    // Validate file upload
    if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed. Please try again.";
    } else {
        $file_tmp = $_FILES['import_file']['tmp_name'];
        $file_name = $_FILES['import_file']['name'];

        // Check file extension
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $errors[] = "Only Excel files (.xlsx) are allowed. Please download the template and use that format.";
        } else {
            // Process the Excel file
            $xlsx = SimpleXLSX::parse($file_tmp);

            if (!$xlsx) {
                $errors[] = "Failed to parse Excel file. Please make sure it's a valid .xlsx file.";
            } else {
                $rows = $xlsx->rows();

                if (count($rows) < 2) {
                    $errors[] = "The file appears to be empty or has no data rows.";
                } else {
                    // Read header row
                    $headers = $rows[0];

                    // Expected headers
                    $expected_headers = [
                        'first_name', 'last_name', 'date_of_birth', 'gender', 'marital_status', 'blood_group',
                        'phone', 'alt_phone', 'email', 'personal_email',
                        'address_line1', 'address_line2', 'city', 'state', 'pincode', 'country',
                        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                        'department', 'designation', 'employment_type', 'date_of_joining', 'work_location',
                        'aadhar_no', 'pan_no', 'uan_no', 'pf_no', 'esi_no',
                        'bank_name', 'bank_account', 'bank_ifsc', 'bank_branch',
                        'basic_salary', 'hra', 'conveyance', 'medical_allowance', 'special_allowance', 'other_allowance',
                        'status', 'notes'
                    ];

                    // Normalize headers for comparison
                    $normalized_headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $headers);

                    if ($normalized_headers !== $expected_headers) {
                        $errors[] = "Invalid file format. Headers don't match the template. Please use the provided template.";
                        $errors[] = "Expected: " . implode(', ', array_slice($expected_headers, 0, 10)) . "...";
                    } else {
                        $pdo->beginTransaction();

                        try {
                            // Process each data row (skip header row)
                            for ($i = 1; $i < count($rows); $i++) {
                                $data = $rows[$i];
                                $row_number = $i + 1;

                                // Skip empty rows
                                if (empty(array_filter($data))) {
                                    continue;
                                }

                                // Skip instruction rows
                                $first_cell = trim($data[0] ?? '');
                                if (empty($first_cell) || stripos($first_cell, 'INSTRUCTIONS') !== false ||
                                    stripos($first_cell, 'DATE FORMAT') !== false || stripos($first_cell, 'GENDER') !== false ||
                                    stripos($first_cell, 'MARITAL') !== false || stripos($first_cell, 'BLOOD') !== false ||
                                    stripos($first_cell, 'EMPLOYMENT') !== false || stripos($first_cell, 'STATUS') !== false ||
                                    stripos($first_cell, 'SALARY') !== false || preg_match('/^\\d+\\./', $first_cell) ||
                                    strpos($first_cell, '-') === 0 || strpos($first_cell, 'Do not') !== false) {
                                    continue;
                                }

                                // Extract data
                                $first_name = trim($data[0] ?? '');
                                $last_name = trim($data[1] ?? '');
                                $date_of_birth = trim($data[2] ?? '');
                                $gender = trim($data[3] ?? 'Male');
                                $marital_status = trim($data[4] ?? 'Single');
                                $blood_group = trim($data[5] ?? '');
                                $phone = trim($data[6] ?? '');
                                $alt_phone = trim($data[7] ?? '');
                                $email = trim($data[8] ?? '');
                                $personal_email = trim($data[9] ?? '');
                                $address_line1 = trim($data[10] ?? '');
                                $address_line2 = trim($data[11] ?? '');
                                $city = trim($data[12] ?? '');
                                $state = trim($data[13] ?? '');
                                $pincode = trim($data[14] ?? '');
                                $country = trim($data[15] ?? 'India');
                                $emergency_contact_name = trim($data[16] ?? '');
                                $emergency_contact_relation = trim($data[17] ?? '');
                                $emergency_contact_phone = trim($data[18] ?? '');
                                $department = trim($data[19] ?? '');
                                $designation = trim($data[20] ?? '');
                                $employment_type = trim($data[21] ?? 'Full-time');
                                $date_of_joining = trim($data[22] ?? '');
                                $work_location = trim($data[23] ?? '');
                                $aadhar_no = trim($data[24] ?? '');
                                $pan_no = strtoupper(trim($data[25] ?? ''));
                                $uan_no = trim($data[26] ?? '');
                                $pf_no = trim($data[27] ?? '');
                                $esi_no = trim($data[28] ?? '');
                                $bank_name = trim($data[29] ?? '');
                                $bank_account = trim($data[30] ?? '');
                                $bank_ifsc = strtoupper(trim($data[31] ?? ''));
                                $bank_branch = trim($data[32] ?? '');
                                $basic_salary = floatval($data[33] ?? 0);
                                $hra = floatval($data[34] ?? 0);
                                $conveyance = floatval($data[35] ?? 0);
                                $medical_allowance = floatval($data[36] ?? 0);
                                $special_allowance = floatval($data[37] ?? 0);
                                $other_allowance = floatval($data[38] ?? 0);
                                $status = trim($data[39] ?? 'Active');
                                $notes = trim($data[40] ?? '');

                                // Validate required fields
                                $row_errors = [];

                                if ($first_name === '') {
                                    $row_errors[] = "First Name is required";
                                }

                                if ($phone === '') {
                                    $row_errors[] = "Phone is required";
                                }

                                if ($date_of_joining === '') {
                                    $row_errors[] = "Date of Joining is required";
                                }

                                // Check for duplicate phone number
                                if (!empty($phone) && empty($row_errors)) {
                                    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE phone = ? AND status != 'Inactive'");
                                    $stmt->execute([$phone]);
                                    $existing = $stmt->fetch();

                                    if ($existing) {
                                        $row_errors[] = "Phone number already exists for: " . $existing['first_name'] . " " . $existing['last_name'];
                                    }
                                }

                                // Validate and convert date formats from DD-MM-YYYY to YYYY-MM-DD
                                if (!empty($date_of_birth)) {
                                    if (preg_match('/^(\\d{2})-(\\d{2})-(\\d{4})$/', $date_of_birth, $matches)) {
                                        $date_of_birth = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                                    } else {
                                        $row_errors[] = "Invalid Date of Birth format (use DD-MM-YYYY)";
                                    }
                                }

                                if (!empty($date_of_joining)) {
                                    if (preg_match('/^(\\d{2})-(\\d{2})-(\\d{4})$/', $date_of_joining, $matches)) {
                                        $date_of_joining = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                                    } else {
                                        $row_errors[] = "Invalid Date of Joining format (use DD-MM-YYYY)";
                                    }
                                }

                                // Validate Aadhar number if provided
                                if (!empty($aadhar_no) && strlen($aadhar_no) !== 12) {
                                    $row_errors[] = "Aadhar Number must be exactly 12 digits";
                                }

                                // Validate PAN number if provided
                                if (!empty($pan_no) && strlen($pan_no) !== 10) {
                                    $row_errors[] = "PAN Number must be exactly 10 characters";
                                }

                                // Validate enum values (default if invalid)
                                if (!empty($gender) && !in_array($gender, $valid_genders)) {
                                    $gender = 'Male';
                                }
                                if (!empty($marital_status) && !in_array($marital_status, $valid_marital)) {
                                    $marital_status = 'Single';
                                }
                                if (!empty($employment_type) && !in_array($employment_type, $valid_employment)) {
                                    $employment_type = 'Full-time';
                                }
                                if (!empty($status) && !in_array($status, $valid_status)) {
                                    $status = 'Active';
                                }

                                // If validation passed, insert the record
                                if (empty($row_errors)) {
                                    try {
                                        // Generate Employee ID
                                        $maxId = $pdo->query("SELECT MAX(CAST(SUBSTRING(emp_id, 5) AS UNSIGNED)) FROM employees WHERE emp_id LIKE 'EMP-%'")->fetchColumn();
                                        $emp_id = 'EMP-' . str_pad(($maxId ?: 0) + 1, 4, '0', STR_PAD_LEFT);

                                        $stmt = $pdo->prepare("
                                            INSERT INTO employees (
                                                emp_id, first_name, last_name, date_of_birth, gender, marital_status, blood_group,
                                                phone, alt_phone, email, personal_email,
                                                address_line1, address_line2, city, state, pincode, country,
                                                emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                                                department, designation, employment_type, date_of_joining, work_location,
                                                aadhar_no, pan_no, uan_no, pf_no, esi_no,
                                                bank_name, bank_account, bank_ifsc, bank_branch,
                                                basic_salary, hra, conveyance, medical_allowance, special_allowance, other_allowance,
                                                status, notes
                                            ) VALUES (
                                                ?, ?, ?, ?, ?, ?, ?,
                                                ?, ?, ?, ?,
                                                ?, ?, ?, ?, ?, ?,
                                                ?, ?, ?,
                                                ?, ?, ?, ?, ?,
                                                ?, ?, ?, ?, ?,
                                                ?, ?, ?, ?,
                                                ?, ?, ?, ?, ?, ?,
                                                ?, ?
                                            )
                                        ");

                                        $stmt->execute([
                                            $emp_id,
                                            $first_name,
                                            $last_name ?: null,
                                            !empty($date_of_birth) ? $date_of_birth : null,
                                            $gender,
                                            $marital_status,
                                            $blood_group ?: null,
                                            $phone,
                                            $alt_phone ?: null,
                                            $email ?: null,
                                            $personal_email ?: null,
                                            $address_line1 ?: null,
                                            $address_line2 ?: null,
                                            $city ?: null,
                                            $state ?: null,
                                            $pincode ?: null,
                                            $country ?: 'India',
                                            $emergency_contact_name ?: null,
                                            $emergency_contact_relation ?: null,
                                            $emergency_contact_phone ?: null,
                                            $department ?: null,
                                            $designation ?: null,
                                            $employment_type,
                                            $date_of_joining,
                                            $work_location ?: null,
                                            $aadhar_no ?: null,
                                            $pan_no ?: null,
                                            $uan_no ?: null,
                                            $pf_no ?: null,
                                            $esi_no ?: null,
                                            $bank_name ?: null,
                                            $bank_account ?: null,
                                            $bank_ifsc ?: null,
                                            $bank_branch ?: null,
                                            $basic_salary,
                                            $hra,
                                            $conveyance,
                                            $medical_allowance,
                                            $special_allowance,
                                            $other_allowance,
                                            $status,
                                            $notes ?: null
                                        ]);

                                        $success_count++;
                                    } catch (PDOException $e) {
                                        $row_errors[] = "Database error: " . $e->getMessage();
                                    }
                                }

                                // Store errors for this row
                                if (!empty($row_errors)) {
                                    $error_rows[] = [
                                        'row' => $row_number,
                                        'name' => $first_name . ($last_name ? ' ' . $last_name : ''),
                                        'phone' => $phone ?: '(empty)',
                                        'errors' => $row_errors
                                    ];
                                }
                            }

                            $pdo->commit();
                            $imported = true;

                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $errors[] = "Database error: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<div class="content">
    <style>
        .import-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .import-stats {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .import-stats h3 {
            margin: 0 0 10px 0;
            color: #155724;
        }
        .error-list {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 15px 0;
            border-radius: 6px;
        }
        .error-list h3 {
            margin: 0 0 15px 0;
            color: #721c24;
        }
        .error-row {
            margin-bottom: 15px;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
        }
        .error-row h4 {
            margin: 0 0 8px 0;
            color: #dc3545;
            font-size: 0.95em;
        }
        .error-row ul {
            margin: 5px 0 0 20px;
            color: #721c24;
        }
        .instructions-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .instructions-box h3 {
            margin: 0 0 15px 0;
            color: #856404;
        }
        .instructions-box h4 {
            margin: 15px 0 10px 0;
            color: #856404;
        }
        .instructions-box ol, .instructions-box ul {
            margin: 0 0 0 20px;
        }
        .instructions-box li {
            margin-bottom: 5px;
        }
        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            font-size: 0.9em;
        }
        .field-table th, .field-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .field-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .field-table tr:last-child td {
            border-bottom: none;
        }
        .required-badge {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        .optional-badge {
            background: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        .upload-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
            margin: 20px 0;
        }
        .upload-form:hover {
            border-color: #667eea;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .option-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .option-tag {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            color: #495057;
        }
        .date-format-box {
            background: #e7f3ff;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 3px solid #0d6efd;
            margin-top: 15px;
        }
        .date-format-box code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 20px 0 10px 0;
            font-weight: 600;
        }
    </style>

    <div class="import-container">
        <div class="page-header">
            <h1>Import Employees from Excel</h1>
            <div class="btn-group">
                <a href="employees.php" class="btn btn-secondary">Back to Employees</a>
                <a href="download_employee_template.php" class="btn btn-primary">Download Template</a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul style="margin: 0;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($imported): ?>
            <div class="import-stats">
                <h3>Import Results</h3>
                <p><strong>Successfully imported:</strong> <?= $success_count ?> employee<?= $success_count != 1 ? 's' : '' ?></p>
                <p><strong>Failed rows:</strong> <?= count($error_rows) ?></p>
            </div>

            <?php if (!empty($error_rows)): ?>
                <div class="error-list">
                    <h3>Errors Found</h3>
                    <?php foreach ($error_rows as $error_row): ?>
                        <div class="error-row">
                            <h4>Row <?= $error_row['row'] ?> - <?= htmlspecialchars($error_row['name']) ?> (Phone: <?= htmlspecialchars($error_row['phone']) ?>)</h4>
                            <ul>
                                <?php foreach ($error_row['errors'] as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_count > 0): ?>
                <p><a href="employees.php" class="btn btn-primary">View Imported Employees</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="upload-form">
            <h2 style="margin-top: 0;">Upload Excel File</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Select Excel File (.xlsx)</label>
                    <input type="file" name="import_file" accept=".xlsx" required
                           style="padding: 10px; border: 1px solid #ced4da; border-radius: 6px; width: 100%; max-width: 400px;">
                </div>
                <button type="submit" class="btn btn-primary">Import Employees</button>
            </form>
        </div>

        <div class="instructions-box">
            <h3>Instructions</h3>
            <ol>
                <li>Download the Excel template file using the button above</li>
                <li>Open the .xlsx file in Microsoft Excel or any compatible application</li>
                <li>Delete the sample rows (keep only the header row)</li>
                <li>Fill in your employee data following the format</li>
                <li>Save the file (keep it as .xlsx format)</li>
                <li>Upload the file using the form above</li>
            </ol>

            <div class="date-format-box">
                <strong>Date Format:</strong> DD-MM-YYYY<br>
                Example: <code>15-06-1990</code> for 15th June 1990<br>
                <em>Both date_of_birth and date_of_joining must follow this format</em>
            </div>
        </div>

        <!-- Personal Information Fields -->
        <div class="section-header">Personal Information</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>first_name <span class="required-badge">Required</span></td>
                    <td>Employee's first name</td>
                    <td>Rajesh</td>
                </tr>
                <tr>
                    <td>last_name <span class="optional-badge">Optional</span></td>
                    <td>Employee's last name</td>
                    <td>Kumar</td>
                </tr>
                <tr>
                    <td>date_of_birth <span class="optional-badge">Optional</span></td>
                    <td>Date of birth (DD-MM-YYYY)</td>
                    <td>15-06-1990</td>
                </tr>
                <tr>
                    <td>gender <span class="optional-badge">Optional</span></td>
                    <td>Male / Female / Other</td>
                    <td>Male</td>
                </tr>
                <tr>
                    <td>marital_status <span class="optional-badge">Optional</span></td>
                    <td>Single / Married / Divorced / Widowed</td>
                    <td>Married</td>
                </tr>
                <tr>
                    <td>blood_group <span class="optional-badge">Optional</span></td>
                    <td>A+, A-, B+, B-, AB+, AB-, O+, O-</td>
                    <td>O+</td>
                </tr>
            </tbody>
        </table>

        <!-- Contact Information Fields -->
        <div class="section-header">Contact Information</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>phone <span class="required-badge">Required</span></td>
                    <td>Primary phone number (must be unique)</td>
                    <td>9876543210</td>
                </tr>
                <tr>
                    <td>alt_phone <span class="optional-badge">Optional</span></td>
                    <td>Alternate phone number</td>
                    <td>9876543211</td>
                </tr>
                <tr>
                    <td>email <span class="optional-badge">Optional</span></td>
                    <td>Official email address</td>
                    <td>rajesh@company.com</td>
                </tr>
                <tr>
                    <td>personal_email <span class="optional-badge">Optional</span></td>
                    <td>Personal email address</td>
                    <td>rajesh.personal@gmail.com</td>
                </tr>
            </tbody>
        </table>

        <!-- Address Fields -->
        <div class="section-header">Address</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>address_line1 <span class="optional-badge">Optional</span></td>
                    <td>Street address, building name</td>
                    <td>123, MG Road</td>
                </tr>
                <tr>
                    <td>address_line2 <span class="optional-badge">Optional</span></td>
                    <td>Area, landmark</td>
                    <td>Near Metro Station</td>
                </tr>
                <tr>
                    <td>city <span class="optional-badge">Optional</span></td>
                    <td>City name</td>
                    <td>Mumbai</td>
                </tr>
                <tr>
                    <td>state <span class="optional-badge">Optional</span></td>
                    <td>State name</td>
                    <td>Maharashtra</td>
                </tr>
                <tr>
                    <td>pincode <span class="optional-badge">Optional</span></td>
                    <td>PIN code</td>
                    <td>400001</td>
                </tr>
                <tr>
                    <td>country <span class="optional-badge">Optional</span></td>
                    <td>Country (defaults to India)</td>
                    <td>India</td>
                </tr>
            </tbody>
        </table>

        <!-- Emergency Contact Fields -->
        <div class="section-header">Emergency Contact</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>emergency_contact_name <span class="optional-badge">Optional</span></td>
                    <td>Name of emergency contact</td>
                    <td>Priya Kumar</td>
                </tr>
                <tr>
                    <td>emergency_contact_relation <span class="optional-badge">Optional</span></td>
                    <td>Relationship</td>
                    <td>Spouse</td>
                </tr>
                <tr>
                    <td>emergency_contact_phone <span class="optional-badge">Optional</span></td>
                    <td>Emergency contact phone</td>
                    <td>9876543212</td>
                </tr>
            </tbody>
        </table>

        <!-- Employment Fields -->
        <div class="section-header">Employment Details</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>department <span class="optional-badge">Optional</span></td>
                    <td>Department name</td>
                    <td>Engineering</td>
                </tr>
                <tr>
                    <td>designation <span class="optional-badge">Optional</span></td>
                    <td>Job title/designation</td>
                    <td>Senior Engineer</td>
                </tr>
                <tr>
                    <td>employment_type <span class="optional-badge">Optional</span></td>
                    <td>Full-time / Part-time / Contract / Intern / Trainee</td>
                    <td>Full-time</td>
                </tr>
                <tr>
                    <td>date_of_joining <span class="required-badge">Required</span></td>
                    <td>Joining date (DD-MM-YYYY)</td>
                    <td>15-01-2020</td>
                </tr>
                <tr>
                    <td>work_location <span class="optional-badge">Optional</span></td>
                    <td>Work location/office</td>
                    <td>Head Office</td>
                </tr>
            </tbody>
        </table>

        <!-- ID Documents Fields -->
        <div class="section-header">ID Documents</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>aadhar_no <span class="optional-badge">Optional</span></td>
                    <td>12-digit Aadhar number</td>
                    <td>234567891234</td>
                </tr>
                <tr>
                    <td>pan_no <span class="optional-badge">Optional</span></td>
                    <td>10-character PAN number</td>
                    <td>ABCDE1234F</td>
                </tr>
                <tr>
                    <td>uan_no <span class="optional-badge">Optional</span></td>
                    <td>Universal Account Number</td>
                    <td>100012345678</td>
                </tr>
                <tr>
                    <td>pf_no <span class="optional-badge">Optional</span></td>
                    <td>PF Account Number</td>
                    <td>MHPUN00001234</td>
                </tr>
                <tr>
                    <td>esi_no <span class="optional-badge">Optional</span></td>
                    <td>ESI Number</td>
                    <td>1234567890</td>
                </tr>
            </tbody>
        </table>

        <!-- Bank Details Fields -->
        <div class="section-header">Bank Details</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>bank_name <span class="optional-badge">Optional</span></td>
                    <td>Bank name</td>
                    <td>HDFC Bank</td>
                </tr>
                <tr>
                    <td>bank_account <span class="optional-badge">Optional</span></td>
                    <td>Account number</td>
                    <td>12345678901234</td>
                </tr>
                <tr>
                    <td>bank_ifsc <span class="optional-badge">Optional</span></td>
                    <td>IFSC Code (auto-uppercase)</td>
                    <td>HDFC0001234</td>
                </tr>
                <tr>
                    <td>bank_branch <span class="optional-badge">Optional</span></td>
                    <td>Branch name</td>
                    <td>Andheri West</td>
                </tr>
            </tbody>
        </table>

        <!-- Salary Fields -->
        <div class="section-header">Salary Components</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>basic_salary <span class="optional-badge">Optional</span></td>
                    <td>Basic salary amount</td>
                    <td>35000</td>
                </tr>
                <tr>
                    <td>hra <span class="optional-badge">Optional</span></td>
                    <td>House Rent Allowance</td>
                    <td>14000</td>
                </tr>
                <tr>
                    <td>conveyance <span class="optional-badge">Optional</span></td>
                    <td>Conveyance allowance</td>
                    <td>1600</td>
                </tr>
                <tr>
                    <td>medical_allowance <span class="optional-badge">Optional</span></td>
                    <td>Medical allowance</td>
                    <td>1250</td>
                </tr>
                <tr>
                    <td>special_allowance <span class="optional-badge">Optional</span></td>
                    <td>Special allowance</td>
                    <td>5000</td>
                </tr>
                <tr>
                    <td>other_allowance <span class="optional-badge">Optional</span></td>
                    <td>Other allowances</td>
                    <td>2000</td>
                </tr>
            </tbody>
        </table>

        <!-- Status and Notes -->
        <div class="section-header">Status & Notes</div>
        <table class="field-table">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>status <span class="optional-badge">Optional</span></td>
                    <td>Active / On Leave / Inactive</td>
                    <td>Active</td>
                </tr>
                <tr>
                    <td>notes <span class="optional-badge">Optional</span></td>
                    <td>Additional notes about employee</td>
                    <td>Team lead for pump assembly</td>
                </tr>
            </tbody>
        </table>

        <!-- Valid Options Summary -->
        <div class="instructions-box" style="margin-top: 25px;">
            <h4>Valid Option Values</h4>

            <strong>Gender</strong>
            <div class="option-list">
                <?php foreach ($valid_genders as $opt): ?>
                    <span class="option-tag"><?= $opt ?></span>
                <?php endforeach; ?>
            </div>

            <strong style="display: block; margin-top: 15px;">Marital Status</strong>
            <div class="option-list">
                <?php foreach ($valid_marital as $opt): ?>
                    <span class="option-tag"><?= $opt ?></span>
                <?php endforeach; ?>
            </div>

            <strong style="display: block; margin-top: 15px;">Employment Type</strong>
            <div class="option-list">
                <?php foreach ($valid_employment as $opt): ?>
                    <span class="option-tag"><?= $opt ?></span>
                <?php endforeach; ?>
            </div>

            <strong style="display: block; margin-top: 15px;">Status</strong>
            <div class="option-list">
                <?php foreach ($valid_status as $opt): ?>
                    <span class="option-tag"><?= $opt ?></span>
                <?php endforeach; ?>
            </div>

            <strong style="display: block; margin-top: 15px;">Blood Group</strong>
            <div class="option-list">
                <span class="option-tag">A+</span>
                <span class="option-tag">A-</span>
                <span class="option-tag">B+</span>
                <span class="option-tag">B-</span>
                <span class="option-tag">AB+</span>
                <span class="option-tag">AB-</span>
                <span class="option-tag">O+</span>
                <span class="option-tag">O-</span>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
