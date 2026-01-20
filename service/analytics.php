<?php
include "../db.php";
include "../includes/dialog.php";

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? '';

// Build date filter
$dateFilter = "YEAR(registered_date) = ?";
$params = [$year];
if ($month) {
    $dateFilter .= " AND MONTH(registered_date) = ?";
    $params[] = $month;
}

// Overall Statistics
$overallStats = $pdo->prepare("
    SELECT
        COUNT(*) AS total_complaints,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) AS assigned_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) AS on_hold_count,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN priority = 'Critical' THEN 1 ELSE 0 END) AS critical_count,
        SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) AS high_count,
        AVG(CASE WHEN resolution_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, registered_date, resolution_date) END) AS avg_resolution_hours
    FROM service_complaints
    WHERE $dateFilter
");
$overallStats->execute($params);
$stats = $overallStats->fetch(PDO::FETCH_ASSOC);

// Calculate resolution rate
$resolutionRate = $stats['total_complaints'] > 0
    ? round(($stats['resolved_count'] / $stats['total_complaints']) * 100, 1)
    : 0;

// Issue Category Breakdown
$categoryStats = $pdo->prepare("
    SELECT
        COALESCE(cat.name, 'Uncategorized') AS category_name,
        COUNT(*) AS complaint_count,
        SUM(CASE WHEN c.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM service_complaints WHERE $dateFilter), 1) AS percentage
    FROM service_complaints c
    LEFT JOIN service_issue_categories cat ON c.issue_category_id = cat.id
    WHERE $dateFilter
    GROUP BY cat.id, cat.name
    ORDER BY complaint_count DESC
");
$categoryStats->execute(array_merge($params, $params));
$categories = $categoryStats->fetchAll(PDO::FETCH_ASSOC);

// Priority Breakdown
$priorityStats = $pdo->prepare("
    SELECT
        priority,
        COUNT(*) AS complaint_count,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM service_complaints WHERE $dateFilter), 1) AS percentage
    FROM service_complaints
    WHERE $dateFilter
    GROUP BY priority
    ORDER BY FIELD(priority, 'Critical', 'High', 'Medium', 'Low')
");
$priorityStats->execute(array_merge($params, $params));
$priorities = $priorityStats->fetchAll(PDO::FETCH_ASSOC);

