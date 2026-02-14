<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');

// Ensure monthly_sales_revenue table exists
try {
    $pdo->query("SELECT 1 FROM monthly_sales_revenue LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_sales_revenue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        revenue_month DATE NOT NULL COMMENT 'First day of month e.g. 2026-02-01',
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes VARCHAR(255) DEFAULT NULL,
        entered_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_month (revenue_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Handle revenue entry save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_revenue') {
    $months = $_POST['rev_month'] ?? [];
    $amounts = $_POST['rev_amount'] ?? [];
    $notes = $_POST['rev_notes'] ?? [];

    $upsert = $pdo->prepare("
        INSERT INTO monthly_sales_revenue (revenue_month, amount, notes, entered_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), notes = VALUES(notes), entered_by = VALUES(entered_by)
    ");
    $userId = $_SESSION['user_id'] ?? 1;
    $saved = 0;
    for ($k = 0; $k < count($months); $k++) {
        $m = trim($months[$k] ?? '');
        $a = (float)($amounts[$k] ?? 0);
        $n = trim($notes[$k] ?? '');
        if ($m && $a > 0) {
            $upsert->execute([$m . '-01', $a, $n ?: null, $userId]);
            $saved++;
        }
    }
    header("Location: sales_analytics.php?saved=$saved");
    exit;
}

$revSavedMsg = isset($_GET['saved']) ? (int)$_GET['saved'] : -1;

// Date ranges
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$thisYearStart = date('Y-01-01');

// === KPI METRICS ===

// Revenue This Month
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$thisMonthStart, $thisMonthEnd]);
    $revenueThisMonth = (float)$stmt->fetchColumn();
} catch (Exception $e) { $revenueThisMonth = 0; }

// Revenue Last Month
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
    $revenueLastMonth = (float)$stmt->fetchColumn();
} catch (Exception $e) { $revenueLastMonth = 0; }

// Growth %
$growthPct = $revenueLastMonth > 0
    ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
    : 0;

// Active Pipeline Value (hot + warm leads)
try {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty), 0)
        FROM crm_lead_requirements r
        JOIN crm_leads l ON r.lead_id = l.id
        WHERE LOWER(l.lead_status) IN ('hot', 'warm')
    ");
    $activePipeline = (float)$stmt->fetchColumn();
} catch (Exception $e) { $activePipeline = 0; }

// Conversion Rate (YTD)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) as converted
        FROM crm_leads WHERE created_at >= ?
    ");
    $stmt->execute([$thisYearStart]);
    $convRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $conversionRate = $convRow['total'] > 0
        ? round(($convRow['converted'] / $convRow['total']) * 100, 1) : 0;
    $totalLeadsYTD = (int)$convRow['total'];
    $convertedLeadsYTD = (int)$convRow['converted'];
} catch (Exception $e) { $conversionRate = 0; $totalLeadsYTD = 0; $convertedLeadsYTD = 0; }

// === REVENUE TREND (12 months) ===
$monthlyRevenue = [];
$revenueValues = [];
for ($i = 11; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd = date('Y-m-t', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(qi.total_amount), 0)
            FROM invoice_master im
            LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$mStart, $mEnd]);
        $rev = (float)$stmt->fetchColumn();
    } catch (Exception $e) { $rev = 0; }
    $monthlyRevenue[] = ['month' => $mLabel, 'revenue' => $rev];
    $revenueValues[] = $rev;
}

// === SALES PROJECTIONS ===

// Weighted Pipeline
try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'hot'
                THEN COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty ELSE 0 END), 0) as hot_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'warm'
                THEN COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty ELSE 0 END), 0) as warm_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'cold'
                THEN COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty ELSE 0 END), 0) as cold_value
        FROM crm_lead_requirements r
        JOIN crm_leads l ON r.lead_id = l.id
        WHERE LOWER(l.lead_status) IN ('hot', 'warm', 'cold')
    ");
    $pipelineRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $hotValue = (float)$pipelineRow['hot_value'];
    $warmValue = (float)$pipelineRow['warm_value'];
    $coldValue = (float)$pipelineRow['cold_value'];
} catch (Exception $e) { $hotValue = $warmValue = $coldValue = 0; }

$weightedPipeline = ($hotValue * 0.7) + ($warmValue * 0.4) + ($coldValue * 0.1);

