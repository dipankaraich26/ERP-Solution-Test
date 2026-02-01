<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    die("Invalid project ID");
}

// Fetch project
$project_stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
    die("Project not found");
}

// Get customers
$customers = $pdo->query("SELECT customer_id, customer_name, company_name FROM customers ORDER BY company_name")->fetchAll();

// Get employees for Project Manager and Project Engineer dropdowns
$employees = [];
try {
    $employees = $pdo->query("SELECT id, first_name, last_name, department FROM employees WHERE status = 'Active' ORDER BY first_name, last_name")->fetchAll();
} catch (PDOException $e) {
    try {
        $employees = $pdo->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll();
    } catch (PDOException $e2) {
        // Silently fail - dropdowns will be empty
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $progress_percentage = (int)$_POST['progress_percentage'] ?? 0;

    if (empty($project_name)) {
        $errors[] = "Project Name is required";
    }

    if (!is_numeric($progress_percentage) || $progress_percentage < 0 || $progress_percentage > 100) {
        $errors[] = "Progress must be between 0 and 100";
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
                UPDATE projects
                SET project_name = ?, project_manager = ?, project_engineer = ?, customer_id = ?,
                    description = ?, start_date = ?, end_date = ?, budget = ?,
                    status = ?, priority = ?, progress_percentage = ?
                WHERE id = ?
            ");

            $stmt->execute([
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
                $progress_percentage,
                $project_id
            ]);

            setModal("Success", "Project updated successfully");
            header("Location: view.php?id=" . $project_id);
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
    <title>Edit Project</title>
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
</script>

<div class="content">
    <h1>Edit Project</h1>

    <a href="view.php?id=<?= $project_id ?>" class="btn btn-secondary">Back to Project</a>
    <br><br>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">

        <label>Project Number</label>
        <input type="text" value="<?= htmlspecialchars($project['project_no']) ?>" readonly>

        <label>Project Name *</label>
        <input type="text" name="project_name" value="<?= htmlspecialchars($project['project_name']) ?>" required>

        <label>Project Manager</label>
        <select name="project_manager">
            <option value="">-- Select Project Manager --</option>
            <?php foreach ($employees as $emp):
                $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
            ?>
                <option value="<?= htmlspecialchars($fullName) ?>" <?= ($project['project_manager'] ?? '') === $fullName ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fullName) ?>
                    <?php if (!empty($emp['department'])): ?>(<?= htmlspecialchars($emp['department']) ?>)<?php endif; ?>
                </option>
            <?php endforeach; ?>
            <?php if (!empty($project['project_manager']) && !in_array($project['project_manager'], array_map(function($e) { return $e['first_name'] . ' ' . $e['last_name']; }, $employees))): ?>
                <option value="<?= htmlspecialchars($project['project_manager']) ?>" selected><?= htmlspecialchars($project['project_manager']) ?> (Not in employee list)</option>
            <?php endif; ?>
        </select>

        <label>Project Engineer</label>
        <select name="project_engineer">
            <option value="">-- Select Project Engineer --</option>
            <?php foreach ($employees as $emp):
                $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
            ?>
                <option value="<?= htmlspecialchars($fullName) ?>" <?= ($project['project_engineer'] ?? '') === $fullName ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fullName) ?>
                    <?php if (!empty($emp['department'])): ?>(<?= htmlspecialchars($emp['department']) ?>)<?php endif; ?>
                </option>
            <?php endforeach; ?>
            <?php if (!empty($project['project_engineer']) && !in_array($project['project_engineer'], array_map(function($e) { return $e['first_name'] . ' ' . $e['last_name']; }, $employees))): ?>
                <option value="<?= htmlspecialchars($project['project_engineer']) ?>" selected><?= htmlspecialchars($project['project_engineer']) ?> (Not in employee list)</option>
            <?php endif; ?>
        </select>

        <label>Customer</label>
        <select name="customer_id">
            <option value="">-- Select Customer --</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= htmlspecialchars($c['customer_id']) ?>"
                    <?= $project['customer_id'] === $c['customer_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Status</label>
        <select name="status">
            <option value="Planning" <?= $project['status'] === 'Planning' ? 'selected' : '' ?>>Planning</option>
            <option value="In Progress" <?= $project['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="On Hold" <?= $project['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
            <option value="Completed" <?= $project['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $project['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <label>Priority</label>
        <select name="priority">
            <option value="Low" <?= $project['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
            <option value="Medium" <?= $project['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="High" <?= $project['priority'] === 'High' ? 'selected' : '' ?>>High</option>
            <option value="Critical" <?= $project['priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
        </select>

        <label>Progress (%)</label>
        <input type="number" name="progress_percentage" value="<?= (int)$project['progress_percentage'] ?>" min="0" max="100" step="5">

        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= $project['start_date'] ?: '' ?>">

        <label>End Date</label>
        <input type="date" name="end_date" value="<?= $project['end_date'] ?: '' ?>">

        <label>Budget</label>
        <input type="number" name="budget" step="0.01" min="0" value="<?= $project['budget'] ?: '' ?>">

        <label>Description</label>
        <textarea name="description" rows="5" style="grid-column: 1 / -1;"><?= htmlspecialchars($project['description'] ?: '') ?></textarea>

        <div></div>
        <button type="submit">Update Project</button>

    </form>

</div>

</body>
</html>
