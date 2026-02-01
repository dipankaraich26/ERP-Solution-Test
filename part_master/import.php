<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';
require '../lib/SimpleXLSX.php';

$errors = [];
$success_count = 0;
$error_rows = [];
$imported = false;

// Valid categories
$valid_categories = ['Assembly', 'Brought Out', 'Finished Good', 'Manufacturing', 'Printing'];

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
                    $expected_headers = ['part_no', 'part_name', 'part_id', 'description', 'category', 'uom', 'rate', 'hsn_code', 'gst'];

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
                                stripos($first_cell, 'CATEGORY') !== false || stripos($first_cell, 'UOM') !== false ||
                                stripos($first_cell, 'FIELD') !== false || preg_match('/^\d+\./', $first_cell) ||
                                strpos($first_cell, '-') === 0) {
                                continue;
                            }

                            // Extract data
                            $part_no = strtoupper(trim($data[0] ?? ''));
                            $part_name = trim($data[1] ?? '');
                            $part_id = trim($data[2] ?? '');
                            $description = trim($data[3] ?? '');
                            $category = trim($data[4] ?? '');
                            $uom = trim($data[5] ?? '');
                            $rate = trim($data[6] ?? '');
                            $hsn_code = trim($data[7] ?? '');
                            $gst = trim($data[8] ?? '');

                            // Validate required fields
                            $row_errors = [];

                            if ($part_no === '') {
                                $row_errors[] = "Part No is required";
                            }

                            if ($part_name === '') {
                                $row_errors[] = "Part Name is required";
                            }

                            if ($part_id === '') {
                                $row_errors[] = "Part ID is required";
                            }

                            if ($description === '') {
                                $row_errors[] = "Description is required";
                            }

                            if ($category === '') {
                                $row_errors[] = "Category is required";
                            } elseif (!in_array($category, $valid_categories)) {
                                $row_errors[] = "Category must be: " . implode(', ', $valid_categories);
                            }

                            if ($uom === '') {
                                $row_errors[] = "UOM is required";
                            }

                            if ($rate === '' || !is_numeric($rate) || $rate < 0) {
                                $row_errors[] = "Rate must be a valid positive number";
                            }

                            if ($gst === '' || !is_numeric($gst) || $gst < 0) {
                                $row_errors[] = "GST must be a valid positive number (0-28)";
                            }

                            // Check for duplicate part_no in database
                            if (empty($row_errors)) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM part_master WHERE part_no = ?");
                                $stmt->execute([$part_no]);

                                if ($stmt->fetchColumn() > 0) {
                                    $row_errors[] = "Part No already exists in database";
                                }
                            }

                            // If validation passed, insert the record
                            if (empty($row_errors)) {
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO part_master
                                        (part_no, part_name, part_id, description, uom, category, rate, hsn_code, gst, status)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                                    ");

                                    $stmt->execute([
                                        $part_no,
                                        $part_name,
                                        $part_id,
                                        $description,
                                        $uom,
                                        $category,
                                        $rate,
                                        $hsn_code ?: null,
                                        $gst
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
                                    'part_no' => $part_no ?: '(empty)',
                                    'part_name' => $part_name,
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
            max-width: 950px;
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
        .category-list, .uom-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .category-tag, .uom-tag {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            color: #495057;
        }
    </style>

    <div class="import-container">
        <div class="page-header">
            <h1>Import Parts from Excel</h1>
            <div class="btn-group">
                <a href="list.php" class="btn btn-secondary">Back to Part Master</a>
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
                <p><strong>Successfully imported:</strong> <?= $success_count ?> parts</p>
                <p><strong>Failed rows:</strong> <?= count($error_rows) ?></p>
            </div>

            <?php if (!empty($error_rows)): ?>
                <div class="error-list">
                    <h3>Errors Found</h3>
                    <?php foreach ($error_rows as $error_row): ?>
                        <div class="error-row">
                            <h4>Row <?= $error_row['row'] ?> - <?= htmlspecialchars($error_row['part_no']) ?> <?= $error_row['part_name'] ? '(' . htmlspecialchars($error_row['part_name']) . ')' : '' ?></h4>
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
                <p><a href="list.php" class="btn btn-primary">View Imported Parts</a></p>
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
                <button type="submit" class="btn btn-primary">Import Parts</button>
            </form>
        </div>

        <div class="instructions-box">
            <h3>Instructions</h3>
            <ol>
                <li>Download the Excel template file using the button above</li>
                <li>Open the .xlsx file in Microsoft Excel or any compatible application</li>
                <li>Delete the sample rows (keep only the header row)</li>
                <li>Fill in your part data following the format</li>
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
                        <td>part_no <span class="required-badge">Required</span></td>
                        <td>Unique part number (auto-converted to UPPERCASE)</td>
                        <td>PUMP-001</td>
                    </tr>
                    <tr>
                        <td>part_name <span class="required-badge">Required</span></td>
                        <td>Name of the part</td>
                        <td>Centrifugal Pump 2HP</td>
                    </tr>
                    <tr>
                        <td>part_id <span class="required-badge">Required</span></td>
                        <td>Part identification code</td>
                        <td>CP-2HP-SS</td>
                    </tr>
                    <tr>
                        <td>description <span class="required-badge">Required</span></td>
                        <td>Detailed part description</td>
                        <td>Stainless Steel Centrifugal Pump 2HP Single Phase</td>
                    </tr>
                    <tr>
                        <td>category <span class="required-badge">Required</span></td>
                        <td>Part category (see options below)</td>
                        <td>Brought Out</td>
                    </tr>
                    <tr>
                        <td>uom <span class="required-badge">Required</span></td>
                        <td>Unit of measurement (see options below)</td>
                        <td>Nos</td>
                    </tr>
                    <tr>
                        <td>rate <span class="required-badge">Required</span></td>
                        <td>Price per unit (numeric)</td>
                        <td>15000.00</td>
                    </tr>
                    <tr>
                        <td>hsn_code <span class="optional-badge">Optional</span></td>
                        <td>HSN Code for GST</td>
                        <td>8413</td>
                    </tr>
                    <tr>
                        <td>gst <span class="required-badge">Required</span></td>
                        <td>GST percentage (0, 5, 12, 18, or 28)</td>
                        <td>18</td>
                    </tr>
                </tbody>
            </table>

            <h4>Valid Categories</h4>
            <div class="category-list">
                <?php foreach ($valid_categories as $cat): ?>
                    <span class="category-tag"><?= $cat ?></span>
                <?php endforeach; ?>
            </div>

            <h4>Common UOM Values</h4>
            <div class="uom-list">
                <span class="uom-tag">Nos</span>
                <span class="uom-tag">Pcs</span>
                <span class="uom-tag">Mtr</span>
                <span class="uom-tag">Kg</span>
                <span class="uom-tag">Gm</span>
                <span class="uom-tag">Ltr</span>
                <span class="uom-tag">Ml</span>
                <span class="uom-tag">Set</span>
                <span class="uom-tag">Box</span>
                <span class="uom-tag">Roll</span>
                <span class="uom-tag">Pair</span>
                <span class="uom-tag">Ft</span>
                <span class="uom-tag">Sqm</span>
                <span class="uom-tag">Sqft</span>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
