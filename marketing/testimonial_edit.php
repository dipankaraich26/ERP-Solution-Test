<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: testimonials.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) { header("Location: testimonials.php"); exit; }

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

    if (empty($errors)) {
        $file_path = $item['file_path'];
        $thumbnail_path = $item['thumbnail_path'];
        $uploadDir = "../uploads/testimonials/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Handle new file upload (optional on edit)
        if (!empty($_FILES['file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','gif','webp','mp4','webm','mov','avi','pdf','doc','docx'];

            if (!in_array($ext, $allowedExts)) {
                $errors[] = "File type not allowed.";
            } else {
                $fileName = $item['testimonial_code'] . '_' . time() . '.' . $ext;
                $fileName = str_replace(['/', '\\', ' '], '_', $fileName);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old file
                    if ($file_path && file_exists("../" . $file_path)) {
                        unlink("../" . $file_path);
                    }
                    $file_path = 'uploads/testimonials/' . $fileName;
                }
            }
        }

        // Handle new thumbnail
        if (!empty($_FILES['thumbnail']['name'])) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['jpg','jpeg','png','gif','webp'])) {
                $thumbName = $item['testimonial_code'] . '_thumb_' . time() . '.' . $thumbExt;
                $thumbName = str_replace(['/', '\\', ' '], '_', $thumbName);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbName)) {
                    if ($thumbnail_path && file_exists("../" . $thumbnail_path)) {
                        unlink("../" . $thumbnail_path);
                    }
                    $thumbnail_path = 'uploads/testimonials/' . $thumbName;
                }
            }
        }

        if (empty($errors)) {
            $updStmt = $pdo->prepare("
                UPDATE testimonials SET
                    title = ?, type = ?, description = ?, customer_name = ?,
                    customer_company = ?, file_path = ?, thumbnail_path = ?,
                    tags = ?, status = ?
                WHERE id = ?
            ");
            $updStmt->execute([
                $title, $type, $description ?: null, $customer_name ?: null,
                $customer_company ?: null, $file_path, $thumbnail_path,
                $tags ?: null, $status, $id
            ]);

            setModal("Updated", "Testimonial updated successfully!");
            header("Location: testimonial_view.php?id=$id");
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
    <title>Edit Testimonial - <?= htmlspecialchars($item['testimonial_code']) ?></title>
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
        .current-file {
            display: flex; align-items: center; gap: 10px; padding: 10px;
            background: var(--card, #f8f9fa); border: 1px solid var(--border, #ddd);
            border-radius: 6px; margin-bottom: 8px;
        }
        .current-file img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .current-file .file-info { font-size: 0.85em; color: #666; }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Testimonial - <?= htmlspecialchars($item['testimonial_code']) ?></h1>
        <p>
            <a href="testimonials.php" class="btn btn-secondary">Back to Testimonials</a>
            <a href="testimonial_view.php?id=<?= $id ?>" class="btn btn-secondary">View</a>
        </p>

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
                        <input type="text" name="title" required value="<?= htmlspecialchars($item['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="type">
                            <?php foreach (['Photo','Video','Flier','Document'] as $t): ?>
                                <option value="<?= $t ?>" <?= $item['type'] === $t ? 'selected' : '' ?>><?= $t === 'Flier' ? 'Flier / Marketing Material' : $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" value="<?= htmlspecialchars($item['customer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Customer / Company</label>
                        <input type="text" name="customer_company" value="<?= htmlspecialchars($item['customer_company'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active" <?= $item['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $item['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tags</label>
                        <input type="text" name="tags" value="<?= htmlspecialchars($item['tags'] ?? '') ?>" placeholder="comma separated">
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Media</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Current File</label>
                        <?php if ($item['file_path']): ?>
                            <?php
                            $ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                            $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                            ?>
                            <div class="current-file">
                                <?php if ($isImg): ?>
                                    <img src="../<?= htmlspecialchars($item['file_path']) ?>" alt="">
                                <?php endif; ?>
                                <div class="file-info">
                                    <?= basename($item['file_path']) ?>
                                    <br>
                                    <a href="../<?= htmlspecialchars($item['file_path']) ?>" target="_blank">View current file</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="color:#999;">No file uploaded</p>
                        <?php endif; ?>
                        <label style="margin-top:8px;">Replace File (optional)</label>
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.avi,.pdf,.doc,.docx">
                        <small style="color:#7f8c8d;">Leave empty to keep current file</small>
                    </div>
                    <div class="form-group">
                        <label>Current Thumbnail</label>
                        <?php if ($item['thumbnail_path']): ?>
                            <div class="current-file">
                                <img src="../<?= htmlspecialchars($item['thumbnail_path']) ?>" alt="">
                                <div class="file-info"><?= basename($item['thumbnail_path']) ?></div>
                            </div>
                        <?php else: ?>
                            <p style="color:#999;">No thumbnail</p>
                        <?php endif; ?>
                        <label style="margin-top:8px;">Replace Thumbnail (optional)</label>
                        <input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <small style="color:#7f8c8d;">Leave empty to keep current</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Update Testimonial</button>
            <a href="testimonial_view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
        </form>
    </div>
</div>

</body>
</html>
