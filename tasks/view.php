<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid task ID");
}

// Get task with related data
$stmt = $pdo->prepare("
    SELECT t.*,
           tc.category_name, tc.color_code,
           CONCAT(e.first_name, ' ', e.last_name) as assigned_name, e.emp_id as assigned_emp_id,
           CONCAT(e2.first_name, ' ', e2.last_name) as assigned_by_name,
           CONCAT(e3.first_name, ' ', e3.last_name) as created_by_name,
           c.customer_name, c.company_name,
           p.project_no, p.project_name
    FROM tasks t
    LEFT JOIN task_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    LEFT JOIN employees e2 ON t.assigned_by = e2.id
    LEFT JOIN employees e3 ON t.created_by = e3.id
    LEFT JOIN customers c ON t.customer_id = c.customer_id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Task not found");
}

// Get comments/activity
$commentsStmt = $pdo->prepare("
    SELECT tc.*, CONCAT(e.first_name, ' ', e.last_name) as commenter_name
    FROM task_comments tc
    LEFT JOIN employees e ON tc.commented_by = e.id
    WHERE tc.task_id = ?
    ORDER BY tc.created_at DESC
");
$commentsStmt->execute([$id]);
$comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get checklist items
$checklistStmt = $pdo->prepare("
    SELECT * FROM task_checklist WHERE task_id = ? ORDER BY sort_order, id
");
$checklistStmt->execute([$id]);
$checklist = $checklistStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_comment' && !empty(trim($_POST['comment']))) {
            $commentStmt = $pdo->prepare("
                INSERT INTO task_comments (task_id, comment, commented_by, comment_type)
                VALUES (?, ?, ?, 'comment')
            ");
            $commentStmt->execute([
                $id,
                trim($_POST['comment']),
                $_SESSION['employee_id'] ?? null
            ]);
            setModal("Success", "Comment added!");
            header("Location: view.php?id=$id");
            exit;
        }

        if ($_POST['action'] === 'add_checklist' && !empty(trim($_POST['item_text']))) {
            $checkStmt = $pdo->prepare("
                INSERT INTO task_checklist (task_id, item_text, sort_order)
                VALUES (?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_checklist t WHERE t.task_id = ?))
            ");
            $checkStmt->execute([$id, trim($_POST['item_text']), $id]);
            header("Location: view.php?id=$id");
            exit;
        }

        if ($_POST['action'] === 'toggle_checklist' && isset($_POST['item_id'])) {
            $itemId = (int)$_POST['item_id'];
            $pdo->prepare("
                UPDATE task_checklist
                SET is_completed = NOT is_completed,
                    completed_at = IF(is_completed = 0, NOW(), NULL),
                    completed_by = IF(is_completed = 0, ?, NULL)
                WHERE id = ? AND task_id = ?
            ")->execute([$_SESSION['employee_id'] ?? null, $itemId, $id]);

            // Update task progress based on checklist
            $totalItems = $pdo->query("SELECT COUNT(*) FROM task_checklist WHERE task_id = $id")->fetchColumn();
            $completedItems = $pdo->query("SELECT COUNT(*) FROM task_checklist WHERE task_id = $id AND is_completed = 1")->fetchColumn();
            if ($totalItems > 0) {
                $progress = round(($completedItems / $totalItems) * 100);
                $pdo->prepare("UPDATE tasks SET progress_percent = ? WHERE id = ?")->execute([$progress, $id]);
            }

            header("Location: view.php?id=$id");
            exit;
        }

        if ($_POST['action'] === 'quick_status' && isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            $validStatuses = ['Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
            if (in_array($newStatus, $validStatuses)) {
                $updateData = ['status' => $newStatus];
                if ($newStatus === 'Completed') {
                    $updateData['completed_date'] = date('Y-m-d');
                    $updateData['progress_percent'] = 100;
                }
                $pdo->prepare("UPDATE tasks SET status = ?, completed_date = ?, progress_percent = ? WHERE id = ?")
                    ->execute([$newStatus, $updateData['completed_date'] ?? null, $updateData['progress_percent'] ?? $task['progress_percent'], $id]);

                // Log status change
                $pdo->prepare("INSERT INTO task_comments (task_id, comment, commented_by, comment_type) VALUES (?, ?, ?, 'status_change')")
                    ->execute([$id, "Status changed from '{$task['status']}' to '$newStatus'", $_SESSION['employee_id'] ?? null]);

                setModal("Success", "Status updated to $newStatus");
                header("Location: view.php?id=$id");
                exit;
            }
        }
    }
}

include "../includes/sidebar.php";
showModal();

// Helper for status class
$statusClass = strtolower(str_replace(' ', '-', $task['status']));
$isOverdue = $task['due_date'] && $task['due_date'] < date('Y-m-d') && !in_array($task['status'], ['Completed', 'Cancelled']);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($task['task_no']) ?> - Task Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .task-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .task-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .task-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        .task-id { color: #999; font-size: 0.9em; margin-top: 5px; }
        .task-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .detail-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 1.1em;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            flex: 0 0 140px;
            color: #7f8c8d;
            font-weight: 500;
        }
        .detail-value { flex: 1; color: #2c3e50; }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-not-started { background: #e0e0e0; color: #616161; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }
        .status-on-hold { background: #fff3e0; color: #ef6c00; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }

        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .priority-critical { background: #c62828; color: white; }
        .priority-high { background: #ef6c00; color: white; }
        .priority-medium { background: #fbc02d; color: #333; }
        .priority-low { background: #90a4ae; color: white; }

        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            color: white;
        }

        .overdue-alert {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-section {
            margin-top: 15px;
        }
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.3s;
        }
        .progress-text {
            text-align: right;
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .quick-action-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.2s;
        }
        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .quick-action-btn.success:hover { background: #27ae60; border-color: #27ae60; }
        .quick-action-btn.warning:hover { background: #f39c12; border-color: #f39c12; }

        .description-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .checklist {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .checklist-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
        }
        .checklist-item.completed .checklist-text {
            text-decoration: line-through;
            color: #999;
        }
        .checklist-text { flex: 1; }

        .add-checklist-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .add-checklist-form input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .activity-author { font-weight: 600; color: #2c3e50; }
        .activity-time { color: #999; font-size: 0.85em; }
        .activity-content { color: #555; line-height: 1.5; }
        .activity-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            margin-left: 8px;
        }
        .activity-type.status_change { background: #e3f2fd; color: #1565c0; }
        .activity-type.assignment { background: #f3e5f5; color: #7b1fa2; }
        .activity-type.progress_update { background: #e8f5e9; color: #2e7d32; }

        .comment-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            min-height: 80px;
            resize: vertical;
        }
        .comment-form button {
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <!-- Navigation -->
    <p style="margin-bottom: 20px;">
        <a href="index.php" class="btn btn-secondary">Back to Tasks</a>
        <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">Dashboard</a>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary" style="margin-left: 10px;">Edit Task</a>
    </p>

    <?php if ($isOverdue): ?>
    <div class="overdue-alert">
        <strong>⚠️ OVERDUE</strong> - This task was due on <?= date('d M Y', strtotime($task['due_date'])) ?>
        (<?= floor((time() - strtotime($task['due_date'])) / 86400) ?> days overdue)
    </div>
    <?php endif; ?>

    <!-- Task Header -->
    <div class="task-header">
        <div class="task-header-top">
            <div>
                <h1 class="task-title"><?= htmlspecialchars($task['task_name']) ?></h1>
                <div class="task-id"><?= htmlspecialchars($task['task_no']) ?></div>
                <div class="task-badges">
                    <span class="status-badge status-<?= $statusClass ?>"><?= $task['status'] ?></span>
                    <span class="priority-badge priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?></span>
                    <?php if ($task['category_name']): ?>
                    <span class="category-badge" style="background: <?= htmlspecialchars($task['color_code'] ?: '#95a5a6') ?>">
                        <?= htmlspecialchars($task['category_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
                <a href="delete.php?id=<?= $id ?>" class="btn btn-danger" style="margin-left: 5px;"
                   onclick="return confirm('Delete this task?')">Delete</a>
            </div>
        </div>

        <!-- Progress -->
        <div class="progress-section">
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width: <?= $task['progress_percent'] ?>%"></div>
            </div>
            <div class="progress-text"><?= $task['progress_percent'] ?>% Complete</div>
        </div>

        <!-- Quick Status Change -->
        <?php if ($task['status'] !== 'Completed' && $task['status'] !== 'Cancelled'): ?>
        <div class="quick-actions">
            <span style="color: #666; margin-right: 10px;">Quick status:</span>
            <?php if ($task['status'] !== 'In Progress'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="quick_status">
                <input type="hidden" name="new_status" value="In Progress">
                <button type="submit" class="quick-action-btn">Start Working</button>
            </form>
            <?php endif; ?>
            <?php if ($task['status'] !== 'On Hold'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="quick_status">
                <input type="hidden" name="new_status" value="On Hold">
                <button type="submit" class="quick-action-btn warning">Put On Hold</button>
            </form>
            <?php endif; ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="quick_status">
                <input type="hidden" name="new_status" value="Completed">
                <button type="submit" class="quick-action-btn success">Mark Complete</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="detail-grid">
        <!-- Task Details -->
        <div class="detail-section">
            <h3>Task Details</h3>
            <div class="detail-row">
                <div class="detail-label">Assigned To</div>
                <div class="detail-value">
                    <?php if ($task['assigned_name']): ?>
                        <?= htmlspecialchars($task['assigned_name']) ?>
                        <small style="color: #999;">(<?= htmlspecialchars($task['assigned_emp_id']) ?>)</small>
                    <?php else: ?>
                        <span style="color: #999;">Unassigned</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created By</div>
                <div class="detail-value"><?= htmlspecialchars($task['created_by_name'] ?: 'System') ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Start Date</div>
                <div class="detail-value"><?= $task['start_date'] ? date('d M Y', strtotime($task['start_date'])) : '-' ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Due Date</div>
                <div class="detail-value" style="<?= $isOverdue ? 'color: #c62828; font-weight: bold;' : '' ?>">
                    <?= $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : '-' ?>
                    <?= $isOverdue ? ' (Overdue)' : '' ?>
                </div>
            </div>
            <?php if ($task['completed_date']): ?>
            <div class="detail-row">
                <div class="detail-label">Completed</div>
                <div class="detail-value"><?= date('d M Y', strtotime($task['completed_date'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <div class="detail-label">Estimated Hours</div>
                <div class="detail-value"><?= $task['estimated_hours'] ? $task['estimated_hours'] . ' hrs' : '-' ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Actual Hours</div>
                <div class="detail-value"><?= $task['actual_hours'] ? $task['actual_hours'] . ' hrs' : '-' ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created</div>
                <div class="detail-value"><?= date('d M Y H:i', strtotime($task['created_at'])) ?></div>
            </div>
        </div>

        <!-- Related Information -->
        <div class="detail-section">
            <h3>Related Information</h3>
            <?php if ($task['related_module']): ?>
            <div class="detail-row">
                <div class="detail-label">Module</div>
                <div class="detail-value"><?= ucfirst(htmlspecialchars($task['related_module'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($task['related_reference']): ?>
            <div class="detail-row">
                <div class="detail-label">Reference</div>
                <div class="detail-value"><?= htmlspecialchars($task['related_reference']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($task['customer_name']): ?>
            <div class="detail-row">
                <div class="detail-label">Customer</div>
                <div class="detail-value">
                    <?= htmlspecialchars($task['customer_name']) ?>
                    <?php if ($task['company_name']): ?>
                        <br><small style="color: #999;"><?= htmlspecialchars($task['company_name']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($task['project_name']): ?>
            <div class="detail-row">
                <div class="detail-label">Project</div>
                <div class="detail-value">
                    <a href="/project_management/view.php?id=<?= $task['project_id'] ?>">
                        <?= htmlspecialchars($task['project_no']) ?> - <?= htmlspecialchars($task['project_name']) ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($task['remarks']): ?>
            <div class="detail-row">
                <div class="detail-label">Remarks</div>
                <div class="detail-value"><?= nl2br(htmlspecialchars($task['remarks'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!$task['related_module'] && !$task['customer_name'] && !$task['project_name'] && !$task['remarks']): ?>
            <p style="color: #999;">No related information.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Description -->
    <?php if ($task['task_description']): ?>
    <div class="detail-section" style="margin-bottom: 20px;">
        <h3>Description</h3>
        <div class="description-box"><?= nl2br(htmlspecialchars($task['task_description'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="detail-grid">
        <!-- Checklist -->
        <div class="detail-section">
            <h3>Checklist (<?= count(array_filter($checklist, fn($c) => $c['is_completed'])) ?>/<?= count($checklist) ?>)</h3>
            <?php if (empty($checklist)): ?>
                <p style="color: #999;">No checklist items.</p>
            <?php else: ?>
                <ul class="checklist">
                    <?php foreach ($checklist as $item): ?>
                    <li class="checklist-item <?= $item['is_completed'] ? 'completed' : '' ?>">
                        <form method="post" style="display: flex; align-items: center; flex: 1;">
                            <input type="hidden" name="action" value="toggle_checklist">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="checkbox" onchange="this.form.submit()" <?= $item['is_completed'] ? 'checked' : '' ?>>
                            <span class="checklist-text"><?= htmlspecialchars($item['item_text']) ?></span>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" class="add-checklist-form">
                <input type="hidden" name="action" value="add_checklist">
                <input type="text" name="item_text" placeholder="Add checklist item..." required>
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
        </div>

        <!-- Activity / Comments -->
        <div class="detail-section">
            <h3>Activity & Comments</h3>
            <div class="activity-list">
                <?php if (empty($comments)): ?>
                    <p style="color: #999;">No activity yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="activity-item">
                        <div class="activity-header">
                            <span class="activity-author">
                                <?= htmlspecialchars($comment['commenter_name'] ?: 'System') ?>
                                <?php if ($comment['comment_type'] !== 'comment'): ?>
                                <span class="activity-type <?= $comment['comment_type'] ?>">
                                    <?= str_replace('_', ' ', ucfirst($comment['comment_type'])) ?>
                                </span>
                                <?php endif; ?>
                            </span>
                            <span class="activity-time"><?= date('d M Y H:i', strtotime($comment['created_at'])) ?></span>
                        </div>
                        <div class="activity-content"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" class="comment-form">
                <input type="hidden" name="action" value="add_comment">
                <textarea name="comment" placeholder="Add a comment..." required></textarea>
                <button type="submit" class="btn btn-primary">Add Comment</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
