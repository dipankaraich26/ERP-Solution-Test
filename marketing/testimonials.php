<?php
include "../db.php";
include "../includes/dialog.php";

// Auto-create table
$pdo->exec("CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    testimonial_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('Photo','Video','Flier','Document') NOT NULL DEFAULT 'Photo',
    description TEXT,
    customer_name VARCHAR(255),
    customer_company VARCHAR(255),
    file_path VARCHAR(500),
    thumbnail_path VARCHAR(500),
    tags VARCHAR(500),
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delStmt = $pdo->prepare("SELECT file_path, thumbnail_path FROM testimonials WHERE id = ?");
    $delStmt->execute([$_POST['delete_id']]);
    $delItem = $delStmt->fetch();
    if ($delItem) {
        if ($delItem['file_path'] && file_exists("../" . $delItem['file_path'])) {
            unlink("../" . $delItem['file_path']);
        }
        if ($delItem['thumbnail_path'] && file_exists("../" . $delItem['thumbnail_path'])) {
            unlink("../" . $delItem['thumbnail_path']);
        }
        $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$_POST['delete_id']]);
        setModal("Deleted", "Testimonial deleted successfully.");
        header("Location: testimonials.php");
        exit;
    }
}

// Filters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($type) {
    $where[] = "type = ?";
    $params[] = $type;
}
if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}
if ($search) {
    $where[] = "(title LIKE ? OR customer_name LIKE ? OR customer_company LIKE ? OR tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);
$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn(),
    'photos' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE type = 'Photo'")->fetchColumn(),
    'videos' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE type = 'Video'")->fetchColumn(),
    'fliers' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE type = 'Flier'")->fetchColumn(),
    'docs' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE type = 'Document'")->fetchColumn(),
];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Testimonials - Marketing</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-box {
            background: var(--card, white); padding: 15px 20px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; min-width: 100px;
        }
        .stat-box .number { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .stat-box .label { color: #7f8c8d; font-size: 0.85em; }
        .stat-box.photo .number { color: #3498db; }
        .stat-box.video .number { color: #e74c3c; }
        .stat-box.flier .number { color: #f39c12; }
        .stat-box.doc .number { color: #27ae60; }

        .filters {
            display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;
        }
        .filters input, .filters select {
            padding: 8px 12px; border: 1px solid var(--border, #ddd); border-radius: 4px;
            background: var(--card, white); color: var(--text, #333);
        }

        .testi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .testi-card {
            background: var(--card, white); border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;
            transition: transform 0.2s;
        }
        .testi-card:hover { transform: translateY(-3px); }

        .testi-thumb {
            height: 180px; background: #f0f0f0; display: flex;
            align-items: center; justify-content: center; position: relative; overflow: hidden;
        }
        .testi-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .testi-thumb .placeholder { font-size: 3.5em; color: #bdc3c7; }
        .testi-thumb .video-play {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 50px; height: 50px; background: rgba(0,0,0,0.6); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.2em;
        }

        .testi-info { padding: 15px; }
        .testi-info h3 { margin: 0 0 6px 0; font-size: 1.05em; }
        .testi-info .meta { color: #7f8c8d; font-size: 0.85em; margin-bottom: 8px; }

        .type-badge {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: 0.75em; font-weight: 600; color: white;
        }
        .type-Photo { background: #3498db; }
        .type-Video { background: #e74c3c; }
        .type-Flier { background: #f39c12; }
        .type-Document { background: #27ae60; }

        .status-badge {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: 0.75em; font-weight: 600;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #fff3cd; color: #856404; }

        .testi-actions {
            padding: 10px 15px; border-top: 1px solid var(--border, #eee);
            display: flex; gap: 8px; align-items: center;
        }
        .testi-actions .btn { font-size: 0.85em; padding: 5px 10px; }
        .testi-tags { margin-top: 8px; }
        .testi-tags span {
            display: inline-block; background: #e8f4fd; color: #1976d2;
            padding: 1px 8px; border-radius: 10px; font-size: 0.75em; margin: 2px 2px;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Testimonials & Media</h1>

    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-box photo">
            <div class="number"><?= $stats['photos'] ?></div>
            <div class="label">Photos</div>
        </div>
        <div class="stat-box video">
            <div class="number"><?= $stats['videos'] ?></div>
            <div class="label">Videos</div>
        </div>
        <div class="stat-box flier">
            <div class="number"><?= $stats['fliers'] ?></div>
            <div class="label">Fliers</div>
        </div>
        <div class="stat-box doc">
            <div class="number"><?= $stats['docs'] ?></div>
            <div class="label">Documents</div>
        </div>
    </div>

    <div class="filters">
        <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width: 200px;">
            <select name="type">
                <option value="">All Types</option>
                <option value="Photo" <?= $type === 'Photo' ? 'selected' : '' ?>>Photo</option>
                <option value="Video" <?= $type === 'Video' ? 'selected' : '' ?>>Video</option>
                <option value="Flier" <?= $type === 'Flier' ? 'selected' : '' ?>>Flier</option>
                <option value="Document" <?= $type === 'Document' ? 'selected' : '' ?>>Document</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="testimonials.php" class="btn btn-secondary">Reset</a>
        </form>
        <div style="margin-left: auto;">
            <a href="testimonial_add.php" class="btn btn-success">+ Add Testimonial</a>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div style="text-align: center; padding: 60px; background: var(--card, white); border-radius: 10px;">
            <p style="color: #7f8c8d; font-size: 1.2em;">No testimonials found</p>
            <a href="testimonial_add.php" class="btn btn-success">Add Your First Testimonial</a>
        </div>
    <?php else: ?>
        <div class="testi-grid">
            <?php foreach ($items as $item): ?>
            <div class="testi-card">
                <div class="testi-thumb">
                    <?php
                    $filePath = $item['file_path'] ? "../" . $item['file_path'] : '';
                    $ext = $item['file_path'] ? strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) : '';
                    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                    $isVideo = in_array($ext, ['mp4','webm','mov','avi']);
                    $isPdf = $ext === 'pdf';
                    ?>
                    <?php if ($item['thumbnail_path']): ?>
                        <img src="../<?= htmlspecialchars($item['thumbnail_path']) ?>" alt="">
                    <?php elseif ($isImage): ?>
                        <img src="../<?= htmlspecialchars($item['file_path']) ?>" alt="">
                    <?php elseif ($isVideo): ?>
                        <span class="placeholder">&#127909;</span>
                        <div class="video-play">&#9654;</div>
                    <?php elseif ($isPdf): ?>
                        <span class="placeholder">&#128196;</span>
                    <?php else: ?>
                        <span class="placeholder">&#128206;</span>
                    <?php endif; ?>
                </div>
                <div class="testi-info">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <div class="meta">
                        <span class="type-badge type-<?= $item['type'] ?>"><?= $item['type'] ?></span>
                        <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                        <?php if ($item['customer_name']): ?>
                            &mdash; <?= htmlspecialchars($item['customer_name']) ?>
                        <?php endif; ?>
                        <?php if ($item['customer_company']): ?>
                            (<?= htmlspecialchars($item['customer_company']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php if ($item['description']): ?>
                        <p style="color:#666; font-size:0.9em; margin:0; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= htmlspecialchars($item['description']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($item['tags']): ?>
                        <div class="testi-tags">
                            <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                <span><?= htmlspecialchars(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="color:#999; font-size:0.8em; margin-top:6px;">
                        <?= date('d M Y', strtotime($item['created_at'])) ?>
                    </div>
                </div>
                <div class="testi-actions">
                    <a href="testimonial_view.php?id=<?= $item['id'] ?>" class="btn btn-sm">View</a>
                    <a href="testimonial_edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <form method="post" style="margin-left:auto;" onsubmit="return confirm('Delete this testimonial?');">
                        <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:#e74c3c; color:white; border:none; cursor:pointer;">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
