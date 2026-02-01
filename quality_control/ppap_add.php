<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get suppliers and projects
try {
    $suppliers = $pdo->query("SELECT id, supplier_name as name FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}

try {
    $projects = $pdo->query("SELECT id, project_no, project_name FROM projects WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

// PPAP 18 Elements
$ppap_elements = [
    1 => ['name' => 'Design Records', 'desc' => 'Customer engineering design records, drawings, specifications'],
    2 => ['name' => 'Engineering Change Documents', 'desc' => 'Authorized engineering change documentation'],
    3 => ['name' => 'Customer Engineering Approval', 'desc' => 'Evidence of customer engineering approval'],
    4 => ['name' => 'Design FMEA', 'desc' => 'Design Failure Mode and Effects Analysis'],
    5 => ['name' => 'Process Flow Diagram', 'desc' => 'Process flow showing manufacturing steps'],
    6 => ['name' => 'Process FMEA', 'desc' => 'Process Failure Mode and Effects Analysis'],
    7 => ['name' => 'Control Plan', 'desc' => 'Documentation of product/process characteristics and controls'],
    8 => ['name' => 'Measurement System Analysis', 'desc' => 'MSA studies for all measurement systems'],
    9 => ['name' => 'Dimensional Results', 'desc' => 'Dimensional verification of sample parts'],
    10 => ['name' => 'Material/Performance Test Results', 'desc' => 'Material certifications and performance test results'],
    11 => ['name' => 'Initial Process Studies', 'desc' => 'Process capability studies (Cpk/Ppk)'],
    12 => ['name' => 'Qualified Laboratory Documentation', 'desc' => 'Lab accreditation and scope documentation'],
    13 => ['name' => 'Appearance Approval Report', 'desc' => 'AAR for parts with appearance requirements'],
    14 => ['name' => 'Sample Production Parts', 'desc' => 'Representative sample parts from production run'],
    15 => ['name' => 'Master Sample', 'desc' => 'Retained master sample for reference'],
    16 => ['name' => 'Checking Aids', 'desc' => 'Fixtures, gages, templates used for inspection'],
    17 => ['name' => 'Customer-Specific Requirements', 'desc' => 'Additional customer-specific requirements'],
    18 => ['name' => 'Part Submission Warrant (PSW)', 'desc' => 'Summary document for PPAP submission']
];

// Submission levels and their required elements
$level_requirements = [
    'Level 1' => [18], // PSW only
    'Level 2' => [9, 10, 11, 14, 18], // Warrant + limited documents
    'Level 3' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18], // All elements
    'Level 4' => [18], // PSW + customer-defined
    'Level 5' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18] // All elements on-site
];

$submission_reasons = ['Initial Submission', 'Engineering Change', 'Tooling Transfer', 'Correction of Discrepancy', 'Tooling Inactive', 'Sub-supplier Change', 'Material Change', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_no = trim($_POST['part_no'] ?? '');
    $part_name = trim($_POST['part_name'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $submission_level = trim($_POST['submission_level'] ?? 'Level 3');
    $submission_reason = trim($_POST['submission_reason'] ?? '');
    $submission_date = trim($_POST['submission_date'] ?? '');
    $required_date = trim($_POST['required_date'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($part_no)) $errors[] = "Part number is required";
    if (empty($submission_reason)) $errors[] = "Submission reason is required";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate PPAP number
            $year = date('Y');
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(ppap_no, 10) AS UNSIGNED)) FROM qc_ppap_submissions WHERE ppap_no LIKE 'PPAP-$year-%'")->fetchColumn();
            $ppap_no = 'PPAP-' . $year . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            // Insert PPAP submission
            $stmt = $pdo->prepare("
                INSERT INTO qc_ppap_submissions (ppap_no, part_no, part_name, customer_name, submission_level, submission_reason, submission_date, required_date, supplier_id, project_id, notes, overall_status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
            ");
            $stmt->execute([
                $ppap_no,
                $part_no,
                $part_name ?: null,
                $customer_name ?: null,
                $submission_level,
                $submission_reason,
                $submission_date ?: null,
                $required_date ?: null,
                $supplier_id,
                $project_id,
                $notes ?: null,
                $_SESSION['user_id'] ?? null
            ]);
            $ppap_id = $pdo->lastInsertId();

            // Create PPAP elements based on level
            $required_elements = $level_requirements[$submission_level] ?? $level_requirements['Level 3'];

            $elem_stmt = $pdo->prepare("
                INSERT INTO qc_ppap_elements (ppap_id, element_no, element_name, required, status)
                VALUES (?, ?, ?, ?, 'Not Started')
            ");

            foreach ($ppap_elements as $num => $element) {
                $is_required = in_array($num, $required_elements) ? 1 : 0;
                $elem_stmt->execute([$ppap_id, $num, $element['name'], $is_required]);
            }

            $pdo->commit();
            setModal("Success", "PPAP '$ppap_no' created successfully with " . count($required_elements) . " required elements.");
            header("Location: ppap_view.php?id=$ppap_id");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New PPAP Submission - QC</title>
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
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }

        .level-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        .level-info h4 { margin: 0 0 10px; color: #0c5460; }
        .level-info ul { margin: 0; padding-left: 20px; color: #0c5460; font-size: 0.9em; }

        .elements-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .elements-preview h4 { margin: 0 0 15px; color: #495057; }
        .elements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
        }
        .element-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            font-size: 0.9em;
        }
        .element-item.required { border-left: 3px solid #27ae60; }
        .element-item.optional { border-left: 3px solid #95a5a6; opacity: 0.7; }
        .element-num {
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            margin-right: 10px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .elements-preview { background: #34495e; }
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
            <h1>New PPAP Submission</h1>
            <p style="color: #666; margin: 5px 0 0;">Production Part Approval Process</p>
        </div>
        <a href="ppap.php" class="btn btn-secondary">Back to PPAP</a>
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
            <!-- Part Information -->
            <div class="form-section">
                <h3>Part Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Part Number <span class="required">*</span></label>
                        <input type="text" name="part_no" value="<?= htmlspecialchars($_POST['part_no'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Part Name</label>
                        <input type="text" name="part_name" value="<?= htmlspecialchars($_POST['part_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($_POST['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Project</label>
                        <select name="project_id">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($_POST['project_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['project_no'] . ' - ' . $p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Submission Details -->
            <div class="form-section">
                <h3>Submission Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Submission Level <span class="required">*</span></label>
                        <select name="submission_level" id="submission_level" onchange="updateLevelInfo()">
                            <option value="Level 1">Level 1 - Warrant Only</option>
                            <option value="Level 2">Level 2 - Warrant + Limited</option>
                            <option value="Level 3" selected>Level 3 - Warrant + All (Default)</option>
                            <option value="Level 4">Level 4 - Customer Defined</option>
                            <option value="Level 5">Level 5 - Warrant + All (On-Site)</option>
                        </select>
                        <div class="level-info" id="level-info">
                            <h4>Level 3 Requirements</h4>
                            <ul>
                                <li>Part Submission Warrant (PSW)</li>
                                <li>All 18 PPAP elements submitted</li>
                                <li>Most common submission level</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Submission Reason <span class="required">*</span></label>
                        <select name="submission_reason" required>
                            <option value="">-- Select Reason --</option>
                            <?php foreach ($submission_reasons as $r): ?>
                                <option value="<?= $r ?>" <?= ($_POST['submission_reason'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Submission Date</label>
                        <input type="date" name="submission_date" value="<?= htmlspecialchars($_POST['submission_date'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Required Date</label>
                        <input type="date" name="required_date" value="<?= htmlspecialchars($_POST['required_date'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- PPAP Elements Preview -->
            <div class="elements-preview">
                <h4>18 PPAP Elements (will be created based on level)</h4>
                <div class="elements-grid">
                    <?php foreach ($ppap_elements as $num => $element): ?>
                        <div class="element-item required" id="elem-<?= $num ?>">
                            <span class="element-num"><?= $num ?></span>
                            <span><?= $element['name'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create PPAP</button>
                <a href="ppap.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const levelInfo = {
    'Level 1': {
        title: 'Level 1 - Warrant Only',
        desc: ['Part Submission Warrant (PSW) only', 'For low-risk parts with established history', 'Minimal documentation'],
        required: [18]
    },
    'Level 2': {
        title: 'Level 2 - Warrant + Limited Documents',
        desc: ['PSW with product samples', 'Dimensional results', 'Material/performance test results', 'Initial process studies'],
        required: [9, 10, 11, 14, 18]
    },
    'Level 3': {
        title: 'Level 3 - Warrant + All Documents (Default)',
        desc: ['All 18 PPAP elements required', 'Most common submission level', 'Complete documentation package'],
        required: [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18]
    },
    'Level 4': {
        title: 'Level 4 - Customer Defined',
        desc: ['PSW and customer-specified elements', 'Requirements defined by customer', 'Tailored submission'],
        required: [18]
    },
    'Level 5': {
        title: 'Level 5 - Warrant + All (On-Site Review)',
        desc: ['Complete documentation reviewed on-site', 'All 18 elements available at supplier', 'For critical or complex parts'],
        required: [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18]
    }
};

function updateLevelInfo() {
    const level = document.getElementById('submission_level').value;
    const info = levelInfo[level];
    const infoDiv = document.getElementById('level-info');

    let html = '<h4>' + info.title + '</h4><ul>';
    info.desc.forEach(d => html += '<li>' + d + '</li>');
    html += '</ul>';
    infoDiv.innerHTML = html;

    // Update element highlighting
    for (let i = 1; i <= 18; i++) {
        const elem = document.getElementById('elem-' + i);
        if (info.required.includes(i)) {
            elem.classList.add('required');
            elem.classList.remove('optional');
        } else {
            elem.classList.remove('required');
            elem.classList.add('optional');
        }
    }
}
</script>

</body>
</html>
