<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

// Get categories
$categories = $pdo->query("SELECT id, category_name, color_code FROM task_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Get employees
$employees = $pdo->query("SELECT id, emp_id, CONCAT(first_name, ' ', last_name) as name, department FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Get customers
$customers = $pdo->query("SELECT customer_id, customer_name, company_name FROM customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);

// Get projects
$projects = $pdo->query("SELECT id, project_no, project_name FROM projects WHERE status != 'Completed' ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);

// Generate task number
$max = $pdo->query("SELECT MAX(CAST(SUBSTRING(task_no, 6) AS UNSIGNED)) FROM tasks WHERE task_no LIKE 'TASK-%'")->fetchColumn();
$next = $max ? ((int)$max + 1) : 1;
$task_no = 'TASK-' . str_pad($next, 5, '0', STR_PAD_LEFT);

// Pre-fill from URL params
$prefill_category = $_GET['category'] ?? '';
$prefill_project = $_GET['project_id'] ?? '';
$prefill_customer = $_GET['customer_id'] ?? '';
$prefill_module = $_GET['module'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $task_name = trim($_POST['task_name'] ?? '');
    $task_description = trim($_POST['task_description'] ?? '');
    $category_id = $_POST['category_id'] ?: null;
    $priority = $_POST['priority'] ?? 'Medium';
    $status = $_POST['status'] ?? 'Not Started';
    $assigned_to = $_POST['assigned_to'] ?: null;
    $start_date = $_POST['start_date'] ?: null;
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $all_day = isset($_POST['all_day']) ? 1 : 0;
    $due_date = $_POST['due_date'] ?: null;
    $estimated_hours = $_POST['estimated_hours'] ?: null;
    $customer_id = $_POST['customer_id'] ?: null;
    $project_id = $_POST['project_id'] ?: null;
    $related_module = $_POST['related_module'] ?: null;
    $related_reference = trim($_POST['related_reference'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Validation
    if (empty($task_name)) {
        $errors[] = "Task name is required";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    task_no, task_name, task_description, category_id, priority, status,
                    assigned_to, assigned_by, start_date, start_time, end_time, all_day, due_date, estimated_hours,
                    customer_id, project_id, related_module, related_reference, remarks,
                    created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");

            $stmt->execute([
                $task_no,
                $task_name,
                $task_description,
                $category_id,
                $priority,
                $status,
                $assigned_to,
                $_SESSION['employee_id'] ?? null,
                $start_date,
                $start_time,
                $end_time,
                $all_day,
                $due_date,
                $estimated_hours,
                $customer_id,
                $project_id,
                $related_module,
                $related_reference,
                $remarks,
                $_SESSION['employee_id'] ?? null
            ]);

            $newId = $pdo->lastInsertId();

            // Add activity log
            if ($assigned_to) {
                $commentStmt = $pdo->prepare("
                    INSERT INTO task_comments (task_id, comment, commented_by, comment_type)
                    VALUES (?, ?, ?, 'assignment')
                ");
                $commentStmt->execute([
                    $newId,
                    "Task created and assigned",
                    $_SESSION['employee_id'] ?? null
                ]);
            }

            setModal("Success", "Task $task_no created successfully!");
            header("Location: view.php?id=$newId");
            exit;

        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create New Task - Task Management</title>
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
            gap: 10px;
            flex-wrap: wrap;
        }
        .status-option {
            flex: 1;
            min-width: 100px;
        }
        .status-option input { display: none; }
        .status-option label {
            display: block;
            padding: 10px 12px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9em;
        }
        .status-option input:checked + label {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .category-select {
            position: relative;
        }
        .category-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Create New Task</h1>
        <p style="margin-bottom: 20px;">
            <a href="index.php" class="btn btn-secondary">Back to Tasks</a>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">Dashboard</a>
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
                        <input type="text" value="<?= htmlspecialchars($task_no) ?>" readonly style="background: #f5f5f5;">
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                    data-color="<?= htmlspecialchars($cat['color_code']) ?>"
                                    <?= ($prefill_category == $cat['id'] || ($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Task Name <span class="required">*</span></label>
                        <input type="text" name="task_name" value="<?= htmlspecialchars($_POST['task_name'] ?? '') ?>" required
                               placeholder="Enter a clear, descriptive task name">
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="task_description" placeholder="Describe what needs to be done..."><?= htmlspecialchars($_POST['task_description'] ?? '') ?></textarea>
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
                        <label>Initial Status</label>
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" name="status" value="Not Started" id="s_not"
                                       <?= ($_POST['status'] ?? 'Not Started') === 'Not Started' ? 'checked' : '' ?>>
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
                               value="<?= htmlspecialchars($_POST['estimated_hours'] ?? '') ?>"
                               placeholder="e.g., 8">
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="all_day" value="1" <?= isset($_POST['all_day']) || !isset($_POST['start_time']) ? 'checked' : '' ?> onchange="toggleTimeFields(this)">
                            All Day Task
                        </label>
                    </div>

                    <div class="form-group time-field" style="<?= isset($_POST['start_time']) ? '' : 'display:none;' ?>">
                        <label>Start Time</label>
                        <input type="time" name="start_time" value="<?= htmlspecialchars($_POST['start_time'] ?? '09:00') ?>">
                    </div>

                    <div class="form-group time-field" style="<?= isset($_POST['start_time']) ? '' : 'display:none;' ?>">
                        <label>End Time</label>
                        <input type="time" name="end_time" value="<?= htmlspecialchars($_POST['end_time'] ?? '17:00') ?>">
                    </div>
                </div>
            </div>

            <script>
            function toggleTimeFields(checkbox) {
                const timeFields = document.querySelectorAll('.time-field');
                timeFields.forEach(field => {
                    field.style.display = checkbox.checked ? 'none' : 'block';
                });
            }
            </script>

            <!-- Related To -->
            <div class="form-section">
                <h2>Related To (Optional)</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Related Module</label>
                        <select name="related_module">
                            <option value="">-- None --</option>
                            <option value="sales" <?= ($prefill_module === 'sales' || ($_POST['related_module'] ?? '') === 'sales') ? 'selected' : '' ?>>Sales</option>
                            <option value="marketing" <?= ($prefill_module === 'marketing' || ($_POST['related_module'] ?? '') === 'marketing') ? 'selected' : '' ?>>Marketing</option>
                            <option value="hr" <?= ($prefill_module === 'hr' || ($_POST['related_module'] ?? '') === 'hr') ? 'selected' : '' ?>>HR</option>
                            <option value="inventory" <?= ($prefill_module === 'inventory' || ($_POST['related_module'] ?? '') === 'inventory') ? 'selected' : '' ?>>Inventory</option>
                            <option value="purchase" <?= ($prefill_module === 'purchase' || ($_POST['related_module'] ?? '') === 'purchase') ? 'selected' : '' ?>>Purchase</option>
                            <option value="operations" <?= ($prefill_module === 'operations' || ($_POST['related_module'] ?? '') === 'operations') ? 'selected' : '' ?>>Operations</option>
                            <option value="service" <?= ($prefill_module === 'service' || ($_POST['related_module'] ?? '') === 'service') ? 'selected' : '' ?>>Service</option>
                            <option value="finance" <?= ($prefill_module === 'finance' || ($_POST['related_module'] ?? '') === 'finance') ? 'selected' : '' ?>>Finance</option>
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
                            <option value="<?= $cust['customer_id'] ?>"
                                    <?= ($prefill_customer == $cust['customer_id'] || ($_POST['customer_id'] ?? '') == $cust['customer_id']) ? 'selected' : '' ?>>
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
                            <option value="<?= $proj['id'] ?>"
                                    <?= ($prefill_project == $proj['id'] || ($_POST['project_id'] ?? '') == $proj['id']) ? 'selected' : '' ?>>
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
                    <textarea name="remarks" placeholder="Any additional notes or remarks..."><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="btn-row">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-success">Create Task</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
