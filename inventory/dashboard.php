<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Safe count function
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Safe query function
function safeQuery($pdo, $query) {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Inventory Stats
$stats = [];
$stats['total_parts'] = safeCount($pdo, "SELECT COUNT(*) FROM part_master");
$stats['active_parts'] = safeCount($pdo, "SELECT COUNT(*) FROM part_master WHERE status = 'Active'");
$stats['total_inventory_items'] = safeCount($pdo, "SELECT COUNT(*) FROM inventory");
$stats['total_stock_value'] = safeCount($pdo, "SELECT COALESCE(SUM(i.qty * p.rate), 0) FROM inventory i JOIN part_master p ON i.part_no = p.part_no");

// Stock levels
$stats['low_stock'] = safeCount($pdo, "
    SELECT COUNT(*) FROM inventory i
    JOIN part_master p ON i.part_no = p.part_no
    WHERE i.qty < COALESCE(p.min_stock, 10)
");
$stats['out_of_stock'] = safeCount($pdo, "SELECT COUNT(*) FROM inventory WHERE qty = 0");
$stats['overstocked'] = safeCount($pdo, "
    SELECT COUNT(*) FROM inventory i
    JOIN part_master p ON i.part_no = p.part_no
    WHERE i.qty > COALESCE(p.max_stock, 1000) AND p.max_stock > 0
");

// Stock entries this month
$stats['entries_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM stock_entries WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['adjustments_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM stock_adjustments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");

// Recent stock entries
$recent_entries = safeQuery($pdo, "
    SELECT se.*, p.description
    FROM stock_entries se
    LEFT JOIN part_master p ON se.part_no = p.part_no
    ORDER BY se.created_at DESC
    LIMIT 10
");

// Low stock items
$low_stock_items = safeQuery($pdo, "
    SELECT p.part_no, p.description, i.qty as current_qty, p.min_stock, p.rate,
           (p.min_stock - i.qty) as shortage
    FROM part_master p
    JOIN inventory i ON p.part_no = i.part_no
    WHERE i.qty < COALESCE(p.min_stock, 10)
    ORDER BY shortage DESC
    LIMIT 10
");

// Out of stock items
$out_of_stock_items = safeQuery($pdo, "
    SELECT p.part_no, p.description, p.category
    FROM part_master p
    JOIN inventory i ON p.part_no = i.part_no
    WHERE i.qty = 0
    LIMIT 10
");

// Top stocked items (by value)
$top_stocked = safeQuery($pdo, "
    SELECT p.part_no, p.part_name, p.description, i.qty, p.rate, (i.qty * p.rate) as total_value
    FROM inventory i
    JOIN part_master p ON i.part_no = p.part_no
    WHERE i.qty > 0
    ORDER BY total_value DESC
    LIMIT 10
");

// Stock by category
$stock_by_category = safeQuery($pdo, "
    SELECT p.category, COUNT(*) as item_count, SUM(i.qty) as total_qty
    FROM part_master p
    JOIN inventory i ON p.part_no = i.part_no
    GROUP BY p.category
    ORDER BY total_qty DESC
    LIMIT 8
");

// Top 20 high value parts (by unit rate) - includes zero stock, excludes specific parts
$excludedParts = ['yid', '42', '44', '46', '52', '83', '91', '99'];
$high_value_parts = [];
try {
    $stmt = $pdo->query("
        SELECT p.part_no, p.part_name, p.description, COALESCE(i.qty, 0) as qty, p.rate, (COALESCE(i.qty, 0) * p.rate) as total_value
        FROM part_master p
        LEFT JOIN inventory i ON i.part_no = p.part_no
        WHERE p.rate > 0
        ORDER BY p.rate DESC
        LIMIT 50
    ");
    $allParts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($allParts as $part) {
        $partNo = strtolower(trim($part['part_no']));
        // Skip if part_no starts with 'yid' or matches excluded list
        $isExcluded = in_array($partNo, $excludedParts) || strpos($partNo, 'yid') === 0;
        if (!$isExcluded && $count < 20) {
            $high_value_parts[] = $part;
            $count++;
        }
    }
} catch (Exception $e) {
    $high_value_parts = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .module-header img {
            max-height: 60px;
            max-width: 150px;
            background: white;
            padding: 8px;
            border-radius: 8px;
            object-fit: contain;
        }
        .module-header h1 { margin: 0; font-size: 1.8em; }
        .module-header p { margin: 5px 0 0; opacity: 0.9; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #4facfe;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }

        .stat-icon { font-size: 2em; margin-bottom: 10px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #4facfe;
            padding-bottom: 10px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.4);
        }
        .quick-action-btn .action-icon { font-size: 1.6em; margin-bottom: 8px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table .text-center { text-align: center; }
        .data-table .text-right { text-align: right; }

        .stock-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .stock-ok { background: #27ae60; }
        .stock-low { background: #f39c12; }
        .stock-critical { background: #e74c3c; }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alerts-panel h4 { margin: 0 0 10px 0; color: #856404; }
        .alerts-panel ul { list-style: none; padding: 0; margin: 0; }
        .alerts-panel li { padding: 5px 0; color: #856404; }
        .alerts-panel a { color: #004085; font-weight: 600; }

        .section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "☀️ Light Mode" : "🌙 Dark Mode";
    });
}
</script>

<div class="content">
    <!-- Module Header -->
    <div class="module-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php
                $logo_path = $settings['logo_path'];
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1>Inventory Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['low_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Stock Alerts</h4>
        <ul>
            <?php if ($stats['out_of_stock'] > 0): ?>
            <li><a href="/inventory/index.php?filter=out_of_stock"><?= $stats['out_of_stock'] ?> Item<?= $stats['out_of_stock'] > 1 ? 's' : '' ?> Out of Stock</a> - Urgent reorder needed</li>
            <?php endif; ?>
            <?php if ($stats['low_stock'] > 0): ?>
            <li><a href="/inventory/index.php?filter=low_stock"><?= $stats['low_stock'] ?> Item<?= $stats['low_stock'] > 1 ? 's' : '' ?> Below Minimum Stock</a> - Plan procurement</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/stock_entry/add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            Stock Entry
        </a>
        <a href="/depletion/stock_adjustment.php" class="quick-action-btn">
            <div class="action-icon">⚖️</div>
            Stock Adjust
        </a>
        <a href="/part_master/add.php" class="quick-action-btn">
            <div class="action-icon">🔧</div>
            New Part
        </a>
        <a href="/reports/monthly.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Reports
        </a>
        <a href="/inventory/index.php" class="quick-action-btn">
            <div class="action-icon">📦</div>
            View Stock
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Inventory Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">⚙️</div>
            <div class="stat-value"><?= $stats['total_parts'] ?></div>
            <div class="stat-label">Total Parts</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['active_parts'] ?></div>
            <div class="stat-label">Active Parts</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['total_inventory_items'] ?></div>
            <div class="stat-label">Stock Items</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?= number_format($stats['total_stock_value']) ?></div>
            <div class="stat-label">Stock Value</div>
        </div>
        <?php if ($stats['low_stock'] > 0): ?>
        <div class="stat-card warning">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?= $stats['low_stock'] ?></div>
            <div class="stat-label">Low Stock</div>
        </div>
        <?php endif; ?>
        <?php if ($stats['out_of_stock'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">🚫</div>
            <div class="stat-value"><?= $stats['out_of_stock'] ?></div>
            <div class="stat-label">Out of Stock</div>
        </div>
        <?php endif; ?>
        <div class="stat-card success">
            <div class="stat-icon">📥</div>
            <div class="stat-value"><?= $stats['entries_this_month'] ?></div>
            <div class="stat-label">Entries (Month)</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Low Stock Items -->
        <div class="dashboard-panel">
            <h3>⚠️ Low Stock Items</h3>
            <?php if (empty($low_stock_items)): ?>
                <p style="color: #27ae60;">All items are well stocked!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part #</th>
                            <th>Description</th>
                            <th>Current</th>
                            <th>Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                        <tr>
                            <td>
                                <span class="stock-indicator stock-<?= $item['current_qty'] == 0 ? 'critical' : 'low' ?>"></span>
                                <a href="/part_master/view.php?part_no=<?= urlencode($item['part_no']) ?>"><?= htmlspecialchars($item['part_no']) ?></a>
                            </td>
                            <td><?= htmlspecialchars(substr($item['description'], 0, 25)) ?></td>
                            <td style="color: <?= $item['current_qty'] == 0 ? '#e74c3c' : '#f39c12' ?>; font-weight: bold;"><?= $item['current_qty'] ?></td>
                            <td><?= $item['min_stock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Stock Entries -->
        <div class="dashboard-panel">
            <h3>📥 Recent Stock Entries</h3>
            <?php if (empty($recent_entries)): ?>
                <p style="color: #7f8c8d;">No recent stock entries.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part #</th>
                            <th>Qty</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_entries as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['part_no']) ?></td>
                            <td style="color: #27ae60; font-weight: bold;">+<?= $entry['qty'] ?></td>
                            <td><?= date('d M', strtotime($entry['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 10 Items by Stock Value -->
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>💰 Top 10 Items by Stock Value</h3>
        <?php if (empty($top_stocked)): ?>
            <p style="color: #7f8c8d;">No stock data available.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Part #</th>
                        <th>Part Name</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Rate</th>
                        <th class="text-right" style="background: #e8f5e9;">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grandTotal = 0;
                    $totalQty = 0;
                    foreach ($top_stocked as $index => $item):
                        $grandTotal += $item['total_value'];
                        $totalQty += $item['qty'];
                    ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><a href="/part_master/view.php?part_no=<?= urlencode($item['part_no']) ?>"><?= htmlspecialchars($item['part_no']) ?></a></td>
                        <td><?= htmlspecialchars(substr($item['part_name'] ?? '', 0, 35)) ?><?= strlen($item['part_name'] ?? '') > 35 ? '...' : '' ?></td>
                        <td class="text-center"><?= number_format($item['qty']) ?></td>
                        <td class="text-right">₹<?= number_format($item['rate'], 2) ?></td>
                        <td class="text-right" style="background: #e8f5e9; font-weight: bold; color: #27ae60;">₹<?= number_format($item['total_value'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="3">Total (Top 10)</td>
                        <td class="text-center"><?= number_format($totalQty) ?></td>
                        <td class="text-right">-</td>
                        <td class="text-right" style="background: #c8e6c9; color: #1b5e20;">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <div class="dashboard-row">
        <!-- Stock by Category -->
        <div class="dashboard-panel">
            <h3>📊 Stock by Category</h3>
            <?php if (empty($stock_by_category)): ?>
                <p style="color: #7f8c8d;">No category data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Total Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_by_category as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['category'] ?: 'Uncategorized') ?></td>
                            <td><?= $cat['item_count'] ?></td>
                            <td><?= number_format($cat['total_qty']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 20 High Value Parts -->
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>💎 Top 20 High Value Parts (by Unit Rate)</h3>
        <?php if (empty($high_value_parts)): ?>
            <p style="color: #7f8c8d;">No stock data available.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Part #</th>
                        <th>Part Name</th>
                        <th>Qty</th>
                        <th>Unit Rate</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($high_value_parts as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><a href="/part_master/view.php?part_no=<?= urlencode($item['part_no']) ?>"><?= htmlspecialchars($item['part_no']) ?></a></td>
                        <td><?= htmlspecialchars(substr($item['part_name'] ?? '', 0, 40)) ?><?= strlen($item['part_name'] ?? '') > 40 ? '...' : '' ?></td>
                        <td><?= number_format($item['qty']) ?></td>
                        <td style="font-weight: bold; color: #e74c3c;">₹<?= number_format($item['rate'], 2) ?></td>
                        <td>₹<?= number_format($item['total_value'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/part_master/list.php" class="quick-action-btn">
            <div class="action-icon">⚙️</div>
            Part Master
        </a>
        <a href="/stock_entry/index.php" class="quick-action-btn">
            <div class="action-icon">📥</div>
            Stock Entries
        </a>
        <a href="/depletion/stock_adjustment.php" class="quick-action-btn">
            <div class="action-icon">⚖️</div>
            Adjustments
        </a>
        <a href="/inventory/index.php" class="quick-action-btn">
            <div class="action-icon">📦</div>
            Current Stock
        </a>
        <a href="/reports/monthly.php" class="quick-action-btn">
            <div class="action-icon">📈</div>
            Reports
        </a>
    </div>
</div>

</body>
</html>
