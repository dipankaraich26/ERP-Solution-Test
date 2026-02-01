<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$task_id || !$project_id) {
    die("Invalid task or project ID");
}

// Fetch task
$task_stmt = $pdo->prepare("SELECT * FROM project_tasks WHERE id = ? AND project_id = ?");
$task_stmt->execute([$task_id, $project_id]);
$task = $task_stmt->fetch();

if (!$task) {
    die("Task not found");
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_name = trim($_POST['task_name'] ?? '');
    $task_start_date = trim($_POST['task_start_date'] ?? '');
    $task_end_date = trim($_POST['task_end_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $remark = trim($_POST['remark'] ?? '');

    if (empty($task_name)) {
        $errors[] = "Task name is required";
    }

    if (!empty($task_start_date) && !empty($task_end_date)) {
        if (strtotime($task_end_date) < strtotime($task_start_date)) {
            $errors[] = "End date must be after start date";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE project_tasks
                SET task_name = ?, task_start_date = ?, task_end_date = ?, status = ?, assigned_to = ?, remark = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $task_name,
                !empty($task_start_date) ? $task_start_date : null,
                !empty($task_end_date) ? $task_end_date : null,
                $status,
                !empty($assigned_to) ? $assigned_to : null,
                !empty($remark) ? $remark : null,
                $task_id
            ]);

            setModal("Success", "Task updated successfully");
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
    <title>Edit Task</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            font-family: inherit;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        body.dark .form-container {
            background: #2c3e50;
        }
        body.dark .form-group input,
        body.dark .form-group select,
        body.dark .form-group textarea {
            background: #34495e;
            color: #ecf0f1;
            border-color: #555;
        }
        body.dark .form-group label {
            color: #ecf0f1;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-row.full {
            grid-template-columns: 1fr;
        }
    </style>
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
    <h1>Edit Task</h1>

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

    <div class="form-container">
        <form method="post">
            <div class="form-group">
                <label>Task Name *</label>
                <input type="text" name="task_name" value="<?= htmlspecialchars($task['task_name']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="task_start_date" value="<?= $task['task_start_date'] ?: '' ?>">
                </div>

                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="task_end_date" value="<?= $task['task_end_date'] ?: '' ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Completed" <?= $task['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="On Hold" <?= $task['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="Cancelled" <?= $task['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned To</label>
                    <input type="text" name="assigned_to" value="<?= htmlspecialchars($task['assigned_to'] ?: '') ?>">
                </div>
            </div>

            <div class="form-group form-row full">
                <label>Remark/Notes</label>
                <textarea name="remark"><?= htmlspecialchars($task['remark'] ?: '') ?></textarea>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Update Task</button>
                <a href="view.php?id=<?= $project_id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

</div>

</body>
</html>
