<?php
/**
 * CEO / Chairman Dashboard - Print Version
 * Generates a print-friendly PDF format of the executive dashboard
 */
include "db.php";
include "includes/auth.php";

// Get company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Date ranges
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$thisYearStart = date('Y-01-01');

// ============ SALES METRICS ============
try {
    $revenueThisMonth = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $revenueThisMonth->execute([$thisMonthStart, $thisMonthEnd]);
    $salesThisMonth = $revenueThisMonth->fetchColumn();
} catch (Exception $e) { $salesThisMonth = 0; }

try {
    $revenueLastMonth = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $revenueLastMonth->execute([$lastMonthStart, $lastMonthEnd]);
    $salesLastMonth = $revenueLastMonth->fetchColumn();
} catch (Exception $e) { $salesLastMonth = 0; }

try {
    $revenueYTD = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
    ");
    $revenueYTD->execute([$thisYearStart]);
    $salesYTD = $revenueYTD->fetchColumn();
} catch (Exception $e) { $salesYTD = 0; }

$salesGrowth = $salesLastMonth > 0 ? round((($salesThisMonth - $salesLastMonth) / $salesLastMonth) * 100, 1) : 0;

try {
    $pendingInvoices = $pdo->query("
        SELECT COUNT(DISTINCT im.id) as count, COALESCE(SUM(qi.total_amount), 0) as value
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'pending' OR im.status = 'draft'
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $pendingInvoices = ['count' => 0, 'value' => 0]; }

try {
    $ordersThisMonth = $pdo->prepare("SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE sales_date BETWEEN ? AND ?");
    $ordersThisMonth->execute([$thisMonthStart, $thisMonthEnd]);
    $totalOrdersThisMonth = $ordersThisMonth->fetchColumn();
} catch (Exception $e) { $totalOrdersThisMonth = 0; }

try {
    $quotesThisMonth = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE created_at >= ?");
    $quotesThisMonth->execute([$thisMonthStart]);
    $totalQuotes = $quotesThisMonth->fetchColumn();
    $convertedQuotes = $pdo->prepare("SELECT COUNT(DISTINCT qm.id) FROM quote_master qm INNER JOIN sales_orders so ON so.linked_quote_id = qm.id WHERE qm.created_at >= ?");
    $convertedQuotes->execute([$thisMonthStart]);
    $convertedCount = $convertedQuotes->fetchColumn();
} catch (Exception $e) { $totalQuotes = 0; $convertedCount = 0; }
$conversionRate = $totalQuotes > 0 ? round(($convertedCount / $totalQuotes) * 100, 1) : 0;

// ============ CRM METRICS ============
try { $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn(); } catch (Exception $e) { $totalCustomers = 0; }
try { $newCustomersMonth = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_at >= ?"); $newCustomersMonth->execute([$thisMonthStart]); $newCustomers = $newCustomersMonth->fetchColumn(); } catch (Exception $e) { $newCustomers = 0; }
try { $totalLeads = $pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn(); } catch (Exception $e) { $totalLeads = 0; }
try { $hotLeads = $pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'")->fetchColumn(); } catch (Exception $e) { $hotLeads = 0; }
try { $convertedLeads = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'converted' AND updated_at >= ?"); $convertedLeads->execute([$thisMonthStart]); $leadsConverted = $convertedLeads->fetchColumn(); } catch (Exception $e) { $leadsConverted = 0; }

// ============ SERVICE METRICS ============
try { $openComplaints = $pdo->query("SELECT COUNT(*) FROM service_complaints WHERE status IN ('open', 'in_progress', 'pending_parts')")->fetchColumn(); } catch (Exception $e) { $openComplaints = 0; }
try { $resolvedMonth = $pdo->prepare("SELECT COUNT(*) FROM service_complaints WHERE status = 'resolved' AND resolved_date >= ?"); $resolvedMonth->execute([$thisMonthStart]); $complaintsResolved = $resolvedMonth->fetchColumn(); } catch (Exception $e) { $complaintsResolved = 0; }
try { $avgResolution = $pdo->query("SELECT AVG(DATEDIFF(resolved_date, complaint_date)) FROM service_complaints WHERE status = 'resolved' AND resolved_date IS NOT NULL")->fetchColumn(); $avgResolutionDays = $avgResolution ? round($avgResolution, 1) : 0; } catch (Exception $e) { $avgResolutionDays = 0; }
try { $pendingInstallations = $pdo->query("SELECT COUNT(*) FROM installations WHERE status IN ('scheduled', 'in_progress')")->fetchColumn(); } catch (Exception $e) { $pendingInstallations = 0; }

// ============ INVENTORY METRICS ============
try { $stockValue = $pdo->query("SELECT COALESCE(SUM(i.qty * p.rate), 0) FROM inventory i JOIN part_master p ON i.part_no = p.part_no")->fetchColumn(); } catch (Exception $e) { $stockValue = 0; }
try { $lowStockItems = $pdo->query("SELECT COUNT(*) FROM inventory i JOIN part_master p ON i.part_no = p.part_no WHERE i.qty < COALESCE(p.min_stock, 10)")->fetchColumn(); } catch (Exception $e) { $lowStockItems = 0; }
try { $outOfStock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE qty = 0")->fetchColumn(); } catch (Exception $e) { $outOfStock = 0; }

// ============ PURCHASE METRICS ============
try { $pendingPOs = $pdo->query("SELECT COUNT(DISTINCT po_no), COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending', 'Approved')")->fetch(PDO::FETCH_NUM); $pendingPOCount = $pendingPOs[0] ?? 0; $pendingPOValue = $pendingPOs[1] ?? 0; } catch (Exception $e) { $pendingPOCount = 0; $pendingPOValue = 0; }
try { $purchaseMonth = $pdo->prepare("SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('received', 'Received', 'closed', 'Closed') AND purchase_date BETWEEN ? AND ?"); $purchaseMonth->execute([$thisMonthStart, $thisMonthEnd]); $purchaseThisMonth = $purchaseMonth->fetchColumn(); } catch (Exception $e) { $purchaseThisMonth = 0; }

// ============ WORK ORDERS METRICS ============
try { $activeWO = $pdo->query("SELECT COUNT(*) FROM work_orders WHERE status IN ('pending', 'in_progress', 'Pending', 'In Progress')")->fetchColumn(); } catch (Exception $e) { $activeWO = 0; }
try { $completedWO = $pdo->prepare("SELECT COUNT(*) FROM work_orders WHERE (status = 'completed' OR status = 'Completed') AND updated_at >= ?"); $completedWO->execute([$thisMonthStart]); $woCompleted = $completedWO->fetchColumn(); } catch (Exception $e) { $woCompleted = 0; }

// ============ HR METRICS ============
try { $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn(); } catch (Exception $e) { $totalEmployees = 0; }
try { $todayAttendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND status = 'Present'"); $todayAttendance->execute([$today]); $presentToday = $todayAttendance->fetchColumn(); } catch (Exception $e) { $presentToday = 0; }
$attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;

// ============ MARKETING METRICS ============
try { $activeCampaigns = $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'active'")->fetchColumn(); } catch (Exception $e) { $activeCampaigns = 0; }

// ============ TOP CUSTOMERS ============
try {
    $topCustomers = $pdo->prepare("
        SELECT c.company_name, c.customer_name, COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM customers c
        JOIN invoice_master im ON c.id = im.customer_id
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
        GROUP BY c.id ORDER BY revenue DESC LIMIT 5
    ");
    $topCustomers->execute([$thisYearStart]);
    $topCustomersList = $topCustomers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $topCustomersList = []; }

// ============ TOP PRODUCTS ============
try {
    $topProducts = $pdo->prepare("
        SELECT qi.part_name, SUM(qi.qty) as qty_sold, SUM(qi.total_amount) as revenue
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        WHERE qm.created_at >= ?
        GROUP BY qi.part_no, qi.part_name ORDER BY revenue DESC LIMIT 5
    ");
    $topProducts->execute([$thisYearStart]);
    $topProductsList = $topProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $topProductsList = []; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Executive Dashboard Report - <?= date('d M Y') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: white;
            padding: 20px;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        .company-info p {
            color: #666;
            font-size: 10px;
        }
        .report-title {
            text-align: right;
        }
        .report-title h2 {
            font-size: 16px;
            color: #667eea;
            margin-bottom: 3px;
        }
        .report-title .date {
            font-size: 10px;
            color: #666;
        }
        .logo-img {
            max-height: 50px;
            max-width: 120px;
            object-fit: contain;
        }

        /* KPI Section */
        .kpi-section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
            margin-bottom: 12px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        .kpi-box {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }
        .kpi-box .value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
        }
        .kpi-box .label {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        .kpi-box.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .kpi-box.highlight .value,
        .kpi-box.highlight .label { color: white; }
        .kpi-box.success { border-left: 3px solid #27ae60; }
        .kpi-box.warning { border-left: 3px solid #f39c12; }
        .kpi-box.danger { border-left: 3px solid #e74c3c; }

        /* Module Cards */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .module-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 12px;
        }
        .module-card h4 {
            font-size: 11px;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .module-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            text-align: center;
            padding: 5px;
            background: #f9f9f9;
            border-radius: 3px;
        }
        .stat-item .val {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-item .lbl {
            font-size: 8px;
            color: #888;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .data-table th, .data-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .data-table th {
            background: #f5f5f5;
            font-weight: bold;
            color: #2c3e50;
        }
        .data-table tr:nth-child(even) {
            background: #fafafa;
        }
        .data-table .number {
            text-align: right;
        }

        /* Two Column Layout */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Footer */
        .report-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #888;
        }

        /* Print Button */
        .print-actions {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .btn-print {
            display: inline-block;
            padding: 10px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            font-size: 13px;
            margin: 0 5px;
        }
        .btn-print:hover { opacity: 0.9; }
        .btn-back {
            display: inline-block;
            padding: 10px 25px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            margin: 0 5px;
        }

        /* Print Styles */
        @media print {
            body { padding: 0; }
            .print-actions { display: none; }
            .print-container { max-width: 100%; }
            .kpi-grid { grid-template-columns: repeat(5, 1fr); }
            .module-grid { grid-template-columns: repeat(3, 1fr); }
            .kpi-box.highlight {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
</head>
<body>

<div class="print-container">
    <!-- Print Actions -->
    <div class="print-actions">
        <button onclick="window.print()" class="btn-print">Print / Save as PDF</button>
        <a href="ceo_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <div class="company-info" style="display: flex; align-items: center; gap: 15px;">
            <?php if (!empty($settings['logo_path'])): ?>
                <?php $logo_path = $settings['logo_path']; if (!preg_match('~^(https?:|/)~', $logo_path)) { $logo_path = '/' . $logo_path; } ?>
                <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" class="logo-img" onerror="this.style.display='none'">
            <?php endif; ?>
            <div>
                <h1><?= htmlspecialchars($settings['company_name'] ?? 'Company Name') ?></h1>
                <p><?= htmlspecialchars($settings['address1'] ?? '') ?> <?= htmlspecialchars($settings['city'] ?? '') ?></p>
            </div>
        </div>
        <div class="report-title">
            <h2>EXECUTIVE DASHBOARD</h2>
            <div class="date">Report Date: <?= date('d F Y') ?></div>
            <div class="date">Period: <?= date('F Y') ?></div>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="kpi-section">
        <div class="section-title">Key Performance Indicators</div>
        <div class="kpi-grid">
            <div class="kpi-box highlight">
                <div class="value"><?= number_format($salesThisMonth) ?></div>
                <div class="label">Revenue This Month</div>
            </div>
            <div class="kpi-box highlight">
                <div class="value"><?= number_format($salesYTD) ?></div>
                <div class="label">Revenue YTD</div>
            </div>
            <div class="kpi-box <?= $salesGrowth >= 0 ? 'success' : 'danger' ?>">
                <div class="value"><?= $salesGrowth >= 0 ? '+' : '' ?><?= $salesGrowth ?>%</div>
                <div class="label">Growth vs Last Month</div>
            </div>
            <div class="kpi-box success">
                <div class="value"><?= $conversionRate ?>%</div>
                <div class="label">Quote Conversion</div>
            </div>
            <div class="kpi-box">
                <div class="value"><?= number_format($totalCustomers) ?></div>
                <div class="label">Active Customers</div>
            </div>
        </div>
    </div>

    <!-- Module Summary -->
    <div class="kpi-section">
        <div class="section-title">Module Summary</div>
        <div class="module-grid">
            <!-- Sales -->
            <div class="module-card">
                <h4>Sales & Revenue</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= $totalOrdersThisMonth ?></div><div class="lbl">Orders (Month)</div></div>
                    <div class="stat-item"><div class="val"><?= $pendingInvoices['count'] ?></div><div class="lbl">Pending Invoices</div></div>
                    <div class="stat-item"><div class="val"><?= number_format($pendingInvoices['value']/100000, 1) ?>L</div><div class="lbl">Pending Value</div></div>
                    <div class="stat-item"><div class="val"><?= $totalQuotes ?></div><div class="lbl">Quotes Generated</div></div>
                </div>
            </div>

            <!-- CRM -->
            <div class="module-card">
                <h4>CRM & Leads</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= $totalLeads ?></div><div class="lbl">Total Leads</div></div>
                    <div class="stat-item"><div class="val"><?= $hotLeads ?></div><div class="lbl">Hot Leads</div></div>
                    <div class="stat-item"><div class="val"><?= $leadsConverted ?></div><div class="lbl">Converted (Month)</div></div>
                    <div class="stat-item"><div class="val"><?= $newCustomers ?></div><div class="lbl">New Customers</div></div>
                </div>
            </div>

            <!-- Service -->
            <div class="module-card">
                <h4>Service & Support</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= $openComplaints ?></div><div class="lbl">Open Complaints</div></div>
                    <div class="stat-item"><div class="val"><?= $complaintsResolved ?></div><div class="lbl">Resolved (Month)</div></div>
                    <div class="stat-item"><div class="val"><?= $avgResolutionDays ?> days</div><div class="lbl">Avg Resolution</div></div>
                    <div class="stat-item"><div class="val"><?= $pendingInstallations ?></div><div class="lbl">Pending Install</div></div>
                </div>
            </div>

            <!-- Inventory -->
            <div class="module-card">
                <h4>Inventory</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= number_format($stockValue/100000, 1) ?>L</div><div class="lbl">Stock Value</div></div>
                    <div class="stat-item"><div class="val"><?= $lowStockItems ?></div><div class="lbl">Low Stock Items</div></div>
                    <div class="stat-item"><div class="val"><?= $outOfStock ?></div><div class="lbl">Out of Stock</div></div>
                    <div class="stat-item"><div class="val"><?= $activeWO ?></div><div class="lbl">Active Work Orders</div></div>
                </div>
            </div>

            <!-- Purchase -->
            <div class="module-card">
                <h4>Purchase</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= $pendingPOCount ?></div><div class="lbl">Pending POs</div></div>
                    <div class="stat-item"><div class="val"><?= number_format($pendingPOValue/100000, 1) ?>L</div><div class="lbl">Pending Value</div></div>
                    <div class="stat-item"><div class="val"><?= number_format($purchaseThisMonth/100000, 1) ?>L</div><div class="lbl">Purchased (Month)</div></div>
                    <div class="stat-item"><div class="val"><?= $woCompleted ?></div><div class="lbl">WO Completed</div></div>
                </div>
            </div>

            <!-- HR -->
            <div class="module-card">
                <h4>Human Resources</h4>
                <div class="module-stats">
                    <div class="stat-item"><div class="val"><?= $totalEmployees ?></div><div class="lbl">Total Employees</div></div>
                    <div class="stat-item"><div class="val"><?= $presentToday ?></div><div class="lbl">Present Today</div></div>
                    <div class="stat-item"><div class="val"><?= $attendanceRate ?>%</div><div class="lbl">Attendance Rate</div></div>
                    <div class="stat-item"><div class="val"><?= $activeCampaigns ?></div><div class="lbl">Active Campaigns</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="two-col">
        <div>
            <div class="section-title">Top Customers (YTD)</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th class="number">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCustomersList as $i => $cust): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($cust['company_name'] ?: $cust['customer_name']) ?></td>
                        <td class="number"><?= number_format($cust['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topCustomersList)): ?>
                    <tr><td colspan="3" style="text-align: center; color: #999;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <div class="section-title">Top Products (YTD)</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="number">Qty</th>
                        <th class="number">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProductsList as $i => $prod): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars(substr($prod['part_name'], 0, 25)) ?></td>
                        <td class="number"><?= number_format($prod['qty_sold']) ?></td>
                        <td class="number"><?= number_format($prod['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topProductsList)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #999;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alerts Summary -->
    <div class="kpi-section">
        <div class="section-title">Attention Required</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Issue</th>
                    <th class="number">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lowStockItems > 0): ?>
                <tr><td>Inventory</td><td>Items below minimum stock level</td><td class="number"><?= $lowStockItems ?></td></tr>
                <?php endif; ?>
                <?php if ($outOfStock > 0): ?>
                <tr><td>Inventory</td><td>Items out of stock</td><td class="number"><?= $outOfStock ?></td></tr>
                <?php endif; ?>
                <?php if ($openComplaints > 5): ?>
                <tr><td>Service</td><td>Open complaints pending</td><td class="number"><?= $openComplaints ?></td></tr>
                <?php endif; ?>
                <?php if ($pendingPOCount > 0): ?>
                <tr><td>Purchase</td><td>Purchase orders awaiting action</td><td class="number"><?= $pendingPOCount ?></td></tr>
                <?php endif; ?>
                <?php if ($hotLeads > 0): ?>
                <tr><td>CRM</td><td>Hot leads requiring follow-up</td><td class="number"><?= $hotLeads ?></td></tr>
                <?php endif; ?>
                <?php if ($pendingInstallations > 0): ?>
                <tr><td>Service</td><td>Installations scheduled/in progress</td><td class="number"><?= $pendingInstallations ?></td></tr>
                <?php endif; ?>
                <?php if ($lowStockItems == 0 && $outOfStock == 0 && $openComplaints <= 5 && $pendingPOCount == 0 && $hotLeads == 0 && $pendingInstallations == 0): ?>
                <tr><td colspan="3" style="text-align: center; color: #27ae60;">All clear - No urgent items requiring attention</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Report Footer -->
    <div class="report-footer">
        <p>This report was generated on <?= date('d F Y \a\t h:i A') ?></p>
        <p>© <?= date('Y') ?> <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?> - Executive Dashboard Report</p>
    </div>
</div>

</body>
</html>
