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
$stats['leads_qualified'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'qualified'");

// Customer stats
$stats['customers'] = safeCount($pdo, "SELECT COUNT(*) FROM customers");
$stats['customers_active'] = safeCount($pdo, "SELECT COUNT(*) FROM customers WHERE status = 'Active'");

// Quote stats (table is quote_master, not quotations)
$stats['quotes_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'pending'");
$stats['quotes_approved'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'approved'");

// Sales Order stats
$stats['so_open'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'open'");
$stats['so_released'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'released'");

// Invoice stats
$stats['invoices_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'pending'");
$stats['invoices_overdue'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'pending' AND due_date < CURDATE()");

// Work Order stats
$stats['wo_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'Pending'");
$stats['wo_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM work_orders WHERE status = 'In Progress'");

// Purchase Order stats
$stats['po_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending'");
$stats['po_received'] = safeCount($pdo, "SELECT COUNT(*) FROM purchase_orders WHERE status = 'Received'");

// Inventory stats
$stats['low_stock'] = safeCount($pdo, "
    SELECT COUNT(*) FROM inventory i
    JOIN part_master p ON i.part_no = p.part_no
    WHERE i.qty < COALESCE(p.min_stock, 10)
");
$stats['total_parts'] = safeCount($pdo, "SELECT COUNT(*) FROM part_master");

// Project stats
$stats['projects_active'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status IN ('Planning', 'In Progress')");
$stats['projects_delayed'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status != 'Completed'");
$stats['projects_total'] = safeCount($pdo, "SELECT COUNT(*) FROM projects");

// HR stats (if tables exist)
$stats['employees'] = safeCount($pdo, "SELECT COUNT(*) FROM employees");
$stats['attendance_today'] = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURDATE()");

// Supplier stats
$stats['suppliers'] = safeCount($pdo, "SELECT COUNT(*) FROM suppliers");
$stats['suppliers_active'] = safeCount($pdo, "SELECT COUNT(*) FROM suppliers WHERE status = 'Active'");

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

// Pending tasks (if needed)
$pending_tasks = safeQuery($pdo, "
    SELECT * FROM project_tasks
    WHERE status = 'Pending'
    ORDER BY task_start_date
    LIMIT 5
");

// Overdue items
$overdue_invoices = safeQuery($pdo, "
    SELECT invoice_no, customer_id, amount_due, due_date
    FROM invoices
    WHERE payment_status = 'pending' AND due_date < CURDATE()
    ORDER BY due_date
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
            object-fit: contain;
            display: block;
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

        .quick-actions-section {
            margin-bottom: 25px;
        }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 100px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
        }
        .quick-action-btn.sales { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .quick-action-btn.purchase { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .quick-action-btn.inventory { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .quick-action-btn.operations { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .quick-action-btn.projects { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .quick-action-btn.hr { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .quick-action-btn .action-icon { font-size: 1.8em; margin-bottom: 8px; }

        .dashboard-section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }

        .quick-action-category {
            margin-bottom: 25px;
        }

        .stat-grid-detailed {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alerts-panel h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .alerts-panel ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .alerts-panel li {
            padding: 5px 0;
            color: #856404;
        }
        .alerts-panel a {
            color: #004085;
            font-weight: 600;
        }

        body.dark .quick-action-btn {
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        body.dark .quick-action-btn:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        body.dark .dashboard-section-title {
            color: #ecf0f1;
            border-bottom-color: #667eea;
        }
        body.dark .alerts-panel {
            background: #664d03;
            border-left-color: #ffc107;
            color: #ffeaa7;
        }
        body.dark .alerts-panel h4 {
            color: #ffc107;
        }
        body.dark .alerts-panel li {
            color: #ffeaa7;
        }

        .user-welcome {
            text-align: right;
            margin-bottom: 15px;
            color: #7f8c8d;
        }
        .user-welcome strong { color: #2c3e50; }
    </style>
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    } else {
        toggle.textContent = "🌙 Dark Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<div class="content">
    <div class="user-welcome">
        Welcome, <strong><?= htmlspecialchars(getUserName()) ?></strong>
        (<?= ucfirst(getUserRole()) ?>)
        | <a href="logout.php">Logout</a>
    </div>

    <!-- Company Header -->
    <div class="dashboard-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php
                // Handle logo path - convert to proper URL
                $logo_path = $settings['logo_path'];
                // If path doesn't start with /, add it
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
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

    <!-- Alerts & Priorities Panel -->
    <?php if ($stats['invoices_overdue'] > 0 || $stats['low_stock'] > 0 || $stats['projects_delayed'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Attention Required</h4>
        <ul>
            <?php if ($stats['invoices_overdue'] > 0): ?>
            <li>
                <a href="invoices/index.php"><?= $stats['invoices_overdue'] ?> Overdue Invoice<?= $stats['invoices_overdue'] > 1 ? 's' : '' ?></a> - Immediate action needed
            </li>
            <?php endif; ?>
            <?php if ($stats['low_stock'] > 0): ?>
            <li>
                <a href="inventory/index.php"><?= $stats['low_stock'] ?> Items Below Min Stock</a> - Consider reordering
            </li>
            <?php endif; ?>
            <?php if ($stats['projects_delayed'] > 0): ?>
            <li>
                <a href="project_management/index.php"><?= $stats['projects_delayed'] ?> Project<?= $stats['projects_delayed'] > 1 ? 's' : '' ?> Delayed</a> - Review timeline
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Key Metrics Overview -->
    <div class="stats-grid">
        <!-- Sales Module -->
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
            <div class="stat-label">Active Customers</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?= $stats['quotes_pending'] ?></div>
            <div class="stat-label">Pending Quotes</div>
        </div>

        <!-- Purchasing Module -->
        <div class="stat-card warning">
            <div class="stat-icon">🛒</div>
            <div class="stat-value"><?= $stats['po_pending'] ?></div>
            <div class="stat-label">Pending PO</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['suppliers'] ?></div>
            <div class="stat-label">Suppliers</div>
        </div>

        <!-- Inventory Module -->
        <div class="stat-card info">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?= $stats['total_parts'] ?></div>
            <div class="stat-label">Total Parts</div>
        </div>
        <?php if ($stats['low_stock'] > 0): ?>
        <div class="stat-card alert">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?= $stats['low_stock'] ?></div>
            <div class="stat-label">Low Stock Items</div>
        </div>
        <?php endif; ?>

        <!-- Operations Module -->
        <div class="stat-card warning">
            <div class="stat-icon">🔧</div>
            <div class="stat-value"><?= $stats['wo_pending'] ?></div>
            <div class="stat-label">Pending WO</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">⚙️</div>
            <div class="stat-value"><?= $stats['wo_in_progress'] ?></div>
            <div class="stat-label">WO In Progress</div>
        </div>

        <!-- Projects Module -->
        <div class="stat-card info">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?= $stats['projects_active'] ?></div>
            <div class="stat-label">Active Projects</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">📄</div>
            <div class="stat-value"><?= $stats['projects_total'] ?></div>
            <div class="stat-label">Total Projects</div>
        </div>

        <!-- Invoicing Module -->
        <div class="stat-card info">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= $stats['invoices_pending'] ?></div>
            <div class="stat-label">Pending Invoices</div>
        </div>
        <?php if ($stats['invoices_overdue'] > 0): ?>
        <div class="stat-card alert">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?= $stats['invoices_overdue'] ?></div>
            <div class="stat-label">Overdue Invoices</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions by Module -->
    <div class="quick-actions-section">
        <!-- Sales & CRM -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">Sales & CRM</div>
            <div class="quick-actions-grid">
                <a href="crm/index.php" class="quick-action-btn sales">
                    <div class="action-icon">➕</div>
                    New Lead
                </a>
                <a href="customers/index.php" class="quick-action-btn sales">
                    <div class="action-icon">👤</div>
                    Customers
                </a>
                <a href="quotes/index.php" class="quick-action-btn sales">
                    <div class="action-icon">📝</div>
                    Quotations
                </a>
                <a href="proforma/index.php" class="quick-action-btn sales">
                    <div class="action-icon">📄</div>
                    Proforma
                </a>
                <a href="customer_po/index.php" class="quick-action-btn sales">
                    <div class="action-icon">📋</div>
                    Customer PO
                </a>
                <a href="sales_orders/index.php" class="quick-action-btn sales">
                    <div class="action-icon">📦</div>
                    Sales Orders
                </a>
                <a href="invoices/index.php" class="quick-action-btn sales">
                    <div class="action-icon">🧾</div>
                    Invoices
                </a>
                <a href="crm/index.php" class="quick-action-btn sales">
                    <div class="action-icon">📊</div>
                    CRM
                </a>
            </div>
        </div>

        <!-- Purchase & Procurement -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">Purchase & SCM</div>
            <div class="quick-actions-grid">
                <a href="suppliers/index.php" class="quick-action-btn purchase">
                    <div class="action-icon">🏭</div>
                    Suppliers
                </a>
                <a href="purchase/index.php" class="quick-action-btn purchase">
                    <div class="action-icon">🛍️</div>
                    Purchase Orders
                </a>
                <a href="procurement/index.php" class="quick-action-btn purchase">
                    <div class="action-icon">🎯</div>
                    Procurement
                </a>
            </div>
        </div>

        <!-- Inventory & Stock -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">Inventory & Stock</div>
            <div class="quick-actions-grid">
                <a href="part_master/list.php" class="quick-action-btn inventory">
                    <div class="action-icon">⚙️</div>
                    Part Master
                </a>
                <a href="stock_entry/index.php" class="quick-action-btn inventory">
                    <div class="action-icon">➕</div>
                    Stock Entry
                </a>
                <a href="depletion/stock_adjustment.php" class="quick-action-btn inventory">
                    <div class="action-icon">⚖️</div>
                    Stock Adjustment
                </a>
                <a href="inventory/index.php" class="quick-action-btn inventory">
                    <div class="action-icon">📦</div>
                    Current Stock
                </a>
                <a href="reports/monthly.php" class="quick-action-btn inventory">
                    <div class="action-icon">📈</div>
                    Reports
                </a>
            </div>
        </div>

        <!-- Operations & Manufacturing -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">Operations & Manufacturing</div>
            <div class="quick-actions-grid">
                <a href="bom/index.php" class="quick-action-btn operations">
                    <div class="action-icon">🔗</div>
                    BOMs
                </a>
                <a href="work_orders/index.php" class="quick-action-btn operations">
                    <div class="action-icon">🔧</div>
                    Work Orders
                </a>
            </div>
        </div>

        <!-- Projects & Tasks -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">Projects & Tasks</div>
            <div class="quick-actions-grid">
                <a href="project_management/index.php" class="quick-action-btn projects">
                    <div class="action-icon">📂</div>
                    Projects
                </a>
            </div>
        </div>

        <!-- HR & Administration -->
        <div class="quick-action-category">
            <div class="dashboard-section-title">HR & Administration</div>
            <div class="quick-actions-grid">
                <a href="hr/employees.php" class="quick-action-btn hr">
                    <div class="action-icon">👥</div>
                    Employees
                </a>
                <a href="hr/attendance.php" class="quick-action-btn hr">
                    <div class="action-icon">✓</div>
                    Attendance
                </a>
                <a href="hr/payroll.php" class="quick-action-btn hr">
                    <div class="action-icon">💵</div>
                    Payroll
                </a>
                <a href="admin/settings.php" class="quick-action-btn hr">
                    <div class="action-icon">⚙️</div>
                    Settings
                </a>
                <a href="admin/users.php" class="quick-action-btn hr">
                    <div class="action-icon">🔑</div>
                    Users
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
