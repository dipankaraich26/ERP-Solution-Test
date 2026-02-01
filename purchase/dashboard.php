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

// Purchase Order stats (note: purchase_orders has qty*rate, no total_amount column)
// Status values: 'open', 'pending', 'Pending', 'Approved', 'received', 'Received', 'Partial', 'cancelled'
$stats['po_total'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders");
$stats['po_pending'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending')");
$stats['po_approved'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status = 'Approved'");
$stats['po_received'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status IN ('received', 'Received')");
$stats['po_partial'] = safeCount($pdo, "SELECT COUNT(DISTINCT po_no) FROM purchase_orders WHERE status = 'Partial'");

// Procurement stats
$stats['procurement_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM procurement WHERE status = 'Pending'");
$stats['procurement_approved'] = safeCount($pdo, "SELECT COUNT(*) FROM procurement WHERE status = 'Approved'");

// This month's purchase value (using qty * rate instead of total_amount)
$stats['po_value_month'] = safeCount($pdo, "SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())");

// Pending PO value
$stats['po_pending_value'] = safeCount($pdo, "SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending', 'Approved')");

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
            border-left: 4px solid #f5576c;
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
            border-bottom: 2px solid #f5576c;
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
        }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-approved { background: #e3f2fd; color: #1565c0; }
        .status-received { background: #e8f5e9; color: #2e7d32; }
        .status-partial { background: #fce4ec; color: #c2185b; }

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

<div class="content" style="overflow-y: auto; height: 100vh;">
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
    <?php if ($stats['po_pending'] > 0 || count($low_stock_items) > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Attention Required</h4>
        <ul>
            <?php if ($stats['po_pending'] > 0): ?>
            <li><a href="/purchase/index.php"><?= $stats['po_pending'] ?> Pending PO<?= $stats['po_pending'] > 1 ? 's' : '' ?></a> - Awaiting approval</li>
            <?php endif; ?>
            <?php if (count($low_stock_items) > 0): ?>
            <li><a href="/procurement/index.php"><?= count($low_stock_items) ?> Item<?= count($low_stock_items) > 1 ? 's' : '' ?> Below Min Stock</a> - Procurement needed</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/purchase/add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New PO
        </a>
        <a href="/suppliers/add.php" class="quick-action-btn">
            <div class="action-icon">🏭</div>
            New Supplier
        </a>
        <a href="/procurement/add.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            Procurement Plan
        </a>
        <a href="/purchase/receive.php" class="quick-action-btn">
            <div class="action-icon">📦</div>
            Receive Goods
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Purchase Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">🏭</div>
            <div class="stat-value"><?= $stats['suppliers_active'] ?></div>
            <div class="stat-label">Active Suppliers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📄</div>
            <div class="stat-value"><?= $stats['po_total'] ?></div>
            <div class="stat-label">Total POs</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= $stats['po_pending'] ?></div>
            <div class="stat-label">Pending POs</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['po_approved'] ?></div>
            <div class="stat-label">Approved POs</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['po_received'] ?></div>
            <div class="stat-label">Received POs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?= number_format($stats['po_value_month']) ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent POs -->
        <div class="dashboard-panel">
            <h3>📄 Recent Purchase Orders</h3>
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
                            <td>₹<?= number_format($po['total_amount'], 2) ?></td>
                            <td><span class="status-badge status-<?= strtolower($po['status']) ?>"><?= $po['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pending POs -->
        <div class="dashboard-panel">
            <h3>⏳ Pending Purchase Orders</h3>
            <?php if (empty($pending_pos)): ?>
                <p style="color: #27ae60;">No pending purchase orders. All clear!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Expected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_pos as $po): ?>
                        <tr>
                            <td><a href="/purchase/view.php?po_no=<?= urlencode($po['po_no']) ?>"><?= htmlspecialchars($po['po_no']) ?></a></td>
                            <td><?= htmlspecialchars($po['supplier_name'] ?? 'N/A') ?></td>
                            <td><?= $po['expected_date'] ? date('d M Y', strtotime($po['expected_date'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Top Suppliers -->
        <div class="dashboard-panel">
            <h3>🏆 Top Suppliers</h3>
            <?php if (empty($top_suppliers)): ?>
                <p style="color: #7f8c8d;">No supplier data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>PO Count</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_suppliers as $supplier): ?>
                        <tr>
                            <td><a href="/suppliers/view.php?id=<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></a></td>
                            <td><?= $supplier['po_count'] ?></td>
                            <td>₹<?= number_format($supplier['total_value'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Low Stock Items -->
        <div class="dashboard-panel">
            <h3>⚠️ Low Stock - Procurement Needed</h3>
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
                            <td><?= htmlspecialchars($item['part_no']) ?></td>
                            <td><?= htmlspecialchars(substr($item['description'], 0, 30)) ?></td>
                            <td style="color: #e74c3c;"><?= $item['current_qty'] ?></td>
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
            <div class="action-icon">🏭</div>
            All Suppliers
        </a>
        <a href="/purchase/index.php" class="quick-action-btn">
            <div class="action-icon">📄</div>
            All POs
        </a>
        <a href="/procurement/index.php" class="quick-action-btn">
            <div class="action-icon">🎯</div>
            Procurement
        </a>
    </div>
</div>

</body>
</html>