// Historical Average (last 6 months, excluding zeros)
$last6 = array_slice($revenueValues, -6);
$nonZero = array_filter($last6, fn($v) => $v > 0);
$historicalAvg = count($nonZero) > 0 ? array_sum($nonZero) / count($nonZero) : 0;

// Blended Projections
$projNextMonth = ($weightedPipeline * 0.6) + ($historicalAvg * 0.4);
$projNextQuarter = $projNextMonth * 3;

// YTD Revenue
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
    ");
    $stmt->execute([$thisYearStart]);
    $revenueYTD = (float)$stmt->fetchColumn();
} catch (Exception $e) { $revenueYTD = 0; }

$currentMonth = (int)date('n');
$remainingMonths = 12 - $currentMonth;
$projNextYear = $revenueYTD + ($projNextMonth * $remainingMonths);

// === PIPELINE FUNNEL ===
try { $funnelLeads = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn(); }
catch (Exception $e) { $funnelLeads = 0; }

try { $funnelHot = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'")->fetchColumn(); }
catch (Exception $e) { $funnelHot = 0; }

try {
    $funnelQuoted = (int)$pdo->query("SELECT COUNT(DISTINCT l.id) FROM crm_leads l JOIN quote_master q ON q.reference = l.lead_no")->fetchColumn();
} catch (Exception $e) { $funnelQuoted = 0; }

try {
    $funnelOrdered = (int)$pdo->query("
        SELECT COUNT(DISTINCT l.id) FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.linked_quote_id = q.id
    ")->fetchColumn();
} catch (Exception $e) { $funnelOrdered = 0; }

try {
    $funnelInvoiced = (int)$pdo->query("
        SELECT COUNT(DISTINCT l.id) FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.linked_quote_id = q.id
        JOIN invoice_master im ON im.so_no = so.so_no AND im.status = 'released'
    ")->fetchColumn();
} catch (Exception $e) { $funnelInvoiced = 0; }

// Funnel values
$funnelPipelineValue = $hotValue + $warmValue + $coldValue;
try {
    $funnelQuotedValue = (float)$pdo->query("
        SELECT COALESCE(SUM(qi.total_amount), 0) FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN quote_items qi ON qi.quote_id = q.id
    ")->fetchColumn();
} catch (Exception $e) { $funnelQuotedValue = 0; }

try {
    $funnelInvoicedValue = (float)$pdo->query("
        SELECT COALESCE(SUM(qi.total_amount), 0) FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.linked_quote_id = q.id
        JOIN invoice_master im ON im.so_no = so.so_no AND im.status = 'released'
        JOIN quote_items qi ON qi.quote_id = q.id
    ")->fetchColumn();
} catch (Exception $e) { $funnelInvoicedValue = 0; }

// === MONTHLY BREAKDOWN TABLE ===
$monthlyBreakdown = [];
for ($i = 11; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd = date('Y-m-t', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$mStart, $mEnd . ' 23:59:59']);
        $newLeads = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $newLeads = 0; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$mStart, $mEnd . ' 23:59:59']);
        $quotesGen = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $quotesGen = 0; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE sales_date BETWEEN ? AND ?");
        $stmt->execute([$mStart, $mEnd]);
        $ordersPlaced = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $ordersPlaced = 0; }

    $mRevenue = $monthlyRevenue[11 - $i]['revenue'];

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) as converted
            FROM crm_leads WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$mStart, $mEnd . ' 23:59:59']);
        $cr = $stmt->fetch(PDO::FETCH_ASSOC);
        $mConvRate = $cr['total'] > 0 ? round(($cr['converted'] / $cr['total']) * 100, 1) : 0;
    } catch (Exception $e) { $mConvRate = 0; }

    $avgDeal = $ordersPlaced > 0 ? $mRevenue / $ordersPlaced : 0;

    $monthlyBreakdown[] = [
        'month' => $mLabel, 'new_leads' => $newLeads, 'quotes' => $quotesGen,
        'orders' => $ordersPlaced, 'revenue' => $mRevenue,
        'conversion' => $mConvRate, 'avg_deal' => $avgDeal
    ];
}

