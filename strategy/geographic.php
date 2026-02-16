<?php
/**
 * Geographic Expansion Analysis
 * State-wise performance, untapped markets, city rankings
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

// ============ ALL STATES DATA ============
$stateData = [];
try {
    $stateData = $pdo->query("
        SELECT s.state_name,
            COALESCE(cust.cnt, 0) as customer_count,
            COALESCE(ord.cnt, 0) as order_count,
            COALESCE(ord.revenue, 0) as revenue,
            COALESCE(ld.cnt, 0) as lead_count
        FROM states s
        LEFT JOIN (SELECT state, COUNT(*) as cnt FROM customers WHERE status='Active' GROUP BY state) cust
            ON cust.state = s.state_name
        LEFT JOIN (
            SELECT c.state, COUNT(DISTINCT so.so_no) as cnt,
                   COALESCE(SUM(qi.total_amount), 0) as revenue
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            GROUP BY c.state
        ) ord ON ord.state = s.state_name
        LEFT JOIN (SELECT state, COUNT(*) as cnt FROM crm_leads GROUP BY state) ld
            ON ld.state = s.state_name
        WHERE s.is_active = 1
        ORDER BY revenue DESC, customer_count DESC, s.state_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ TOP 15 CITIES ============
$topCities = [];
try {
    $topCities = $pdo->query("
        SELECT c.city, c.state, COUNT(DISTINCT c.id) as customer_count,
               COUNT(DISTINCT so.so_no) as order_count,
               COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM customers c
        LEFT JOIN sales_orders so ON so.customer_id = c.id
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE c.city IS NOT NULL AND c.city != '' AND c.status = 'Active'
        GROUP BY c.city, c.state
        ORDER BY revenue DESC
        LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ KPI CALCULATIONS ============
$totalStates = count($stateData);
$activeStates = count(array_filter($stateData, fn($s) => $s['customer_count'] > 0));
$untappedStates = count(array_filter($stateData, fn($s) => $s['customer_count'] == 0));
$prospectStates = count(array_filter($stateData, fn($s) => $s['customer_count'] == 0 && $s['lead_count'] > 0));

$topState = !empty($stateData) ? $stateData[0]['state_name'] : '-';
$topStateRevenue = !empty($stateData) ? (float)$stateData[0]['revenue'] : 0;

$top10States = array_filter(array_slice($stateData, 0, 10), fn($s) => $s['revenue'] > 0 || $s['customer_count'] > 0);
$statesWithCustomers = array_filter($stateData, fn($s) => $s['customer_count'] > 0);
$top8ForDoughnut = array_slice($statesWithCustomers, 0, 8);

// Recommendations
$recommendations = [];
if ($prospectStates > 0) {
    $prospectNames = array_column(array_filter($stateData, fn($s) => $s['customer_count'] == 0 && $s['lead_count'] > 0), 'state_name');
    $recommendations[] = "$prospectStates state(s) have leads but no customers: " . implode(', ', array_slice($prospectNames, 0, 5)) . ". Focus on converting these leads.";
}
if ($untappedStates > 0) {
    $untappedNames = array_column(array_filter($stateData, fn($s) => $s['customer_count'] == 0 && $s['lead_count'] == 0), 'state_name');
    if (!empty($untappedNames)) {
        $recommendations[] = count($untappedNames) . " state(s) have zero presence. Consider expansion into: " . implode(', ', array_slice($untappedNames, 0, 5)) . ".";
    }
}
$singleCustomerStates = array_filter($stateData, fn($s) => $s['customer_count'] == 1);
if (count($singleCustomerStates) > 0) {
    $recommendations[] = count($singleCustomerStates) . " state(s) have only 1 customer. Appoint distributors or run targeted campaigns to grow these markets.";
}

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Geographic Expansion Analysis - ERP System</title>
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
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }

        .chart-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 20px;
        }
        .chart-card h3 { margin: 0 0 15px 0; font-size: 1em; color: var(--text); }
        .chart-container { position: relative; height: 320px; }

        .section { margin: 25px 0; }
        .section h3 { font-size: 1.05em; font-weight: 600; color: var(--text); margin: 0 0 15px 0; }

        .data-table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 10px; overflow: hidden; border: 1px solid var(--border); }
        .data-table th { background: var(--table-header-bg, #1e293b); color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82em; text-transform: uppercase; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.88em; color: var(--text); }
        .data-table tr:hover { background: var(--row-hover, var(--bg)); }
        .data-table .rank { color: var(--muted-text); font-weight: 600; }

        .row-untapped { background: rgba(239, 68, 68, 0.06) !important; }
        .row-prospect { background: rgba(245, 158, 11, 0.06) !important; }
        body.dark .row-untapped { background: rgba(239, 68, 68, 0.1) !important; }
        body.dark .row-prospect { background: rgba(245, 158, 11, 0.1) !important; }
        body.mid .row-untapped { background: rgba(239, 68, 68, 0.08) !important; }
        body.mid .row-prospect { background: rgba(245, 158, 11, 0.08) !important; }

        .status-untapped { background: #fef2f2; color: #dc2626; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; display: inline-block; }
        .status-prospect { background: #fffbeb; color: #d97706; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; display: inline-block; }
        .status-emerging { background: #eff6ff; color: #2563eb; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; display: inline-block; }
        .status-active { background: #f0fdf4; color: #16a34a; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; display: inline-block; }

        .recommendations {
            background: var(--card); border: 1px solid var(--border); border-left: 4px solid #6366f1;
            border-radius: 10px; padding: 20px; margin: 25px 0;
        }
        .recommendations h3 { color: #6366f1; margin: 0 0 12px 0; font-size: 1.05em; }
        .recommendations ul { margin: 0; padding-left: 20px; }
        .recommendations li { padding: 6px 0; font-size: 0.9em; color: var(--text); line-height: 1.5; }

        .back-link { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; font-size: 0.9em; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="content">

    <a href="/strategy/index.php" class="back-link">&larr; Back to Strategy Dashboard</a>

    <div class="strategy-header">
        <div>
            <h1>Geographic Expansion Analysis</h1>
            <div class="subtitle">State-wise performance and market expansion opportunities</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #6366f1;"></div>
            <div class="kpi-label">Total States</div>
            <div class="kpi-value"><?= $totalStates ?></div>
            <div class="kpi-sub">In coverage area</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #10b981;"></div>
            <div class="kpi-label">Active States</div>
            <div class="kpi-value"><?= $activeStates ?></div>
            <div class="kpi-sub">With at least 1 customer</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #ef4444;"></div>
            <div class="kpi-label">Untapped States</div>
            <div class="kpi-value"><?= $untappedStates ?></div>
            <div class="kpi-sub"><?= $prospectStates ?> have leads, <?= $untappedStates - $prospectStates ?> have nothing</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #f59e0b;"></div>
            <div class="kpi-label">Top State</div>
            <div class="kpi-value" style="font-size: 1.2em;"><?= htmlspecialchars($topState) ?></div>
            <div class="kpi-sub"><?= $topStateRevenue >= 100000 ? '&#8377;' . number_format($topStateRevenue / 100000, 1) . 'L revenue' : '&#8377;' . number_format($topStateRevenue) . ' revenue' ?></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="dashboard-grid">
        <div class="chart-card">
            <h3>Top States by Revenue</h3>
            <div class="chart-container">
                <canvas id="stateRevenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>Customer Distribution</h3>
            <div class="chart-container">
                <canvas id="customerDistChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <?php if (!empty($recommendations)): ?>
    <div class="recommendations">
        <h3>Expansion Recommendations</h3>
        <ul>
            <?php foreach ($recommendations as $rec): ?>
            <li><?= htmlspecialchars($rec) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- All States Table -->
    <div class="section">
        <h3>All States Performance</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>State</th><th>Customers</th><th>Orders</th><th>Revenue</th><th>Leads</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($stateData as $s):
                        $rowClass = '';
                        $statusBadge = '';
                        if ($s['customer_count'] == 0 && $s['lead_count'] == 0) {
                            $rowClass = 'row-untapped';
                            $statusBadge = '<span class="status-untapped">Untapped</span>';
                        } elseif ($s['customer_count'] == 0 && $s['lead_count'] > 0) {
                            $rowClass = 'row-prospect';
                            $statusBadge = '<span class="status-prospect">Prospect</span>';
                        } elseif ($s['customer_count'] <= 2) {
                            $statusBadge = '<span class="status-emerging">Emerging</span>';
                        } else {
                            $statusBadge = '<span class="status-active">Active</span>';
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><strong><?= htmlspecialchars($s['state_name']) ?></strong></td>
                        <td><?= $s['customer_count'] ?></td>
                        <td><?= $s['order_count'] ?></td>
                        <td><?= $s['revenue'] >= 100000 ? '&#8377;' . number_format($s['revenue'] / 100000, 1) . 'L' : ($s['revenue'] > 0 ? '&#8377;' . number_format($s['revenue']) : '-') ?></td>
                        <td><?= $s['lead_count'] ?: '-' ?></td>
                        <td><?= $statusBadge ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Cities -->
    <?php if (!empty($topCities)): ?>
    <div class="section">
        <h3>Top Performing Cities</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>City</th><th>State</th><th>Customers</th><th>Orders</th><th>Revenue</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topCities as $i => $city): ?>
                    <tr>
                        <td class="rank"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($city['city']) ?></strong></td>
                        <td><?= htmlspecialchars($city['state']) ?></td>
                        <td><?= $city['customer_count'] ?></td>
                        <td><?= $city['order_count'] ?></td>
                        <td><?= $city['revenue'] >= 100000 ? '&#8377;' . number_format($city['revenue'] / 100000, 1) . 'L' : ($city['revenue'] > 0 ? '&#8377;' . number_format($city['revenue']) : '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
const stateData = <?= json_encode(array_values($top10States)) ?>;
if (stateData.length > 0) {
    new Chart(document.getElementById('stateRevenueChart'), {
        type: 'bar',
        data: {
            labels: stateData.map(d => d.state_name),
            datasets: [{
                label: 'Revenue',
                data: stateData.map(d => parseFloat(d.revenue)),
                backgroundColor: '#6366f1',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const v = ctx.raw;
                            return v >= 100000 ? '\u20B9' + (v / 100000).toFixed(1) + 'L' : '\u20B9' + v.toLocaleString('en-IN');
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: function(v) {
                            return v >= 100000 ? '\u20B9' + (v / 100000).toFixed(1) + 'L' : '\u20B9' + v.toLocaleString('en-IN');
                        }
                    }
                }
            }
        }
    });
}

const doughnutData = <?= json_encode(array_values($top8ForDoughnut)) ?>;
if (doughnutData.length > 0) {
    const colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
    new Chart(document.getElementById('customerDistChart'), {
        type: 'doughnut',
        data: {
            labels: doughnutData.map(d => d.state_name),
            datasets: [{
                data: doughnutData.map(d => parseInt(d.customer_count)),
                backgroundColor: colors.slice(0, doughnutData.length)
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });
}
</script>

</body>
</html>