<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');

/* =======================================================
   SALES BEHAVIOR & BUYER MENTALITY ANALYSIS
   Reads all interaction data and provides deep insights
   on buyer patterns and sales executive effectiveness.
======================================================= */

// Filters
$filter_from = $_GET['date_from'] ?? date('Y-m-01', strtotime('-6 months'));
$filter_to = $_GET['date_to'] ?? date('Y-m-d');
$filter_exec = $_GET['exec'] ?? '';

$dateWhere = "i.interaction_date BETWEEN ? AND ?";
$dateParams = [$filter_from . ' 00:00:00', $filter_to . ' 23:59:59'];

$execWhere = '';
$execParams = [];
if ($filter_exec) {
    $execWhere = " AND i.handled_by = ?";
    $execParams = [$filter_exec];
}

$allParams = array_merge($dateParams, $execParams);

// Get all handlers for filter dropdown
$handlers = $pdo->query("SELECT DISTINCT handled_by FROM crm_lead_interactions WHERE handled_by IS NOT NULL AND handled_by != '' ORDER BY handled_by")->fetchAll(PDO::FETCH_COLUMN);

/* =======================================================
   1. OVERALL INTERACTION METRICS
======================================================= */
$overallSql = "
    SELECT
        COUNT(DISTINCT i.lead_id) as total_leads_contacted,
        COUNT(i.id) as total_interactions,
        COUNT(DISTINCT i.handled_by) as active_executives,
        SUM(CASE WHEN l.lead_status = 'converted' THEN 1 ELSE 0 END) as interactions_on_converted,
        SUM(CASE WHEN l.lead_status = 'lost' THEN 1 ELSE 0 END) as interactions_on_lost,
        ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT i.lead_id), 0), 1) as avg_interactions_per_lead,
        ROUND(COUNT(i.id) / NULLIF(DATEDIFF(?, ?), 0), 1) as avg_per_day
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    WHERE $dateWhere $execWhere
";
$overallStmt = $pdo->prepare($overallSql);
$overallStmt->execute(array_merge([$filter_to, $filter_from], $allParams));
$overall = $overallStmt->fetch(PDO::FETCH_ASSOC);

