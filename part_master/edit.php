<?php
include "../db.php";
include "../includes/dialog.php";

$part_no = $_GET['part_no'] ?? null;
$errors = [];

if (!$part_no) {
    setModal("Failed to edit part", "Part not specified");
    header("Location: list.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no=?");
$stmt->execute([$part_no]);
$part = $stmt->fetch();

if (!$part) {
    setModal("Failed to edit part", "Part not found");
    header("Location: list.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $attachmentPath = $part['attachment_path']; // Keep existing attachment by default

    // Handle new attachment upload
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
                // Delete old attachment if exists
                if (!empty($part['attachment_path']) && file_exists("../" . $part['attachment_path'])) {
                    unlink("../" . $part['attachment_path']);
                }
                $attachmentPath = "uploads/parts/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        // Sanitize input
        $part_name = trim($_POST['part_name'] ?? '');
        $part_id_val = trim($_POST['part_id'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $uom = trim($_POST['uom'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $rate = $_POST['rate'] ?? 0;
        $hsn_code = trim($_POST['hsn_code'] ?? '');
        $gst = $_POST['gst'] ?? 0;

        $stmt = $pdo->prepare("
            UPDATE part_master
            SET part_name=?, part_id=?, description=?, uom=?, category=?, rate=?, hsn_code=?, gst=?, attachment_path=?
            WHERE part_no=?
        ");
        $stmt->execute([
            $part_name,
            $part_id_val,
            $description,
            $uom,
            $category,
            $rate,
            $hsn_code,
            $gst,
            $attachmentPath,
            $part_no
        ]);

        setModal("Success", "Part updated successfully!");
        header("Location: list.php");
        exit;
    }
}

// Include sidebar AFTER all redirects
include "../includes/sidebar.php";
showModal();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Part - <?= htmlspecialchars($part['part_no']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .edit-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .page-header h1 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #1e3a5f;
        }

        .page-header .part-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: 600;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-body {
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9em;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }

        .form-group input[readonly] {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            cursor: not-allowed;
            border-style: dashed;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .input-hint {
            font-size: 0.8em;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s ease;
        }

        .file-upload-wrapper:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-content {
            pointer-events: none;
        }

        .file-upload-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .file-upload-text {
            color: #666;
            font-size: 0.9em;
        }

        .current-file {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e8f5e9;
            padding: 8px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9em;
        }

        .current-file a {
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
        }

        .current-file a:hover {
            text-decoration: underline;
        }

        .error-box {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 1px solid #fc8181;
            border-left: 4px solid #e53e3e;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
            color: #c53030;
        }

        .error-box li {
            margin-bottom: 5px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
            margin-top: 10px;
        }

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: #f8f9fa;
            color: #495057;
            padding: 14px 25px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        /* Dark mode support */
        body.dark .form-card {
            background: #2c3e50;
        }

        body.dark .form-body {
            background: #2c3e50;
        }

        body.dark .form-group label {
            color: #ecf0f1;
        }

        body.dark .form-group input,
        body.dark .form-group select,
        body.dark .form-group textarea {
            background: #34495e;
            border-color: #4a5568;
            color: #ecf0f1;
        }

        body.dark .form-group input[readonly] {
            background: #1a252f;
        }

        body.dark .form-section-title {
            color: #ecf0f1;
        }

        body.dark .page-header h1 {
            color: #ecf0f1;
        }

        body.dark .file-upload-wrapper {
            background: #34495e;
            border-color: #4a5568;
        }

        body.dark .file-upload-text {
            color: #bdc3c7;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-update, .btn-back {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="edit-container">
        <div class="page-header">
            <h1>
                Edit Part
                <span class="part-badge"><?= htmlspecialchars($part['part_no']) ?></span>
            </h1>
            <a href="list.php" class="btn-back">
                <span>←</span> Back to List
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-header">
                <h2>
                    <span>📝</span>
                    Part Information
                </h2>
            </div>

            <div class="form-body">
                <form method="post" enctype="multipart/form-data">

                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span>📦</span> Basic Details
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Part No</label>
                                <input type="text" value="<?= htmlspecialchars($part['part_no'] ?? '') ?>" readonly>
                                <div class="input-hint">Part number cannot be changed</div>
                            </div>
                            <div class="form-group">
                                <label>Part ID</label>
                                <input type="text" name="part_id" value="<?= htmlspecialchars($part['part_id'] ?? '') ?>" placeholder="Enter Part ID">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Part Name <span class="required">*</span></label>
                                <input type="text" name="part_name" value="<?= htmlspecialchars($part['part_name'] ?? '') ?>" required placeholder="Enter Part Name">
                            </div>
                            <div class="form-group">
                                <label>Category <span class="required">*</span></label>
                                <select name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Assembly" <?= $part['category'] === 'Assembly' ? 'selected' : '' ?>>Assembly</option>
                                    <option value="Brought Out" <?= $part['category'] === 'Brought Out' ? 'selected' : '' ?>>Brought Out</option>
                                    <option value="Finished Good" <?= $part['category'] === 'Finished Good' ? 'selected' : '' ?>>Finished Good</option>
                                    <option value="Manufacturing" <?= $part['category'] === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                    <option value="Printing" <?= $part['category'] === 'Printing' ? 'selected' : '' ?>>Printing</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" placeholder="Enter part description..."><?= htmlspecialchars($part['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span>📐</span> Specifications & Pricing
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>UOM (Unit of Measure) <span class="required">*</span></label>
                                <select name="uom" required>
                                    <option value="">-- Select UOM --</option>
                                    <?php
                                    $uomOptions = ['Nos' => 'Nos (Numbers)', 'Mtr' => 'Mtr (Meter)', 'Kg' => 'Kg (Kilogram)', 'Gm' => 'Gm (Gram)', 'Ltr' => 'Ltr (Litre)', 'Ml' => 'Ml (Millilitre)', 'Pcs' => 'Pcs (Pieces)', 'Set' => 'Set', 'Box' => 'Box', 'Roll' => 'Roll', 'Pair' => 'Pair', 'Ft' => 'Ft (Feet)', 'Sqm' => 'Sqm (Square Meter)', 'Sqft' => 'Sqft (Square Feet)'];
                                    $currentUom = $part['uom'] ?? '';
                                    foreach ($uomOptions as $value => $label):
                                    ?>
                                    <option value="<?= $value ?>" <?= $currentUom === $value ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Rate (₹)</label>
                                <input name="rate" type="number" step="0.01" min="0" value="<?= htmlspecialchars($part['rate']) ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>HSN Code</label>
                                <input name="hsn_code" value="<?= htmlspecialchars($part['hsn_code'] ?? '') ?>" placeholder="Enter HSN Code">
                            </div>
                            <div class="form-group">
                                <label>GST (%)</label>
                                <input name="gst" type="number" step="0.01" min="0" max="100" value="<?= htmlspecialchars($part['gst'] ?? '') ?>" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <!-- Attachment -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span>📎</span> Attachment
                        </div>

                        <div class="form-group">
                            <label>Upload PDF Document</label>
                            <div class="file-upload-wrapper">
                                <input type="file" name="attachment" accept="application/pdf">
                                <div class="file-upload-content">
                                    <div class="file-upload-icon">📄</div>
                                    <div class="file-upload-text">
                                        <strong>Click to upload</strong> or drag and drop<br>
                                        <small>PDF files only (max 10MB)</small>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($part['attachment_path'])): ?>
                                <div class="current-file">
                                    <span>📎</span>
                                    Current file:
                                    <a href="../<?= htmlspecialchars($part['attachment_path']) ?>" target="_blank">View PDF</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-update">
                            <span>💾</span> Update Part
                        </button>
                        <a href="list.php" class="btn-back">Cancel</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
