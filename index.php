<?php
include "db.php";
include "includes/auth.php";
requireLogin();

$settings = getCompanySettings();

// Helper function to safely query count - test
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// Helper function to safely query array
function safeQuery($pdo, $query) {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Fetch dashboard stats
$stats = [];

// CRM Stats
$stats['leads_total'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads");
$stats['leads_hot'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'");

// Customer stats
$stats['customers'] = safeCount($pdo, "SELECT COUNT(*) FROM customers");

// Quote stats (table is quote_master, not quotations)
$stats['quotes_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'pending'");

// Sales Order stats
$stats['so_open'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'open'");
$stats['so_released'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'released'");

// Work Order stats
$stats['wo_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'Pending'");
$stats['wo_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'In Progress'");

// Purchase Order stats
$stats['po_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending'");

// Low stock alerts
$stats['low_stock'] = safeCount($pdo, "
    SELECT COUNT(*) FROM inventory i
    JOIN part_master p ON i.part_no = p.part_no
    WHERE i.qty < COALESCE(p.min_stock, 10)
");

// Recent activities
$activities = safeQuery($pdo, "
    SELECT a.*, u.full_name
    FROM activity_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
");

// Upcoming follow-ups (CRM)
$followups = safeQuery($pdo, "
    SELECT lead_no, company_name, contact_person, next_followup_date, lead_status
    FROM crm_leads
    WHERE next_followup_date IS NOT NULL
      AND next_followup_date >= CURDATE()
    ORDER BY next_followup_date
    LIMIT 5
");

include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
        }
        .dashboard-header img {
            max-height: 80px;
            max-width: 200px;
            background: white;
            padding: 10px;
            border-radius: 8px;
        }
        .dashboard-header h1 {
            margin: 0;
            font-size: 2em;
        }
        .dashboard-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .stat-label {
            color: #7f8c8d;
            margin-top: 5px;
        }
        .stat-card.alert { border-left: 4px solid #e74c3c; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.success { border-left: 4px solid #27ae60; }
        .stat-card.info { border-left: 4px solid #3498db; }

        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        @media (max-width: 900px) {
            .dashboard-row { grid-template-columns: 1fr; }
        }

        .dashboard-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .dashboard-panel h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .dashboard-panel .panel-content {
            padding: 15px 20px;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .activity-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-list li:last-child { border-bottom: none; }
        .activity-list .activity-text { flex: 1; }
        .activity-list .activity-time {
            color: #95a5a6;
            font-size: 0.85em;
        }

        .followup-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .followup-list li {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .followup-list li:last-child { border-bottom: none; }
        .followup-list .followup-date {
            font-weight: bold;
            color: #3498db;
        }
        .followup-list .followup-lead { color: #2c3e50; }
        .followup-list .followup-company { color: #7f8c8d; font-size: 0.9em; }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .quick-link {
            display: block;
            padding: 20px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.2s;
        }
        .quick-link:hover {
            background: #667eea;
            color: white;
        }
        .quick-link .link-icon { font-size: 1.5em; margin-bottom: 8px; }

        .user-welcome {
            text-align: right;
            margin-bottom: 15px;
            color: #7f8c8d;
        }
        .user-welcome strong { color: #2c3e50; }
    </style>
</head>
<body>

<div class="content">
    <div class="user-welcome">
        Welcome, <strong><?= htmlspecialchars(getUserName()) ?></strong>
        (<?= ucfirst(getUserRole()) ?>)
        | <a href="logout.php">Logout</a>
    </div>

    <!-- Company Header -->
    <div class="dashboard-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo">
        <?php endif; ?>
        <div>
            <h1><?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></h1>
            <p>
                <?php
                $address_parts = array_filter([
                    $settings['address_line1'] ?? '',
                    $settings['city'] ?? '',
                    $settings['state'] ?? '',
                    $settings['pincode'] ?? ''
                ]);
                echo htmlspecialchars(implode(', ', $address_parts) ?: 'Configure company details in Admin Settings');
                ?>
            </p>
            <?php if (!empty($settings['phone']) || !empty($settings['email'])): ?>
            <p>
                <?php if (!empty($settings['phone'])): ?>
                    Tel: <?= htmlspecialchars($settings['phone']) ?>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    | <?= htmlspecialchars($settings['email']) ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $stats['leads_total'] ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">🔥</div>
            <div class="stat-value"><?= $stats['leads_hot'] ?></div>
            <div class="stat-label">Hot Leads</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">🏢</div>
            <div class="stat-value"><?= $stats['customers'] ?></div>
            <div class="stat-label">Customers</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?= $stats['quotes_pending'] ?></div>
            <div class="stat-label">Pending Quotes</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['so_open'] ?></div>
            <div class="stat-label">Open Sales Orders</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['so_released'] ?></div>
            <div class="stat-label">Released SO</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">🔧</div>
            <div class="stat-value"><?= $stats['wo_in_progress'] ?></div>
            <div class="stat-label">WO In Progress</div>
        </div>
        <?php if ($stats['low_stock'] > 0): ?>
        <div class="stat-card alert">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?= $stats['low_stock'] ?></div>
            <div class="stat-label">Low Stock Items</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>Quick Actions</h3>
        <div class="panel-content">
            <div class="quick-links">
                <a href="crm/add.php" class="quick-link">
                    <div class="link-icon">➕</div>
                    New Lead
                </a>
                <a href="quotes/add.php" class="quick-link">
                    <div class="link-icon">📝</div>
                    New Quotation
                </a>
                <a href="sales_orders/index.php" class="quick-link">
                    <div class="link-icon">📦</div>
                    Sales Orders
                </a>
                <a href="work_orders/add.php" class="quick-link">
                    <div class="link-icon">🔧</div>
                    New Work Order
                </a>
                <a href="purchase/index.php" class="quick-link">
                    <div class="link-icon">🛒</div>
                    Purchase
                </a>
                <a href="inventory/index.php" class="quick-link">
                    <div class="link-icon">📊</div>
                    Inventory
                </a>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Activity -->
        <div class="dashboard-panel">
            <h3>Recent Activity</h3>
            <div class="panel-content">
                <?php if (empty($activities)): ?>
                    <p style="color: #7f8c8d;">No recent activity</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($activities as $a): ?>
                        <li>
                            <span class="activity-text">
                                <strong><?= htmlspecialchars($a['full_name'] ?? 'System') ?></strong>
                                <?= htmlspecialchars($a['action']) ?>
                                in <?= htmlspecialchars($a['module']) ?>
                            </span>
                            <span class="activity-time">
                                <?= date('d M, H:i', strtotime($a['created_at'])) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Follow-ups -->
        <div class="dashboard-panel">
            <h3>Upcoming Follow-ups</h3>
            <div class="panel-content">
                <?php if (empty($followups)): ?>
                    <p style="color: #7f8c8d;">No upcoming follow-ups</p>
                <?php else: ?>
                    <ul class="followup-list">
                        <?php foreach ($followups as $f): ?>
                        <li>
                            <div class="followup-date">
                                <?= date('d M Y', strtotime($f['next_followup_date'])) ?>
                            </div>
                            <div class="followup-lead">
                                <?= htmlspecialchars($f['lead_no']) ?> -
                                <?= htmlspecialchars($f['contact_person']) ?>
                            </div>
                            <?php if ($f['company_name']): ?>
                            <div class="followup-company">
                                <?= htmlspecialchars($f['company_name']) ?>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top: 15px;">
                        <a href="crm/index.php">View all leads</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Company Info Card -->
    <?php if (hasPermission('settings', 'view')): ?>
    <div class="dashboard-panel">
        <h3>Company Information</h3>
        <div class="panel-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <strong>Address:</strong><br>
                    <?= htmlspecialchars($settings['address_line1'] ?? '-') ?><br>
                    <?php if ($settings['address_line2'] ?? ''): ?>
                        <?= htmlspecialchars($settings['address_line2']) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars(implode(', ', array_filter([
                        $settings['city'] ?? '',
                        $settings['state'] ?? '',
                        $settings['pincode'] ?? ''
                    ]))) ?>
                </div>
                <div>
                    <strong>Contact:</strong><br>
                    Phone: <?= htmlspecialchars($settings['phone'] ?? '-') ?><br>
                    Email: <?= htmlspecialchars($settings['email'] ?? '-') ?><br>
                    Website: <?= htmlspecialchars($settings['website'] ?? '-') ?>
                </div>
                <div>
                    <strong>Tax Info:</strong><br>
                    GSTIN: <?= htmlspecialchars($settings['gstin'] ?? '-') ?><br>
                    PAN: <?= htmlspecialchars($settings['pan'] ?? '-') ?>
                </div>
            </div>
            <?php if (hasPermission('settings', 'edit')): ?>
            <p style="margin-top: 20px;">
                <a href="admin/settings.php" class="btn btn-primary">Edit Company Settings</a>
                <a href="admin/users.php" class="btn btn-secondary">Manage Users</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