// === SALESPERSON PERFORMANCE ===
$salespersonPerf = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            e.id as user_id,
            CONCAT(e.first_name, ' ', e.last_name) as salesperson,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN 1 ELSE 0 END) as hot_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN 1 ELSE 0 END) as converted_leads,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN sub.lead_value ELSE 0 END), 0) as revenue
        FROM employees e
        INNER JOIN crm_leads l ON l.assigned_user_id = e.id AND l.created_at >= ?
        LEFT JOIN (
            SELECT l2.id as lead_id, COALESCE(SUM(qi.total_amount), 0) as lead_value
            FROM crm_leads l2
            LEFT JOIN quote_master q ON q.reference = l2.lead_no
            LEFT JOIN quote_items qi ON qi.quote_id = q.id
            GROUP BY l2.id
        ) sub ON sub.lead_id = l.id
        GROUP BY e.id, e.first_name, e.last_name
        HAVING total_leads > 0
        ORDER BY revenue DESC, converted_leads DESC
    ");
    $stmt->execute([$thisYearStart]);
    $salespersonPerf = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $salespersonPerf = []; }

// === PRODUCT PERFORMANCE ===

// Most Quoted
$mostQuoted = [];
try {
    $stmt = $pdo->prepare("
        SELECT qi.part_no, qi.part_name, COUNT(DISTINCT qi.quote_id) as quote_count,
               SUM(qi.qty) as total_qty, SUM(qi.total_amount) as total_value
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        WHERE qm.created_at >= ?
        GROUP BY qi.part_no, qi.part_name
        ORDER BY quote_count DESC
        LIMIT 10
    ");
    $stmt->execute([$thisYearStart]);
    $mostQuoted = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $mostQuoted = []; }

// Most Sold
$mostSold = [];
try {
    $stmt = $pdo->prepare("
        SELECT qi.part_no, qi.part_name, SUM(qi.qty) as qty_sold, SUM(qi.total_amount) as revenue
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.linked_quote_id = qm.id
        JOIN invoice_master im ON im.so_no = so.so_no AND im.status = 'released'
        WHERE im.invoice_date >= ?
        GROUP BY qi.part_no, qi.part_name
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$thisYearStart]);
    $mostSold = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $mostSold = []; }

