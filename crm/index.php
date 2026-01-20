<?php
include "../db.php";
include "../includes/dialog.php";

/* =========================
   FILTERS
========================= */
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_timeline = $_GET['timeline'] ?? '';
$search = $_GET['search'] ?? '';

/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

/* =========================
   BUILD QUERY WITH FILTERS
========================= */
$where = [];
$params = [];

if ($filter_status) {
    $where[] = "lead_status = ?";
    $params[] = $filter_status;
}
if ($filter_type) {
    $where[] = "customer_type = ?";
    $params[] = $filter_type;
}
if ($filter_timeline) {
    $where[] = "buying_timeline = ?";
    $params[] = $filter_timeline;
}
if ($search) {
    $where[] = "(company_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM crm_leads $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get leads
$sql = "
    SELECT l.*,
           (SELECT COUNT(*) FROM crm_lead_requirements WHERE lead_id = l.id) as req_count,
           (SELECT COUNT(*) FROM crm_lead_interactions WHERE lead_id = l.id) as interaction_count
    FROM crm_leads l
    $whereClause
    ORDER BY
        CASE lead_status
            WHEN 'hot' THEN 1
            WHEN 'warm' THEN 2
            WHEN 'cold' THEN 3
            WHEN 'converted' THEN 4
            WHEN 'lost' THEN 5
        END,
        next_followup_date ASC,
        updated_at DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   STATS FOR DASHBOARD
========================= */
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN lead_status = 'hot' THEN 1 ELSE 0 END) as hot,
        SUM(CASE WHEN lead_status = 'warm' THEN 1 ELSE 0 END) as warm,
        SUM(CASE WHEN lead_status = 'cold' THEN 1 ELSE 0 END) as cold,
        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) as lost,
        SUM(CASE WHEN next_followup_date = CURDATE() THEN 1 ELSE 0 END) as followup_today,
        SUM(CASE WHEN next_followup_date < CURDATE() AND lead_status NOT IN ('converted', 'lost') THEN 1 ELSE 0 END) as overdue
    FROM crm_leads
