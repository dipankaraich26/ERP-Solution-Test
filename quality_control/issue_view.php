<?php
/**
 * View Quality Issue
 * Shows issue details, action items, timeline, and comments
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

// Get issue details
$stmt = $pdo->prepare("SELECT * FROM qc_quality_issues WHERE id = ?");
$stmt->execute([$issue_id]);
$issue = $stmt->fetch();

if (!$issue) {
    setModal("Error", "Issue not found");
    header("Location: issues.php");
    exit;
}

// Get action items
$actions_stmt = $pdo->prepare("SELECT * FROM qc_issue_actions WHERE issue_id = ? ORDER BY action_no ASC");
$actions_stmt->execute([$issue_id]);
$actions = $actions_stmt->fetchAll();

// Get comments/updates
$comments_stmt = $pdo->prepare("SELECT * FROM qc_issue_comments WHERE issue_id = ? ORDER BY created_at DESC");
$comments_stmt->execute([$issue_id]);
$comments = $comments_stmt->fetchAll();

// Get employees for assignment
$employees = [];
try {
    $employees = $pdo->query("SELECT id, emp_name, department FROM employees WHERE status = 'Active' ORDER BY emp_name")->fetchAll();
} catch (PDOException $e) {
    try {
        $employees = $pdo->query("SELECT id, full_name as emp_name, '' as department FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
    } catch (PDOException $e2) {}
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_status') {
        $new_status = trim($_POST['new_status'] ?? '');
        $comment = trim($_POST['status_comment'] ?? '');

        if ($new_status && $new_status !== $issue['status']) {
            $old_status = $issue['status'];

            // Update issue status
            $update = $pdo->prepare("UPDATE qc_quality_issues SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_status, $issue_id]);

            if ($new_status === 'Closed') {
                $pdo->prepare("UPDATE qc_quality_issues SET actual_closure_date = CURDATE(), closed_by = ? WHERE id = ?")->execute([$_SESSION['user_id'] ?? null, $issue_id]);
            }

            // Add comment
            $comment_text = "Status changed from '$old_status' to '$new_status'";
            if ($comment) $comment_text .= ": $comment";

            $ins = $pdo->prepare("INSERT INTO qc_issue_comments (issue_id, comment_type, comment, old_status, new_status, created_by, created_by_id) VALUES (?, 'Status Change', ?, ?, ?, ?, ?)");
            $ins->execute([$issue_id, $comment_text, $old_status, $new_status, $_SESSION['user_name'] ?? 'System', $_SESSION['user_id'] ?? null]);

            setModal("Success", "Status updated to '$new_status'");
            header("Location: issue_view.php?id=$issue_id");
            exit;
        }
    }

    if ($action === 'add_action') {
        $action_type = trim($_POST['action_type'] ?? 'Corrective');
        $action_title = trim($_POST['action_title'] ?? '');
        $action_desc = trim($_POST['action_description'] ?? '');
        $action_priority = trim($_POST['action_priority'] ?? 'Medium');
        $action_assigned_id = !empty($_POST['action_assigned_id']) ? (int)$_POST['action_assigned_id'] : null;
        $action_assigned = trim($_POST['action_assigned'] ?? '');
        $action_target_date = trim($_POST['action_target_date'] ?? '');
        $action_dept = trim($_POST['action_department'] ?? '');

        if (!empty($action_title) && !empty($action_target_date)) {
            // Get next action number
            $maxNo = $pdo->query("SELECT MAX(action_no) FROM qc_issue_actions WHERE issue_id = $issue_id")->fetchColumn();
            $action_no = ($maxNo ?: 0) + 1;

            $ins = $pdo->prepare("
                INSERT INTO qc_issue_actions (issue_id, action_no, action_type, title, description, priority, assigned_to, assigned_to_id, department, target_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $ins->execute([
                $issue_id, $action_no, $action_type, $action_title, $action_desc ?: null,
                $action_priority, $action_assigned ?: null, $action_assigned_id, $action_dept ?: null,
                $action_target_date, $_SESSION['user_id'] ?? null
            ]);

            // Update issue status to In Progress if it was Open
            if ($issue['status'] === 'Open') {
                $pdo->prepare("UPDATE qc_quality_issues SET status = 'Action Required' WHERE id = ?")->execute([$issue_id]);
            }

            setModal("Success", "Action item added successfully");
            header("Location: issue_view.php?id=$issue_id");
            exit;
        }
    }

    if ($action === 'update_action') {
        $action_id = (int)$_POST['action_id'];
        $action_status = trim($_POST['action_status'] ?? '');
        $action_remarks = trim($_POST['action_remarks'] ?? '');
        $completion_pct = (int)($_POST['completion_percentage'] ?? 0);

        if ($action_id && $action_status) {
            $upd = $pdo->prepare("UPDATE qc_issue_actions SET status = ?, remarks = ?, completion_percentage = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$action_status, $action_remarks, $completion_pct, $action_id]);

            if ($action_status === 'Completed') {
                $pdo->prepare("UPDATE qc_issue_actions SET actual_completion_date = CURDATE() WHERE id = ?")->execute([$action_id]);
            }

            setModal("Success", "Action item updated");
            header("Location: issue_view.php?id=$issue_id");
            exit;
        }
    }

    if ($action === 'add_comment') {
        $comment_text = trim($_POST['comment_text'] ?? '');
        if ($comment_text) {
            $ins = $pdo->prepare("INSERT INTO qc_issue_comments (issue_id, comment_type, comment, created_by, created_by_id) VALUES (?, 'Update', ?, ?, ?)");
            $ins->execute([$issue_id, $comment_text, $_SESSION['user_name'] ?? 'System', $_SESSION['user_id'] ?? null]);

            header("Location: issue_view.php?id=$issue_id");
            exit;
        }
    }
}

// Status workflow
$statuses = ['Open', 'Analysis', 'Action Required', 'In Progress', 'Verification', 'Closed', 'Cancelled'];
$action_types = ['Containment', 'Corrective', 'Preventive', 'Verification', 'Investigation', 'Other'];
$action_statuses = ['Pending', 'In Progress', 'Completed', 'Verified', 'Overdue', 'Cancelled'];
$priorities = ['Critical', 'High', 'Medium', 'Low'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($issue['issue_no']) ?> - Quality Issue</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .issue-header {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 10px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .badge-critical { background: #ffebee; color: #c62828; }
        .badge-high { background: #fff3e0; color: #e65100; }
        .badge-medium { background: #e3f2fd; color: #1565c0; }
        .badge-low { background: #e8f5e9; color: #2e7d32; }

        .badge-open { background: #e3f2fd; color: #1565c0; }
        .badge-analysis { background: #fff3e0; color: #e65100; }
        .badge-action { background: #fce4ec; color: #c2185b; }
        .badge-progress { background: #e8f5e9; color: #2e7d32; }
        .badge-verification { background: #f3e5f5; color: #7b1fa2; }
        .badge-closed { background: #eceff1; color: #546e7a; }

        .badge-field { background: #e8eaf6; color: #3949ab; }
        .badge-internal { background: #fff8e1; color: #ff8f00; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .section-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .section-card h3 {
            margin: 0 0 15px 0;
            color: #667eea;
            font-size: 1.1em;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-item label {
            display: block;
            font-size: 0.8em;
            color: #666;
            margin-bottom: 3px;
        }
        .detail-item .value {
            font-weight: 500;
            color: #333;
        }
        .detail-item.full-width { grid-column: 1 / -1; }

        .actions-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .action-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .action-item.containment { border-left-color: #e74c3c; }
        .action-item.corrective { border-left-color: #f39c12; }
        .action-item.preventive { border-left-color: #27ae60; }
        .action-item.verification { border-left-color: #9b59b6; }

        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .action-title { font-weight: 600; color: #333; }
        .action-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .action-status-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75em;
        }
        .action-status-pending { background: #fff3e0; color: #e65100; }
        .action-status-progress { background: #e3f2fd; color: #1565c0; }
        .action-status-completed { background: #e8f5e9; color: #2e7d32; }
        .action-status-verified { background: #f3e5f5; color: #7b1fa2; }
        .action-status-overdue { background: #ffebee; color: #c62828; }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar .fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s;
        }

        .timeline {
            position: relative;
            padding-left: 25px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -21px;
            top: 5px;
            width: 10px;
            height: 10px;
            background: #667eea;
            border-radius: 50%;
        }
        .timeline-item.status-change::before { background: #f39c12; }
        .timeline-date {
            font-size: 0.75em;
            color: #888;
        }
        .timeline-text {
            margin-top: 3px;
            font-size: 0.9em;
        }

        .status-update-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .add-action-form {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        .add-action-form.visible { display: block; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row.full { grid-template-columns: 1fr; }

        .quick-action-btn {
            display: inline-block;
            padding: 5px 10px;
            font-size: 0.8em;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #ddd;
            background: white;
            margin-right: 5px;
        }
        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        body.dark .section-card { background: #2c3e50; }
        body.dark .action-item { background: #34495e; }
        body.dark .add-action-form { background: #34495e; }
        body.dark .status-update-form { background: #34495e; }
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
            <div class="issue-header">
                <h1><?= htmlspecialchars($issue['issue_no']) ?></h1>
                <?php
                    $typeBadge = $issue['issue_type'] === 'Field Issue' ? 'badge-field' : 'badge-internal';
                    $statusMap = ['Open' => 'badge-open', 'Analysis' => 'badge-analysis', 'Action Required' => 'badge-action', 'In Progress' => 'badge-progress', 'Verification' => 'badge-verification', 'Closed' => 'badge-closed'];
                ?>
                <span class="badge <?= $typeBadge ?>"><?= htmlspecialchars($issue['issue_type']) ?></span>
                <span class="badge badge-<?= strtolower($issue['priority']) ?>"><?= htmlspecialchars($issue['priority']) ?></span>
                <span class="badge <?= $statusMap[$issue['status']] ?? 'badge-open' ?>"><?= htmlspecialchars($issue['status']) ?></span>
            </div>
            <p style="color: #666; margin: 5px 0 0; font-size: 1.1em;"><?= htmlspecialchars($issue['title']) ?></p>
        </div>
        <div>
            <a href="issue_edit.php?id=<?= $issue_id ?>" class="btn btn-primary">Edit Issue</a>
            <a href="issues.php" class="btn btn-secondary" style="margin-left: 10px;">Back to Issues</a>
        </div>
    </div>

    <div class="content-grid">
        <!-- Main Content -->
        <div>
            <!-- Issue Details -->
            <div class="section-card">
                <h3>Issue Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Issue Date</label>
                        <div class="value"><?= date('d M Y', strtotime($issue['issue_date'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Detection Stage</label>
                        <div class="value"><?= htmlspecialchars($issue['detection_stage']) ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Category</label>
                        <div class="value"><?= htmlspecialchars($issue['category']) ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Severity</label>
                        <div class="value"><?= htmlspecialchars($issue['severity']) ?></div>
                    </div>
                    <?php if ($issue['part_no']): ?>
                    <div class="detail-item">
                        <label>Part Number</label>
                        <div class="value"><?= htmlspecialchars($issue['part_no']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['lot_no']): ?>
                    <div class="detail-item">
                        <label>Lot Number</label>
                        <div class="value"><?= htmlspecialchars($issue['lot_no']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['customer_name']): ?>
                    <div class="detail-item">
                        <label>Customer</label>
                        <div class="value"><?= htmlspecialchars($issue['customer_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($issue['supplier_name'])): ?>
                    <div class="detail-item">
                        <label>Supplier</label>
                        <div class="value"><?= htmlspecialchars($issue['supplier_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['location']): ?>
                    <div class="detail-item">
                        <label>Location</label>
                        <div class="value"><?= htmlspecialchars($issue['location']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item full-width">
                        <label>Description</label>
                        <div class="value"><?= nl2br(htmlspecialchars($issue['description'])) ?></div>
                    </div>
                    <?php if ($issue['qty_affected'] > 0): ?>
                    <div class="detail-item">
                        <label>Quantity Affected</label>
                        <div class="value"><?= number_format($issue['qty_affected']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['cost_impact'] > 0): ?>
                    <div class="detail-item">
                        <label>Cost Impact</label>
                        <div class="value">Rs. <?= number_format($issue['cost_impact'], 2) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Root Cause & Containment -->
            <?php if ($issue['root_cause'] || $issue['containment_action'] || $issue['why_analysis']): ?>
            <div class="section-card">
                <h3>Root Cause & Containment</h3>
                <div class="detail-grid">
                    <?php if ($issue['root_cause_category']): ?>
                    <div class="detail-item">
                        <label>Root Cause Category</label>
                        <div class="value"><?= htmlspecialchars($issue['root_cause_category']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['root_cause']): ?>
                    <div class="detail-item full-width">
                        <label>Root Cause</label>
                        <div class="value"><?= nl2br(htmlspecialchars($issue['root_cause'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['why_analysis']): ?>
                    <div class="detail-item full-width">
                        <label>5-Why Analysis</label>
                        <div class="value"><?= nl2br(htmlspecialchars($issue['why_analysis'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($issue['containment_action']): ?>
                    <div class="detail-item full-width">
                        <label>Containment Action</label>
                        <div class="value"><?= nl2br(htmlspecialchars($issue['containment_action'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Items -->
            <div class="section-card">
                <h3>
                    Action Items (<?= count($actions) ?>)
                    <button type="button" onclick="toggleAddActionForm()" class="btn btn-primary" style="float: right; padding: 5px 15px; font-size: 0.85em;">+ Add Action</button>
                </h3>

                <!-- Add Action Form -->
                <div class="add-action-form" id="addActionForm">
                    <form method="post">
                        <input type="hidden" name="form_action" value="add_action">
                        <h4 style="margin: 0 0 15px 0;">New Action Item</h4>

                        <div class="form-row">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Action Type *</label>
                                <select name="action_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                    <?php foreach ($action_types as $at): ?>
                                        <option value="<?= $at ?>"><?= $at ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Priority</label>
                                <select name="action_priority" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                    <?php foreach ($priorities as $p): ?>
                                        <option value="<?= $p ?>" <?= $p === 'Medium' ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Target Date *</label>
                                <input type="date" name="action_target_date" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                            </div>
                        </div>

                        <div class="form-row full">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Action Title *</label>
                                <input type="text" name="action_title" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Brief title for the action item">
                            </div>
                        </div>

                        <div class="form-row full">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Description</label>
                                <textarea name="action_description" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Detailed description of what needs to be done..."></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Assign To</label>
                                <select name="action_assigned_id" onchange="updateActionAssignedName(this)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['emp_name']) ?>" data-dept="<?= htmlspecialchars($emp['department'] ?? '') ?>">
                                            <?= htmlspecialchars($emp['emp_name']) ?>
                                            <?php if (!empty($emp['department'])): ?>(<?= $emp['department'] ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="action_assigned" id="action_assigned">
                                <input type="hidden" name="action_department" id="action_department">
                            </div>
                        </div>

                        <div style="margin-top: 15px;">
                            <button type="submit" class="btn btn-primary">Add Action Item</button>
                            <button type="button" onclick="toggleAddActionForm()" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Actions List -->
                <?php if (count($actions) > 0): ?>
                <ul class="actions-list">
                    <?php foreach ($actions as $act):
                        $isOverdue = $act['target_date'] < date('Y-m-d') && !in_array($act['status'], ['Completed', 'Verified', 'Cancelled']);
                        $statusClass = 'action-status-' . strtolower(str_replace(' ', '', $act['status']));
                        if ($isOverdue) $statusClass = 'action-status-overdue';
                    ?>
                    <li class="action-item <?= strtolower($act['action_type']) ?>">
                        <div class="action-header">
                            <div>
                                <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em; margin-right: 8px;">
                                    #<?= $act['action_no'] ?>
                                </span>
                                <span class="action-title"><?= htmlspecialchars($act['title']) ?></span>
                            </div>
                            <span class="action-status-badge <?= $statusClass ?>">
                                <?= $isOverdue ? 'OVERDUE' : htmlspecialchars($act['status']) ?>
                            </span>
                        </div>

                        <?php if ($act['description']): ?>
                            <p style="margin: 10px 0; color: #555; font-size: 0.9em;"><?= nl2br(htmlspecialchars($act['description'])) ?></p>
                        <?php endif; ?>

                        <div class="action-meta">
                            <span><strong>Type:</strong> <?= htmlspecialchars($act['action_type']) ?></span>
                            <span><strong>Assigned:</strong> <?= htmlspecialchars($act['assigned_to'] ?: 'Unassigned') ?></span>
                            <span><strong>Target:</strong> <?= date('d M Y', strtotime($act['target_date'])) ?></span>
                            <?php if ($act['actual_completion_date']): ?>
                                <span><strong>Completed:</strong> <?= date('d M Y', strtotime($act['actual_completion_date'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="progress-bar">
                            <div class="fill" style="width: <?= $act['completion_percentage'] ?>%;"></div>
                        </div>
                        <small style="color: #888;"><?= $act['completion_percentage'] ?>% complete</small>

                        <!-- Quick Update -->
                        <?php if (!in_array($act['status'], ['Completed', 'Verified', 'Cancelled'])): ?>
                        <form method="post" style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                            <input type="hidden" name="form_action" value="update_action">
                            <input type="hidden" name="action_id" value="<?= $act['id'] ?>">
                            <select name="action_status" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <?php foreach ($action_statuses as $as): ?>
                                    <option value="<?= $as ?>" <?= $act['status'] === $as ? 'selected' : '' ?>><?= $as ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="completion_percentage" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <?php for ($i = 0; $i <= 100; $i += 10): ?>
                                    <option value="<?= $i ?>" <?= $act['completion_percentage'] == $i ? 'selected' : '' ?>><?= $i ?>%</option>
                                <?php endfor; ?>
                            </select>
                            <input type="text" name="action_remarks" placeholder="Remarks" style="flex: 1; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.85em;">Update</button>
                        </form>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No action items yet. Click "+ Add Action" to create one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Assignment & Status -->
            <div class="section-card">
                <h3>Assignment & Timeline</h3>
                <div class="detail-item">
                    <label>Assigned To</label>
                    <div class="value"><?= htmlspecialchars($issue['assigned_to'] ?: 'Unassigned') ?></div>
                </div>
                <?php if ($issue['department']): ?>
                <div class="detail-item">
                    <label>Department</label>
                    <div class="value"><?= htmlspecialchars($issue['department']) ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <label>Reported By</label>
                    <div class="value"><?= htmlspecialchars($issue['reported_by'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <label>Target Closure</label>
                    <div class="value <?= ($issue['target_closure_date'] && $issue['target_closure_date'] < date('Y-m-d') && $issue['status'] !== 'Closed') ? 'overdue-text' : '' ?>">
                        <?= $issue['target_closure_date'] ? date('d M Y', strtotime($issue['target_closure_date'])) : '-' ?>
                    </div>
                </div>
                <?php if ($issue['actual_closure_date']): ?>
                <div class="detail-item">
                    <label>Actual Closure</label>
                    <div class="value"><?= date('d M Y', strtotime($issue['actual_closure_date'])) ?></div>
                </div>
                <?php endif; ?>

                <!-- Status Update -->
                <?php if ($issue['status'] !== 'Closed' && $issue['status'] !== 'Cancelled'): ?>
                <div class="status-update-form">
                    <form method="post">
                        <input type="hidden" name="form_action" value="update_status">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Update Status</label>
                        <select name="new_status" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $issue['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="status_comment" placeholder="Comment (optional)" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Update Status</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Activity Timeline -->
            <div class="section-card">
                <h3>Activity Timeline</h3>

                <!-- Add Comment -->
                <form method="post" style="margin-bottom: 15px;">
                    <input type="hidden" name="form_action" value="add_comment">
                    <textarea name="comment_text" rows="2" placeholder="Add a comment or update..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 8px;"></textarea>
                    <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 8px;">Add Comment</button>
                </form>

                <div class="timeline">
                    <?php foreach ($comments as $c): ?>
                    <div class="timeline-item <?= $c['comment_type'] === 'Status Change' ? 'status-change' : '' ?>">
                        <div class="timeline-date"><?= date('d M Y, H:i', strtotime($c['created_at'])) ?></div>
                        <div class="timeline-text">
                            <strong><?= htmlspecialchars($c['created_by']) ?>:</strong>
                            <?= nl2br(htmlspecialchars($c['comment'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="timeline-item">
                        <div class="timeline-date"><?= date('d M Y, H:i', strtotime($issue['created_at'])) ?></div>
                        <div class="timeline-text">Issue created</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAddActionForm() {
    const form = document.getElementById('addActionForm');
    form.classList.toggle('visible');
}

function updateActionAssignedName(select) {
    const selected = select.options[select.selectedIndex];
    document.getElementById('action_assigned').value = selected.dataset.name || '';
    document.getElementById('action_department').value = selected.dataset.dept || '';
}
</script>

</body>
</html>
