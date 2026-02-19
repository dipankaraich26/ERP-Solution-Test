<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');
include "../includes/dialog.php";

$currentUserId = getUserId();
$currentUserRole = getUserRole();
$isAdmin = ($currentUserRole === 'admin');

/* =========================
   FILTERS
========================= */
$filter_type = $_GET['type'] ?? '';
$filter_handled_by = $_GET['handled_by'] ?? '';
$filter_assigned = $_GET['assigned'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

/* =========================
   GET EMPLOYEES FOR FILTER
========================= */
$employees = [];
try {
    $employees = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as employee_name, department
        FROM employees WHERE status = 'Active'
        ORDER BY first_name, last_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Get unique 'handled_by' values for filter dropdown
$handledByList = [];
try {
    $handledByList = $pdo->query("
        SELECT DISTINCT handled_by FROM crm_lead_interactions
        WHERE handled_by IS NOT NULL AND handled_by != ''
        ORDER BY handled_by
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

/* =========================
   PAGINATION
========================= */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

/* =========================
   BUILD QUERY
========================= */
$where = [];
$params = [];

if ($filter_type) {
    $where[] = "i.interaction_type = ?";
    $params[] = $filter_type;
}
if ($filter_handled_by) {
    $where[] = "i.handled_by = ?";
    $params[] = $filter_handled_by;
}
if ($filter_assigned) {
    $where[] = "l.assigned_user_id = ?";
    $params[] = $filter_assigned;
}
if ($filter_date_from) {
    $where[] = "DATE(i.interaction_date) >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where[] = "DATE(i.interaction_date) <= ?";
    $params[] = $filter_date_to;
}
if ($search) {
    $where[] = "(l.company_name LIKE ? OR l.contact_person LIKE ? OR l.lead_no LIKE ? OR i.subject LIKE ? OR i.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countSql = "SELECT COUNT(*) FROM crm_lead_interactions i JOIN crm_leads l ON l.id = i.lead_id $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get interactions
$sql = "
    SELECT i.*, l.lead_no, l.company_name, l.contact_person, l.lead_status,
           l.assigned_to, l.phone
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    $whereClause
    ORDER BY i.interaction_date DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SUMMARY STATS
========================= */
$statsSql = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN i.interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
        SUM(CASE WHEN i.interaction_type = 'email' THEN 1 ELSE 0 END) as emails,
        SUM(CASE WHEN i.interaction_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
        SUM(CASE WHEN i.interaction_type = 'site_visit' THEN 1 ELSE 0 END) as site_visits,
        SUM(CASE WHEN i.interaction_type = 'demo' THEN 1 ELSE 0 END) as demos,
        SUM(CASE WHEN i.interaction_type = 'quotation_sent' THEN 1 ELSE 0 END) as quotations,
        SUM(CASE WHEN i.next_action_date IS NOT NULL AND i.next_action_date < CURDATE() THEN 1 ELSE 0 END) as overdue_actions
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    $whereClause
";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   LEAD STATUS-WISE SUMMARY
========================= */
$leadStatusSummarySql = "
    SELECT l.lead_status,
           COUNT(DISTINCT l.id) as total_leads,
           COUNT(i.id) as total_interactions,
           SUM(CASE WHEN i.interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
           SUM(CASE WHEN i.interaction_type = 'email' THEN 1 ELSE 0 END) as emails,
           SUM(CASE WHEN i.interaction_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
           SUM(CASE WHEN i.interaction_type = 'site_visit' THEN 1 ELSE 0 END) as site_visits,
           SUM(CASE WHEN i.interaction_type = 'demo' THEN 1 ELSE 0 END) as demos,
           SUM(CASE WHEN i.interaction_type = 'quotation_sent' THEN 1 ELSE 0 END) as quotations,
           ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT l.id), 0), 1) as avg_per_lead
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    $whereClause
    GROUP BY l.lead_status
    ORDER BY FIELD(l.lead_status, 'hot', 'warm', 'cold', 'converted', 'lost')
";
$leadStatusSummaryStmt = $pdo->prepare($leadStatusSummarySql);
$leadStatusSummaryStmt->execute($params);
$leadStatusSummary = $leadStatusSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   EMPLOYEE-WISE SUMMARY (for filtered results)
========================= */
$empSummarySql = "
    SELECT i.handled_by,
           COUNT(*) as total_interactions,
           SUM(CASE WHEN i.interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
           SUM(CASE WHEN i.interaction_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
           SUM(CASE WHEN i.interaction_type = 'site_visit' THEN 1 ELSE 0 END) as site_visits,
           COUNT(DISTINCT i.lead_id) as unique_leads
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    $whereClause
    GROUP BY i.handled_by
    ORDER BY total_interactions DESC
";
$empSummaryStmt = $pdo->prepare($empSummarySql);
$empSummaryStmt->execute($params);
$empSummary = $empSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Interaction Report - CRM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #fff;
        }
        .stat-card .number { font-size: 1.8em; font-weight: bold; }
        .stat-card .label { font-size: 0.8em; opacity: 0.9; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            align-items: end;
        }
        .filters .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filters .filter-group label { font-size: 0.8em; font-weight: bold; color: #555; }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .int-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .type-call { background: #d5f5e3; color: #27ae60; }
        .type-email { background: #d6eaf8; color: #2980b9; }
        .type-meeting { background: #fdebd0; color: #e67e22; }
        .type-site_visit { background: #f5eef8; color: #8e44ad; }
        .type-demo { background: #fadbd8; color: #e74c3c; }
        .type-quotation_sent { background: #d4efdf; color: #1e8449; }
        .type-other { background: #eaecee; color: #566573; }

        .lead-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-hot { background: #e74c3c; color: #fff; }
        .status-warm { background: #f39c12; color: #fff; }
        .status-cold { background: #bdc3c7; color: #2c3e50; }
        .status-converted { background: #27ae60; color: #fff; }
        .status-lost { background: #7f8c8d; color: #fff; }

        table { width: 100%; }
        table th { background: #2c3e50; color: #fff; font-size: 0.85em; }
        table td { font-size: 0.9em; vertical-align: top; }

        .overdue-action { color: #e74c3c; font-weight: bold; }

        .summary-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .summary-table th { background: #34495e; color: #fff; padding: 10px; font-size: 0.85em; }
        .summary-table td { padding: 10px; text-align: center; }
        .summary-table td:first-child { text-align: left; font-weight: bold; }

        .section-toggle {
            cursor: pointer;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-toggle:hover { background: #e9ecef; }
        .section-toggle h3 { margin: 0; }
    </style>
</head>
<body>

<div class="content">
    <h1>Lead Interaction Report</h1>
    <p>
        <a href="index.php" class="btn btn-secondary">Back to Leads</a>
        <a href="dashboard.php" class="btn btn-secondary">CRM Dashboard</a>
    </p>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="background: #3498db;">
            <div class="number"><?= $stats['total'] ?? 0 ?></div>
            <div class="label">Total Interactions</div>
        </div>
        <div class="stat-card" style="background: #27ae60;">
            <div class="number"><?= $stats['calls'] ?? 0 ?></div>
            <div class="label">Calls</div>
        </div>
        <div class="stat-card" style="background: #2980b9;">
            <div class="number"><?= $stats['emails'] ?? 0 ?></div>
            <div class="label">Emails</div>
        </div>
        <div class="stat-card" style="background: #e67e22;">
            <div class="number"><?= $stats['meetings'] ?? 0 ?></div>
            <div class="label">Meetings</div>
        </div>
        <div class="stat-card" style="background: #8e44ad;">
            <div class="number"><?= $stats['site_visits'] ?? 0 ?></div>
            <div class="label">Site Visits</div>
        </div>
        <div class="stat-card" style="background: #e74c3c;">
            <div class="number"><?= $stats['demos'] ?? 0 ?></div>
            <div class="label">Demos</div>
        </div>
        <div class="stat-card" style="background: #1e8449;">
            <div class="number"><?= $stats['quotations'] ?? 0 ?></div>
            <div class="label">Quotations Sent</div>
        </div>
        <div class="stat-card" style="background: #c0392b;">
            <div class="number"><?= $stats['overdue_actions'] ?? 0 ?></div>
            <div class="label">Overdue Actions</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filters">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" placeholder="Lead, company, subject..."
                   value="<?= htmlspecialchars($search) ?>" style="min-width: 180px;">
        </div>
        <div class="filter-group">
            <label>Interaction Type</label>
            <select name="type">
                <option value="">All Types</option>
                <option value="call" <?= $filter_type === 'call' ? 'selected' : '' ?>>Phone Call</option>
                <option value="email" <?= $filter_type === 'email' ? 'selected' : '' ?>>Email</option>
                <option value="meeting" <?= $filter_type === 'meeting' ? 'selected' : '' ?>>Meeting</option>
                <option value="site_visit" <?= $filter_type === 'site_visit' ? 'selected' : '' ?>>Site Visit</option>
                <option value="demo" <?= $filter_type === 'demo' ? 'selected' : '' ?>>Demo</option>
                <option value="quotation_sent" <?= $filter_type === 'quotation_sent' ? 'selected' : '' ?>>Quotation Sent</option>
                <option value="other" <?= $filter_type === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Handled By</label>
            <select name="handled_by">
                <option value="">All</option>
                <?php foreach ($handledByList as $hb): ?>
                    <option value="<?= htmlspecialchars($hb) ?>" <?= $filter_handled_by === $hb ? 'selected' : '' ?>>
                        <?= htmlspecialchars($hb) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($employees)): ?>
        <div class="filter-group">
            <label>Assigned To (Lead)</label>
            <select name="assigned">
                <option value="">All</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $filter_assigned == $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['employee_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
        </div>
        <div class="filter-group">
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="interaction_report.php" class="btn btn-secondary">Clear</a>
            </div>
        </div>
    </form>

    <!-- Lead Status-wise Summary -->
    <?php if (!empty($leadStatusSummary)): ?>
    <div class="section-toggle" onclick="toggleSection('leadStatusSummary')" style="border-left: 4px solid #e74c3c;">
        <h3>Lead Status-wise Interaction Summary</h3>
        <span id="leadStatusSummaryArrow">&#9650;</span>
    </div>
    <div id="leadStatusSummary">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <?php
            $statusColors = ['hot' => '#e74c3c', 'warm' => '#f39c12', 'cold' => '#95a5a6', 'converted' => '#27ae60', 'lost' => '#7f8c8d'];
            foreach ($leadStatusSummary as $ls):
                $color = $statusColors[$ls['lead_status']] ?? '#34495e';
            ?>
            <div style="background: <?= $color ?>; color: white; border-radius: 10px; padding: 15px; text-align: center;">
                <div style="font-size: 0.8em; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;"><?= ucfirst($ls['lead_status']) ?> Leads</div>
                <div style="font-size: 2em; font-weight: bold; margin: 5px 0;"><?= $ls['total_leads'] ?></div>
                <div style="font-size: 0.85em; opacity: 0.9;"><?= $ls['total_interactions'] ?> interactions</div>
                <div style="font-size: 0.8em; opacity: 0.8;"><?= $ls['avg_per_lead'] ?> avg/lead</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="overflow-x: auto; margin-bottom: 25px;">
        <table class="summary-table" border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Lead Status</th>
                    <th>Leads</th>
                    <th>Total Interactions</th>
                    <th>Calls</th>
                    <th>Emails</th>
                    <th>Meetings</th>
                    <th>Site Visits</th>
                    <th>Demos</th>
                    <th>Quotations</th>
                    <th>Avg/Lead</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandLeads = 0; $grandInt = 0; $grandCalls = 0; $grandEmails = 0;
                $grandMeetings = 0; $grandSV = 0; $grandDemos = 0; $grandQuot = 0;
                foreach ($leadStatusSummary as $ls):
                    $grandLeads += $ls['total_leads'];
                    $grandInt += $ls['total_interactions'];
                    $grandCalls += $ls['calls'];
                    $grandEmails += $ls['emails'];
                    $grandMeetings += $ls['meetings'];
                    $grandSV += $ls['site_visits'];
                    $grandDemos += $ls['demos'];
                    $grandQuot += $ls['quotations'];
                ?>
                <tr>
                    <td>
                        <span class="lead-status status-<?= $ls['lead_status'] ?>"><?= ucfirst($ls['lead_status']) ?></span>
                    </td>
                    <td><strong><?= $ls['total_leads'] ?></strong></td>
                    <td><strong><?= $ls['total_interactions'] ?></strong></td>
                    <td><?= $ls['calls'] ?></td>
                    <td><?= $ls['emails'] ?></td>
                    <td><?= $ls['meetings'] ?></td>
                    <td><?= $ls['site_visits'] ?></td>
                    <td><?= $ls['demos'] ?></td>
                    <td><?= $ls['quotations'] ?></td>
                    <td><strong><?= $ls['avg_per_lead'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #2c3e50; color: white; font-weight: bold;">
                    <td style="text-align: left; color: white;">TOTAL</td>
                    <td><?= $grandLeads ?></td>
                    <td><?= $grandInt ?></td>
                    <td><?= $grandCalls ?></td>
                    <td><?= $grandEmails ?></td>
                    <td><?= $grandMeetings ?></td>
                    <td><?= $grandSV ?></td>
                    <td><?= $grandDemos ?></td>
                    <td><?= $grandQuot ?></td>
                    <td><?= $grandLeads > 0 ? round($grandInt / $grandLeads, 1) : 0 ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Employee-wise Summary -->
    <?php if (!empty($empSummary)): ?>
    <div class="section-toggle" onclick="toggleSection('empSummary')">
        <h3>Employee-wise Interaction Summary</h3>
        <span id="empSummaryArrow">&#9660;</span>
    </div>
    <div id="empSummary" style="display: none;">
        <div style="overflow-x: auto;">
        <table class="summary-table" border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Handled By</th>
                    <th>Total Interactions</th>
                    <th>Calls</th>
                    <th>Meetings</th>
                    <th>Site Visits</th>
                    <th>Unique Leads Contacted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empSummary as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['handled_by'] ?: 'Not specified') ?></td>
                    <td><strong><?= $emp['total_interactions'] ?></strong></td>
                    <td><?= $emp['calls'] ?></td>
                    <td><?= $emp['meetings'] ?></td>
                    <td><?= $emp['site_visits'] ?></td>
                    <td><?= $emp['unique_leads'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Interactions Table -->
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>Date</th>
                <th>Lead</th>
                <th>Company / Contact</th>
                <th>Lead Status</th>
                <th>Type</th>
                <th>Subject</th>
                <th>Description</th>
                <th>Outcome</th>
                <th>Next Action</th>
                <th>Next Action Date</th>
                <th>Handled By</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($interactions as $int): ?>
            <?php
                $isOverdue = $int['next_action_date'] && strtotime($int['next_action_date']) < strtotime(date('Y-m-d'));
            ?>
            <tr>
                <td style="white-space: nowrap;">
                    <?= date('d M Y', strtotime($int['interaction_date'])) ?><br>
                    <small style="color: #888;"><?= date('h:i A', strtotime($int['interaction_date'])) ?></small>
                </td>
                <td>
                    <a href="view.php?id=<?= $int['lead_id'] ?>#interactions">
                        <strong><?= htmlspecialchars($int['lead_no']) ?></strong>
                    </a>
                </td>
                <td>
                    <?php if ($int['company_name']): ?>
                        <strong><?= htmlspecialchars($int['company_name']) ?></strong><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($int['contact_person']) ?>
                    <?php if ($int['phone']): ?>
                        <br><small style="color: #888;"><?= htmlspecialchars($int['phone']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="lead-status status-<?= $int['lead_status'] ?>">
                        <?= ucfirst($int['lead_status']) ?>
                    </span>
                </td>
                <td>
                    <span class="int-type-badge type-<?= $int['interaction_type'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $int['interaction_type'])) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($int['subject'] ?? '-') ?></td>
                <td style="max-width: 200px;">
                    <?php if ($int['description']): ?>
                        <span title="<?= htmlspecialchars($int['description']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($int['description'], 0, 100, '...')) ?>
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($int['outcome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($int['next_action'] ?? '-') ?></td>
                <td class="<?= $isOverdue ? 'overdue-action' : '' ?>" style="white-space: nowrap;">
                    <?php if ($int['next_action_date']): ?>
                        <?= date('d M Y', strtotime($int['next_action_date'])) ?>
                        <?php if ($isOverdue): ?>
                            <br><small>OVERDUE</small>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($int['handled_by'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($interactions)): ?>
            <tr>
                <td colspan="11" style="text-align: center; padding: 30px; color: #999;">
                    No interactions found for the selected filters.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $queryString = http_build_query($queryParams);
            $queryString = $queryString ? '&' . $queryString : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $queryString ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $queryString ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> interactions)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $queryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleSection(id) {
    const section = document.getElementById(id);
    const arrow = document.getElementById(id + 'Arrow');
    if (section.style.display === 'none') {
        section.style.display = 'block';
        arrow.innerHTML = '&#9650;';
    } else {
        section.style.display = 'none';
        arrow.innerHTML = '&#9660;';
    }
}
</script>

</body>
</html>
