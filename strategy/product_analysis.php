<?php
/**
 * Product Revenue Analysis
 * Top products, zero-price audit, cross-sell patterns, category breakdown
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

// ============ TOP 20 PRODUCTS BY REVENUE ============
$topProducts = [];
try {
    $topProducts = $pdo->query("
        SELECT qi.part_no, qi.part_name,
               SUM(qi.qty) as qty_sold,
               SUM(qi.total_amount) as revenue,
               COUNT(DISTINCT so.customer_id) as customer_count
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        GROUP BY qi.part_no, qi.part_name
        ORDER BY revenue DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$totalProductRevenue = array_sum(array_column($topProducts, 'revenue'));

// ============ ZERO PRICE PRODUCTS ============
$zeroPriceProducts = [];
try {
    $zeroPriceProducts = $pdo->query("
        SELECT p.part_no, p.part_name, p.category, p.status,
               COALESCE(sold.qty_sold, 0) as qty_sold
        FROM part_master p
        LEFT JOIN (
            SELECT qi.part_no, SUM(qi.qty) as qty_sold
            FROM quote_items qi
            JOIN quote_master qm ON qi.quote_id = qm.id
            INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
            GROUP BY qi.part_no
        ) sold ON sold.part_no = p.part_no
        WHERE (p.rate = 0 OR p.rate IS NULL) AND p.status = 'active'
        ORDER BY sold.qty_sold DESC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ CROSS-SELL / CO-PURCHASE MATRIX ============
$coPurchase = [];
try {
    $coPurchase = $pdo->query("
        SELECT qi1.part_name as product_a, qi2.part_name as product_b,
               COUNT(DISTINCT qi1.quote_id) as times_together
        FROM quote_items qi1
        JOIN quote_items qi2 ON qi1.quote_id = qi2.quote_id AND qi1.part_no < qi2.part_no
        JOIN quote_master qm ON qi1.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        GROUP BY qi1.part_no, qi1.part_name, qi2.part_no, qi2.part_name
        HAVING times_together >= 2
        ORDER BY times_together DESC
        LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ CATEGORY REVENUE BREAKDOWN ============
$categoryRevenue = [];
try {
    $categoryRevenue = $pdo->query("
        SELECT COALESCE(NULLIF(p.category, ''), 'Uncategorized') as category,
               SUM(qi.total_amount) as revenue,
               SUM(qi.qty) as qty_sold,
               COUNT(DISTINCT qi.part_no) as product_count
        FROM quote_items qi
        JOIN part_master p ON qi.part_no = p.part_no
        JOIN quote_master qm ON qi.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        GROUP BY category
        ORDER BY revenue DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Summary KPIs
$totalProductsSold = count($topProducts);
$zeroPriceCount = count($zeroPriceProducts);
$coPurchaseCount = count($coPurchase);
$categoryCount = count($categoryRevenue);

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Revenue Analysis - ERP System</title>
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
        .section h3 { font-size: 1.05em; font-weight: 600; color: var(--text); margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px; }
        .section-count { background: var(--bg); padding: 2px 10px; border-radius: 12px; font-size: 0.8em; color: var(--muted-text); }

        .data-table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 10px; overflow: hidden; border: 1px solid var(--border); }
        .data-table th { background: var(--table-header-bg, #1e293b); color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82em; text-transform: uppercase; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.88em; color: var(--text); }
        .data-table tr:hover { background: var(--row-hover, var(--bg)); }
        .data-table .rank { color: var(--muted-text); font-weight: 600; width: 30px; }

        .pct-bar-container { display: flex; align-items: center; gap: 8px; }
        .pct-bar { height: 6px; border-radius: 3px; background: #6366f1; display: inline-block; }
        .pct-text { font-size: 0.8em; color: var(--muted-text); min-width: 40px; }

        .alert-section {
            border: 1px solid var(--border); border-left: 4px solid #ef4444;
            border-radius: 10px; padding: 20px; background: var(--card); margin: 25px 0;
        }
        .alert-section h3 { color: #ef4444; margin: 0 0 15px 0; font-size: 1.05em; }

        .bundle-section {
            border: 1px solid var(--border); border-left: 4px solid #10b981;
            border-radius: 10px; padding: 20px; background: var(--card); margin: 25px 0;
        }
        .bundle-section h3 { color: #10b981; margin: 0 0 15px 0; font-size: 1.05em; }

        .btn-fix {
            display: inline-block; padding: 4px 12px; font-size: 0.78em;
            border-radius: 5px; text-decoration: none; font-weight: 600;
            background: var(--primary, #2563eb); color: white;
        }
        .btn-fix:hover { opacity: 0.85; }

        .back-link { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; font-size: 0.9em; }
        .back-link:hover { text-decoration: underline; }

        .badge-bundle { background: #f0fdf4; color: #16a34a; padding: 3px 10px; border-radius: 12px; font-size: 0.78em; font-weight: 600; }
    </style>
</head>
<body>
<div class="content">

    <a href="/strategy/index.php" class="back-link">&larr; Back to Strategy Dashboard</a>

    <div class="strategy-header">
        <div>
            <h1>Product Revenue Analysis</h1>
            <div class="subtitle">Revenue by product, pricing audit, and cross-sell opportunities</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #6366f1;"></div>
            <div class="kpi-label">Total Product Revenue</div>
            <div class="kpi-value"><?= $totalProductRevenue >= 100000 ? '&#8377;' . number_format($totalProductRevenue / 100000, 1) . 'L' : '&#8377;' . number_format($totalProductRevenue) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #10b981;"></div>
            <div class="kpi-label">Products Sold</div>
            <div class="kpi-value"><?= $totalProductsSold ?></div>
            <div class="kpi-sub">Distinct products with orders</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #ef4444;"></div>
            <div class="kpi-label">Zero-Price Products</div>
            <div class="kpi-value"><?= $zeroPriceCount ?></div>
            <div class="kpi-sub">Need pricing update</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background: #f59e0b;"></div>
            <div class="kpi-label">Bundle Opportunities</div>
            <div class="kpi-value"><?= $coPurchaseCount ?></div>
            <div class="kpi-sub">Product pairs bought together</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="dashboard-grid">
        <div class="chart-card">
            <h3>Top 10 Products by Revenue</h3>
            <div class="chart-container">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h3>Revenue by Category</h3>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top 20 Products Table -->
    <div class="section">
        <h3>Top 20 Products by Revenue <span class="section-count"><?= count($topProducts) ?> products</span></h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Part No</th><th>Product Name</th><th>Qty Sold</th><th>Revenue</th><th>Customers</th><th>% of Total</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--muted-text); padding: 30px;">No sales data found</td></tr>
                    <?php else: ?>
                    <?php foreach ($topProducts as $i => $p):
                        $pct = $totalProductRevenue > 0 ? round(($p['revenue'] / $totalProductRevenue) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td class="rank"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($p['part_no']) ?></strong></td>
                        <td><?= htmlspecialchars($p['part_name']) ?></td>
                        <td><?= number_format($p['qty_sold']) ?></td>
                        <td><?= $p['revenue'] >= 100000 ? '&#8377;' . number_format($p['revenue'] / 100000, 1) . 'L' : '&#8377;' . number_format($p['revenue']) ?></td>
                        <td><?= $p['customer_count'] ?></td>
                        <td>
                            <div class="pct-bar-container">
                                <div class="pct-bar" style="width: <?= max(3, $pct) ?>%;"></div>
                                <span class="pct-text"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Zero Price Products -->
    <?php if (!empty($zeroPriceProducts)): ?>
    <div class="alert-section">
        <h3>Products with Rs. 0 Pricing (<?= $zeroPriceCount ?> items need attention)</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Part No</th><th>Product Name</th><th>Category</th><th>Status</th><th>Qty Sold</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($zeroPriceProducts as $z): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($z['part_no']) ?></strong></td>
                        <td><?= htmlspecialchars($z['part_name']) ?></td>
                        <td><?= htmlspecialchars($z['category'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($z['status']) ?></td>
                        <td><?= $z['qty_sold'] > 0 ? number_format($z['qty_sold']) : '-' ?></td>
                        <td><a href="/part_master/edit.php?part_no=<?= urlencode($z['part_no']) ?>" class="btn-fix">Fix Pricing</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Co-Purchase Patterns -->
    <?php if (!empty($coPurchase)): ?>
    <div class="bundle-section">
        <h3>Co-Purchase Patterns (Bundle Opportunities)</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Product A</th><th>Product B</th><th>Times Bought Together</th><th>Suggestion</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($coPurchase as $cp): ?>
                    <tr>
                        <td><?= htmlspecialchars($cp['product_a']) ?></td>
                        <td><?= htmlspecialchars($cp['product_b']) ?></td>
                        <td><strong><?= $cp['times_together'] ?></strong></td>
                        <td><span class="badge-bundle">Consider bundling</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Category Breakdown Table -->
    <?php if (!empty($categoryRevenue)): ?>
    <div class="section">
        <h3>Revenue by Category</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Category</th><th>Products</th><th>Qty Sold</th><th>Revenue</th><th>% of Total</th></tr>
                </thead>
                <tbody>
                    <?php
                    $totalCatRevenue = array_sum(array_column($categoryRevenue, 'revenue'));
                    foreach ($categoryRevenue as $cat):
                        $catPct = $totalCatRevenue > 0 ? round(($cat['revenue'] / $totalCatRevenue) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['category']) ?></strong></td>
                        <td><?= $cat['product_count'] ?></td>
                        <td><?= number_format($cat['qty_sold']) ?></td>
                        <td><?= $cat['revenue'] >= 100000 ? '&#8377;' . number_format($cat['revenue'] / 100000, 1) . 'L' : '&#8377;' . number_format($cat['revenue']) ?></td>
                        <td>
                            <div class="pct-bar-container">
                                <div class="pct-bar" style="width: <?= max(3, $catPct) ?>%;"></div>
                                <span class="pct-text"><?= $catPct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Top 10 Products Horizontal Bar
<?php $top10 = array_slice($topProducts, 0, 10); ?>
const top10Data = <?= json_encode($top10) ?>;
if (top10Data.length > 0) {
    new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: top10Data.map(d => d.part_name.length > 25 ? d.part_name.substring(0, 25) + '...' : d.part_name),
            datasets: [{
                label: 'Revenue',
                data: top10Data.map(d => parseFloat(d.revenue)),
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

// Category Revenue Doughnut
const catData = <?= json_encode($categoryRevenue) ?>;
if (catData.length > 0) {
    const colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#14b8a6'];
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catData.map(d => d.category),
            datasets: [{
                data: catData.map(d => parseFloat(d.revenue)),
                backgroundColor: colors.slice(0, catData.length)
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const v = ctx.raw;
                            return ctx.label + ': ' + (v >= 100000 ? '\u20B9' + (v / 100000).toFixed(1) + 'L' : '\u20B9' + v.toLocaleString('en-IN'));
                        }
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>