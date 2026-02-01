<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audit_type = trim($_POST['audit_type'] ?? '');
    $facility_name = trim($_POST['facility_name'] ?? '');
    $facility_address = trim($_POST['facility_address'] ?? '');
    $auditor_name = trim($_POST['auditor_name'] ?? '');
    $audit_date = trim($_POST['audit_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');
    $scope = trim($_POST['scope'] ?? '');
    $major_nc = (int)($_POST['major_nc'] ?? 0);
    $minor_nc = (int)($_POST['minor_nc'] ?? 0);
    $result = trim($_POST['result'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($audit_type) || empty($facility_name) || empty($auditor_name)) {
        $error = "Audit type, facility name, and auditor name are required.";
    } else {
        try {
            // Generate audit number
            $year = date('Y');
            $countStmt = $pdo->query("SELECT COUNT(*) FROM qms_icmed_audits WHERE YEAR(created_at) = $year");
            $count = $countStmt->fetchColumn() + 1;
            $audit_no = "ICMED-AUD-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO qms_icmed_audits
                (audit_no, audit_type, areas_audited, audit_team, auditor_name, scheduled_date,
                 status, checklist_used, major_nc, minor_nc, audit_result, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $audit_no,
                $audit_type,
                $facility_name,
                $facility_address,
                $auditor_name,
                $audit_date ?: null,
                $status,
                $scope,
                $major_nc,
                $minor_nc,
                $result ?: null,
                $remarks
            ]);

            header("Location: audits.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error scheduling audit: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Schedule ICMED Audit - QMS</title>
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
        .findings-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .findings-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Schedule ICMED Factory Audit</h1>
        <a href="audits.php" class="btn btn-secondary">← Back to Audits</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>ICMED Factory Audit Types:</strong><br>
        • <strong>Initial:</strong> First audit for new certification<br>
        • <strong>Surveillance:</strong> Annual follow-up audits during certification period<br>
        • <strong>Renewal:</strong> Audit for certificate renewal (every 3 years)<br>
        • <strong>Special:</strong> Triggered by complaints, changes, or non-conformances
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Audit Type</label>
                    <select name="audit_type" required>
                        <option value="">Select Audit Type</option>
                        <option value="Initial">Initial Certification Audit</option>
                        <option value="Surveillance">Surveillance Audit</option>
                        <option value="Renewal">Renewal Audit</option>
                        <option value="Special">Special Audit</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Audit Date</label>
                    <input type="date" name="audit_date" value="<?= htmlspecialchars($_POST['audit_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="required">Facility Name</label>
                <input type="text" name="facility_name" value="<?= htmlspecialchars($_POST['facility_name'] ?? '') ?>" required
                       placeholder="Manufacturing facility name">
            </div>

            <div class="form-group">
                <label>Facility Address</label>
                <textarea name="facility_address" placeholder="Complete address of the facility"><?= htmlspecialchars($_POST['facility_address'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Lead Auditor Name</label>
                    <input type="text" name="auditor_name" value="<?= htmlspecialchars($_POST['auditor_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Passed">Passed</option>
                        <option value="Pending Corrective Action">Pending Corrective Action</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Audit Scope</label>
                <textarea name="scope" placeholder="Define the scope: products, processes, standards to be audited..."><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
            </div>

            <div class="findings-section">
                <h3>Audit Findings (fill after completion)</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Major Non-Conformances</label>
                        <input type="number" name="major_nc" value="<?= htmlspecialchars($_POST['major_nc'] ?? '0') ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Minor Non-Conformances</label>
                        <input type="number" name="minor_nc" value="<?= htmlspecialchars($_POST['minor_nc'] ?? '0') ?>" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Audit Result</label>
                    <select name="result">
                        <option value="">Not yet determined</option>
                        <option value="Pass">Pass - Certificate Recommended</option>
                        <option value="Conditional">Conditional - Pending Corrective Actions</option>
                        <option value="Fail">Fail - Certificate Not Recommended</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Remarks</label>
                <textarea name="remarks"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Schedule Audit</button>
                <a href="audits.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
