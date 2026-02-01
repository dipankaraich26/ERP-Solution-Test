<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $standard_code = trim($_POST['standard_code'] ?? '');
    $standard_name = trim($_POST['standard_name'] ?? '');
    $certificate_no = trim($_POST['certificate_no'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $certification_body = trim($_POST['certification_body'] ?? '');
    $status = trim($_POST['status'] ?? 'Planning');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');

    if (empty($standard_name) || empty($certification_body)) {
        $error = "Standard name and certification body are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_iso_certifications
                (standard_code, standard_name, certificate_no, scope, certification_body, status, issue_date, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $standard_code ?: $standard_name,
                $standard_name,
                $certificate_no ?: null,
                $scope,
                $certification_body,
                $status,
                $issue_date ?: null,
                $expiry_date ?: null
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
    <title>Add ISO Certification - QMS</title>
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
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Add ISO Certification</h1>
        <a href="certifications.php" class="btn btn-secondary">← Back to Certifications</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Common ISO Standards for Medical Devices:</strong><br>
        ISO 9001 (QMS), ISO 13485 (Medical Device QMS), ISO 14001 (Environmental),
        ISO 14971 (Risk Management), ISO 10993 (Biocompatibility), ISO 11135 (EO Sterilization)
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">ISO Standard</label>
                    <select name="standard_name" required>
                        <option value="">Select Standard</option>
                        <option value="ISO 9001:2015">ISO 9001:2015 - Quality Management Systems</option>
                        <option value="ISO 13485:2016">ISO 13485:2016 - Medical Devices QMS</option>
                        <option value="ISO 14001:2015">ISO 14001:2015 - Environmental Management</option>
                        <option value="ISO 14971:2019">ISO 14971:2019 - Risk Management</option>
                        <option value="ISO 10993">ISO 10993 - Biocompatibility</option>
                        <option value="ISO 11135">ISO 11135 - EO Sterilization</option>
                        <option value="ISO 11137">ISO 11137 - Radiation Sterilization</option>
                        <option value="ISO 11607">ISO 11607 - Packaging for Sterilized Devices</option>
                        <option value="ISO 15223">ISO 15223 - Symbols for Medical Devices</option>
                        <option value="ISO 22000:2018">ISO 22000:2018 - Food Safety Management</option>
                        <option value="ISO 45001:2018">ISO 45001:2018 - Occupational Health & Safety</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Certificate Number</label>
                    <input type="text" name="certificate_no" value="<?= htmlspecialchars($_POST['certificate_no'] ?? '') ?>"
                           placeholder="e.g., QMS-2024-001">
                </div>
            </div>

            <div class="form-group">
                <label>Scope of Certification</label>
                <textarea name="scope" placeholder="Define the scope of certification: products, processes, facilities covered..."><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Certification Body</label>
                    <select name="certification_body" required>
                        <option value="">Select Certification Body</option>
                        <option value="TUV SUD">TUV SUD</option>
                        <option value="TUV Rheinland">TUV Rheinland</option>
                        <option value="BSI (British Standards Institution)">BSI (British Standards Institution)</option>
                        <option value="DNV GL">DNV GL</option>
                        <option value="Bureau Veritas">Bureau Veritas</option>
                        <option value="SGS">SGS</option>
                        <option value="Intertek">Intertek</option>
                        <option value="DEKRA">DEKRA</option>
                        <option value="UL">UL</option>
                        <option value="NABCB Accredited Body">NABCB Accredited Body</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Planning">Planning</option>
                        <option value="Implementation">Implementation</option>
                        <option value="Audit Scheduled">Audit Scheduled</option>
                        <option value="Certified">Certified</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Withdrawn">Withdrawn</option>
                        <option value="Renewal Due">Renewal Due</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" value="<?= htmlspecialchars($_POST['issue_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>
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
