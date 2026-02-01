<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid task ID");
}

// Get task
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Task not found");
}

$errors = [];

// Get categories
$categories = $pdo->query("SELECT id, category_name, color_code FROM task_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Get employees
$employees = $pdo->query("SELECT id, emp_id, CONCAT(first_name, ' ', last_name) as name, department FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Get customers
$customers = $pdo->query("SELECT customer_id, customer_name, company_name FROM customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);

// Get projects
$projects = $pdo->query("SELECT id, project_no, project_name FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $task_name = trim($_POST['task_name'] ?? '');
    $task_description = trim($_POST['task_description'] ?? '');
    $category_id = $_POST['category_id'] ?: null;
    $priority = $_POST['priority'] ?? 'Medium';
    $status = $_POST['status'] ?? 'Not Started';
    $assigned_to = $_POST['assigned_to'] ?: null;
    $start_date = $_POST['start_date'] ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $completed_date = $_POST['completed_date'] ?: null;
    $progress_percent = (int)($_POST['progress_percent'] ?? 0);
    $estimated_hours = $_POST['estimated_hours'] ?: null;
    $actual_hours = $_POST['actual_hours'] ?: null;
    $customer_id = $_POST['customer_id'] ?: null;
    $project_id = $_POST['project_id'] ?: null;
    $related_module = $_POST['related_module'] ?: null;
    $related_reference = trim($_POST['related_reference'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Auto-set completed date if status changed to Completed
    if ($status === 'Completed' && $task['status'] !== 'Completed' && !$completed_date) {
        $completed_date = date('Y-m-d');
    }
    // Auto-set progress to 100% if completed
    if ($status === 'Completed') {
        $progress_percent = 100;
    }

    // Validation
    if (empty($task_name)) {
        $errors[] = "Task name is required";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tasks SET
                    task_name = ?, task_description = ?, category_id = ?, priority = ?, status = ?,
                    assigned_to = ?, start_date = ?, due_date = ?, completed_date = ?,
                    progress_percent = ?, estimated_hours = ?, actual_hours = ?,
                    customer_id = ?, project_id = ?, related_module = ?, related_reference = ?,
                    remarks = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $task_name,
                $task_description,
                $category_id,
                $priority,
                $status,
                $assigned_to,
                $start_date,
                $due_date,
                $completed_date,
                $progress_percent,
                $estimated_hours,
                $actual_hours,
                $customer_id,
                $project_id,
                $related_module,
                $related_reference,
                $remarks,
                $id
            ]);

            // Add activity log for status change
            if ($status !== $task['status']) {
                $commentStmt = $pdo->prepare("
                    INSERT INTO task_comments (task_id, comment, commented_by, comment_type)
                    VALUES (?, ?, ?, 'status_change')
                ");
                $commentStmt->execute([
                    $id,
                    "Status changed from '{$task['status']}' to '$status'",
                    $_SESSION['employee_id'] ?? null
                ]);
            }

            // Add activity log for assignment change
            if ($assigned_to != $task['assigned_to']) {
                $commentStmt = $pdo->prepare("
                    INSERT INTO task_comments (task_id, comment, commented_by, comment_type)
                    VALUES (?, ?, ?, 'assignment')
                ");
                $assigneeName = $assigned_to ? $pdo->query("SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE id = $assigned_to")->fetchColumn() : 'Unassigned';
                $commentStmt->execute([
                    $id,
                    "Task reassigned to $assigneeName",
                    $_SESSION['employee_id'] ?? null
                ]);
            }

            setModal("Success", "Task updated successfully!");
            header("Location: view.php?id=$id");
            exit;

        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $task;
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Task - <?= htmlspecialchars($task['task_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-section h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 1.2em;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 5px 0 0 20px; padding: 0; }

        .priority-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .priority-option {
            flex: 1;
            min-width: 80px;
        }
        .priority-option input { display: none; }
        .priority-option label {
            display: block;
            padding: 10px 15px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        .priority-option input:checked + label {
            border-color: currentColor;
        }
        .priority-option.critical label { color: #c62828; }
        .priority-option.critical input:checked + label { background: #ffebee; }
        .priority-option.high label { color: #ef6c00; }
        .priority-option.high input:checked + label { background: #fff3e0; }
        .priority-option.medium label { color: #f9a825; }
        .priority-option.medium input:checked + label { background: #fffde7; }
        .priority-option.low label { color: #78909c; }
        .priority-option.low input:checked + label { background: #eceff1; }

        .status-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-option {
            flex: 1;
            min-width: 90px;
        }
        .status-option input { display: none; }
        .status-option label {
            display: block;
            padding: 10px 8px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85em;
        }
        .status-option input:checked + label {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        .status-option.completed input:checked + label {
            background: #27ae60;
            border-color: #27ae60;
        }
        .status-option.cancelled input:checked + label {
            background: #e74c3c;
            border-color: #e74c3c;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 20px;
        }

        .progress-slider {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .progress-slider input[type="range"] {
            flex: 1;
            height: 8px;
            -webkit-appearance: none;
            background: #e0e0e0;
            border-radius: 4px;
            outline: none;
        }
        .progress-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #667eea;
            border-radius: 50%;
            cursor: pointer;
        }
        .progress-value {
            font-weight: bold;
            min-width: 45px;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Task</h1>
        <p style="margin-bottom: 20px;">
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Back to Task</a>
            <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">All Tasks</a>
            <span style="color: #999; margin-left: 15px;"><?= htmlspecialchars($task['task_no']) ?></span>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">
            <!-- Basic Information -->
            <div class="form-section">
                <h2>Task Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Task Number</label>
                        <input type="text" value="<?= htmlspecialchars($task['task_no']) ?>" readonly style="background: #f5f5f5;">
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Task Name <span class="required">*</span></label>
                        <input type="text" name="task_name" value="<?= htmlspecialchars($_POST['task_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="task_description"><?= htmlspecialchars($_POST['task_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Priority & Status -->
            <div class="form-section">
                <h2>Priority & Status</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Priority</label>
                        <div class="priority-options">
                            <div class="priority-option critical">
                                <input type="radio" name="priority" value="Critical" id="p_critical"
                                       <?= ($_POST['priority'] ?? '') === 'Critical' ? 'checked' : '' ?>>
                                <label for="p_critical">Critical</label>
                            </div>
                            <div class="priority-option high">
                                <input type="radio" name="priority" value="High" id="p_high"
                                       <?= ($_POST['priority'] ?? '') === 'High' ? 'checked' : '' ?>>
                                <label for="p_high">High</label>
                            </div>
                            <div class="priority-option medium">
                                <input type="radio" name="priority" value="Medium" id="p_medium"
                                       <?= ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'checked' : '' ?>>
                                <label for="p_medium">Medium</label>
                            </div>
                            <div class="priority-option low">
                                <input type="radio" name="priority" value="Low" id="p_low"
                                       <?= ($_POST['priority'] ?? '') === 'Low' ? 'checked' : '' ?>>
                                <label for="p_low">Low</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" name="status" value="Not Started" id="s_not"
                                       <?= ($_POST['status'] ?? '') === 'Not Started' ? 'checked' : '' ?>>
                                <label for="s_not">Not Started</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="In Progress" id="s_progress"
                                       <?= ($_POST['status'] ?? '') === 'In Progress' ? 'checked' : '' ?>>
                                <label for="s_progress">In Progress</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="On Hold" id="s_hold"
                                       <?= ($_POST['status'] ?? '') === 'On Hold' ? 'checked' : '' ?>>
                                <label for="s_hold">On Hold</label>
                            </div>
                            <div class="status-option completed">
                                <input type="radio" name="status" value="Completed" id="s_completed"
                                       <?= ($_POST['status'] ?? '') === 'Completed' ? 'checked' : '' ?>>
                                <label for="s_completed">Completed</label>
                            </div>
                            <div class="status-option cancelled">
                                <input type="radio" name="status" value="Cancelled" id="s_cancelled"
                                       <?= ($_POST['status'] ?? '') === 'Cancelled' ? 'checked' : '' ?>>
                                <label for="s_cancelled">Cancelled</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Progress</label>
                        <div class="progress-slider">
                            <input type="range" name="progress_percent" id="progress" min="0" max="100" step="5"
                                   value="<?= (int)($_POST['progress_percent'] ?? 0) ?>">
                            <span class="progress-value" id="progressValue"><?= (int)($_POST['progress_percent'] ?? 0) ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment & Dates -->
            <div class="form-section">
                <h2>Assignment & Timeline</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($_POST['assigned_to'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['name']) ?>
                                <?= $emp['department'] ? '(' . htmlspecialchars($emp['department']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Estimated Hours</label>
                        <input type="number" name="estimated_hours" min="0" step="0.5"
                               value="<?= htmlspecialchars($_POST['estimated_hours'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Actual Hours</label>
                        <input type="number" name="actual_hours" min="0" step="0.5"
                               value="<?= htmlspecialchars($_POST['actual_hours'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Completed Date</label>
                        <input type="date" name="completed_date" value="<?= htmlspecialchars($_POST['completed_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Related To -->
            <div class="form-section">
                <h2>Related To</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Related Module</label>
                        <select name="related_module">
                            <option value="">-- None --</option>
                            <option value="sales" <?= ($_POST['related_module'] ?? '') === 'sales' ? 'selected' : '' ?>>Sales</option>
                            <option value="marketing" <?= ($_POST['related_module'] ?? '') === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                            <option value="hr" <?= ($_POST['related_module'] ?? '') === 'hr' ? 'selected' : '' ?>>HR</option>
                            <option value="inventory" <?= ($_POST['related_module'] ?? '') === 'inventory' ? 'selected' : '' ?>>Inventory</option>
                            <option value="purchase" <?= ($_POST['related_module'] ?? '') === 'purchase' ? 'selected' : '' ?>>Purchase</option>
                            <option value="operations" <?= ($_POST['related_module'] ?? '') === 'operations' ? 'selected' : '' ?>>Operations</option>
                            <option value="service" <?= ($_POST['related_module'] ?? '') === 'service' ? 'selected' : '' ?>>Service</option>
                            <option value="finance" <?= ($_POST['related_module'] ?? '') === 'finance' ? 'selected' : '' ?>>Finance</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Related Reference</label>
                        <input type="text" name="related_reference" placeholder="e.g., INV-0001, PO-0012"
                               value="<?= htmlspecialchars($_POST['related_reference'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['customer_id'] ?>" <?= ($_POST['customer_id'] ?? '') == $cust['customer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cust['customer_name']) ?>
                                <?= $cust['company_name'] ? '(' . htmlspecialchars($cust['company_name']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Project</label>
                        <select name="project_id">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($_POST['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_no']) ?> - <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-section">
                <h2>Additional Information</h2>
                <div class="form-group">
                    <label>Remarks / Notes</label>
                    <textarea name="remarks"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="btn-row">
                <a href="delete.php?id=<?= $id ?>" class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to delete this task?')">Delete Task</a>
                <div>
                    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">Update Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Progress slider
const progressSlider = document.getElementById('progress');
const progressValue = document.getElementById('progressValue');

progressSlider.addEventListener('input', function() {
    progressValue.textContent = this.value + '%';
});

// Auto-set progress to 100% when Completed is selected
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'Completed') {
            progressSlider.value = 100;
            progressValue.textContent = '100%';
        }
    });
});
</script>

</body>
</html>
