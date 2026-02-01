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
    $whereClause .= " AND (license_type LIKE :search OR license_no LIKE :search OR facility_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_cdsco_licenses $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count FROM qms_cdsco_licenses GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manufacturing Licenses - CDSCO</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #e2e3e5; color: #383d41; }

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

        .license-type-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: #e7f3ff;
            color: #004085;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>CDSCO Manufacturing Licenses</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="licenses.php" class="quick-filter <?= $statusFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?status=Active" class="quick-filter <?= $statusFilter === 'Active' ? 'active' : '' ?>">
            Active <span class="count"><?= $statusCounts['Active'] ?? 0 ?></span>
        </a>
        <a href="?status=Pending" class="quick-filter <?= $statusFilter === 'Pending' ? 'active' : '' ?>">
            Pending <span class="count"><?= $statusCounts['Pending'] ?? 0 ?></span>
        </a>
        <a href="?status=Expired" class="quick-filter <?= $statusFilter === 'Expired' ? 'active' : '' ?>">
            Expired <span class="count"><?= $statusCounts['Expired'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="license_add.php" class="btn btn-primary">+ Add License</a>
            <a href="products.php" class="btn btn-secondary">Product Registration</a>
            <a href="adverse_events.php" class="btn btn-secondary">Adverse Events</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search licenses..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="licenses.php<?= $statusFilter ? '?status='.$statusFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>License Type</th>
            <th>License No</th>
            <th>Facility Name</th>
            <th>Status</th>
            <th>Issue Date</th>
            <th>Expiry Date</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT * FROM qms_cdsco_licenses $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower($row['status']);

            $expiringSoon = false;
            if ($row['expiry_date']) {
                $expiryDate = new DateTime($row['expiry_date']);
                $today = new DateTime();
                $diff = $today->diff($expiryDate);
                if ($diff->days <= 90 && $expiryDate > $today) {
                    $expiringSoon = true;
                }
            }
        ?>
        <tr <?= $expiringSoon ? 'style="background: #fff8e6;"' : '' ?>>
            <td><span class="license-type-badge"><?= htmlspecialchars($row['license_type']) ?></span></td>
            <td><?= htmlspecialchars($row['license_no'] ?: '-') ?></td>
            <td>
                <?= htmlspecialchars($row['facility_name']) ?>
                <?= $expiringSoon ? '<span title="Expiring Soon" style="color: orange;">⚠</span>' : '' ?>
            </td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= $row['issue_date'] ? date('d-M-Y', strtotime($row['issue_date'])) : '-' ?></td>
            <td><?= $row['expiry_date'] ? date('d-M-Y', strtotime($row['expiry_date'])) : '-' ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="license_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="license_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                No licenses found. <a href="license_add.php">Add your first license</a>
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
