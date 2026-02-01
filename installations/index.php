<?php
include "../db.php";
include "../includes/dialog.php";

// Check if table exists, redirect to installer if not
try {
    $pdo->query("SELECT 1 FROM installations LIMIT 1");
} catch (PDOException $e) {
    header("Location: install.php");
    exit;
}

// Filters
$status = $_GET['status'] ?? '';
$engineer = $_GET['engineer'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "i.status = ?";
    $params[] = $status;
}
if ($engineer) {
    $where[] = "(i.engineer_id = ? OR i.external_engineer_name LIKE ?)";
    $params[] = $engineer;
    $params[] = "%$engineer%";
}
if ($search) {
    $where[] = "(i.installation_no LIKE ? OR c.company_name LIKE ? OR c.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($date_from) {
    $where[] = "i.installation_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where[] = "i.installation_date <= ?";
    $params[] = $date_to;
}

$whereClause = implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM installations i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE $whereClause
");
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Fetch installations
$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.company_name,
        c.customer_name,
        c.contact as customer_phone,
        CASE
            WHEN i.engineer_type = 'internal' THEN CONCAT(e.first_name, ' ', e.last_name)
            ELSE i.external_engineer_name
        END as engineer_name,
        im.invoice_no,
        im.invoice_date as inv_date
    FROM installations i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN employees e ON i.engineer_id = e.id
    LEFT JOIN invoice_master im ON i.invoice_id = im.id
    WHERE $whereClause
    ORDER BY i.installation_date DESC, i.id DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$installations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for each installation
$installationIds = array_column($installations, 'id');
$productsMap = [];
if (!empty($installationIds)) {
    $placeholders = implode(',', array_fill(0, count($installationIds), '?'));
    $prodStmt = $pdo->prepare("
        SELECT installation_id, part_no, product_name, quantity
        FROM installation_products
        WHERE installation_id IN ($placeholders)
        ORDER BY id
    ");
    $prodStmt->execute($installationIds);
    $allProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allProducts as $prod) {
        $productsMap[$prod['installation_id']][] = $prod;
    }
}

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM installations")->fetchColumn(),
    'scheduled' => $pdo->query("SELECT COUNT(*) FROM installations WHERE status = 'scheduled'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM installations WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM installations WHERE status = 'completed'")->fetchColumn(),
];

// Get engineers for filter
$engineers = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name
")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Installations - Sales</title>
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
            flex: 1;
        }
        .stat-box .number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-box .label { color: #7f8c8d; }
        .stat-box.scheduled .number { color: #3498db; }
        .stat-box.in-progress .number { color: #f39c12; }
        .stat-box.completed .number { color: #27ae60; }
        .filter-form {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-form label {
            font-size: 0.85em;
            color: #666;
        }
        .filter-form input, .filter-form select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-in_progress { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e9; color: #388e3c; }
        .status-cancelled { background: #ffebee; color: #d32f2f; }
        .status-on_hold { background: #f3e5f5; color: #7b1fa2; }
    </style>
</head>
<body>

<div class="content">
    <h1>Installations</h1>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-box scheduled">
            <div class="number"><?= $stats['scheduled'] ?></div>
            <div class="label">Scheduled</div>
        </div>
        <div class="stat-box in-progress">
            <div class="number"><?= $stats['in_progress'] ?></div>
            <div class="label">In Progress</div>
        </div>
        <div class="stat-box completed">
            <div class="number"><?= $stats['completed'] ?></div>
            <div class="label">Completed</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="margin-bottom: 20px;">
        <a href="add.php" class="btn btn-success">+ New Installation</a>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-form">
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Installation #, Customer...">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
            </select>
        </div>
        <div class="form-group">
            <label>Engineer</label>
            <select name="engineer">
                <option value="">All Engineers</option>
                <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>" <?= $engineer == $eng['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($eng['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>From Date</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="form-group">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="index.php" class="btn btn-secondary">Clear</a>
    </form>

    <!-- Installations Table -->
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Installation #</th>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Products Installed</th>
                    <th>Installation Date</th>
                    <th>Engineer</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($installations)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                            No installations found.
                            <a href="add.php">Create your first installation</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($installations as $inst): ?>
                        <?php $products = $productsMap[$inst['id']] ?? []; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inst['installation_no']) ?></strong></td>
                            <td>
                                <?php if ($inst['invoice_no']): ?>
                                    <strong><?= htmlspecialchars($inst['invoice_no']) ?></strong>
                                    <?php if ($inst['inv_date']): ?>
                                        <br><small style="color: #666;"><?= date('d M Y', strtotime($inst['inv_date'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($inst['company_name'] ?: $inst['customer_name']) ?></strong>
                                <?php if ($inst['customer_phone']): ?>
                                    <br><small><?= htmlspecialchars($inst['customer_phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $prod): ?>
                                        <div style="margin-bottom: 3px;">
                                            <span style="background: #e8f5e9; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">
                                                <?= htmlspecialchars($prod['part_no']) ?>
                                            </span>
                                            <span style="color: #666; font-size: 0.9em;">
                                                <?= htmlspecialchars($prod['product_name']) ?>
                                                <strong>(×<?= $prod['quantity'] ?>)</strong>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999;">No products listed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($inst['installation_date'])) ?>
                                <?php if ($inst['installation_time']): ?>
                                    <br><small><?= date('h:i A', strtotime($inst['installation_time'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($inst['engineer_name'] ?: 'Not Assigned') ?>
                                <?php if ($inst['engineer_type'] === 'external'): ?>
                                    <br><small style="color: #666;">(External)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $inst['status'] ?>">
                                    <?= ucwords(str_replace('_', ' ', $inst['status'])) ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="view.php?id=<?= $inst['id'] ?>" class="btn btn-primary btn-small">View</a>
                                <a href="edit.php?id=<?= $inst['id'] ?>" class="btn btn-secondary btn-small">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $queryString = $queryString ? "&$queryString" : '';
        ?>

        <?php if ($page > 1): ?>
            <a href="?page=1<?= $queryString ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $queryString ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> installations)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $queryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
