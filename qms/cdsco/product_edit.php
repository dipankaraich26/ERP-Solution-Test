<?php
include "../../db.php";
include "../../includes/sidebar.php";

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Fetch existing product
$stmt = $pdo->prepare("SELECT * FROM qms_cdsco_products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php?error=notfound");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $risk_classification = trim($_POST['risk_classification'] ?? '');
    $intended_use = trim($_POST['intended_use'] ?? '');
    $registration_no = trim($_POST['registration_no'] ?? '');
    $registration_status = trim($_POST['registration_status'] ?? 'Pending');
    $application_date = trim($_POST['application_date'] ?? '');
    $approval_date = trim($_POST['approval_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($product_name) || empty($category) || empty($risk_classification)) {
        $error = "Product name, category, and risk classification are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE qms_cdsco_products SET
                product_name = ?, category = ?, risk_classification = ?, intended_use = ?,
                registration_no = ?, registration_status = ?, application_date = ?,
                approval_date = ?, expiry_date = ?, remarks = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $product_name,
                $category,
                $risk_classification,
                $intended_use,
                $registration_no ?: null,
                $registration_status,
                $application_date ?: null,
                $approval_date ?: null,
                $expiry_date ?: null,
                $remarks,
                $id
            ]);

            header("Location: products.php?success=updated");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating product: " . $e->getMessage();
        }
    }
} else {
    $_POST = $product;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit CDSCO Product - QMS</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Edit CDSCO Product</h1>
        <a href="products.php" class="btn btn-secondary">← Back to Products</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Product Name</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="required">Category</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        $categories = ['Diagnostic Equipment', 'Therapeutic Equipment', 'Surgical Instruments',
                                      'Implants', 'IVD (In Vitro Diagnostic)', 'Consumables',
                                      'Software as Medical Device', 'Other'];
                        foreach ($categories as $cat):
                        ?>
                        <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Risk Classification</label>
                    <select name="risk_classification" required>
                        <option value="">Select Risk Class</option>
                        <option value="A" <?= ($_POST['risk_classification'] ?? '') === 'A' ? 'selected' : '' ?>>Class A - Low Risk</option>
                        <option value="B" <?= ($_POST['risk_classification'] ?? '') === 'B' ? 'selected' : '' ?>>Class B - Low-Moderate Risk</option>
                        <option value="C" <?= ($_POST['risk_classification'] ?? '') === 'C' ? 'selected' : '' ?>>Class C - Moderate-High Risk</option>
                        <option value="D" <?= ($_POST['risk_classification'] ?? '') === 'D' ? 'selected' : '' ?>>Class D - High Risk</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Registration Status</label>
                    <select name="registration_status">
                        <?php
                        $statuses = ['Pending', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Expired'];
                        foreach ($statuses as $stat):
                        ?>
                        <option value="<?= $stat ?>" <?= ($_POST['registration_status'] ?? '') === $stat ? 'selected' : '' ?>><?= $stat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Intended Use / Indications</label>
                <textarea name="intended_use" placeholder="Describe the intended use..."><?= htmlspecialchars($_POST['intended_use'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>CDSCO Registration Number</label>
                    <input type="text" name="registration_no" placeholder="e.g., MD-XXXX-YYYY"
                           value="<?= htmlspecialchars($_POST['registration_no'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Application Date</label>
                    <input type="date" name="application_date"
                           value="<?= htmlspecialchars($_POST['application_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Approval Date</label>
                    <input type="date" name="approval_date"
                           value="<?= htmlspecialchars($_POST['approval_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date"
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Remarks / Notes</label>
                <textarea name="remarks"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="product_view.php?id=<?= $id ?>" class="btn btn-secondary">View Details</a>
                <a href="products.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
