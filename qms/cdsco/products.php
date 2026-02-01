<?php
include "../../db.php";
include "../../includes/sidebar.php";

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query with search
$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (product_name LIKE :search
                    OR registration_no LIKE :search
                    OR product_category LIKE :search
                    OR risk_class LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClause .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

// Get total count
$countSql = "SELECT COUNT(*) FROM qms_cdsco_products $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get status counts for quick filters
$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM qms_cdsco_products
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html>
<head>
    <title>CDSCO Products - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-under { background: #d1ecf1; color: #0c5460; }
        .status-query { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-renewed { background: #d4edda; color: #155724; }

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
            transition: all 0.2s;
        }
        .quick-filter:hover { background: #e0e0e0; }
        .quick-filter.active { background: #007bff; color: white; }
        .quick-filter .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }

        .risk-class {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .risk-a { background: #d4edda; color: #155724; }
        .risk-b { background: #fff3cd; color: #856404; }
        .risk-c { background: #f8d7da; color: #721c24; }
        .risk-d { background: #721c24; color: white; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>CDSCO Product Registration</h1>
        <a href="../dashboard.php" class="btn btn-secondary">← Back to QMS Dashboard</a>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="products.php" class="quick-filter <?= $statusFilter === '' ? 'active' : '' ?>">
            All <span class="count"><?= array_sum($statusCounts) ?></span>
        </a>
        <a href="?status=Approved" class="quick-filter <?= $statusFilter === 'Approved' ? 'active' : '' ?>">
            ✓ Approved <span class="count"><?= $statusCounts['Approved'] ?? 0 ?></span>
        </a>
        <a href="?status=Draft" class="quick-filter <?= $statusFilter === 'Draft' ? 'active' : '' ?>">
            Draft <span class="count"><?= $statusCounts['Draft'] ?? 0 ?></span>
        </a>
        <a href="?status=Submitted" class="quick-filter <?= $statusFilter === 'Submitted' ? 'active' : '' ?>">
            📤 Submitted <span class="count"><?= $statusCounts['Submitted'] ?? 0 ?></span>
        </a>
        <a href="?status=Under Review" class="quick-filter <?= $statusFilter === 'Under Review' ? 'active' : '' ?>">
            Under Review <span class="count"><?= $statusCounts['Under Review'] ?? 0 ?></span>
        </a>
        <a href="?status=Expired" class="quick-filter <?= $statusFilter === 'Expired' ? 'active' : '' ?>">
            ⚠ Expired <span class="count"><?= $statusCounts['Expired'] ?? 0 ?></span>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="product_add.php" class="btn btn-primary">+ Add Product</a>
            <a href="../cdsco/licenses.php" class="btn btn-secondary">Manufacturing Licenses</a>
            <a href="../cdsco/adverse_events.php" class="btn btn-secondary">Adverse Events</a>
        </div>

        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <?php if ($statusFilter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search products, registration no..."
                   value="<?= htmlspecialchars($search) ?>"
                   style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 280px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="products.php<?= $statusFilter ? '?status='.$statusFilter : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($search !== ''): ?>
    <div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
        Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
        (<?= $total_count ?> product<?= $total_count != 1 ? 's' : '' ?> found)
    </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Risk Class</th>
            <th>Registration No</th>
            <th>Status</th>
            <th>Registration Date</th>
            <th>Expiry Date</th>
            <th>Actions</th>
        </tr>

        <?php
        $sql = "SELECT * FROM qms_cdsco_products $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $row['status']));
            $riskClass = 'risk-' . strtolower(str_replace('Class ', '', $row['risk_class']));

            // Check if expiring soon (within 90 days)
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
            <td>
                <?= htmlspecialchars($row['product_name']) ?>
                <?= $expiringSoon ? '<span title="Expiring Soon" style="color: orange;">⚠</span>' : '' ?>
            </td>
            <td><?= htmlspecialchars($row['product_category']) ?></td>
            <td>
                <span class="risk-class <?= $riskClass ?>">
                    <?= htmlspecialchars($row['risk_class']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['registration_no'] ?: '-') ?></td>
            <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= $row['registration_date'] ? date('d-M-Y', strtotime($row['registration_date'])) : '-' ?></td>
            <td><?= $row['expiry_date'] ? date('d-M-Y', strtotime($row['expiry_date'])) : '-' ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="product_view.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn btn-secondary" href="product_edit.php?id=<?= $row['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                No products found. <a href="product_add.php">Add your first product</a>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <!-- Pagination -->
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

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total products)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $statusParam ?><?= $searchParam ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
