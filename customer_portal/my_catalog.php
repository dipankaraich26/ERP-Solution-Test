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
    $catStmt = $pdo->query("SELECT id, catalog_name, catalog_code, description, category, image_path, brochure_path FROM marketing_catalogs WHERE status = 'Active' ORDER BY category, catalog_name");
    $catalogs = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$catalogsByCategory = [];
foreach ($catalogs as $cat) {
    $category = $cat['category'] ?: 'General';
    if (!isset($catalogsByCategory[$category])) $catalogsByCategory[$category] = [];
    $catalogsByCategory[$category][] = $cat;
}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'includes/pwa_head.php'; ?>
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
        .catalog-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
        .catalog-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; transition: all 0.3s; }
        .catalog-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.15); }
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
            width: 60px;
            height: 70px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8em;
            flex-shrink: 0;
        }
        .catalog-pdf-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .catalog-info { flex: 1; }
        .catalog-info h4 { margin: 0 0 5px 0; color: #2c3e50; }
        .catalog-info .code { font-size: 0.85em; color: #7f8c8d; margin-bottom: 5px; }
        .catalog-info .desc { font-size: 0.9em; color: #495057; line-height: 1.4; }
        .catalog-actions { display: flex; gap: 10px; flex-shrink: 0; }
        .btn-pdf-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-pdf-download:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(231,76,60,0.4); }
        .no-pdf { color: #adb5bd; font-size: 0.9em; font-style: italic; }
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
</div>
<?php include 'includes/whatsapp_button.php'; ?>
<?php include 'includes/pwa_sw.php'; ?>
</body>
</html>