")->fetch(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>CRM - Lead Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #fff;
        }
        .stat-card .number { font-size: 2em; font-weight: bold; }
        .stat-card .label { font-size: 0.85em; opacity: 0.9; }
        .stat-total { background: #3498db; }
        .stat-hot { background: #e74c3c; }
        .stat-warm { background: #f39c12; }
        .stat-cold { background: #95a5a6; }
        .stat-converted { background: #27ae60; }
        .stat-lost { background: #7f8c8d; }
        .stat-followup { background: #9b59b6; }
        .stat-overdue { background: #c0392b; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .lead-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-hot { background: #e74c3c; color: #fff; }
        .status-warm { background: #f39c12; color: #fff; }
        .status-cold { background: #bdc3c7; color: #2c3e50; }
        .status-converted { background: #27ae60; color: #fff; }
        .status-lost { background: #7f8c8d; color: #fff; }

        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .type-b2b { background: #3498db; color: #fff; }
        .type-b2c { background: #9b59b6; color: #fff; }

        .timeline-badge {
            font-size: 0.8em;
            color: #666;
        }
        .timeline-immediate { color: #e74c3c; font-weight: bold; }
        .timeline-1_month { color: #f39c12; }

        .followup-overdue { color: #e74c3c; font-weight: bold; }
        .followup-today { color: #27ae60; font-weight: bold; }
        .followup-upcoming { color: #3498db; }

        table { width: 100%; }
        table th { background: #2c3e50; color: #fff; }
    </style>
</head>
<body>

<div class="content">
    <h1>CRM - Lead Management</h1>

    <!-- Stats Dashboard -->
    <div class="stats-grid">
        <div class="stat-card stat-total">
            <div class="number"><?= $stats['total'] ?? 0 ?></div>
            <div class="label">Total Leads</div>
        </div>
        <div class="stat-card stat-hot">
            <div class="number"><?= $stats['hot'] ?? 0 ?></div>
            <div class="label">Hot</div>
        </div>
        <div class="stat-card stat-warm">
            <div class="number"><?= $stats['warm'] ?? 0 ?></div>
            <div class="label">Warm</div>
        </div>
        <div class="stat-card stat-cold">
            <div class="number"><?= $stats['cold'] ?? 0 ?></div>
            <div class="label">Cold</div>
        </div>
        <div class="stat-card stat-converted">
            <div class="number"><?= $stats['converted'] ?? 0 ?></div>
            <div class="label">Converted</div>
        </div>
        <div class="stat-card stat-followup">
            <div class="number"><?= $stats['followup_today'] ?? 0 ?></div>
            <div class="label">Follow-up Today</div>
        </div>
        <div class="stat-card stat-overdue">
            <div class="number"><?= $stats['overdue'] ?? 0 ?></div>
            <div class="label">Overdue</div>
        </div>
    </div>

    <p>
        <a href="add.php" class="btn btn-primary">+ Add New Lead</a>
    </p>

    <!-- Filters -->
    <form method="get" class="filters">
        <input type="text" name="search" placeholder="Search name, phone, email..."
               value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">

        <select name="status">
            <option value="">All Status</option>
            <option value="hot" <?= $filter_status === 'hot' ? 'selected' : '' ?>>Hot</option>
            <option value="warm" <?= $filter_status === 'warm' ? 'selected' : '' ?>>Warm</option>
            <option value="cold" <?= $filter_status === 'cold' ? 'selected' : '' ?>>Cold</option>
            <option value="converted" <?= $filter_status === 'converted' ? 'selected' : '' ?>>Converted</option>
            <option value="lost" <?= $filter_status === 'lost' ? 'selected' : '' ?>>Lost</option>
        </select>

        <select name="type">
            <option value="">All Types</option>
            <option value="B2B" <?= $filter_type === 'B2B' ? 'selected' : '' ?>>B2B</option>
            <option value="B2C" <?= $filter_type === 'B2C' ? 'selected' : '' ?>>B2C</option>
        </select>

        <select name="timeline">
            <option value="">All Timelines</option>
            <option value="immediate" <?= $filter_timeline === 'immediate' ? 'selected' : '' ?>>Immediate</option>
            <option value="1_month" <?= $filter_timeline === '1_month' ? 'selected' : '' ?>>Within 1 Month</option>
            <option value="3_months" <?= $filter_timeline === '3_months' ? 'selected' : '' ?>>Within 3 Months</option>
            <option value="6_months" <?= $filter_timeline === '6_months' ? 'selected' : '' ?>>Within 6 Months</option>
            <option value="1_year" <?= $filter_timeline === '1_year' ? 'selected' : '' ?>>Within 1 Year</option>
            <option value="uncertain" <?= $filter_timeline === 'uncertain' ? 'selected' : '' ?>>Uncertain</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="index.php" class="btn btn-secondary">Clear</a>
    </form>

    <!-- Leads Table -->
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>Lead #</th>
                <th>Type</th>
                <th>Company / Contact</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Buying Timeline</th>
                <th>Next Follow-up</th>
                <th>Reqs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $lead): ?>
            <?php
                $followupClass = '';
                if ($lead['next_followup_date']) {
                    $fdate = strtotime($lead['next_followup_date']);
                    $today = strtotime(date('Y-m-d'));
                    if ($fdate < $today && !in_array($lead['lead_status'], ['converted', 'lost'])) {
                        $followupClass = 'followup-overdue';
                    } elseif ($fdate == $today) {
                        $followupClass = 'followup-today';
                    } else {
                        $followupClass = 'followup-upcoming';
                    }
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($lead['lead_no']) ?></strong></td>
                <td>
                    <span class="type-badge type-<?= strtolower($lead['customer_type']) ?>">
                        <?= $lead['customer_type'] ?>
                    </span>
                </td>
                <td>
                    <?php if ($lead['company_name']): ?>
                        <strong><?= htmlspecialchars($lead['company_name']) ?></strong><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($lead['contact_person']) ?>
                    <?php if ($lead['designation']): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($lead['designation']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= htmlspecialchars($lead['phone'] ?? '-') ?>
                    <?php if ($lead['email']): ?>
                        <br><small><?= htmlspecialchars($lead['email']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="lead-status status-<?= $lead['lead_status'] ?>">
                        <?= ucfirst($lead['lead_status']) ?>
                    </span>
                </td>
                <td>
                    <span class="timeline-badge timeline-<?= $lead['buying_timeline'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $lead['buying_timeline'])) ?>
                    </span>
                    <?php if ($lead['budget_range']): ?>
                        <br><small>Budget: <?= htmlspecialchars($lead['budget_range']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($lead['next_followup_date']): ?>
                        <span class="<?= $followupClass ?>">
                            <?= date('d M Y', strtotime($lead['next_followup_date'])) ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">Not set</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;">
                    <?= $lead['req_count'] ?>
                </td>
                <td style="white-space: nowrap;">
                    <a class="btn btn-secondary" href="view.php?id=<?= $lead['id'] ?>">View</a>
                    <a class="btn btn-primary" href="edit.php?id=<?= $lead['id'] ?>">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($leads)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 30px;">
                    No leads found. <a href="add.php">Add your first lead</a>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> leads)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $queryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
