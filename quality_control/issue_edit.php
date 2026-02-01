<?php
/**
 * Edit Quality Issue
 */
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$issue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$issue_id) {
    header("Location: issues.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM qc_quality_issues WHERE id = ?");
$stmt->execute([$issue_id]);
$issue = $stmt->fetch();

if (!$issue) {
    setModal("Error", "Issue not found");
    header("Location: issues.php");
    exit;
}

$errors = [];

// Get employees
$employees = [];
try {
    $employees = $pdo->query("SELECT id, emp_name, department FROM employees WHERE status = 'Active' ORDER BY emp_name")->fetchAll();
} catch (PDOException $e) {
    try {
        $employees = $pdo->query("SELECT id, full_name as emp_name, '' as department FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
    } catch (PDOException $e2) {}
}

// Get customers
$customers = [];
try {
    $customers = $pdo->query("SELECT customer_id, customer_name, company_name FROM customers ORDER BY company_name")->fetchAll();
} catch (PDOException $e) {}

// Get suppliers
$suppliers = [];
try {
    $suppliers = $pdo->query("SELECT id, supplier_name as name FROM suppliers ORDER BY supplier_name")->fetchAll();
} catch (PDOException $e) {}

// Issue types and options
$issue_types = ['Field Issue', 'Internal Issue', 'Customer Complaint', 'Supplier Issue', 'Process Issue'];
$issue_sources = ['Customer', 'Internal Inspection', 'Production', 'Warehouse', 'Shipping', 'Installation', 'Service', 'Audit', 'Other'];
$priorities = ['Critical', 'High', 'Medium', 'Low'];
$severities = ['Critical', 'Major', 'Minor', 'Observation'];
$detection_stages = ['Incoming', 'In-Process', 'Final Inspection', 'Packing', 'Shipping', 'Installation', 'Field', 'Customer Use'];
$root_cause_categories = ['Man', 'Machine', 'Method', 'Material', 'Measurement', 'Environment', 'Other'];
$departments = ['Production', 'Quality', 'Engineering', 'Maintenance', 'Stores', 'Purchase', 'Sales', 'Service', 'HR', 'Admin'];
$categories = ['Dimensional', 'Visual', 'Functional', 'Material', 'Packaging', 'Documentation', 'Process', 'Safety', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_type = trim($_POST['issue_type'] ?? '');
    $issue_source = trim($_POST['issue_source'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'Other');
    $part_no = trim($_POST['part_no'] ?? '');
    $lot_no = trim($_POST['lot_no'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');
    $work_order_no = trim($_POST['work_order_no'] ?? '');
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $detection_stage = trim($_POST['detection_stage'] ?? 'In-Process');
    $qty_affected = (int)($_POST['qty_affected'] ?? 0);
    $qty_scrapped = (int)($_POST['qty_scrapped'] ?? 0);
    $qty_reworked = (int)($_POST['qty_reworked'] ?? 0);
    $priority = trim($_POST['priority'] ?? 'Medium');
    $severity = trim($_POST['severity'] ?? 'Major');
    $cost_impact = !empty($_POST['cost_impact']) ? (float)$_POST['cost_impact'] : 0;
    $issue_date = trim($_POST['issue_date'] ?? '');
    $target_closure_date = trim($_POST['target_closure_date'] ?? '');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $assigned_to_id = !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null;
    $department = trim($_POST['department'] ?? '');
    $root_cause = trim($_POST['root_cause'] ?? '');
    $root_cause_category = trim($_POST['root_cause_category'] ?? '');
    $why_analysis = trim($_POST['why_analysis'] ?? '');
    $containment_action = trim($_POST['containment_action'] ?? '');

    if (empty($issue_type)) $errors[] = "Issue type is required";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE qc_quality_issues SET
                issue_type = ?, issue_source = ?, title = ?, description = ?, category = ?,
                part_no = ?, lot_no = ?, serial_no = ?, work_order_no = ?,
                customer_id = ?, customer_name = ?, supplier_id = ?, supplier_name = ?, location = ?, detection_stage = ?,
                qty_affected = ?, qty_scrapped = ?, qty_reworked = ?,
                priority = ?, severity = ?, cost_impact = ?,
                issue_date = ?, target_closure_date = ?,
                assigned_to = ?, assigned_to_id = ?, department = ?,
                root_cause = ?, root_cause_category = ?, why_analysis = ?,
                containment_action = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $issue_type, $issue_source, $title, $description, $category,
            $part_no ?: null, $lot_no ?: null, $serial_no ?: null, $work_order_no ?: null,
            $customer_id, $customer_name ?: null, $supplier_id, $supplier_name ?: null, $location ?: null, $detection_stage,
            $qty_affected, $qty_scrapped, $qty_reworked,
            $priority, $severity, $cost_impact,
            $issue_date, $target_closure_date ?: null,
            $assigned_to ?: null, $assigned_to_id, $department ?: null,
            $root_cause ?: null, $root_cause_category ?: null, $why_analysis ?: null,
            $containment_action ?: null,
            $issue_id
        ]);

        setModal("Success", "Issue updated successfully");
        header("Location: issue_view.php?id=$issue_id");
        exit;
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit <?= htmlspecialchars($issue['issue_no']) ?> - Quality Issue</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h1 { margin: 0; color: #2c3e50; }
        .form-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 1000px; }
        .form-section { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .form-section:last-child { border-bottom: none; }
        .form-section h3 { margin: 0 0 15px 0; color: #667eea; font-size: 1.1em; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #495057; }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; box-sizing: border-box; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .error-box { background: #ffebee; border: 1px solid #ffcdd2; border-radius: 6px; padding: 15px; margin-bottom: 20px; color: #c62828; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; }
        body.dark .form-container { background: #2c3e50; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea { background: #34495e; border-color: #4a6278; color: #ecf0f1; }
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
            <h1>Edit Issue: <?= htmlspecialchars($issue['issue_no']) ?></h1>
            <p style="color: #666; margin: 5px 0 0;"><?= htmlspecialchars($issue['title']) ?></p>
        </div>
        <a href="issue_view.php?id=<?= $issue_id ?>" class="btn btn-secondary">Cancel</a>
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
            <div class="form-section">
                <h3>Issue Type & Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Issue Type <span class="required">*</span></label>
                        <select name="issue_type" required>
                            <?php foreach ($issue_types as $t): ?>
                                <option value="<?= $t ?>" <?= $issue['issue_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Source</label>
                        <select name="issue_source">
                            <?php foreach ($issue_sources as $s): ?>
                                <option value="<?= $s ?>" <?= $issue['issue_source'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issue_date" value="<?= htmlspecialchars($issue['issue_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>" <?= $issue['category'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($issue['title']) ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" rows="4" required><?= htmlspecialchars($issue['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>References</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Part Number</label>
                        <input type="text" name="part_no" value="<?= htmlspecialchars($issue['part_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Lot Number</label>
                        <input type="text" name="lot_no" value="<?= htmlspecialchars($issue['lot_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_no" value="<?= htmlspecialchars($issue['serial_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Work Order No</label>
                        <input type="text" name="work_order_no" value="<?= htmlspecialchars($issue['work_order_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" onchange="updateCustomerName(this)">
                            <option value="">-- Select --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>" data-name="<?= htmlspecialchars($c['company_name']) ?>" <?= $issue['customer_id'] == $c['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="customer_name" id="customer_name" value="<?= htmlspecialchars($issue['customer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" onchange="updateSupplierName(this)">
                            <option value="">-- Select --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>" <?= ($issue['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="supplier_name" id="supplier_name" value="<?= htmlspecialchars($issue['supplier_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Location Found</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($issue['location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Detection Stage</label>
                        <select name="detection_stage">
                            <?php foreach ($detection_stages as $d): ?>
                                <option value="<?= $d ?>" <?= $issue['detection_stage'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Quantity & Impact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Quantity Affected</label>
                        <input type="number" name="qty_affected" value="<?= (int)$issue['qty_affected'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Quantity Scrapped</label>
                        <input type="number" name="qty_scrapped" value="<?= (int)$issue['qty_scrapped'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Quantity Reworked</label>
                        <input type="number" name="qty_reworked" value="<?= (int)$issue['qty_reworked'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Cost Impact (Rs.)</label>
                        <input type="number" name="cost_impact" value="<?= $issue['cost_impact'] ?>" min="0" step="0.01">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Priority & Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <?php foreach ($priorities as $p): ?>
                                <option value="<?= $p ?>" <?= $issue['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Severity</label>
                        <select name="severity">
                            <?php foreach ($severities as $s): ?>
                                <option value="<?= $s ?>" <?= $issue['severity'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to_id" onchange="updateAssignedName(this)">
                            <option value="">-- Select --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['emp_name']) ?>" data-dept="<?= htmlspecialchars($emp['department'] ?? '') ?>" <?= $issue['assigned_to_id'] == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['emp_name']) ?>
                                    <?php if (!empty($emp['department'])): ?>(<?= $emp['department'] ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="assigned_to" id="assigned_to" value="<?= htmlspecialchars($issue['assigned_to'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="department_select">
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d ?>" <?= $issue['department'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Closure Date</label>
                        <input type="date" name="target_closure_date" value="<?= htmlspecialchars($issue['target_closure_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Root Cause & Containment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Root Cause Category</label>
                        <select name="root_cause_category">
                            <option value="">-- Select --</option>
                            <?php foreach ($root_cause_categories as $r): ?>
                                <option value="<?= $r ?>" <?= $issue['root_cause_category'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Root Cause</label>
                        <textarea name="root_cause" rows="2"><?= htmlspecialchars($issue['root_cause'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>5-Why Analysis</label>
                        <textarea name="why_analysis" rows="3"><?= htmlspecialchars($issue['why_analysis'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Containment Action</label>
                        <textarea name="containment_action" rows="2"><?= htmlspecialchars($issue['containment_action'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="issue_view.php?id=<?= $issue_id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function updateCustomerName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('customer_name').value = selected.dataset.name || '';
}

function updateSupplierName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('supplier_name').value = selected.dataset.name || '';
}

function updateAssignedName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('assigned_to').value = selected.dataset.name || '';
    if (selected.dataset.dept) {
        document.getElementById('department_select').value = selected.dataset.dept;
    }
}
</script>

</body>
</html>