/* =======================================================
   2. LEAD STATUS JOURNEY - How many interactions to convert?
======================================================= */
$journeySql = "
    SELECT l.lead_status,
        COUNT(DISTINCT l.id) as lead_count,
        COUNT(i.id) as total_interactions,
        ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT l.id), 0), 1) as avg_interactions,
        SUM(CASE WHEN i.interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
        SUM(CASE WHEN i.interaction_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
        SUM(CASE WHEN i.interaction_type = 'site_visit' THEN 1 ELSE 0 END) as site_visits,
        SUM(CASE WHEN i.interaction_type = 'demo' THEN 1 ELSE 0 END) as demos,
        SUM(CASE WHEN i.interaction_type = 'quotation_sent' THEN 1 ELSE 0 END) as quotations,
        SUM(CASE WHEN i.interaction_type = 'email' THEN 1 ELSE 0 END) as emails,
        SUM(CASE WHEN i.interaction_type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp,
        ROUND(AVG(DATEDIFF(COALESCE(l.last_contact_date, NOW()), l.created_at)), 0) as avg_engagement_days
    FROM crm_leads l
    LEFT JOIN crm_lead_interactions i ON i.lead_id = l.id AND $dateWhere $execWhere
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions WHERE $dateWhere $execWhere)
    GROUP BY l.lead_status
    ORDER BY FIELD(l.lead_status, 'hot', 'warm', 'cold', 'converted', 'lost')
";
$journeyStmt = $pdo->prepare($journeySql);
$journeyStmt->execute(array_merge($allParams, $allParams));
$journey = $journeyStmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================================================
   3. CONVERSION FUNNEL - Interaction types that lead to conversion
======================================================= */
$funnelSql = "
    SELECT i.interaction_type,
        COUNT(*) as total_count,
        SUM(CASE WHEN l.lead_status = 'converted' THEN 1 ELSE 0 END) as on_converted_leads,
        SUM(CASE WHEN l.lead_status = 'lost' THEN 1 ELSE 0 END) as on_lost_leads,
        SUM(CASE WHEN l.lead_status IN ('hot','warm') THEN 1 ELSE 0 END) as on_active_leads,
        ROUND(SUM(CASE WHEN l.lead_status = 'converted' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as conversion_correlation
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    WHERE $dateWhere $execWhere
    GROUP BY i.interaction_type
    ORDER BY total_count DESC
";
$funnelStmt = $pdo->prepare($funnelSql);
$funnelStmt->execute($allParams);
$funnel = $funnelStmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================================================
   4. EXECUTIVE-WISE DEEP ANALYSIS
======================================================= */
$execSql = "
    SELECT i.handled_by,
        COUNT(i.id) as total_interactions,
        COUNT(DISTINCT i.lead_id) as leads_touched,
        SUM(CASE WHEN l.lead_status = 'converted' THEN 1 ELSE 0 END) as converted_interactions,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as converted_leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'lost' THEN l.id END) as lost_leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'hot' THEN l.id END) as hot_leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'warm' THEN l.id END) as warm_leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'cold' THEN l.id END) as cold_leads,
        ROUND(COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) * 100.0 /
              NULLIF(COUNT(DISTINCT i.lead_id), 0), 1) as conversion_rate,
        ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT i.lead_id), 0), 1) as avg_interactions_per_lead,
        SUM(CASE WHEN i.interaction_type = 'call' THEN 1 ELSE 0 END) as calls,
        SUM(CASE WHEN i.interaction_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
        SUM(CASE WHEN i.interaction_type = 'site_visit' THEN 1 ELSE 0 END) as site_visits,
        SUM(CASE WHEN i.interaction_type = 'demo' THEN 1 ELSE 0 END) as demos,
        SUM(CASE WHEN i.interaction_type = 'quotation_sent' THEN 1 ELSE 0 END) as quotations,
        SUM(CASE WHEN i.interaction_type = 'email' THEN 1 ELSE 0 END) as emails,
        SUM(CASE WHEN i.interaction_type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp,
        SUM(CASE WHEN i.next_action IS NOT NULL AND i.next_action != '' THEN 1 ELSE 0 END) as has_next_action,
        SUM(CASE WHEN i.next_action_date IS NOT NULL AND i.next_action_date < CURDATE()
            AND l.lead_status NOT IN ('converted','lost') THEN 1 ELSE 0 END) as overdue_actions,
        SUM(CASE WHEN i.outcome IS NOT NULL AND i.outcome != '' THEN 1 ELSE 0 END) as has_outcome,
        SUM(CASE WHEN i.description IS NOT NULL AND i.description != '' THEN 1 ELSE 0 END) as has_description,
        MIN(i.interaction_date) as first_interaction,
        MAX(i.interaction_date) as last_interaction,
        DATEDIFF(MAX(i.interaction_date), MIN(i.interaction_date)) as active_days
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    WHERE $dateWhere $execWhere
    AND i.handled_by IS NOT NULL AND i.handled_by != ''
    GROUP BY i.handled_by
    ORDER BY total_interactions DESC
";
$execStmt = $pdo->prepare($execSql);
$execStmt->execute($allParams);
$executives = $execStmt->fetchAll(PDO::FETCH_ASSOC);

// Compute team averages for comparison
$teamAvg = [
    'interactions' => 0, 'leads' => 0, 'conversion_rate' => 0,
    'avg_per_lead' => 0, 'calls' => 0, 'meetings' => 0, 'demos' => 0
];
if (count($executives) > 0) {
    foreach ($executives as $e) {
        $teamAvg['interactions'] += $e['total_interactions'];
        $teamAvg['leads'] += $e['leads_touched'];
        $teamAvg['conversion_rate'] += $e['conversion_rate'];
        $teamAvg['avg_per_lead'] += $e['avg_interactions_per_lead'];
        $teamAvg['calls'] += $e['calls'];
        $teamAvg['meetings'] += $e['meetings'];
        $teamAvg['demos'] += $e['demos'];
    }
    $n = count($executives);
    $teamAvg['interactions'] = round($teamAvg['interactions'] / $n, 1);
    $teamAvg['leads'] = round($teamAvg['leads'] / $n, 1);
    $teamAvg['conversion_rate'] = round($teamAvg['conversion_rate'] / $n, 1);
    $teamAvg['avg_per_lead'] = round($teamAvg['avg_per_lead'] / $n, 1);
    $teamAvg['calls'] = round($teamAvg['calls'] / $n, 1);
    $teamAvg['meetings'] = round($teamAvg['meetings'] / $n, 1);
    $teamAvg['demos'] = round($teamAvg['demos'] / $n, 1);
}

/* =======================================================
   5. BUYER BEHAVIOR PATTERNS - Analyze descriptions & outcomes
======================================================= */
// Most common outcomes
$outcomesSql = "
    SELECT LOWER(TRIM(i.outcome)) as outcome_text, COUNT(*) as cnt,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as led_to_conversion,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'lost' THEN l.id END) as led_to_loss
    FROM crm_lead_interactions i
    JOIN crm_leads l ON l.id = i.lead_id
    WHERE $dateWhere $execWhere
    AND i.outcome IS NOT NULL AND i.outcome != ''
    GROUP BY outcome_text
    ORDER BY cnt DESC
    LIMIT 20
";
$outcomesStmt = $pdo->prepare($outcomesSql);
$outcomesStmt->execute($allParams);
$topOutcomes = $outcomesStmt->fetchAll(PDO::FETCH_ASSOC);

// Lead source effectiveness
$sourceSql = "
    SELECT l.lead_source,
        COUNT(DISTINCT l.id) as leads,
        COUNT(i.id) as interactions,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as converted,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'lost' THEN l.id END) as lost,
        ROUND(COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) * 100.0 /
              NULLIF(COUNT(DISTINCT l.id), 0), 1) as conv_rate,
        ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT l.id), 0), 1) as avg_effort
    FROM crm_leads l
    LEFT JOIN crm_lead_interactions i ON i.lead_id = l.id AND $dateWhere $execWhere
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions WHERE $dateWhere $execWhere)
    AND l.lead_source IS NOT NULL AND l.lead_source != ''
    GROUP BY l.lead_source
    ORDER BY leads DESC
";
$sourceStmt = $pdo->prepare($sourceSql);
$sourceStmt->execute(array_merge($allParams, $allParams));
$sources = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);

// Buying timeline analysis
$timelineSql = "
    SELECT l.buying_timeline,
        COUNT(DISTINCT l.id) as leads,
        COUNT(i.id) as interactions,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as converted,
        ROUND(COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) * 100.0 /
              NULLIF(COUNT(DISTINCT l.id), 0), 1) as conv_rate
    FROM crm_leads l
    LEFT JOIN crm_lead_interactions i ON i.lead_id = l.id AND $dateWhere $execWhere
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions WHERE $dateWhere $execWhere)
    AND l.buying_timeline IS NOT NULL AND l.buying_timeline != ''
    GROUP BY l.buying_timeline
    ORDER BY FIELD(l.buying_timeline, 'immediate', '1_month', '3_months', '6_months', '1_year', 'uncertain')
";
$timelineStmt = $pdo->prepare($timelineSql);
$timelineStmt->execute(array_merge($allParams, $allParams));
$timelines = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);

