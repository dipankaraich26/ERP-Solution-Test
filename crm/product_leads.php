<?php
/**
 * Product-wise Lead Status Page
 * Shows leads grouped by product/part, categorized as Hot, Warm, or Cold
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');

// Filters
$searchQuery = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'hot';

// Build WHERE clause for status filter
$statusConditions = "UPPER(l.lead_status) IN ('HOT', 'WARM', 'COLD')";
if ($statusFilter === 'hot') {
    $statusConditions = "UPPER(l.lead_status) = 'HOT'";
} elseif ($statusFilter === 'warm') {
    $statusConditions = "UPPER(l.lead_status) = 'WARM'";
} elseif ($statusFilter === 'cold') {
    $statusConditions = "UPPER(l.lead_status) = 'COLD'";
}

// Build ORDER BY
$orderBy = "hot_count DESC, warm_count DESC, total_leads DESC";
if ($sortBy === 'total') {
    $orderBy = "total_leads DESC, hot_count DESC";
} elseif ($sortBy === 'value') {
    $orderBy = "pipeline_value DESC, hot_count DESC";
}

// Overall stats
try {
    $overallStats = $pdo->query("
        SELECT
            COUNT(DISTINCT r.part_no) as total_products,
            SUM(CASE WHEN UPPER(l.lead_status) = 'HOT' THEN 1 ELSE 0 END) as hot_total,
            SUM(CASE WHEN UPPER(l.lead_status) = 'WARM' THEN 1 ELSE 0 END) as warm_total,
            SUM(CASE WHEN UPPER(l.lead_status) = 'COLD' THEN 1 ELSE 0 END) as cold_total,
            COALESCE(SUM(COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty), 0) as total_pipeline
        FROM crm_lead_requirements r
        JOIN crm_leads l ON r.lead_id = l.id
        WHERE UPPER(l.lead_status) IN ('HOT', 'WARM', 'COLD')
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $overallStats = ['total_products' => 0, 'hot_total' => 0, 'warm_total' => 0, 'cold_total' => 0, 'total_pipeline' => 0];
}

// Product-wise summary
$searchWhere = '';
$params = [];
if ($searchQuery !== '') {
    $searchWhere = " AND (r.product_name LIKE ? OR r.part_no LIKE ? OR pm.part_name LIKE ?)";
    $params = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
}

try {
    $stmt = $pdo->prepare("
        SELECT
            r.part_no,
            COALESCE(NULLIF(r.product_name, ''), pm.part_name, r.part_no) as product_name,
            SUM(CASE WHEN UPPER(l.lead_status) = 'HOT' THEN 1 ELSE 0 END) as hot_count,
            SUM(CASE WHEN UPPER(l.lead_status) = 'WARM' THEN 1 ELSE 0 END) as warm_count,
            SUM(CASE WHEN UPPER(l.lead_status) = 'COLD' THEN 1 ELSE 0 END) as cold_count,
            COUNT(*) as total_leads,
            COALESCE(SUM(r.estimated_qty), 0) as total_qty,
            COALESCE(SUM(COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty), 0) as pipeline_value
        FROM crm_lead_requirements r
        JOIN crm_leads l ON r.lead_id = l.id
        LEFT JOIN part_master pm ON r.part_no = pm.part_no
        WHERE $statusConditions $searchWhere
        GROUP BY r.part_no, product_name
        ORDER BY $orderBy
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

// Fetch all lead details grouped by part_no for expandable sections
$leadDetails = [];
try {
    $detailStmt = $pdo->prepare("
        SELECT
            r.part_no,
            l.id, l.lead_no, l.lead_status, l.company_name, l.contact_person,
            l.phone, l.next_followup_date, l.assigned_to,
            r.estimated_qty, r.target_price, r.our_price, r.priority
        FROM crm_leads l
        JOIN crm_lead_requirements r ON r.lead_id = l.id
        WHERE $statusConditions $searchWhere
        ORDER BY r.part_no, FIELD(UPPER(l.lead_status), 'HOT', 'WARM', 'COLD'), l.next_followup_date
    ");
    $detailStmt->execute($params);
    $allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allDetails as $row) {
        $leadDetails[$row['part_no']][] = $row;
    }
} catch (Exception $e) {
    $leadDetails = [];
}

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product-wise Lead Status</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .product-leads-page {
            padding: 20px;
            padding-top: calc(48px + 20px);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        .page-header h1 {
            margin: 0;
            color: var(--text, #2c3e50);
            font-size: 1.6em;
        }
        .page-header .subtitle {
            color: var(--muted-text, #7f8c8d);
            font-size: 0.9em;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--card, white);
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-top: 3px solid #3498db;
        }
        .stat-card.hot { border-top-color: #e74c3c; }
        .stat-card.warm { border-top-color: #f39c12; }
        .stat-card.cold { border-top-color: #3498db; }
        .stat-card.pipeline { border-top-color: #27ae60; }
        .stat-card .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--text, #2c3e50);
        }
        .stat-card .stat-label {
            font-size: 0.8em;
            color: var(--muted-text, #7f8c8d);
            margin-top: 4px;
        }

        /* Filters */
        .filters-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters-bar input[type="text"] {
            padding: 8px 14px;
            border: 1px solid var(--border, #d1d5db);
            border-radius: 6px;
            font-size: 0.9em;
            min-width: 220px;
            background: var(--card, white);
            color: var(--text, #333);
        }
        .filters-bar select {
            padding: 8px 14px;
            border: 1px solid var(--border, #d1d5db);
            border-radius: 6px;
            font-size: 0.9em;
            background: var(--card, white);
            color: var(--text, #333);
        }

        /* Product Cards */
        .product-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .product-card {
            background: var(--card, white);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .product-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            transition: background 0.2s;
            gap: 15px;
            flex-wrap: wrap;
        }
        .product-card-header:hover {
            background: var(--bg, #f8f9fa);
        }
        .product-info {
            flex: 1;
            min-width: 200px;
        }
        .product-name {
            font-size: 1.05em;
            font-weight: 600;
            color: var(--text, #2c3e50);
        }
        .product-partno {
            font-size: 0.8em;
            color: var(--muted-text, #7f8c8d);
            margin-top: 2px;
        }
        .lead-badges {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .lead-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .lead-badge.hot {
            background: #fde8e8;
            color: #e74c3c;
        }
        .lead-badge.warm {
            background: #fef3cd;
            color: #d68910;
        }
        .lead-badge.cold {
            background: #d6eaf8;
            color: #2980b9;
        }
        body.dark .lead-badge.hot { background: rgba(231,76,60,0.2); }
        body.dark .lead-badge.warm { background: rgba(243,156,18,0.2); }
        body.dark .lead-badge.cold { background: rgba(52,152,219,0.2); }
        .product-meta {
            display: flex;
            gap: 20px;
            align-items: center;
            font-size: 0.85em;
            color: var(--muted-text, #7f8c8d);
        }
        .product-meta strong {
            color: var(--text, #2c3e50);
        }
        .expand-arrow {
            font-size: 0.8em;
            color: var(--muted-text, #7f8c8d);
            transition: transform 0.2s;
        }
        .product-card.open .expand-arrow {
            transform: rotate(90deg);
        }

        /* Expandable Detail */
        .product-detail {
            display: none;
            border-top: 1px solid var(--border, #eee);
            padding: 0;
        }
        .product-card.open .product-detail {
            display: block;
        }
        .status-group {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border, #f0f0f0);
        }
        .status-group:last-child {
            border-bottom: none;
        }
        .status-group-header {
            font-weight: 600;
            font-size: 0.85em;
            margin-bottom: 8px;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-group-header.hot { background: #fde8e8; color: #e74c3c; }
        .status-group-header.warm { background: #fef3cd; color: #d68910; }
        .status-group-header.cold { background: #d6eaf8; color: #2980b9; }
        body.dark .status-group-header.hot { background: rgba(231,76,60,0.2); }
        body.dark .status-group-header.warm { background: rgba(243,156,18,0.2); }
        body.dark .status-group-header.cold { background: rgba(52,152,219,0.2); }

        .leads-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
        }
        .leads-table th {
            text-align: left;
            padding: 8px 10px;
            color: var(--muted-text, #7f8c8d);
            font-weight: 600;
            font-size: 0.9em;
            border-bottom: 1px solid var(--border, #eee);
        }
        .leads-table td {
            padding: 8px 10px;
            color: var(--text, #333);
            border-bottom: 1px solid var(--border, #f5f5f5);
        }
        .leads-table tr:hover td {
            background: var(--bg, #f8f9fa);
        }
        .leads-table a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        .leads-table a:hover {
            text-decoration: underline;
        }
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .priority-badge.high { background: #fde8e8; color: #e74c3c; }
        .priority-badge.medium { background: #fef3cd; color: #d68910; }
        .priority-badge.low { background: #d5f5e3; color: #27ae60; }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted-text, #999);
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .product-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .lead-badges {
                flex-wrap: wrap;
            }
            .product-meta {
                flex-wrap: wrap;
                gap: 10px;
            }
            .leads-table { font-size: 0.8em; }
        }
    </style>
</head>
<body>

<div class="content product-leads-page">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Product-wise Lead Status</h1>
            <div class="subtitle">Leads grouped by product - Hot, Warm & Cold pipeline</div>
        </div>
        <a href="/crm/index.php" class="btn btn-secondary">Back to CRM</a>
    </div>

    <!-- Summary Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($overallStats['total_products']) ?></div>
            <div class="stat-label">Products with Leads</div>
        </div>
        <div class="stat-card hot">
            <div class="stat-value"><?= number_format($overallStats['hot_total']) ?></div>
            <div class="stat-label">Hot Leads</div>
        </div>
        <div class="stat-card warm">
            <div class="stat-value"><?= number_format($overallStats['warm_total']) ?></div>
            <div class="stat-label">Warm Leads</div>
        </div>
        <div class="stat-card cold">
            <div class="stat-value"><?= number_format($overallStats['cold_total']) ?></div>
            <div class="stat-label">Cold Leads</div>
        </div>
        <div class="stat-card pipeline">
            <div class="stat-value"><?= $overallStats['total_pipeline'] >= 100000 ? '₹' . number_format($overallStats['total_pipeline'] / 100000, 1) . 'L' : '₹' . number_format($overallStats['total_pipeline'], 0) ?></div>
            <div class="stat-label">Pipeline Value</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filters-bar">
        <input type="text" name="search" placeholder="Search product name or part no..." value="<?= htmlspecialchars($searchQuery) ?>">
        <select name="status" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="hot" <?= $statusFilter === 'hot' ? 'selected' : '' ?>>Hot Only</option>
            <option value="warm" <?= $statusFilter === 'warm' ? 'selected' : '' ?>>Warm Only</option>
            <option value="cold" <?= $statusFilter === 'cold' ? 'selected' : '' ?>>Cold Only</option>
        </select>
        <select name="sort" onchange="this.form.submit()">
            <option value="hot" <?= $sortBy === 'hot' ? 'selected' : '' ?>>Sort: Most Hot Leads</option>
            <option value="total" <?= $sortBy === 'total' ? 'selected' : '' ?>>Sort: Most Total Leads</option>
            <option value="value" <?= $sortBy === 'value' ? 'selected' : '' ?>>Sort: Highest Value</option>
        </select>
        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">Filter</button>
        <?php if ($searchQuery || $statusFilter !== 'all' || $sortBy !== 'hot'): ?>
            <a href="product_leads.php" class="btn btn-secondary" style="padding: 8px 16px;">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Product Cards -->
    <?php if (empty($products)): ?>
        <div class="no-data">
            No products found with active leads.
            <?php if ($searchQuery || $statusFilter !== 'all'): ?>
                <br><a href="product_leads.php">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $prod):
            $partNo = $prod['part_no'];
            $details = $leadDetails[$partNo] ?? [];
            $hotLeads = array_filter($details, fn($d) => strtoupper($d['lead_status']) === 'HOT');
            $warmLeads = array_filter($details, fn($d) => strtoupper($d['lead_status']) === 'WARM');
            $coldLeads = array_filter($details, fn($d) => strtoupper($d['lead_status']) === 'COLD');
        ?>
        <div class="product-card" id="card-<?= htmlspecialchars($partNo) ?>">
            <div class="product-card-header" onclick="toggleCard('card-<?= htmlspecialchars($partNo) ?>')">
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($prod['product_name']) ?></div>
                    <div class="product-partno"><?= htmlspecialchars($partNo) ?></div>
                </div>
                <div class="lead-badges">
                    <?php if ($prod['hot_count'] > 0): ?>
                    <span class="lead-badge hot"><?= $prod['hot_count'] ?> Hot</span>
                    <?php endif; ?>
                    <?php if ($prod['warm_count'] > 0): ?>
                    <span class="lead-badge warm"><?= $prod['warm_count'] ?> Warm</span>
                    <?php endif; ?>
                    <?php if ($prod['cold_count'] > 0): ?>
                    <span class="lead-badge cold"><?= $prod['cold_count'] ?> Cold</span>
                    <?php endif; ?>
                </div>
                <div class="product-meta">
                    <span>Qty: <strong><?= number_format($prod['total_qty']) ?></strong></span>
                    <span>Value: <strong><?= $prod['pipeline_value'] >= 100000 ? '₹' . number_format($prod['pipeline_value'] / 100000, 1) . 'L' : '₹' . number_format($prod['pipeline_value'], 0) ?></strong></span>
                </div>
                <span class="expand-arrow">&#9654;</span>
            </div>
            <div class="product-detail">
                <?php if (!empty($hotLeads)): ?>
                <div class="status-group">
                    <div class="status-group-header hot">Hot Leads (<?= count($hotLeads) ?>)</div>
                    <?php echo renderLeadsTable($hotLeads); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($warmLeads)): ?>
                <div class="status-group">
                    <div class="status-group-header warm">Warm Leads (<?= count($warmLeads) ?>)</div>
                    <?php echo renderLeadsTable($warmLeads); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($coldLeads)): ?>
                <div class="status-group">
                    <div class="status-group-header cold">Cold Leads (<?= count($coldLeads) ?>)</div>
                    <?php echo renderLeadsTable($coldLeads); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleCard(id) {
    document.getElementById(id).classList.toggle('open');
}
</script>

</body>
</html>

<?php
function renderLeadsTable(array $leads): string {
    $html = '<table class="leads-table">';
    $html .= '<thead><tr>';
    $html .= '<th>Lead #</th><th>Company</th><th>Contact</th><th>Qty</th><th>Target Price</th><th>Our Price</th><th>Priority</th><th>Follow-up</th><th>Assigned</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($leads as $lead) {
        $priorityClass = strtolower($lead['priority'] ?? 'medium');
        $followup = $lead['next_followup_date'] ? date('d M Y', strtotime($lead['next_followup_date'])) : '-';
        $isOverdue = $lead['next_followup_date'] && $lead['next_followup_date'] < date('Y-m-d');
        $html .= '<tr>';
        $html .= '<td><a href="/crm/view.php?id=' . (int)$lead['id'] . '">' . htmlspecialchars($lead['lead_no']) . '</a></td>';
        $html .= '<td>' . htmlspecialchars($lead['company_name'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['contact_person'] ?? '-') . '</td>';
        $html .= '<td>' . number_format($lead['estimated_qty'] ?? 0) . '</td>';
        $html .= '<td>' . ($lead['target_price'] ? '₹' . number_format($lead['target_price'], 0) : '-') . '</td>';
        $html .= '<td>' . ($lead['our_price'] ? '₹' . number_format($lead['our_price'], 0) : '-') . '</td>';
        $html .= '<td><span class="priority-badge ' . $priorityClass . '">' . ucfirst($priorityClass) . '</span></td>';
        $html .= '<td' . ($isOverdue ? ' style="color:#e74c3c;font-weight:600;"' : '') . '>' . $followup . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['assigned_to'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}
?>
