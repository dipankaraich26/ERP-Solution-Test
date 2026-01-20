<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catalog_name = trim($_POST['catalog_name'] ?? '');
    $category = $_POST['category'] ?? '';

    if ($catalog_name === '') $errors[] = "Catalog name is required";

    if (empty($errors)) {
        // Generate catalog code
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $catalog_name), 0, 3));
        $maxCode = $pdo->query("SELECT MAX(CAST(SUBSTRING(catalog_code, 5) AS UNSIGNED)) FROM marketing_catalogs WHERE catalog_code LIKE 'CAT-%'")->fetchColumn();
        $catalog_code = 'CAT-' . str_pad(($maxCode ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        // Handle image upload
        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = "../uploads/catalogs/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $fileName = $catalog_code . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                    $image_path = 'uploads/catalogs/' . $fileName;
                }
            }
        }

        // Handle brochure upload
        $brochure_path = null;
        if (!empty($_FILES['brochure']['name'])) {
            $uploadDir = "../uploads/catalogs/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['brochure']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                $fileName = $catalog_code . '_brochure_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['brochure']['tmp_name'], $uploadDir . $fileName)) {
                    $brochure_path = 'uploads/catalogs/' . $fileName;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO marketing_catalogs (
                catalog_code, catalog_name, category, model_no, description,
                specifications, features, target_audience, price_range,
                image_path, brochure_path, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $catalog_code,
            $catalog_name,
            $category ?: null,
            $_POST['model_no'] ?: null,
            $_POST['description'] ?: null,
            $_POST['specifications'] ?: null,
            $_POST['features'] ?: null,
            $_POST['target_audience'] ?: null,
            $_POST['price_range'] ?: null,
            $image_path,
            $brochure_path,
            $_POST['status'] ?? 'Active'
        ]);

        setModal("Success", "Catalog '$catalog_code' created successfully!");
        header("Location: catalogs.php");
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
    <title>Add Catalog - Marketing</title>
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
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Add New Catalog</h1>
        <p><a href="catalogs.php" class="btn btn-secondary">Back to Catalogs</a></p>

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
                        <input type="text" name="catalog_name" required>
                    </div>
                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="model_no">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">-- Select --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description of the product..."></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Product Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Specifications</label>
                        <textarea name="specifications" placeholder="Technical specifications..."></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Features</label>
                        <textarea name="features" placeholder="Key features and benefits..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" placeholder="e.g., Hospitals, Clinics, Labs">
                    </div>
                    <div class="form-group">
                        <label>Price Range</label>
                        <input type="text" name="price_range" placeholder="e.g., 50,000 - 1,00,000">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Media</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif">
                        <small style="color: #7f8c8d;">JPG, PNG or GIF</small>
                    </div>
                    <div class="form-group">
                        <label>Brochure/Datasheet</label>
                        <input type="file" name="brochure" accept=".pdf,.doc,.docx">
                        <small style="color: #7f8c8d;">PDF or DOC</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Create Catalog</button>
            <a href="catalogs.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
