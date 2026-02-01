<?php
/**
 * Add Quality Issue
 * Form to create new field or internal quality issues
 */
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Check if tables exist
try {
    $pdo->query("SELECT 1 FROM qc_quality_issues LIMIT 1");
} catch (PDOException $e) {
    header("Location: setup_quality_issues.php");
    exit;
}

// Get employees for assignment dropdowns
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

// Get projects
$projects = [];
try {
    $projects = $pdo->query("SELECT id, project_no, project_name FROM projects WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY project_name")->fetchAll();
} catch (PDOException $e) {}

// Get issue categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM qc_issue_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
} catch (PDOException $e) {}

// Issue types
$issue_types = ['Field Issue', 'Internal Issue', 'Customer Complaint', 'Supplier Issue', 'Process Issue'];
$issue_sources = ['Customer', 'Internal Inspection', 'Production', 'Warehouse', 'Shipping', 'Installation', 'Service', 'Audit', 'Other'];
$priorities = ['Critical', 'High', 'Medium', 'Low'];
$severities = ['Critical', 'Major', 'Minor', 'Observation'];
$detection_stages = ['Incoming', 'In-Process', 'Final Inspection', 'Packing', 'Shipping', 'Installation', 'Field', 'Customer Use'];
$root_cause_categories = ['Man', 'Machine', 'Method', 'Material', 'Measurement', 'Environment', 'Other'];

