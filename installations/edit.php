<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch installation
$stmt = $pdo->prepare("SELECT * FROM installations WHERE id = ?");
$stmt->execute([$id]);
$installation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$installation) {
    setModal("Error", "Installation not found");
    header("Location: index.php");
    exit;
}

// Fetch customers
$customers = $pdo->query("
    SELECT id, customer_id, company_name, customer_name, contact, email, address1, address2, city, state, pincode
    FROM customers
    WHERE status = 'active'
    ORDER BY company_name, customer_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch internal engineers (employees)
$engineers = $pdo->query("
    SELECT id, emp_id, first_name, last_name, phone, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $installation_date = $_POST['installation_date'] ?? '';
    $installation_time = $_POST['installation_time'] ?? null;
    $engineer_type = $_POST['engineer_type'] ?? 'internal';
    $engineer_id = $engineer_type === 'internal' ? (int)($_POST['engineer_id'] ?? 0) : null;
    $external_engineer_name = $engineer_type === 'external' ? trim($_POST['external_engineer_name'] ?? '') : null;
    $external_engineer_phone = $engineer_type === 'external' ? trim($_POST['external_engineer_phone'] ?? '') : null;
    $external_engineer_company = $engineer_type === 'external' ? trim($_POST['external_engineer_company'] ?? '') : null;
    $site_address = trim($_POST['site_address'] ?? '');
    $site_contact_person = trim($_POST['site_contact_person'] ?? '');
    $site_contact_phone = trim($_POST['site_contact_phone'] ?? '');
    $installation_notes = trim($_POST['installation_notes'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';
    $completion_date = $_POST['completion_date'] ?? null;
    $customer_feedback = trim($_POST['customer_feedback'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0) ?: null;
    $customer_signature = isset($_POST['customer_signature']) ? 1 : 0;

    // Validation
    if (!$customer_id) {
        $error = "Please select a customer";
    } elseif (!$installation_date) {
        $error = "Installation date is required";
    } elseif ($engineer_type === 'internal' && !$engineer_id) {
        $error = "Please select an engineer";
    } elseif ($engineer_type === 'external' && !$external_engineer_name) {
        $error = "External engineer name is required";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE installations SET
                    customer_id = ?,
                    installation_date = ?,
                    installation_time = ?,
                    engineer_type = ?,
                    engineer_id = ?,
                    external_engineer_name = ?,
                    external_engineer_phone = ?,
                    external_engineer_company = ?,
                    site_address = ?,
                    site_contact_person = ?,
                    site_contact_phone = ?,
                    installation_notes = ?,
                    status = ?,
                    completion_date = ?,
                    customer_feedback = ?,
                    rating = ?,
                    customer_signature = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $customer_id,
                $installation_date,
                $installation_time ?: null,
                $engineer_type,
                $engineer_id,
                $external_engineer_name,
                $external_engineer_phone,
                $external_engineer_company,
                $site_address,
                $site_contact_person,
                $site_contact_phone,
                $installation_notes,
                $status,
                $completion_date ?: null,
                $customer_feedback,
                $rating,
                $customer_signature,
                $id
            ]);

            setModal("Success", "Installation updated successfully");
            header("Location: view.php?id=$id");
            exit;

        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Installation - <?= htmlspecialchars($installation['installation_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
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
            min-height: 80px;
            resize: vertical;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .engineer-type-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .engineer-type-toggle label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        .external-fields, .internal-fields {
            display: none;
        }
        .external-fields.active, .internal-fields.active {
            display: block;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .rating-input {
            display: flex;
            gap: 5px;
        }
        .rating-input input[type="radio"] {
            display: none;
        }
        .rating-input label {
            font-size: 1.5em;
            cursor: pointer;
            color: #ddd;
        }
        .rating-input label:hover,
        .rating-input label:hover ~ label,
        .rating-input input:checked ~ label {
            color: #f1c40f;
        }
        .rating-input {
            direction: rtl;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Edit Installation: <?= htmlspecialchars($installation['installation_no']) ?></h1>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <!-- Customer Section -->
        <div class="form-section">
            <h3>Customer Details</h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Select Customer *</label>
                    <select name="customer_id" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $installation['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_id']) ?> - <?= htmlspecialchars($c['company_name'] ?: $c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Installation Details -->
        <div class="form-section">
            <h3>Installation Details</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Installation Date *</label>
                    <input type="date" name="installation_date" required value="<?= htmlspecialchars($installation['installation_date']) ?>">
                </div>
                <div class="form-group">
                    <label>Installation Time</label>
                    <input type="time" name="installation_time" value="<?= htmlspecialchars($installation['installation_time'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="scheduled" <?= $installation['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="in_progress" <?= $installation['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $installation['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="on_hold" <?= $installation['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="cancelled" <?= $installation['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Completion Date</label>
                    <input type="date" name="completion_date" value="<?= htmlspecialchars($installation['completion_date'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- Engineer Assignment -->
        <div class="form-section">
            <h3>Engineer Assignment</h3>

            <div class="engineer-type-toggle">
                <label>
                    <input type="radio" name="engineer_type" value="internal"
                           <?= $installation['engineer_type'] === 'internal' ? 'checked' : '' ?>
                           onchange="toggleEngineerType()">
                    Internal Employee
                </label>
                <label>
                    <input type="radio" name="engineer_type" value="external"
                           <?= $installation['engineer_type'] === 'external' ? 'checked' : '' ?>
                           onchange="toggleEngineerType()">
                    External Engineer
                </label>
            </div>

            <div id="internalFields" class="internal-fields <?= $installation['engineer_type'] === 'internal' ? 'active' : '' ?>">
                <div class="form-group">
                    <label>Select Engineer *</label>
                    <select name="engineer_id" id="engineerSelect">
                        <option value="">-- Select Engineer --</option>
                        <?php foreach ($engineers as $eng): ?>
                            <option value="<?= $eng['id'] ?>" <?= $installation['engineer_id'] == $eng['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eng['emp_id']) ?> - <?= htmlspecialchars($eng['first_name'] . ' ' . $eng['last_name']) ?>
                                <?php if ($eng['department']): ?>(<?= htmlspecialchars($eng['department']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="externalFields" class="external-fields <?= $installation['engineer_type'] === 'external' ? 'active' : '' ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Engineer Name *</label>
                        <input type="text" name="external_engineer_name" value="<?= htmlspecialchars($installation['external_engineer_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="external_engineer_phone" value="<?= htmlspecialchars($installation['external_engineer_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="external_engineer_company" value="<?= htmlspecialchars($installation['external_engineer_company'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Site Details -->
        <div class="form-section">
            <h3>Site Details</h3>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Site Address</label>
                    <textarea name="site_address"><?= htmlspecialchars($installation['site_address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Site Contact Person</label>
                    <input type="text" name="site_contact_person" value="<?= htmlspecialchars($installation['site_contact_person'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Site Contact Phone</label>
                    <input type="text" name="site_contact_phone" value="<?= htmlspecialchars($installation['site_contact_phone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-section">
            <h3>Notes</h3>
            <div class="form-group">
                <label>Installation Notes</label>
                <textarea name="installation_notes"><?= htmlspecialchars($installation['installation_notes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Customer Feedback -->
        <div class="form-section">
            <h3>Customer Feedback</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating">
                        <option value="">No Rating</option>
                        <option value="1" <?= $installation['rating'] == 1 ? 'selected' : '' ?>>1 Star - Poor</option>
                        <option value="2" <?= $installation['rating'] == 2 ? 'selected' : '' ?>>2 Stars - Fair</option>
                        <option value="3" <?= $installation['rating'] == 3 ? 'selected' : '' ?>>3 Stars - Good</option>
                        <option value="4" <?= $installation['rating'] == 4 ? 'selected' : '' ?>>4 Stars - Very Good</option>
                        <option value="5" <?= $installation['rating'] == 5 ? 'selected' : '' ?>>5 Stars - Excellent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="customer_signature" <?= $installation['customer_signature'] ? 'checked' : '' ?>>
                        Customer Signature Obtained
                    </label>
                </div>
                <div class="form-group full-width">
                    <label>Customer Feedback</label>
                    <textarea name="customer_feedback"><?= htmlspecialchars($installation['customer_feedback'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-success">Update Installation</button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleEngineerType() {
    const type = document.querySelector('input[name="engineer_type"]:checked').value;
    const internalDiv = document.getElementById('internalFields');
    const externalDiv = document.getElementById('externalFields');
    const engineerSelect = document.getElementById('engineerSelect');

    if (type === 'internal') {
        internalDiv.classList.add('active');
        externalDiv.classList.remove('active');
        engineerSelect.required = true;
    } else {
        internalDiv.classList.remove('active');
        externalDiv.classList.add('active');
        engineerSelect.required = false;
    }
}
</script>

</body>
</html>
