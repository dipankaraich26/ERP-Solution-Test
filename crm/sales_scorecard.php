<?php
/**
 * Sales Executive Performance Scorecard
 * Tracks KPIs per sales executive with auto-generated improvement suggestions
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');

// Date range defaults (current month)
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$filterExec = $_GET['exec_id'] ?? '';

// Fetch all active employees for filter dropdown
$employees = [];
try {
    $employees = $pdo->query("
        SELECT id, emp_id, CONCAT(first_name,' ',last_name) as full_name, designation, department
        FROM employees WHERE status='Active' ORDER BY first_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── DATA QUERIES ──

// 1. Leads per executive
$execData = [];
try {
    $sql = "
        SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name, e.designation, e.department, e.emp_id,
            COUNT(DISTINCT l.id) as total_leads,
            SUM(CASE WHEN l.lead_status='hot' THEN 1 ELSE 0 END) as hot,
            SUM(CASE WHEN l.lead_status='warm' THEN 1 ELSE 0 END) as warm,
            SUM(CASE WHEN l.lead_status='cold' THEN 1 ELSE 0 END) as cold,
            SUM(CASE WHEN l.lead_status='converted' THEN 1 ELSE 0 END) as converted,
            SUM(CASE WHEN l.lead_status='lost' THEN 1 ELSE 0 END) as lost
        FROM employees e
        LEFT JOIN crm_leads l ON l.assigned_user_id = e.id AND l.created_at BETWEEN ? AND ?
        WHERE e.status='Active'
    ";
    $params = [$dateFrom, $dateTo . ' 23:59:59'];
    if ($filterExec) {
        $sql .= " AND e.id = ?";
        $params[] = (int)$filterExec;
    }
    $sql .= " GROUP BY e.id ORDER BY total_leads DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $execData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $execData = []; }

// 2. Quotes & Revenue per executive (via lead→quote→SO→invoice chain)
$revenueData = [];
try {
    $sql2 = "
        SELECT e.id,
            COUNT(DISTINCT q.id) as quotes,
            COALESCE(SUM(DISTINCT qi.total_amount), 0) as quoted_value,
            COUNT(DISTINCT so.so_no) as orders
        FROM employees e
        LEFT JOIN crm_leads l ON l.assigned_user_id = e.id AND l.created_at BETWEEN ? AND ?
        LEFT JOIN quote_master q ON q.reference = l.lead_no
        LEFT JOIN quote_items qi ON qi.quote_id = q.id
        LEFT JOIN sales_orders so ON so.linked_quote_id = q.id
        WHERE e.status='Active'
    ";
    $params2 = [$dateFrom, $dateTo . ' 23:59:59'];
    if ($filterExec) {
        $sql2 .= " AND e.id = ?";
        $params2[] = (int)$filterExec;
    }
    $sql2 .= " GROUP BY e.id";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params2);
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $revenueData[$r['id']] = $r;
    }
} catch (Exception $e) {}

// 3. Realized revenue per exec (released invoices)
$realizedRevenue = [];
try {
    $sql3 = "
        SELECT e.id,
            COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM employees e
        JOIN crm_leads l ON l.assigned_user_id = e.id
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN quote_items qi ON qi.quote_id = q.id
        JOIN sales_orders so ON so.linked_quote_id = q.id
        JOIN invoice_master im ON im.so_no = so.so_no AND im.status='released'
            AND im.invoice_date BETWEEN ? AND ?
        WHERE e.status='Active'
    ";
    $params3 = [$dateFrom, $dateTo];
    if ($filterExec) {
        $sql3 .= " AND e.id = ?";
        $params3[] = (int)$filterExec;
    }
    $sql3 .= " GROUP BY e.id";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute($params3);
    while ($r = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        $realizedRevenue[$r['id']] = (float)$r['revenue'];
    }
} catch (Exception $e) {}

// 4. Interactions per executive
$interactions = [];
try {
    $intSql = "SELECT handled_by, COUNT(*) as cnt FROM crm_lead_interactions WHERE interaction_date BETWEEN ? AND ? GROUP BY handled_by";
    $intStmt = $pdo->prepare($intSql);
    $intStmt->execute([$dateFrom, $dateTo]);
    while ($r = $intStmt->fetch(PDO::FETCH_ASSOC)) {
        $interactions[$r['handled_by']] = (int)$r['cnt'];
    }
} catch (Exception $e) {}

// 5. Pipeline value (hot+warm leads with quote values)
$pipeline = [];
try {
    $pipeSql = "
        SELECT l.assigned_user_id as eid,
            COALESCE(SUM(qi.total_amount), 0) as pipe_val
        FROM crm_leads l
        LEFT JOIN quote_master q ON q.reference = l.lead_no
        LEFT JOIN quote_items qi ON qi.quote_id = q.id
        WHERE l.lead_status IN ('hot','warm')
        GROUP BY l.assigned_user_id
    ";
    $pipeStmt = $pdo->query($pipeSql);
    while ($r = $pipeStmt->fetch(PDO::FETCH_ASSOC)) {
        $pipeline[$r['eid']] = (float)$r['pipe_val'];
    }
} catch (Exception $e) {}

// 6. New customers acquired (converted leads → distinct customers)
$newCustomers = [];
try {
    $ncSql = "
        SELECT l.assigned_user_id as eid, COUNT(DISTINCT c.id) as new_cust
        FROM crm_leads l
        JOIN customers c ON c.company_name = l.company_name
        WHERE l.lead_status = 'converted' AND l.created_at BETWEEN ? AND ?
        GROUP BY l.assigned_user_id
    ";
    $ncStmt = $pdo->prepare($ncSql);
    $ncStmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
    while ($r = $ncStmt->fetch(PDO::FETCH_ASSOC)) {
        $newCustomers[$r['eid']] = (int)$r['new_cust'];
    }
} catch (Exception $e) {}

// 7. Pending collections
$pendingCollections = [];
try {
    $pcSql = "
        SELECT e.id as eid,
            COUNT(DISTINCT im.id) as pending_invoices,
            COALESCE(SUM(qi.total_amount), 0) as pending_amount
        FROM employees e
        JOIN crm_leads l ON l.assigned_user_id = e.id
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN quote_items qi ON qi.quote_id = q.id
        JOIN sales_orders so ON so.linked_quote_id = q.id
        JOIN invoice_master im ON im.so_no = so.so_no
            AND im.status='released' AND (im.payment_status IS NULL OR im.payment_status != 'paid')
        WHERE e.status='Active'
        GROUP BY e.id
    ";
    $pcStmt = $pdo->query($pcSql);
    while ($r = $pcStmt->fetch(PDO::FETCH_ASSOC)) {
        $pendingCollections[$r['eid']] = $r;
    }
} catch (Exception $e) {}

// ── BUILD SCORECARD DATA ──
$scorecard = [];
$teamTotals = [
    'leads' => 0, 'converted' => 0, 'lost' => 0, 'hot' => 0, 'warm' => 0, 'cold' => 0,
    'quotes' => 0, 'quoted_value' => 0, 'orders' => 0, 'revenue' => 0,
    'interactions' => 0, 'pipeline' => 0, 'new_customers' => 0
];

foreach ($execData as $exec) {
    $eid = $exec['id'];
    $rev = $revenueData[$eid] ?? [];
    $realRev = $realizedRevenue[$eid] ?? 0;
    $intCount = $interactions[$exec['name']] ?? ($interactions[$exec['emp_id']] ?? 0);
    $pipeVal = $pipeline[$eid] ?? 0;
    $newCust = $newCustomers[$eid] ?? 0;
    $totalLeads = (int)$exec['total_leads'];
    $converted = (int)$exec['converted'];
    $quotes = (int)($rev['quotes'] ?? 0);
    $quotedVal = (float)($rev['quoted_value'] ?? 0);
    $orders = (int)($rev['orders'] ?? 0);
    $convRate = $totalLeads > 0 ? round($converted * 100 / $totalLeads, 1) : 0;
    $quoteToOrder = $quotes > 0 ? round($orders * 100 / $quotes, 1) : 0;
    $avgDeal = $converted > 0 ? $quotedVal / $converted : 0;

    $row = [
        'id' => $eid,
        'name' => $exec['name'],
        'designation' => $exec['designation'],
        'department' => $exec['department'],
        'emp_id' => $exec['emp_id'],
        'total_leads' => $totalLeads,
        'hot' => (int)$exec['hot'],
        'warm' => (int)$exec['warm'],
        'cold' => (int)$exec['cold'],
        'converted' => $converted,
        'lost' => (int)$exec['lost'],
        'conv_rate' => $convRate,
        'quotes' => $quotes,
        'quoted_value' => $quotedVal,
        'orders' => $orders,
        'revenue' => $realRev,
        'interactions' => $intCount,
        'pipeline' => $pipeVal,
        'new_customers' => $newCust,
        'avg_deal' => $avgDeal,
        'quote_to_order' => $quoteToOrder,
        'pending_inv' => $pendingCollections[$eid]['pending_invoices'] ?? 0,
        'pending_amt' => (float)($pendingCollections[$eid]['pending_amount'] ?? 0),
    ];

    // Only include executives who have any leads or activity
    if ($totalLeads > 0 || $intCount > 0 || $quotes > 0 || $realRev > 0) {
        $scorecard[] = $row;
        $teamTotals['leads'] += $totalLeads;
        $teamTotals['converted'] += $converted;
        $teamTotals['lost'] += (int)$exec['lost'];
        $teamTotals['hot'] += (int)$exec['hot'];
        $teamTotals['warm'] += (int)$exec['warm'];
        $teamTotals['cold'] += (int)$exec['cold'];
        $teamTotals['quotes'] += $quotes;
        $teamTotals['quoted_value'] += $quotedVal;
        $teamTotals['orders'] += $orders;
        $teamTotals['revenue'] += $realRev;
        $teamTotals['interactions'] += $intCount;
        $teamTotals['pipeline'] += $pipeVal;
        $teamTotals['new_customers'] += $newCust;
    }
}

$teamCount = count($scorecard);
$teamAvg = [];
if ($teamCount > 0) {
    foreach ($teamTotals as $k => $v) {
        $teamAvg[$k] = $v / $teamCount;
    }
    $teamAvg['conv_rate'] = $teamTotals['leads'] > 0 ? round($teamTotals['converted'] * 100 / $teamTotals['leads'], 1) : 0;
    $teamAvg['quote_to_order'] = $teamTotals['quotes'] > 0 ? round($teamTotals['orders'] * 100 / $teamTotals['quotes'], 1) : 0;
    $teamAvg['avg_deal'] = $teamTotals['converted'] > 0 ? $teamTotals['quoted_value'] / $teamTotals['converted'] : 0;
}

// ── COMPUTE SCORES ──
foreach ($scorecard as &$sc) {
    $score = 0;
    if ($teamCount > 0) {
        // Conversion Rate (25%)
        $maxConv = max(array_column($scorecard, 'conv_rate')) ?: 1;
        $score += ($sc['conv_rate'] / $maxConv) * 25;

        // Revenue (25%)
        $maxRev = max(array_column($scorecard, 'revenue')) ?: 1;
        $score += ($sc['revenue'] / $maxRev) * 25;

        // Interactions (15%)
        $maxInt = max(array_column($scorecard, 'interactions')) ?: 1;
        $score += ($sc['interactions'] / $maxInt) * 15;

        // Pipeline (15%)
        $maxPipe = max(array_column($scorecard, 'pipeline')) ?: 1;
        $score += ($sc['pipeline'] / $maxPipe) * 15;

        // New Customers (10%)
        $maxCust = max(array_column($scorecard, 'new_customers')) ?: 1;
        $score += ($sc['new_customers'] / $maxCust) * 10;

        // Quote-to-Order (10%)
        $maxQO = max(array_column($scorecard, 'quote_to_order')) ?: 1;
        $score += ($sc['quote_to_order'] / $maxQO) * 10;
    }
    $sc['score'] = round($score);

    // Generate improvement suggestions
    $suggestions = [];
    if ($teamCount > 1) {
        if ($sc['conv_rate'] < ($teamAvg['conv_rate'] ?? 0))
            $suggestions[] = ['Conversion rate (' . $sc['conv_rate'] . '%) is below team average (' . round($teamAvg['conv_rate'], 1) . '%). Focus on lead qualification and timely follow-ups.', '#e74c3c'];
        if ($sc['interactions'] < ($teamAvg['interactions'] ?? 0))
            $suggestions[] = ['Interaction count (' . $sc['interactions'] . ') is below average (' . round($teamAvg['interactions']) . '). Increase customer touchpoints through calls, emails and meetings.', '#f39c12'];
        if ($sc['lost'] > ($teamAvg['lost'] ?? 0))
            $suggestions[] = [$sc['lost'] . ' lost leads (above avg ' . round($teamAvg['lost']) . '). Review lost reasons and consider re-engagement campaigns.', '#e74c3c'];
        if ($sc['quotes'] > 0 && $sc['quote_to_order'] < ($teamAvg['quote_to_order'] ?? 0))
            $suggestions[] = ['Quote-to-order rate (' . $sc['quote_to_order'] . '%) is below team average. Improve quote follow-up and negotiate better terms.', '#f39c12'];
        if ($sc['hot'] == 0 && $sc['total_leads'] > 0)
            $suggestions[] = ['No hot leads currently. Prioritize lead warming activities, demos, and site visits.', '#e67e22'];
        if ($sc['avg_deal'] > 0 && $sc['avg_deal'] < ($teamAvg['avg_deal'] ?? 0))
            $suggestions[] = ['Average deal size is below team average. Focus on upselling and targeting larger accounts.', '#3498db'];
        if ($sc['new_customers'] < ($teamAvg['new_customers'] ?? 0))
            $suggestions[] = ['New customer acquisition (' . $sc['new_customers'] . ') is below average. Allocate more time to prospecting.', '#9b59b6'];
        if ($sc['pending_inv'] > 0)
            $suggestions[] = [$sc['pending_inv'] . ' pending invoice(s) worth ' . number_format($sc['pending_amt'], 0) . '. Follow up on collections.', '#e74c3c'];
    }
    if (empty($suggestions) && $sc['total_leads'] > 0)
        $suggestions[] = ['Performing well across all metrics. Maintain momentum and mentor team members.', '#27ae60'];
    if ($sc['total_leads'] == 0 && $sc['interactions'] == 0)
        $suggestions[] = ['No leads or interactions in this period. Start prospecting and logging activities.', '#e74c3c'];

    $sc['suggestions'] = $suggestions;
}
unset($sc);

// Sort by score descending
usort($scorecard, fn($a, $b) => $b['score'] - $a['score']);

// Format currency helper
function formatINR($amount) {
    if ($amount >= 10000000) return round($amount / 10000000, 2) . ' Cr';
    if ($amount >= 100000) return round($amount / 100000, 2) . ' L';
    if ($amount >= 1000) return round($amount / 1000, 1) . ' K';
    return number_format($amount, 0);
}

function scoreColor($score) {
    if ($score >= 80) return '#27ae60';
    if ($score >= 60) return '#2980b9';
    if ($score >= 40) return '#f39c12';
    return '#e74c3c';
}

function scoreLabel($score) {
    if ($score >= 80) return 'Excellent';
    if ($score >= 60) return 'Good';
    if ($score >= 40) return 'Average';
    return 'Needs Improvement';
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content" style="overflow-y: auto; height: 100vh; padding-bottom: 40px;">
    <div style="max-width: 1400px;">

        <!-- Page Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="margin: 0; color: #2c3e50;">Sales Executive Scorecard</h1>
                <p style="margin: 5px 0 0; color: #7f8c8d;">
                    Performance analysis: <?= date('d M Y', strtotime($dateFrom)) ?> - <?= date('d M Y', strtotime($dateTo)) ?>
                </p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">CRM Dashboard</a>
                <a href="sales_analytics.php" class="btn btn-secondary" style="margin-left: 5px;">Sales Analytics</a>
            </div>
        </div>

        <!-- Filters -->
        <div style="background: #f8f9fa; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap; width: 100%;">
                <div>
                    <label style="display: block; font-size: 0.85em; font-weight: 600; margin-bottom: 4px; color: #555;">From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85em; font-weight: 600; margin-bottom: 4px; color: #555;">To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85em; font-weight: 600; margin-bottom: 4px; color: #555;">Sales Executive</label>
                    <select name="exec_id" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; min-width: 200px;">
                        <option value="">All Executives</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filterExec == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['emp_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filter</button>
                <a href="sales_scorecard.php" class="btn btn-secondary" style="padding: 8px 15px;">Clear</a>

                <!-- Quick date presets -->
                <div style="margin-left: auto; display: flex; gap: 5px;">
                    <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-t') ?>&exec_id=<?= $filterExec ?>" class="btn btn-sm" style="background: #eee; color: #333; padding: 6px 10px; font-size: 0.8em;">This Month</a>
                    <a href="?date_from=<?= date('Y-m-01', strtotime('-1 month')) ?>&date_to=<?= date('Y-m-t', strtotime('-1 month')) ?>&exec_id=<?= $filterExec ?>" class="btn btn-sm" style="background: #eee; color: #333; padding: 6px 10px; font-size: 0.8em;">Last Month</a>
                    <a href="?date_from=<?= date('Y-01-01') ?>&date_to=<?= date('Y-m-t') ?>&exec_id=<?= $filterExec ?>" class="btn btn-sm" style="background: #eee; color: #333; padding: 6px 10px; font-size: 0.8em;">YTD</a>
                    <a href="?date_from=<?= date('Y-m-d', strtotime('-90 days')) ?>&date_to=<?= date('Y-m-d') ?>&exec_id=<?= $filterExec ?>" class="btn btn-sm" style="background: #eee; color: #333; padding: 6px 10px; font-size: 0.8em;">Last 90 Days</a>
                </div>
            </form>
        </div>

        <!-- KPI Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">Total Leads</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= $teamTotals['leads'] ?></div>
                <div style="font-size: 0.8em; opacity: 0.8;">Hot: <?= $teamTotals['hot'] ?> | Warm: <?= $teamTotals['warm'] ?></div>
            </div>
            <div style="background: linear-gradient(135deg, #11998e, #38ef7d); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">Conversion Rate</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= $teamTotals['leads'] > 0 ? round($teamTotals['converted'] * 100 / $teamTotals['leads'], 1) : 0 ?>%</div>
                <div style="font-size: 0.8em; opacity: 0.8;"><?= $teamTotals['converted'] ?> converted / <?= $teamTotals['leads'] ?> total</div>
            </div>
            <div style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">Revenue Realized</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= formatINR($teamTotals['revenue']) ?></div>
                <div style="font-size: 0.8em; opacity: 0.8;"><?= $teamTotals['orders'] ?> orders</div>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">Active Pipeline</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= formatINR($teamTotals['pipeline']) ?></div>
                <div style="font-size: 0.8em; opacity: 0.8;">Hot + Warm leads</div>
            </div>
            <div style="background: linear-gradient(135deg, #fa709a, #fee140); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">New Customers</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= $teamTotals['new_customers'] ?></div>
                <div style="font-size: 0.8em; opacity: 0.8;"><?= $teamCount ?> active exec<?= $teamCount != 1 ? 's' : '' ?></div>
            </div>
            <div style="background: linear-gradient(135deg, #a18cd1, #fbc2eb); color: white; padding: 20px; border-radius: 12px;">
                <div style="font-size: 0.85em; opacity: 0.9;">Avg Deal Size</div>
                <div style="font-size: 2em; font-weight: 700; margin: 5px 0;"><?= formatINR($teamAvg['avg_deal'] ?? 0) ?></div>
                <div style="font-size: 0.8em; opacity: 0.8;"><?= $teamTotals['quotes'] ?> quotes sent</div>
            </div>
        </div>

        <?php if (empty($scorecard)): ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="font-size: 48px; margin-bottom: 15px;">&#128202;</div>
            <h3 style="color: #7f8c8d;">No Sales Activity Found</h3>
            <p style="color: #95a5a6;">No leads, quotes, or interactions found for the selected date range.</p>
        </div>
        <?php else: ?>

        <!-- Performance Comparison Table -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; overflow: hidden;">
            <div style="padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: #2c3e50; font-size: 1.2em;">Performance Ranking</h2>
                <span style="color: #7f8c8d; font-size: 0.85em;"><?= $teamCount ?> executive<?= $teamCount != 1 ? 's' : '' ?> with activity</span>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #495057; white-space: nowrap;">#</th>
                            <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #495057;">Executive</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057;" title="Total Leads">Leads</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #e74c3c;" title="Hot Leads">Hot</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #f39c12;" title="Warm Leads">Warm</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #27ae60;" title="Converted">Conv</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #95a5a6;" title="Lost">Lost</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057;">Conv%</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057;">Quotes</th>
                            <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #495057;">Quoted Value</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057;">Orders</th>
                            <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #495057;">Revenue</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057;">Interactions</th>
                            <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #495057; white-space: nowrap;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scorecard as $rank => $sc): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0; cursor: pointer;" onclick="toggleDetail(<?= $sc['id'] ?>)" title="Click to see details">
                            <td style="padding: 12px 15px; font-weight: 600; color: <?= $rank < 3 ? '#f39c12' : '#999' ?>;">
                                <?php if ($rank == 0): ?>&#127942;<?php elseif ($rank == 1): ?>&#129352;<?php elseif ($rank == 2): ?>&#129353;<?php else: echo $rank + 1; endif; ?>
                            </td>
                            <td style="padding: 12px 15px;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($sc['name']) ?></div>
                                <div style="font-size: 0.8em; color: #999;"><?= htmlspecialchars($sc['designation'] ?: $sc['department'] ?: $sc['emp_id']) ?></div>
                            </td>
                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;"><?= $sc['total_leads'] ?></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <?php if ($sc['hot'] > 0): ?><span style="background: #fde8e8; color: #e74c3c; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?= $sc['hot'] ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <?php if ($sc['warm'] > 0): ?><span style="background: #fef3cd; color: #d68910; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?= $sc['warm'] ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <?php if ($sc['converted'] > 0): ?><span style="background: #d5f5e3; color: #27ae60; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?= $sc['converted'] ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td style="padding: 12px 15px; text-align: center; color: #999;"><?= $sc['lost'] ?: '-' ?></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <span style="font-weight: 600; color: <?= $sc['conv_rate'] >= ($teamAvg['conv_rate'] ?? 0) ? '#27ae60' : '#e74c3c' ?>;"><?= $sc['conv_rate'] ?>%</span>
                            </td>
                            <td style="padding: 12px 15px; text-align: center;"><?= $sc['quotes'] ?: '-' ?></td>
                            <td style="padding: 12px 15px; text-align: right; font-size: 0.9em;"><?= $sc['quoted_value'] > 0 ? formatINR($sc['quoted_value']) : '-' ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= $sc['orders'] ?: '-' ?></td>
                            <td style="padding: 12px 15px; text-align: right; font-weight: 600; color: #27ae60;"><?= $sc['revenue'] > 0 ? formatINR($sc['revenue']) : '-' ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= $sc['interactions'] ?: '-' ?></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: <?= scoreColor($sc['score']) ?>15; padding: 4px 12px; border-radius: 20px; border: 2px solid <?= scoreColor($sc['score']) ?>;">
                                    <span style="font-weight: 700; color: <?= scoreColor($sc['score']) ?>; font-size: 1.1em;"><?= $sc['score'] ?></span>
                                </div>
                            </td>
                        </tr>
                        <!-- Expandable Detail Row -->
                        <tr id="detail-<?= $sc['id'] ?>" style="display: none; background: #fafbfc;">
                            <td colspan="14" style="padding: 0;">
                                <div style="padding: 20px 25px; border-left: 4px solid <?= scoreColor($sc['score']) ?>;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <!-- Left: Metrics Detail -->
                                        <div>
                                            <h4 style="margin: 0 0 15px; color: #2c3e50;">Detailed Metrics</h4>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">Pipeline Value</div>
                                                    <div style="font-weight: 600; font-size: 1.1em;"><?= formatINR($sc['pipeline']) ?></div>
                                                </div>
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">New Customers</div>
                                                    <div style="font-weight: 600; font-size: 1.1em;"><?= $sc['new_customers'] ?></div>
                                                </div>
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">Avg Deal Size</div>
                                                    <div style="font-weight: 600; font-size: 1.1em;"><?= formatINR($sc['avg_deal']) ?></div>
                                                </div>
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">Quote-to-Order</div>
                                                    <div style="font-weight: 600; font-size: 1.1em;"><?= $sc['quote_to_order'] ?>%</div>
                                                </div>
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">Pending Invoices</div>
                                                    <div style="font-weight: 600; font-size: 1.1em; color: <?= $sc['pending_inv'] > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $sc['pending_inv'] ?> (<?= formatINR($sc['pending_amt']) ?>)</div>
                                                </div>
                                                <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #eee;">
                                                    <div style="font-size: 0.75em; color: #999; text-transform: uppercase;">Score Rating</div>
                                                    <div style="font-weight: 600; font-size: 1.1em; color: <?= scoreColor($sc['score']) ?>;"><?= scoreLabel($sc['score']) ?></div>
                                                </div>
                                            </div>

                                            <!-- Score Breakdown Bar -->
                                            <div style="margin-top: 15px;">
                                                <div style="font-size: 0.85em; font-weight: 600; margin-bottom: 8px; color: #555;">Score Breakdown</div>
                                                <div style="background: #e9ecef; border-radius: 8px; height: 24px; overflow: hidden; display: flex;">
                                                    <?php
                                                    $maxConv = max(array_column($scorecard, 'conv_rate')) ?: 1;
                                                    $maxRev = max(array_column($scorecard, 'revenue')) ?: 1;
                                                    $maxInt = max(array_column($scorecard, 'interactions')) ?: 1;
                                                    $maxPipe = max(array_column($scorecard, 'pipeline')) ?: 1;
                                                    $maxCust = max(array_column($scorecard, 'new_customers')) ?: 1;
                                                    $maxQO = max(array_column($scorecard, 'quote_to_order')) ?: 1;
                                                    $s1 = round(($sc['conv_rate'] / $maxConv) * 25);
                                                    $s2 = round(($sc['revenue'] / $maxRev) * 25);
                                                    $s3 = round(($sc['interactions'] / $maxInt) * 15);
                                                    $s4 = round(($sc['pipeline'] / $maxPipe) * 15);
                                                    $s5 = round(($sc['new_customers'] / $maxCust) * 10);
                                                    $s6 = round(($sc['quote_to_order'] / $maxQO) * 10);
                                                    ?>
                                                    <div style="width: <?= $s1 ?>%; background: #e74c3c;" title="Conversion: <?= $s1 ?>/25"></div>
                                                    <div style="width: <?= $s2 ?>%; background: #27ae60;" title="Revenue: <?= $s2 ?>/25"></div>
                                                    <div style="width: <?= $s3 ?>%; background: #3498db;" title="Interactions: <?= $s3 ?>/15"></div>
                                                    <div style="width: <?= $s4 ?>%; background: #f39c12;" title="Pipeline: <?= $s4 ?>/15"></div>
                                                    <div style="width: <?= $s5 ?>%; background: #9b59b6;" title="Customers: <?= $s5 ?>/10"></div>
                                                    <div style="width: <?= $s6 ?>%; background: #1abc9c;" title="Q-to-O: <?= $s6 ?>/10"></div>
                                                </div>
                                                <div style="display: flex; gap: 10px; margin-top: 6px; font-size: 0.7em; flex-wrap: wrap;">
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #e74c3c; border-radius: 2px;"></span> Conv (<?= $s1 ?>/25)</span>
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #27ae60; border-radius: 2px;"></span> Revenue (<?= $s2 ?>/25)</span>
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #3498db; border-radius: 2px;"></span> Activity (<?= $s3 ?>/15)</span>
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #f39c12; border-radius: 2px;"></span> Pipeline (<?= $s4 ?>/15)</span>
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #9b59b6; border-radius: 2px;"></span> Customers (<?= $s5 ?>/10)</span>
                                                    <span><span style="display: inline-block; width: 8px; height: 8px; background: #1abc9c; border-radius: 2px;"></span> Q-to-O (<?= $s6 ?>/10)</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right: Improvement Suggestions -->
                                        <div>
                                            <h4 style="margin: 0 0 15px; color: #2c3e50;">Improvement Areas</h4>
                                            <?php foreach ($sc['suggestions'] as $sug): ?>
                                            <div style="padding: 12px 15px; background: <?= $sug[1] ?>10; border-left: 3px solid <?= $sug[1] ?>; border-radius: 0 6px 6px 0; margin-bottom: 8px; font-size: 0.9em; color: #333;">
                                                <?= htmlspecialchars($sug[0]) ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <!-- Team Average Footer -->
                    <tfoot>
                        <tr style="background: #2c3e50; color: white; font-weight: 600;">
                            <td style="padding: 12px 15px;" colspan="2">Team Average</td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['leads'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['hot'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['warm'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['converted'] ?? 0, 1) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['lost'] ?? 0, 1) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= $teamAvg['conv_rate'] ?? 0 ?>%</td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['quotes'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: right;"><?= formatINR($teamAvg['quoted_value'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['orders'] ?? 0, 1) ?></td>
                            <td style="padding: 12px 15px; text-align: right;"><?= formatINR($teamAvg['revenue'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;"><?= round($teamAvg['interactions'] ?? 0) ?></td>
                            <td style="padding: 12px 15px; text-align: center;">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Score Legend -->
        <div style="background: white; border-radius: 12px; padding: 20px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px;">
            <h3 style="margin: 0 0 15px; color: #2c3e50; font-size: 1.1em;">Scoring Methodology</h3>
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: #27ae60; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.7em; font-weight: 700;">80+</div>
                    <span style="font-size: 0.85em;">Excellent</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: #2980b9; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.7em; font-weight: 700;">60+</div>
                    <span style="font-size: 0.85em;">Good</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: #f39c12; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.7em; font-weight: 700;">40+</div>
                    <span style="font-size: 0.85em;">Average</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: #e74c3c; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.6em; font-weight: 700;">&lt;40</div>
                    <span style="font-size: 0.85em;">Needs Improvement</span>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; font-size: 0.85em;">
                <div style="padding: 8px 12px; background: #fdf2f2; border-radius: 6px;"><strong style="color: #e74c3c;">25%</strong> Conversion Rate</div>
                <div style="padding: 8px 12px; background: #eafaf1; border-radius: 6px;"><strong style="color: #27ae60;">25%</strong> Revenue Realized</div>
                <div style="padding: 8px 12px; background: #ebf5fb; border-radius: 6px;"><strong style="color: #3498db;">15%</strong> Interaction Activity</div>
                <div style="padding: 8px 12px; background: #fef9e7; border-radius: 6px;"><strong style="color: #f39c12;">15%</strong> Pipeline Value</div>
                <div style="padding: 8px 12px; background: #f4ecf7; border-radius: 6px;"><strong style="color: #9b59b6;">10%</strong> New Customers</div>
                <div style="padding: 8px 12px; background: #e8f8f5; border-radius: 6px;"><strong style="color: #1abc9c;">10%</strong> Quote-to-Order Rate</div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    if (row) {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
}
</script>

<?php include "../includes/footer.php"; ?>
