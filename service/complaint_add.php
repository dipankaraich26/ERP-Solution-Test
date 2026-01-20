<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $complaint_description = trim($_POST['complaint_description'] ?? '');

    if ($customer_name === '') $errors[] = "Customer name is required";
    if ($customer_phone === '') $errors[] = "Customer phone is required";
    if ($complaint_description === '') $errors[] = "Complaint description is required";

    if (empty($errors)) {
        // Generate complaint number
        $year = date('Y');
        $month = date('m');
        $prefix = "SVC-$year$month-";
        $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(complaint_no, 13) AS UNSIGNED)) FROM service_complaints WHERE complaint_no LIKE '$prefix%'")->fetchColumn();
        $complaint_no = $prefix . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO service_complaints (
                complaint_no, customer_name, customer_phone, customer_email, customer_address,
                city, state_id, pincode,
                product_name, product_model, serial_number, purchase_date, warranty_status,
                issue_category_id, complaint_description, priority,
                assigned_technician_id, scheduled_visit_date, scheduled_visit_time,
                status, registered_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $assignedTech = $_POST['assigned_technician_id'] ?: null;
        $status = $assignedTech ? 'Assigned' : 'Open';

        $stmt->execute([
            $complaint_no,
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
            $assignedTech,
            $_POST['scheduled_visit_date'] ?: null,
            $_POST['scheduled_visit_time'] ?: null,
            $status
        ]);

        $newId = $pdo->lastInsertId();

        // Log status
        $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, new_status, remarks) VALUES (?, ?, 'Complaint registered')")->execute([$newId, $status]);

        setModal("Success", "Complaint '$complaint_no' registered successfully!");
        header("Location: complaint_view.php?id=$newId");
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
    <title>Register Complaint - Service</title>
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

        .priority-option { padding: 5px 0; }
        .priority-option input { margin-right: 10px; }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Register New Complaint</h1>
        <p><a href="complaints.php" class="btn btn-secondary">Back to Complaints</a></p>

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
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="customer_email">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="customer_address" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state_id">
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" maxlength="10">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Product Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="product_name">
                    </div>
                    <div class="form-group">
                        <label>Model Number</label>
                        <input type="text" name="product_model">
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number">
                    </div>
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date">
                    </div>
                    <div class="form-group">
                        <label>Warranty Status</label>
                        <select name="warranty_status">
                            <option value="Out of Warranty">Out of Warranty</option>
                            <option value="Under Warranty">Under Warranty</option>
                            <option value="AMC">AMC (Annual Maintenance Contract)</option>
                            <option value="Extended Warranty">Extended Warranty</option>
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
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Complaint Description *</label>
                        <textarea name="complaint_description" required placeholder="Describe the issue in detail..."></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Assignment (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Assign Technician</label>
                        <select name="assigned_technician_id">
                            <option value="">-- Assign Later --</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?> (<?= $tech['tech_code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Scheduled Visit Date</label>
                        <input type="date" name="scheduled_visit_date">
                    </div>
                    <div class="form-group">
                        <label>Scheduled Visit Time</label>
                        <input type="time" name="scheduled_visit_time">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Register Complaint</button>
            <a href="complaints.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
