<?php
include "../db.php";
include "../includes/dialog.php";

$year = $_GET['year'] ?? date('Y');
$region = $_GET['region'] ?? '';

// Get all regions for filter
$regions = ['North', 'South', 'East', 'West', 'Central', 'Northeast'];

// Build state filter for query
$stateCondition = "";
$params = [$year];
if ($region) {
    $stateCondition = "AND s.region = ?";
    $params[] = $region;
}

// Get state-wise campaign statistics
$stateStats = $pdo->prepare("
    SELECT
        s.id AS state_id,
        s.state_code,
        s.state_name,
        s.region,
        COUNT(c.id) AS campaign_count,
        COALESCE(SUM(c.budget), 0) AS total_budget,
        COALESCE(SUM(c.actual_cost), 0) AS total_spent,
        COALESCE(SUM(c.actual_attendees), 0) AS total_attendees,
        COALESCE(SUM(c.leads_generated), 0) AS total_leads,
        COALESCE(SUM(c.orders_received), 0) AS total_orders,
        COALESCE(SUM(c.revenue_generated), 0) AS total_revenue
    FROM india_states s
    LEFT JOIN marketing_campaigns c ON s.id = c.state_id AND YEAR(c.start_date) = ?
    WHERE 1=1 $stateCondition
    GROUP BY s.id, s.state_code, s.state_name, s.region
    ORDER BY s.region, s.state_name
");
$stateStats->execute($params);
$statesData = $stateStats->fetchAll(PDO::FETCH_ASSOC);

// Get region-wise summary
$regionStats = $pdo->prepare("
    SELECT
        s.region,
        COUNT(c.id) AS campaign_count,
        COALESCE(SUM(c.budget), 0) AS total_budget,
        COALESCE(SUM(c.actual_cost), 0) AS total_spent,
        COALESCE(SUM(c.actual_attendees), 0) AS total_attendees,
        COALESCE(SUM(c.leads_generated), 0) AS total_leads,
        COALESCE(SUM(c.orders_received), 0) AS total_orders,
        COALESCE(SUM(c.revenue_generated), 0) AS total_revenue
    FROM india_states s
    LEFT JOIN marketing_campaigns c ON s.id = c.state_id AND YEAR(c.start_date) = ?
    GROUP BY s.region
    ORDER BY s.region
");
$regionStats->execute([$year]);
$regionsData = $regionStats->fetchAll(PDO::FETCH_ASSOC);

// Get overall totals for the year
$overallStats = $pdo->prepare("
    SELECT
        COUNT(*) AS total_campaigns,
        COALESCE(SUM(budget), 0) AS total_budget,
        COALESCE(SUM(actual_cost), 0) AS total_spent,
        COALESCE(SUM(actual_attendees), 0) AS total_attendees,
        COALESCE(SUM(leads_generated), 0) AS total_leads,
        COALESCE(SUM(orders_received), 0) AS total_orders,
        COALESCE(SUM(revenue_generated), 0) AS total_revenue
    FROM marketing_campaigns
    WHERE YEAR(start_date) = ?
");
$overallStats->execute([$year]);
$overall = $overallStats->fetch(PDO::FETCH_ASSOC);

// Get campaign type breakdown
$typeStats = $pdo->prepare("
    SELECT
        ct.name AS type_name,
        COUNT(c.id) AS campaign_count,
        COALESCE(SUM(c.budget), 0) AS total_budget,
        COALESCE(SUM(c.leads_generated), 0) AS total_leads
    FROM campaign_types ct
    LEFT JOIN marketing_campaigns c ON ct.id = c.campaign_type_id AND YEAR(c.start_date) = ?
    GROUP BY ct.id, ct.name
    ORDER BY campaign_count DESC
");
$typeStats->execute([$year]);
$typesData = $typeStats->fetchAll(PDO::FETCH_ASSOC);

// Get top performing states (by leads generated)
$topStates = $pdo->prepare("
    SELECT
        s.state_name,
        COUNT(c.id) AS campaigns,
        SUM(c.leads_generated) AS leads,
        SUM(c.revenue_generated) AS revenue
    FROM marketing_campaigns c
    JOIN india_states s ON c.state_id = s.id
    WHERE YEAR(c.start_date) = ?
    GROUP BY s.id, s.state_name
    HAVING leads > 0
    ORDER BY leads DESC
    LIMIT 10
");
$topStates->execute([$year]);
$topPerformers = $topStates->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Marketing Analytics - <?= $year ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .analytics-container { max-width: 1200px; }

        .year-nav {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        .year-nav h2 { margin: 0; }
        .year-nav a { font-size: 1.5em; color: #3498db; text-decoration: none; }

        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .filter-bar select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 150px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        .summary-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .summary-card.campaigns { border-top: 4px solid #3498db; }
        .summary-card.budget { border-top: 4px solid #9b59b6; }
        .summary-card.attendees { border-top: 4px solid #2ecc71; }
        .summary-card.leads { border-top: 4px solid #e74c3c; }
        .summary-card.orders { border-top: 4px solid #f39c12; }
        .summary-card.revenue { border-top: 4px solid #1abc9c; }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .data-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .data-section h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .data-section .content {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th { font-weight: bold; color: #7f8c8d; font-size: 0.85em; }
        .data-table tr:hover { background: #fafafa; }

        .region-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .region-North { background: #e3f2fd; color: #1565c0; }
        .region-South { background: #e8f5e9; color: #2e7d32; }
        .region-East { background: #fff3e0; color: #e65100; }
        .region-West { background: #fce4ec; color: #c2185b; }
        .region-Central { background: #f3e5f5; color: #7b1fa2; }
        .region-Northeast { background: #e0f7fa; color: #00838f; }

        .progress-bar {
            background: #ecf0f1;
            border-radius: 10px;
            height: 8px;
            margin-top: 5px;
        }
        .progress-bar .fill {
            height: 100%;
            border-radius: 10px;
            background: #3498db;
        }

        .state-row { cursor: pointer; }
        .state-row:hover { background: #f5f5f5; }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="analytics-container">

        <div class="year-nav">
            <a href="?year=<?= $year - 1 ?>">&larr;</a>
            <h2>Marketing Analytics <?= $year ?></h2>
            <a href="?year=<?= $year + 1 ?>">&rarr;</a>
        </div>

        <div class="filter-bar">
            <a href="campaigns.php" class="btn btn-secondary">All Campaigns</a>
            <a href="catalogs.php" class="btn btn-secondary">Catalogs</a>

            <form method="get" style="margin-left: auto; display: flex; gap: 10px;">
                <input type="hidden" name="year" value="<?= $year ?>">
                <select name="region" onchange="this.form.submit()">
                    <option value="">All Regions</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= $r ?>" <?= $region === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Overall Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card campaigns">
                <div class="value"><?= number_format($overall['total_campaigns']) ?></div>
                <div class="label">Total Campaigns</div>
            </div>
            <div class="summary-card budget">
                <div class="value"><?= number_format($overall['total_budget'] / 100000, 1) ?>L</div>
                <div class="label">Total Budget</div>
            </div>
            <div class="summary-card attendees">
                <div class="value"><?= number_format($overall['total_attendees']) ?></div>
                <div class="label">Total Attendees</div>
            </div>
            <div class="summary-card leads">
                <div class="value"><?= number_format($overall['total_leads']) ?></div>
                <div class="label">Leads Generated</div>
            </div>
            <div class="summary-card orders">
                <div class="value"><?= number_format($overall['total_orders']) ?></div>
                <div class="label">Orders Received</div>
            </div>
            <div class="summary-card revenue">
                <div class="value"><?= number_format($overall['total_revenue'] / 100000, 1) ?>L</div>
                <div class="label">Revenue Generated</div>
            </div>
        </div>

        <div class="section-grid">
            <!-- Region-wise Summary -->
            <div class="data-section">
                <h3>Region-wise Performance</h3>
                <div class="content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Campaigns</th>
                                <th>Budget</th>
                                <th>Leads</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regionsData as $r): ?>
                            <tr>
                                <td><span class="region-badge region-<?= $r['region'] ?>"><?= $r['region'] ?></span></td>
                                <td><?= $r['campaign_count'] ?></td>
                                <td><?= number_format($r['total_budget'] / 1000, 0) ?>K</td>
                                <td><?= $r['total_leads'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campaign Type Breakdown -->
            <div class="data-section">
                <h3>Campaign Type Breakdown</h3>
                <div class="content">
                    <?php if (empty(array_filter($typesData, fn($t) => $t['campaign_count'] > 0))): ?>
                        <div class="no-data">No campaigns recorded for <?= $year ?></div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Budget</th>
                                    <th>Leads</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($typesData as $t): ?>
                                    <?php if ($t['campaign_count'] > 0): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t['type_name']) ?></td>
                                        <td><?= $t['campaign_count'] ?></td>
                                        <td><?= number_format($t['total_budget'] / 1000, 0) ?>K</td>
                                        <td><?= $t['total_leads'] ?></td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Performing States -->
            <div class="data-section">
                <h3>Top Performing States (by Leads)</h3>
                <div class="content">
                    <?php if (empty($topPerformers)): ?>
                        <div class="no-data">No performance data for <?= $year ?></div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>State</th>
                                    <th>Campaigns</th>
                                    <th>Leads</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPerformers as $tp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tp['state_name']) ?></td>
                                    <td><?= $tp['campaigns'] ?></td>
                                    <td><strong><?= $tp['leads'] ?></strong></td>
                                    <td><?= number_format($tp['revenue'] / 1000, 0) ?>K</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- State-wise Detailed Table -->
        <div class="data-section">
            <h3>State-wise Marketing Activity <?= $region ? "($region Region)" : '' ?></h3>
            <div class="content" style="max-height: 600px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Region</th>
                            <th>Campaigns</th>
                            <th>Budget</th>
                            <th>Spent</th>
                            <th>Attendees</th>
                            <th>Leads</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasData = false;
                        foreach ($statesData as $s):
                            if ($s['campaign_count'] > 0) $hasData = true;
                        ?>
                        <tr class="state-row" onclick="window.location='campaigns.php?state=<?= $s['state_id'] ?>&year=<?= $year ?>'">
                            <td><strong><?= htmlspecialchars($s['state_name']) ?></strong></td>
                            <td><span class="region-badge region-<?= $s['region'] ?>"><?= $s['region'] ?></span></td>
                            <td><?= $s['campaign_count'] ?></td>
                            <td><?= $s['total_budget'] > 0 ? number_format($s['total_budget'] / 1000, 0) . 'K' : '-' ?></td>
                            <td><?= $s['total_spent'] > 0 ? number_format($s['total_spent'] / 1000, 0) . 'K' : '-' ?></td>
                            <td><?= $s['total_attendees'] ?: '-' ?></td>
                            <td><?= $s['total_leads'] ?: '-' ?></td>
                            <td><?= $s['total_orders'] ?: '-' ?></td>
                            <td><?= $s['total_revenue'] > 0 ? number_format($s['total_revenue'] / 1000, 0) . 'K' : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!$hasData): ?>
                <div class="no-data" style="margin-top: 20px;">
                    No marketing campaigns recorded for <?= $year ?><?= $region ? " in $region region" : '' ?>.
                    <br><br>
                    <a href="campaign_add.php" class="btn btn-primary">Create First Campaign</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
