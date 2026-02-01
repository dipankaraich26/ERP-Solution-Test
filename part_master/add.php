<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../db.php';

$errors = [];
$success = false;

// Fetch part ID series for dropdown
$partIdSeries = [];
try {
    $partIdSeries = $pdo->query("
        SELECT id, part_id, series_prefix, current_number, number_padding, description
        FROM part_id_series
        WHERE is_active = 1
        ORDER BY part_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
}

// Handle AJAX request to generate part number (preview only - doesn't commit until saved)
if (isset($_GET['action']) && $_GET['action'] === 'generate_part_no' && isset($_GET['series_id'])) {
    header('Content-Type: application/json');
    try {
        $seriesId = (int)$_GET['series_id'];
        $stmt = $pdo->prepare("SELECT * FROM part_id_series WHERE id = ? AND is_active = 1");
        $stmt->execute([$seriesId]);
        $series = $stmt->fetch();

        if ($series) {
            // Find the next available number (skip any that already exist in part_master)
            $nextNumber = $series['current_number'] + 1;
            $maxAttempts = 100; // Prevent infinite loop
            $attempts = 0;

            while ($attempts < $maxAttempts) {
                $paddedNumber = str_pad($nextNumber, $series['number_padding'], '0', STR_PAD_LEFT);
                $generatedPartNo = $series['series_prefix'] . $paddedNumber;

                // Check if this part number already exists in part_master
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM part_master WHERE part_no = ?");
                $checkStmt->execute([$generatedPartNo]);

                if ($checkStmt->fetchColumn() == 0) {
                    // This number is available
                    break;
                }

                // Number exists, try next one
                $nextNumber++;
                $attempts++;
            }

            if ($attempts >= $maxAttempts) {
                echo json_encode(['success' => false, 'error' => 'Could not find available part number']);
                exit;
            }

            // DON'T update the counter here - only update when part is actually saved
            // Store the series info for the form to use
            echo json_encode([
                'success' => true,
                'part_no' => $generatedPartNo,
                'part_id' => $series['part_id'],
                'series_id' => $seriesId,
                'next_number' => $nextNumber
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Series not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize + normalize input
    $part_no   = strtoupper(trim($_POST['part_no'] ?? ''));
    $part_name = trim($_POST['part_name'] ?? '');
    $part_id = trim($_POST['part_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $uom = trim($_POST['uom'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $rate = trim($_POST['rate'] ?? '');
    $hsn_code = trim($_POST['hsn_code'] ?? '');
    $gst       = trim($_POST['gst'] ?? '');

    // Series tracking - for updating counter after successful save
    $generated_series_id = (int)($_POST['generated_series_id'] ?? 0);
    $generated_next_number = (int)($_POST['generated_next_number'] ?? 0);

    // Validation
    if ($part_no === '') {
        $errors[] = "Part No is required";
    }

    if ($part_name === '') {
        $errors[] = "Part Name is required";
    }

    if ($part_id === '') {
        $errors[] = "Part ID is required";
    }

    if ($description === '') {
        $errors[] = "Part Description is required";
    }

    if ($uom === '') {
        $errors[] = "Part UOM is required";
    }

    if ($category === '') {
        $errors[] = "Part Category is required";
    }

    if ($rate === '' || !is_numeric($rate) || $rate < 0) {
        $errors[] = "Rate must be a valid number";
    }

    if ($gst === '' || !is_numeric($gst) || $gst < 0) {
        $errors[] = "GST must be a valid number";
    }

    // Uniqueness check (ONLY if no validation errors)
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM part_master
            WHERE part_no = ?
        ");
        $stmt->execute([$part_no]);

        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Part No must be unique";
        }
    }

    $attachmentPath = null;

    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "../uploads/parts/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "Only PDF files are allowed";
        } else {
            $fileName = $part_no . "_" . time() . ".pdf";
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                $attachmentPath = "uploads/parts/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    // Insert only if no errors
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO part_master
            (part_no, part_name, part_id, description, uom, category, hsn_code, rate, gst, attachment_path, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $part_no,
            $part_name,
            $part_id,
            $description,
            $uom,
            $category,
            $hsn_code,
            $rate,
            $gst,
            $attachmentPath
        ]);

        // Update series counter ONLY after part is successfully saved
        if ($generated_series_id > 0 && $generated_next_number > 0) {
            try {
                $pdo->prepare("
                    UPDATE part_id_series
                    SET current_number = ?
                    WHERE id = ? AND current_number < ?
                ")->execute([$generated_next_number, $generated_series_id, $generated_next_number]);
            } catch (Exception $e) {
                // Silently ignore - part was saved, series update is secondary
            }
        }

        $success = true;
        // Redirect to list after successful insertion
        header("Location: list.php");
        exit;
    }
}

// Include sidebar AFTER form processing (to avoid headers already sent error)
require '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Part</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .part-no-generator {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .part-no-generator h4 {
            margin: 0 0 15px 0;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .generator-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .generator-field {
            flex: 1;
            min-width: 200px;
        }
        .generator-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9em;
            color: #333;
        }
        .generator-field select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }
        .generate-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .generate-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .generated-preview {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 6px;
            padding: 12px 20px;
            margin-top: 15px;
            display: none;
        }
        .generated-preview.show {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .generated-preview .part-no-display {
            font-family: monospace;
            font-size: 1.3em;
            font-weight: bold;
            color: #2e7d32;
        }
        .generated-preview .success-icon {
            color: #2e7d32;
            font-size: 1.2em;
        }
        .series-description {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="content">

    <h2>Add Part</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">
            Part added successfully.
        </div>
    <?php endif; ?>

    <!-- Part Number Generator Section -->
    <?php if (!empty($partIdSeries)): ?>
    <div class="part-no-generator">
        <h4><span style="font-size: 1.2em;">#</span> Part Number Generator</h4>
        <div class="generator-row">
            <div class="generator-field">
                <label>Select Part ID Series</label>
                <select id="partIdSeriesSelect" onchange="updateSeriesDescription()">
                    <option value="">-- Select a Series --</option>
                    <?php foreach ($partIdSeries as $series): ?>
                        <option value="<?= $series['id'] ?>"
                                data-part-id="<?= htmlspecialchars($series['part_id']) ?>"
                                data-prefix="<?= htmlspecialchars($series['series_prefix']) ?>"
                                data-next="<?= $series['current_number'] + 1 ?>"
                                data-padding="<?= $series['number_padding'] ?>"
                                data-description="<?= htmlspecialchars($series['description'] ?? '') ?>">
                            <?= htmlspecialchars($series['part_id']) ?> - <?= htmlspecialchars($series['series_prefix']) ?>
                            (Next: <?= $series['series_prefix'] . str_pad($series['current_number'] + 1, $series['number_padding'], '0', STR_PAD_LEFT) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="seriesDescription" class="series-description"></div>
            </div>
            <button type="button" class="generate-btn" id="generateBtn" onclick="generatePartNumber()" disabled>
                Generate Part No
            </button>
        </div>
        <div class="generated-preview" id="generatedPreview">
            <span class="success-icon">&#10004;</span>
            <span>Generated Part No: <strong class="part-no-display" id="generatedPartNo"></strong></span>
            <span style="color: #666; font-size: 0.9em;">(auto-filled below)</span>
        </div>
    </div>
    <?php else: ?>
    <div class="part-no-generator" style="background: #fff3cd; border-color: #ffc107;">
        <p style="margin: 0; color: #856404;">
            <strong>Tip:</strong> Set up Part ID Series to auto-generate part numbers.
            <a href="/admin/setup_part_id_series.php" style="color: #667eea;">Setup Part ID Series</a> |
            <a href="/project_management/part_id_series.php" style="color: #667eea;">Manage Series</a>
        </p>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid">
        <!-- Hidden fields for series tracking -->
        <input type="hidden" name="generated_series_id" id="generatedSeriesId" value="0">
        <input type="hidden" name="generated_next_number" id="generatedNextNumber" value="0">

        <label>Part No <span style="color: #dc3545;">*</span></label>
        <input type="text" name="part_no" id="partNoInput" required placeholder="Enter or generate part number">

        <label>Part Name <span style="color: #dc3545;">*</span></label>
        <input type="text" name="part_name" required>

        <label>Part ID <span style="color: #dc3545;">*</span></label>
        <input type="text" name="part_id" id="partIdInput" required placeholder="e.g., RAW, FG, WIP">

        <label>Description</label>
        <input type="text" name="description" required>

        <label>Category</label>
        <select name="category" required>
            <option value="">-- Select Category --</option>
            <option value="Assembly">Assembly</option>
            <option value="Brought Out">Brought Out</option>
            <option value="Finished Good">Finished Good</option>
            <option value="Manufacturing">Manufacturing</option>
            <option value="Printing">Printing</option>
        </select>

        <label>UOM (Unit of Measure)</label>
        <select name="uom" required>
            <option value="">-- Select UOM --</option>
            <option value="Nos">Nos (Numbers)</option>
            <option value="Mtr">Mtr (Meter)</option>
            <option value="Kg">Kg (Kilogram)</option>
            <option value="Gm">Gm (Gram)</option>
            <option value="Ltr">Ltr (Litre)</option>
            <option value="Ml">Ml (Millilitre)</option>
            <option value="Pcs">Pcs (Pieces)</option>
            <option value="Set">Set</option>
            <option value="Box">Box</option>
            <option value="Roll">Roll</option>
            <option value="Pair">Pair</option>
            <option value="Ft">Ft (Feet)</option>
            <option value="Sqm">Sqm (Square Meter)</option>
            <option value="Sqft">Sqft (Square Feet)</option>
        </select>

        <label>Rate</label>
        <input type="number" name="rate" step="0.01" min="0" required>

        <label>HSN Code</label>
        <input type="text" name="hsn_code">

        <label>GST (%)</label>
        <input type="number" name="gst" step="0.01" min="0" required>

        <label>Attachment (PDF)</label>
        <input type="file" name="attachment" accept="application/pdf">

        <div></div>
        <button type="submit">Add Part</button>

    </form>

</div>

<script>
function generatePartNumber() {
    const select = document.getElementById('partIdSeriesSelect');
    const seriesId = select.value;

    if (!seriesId) {
        alert('Please select a Part ID Series first');
        return;
    }

    const generateBtn = document.getElementById('generateBtn');
    generateBtn.disabled = true;
    generateBtn.textContent = 'Generating...';

    fetch('?action=generate_part_no&series_id=' + seriesId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fill in the form fields
                document.getElementById('partNoInput').value = data.part_no;
                document.getElementById('partIdInput').value = data.part_id;

                // Store series info in hidden fields for counter update on save
                document.getElementById('generatedSeriesId').value = data.series_id;
                document.getElementById('generatedNextNumber').value = data.next_number;

                // Show the preview
                const preview = document.getElementById('generatedPreview');
                document.getElementById('generatedPartNo').textContent = data.part_no;
                preview.classList.add('show');

                // Highlight the part number field
                const partNoInput = document.getElementById('partNoInput');
                partNoInput.style.backgroundColor = '#e8f5e9';
                partNoInput.style.borderColor = '#28a745';
                setTimeout(() => {
                    partNoInput.style.backgroundColor = '';
                    partNoInput.style.borderColor = '';
                }, 2000);

                // Disable generate button to prevent multiple generations
                generateBtn.textContent = 'Generated';
                generateBtn.disabled = true;
                generateBtn.style.opacity = '0.6';

            } else {
                alert('Error: ' + (data.error || 'Failed to generate part number'));
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate Part No';
            }
        })
        .catch(err => {
            alert('Error generating part number');
            console.error(err);
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generate Part No';
        });
}

// Reset generation when series selection changes
function updateSeriesDescription() {
    const select = document.getElementById('partIdSeriesSelect');
    const descDiv = document.getElementById('seriesDescription');
    const generateBtn = document.getElementById('generateBtn');
    const selectedOption = select.options[select.selectedIndex];
    const preview = document.getElementById('generatedPreview');

    // Reset hidden fields when series changes
    document.getElementById('generatedSeriesId').value = '0';
    document.getElementById('generatedNextNumber').value = '0';

    // Hide preview
    if (preview) {
        preview.classList.remove('show');
    }

    // Reset generate button
    generateBtn.style.opacity = '1';
    generateBtn.textContent = 'Generate Part No';

    if (select.value) {
        const description = selectedOption.getAttribute('data-description');
        descDiv.textContent = description || '';
        generateBtn.disabled = false;
    } else {
        descDiv.textContent = '';
        generateBtn.disabled = true;
    }
}
</script>

</body>
</html>
