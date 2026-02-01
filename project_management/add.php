<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

// Check if projects table exists
$tableExists = true;
try {
    $pdo->query("SELECT 1 FROM projects LIMIT 1");
} catch (PDOException $e) {
    $tableExists = false;
}

// Available project code prefixes
$project_prefixes = ['PROJ', 'PROJECT', 'ER', 'NPD', 'RND'];

// Function to get next serial number for a prefix
function getNextProjectNumber($pdo, $prefix) {
    $prefixLen = strlen($prefix) + 1; // +1 for the dash
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(project_no, ?) AS UNSIGNED))
        FROM projects
        WHERE project_no LIKE ?
    ");
    $stmt->execute([$prefixLen + 1, $prefix . '-%']);
    $max = $stmt->fetchColumn();
    $next = $max ? ((int)$max + 1) : 1;
    return $prefix . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// Get next numbers for each prefix (for JavaScript)
$prefix_next_numbers = [];
if ($tableExists) {
    foreach ($project_prefixes as $prefix) {
        $prefix_next_numbers[$prefix] = getNextProjectNumber($pdo, $prefix);
    }
} else {
    foreach ($project_prefixes as $prefix) {
        $prefix_next_numbers[$prefix] = $prefix . '-0001';
    }
}

// Default project number
$selected_prefix = isset($_POST['project_prefix']) ? $_POST['project_prefix'] : 'PROJ';
$project_no = $prefix_next_numbers[$selected_prefix] ?? 'PROJ-0001';

// Get customers for dropdown
$customers = $pdo->query("SELECT customer_id, customer_name, company_name FROM customers ORDER BY company_name")->fetchAll();

// Get employees for Project Manager and Project Engineer dropdowns
$employees = [];
try {
    $employees = $pdo->query("SELECT id, first_name, last_name, department FROM employees WHERE status = 'Active' ORDER BY first_name, last_name")->fetchAll();
} catch (PDOException $e) {
    // employees table might not exist or have different structure
    try {
        $employees = $pdo->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();
    } catch (PDOException $e2) {
        // Silently fail - dropdowns will be empty
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $project_name = trim($_POST['project_name'] ?? '');
    $project_manager = trim($_POST['project_manager'] ?? '');
    $project_engineer = trim($_POST['project_engineer'] ?? '');
    $customer_id = trim($_POST['customer_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $status = trim($_POST['status'] ?? 'Planning');
    $priority = trim($_POST['priority'] ?? 'Medium');

    // Handle project prefix and number
    $selected_prefix = trim($_POST['project_prefix'] ?? 'PROJ');
    $custom_prefix = trim($_POST['custom_prefix'] ?? '');

    // If custom prefix is provided, use it
    if ($selected_prefix === 'CUSTOM' && !empty($custom_prefix)) {
        $selected_prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $custom_prefix));
    }

    // Generate the project number with selected prefix
    $project_no = getNextProjectNumber($pdo, $selected_prefix);

    if (empty($project_name)) {
        $errors[] = "Project Name is required";
    }

    if (!empty($budget) && !is_numeric($budget)) {
        $errors[] = "Budget must be a valid number";
    }

    if (!empty($start_date) && !empty($end_date)) {
        if (strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End Date must be after Start Date";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects
                (project_no, project_name, project_manager, project_engineer, customer_id,
                 description, start_date, end_date, budget, status, priority, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $project_no,
                $project_name,
                $project_manager,
                $project_engineer,
                !empty($customer_id) ? $customer_id : null,
                $description,
                !empty($start_date) ? $start_date : null,
                !empty($end_date) ? $end_date : null,
                !empty($budget) ? $budget : null,
                $status,
                $priority,
                $_SESSION['user_id'] ?? 'System'
            ]);

            setModal("Success", "Project created successfully");
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Project</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

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
        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "Dark Mode";
        }
    });
}

// Project number prefix handling
const prefixNumbers = <?= json_encode($prefix_next_numbers) ?>;

function updateProjectNumber() {
    const prefixSelect = document.getElementById('projectPrefix');
    const customInput = document.getElementById('customPrefix');
    const preview = document.getElementById('projectNumberPreview');

    let selectedPrefix = prefixSelect.value;

    // Show/hide custom input
    if (selectedPrefix === 'CUSTOM') {
        customInput.style.display = 'block';
        customInput.focus();
        selectedPrefix = customInput.value || 'CUSTOM';
        if (selectedPrefix && selectedPrefix !== 'CUSTOM') {
            preview.textContent = selectedPrefix + '-0001';
        } else {
            preview.textContent = 'Enter code...';
        }
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
        preview.textContent = prefixNumbers[selectedPrefix] || selectedPrefix + '-0001';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateProjectNumber();
});
</script>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Create New Project</h1>

    <a href="index.php" class="btn btn-secondary">Back to Projects</a>
    <br><br>

    <?php if (!$tableExists): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Setup Required:</strong> The projects table has not been created yet.
            <p style="margin: 10px 0 0 0;">
                <a href="/admin/setup_project_management.php" style="color: #533f03; font-weight: 600;">Click here to run the setup</a>
                to create the required database tables.
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">Project created successfully.</div>
    <?php endif; ?>

    <form method="post" class="form-grid">

        <label>Project Code</label>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select name="project_prefix" id="projectPrefix" style="width: auto; min-width: 120px;" onchange="updateProjectNumber()">
                <?php foreach ($project_prefixes as $prefix): ?>
                    <option value="<?= htmlspecialchars($prefix) ?>" <?= $selected_prefix === $prefix ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prefix) ?>
                    </option>
                <?php endforeach; ?>
                <option value="CUSTOM">Custom...</option>
            </select>
            <input type="text" name="custom_prefix" id="customPrefix" placeholder="Enter custom code"
                   style="width: 120px; display: none;" maxlength="10"
                   onkeyup="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); updateProjectNumber();">
            <span style="font-size: 1.1em; font-weight: 600; color: #667eea;" id="projectNumberPreview">
                <?= htmlspecialchars($project_no) ?>
            </span>
            <input type="hidden" name="generated_project_no" id="generatedProjectNo" value="<?= htmlspecialchars($project_no) ?>">
        </div>

        <label>Project Name *</label>
        <input type="text" name="project_name" required>

        <label>Project Manager</label>
        <select name="project_manager">
            <option value="">-- Select Project Manager --</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    <?php if (!empty($emp['department'])): ?>(<?= htmlspecialchars($emp['department']) ?>)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Project Engineer</label>
        <select name="project_engineer">
            <option value="">-- Select Project Engineer --</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>">
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    <?php if (!empty($emp['department'])): ?>(<?= htmlspecialchars($emp['department']) ?>)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Customer</label>
        <select name="customer_id">
            <option value="">-- Select Customer --</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= htmlspecialchars($c['customer_id']) ?>">
                    <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Status</label>
        <select name="status">
            <option value="Planning">Planning</option>
            <option value="In Progress">In Progress</option>
            <option value="On Hold">On Hold</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
        </select>

        <label>Priority</label>
        <select name="priority">
            <option value="Low">Low</option>
            <option value="Medium" selected>Medium</option>
            <option value="High">High</option>
            <option value="Critical">Critical</option>
        </select>

        <label>Start Date</label>
        <input type="date" name="start_date">

        <label>End Date</label>
        <input type="date" name="end_date">

        <label>Budget</label>
        <input type="number" name="budget" step="0.01" min="0" placeholder="0.00">

        <label>Description</label>
        <textarea name="description" rows="5" style="grid-column: 1 / -1;"></textarea>

        <div></div>
        <button type="submit">Create Project</button>

    </form>

</div>

</body>
</html>