// Monthly Trend (for current year)
$monthlyTrend = $pdo->prepare("
    SELECT
        MONTH(registered_date) AS month_num,
        MONTHNAME(registered_date) AS month_name,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved
    FROM service_complaints
    WHERE YEAR(registered_date) = ?
    GROUP BY MONTH(registered_date), MONTHNAME(registered_date)
    ORDER BY month_num
");
$monthlyTrend->execute([$year]);
$months = $monthlyTrend->fetchAll(PDO::FETCH_ASSOC);

// State-wise Complaints
$stateStats = $pdo->prepare("
    SELECT
        COALESCE(s.state_name, 'Not Specified') AS state_name,
        s.region,
        COUNT(*) AS complaint_count
    FROM service_complaints c
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE $dateFilter
    GROUP BY s.id, s.state_name, s.region
    ORDER BY complaint_count DESC
    LIMIT 15
");
$stateStats->execute($params);
$stateData = $stateStats->fetchAll(PDO::FETCH_ASSOC);

// Technician Performance
$techStats = $pdo->prepare("
    SELECT
        t.name AS technician_name,
        COUNT(c.id) AS assigned_count,
        SUM(CASE WHEN c.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
        AVG(CASE WHEN c.resolution_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, c.assigned_date, c.resolution_date) END) AS avg_hours
    FROM service_technicians t
    LEFT JOIN service_complaints c ON t.id = c.assigned_technician_id AND $dateFilter
    WHERE t.status = 'Active'
    GROUP BY t.id, t.name
    ORDER BY resolved_count DESC
");
$techStats->execute($params);
$techData = $techStats->fetchAll(PDO::FETCH_ASSOC);

// Warranty Status Breakdown
$warrantyStats = $pdo->prepare("
    SELECT
        warranty_status,
        COUNT(*) AS complaint_count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM service_complaints WHERE $dateFilter), 1) AS percentage
    FROM service_complaints
    WHERE $dateFilter
    GROUP BY warranty_status
    ORDER BY complaint_count DESC
");
$warrantyStats->execute(array_merge($params, $params));
$warrantyData = $warrantyStats->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Analytics - <?= $year ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .analytics-container { max-width: 1200px; }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-row h1 { margin: 0; }

        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .filter-bar select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .summary-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .summary-card.total { border-top: 4px solid #3498db; }
        .summary-card.open { border-top: 4px solid #e74c3c; }
        .summary-card.resolved { border-top: 4px solid #27ae60; }
        .summary-card.rate { border-top: 4px solid #9b59b6; }
        .summary-card.critical { border-top: 4px solid #c0392b; }
        .summary-card.avg-time { border-top: 4px solid #f39c12; }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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
            font-size: 1em;
        }
        .data-section .content {
            padding: 15px;
            max-height: 350px;
            overflow-y: auto;
        }

        .category-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .category-item:last-child { border-bottom: none; }
        .category-item .info { flex: 1; }
        .category-item .name { font-weight: 500; }
        .category-item .stats { color: #7f8c8d; font-size: 0.85em; }
        .category-item .percentage {
            font-weight: bold;
            font-size: 1.1em;
            color: #3498db;
            min-width: 50px;
            text-align: right;
        }

        .progress-bar {
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .fill-critical { background: #e74c3c; }
        .fill-high { background: #e67e22; }
        .fill-medium { background: #f1c40f; }
        .fill-low { background: #95a5a6; }
        .fill-blue { background: #3498db; }
        .fill-green { background: #27ae60; }

        .priority-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85em;
            min-width: 70px;
            text-align: center;
        }
        .priority-Critical { background: #e74c3c; color: white; }
        .priority-High { background: #e67e22; color: white; }
        .priority-Medium { background: #f1c40f; color: #333; }
        .priority-Low { background: #95a5a6; color: white; }

        .month-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 200px;
            padding: 20px 10px;
        }
        .month-bar {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            max-width: 60px;
        }
        .month-bar .bar {
            width: 30px;
            background: #3498db;
            border-radius: 4px 4px 0 0;
            position: relative;
        }
        .month-bar .bar .resolved {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #27ae60;
            border-radius: 0 0 4px 4px;
        }
        .month-bar .label {
            margin-top: 8px;
            font-size: 0.75em;
            color: #7f8c8d;
        }
        .month-bar .count {
            font-size: 0.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .region-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            margin-left: 10px;
        }
        .region-North { background: #e3f2fd; color: #1565c0; }
        .region-South { background: #e8f5e9; color: #2e7d32; }
        .region-East { background: #fff3e0; color: #e65100; }
        .region-West { background: #fce4ec; color: #c2185b; }
        .region-Central { background: #f3e5f5; color: #7b1fa2; }
        .region-Northeast { background: #e0f7fa; color: #00838f; }

        .tech-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .tech-item .name { font-weight: 500; }
        .tech-item .metrics {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
        }
        .tech-item .metric { text-align: center; }
        .tech-item .metric .num { font-weight: bold; }
        .tech-item .metric .label { color: #7f8c8d; font-size: 0.8em; }

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

        <div class="header-row">
            <h1>Service Analytics</h1>
            <div class="filter-bar">
                <a href="complaints.php" class="btn btn-secondary">All Complaints</a>
                <form method="get" style="display: flex; gap: 10px;">
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="month" onchange="this.form.submit()">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card total">
                <div class="value"><?= number_format($stats['total_complaints']) ?></div>
                <div class="label">Total Complaints</div>
            </div>
            <div class="summary-card open">
                <div class="value"><?= number_format($stats['open_count'] + $stats['assigned_count'] + $stats['in_progress_count']) ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="summary-card resolved">
                <div class="value"><?= number_format($stats['resolved_count']) ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="summary-card rate">
                <div class="value"><?= $resolutionRate ?>%</div>
                <div class="label">Resolution Rate</div>
            </div>
            <div class="summary-card critical">
                <div class="value"><?= number_format($stats['critical_count'] + $stats['high_count']) ?></div>
                <div class="label">High Priority</div>
            </div>
            <div class="summary-card avg-time">
                <div class="value"><?= $stats['avg_resolution_hours'] ? round($stats['avg_resolution_hours']) : '-' ?></div>
                <div class="label">Avg Hours to Resolve</div>
            </div>
        </div>

        <div class="section-grid">
            <!-- Issue Category Breakdown -->
            <div class="data-section">
                <h3>Issue Categories (Problem Types)</h3>
                <div class="content">
                    <?php if (empty($categories)): ?>
                        <div class="no-data">No data available</div>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <div class="category-item">
                            <div class="info">
                                <div class="name"><?= htmlspecialchars($cat['category_name']) ?></div>
                                <div class="stats"><?= $cat['complaint_count'] ?> complaints, <?= $cat['resolved_count'] ?> resolved</div>
                                <div class="progress-bar">
                                    <div class="fill fill-blue" style="width: <?= $cat['percentage'] ?>%;"></div>
                                </div>
                            </div>
                            <div class="percentage"><?= $cat['percentage'] ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Priority Breakdown -->
            <div class="data-section">
                <h3>Priority Distribution</h3>
                <div class="content">
                    <?php if (empty($priorities)): ?>
                        <div class="no-data">No data available</div>
                    <?php else: ?>
                        <?php foreach ($priorities as $p): ?>
                        <div class="priority-item">
                            <span class="priority-badge priority-<?= $p['priority'] ?>"><?= $p['priority'] ?></span>
                            <div style="flex: 1; margin: 0 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                                    <span><?= $p['complaint_count'] ?> complaints</span>
                                    <span><?= $p['percentage'] ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="fill fill-<?= strtolower($p['priority']) ?>" style="width: <?= $p['percentage'] ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- State-wise Distribution -->
            <div class="data-section">
                <h3>Top Locations (State-wise)</h3>
                <div class="content">
                    <?php if (empty($stateData)): ?>
                        <div class="no-data">No data available</div>
                    <?php else: ?>
                        <?php foreach ($stateData as $s): ?>
                        <div class="category-item">
                            <div class="info">
                                <div class="name">
                                    <?= htmlspecialchars($s['state_name']) ?>
                                    <?php if ($s['region']): ?>
                                        <span class="region-badge region-<?= $s['region'] ?>"><?= $s['region'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="percentage"><?= $s['complaint_count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Warranty Status -->
            <div class="data-section">
                <h3>Warranty Status</h3>
                <div class="content">
                    <?php if (empty($warrantyData)): ?>
                        <div class="no-data">No data available</div>
                    <?php else: ?>
                        <?php foreach ($warrantyData as $w): ?>
                        <div class="category-item">
                            <div class="info">
                                <div class="name"><?= htmlspecialchars($w['warranty_status']) ?></div>
                                <div class="progress-bar">
                                    <div class="fill fill-green" style="width: <?= $w['percentage'] ?>%;"></div>
                                </div>
                            </div>
                            <div class="percentage"><?= $w['percentage'] ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <?php if (!$month && !empty($months)): ?>
        <div class="data-section" style="margin-bottom: 30px;">
            <h3>Monthly Trend - <?= $year ?></h3>
            <div class="content">
                <?php
                $maxCount = max(array_column($months, 'total'));
                $maxHeight = 150;
                ?>
                <div class="month-chart">
                    <?php foreach ($months as $m): ?>
                    <?php
                    $barHeight = $maxCount > 0 ? ($m['total'] / $maxCount) * $maxHeight : 0;
                    $resolvedHeight = $m['total'] > 0 ? ($m['resolved'] / $m['total']) * $barHeight : 0;
                    ?>
                    <div class="month-bar">
                        <div class="count"><?= $m['total'] ?></div>
                        <div class="bar" style="height: <?= $barHeight ?>px;">
                            <div class="resolved" style="height: <?= $resolvedHeight ?>px;"></div>
                        </div>
                        <div class="label"><?= substr($m['month_name'], 0, 3) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 10px; font-size: 0.85em; color: #7f8c8d;">
                    <span style="display: inline-block; width: 12px; height: 12px; background: #3498db; border-radius: 2px; margin-right: 5px;"></span> Total
                    <span style="display: inline-block; width: 12px; height: 12px; background: #27ae60; border-radius: 2px; margin-left: 20px; margin-right: 5px;"></span> Resolved
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Technician Performance -->
        <div class="data-section">
            <h3>Technician Performance</h3>
            <div class="content">
                <?php if (empty($techData)): ?>
                    <div class="no-data">No technicians assigned yet</div>
                <?php else: ?>
                    <?php foreach ($techData as $tech): ?>
                    <div class="tech-item">
                        <div class="name"><?= htmlspecialchars($tech['technician_name']) ?></div>
                        <div class="metrics">
                            <div class="metric">
                                <div class="num"><?= $tech['assigned_count'] ?></div>
                                <div class="label">Assigned</div>
                            </div>
                            <div class="metric">
                                <div class="num" style="color: #27ae60;"><?= $tech['resolved_count'] ?></div>
                                <div class="label">Resolved</div>
                            </div>
                            <div class="metric">
                                <div class="num"><?= $tech['avg_hours'] ? round($tech['avg_hours']) . 'h' : '-' ?></div>
                                <div class="label">Avg Time</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
