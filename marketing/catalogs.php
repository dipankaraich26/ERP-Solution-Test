<?php
include "../db.php";
include "../includes/dialog.php";

// Filters
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}
if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}
if ($search) {
    $where[] = "(catalog_code LIKE ? OR catalog_name LIKE ? OR model_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$stmt = $pdo->prepare("SELECT * FROM marketing_catalogs WHERE $whereClause ORDER BY catalog_name");
$stmt->execute($params);
$catalogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT name FROM catalog_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM marketing_catalogs")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM marketing_catalogs WHERE status = 'Active'")->fetchColumn(),
];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Catalogs - Marketing</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 120px;
        }
        .stat-box .number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-box .label { color: #7f8c8d; }
        .stat-box.active .number { color: #27ae60; }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .catalog-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.2s;
        }
        .catalog-card:hover { transform: translateY(-3px); }

        .catalog-image {
            height: 150px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bdc3c7;
            font-size: 3em;
        }
        .catalog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .catalog-info {
            padding: 15px;
        }
        .catalog-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.1em;
        }
        .catalog-info .code {
            color: #7f8c8d;
            font-size: 0.85em;
            margin-bottom: 10px;
        }
        .catalog-info .category {
            display: inline-block;
            padding: 3px 10px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 12px;
            font-size: 0.8em;
            margin-bottom: 10px;
        }
        .catalog-info .description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #fff3cd; color: #856404; }
        .status-Discontinued { background: #f8d7da; color: #721c24; }

        .catalog-actions {
            padding: 10px 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Product Catalogs</h1>

    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total Catalogs</div>
        </div>
        <div class="stat-box active">
            <div class="number"><?= $stats['active'] ?></div>
            <div class="label">Active</div>
        </div>
    </div>

    <div class="filters">
        <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <input type="text" name="search" placeholder="Search catalogs..."
                   value="<?= htmlspecialchars($search) ?>" style="width: 200px;">

            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">All Status</option>
                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="Discontinued" <?= $status === 'Discontinued' ? 'selected' : '' ?>>Discontinued</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="catalogs.php" class="btn btn-secondary">Reset</a>
        </form>

        <div style="margin-left: auto;">
            <a href="catalog_add.php" class="btn btn-success">+ Add Catalog</a>
        </div>
    </div>

    <?php if (empty($catalogs)): ?>
        <div style="text-align: center; padding: 60px; background: white; border-radius: 10px;">
            <p style="color: #7f8c8d; font-size: 1.2em;">No catalogs found</p>
            <a href="catalog_add.php" class="btn btn-success">Add Your First Catalog</a>
        </div>
    <?php else: ?>
        <div class="catalog-grid">
            <?php foreach ($catalogs as $cat): ?>
            <div class="catalog-card">
                <div class="catalog-image">
                    <?php if ($cat['image_path']): ?>
                        <img src="../<?= htmlspecialchars($cat['image_path']) ?>" alt="">
                    <?php else: ?>
                        📦
                    <?php endif; ?>
                </div>
                <div class="catalog-info">
                    <h3><?= htmlspecialchars($cat['catalog_name']) ?></h3>
                    <div class="code"><?= htmlspecialchars($cat['catalog_code']) ?>
                        <?php if ($cat['model_no']): ?>
                            | Model: <?= htmlspecialchars($cat['model_no']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($cat['category']): ?>
                        <span class="category"><?= htmlspecialchars($cat['category']) ?></span>
                    <?php endif; ?>
                    <span class="status-badge status-<?= $cat['status'] ?>"><?= $cat['status'] ?></span>
                    <?php if ($cat['description']): ?>
                        <p class="description"><?= htmlspecialchars($cat['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="catalog-actions">
                    <a href="catalog_view.php?id=<?= $cat['id'] ?>" class="btn btn-sm">View</a>
                    <a href="catalog_edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
