<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

// Fetch certifications for dropdown
$certifications = $pdo->query("SELECT id, standard_name FROM qms_iso_certifications ORDER BY standard_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $certification_id = trim($_POST['certification_id'] ?? '');
    $audit_no = trim($_POST['audit_no'] ?? '');
    $audit_type = trim($_POST['audit_type'] ?? 'Internal');
    $audit_standard = trim($_POST['audit_standard'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $audit_date = trim($_POST['audit_date'] ?? '');
    $auditor_name = trim($_POST['auditor_name'] ?? '');
    $audit_team = trim($_POST['audit_team'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');

    if (empty($audit_type) || empty($auditor_name)) {
        $error = "Audit type and auditor name are required.";
    } else {
        try {
            // Generate audit number if not provided
            if (empty($audit_no)) {
                $year = date('Y');
                $countStmt = $pdo->query("SELECT COUNT(*) FROM qms_iso_audits WHERE YEAR(created_at) = $year");
                $count = $countStmt->fetchColumn() + 1;
                $audit_no = "AUD-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO qms_iso_audits
                (certification_id, audit_no, audit_type, audit_standard, audit_scope, planned_date, lead_auditor, audit_team, department, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $certification_id ?: null,
                $audit_no,
                $audit_type,
                $audit_standard,
                $scope,
                $audit_date ?: null,
                $auditor_name,
                $audit_team,
                $department,
                $status
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
    <title>Schedule Audit - QMS</title>
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
        <h1>Schedule Audit</h1>
        <a href="audits.php" class="btn btn-secondary">← Back to Audits</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Audit Types:</strong><br>
        Internal (by company auditors), External (by certification body),
        Supplier (vendor audits), Customer (customer-initiated audits), Regulatory (by authorities)
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Audit Number</label>
                    <input type="text" name="audit_no" value="<?= htmlspecialchars($_POST['audit_no'] ?? '') ?>"
                           placeholder="Auto-generated if left blank">
                </div>
                <div class="form-group">
                    <label class="required">Audit Type</label>
                    <select name="audit_type" required>
                        <option value="Internal">Internal</option>
                        <option value="External">External</option>
                        <option value="Supplier">Supplier</option>
                        <option value="Customer">Customer</option>
                        <option value="Regulatory">Regulatory</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Related Certification</label>
                    <select name="certification_id">
                        <option value="">-- None --</option>
                        <?php foreach ($certifications as $cert): ?>
                            <option value="<?= $cert['id'] ?>"><?= htmlspecialchars($cert['standard_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Audit Standard</label>
                    <input type="text" name="audit_standard" value="<?= htmlspecialchars($_POST['audit_standard'] ?? '') ?>"
                           placeholder="e.g., ISO 9001:2015 Clause 7">
                </div>
            </div>

            <div class="form-group">
                <label>Audit Scope / Areas</label>
                <textarea name="scope" placeholder="Define the scope, departments, processes, or clauses to be audited..."><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Lead Auditor</label>
                    <input type="text" name="auditor_name" value="<?= htmlspecialchars($_POST['auditor_name'] ?? '') ?>" required
                           placeholder="Lead auditor name">
                </div>
                <div class="form-group">
                    <label>Audit Date</label>
                    <input type="date" name="audit_date" value="<?= htmlspecialchars($_POST['audit_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Audit Team Members</label>
                    <input type="text" name="audit_team" value="<?= htmlspecialchars($_POST['audit_team'] ?? '') ?>"
                           placeholder="Comma-separated names">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">Select Department</option>
                        <option value="Quality Assurance">Quality Assurance</option>
                        <option value="Quality Control">Quality Control</option>
                        <option value="Production">Production</option>
                        <option value="R&D">R&D</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Warehouse">Warehouse</option>
                        <option value="HR">Human Resources</option>
                        <option value="Purchase">Purchase</option>
                        <option value="Sales">Sales</option>
                        <option value="All">All Departments</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Planned">Planned</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Postponed">Postponed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Schedule Audit</button>
                <a href="audits.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
