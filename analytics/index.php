<?php
/**
 * AI-Driven Analytics & Decision Support Dashboard
 * Covers: Sales, CRM, Inventory, Purchase, Operations
 */
include "../db.php";
include "../includes/auth.php";
include "../includes/sidebar.php";

// Date ranges
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$thisYearStart = date('Y-01-01');
$last90Days = date('Y-m-d', strtotime('-90 days'));
$last30Days = date('Y-m-d', strtotime('-30 days'));

// ============ SALES INTELLIGENCE ============
// Revenue this month
$salesThisMonth = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$thisMonthStart, $thisMonthEnd]);
    $salesThisMonth = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// Revenue last month
$salesLastMonth = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
    $salesLastMonth = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

$salesGrowth = $salesLastMonth > 0 ? round((($salesThisMonth - $salesLastMonth) / $salesLastMonth) * 100, 1) : 0;

// Revenue YTD
$salesYTD = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
    ");
    $stmt->execute([$thisYearStart]);
    $salesYTD = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// Monthly revenue trend (last 6 months)
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t', strtotime("-$i months"));
    $mn = date('M', strtotime("-$i months"));
    $rev = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(qi.total_amount), 0)
            FROM invoice_master im
            LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$ms, $me]);
        $rev = (float)$stmt->fetchColumn();
    } catch (Exception $e) {}
    $monthlyRevenue[] = ['month' => $mn, 'revenue' => $rev];
}

// Top 5 customers by revenue (YTD)
$topCustomers = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.company_name, COALESCE(SUM(qi.total_amount), 0) as revenue,
               COUNT(DISTINCT im.id) as invoice_count
        FROM customers c
        JOIN invoice_master im ON c.id = im.customer_id
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
        GROUP BY c.id ORDER BY revenue DESC LIMIT 5
    ");
    $stmt->execute([$thisYearStart]);
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Top 5 products by revenue (YTD)
$topProducts = [];
try {
    $stmt = $pdo->prepare("
        SELECT qi.part_name, SUM(qi.qty) as qty_sold, SUM(qi.total_amount) as revenue
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        WHERE qm.created_at >= ?
        GROUP BY qi.part_no, qi.part_name ORDER BY revenue DESC LIMIT 5
    ");
    $stmt->execute([$thisYearStart]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Quote conversion rate
$totalQuotes = 0; $convertedCount = 0;
try {
    $totalQuotes = (int)$pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE created_at >= ?")->execute([$thisYearStart]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE created_at >= ?");
    $stmt->execute([$thisYearStart]);
    $totalQuotes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT qm.id) FROM quote_master qm INNER JOIN sales_orders so ON so.linked_quote_id = qm.id WHERE qm.created_at >= ?");
    $stmt->execute([$thisYearStart]);
    $convertedCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
$conversionRate = $totalQuotes > 0 ? round(($convertedCount / $totalQuotes) * 100, 1) : 0;

// Average order value
$avgOrderValue = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(order_total), 0) FROM (
            SELECT so.so_no, SUM(qi.total_amount) as order_total
            FROM sales_orders so
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE so.sales_date >= ?
            GROUP BY so.so_no
        ) t
    ");
    $stmt->execute([$thisYearStart]);
    $avgOrderValue = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// Sales orders by month (last 6 months)
$monthlyOrders = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t', strtotime("-$i months"));
    $mn = date('M', strtotime("-$i months"));
    $cnt = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE sales_date BETWEEN ? AND ?");
        $stmt->execute([$ms, $me]);
        $cnt = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
    $monthlyOrders[] = ['month' => $mn, 'count' => $cnt];
}

// ============ CRM INTELLIGENCE ============
$totalLeads = 0; $hotLeads = 0; $warmLeads = 0; $coldLeads = 0; $convertedLeads = 0; $lostLeads = 0;
try {
    $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
    $hotLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'")->fetchColumn();
    $warmLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'warm'")->fetchColumn();
    $coldLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'cold'")->fetchColumn();
    $convertedLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'converted'")->fetchColumn();
    $lostLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'lost'")->fetchColumn();
} catch (Exception $e) {}

$leadConversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

