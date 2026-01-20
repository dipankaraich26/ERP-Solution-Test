<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: catalogs.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM marketing_catalogs WHERE id = ?");
$stmt->execute([$id]);
$catalog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$catalog) {
    header("Location: catalogs.php");
    exit;
}

// Get campaigns using this catalog
$campaignStmt = $pdo->prepare("
    SELECT id, campaign_code, campaign_name, start_date, status
    FROM marketing_campaigns
    WHERE FIND_IN_SET(?, catalog_ids)
    ORDER BY start_date DESC
    LIMIT 5
");
$campaignStmt->execute([$id]);
$campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Catalog - <?= htmlspecialchars($catalog['catalog_code']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .catalog-view { max-width: 900px; }

        .catalog-header {
            display: flex;
            gap: 30px;
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .catalog-image {
            width: 250px;
            height: 200px;
            background: #f5f5f5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            color: #bdc3c7;
            flex-shrink: 0;
        }
        .catalog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        .catalog-main-info { flex: 1; }
        .catalog-main-info h1 { margin: 0 0 10px 0; color: #2c3e50; }
        .catalog-main-info .code { color: #7f8c8d; font-size: 1.1em; margin-bottom: 15px; }
        .catalog-main-info p { margin: 5px 0; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #fff3cd; color: #856404; }
        .status-Discontinued { background: #f8d7da; color: #721c24; }

        .info-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .info-section h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .info-section .content-area {
            padding: 20px;
        }
        .info-section p {
            white-space: pre-wrap;
            margin: 0;
            line-height: 1.6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item label { display: block; color: #7f8c8d; font-size: 0.85em; }
        .info-item .value { font-weight: 500; }

        .action-buttons { margin-bottom: 20px; }
        .action-buttons .btn { margin-right: 10px; }
    </style>
</head>
<body>

<div class="content">
    <div class="catalog-view">

        <div class="action-buttons">
            <a href="catalogs.php" class="btn btn-secondary">Back to Catalogs</a>
            <a href="catalog_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php if ($catalog['brochure_path']): ?>
                <a href="../<?= htmlspecialchars($catalog['brochure_path']) ?>" target="_blank" class="btn btn-secondary">Download Brochure</a>
            <?php endif; ?>
        </div>

        <div class="catalog-header">
            <div class="catalog-image">
                <?php if ($catalog['image_path']): ?>
                    <img src="../<?= htmlspecialchars($catalog['image_path']) ?>" alt="">
                <?php else: ?>
                    📦
                <?php endif; ?>
            </div>
            <div class="catalog-main-info">
                <div class="code"><?= htmlspecialchars($catalog['catalog_code']) ?></div>
                <h1><?= htmlspecialchars($catalog['catalog_name']) ?></h1>
                <?php if ($catalog['model_no']): ?>
                    <p><strong>Model:</strong> <?= htmlspecialchars($catalog['model_no']) ?></p>
                <?php endif; ?>
                <?php if ($catalog['category']): ?>
                    <p><strong>Category:</strong> <?= htmlspecialchars($catalog['category']) ?></p>
                <?php endif; ?>
                <p style="margin-top: 15px;">
                    <span class="status-badge status-<?= $catalog['status'] ?>"><?= $catalog['status'] ?></span>
                </p>
                <?php if ($catalog['target_audience']): ?>
                    <p style="margin-top: 15px;"><strong>Target:</strong> <?= htmlspecialchars($catalog['target_audience']) ?></p>
                <?php endif; ?>
                <?php if ($catalog['price_range']): ?>
                    <p><strong>Price Range:</strong> <?= htmlspecialchars($catalog['price_range']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($catalog['description']): ?>
        <div class="info-section">
            <h3>Description</h3>
            <div class="content-area">
                <p><?= nl2br(htmlspecialchars($catalog['description'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($catalog['specifications']): ?>
        <div class="info-section">
            <h3>Specifications</h3>
            <div class="content-area">
                <p><?= nl2br(htmlspecialchars($catalog['specifications'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($catalog['features']): ?>
        <div class="info-section">
            <h3>Features</h3>
            <div class="content-area">
                <p><?= nl2br(htmlspecialchars($catalog['features'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($campaigns)): ?>
        <div class="info-section">
            <h3>Recent Campaigns</h3>
            <div class="content-area">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #eee;">
                            <th style="text-align: left; padding: 8px;">Campaign</th>
                            <th style="text-align: left; padding: 8px;">Date</th>
                            <th style="text-align: left; padding: 8px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $c): ?>
                        <tr style="border-bottom: 1px solid #f5f5f5;">
                            <td style="padding: 8px;">
                                <a href="campaign_view.php?id=<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['campaign_name']) ?>
                                </a>
                            </td>
                            <td style="padding: 8px;"><?= date('d M Y', strtotime($c['start_date'])) ?></td>
                            <td style="padding: 8px;"><?= $c['status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
