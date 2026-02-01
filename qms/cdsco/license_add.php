<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_type = trim($_POST['license_type'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');
    $facility_name = trim($_POST['facility_name'] ?? '');
    $facility_address = trim($_POST['facility_address'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($license_type) || empty($facility_name)) {
        $error = "License type and facility name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_cdsco_licenses
                (license_type, license_no, facility_name, facility_address, status, issue_date, expiry_date, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $license_type,
                $license_no ?: null,
                $facility_name,
                $facility_address,
                $status,
                $issue_date ?: null,
                $expiry_date ?: null,
                $remarks
            ]);

            header("Location: licenses.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding license: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add License - CDSCO</title>
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
            height: 80px;
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
        <h1>Add Manufacturing License</h1>
        <a href="licenses.php" class="btn btn-secondary">← Back to Licenses</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>CDSCO License Types:</strong>
        Form MD-9 (Import), Form MD-14 (Manufacturing), Form MD-15 (Loan License),
        Form 28/28-D (Drug Manufacturing), Form 29/29-B (Blood Bank)
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">License Type</label>
                    <select name="license_type" required>
                        <option value="">Select License Type</option>
                        <option value="Form MD-9 (Import License)">Form MD-9 (Import License)</option>
                        <option value="Form MD-14 (Manufacturing License)">Form MD-14 (Manufacturing License)</option>
                        <option value="Form MD-15 (Loan License)">Form MD-15 (Loan License)</option>
                        <option value="Form 28 (Drug Manufacturing)">Form 28 (Drug Manufacturing)</option>
                        <option value="Form 28-D (Drug Manufacturing - Cosmetics)">Form 28-D (Drug Manufacturing - Cosmetics)</option>
                        <option value="Form 29 (Blood Bank)">Form 29 (Blood Bank)</option>
                        <option value="Form 29-B (Blood Product)">Form 29-B (Blood Product)</option>
                        <option value="WHO-GMP Certificate">WHO-GMP Certificate</option>
                        <option value="ISO 13485 Certificate">ISO 13485 Certificate</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_no" value="<?= htmlspecialchars($_POST['license_no'] ?? '') ?>"
                           placeholder="e.g., MFG/MD/2024/001">
                </div>
            </div>

            <div class="form-group">
                <label class="required">Facility Name</label>
                <input type="text" name="facility_name" value="<?= htmlspecialchars($_POST['facility_name'] ?? '') ?>" required
                       placeholder="Manufacturing facility name">
            </div>

            <div class="form-group">
                <label>Facility Address</label>
                <textarea name="facility_address" placeholder="Complete address of the manufacturing facility"><?= htmlspecialchars($_POST['facility_address'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Pending">Pending</option>
                        <option value="Active">Active</option>
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
                <label>Remarks</label>
                <textarea name="remarks"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Add License</button>
                <a href="licenses.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
