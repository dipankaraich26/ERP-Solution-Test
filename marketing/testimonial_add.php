<?php
include "../db.php";
include "../includes/dialog.php";

// Ensure table exists
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'Photo';
    $description = trim($_POST['description'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_company = trim($_POST['customer_company'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if ($title === '') $errors[] = "Title is required";
    if (empty($_FILES['file']['name'])) $errors[] = "Please upload a file";

    if (empty($errors)) {
        // Generate code
        $maxId = $pdo->query("SELECT MAX(id) FROM testimonials")->fetchColumn();
        $testimonial_code = 'TM-' . date('Y') . '-' . str_pad(($maxId ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        // Handle file upload
        $file_path = null;
        $uploadDir = "../uploads/testimonials/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg','jpeg','png','gif','webp','mp4','webm','mov','avi','pdf','doc','docx'];

        if (!in_array($ext, $allowedExts)) {
            $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowedExts);
        } else {
            $fileName = $testimonial_code . '_' . time() . '.' . $ext;
            $fileName = str_replace(['/', '\\', ' '], '_', $fileName);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
                $file_path = 'uploads/testimonials/' . $fileName;
            } else {
                $errors[] = "Failed to upload file";
            }
        }

        // Handle optional thumbnail
        $thumbnail_path = null;
        if (!empty($_FILES['thumbnail']['name'])) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['jpg','jpeg','png','gif','webp'])) {
                $thumbName = $testimonial_code . '_thumb_' . time() . '.' . $thumbExt;
                $thumbName = str_replace(['/', '\\', ' '], '_', $thumbName);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbName)) {
                    $thumbnail_path = 'uploads/testimonials/' . $thumbName;
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO testimonials (testimonial_code, title, type, description, customer_name, customer_company, file_path, thumbnail_path, tags, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $testimonial_code, $title, $type,
                $description ?: null, $customer_name ?: null, $customer_company ?: null,
                $file_path, $thumbnail_path, $tags ?: null, $status,
                $_SESSION['user_id'] ?? null
            ]);

            setModal("Success", "Testimonial '$testimonial_code' created successfully!");
            header("Location: testimonials.php");
            exit;
        }
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Testimonial - Marketing</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 800px; }
        .form-section {
            background: var(--card, #fafafa); border: 1px solid var(--border, #ddd);
            border-radius: 8px; padding: 20px; margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0; padding-bottom: 10px;
            border-bottom: 2px solid #9b59b6; color: var(--text, #2c3e50);
        }
        .form-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid var(--border, #ccc);
            border-radius: 4px; box-sizing: border-box;
            background: var(--card, white); color: var(--text, #333);
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group.full-width { grid-column: 1 / -1; }
        .error-box {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }
        .file-hint { color: #7f8c8d; font-size: 0.85em; margin-top: 4px; }
        .preview-area {
            margin-top: 10px; max-width: 300px; max-height: 200px; overflow: hidden;
            border-radius: 8px; border: 1px solid #ddd; display: none;
        }
        .preview-area img { width: 100%; height: auto; }
        .preview-area video { width: 100%; height: auto; }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Add Testimonial</h1>
        <p><a href="testimonials.php" class="btn btn-secondary">Back to Testimonials</a></p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g., Customer Review - ABC Hospital">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="type" id="typeSelect" onchange="updateFileHint()">
                            <option value="Photo" <?= ($_POST['type'] ?? '') === 'Photo' ? 'selected' : '' ?>>Photo</option>
                            <option value="Video" <?= ($_POST['type'] ?? '') === 'Video' ? 'selected' : '' ?>>Video</option>
                            <option value="Flier" <?= ($_POST['type'] ?? '') === 'Flier' ? 'selected' : '' ?>>Flier / Marketing Material</option>
                            <option value="Document" <?= ($_POST['type'] ?? '') === 'Document' ? 'selected' : '' ?>>Document</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" placeholder="Name of the person">
                    </div>
                    <div class="form-group">
                        <label>Customer / Company</label>
                        <input type="text" name="customer_company" value="<?= htmlspecialchars($_POST['customer_company'] ?? '') ?>" placeholder="Company or organization">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tags</label>
                        <input type="text" name="tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>" placeholder="e.g., hospital, review, product-x (comma separated)">
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe the testimonial or media content..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Upload Media</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>File * <span id="fileHintLabel">(Photo)</span></label>
                        <input type="file" name="file" id="fileInput" required onchange="previewFile(this)">
                        <div class="file-hint" id="fileHint">Accepted: JPG, PNG, GIF, WebP</div>
                        <div class="preview-area" id="previewArea"></div>
                    </div>
                    <div class="form-group">
                        <label>Thumbnail (optional)</label>
                        <input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="previewThumb(this)">
                        <div class="file-hint">For videos/documents - a preview image. JPG, PNG, GIF</div>
                        <div class="preview-area" id="thumbPreview"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Create Testimonial</button>
            <a href="testimonials.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
        </form>
    </div>
</div>

<script>
function updateFileHint() {
    const type = document.getElementById('typeSelect').value;
    const hint = document.getElementById('fileHint');
    const label = document.getElementById('fileHintLabel');
    const fileInput = document.getElementById('fileInput');

    const hints = {
        'Photo': { text: 'Accepted: JPG, PNG, GIF, WebP', accept: '.jpg,.jpeg,.png,.gif,.webp', label: '(Photo)' },
        'Video': { text: 'Accepted: MP4, WebM, MOV, AVI', accept: '.mp4,.webm,.mov,.avi', label: '(Video)' },
        'Flier': { text: 'Accepted: JPG, PNG, PDF, GIF, WebP', accept: '.jpg,.jpeg,.png,.gif,.webp,.pdf', label: '(Flier/Marketing Material)' },
        'Document': { text: 'Accepted: PDF, DOC, DOCX, JPG, PNG', accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png', label: '(Document)' }
    };

    const h = hints[type] || hints['Photo'];
    hint.textContent = h.text;
    label.textContent = h.label;
    fileInput.accept = h.accept;
}

function previewFile(input) {
    const area = document.getElementById('previewArea');
    area.innerHTML = '';
    area.style.display = 'none';

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const ext = file.name.split('.').pop().toLowerCase();

        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            const reader = new FileReader();
            reader.onload = function(e) {
                area.innerHTML = '<img src="' + e.target.result + '">';
                area.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else if (['mp4','webm','mov'].includes(ext)) {
            const url = URL.createObjectURL(file);
            area.innerHTML = '<video src="' + url + '" controls muted></video>';
            area.style.display = 'block';
        }
    }
}

function previewThumb(input) {
    const area = document.getElementById('thumbPreview');
    area.innerHTML = '';
    area.style.display = 'none';

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            area.innerHTML = '<img src="' + e.target.result + '">';
            area.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

updateFileHint();
</script>

</body>
</html>
