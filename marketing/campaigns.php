<?php
include "../db.php";
include "../includes/dialog.php";

// Filters
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$state = $_GET['state'] ?? '';
$month = $_GET['month'] ?? '';

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($type) {
    $where[] = "c.campaign_type_id = ?";
    $params[] = $type;
}
if ($state) {
    $where[] = "c.state_id = ?";
    $params[] = $state;
}
if ($month) {
    $where[] = "DATE_FORMAT(c.start_date, '%Y-%m') = ?";
    $params[] = $month;
}

$whereClause = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT c.*, ct.name as type_name, s.state_name
    FROM marketing_campaigns c
    LEFT JOIN campaign_types ct ON c.campaign_type_id = ct.id
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE $whereClause
    ORDER BY c.start_date DESC
");
$stmt->execute($params);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$types = $pdo->query("SELECT id, name FROM campaign_types WHERE is_active = 1 ORDER BY name")->fetchAll();
$states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM marketing_campaigns")->fetchColumn(),
    'ongoing' => $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'Ongoing'")->fetchColumn(),
    'planned' => $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'Planned'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'Completed'")->fetchColumn(),
];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Marketing Campaigns</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 120px;
        }
        .stat-box .number { font-size: 2em; font-weight: bold; }
        .stat-box .label { color: #7f8c8d; }
        .stat-box.ongoing .number { color: #3498db; }
        .stat-box.planned .number { color: #f39c12; }
        .stat-box.completed .number { color: #27ae60; }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .campaign-table { width: 100%; border-collapse: collapse; }
        .campaign-table th, .campaign-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .campaign-table th { background: #f5f5f5; font-weight: bold; }
        .campaign-table tr:hover { background: #fafafa; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-Planned { background: #fff3cd; color: #856404; }
        .status-Ongoing { background: #cce5ff; color: #004085; }
        .status-Completed { background: #d4edda; color: #155724; }
        .status-Cancelled { background: #f8d7da; color: #721c24; }
        .status-Postponed { background: #e2e3e5; color: #383d41; }

        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 4px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Marketing Campaigns</h1>

    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-box ongoing">
            <div class="number"><?= $stats['ongoing'] ?></div>
            <div class="label">Ongoing</div>
        </div>
        <div class="stat-box planned">
            <div class="number"><?= $stats['planned'] ?></div>
            <div class="label">Planned</div>
        </div>
        <div class="stat-box completed">
            <div class="number"><?= $stats['completed'] ?></div>
            <div class="label">Completed</div>
        </div>
    </div>

    <div class="filters">
        <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <select name="status">
                <option value="">All Status</option>
                <option value="Planned" <?= $status === 'Planned' ? 'selected' : '' ?>>Planned</option>
                <option value="Ongoing" <?= $status === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="Postponed" <?= $status === 'Postponed' ? 'selected' : '' ?>>Postponed</option>
            </select>

            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $type == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="state">
                <option value="">All States</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $state == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['state_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="campaigns.php" class="btn btn-secondary">Reset</a>
        </form>

        <div style="margin-left: auto;">
            <a href="campaign_add.php" class="btn btn-success">+ New Campaign</a>
        </div>
    </div>

    <table class="campaign-table">
        <thead>
            <tr>
                <th>Campaign</th>
                <th>Type</th>
                <th>Location</th>
                <th>Date</th>
                <th>Budget</th>
                <th>Leads</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)): ?>
                <tr><td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">No campaigns found</td></tr>
            <?php else: ?>
                <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($c['campaign_name']) ?></strong><br>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($c['campaign_code']) ?></small>
                    </td>
                    <td>
                        <?php if ($c['type_name']): ?>
                            <span class="type-badge"><?= htmlspecialchars($c['type_name']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['city'] ?? '') ?>
                        <?php if ($c['state_name']): ?>
                            <br><small style="color: #7f8c8d;"><?= htmlspecialchars($c['state_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= date('d M Y', strtotime($c['start_date'])) ?>
                        <?php if ($c['end_date'] && $c['end_date'] !== $c['start_date']): ?>
                            <br><small>to <?= date('d M Y', strtotime($c['end_date'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['budget'] ? number_format($c['budget'], 0) : '-' ?></td>
                    <td><?= $c['leads_generated'] ?: '-' ?></td>
                    <td>
                        <span class="status-badge status-<?= $c['status'] ?>"><?= $c['status'] ?></span>
                    </td>
                    <td>
                        <a href="campaign_view.php?id=<?= $c['id'] ?>" class="btn btn-sm">View</a>
                        <a href="campaign_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
