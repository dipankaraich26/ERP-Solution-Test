<?php
/**
 * Hot Lead Action Tracker
 * Track and prioritize high-value leads with urgency indicators
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_assigned = $_GET['assigned'] ?? '';
$filter_source = $_GET['source'] ?? '';
$filter_overdue = $_GET['overdue'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get filter options
$assignedOptions = [];
$sourceOptions = [];
try {
    $assignedOptions = $pdo->query("
        SELECT DISTINCT e.id, CONCAT(e.first_name, ' ', e.last_name) as name
        FROM employees e
        INNER JOIN crm_leads l ON l.assigned_user_id = e.id
        WHERE l.lead_status IN ('hot', 'warm')
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $sourceOptions = $pdo->query("
        SELECT DISTINCT lead_source FROM crm_leads
        WHERE lead_source IS NOT NULL AND lead_source != '' AND lead_status IN ('hot', 'warm')
        ORDER BY lead_source
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Build WHERE clause
$where = ["l.lead_status IN ('hot', 'warm')"];
$params = [];

if ($filter_status && in_array($filter_status, ['hot', 'warm'])) {
    $where[] = "l.lead_status = ?";
    $params[] = $filter_status;
}
if ($filter_assigned) {
    $where[] = "l.assigned_user_id = ?";
    $params[] = $filter_assigned;
}
if ($filter_source) {
    $where[] = "l.lead_source = ?";
    $params[] = $filter_source;
}
if ($filter_overdue === 'yes') {
    $where[] = "l.next_followup_date < CURDATE()";
}
if ($search) {
    $where[] = "(l.company_name LIKE ? OR l.contact_person LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Count
$totalCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads l $whereClause");
    $stmt->execute($params);
    $totalCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$totalPages = max(1, ceil($totalCount / $per_page));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per_page;

// Main query
$leads = [];
try {
    $sql = "
        SELECT l.*,
            CONCAT(e.first_name, ' ', e.last_name) as assigned_name,
            CASE
                WHEN l.next_followup_date IS NULL THEN NULL
                ELSE DATEDIFF(CURDATE(), l.next_followup_date)
            END as days_overdue,
            (SELECT MAX(interaction_date) FROM crm_lead_interactions WHERE lead_id = l.id) as last_interaction
        FROM crm_leads l
        LEFT JOIN employees e ON e.id = l.assigned_user_id
        $whereClause
        ORDER BY
            CASE
                WHEN l.next_followup_date IS NULL THEN 3
                WHEN l.next_followup_date < CURDATE() THEN 0
                WHEN l.next_followup_date = CURDATE() THEN 1
                ELSE 2
            END,
            l.next_followup_date ASC
        LIMIT ? OFFSET ?
    ";
    $allParams = array_merge($params, [$per_page, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// KPI Summary
$totalHot = 0; $totalWarm = 0; $overdueCount = 0; $avgDaysSinceInteraction = 0;
try {
    $summary = $pdo->query("
        SELECT
            SUM(CASE WHEN lead_status='hot' THEN 1 ELSE 0 END) as total_hot,
            SUM(CASE WHEN lead_status='warm' THEN 1 ELSE 0 END) as total_warm,
            SUM(CASE WHEN next_followup_date < CURDATE() AND lead_status IN ('hot','warm') THEN 1 ELSE 0 END) as overdue_count
        FROM crm_leads
    ")->fetch(PDO::FETCH_ASSOC);
    $totalHot = (int)$summary['total_hot'];
    $totalWarm = (int)$summary['total_warm'];
    $overdueCount = (int)$summary['overdue_count'];

    $avgStmt = $pdo->query("
        SELECT AVG(DATEDIFF(CURDATE(), li.last_date)) as avg_days
        FROM crm_leads l
        JOIN (SELECT lead_id, MAX(interaction_date) as last_date FROM crm_lead_interactions GROUP BY lead_id) li ON li.lead_id = l.id
        WHERE l.lead_status IN ('hot', 'warm')
    ");
    $avgDaysSinceInteraction = round((float)$avgStmt->fetchColumn());
} catch (Exception $e) {}

// Query string for pagination
$queryParams = [];
if ($filter_status) $queryParams['status'] = $filter_status;
if ($filter_assigned) $queryParams['assigned'] = $filter_assigned;
if ($filter_source) $queryParams['source'] = $filter_source;
if ($filter_overdue) $queryParams['overdue'] = $filter_overdue;
if ($search) $queryParams['search'] = $search;
$queryString = $queryParams ? '&' . http_build_query($queryParams) : '';

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hot Lead Tracker - ERP System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .strategy-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 3px solid #10b981;
        }
        .strategy-header h1 { margin: 0; font-size: 1.6em; color: var(--text); }
        .strategy-header .subtitle { font-size: 0.85em; color: var(--muted-text); }

        .kpi-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin-bottom: 20px;
        }
        .kpi-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px; position: relative; overflow: hidden;
        }
        .kpi-card .kpi-stripe { position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .kpi-card .kpi-label { font-size: 0.75em; color: var(--muted-text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .kpi-card .kpi-value { font-size: 1.7em; font-weight: 700; color: var(--text); }
        .kpi-card .kpi-sub { font-size: 0.78em; color: var(--muted-text); margin-top: 4px; }

        .filter-bar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            background: var(--card); padding: 15px 20px; border-radius: 10px;
            border: 1px solid var(--border); margin-bottom: 20px;
        }
        .filter-bar select, .filter-bar input[type="text"] {
            padding: 8px 12px; border-radius: 6px;
            border: 1px solid var(--input-border, var(--border));
            background: var(--input-bg, var(--card)); color: var(--input-text, var(--text));
            font-size: 0.9em;
        }
        .filter-bar .btn {
            padding: 8px 16px; border-radius: 6px; font-size: 0.9em;
            font-weight: 600; text-decoration: none; border: none; cursor: pointer;
        }
        .btn-primary { background: var(--primary, #2563eb); color: white; }
        .btn-secondary { background: var(--secondary, #64748b); color: white; }

        .data-table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--border); }
        .data-table th { background: var(--table-header-bg, #1e293b); color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82em; text-transform: uppercase; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.88em; color: var(--text); }

        .row-overdue { background: #fef2f2 !important; border-left: 4px solid #ef4444; }
        .row-today { background: #fffbeb !important; border-left: 4px solid #f59e0b; }
        .row-upcoming { background: #f0fdf4 !important; border-left: 4px solid #10b981; }
        .row-nodate { background: var(--bg) !important; border-left: 4px solid #94a3b8; }

        body.dark .row-overdue { background: #1a0808 !important; }
        body.dark .row-today { background: #1a1408 !important; }
        body.dark .row-upcoming { background: #081a0f !important; }
        body.dark .row-nodate { background: #111827 !important; }
        body.mid .row-overdue { background: #2d1515 !important; }
        body.mid .row-today { background: #2d2515 !important; }
        body.mid .row-upcoming { background: #152d1f !important; }
        body.mid .row-nodate { background: #1e293b !important; }

        .badge-hot { background: #ef4444; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .badge-warm { background: #f59e0b; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; text-transform: uppercase; display: inline-block; }

        .overdue-text { color: #dc2626; font-weight: 600; }
        .today-text { color: #d97706; font-weight: 600; }
        .upcoming-text { color: #16a34a; }

        .btn-action {
            display: inline-block; padding: 4px 10px; font-size: 0.78em;
            border-radius: 5px; text-decoration: none; font-weight: 600; margin: 1px;
        }
        .btn-action.view { background: var(--secondary, #64748b); color: white; }
        .btn-action.quote { background: #10b981; color: white; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; }
        .pagination a, .pagination span {
            padding: 6px 12px; border-radius: 6px; text-decoration: none;
            font-size: 0.88em; border: 1px solid var(--border);
        }
        .pagination a { background: var(--card); color: var(--text); }
        .pagination a:hover { background: var(--bg); }
        .pagination .current { background: var(--primary, #2563eb); color: white; border-color: var(--primary, #2563eb); }

        .back-link { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; font-size: 0.9em; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="content">

    <a href="/strategy/index.php" class="back-link">&larr; Back to Strategy Dashboard</a>

    <div class="strategy-header">
        <div>
            <h1>Hot Lead Action Tracker</h1>
            <div class="subtitle">Prioritize and follow up on high-value leads</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #ef4444;"></div>
            <div class="kpi-label">Hot Leads</div>
            <div class="kpi-value"><?= $totalHot ?></div>
            <div class="kpi-sub">High buying intent</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #f59e0b;"></div>
            <div class="kpi-label">Warm Leads</div>
            <div class="kpi-value"><?= $totalWarm ?></div>
            <div class="kpi-sub">Moderate interest</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #dc2626;"></div>
            <div class="kpi-label">Overdue Follow-ups</div>
            <div class="kpi-value"><?= $overdueCount ?></div>
            <div class="kpi-sub"><?= $overdueCount > 0 ? 'Needs immediate action' : 'All on track' ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #6366f1;"></div>
            <div class="kpi-label">Avg Days Since Contact</div>
            <div class="kpi-value"><?= $avgDaysSinceInteraction ?: '-' ?></div>
            <div class="kpi-sub">Last interaction average</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
        <select name="status">
            <option value="">All (Hot + Warm)</option>
            <option value="hot" <?= $filter_status === 'hot' ? 'selected' : '' ?>>Hot Only</option>
            <option value="warm" <?= $filter_status === 'warm' ? 'selected' : '' ?>>Warm Only</option>
        </select>
        <select name="assigned">
            <option value="">All Assigned</option>
            <?php foreach ($assignedOptions as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $filter_assigned == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="source">
            <option value="">All Sources</option>
            <?php foreach ($sourceOptions as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $filter_source === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="overdue">
            <option value="">All Leads</option>
            <option value="yes" <?= $filter_overdue === 'yes' ? 'selected' : '' ?>>Overdue Only</option>
        </select>
        <input type="text" name="search" placeholder="Search company, contact..." value="<?= htmlspecialchars($search) ?>" style="min-width: 180px;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="lead_tracker.php" class="btn btn-secondary" style="text-decoration:none; display:inline-block;">Clear</a>
    </form>

    <!-- Lead Table -->
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Lead #</th>
                    <th>Company</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Next Follow-up</th>
                    <th>Urgency</th>
                    <th>Source</th>
                    <th>Timeline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                <tr><td colspan="11" style="text-align: center; padding: 40px; color: var(--muted-text);">No leads found with current filters</td></tr>
                <?php else: ?>
                <?php foreach ($leads as $lead):
                    $daysOverdue = $lead['days_overdue'];
                    $rowClass = 'row-nodate';
                    $urgencyText = 'No date set';
                    $urgencyClass = '';

                    if ($daysOverdue !== null) {
                        $daysOverdue = (int)$daysOverdue;
                        if ($daysOverdue > 0) {
                            $rowClass = 'row-overdue';
                            $urgencyText = $daysOverdue . ' day' . ($daysOverdue > 1 ? 's' : '') . ' overdue';
                            $urgencyClass = 'overdue-text';
                        } elseif ($daysOverdue == 0) {
                            $rowClass = 'row-today';
                            $urgencyText = 'Due today';
                            $urgencyClass = 'today-text';
                        } else {
                            $rowClass = 'row-upcoming';
                            $urgencyText = 'In ' . abs($daysOverdue) . ' day' . (abs($daysOverdue) > 1 ? 's' : '');
                            $urgencyClass = 'upcoming-text';
                        }
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><strong><?= htmlspecialchars($lead['lead_no']) ?></strong></td>
                    <td><?= htmlspecialchars($lead['company_name'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($lead['contact_person'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($lead['phone'] ?: '-') ?></td>
                    <td><span class="badge-<?= $lead['lead_status'] ?>"><?= ucfirst($lead['lead_status']) ?></span></td>
                    <td><?= htmlspecialchars($lead['assigned_name'] ?: 'Unassigned') ?></td>
                    <td><?= $lead['next_followup_date'] ? date('d M Y', strtotime($lead['next_followup_date'])) : '<span style="color: #94a3b8;">Not set</span>' ?></td>
                    <td class="<?= $urgencyClass ?>"><?= $urgencyText ?></td>
                    <td><?= htmlspecialchars($lead['lead_source'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($lead['buying_timeline'] ?: '-') ?></td>
                    <td style="white-space: nowrap;">
                        <a href="/crm/view.php?id=<?= $lead['id'] ?>" class="btn-action view">View</a>
                        <a href="/quotes/add.php" class="btn-action quote">Quote</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=1<?= $queryString ?>">First</a>
        <a href="?page=<?= $page - 1 ?><?= $queryString ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <?php if ($i == $page): ?>
        <span class="current"><?= $i ?></span>
        <?php else: ?>
        <a href="?page=<?= $i ?><?= $queryString ?>"><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $queryString ?>">Next &raquo;</a>
        <a href="?page=<?= $totalPages ?><?= $queryString ?>">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 10px; font-size: 0.82em; color: var(--muted-text);">
        Showing <?= min($offset + 1, $totalCount) ?>-<?= min($offset + $per_page, $totalCount) ?> of <?= $totalCount ?> leads
    </div>

</div>
</body>
</html>