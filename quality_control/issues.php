<?php
/**
 * Quality Issues - Main Listing Page
 * Shows all field and internal quality issues with filters
 */
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

// Check if tables exist
$tableExists = true;
try {
    $pdo->query("SELECT 1 FROM qc_quality_issues LIMIT 1");
} catch (PDOException $e) {
    $tableExists = false;
}

if (!$tableExists) {
    header("Location: setup_quality_issues.php");
    exit;
}

// Filters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$filter_assigned = isset($_GET['assigned']) ? (int)$_GET['assigned'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = ["1=1"];
$params = [];

if ($filter_type) {
    $where[] = "issue_type = ?";
    $params[] = $filter_type;
}
if ($filter_status) {
    $where[] = "status = ?";
    $params[] = $filter_status;
}
if ($filter_priority) {
    $where[] = "priority = ?";
    $params[] = $filter_priority;
}
if ($filter_assigned) {
    $where[] = "assigned_to_id = ?";
    $params[] = $filter_assigned;
}
if ($filter_date_from) {
    $where[] = "issue_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where[] = "issue_date <= ?";
    $params[] = $filter_date_to;
}
if ($search) {
    $where[] = "(issue_no LIKE ? OR title LIKE ? OR part_no LIKE ? OR customer_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(" AND ", $where);

// Get issues
$stmt = $pdo->prepare("
    SELECT i.*,
        (SELECT COUNT(*) FROM qc_issue_actions WHERE issue_id = i.id) as action_count,
        (SELECT COUNT(*) FROM qc_issue_actions WHERE issue_id = i.id AND status IN ('Pending', 'In Progress', 'Overdue')) as open_actions
    FROM qc_quality_issues i
    WHERE $whereClause
    ORDER BY
        CASE i.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 END,
        i.issue_date DESC
");
$stmt->execute($params);
$issues = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status NOT IN ('Closed', 'Cancelled') THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_count,
        SUM(CASE WHEN priority = 'Critical' AND status NOT IN ('Closed', 'Cancelled') THEN 1 ELSE 0 END) as critical_open,
        SUM(CASE WHEN issue_type = 'Field Issue' THEN 1 ELSE 0 END) as field_issues,
        SUM(CASE WHEN issue_type = 'Internal Issue' THEN 1 ELSE 0 END) as internal_issues,
        SUM(CASE WHEN target_closure_date < CURDATE() AND status NOT IN ('Closed', 'Cancelled') THEN 1 ELSE 0 END) as overdue
    FROM qc_quality_issues
")->fetch();

// Get employees for filter
$employees = [];
try {
    $employees = $pdo->query("SELECT id, emp_name FROM employees WHERE status = 'Active' ORDER BY emp_name")->fetchAll();
} catch (PDOException $e) {
    try {
        $employees = $pdo->query("SELECT id, full_name as emp_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
    } catch (PDOException $e2) {}
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quality Issues - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .number {
            font-size: 2em;
            font-weight: 700;
            color: #667eea;
        }
        .stat-card .label {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        .stat-card.critical .number { color: #e74c3c; }
        .stat-card.warning .number { color: #f39c12; }
        .stat-card.success .number { color: #27ae60; }

        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 0.8em;
            color: #666;
            font-weight: 600;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            min-width: 140px;
        }

        .data-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .data-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75em;
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
        .badge-cancelled { background: #fafafa; color: #9e9e9e; }

        .badge-field { background: #e8eaf6; color: #3949ab; }
        .badge-internal { background: #fff8e1; color: #ff8f00; }
        .badge-customer { background: #fce4ec; color: #c2185b; }

        .action-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .action-count .open {
            background: #ffebee;
            color: #c62828;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }

        .issue-title {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .overdue-indicator {
            color: #e74c3c;
            font-weight: 600;
        }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .filter-bar { background: #2c3e50; }
        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-color: #34495e; }
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
            <h1>Quality Issues</h1>
            <p style="color: #666; margin: 5px 0 0;">Track field and internal quality issues with corrective actions</p>
        </div>
        <div>
            <a href="issue_add.php" class="btn btn-primary">+ New Issue</a>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">Dashboard</a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="number"><?= (int)$stats['open_count'] ?></div>
            <div class="label">Open Issues</div>
        </div>
        <div class="stat-card critical">
            <div class="number"><?= (int)$stats['critical_open'] ?></div>
            <div class="label">Critical Open</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?= (int)$stats['overdue'] ?></div>
            <div class="label">Overdue</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= (int)$stats['field_issues'] ?></div>
            <div class="label">Field Issues</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= (int)$stats['internal_issues'] ?></div>
            <div class="label">Internal Issues</div>
        </div>
        <div class="stat-card success">
            <div class="number"><?= (int)$stats['closed_count'] ?></div>
            <div class="label">Closed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="get">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Issue No, Title, Part...">
                </div>
                <div class="filter-group">
                    <label>Issue Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="Field Issue" <?= $filter_type === 'Field Issue' ? 'selected' : '' ?>>Field Issue</option>
                        <option value="Internal Issue" <?= $filter_type === 'Internal Issue' ? 'selected' : '' ?>>Internal Issue</option>
                        <option value="Customer Complaint" <?= $filter_type === 'Customer Complaint' ? 'selected' : '' ?>>Customer Complaint</option>
                        <option value="Supplier Issue" <?= $filter_type === 'Supplier Issue' ? 'selected' : '' ?>>Supplier Issue</option>
                        <option value="Process Issue" <?= $filter_type === 'Process Issue' ? 'selected' : '' ?>>Process Issue</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="Open" <?= $filter_status === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="Analysis" <?= $filter_status === 'Analysis' ? 'selected' : '' ?>>Analysis</option>
                        <option value="Action Required" <?= $filter_status === 'Action Required' ? 'selected' : '' ?>>Action Required</option>
                        <option value="In Progress" <?= $filter_status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Verification" <?= $filter_status === 'Verification' ? 'selected' : '' ?>>Verification</option>
                        <option value="Closed" <?= $filter_status === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="">All Priority</option>
                        <option value="Critical" <?= $filter_priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="High" <?= $filter_priority === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $filter_priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $filter_priority === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Assigned To</label>
                    <select name="assigned">
                        <option value="">All</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filter_assigned == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['emp_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filter</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="issues.php" class="btn btn-secondary" style="padding: 8px 15px;">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Issues Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Issue No</th>
                <th>Type</th>
                <th>Title</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Assigned To</th>
                <th>Issue Date</th>
                <th>Target Date</th>
                <th>Actions</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($issues) === 0): ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                        No quality issues found. <a href="issue_add.php">Create your first issue</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($issues as $issue):
                    $isOverdue = $issue['target_closure_date'] && $issue['target_closure_date'] < date('Y-m-d') && !in_array($issue['status'], ['Closed', 'Cancelled']);
                    $typeBadge = 'badge-internal';
                    if ($issue['issue_type'] === 'Field Issue') $typeBadge = 'badge-field';
                    elseif ($issue['issue_type'] === 'Customer Complaint') $typeBadge = 'badge-customer';

                    $statusBadge = 'badge-open';
                    $statusMap = [
                        'Open' => 'badge-open',
                        'Analysis' => 'badge-analysis',
                        'Action Required' => 'badge-action',
                        'In Progress' => 'badge-progress',
                        'Verification' => 'badge-verification',
                        'Closed' => 'badge-closed',
                        'Cancelled' => 'badge-cancelled'
                    ];
                    $statusBadge = $statusMap[$issue['status']] ?? 'badge-open';
                ?>
                <tr>
                    <td><a href="issue_view.php?id=<?= $issue['id'] ?>" style="color: #667eea; font-weight: 600;"><?= htmlspecialchars($issue['issue_no']) ?></a></td>
                    <td><span class="badge <?= $typeBadge ?>"><?= htmlspecialchars($issue['issue_type']) ?></span></td>
                    <td>
                        <div class="issue-title" title="<?= htmlspecialchars($issue['title']) ?>">
                            <?= htmlspecialchars($issue['title']) ?>
                        </div>
                        <?php if ($issue['part_no']): ?>
                            <small style="color: #888;">Part: <?= htmlspecialchars($issue['part_no']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= strtolower($issue['priority']) ?>"><?= htmlspecialchars($issue['priority']) ?></span></td>
                    <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($issue['status']) ?></span></td>
                    <td><?= htmlspecialchars($issue['assigned_to'] ?: '-') ?></td>
                    <td><?= date('d M Y', strtotime($issue['issue_date'])) ?></td>
                    <td class="<?= $isOverdue ? 'overdue-indicator' : '' ?>">
                        <?= $issue['target_closure_date'] ? date('d M Y', strtotime($issue['target_closure_date'])) : '-' ?>
                        <?php if ($isOverdue): ?>
                            <br><small>OVERDUE</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="action-count">
                            <?= (int)$issue['action_count'] ?>
                            <?php if ($issue['open_actions'] > 0): ?>
                                <span class="open"><?= $issue['open_actions'] ?> open</span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <a href="issue_view.php?id=<?= $issue['id'] ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.85em;">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top: 15px; color: #666;">
        Showing <?= count($issues) ?> issue(s)
    </p>
</div>

</body>
</html>
