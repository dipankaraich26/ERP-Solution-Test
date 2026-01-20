<?php
include "../db.php";
include "../includes/dialog.php";

// Fetch current settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle logo upload
    $logo_path = $settings['logo_path'] ?? null;
    if (!empty($_FILES['logo']['name'])) {
        $uploadDir = "../uploads/company/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

        if (in_array($ext, $allowedExts)) {
            $fileName = "logo_" . time() . "." . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $fullPath)) {
                // Delete old logo if exists
                if ($logo_path && file_exists("../" . $logo_path)) {
                    unlink("../" . $logo_path);
                }
                $logo_path = "uploads/company/" . $fileName;
            }
        }
    }

    // Update settings
    $stmt = $pdo->prepare("
        UPDATE company_settings SET
            company_name = ?,
            logo_path = ?,
            address_line1 = ?,
            address_line2 = ?,
            city = ?,
            state = ?,
            pincode = ?,
            country = ?,
            phone = ?,
            email = ?,
            website = ?,
            gstin = ?,
            pan = ?,
            bank_name = ?,
            bank_account = ?,
            bank_ifsc = ?,
            bank_branch = ?,
            terms_conditions = ?
        WHERE id = 1
    ");

    $stmt->execute([
        $_POST['company_name'],
        $logo_path,
        $_POST['address_line1'] ?: null,
        $_POST['address_line2'] ?: null,
        $_POST['city'] ?: null,
        $_POST['state'] ?: null,
        $_POST['pincode'] ?: null,
        $_POST['country'] ?: 'India',
        $_POST['phone'] ?: null,
        $_POST['email'] ?: null,
        $_POST['website'] ?: null,
        $_POST['gstin'] ?: null,
        $_POST['pan'] ?: null,
        $_POST['bank_name'] ?: null,
        $_POST['bank_account'] ?: null,
        $_POST['bank_ifsc'] ?: null,
        $_POST['bank_branch'] ?: null,
        $_POST['terms_conditions'] ?: null
    ]);

    setModal("Success", "Company settings updated successfully!");
    header("Location: settings.php");
    exit;
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Company Settings - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .settings-container { max-width: 900px; }
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
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }

        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            margin: 10px 0;
            border: 1px solid #ddd;
            padding: 5px;
            background: #fff;
        }
        .current-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="settings-container">
        <h1>Company Settings</h1>

        <p><a href="/" class="btn btn-secondary">Back to Dashboard</a></p>

        <form method="post" enctype="multipart/form-data">

            <!-- Company Info -->
            <div class="form-section">
                <h3>Company Information</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Company Name *</label>
                        <input type="text" name="company_name" required
                               value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Company Logo</label>
                        <?php if (!empty($settings['logo_path'])): ?>
                        <div class="current-logo">
                            <img src="../<?= htmlspecialchars($settings['logo_path']) ?>"
                                 alt="Current Logo" class="logo-preview">
                            <span>Current logo</span>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.svg">
                        <small style="color: #666;">Allowed: JPG, PNG, GIF, SVG. Recommended size: 300x100px</small>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 1</label>
                        <input type="text" name="address_line1"
                               value="<?= htmlspecialchars($settings['address_line1'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 2</label>
                        <input type="text" name="address_line2"
                               value="<?= htmlspecialchars($settings['address_line2'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city"
                               value="<?= htmlspecialchars($settings['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="state"
                               value="<?= htmlspecialchars($settings['state'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode"
                               value="<?= htmlspecialchars($settings['pincode'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country"
                               value="<?= htmlspecialchars($settings['country'] ?? 'India') ?>">
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="form-section">
                <h3>Contact Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone"
                               value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="text" name="website"
                               value="<?= htmlspecialchars($settings['website'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Tax & Legal -->
            <div class="form-section">
                <h3>Tax & Legal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input type="text" name="gstin"
                               value="<?= htmlspecialchars($settings['gstin'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>PAN</label>
                        <input type="text" name="pan"
                               value="<?= htmlspecialchars($settings['pan'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="form-section">
                <h3>Bank Details (for invoices)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name"
                               value="<?= htmlspecialchars($settings['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account"
                               value="<?= htmlspecialchars($settings['bank_account'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="bank_ifsc"
                               value="<?= htmlspecialchars($settings['bank_ifsc'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" name="bank_branch"
                               value="<?= htmlspecialchars($settings['bank_branch'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Default Terms -->
            <div class="form-section">
                <h3>Default Terms & Conditions</h3>
                <div class="form-group">
                    <label>Terms & Conditions (appears on quotes/invoices)</label>
                    <textarea name="terms_conditions" rows="6"><?= htmlspecialchars($settings['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                Save Settings
            </button>
        </form>
    </div>
</div>

</body>
</html>