// Overdue follow-ups
$overdueFollowups = 0;
try {
    $overdueFollowups = (int)$pdo->prepare("
        SELECT COUNT(*) FROM crm_leads WHERE next_followup_date < ? AND lead_status NOT IN ('converted', 'lost', 'dead')
    ")->execute([$today]) ? 0 : 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE next_followup_date < ? AND lead_status NOT IN ('converted', 'lost', 'dead')");
    $stmt->execute([$today]);
    $overdueFollowups = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// Leads by source (for pie chart)
$leadsBySource = [];
try {
    $leadsBySource = $pdo->query("
        SELECT COALESCE(NULLIF(customer_type, ''), 'Unknown') as source, COUNT(*) as cnt
        FROM crm_leads GROUP BY source ORDER BY cnt DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ INVENTORY INTELLIGENCE ============
$stockValue = 0; $totalSKUs = 0; $lowStockItems = 0; $outOfStock = 0;
try {
    $stockValue = (float)$pdo->query("SELECT COALESCE(SUM(i.qty * p.rate), 0) FROM inventory i JOIN part_master p ON i.part_no = p.part_no")->fetchColumn();
    $totalSKUs = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE qty > 0")->fetchColumn();
    $lowStockItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory i JOIN part_master p ON i.part_no = p.part_no WHERE i.qty < COALESCE(p.min_stock, 10) AND i.qty > 0")->fetchColumn();
    $outOfStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE qty = 0")->fetchColumn();
} catch (Exception $e) {}

// Slow-moving items (no depletion in 90 days)
$slowMoving = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.part_no, p.part_name, i.qty, p.rate, (i.qty * p.rate) as value,
               MAX(d.issue_date) as last_moved
        FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
        LEFT JOIN depletion d ON d.part_no = i.part_no
        WHERE i.qty > 0
        GROUP BY p.part_no, p.part_name, i.qty, p.rate
        HAVING last_moved IS NULL OR last_moved < ?
        ORDER BY value DESC LIMIT 10
    ");
    $stmt->execute([$last90Days]);
    $slowMoving = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$slowMovingValue = array_sum(array_column($slowMoving, 'value'));

// Critical reorder items (below min stock, sorted by urgency)
$criticalReorder = [];
try {
    $criticalReorder = $pdo->query("
        SELECT p.part_no, p.part_name, i.qty as current_stock, COALESCE(p.min_stock, 10) as min_stock,
               (COALESCE(p.min_stock, 10) - i.qty) as shortage
        FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
        WHERE i.qty < COALESCE(p.min_stock, 10)
        ORDER BY shortage DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Inventory value by top categories (top parts by value)
$invByValue = [];
try {
    $invByValue = $pdo->query("
        SELECT p.part_name, (i.qty * p.rate) as value
        FROM inventory i JOIN part_master p ON i.part_no = p.part_no
        WHERE i.qty > 0 ORDER BY value DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ PURCHASE INTELLIGENCE ============
$pendingPOCount = 0; $pendingPOValue = 0; $purchaseThisMonth = 0;
try {
    $r = $pdo->query("SELECT COUNT(DISTINCT po_no), COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending', 'Approved')")->fetch(PDO::FETCH_NUM);
    $pendingPOCount = $r[0] ?? 0; $pendingPOValue = $r[1] ?? 0;
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('received', 'Received', 'closed', 'Closed') AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$thisMonthStart, $thisMonthEnd]);
    $purchaseThisMonth = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// Monthly purchase trend
$monthlyPurchase = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t', strtotime("-$i months"));
    $mn = date('M', strtotime("-$i months"));
    $val = 0;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE purchase_date BETWEEN ? AND ?");
        $stmt->execute([$ms, $me]);
        $val = (float)$stmt->fetchColumn();
    } catch (Exception $e) {}
    $monthlyPurchase[] = ['month' => $mn, 'value' => $val];
}

// Top suppliers by spend (YTD)
$topSuppliers = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.supplier_name, COALESCE(SUM(po.qty * po.rate), 0) as spend, COUNT(DISTINCT po.po_no) as po_count
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.purchase_date >= ?
        GROUP BY s.id ORDER BY spend DESC LIMIT 5
    ");
    $stmt->execute([$thisYearStart]);
    $topSuppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============ OPERATIONS / WORK ORDERS ============
$woOpen = 0; $woReleased = 0; $woInProgress = 0; $woCompleted = 0; $woClosed = 0;
try {
    $woOpen = (int)$pdo->query("SELECT COUNT(*) FROM work_orders WHERE status IN ('open','created')")->fetchColumn();
    $woReleased = (int)$pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'released'")->fetchColumn();
    $woInProgress = (int)$pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'in_progress'")->fetchColumn();
    $woCompleted = (int)$pdo->query("SELECT COUNT(*) FROM work_orders WHERE status IN ('completed','qc_approval')")->fetchColumn();
    $woClosed = (int)$pdo->query("SELECT COUNT(*) FROM work_orders WHERE status = 'closed'")->fetchColumn();
} catch (Exception $e) {}

// ============ AI-DRIVEN INSIGHTS ============
$insights = [];

// Sales insights
if ($salesGrowth > 15) {
    $insights[] = ['type' => 'success', 'category' => 'Sales', 'title' => 'Strong Revenue Growth', 'message' => "Revenue grew {$salesGrowth}% vs last month. Consider scaling production capacity to sustain momentum."];
} elseif ($salesGrowth < -10) {
    $insights[] = ['type' => 'danger', 'category' => 'Sales', 'title' => 'Revenue Decline Alert', 'message' => "Revenue dropped " . abs($salesGrowth) . "% vs last month. Review pipeline and increase customer outreach."];
} elseif ($salesGrowth >= -10 && $salesGrowth <= 0) {
    $insights[] = ['type' => 'warning', 'category' => 'Sales', 'title' => 'Flat Revenue Trend', 'message' => "Revenue is flat or slightly down ({$salesGrowth}%). Focus on converting open quotes and upselling to existing customers."];
}

if ($conversionRate < 20 && $totalQuotes > 5) {
    $insights[] = ['type' => 'warning', 'category' => 'Sales', 'title' => 'Low Quote Conversion', 'message' => "Only {$conversionRate}% of quotes convert to orders. Review pricing strategy and follow-up process."];
} elseif ($conversionRate > 50) {
    $insights[] = ['type' => 'success', 'category' => 'Sales', 'title' => 'Excellent Conversion Rate', 'message' => "{$conversionRate}% quote-to-order conversion. Strong sales execution — consider expanding target market."];
}

// CRM insights
if ($overdueFollowups > 5) {
    $insights[] = ['type' => 'danger', 'category' => 'CRM', 'title' => 'Overdue Follow-ups', 'message' => "{$overdueFollowups} leads have overdue follow-ups. Immediate action needed to prevent lead leakage."];
} elseif ($overdueFollowups > 0) {
    $insights[] = ['type' => 'warning', 'category' => 'CRM', 'title' => 'Pending Follow-ups', 'message' => "{$overdueFollowups} leads need follow-up. Schedule calls today to maintain engagement."];
}

if ($hotLeads > 0) {
    $insights[] = ['type' => 'success', 'category' => 'CRM', 'title' => 'Hot Leads Ready', 'message' => "{$hotLeads} hot leads in pipeline. Prioritize these for conversion — highest probability of closure."];
}

if ($leadConversionRate < 10 && $totalLeads > 20) {
    $insights[] = ['type' => 'warning', 'category' => 'CRM', 'title' => 'Low Lead Conversion', 'message' => "Only {$leadConversionRate}% lead conversion rate. Review lead qualification criteria and nurture process."];
}

// Inventory insights
if ($outOfStock > 0) {
    $insights[] = ['type' => 'danger', 'category' => 'Inventory', 'title' => 'Out of Stock Alert', 'message' => "{$outOfStock} items are completely out of stock. This may cause production delays and lost sales."];
}

if ($lowStockItems > 10) {
    $insights[] = ['type' => 'warning', 'category' => 'Inventory', 'title' => 'Multiple Low Stock Items', 'message' => "{$lowStockItems} items below minimum stock level. Raise purchase orders to avoid production disruption."];
} elseif ($lowStockItems > 0) {
    $insights[] = ['type' => 'warning', 'category' => 'Inventory', 'title' => 'Low Stock Warning', 'message' => "{$lowStockItems} items approaching minimum stock. Plan procurement soon."];
}

if ($slowMovingValue > 100000) {
    $insights[] = ['type' => 'info', 'category' => 'Inventory', 'title' => 'High Slow-Moving Stock', 'message' => "₹" . number_format($slowMovingValue) . " worth of inventory hasn't moved in 90 days. Consider clearance pricing or production planning."];
} elseif (count($slowMoving) > 5) {
    $insights[] = ['type' => 'info', 'category' => 'Inventory', 'title' => 'Slow-Moving Items', 'message' => count($slowMoving) . " items with no movement in 90 days. Review demand forecast and adjust reorder levels."];
}

// Purchase insights
if ($pendingPOValue > $purchaseThisMonth && $pendingPOValue > 0) {
    $insights[] = ['type' => 'info', 'category' => 'Purchase', 'title' => 'Pending PO Value High', 'message' => "₹" . number_format($pendingPOValue) . " in pending POs. Expedite approvals to avoid supply chain delays."];
}

// Operations insights
$totalActiveWO = $woOpen + $woReleased + $woInProgress;
if ($totalActiveWO > 20) {
    $insights[] = ['type' => 'warning', 'category' => 'Operations', 'title' => 'High WO Load', 'message' => "{$totalActiveWO} active work orders. Monitor production capacity and engineer workload."];
}
if ($woOpen > 5) {
    $insights[] = ['type' => 'info', 'category' => 'Operations', 'title' => 'WO Pending Release', 'message' => "{$woOpen} work orders awaiting release. Review BOM availability and assign engineers."];
}

// If no insights generated
if (empty($insights)) {
    $insights[] = ['type' => 'success', 'category' => 'Overall', 'title' => 'Systems Normal', 'message' => 'All metrics within healthy ranges. Continue monitoring for opportunities.'];
}

// Sort insights: danger first, then warning, info, success
usort($insights, function($a, $b) {
    $order = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];
    return ($order[$a['type']] ?? 4) - ($order[$b['type']] ?? 4);
});
?>

<!DOCTYPE html>
<html>
<head>
    <title>AI Analytics & Decision Support</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-dash { padding: 20px; padding-top: calc(48px + 20px); }
        .analytics-header {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
            margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #6366f1;
        }
        .analytics-header h1 { margin: 0; font-size: 1.7em; color: var(--text); }
        .analytics-header .subtitle { color: var(--muted-text); font-size: 0.9em; }

        .section-title {
            font-size: 1.25em; font-weight: 700; color: var(--text); margin: 30px 0 15px 0;
            padding: 10px 15px; border-left: 4px solid #6366f1; background: var(--card);
            border-radius: 0 8px 8px 0;
        }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .kpi-card {
            background: var(--card); border: 1px solid var(--border); border-radius: 10px;
            padding: 18px; position: relative; overflow: hidden;
        }
        .kpi-card .kpi-label { font-size: 0.8em; color: var(--muted-text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .kpi-card .kpi-value { font-size: 1.8em; font-weight: 700; color: var(--text); line-height: 1.2; }
        .kpi-card .kpi-sub { font-size: 0.8em; color: var(--muted-text); margin-top: 4px; }
        .kpi-card .kpi-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: 600; }
        .badge-up { background: #dcfce7; color: #166534; }
        .badge-down { background: #fee2e2; color: #991b1b; }
        .badge-neutral { background: #f3f4f6; color: #6b7280; }
        .kpi-card .kpi-stripe { position: absolute; top: 0; left: 0; right: 0; height: 3px; }

        .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .chart-card {
            background: var(--card); border: 1px solid var(--border); border-radius: 10px;
            padding: 20px; min-height: 300px;
        }
        .chart-card h3 { margin: 0 0 15px 0; font-size: 1em; color: var(--text); }

        .insight-card {
            background: var(--card); border-radius: 10px; padding: 15px 18px;
            margin-bottom: 10px; display: flex; gap: 15px; align-items: flex-start;
            border-left: 4px solid transparent;
        }
        .insight-danger { border-left-color: #ef4444; background: #fef2f2; }
        .insight-warning { border-left-color: #f59e0b; background: #fffbeb; }
        .insight-info { border-left-color: #3b82f6; background: #eff6ff; }
        .insight-success { border-left-color: #10b981; background: #f0fdf4; }
        .insight-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1em; flex-shrink: 0; }
        .insight-danger .insight-icon { background: #fee2e2; }
        .insight-warning .insight-icon { background: #fef3c7; }
        .insight-info .insight-icon { background: #dbeafe; }
        .insight-success .insight-icon { background: #d1fae5; }
        .insight-body { flex: 1; }
        .insight-body .insight-cat { font-size: 0.7em; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .insight-body .insight-title { font-weight: 600; font-size: 0.95em; color: var(--text); margin: 2px 0; }
        .insight-body .insight-msg { font-size: 0.85em; color: var(--muted-text); line-height: 1.4; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        .data-table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--border); color: var(--muted-text); font-weight: 600; font-size: 0.8em; text-transform: uppercase; }
        .data-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); color: var(--text); }
        .data-table tr:last-child td { border-bottom: none; }

        .pipeline-bar { display: flex; height: 32px; border-radius: 6px; overflow: hidden; margin-bottom: 10px; }
        .pipeline-bar div { display: flex; align-items: center; justify-content: center; font-size: 0.7em; font-weight: 600; color: white; min-width: 30px; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) {
            .two-col { grid-template-columns: 1fr; }
            .chart-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* Dark mode overrides */
        .dark .insight-danger { background: #1a0505; }
        .dark .insight-warning { background: #1a1405; }
        .dark .insight-info { background: #050f1a; }
        .dark .insight-success { background: #051a0f; }
    </style>
</head>
<body>
<div class="content analytics-dash">

    <div class="analytics-header">
        <div>
            <h1>AI Analytics & Decision Support</h1>
            <div class="subtitle">Data-driven insights across Sales, CRM, Inventory & Operations &bull; <?= date('d M Y') ?></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/ceo_dashboard.php" class="btn btn-secondary">CEO Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary">Print Report</button>
        </div>
    </div>

    <!-- ============ AI INSIGHTS ============ -->
    <div class="section-title">AI-Driven Insights & Recommendations</div>
    <div style="margin-bottom:25px;">
        <?php foreach ($insights as $ins): ?>
        <div class="insight-card insight-<?= $ins['type'] ?>">
            <div class="insight-icon">
                <?php
                $icons = ['danger' => '&#9888;', 'warning' => '&#9888;', 'info' => '&#8505;', 'success' => '&#10004;'];
                echo $icons[$ins['type']] ?? '&#8505;';
                ?>
            </div>
            <div class="insight-body">
                <div class="insight-cat"><?= htmlspecialchars($ins['category']) ?></div>
                <div class="insight-title"><?= htmlspecialchars($ins['title']) ?></div>
                <div class="insight-msg"><?= htmlspecialchars($ins['message']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ SALES ANALYTICS ============ -->
    <div class="section-title">Sales Analytics</div>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#6366f1;"></div>
            <div class="kpi-label">Revenue This Month</div>
            <div class="kpi-value"><?= $salesThisMonth > 0 ? '₹' . number_format($salesThisMonth) : '₹0' ?></div>
            <div class="kpi-sub">
                <span class="kpi-badge <?= $salesGrowth >= 0 ? 'badge-up' : 'badge-down' ?>">
                    <?= $salesGrowth >= 0 ? '▲' : '▼' ?> <?= abs($salesGrowth) ?>% vs last month
                </span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#10b981;"></div>
            <div class="kpi-label">Revenue YTD</div>
            <div class="kpi-value"><?= '₹' . number_format($salesYTD) ?></div>
            <div class="kpi-sub">Year to date</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#f59e0b;"></div>
            <div class="kpi-label">Quote Conversion</div>
            <div class="kpi-value"><?= $conversionRate ?>%</div>
            <div class="kpi-sub"><?= $convertedCount ?> of <?= $totalQuotes ?> quotes converted</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#0891b2;"></div>
            <div class="kpi-label">Avg Order Value</div>
            <div class="kpi-value"><?= '₹' . number_format($avgOrderValue) ?></div>
            <div class="kpi-sub">This year</div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Monthly Revenue Trend</h3>
            <canvas id="revenueChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Monthly Orders</h3>
            <canvas id="ordersChart"></canvas>
        </div>
    </div>

    <div class="two-col">
        <div class="chart-card">
            <h3>Top 5 Customers (YTD Revenue)</h3>
            <table class="data-table">
                <tr><th>Customer</th><th style="text-align:right">Revenue</th><th style="text-align:center">Invoices</th></tr>
                <?php foreach ($topCustomers as $i => $c): ?>
                <tr>
                    <td><strong><?= ($i+1) ?>.</strong> <?= htmlspecialchars($c['company_name'] ?: 'N/A') ?></td>
                    <td style="text-align:right;font-weight:600;">₹<?= number_format($c['revenue']) ?></td>
                    <td style="text-align:center;"><?= $c['invoice_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topCustomers)): ?><tr><td colspan="3" style="text-align:center;color:#999;">No data</td></tr><?php endif; ?>
            </table>
        </div>
        <div class="chart-card">
            <h3>Top 5 Products (YTD Revenue)</h3>
            <table class="data-table">
                <tr><th>Product</th><th style="text-align:right">Revenue</th><th style="text-align:center">Qty Sold</th></tr>
                <?php foreach ($topProducts as $i => $p): ?>
                <tr>
                    <td><strong><?= ($i+1) ?>.</strong> <?= htmlspecialchars($p['part_name'] ?: 'N/A') ?></td>
                    <td style="text-align:right;font-weight:600;">₹<?= number_format($p['revenue']) ?></td>
                    <td style="text-align:center;"><?= $p['qty_sold'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?><tr><td colspan="3" style="text-align:center;color:#999;">No data</td></tr><?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ============ CRM ANALYTICS ============ -->
    <div class="section-title">CRM & Lead Intelligence</div>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#7c3aed;"></div>
            <div class="kpi-label">Total Leads</div>
            <div class="kpi-value"><?= $totalLeads ?></div>
            <div class="kpi-sub">All time pipeline</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#ef4444;"></div>
            <div class="kpi-label">Hot Leads</div>
            <div class="kpi-value"><?= $hotLeads ?></div>
            <div class="kpi-sub">Ready for conversion</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#10b981;"></div>
            <div class="kpi-label">Lead Conversion Rate</div>
            <div class="kpi-value"><?= $leadConversionRate ?>%</div>
            <div class="kpi-sub"><?= $convertedLeads ?> converted</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#f59e0b;"></div>
            <div class="kpi-label">Overdue Follow-ups</div>
            <div class="kpi-value"><?= $overdueFollowups ?></div>
            <div class="kpi-sub" style="color:<?= $overdueFollowups > 0 ? '#ef4444' : '#10b981' ?>;"><?= $overdueFollowups > 0 ? 'Needs attention' : 'All caught up' ?></div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Lead Pipeline</h3>
            <?php $pipeTotal = $hotLeads + $warmLeads + $coldLeads + $convertedLeads + $lostLeads; ?>
            <?php if ($pipeTotal > 0): ?>
            <div class="pipeline-bar">
                <?php if ($hotLeads > 0): ?><div style="width:<?= round($hotLeads/$pipeTotal*100) ?>%;background:#ef4444;" title="Hot: <?= $hotLeads ?>"><?= $hotLeads ?></div><?php endif; ?>
                <?php if ($warmLeads > 0): ?><div style="width:<?= round($warmLeads/$pipeTotal*100) ?>%;background:#f59e0b;" title="Warm: <?= $warmLeads ?>"><?= $warmLeads ?></div><?php endif; ?>
                <?php if ($coldLeads > 0): ?><div style="width:<?= round($coldLeads/$pipeTotal*100) ?>%;background:#3b82f6;" title="Cold: <?= $coldLeads ?>"><?= $coldLeads ?></div><?php endif; ?>
                <?php if ($convertedLeads > 0): ?><div style="width:<?= round($convertedLeads/$pipeTotal*100) ?>%;background:#10b981;" title="Converted: <?= $convertedLeads ?>"><?= $convertedLeads ?></div><?php endif; ?>
                <?php if ($lostLeads > 0): ?><div style="width:<?= round($lostLeads/$pipeTotal*100) ?>%;background:#6b7280;" title="Lost: <?= $lostLeads ?>"><?= $lostLeads ?></div><?php endif; ?>
            </div>
            <div style="display:flex;gap:15px;flex-wrap:wrap;font-size:0.8em;color:var(--muted-text);">
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;"></span> Hot (<?= $hotLeads ?>)</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span> Warm (<?= $warmLeads ?>)</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3b82f6;"></span> Cold (<?= $coldLeads ?>)</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;"></span> Converted (<?= $convertedLeads ?>)</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#6b7280;"></span> Lost (<?= $lostLeads ?>)</span>
            </div>
            <?php else: ?>
            <p style="color:#999;text-align:center;">No lead data available</p>
            <?php endif; ?>
        </div>
        <div class="chart-card">
            <h3>Leads by Source</h3>
            <canvas id="leadsSourceChart"></canvas>
        </div>
    </div>

    <!-- ============ INVENTORY ANALYTICS ============ -->
    <div class="section-title">Inventory Intelligence</div>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#0d9488;"></div>
            <div class="kpi-label">Total Stock Value</div>
            <div class="kpi-value"><?= '₹' . number_format($stockValue) ?></div>
            <div class="kpi-sub"><?= $totalSKUs ?> active SKUs</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#f59e0b;"></div>
            <div class="kpi-label">Low Stock Items</div>
            <div class="kpi-value"><?= $lowStockItems ?></div>
            <div class="kpi-sub">Below minimum level</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#ef4444;"></div>
            <div class="kpi-label">Out of Stock</div>
            <div class="kpi-value"><?= $outOfStock ?></div>
            <div class="kpi-sub" style="color:<?= $outOfStock > 0 ? '#ef4444' : '#10b981' ?>;"><?= $outOfStock > 0 ? 'Needs reorder' : 'All stocked' ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#6b7280;"></div>
            <div class="kpi-label">Slow-Moving Value</div>
            <div class="kpi-value"><?= '₹' . number_format($slowMovingValue) ?></div>
            <div class="kpi-sub">No movement in 90 days</div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Inventory Value Distribution (Top Items)</h3>
            <canvas id="invValueChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Critical Reorder List</h3>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <tr><th>Part No</th><th>Name</th><th style="text-align:center">Stock</th><th style="text-align:center">Min</th><th style="text-align:center">Short</th></tr>
                <?php foreach ($criticalReorder as $cr): ?>
                <tr>
                    <td><code style="font-size:0.85em;"><?= htmlspecialchars($cr['part_no']) ?></code></td>
                    <td><?= htmlspecialchars($cr['part_name'] ?: '-') ?></td>
                    <td style="text-align:center;color:#ef4444;font-weight:600;"><?= $cr['current_stock'] ?></td>
                    <td style="text-align:center;"><?= $cr['min_stock'] ?></td>
                    <td style="text-align:center;color:#991b1b;font-weight:600;"><?= $cr['shortage'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($criticalReorder)): ?><tr><td colspan="5" style="text-align:center;color:#10b981;">All items adequately stocked</td></tr><?php endif; ?>
            </table>
            </div>
        </div>
    </div>

    <?php if (!empty($slowMoving)): ?>
    <div class="chart-card" style="margin-bottom:20px;">
        <h3>Slow-Moving Inventory (No Movement in 90 Days)</h3>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <tr><th>Part No</th><th>Name</th><th style="text-align:center">Qty</th><th style="text-align:right">Value</th><th>Last Moved</th></tr>
            <?php foreach ($slowMoving as $sm): ?>
            <tr>
                <td><code style="font-size:0.85em;"><?= htmlspecialchars($sm['part_no']) ?></code></td>
                <td><?= htmlspecialchars($sm['part_name'] ?: '-') ?></td>
                <td style="text-align:center;"><?= $sm['qty'] ?></td>
                <td style="text-align:right;font-weight:600;">₹<?= number_format($sm['value']) ?></td>
                <td><?= $sm['last_moved'] ? date('d-M-Y', strtotime($sm['last_moved'])) : 'Never' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ PURCHASE ANALYTICS ============ -->
    <div class="section-title">Purchase & Supplier Analytics</div>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#d97706;"></div>
            <div class="kpi-label">Purchase This Month</div>
            <div class="kpi-value"><?= '₹' . number_format($purchaseThisMonth) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#dc2626;"></div>
            <div class="kpi-label">Pending POs</div>
            <div class="kpi-value"><?= $pendingPOCount ?></div>
            <div class="kpi-sub">₹<?= number_format($pendingPOValue) ?> value</div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Monthly Purchase Trend</h3>
            <canvas id="purchaseChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Top 5 Suppliers (YTD Spend)</h3>
            <table class="data-table">
                <tr><th>Supplier</th><th style="text-align:right">Spend</th><th style="text-align:center">POs</th></tr>
                <?php foreach ($topSuppliers as $i => $s): ?>
                <tr>
                    <td><strong><?= ($i+1) ?>.</strong> <?= htmlspecialchars($s['supplier_name'] ?: 'N/A') ?></td>
                    <td style="text-align:right;font-weight:600;">₹<?= number_format($s['spend']) ?></td>
                    <td style="text-align:center;"><?= $s['po_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topSuppliers)): ?><tr><td colspan="3" style="text-align:center;color:#999;">No data</td></tr><?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ============ OPERATIONS ANALYTICS ============ -->
    <div class="section-title">Operations & Work Orders</div>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#6366f1;"></div>
            <div class="kpi-label">Open / Created</div>
            <div class="kpi-value"><?= $woOpen ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#0891b2;"></div>
            <div class="kpi-label">Released</div>
            <div class="kpi-value"><?= $woReleased ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#d946ef;"></div>
            <div class="kpi-label">In Progress</div>
            <div class="kpi-value"><?= $woInProgress ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#10b981;"></div>
            <div class="kpi-label">Completed / QC</div>
            <div class="kpi-value"><?= $woCompleted ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-stripe" style="background:#6b7280;"></div>
            <div class="kpi-label">Closed</div>
            <div class="kpi-value"><?= $woClosed ?></div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>Work Order Status Distribution</h3>
            <canvas id="woChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Revenue vs Purchase (6 Months)</h3>
            <canvas id="revVsPurchChart"></canvas>
        </div>
    </div>

</div>

<script>
// Chart.js defaults
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.font.size = 12;

// Revenue Trend
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?= json_encode(array_column($monthlyRevenue, 'revenue')) ?>,
            backgroundColor: 'rgba(99, 102, 241, 0.8)',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + (v >= 100000 ? (v/100000).toFixed(1) + 'L' : v >= 1000 ? (v/1000).toFixed(0) + 'K' : v) } } }
    }
});

// Orders Trend
new Chart(document.getElementById('ordersChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyOrders, 'month')) ?>,
        datasets: [{
            label: 'Sales Orders',
            data: <?= json_encode(array_column($monthlyOrders, 'count')) ?>,
            borderColor: '#0891b2', backgroundColor: 'rgba(8, 145, 178, 0.1)',
            fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#0891b2'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Leads by Source
<?php if (!empty($leadsBySource)): ?>
new Chart(document.getElementById('leadsSourceChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($leadsBySource, 'source')) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($leadsBySource, 'cnt'))) ?>,
            backgroundColor: ['#6366f1','#f59e0b','#10b981','#ef4444','#0891b2','#d946ef']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } } } }
});
<?php endif; ?>

// Inventory Value
<?php if (!empty($invByValue)): ?>
new Chart(document.getElementById('invValueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($r) { return mb_substr($r['part_name'], 0, 20); }, $invByValue)) ?>,
        datasets: [{
            label: 'Value (₹)',
            data: <?= json_encode(array_column($invByValue, 'value')) ?>,
            backgroundColor: ['#0d9488','#10b981','#34d399','#6ee7b7','#a7f3d0','#14b8a6','#2dd4bf','#5eead4']
        }]
    },
    options: {
        indexAxis: 'y', responsive: true, plugins: { legend: { display: false } },
        scales: { x: { ticks: { callback: v => '₹' + (v >= 100000 ? (v/100000).toFixed(1) + 'L' : v >= 1000 ? (v/1000).toFixed(0) + 'K' : v) } } }
    }
});
<?php endif; ?>

// Purchase Trend
new Chart(document.getElementById('purchaseChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyPurchase, 'month')) ?>,
        datasets: [{
            label: 'Purchase (₹)',
            data: <?= json_encode(array_column($monthlyPurchase, 'value')) ?>,
            backgroundColor: 'rgba(217, 119, 6, 0.8)', borderRadius: 6
        }]
    },
    options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + (v >= 100000 ? (v/100000).toFixed(1) + 'L' : v >= 1000 ? (v/1000).toFixed(0) + 'K' : v) } } }
    }
});

// Work Order Distribution
new Chart(document.getElementById('woChart'), {
    type: 'doughnut',
    data: {
        labels: ['Open/Created', 'Released', 'In Progress', 'Completed/QC', 'Closed'],
        datasets: [{
            data: [<?= $woOpen ?>, <?= $woReleased ?>, <?= $woInProgress ?>, <?= $woCompleted ?>, <?= $woClosed ?>],
            backgroundColor: ['#6366f1', '#0891b2', '#d946ef', '#10b981', '#6b7280']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } } } }
});

// Revenue vs Purchase
new Chart(document.getElementById('revVsPurchChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
        datasets: [
            { label: 'Revenue', data: <?= json_encode(array_column($monthlyRevenue, 'revenue')) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.4 },
            { label: 'Purchase', data: <?= json_encode(array_column($monthlyPurchase, 'value')) ?>, borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,0.1)', fill: true, tension: 0.4 }
        ]
    },
    options: {
        responsive: true, plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + (v >= 100000 ? (v/100000).toFixed(1) + 'L' : v >= 1000 ? (v/1000).toFixed(0) + 'K' : v) } } }
    }
});
</script>

</body>
</html>
