<?php
/**
 * Sales Strategy Dashboard - Main Hub
 * Executive view of key sales metrics, risks, and action items
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

$today = date('Y-m-d');
$thisYearStart = date('Y-01-01');
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');

// ============ KPI DATA ============

// Revenue YTD
$salesYTD = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
    ");
    $stmt->execute([$thisYearStart]);
    $salesYTD = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// Active vs Total Customers
$totalCustomers = 0;
$activeCustomers = 0;
try {
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $activeCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Active'")->fetchColumn();
} catch (Exception $e) {}

// Lead Conversion Rate
$totalLeads = 0;
$convertedLeads = 0;
try {
    $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
    $convertedLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'converted'")->fetchColumn();
} catch (Exception $e) {}
$leadConversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

// Quote-to-Order Rate
$totalQuotes = 0;
$convertedQuotes = 0;
try {
    $totalQuotes = (int)$pdo->query("SELECT COUNT(*) FROM quote_master")->fetchColumn();
    $convertedQuotes = (int)$pdo->query("SELECT COUNT(DISTINCT qm.id) FROM quote_master qm INNER JOIN sales_orders so ON so.linked_quote_id = qm.id")->fetchColumn();
} catch (Exception $e) {}
$quoteToOrderRate = $totalQuotes > 0 ? round(($convertedQuotes / $totalQuotes) * 100, 1) : 0;

// Average Order Value
$avgOrderValue = 0;
try {
    $stmt = $pdo->query("
        SELECT COALESCE(AVG(order_total), 0) FROM (
            SELECT so.linked_quote_id, SUM(qi.total_amount) as order_total
            FROM sales_orders so
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE so.linked_quote_id IS NOT NULL
            GROUP BY so.linked_quote_id
        ) t WHERE order_total > 0
    ");
    $avgOrderValue = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// ============ CUSTOMER CONCENTRATION ============
$topCustomers = [];
try {
    $topCustomers = $pdo->query("
        SELECT c.company_name, COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM customers c
        INNER JOIN sales_orders so ON so.customer_id = c.id
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        GROUP BY c.id, c.company_name
        HAVING revenue > 0
        ORDER BY revenue DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$totalCustomerRevenue = array_sum(array_column($topCustomers, 'revenue'));
$revenueAtRisk = 0;
$topCustomerData = [];
foreach ($topCustomers as $tc) {
    $pct = $totalCustomerRevenue > 0 ? round(($tc['revenue'] / $totalCustomerRevenue) * 100, 1) : 0;
    if ($pct > 30) $revenueAtRisk += $tc['revenue'];
    $topCustomerData[] = ['name' => $tc['company_name'], 'revenue' => $tc['revenue'], 'pct' => $pct];
}

// ============ MONTHLY REVENUE TREND (12 months) ============
$monthlyRevenue = [];
for ($i = 11; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t', strtotime("-$i months"));
    $mn = date('M Y', strtotime("-$i months"));
    $rev = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(qi.total_amount), 0)
            FROM invoice_master im
            LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$ms, $me]);
        $rev = (float)$stmt->fetchColumn();
    } catch (Exception $e) {}
    $monthlyRevenue[] = ['month' => $mn, 'revenue' => $rev];
}

// ============ LEAD PIPELINE ============
$pipeline = ['hot' => 0, 'warm' => 0, 'cold' => 0, 'converted' => 0, 'lost' => 0];
try {
    $rows = $pdo->query("SELECT lead_status, COUNT(*) as cnt FROM crm_leads GROUP BY lead_status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (isset($pipeline[$r['lead_status']])) $pipeline[$r['lead_status']] = (int)$r['cnt'];
    }
} catch (Exception $e) {}

// ============ GEOGRAPHIC TOP 10 ============
$geoTop = [];
try {
    $geoTop = $pdo->query("
        SELECT c.state,
            COUNT(DISTINCT c.id) as customer_count,
            COUNT(DISTINCT so.so_no) as order_count,
            COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM customers c
        LEFT JOIN sales_orders so ON so.customer_id = c.id
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE c.state IS NOT NULL AND c.state != ''
        GROUP BY c.state
        ORDER BY revenue DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ ACTION ITEMS ============
$actions = [];

// Hot leads not followed up in 7+ days
try {
    $cnt = (int)$pdo->query("
        SELECT COUNT(*) FROM crm_leads
        WHERE lead_status = 'hot'
        AND (next_followup_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR next_followup_date IS NULL)
    ")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Hot leads overdue for follow-up", 'count' => $cnt, 'severity' => 'danger', 'link' => '/strategy/lead_tracker.php?overdue=yes'];
} catch (Exception $e) {}

// Quotes in draft for 14+ days
try {
    $cnt = (int)$pdo->query("
        SELECT COUNT(*) FROM quote_master
        WHERE status = 'draft' AND created_at < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    ")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Quotes stuck in draft for 14+ days", 'count' => $cnt, 'severity' => 'warning', 'link' => '/quotes/index.php'];
} catch (Exception $e) {}

// Dormant customers (no orders in 90 days)
try {
    $cnt = (int)$pdo->query("
        SELECT COUNT(*) FROM customers c
        WHERE c.status = 'Active'
        AND c.id NOT IN (
            SELECT DISTINCT customer_id FROM sales_orders
            WHERE sales_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            AND customer_id IS NOT NULL
        )
    ")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Dormant customers (no orders in 90 days)", 'count' => $cnt, 'severity' => 'warning', 'link' => '/strategy/dormant_customers.php'];
} catch (Exception $e) {}

// Products with zero pricing
try {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM part_master WHERE (rate = 0 OR rate IS NULL) AND status = 'active'")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Products with Rs. 0 pricing", 'count' => $cnt, 'severity' => 'danger', 'link' => '/strategy/product_analysis.php'];
} catch (Exception $e) {}

// Low stock items
try {
    $cnt = (int)$pdo->query("
        SELECT COUNT(*) FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
        LEFT JOIN part_min_stock pms ON pms.part_no = i.part_no
        WHERE pms.min_stock_qty IS NOT NULL AND i.qty <= pms.min_stock_qty
    ")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Items below minimum stock level", 'count' => $cnt, 'severity' => 'warning', 'link' => '/inventory/index.php'];
} catch (Exception $e) {}

// Leads missing source
try {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_source IS NULL OR lead_source = ''")->fetchColumn();
    if ($cnt > 0) $actions[] = ['label' => "Leads with no source recorded", 'count' => $cnt, 'severity' => 'info', 'link' => '/strategy/data_quality.php'];
} catch (Exception $e) {}

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Strategy Dashboard - ERP System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .strategy-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 3px solid #10b981;
        }
        .strategy-header h1 { margin: 0; font-size: 1.6em; color: var(--text); }
        .strategy-header .subtitle { font-size: 0.85em; color: var(--muted-text); }

        .kpi-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px; margin-bottom: 25px;
        }
        .kpi-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px; position: relative; overflow: hidden;
        }
        .kpi-card .kpi-stripe { position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .kpi-card .kpi-label { font-size: 0.75em; color: var(--muted-text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .kpi-card .kpi-value { font-size: 1.7em; font-weight: 700; color: var(--text); }
        .kpi-card .kpi-sub { font-size: 0.78em; color: var(--muted-text); margin-top: 4px; }

        .dashboard-grid {
            display: grid; grid-template-columns: 3fr 2fr;
            gap: 20px; margin-bottom: 25px;
        }
        .dashboard-grid.equal { grid-template-columns: 1fr 2fr; }
        @media (max-width: 900px) {
            .dashboard-grid, .dashboard-grid.equal { grid-template-columns: 1fr; }
        }

        .chart-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 20px;
        }
        .chart-card h3 { margin: 0 0 15px 0; font-size: 1em; color: var(--text); }
        .chart-container { position: relative; height: 300px; }

        .section-title { font-size: 1.1em; font-weight: 600; color: var(--text); margin: 25px 0 15px 0; }

        /* Lead Funnel */
        .funnel { display: flex; flex-direction: column; gap: 6px; padding: 10px 0; }
        .funnel-step {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 15px; border-radius: 8px;
            background: var(--bg); transition: transform 0.2s;
        }
        .funnel-step:hover { transform: translateX(5px); }
        .funnel-step .funnel-bar {
            height: 28px; border-radius: 4px; min-width: 20px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 0.85em;
        }
        .funnel-step .funnel-label { font-size: 0.85em; color: var(--muted-text); min-width: 80px; }
        .funnel-hot .funnel-bar { background: #ef4444; }
        .funnel-warm .funnel-bar { background: #f59e0b; }
        .funnel-cold .funnel-bar { background: #6b7280; }
        .funnel-converted .funnel-bar { background: #10b981; }
        .funnel-lost .funnel-bar { background: #374151; }

        /* Action Items */
        .action-list { display: flex; flex-direction: column; gap: 8px; }
        .action-item {
            display: flex; align-items: center; gap: 15px;
            padding: 12px 15px; background: var(--card); border-radius: 8px;
            border: 1px solid var(--border); border-left: 4px solid transparent;
            text-decoration: none; color: inherit; transition: transform 0.2s;
        }
        .action-item:hover { transform: translateX(5px); }
        .action-item.danger { border-left-color: #ef4444; }
        .action-item.warning { border-left-color: #f59e0b; }
        .action-item.info { border-left-color: #3b82f6; }
        .action-item .action-count {
            background: var(--bg); border-radius: 50%; width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9em; color: var(--text); flex-shrink: 0;
        }
        .action-item .action-label { flex: 1; font-size: 0.9em; color: var(--text); }
        .action-item .action-arrow { color: var(--muted-text); font-size: 1.2em; }

        /* Geo Table */
        .geo-table { width: 100%; border-collapse: collapse; }
        .geo-table th { background: var(--table-header-bg, #1e293b); color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82em; text-transform: uppercase; }
        .geo-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.88em; color: var(--text); }
        .geo-table tr:hover { background: var(--row-hover, var(--bg)); }
        .geo-untapped { background: rgba(239, 68, 68, 0.08) !important; }

        /* Navigation Cards */
        .nav-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin-top: 25px;
        }
        .nav-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 20px; text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s; display: block;
        }
        .nav-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-card .nav-icon { font-size: 1.8em; margin-bottom: 8px; }
        .nav-card .nav-title { font-weight: 600; font-size: 0.95em; color: var(--text); margin-bottom: 4px; }
        .nav-card .nav-desc { font-size: 0.78em; color: var(--muted-text); }

        .risk-badge {
            display: inline-block; padding: 3px 8px; border-radius: 4px;
            font-size: 0.75em; font-weight: 600;
        }
        .risk-badge.high { background: #fef2f2; color: #dc2626; }
        .risk-badge.medium { background: #fffbeb; color: #d97706; }
        .risk-badge.low { background: #f0fdf4; color: #16a34a; }
    </style>
</head>
<body>
<div class="content">

    <div class="strategy-header">
        <div>
            <h1>Sales Strategy Dashboard</h1>
            <div class="subtitle">Data-driven insights to improve sales and revenue</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #6366f1;"></div>
            <div class="kpi-label">Revenue YTD</div>
            <div class="kpi-value"><?= $salesYTD >= 100000 ? '&#8377;' . number_format($salesYTD / 100000, 1) . 'L' : '&#8377;' . number_format($salesYTD) ?></div>
            <div class="kpi-sub"><?= date('Y') ?> Year to Date</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #10b981;"></div>
            <div class="kpi-label">Active Customers</div>
            <div class="kpi-value"><?= $activeCustomers ?> <span style="font-size:0.5em; color:var(--muted-text)">/ <?= $totalCustomers ?></span></div>
            <div class="kpi-sub"><?= $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100) : 0 ?>% active rate</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #f59e0b;"></div>
            <div class="kpi-label">Lead Conversion</div>
            <div class="kpi-value"><?= $leadConversionRate ?>%</div>
            <div class="kpi-sub"><?= $convertedLeads ?> of <?= $totalLeads ?> leads converted</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #3b82f6;"></div>
            <div class="kpi-label">Quote-to-Order</div>
            <div class="kpi-value"><?= $quoteToOrderRate ?>%</div>
            <div class="kpi-sub"><?= $convertedQuotes ?> of <?= $totalQuotes ?> quotes converted</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #8b5cf6;"></div>
            <div class="kpi-label">Avg Order Value</div>
            <div class="kpi-value"><?= $avgOrderValue >= 100000 ? '&#8377;' . number_format($avgOrderValue / 100000, 1) . 'L' : '&#8377;' . number_format($avgOrderValue) ?></div>
            <div class="kpi-sub">Per sales order</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: <?= $revenueAtRisk > 0 ? '#ef4444' : '#10b981' ?>;"></div>
            <div class="kpi-label">Revenue at Risk</div>
            <div class="kpi-value"><?= $revenueAtRisk >= 100000 ? '&#8377;' . number_format($revenueAtRisk / 100000, 1) . 'L' : '&#8377;' . number_format($revenueAtRisk) ?></div>
            <div class="kpi-sub">
                <?php if ($revenueAtRisk > 0): ?>
                    <span class="risk-badge high">High concentration risk</span>
                <?php else: ?>
                    <span class="risk-badge low">Healthy diversification</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Row 1: Revenue Trend + Customer Concentration -->
    <div class="dashboard-grid">
        <div class="chart-card">
            <h3>Revenue Trend (12 Months)</h3>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>Customer Revenue Concentration</h3>
            <?php if (!empty($topCustomerData)): ?>
                <div class="chart-container" style="height: 260px;">
                    <canvas id="concentrationChart"></canvas>
                </div>
                <?php if ($revenueAtRisk > 0): ?>
                <div style="margin-top: 10px; padding: 8px 12px; background: #fef2f2; border-radius: 6px; font-size: 0.82em; color: #dc2626;">
                    Warning: Customer(s) exceeding 30% revenue share detected. Diversify urgently.
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p style="color: var(--muted-text); text-align: center; padding: 40px 0;">No revenue data yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 2: Lead Pipeline + Geographic -->
    <div class="dashboard-grid equal">
        <div class="chart-card">
            <h3>Lead Pipeline</h3>
            <?php
            $maxFunnel = max(1, max($pipeline));
            ?>
            <div class="funnel">
                <?php foreach ([
                    'hot' => ['label' => 'Hot', 'class' => 'funnel-hot'],
                    'warm' => ['label' => 'Warm', 'class' => 'funnel-warm'],
                    'cold' => ['label' => 'Cold', 'class' => 'funnel-cold'],
                    'converted' => ['label' => 'Converted', 'class' => 'funnel-converted'],
                    'lost' => ['label' => 'Lost', 'class' => 'funnel-lost'],
                ] as $key => $cfg): ?>
                <div class="funnel-step <?= $cfg['class'] ?>">
                    <div class="funnel-label"><?= $cfg['label'] ?></div>
                    <div class="funnel-bar" style="width: <?= max(15, ($pipeline[$key] / $maxFunnel) * 100) ?>%;">
                        <?= $pipeline[$key] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 12px; font-size: 0.82em; color: var(--muted-text);">
                Total: <?= array_sum($pipeline) ?> leads | Conversion rate: <?= $leadConversionRate ?>%
            </div>
        </div>
        <div class="chart-card">
            <h3>Top States by Revenue <a href="/strategy/geographic.php" style="font-size:0.8em; color: var(--primary); text-decoration:none; float:right;">View All &rarr;</a></h3>
            <div style="overflow-x: auto;">
                <table class="geo-table">
                    <thead>
                        <tr><th>State</th><th>Customers</th><th>Orders</th><th>Revenue</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($geoTop)): ?>
                        <tr><td colspan="4" style="text-align:center; color: var(--muted-text);">No data</td></tr>
                        <?php else: ?>
                        <?php foreach ($geoTop as $g): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($g['state']) ?></strong></td>
                            <td><?= $g['customer_count'] ?></td>
                            <td><?= $g['order_count'] ?></td>
                            <td><?= $g['revenue'] >= 100000 ? '&#8377;' . number_format($g['revenue'] / 100000, 1) . 'L' : '&#8377;' . number_format($g['revenue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Action Items -->
    <?php if (!empty($actions)): ?>
    <div class="section-title">Action Items Requiring Attention</div>
    <div class="action-list">
        <?php foreach ($actions as $a): ?>
        <a href="<?= $a['link'] ?>" class="action-item <?= $a['severity'] ?>">
            <div class="action-count"><?= $a['count'] ?></div>
            <div class="action-label"><?= htmlspecialchars($a['label']) ?></div>
            <div class="action-arrow">&rarr;</div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Navigation to Sub-pages -->
    <div class="section-title">Deep Dive Analysis</div>
    <div class="nav-grid">
        <a href="/strategy/dormant_customers.php" class="nav-card">
            <div class="nav-icon">&#128564;</div>
            <div class="nav-title">Dormant Customers</div>
            <div class="nav-desc">Identify and re-engage inactive customers</div>
        </a>
        <a href="/strategy/lead_tracker.php" class="nav-card">
            <div class="nav-icon">&#127919;</div>
            <div class="nav-title">Hot Lead Tracker</div>
            <div class="nav-desc">Track and prioritize high-value leads</div>
        </a>
        <a href="/strategy/product_analysis.php" class="nav-card">
            <div class="nav-icon">&#128230;</div>
            <div class="nav-title">Product Analysis</div>
            <div class="nav-desc">Revenue by product, cross-sell opportunities</div>
        </a>
        <a href="/strategy/data_quality.php" class="nav-card">
            <div class="nav-icon">&#128202;</div>
            <div class="nav-title">Data Quality</div>
            <div class="nav-desc">Audit and fix data gaps across modules</div>
        </a>
        <a href="/strategy/geographic.php" class="nav-card">
            <div class="nav-icon">&#127758;</div>
            <div class="nav-title">Geographic Analysis</div>
            <div class="nav-desc">State-wise performance and expansion potential</div>
        </a>
    </div>

</div>

<script>
// Revenue Trend Line Chart
const monthlyData = <?= json_encode($monthlyRevenue) ?>;
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
            label: 'Revenue',
            data: monthlyData.map(d => d.revenue),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 3, fill: true, tension: 0.4,
            pointBackgroundColor: '#6366f1', pointBorderColor: '#fff',
            pointBorderWidth: 2, pointRadius: 5
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) {
                        if (v >= 100000) return '\u20B9' + (v / 100000).toFixed(1) + 'L';
                        return '\u20B9' + v.toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});

// Customer Concentration Bar Chart
<?php if (!empty($topCustomerData)): ?>
const custData = <?= json_encode($topCustomerData) ?>;
new Chart(document.getElementById('concentrationChart'), {
    type: 'bar',
    data: {
        labels: custData.map(d => d.name.length > 20 ? d.name.substring(0, 20) + '...' : d.name),
        datasets: [{
            label: '% of Revenue',
            data: custData.map(d => d.pct),
            backgroundColor: custData.map(d => d.pct > 30 ? '#ef4444' : '#6366f1'),
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const item = custData[ctx.dataIndex];
                        const rev = item.revenue >= 100000 ? '\u20B9' + (item.revenue / 100000).toFixed(1) + 'L' : '\u20B9' + item.revenue.toLocaleString('en-IN');
                        return ctx.raw.toFixed(1) + '% (' + rev + ')';
                    }
                }
            }
        },
        scales: {
            x: { max: 100, ticks: { callback: function(v) { return v + '%'; } } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>