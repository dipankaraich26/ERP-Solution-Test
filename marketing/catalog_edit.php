<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: catalogs.php");
    exit;
}

// Fetch catalog
$stmt = $pdo->prepare("SELECT * FROM marketing_catalogs WHERE id = ?");
$stmt->execute([$id]);
$catalog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$catalog) {
    header("Location: catalogs.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catalog_name = trim($_POST['catalog_name'] ?? '');

    if ($catalog_name === '') $errors[] = "Catalog name is required";

    if (empty($errors)) {
        // Handle image upload
        $image_path = $catalog['image_path'];
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = "../uploads/catalogs/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $fileName = $catalog['catalog_code'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old image if exists
                    if ($image_path && file_exists('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                    $image_path = 'uploads/catalogs/' . $fileName;
                }
            }
        }

        // Handle brochure upload
        $brochure_path = $catalog['brochure_path'];
        if (!empty($_FILES['brochure']['name'])) {
            $uploadDir = "../uploads/catalogs/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['brochure']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                $fileName = $catalog['catalog_code'] . '_brochure_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['brochure']['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old brochure if exists
                    if ($brochure_path && file_exists('../' . $brochure_path)) {
                        unlink('../' . $brochure_path);
                    }
                    $brochure_path = 'uploads/catalogs/' . $fileName;
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE marketing_catalogs SET
                catalog_name = ?, category = ?, model_no = ?, description = ?,
                specifications = ?, features = ?, target_audience = ?, price_range = ?,
                image_path = ?, brochure_path = ?, status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $catalog_name,
            $_POST['category'] ?: null,
            $_POST['model_no'] ?: null,
            $_POST['description'] ?: null,
            $_POST['specifications'] ?: null,
            $_POST['features'] ?: null,
            $_POST['target_audience'] ?: null,
            $_POST['price_range'] ?: null,
            $image_path,
            $brochure_path,
            $_POST['status'] ?? 'Active',
            $id
        ]);

        setModal("Success", "Catalog updated successfully!");
        header("Location: catalog_view.php?id=$id");
        exit;
    }
}

// Get categories
$categories = $pdo->query("SELECT name FROM catalog_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Catalog - <?= htmlspecialchars($catalog['catalog_code']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 800px; }
        .form-section {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #9b59b6;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }

        .catalog-code {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .current-file {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
            padding: 8px;
            background: #e8f4fd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .current-file img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Catalog</h1>
        <div class="catalog-code">Code: <?= htmlspecialchars($catalog['catalog_code']) ?></div>
        <p>
            <a href="catalog_view.php?id=<?= $id ?>" class="btn btn-secondary">Back to Catalog</a>
            <a href="catalogs.php" class="btn btn-secondary">All Catalogs</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Catalog Name *</label>
                        <input type="text" name="catalog_name" required value="<?= htmlspecialchars($catalog['catalog_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="model_no" value="<?= htmlspecialchars($catalog['model_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">-- Select --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $catalog['category'] === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active" <?= $catalog['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $catalog['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="Discontinued" <?= $catalog['status'] === 'Discontinued' ? 'selected' : '' ?>>Discontinued</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description of the product..."><?= htmlspecialchars($catalog['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Product Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Specifications</label>
                        <textarea name="specifications" placeholder="Technical specifications..."><?= htmlspecialchars($catalog['specifications'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Features</label>
                        <textarea name="features" placeholder="Key features and benefits..."><?= htmlspecialchars($catalog['features'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" value="<?= htmlspecialchars($catalog['target_audience'] ?? '') ?>" placeholder="e.g., Hospitals, Clinics, Labs">
                    </div>
                    <div class="form-group">
                        <label>Price Range</label>
                        <input type="text" name="price_range" value="<?= htmlspecialchars($catalog['price_range'] ?? '') ?>" placeholder="e.g., 50,000 - 1,00,000">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Media</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Image</label>
                        <?php if ($catalog['image_path']): ?>
                            <div class="current-file">
                                <img src="../<?= htmlspecialchars($catalog['image_path']) ?>" alt="">
                                <span>Current image uploaded</span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif" style="margin-top: 10px;">
                        <small style="color: #7f8c8d;">JPG, PNG or GIF (leave empty to keep current)</small>
                    </div>
                    <div class="form-group">
                        <label>Brochure/Datasheet</label>
                        <?php if ($catalog['brochure_path']): ?>
                            <div class="current-file">
                                <span>Current brochure: <a href="../<?= htmlspecialchars($catalog['brochure_path']) ?>" target="_blank">View</a></span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="brochure" accept=".pdf,.doc,.docx" style="margin-top: 10px;">
                        <small style="color: #7f8c8d;">PDF or DOC (leave empty to keep current)</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Save Changes</button>
            <a href="catalog_view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
