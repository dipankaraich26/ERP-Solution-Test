<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $risk_class = trim($_POST['risk_class'] ?? '');
    $certificate_no = trim($_POST['certificate_no'] ?? '');
    $certifying_body = trim($_POST['certifying_body'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($product_name) || empty($risk_class)) {
        $error = "Product name and risk classification are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_icmed_certifications
                (product_name, risk_class, certificate_no, certifying_body, status,
                 issue_date, expiry_date, scope, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $product_name,
                $risk_class,
                $certificate_no ?: null,
                $certifying_body,
                $status,
                $issue_date ?: null,
                $expiry_date ?: null,
                $scope,
                $remarks
            ]);

            header("Location: certifications.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding certification: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add ICMED Certification - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 700px;
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
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
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
        <h1>Add ICMED Certification</h1>
        <a href="certifications.php" class="btn btn-secondary">← Back to Certifications</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>ICMED Certification Process:</strong><br>
        1. Application submission to QCI-accredited Certification Body<br>
        2. Document review and assessment<br>
        3. Factory audit (if applicable)<br>
        4. Certificate issuance (valid for 3 years)
    </div>

    <div class="risk-info">
        <h4>ICMED Risk Classification (aligned with MDR 2017)</h4>
        <ul>
            <li><strong>Class A:</strong> Low risk devices (non-invasive, non-measuring)</li>
            <li><strong>Class B:</strong> Low-moderate risk (short-term invasive, measuring devices)</li>
            <li><strong>Class C:</strong> Moderate-high risk (long-term invasive, active therapeutic)</li>
            <li><strong>Class D:</strong> High risk (CNS contact, cardiovascular, biologicals)</li>
        </ul>
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Product Name</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="required">Risk Classification</label>
                    <select name="risk_class" required>
                        <option value="">Select Risk Class</option>
                        <option value="A">Class A - Low Risk</option>
                        <option value="B">Class B - Low-Moderate Risk</option>
                        <option value="C">Class C - Moderate-High Risk</option>
                        <option value="D">Class D - High Risk</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Certificate Number</label>
                    <input type="text" name="certificate_no" value="<?= htmlspecialchars($_POST['certificate_no'] ?? '') ?>"
                           placeholder="e.g., ICMED/2024/001">
                </div>
                <div class="form-group">
                    <label>Certifying Body</label>
                    <select name="certifying_body">
                        <option value="">Select Certifying Body</option>
                        <option value="QCI (Quality Council of India)">QCI (Quality Council of India)</option>
                        <option value="TUV SUD South Asia">TUV SUD South Asia</option>
                        <option value="Bureau Veritas India">Bureau Veritas India</option>
                        <option value="SGS India">SGS India</option>
                        <option value="Intertek India">Intertek India</option>
                        <option value="NABL Accredited Body">NABL Accredited Body</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Pending">Pending</option>
                        <option value="Applied">Applied</option>
                        <option value="Under Review">Under Review</option>
                        <option value="Certified">Certified</option>
                        <option value="Expired">Expired</option>
                        <option value="Suspended">Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" value="<?= htmlspecialchars($_POST['issue_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>
                <div class="form-group"></div>
            </div>

            <div class="form-group">
                <label>Certification Scope</label>
                <textarea name="scope" placeholder="Define the scope: products covered, intended use, manufacturing processes..."><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Add Certification</button>
                <a href="certifications.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