// Decision maker analysis
$dmSql = "
    SELECT l.decision_maker,
        COUNT(DISTINCT l.id) as leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as converted,
        ROUND(COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) * 100.0 /
              NULLIF(COUNT(DISTINCT l.id), 0), 1) as conv_rate,
        ROUND(COUNT(i.id) / NULLIF(COUNT(DISTINCT l.id), 0), 1) as avg_effort
    FROM crm_leads l
    LEFT JOIN crm_lead_interactions i ON i.lead_id = l.id AND $dateWhere $execWhere
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions WHERE $dateWhere $execWhere)
    AND l.decision_maker IS NOT NULL AND l.decision_maker != ''
    GROUP BY l.decision_maker
";
$dmStmt = $pdo->prepare($dmSql);
$dmStmt->execute(array_merge($allParams, $allParams));
$decisionMakers = $dmStmt->fetchAll(PDO::FETCH_ASSOC);

// Response time analysis - time between first interaction and status change
$responseSql = "
    SELECT l.lead_status,
        ROUND(AVG(DATEDIFF(
            (SELECT MIN(i2.interaction_date) FROM crm_lead_interactions i2 WHERE i2.lead_id = l.id),
            l.created_at
        )), 1) as avg_days_to_first_contact,
        ROUND(AVG(
            TIMESTAMPDIFF(DAY, l.created_at, COALESCE(l.last_contact_date, NOW()))
        ), 0) as avg_total_engagement_days
    FROM crm_leads l
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions i WHERE $dateWhere $execWhere)
    GROUP BY l.lead_status
    ORDER BY FIELD(l.lead_status, 'hot', 'warm', 'cold', 'converted', 'lost')
";
$responseStmt = $pdo->prepare($responseSql);
$responseStmt->execute($allParams);
$responseTimes = $responseStmt->fetchAll(PDO::FETCH_ASSOC);

// Market classification analysis
$marketSql = "
    SELECT COALESCE(l.market_classification, 'Not Specified') as market,
        COUNT(DISTINCT l.id) as leads,
        COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) as converted,
        ROUND(COUNT(DISTINCT CASE WHEN l.lead_status = 'converted' THEN l.id END) * 100.0 /
              NULLIF(COUNT(DISTINCT l.id), 0), 1) as conv_rate
    FROM crm_leads l
    WHERE l.id IN (SELECT DISTINCT lead_id FROM crm_lead_interactions i WHERE $dateWhere $execWhere)
    GROUP BY market
    ORDER BY leads DESC