// Departments
$departments = ['Production', 'Quality', 'Engineering', 'Maintenance', 'Stores', 'Purchase', 'Sales', 'Service', 'HR', 'Admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic fields
    $issue_type = trim($_POST['issue_type'] ?? '');
    $issue_source = trim($_POST['issue_source'] ?? 'Internal Inspection');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'Other');

    // References
    $part_no = trim($_POST['part_no'] ?? '');
    $lot_no = trim($_POST['lot_no'] ?? '');
    $serial_no = trim($_POST['serial_no'] ?? '');
    $work_order_no = trim($_POST['work_order_no'] ?? '');
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    // Location and detection
    $location = trim($_POST['location'] ?? '');
    $detection_stage = trim($_POST['detection_stage'] ?? 'In-Process');

    // Quantity
    $qty_affected = (int)($_POST['qty_affected'] ?? 0);
    $qty_scrapped = (int)($_POST['qty_scrapped'] ?? 0);
    $qty_reworked = (int)($_POST['qty_reworked'] ?? 0);

    // Priority and severity
    $priority = trim($_POST['priority'] ?? 'Medium');
    $severity = trim($_POST['severity'] ?? 'Major');

    // Cost
    $cost_impact = !empty($_POST['cost_impact']) ? (float)$_POST['cost_impact'] : 0;

    // Dates
    $issue_date = trim($_POST['issue_date'] ?? date('Y-m-d'));
    $target_closure_date = trim($_POST['target_closure_date'] ?? '');

    // Assignment
    $reported_by = trim($_POST['reported_by'] ?? '');
    $reported_by_id = !empty($_POST['reported_by_id']) ? (int)$_POST['reported_by_id'] : null;
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $assigned_to_id = !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null;
    $department = trim($_POST['department'] ?? '');

    // Root cause
    $root_cause = trim($_POST['root_cause'] ?? '');
    $root_cause_category = trim($_POST['root_cause_category'] ?? '');
    $why_analysis = trim($_POST['why_analysis'] ?? '');

    // Containment
    $containment_action = trim($_POST['containment_action'] ?? '');

    // Validation
    if (empty($issue_type)) $errors[] = "Issue type is required";
    if (empty($title)) $errors[] = "Issue title is required";
    if (empty($description)) $errors[] = "Description is required";

    if (empty($errors)) {
        try {
            // Generate issue number: QI-YYYYMM-XXXX
            $year = date('Y');
            $month = date('m');
            $prefix = 'QI-' . $year . $month;
            $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(issue_no, 11) AS UNSIGNED)) FROM qc_quality_issues WHERE issue_no LIKE '$prefix-%'")->fetchColumn();
            $issue_no = $prefix . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO qc_quality_issues (
                    issue_no, issue_type, issue_source, title, description, category,
                    part_no, lot_no, serial_no, work_order_no, customer_id, customer_name, supplier_id, supplier_name, project_id,
                    location, detection_stage,
                    qty_affected, qty_scrapped, qty_reworked,
                    priority, severity, cost_impact,
                    issue_date, target_closure_date,
                    reported_by, reported_by_id, assigned_to, assigned_to_id, department,
                    root_cause, root_cause_category, why_analysis,
                    containment_action,
                    status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open', ?)
            ");

            $stmt->execute([
                $issue_no, $issue_type, $issue_source, $title, $description, $category,
                $part_no ?: null, $lot_no ?: null, $serial_no ?: null, $work_order_no ?: null,
                $customer_id, $customer_name ?: null, $supplier_id, $supplier_name ?: null, $project_id,
                $location ?: null, $detection_stage,
                $qty_affected, $qty_scrapped, $qty_reworked,
                $priority, $severity, $cost_impact,
                $issue_date, $target_closure_date ?: null,
                $reported_by ?: null, $reported_by_id, $assigned_to ?: null, $assigned_to_id, $department ?: null,
                $root_cause ?: null, $root_cause_category ?: null, $why_analysis ?: null,
                $containment_action ?: null,
                $_SESSION['user_id'] ?? null
            ]);

            $newId = $pdo->lastInsertId();
            setModal("Success", "Quality Issue '$issue_no' created successfully!");
            header("Location: issue_view.php?id=$newId");
            exit;

        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Quality Issue - QC</title>
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
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #667eea;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section h3 .section-icon {
            width: 28px;
            height: 28px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
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
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }
        .error-box ul { margin: 10px 0 0; padding-left: 20px; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .priority-indicator {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .priority-indicator span {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            cursor: pointer;
        }
        .priority-indicator span.active {
            box-shadow: 0 0 0 2px #667eea;
        }
        .pi-critical { background: #ffebee; color: #c62828; }
        .pi-high { background: #fff3e0; color: #e65100; }
        .pi-medium { background: #e3f2fd; color: #1565c0; }
        .pi-low { background: #e8f5e9; color: #2e7d32; }

        .issue-type-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .issue-type-card {
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .issue-type-card:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .issue-type-card.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .issue-type-card input { display: none; }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
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
            <h1>New Quality Issue</h1>
            <p style="color: #666; margin: 5px 0 0;">Report a field or internal quality issue</p>
        </div>
        <a href="issues.php" class="btn btn-secondary">Back to Issues</a>
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
            <!-- Issue Type Selection -->
            <div class="form-section">
                <h3><span class="section-icon">1</span> Issue Type</h3>
                <div class="issue-type-cards">
                    <?php foreach ($issue_types as $type): ?>
                        <label class="issue-type-card <?= ($_POST['issue_type'] ?? '') === $type ? 'selected' : '' ?>">
                            <input type="radio" name="issue_type" value="<?= $type ?>" <?= ($_POST['issue_type'] ?? '') === $type ? 'checked' : '' ?> required>
                            <?= $type ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Issue Source</label>
                        <select name="issue_source">
                            <?php foreach ($issue_sources as $src): ?>
                                <option value="<?= $src ?>" <?= ($_POST['issue_source'] ?? 'Internal Inspection') === $src ? 'selected' : '' ?>><?= $src ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Date <span class="required">*</span></label>
                        <input type="date" name="issue_date" value="<?= htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>
            </div>

            <!-- Issue Details -->
            <div class="form-section">
                <h3><span class="section-icon">2</span> Issue Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Issue Title <span class="required">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required placeholder="Brief title describing the issue">
                    </div>

                    <div class="form-group full-width">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" rows="4" required placeholder="Detailed description of the quality issue..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="Dimensional">Dimensional</option>
                            <option value="Visual">Visual / Cosmetic</option>
                            <option value="Functional">Functional</option>
                            <option value="Material">Material</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Documentation">Documentation</option>
                            <option value="Process">Process</option>
                            <option value="Safety">Safety</option>
                            <option value="Other" selected>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Detection Stage</label>
                        <select name="detection_stage">
                            <?php foreach ($detection_stages as $stage): ?>
                                <option value="<?= $stage ?>" <?= ($_POST['detection_stage'] ?? 'In-Process') === $stage ? 'selected' : '' ?>><?= $stage ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location Found</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" placeholder="e.g., Assembly Line 2, Customer Site">
                    </div>
                </div>
            </div>

            <!-- References -->
            <div class="form-section">
                <h3><span class="section-icon">3</span> References</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Part Number</label>
                        <input type="text" name="part_no" value="<?= htmlspecialchars($_POST['part_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Lot Number</label>
                        <input type="text" name="lot_no" value="<?= htmlspecialchars($_POST['lot_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_no" value="<?= htmlspecialchars($_POST['serial_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Work Order No</label>
                        <input type="text" name="work_order_no" value="<?= htmlspecialchars($_POST['work_order_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" id="customer_select" onchange="updateCustomerName()">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>" data-name="<?= htmlspecialchars($c['company_name']) ?>" <?= ($_POST['customer_id'] ?? '') == $c['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="customer_name" id="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" id="supplier_select" onchange="updateSupplierName()">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>" <?= ($_POST['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="supplier_name" id="supplier_name" value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Project</label>
                        <select name="project_id">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($_POST['project_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['project_no'] . ' - ' . $p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Quantity & Impact -->
            <div class="form-section">
                <h3><span class="section-icon">4</span> Quantity & Impact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Quantity Affected</label>
                        <input type="number" name="qty_affected" value="<?= (int)($_POST['qty_affected'] ?? 0) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Quantity Scrapped</label>
                        <input type="number" name="qty_scrapped" value="<?= (int)($_POST['qty_scrapped'] ?? 0) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Quantity Reworked</label>
                        <input type="number" name="qty_reworked" value="<?= (int)($_POST['qty_reworked'] ?? 0) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Cost Impact (Rs.)</label>
                        <input type="number" name="cost_impact" value="<?= ($_POST['cost_impact'] ?? '') ?>" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
            </div>

            <!-- Priority & Assignment -->
            <div class="form-section">
                <h3><span class="section-icon">5</span> Priority & Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="priority" id="priority_select" onchange="updatePriorityIndicator()">
                            <?php foreach ($priorities as $p): ?>
                                <option value="<?= $p ?>" <?= ($_POST['priority'] ?? 'Medium') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="priority-indicator">
                            <span class="pi-critical">Critical - Immediate action</span>
                            <span class="pi-high">High - Within 24 hours</span>
                            <span class="pi-medium active">Medium - Within 1 week</span>
                            <span class="pi-low">Low - As scheduled</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Severity</label>
                        <select name="severity">
                            <?php foreach ($severities as $s): ?>
                                <option value="<?= $s ?>" <?= ($_POST['severity'] ?? 'Major') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Reported By</label>
                        <select name="reported_by_id" onchange="updateReportedByName(this)">
                            <option value="">-- Select --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['emp_name']) ?>" <?= ($_POST['reported_by_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['emp_name']) ?>
                                    <?php if (!empty($emp['department'])): ?>(<?= $emp['department'] ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="reported_by" id="reported_by" value="<?= htmlspecialchars($_POST['reported_by'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to_id" onchange="updateAssignedToName(this)">
                            <option value="">-- Select --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['emp_name']) ?>" data-dept="<?= htmlspecialchars($emp['department'] ?? '') ?>" <?= ($_POST['assigned_to_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['emp_name']) ?>
                                    <?php if (!empty($emp['department'])): ?>(<?= $emp['department'] ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="assigned_to" id="assigned_to" value="<?= htmlspecialchars($_POST['assigned_to'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="department_select">
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target Closure Date</label>
                        <input type="date" name="target_closure_date" value="<?= htmlspecialchars($_POST['target_closure_date'] ?? '') ?>">
                        <small>Based on priority: Critical=Today, High=+1 day, Medium=+7 days, Low=+14 days</small>
                    </div>
                </div>
            </div>

            <!-- Root Cause & Containment -->
            <div class="form-section">
                <h3><span class="section-icon">6</span> Root Cause & Containment (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Root Cause Category (Ishikawa)</label>
                        <select name="root_cause_category">
                            <option value="">-- Select --</option>
                            <?php foreach ($root_cause_categories as $rcc): ?>
                                <option value="<?= $rcc ?>" <?= ($_POST['root_cause_category'] ?? '') === $rcc ? 'selected' : '' ?>><?= $rcc ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Root Cause Description</label>
                        <textarea name="root_cause" rows="2" placeholder="What is the root cause of this issue?"><?= htmlspecialchars($_POST['root_cause'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>5-Why Analysis</label>
                        <textarea name="why_analysis" rows="3" placeholder="Why 1: ...&#10;Why 2: ...&#10;Why 3: ...&#10;Why 4: ...&#10;Why 5: ..."><?= htmlspecialchars($_POST['why_analysis'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Containment Action</label>
                        <textarea name="containment_action" rows="2" placeholder="Immediate actions taken to contain the issue..."><?= htmlspecialchars($_POST['containment_action'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Issue</button>
                <a href="issues.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Issue type card selection
document.querySelectorAll('.issue-type-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.issue-type-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
    });
});

function updateCustomerName() {
    const select = document.getElementById('customer_select');
    const nameInput = document.getElementById('customer_name');
    const selected = select.options[select.selectedIndex];
    nameInput.value = selected.dataset.name || '';
}

function updateSupplierName() {
    const select = document.getElementById('supplier_select');
    const nameInput = document.getElementById('supplier_name');
    const selected = select.options[select.selectedIndex];
    nameInput.value = selected.dataset.name || '';
}

function updateReportedByName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('reported_by').value = selected.dataset.name || '';
}

function updateAssignedToName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('assigned_to').value = selected.dataset.name || '';
    if (selected.dataset.dept) {
        document.getElementById('department_select').value = selected.dataset.dept;
    }
}

function updatePriorityIndicator() {
    const priority = document.getElementById('priority_select').value;
    const indicators = document.querySelectorAll('.priority-indicator span');
    indicators.forEach(span => span.classList.remove('active'));

    const priorityClass = 'pi-' + priority.toLowerCase();
    document.querySelector('.' + priorityClass)?.classList.add('active');

    // Auto-set target date based on priority
    const targetInput = document.querySelector('input[name="target_closure_date"]');
    if (!targetInput.value) {
        const today = new Date();
        let days = 7;
        if (priority === 'Critical') days = 0;
        else if (priority === 'High') days = 1;
        else if (priority === 'Medium') days = 7;
        else if (priority === 'Low') days = 14;

        today.setDate(today.getDate() + days);
        targetInput.value = today.toISOString().split('T')[0];
    }
}

// Initialize
updatePriorityIndicator();
</script>

</body>
</html>
