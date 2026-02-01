<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';
require '../lib/SimpleXLSX.php';

$errors = [];
$success_count = 0;
$error_rows = [];
$imported = false;

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

                    // Validate headers
                    $expected_headers = ['supplier_code', 'supplier_name', 'contact_person', 'phone', 'email', 'address1', 'address2', 'city', 'pincode', 'state', 'gstin'];

                    // Normalize headers for comparison
                    $normalized_headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $headers);

                    if ($normalized_headers !== $expected_headers) {
                        $errors[] = "Invalid file format. Headers don't match the template. Please use the provided template.";
                        $errors[] = "Expected: " . implode(', ', $expected_headers);
                    } else {
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
                                stripos($first_cell, 'GSTIN') !== false || preg_match('/^\d+\./', $first_cell) ||
                                strpos($first_cell, '-') === 0) {
                                continue;
                            }

                            // Extract data
                            $supplier_code = strtoupper(trim($data[0] ?? ''));
                            $supplier_name = trim($data[1] ?? '');
                            $contact_person = trim($data[2] ?? '');
                            $phone = trim($data[3] ?? '');
                            $email = trim($data[4] ?? '');
                            $address1 = trim($data[5] ?? '');
                            $address2 = trim($data[6] ?? '');
                            $city = trim($data[7] ?? '');
                            $pincode = trim($data[8] ?? '');
                            $state = trim($data[9] ?? '');
                            $gstin = strtoupper(trim($data[10] ?? ''));

                            // Validate required fields
                            $row_errors = [];

                            if ($supplier_code === '') {
                                $row_errors[] = "Supplier Code is required";
                            }

                            if ($supplier_name === '') {
                                $row_errors[] = "Supplier Name is required";
                            }

                            // Validate GSTIN format if provided
                            if (!empty($gstin) && strlen($gstin) !== 15) {
                                $row_errors[] = "GSTIN must be exactly 15 characters";
                            }

                            // Check for duplicate supplier_code in database
                            if (!empty($supplier_code) && empty($row_errors)) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE supplier_code = ?");
                                $stmt->execute([$supplier_code]);

                                if ($stmt->fetchColumn() > 0) {
                                    $row_errors[] = "Supplier Code already exists in database";
                                }
                            }

                            // If validation passed, insert the record
                            if (empty($row_errors)) {
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO suppliers
                                        (supplier_code, supplier_name, contact_person, phone, email, address1, address2, city, pincode, state, gstin)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ");

                                    $stmt->execute([
                                        $supplier_code,
                                        $supplier_name,
                                        $contact_person ?: null,
                                        $phone ?: null,
                                        $email ?: null,
                                        $address1 ?: null,
                                        $address2 ?: null,
                                        $city ?: null,
                                        $pincode ?: null,
                                        $state ?: null,
                                        $gstin ?: null
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
                                    'supplier_code' => $supplier_code ?: '(empty)',
                                    'supplier_name' => $supplier_name,
                                    'errors' => $row_errors
                                ];
                            }
                        }

                        $imported = true;
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
            max-width: 900px;
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
        .gstin-format {
            background: #e7f3ff;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 3px solid #0d6efd;
            margin-top: 15px;
        }
        .gstin-format code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>

    <div class="import-container">
        <div class="page-header">
            <h1>Import Suppliers from Excel</h1>
            <div class="btn-group">
                <a href="index.php" class="btn btn-secondary">Back to Suppliers</a>
                <a href="download_template.php" class="btn btn-primary">Download Template</a>
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
                <p><strong>Successfully imported:</strong> <?= $success_count ?> suppliers</p>
                <p><strong>Failed rows:</strong> <?= count($error_rows) ?></p>
            </div>

            <?php if (!empty($error_rows)): ?>
                <div class="error-list">
                    <h3>Errors Found</h3>
                    <?php foreach ($error_rows as $error_row): ?>
                        <div class="error-row">
                            <h4>Row <?= $error_row['row'] ?> - <?= htmlspecialchars($error_row['supplier_code']) ?> <?= $error_row['supplier_name'] ? '(' . htmlspecialchars($error_row['supplier_name']) . ')' : '' ?></h4>
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
                <p><a href="index.php" class="btn btn-primary">View Imported Suppliers</a></p>
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
                <button type="submit" class="btn btn-primary">Import Suppliers</button>
            </form>
        </div>

        <div class="instructions-box">
            <h3>Instructions</h3>
            <ol>
                <li>Download the Excel template file using the button above</li>
                <li>Open the .xlsx file in Microsoft Excel or any compatible application</li>
                <li>Delete the sample rows (keep only the header row)</li>
                <li>Fill in your supplier data following the format</li>
                <li>Save the file (keep it as .xlsx format)</li>
                <li>Upload the file using the form above</li>
            </ol>

            <h4>Field Reference</h4>
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
                        <td>supplier_code <span class="required-badge">Required</span></td>
                        <td>Unique supplier code (auto-converted to UPPERCASE)</td>
                        <td>SUP-001</td>
                    </tr>
                    <tr>
                        <td>supplier_name <span class="required-badge">Required</span></td>
                        <td>Company/Business name</td>
                        <td>Precision Engineering Works</td>
                    </tr>
                    <tr>
                        <td>contact_person <span class="optional-badge">Optional</span></td>
                        <td>Primary contact person name</td>
                        <td>Rajesh Kumar</td>
                    </tr>
                    <tr>
                        <td>phone <span class="optional-badge">Optional</span></td>
                        <td>Phone number</td>
                        <td>9876543210</td>
                    </tr>
                    <tr>
                        <td>email <span class="optional-badge">Optional</span></td>
                        <td>Email address</td>
                        <td>rajesh@company.com</td>
                    </tr>
                    <tr>
                        <td>address1 <span class="optional-badge">Optional</span></td>
                        <td>Street address, building name</td>
                        <td>45, MIDC Industrial Area</td>
                    </tr>
                    <tr>
                        <td>address2 <span class="optional-badge">Optional</span></td>
                        <td>Area, landmark</td>
                        <td>Phase 2, Sector 12</td>
                    </tr>
                    <tr>
                        <td>city <span class="optional-badge">Optional</span></td>
                        <td>City name</td>
                        <td>Pune</td>
                    </tr>
                    <tr>
                        <td>pincode <span class="optional-badge">Optional</span></td>
                        <td>PIN/ZIP code</td>
                        <td>411019</td>
                    </tr>
                    <tr>
                        <td>state <span class="optional-badge">Optional</span></td>
                        <td>State name</td>
                        <td>Maharashtra</td>
                    </tr>
                    <tr>
                        <td>gstin <span class="optional-badge">Optional</span></td>
                        <td>15-character GSTIN</td>
                        <td>27AABCP1234R1ZM</td>
                    </tr>
                </tbody>
            </table>

            <div class="gstin-format">
                <strong>GSTIN Format:</strong> 15 characters<br>
                <code>SS</code> State Code (2 digits) +
                <code>PPPPPPPPPP</code> PAN (10 chars) +
                <code>E</code> Entity (1 digit) +
                <code>Z</code> Default +
                <code>C</code> Checksum<br>
                <em>Example: <code>27AABCP1234R1ZM</code> (Maharashtra)</em>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
