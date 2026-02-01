<?php
include "../db.php";
include "../includes/dialog.php";

// Fetch current settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Fetch payment terms
$paymentTerms = [];
try {
    $paymentTerms = $pdo->query("SELECT * FROM payment_terms ORDER BY sort_order, term_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet - ignore
}

// Handle payment term actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_term_action'])) {
    $action = $_POST['payment_term_action'];

    if ($action === 'add') {
        $termName = trim($_POST['new_term_name'] ?? '');
        $termDesc = trim($_POST['new_term_description'] ?? '');
        $termDays = (int)($_POST['new_term_days'] ?? 0);

        if ($termName) {
            $maxSort = $pdo->query("SELECT IFNULL(MAX(sort_order), 0) + 1 FROM payment_terms")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO payment_terms (term_name, term_description, days, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$termName, $termDesc, $termDays, $maxSort]);
            setModal("Success", "Payment term added successfully!");
        }
        header("Location: settings.php#payment-terms");
        exit;
    }

    if ($action === 'delete' && isset($_POST['term_id'])) {
        $termId = (int)$_POST['term_id'];
        $stmt = $pdo->prepare("DELETE FROM payment_terms WHERE id = ?");
        $stmt->execute([$termId]);
        setModal("Success", "Payment term deleted!");
        header("Location: settings.php#payment-terms");
        exit;
    }

    if ($action === 'toggle' && isset($_POST['term_id'])) {
        $termId = (int)$_POST['term_id'];
        $stmt = $pdo->prepare("UPDATE payment_terms SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$termId]);
        header("Location: settings.php#payment-terms");
        exit;
    }

    if ($action === 'set_default' && isset($_POST['term_id'])) {
        $termId = (int)$_POST['term_id'];
        // Clear all defaults first
        $pdo->exec("UPDATE payment_terms SET is_default = 0");
        // Set new default
        $stmt = $pdo->prepare("UPDATE payment_terms SET is_default = 1 WHERE id = ?");
        $stmt->execute([$termId]);
        setModal("Success", "Default payment term updated!");
        header("Location: settings.php#payment-terms");
        exit;
    }

    if ($action === 'update' && isset($_POST['term_id'])) {
        $termId = (int)$_POST['term_id'];
        $termName = trim($_POST['edit_term_name'] ?? '');
        $termDesc = trim($_POST['edit_term_description'] ?? '');
        $termDays = (int)($_POST['edit_term_days'] ?? 0);

        if ($termName) {
            $stmt = $pdo->prepare("UPDATE payment_terms SET term_name = ?, term_description = ?, days = ? WHERE id = ?");
            $stmt->execute([$termName, $termDesc, $termDays, $termId]);
            setModal("Success", "Payment term updated!");
        }
        header("Location: settings.php#payment-terms");
        exit;
    }
}

