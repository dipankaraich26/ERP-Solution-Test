<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

$types = ['Incoming Inspection', 'In-Process', 'Final Inspection', 'Outgoing', 'Supplier Audit', 'Process Audit', 'Product Audit', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checklist_name = trim($_POST['checklist_name'] ?? '');
    $checklist_type = trim($_POST['checklist_type'] ?? '');
    $applicable_to = trim($_POST['applicable_to'] ?? '');
    $revision = trim($_POST['revision'] ?? 'Rev A');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Draft');

    // Get check points
    $checkpoints = $_POST['checkpoint'] ?? [];
    $specifications = $_POST['specification'] ?? [];
    $methods = $_POST['method'] ?? [];
    $criteria = $_POST['criteria'] ?? [];
    $is_critical = $_POST['is_critical'] ?? [];
    $categories = $_POST['category'] ?? [];

    // Validation
    if (empty($checklist_name)) $errors[] = "Checklist name is required";
    if (empty($checklist_type)) $errors[] = "Checklist type is required";

    // Check if at least one checkpoint is provided
    $valid_checkpoints = array_filter($checkpoints, function($cp) { return !empty(trim($cp)); });
    if (empty($valid_checkpoints)) $errors[] = "At least one check point is required";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate checklist number
            $year = date('Y');
            $prefix = 'CL';
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(checklist_no, 9) AS UNSIGNED)) FROM qc_checklists WHERE checklist_no LIKE '$prefix-$year-%'")->fetchColumn();
            $checklist_no = $prefix . '-' . $year . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            // Insert checklist
            $stmt = $pdo->prepare("
                INSERT INTO qc_checklists (checklist_no, checklist_name, checklist_type, applicable_to, revision, description, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $checklist_no,
                $checklist_name,
                $checklist_type,
                $applicable_to ?: null,
                $revision,
                $description ?: null,
                $status,
                $_SESSION['user_id'] ?? null
            ]);
            $checklist_id = $pdo->lastInsertId();

            // Insert check points
            $item_stmt = $pdo->prepare("
                INSERT INTO qc_checklist_items (checklist_id, item_no, check_point, specification, method, acceptance_criteria, is_critical, category, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $item_no = 1;
            foreach ($checkpoints as $idx => $checkpoint) {
                if (!empty(trim($checkpoint))) {
                    $item_stmt->execute([
                        $checklist_id,
                        $item_no,
                        trim($checkpoint),
                        !empty($specifications[$idx]) ? trim($specifications[$idx]) : null,
                        !empty($methods[$idx]) ? trim($methods[$idx]) : null,
                        !empty($criteria[$idx]) ? trim($criteria[$idx]) : null,
                        isset($is_critical[$idx]) ? 1 : 0,
                        !empty($categories[$idx]) ? trim($categories[$idx]) : null,
                        $item_no
                    ]);
                    $item_no++;
                }
            }

            $pdo->commit();
            setModal("Success", "Checklist '$checklist_no' created with " . ($item_no - 1) . " check points.");
            header("Location: checklist_view.php?id=$checklist_id");
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
    <title>Create Checklist - QC</title>
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
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
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

        .checkpoints-table {
            width: 100%;
            border-collapse: collapse;
        }
        .checkpoints-table th {
            background: #f8f9fa;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .checkpoints-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .checkpoints-table input, .checkpoints-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .checkpoints-table input[type="checkbox"] { width: auto; }

        .remove-row {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .remove-row:hover { background: #c0392b; }

        .add-row-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
        }
        .add-row-btn:hover { background: #219a52; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .checkpoints-table th { background: #34495e; color: #ecf0f1; }
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
            <h1>Create Quality Checklist</h1>
            <p style="color: #666; margin: 5px 0 0;">Define inspection or audit check points</p>
        </div>
        <a href="checklists.php" class="btn btn-secondary">Back to Checklists</a>
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
        <form method="post" id="checklistForm">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Checklist Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Checklist Name <span class="required">*</span></label>
                        <input type="text" name="checklist_name" value="<?= htmlspecialchars($_POST['checklist_name'] ?? '') ?>" required placeholder="e.g., Incoming Inspection - Raw Materials">
                    </div>

                    <div class="form-group">
                        <label>Checklist Type <span class="required">*</span></label>
                        <select name="checklist_type" required>
                            <option value="">-- Select Type --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t ?>" <?= ($_POST['checklist_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Applicable To</label>
                        <input type="text" name="applicable_to" value="<?= htmlspecialchars($_POST['applicable_to'] ?? '') ?>" placeholder="Part number, process name, or 'General'">
                    </div>

                    <div class="form-group">
                        <label>Revision</label>
                        <input type="text" name="revision" value="<?= htmlspecialchars($_POST['revision'] ?? 'Rev A') ?>">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Draft" <?= ($_POST['status'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Active" <?= ($_POST['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description of this checklist..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Check Points -->
            <div class="form-section">
                <h3>Check Points</h3>
                <p style="color: #666; margin-bottom: 15px;">Define the inspection/audit check points. Mark critical items that require special attention.</p>

                <div style="overflow-x: auto;">
                    <table class="checkpoints-table" id="checkpointsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 25%;">Check Point <span class="required">*</span></th>
                                <th style="width: 15%;">Specification</th>
                                <th style="width: 15%;">Method/Tool</th>
                                <th style="width: 15%;">Acceptance Criteria</th>
                                <th style="width: 12%;">Category</th>
                                <th style="width: 60px;">Critical</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="checkpointsBody">
                            <tr>
                                <td class="row-num">1</td>
                                <td><input type="text" name="checkpoint[]" placeholder="What to check"></td>
                                <td><input type="text" name="specification[]" placeholder="Spec/Tolerance"></td>
                                <td><input type="text" name="method[]" placeholder="How to check"></td>
                                <td><input type="text" name="criteria[]" placeholder="Pass/Fail criteria"></td>
                                <td>
                                    <select name="category[]">
                                        <option value="">--</option>
                                        <option value="Visual">Visual</option>
                                        <option value="Dimensional">Dimensional</option>
                                        <option value="Functional">Functional</option>
                                        <option value="Material">Material</option>
                                        <option value="Documentation">Documentation</option>
                                        <option value="Packaging">Packaging</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </td>
                                <td style="text-align: center;"><input type="checkbox" name="is_critical[0]" value="1"></td>
                                <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="add-row-btn" onclick="addRow()">+ Add Check Point</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Checklist</button>
                <a href="checklists.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
let rowCount = 1;

function addRow() {
    const tbody = document.getElementById('checkpointsBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td class="row-num">${rowCount + 1}</td>
        <td><input type="text" name="checkpoint[]" placeholder="What to check"></td>
        <td><input type="text" name="specification[]" placeholder="Spec/Tolerance"></td>
        <td><input type="text" name="method[]" placeholder="How to check"></td>
        <td><input type="text" name="criteria[]" placeholder="Pass/Fail criteria"></td>
        <td>
            <select name="category[]">
                <option value="">--</option>
                <option value="Visual">Visual</option>
                <option value="Dimensional">Dimensional</option>
                <option value="Functional">Functional</option>
                <option value="Material">Material</option>
                <option value="Documentation">Documentation</option>
                <option value="Packaging">Packaging</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td style="text-align: center;"><input type="checkbox" name="is_critical[${rowCount}]" value="1"></td>
        <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
    `;
    tbody.appendChild(newRow);
    rowCount++;
    renumberRows();
}

function removeRow(btn) {
    const tbody = document.getElementById('checkpointsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        renumberRows();
    }
}

function renumberRows() {
    const rows = document.querySelectorAll('#checkpointsBody tr');
    rows.forEach((row, index) => {
        row.querySelector('.row-num').textContent = index + 1;
    });
}
</script>

</body>
</html>
