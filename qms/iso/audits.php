<?php
include "../../db.php";
include "../../includes/sidebar.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (audit_no LIKE :search OR auditor_name LIKE :search OR scope LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($typeFilter !== '') {
    $whereClause .= " AND audit_type = :type";
    $params[':type'] = $typeFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_iso_audits $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$typeCounts = $pdo->query("
    SELECT audit_type, COUNT(*) as count FROM qms_iso_audits GROUP BY audit_type
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>ISO Audits - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-planned { background: #cce5ff; color: #004085; }
        .status-inprogress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-postponed { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }

        .type-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .type-internal { background: #17a2b8; color: white; }
        .type-external { background: #6c757d; color: white; }
        .type-supplier { background: #ffc107; color: #333; }
        .type-customer { background: #28a745; color: white; }
        .type-regulatory { background: #dc3545; color: white; }

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

        .findings-summary {
            display: flex;
            gap: 8px;
        }
        .finding-count {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .finding-major { background: #f8d7da; color: #721c24; }
        .finding-minor { background: #fff3cd; color: #856404; }
        .finding-obs { background: #e7f3ff; color: #004085; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>ISO Audits</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="audits.php" class="quick-filter <?= $typeFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($typeCounts) ?></span>
        </a>
        <a href="?type=Internal" class="quick-filter <?= $typeFilter === 'Internal' ? 'active' : '' ?>">
            Internal <span class="count"><?= $typeCounts['Internal'] ?? 0 ?></span>
        </a>
        <a href="?type=External" class="quick-filter <?= $typeFilter === 'External' ? 'active' : '' ?>">
            External <span class="count"><?= $typeCounts['External'] ?? 0 ?></span>
        </a>
        <a href="?type=Supplier" class="quick-filter <?= $typeFilter === 'Supplier' ? 'active' : '' ?>">
            Supplier <span class="count"><?= $typeCounts['Supplier'] ?? 0 ?></span>
        </a>
        <a href="?type=Regulatory" class="quick-filter <?= $typeFilter === 'Regulatory' ? 'active' : '' ?>">
            Regulatory <span class="count"><?= $typeCounts['Regulatory'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="audit_add.php" class="btn btn-primary">+ Schedule Audit</a>
            <a href="certifications.php" class="btn btn-secondary">Certifications</a>
            <a href="ncr.php" class="btn btn-secondary">NCRs</a>
            <a href="capa.php" class="btn btn-secondary">CAPA</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($typeFilter): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search audits..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="audits.php<?= $typeFilter ? '?type='.$typeFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Audit No</th>
            <th>Type</th>
            <th>Scope/Standard</th>
            <th>Auditor</th>
            <th>Audit Date</th>
            <th>Status</th>
            <th>Findings</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT *, audit_scope as scope, lead_auditor as auditor_name, COALESCE(actual_date, planned_date) as audit_date FROM qms_iso_audits $whereClause ORDER BY COALESCE(actual_date, planned_date) DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
            $typeClass = 'type-' . strtolower($row['audit_type']);
        ?>
        <tr>
            <td><?= htmlspecialchars($row['audit_no'] ?: 'AUD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
            <td><span class="type-badge <?= $typeClass ?>"><?= htmlspecialchars($row['audit_type']) ?></span></td>
            <td style="max-width: 200px;"><?= htmlspecialchars(substr($row['scope'], 0, 60)) ?>...</td>
            <td><?= htmlspecialchars($row['auditor_name']) ?></td>
            <td><?= $row['audit_date'] ? date('d-M-Y', strtotime($row['audit_date'])) : '-' ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td>
                <div class="findings-summary">
                    <?php if ($row['major_nc'] > 0): ?>
                    <span class="finding-count finding-major"><?= $row['major_nc'] ?> Major</span>
                    <?php endif; ?>
                    <?php if ($row['minor_nc'] > 0): ?>
                    <span class="finding-count finding-minor"><?= $row['minor_nc'] ?> Minor</span>
                    <?php endif; ?>
                    <?php if ($row['observations'] > 0): ?>
                    <span class="finding-count finding-obs"><?= $row['observations'] ?> OBS</span>
                    <?php endif; ?>
                    <?php if ($row['major_nc'] == 0 && $row['minor_nc'] == 0 && $row['observations'] == 0): ?>
                    <span style="color: #666;">-</span>
                    <?php endif; ?>
                </div>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="audit_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="audit_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                No audits found. <a href="audit_add.php">Schedule your first audit</a>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
    $searchParam = $search !== '' ? '&search=' . urlencode($search) : '';
    $typeParam = $typeFilter !== '' ? '&type=' . urlencode($typeFilter) : '';
    ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $typeParam ?><?= $searchParam ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $typeParam ?><?= $searchParam ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $typeParam ?><?= $searchParam ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $typeParam ?><?= $searchParam ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
