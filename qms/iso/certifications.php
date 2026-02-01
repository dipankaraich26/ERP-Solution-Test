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
    $whereClause .= " AND (standard_name LIKE :search OR certificate_no LIKE :search OR certification_body LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_iso_certifications $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count FROM qms_iso_certifications GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>ISO Certifications - QMS</title>
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

        .standard-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            background: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>ISO Certifications</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="certifications.php" class="quick-filter <?= $statusFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?status=Certified" class="quick-filter <?= $statusFilter === 'Certified' ? 'active' : '' ?>">
            Certified <span class="count"><?= $statusCounts['Certified'] ?? 0 ?></span>
        </a>
        <a href="?status=Planning" class="quick-filter <?= $statusFilter === 'Planning' ? 'active' : '' ?>">
            Planning <span class="count"><?= $statusCounts['Planning'] ?? 0 ?></span>
        </a>
        <a href="?status=Renewal Due" class="quick-filter <?= $statusFilter === 'Renewal Due' ? 'active' : '' ?>">
            Renewal Due <span class="count"><?= $statusCounts['Renewal Due'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="certification_add.php" class="btn btn-primary">+ Add Certification</a>
            <a href="audits.php" class="btn btn-secondary">Audits</a>
            <a href="ncr.php" class="btn btn-secondary">NCRs</a>
            <a href="capa.php" class="btn btn-secondary">CAPA</a>
            <a href="documents.php" class="btn btn-secondary">Documents</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search certifications..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="certifications.php<?= $statusFilter ? '?status='.$statusFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Standard</th>
            <th>Certificate No</th>
            <th>Scope</th>
            <th>Certifying Body</th>
            <th>Status</th>
            <th>Issue Date</th>
            <th>Expiry Date</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT * FROM qms_iso_certifications $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
            <td><span class="standard-badge"><?= htmlspecialchars($row['standard_name']) ?></span></td>
            <td><?= htmlspecialchars($row['certificate_no'] ?: '-') ?></td>
            <td style="max-width: 200px;"><?= htmlspecialchars(substr($row['scope'], 0, 80)) ?>...</td>
            <td><?= htmlspecialchars($row['certification_body'] ?? '') ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= $row['issue_date'] ? date('d-M-Y', strtotime($row['issue_date'])) : '-' ?></td>
            <td>
                <?= $row['expiry_date'] ? date('d-M-Y', strtotime($row['expiry_date'])) : '-' ?>
                <?= $expiringSoon ? '<span style="color: orange;">⚠</span>' : '' ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="certification_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="certification_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                No certifications found. <a href="certification_add.php">Add your first certification</a>
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
