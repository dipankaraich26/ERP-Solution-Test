<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    die("Invalid project ID");
}

// Fetch project details
$project_stmt = $pdo->prepare("
    SELECT p.*, c.customer_name, c.company_name
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.customer_id
    WHERE p.id = ?
");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();

if (!$project) {
    die("Project not found");
}

// Fetch milestones
$milestones_stmt = $pdo->prepare("
    SELECT * FROM project_milestones
    WHERE project_id = ?
    ORDER BY target_date
");
$milestones_stmt->execute([$project_id]);
$milestones = $milestones_stmt->fetchAll();

// Fetch activities
$activities_stmt = $pdo->prepare("
    SELECT * FROM project_activities
    WHERE project_id = ?
    ORDER BY created_at DESC
");
$activities_stmt->execute([$project_id]);
$activities = $activities_stmt->fetchAll();

// Fetch project tasks
$tasks_stmt = $pdo->prepare("
    SELECT * FROM project_tasks
    WHERE project_id = ?
    ORDER BY task_start_date ASC, created_at DESC
");
$tasks_stmt->execute([$project_id]);
$tasks = $tasks_stmt->fetchAll();

// Fetch milestone documents (grouped by milestone_id)
$milestone_documents = [];
$milestoneDocsEnabled = true;
try {
    $docs_stmt = $pdo->prepare("
        SELECT * FROM milestone_documents
        WHERE project_id = ?
        ORDER BY created_at DESC
    ");
    $docs_stmt->execute([$project_id]);
    $all_milestone_docs = $docs_stmt->fetchAll();

    // Group by milestone_id
    foreach ($all_milestone_docs as $doc) {
        $milestone_documents[$doc['milestone_id']][] = $doc;
    }
} catch (PDOException $e) {
    $milestoneDocsEnabled = false;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Details</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            margin: 10px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #34495e;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-planning {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-in-progress {
            background: #fff3e0;
            color: #f57c00;
        }
        .status-on-hold {
            background: #fce4ec;
            color: #c2185b;
        }
        .status-completed {
            background: #e8f5e9;
            color: #388e3c;
        }
        .status-cancelled {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .milestone-card {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .activity-card {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #ecf0f1;
            border-radius: 12px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60 0%, #2ecc71 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        body.dark .detail-section {
            background: #2c3e50;
        }
        body.dark .milestone-card,
        body.dark .activity-card {
            background: #34495e;
            border-color: #555;
        }
        .task-section {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #9b59b6;
        }
        .task-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            border-radius: 4px;
            overflow: hidden;
        }
        .task-table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .task-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .task-table tr:hover {
            background: #f5f5f5;
        }
        body.dark .task-table {
            background: #34495e;
        }
        body.dark .task-table th {
            background: #2c3e50;
        }
        body.dark .task-table td {
            border-bottom-color: #555;
        }
        body.dark .task-table tr:hover {
            background: #2c3e50;
        }
        .task-form-section {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .task-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 120px;
            gap: 10px;
            margin-bottom: 10px;
        }
        .task-form-row input,
        .task-form-row select,
        .task-form-row textarea {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .task-actions {
            display: flex;
            gap: 5px;
        }
        .task-actions a,
        .task-actions button {
            padding: 6px 10px;
            font-size: 0.85em;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
        }
        .task-actions .btn-edit {
            background: #3498db;
            color: white;
        }
        .task-actions .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .task-actions .btn-status {
            background: #27ae60;
            color: white;
        }
        .status-pending {
            background: #e8f4f8;
            color: #2980b9;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .status-in-progress-task {
            background: #fff3e0;
            color: #f57c00;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .status-completed-task {
            background: #e8f5e9;
            color: #388e3c;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .status-on-hold-task {
            background: #fce4ec;
            color: #c2185b;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .status-cancelled-task {
            background: #f3e5f5;
            color: #7b1fa2;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        body.dark .task-section {
            background: #2c3e50;
        }
        body.dark .task-form-section {
            background: #34495e;
            border-color: #555;
        }
        body.dark .task-form-row input,
        body.dark .task-form-row select,
        body.dark .task-form-row textarea {
            background: #2c3e50;
            color: #ecf0f1;
            border-color: #555;
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

// Toggle milestone document upload form
function toggleUploadForm(milestoneId) {
    const form = document.getElementById('uploadForm_' + milestoneId);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}
</script>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1><?= htmlspecialchars($project['project_name']) ?></h1>

    <a href="index.php" class="btn btn-secondary">Back to Projects</a>
    <a href="edit.php?id=<?= $project['id'] ?>" class="btn btn-primary">Edit</a>
    <br><br>

    <!-- Project Overview -->
    <div class="detail-section">
        <h2>Project Overview</h2>

        <div class="detail-row">
            <div class="detail-label">Project Number:</div>
            <div><?= htmlspecialchars($project['project_no']) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Status:</div>
            <div>
                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $project['status'])) ?>">
                    <?= htmlspecialchars($project['status']) ?>
                </span>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Priority:</div>
            <div><strong><?= htmlspecialchars($project['priority']) ?></strong></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Progress:</div>
            <div>
                <div class="progress-bar" style="margin-bottom: 10px;">
                    <div class="progress-fill" style="width: <?= (int)($project['progress_percentage'] ?? 0) ?>%;">
                        <?= (int)($project['progress_percentage'] ?? 0) ?>%
                    </div>
                </div>
                <form method="post" action="update_progress.php" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="project_id" value="<?= $project_id ?>">
                    <input type="range" name="progress_percentage" min="0" max="100" step="5"
                           value="<?= (int)($project['progress_percentage'] ?? 0) ?>"
                           oninput="this.nextElementSibling.value = this.value + '%'"
                           style="width: 150px;">
                    <output style="width: 40px;"><?= (int)($project['progress_percentage'] ?? 0) ?>%</output>
                    <button type="submit" class="btn btn-primary" style="padding: 5px 12px; font-size: 0.85em;">Update</button>
                </form>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Project Manager:</div>
            <div><?= htmlspecialchars($project['project_manager'] ?: 'N/A') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Project Engineer:</div>
            <div><?= htmlspecialchars($project['project_engineer'] ?: 'N/A') ?></div>
        </div>

        <?php if (!empty($project['customer_id'])): ?>
        <div class="detail-row">
            <div class="detail-label">Customer:</div>
            <div>
                <?= htmlspecialchars($project['company_name']) ?> (<?= htmlspecialchars($project['customer_name']) ?>)
            </div>
        </div>
        <?php endif; ?>

        <div class="detail-row">
            <div class="detail-label">Start Date:</div>
            <div><?= $project['start_date'] ? date('d M Y', strtotime($project['start_date'])) : 'N/A' ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">End Date:</div>
            <div><?= $project['end_date'] ? date('d M Y', strtotime($project['end_date'])) : 'N/A' ?></div>
        </div>

        <?php if (!empty($project['budget'])): ?>
        <div class="detail-row">
            <div class="detail-label">Budget:</div>
            <div>₹<?= number_format($project['budget'], 2) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($project['description'])): ?>
        <div class="detail-row">
            <div class="detail-label">Description:</div>
            <div><?= nl2br(htmlspecialchars($project['description'])) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Milestones -->
    <div class="detail-section">
        <h2>Project Milestones (<?= count($milestones) ?>)</h2>

        <!-- Add New Milestone Form -->
        <div class="task-form-section" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0;">Add New Milestone</h3>
            <form method="post" action="manage_milestones.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">

                <div class="task-form-row" style="grid-template-columns: 1fr 150px 150px 120px;">
                    <input type="text" name="milestone_name" placeholder="Milestone Name *" required>
                    <input type="date" name="target_date" required title="Target Date">
                    <select name="status">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 12px;">Add</button>
                </div>
                <div class="task-form-row" style="grid-template-columns: 1fr; margin-top: 10px;">
                    <input type="text" name="description" placeholder="Description (optional)">
                </div>
            </form>
        </div>

        <?php if (count($milestones) > 0): ?>
            <?php foreach ($milestones as $m):
                $docs = $milestone_documents[$m['id']] ?? [];
            ?>
                <div class="milestone-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($m['milestone_name']) ?></h4>
                            <p style="margin: 5px 0; color: #666;">
                                Target: <?= date('d M Y', strtotime($m['target_date'])) ?>
                                <?php if ($m['completion_date']): ?>
                                    | Completed: <?= date('d M Y', strtotime($m['completion_date'])) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($m['description'])): ?>
                                <p style="margin: 5px 0; color: #888; font-size: 0.9em;"><?= htmlspecialchars($m['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <?php
                            $statusColor = '#e8f4f8'; $statusText = '#2980b9';
                            if ($m['status'] === 'Completed') { $statusColor = '#e8f5e9'; $statusText = '#388e3c'; }
                            elseif ($m['status'] === 'In Progress') { $statusColor = '#fff3e0'; $statusText = '#f57c00'; }
                            elseif ($m['status'] === 'Missed') { $statusColor = '#fce4ec'; $statusText = '#c2185b'; }
                            ?>
                            <span class="status-badge" style="background: <?= $statusColor ?>; color: <?= $statusText ?>;">
                                <?= htmlspecialchars($m['status']) ?>
                            </span>
                            <?php if ($m['status'] !== 'Completed'): ?>
                                <a href="manage_milestones.php?action=complete&id=<?= $m['id'] ?>&project_id=<?= $project_id ?>"
                                   style="padding: 6px 10px; font-size: 0.85em; background: #27ae60; color: white; border-radius: 3px; text-decoration: none;"
                                   onclick="return confirm('Mark this milestone as completed?')">Complete</a>
                            <?php endif; ?>
                            <a href="manage_milestones.php?action=delete&id=<?= $m['id'] ?>&project_id=<?= $project_id ?>"
                               style="padding: 6px 10px; font-size: 0.85em; background: #e74c3c; color: white; border-radius: 3px; text-decoration: none;"
                               onclick="return confirm('Delete this milestone?')">Delete</a>
                        </div>
                    </div>

                    <!-- Milestone Documents Section -->
                    <?php if ($milestoneDocsEnabled): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 0.9em; color: #555;">Attachments (<?= count($docs) ?>)</strong>
                            <button type="button" onclick="toggleUploadForm(<?= $m['id'] ?>)"
                                    style="padding: 4px 10px; font-size: 0.8em; background: #667eea; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                + Add Document
                            </button>
                        </div>

                        <!-- Upload Form (Hidden by default) -->
                        <div id="uploadForm_<?= $m['id'] ?>" style="display: none; background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px;">
                            <form method="post" action="milestone_documents.php" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload">
                                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">

                                <div style="display: grid; grid-template-columns: 1fr 150px 1fr auto; gap: 10px; align-items: end;">
                                    <div>
                                        <label style="font-size: 0.8em; color: #666; display: block; margin-bottom: 3px;">File *</label>
                                        <input type="file" name="document" required
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.csv,.zip,.dwg,.dxf"
                                               style="font-size: 0.85em; padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                    </div>
                                    <div>
                                        <label style="font-size: 0.8em; color: #666; display: block; margin-bottom: 3px;">Type</label>
                                        <select name="document_type" style="font-size: 0.85em; padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                            <option value="">Select type</option>
                                            <option value="Drawing">Drawing</option>
                                            <option value="Specification">Specification</option>
                                            <option value="Report">Report</option>
                                            <option value="Test Result">Test Result</option>
                                            <option value="Meeting Notes">Meeting Notes</option>
                                            <option value="Approval">Approval</option>
                                            <option value="Certificate">Certificate</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 0.8em; color: #666; display: block; margin-bottom: 3px;">Remarks</label>
                                        <input type="text" name="remarks" placeholder="Optional notes"
                                               style="font-size: 0.85em; padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                    </div>
                                    <div>
                                        <button type="submit" style="padding: 6px 15px; font-size: 0.85em; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                            Upload
                                        </button>
                                    </div>
                                </div>
                                <small style="color: #888; font-size: 0.75em; margin-top: 5px; display: block;">
                                    Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, Images, TXT, CSV, ZIP, DWG, DXF (Max 10MB)
                                </small>
                            </form>
                        </div>

                        <!-- Attached Documents List -->
                        <?php if (count($docs) > 0): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($docs as $doc): ?>
                            <div style="display: flex; align-items: center; gap: 8px; background: #e3e8ff; padding: 6px 12px; border-radius: 20px; font-size: 0.85em;">
                                <a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                                   style="color: #667eea; text-decoration: none; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                   title="<?= htmlspecialchars($doc['original_name']) ?><?= $doc['document_type'] ? ' (' . htmlspecialchars($doc['document_type']) . ')' : '' ?>">
                                    <?= htmlspecialchars(strlen($doc['original_name']) > 20 ? substr($doc['original_name'], 0, 17) . '...' : $doc['original_name']) ?>
                                </a>
                                <span style="color: #888; font-size: 0.8em;"><?= number_format($doc['file_size'] / 1024, 0) ?>KB</span>
                                <a href="milestone_documents.php?action=delete&id=<?= $doc['id'] ?>&project_id=<?= $project_id ?>"
                                   onclick="return confirm('Delete this document?')"
                                   style="color: #e74c3c; text-decoration: none; font-weight: bold;" title="Delete">&times;</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="color: #999; font-size: 0.85em; margin: 0;">No documents attached yet.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #666;">No milestones yet. Add your first milestone above.</p>
        <?php endif; ?>
    </div>

    <!-- Activities -->
    <div class="detail-section">
        <h2>Project Activities (<?= count($activities) ?>)</h2>

        <?php if (count($activities) > 0): ?>
            <?php foreach ($activities as $a): ?>
                <div class="activity-card">
                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <h4 style="margin: 0 0 5px 0;">
                                <?= htmlspecialchars($a['activity_type']) ?> - <?= htmlspecialchars($a['activity_description']) ?>
                            </h4>
                            <p style="margin: 5px 0; font-size: 0.9em; color: #666;">
                                Assigned to: <?= htmlspecialchars($a['assigned_to'] ?: 'Unassigned') ?> |
                                Due: <?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : 'N/A' ?>
                            </p>
                        </div>
                        <span class="status-badge" style="background: #fff3e0; color: #f57c00;">
                            <?= htmlspecialchars($a['status']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No activities yet.</p>
        <?php endif; ?>
    </div>

    <!-- Project Tasks -->
    <div class="task-section">
        <h2>Project Tasks (<?= count($tasks) ?>)</h2>

        <!-- Add New Task Form -->
        <div class="task-form-section">
            <h3 style="margin: 0 0 15px 0;">Add New Task</h3>
            <form method="post" action="manage_tasks.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">

                <div class="task-form-row">
                    <input type="text" name="task_name" placeholder="Task Name *" required>
                    <input type="date" name="task_start_date" placeholder="Start Date">
                    <input type="date" name="task_end_date" placeholder="End Date">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 12px;">Add Task</button>
                </div>

                <div class="task-form-row" style="grid-template-columns: 1fr 1fr;">
                    <select name="status">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <input type="text" name="assigned_to" placeholder="Assigned To">
                </div>

                <div class="task-form-row" style="grid-template-columns: 1fr;">
                    <textarea name="remark" placeholder="Remark/Notes" rows="2" style="grid-column: 1 / -1;"></textarea>
                </div>
            </form>
        </div>

        <!-- Tasks Table -->
        <?php if (count($tasks) > 0): ?>
            <table class="task-table">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Remark</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= htmlspecialchars($task['task_name']) ?></td>
                            <td><?= $task['task_start_date'] ? date('d M Y', strtotime($task['task_start_date'])) : '-' ?></td>
                            <td><?= $task['task_end_date'] ? date('d M Y', strtotime($task['task_end_date'])) : '-' ?></td>
                            <td>
                                <span class="status-<?= strtolower(str_replace(' ', '-', $task['status'])) . '-task' ?>">
                                    <?= htmlspecialchars($task['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($task['assigned_to'] ?: '-') ?></td>
                            <td><?= htmlspecialchars(substr($task['remark'], 0, 50)) ?><?= strlen($task['remark']) > 50 ? '...' : '' ?></td>
                            <td>
                                <div class="task-actions">
                                    <a href="edit_task.php?id=<?= $task['id'] ?>&project_id=<?= $project_id ?>" class="btn-edit">Edit</a>
                                    <a href="manage_tasks.php?action=delete&id=<?= $task['id'] ?>&project_id=<?= $project_id ?>" class="btn-delete" onclick="return confirm('Delete this task?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #666;">No tasks yet. Add your first task above.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
