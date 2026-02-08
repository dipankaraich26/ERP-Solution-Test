<?php
session_start();
include "../db.php";

// Check if customer is logged in
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get stats
$stats = [
    'total_invoices' => 0,
    'total_orders' => 0,
    'total_quotations' => 0,
    'total_proforma' => 0
];

// Get invoice stats
try {
    $invStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT i.id) as count
        FROM invoice_master i
        JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ?
    ");
    $invStmt->execute([$customer_id]);
    $stats['total_invoices'] = $invStmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Get sales orders count
try {
    $soStmt = $pdo->prepare("SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE customer_id = ?");
    $soStmt->execute([$customer_id]);
    $stats['total_orders'] = $soStmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Get quotations count
try {
    $quoteStmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE customer_id = ?");
    $quoteStmt->execute([$customer['customer_id']]);
    $stats['total_quotations'] = $quoteStmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Get proforma count
try {
    $piStmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE customer_id = ? AND pi_no IS NOT NULL");
    $piStmt->execute([$customer['customer_id']]);
    $stats['total_proforma'] = $piStmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Get recent invoices
$recent_invoices = [];
try {
    $recentInvStmt = $pdo->prepare("
        SELECT i.id, i.invoice_no, i.invoice_date, i.status,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value
        FROM invoice_master i
        JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ?
        ORDER BY i.invoice_date DESC
        LIMIT 5
    ");
    $recentInvStmt->execute([$customer_id]);
    $recent_invoices = $recentInvStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get recent orders
$recent_orders = [];
try {
    $recentOrderStmt = $pdo->prepare("
        SELECT so_no, status, created_at
        FROM sales_orders
        WHERE customer_id = ?
        GROUP BY so_no
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentOrderStmt->execute([$customer_id]);
    $recent_orders = $recentOrderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Company settings
$company_settings = null;
try {
    $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Portal - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .portal-navbar {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .portal-navbar .brand {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }
        .portal-navbar .brand img {
            height: 40px;
        }
        .portal-navbar .brand h2 {
            font-size: 1.2em;
        }
        .portal-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
        }
        .portal-navbar .user-info span {
            font-weight: 500;
        }
        .portal-navbar .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .portal-navbar .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .portal-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .welcome-banner {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .welcome-banner h1 {
            color: #2c3e50;
            font-size: 1.6em;
        }
        .welcome-banner p {
            color: #7f8c8d;
            margin-top: 5px;
        }
        .welcome-banner .customer-code {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid #3498db;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card .icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .label {
            color: #7f8c8d;
            margin-top: 5px;
        }
        .stat-card.invoices { border-top-color: #27ae60; }
        .stat-card.orders { border-top-color: #3498db; }
        .stat-card.quotations { border-top-color: #f39c12; }
        .stat-card.proforma { border-top-color: #9b59b6; }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .portal-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .portal-panel h3 {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
            font-size: 1.1em;
        }
        .portal-panel .panel-content {
            padding: 20px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }
        .menu-item {
            background: #f8f9fa;
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .menu-item:hover {
            background: white;
            border-color: #11998e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .menu-item .icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .menu-item .title {
            font-weight: 600;
            font-size: 0.9em;
        }

        .recent-list {
            list-style: none;
        }
        .recent-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-list li:last-child {
            border-bottom: none;
        }
        .recent-list .item-info {
            display: flex;
            flex-direction: column;
        }
        .recent-list .item-no {
            font-weight: 600;
            color: #2c3e50;
        }
        .recent-list .item-date {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        .recent-list .item-value {
            font-weight: bold;
            color: #27ae60;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-released, .status-completed { background: #d4edda; color: #155724; }
        .status-pending, .status-draft { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }

        .view-all-link {
            display: block;
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            color: #11998e;
            text-decoration: none;
            font-weight: 600;
            border-top: 1px solid #e9ecef;
        }
        .view-all-link:hover {
            background: #e9ecef;
        }

        .customer-info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .customer-info-box .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .customer-info-box .info-row:last-child {
            border-bottom: none;
        }
        .customer-info-box .info-label {
            width: 100px;
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .customer-info-box .info-value {
            flex: 1;
            font-weight: 500;
            color: #2c3e50;
        }
    </style>
</head>
<body>

<nav class="portal-navbar">
    <div class="brand">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo">
        <?php endif; ?>
        <h2>Customer Portal</h2>
    </div>
    <div class="user-info">
        <span>Welcome, <?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="portal-content">
    <div class="welcome-banner">
        <div>
            <h1>Welcome, <?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></h1>
            <p>Access your invoices, orders, quotations, and more</p>
        </div>
        <div class="customer-code">
            Customer ID: <?= htmlspecialchars($customer['customer_id']) ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card invoices">
            <div class="icon">🧾</div>
            <div class="value"><?= $stats['total_invoices'] ?></div>
            <div class="label">Invoices</div>
        </div>
        <div class="stat-card orders">
            <div class="icon">📦</div>
            <div class="value"><?= $stats['total_orders'] ?></div>
            <div class="label">Orders</div>
        </div>
        <div class="stat-card quotations">
            <div class="icon">📋</div>
            <div class="value"><?= $stats['total_quotations'] ?></div>
            <div class="label">Quotations</div>
        </div>
        <div class="stat-card proforma">
            <div class="icon">📄</div>
            <div class="value"><?= $stats['total_proforma'] ?></div>
            <div class="label">Proforma</div>
        </div>
    </div>

    <div class="portal-panel" style="margin-bottom: 30px;">
        <h3>Quick Access</h3>
        <div class="panel-content">
            <div class="menu-grid">
                <a href="my_invoices.php" class="menu-item">
                    <div class="icon">🧾</div>
                    <div class="title">Invoices</div>
                </a>
                <a href="my_quotations.php" class="menu-item">
                    <div class="icon">📋</div>
                    <div class="title">Quotations</div>
                </a>
                <a href="my_proforma.php" class="menu-item">
                    <div class="icon">📄</div>
                    <div class="title">Proforma</div>
                </a>
                <a href="my_orders.php" class="menu-item">
                    <div class="icon">📦</div>
                    <div class="title">Orders</div>
                </a>
                <a href="my_ledger.php" class="menu-item">
                    <div class="icon">📒</div>
                    <div class="title">Ledger</div>
                </a>
                <a href="my_catalog.php" class="menu-item">
                    <div class="icon">📚</div>
                    <div class="title">Catalog</div>
                </a>
                <a href="my_order_tracking.php" class="menu-item">
                    <div class="icon">📊</div>
                    <div class="title">Order Tracking</div>
                </a>
                <a href="my_dockets.php" class="menu-item">
                    <div class="icon">🚚</div>
                    <div class="title">Dockets</div>
                </a>
                <a href="my_eway_bills.php" class="menu-item">
                    <div class="icon">📃</div>
                    <div class="title">E-Way Bills</div>
                </a>
            </div>
        </div>
    </div>

    <div class="portal-grid">
        <div class="portal-panel">
            <h3>Recent Invoices</h3>
            <?php if (empty($recent_invoices)): ?>
                <div class="panel-content" style="text-align: center; color: #7f8c8d; padding: 30px;">
                    No invoices yet
                </div>
            <?php else: ?>
                <div class="panel-content">
                    <ul class="recent-list">
                        <?php foreach ($recent_invoices as $inv): ?>
                        <li>
                            <div class="item-info">
                                <span class="item-no"><?= htmlspecialchars($inv['invoice_no']) ?></span>
                                <span class="item-date"><?= $inv['invoice_date'] ? date('d M Y', strtotime($inv['invoice_date'])) : '-' ?></span>
                            </div>
                            <span class="item-value"><?= $inv['total_value'] ? number_format($inv['total_value'], 2) : '-' ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a href="my_invoices.php" class="view-all-link">View All Invoices &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="portal-panel">
            <h3>Recent Orders</h3>
            <?php if (empty($recent_orders)): ?>
                <div class="panel-content" style="text-align: center; color: #7f8c8d; padding: 30px;">
                    No orders yet
                </div>
            <?php else: ?>
                <div class="panel-content">
                    <ul class="recent-list">
                        <?php foreach ($recent_orders as $order): ?>
                        <li>
                            <div class="item-info">
                                <span class="item-no"><?= htmlspecialchars($order['so_no']) ?></span>
                                <span class="item-date"><?= $order['created_at'] ? date('d M Y', strtotime($order['created_at'])) : '-' ?></span>
                            </div>
                            <span class="status-badge status-<?= strtolower($order['status'] ?: 'pending') ?>">
                                <?= htmlspecialchars($order['status'] ?: 'Pending') ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <a href="my_orders.php" class="view-all-link">View All Orders &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="portal-panel">
            <h3>Your Information</h3>
            <div class="panel-content">
                <div class="customer-info-box">
                    <div class="info-row">
                        <span class="info-label">Company</span>
                        <span class="info-value"><?= htmlspecialchars($customer['company_name'] ?: '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact</span>
                        <span class="info-value"><?= htmlspecialchars($customer['customer_name'] ?: '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($customer['email'] ?: '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($customer['contact'] ?: '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">GSTIN</span>
                        <span class="info-value"><?= htmlspecialchars($customer['gstin'] ?: '-') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/whatsapp_button.php'; ?>

</body>
</html>
