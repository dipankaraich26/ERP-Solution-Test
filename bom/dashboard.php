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

// Operations Stats
$stats = [];

// BOM stats
$stats['bom_total'] = safeCount($pdo, "SELECT COUNT(DISTINCT bom_no) FROM bom_master");
$stats['bom_active'] = safeCount($pdo, "SELECT COUNT(DISTINCT bom_no) FROM bom_master WHERE status = 'Active'");

// Work Order stats
$stats['wo_total'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders");
$stats['wo_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'Pending'");
$stats['wo_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'In Progress'");
$stats['wo_completed'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'Completed'");
$stats['wo_completed_month'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'Completed' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");

// WO quantity stats
$stats['wo_qty_pending'] = safeCount($pdo, "SELECT COALESCE(SUM(qty), 0) FROM work_orders WHERE status = 'Pending'");
$stats['wo_qty_in_progress'] = safeCount($pdo, "SELECT COALESCE(SUM(qty), 0) FROM work_orders WHERE status = 'In Progress'");

// Recent work orders
$recent_wos = safeQuery($pdo, "
    SELECT wo.*, p.description as part_desc
    FROM work_orders wo
    LEFT JOIN part_master p ON wo.part_no = p.part_no
    ORDER BY wo.created_at DESC
    LIMIT 10
");

// Pending work orders
$pending_wos = safeQuery($pdo, "
    SELECT wo.*, p.description as part_desc
    FROM work_orders wo
    LEFT JOIN part_master p ON wo.part_no = p.part_no
    WHERE wo.status = 'Pending'
    ORDER BY wo.start_date
    LIMIT 10
");

// In progress work orders
$in_progress_wos = safeQuery($pdo, "
    SELECT wo.*, p.description as part_desc
    FROM work_orders wo
    LEFT JOIN part_master p ON wo.part_no = p.part_no
    WHERE wo.status = 'In Progress'
    ORDER BY wo.start_date
    LIMIT 10
");

// Recent BOMs
$recent_boms = safeQuery($pdo, "
    SELECT DISTINCT bm.bom_no, bm.description, bm.status, bm.created_at,
           (SELECT COUNT(*) FROM bom_items bi WHERE bi.bom_no = bm.bom_no) as item_count
    FROM bom_master bm
    ORDER BY bm.created_at DESC
    LIMIT 10
");

// BOMs with most components
$complex_boms = safeQuery($pdo, "
    SELECT bm.bom_no, bm.description,
           COUNT(bi.id) as component_count
    FROM bom_master bm
    LEFT JOIN bom_items bi ON bm.bom_no = bi.bom_no
    GROUP BY bm.bom_no, bm.description
    ORDER BY component_count DESC
    LIMIT 5
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Operations Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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
        .module-header h1 { margin: 0; font-size: 1.8em; color: #1a5f2c; }
        .module-header p { margin: 5px 0 0; opacity: 0.8; color: #1a5f2c; }

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
            border-left: 4px solid #43e97b;
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
            border-bottom: 2px solid #43e97b;
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
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: #1a5f2c;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(67, 233, 123, 0.4);
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
        .status-in-progress, .status-active { background: #e3f2fd; color: #1565c0; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }

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
            <h1>Operations & Manufacturing</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['wo_pending'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Production Alerts</h4>
        <ul>
            <?php if ($stats['wo_pending'] > 0): ?>
            <li><a href="/work_orders/index.php?status=Pending"><?= $stats['wo_pending'] ?> Work Order<?= $stats['wo_pending'] > 1 ? 's' : '' ?> Pending</a> - Ready to start</li>
            <?php endif; ?>
            <?php if ($stats['wo_in_progress'] > 0): ?>
            <li><a href="/work_orders/index.php?status=In Progress"><?= $stats['wo_in_progress'] ?> Work Order<?= $stats['wo_in_progress'] > 1 ? 's' : '' ?> In Progress</a> - Monitor production</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/work_orders/add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New Work Order
        </a>
        <a href="/bom/add.php" class="quick-action-btn">
            <div class="action-icon">🔗</div>
            Create BOM
        </a>
        <a href="/work_orders/index.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            All Work Orders
        </a>
        <a href="/bom/index.php" class="quick-action-btn">
            <div class="action-icon">📄</div>
            All BOMs
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Production Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">🔗</div>
            <div class="stat-value"><?= $stats['bom_total'] ?></div>
            <div class="stat-label">Total BOMs</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['bom_active'] ?></div>
            <div class="stat-label">Active BOMs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔧</div>
            <div class="stat-value"><?= $stats['wo_total'] ?></div>
            <div class="stat-label">Total Work Orders</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= $stats['wo_pending'] ?></div>
            <div class="stat-label">Pending WO</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">⚙️</div>
            <div class="stat-value"><?= $stats['wo_in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">🎉</div>
            <div class="stat-value"><?= $stats['wo_completed_month'] ?></div>
            <div class="stat-label">Completed (Month)</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Pending Work Orders -->
        <div class="dashboard-panel">
            <h3>⏳ Pending Work Orders</h3>
            <?php if (empty($pending_wos)): ?>
                <p style="color: #27ae60;">No pending work orders. All clear!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>WO #</th>
                            <th>Part</th>
                            <th>Qty</th>
                            <th>Start</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_wos as $wo): ?>
                        <tr>
                            <td><a href="/work_orders/view.php?wo_no=<?= urlencode($wo['wo_no']) ?>"><?= htmlspecialchars($wo['wo_no']) ?></a></td>
                            <td><?= htmlspecialchars(substr($wo['part_desc'] ?? $wo['part_no'], 0, 20)) ?></td>
                            <td><?= $wo['qty'] ?></td>
                            <td><?= $wo['start_date'] ? date('d M', strtotime($wo['start_date'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- In Progress Work Orders -->
        <div class="dashboard-panel">
            <h3>⚙️ In Progress</h3>
            <?php if (empty($in_progress_wos)): ?>
                <p style="color: #7f8c8d;">No work orders in progress.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>WO #</th>
                            <th>Part</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($in_progress_wos as $wo): ?>
                        <tr>
                            <td><a href="/work_orders/view.php?wo_no=<?= urlencode($wo['wo_no']) ?>"><?= htmlspecialchars($wo['wo_no']) ?></a></td>
                            <td><?= htmlspecialchars(substr($wo['part_desc'] ?? $wo['part_no'], 0, 20)) ?></td>
                            <td><?= $wo['qty'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent BOMs -->
        <div class="dashboard-panel">
            <h3>📄 Recent BOMs</h3>
            <?php if (empty($recent_boms)): ?>
                <p style="color: #7f8c8d;">No BOMs found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>BOM #</th>
                            <th>Description</th>
                            <th>Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_boms as $bom): ?>
                        <tr>
                            <td><a href="/bom/view.php?bom_no=<?= urlencode($bom['bom_no']) ?>"><?= htmlspecialchars($bom['bom_no']) ?></a></td>
                            <td><?= htmlspecialchars(substr($bom['description'], 0, 20)) ?></td>
                            <td><?= $bom['item_count'] ?></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $bom['status'])) ?>"><?= $bom['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Complex BOMs -->
        <div class="dashboard-panel">
            <h3>🔗 BOMs with Most Components</h3>
            <?php if (empty($complex_boms)): ?>
                <p style="color: #7f8c8d;">No BOM data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>BOM #</th>
                            <th>Description</th>
                            <th>Components</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complex_boms as $bom): ?>
                        <tr>
                            <td><a href="/bom/view.php?bom_no=<?= urlencode($bom['bom_no']) ?>"><?= htmlspecialchars($bom['bom_no']) ?></a></td>
                            <td><?= htmlspecialchars(substr($bom['description'], 0, 25)) ?></td>
                            <td><?= $bom['component_count'] ?></td>
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
        <a href="/bom/index.php" class="quick-action-btn">
            <div class="action-icon">🔗</div>
            Bill of Materials
        </a>
        <a href="/work_orders/index.php" class="quick-action-btn">
            <div class="action-icon">🔧</div>
            Work Orders
        </a>
    </div>
</div>

</body>
</html>
