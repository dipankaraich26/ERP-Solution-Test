<?php
include "../../db.php";
include "../../includes/sidebar.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (ncr_no LIKE :search OR description LIKE :search OR source LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_ncr $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count FROM qms_ncr GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Non-Conformance Reports - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-open { background: #f8d7da; color: #721c24; }
        .status-actionplanned { background: #fff3cd; color: #856404; }
        .status-inprogress { background: #cce5ff; color: #004085; }
        .status-verificationpending { background: #d1ecf1; color: #0c5460; }
        .status-closed { background: #d4edda; color: #155724; }
        .status-reopened { background: #f8d7da; color: #721c24; }

        .severity-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .severity-major { background: #dc3545; color: white; }
        .severity-minor { background: #ffc107; color: #333; }
        .severity-observation { background: #17a2b8; color: white; }

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

        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Non-Conformance Reports (NCR)</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="ncr.php" class="quick-filter <?= $statusFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?status=Open" class="quick-filter <?= $statusFilter === 'Open' ? 'active' : '' ?>">
            Open <span class="count"><?= $statusCounts['Open'] ?? 0 ?></span>
        </a>
        <a href="?status=Action Planned" class="quick-filter <?= $statusFilter === 'Action Planned' ? 'active' : '' ?>">
            Action Planned <span class="count"><?= $statusCounts['Action Planned'] ?? 0 ?></span>
        </a>
        <a href="?status=In Progress" class="quick-filter <?= $statusFilter === 'In Progress' ? 'active' : '' ?>">
            In Progress <span class="count"><?= $statusCounts['In Progress'] ?? 0 ?></span>
        </a>
        <a href="?status=Closed" class="quick-filter <?= $statusFilter === 'Closed' ? 'active' : '' ?>">
            Closed <span class="count"><?= $statusCounts['Closed'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="ncr_add.php" class="btn btn-primary">+ Raise NCR</a>
            <a href="capa.php" class="btn btn-secondary">CAPA</a>
            <a href="audits.php" class="btn btn-secondary">Audits</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search NCRs..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="ncr.php<?= $statusFilter ? '?status='.$statusFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>NCR No</th>
            <th>Severity</th>
            <th>Source</th>
            <th>Description</th>
            <th>Detected Date</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT *, nc_type as severity, target_date as due_date, created_at as detected_date FROM qms_ncr $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
            $severityClass = 'severity-' . strtolower(str_replace(' ', '', $row['severity']));

            // Check if overdue
            $isOverdue = false;
            if ($row['due_date'] && $row['status'] !== 'Closed' && $row['status'] !== 'Verified') {
                $dueDate = new DateTime($row['due_date']);
                $today = new DateTime();
                if ($dueDate < $today) {
                    $isOverdue = true;
                }
            }
        ?>
        <tr <?= $isOverdue ? 'style="background: #fff8e6;"' : '' ?>>
            <td><?= htmlspecialchars($row['ncr_no'] ?: 'NCR-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT)) ?></td>
            <td><span class="severity-badge <?= $severityClass ?>"><?= htmlspecialchars($row['severity']) ?></span></td>
            <td><?= htmlspecialchars($row['source']) ?></td>
            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars(substr($row['description'], 0, 80)) ?>...
            </td>
            <td><?= date('d-M-Y', strtotime($row['detected_date'])) ?></td>
            <td class="<?= $isOverdue ? 'overdue' : '' ?>">
                <?= $row['due_date'] ? date('d-M-Y', strtotime($row['due_date'])) : '-' ?>
                <?= $isOverdue ? ' (Overdue)' : '' ?>
            </td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="ncr_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="ncr_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                No NCRs found. <a href="ncr_add.php">Raise a new NCR</a>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
    $searchParam = $search !== '' ? '&search=' . urlencode($search) : '';
    $statusParam = $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '';
    ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
