<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

// Fetch audits for dropdown
$audits = $pdo->query("SELECT id, audit_no, audit_type FROM qms_iso_audits ORDER BY created_at DESC LIMIT 50")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audit_id = trim($_POST['audit_id'] ?? '');
    $ncr_no = trim($_POST['ncr_no'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $severity = trim($_POST['severity'] ?? 'Minor');
    $clause_reference = trim($_POST['clause_reference'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $evidence = trim($_POST['evidence'] ?? '');
    $immediate_action = trim($_POST['immediate_action'] ?? '');
    $responsible_person = trim($_POST['responsible_person'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Open');

    if (empty($source) || empty($severity) || empty($description)) {
        $error = "Source, severity, and description are required.";
    } else {
        try {
            // Generate NCR number if not provided
            if (empty($ncr_no)) {
                $year = date('Y');
                $countStmt = $pdo->query("SELECT COUNT(*) FROM qms_ncr WHERE YEAR(created_at) = $year");
                $count = $countStmt->fetchColumn() + 1;
                $ncr_no = "NCR-$year-" . str_pad($count, 5, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO qms_ncr
                (ncr_no, audit_id, source, nc_type, clause_reference, department, description, evidence, immediate_action, responsible_person, target_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ncr_no,
                $audit_id ?: null,
                $source,
                $severity,
                $clause_reference,
                $department,
                $description,
                $evidence,
                $immediate_action,
                $responsible_person,
                $due_date ?: null,
                $status
            ]);

            header("Location: ncr.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error raising NCR: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Raise NCR - QMS</title>
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
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
        .info-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #ffc107;
        }
        .severity-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .severity-info h4 { margin: 0 0 10px 0; }
        .severity-info ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Raise Non-Conformance Report (NCR)</h1>
        <a href="ncr.php" class="btn btn-secondary">← Back to NCRs</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="severity-info">
        <h4>NCR Severity Classification</h4>
        <ul>
            <li><strong>Major NC:</strong> Systematic failure, absence of required procedure, or total breakdown of a process</li>
            <li><strong>Minor NC:</strong> Isolated lapse, single deviation, or incomplete implementation</li>
            <li><strong>Observation:</strong> Area of concern that may lead to non-conformance if not addressed</li>
        </ul>
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row-3">
                <div class="form-group">
                    <label>NCR Number</label>
                    <input type="text" name="ncr_no" value="<?= htmlspecialchars($_POST['ncr_no'] ?? '') ?>"
                           placeholder="Auto-generated if blank">
                </div>
                <div class="form-group">
                    <label class="required">Source</label>
                    <select name="source" required>
                        <option value="">Select Source</option>
                        <option value="Internal Audit">Internal Audit</option>
                        <option value="External Audit">External Audit</option>
                        <option value="Customer Complaint">Customer Complaint</option>
                        <option value="Process Deviation">Process Deviation</option>
                        <option value="Supplier Issue">Supplier Issue</option>
                        <option value="Management Review">Management Review</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Severity</label>
                    <select name="severity" required>
                        <option value="Minor">Minor</option>
                        <option value="Major">Major</option>
                        <option value="Observation">Observation</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Related Audit</label>
                    <select name="audit_id">
                        <option value="">-- None --</option>
                        <?php foreach ($audits as $audit): ?>
                            <option value="<?= $audit['id'] ?>">
                                <?= htmlspecialchars($audit['audit_no'] . ' - ' . $audit['audit_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Clause / Requirement Reference</label>
                    <input type="text" name="clause_reference" value="<?= htmlspecialchars($_POST['clause_reference'] ?? '') ?>"
                           placeholder="e.g., ISO 9001:2015 Clause 7.5">
                </div>
            </div>

            <div class="form-group">
                <label class="required">Non-Conformance Description</label>
                <textarea name="description" required placeholder="Describe the non-conformance in detail. Include what requirement was not met and how..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Objective Evidence</label>
                <textarea name="evidence" placeholder="Document the evidence observed (records, observations, interview notes, etc.)..."><?= htmlspecialchars($_POST['evidence'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Immediate / Containment Action</label>
                <textarea name="immediate_action" placeholder="Describe any immediate containment actions taken..."><?= htmlspecialchars($_POST['immediate_action'] ?? '') ?></textarea>
            </div>

            <div class="form-row-3">
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
                        <option value="IT">IT</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Responsible Person</label>
                    <input type="text" name="responsible_person" value="<?= htmlspecialchars($_POST['responsible_person'] ?? '') ?>"
                           placeholder="Person responsible for closure">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="Open">Open</option>
                    <option value="Action Planned">Action Planned</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Verification Pending">Verification Pending</option>
                    <option value="Closed">Closed</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Raise NCR</button>
                <a href="ncr.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
