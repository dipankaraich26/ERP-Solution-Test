<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customer_id) {
    header("Location: index.php");
    exit;
}

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: index.php");
    exit;
}

// Get catalogs from marketing module
$catalogs = [];
try {
    $catStmt = $pdo->query("
        SELECT
            c.id,
            c.catalog_name,
            c.catalog_code,
            c.description,
            c.category,
            c.file_path,
            c.thumbnail_path,
            c.status,
            c.created_at
        FROM marketing_catalogs c
        WHERE c.status = 'Active'
        ORDER BY c.category, c.catalog_name
    ");
    $catalogs = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// Group by category
$catalogsByCategory = [];
foreach ($catalogs as $cat) {
    $category = $cat['category'] ?: 'General';
    if (!isset($catalogsByCategory[$category])) {
        $catalogsByCategory[$category] = [];
    }
    $catalogsByCategory[$category][] = $cat;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Catalog - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .breadcrumb {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .customer-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }

        .category-section {
            margin-bottom: 30px;
        }
        .category-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .catalog-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .catalog-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .catalog-thumbnail {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            color: white;
        }
        .catalog-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .catalog-content {
            padding: 15px;
        }
        .catalog-content h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
        }
        .catalog-content .code {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        .catalog-content .desc {
            font-size: 0.9em;
            color: #495057;
            margin-bottom: 15px;
            line-height: 1.4;
            max-height: 60px;
            overflow: hidden;
        }
        .catalog-actions {
            display: flex;
            gap: 10px;
        }
        .catalog-actions a {
            flex: 1;
            text-align: center;
        }

        .no-catalogs {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .no-catalogs .icon { font-size: 3em; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        Catalog
    </div>

    <div class="page-header">
        <h1>Product Catalog</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <?php if (empty($catalogs)): ?>
        <div class="no-catalogs">
            <div class="icon">📚</div>
            <h3 style="color: #2c3e50;">No Catalogs Available</h3>
            <p style="color: #7f8c8d;">Product catalogs will appear here once they are published.</p>
        </div>
    <?php else: ?>
        <?php foreach ($catalogsByCategory as $category => $items): ?>
            <div class="category-section">
                <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
                <div class="catalog-grid">
                    <?php foreach ($items as $cat): ?>
                        <div class="catalog-card">
                            <div class="catalog-thumbnail">
                                <?php if ($cat['thumbnail_path']): ?>
                                    <img src="/<?= htmlspecialchars($cat['thumbnail_path']) ?>" alt="<?= htmlspecialchars($cat['catalog_name']) ?>">
                                <?php else: ?>
                                    📄
                                <?php endif; ?>
                            </div>
                            <div class="catalog-content">
                                <h4><?= htmlspecialchars($cat['catalog_name']) ?></h4>
                                <?php if ($cat['catalog_code']): ?>
                                    <div class="code">Code: <?= htmlspecialchars($cat['catalog_code']) ?></div>
                                <?php endif; ?>
                                <div class="desc"><?= htmlspecialchars($cat['description'] ?: 'No description available.') ?></div>
                                <div class="catalog-actions">
                                    <a href="/marketing/catalog_view.php?id=<?= $cat['id'] ?>" class="btn btn-sm" target="_blank">View Details</a>
                                    <?php if ($cat['file_path']): ?>
                                        <a href="/<?= htmlspecialchars($cat['file_path']) ?>" class="btn btn-sm btn-primary" target="_blank" download>Download</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="index.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary">&larr; Back to Portal</a>
    </div>
</div>

</body>
</html>
