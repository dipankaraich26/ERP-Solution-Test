<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_no, project_name FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);

// Get parts for dropdown (only active parts)
$parts = $pdo->query("SELECT part_no, description FROM part_master WHERE status = 'active' ORDER BY part_no")->fetchAll(PDO::FETCH_ASSOC);

// Get users
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $change_type = trim($_POST['change_type'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $reason_for_change = trim($_POST['reason_for_change'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $current_state = trim($_POST['current_state'] ?? '');
    $proposed_change = trim($_POST['proposed_change'] ?? '');
    $impact_quality = trim($_POST['impact_quality'] ?? '');
    $impact_cost = trim($_POST['impact_cost'] ?? '');
    $impact_schedule = trim($_POST['impact_schedule'] ?? '');
    $estimated_cost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : null;
    $requested_by = trim($_POST['requested_by'] ?? '');
    $request_date = trim($_POST['request_date'] ?? date('Y-m-d'));

    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($change_type)) $errors[] = "Change type is required";
    if (empty($reason_for_change)) $errors[] = "Reason for change is required";

    if (empty($errors)) {
        // Generate ECO number
        $year = date('Y');
        $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(eco_no, 5) AS UNSIGNED)) FROM change_requests WHERE eco_no LIKE 'ECO-$year%'")->fetchColumn();
        $eco_no = 'ECO-' . $year . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO change_requests (
                eco_no, project_id, title, change_type, priority,
                reason_for_change, description, current_state, proposed_change,
                impact_quality, impact_cost, impact_schedule, estimated_cost,
                requested_by, requested_user_id, request_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
        ");

        $stmt->execute([
            $eco_no, $project_id, $title, $change_type, $priority,
            $reason_for_change, $description ?: null, $current_state ?: null, $proposed_change ?: null,
            $impact_quality ?: null, $impact_cost ?: null, $impact_schedule ?: null, $estimated_cost,
            $requested_by ?: null, $_SESSION['user_id'] ?? null, $request_date
        ]);

        $eco_id = $pdo->lastInsertId();

        // Add affected parts if any
        if (!empty($_POST['affected_parts']) && is_array($_POST['affected_parts'])) {
            $partStmt = $pdo->prepare("
                INSERT INTO eco_affected_parts (eco_id, part_no, part_description, change_description)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($_POST['affected_parts'] as $index => $part_no) {
                if (!empty($part_no)) {
                    $partStmt->execute([
                        $eco_id,
                        $part_no,
                        $_POST['part_descriptions'][$index] ?? null,
                        $_POST['part_changes'][$index] ?? null
                    ]);
                }
            }
        }

        setModal("Success", "Change Request '$eco_no' created successfully!");
        header("Location: eco_view.php?id=$eco_id");
        exit;
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Change Request - Product Engineering</title>
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
            max-width: 1000px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
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

        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.85em;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        /* Affected Parts Table */
        .parts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .parts-table th, .parts-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        .parts-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .parts-table input, .parts-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .add-part-btn {
            margin-top: 10px;
            padding: 8px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .remove-part-btn {
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .parts-table th { background: #34495e; }
        body.dark .parts-table td { border-color: #4a6278; }
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

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>New Change Request (ECO)</h1>
            <p style="color: #666; margin: 5px 0 0;">Create an Engineering Change Order</p>
        </div>
        <a href="change_requests.php" class="btn btn-secondary">Back to ECOs</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
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
                <h3>Change Request Information</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required placeholder="Brief title describing the change">
                    </div>

                    <div class="form-group">
                        <label>Change Type <span class="required">*</span></label>
                        <select name="change_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Design Change" <?= ($_POST['change_type'] ?? '') === 'Design Change' ? 'selected' : '' ?>>Design Change</option>
                            <option value="Material Change" <?= ($_POST['change_type'] ?? '') === 'Material Change' ? 'selected' : '' ?>>Material Change</option>
                            <option value="Process Change" <?= ($_POST['change_type'] ?? '') === 'Process Change' ? 'selected' : '' ?>>Process Change</option>
                            <option value="Document Change" <?= ($_POST['change_type'] ?? '') === 'Document Change' ? 'selected' : '' ?>>Document Change</option>
                            <option value="Supplier Change" <?= ($_POST['change_type'] ?? '') === 'Supplier Change' ? 'selected' : '' ?>>Supplier Change</option>
                            <option value="Specification Change" <?= ($_POST['change_type'] ?? '') === 'Specification Change' ? 'selected' : '' ?>>Specification Change</option>
                            <option value="Other" <?= ($_POST['change_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="priority" required>
                            <option value="Critical" <?= ($_POST['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical - Immediate action required</option>
                            <option value="High" <?= ($_POST['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High - Urgent</option>
                            <option value="Medium" <?= ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium - Normal</option>
                            <option value="Low" <?= ($_POST['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low - When convenient</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Project (Optional)</label>
                        <select name="project_id">
                            <option value="">-- Not linked to a project --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= ($_POST['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['project_no'] . ' - ' . $proj['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Requested By</label>
                        <input type="text" name="requested_by" value="<?= htmlspecialchars($_POST['requested_by'] ?? ($_SESSION['user_name'] ?? '')) ?>" placeholder="Name of requestor">
                    </div>

                    <div class="form-group">
                        <label>Request Date</label>
                        <input type="date" name="request_date" value="<?= htmlspecialchars($_POST['request_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
            </div>

            <!-- Change Details -->
            <div class="form-section">
                <h3>Change Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Reason for Change <span class="required">*</span></label>
                        <textarea name="reason_for_change" rows="3" required placeholder="Why is this change needed?"><?= htmlspecialchars($_POST['reason_for_change'] ?? '') ?></textarea>
                        <small>Clearly explain the problem or opportunity that requires this change</small>
                    </div>

                    <div class="form-group full-width">
                        <label>Current State</label>
                        <textarea name="current_state" rows="3" placeholder="Describe the current situation or design..."><?= htmlspecialchars($_POST['current_state'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Proposed Change</label>
                        <textarea name="proposed_change" rows="3" placeholder="Describe the proposed change or solution..."><?= htmlspecialchars($_POST['proposed_change'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Detailed Description</label>
                        <textarea name="description" rows="4" placeholder="Provide detailed description of the change..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Impact Analysis -->
            <div class="form-section">
                <h3>Impact Analysis</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Quality Impact</label>
                        <textarea name="impact_quality" rows="3" placeholder="How will this affect product quality?"><?= htmlspecialchars($_POST['impact_quality'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Cost Impact</label>
                        <textarea name="impact_cost" rows="3" placeholder="What are the cost implications?"><?= htmlspecialchars($_POST['impact_cost'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Schedule Impact</label>
                        <textarea name="impact_schedule" rows="3" placeholder="How will this affect timelines?"><?= htmlspecialchars($_POST['impact_schedule'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Estimated Cost</label>
                        <input type="number" name="estimated_cost" step="0.01" min="0" value="<?= htmlspecialchars($_POST['estimated_cost'] ?? '') ?>" placeholder="0.00">
                        <small>Total estimated cost to implement this change</small>
                    </div>
                </div>
            </div>

            <!-- Affected Parts -->
            <div class="form-section">
                <h3>Affected Parts</h3>
                <p style="color: #666; margin-bottom: 15px;">List the parts/components affected by this change</p>

                <table class="parts-table" id="partsTable">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Part Number</th>
                            <th style="width: 30%;">Description</th>
                            <th style="width: 35%;">Change Description</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="partsBody">
                        <tr>
                            <td>
                                <input type="text" name="affected_parts[]" list="partsList" placeholder="Select or type part no">
                            </td>
                            <td>
                                <input type="text" name="part_descriptions[]" placeholder="Part description">
                            </td>
                            <td>
                                <input type="text" name="part_changes[]" placeholder="What changes for this part?">
                            </td>
                            <td>
                                <button type="button" class="remove-part-btn" onclick="removePartRow(this)">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <datalist id="partsList">
                    <?php foreach ($parts as $part): ?>
                        <option value="<?= htmlspecialchars($part['part_no']) ?>"><?= htmlspecialchars($part['description']) ?></option>
                    <?php endforeach; ?>
                </datalist>

                <button type="button" class="add-part-btn" onclick="addPartRow()">+ Add Part</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Change Request</button>
                <a href="change_requests.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addPartRow() {
    const tbody = document.getElementById('partsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <input type="text" name="affected_parts[]" list="partsList" placeholder="Select or type part no">
        </td>
        <td>
            <input type="text" name="part_descriptions[]" placeholder="Part description">
        </td>
        <td>
            <input type="text" name="part_changes[]" placeholder="What changes for this part?">
        </td>
        <td>
            <button type="button" class="remove-part-btn" onclick="removePartRow(this)">X</button>
        </td>
    `;
    tbody.appendChild(row);
}

function removePartRow(btn) {
    const tbody = document.getElementById('partsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
    }
}
</script>

</body>
</html>
