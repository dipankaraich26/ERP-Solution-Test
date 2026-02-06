<?php
session_start();
include "../db.php";

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) { header("Location: logout.php"); exit; }

$catalogs = [];
try {
    $catStmt = $pdo->query("SELECT id, catalog_name, catalog_code, description, category, file_path, thumbnail_path FROM marketing_catalogs WHERE status = 'Active' ORDER BY category, catalog_name");
    $catalogs = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$catalogsByCategory = [];
foreach ($catalogs as $cat) {
    $category = $cat['category'] ?: 'General';
    if (!isset($catalogsByCategory[$category])) $catalogsByCategory[$category] = [];
    $catalogsByCategory[$category][] = $cat;
}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog - Customer Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .portal-navbar { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .portal-navbar .brand { display: flex; align-items: center; gap: 15px; color: white; }
        .portal-navbar .brand img { height: 40px; }
        .portal-navbar .user-info { display: flex; align-items: center; gap: 20px; color: white; }
        .portal-navbar .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; }
        .portal-content { max-width: 1200px; margin: 0 auto; padding: 30px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #2c3e50; margin-top: 10px; }
        .back-link { color: #11998e; text-decoration: none; font-weight: 500; }
        .category-section { margin-bottom: 30px; }
        .category-title { font-size: 1.3em; color: #2c3e50; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #11998e; }
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .catalog-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; transition: all 0.3s; }
        .catalog-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
        .catalog-thumbnail { width: 100%; height: 160px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); display: flex; align-items: center; justify-content: center; font-size: 4em; color: white; }
        .catalog-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        .catalog-content { padding: 15px; }
        .catalog-content h4 { margin: 0 0 8px 0; color: #2c3e50; }
        .catalog-content .desc { font-size: 0.9em; color: #495057; margin-bottom: 15px; line-height: 1.4; max-height: 60px; overflow: hidden; }
        .catalog-actions { display: flex; gap: 10px; }
        .catalog-actions a { flex: 1; text-align: center; padding: 8px; border-radius: 6px; text-decoration: none; font-size: 0.9em; }
        .btn-view { background: #f8f9fa; color: #2c3e50; }
        .btn-download { background: #11998e; color: white; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; color: #7f8c8d; }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }
    </style>
</head>
<body>
<nav class="portal-navbar">
    <div class="brand">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?><img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo"><?php endif; ?>
        <h2>Customer Portal</h2>
    </div>
    <div class="user-info">
        <span><?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<div class="portal-content">
    <div class="page-header">
        <a href="my_portal.php" class="back-link">&larr; Back to Portal</a>
        <h1>Product Catalog</h1>
    </div>
    <?php if (empty($catalogs)): ?>
        <div class="empty-state"><div class="icon">📚</div><h3>No Catalogs Available</h3><p>Product catalogs will appear here once published.</p></div>
    <?php else: ?>
        <?php foreach ($catalogsByCategory as $category => $items): ?>
        <div class="category-section">
            <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
            <div class="catalog-grid">
                <?php foreach ($items as $cat): ?>
                <div class="catalog-card">
                    <div class="catalog-thumbnail"><?php if ($cat['thumbnail_path']): ?><img src="/<?= htmlspecialchars($cat['thumbnail_path']) ?>" alt=""><?php else: ?>📄<?php endif; ?></div>
                    <div class="catalog-content">
                        <h4><?= htmlspecialchars($cat['catalog_name']) ?></h4>
                        <div class="desc"><?= htmlspecialchars($cat['description'] ?: 'No description available.') ?></div>
                        <div class="catalog-actions">
                            <a href="/marketing/catalog_view.php?id=<?= $cat['id'] ?>" class="btn-view" target="_blank">View</a>
                            <?php if ($cat['file_path']): ?><a href="/<?= htmlspecialchars($cat['file_path']) ?>" class="btn-download" target="_blank" download>Download</a><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
