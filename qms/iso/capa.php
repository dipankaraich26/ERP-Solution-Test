<?php
include "../../db.php";
include "../../includes/sidebar.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (capa_no LIKE :search OR description LIKE :search OR source LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

if ($typeFilter !== '') {
    $whereClause .= " AND capa_type = :type";
    $params[':type'] = $typeFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_capa $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count FROM qms_capa GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$typeCounts = $pdo->query("
    SELECT capa_type, COUNT(*) as count FROM qms_capa GROUP BY capa_type
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>CAPA - Corrective and Preventive Actions</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-open { background: #f8d7da; color: #721c24; }
        .status-planning { background: #cce5ff; color: #004085; }
        .status-implementation { background: #fff3cd; color: #856404; }
        .status-verification { background: #d1ecf1; color: #0c5460; }
        .status-closed { background: #d4edda; color: #155724; }

        .type-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .type-corrective { background: #dc3545; color: white; }
        .type-preventive { background: #17a2b8; color: white; }

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

        .effectiveness-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .effectiveness-verified { background: #28a745; }
        .effectiveness-pending { background: #ffc107; }
        .effectiveness-na { background: #6c757d; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>CAPA - Corrective & Preventive Actions</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="capa.php" class="quick-filter <?= $statusFilter === '' && $typeFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?type=Corrective" class="quick-filter <?= $typeFilter === 'Corrective' ? 'active' : '' ?>">
            Corrective <span class="count"><?= $typeCounts['Corrective'] ?? 0 ?></span>
        </a>
        <a href="?type=Preventive" class="quick-filter <?= $typeFilter === 'Preventive' ? 'active' : '' ?>">
            Preventive <span class="count"><?= $typeCounts['Preventive'] ?? 0 ?></span>
        </a>
        <a href="?status=Open" class="quick-filter <?= $statusFilter === 'Open' ? 'active' : '' ?>">
            Open <span class="count"><?= $statusCounts['Open'] ?? 0 ?></span>
        </a>
        <a href="?status=Implementation" class="quick-filter <?= $statusFilter === 'Implementation' ? 'active' : '' ?>">
            In Progress <span class="count"><?= $statusCounts['Implementation'] ?? 0 ?></span>
        </a>
        <a href="?status=Closed" class="quick-filter <?= $statusFilter === 'Closed' ? 'active' : '' ?>">
            Closed <span class="count"><?= $statusCounts['Closed'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="capa_add.php" class="btn btn-primary">+ Initiate CAPA</a>
            <a href="ncr.php" class="btn btn-secondary">NCRs</a>
            <a href="audits.php" class="btn btn-secondary">Audits</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="search" placeholder="Search CAPA..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== '' || $statusFilter !== '' || $typeFilter !== ''): ?>
                <a href="capa.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>CAPA No</th>
            <th>Type</th>
            <th>Source</th>
            <th>Description</th>
            <th>Initiated</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Effectiveness</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT *, problem_description as description, created_at as initiated_date, target_date as due_date, CASE WHEN effectiveness_result IS NOT NULL AND effectiveness_result != '' THEN 1 ELSE 0 END as effectiveness_verified FROM qms_capa $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
            $typeClass = 'type-' . strtolower($row['capa_type']);

            // Check if overdue
            $isOverdue = false;
            if ($row['due_date'] && $row['status'] !== 'Closed') {
                $dueDate = new DateTime($row['due_date']);
                $today = new DateTime();
                if ($dueDate < $today) {
                    $isOverdue = true;
                }
            }

            $effectivenessClass = 'effectiveness-na';
            $effectivenessText = 'Pending';
            if ($row['effectiveness_verified']) {
                $effectivenessClass = 'effectiveness-verified';
                $effectivenessText = 'Verified';
            }
        ?>
        <tr <?= $isOverdue ? 'style="background: #fff8e6;"' : '' ?>>
            <td><?= htmlspecialchars($row['capa_no'] ?: 'CAPA-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT)) ?></td>
            <td><span class="type-badge <?= $typeClass ?>"><?= htmlspecialchars($row['capa_type']) ?></span></td>
            <td><?= htmlspecialchars($row['source']) ?></td>
            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars(substr($row['description'], 0, 60)) ?>...
            </td>
            <td><?= date('d-M-Y', strtotime($row['initiated_date'])) ?></td>
            <td style="<?= $isOverdue ? 'color: red; font-weight: bold;' : '' ?>">
                <?= $row['due_date'] ? date('d-M-Y', strtotime($row['due_date'])) : '-' ?>
            </td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td>
                <span class="effectiveness-indicator <?= $effectivenessClass ?>"></span>
                <?= $effectivenessText ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="capa_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="capa_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                No CAPA records found. <a href="capa_add.php">Initiate a new CAPA</a>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
    $queryParams = [];
    if ($search !== '') $queryParams[] = 'search=' . urlencode($search);
    if ($statusFilter !== '') $queryParams[] = 'status=' . urlencode($statusFilter);
    if ($typeFilter !== '') $queryParams[] = 'type=' . urlencode($typeFilter);
    $queryString = implode('&', $queryParams);
    $queryString = $queryString ? '&' . $queryString : '';
    ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $queryString ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $queryString ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $queryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
