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
    $whereClause .= " AND (doc_no LIKE :search OR title LIKE :search OR department LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($typeFilter !== '') {
    $whereClause .= " AND doc_type = :type";
    $params[':type'] = $typeFilter;
}

$countSql = "SELECT COUNT(*) FROM qms_documents $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$typeCounts = $pdo->query("
    SELECT doc_type, COUNT(*) as count FROM qms_documents GROUP BY doc_type
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Document Control - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-obsolete { background: #e2e3e5; color: #383d41; }
        .status-under { background: #cce5ff; color: #004085; }

        .type-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .type-procedure { background: #007bff; color: white; }
        .type-work { background: #17a2b8; color: white; }
        .type-form { background: #28a745; color: white; }
        .type-policy { background: #6f42c1; color: white; }
        .type-specification { background: #fd7e14; color: white; }
        .type-manual { background: #dc3545; color: white; }

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

        .revision-badge {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            color: #495057;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Document Control</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <div class="quick-filters">
        <a href="documents.php" class="quick-filter <?= $typeFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($typeCounts) ?></span>
        </a>
        <a href="?type=Procedure" class="quick-filter <?= $typeFilter === 'Procedure' ? 'active' : '' ?>">
            Procedures <span class="count"><?= $typeCounts['Procedure'] ?? 0 ?></span>
        </a>
        <a href="?type=Work Instruction" class="quick-filter <?= $typeFilter === 'Work Instruction' ? 'active' : '' ?>">
            Work Instructions <span class="count"><?= $typeCounts['Work Instruction'] ?? 0 ?></span>
        </a>
        <a href="?type=Form" class="quick-filter <?= $typeFilter === 'Form' ? 'active' : '' ?>">
            Forms <span class="count"><?= $typeCounts['Form'] ?? 0 ?></span>
        </a>
        <a href="?type=Policy" class="quick-filter <?= $typeFilter === 'Policy' ? 'active' : '' ?>">
            Policies <span class="count"><?= $typeCounts['Policy'] ?? 0 ?></span>
        </a>
        <a href="?type=Specification" class="quick-filter <?= $typeFilter === 'Specification' ? 'active' : '' ?>">
            Specifications <span class="count"><?= $typeCounts['Specification'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="document_add.php" class="btn btn-primary">+ Add Document</a>
            <a href="certifications.php" class="btn btn-secondary">Certifications</a>
            <a href="audits.php" class="btn btn-secondary">Audits</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($typeFilter): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search documents..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 250px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="documents.php<?= $typeFilter ? '?type='.$typeFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Doc No</th>
            <th>Type</th>
            <th>Title</th>
            <th>Department</th>
            <th>Revision</th>
            <th>Status</th>
            <th>Effective Date</th>
            <th>Review Date</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT * FROM qms_documents $whereClause ORDER BY doc_no ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
            $typeClass = 'type-' . strtolower(str_replace(' ', '', $row['doc_type']));

            // Check if review due
            $reviewDue = false;
            if ($row['review_date'] && $row['status'] === 'Active') {
                $reviewDate = new DateTime($row['review_date']);
                $today = new DateTime();
                $diff = $today->diff($reviewDate);
                if ($reviewDate <= $today || $diff->days <= 30) {
                    $reviewDue = true;
                }
            }
        ?>
        <tr <?= $reviewDue ? 'style="background: #fff8e6;"' : '' ?>>
            <td><?= htmlspecialchars($row['doc_no']) ?></td>
            <td><span class="type-badge <?= $typeClass ?>"><?= htmlspecialchars($row['doc_type']) ?></span></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['department']) ?></td>
            <td><span class="revision-badge">Rev <?= htmlspecialchars($row['revision']) ?></span></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= $row['effective_date'] ? date('d-M-Y', strtotime($row['effective_date'])) : '-' ?></td>
            <td style="<?= $reviewDue ? 'color: orange; font-weight: bold;' : '' ?>">
                <?= $row['review_date'] ? date('d-M-Y', strtotime($row['review_date'])) : '-' ?>
                <?= $reviewDue ? ' ⚠' : '' ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="document_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="document_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                No documents found. <a href="document_add.php">Add your first document</a>
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