";
$marketStmt = $pdo->prepare($marketSql);
$marketStmt->execute($allParams);
$markets = $marketStmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Behavior & Buyer Analysis - CRM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .analysis-page { max-width: 1200px; }
        .page-title { margin: 0 0 5px 0; color: #2c3e50; }
        .page-subtitle { color: #7f8c8d; margin: 0 0 20px 0; font-size: 0.95em; }

        .filters-bar {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: end;
            padding: 15px 20px; background: #f8f9fa; border-radius: 10px;
            margin-bottom: 25px; border: 1px solid #eee;
        }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.8em; font-weight: 700; color: #555; }
        .filter-group select, .filter-group input {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9em;
        }

        .section { margin-bottom: 30px; }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; background: #2c3e50; color: white;
            border-radius: 10px 10px 0 0; cursor: pointer;
        }
        .section-header h2 { margin: 0; font-size: 1.05em; }
        .section-header .arrow { font-size: 0.8em; transition: transform 0.2s; }
        .section-body {
            background: white; border: 1px solid #eee; border-top: none;
            border-radius: 0 0 10px 10px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        /* KPI Row */
        .kpi-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-bottom: 25px;
        }
        .kpi-card {
            padding: 15px; border-radius: 10px; text-align: center; color: white;
        }
        .kpi-card .kpi-value { font-size: 1.8em; font-weight: bold; }
        .kpi-card .kpi-label { font-size: 0.78em; opacity: 0.9; margin-top: 4px; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .data-table th {
            background: #34495e; color: white; padding: 10px 12px;
            text-align: center; font-size: 0.85em; white-space: nowrap;
        }
        .data-table th:first-child { text-align: left; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; text-align: center; }
        .data-table td:first-child { text-align: left; font-weight: 600; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table tfoot td { background: #ecf0f1; font-weight: bold; border-top: 2px solid #bdc3c7; }

        /* Badges */
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .s-hot { background: #e74c3c; color: #fff; }
        .s-warm { background: #f39c12; color: #fff; }
        .s-cold { background: #bdc3c7; color: #2c3e50; }
        .s-converted { background: #27ae60; color: #fff; }
        .s-lost { background: #7f8c8d; color: #fff; }

        /* Insight Cards */
        .insight-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; }
        .insight-card {
            background: white; border-radius: 10px; padding: 18px;
            border: 1px solid #eee; box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .insight-card h4 { margin: 0 0 10px 0; color: #2c3e50; font-size: 0.95em; }
        .insight-card .insight-text { color: #555; font-size: 0.9em; line-height: 1.6; }

        /* Exec Card */
        .exec-card {
            background: white; border: 1px solid #eee; border-radius: 10px;
            margin-bottom: 15px; overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .exec-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .exec-header h3 { margin: 0; font-size: 1em; color: #2c3e50; }
        .exec-header .exec-stats { display: flex; gap: 15px; font-size: 0.85em; color: #666; }
        .exec-header .exec-stats strong { color: #2c3e50; }
        .exec-body { padding: 20px; display: none; }
        .exec-body.open { display: block; }

        .metric-bar {
            display: flex; align-items: center; margin-bottom: 8px; gap: 10px;
        }
        .metric-bar .metric-label { width: 120px; font-size: 0.85em; color: #555; font-weight: 600; }
        .metric-bar .bar-track { flex: 1; height: 20px; background: #ecf0f1; border-radius: 10px; overflow: hidden; position: relative; }
        .metric-bar .bar-fill { height: 100%; border-radius: 10px; transition: width 0.3s; }
        .metric-bar .bar-value { width: 50px; text-align: right; font-size: 0.85em; font-weight: bold; }

        .suggestion-box {
            background: #fff8e1; border: 1px solid #ffecb3; border-radius: 8px;
            padding: 12px 15px; margin-top: 12px; font-size: 0.88em; color: #795548;
        }
        .suggestion-box strong { color: #e65100; }
        .suggestion-box ul { margin: 8px 0 0; padding-left: 18px; }
        .suggestion-box li { margin: 4px 0; }

        .good-point { color: #27ae60; }
        .warn-point { color: #e67e22; }
        .bad-point { color: #e74c3c; }

        .quick-links { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    </style>
</head>
<body>

<div class="content">
    <div class="analysis-page">

        <div class="quick-links">
            <a href="index.php" class="btn btn-secondary">Back to Leads</a>
            <a href="interaction_report.php" class="btn btn-secondary">Interaction Report</a>
            <a href="sales_scorecard.php" class="btn btn-secondary">Sales Scorecard</a>
            <a href="dashboard.php" class="btn btn-secondary">CRM Dashboard</a>
        </div>

        <h1 class="page-title">Sales Behavior & Buyer Mentality Analysis</h1>
        <p class="page-subtitle">Deep analysis of interaction patterns, buyer behavior, and executive effectiveness with actionable improvement suggestions.</p>

        <!-- Filters -->
        <form method="get" class="filters-bar">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_from) ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_to) ?>">
            </div>
            <div class="filter-group">
                <label>Sales Executive</label>
                <select name="exec">
                    <option value="">All Executives</option>
                    <?php foreach ($handlers as $h): ?>
                        <option value="<?= htmlspecialchars($h) ?>" <?= $filter_exec === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Analyze</button>
                    <a href="sales_behavior_analysis.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <!-- ====== SECTION 1: OVERALL KPIs ====== -->
        <div class="kpi-row">
            <div class="kpi-card" style="background: #3498db;">
                <div class="kpi-value"><?= $overall['total_leads_contacted'] ?? 0 ?></div>
                <div class="kpi-label">Leads Contacted</div>
            </div>
            <div class="kpi-card" style="background: #2c3e50;">
                <div class="kpi-value"><?= $overall['total_interactions'] ?? 0 ?></div>
                <div class="kpi-label">Total Interactions</div>
            </div>
            <div class="kpi-card" style="background: #8e44ad;">
                <div class="kpi-value"><?= $overall['avg_interactions_per_lead'] ?? 0 ?></div>
                <div class="kpi-label">Avg per Lead</div>
            </div>
            <div class="kpi-card" style="background: #27ae60;">
                <div class="kpi-value"><?= $overall['avg_per_day'] ?? 0 ?></div>
                <div class="kpi-label">Avg per Day</div>
            </div>
            <div class="kpi-card" style="background: #e67e22;">
                <div class="kpi-value"><?= $overall['active_executives'] ?? 0 ?></div>
                <div class="kpi-label">Active Executives</div>
            </div>
        </div>

        <!-- ====== SECTION 2: BUYER MENTALITY INSIGHTS ====== -->
        <div class="section">
            <div class="section-header" onclick="toggleSec('buyerInsights')">
                <h2>Buyer Mentality & Behavior Patterns</h2>
                <span class="arrow" id="buyerInsightsArrow">&#9660;</span>
            </div>
            <div class="section-body" id="buyerInsights" style="display:block;">

                <h3 style="margin-top:0; color:#2c3e50;">How Much Effort to Convert?</h3>
                <p style="color:#666; font-size:0.9em; margin-bottom:15px;">Interactions needed per lead status - reveals buyer resistance level and sales effort required.</p>

                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lead Status</th>
                            <th>Leads</th>
                            <th>Total Interactions</th>
                            <th>Avg Interactions/Lead</th>
                            <th>Calls</th>
                            <th>Meetings</th>
                            <th>Site Visits</th>
                            <th>Demos</th>
                            <th>Quotations</th>
                            <th>Avg Days Engaged</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($journey as $j): ?>
                        <tr>
                            <td><span class="status-badge s-<?= $j['lead_status'] ?>"><?= ucfirst($j['lead_status']) ?></span></td>
                            <td><strong><?= $j['lead_count'] ?></strong></td>
                            <td><?= $j['total_interactions'] ?></td>
                            <td><strong><?= $j['avg_interactions'] ?></strong></td>
                            <td><?= $j['calls'] ?></td>
                            <td><?= $j['meetings'] ?></td>
                            <td><?= $j['site_visits'] ?></td>
                            <td><?= $j['demos'] ?></td>
                            <td><?= $j['quotations'] ?></td>
                            <td><?= $j['avg_engagement_days'] ?> days</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php
                // Auto-generate buyer insights
                $convertedData = null; $lostData = null; $hotData = null;
                foreach ($journey as $j) {
                    if ($j['lead_status'] === 'converted') $convertedData = $j;
                    if ($j['lead_status'] === 'lost') $lostData = $j;
                    if ($j['lead_status'] === 'hot') $hotData = $j;
                }
                ?>

                <div class="insight-grid" style="margin-top:20px;">
                    <?php if ($convertedData): ?>
                    <div class="insight-card" style="border-left: 4px solid #27ae60;">
                        <h4 class="good-point">Conversion Pattern</h4>
                        <div class="insight-text">
                            Converted leads needed an average of <strong><?= $convertedData['avg_interactions'] ?> interactions</strong>
                            over <strong><?= $convertedData['avg_engagement_days'] ?> days</strong>.<br>
                            Key activities: <?= $convertedData['calls'] ?> calls, <?= $convertedData['meetings'] ?> meetings,
                            <?= $convertedData['demos'] ?> demos, <?= $convertedData['quotations'] ?> quotations sent.
                            <br><br><strong>Insight:</strong> This is the effort benchmark to convert a lead. If a lead has fewer interactions than this, it may need more push.
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($lostData): ?>
                    <div class="insight-card" style="border-left: 4px solid #e74c3c;">
                        <h4 class="bad-point">Lost Lead Pattern</h4>
                        <div class="insight-text">
                            Lost leads had <strong><?= $lostData['avg_interactions'] ?> avg interactions</strong>
                            over <strong><?= $lostData['avg_engagement_days'] ?> days</strong>.<br>
                            <?php if ($convertedData && $lostData['avg_interactions'] < $convertedData['avg_interactions']): ?>
                                <strong>Red Flag:</strong> Lost leads got fewer interactions (<?= $lostData['avg_interactions'] ?>) than converted ones (<?= $convertedData['avg_interactions'] ?>).
                                This suggests leads were <strong>abandoned too early</strong> before exhausting conversion potential.
                            <?php else: ?>
                                Lost leads received adequate interaction effort. The loss may be due to pricing, competition, or buyer readiness rather than insufficient follow-up.
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Buying Timeline Insight -->
                    <?php if (!empty($timelines)): ?>
                    <div class="insight-card" style="border-left: 4px solid #3498db;">
                        <h4>Buyer Readiness (Timeline)</h4>
                        <div class="insight-text">
                            <?php foreach ($timelines as $t):
                                $label = str_replace('_', ' ', $t['buying_timeline']);
                            ?>
                                <strong><?= ucfirst($label) ?>:</strong> <?= $t['leads'] ?> leads, <?= $t['conv_rate'] ?>% conversion<br>
                            <?php endforeach; ?>
                            <br><strong>Insight:</strong> Prioritize "immediate" and "1 month" buyers - they have the highest purchase intent.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Decision Maker Insight -->
                    <?php if (!empty($decisionMakers)): ?>
                    <div class="insight-card" style="border-left: 4px solid #8e44ad;">
                        <h4>Decision Maker Impact</h4>
                        <div class="insight-text">
                            <?php foreach ($decisionMakers as $dm):
                                $dmLabel = $dm['decision_maker'] === 'yes' ? 'Decision Maker' : ($dm['decision_maker'] === 'influencer' ? 'Influencer' : 'Not DM');
                            ?>
                                <strong><?= $dmLabel ?>:</strong> <?= $dm['leads'] ?> leads, <?= $dm['conv_rate'] ?>% conv, ~<?= $dm['avg_effort'] ?> interactions/lead<br>
                            <?php endforeach; ?>
                            <br><strong>Insight:</strong> Engaging decision makers directly typically requires fewer interactions. Push team to identify and reach decision makers early.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Lead Source Effectiveness -->
                <?php if (!empty($sources)): ?>
                <h3 style="margin-top:25px; color:#2c3e50;">Lead Source Effectiveness</h3>
                <p style="color:#666; font-size:0.9em; margin-bottom:10px;">Which lead sources yield the best conversion with the least effort?</p>
                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Source</th><th>Leads</th><th>Interactions</th><th>Converted</th><th>Lost</th><th>Conv %</th><th>Avg Effort/Lead</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sources as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['lead_source']) ?></td>
                            <td><?= $s['leads'] ?></td>
                            <td><?= $s['interactions'] ?></td>
                            <td class="good-point"><strong><?= $s['converted'] ?></strong></td>
                            <td class="bad-point"><?= $s['lost'] ?></td>
                            <td><strong><?= $s['conv_rate'] ?>%</strong></td>
                            <td><?= $s['avg_effort'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

                <!-- Market Classification -->
                <?php if (!empty($markets)): ?>
                <h3 style="margin-top:25px; color:#2c3e50;">Market Segment Performance</h3>
                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Market Segment</th><th>Leads</th><th>Converted</th><th>Conv %</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($markets as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['market']) ?></td>
                            <td><?= $m['leads'] ?></td>
                            <td class="good-point"><strong><?= $m['converted'] ?></strong></td>
                            <td><strong><?= $m['conv_rate'] ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

                <!-- Interaction Type Conversion Correlation -->
                <h3 style="margin-top:25px; color:#2c3e50;">Which Activities Drive Conversions?</h3>
                <p style="color:#666; font-size:0.9em; margin-bottom:10px;">Correlation between interaction types and lead outcomes.</p>
                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Activity Type</th><th>Total</th><th>On Converted Leads</th><th>On Lost Leads</th><th>On Active Leads</th><th>Conversion Correlation</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($funnel as $f): ?>
                        <tr>
                            <td><?= ucfirst(str_replace('_', ' ', $f['interaction_type'])) ?></td>
                            <td><strong><?= $f['total_count'] ?></strong></td>
                            <td class="good-point"><?= $f['on_converted_leads'] ?></td>
                            <td class="bad-point"><?= $f['on_lost_leads'] ?></td>
                            <td><?= $f['on_active_leads'] ?></td>
                            <td><strong><?= $f['conversion_correlation'] ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Response Time -->
                <?php if (!empty($responseTimes)): ?>
                <h3 style="margin-top:25px; color:#2c3e50;">Response Speed by Lead Outcome</h3>
                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Lead Status</th><th>Avg Days to First Contact</th><th>Avg Total Engagement Days</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($responseTimes as $rt): ?>
                        <tr>
                            <td><span class="status-badge s-<?= $rt['lead_status'] ?>"><?= ucfirst($rt['lead_status']) ?></span></td>
                            <td><?= $rt['avg_days_to_first_contact'] ?? '-' ?> days</td>
                            <td><?= $rt['avg_total_engagement_days'] ?? '-' ?> days</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

                <!-- Common Outcomes -->
                <?php if (!empty($topOutcomes)): ?>
                <h3 style="margin-top:25px; color:#2c3e50;">Most Common Interaction Outcomes</h3>
                <p style="color:#666; font-size:0.9em; margin-bottom:10px;">What outcomes are recorded most often? Reveals buyer responses and sales patterns.</p>
                <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr><th>Outcome</th><th>Count</th><th>Led to Conversion</th><th>Led to Loss</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topOutcomes as $o): ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst($o['outcome_text'])) ?></td>
                            <td><strong><?= $o['cnt'] ?></strong></td>
                            <td class="good-point"><?= $o['led_to_conversion'] ?></td>
                            <td class="bad-point"><?= $o['led_to_loss'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ====== SECTION 3: EXECUTIVE-WISE ANALYSIS ====== -->
        <div class="section">
            <div class="section-header" onclick="toggleSec('execAnalysis')">
                <h2>Sales Executive-wise Analysis & Improvement Plan</h2>
                <span class="arrow" id="execAnalysisArrow">&#9660;</span>
            </div>
            <div class="section-body" id="execAnalysis" style="display:block;">

                <?php if (!empty($executives)): ?>
                <p style="color:#666; font-size:0.9em; margin-bottom:15px;">
                    Team Average: <?= $teamAvg['interactions'] ?> interactions | <?= $teamAvg['leads'] ?> leads |
                    <?= $teamAvg['conversion_rate'] ?>% conversion | <?= $teamAvg['avg_per_lead'] ?> interactions/lead.
                    Click each executive for detailed breakdown.
                </p>

                <?php foreach ($executives as $idx => $exec):
                    $convRate = $exec['conversion_rate'];
                    $avgPerLead = $exec['avg_interactions_per_lead'];
                    $docRate = $exec['has_description'] > 0 ? round($exec['has_description'] * 100 / $exec['total_interactions'], 0) : 0;
                    $outcomeRate = $exec['has_outcome'] > 0 ? round($exec['has_outcome'] * 100 / $exec['total_interactions'], 0) : 0;
                    $nextActionRate = $exec['has_next_action'] > 0 ? round($exec['has_next_action'] * 100 / $exec['total_interactions'], 0) : 0;
                    $callPct = $exec['total_interactions'] > 0 ? round($exec['calls'] * 100 / $exec['total_interactions'], 0) : 0;
                    $meetingPct = $exec['total_interactions'] > 0 ? round($exec['meetings'] * 100 / $exec['total_interactions'], 0) : 0;
                    $demoPct = $exec['total_interactions'] > 0 ? round($exec['demos'] * 100 / $exec['total_interactions'], 0) : 0;

                    // Color code conversion rate
                    $convColor = '#e74c3c';
                    if ($convRate >= 30) $convColor = '#27ae60';
                    elseif ($convRate >= 15) $convColor = '#f39c12';

                    // Generate suggestions
                    $suggestions = [];
                    if ($convRate < $teamAvg['conversion_rate'] && $teamAvg['conversion_rate'] > 0) {
                        $suggestions[] = "Conversion rate ({$convRate}%) is below team average ({$teamAvg['conversion_rate']}%). Focus on <strong>lead qualification</strong> - spend more time understanding buyer needs before pitching.";
                    }
                    if ($avgPerLead < $teamAvg['avg_per_lead'] && $exec['lost_leads'] > $exec['converted_leads']) {
                        $suggestions[] = "Low follow-up intensity ({$avgPerLead} vs {$teamAvg['avg_per_lead']} avg). Leads may be <strong>abandoned too early</strong>. Increase touchpoints before marking as lost.";
                    }
                    if ($exec['meetings'] < $teamAvg['meetings'] && $teamAvg['meetings'] > 0) {
                        $suggestions[] = "Fewer meetings ({$exec['meetings']}) than team average ({$teamAvg['meetings']}). Push for <strong>face-to-face meetings</strong> - they build trust faster than calls.";
                    }
                    if ($exec['demos'] < $teamAvg['demos'] && $teamAvg['demos'] > 0) {
                        $suggestions[] = "Low demo count ({$exec['demos']}). Demos are the strongest conversion driver. <strong>Offer product demonstrations</strong> to warm and hot leads.";
                    }
                    if ($exec['quotations'] == 0) {
                        $suggestions[] = "No quotations sent in this period. If leads are warm/hot, <strong>send quotations promptly</strong> to show commitment.";
                    }
                    if ($docRate < 50) {
                        $suggestions[] = "Only {$docRate}% of interactions have descriptions logged. <strong>Document every interaction</strong> - it helps track buyer psychology and handover context.";
                    }
                    if ($outcomeRate < 50) {
                        $suggestions[] = "Only {$outcomeRate}% of interactions have outcomes recorded. <strong>Always log the outcome</strong> - it reveals buyer sentiment and next steps.";
                    }
                    if ($nextActionRate < 60) {
                        $suggestions[] = "Only {$nextActionRate}% have a planned next action. <strong>Always set a follow-up action</strong> after every interaction to maintain momentum.";
                    }
                    if ($exec['overdue_actions'] > 0) {
                        $suggestions[] = "<strong class='bad-point'>{$exec['overdue_actions']} overdue follow-ups!</strong> Clear these immediately - delayed follow-up is the #1 reason for losing warm leads.";
                    }
                    if ($exec['cold_leads'] > ($exec['hot_leads'] + $exec['warm_leads']) * 2 && $exec['cold_leads'] > 3) {
                        $suggestions[] = "Too many cold leads ({$exec['cold_leads']}) vs active ({$exec['hot_leads']}H + {$exec['warm_leads']}W). Focus energy on <strong>warming existing leads</strong> rather than accumulating new cold ones.";
                    }
                    if ($callPct > 80 && $exec['meetings'] == 0 && $exec['site_visits'] == 0) {
                        $suggestions[] = "Interaction mix is {$callPct}% calls with no meetings or visits. <strong>Diversify approach</strong> - in-person interactions have 3-5x higher conversion impact.";
                    }
                    if (empty($suggestions)) {
                        $suggestions[] = "Performance is solid across metrics. Keep up the good work and <strong>mentor other team members</strong> on your approach.";
                    }
                ?>

                <div class="exec-card">
                    <div class="exec-header" onclick="toggleExec(<?= $idx ?>)">
                        <h3><?= htmlspecialchars($exec['handled_by']) ?></h3>
                        <div class="exec-stats">
                            <span><strong><?= $exec['total_interactions'] ?></strong> interactions</span>
                            <span><strong><?= $exec['leads_touched'] ?></strong> leads</span>
                            <span style="color:<?= $convColor ?>"><strong><?= $convRate ?>%</strong> conv</span>
                            <span><?= $exec['converted_leads'] ?>W / <?= $exec['lost_leads'] ?>L</span>
                        </div>
                    </div>
                    <div class="exec-body" id="exec-<?= $idx ?>">
                        <!-- Activity Mix -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h4 style="margin: 0 0 10px; color: #2c3e50;">Activity Breakdown</h4>
                                <div class="metric-bar">
                                    <span class="metric-label">Calls</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= min($callPct, 100) ?>%; background:#27ae60;"></div></div>
                                    <span class="bar-value"><?= $exec['calls'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Emails</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['total_interactions'] > 0 ? min(round($exec['emails']*100/$exec['total_interactions']), 100) : 0 ?>%; background:#2980b9;"></div></div>
                                    <span class="bar-value"><?= $exec['emails'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">WhatsApp</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['total_interactions'] > 0 ? min(round($exec['whatsapp']*100/$exec['total_interactions']), 100) : 0 ?>%; background:#25D366;"></div></div>
                                    <span class="bar-value"><?= $exec['whatsapp'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Meetings</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= min($meetingPct, 100) ?>%; background:#e67e22;"></div></div>
                                    <span class="bar-value"><?= $exec['meetings'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Site Visits</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['total_interactions'] > 0 ? min(round($exec['site_visits']*100/$exec['total_interactions']), 100) : 0 ?>%; background:#8e44ad;"></div></div>
                                    <span class="bar-value"><?= $exec['site_visits'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Demos</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= min($demoPct, 100) ?>%; background:#e74c3c;"></div></div>
                                    <span class="bar-value"><?= $exec['demos'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Quotations</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['total_interactions'] > 0 ? min(round($exec['quotations']*100/$exec['total_interactions']), 100) : 0 ?>%; background:#1e8449;"></div></div>
                                    <span class="bar-value"><?= $exec['quotations'] ?></span>
                                </div>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 10px; color: #2c3e50;">Lead Pipeline</h4>
                                <div class="metric-bar">
                                    <span class="metric-label">Hot</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['leads_touched'] > 0 ? min(round($exec['hot_leads']*100/$exec['leads_touched']), 100) : 0 ?>%; background:#e74c3c;"></div></div>
                                    <span class="bar-value"><?= $exec['hot_leads'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Warm</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['leads_touched'] > 0 ? min(round($exec['warm_leads']*100/$exec['leads_touched']), 100) : 0 ?>%; background:#f39c12;"></div></div>
                                    <span class="bar-value"><?= $exec['warm_leads'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Cold</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['leads_touched'] > 0 ? min(round($exec['cold_leads']*100/$exec['leads_touched']), 100) : 0 ?>%; background:#bdc3c7;"></div></div>
                                    <span class="bar-value"><?= $exec['cold_leads'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Converted</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['leads_touched'] > 0 ? min(round($exec['converted_leads']*100/$exec['leads_touched']), 100) : 0 ?>%; background:#27ae60;"></div></div>
                                    <span class="bar-value"><?= $exec['converted_leads'] ?></span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Lost</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $exec['leads_touched'] > 0 ? min(round($exec['lost_leads']*100/$exec['leads_touched']), 100) : 0 ?>%; background:#7f8c8d;"></div></div>
                                    <span class="bar-value"><?= $exec['lost_leads'] ?></span>
                                </div>

                                <h4 style="margin: 15px 0 10px; color: #2c3e50;">Documentation Quality</h4>
                                <div class="metric-bar">
                                    <span class="metric-label">Descriptions</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $docRate ?>%; background:<?= $docRate >= 70 ? '#27ae60' : ($docRate >= 40 ? '#f39c12' : '#e74c3c') ?>;"></div></div>
                                    <span class="bar-value"><?= $docRate ?>%</span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Outcomes</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $outcomeRate ?>%; background:<?= $outcomeRate >= 70 ? '#27ae60' : ($outcomeRate >= 40 ? '#f39c12' : '#e74c3c') ?>;"></div></div>
                                    <span class="bar-value"><?= $outcomeRate ?>%</span>
                                </div>
                                <div class="metric-bar">
                                    <span class="metric-label">Next Actions</span>
                                    <div class="bar-track"><div class="bar-fill" style="width:<?= $nextActionRate ?>%; background:<?= $nextActionRate >= 70 ? '#27ae60' : ($nextActionRate >= 40 ? '#f39c12' : '#e74c3c') ?>;"></div></div>
                                    <span class="bar-value"><?= $nextActionRate ?>%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Personalized Improvement Suggestions -->
                        <div class="suggestion-box">
                            <strong>Improvement Suggestions for <?= htmlspecialchars($exec['handled_by']) ?>:</strong>
                            <ul>
                                <?php foreach ($suggestions as $s): ?>
                                    <li><?= $s ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
                <?php else: ?>
                <p style="text-align:center; color:#999; padding:30px;">No interaction data found for the selected period.</p>
                <?php endif; ?>

            </div>
        </div>

        <!-- ====== SECTION 4: OVERALL IMPROVEMENT STRATEGY ====== -->
        <div class="section">
            <div class="section-header" style="background: #1a5276;" onclick="toggleSec('overallStrategy')">
                <h2>Overall Improvement Strategy</h2>
                <span class="arrow" id="overallStrategyArrow">&#9660;</span>
            </div>
            <div class="section-body" id="overallStrategy" style="display:block;">

                <?php
                // Compute overall improvement suggestions
                $totalLeads = $overall['total_leads_contacted'] ?? 0;
                $totalInt = $overall['total_interactions'] ?? 0;
                $avgPL = $overall['avg_interactions_per_lead'] ?? 0;

                $convertedCount = 0; $lostCount = 0; $totalJourneyLeads = 0;
                foreach ($journey as $j) {
                    $totalJourneyLeads += $j['lead_count'];
                    if ($j['lead_status'] === 'converted') $convertedCount = $j['lead_count'];
                    if ($j['lead_status'] === 'lost') $lostCount = $j['lead_count'];
                }
                $overallConvRate = $totalJourneyLeads > 0 ? round($convertedCount * 100 / $totalJourneyLeads, 1) : 0;

                // Find best performing source
                $bestSource = null; $bestSourceConv = 0;
                foreach ($sources as $s) {
                    if ($s['conv_rate'] > $bestSourceConv && $s['leads'] >= 3) {
                        $bestSourceConv = $s['conv_rate'];
                        $bestSource = $s;
                    }
                }

                // Find highest conversion activity
                $bestActivity = null; $bestActCorr = 0;
                foreach ($funnel as $f) {
                    if ($f['conversion_correlation'] > $bestActCorr) {
                        $bestActCorr = $f['conversion_correlation'];
                        $bestActivity = $f;
                    }
                }
                ?>

                <div class="insight-grid">
                    <div class="insight-card" style="border-left: 4px solid #e74c3c;">
                        <h4>1. Increase Follow-up Discipline</h4>
                        <div class="insight-text">
                            Current average: <strong><?= $avgPL ?> interactions per lead</strong>.
                            <?php if ($convertedData): ?>
                                Converted leads needed <strong><?= $convertedData['avg_interactions'] ?></strong> interactions on average.
                            <?php endif; ?>
                            <br><br>
                            <strong>Action:</strong> Set a minimum of <?= max(5, ($convertedData['avg_interactions'] ?? 5)) ?> touchpoints per lead before marking as lost.
                            Use the CRM's next-action-date feature to never miss a follow-up.
                        </div>
                    </div>

                    <div class="insight-card" style="border-left: 4px solid #27ae60;">
                        <h4>2. Improve Activity Mix</h4>
                        <div class="insight-text">
                            <?php if ($bestActivity): ?>
                                <strong><?= ucfirst(str_replace('_', ' ', $bestActivity['interaction_type'])) ?></strong> has the highest conversion correlation at <?= $bestActivity['conversion_correlation'] ?>%.
                            <?php endif; ?>
                            <br><br>
                            <strong>Action:</strong> Push all executives to include at least 1 meeting/demo for every hot or warm lead.
                            Face-to-face interactions build trust and close deals faster than calls alone.
                        </div>
                    </div>

                    <div class="insight-card" style="border-left: 4px solid #3498db;">
                        <h4>3. Focus on High-Value Sources</h4>
                        <div class="insight-text">
                            <?php if ($bestSource): ?>
                                Best performing source: <strong><?= htmlspecialchars($bestSource['lead_source']) ?></strong> with <?= $bestSource['conv_rate'] ?>% conversion.
                            <?php endif; ?>
                            Overall conversion rate: <strong><?= $overallConvRate ?>%</strong> (<?= $convertedCount ?> of <?= $totalJourneyLeads ?> leads).
                            <br><br>
                            <strong>Action:</strong> Allocate more marketing budget to high-converting sources.
                            Referrals and existing customers typically convert 2-3x better than cold leads.
                        </div>
                    </div>

                    <div class="insight-card" style="border-left: 4px solid #8e44ad;">
                        <h4>4. Improve Documentation</h4>
                        <div class="insight-text">
                            Poor documentation means lost context when leads are reassigned or revisited.
                            <br><br>
                            <strong>Action:</strong> Mandate logging <strong>description + outcome + next action</strong> for every interaction.
                            This creates a buyer psychology profile that helps predict behavior and tailor the approach.
                        </div>
                    </div>

                    <div class="insight-card" style="border-left: 4px solid #f39c12;">
                        <h4>5. Speed Up Response Time</h4>
                        <div class="insight-text">
                            <?php if (!empty($responseTimes)):
                                foreach ($responseTimes as $rt):
                                    if ($rt['lead_status'] === 'converted'):
                            ?>
                                Converted leads: first contact in <strong><?= $rt['avg_days_to_first_contact'] ?> days</strong>.
                            <?php   endif;
                                    if ($rt['lead_status'] === 'lost'):
                            ?>
                                Lost leads: first contact in <strong><?= $rt['avg_days_to_first_contact'] ?> days</strong>.
                            <?php   endif;
                                endforeach;
                            endif; ?>
                            <br><br>
                            <strong>Action:</strong> Target first contact within 24 hours of lead creation. Every day of delay reduces conversion probability by 10-15%.
                        </div>
                    </div>

                    <div class="insight-card" style="border-left: 4px solid #2c3e50;">
                        <h4>6. Reduce Lost Lead Ratio</h4>
                        <div class="insight-text">
                            Currently losing <strong><?= $lostCount ?></strong> leads vs converting <strong><?= $convertedCount ?></strong>.
                            <?php if ($lostCount > $convertedCount * 2): ?>
                                <span class="bad-point">Lost leads are more than double the converted ones - this needs immediate attention.</span>
                            <?php endif; ?>
                            <br><br>
                            <strong>Action:</strong> Conduct weekly lost-lead reviews. Identify top 3 loss reasons and create targeted rebuttals/offers to counter them.
                            Consider a win-back campaign for recently lost leads.
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
function toggleSec(id) {
    const el = document.getElementById(id);
    const arrow = document.getElementById(id + 'Arrow');
    if (el.style.display === 'none') {
        el.style.display = 'block';
        if (arrow) arrow.innerHTML = '&#9660;';
    } else {
        el.style.display = 'none';
        if (arrow) arrow.innerHTML = '&#9654;';
    }
}

function toggleExec(idx) {
    const body = document.getElementById('exec-' + idx);
    body.classList.toggle('open');
}
</script>

</body>
</html>