// Handle Google Reviews form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_reviews_action'])) {
    // Check if columns exist, if not add them
    try {
        $pdo->query("SELECT google_review_rating FROM company_settings LIMIT 1");
    } catch (PDOException $e) {
        // Add Google review columns if they don't exist
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN google_review_rating DECIMAL(2,1) DEFAULT NULL");
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN google_review_count INT DEFAULT 0");
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN google_review_url VARCHAR(500) DEFAULT NULL");
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN google_place_id VARCHAR(100) DEFAULT NULL");
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN google_review_updated_at DATETIME DEFAULT NULL");
    }

    $stmt = $pdo->prepare("
        UPDATE company_settings SET
            google_review_rating = ?,
            google_review_count = ?,
            google_review_url = ?,
            google_place_id = ?,
            google_review_updated_at = NOW()
        WHERE id = 1
    ");
    $stmt->execute([
        $_POST['google_review_rating'] ?: null,
        (int)($_POST['google_review_count'] ?? 0),
        $_POST['google_review_url'] ?: null,
        $_POST['google_place_id'] ?: null
    ]);

    setModal("Success", "Google Reviews information updated!");
    header("Location: settings.php#google-reviews");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['payment_term_action']) && !isset($_POST['google_reviews_action'])) {

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

        /* Payment Terms Styles */
        .payment-terms-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .payment-terms-table th,
        .payment-terms-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .payment-terms-table th {
            background: #3498db;
            color: white;
        }
        .payment-terms-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .payment-terms-table .btn-sm {
            padding: 4px 8px;
            font-size: 0.85em;
            margin: 2px;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .badge-primary { background: #cce5ff; color: #004085; }
        .add-term-form {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .add-term-form h4 { margin-top: 0; color: #2c3e50; }
        .inline-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .inline-form .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 150px;
        }
        .inline-form .form-group label {
            font-size: 0.85em;
        }

        /* Edit Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h3 { margin-top: 0; }
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

        <!-- Google Reviews Section -->
        <div class="form-section" id="google-reviews" style="margin-top: 30px;">
            <h3>Google Reviews</h3>
            <p style="color: #666; margin-bottom: 15px;">
                Enter your Google Reviews information to display on the Executive Dashboard.
                You can update these manually or integrate with Google Places API later.
            </p>

            <form method="post">
                <input type="hidden" name="google_reviews_action" value="update">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Overall Rating (out of 5)</label>
                        <input type="number" name="google_review_rating" step="0.1" min="0" max="5"
                               value="<?= htmlspecialchars($settings['google_review_rating'] ?? '') ?>"
                               placeholder="e.g., 4.5">
                        <small style="color: #666;">Your Google rating (1.0 to 5.0)</small>
                    </div>
                    <div class="form-group">
                        <label>Total Number of Reviews</label>
                        <input type="number" name="google_review_count" min="0"
                               value="<?= htmlspecialchars($settings['google_review_count'] ?? 0) ?>"
                               placeholder="e.g., 125">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Google Reviews Page URL</label>
                        <input type="url" name="google_review_url"
                               value="<?= htmlspecialchars($settings['google_review_url'] ?? '') ?>"
                               placeholder="https://g.page/r/your-business/review">
                        <small style="color: #666;">Direct link to your Google reviews page</small>
                    </div>
                    <div class="form-group">
                        <label>Google Place ID (for future API)</label>
                        <input type="text" name="google_place_id"
                               value="<?= htmlspecialchars($settings['google_place_id'] ?? '') ?>"
                               placeholder="ChIJ...">
                        <small style="color: #666;">Optional - for Google Places API integration</small>
                    </div>
                    <?php if (!empty($settings['google_review_updated_at'])): ?>
                    <div class="form-group">
                        <label>Last Updated</label>
                        <p style="padding: 10px; background: #f5f5f5; border-radius: 4px; margin: 0;">
                            <?= date('d M Y, h:i A', strtotime($settings['google_review_updated_at'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-success" style="margin-top: 15px;">
                    Save Google Reviews Info
                </button>
            </form>

            <?php if (!empty($settings['google_review_rating'])): ?>
            <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #fbbc04;">
                <h4 style="margin: 0 0 10px 0;">Preview</h4>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 2.5em; font-weight: bold; color: #2c3e50;">
                        <?= number_format($settings['google_review_rating'], 1) ?>
                    </div>
                    <div>
                        <div style="color: #fbbc04; font-size: 1.5em;">
                            <?php
                            $rating = $settings['google_review_rating'];
                            $fullStars = floor($rating);
                            $halfStar = ($rating - $fullStars) >= 0.5;
                            for ($i = 0; $i < $fullStars; $i++) echo '★';
                            if ($halfStar) echo '☆';
                            for ($i = $fullStars + ($halfStar ? 1 : 0); $i < 5; $i++) echo '☆';
                            ?>
                        </div>
                        <div style="color: #666;">
                            <?= number_format($settings['google_review_count'] ?? 0) ?> Google reviews
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Payment Terms Section (separate from main form) -->
        <div class="form-section" id="payment-terms" style="margin-top: 30px;">
            <h3>Payment Terms & Conditions</h3>
            <p style="color: #666; margin-bottom: 15px;">
                Manage payment terms that can be selected in Quotations and Proforma Invoices.
                <?php if (empty($paymentTerms)): ?>
                    <br><a href="setup_payment_terms.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Setup Payment Terms</a>
                <?php endif; ?>
            </p>

            <?php if (!empty($paymentTerms)): ?>
            <table class="payment-terms-table">
                <thead>
                    <tr>
                        <th>Payment Term</th>
                        <th>Description</th>
                        <th style="width: 80px;">Days</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentTerms as $term): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($term['term_name']) ?>
                            <?php if ($term['is_default']): ?>
                                <span class="badge badge-primary">Default</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($term['term_description'] ?? '') ?></td>
                        <td><?= $term['days'] ?: '-' ?></td>
                        <td>
                            <?php if ($term['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="editTerm(<?= $term['id'] ?>, '<?= addslashes($term['term_name']) ?>', '<?= addslashes($term['term_description'] ?? '') ?>', <?= $term['days'] ?>)">Edit</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="payment_term_action" value="toggle">
                                <input type="hidden" name="term_id" value="<?= $term['id'] ?>">
                                <button type="submit" class="btn btn-<?= $term['is_active'] ? 'warning' : 'success' ?> btn-sm">
                                    <?= $term['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <?php if (!$term['is_default']): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="payment_term_action" value="set_default">
                                <input type="hidden" name="term_id" value="<?= $term['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm" title="Set as default">Default</button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this payment term?');">
                                <input type="hidden" name="payment_term_action" value="delete">
                                <input type="hidden" name="term_id" value="<?= $term['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Add New Payment Term -->
            <div class="add-term-form">
                <h4>Add New Payment Term</h4>
                <form method="post" class="inline-form">
                    <input type="hidden" name="payment_term_action" value="add">
                    <div class="form-group" style="flex: 2;">
                        <label>Term Name *</label>
                        <input type="text" name="new_term_name" required placeholder="e.g., Net 30 Days">
                    </div>
                    <div class="form-group" style="flex: 3;">
                        <label>Description</label>
                        <input type="text" name="new_term_description" placeholder="e.g., Payment due within 30 days">
                    </div>
                    <div class="form-group" style="flex: 0.5; min-width: 80px;">
                        <label>Days</label>
                        <input type="number" name="new_term_days" value="0" min="0">
                    </div>
                    <div class="form-group" style="flex: 0; min-width: auto;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-success">Add Term</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Term Modal -->
<div class="modal-overlay" id="editTermModal">
    <div class="modal-content">
        <h3>Edit Payment Term</h3>
        <form method="post">
            <input type="hidden" name="payment_term_action" value="update">
            <input type="hidden" name="term_id" id="edit_term_id">
            <div class="form-group">
                <label>Term Name *</label>
                <input type="text" name="edit_term_name" id="edit_term_name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="edit_term_description" id="edit_term_description">
            </div>
            <div class="form-group">
                <label>Days</label>
                <input type="number" name="edit_term_days" id="edit_term_days" value="0" min="0">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTerm(id, name, description, days) {
    document.getElementById('edit_term_id').value = id;
    document.getElementById('edit_term_name').value = name;
    document.getElementById('edit_term_description').value = description;
    document.getElementById('edit_term_days').value = days;
    document.getElementById('editTermModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editTermModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editTermModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

</body>
</html>
