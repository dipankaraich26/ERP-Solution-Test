<?php
/**
 * Dormant Customer Analysis
 * Identify and re-engage inactive customers
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Filters
$filter_state = $_GET['state'] ?? '';
$filter_industry = $_GET['industry'] ?? '';
$filter_days = (int)($_GET['days'] ?? 90);
if (!in_array($filter_days, [30, 60, 90, 180, 365])) $filter_days = 90;
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get filter options
$stateOptions = [];
$industryOptions = [];
try {
    $stateOptions = $pdo->query("SELECT DISTINCT state FROM customers WHERE state IS NOT NULL AND state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
    $industryOptions = $pdo->query("SELECT DISTINCT industry FROM customers WHERE industry IS NOT NULL AND industry != '' ORDER BY industry")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Build WHERE clause
$where = ["c.status = 'Active'"];
$params = [];

// Dormancy: no orders in last N days, or never ordered
$dormancyDate = date('Y-m-d', strtotime("-{$filter_days} days"));
$where[] = "(last_order.last_order_date < ? OR last_order.last_order_date IS NULL)";
$params[] = $dormancyDate;

if ($filter_state) { $where[] = "c.state = ?"; $params[] = $filter_state; }
if ($filter_industry) { $where[] = "c.industry = ?"; $params[] = $filter_industry; }
if ($search) {
    $where[] = "(c.company_name LIKE ? OR c.customer_name LIKE ? OR c.city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Count query
$totalCount = 0;
try {
    $countSql = "
        SELECT COUNT(*) FROM customers c
        LEFT JOIN (SELECT customer_id, MAX(sales_date) as last_order_date FROM sales_orders GROUP BY customer_id) last_order
            ON last_order.customer_id = c.id
        $whereClause
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$totalPages = max(1, ceil($totalCount / $per_page));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per_page;

// Main query
$customers = [];
try {
    $sql = "
        SELECT c.id, c.customer_id, c.company_name, c.customer_name, c.city, c.state,
               c.industry, c.email, c.contact,
               last_order.last_order_date,
               last_quote.last_quote_date,
               COALESCE(total_rev.revenue, 0) as total_revenue,
               CASE
                   WHEN last_order.last_order_date IS NULL THEN -1
                   ELSE DATEDIFF(CURDATE(), last_order.last_order_date)
               END as days_dormant
        FROM customers c
        LEFT JOIN (
            SELECT customer_id, MAX(sales_date) as last_order_date
            FROM sales_orders GROUP BY customer_id
        ) last_order ON last_order.customer_id = c.id
        LEFT JOIN (
            SELECT customer_id, MAX(quote_date) as last_quote_date
            FROM quote_master GROUP BY customer_id
        ) last_quote ON last_quote.customer_id = c.id
        LEFT JOIN (
            SELECT so.customer_id, COALESCE(SUM(qi.total_amount), 0) as revenue
            FROM sales_orders so
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            GROUP BY so.customer_id
        ) total_rev ON total_rev.customer_id = c.id
        $whereClause
        ORDER BY
            CASE WHEN last_order.last_order_date IS NULL THEN 1 ELSE 0 END,
            total_rev.revenue DESC
        LIMIT ? OFFSET ?
    ";
    $allParams = array_merge($params, [$per_page, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// KPI Summary
$neverOrdered = 0;
$avgDaysDormant = 0;
$potentialRevenue = 0;
try {
    $neverOrdered = (int)$pdo->query("
        SELECT COUNT(*) FROM customers c
        WHERE c.status = 'Active'
        AND c.id NOT IN (SELECT DISTINCT customer_id FROM sales_orders WHERE customer_id IS NOT NULL)
    ")->fetchColumn();

    $avgStmt = $pdo->query("
        SELECT AVG(DATEDIFF(CURDATE(), last_order.last_order_date)) as avg_days
        FROM customers c
        JOIN (SELECT customer_id, MAX(sales_date) as last_order_date FROM sales_orders GROUP BY customer_id) last_order
            ON last_order.customer_id = c.id
        WHERE c.status = 'Active' AND last_order.last_order_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ");
    $avgDaysDormant = round((float)$avgStmt->fetchColumn());

    // Average order value for potential revenue
    $avgOV = (float)$pdo->query("
        SELECT COALESCE(AVG(order_total), 0) FROM (
            SELECT so.linked_quote_id, SUM(qi.total_amount) as order_total
            FROM sales_orders so
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE so.linked_quote_id IS NOT NULL
            GROUP BY so.linked_quote_id
        ) t WHERE order_total > 0
    ")->fetchColumn();
    $potentialRevenue = $totalCount * $avgOV;
} catch (Exception $e) {}

// Build query string for pagination
$queryParams = [];
if ($filter_state) $queryParams['state'] = $filter_state;
if ($filter_industry) $queryParams['industry'] = $filter_industry;
if ($filter_days != 90) $queryParams['days'] = $filter_days;
if ($search) $queryParams['search'] = $search;
$queryString = $queryParams ? '&' . http_build_query($queryParams) : '';

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dormant Customer Analysis - ERP System</title>
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

        .data-table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 10px; overflow: hidden; border: 1px solid var(--border); }
        .data-table th { background: var(--table-header-bg, #1e293b); color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82em; text-transform: uppercase; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.88em; color: var(--text); }
        .data-table tr:hover { background: var(--row-hover, var(--bg)); }

        .row-never { background: rgba(239, 68, 68, 0.06) !important; }
        .dormant-critical { color: #dc2626; font-weight: 600; }
        .dormant-high { color: #ea580c; font-weight: 600; }
        .dormant-medium { color: #d97706; }
        .dormant-never { color: #dc2626; font-weight: 700; }

        body.dark .row-never { background: rgba(239, 68, 68, 0.1) !important; }
        body.mid .row-never { background: rgba(239, 68, 68, 0.08) !important; }

        .btn-action {
            display: inline-block; padding: 4px 10px; font-size: 0.78em;
            border-radius: 5px; text-decoration: none; font-weight: 600; margin: 1px;
        }
        .btn-action.primary { background: var(--primary, #2563eb); color: white; }
        .btn-action.success { background: #10b981; color: white; }

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
        .empty-msg { text-align: center; padding: 40px; color: var(--muted-text); }
    </style>
</head>
<body>
<div class="content">

    <a href="/strategy/index.php" class="back-link">&larr; Back to Strategy Dashboard</a>

    <div class="strategy-header">
        <div>
            <h1>Dormant Customer Analysis</h1>
            <div class="subtitle">Identify inactive customers for re-engagement</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #ef4444;"></div>
            <div class="kpi-label">Total Dormant</div>
            <div class="kpi-value"><?= $totalCount ?></div>
            <div class="kpi-sub">No orders in <?= $filter_days ?>+ days</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #dc2626;"></div>
            <div class="kpi-label">Never Ordered</div>
            <div class="kpi-value"><?= $neverOrdered ?></div>
            <div class="kpi-sub">Zero orders ever placed</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #f59e0b;"></div>
            <div class="kpi-label">Avg Days Dormant</div>
            <div class="kpi-value"><?= $avgDaysDormant ?></div>
            <div class="kpi-sub">Among customers with past orders</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #10b981;"></div>
            <div class="kpi-label">Potential Revenue</div>
            <div class="kpi-value"><?= $potentialRevenue >= 100000 ? '&#8377;' . number_format($potentialRevenue / 100000, 1) . 'L' : '&#8377;' . number_format($potentialRevenue) ?></div>
            <div class="kpi-sub">If re-engaged at avg order value</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
        <select name="state">
            <option value="">All States</option>
            <?php foreach ($stateOptions as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $filter_state === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="industry">
            <option value="">All Industries</option>
            <?php foreach ($industryOptions as $ind): ?>
            <option value="<?= htmlspecialchars($ind) ?>" <?= $filter_industry === $ind ? 'selected' : '' ?>><?= htmlspecialchars($ind) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="days">
            <?php foreach ([30, 60, 90, 180, 365] as $d): ?>
            <option value="<?= $d ?>" <?= $filter_days == $d ? 'selected' : '' ?>><?= $d ?> days</option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Search company, name, city..." value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="dormant_customers.php" class="btn btn-secondary" style="text-decoration:none; display:inline-block;">Clear</a>
    </form>

    <!-- Data Table -->
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Company</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Industry</th>
                    <th>Last Order</th>
                    <th>Last Quote</th>
                    <th>Total Revenue</th>
                    <th>Days Dormant</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="10" class="empty-msg">No dormant customers found with current filters</td></tr>
                <?php else: ?>
                <?php foreach ($customers as $c):
                    $isNeverOrdered = $c['days_dormant'] == -1;
                    $rowClass = $isNeverOrdered ? 'row-never' : '';
                    $dormantClass = '';
                    if ($isNeverOrdered) $dormantClass = 'dormant-never';
                    elseif ($c['days_dormant'] > 180) $dormantClass = 'dormant-critical';
                    elseif ($c['days_dormant'] > 90) $dormantClass = 'dormant-high';
                    else $dormantClass = 'dormant-medium';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><strong><?= htmlspecialchars($c['customer_id']) ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($c['company_name'] ?: $c['customer_name']) ?></strong>
                        <?php if ($c['company_name'] && $c['customer_name']): ?>
                        <br><span style="font-size:0.82em; color:var(--muted-text);"><?= htmlspecialchars($c['customer_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['city'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($c['state'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($c['industry'] ?: '-') ?></td>
                    <td><?= $c['last_order_date'] ? date('d M Y', strtotime($c['last_order_date'])) : '<span style="color:#dc2626;">Never</span>' ?></td>
                    <td><?= $c['last_quote_date'] ? date('d M Y', strtotime($c['last_quote_date'])) : '-' ?></td>
                    <td><?= $c['total_revenue'] > 0 ? '&#8377;' . number_format($c['total_revenue']) : '-' ?></td>
                    <td class="<?= $dormantClass ?>">
                        <?= $isNeverOrdered ? 'Never ordered' : $c['days_dormant'] . ' days' ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <a href="/crm/add.php?company=<?= urlencode($c['company_name'] ?: $c['customer_name']) ?>" class="btn-action primary">Create Lead</a>
                        <a href="/quotes/add.php?customer_id=<?= $c['customer_id'] ?>" class="btn-action success">Send Quote</a>
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
        Showing <?= min($offset + 1, $totalCount) ?>-<?= min($offset + $per_page, $totalCount) ?> of <?= $totalCount ?> dormant customers
    </div>

</div>
</body>
</html>