<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: testimonials.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) { header("Location: testimonials.php"); exit; }

// Determine file type
$ext = $item['file_path'] ? strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) : '';
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
$isVideo = in_array($ext, ['mp4','webm','mov','avi']);
$isPdf = $ext === 'pdf';
$isDoc = in_array($ext, ['doc','docx']);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Testimonial - <?= htmlspecialchars($item['testimonial_code']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .tview { max-width: 900px; }

        .action-buttons { margin-bottom: 20px; }
        .action-buttons .btn { margin-right: 10px; }

        .tview-header {
            background: var(--card, white); border-radius: 12px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px;
        }
        .tview-header h1 { margin: 0 0 10px 0; }
        .tview-meta { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
        .tview-meta .code { color: #7f8c8d; font-size: 1em; }

        .type-badge {
            display: inline-block; padding: 3px 12px; border-radius: 15px;
            font-size: 0.85em; font-weight: 600; color: white;
        }
        .type-Photo { background: #3498db; }
        .type-Video { background: #e74c3c; }
        .type-Flier { background: #f39c12; }
        .type-Document { background: #27ae60; }
        .status-badge {
            display: inline-block; padding: 3px 12px; border-radius: 15px;
            font-size: 0.85em; font-weight: 600;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #fff3cd; color: #856404; }

        .media-section {
            background: var(--card, white); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; overflow: hidden;
        }
        .media-section h3 {
            margin: 0; padding: 15px 20px; background: var(--card, #f8f9fa);
            border-bottom: 1px solid var(--border, #eee);
        }
        .media-content {
            padding: 20px; text-align: center;
        }
        .media-content img {
            max-width: 100%; max-height: 600px; border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .media-content video {
            max-width: 100%; max-height: 500px; border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .media-content iframe {
            width: 100%; height: 600px; border: none; border-radius: 8px;
        }
        .media-content .doc-download {
            display: inline-flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 15px 30px; border-radius: 10px;
            text-decoration: none; font-size: 1.1em; font-weight: 600;
            transition: transform 0.2s;
        }
        .media-content .doc-download:hover { transform: translateY(-2px); }

        .info-section {
            background: var(--card, white); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; overflow: hidden;
        }
        .info-section h3 {
            margin: 0; padding: 15px 20px; background: var(--card, #f8f9fa);
            border-bottom: 1px solid var(--border, #eee);
        }
        .info-section .content-area { padding: 20px; }

        .info-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;
        }
        .info-item label { display: block; color: #7f8c8d; font-size: 0.85em; margin-bottom: 3px; }
        .info-item .value { font-weight: 500; }

        .tag-list { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
        .tag-list span {
            background: #e8f4fd; color: #1976d2; padding: 3px 12px;
            border-radius: 15px; font-size: 0.85em;
        }

        /* Lightbox for image zoom */
        .lightbox {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9); z-index: 9999;
            align-items: center; justify-content: center; cursor: zoom-out;
        }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 95%; max-height: 95%; object-fit: contain; }
        .lightbox-close {
            position: absolute; top: 15px; right: 20px; color: white;
            font-size: 2em; cursor: pointer; z-index: 10000;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="tview">

        <div class="action-buttons">
            <a href="testimonials.php" class="btn btn-secondary">Back to Testimonials</a>
            <a href="testimonial_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php if ($item['file_path']): ?>
                <a href="../<?= htmlspecialchars($item['file_path']) ?>" download class="btn btn-success">Download File</a>
            <?php endif; ?>
        </div>

        <!-- Header -->
        <div class="tview-header">
            <div class="tview-meta">
                <span class="code"><?= htmlspecialchars($item['testimonial_code']) ?></span>
                <span class="type-badge type-<?= $item['type'] ?>"><?= $item['type'] ?></span>
                <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
            </div>
            <h1><?= htmlspecialchars($item['title']) ?></h1>
            <?php if ($item['customer_name'] || $item['customer_company']): ?>
                <p style="color:#666; margin:5px 0 0;">
                    <?php if ($item['customer_name']): ?>
                        <strong><?= htmlspecialchars($item['customer_name']) ?></strong>
                    <?php endif; ?>
                    <?php if ($item['customer_company']): ?>
                        &mdash; <?= htmlspecialchars($item['customer_company']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($item['tags']): ?>
                <div class="tag-list">
                    <?php foreach (explode(',', $item['tags']) as $tag): ?>
                        <span><?= htmlspecialchars(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p style="color:#999; font-size:0.85em; margin-top:10px;">
                Added on <?= date('d M Y, h:i A', strtotime($item['created_at'])) ?>
            </p>
        </div>

        <!-- Media Display -->
        <?php if ($item['file_path']): ?>
        <div class="media-section">
            <h3>Media</h3>
            <div class="media-content">
                <?php if ($isImage): ?>
                    <img src="../<?= htmlspecialchars($item['file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"
                         style="cursor: zoom-in;" onclick="openLightbox(this.src)">
                    <p style="color:#999; font-size:0.85em; margin-top:10px;">Click image to zoom</p>

                <?php elseif ($isVideo): ?>
                    <video controls style="width: 100%; max-height: 500px;"
                           <?php if ($item['thumbnail_path']): ?>poster="../<?= htmlspecialchars($item['thumbnail_path']) ?>"<?php endif; ?>>
                        <source src="../<?= htmlspecialchars($item['file_path']) ?>" type="video/<?= $ext === 'mov' ? 'quicktime' : $ext ?>">
                        Your browser does not support the video tag.
                    </video>

                <?php elseif ($isPdf): ?>
                    <iframe src="../<?= htmlspecialchars($item['file_path']) ?>" style="width:100%; height:600px;"></iframe>
                    <p style="margin-top:10px;">
                        <a href="../<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="doc-download">
                            Open PDF in New Tab
                        </a>
                    </p>

                <?php elseif ($isDoc): ?>
                    <div style="padding:40px;">
                        <p style="font-size:4em; margin-bottom:15px;">&#128196;</p>
                        <p style="color:#666; margin-bottom:20px;">This is a Word document. Download to view.</p>
                        <a href="../<?= htmlspecialchars($item['file_path']) ?>" download class="doc-download">
                            Download Document
                        </a>
                    </div>

                <?php else: ?>
                    <div style="padding:40px;">
                        <p style="font-size:4em; margin-bottom:15px;">&#128206;</p>
                        <a href="../<?= htmlspecialchars($item['file_path']) ?>" download class="doc-download">
                            Download File
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Thumbnail (if separate) -->
        <?php if ($item['thumbnail_path'] && !$isVideo): ?>
        <div class="info-section">
            <h3>Thumbnail</h3>
            <div class="content-area" style="text-align:center;">
                <img src="../<?= htmlspecialchars($item['thumbnail_path']) ?>" style="max-width:300px; border-radius:8px;">
            </div>
        </div>
        <?php endif; ?>

        <!-- Description -->
        <?php if ($item['description']): ?>
        <div class="info-section">
            <h3>Description</h3>
            <div class="content-area">
                <p style="white-space:pre-wrap; line-height:1.6;"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Details -->
        <div class="info-section">
            <h3>Details</h3>
            <div class="content-area">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Code</label>
                        <div class="value"><?= htmlspecialchars($item['testimonial_code']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Type</label>
                        <div class="value"><?= $item['type'] ?></div>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <div class="value"><?= $item['status'] ?></div>
                    </div>
                    <div class="info-item">
                        <label>File</label>
                        <div class="value"><?= $item['file_path'] ? basename($item['file_path']) : 'No file' ?></div>
                    </div>
                    <div class="info-item">
                        <label>Created</label>
                        <div class="value"><?= date('d M Y, h:i A', strtotime($item['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Updated</label>
                        <div class="value"><?= date('d M Y, h:i A', strtotime($item['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img id="lightboxImg" src="" alt="">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>

</body>
</html>
