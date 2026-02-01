<?php
include "../../db.php";
include "../../includes/sidebar.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$severityFilter = isset($_GET['severity']) ? trim($_GET['severity']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (product_name LIKE :search OR event_description LIKE :search OR report_no LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($severityFilter !== '') {
    $whereClause .= " AND severity = :severity";
    $params[':severity'] = $severityFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_cdsco_adverse_events $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$severityCounts = $pdo->query("
    SELECT severity, COUNT(*) as count FROM qms_cdsco_adverse_events GROUP BY severity
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Adverse Events - CDSCO</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .severity-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .severity-critical { background: #721c24; color: white; }
        .severity-serious { background: #f8d7da; color: #721c24; }
        .severity-moderate { background: #fff3cd; color: #856404; }
        .severity-minor { background: #d4edda; color: #155724; }

        .status-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
        }
        .status-open { background: #cce5ff; color: #004085; }
        .status-investigating { background: #fff3cd; color: #856404; }
        .status-closed { background: #d4edda; color: #155724; }
        .status-reported { background: #e2e3e5; color: #383d41; }

        .quick-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .quick-filter {
            padding: 8px 16px;
            border-radius: 20px;
            background: #f0f0f0;
            text-decoration: none;
            color: #333;
            font-size: 13px;
        }
        .quick-filter:hover { background: #e0e0e0; }
        .quick-filter.active { background: #007bff; color: white; }
        .quick-filter .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>CDSCO Adverse Event Reports (MDVR)</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="adverse_events.php" class="quick-filter <?= $severityFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($severityCounts) ?></span>
        </a>
        <a href="?severity=Critical" class="quick-filter <?= $severityFilter === 'Critical' ? 'active' : '' ?>">
            Critical <span class="count"><?= $severityCounts['Critical'] ?? 0 ?></span>
        </a>
        <a href="?severity=Serious" class="quick-filter <?= $severityFilter === 'Serious' ? 'active' : '' ?>">
            Serious <span class="count"><?= $severityCounts['Serious'] ?? 0 ?></span>
        </a>
        <a href="?severity=Moderate" class="quick-filter <?= $severityFilter === 'Moderate' ? 'active' : '' ?>">
            Moderate <span class="count"><?= $severityCounts['Moderate'] ?? 0 ?></span>
        </a>
        <a href="?severity=Minor" class="quick-filter <?= $severityFilter === 'Minor' ? 'active' : '' ?>">
            Minor <span class="count"><?= $severityCounts['Minor'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="adverse_event_add.php" class="btn btn-primary">+ Report Adverse Event</a>
            <a href="products.php" class="btn btn-secondary">Product Registration</a>
            <a href="licenses.php" class="btn btn-secondary">Manufacturing Licenses</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($severityFilter): ?>
                <input type="hidden" name="severity" value="<?= htmlspecialchars($severityFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search events..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="adverse_events.php<?= $severityFilter ? '?severity='.$severityFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Report No</th>
            <th>Product</th>
            <th>Event Date</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Description</th>
            <th>Reported To CDSCO</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT * FROM qms_cdsco_adverse_events $whereClause ORDER BY event_date DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $severityClass = 'severity-' . strtolower($row['severity']);
            $statusClass = 'status-' . strtolower($row['status']);
        ?>
        <tr>
            <td><?= htmlspecialchars($row['report_no'] ?: 'AE-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT)) ?></td>
            <td><?= htmlspecialchars($row['product_name']) ?></td>
            <td><?= date('d-M-Y', strtotime($row['event_date'])) ?></td>
            <td><span class="severity-badge <?= $severityClass ?>"><?= htmlspecialchars($row['severity']) ?></span></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars(substr($row['event_description'], 0, 100)) ?>...
            </td>
            <td style="text-align: center;">
                <?= $row['reported_to_cdsco'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: #666;">No</span>' ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="adverse_event_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="adverse_event_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                No adverse events recorded. <a href="adverse_event_add.php">Report an adverse event</a>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
    $searchParam = $search !== '' ? '&search=' . urlencode($search) : '';
    $severityParam = $severityFilter !== '' ? '&severity=' . urlencode($severityFilter) : '';
    ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $severityParam ?><?= $searchParam ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $severityParam ?><?= $searchParam ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $severityParam ?><?= $searchParam ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $severityParam ?><?= $searchParam ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
