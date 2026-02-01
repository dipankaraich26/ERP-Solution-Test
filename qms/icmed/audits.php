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
    $whereClause .= " AND (audit_no LIKE :search OR auditor_name LIKE :search OR facility_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_icmed_audits $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count FROM qms_icmed_audits GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>ICMED Factory Audits - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-scheduled { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-passed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }

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

        .audit-type-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .type-initial { background: #007bff; color: white; }
        .type-surveillance { background: #17a2b8; color: white; }
        .type-renewal { background: #28a745; color: white; }
        .type-special { background: #dc3545; color: white; }

        .result-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .result-pass { background: #d4edda; color: #155724; }
        .result-conditional { background: #fff3cd; color: #856404; }
        .result-fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>ICMED Factory Audits</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="audits.php" class="quick-filter <?= $statusFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?status=Scheduled" class="quick-filter <?= $statusFilter === 'Scheduled' ? 'active' : '' ?>">
            Scheduled <span class="count"><?= $statusCounts['Scheduled'] ?? 0 ?></span>
        </a>
        <a href="?status=Completed" class="quick-filter <?= $statusFilter === 'Completed' ? 'active' : '' ?>">
            Completed <span class="count"><?= $statusCounts['Completed'] ?? 0 ?></span>
        </a>
        <a href="?status=Passed" class="quick-filter <?= $statusFilter === 'Passed' ? 'active' : '' ?>">
            Passed <span class="count"><?= $statusCounts['Passed'] ?? 0 ?></span>
        </a>
        <a href="?status=Pending Corrective Action" class="quick-filter <?= $statusFilter === 'Pending Corrective Action' ? 'active' : '' ?>">
            Pending CA <span class="count"><?= $statusCounts['Pending Corrective Action'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="audit_add.php" class="btn btn-primary">+ Schedule Audit</a>
            <a href="certifications.php" class="btn btn-secondary">Certifications</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search audits..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="audits.php<?= $statusFilter ? '?status='.$statusFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Audit No</th>
            <th>Type</th>
            <th>Facility</th>
            <th>Auditor</th>
            <th>Audit Date</th>
            <th>Status</th>
            <th>Result</th>
            <th>NCs Found</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT *, COALESCE(actual_date, scheduled_date) as audit_date, audit_result as result, areas_audited as facility_name FROM qms_icmed_audits $whereClause ORDER BY COALESCE(actual_date, scheduled_date) DESC LIMIT :limit OFFSET :offset";
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

            $resultClass = '';
            if ($row['result'] === 'Pass') $resultClass = 'result-pass';
            elseif ($row['result'] === 'Conditional Pass') $resultClass = 'result-conditional';
            elseif ($row['result'] === 'Fail') $resultClass = 'result-fail';
        ?>
        <tr>
            <td><?= htmlspecialchars($row['audit_no'] ?: 'ICMED-AUD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)) ?></td>
            <td><span class="audit-type-badge <?= $typeClass ?>"><?= htmlspecialchars($row['audit_type']) ?></span></td>
            <td><?= htmlspecialchars($row['facility_name']) ?></td>
            <td><?= htmlspecialchars($row['auditor_name']) ?></td>
            <td><?= $row['audit_date'] ? date('d-M-Y', strtotime($row['audit_date'])) : '-' ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td>
                <?php if ($row['result']): ?>
                <span class="result-badge <?= $resultClass ?>"><?= htmlspecialchars($row['result']) ?></span>
                <?php else: ?>
                -
                <?php endif; ?>
            </td>
            <td style="text-align: center;">
                <?php
                $ncCount = ($row['major_nc'] ?? 0) + ($row['minor_nc'] ?? 0);
                if ($ncCount > 0) {
                    echo '<span style="color: ' . ($row['major_nc'] > 0 ? '#dc3545' : '#ffc107') . '; font-weight: bold;">' . $ncCount . '</span>';
                } else {
                    echo '-';
                }
                ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="audit_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="audit_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                No audits found. <a href="audit_add.php">Schedule your first audit</a>
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
