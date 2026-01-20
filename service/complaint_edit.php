<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: complaints.php");
    exit;
}

// Fetch complaint
$stmt = $pdo->prepare("SELECT * FROM service_complaints WHERE id = ?");
$stmt->execute([$id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: complaints.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $complaint_description = trim($_POST['complaint_description'] ?? '');

    if ($customer_name === '') $errors[] = "Customer name is required";
    if ($customer_phone === '') $errors[] = "Customer phone is required";
    if ($complaint_description === '') $errors[] = "Complaint description is required";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE service_complaints SET
                customer_name = ?, customer_phone = ?, customer_email = ?, customer_address = ?,
                city = ?, state_id = ?, pincode = ?,
                product_name = ?, product_model = ?, serial_number = ?, purchase_date = ?, warranty_status = ?,
                issue_category_id = ?, complaint_description = ?, priority = ?,
                assigned_technician_id = ?, scheduled_visit_date = ?, scheduled_visit_time = ?,
                internal_notes = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $customer_name,
            $customer_phone,
            $_POST['customer_email'] ?: null,
            $_POST['customer_address'] ?: null,
            $_POST['city'] ?: null,
            $_POST['state_id'] ?: null,
            $_POST['pincode'] ?: null,
            $_POST['product_name'] ?: null,
            $_POST['product_model'] ?: null,
            $_POST['serial_number'] ?: null,
            $_POST['purchase_date'] ?: null,
            $_POST['warranty_status'] ?: 'Out of Warranty',
            $_POST['issue_category_id'] ?: null,
            $complaint_description,
            $_POST['priority'] ?? 'Medium',
            $_POST['assigned_technician_id'] ?: null,
            $_POST['scheduled_visit_date'] ?: null,
            $_POST['scheduled_visit_time'] ?: null,
            $_POST['internal_notes'] ?: null,
            $id
        ]);

        setModal("Success", "Complaint updated successfully!");
        header("Location: complaint_view.php?id=$id");
        exit;
    }
}

// Get options
$categories = $pdo->query("SELECT id, name FROM service_issue_categories WHERE is_active = 1 ORDER BY name")->fetchAll();
$technicians = $pdo->query("SELECT id, tech_code, name FROM service_technicians WHERE status = 'Active' ORDER BY name")->fetchAll();
$states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Complaint - <?= htmlspecialchars($complaint['complaint_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 900px; }
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
            border-bottom: 2px solid #e74c3c;
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

        .complaint-code {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Complaint</h1>
        <div class="complaint-code">Complaint #: <?= htmlspecialchars($complaint['complaint_no']) ?></div>
        <p>
            <a href="complaint_view.php?id=<?= $id ?>" class="btn btn-secondary">Back to Complaint</a>
            <a href="complaints.php" class="btn btn-secondary">All Complaints</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">

            <div class="form-section">
                <h3>Customer Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" required value="<?= htmlspecialchars($complaint['customer_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required value="<?= htmlspecialchars($complaint['customer_phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="customer_email" value="<?= htmlspecialchars($complaint['customer_email'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="customer_address" rows="2"><?= htmlspecialchars($complaint['customer_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($complaint['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state_id">
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $complaint['state_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" maxlength="10" value="<?= htmlspecialchars($complaint['pincode'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Product Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="product_name" value="<?= htmlspecialchars($complaint['product_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="product_model" value="<?= htmlspecialchars($complaint['product_model'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" value="<?= htmlspecialchars($complaint['serial_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date" value="<?= $complaint['purchase_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Warranty Status</label>
                        <select name="warranty_status">
                            <option value="Out of Warranty" <?= $complaint['warranty_status'] === 'Out of Warranty' ? 'selected' : '' ?>>Out of Warranty</option>
                            <option value="Under Warranty" <?= $complaint['warranty_status'] === 'Under Warranty' ? 'selected' : '' ?>>Under Warranty</option>
                            <option value="AMC" <?= $complaint['warranty_status'] === 'AMC' ? 'selected' : '' ?>>AMC (Annual Maintenance Contract)</option>
                            <option value="Extended Warranty" <?= $complaint['warranty_status'] === 'Extended Warranty' ? 'selected' : '' ?>>Extended Warranty</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Complaint Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Issue Category</label>
                        <select name="issue_category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $complaint['issue_category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="Low" <?= $complaint['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= $complaint['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= $complaint['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Critical" <?= $complaint['priority'] === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Complaint Description *</label>
                        <textarea name="complaint_description" required><?= htmlspecialchars($complaint['complaint_description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assign Technician</label>
                        <select name="assigned_technician_id">
                            <option value="">-- Not Assigned --</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" <?= $complaint['assigned_technician_id'] == $tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?> (<?= $tech['tech_code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Scheduled Visit Date</label>
                        <input type="date" name="scheduled_visit_date" value="<?= $complaint['scheduled_visit_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Scheduled Visit Time</label>
                        <input type="time" name="scheduled_visit_time" value="<?= $complaint['scheduled_visit_time'] ?? '' ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Internal Notes</h3>
                <div class="form-group">
                    <textarea name="internal_notes" rows="3" placeholder="Internal notes (not visible to customer)..."><?= htmlspecialchars($complaint['internal_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Save Changes</button>
            <a href="complaint_view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
