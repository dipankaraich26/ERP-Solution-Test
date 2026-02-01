<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code = trim($_POST['product_code'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $product_category = trim($_POST['product_category'] ?? '');
    $risk_class = trim($_POST['risk_class'] ?? '');
    $intended_use = trim($_POST['intended_use'] ?? '');
    $registration_no = trim($_POST['registration_no'] ?? '');
    $status = trim($_POST['status'] ?? 'Draft');
    $submission_date = trim($_POST['submission_date'] ?? '');
    $approval_date = trim($_POST['approval_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($product_code) || empty($product_name) || empty($product_category) || empty($risk_class)) {
        $error = "Product code, name, category, and risk classification are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_cdsco_products
                (product_code, product_name, product_category, risk_class, intended_use, registration_no,
                 status, submission_date, approval_date, expiry_date, manufacturer, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $product_code,
                $product_name,
                $product_category,
                $risk_class,
                $intended_use,
                $registration_no ?: null,
                $status,
                $submission_date ?: null,
                $approval_date ?: null,
                $expiry_date ?: null,
                $manufacturer,
                $remarks
            ]);

            header("Location: products.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add CDSCO Product - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .risk-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .risk-info h4 { margin: 0 0 10px 0; }
        .risk-info ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Add CDSCO Product Registration</h1>
        <a href="products.php" class="btn btn-secondary">← Back to Products</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="risk-info">
        <h4>CDSCO Medical Device Risk Classification (MDR 2017)</h4>
        <ul>
            <li><strong>Class A (Low Risk):</strong> Non-invasive devices, containers, non-active therapeutic devices</li>
            <li><strong>Class B (Low-Moderate Risk):</strong> Surgically invasive for short-term use, active diagnostic devices</li>
            <li><strong>Class C (Moderate-High Risk):</strong> Long-term implantable devices, active therapeutic devices</li>
            <li><strong>Class D (High Risk):</strong> Devices in contact with CNS, cardiovascular system, or contain biologicals</li>
        </ul>
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Product Code</label>
                    <input type="text" name="product_code" value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>" required
                           placeholder="e.g., PRD-001">
                </div>
                <div class="form-group">
                    <label class="required">Product Name</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Category</label>
                    <select name="product_category" required>
                        <option value="">Select Category</option>
                        <option value="Medical Device">Medical Device</option>
                        <option value="Diagnostic">Diagnostic</option>
                        <option value="Pharmaceutical">Pharmaceutical</option>
                        <option value="IVD">IVD (In Vitro Diagnostic)</option>
                        <option value="Implant">Implant</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Risk Classification</label>
                    <select name="risk_class" required>
                        <option value="">Select Risk Class</option>
                        <option value="Class A">Class A - Low Risk</option>
                        <option value="Class B">Class B - Low-Moderate Risk</option>
                        <option value="Class C">Class C - Moderate-High Risk</option>
                        <option value="Class D">Class D - High Risk</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Draft">Draft</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Query Raised">Query Raised</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Expired">Expired</option>
                        <option value="Renewed">Renewed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Manufacturer</label>
                    <input type="text" name="manufacturer" value="<?= htmlspecialchars($_POST['manufacturer'] ?? '') ?>"
                           placeholder="Manufacturer name">
                </div>
            </div>

            <div class="form-group">
                <label>Intended Use / Indications</label>
                <textarea name="intended_use" placeholder="Describe the intended use, indications, and patient population..."><?= htmlspecialchars($_POST['intended_use'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>CDSCO Registration Number</label>
                    <input type="text" name="registration_no" placeholder="e.g., MD-XXXX-YYYY"
                           value="<?= htmlspecialchars($_POST['registration_no'] ?? '') ?>">
                    <div class="help-text">Leave blank if not yet registered</div>
                </div>
                <div class="form-group">
                    <label>Submission Date</label>
                    <input type="date" name="submission_date"
                           value="<?= htmlspecialchars($_POST['submission_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Approval Date</label>
                    <input type="date" name="approval_date"
                           value="<?= htmlspecialchars($_POST['approval_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date"
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                    <div class="help-text">Registration validity (usually 5 years)</div>
                </div>
            </div>

            <div class="form-group">
                <label>Remarks / Notes</label>
                <textarea name="remarks" placeholder="Any additional notes or requirements..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Add Product</button>
                <a href="products.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
