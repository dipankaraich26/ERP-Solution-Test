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

// Purchase Stats
$stats = [];
$stats['suppliers_total'] = safeCount($pdo, "SELECT COUNT(*) FROM suppliers");
$stats['suppliers_active'] = safeCount($pdo, "SELECT COUNT(*) FROM suppliers WHERE status = 'Active'");

// Purchase Order stats
$stats['po_total'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders");
$stats['po_pending'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending')");
$stats['po_approved'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status = 'Approved'");
$stats['po_received'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status IN ('received', 'Received')");
$stats['po_partial'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status = 'Partial'");

// Procurement stats
$stats['procurement_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM procurement WHERE status = 'Pending'");
$stats['procurement_approved'] = safeCount($pdo, "SELECT COUNT(*) FROM procurement WHERE status = 'Approved'");

// This month's purchase value
$stats['po_value_month'] = safeCount($pdo, "SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())");

// Total purchase value
$stats['po_value_total'] = safeCount($pdo, "SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders");

// Pending PO value
$stats['po_pending_value'] = safeCount($pdo, "SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending', 'Approved')");

// PI tasks awaiting PO creation (auto-generated from PI release)
$pi_tasks = safeQuery($pdo, "
    SELECT t.id, t.task_no, t.task_name, t.related_reference, t.created_at, t.status, t.priority,
           c.company_name, c.customer_name
    FROM tasks t
    LEFT JOIN customers c ON t.customer_id = c.customer_id
    WHERE t.related_module = 'Proforma Invoice' AND t.status NOT IN ('Completed', 'Cancelled')
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stats['pi_tasks_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE related_module = 'Proforma Invoice' AND status NOT IN ('Completed', 'Cancelled')");

// Recent purchase orders (grouped by PO number)
$recent_pos = safeQuery($pdo, "
    SELECT po.po_no, po.supplier_id, SUM(po.qty * po.rate) as total_amount,
           MAX(po.status) as status, MAX(po.purchase_date) as created_at, s.supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    GROUP BY po.po_no, po.supplier_id, s.supplier_name
    ORDER BY MAX(po.purchase_date) DESC
    LIMIT 10
");

// Pending POs
$pending_pos = safeQuery($pdo, "
    SELECT po.po_no, po.supplier_id, SUM(po.qty * po.rate) as total_amount,
           MAX(po.purchase_date) as expected_date, s.supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.status IN ('open', 'pending', 'Pending')
    GROUP BY po.po_no, po.supplier_id, s.supplier_name
    ORDER BY MAX(po.purchase_date)
    LIMIT 10
");

// Top suppliers (by PO count)
$top_suppliers = safeQuery($pdo, "
    SELECT s.id, s.supplier_name, COUNT(DISTINCT po.po_no) as po_count, SUM(po.qty * po.rate) as total_value
    FROM suppliers s
    LEFT JOIN purchase_orders po ON s.id = po.supplier_id
    GROUP BY s.id, s.supplier_name
    ORDER BY po_count DESC
    LIMIT 5
");

// Low stock items needing procurement
$low_stock_items = safeQuery($pdo, "
    SELECT p.part_no, p.description, i.qty as current_qty, p.min_stock
    FROM part_master p
    JOIN inventory i ON p.part_no = i.part_no
    WHERE i.qty < COALESCE(p.min_stock, 10)
    ORDER BY (p.min_stock - i.qty) DESC
    LIMIT 10
");

// Monthly purchase trend (last 6 months)
$monthly_trend = safeQuery($pdo, "
    SELECT DATE_FORMAT(purchase_date, '%Y-%m') as month_key,
           DATE_FORMAT(purchase_date, '%b %Y') as month_label,
           SUM(qty * rate) as total_value,
           COUNT(DISTINCT po_no) as po_count
    FROM purchase_orders
    WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");

// Calculate max for chart scaling
$max_trend_value = 1;
foreach ($monthly_trend as $m) {
    if ($m['total_value'] > $max_trend_value) $max_trend_value = $m['total_value'];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase & SCM Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 18px 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #f5576c;
            transition: transform 0.2s;
            cursor: default;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.purple { border-left-color: #8e44ad; }

        .stat-icon { font-size: 1.8em; margin-bottom: 8px; }
        .stat-value { font-size: 1.9em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.85em; margin-top: 5px; }

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
            border-bottom: 2px solid #f5576c;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-panel h3 .panel-badge {
            font-size: 0.65em;
            background: #f5576c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        .dashboard-panel.full-width {
            grid-column: 1 / -1;
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
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

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-pending, .status-open { background: #fff3e0; color: #ef6c00; }
        .status-approved { background: #e3f2fd; color: #1565c0; }
        .status-received { background: #e8f5e9; color: #2e7d32; }
        .status-partial { background: #fce4ec; color: #c2185b; }
        .status-not-started { background: #f3e5f5; color: #7b1fa2; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }
        .status-cancelled { background: #efebe9; color: #795548; }

        .priority-high, .priority-critical { color: #e74c3c; font-weight: 600; }
        .priority-medium { color: #f39c12; }
        .priority-low { color: #27ae60; }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alerts-panel h4 { margin: 0 0 10px 0; color: #856404; }
        .alerts-panel ul { list-style: none; padding: 0; margin: 0; }
        .alerts-panel li { padding: 6px 0; color: #856404; display: flex; align-items: center; gap: 8px; }
        .alerts-panel a { color: #004085; font-weight: 600; }
        .alert-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .alert-dot.red { background: #e74c3c; }
        .alert-dot.orange { background: #f39c12; }
        .alert-dot.purple { background: #8e44ad; }

        .section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        /* Trend Chart */
        .trend-chart {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            height: 160px;
            padding: 10px 0;
        }
        .trend-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        .trend-bar {
            width: 100%;
            max-width: 50px;
            background: linear-gradient(180deg, #f093fb 0%, #f5576c 100%);
            border-radius: 6px 6px 0 0;
            min-height: 4px;
            transition: height 0.5s ease;
            position: relative;
        }
        .trend-bar:hover { opacity: 0.85; }
        .trend-bar .bar-tooltip {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #2c3e50;
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75em;
            white-space: nowrap;
            margin-bottom: 5px;
        }
        .trend-bar:hover .bar-tooltip { display: block; }
        .trend-value {
            font-size: 0.7em;
            color: #7f8c8d;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .trend-label {
            font-size: 0.7em;
            color: #7f8c8d;
            margin-top: 6px;
            text-align: center;
        }
        .trend-count {
            font-size: 0.65em;
            color: #bdc3c7;
        }

        /* Task action button */
        .task-action-btn {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.78em;
            text-decoration: none;
            font-weight: 600;
            background: #f5576c;
            color: white;
        }
        .task-action-btn:hover { opacity: 0.85; }
        .task-action-btn.complete {
            background: #27ae60;
        }

        /* Dark mode */
        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .alerts-panel { background: #3e3416; border-left-color: #ffc107; }
        body.dark .alerts-panel h4, body.dark .alerts-panel li { color: #ffc107; }
        body.dark .trend-value, body.dark .trend-label { color: #bdc3c7; }
        body.dark .section-title { color: #ecf0f1; }
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

<div class="content" style="overflow-y: auto; height: 100vh; padding-bottom: 40px;">
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
            <h1>Purchase & SCM Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['po_pending'] > 0 || count($low_stock_items) > 0 || $stats['pi_tasks_pending'] > 0 || $stats['procurement_pending'] > 0): ?>
    <div class="alerts-panel">
        <h4>Attention Required</h4>
        <ul>
            <?php if ($stats['pi_tasks_pending'] > 0): ?>
            <li><span class="alert-dot purple"></span> <a href="/tasks/index.php?module=Proforma+Invoice"><?= $stats['pi_tasks_pending'] ?> PI<?= $stats['pi_tasks_pending'] > 1 ? 's' : '' ?> Awaiting PO Creation</a> - Purchase orders need to be created</li>
            <?php endif; ?>
            <?php if ($stats['po_pending'] > 0): ?>
            <li><span class="alert-dot orange"></span> <a href="/purchase/index.php"><?= $stats['po_pending'] ?> Pending PO<?= $stats['po_pending'] > 1 ? 's' : '' ?></a> - Awaiting approval</li>
            <?php endif; ?>
            <?php if ($stats['procurement_pending'] > 0): ?>
            <li><span class="alert-dot orange"></span> <a href="/procurement/index.php"><?= $stats['procurement_pending'] ?> Procurement Plan<?= $stats['procurement_pending'] > 1 ? 's' : '' ?></a> - Pending review</li>
            <?php endif; ?>
            <?php if (count($low_stock_items) > 0): ?>
            <li><span class="alert-dot red"></span> <a href="/procurement/index.php"><?= count($low_stock_items) ?> Item<?= count($low_stock_items) > 1 ? 's' : '' ?> Below Min Stock</a> - Procurement needed</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/purchase/index.php" class="quick-action-btn">
            <div class="action-icon">+</div>
            New PO
        </a>
        <a href="/suppliers/index.php" class="quick-action-btn">
            <div class="action-icon">+</div>
            New Supplier
        </a>
        <a href="/procurement/create.php" class="quick-action-btn">
            <div class="action-icon">+</div>
            Procurement Plan
        </a>
        <a href="/purchase/supplier_pricing.php" class="quick-action-btn">
            <div class="action-icon">$</div>
            Supplier Pricing
        </a>
        <a href="/proforma/index.php" class="quick-action-btn">
            <div class="action-icon">PI</div>
            Proforma Invoices
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Purchase Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-value"><?= $stats['suppliers_active'] ?></div>
            <div class="stat-label">Active Suppliers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['po_total'] ?></div>
            <div class="stat-label">Total POs</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?= $stats['po_pending'] ?></div>
            <div class="stat-label">Pending POs</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= $stats['po_approved'] ?></div>
            <div class="stat-label">Approved POs</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $stats['po_received'] ?></div>
            <div class="stat-label">Received POs</div>
        </div>
        <?php if ($stats['po_partial'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-value"><?= $stats['po_partial'] ?></div>
            <div class="stat-label">Partial Receipt</div>
        </div>
        <?php endif; ?>
        <div class="stat-card purple">
            <div class="stat-value"><?= $stats['pi_tasks_pending'] ?></div>
            <div class="stat-label">PIs Awaiting PO</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['po_value_month']) ?></div>
            <div class="stat-label">This Month (Rs)</div>
        </div>
    </div>

    <!-- PI Tasks Awaiting PO + Monthly Trend -->
    <div class="dashboard-row">
        <!-- PI Tasks Awaiting PO Creation -->
        <div class="dashboard-panel">
            <h3>
                PI Awaiting PO Creation
                <?php if ($stats['pi_tasks_pending'] > 0): ?>
                    <span class="panel-badge"><?= $stats['pi_tasks_pending'] ?></span>
                <?php endif; ?>
            </h3>
            <?php if (empty($pi_tasks)): ?>
                <p style="color: #27ae60; padding: 15px 0;">No pending PI tasks. All POs have been created.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PI No</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pi_tasks as $task): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($task['related_reference'] ?? '') ?></strong>
                                <div style="font-size: 0.8em; color: #999;"><?= htmlspecialchars($task['task_no']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($task['company_name'] ?? $task['customer_name'] ?? '-') ?></td>
                            <td style="white-space: nowrap;"><?= $task['created_at'] ? date('d M Y', strtotime($task['created_at'])) : '-' ?></td>
                            <td>
                                <a href="/purchase/index.php" class="task-action-btn">Create PO</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Monthly Trend -->
        <div class="dashboard-panel">
            <h3>Purchase Trend (6 Months)</h3>
            <?php if (empty($monthly_trend)): ?>
                <p style="color: #7f8c8d; padding: 15px 0;">No purchase data available yet.</p>
            <?php else: ?>
                <div class="trend-chart">
                    <?php foreach ($monthly_trend as $m): ?>
                        <?php $pct = ($m['total_value'] / $max_trend_value) * 100; ?>
                        <div class="trend-bar-wrap">
                            <div class="trend-value"><?= $m['po_count'] ?> POs</div>
                            <div class="trend-bar" style="height: <?= max($pct, 3) ?>%;">
                                <div class="bar-tooltip">Rs <?= number_format($m['total_value']) ?> | <?= $m['po_count'] ?> POs</div>
                            </div>
                            <div class="trend-label"><?= $m['month_label'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 10px; font-size: 0.85em; color: #7f8c8d;">
                    Total: Rs <?= number_format($stats['po_value_total']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent POs -->
        <div class="dashboard-panel">
            <h3>Recent Purchase Orders</h3>
            <?php if (empty($recent_pos)): ?>
                <p style="color: #7f8c8d;">No purchase orders found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_pos as $po): ?>
                        <tr>
                            <td><a href="/purchase/view.php?po_no=<?= urlencode($po['po_no']) ?>"><?= htmlspecialchars($po['po_no']) ?></a></td>
                            <td><?= htmlspecialchars($po['supplier_name'] ?? 'N/A') ?></td>
                            <td style="white-space: nowrap;">Rs <?= number_format($po['total_amount'], 2) ?></td>
                            <td><span class="status-badge status-<?= strtolower($po['status']) ?>"><?= $po['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pending POs -->
        <div class="dashboard-panel">
            <h3>
                Pending Purchase Orders
                <?php if (!empty($pending_pos)): ?>
                    <span class="panel-badge"><?= count($pending_pos) ?></span>
                <?php endif; ?>
            </h3>
            <?php if (empty($pending_pos)): ?>
                <p style="color: #27ae60;">No pending purchase orders. All clear!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_pos as $po): ?>
                        <tr>
                            <td><a href="/purchase/view.php?po_no=<?= urlencode($po['po_no']) ?>"><?= htmlspecialchars($po['po_no']) ?></a></td>
                            <td><?= htmlspecialchars($po['supplier_name'] ?? 'N/A') ?></td>
                            <td style="white-space: nowrap;">Rs <?= number_format($po['total_amount'], 2) ?></td>
                            <td style="white-space: nowrap;"><?= $po['expected_date'] ? date('d M Y', strtotime($po['expected_date'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px; font-size: 0.9em; color: #7f8c8d;">
                    Pending Value: <strong style="color: #f39c12;">Rs <?= number_format($stats['po_pending_value']) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Top Suppliers -->
        <div class="dashboard-panel">
            <h3>Top Suppliers</h3>
            <?php if (empty($top_suppliers)): ?>
                <p style="color: #7f8c8d;">No supplier data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>POs</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_suppliers as $i => $supplier): ?>
                        <tr>
                            <td>
                                <span style="color: #f5576c; font-weight: 700; margin-right: 5px;">#<?= $i + 1 ?></span>
                                <a href="/suppliers/view.php?id=<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></a>
                            </td>
                            <td><?= $supplier['po_count'] ?></td>
                            <td style="white-space: nowrap;">Rs <?= number_format($supplier['total_value'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Low Stock Items -->
        <div class="dashboard-panel">
            <h3>
                Low Stock - Procurement Needed
                <?php if (!empty($low_stock_items)): ?>
                    <span class="panel-badge"><?= count($low_stock_items) ?></span>
                <?php endif; ?>
            </h3>
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
                            <td><strong><?= htmlspecialchars($item['part_no']) ?></strong></td>
                            <td><?= htmlspecialchars(substr($item['description'], 0, 30)) ?></td>
                            <td>
                                <?php
                                    $ratio = $item['min_stock'] > 0 ? ($item['current_qty'] / $item['min_stock']) : 0;
                                    $stockColor = $ratio < 0.3 ? '#e74c3c' : ($ratio < 0.6 ? '#f39c12' : '#27ae60');
                                ?>
                                <span style="color: <?= $stockColor ?>; font-weight: 600;"><?= $item['current_qty'] ?></span>
                            </td>
                            <td><?= $item['min_stock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/suppliers/index.php" class="quick-action-btn">
            <div class="action-icon">S</div>
            All Suppliers
        </a>
        <a href="/purchase/index.php" class="quick-action-btn">
            <div class="action-icon">PO</div>
            All POs
        </a>
        <a href="/procurement/index.php" class="quick-action-btn">
            <div class="action-icon">PR</div>
            Procurement
        </a>
        <a href="/purchase/supplier_pricing.php" class="quick-action-btn">
            <div class="action-icon">$</div>
            Supplier Pricing
        </a>
        <a href="/proforma/index.php" class="quick-action-btn">
            <div class="action-icon">PI</div>
            Proforma Invoices
        </a>
        <a href="/inventory/dashboard.php" class="quick-action-btn">
            <div class="action-icon">I</div>
            Inventory
        </a>
    </div>
</div>

</body>
</html>
