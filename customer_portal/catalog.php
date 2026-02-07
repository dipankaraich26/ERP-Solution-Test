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
        SELECT c.id, c.catalog_name, c.catalog_code, c.description, c.category,
               c.image_path, c.brochure_path, c.status
        FROM marketing_catalogs c
        WHERE c.status = 'Active'
        ORDER BY c.category, c.catalog_name
    ");
    $catalogs = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .breadcrumb { color: #7f8c8d; margin-bottom: 10px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .customer-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; }

        .category-section { margin-bottom: 30px; }
        .category-title { font-size: 1.3em; color: #2c3e50; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }

        .catalog-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
        .catalog-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        .catalog-card:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.12); }
        .catalog-pdf-icon {
            width: 60px; height: 70px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.8em; flex-shrink: 0;
        }
        .catalog-pdf-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .catalog-info { flex: 1; }
        .catalog-info h4 { margin: 0 0 5px 0; color: #2c3e50; }
        .catalog-info .code { font-size: 0.85em; color: #7f8c8d; margin-bottom: 5px; }
        .catalog-info .desc { font-size: 0.9em; color: #495057; line-height: 1.4; }
        .catalog-actions { display: flex; gap: 10px; flex-shrink: 0; }
        .btn-pdf-download {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s;
        }
        .btn-pdf-download:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231,76,60,0.4); }
        .no-pdf { color: #adb5bd; font-size: 0.9em; font-style: italic; }
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
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">📚</div>
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
                        <div class="catalog-pdf-icon">
                            <?php if ($cat['image_path']): ?>
                                <img src="/<?= htmlspecialchars($cat['image_path']) ?>" alt="">
                            <?php else: ?>
                                📄
                            <?php endif; ?>
                        </div>
                        <div class="catalog-info">
                            <h4><?= htmlspecialchars($cat['catalog_name']) ?></h4>
                            <?php if ($cat['catalog_code']): ?><div class="code">Code: <?= htmlspecialchars($cat['catalog_code']) ?></div><?php endif; ?>
                            <div class="desc"><?= htmlspecialchars($cat['description'] ?: '') ?></div>
                        </div>
                        <div class="catalog-actions">
                            <?php if ($cat['brochure_path']): ?>
                                <a href="/<?= htmlspecialchars($cat['brochure_path']) ?>" class="btn-pdf-download" target="_blank">
                                    📥 Download PDF
                                </a>
                            <?php else: ?>
                                <span class="no-pdf">PDF not available</span>
                            <?php endif; ?>
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
