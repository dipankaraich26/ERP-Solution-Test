<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $capa_no = trim($_POST['capa_no'] ?? '');
    $capa_type = trim($_POST['capa_type'] ?? 'Corrective');
    $source = trim($_POST['source'] ?? '');
    $source_reference = trim($_POST['source_reference'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $problem_description = trim($_POST['problem_description'] ?? '');
    $affected_area = trim($_POST['affected_area'] ?? '');
    $root_cause_method = trim($_POST['root_cause_method'] ?? '5 Why');
    $root_cause_analysis = trim($_POST['root_cause_analysis'] ?? '');
    $proposed_action = trim($_POST['proposed_action'] ?? '');
    $implementation_plan = trim($_POST['implementation_plan'] ?? '');
    $responsible_person = trim($_POST['responsible_person'] ?? '');
    $target_date = trim($_POST['target_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Initiated');
    $effectiveness_criteria = trim($_POST['effectiveness_criteria'] ?? '');

    if (empty($source) || empty($problem_description) || empty($proposed_action)) {
        $error = "Source, problem description, and proposed action are required.";
    } else {
        try {
            // Generate CAPA number if not provided
            if (empty($capa_no)) {
                $year = date('Y');
                $prefix = $capa_type === 'Corrective' ? 'CA' : 'PA';
                $countStmt = $pdo->query("SELECT COUNT(*) FROM qms_capa WHERE YEAR(created_at) = $year");
                $count = $countStmt->fetchColumn() + 1;
                $capa_no = "CAPA-$prefix-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO qms_capa
                (capa_no, capa_type, source, source_reference, priority, problem_description, affected_area,
                 root_cause_method, root_cause_analysis, proposed_action, implementation_plan,
                 responsible_person, target_date, status, effectiveness_criteria)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $capa_no,
                $capa_type,
                $source,
                $source_reference ?: null,
                $priority,
                $problem_description,
                $affected_area,
                $root_cause_method,
                $root_cause_analysis,
                $proposed_action,
                $implementation_plan,
                $responsible_person,
                $target_date ?: null,
                $status,
                $effectiveness_criteria
            ]);

            header("Location: capa.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error initiating CAPA: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Initiate CAPA - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
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
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #007bff;
        }
        .type-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .type-option {
            flex: 1;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .type-option:hover {
            border-color: #007bff;
        }
        .type-option.selected {
            border-color: #007bff;
            background: #e7f3ff;
        }
        .type-option input {
            display: none;
        }
        .type-option h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .type-option p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Initiate CAPA</h1>
        <a href="capa.php" class="btn btn-secondary">← Back to CAPA List</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>CAPA Process:</strong>
        Corrective Action addresses the root cause of existing non-conformances to prevent recurrence.
        Preventive Action addresses potential non-conformances to prevent their occurrence.
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="type-selector">
                <label class="type-option selected" id="type-corrective">
                    <input type="radio" name="capa_type" value="Corrective" checked>
                    <h4>Corrective Action</h4>
                    <p>Fix existing problem and prevent recurrence</p>
                </label>
                <label class="type-option" id="type-preventive">
                    <input type="radio" name="capa_type" value="Preventive">
                    <h4>Preventive Action</h4>
                    <p>Prevent potential problem from occurring</p>
                </label>
            </div>

            <div class="section-title">Basic Information</div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>CAPA Number</label>
                    <input type="text" name="capa_no" value="<?= htmlspecialchars($_POST['capa_no'] ?? '') ?>"
                           placeholder="Auto-generated if blank">
                </div>
                <div class="form-group">
                    <label class="required">Source</label>
                    <select name="source" required>
                        <option value="">Select Source</option>
                        <option value="NCR">NCR</option>
                        <option value="Customer Complaint">Customer Complaint</option>
                        <option value="Audit Finding">Audit Finding</option>
                        <option value="Process Deviation">Process Deviation</option>
                        <option value="Risk Assessment">Risk Assessment</option>
                        <option value="Management Decision">Management Decision</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Source Reference</label>
                    <input type="text" name="source_reference" value="<?= htmlspecialchars($_POST['source_reference'] ?? '') ?>"
                           placeholder="e.g., NCR-2024-00001">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Affected Area / Process</label>
                    <input type="text" name="affected_area" value="<?= htmlspecialchars($_POST['affected_area'] ?? '') ?>"
                           placeholder="e.g., Production Line 2, Packaging">
                </div>
            </div>

            <div class="section-title">Problem Description</div>

            <div class="form-group">
                <label class="required">Problem / Issue Description</label>
                <textarea name="problem_description" required placeholder="Describe the problem or potential issue in detail. Include what, when, where, and extent of the problem..."><?= htmlspecialchars($_POST['problem_description'] ?? '') ?></textarea>
            </div>

            <div class="section-title">Root Cause Analysis</div>

            <div class="form-row">
                <div class="form-group">
                    <label>Root Cause Analysis Method</label>
                    <select name="root_cause_method">
                        <option value="5 Why">5 Why Analysis</option>
                        <option value="Fishbone">Fishbone (Ishikawa) Diagram</option>
                        <option value="Fault Tree">Fault Tree Analysis</option>
                        <option value="FMEA">FMEA</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group"></div>
            </div>

            <div class="form-group">
                <label>Root Cause Analysis</label>
                <textarea name="root_cause_analysis" placeholder="Document the root cause analysis. For 5 Why, list each 'Why' and its answer..."><?= htmlspecialchars($_POST['root_cause_analysis'] ?? '') ?></textarea>
            </div>

            <div class="section-title">Proposed Action</div>

            <div class="form-group">
                <label class="required">Proposed Corrective / Preventive Action</label>
                <textarea name="proposed_action" required placeholder="Describe the proposed action to address the root cause..."><?= htmlspecialchars($_POST['proposed_action'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Implementation Plan</label>
                <textarea name="implementation_plan" placeholder="Outline the steps for implementing the action, including any resources needed..."><?= htmlspecialchars($_POST['implementation_plan'] ?? '') ?></textarea>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>Responsible Person</label>
                    <input type="text" name="responsible_person" value="<?= htmlspecialchars($_POST['responsible_person'] ?? '') ?>"
                           placeholder="Person responsible">
                </div>
                <div class="form-group">
                    <label>Target Completion Date</label>
                    <input type="date" name="target_date" value="<?= htmlspecialchars($_POST['target_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Initiated">Initiated</option>
                        <option value="Investigation">Investigation</option>
                        <option value="Action Planned">Action Planned</option>
                        <option value="Implementation">Implementation</option>
                        <option value="Verification">Verification</option>
                    </select>
                </div>
            </div>

            <div class="section-title">Effectiveness Verification</div>

            <div class="form-group">
                <label>Effectiveness Criteria</label>
                <textarea name="effectiveness_criteria" placeholder="Define how the effectiveness of this CAPA will be measured (e.g., metrics, timeframe, expected outcomes)..."><?= htmlspecialchars($_POST['effectiveness_criteria'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary">Initiate CAPA</button>
                <a href="capa.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.type-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.type-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
});
</script>

</body>
</html>
