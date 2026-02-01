<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get suppliers
try {
    $suppliers = $pdo->query("SELECT id, supplier_name as name FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}

$defect_types = ['Dimensional', 'Visual', 'Functional', 'Material', 'Packaging', 'Documentation', 'Other'];
$severities = ['Critical', 'Major', 'Minor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $part_no = trim($_POST['part_no'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');
    $lot_no = trim($_POST['lot_no'] ?? '');
    $ncr_date = trim($_POST['ncr_date'] ?? date('Y-m-d'));
    $defect_type = trim($_POST['defect_type'] ?? '');
    $severity = trim($_POST['severity'] ?? 'Major');
    $qty_affected = (int)($_POST['qty_affected'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $root_cause = trim($_POST['root_cause'] ?? '');
    $containment_action = trim($_POST['containment_action'] ?? '');
    $corrective_action = trim($_POST['corrective_action'] ?? '');
    $preventive_action = trim($_POST['preventive_action'] ?? '');
    $cost_impact = !empty($_POST['cost_impact']) ? (float)$_POST['cost_impact'] : 0;

    // Validation
    if (empty($supplier_id)) $errors[] = "Supplier is required";
    if (empty($defect_type)) $errors[] = "Defect type is required";
    if (empty($description)) $errors[] = "Description is required";

    if (empty($errors)) {
        try {
            // Generate NCR number
            $year = date('Y');
            $month = date('m');
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(ncr_no, 12) AS UNSIGNED)) FROM qc_supplier_ncrs WHERE ncr_no LIKE 'NCR-$year$month-%'")->fetchColumn();
            $ncr_no = 'NCR-' . $year . $month . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO qc_supplier_ncrs (
                    ncr_no, supplier_id, part_no, po_no, lot_no, ncr_date,
                    defect_type, severity, qty_affected, description,
                    root_cause, containment_action, corrective_action,
                    preventive_action, cost_impact, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open', ?)
            ");
            $stmt->execute([
                $ncr_no,
                $supplier_id,
                $part_no ?: null,
                $po_no ?: null,
                $lot_no ?: null,
                $ncr_date,
                $defect_type,
                $severity,
                $qty_affected,
                $description,
                $root_cause ?: null,
                $containment_action ?: null,
                $corrective_action ?: null,
                $preventive_action ?: null,
                $cost_impact,
                $_SESSION['user_id'] ?? null
            ]);

            $newId = $pdo->lastInsertId();
            setModal("Success", "NCR '$ncr_no' created successfully.");
            header("Location: ncr_view.php?id=$newId");
            exit;
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Supplier NCR - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 900px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child { border-bottom: none; }
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #667eea;
            font-size: 1.1em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
        }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }

        .severity-info {
            font-size: 0.85em;
            margin-top: 5px;
            padding: 10px;
            border-radius: 6px;
        }
        .severity-critical { background: #ffebee; color: #c62828; }
        .severity-major { background: #fff3e0; color: #e65100; }
        .severity-minor { background: #e3f2fd; color: #1565c0; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content">
    <div class="page-header">
        <div>
            <h1>New Supplier NCR</h1>
            <p style="color: #666; margin: 5px 0 0;">Non-Conformance Report for supplier quality issues</p>
        </div>
        <a href="ncrs.php" class="btn btn-secondary">Back to NCRs</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 10px 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="post">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Non-Conformance Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier <span class="required">*</span></label>
                        <select name="supplier_id" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($_POST['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>NCR Date <span class="required">*</span></label>
                        <input type="date" name="ncr_date" value="<?= htmlspecialchars($_POST['ncr_date'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Part Number</label>
                        <input type="text" name="part_no" value="<?= htmlspecialchars($_POST['part_no'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" name="po_no" value="<?= htmlspecialchars($_POST['po_no'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Lot Number</label>
                        <input type="text" name="lot_no" value="<?= htmlspecialchars($_POST['lot_no'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Quantity Affected</label>
                        <input type="number" name="qty_affected" value="<?= (int)($_POST['qty_affected'] ?? 0) ?>" min="0">
                    </div>

                    <div class="form-group">
                        <label>Defect Type <span class="required">*</span></label>
                        <select name="defect_type" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($defect_types as $dt): ?>
                                <option value="<?= $dt ?>" <?= ($_POST['defect_type'] ?? '') === $dt ? 'selected' : '' ?>><?= $dt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" id="severity" onchange="updateSeverityInfo()">
                            <?php foreach ($severities as $sv): ?>
                                <option value="<?= $sv ?>" <?= ($_POST['severity'] ?? 'Major') === $sv ? 'selected' : '' ?>><?= $sv ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="severity-info severity-major" id="severity-info">
                            <strong>Major:</strong> Significant defect affecting fit, form, or function. Requires corrective action.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Cost Impact (Rs.)</label>
                        <input type="number" name="cost_impact" value="<?= ($_POST['cost_impact'] ?? '') ?>" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group full-width">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" rows="3" required placeholder="Detailed description of the non-conformance..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Analysis & Actions -->
            <div class="form-section">
                <h3>Analysis & Corrective Actions</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Root Cause</label>
                        <textarea name="root_cause" rows="2" placeholder="What caused this issue?"><?= htmlspecialchars($_POST['root_cause'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Containment Action</label>
                        <textarea name="containment_action" rows="2" placeholder="Immediate actions to contain the issue..."><?= htmlspecialchars($_POST['containment_action'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Corrective Action</label>
                        <textarea name="corrective_action" rows="2" placeholder="Actions to correct the issue and prevent immediate recurrence..."><?= htmlspecialchars($_POST['corrective_action'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Preventive Action</label>
                        <textarea name="preventive_action" rows="2" placeholder="Long-term actions to prevent similar issues..."><?= htmlspecialchars($_POST['preventive_action'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create NCR</button>
                <a href="ncrs.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const severityInfo = {
    'Critical': '<strong>Critical:</strong> Safety hazard or regulatory non-compliance. Immediate containment required. Escalate to management.',
    'Major': '<strong>Major:</strong> Significant defect affecting fit, form, or function. Requires corrective action.',
    'Minor': '<strong>Minor:</strong> Does not affect functionality but deviates from specification. May require process improvement.'
};

function updateSeverityInfo() {
    const severity = document.getElementById('severity').value;
    const infoDiv = document.getElementById('severity-info');
    infoDiv.innerHTML = severityInfo[severity];
    infoDiv.className = 'severity-info severity-' + severity.toLowerCase();
}

updateSeverityInfo();
</script>

</body>
</html>