// Highest Pipeline
$highestPipeline = [];
try {
    $stmt = $pdo->query("
        SELECT r.part_no,
               COALESCE(NULLIF(r.product_name, ''), pm.part_name, r.part_no) as product_name,
               COUNT(DISTINCT l.id) as lead_count,
               SUM(r.estimated_qty) as total_qty,
               SUM(COALESCE(r.our_price, r.target_price, 0) * r.estimated_qty) as pipeline_value
        FROM crm_lead_requirements r
        JOIN crm_leads l ON r.lead_id = l.id
        LEFT JOIN part_master pm ON r.part_no = pm.part_no
        WHERE LOWER(l.lead_status) IN ('hot', 'warm', 'cold')
        GROUP BY r.part_no, product_name
        ORDER BY pipeline_value DESC
        LIMIT 10
    ");
    $highestPipeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $highestPipeline = []; }

// === MANUAL REVENUE ENTRIES ===
$manualRevenue = [];
try {
    $stmt = $pdo->prepare("
        SELECT revenue_month, amount, notes,
               CONCAT(e.first_name, ' ', e.last_name) as entered_by_name
        FROM monthly_sales_revenue msr
        LEFT JOIN employees e ON msr.entered_by = e.id
        WHERE revenue_month >= ?
        ORDER BY revenue_month DESC
    ");
    $stmt->execute([date('Y-m-01', strtotime('-11 months'))]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $key = date('Y-m', strtotime($r['revenue_month']));
        $manualRevenue[$key] = $r;
    }
} catch (Exception $e) { $manualRevenue = []; }

// Build 12-month list for the entry form
$revenueEntryMonths = [];
for ($i = 0; $i <= 11; $i++) {
    $mKey = date('Y-m', strtotime("-$i months"));
    $mLabel = date('F Y', strtotime("-$i months"));
    $existing = $manualRevenue[$mKey] ?? null;
    $revenueEntryMonths[] = [
        'key' => $mKey,
        'label' => $mLabel,
        'amount' => $existing ? (float)$existing['amount'] : '',
        'notes' => $existing['notes'] ?? '',
        'entered_by' => $existing['entered_by_name'] ?? '',
    ];
}

// Calculate manual revenue total YTD
$manualRevenueYTD = 0;
foreach ($manualRevenue as $mKey => $mr) {
    if (strtotime($mr['revenue_month']) >= strtotime($thisYearStart)) {
        $manualRevenueYTD += (float)$mr['amount'];
    }
}

// Helper: format currency
function formatINR($val) {
    if ($val >= 10000000) return number_format($val / 10000000, 2) . ' Cr';
    if ($val >= 100000) return number_format($val / 100000, 1) . ' L';
    return number_format($val, 0);
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Analytics & Projections</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-page { padding: 20px; padding-top: calc(48px + 20px); }
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; flex-wrap: wrap; gap: 10px;
        }
        .page-header h1 { margin: 0; color: var(--text, #2c3e50); font-size: 1.6em; }
        .page-header .subtitle { color: var(--muted-text, #7f8c8d); font-size: 0.9em; }
        .page-header .date-info { color: var(--muted-text, #7f8c8d); text-align: right; font-size: 0.9em; }

        /* KPI Row */
        .kpi-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 15px; margin-bottom: 25px;
        }
        .kpi-card {
            border-radius: 12px; padding: 18px; color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); position: relative; overflow: hidden;
        }
        .kpi-card .kpi-label { font-size: 0.8em; opacity: 0.9; margin-bottom: 5px; }
        .kpi-card .kpi-value { font-size: 1.6em; font-weight: bold; }
        .kpi-card .kpi-sub { font-size: 0.75em; opacity: 0.8; margin-top: 5px; }
        .kpi-green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .kpi-blue { background: linear-gradient(135deg, #2980b9, #3498db); }
        .kpi-orange { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .kpi-purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
        .kpi-red { background: linear-gradient(135deg, #c0392b, #e74c3c); }

        /* Sections */
        .section-card {
            background: var(--card, white); border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px;
        }
        .section-title {
            font-size: 1.1em; color: var(--text, #2c3e50); margin: 0 0 15px 0;
            padding-bottom: 10px; border-bottom: 2px solid var(--border, #eee);
            display: flex; align-items: center; gap: 10px;
        }
        .section-title .icon { font-size: 1.2em; }

        /* Two-column grid */
        .two-col { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

        /* Chart */
        .chart-container { position: relative; height: 300px; }

        /* Funnel */
        .funnel-stage {
            margin: 8px auto; padding: 14px 20px; border-radius: 8px;
            display: flex; justify-content: space-between; align-items: center;
            color: white; font-weight: 600; font-size: 0.9em;
            transition: transform 0.2s;
        }
        .funnel-stage:hover { transform: scale(1.02); }
        .funnel-stage .count { font-size: 1.3em; }
        .funnel-stage .fval { font-size: 0.8em; opacity: 0.9; }

        /* Projection Cards */
        .proj-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .proj-card {
            background: var(--bg, #f8f9fa); border-radius: 10px; padding: 20px;
            text-align: center; border: 2px solid var(--border, #eee);
        }
        .proj-card .proj-label { font-size: 0.85em; color: var(--muted-text, #7f8c8d); margin-bottom: 8px; }
        .proj-card .proj-value { font-size: 1.8em; font-weight: bold; color: var(--text, #2c3e50); }
        .proj-card .proj-sub { font-size: 0.75em; color: var(--muted-text, #999); margin-top: 5px; }
        .proj-card.highlight { border-color: #27ae60; background: rgba(39, 174, 96, 0.05); }

        /* Methodology */
        .method-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;
            font-size: 0.85em;
        }
        .method-item {
            display: flex; justify-content: space-between; padding: 8px 12px;
            background: var(--bg, #f8f9fa); border-radius: 6px;
        }
        .method-item .label { color: var(--muted-text, #666); }
        .method-item .val { font-weight: 600; color: var(--text, #2c3e50); }
        @media (max-width: 600px) { .method-grid { grid-template-columns: 1fr; } }

        /* Data Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td {
            padding: 10px 12px; text-align: left;
            border-bottom: 1px solid var(--border, #eee); font-size: 0.88em;
            color: var(--text, #2c3e50);
        }
        .data-table th {
            font-weight: 600; color: var(--muted-text, #7f8c8d);
            font-size: 0.8em; text-transform: uppercase; background: var(--bg, #f8f9fa);
            position: sticky; top: 0;
        }
        .data-table tr:hover { background: var(--bg, #f8f9fa); }
        .data-table .num { text-align: right; }
        .data-table .highlight-cell { font-weight: 600; }

        /* Product Tabs */
        .tab-nav { display: flex; gap: 0; margin-bottom: 15px; border-bottom: 2px solid var(--border, #eee); }
        .tab-btn {
            padding: 10px 20px; border: none; background: none; cursor: pointer;
            font-size: 0.9em; color: var(--muted-text, #7f8c8d); font-weight: 500;
            border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s;
        }
        .tab-btn.active { color: #3498db; border-bottom-color: #3498db; font-weight: 600; }
        .tab-btn:hover { color: var(--text, #2c3e50); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Scrollable table wrapper */
        .table-scroll { overflow-x: auto; }

        /* Revenue Entry */
        .rev-entry-row {
            display: grid; grid-template-columns: 160px 1fr 1fr 60px; gap: 10px;
            align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border, #f0f0f0);
        }
        .rev-entry-row.header { font-weight: 600; color: var(--muted-text, #7f8c8d); font-size: 0.8em; text-transform: uppercase; border-bottom: 2px solid var(--border, #eee); padding-bottom: 10px; }
        .rev-entry-row .month-label { font-weight: 500; color: var(--text, #2c3e50); font-size: 0.9em; }
        .rev-entry-row input[type="number"], .rev-entry-row input[type="text"] {
            width: 100%; padding: 8px 10px; border: 1px solid var(--border, #ddd); border-radius: 6px;
            font-size: 0.9em; background: var(--bg, #fff); color: var(--text, #2c3e50);
            box-sizing: border-box;
        }
        .rev-entry-row input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
        .rev-entry-row .existing { font-size: 0.75em; color: var(--muted-text, #999); }
        .rev-save-btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 28px;
            background: #27ae60; color: white; border: none; border-radius: 8px;
            font-weight: 600; font-size: 0.95em; cursor: pointer; transition: background 0.2s;
        }
        .rev-save-btn:hover { background: #219a52; }
        .saved-msg { display: inline-block; padding: 8px 16px; background: #e8f5e9; color: #388e3c; border-radius: 6px; font-size: 0.9em; font-weight: 500; margin-left: 10px; }

        @media (max-width: 768px) {
            .kpi-row { grid-template-columns: 1fr 1fr; }
            .proj-row { grid-template-columns: 1fr; }
            .rev-entry-row { grid-template-columns: 120px 1fr; }
        }
    </style>
</head>
<body>

<div class="content analytics-page">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Sales Analytics & Projections</h1>
            <div class="subtitle">Pipeline insights, revenue trends, and forecasting</div>
        </div>
        <div class="date-info">
            <strong><?= date('d M Y') ?></strong><br>
            <small>FY <?= date('Y') ?>-<?= date('y', strtotime('+1 year')) ?></small>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="kpi-row">
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Revenue This Month</div>
            <div class="kpi-value"><?= formatINR($revenueThisMonth) ?></div>
            <div class="kpi-sub"><?= date('F Y') ?></div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Revenue Last Month</div>
            <div class="kpi-value"><?= formatINR($revenueLastMonth) ?></div>
            <div class="kpi-sub"><?= date('F Y', strtotime('-1 month')) ?></div>
        </div>
        <div class="kpi-card kpi-orange">
            <div class="kpi-label">Month-over-Month</div>
            <div class="kpi-value"><?= $growthPct >= 0 ? '+' : '' ?><?= $growthPct ?>%</div>
            <div class="kpi-sub"><?= $growthPct >= 0 ? 'Growth' : 'Decline' ?></div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-label">Active Pipeline</div>
            <div class="kpi-value"><?= formatINR($activePipeline) ?></div>
            <div class="kpi-sub">Hot + Warm leads value</div>
        </div>
        <div class="kpi-card kpi-red">
            <div class="kpi-label">Conversion Rate (YTD)</div>
            <div class="kpi-value"><?= $conversionRate ?>%</div>
            <div class="kpi-sub"><?= $convertedLeadsYTD ?> of <?= $totalLeadsYTD ?> leads</div>
        </div>
    </div>

    <!-- Revenue Chart + Pipeline Funnel -->
    <div class="two-col">
        <div class="section-card">
            <div class="section-title"><span class="icon">📊</span> Revenue Trend (12 Months)</div>
            <div class="chart-container">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
        <div class="section-card">
            <div class="section-title"><span class="icon">🔽</span> Pipeline Funnel</div>
            <div class="funnel-stage" style="width:100%; background: linear-gradient(135deg, #667eea, #764ba2);">
                <div>Total Leads<br><span class="fval"><?= formatINR($funnelPipelineValue) ?></span></div>
                <div class="count"><?= $funnelLeads ?></div>
            </div>
            <div class="funnel-stage" style="width:85%; background: linear-gradient(135deg, #e74c3c, #c0392b);">
                <div>Hot Leads<br><span class="fval"><?= formatINR($hotValue) ?></span></div>
                <div class="count"><?= $funnelHot ?></div>
            </div>
            <div class="funnel-stage" style="width:70%; background: linear-gradient(135deg, #f39c12, #e67e22);">
                <div>Quoted<br><span class="fval"><?= formatINR($funnelQuotedValue) ?></span></div>
                <div class="count"><?= $funnelQuoted ?></div>
            </div>
            <div class="funnel-stage" style="width:55%; background: linear-gradient(135deg, #2193b0, #6dd5ed);">
                <div>Ordered<br><span class="fval">-</span></div>
                <div class="count"><?= $funnelOrdered ?></div>
            </div>
            <div class="funnel-stage" style="width:40%; background: linear-gradient(135deg, #27ae60, #2ecc71);">
                <div>Invoiced<br><span class="fval"><?= formatINR($funnelInvoicedValue) ?></span></div>
                <div class="count"><?= $funnelInvoiced ?></div>
            </div>
        </div>
    </div>

    <!-- Sales Projections -->
    <div class="section-card">
        <div class="section-title"><span class="icon">🎯</span> Sales Projections</div>
        <div class="proj-row">
            <div class="proj-card highlight">
                <div class="proj-label">Next Month</div>
                <div class="proj-value"><?= formatINR($projNextMonth) ?></div>
                <div class="proj-sub"><?= date('F Y', strtotime('+1 month')) ?></div>
            </div>
            <div class="proj-card">
                <div class="proj-label">Next Quarter</div>
                <div class="proj-value"><?= formatINR($projNextQuarter) ?></div>
                <div class="proj-sub"><?= date('M', strtotime('+1 month')) ?> - <?= date('M Y', strtotime('+3 months')) ?></div>
            </div>
            <div class="proj-card">
                <div class="proj-label">Full Year <?= date('Y') ?></div>
                <div class="proj-value"><?= formatINR($projNextYear) ?></div>
                <div class="proj-sub">YTD <?= formatINR($revenueYTD) ?> + <?= $remainingMonths ?> months projected</div>
            </div>
        </div>

        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; color: var(--muted-text, #7f8c8d); font-size: 0.85em; font-weight: 500;">
                Projection Methodology
            </summary>
            <div class="method-grid">
                <div class="method-item">
                    <span class="label">Hot Pipeline (x70%)</span>
                    <span class="val"><?= formatINR($hotValue) ?> &rarr; <?= formatINR($hotValue * 0.7) ?></span>
                </div>
                <div class="method-item">
                    <span class="label">Warm Pipeline (x40%)</span>
                    <span class="val"><?= formatINR($warmValue) ?> &rarr; <?= formatINR($warmValue * 0.4) ?></span>
                </div>
                <div class="method-item">
                    <span class="label">Cold Pipeline (x10%)</span>
                    <span class="val"><?= formatINR($coldValue) ?> &rarr; <?= formatINR($coldValue * 0.1) ?></span>
                </div>
                <div class="method-item">
                    <span class="label">Weighted Pipeline Total</span>
                    <span class="val"><?= formatINR($weightedPipeline) ?></span>
                </div>
                <div class="method-item">
                    <span class="label">Historical Avg (6mo)</span>
                    <span class="val"><?= formatINR($historicalAvg) ?></span>
                </div>
                <div class="method-item">
                    <span class="label">Blend (60% pipe + 40% hist)</span>
                    <span class="val"><?= formatINR($projNextMonth) ?>/mo</span>
                </div>
            </div>
        </details>
    </div>

    <!-- Monthly Breakdown -->
    <div class="section-card">
        <div class="section-title"><span class="icon">📅</span> Monthly Breakdown</div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="num">New Leads</th>
                        <th class="num">Quotes</th>
                        <th class="num">Orders</th>
                        <th class="num">Revenue</th>
                        <th class="num">Conv. Rate</th>
                        <th class="num">Avg Deal Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyBreakdown as $m): ?>
                    <tr>
                        <td><?= $m['month'] ?></td>
                        <td class="num"><?= $m['new_leads'] ?></td>
                        <td class="num"><?= $m['quotes'] ?></td>
                        <td class="num"><?= $m['orders'] ?></td>
                        <td class="num highlight-cell"><?= $m['revenue'] > 0 ? formatINR($m['revenue']) : '-' ?></td>
                        <td class="num"><?= $m['conversion'] ?>%</td>
                        <td class="num"><?= $m['avg_deal'] > 0 ? formatINR($m['avg_deal']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Manual Revenue Entry -->
    <div class="section-card">
        <div class="section-title"><span class="icon">💰</span> Monthly Sales Revenue (Manual Entry)</div>
        <?php if ($revSavedMsg >= 0): ?>
            <div class="saved-msg" style="margin-bottom: 15px;"><?= $revSavedMsg ?> month(s) saved successfully</div>
        <?php endif; ?>
        <p style="color: var(--muted-text, #7f8c8d); font-size: 0.85em; margin: 0 0 15px 0;">
            Enter actual sales revenue for each month. This is independent of invoice-based revenue calculations above.
        </p>
        <form method="post">
            <input type="hidden" name="action" value="save_revenue">
            <div class="rev-entry-row header">
                <div>Month</div>
                <div>Revenue Amount (₹)</div>
                <div>Notes</div>
                <div></div>
            </div>
            <?php foreach ($revenueEntryMonths as $rem): ?>
            <div class="rev-entry-row">
                <div class="month-label">
                    <?= $rem['label'] ?>
                    <input type="hidden" name="rev_month[]" value="<?= $rem['key'] ?>">
                </div>
                <div>
                    <input type="number" name="rev_amount[]" step="0.01" min="0"
                           value="<?= $rem['amount'] !== '' ? $rem['amount'] : '' ?>"
                           placeholder="0.00">
                </div>
                <div>
                    <input type="text" name="rev_notes[]"
                           value="<?= htmlspecialchars($rem['notes']) ?>"
                           placeholder="Optional notes">
                </div>
                <div>
                    <?php if ($rem['entered_by']): ?>
                        <span class="existing" title="Entered by <?= htmlspecialchars($rem['entered_by']) ?>">✓</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <button type="submit" class="rev-save-btn">Save Revenue Data</button>
                <?php if ($manualRevenueYTD > 0): ?>
                    <div style="color: var(--text, #2c3e50); font-size: 0.9em;">
                        Manual YTD Total: <strong style="color: #27ae60;">₹<?= number_format($manualRevenueYTD, 0) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Salesperson Performance -->
    <div class="section-card">
        <div class="section-title"><span class="icon">👤</span> Salesperson Performance (YTD)</div>
        <?php if (empty($salespersonPerf)): ?>
            <p style="color: var(--muted-text, #666); font-style: italic;">No salesperson data available</p>
        <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Salesperson</th>
                        <th class="num">Leads</th>
                        <th class="num">Hot</th>
                        <th class="num">Converted</th>
                        <th class="num">Revenue</th>
                        <th class="num">Conv. Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salespersonPerf as $sp): ?>
                    <tr>
                        <td><?= htmlspecialchars($sp['salesperson']) ?></td>
                        <td class="num"><?= $sp['total_leads'] ?></td>
                        <td class="num" style="color: #e74c3c; font-weight: 600;"><?= $sp['hot_leads'] ?></td>
                        <td class="num" style="color: #27ae60; font-weight: 600;"><?= $sp['converted_leads'] ?></td>
                        <td class="num highlight-cell"><?= $sp['revenue'] > 0 ? formatINR($sp['revenue']) : '-' ?></td>
                        <td class="num"><?= $sp['total_leads'] > 0 ? round(($sp['converted_leads'] / $sp['total_leads']) * 100, 1) : 0 ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Product Performance -->
    <div class="section-card">
        <div class="section-title"><span class="icon">📦</span> Product Performance (YTD)</div>
        <div class="tab-nav">
            <button class="tab-btn active" onclick="showTab('quoted')">Most Quoted</button>
            <button class="tab-btn" onclick="showTab('sold')">Most Sold</button>
            <button class="tab-btn" onclick="showTab('pipeline')">Highest Pipeline</button>
        </div>

        <div id="tab-quoted" class="tab-content active">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Part No</th><th>Product</th><th class="num">Quotes</th><th class="num">Qty</th><th class="num">Value</th></tr></thead>
                    <tbody>
                        <?php if (empty($mostQuoted)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--muted-text);">No data</td></tr>
                        <?php else: foreach ($mostQuoted as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['part_no']) ?></td>
                            <td><?= htmlspecialchars($p['part_name']) ?></td>
                            <td class="num"><?= $p['quote_count'] ?></td>
                            <td class="num"><?= number_format($p['total_qty'], 0) ?></td>
                            <td class="num highlight-cell"><?= formatINR($p['total_value']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-sold" class="tab-content">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Part No</th><th>Product</th><th class="num">Qty Sold</th><th class="num">Revenue</th></tr></thead>
                    <tbody>
                        <?php if (empty($mostSold)): ?>
                            <tr><td colspan="4" style="text-align: center; color: var(--muted-text);">No data</td></tr>
                        <?php else: foreach ($mostSold as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['part_no']) ?></td>
                            <td><?= htmlspecialchars($p['part_name']) ?></td>
                            <td class="num"><?= number_format($p['qty_sold'], 0) ?></td>
                            <td class="num highlight-cell"><?= formatINR($p['revenue']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-pipeline" class="tab-content">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Part No</th><th>Product</th><th class="num">Leads</th><th class="num">Qty</th><th class="num">Pipeline Value</th></tr></thead>
                    <tbody>
                        <?php if (empty($highestPipeline)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--muted-text);">No data</td></tr>
                        <?php else: foreach ($highestPipeline as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['part_no']) ?></td>
                            <td><?= htmlspecialchars($p['product_name']) ?></td>
                            <td class="num"><?= $p['lead_count'] ?></td>
                            <td class="num"><?= number_format($p['total_qty'], 0) ?></td>
                            <td class="num highlight-cell"><?= formatINR($p['pipeline_value']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue Trend Chart
const monthlyData = <?= json_encode($monthlyRevenue) ?>;
const ctx = document.getElementById('revenueTrendChart').getContext('2d');

let cumulative = 0;
const cumulativeData = monthlyData.map(d => { cumulative += d.revenue; return cumulative; });

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [
            {
                label: 'Monthly Revenue',
                data: monthlyData.map(d => d.revenue),
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: '#667eea',
                borderWidth: 1,
                borderRadius: 6,
                order: 2
            },
            {
                label: 'Cumulative',
                data: cumulativeData,
                type: 'line',
                borderColor: '#27ae60',
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 3,
                tension: 0.4,
                yAxisID: 'y1',
                order: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let val = context.raw;
                        if (val >= 10000000) return context.dataset.label + ': ' + (val/10000000).toFixed(2) + ' Cr';
                        if (val >= 100000) return context.dataset.label + ': ' + (val/100000).toFixed(1) + ' L';
                        return context.dataset.label + ': ' + val.toLocaleString('en-IN');
                    }
                }
            },
            legend: { position: 'bottom', labels: { usePointStyle: true } }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) {
                        if (v >= 10000000) return (v/10000000).toFixed(1) + ' Cr';
                        if (v >= 100000) return (v/100000).toFixed(0) + ' L';
                        return v.toLocaleString('en-IN');
                    }
                },
                grid: { color: 'rgba(0,0,0,0.05)' }
            },
            y1: {
                position: 'right',
                beginAtZero: true,
                grid: { drawOnChartArea: false },
                ticks: {
                    callback: function(v) {
                        if (v >= 10000000) return (v/10000000).toFixed(1) + ' Cr';
                        if (v >= 100000) return (v/100000).toFixed(0) + ' L';
                        return v.toLocaleString('en-IN');
                    }
                }
            },
            x: {
                ticks: { maxRotation: 45, font: { size: 11 } },
                grid: { display: false }
            }
        }
    }
});

// Product Performance Tabs
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>

</body>
</html>
