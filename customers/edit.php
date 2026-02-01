<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

$customer_id = $_GET['customer_id'] ?? null;
$errors = [];
$success_msg = '';

if (!$customer_id) {
    setModal("Failed to edit customer", "Customer not specified");
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id=?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    setModal("Failed to edit customer", "Customer not found");
    header("Location: index.php");
    exit;
}

// Check if customer_documents table exists
$documentsEnabled = false;
try {
    $pdo->query("SELECT 1 FROM customer_documents LIMIT 1");
    $documentsEnabled = true;
} catch (Exception $e) {
    $documentsEnabled = false;
}

// Handle document upload
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    if ($documentsEnabled && isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/customer_documents/';

        // Create directory if not exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $documentType = $_POST['document_type'] ?? 'Other';
        $remarks = $_POST['document_remarks'] ?? '';
        $originalName = $_FILES['document']['name'];
        $fileSize = $_FILES['document']['size'];

        // Check file size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "File size exceeds 10MB limit.";
        } else {
            // Generate unique filename
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];

            if (!in_array(strtolower($extension), $allowedExtensions)) {
                $errors[] = "Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF";
            } else {
                $newFileName = $customer_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $originalName);
                $filePath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO customer_documents (customer_id, document_type, document_name, file_path, file_size, remarks)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $customer_id,
                            $documentType,
                            $originalName,
                            'uploads/customer_documents/' . $newFileName,
                            $fileSize,
                            $remarks
                        ]);
                        $success_msg = "Document uploaded successfully!";
                    } catch (PDOException $e) {
                        $errors[] = "Failed to save document record: " . $e->getMessage();
                        unlink($filePath); // Remove uploaded file if DB insert fails
                    }
                } else {
                    $errors[] = "Failed to upload file.";
                }
            }
        }
    } elseif (!$documentsEnabled) {
        $errors[] = "Document storage is not configured. Please run admin/setup_customer_documents.php first.";
    }
}

// Handle document deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    $docId = $_POST['document_id'] ?? null;
    if ($docId && $documentsEnabled) {
        try {
            // Get file path before deleting record
            $stmt = $pdo->prepare("SELECT file_path FROM customer_documents WHERE id = ? AND customer_id = ?");
            $stmt->execute([$docId, $customer_id]);
            $doc = $stmt->fetch();

            if ($doc) {
                // Delete file
                $fullPath = dirname(__DIR__) . '/' . $doc['file_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                // Delete record
                $stmt = $pdo->prepare("DELETE FROM customer_documents WHERE id = ? AND customer_id = ?");
                $stmt->execute([$docId, $customer_id]);
                $success_msg = "Document deleted successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "Failed to delete document: " . $e->getMessage();
        }
    }
}

// Handle customer update
if ($_SERVER["REQUEST_METHOD"] === "POST" && (!isset($_POST['action']) || $_POST['action'] === 'update_customer')) {
    if (isset($_POST['company_name'])) {
        $stmt = $pdo->prepare("
            UPDATE customers
            SET company_name=?, designation=?, customer_name=?, contact=?, email=?, address1=?, address2=?, city=?, pincode=?, state=?, gstin=?, secondary_designation=?, secondary_contact_name=?, customer_type=?, industry=?
            WHERE customer_id=?
        ");
        $stmt->execute([
            $_POST['company_name'],
            $_POST['designation'] ?? null,
            $_POST['customer_name'],
            $_POST['contact'],
            $_POST['email'],
            $_POST['address1'],
            $_POST['address2'],
            $_POST['city'],
            $_POST['pincode'],
            $_POST['state'],
            $_POST['gstin'],
            $_POST['secondary_designation'] ?? null,
            $_POST['secondary_contact_name'] ?? null,
            $_POST['customer_type'] ?? 'B2B',
            $_POST['industry'] ?? null,
            $customer_id
        ]);

        setModal("Success", "Customer updated successfully!");
        header("Location: index.php");
        exit;
    }
}

// Fetch existing documents
$documents = [];
if ($documentsEnabled) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer_documents WHERE customer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$customer_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist
    }
}

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Customer</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .form-section h3 {
            margin: 0 0 18px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #667eea;
            color: #2c3e50;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section h3 .icon { font-size: 1.2em; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }
        .form-group label .required { color: #dc3545; margin-left: 2px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-size: 0.8em;
        }
        .form-group.full-width { grid-column: 1 / -1; }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .page-header h1 { margin: 0; color: #2c3e50; font-size: 1.6em; }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .customer-type-toggle { display: flex; gap: 10px; }
        .customer-type-toggle label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .customer-type-toggle label:hover { border-color: #667eea; background: #f8f9ff; }
        .customer-type-toggle input[type="radio"] { width: auto; margin: 0; }
        .customer-type-toggle input[type="radio"]:checked + span { color: #667eea; font-weight: 600; }
        .customer-type-toggle label:has(input:checked) { border-color: #667eea; background: #f0f4ff; }
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        body.dark .form-section { background: #2c3e50; border-color: #3d566e; }
        body.dark .form-section h3 { color: #ecf0f1; }
        body.dark .form-group label { color: #bdc3c7; }
        body.dark .form-group input, body.dark .form-group select {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .customer-type-toggle label { border-color: #4a6278; color: #ecf0f1; }
        body.dark .customer-type-toggle label:hover,
        body.dark .customer-type-toggle label:has(input:checked) { background: #3d566e; }
    </style>
</head>
<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}

function loadCities(stateName, selectedCity = null) {
    const citySelect = document.getElementById('city_select');

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select City --</option>';
        return;
    }

    citySelect.innerHTML = '<option value="">Loading cities...</option>';

    // Try primary API first
    fetch(`/api/get_cities.php?state=${encodeURIComponent(stateName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.cities && data.cities.length > 0) {
                populateCities(data.cities, selectedCity);
            } else {
                // Fall back to india_cities API
                fetchIndianCities(stateName, selectedCity);
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            fetchIndianCities(stateName, selectedCity);
        });
}

function fetchIndianCities(stateName, selectedCity = null) {
    const citySelect = document.getElementById('city_select');

    fetch(`/api/get_india_cities.php?state=${encodeURIComponent(stateName)}`)
        .then(response => response.json())
        .then(data => {
            if (Array.isArray(data) && data.length > 0) {
                populateCities(data, selectedCity);
            } else {
                citySelect.innerHTML = '<option value="">-- No cities available --</option>';
            }
        })
        .catch(error => {
            console.error('Error loading cities from india table:', error);
            citySelect.innerHTML = '<option value="">-- Error loading cities --</option>';
        });
}

function populateCities(cities, selectedCity = null) {
    const citySelect = document.getElementById('city_select');
    citySelect.innerHTML = '<option value="">-- Select City --</option>';

    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.city_name;
        option.textContent = city.city_name;
        if (selectedCity && city.city_name === selectedCity) {
            option.selected = true;
        }
        citySelect.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.querySelector('select[name="state"]');
    const currentCity = '<?= htmlspecialchars($customer['city'] ?? '') ?>';

    // Load cities for the current state on page load
    if (stateSelect.value) {
        loadCities(stateSelect.value, currentCity);
    }

    stateSelect.addEventListener('change', function() {
        loadCities(this.value);
    });
});
</script>
<body>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="form-container">
        <div class="page-header">
            <h1>Edit Customer</h1>
            <a href="index.php" class="btn btn-secondary">Back to Customers</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="success-box" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <!-- Customer Type Section -->
            <div class="form-section">
                <h3><span class="icon">🏢</span> Customer Classification</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer ID</label>
                        <input value="<?= htmlspecialchars($customer['customer_id']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Customer Type</label>
                        <div class="customer-type-toggle">
                            <label>
                                <input type="radio" name="customer_type" value="B2B" <?= ($customer['customer_type'] ?? 'B2B') === 'B2B' ? 'checked' : '' ?>>
                                <span>B2B (Business)</span>
                            </label>
                            <label>
                                <input type="radio" name="customer_type" value="B2C" <?= ($customer['customer_type'] ?? '') === 'B2C' ? 'checked' : '' ?>>
                                <span>B2C (Consumer)</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Industry / Sector</label>
                        <select name="industry">
                            <option value="">-- Select Industry --</option>
                            <?php
                            $industries = [
                                'Healthcare (B2B)' => ['Hospital', 'Multi-Specialty Hospital', 'Super-Specialty Hospital', 'Clinic', 'Polyclinic', 'Medical College', 'Nursing Home', 'Diagnostic Center', 'Pathology Lab', 'Imaging Center', 'Dental Clinic', 'Eye Hospital', 'IVF Center', 'Rehabilitation Center', 'Physiotherapy Center', 'Ayurveda / Wellness Center'],
                                'Medical Trade (B2B)' => ['Medical Equipment Dealer', 'Pharmaceutical Distributor', 'Pharmacy / Retail Chemist', 'Hospital Supply Chain', 'Lab Equipment Supplier', 'Surgical Instrument Dealer'],
                                'Manufacturing (B2B)' => ['Manufacturing', 'Medical Device Manufacturing', 'Pharmaceutical Manufacturing', 'Industrial Manufacturing', 'FMCG Manufacturing', 'Textile Manufacturing', 'Food Processing'],
                                'Retail & Trading (B2B/B2C)' => ['Retail', 'Wholesale Trading', 'E-commerce', 'Supermarket / Hypermarket', 'Franchise Business'],
                                'Services (B2B)' => ['IT / Software', 'Consulting', 'Finance / Banking', 'Insurance', 'Logistics', 'Telecom', 'Hospitality', 'Education', 'Training Institute'],
                                'Real Estate & Construction (B2B)' => ['Construction', 'Real Estate', 'Infrastructure', 'Interior Design'],
                                'Agriculture (B2B)' => ['Agriculture', 'Agri Business', 'Dairy / Poultry']
                            ];
                            $currentIndustry = $customer['industry'] ?? '';
                            foreach ($industries as $group => $items) {
                                echo "<optgroup label=\"$group\">";
                                foreach ($items as $ind) {
                                    $sel = ($currentIndustry === $ind) ? 'selected' : '';
                                    echo "<option value=\"$ind\" $sel>$ind</option>";
                                }
                                echo "</optgroup>";
                            }
                            ?>
                            <option value="Other" <?= $currentIndustry === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3><span class="icon">👤</span> Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name <span class="required">*</span></label>
                        <input name="company_name" value="<?= htmlspecialchars($customer['company_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Name <span class="required">*</span></label>
                        <input name="customer_name" value="<?= htmlspecialchars($customer['customer_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation">
                            <option value="">-- Select Designation --</option>
                            <?php
                            $designations = [
                                'Title' => ['Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Prof.'],
                                'Position' => ['Chairman', 'Managing Director', 'Director', 'Owner', 'Partner', 'CEO', 'CFO', 'COO', 'General Manager', 'Manager', 'Purchase Manager', 'Accountant', 'Administrator']
                            ];
                            $currentDesig = $customer['designation'] ?? '';
                            foreach ($designations as $group => $items) {
                                echo "<optgroup label=\"$group\">";
                                foreach ($items as $d) {
                                    $sel = ($currentDesig === $d) ? 'selected' : '';
                                    echo "<option value=\"$d\" $sel>$d</option>";
                                }
                                echo "</optgroup>";
                            }
                            ?>
                            <option value="Other" <?= $currentDesig === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact (Phone)</label>
                        <input name="contact" value="<?= htmlspecialchars($customer['contact'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input name="email" type="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Secondary Contact -->
            <div class="form-section">
                <h3><span class="icon">👥</span> Secondary Contact (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contact Person Name</label>
                        <input name="secondary_contact_name" value="<?= htmlspecialchars($customer['secondary_contact_name'] ?? '') ?>" placeholder="Secondary contact person name">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3><span class="icon">📍</span> Address</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Address Line 1</label>
                        <input name="address1" value="<?= htmlspecialchars($customer['address1'] ?? '') ?>" placeholder="Street address, building name">
                    </div>
                    <div class="form-group full-width">
                        <label>Address Line 2</label>
                        <input name="address2" value="<?= htmlspecialchars($customer['address2'] ?? '') ?>" placeholder="Area, landmark">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state">
                            <option value="">-- Select State --</option>
                            <?php
                            $states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
                            foreach ($states as $state) {
                                $selected = ($customer['state'] === $state['state_name']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($state['state_name']) . '" ' . $selected . '>' . htmlspecialchars($state['state_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <select id="city_select" name="city">
                            <option value="">-- Select City --</option>
                            <option value="<?= htmlspecialchars($customer['city'] ?? '') ?>" selected><?= htmlspecialchars($customer['city'] ?? '') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input name="pincode" value="<?= htmlspecialchars($customer['pincode'] ?? '') ?>" maxlength="10" placeholder="6-digit pincode">
                    </div>
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input name="gstin" value="<?= htmlspecialchars($customer['gstin'] ?? '') ?>" placeholder="15-character GSTIN" maxlength="15">
                        <small>Format: 22AAAAA0000A1Z5</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group">
                <button type="submit" class="btn-primary">Update Customer</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <?php if ($documentsEnabled): ?>
        <!-- Documents & Attachments Section -->
        <div class="form-section" style="margin-top: 30px;">
            <h3><span class="icon">📎</span> Documents & Attachments</h3>

            <!-- Upload Form -->
            <form method="post" enctype="multipart/form-data" style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <input type="hidden" name="action" value="upload_document">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Document Type <span class="required">*</span></label>
                        <select name="document_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Dealer Agreement">Dealer Agreement</option>
                            <option value="GST Certificate">GST Certificate</option>
                            <option value="PAN Card">PAN Card</option>
                            <option value="Trade License">Trade License</option>
                            <option value="TAN Certificate">TAN Certificate</option>
                            <option value="Bank Details">Bank Details</option>
                            <option value="Company Registration">Company Registration</option>
                            <option value="Address Proof">Address Proof</option>
                            <option value="Authorization Letter">Authorization Letter</option>
                            <option value="Purchase Order">Purchase Order</option>
                            <option value="Contract">Contract</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select File <span class="required">*</span></label>
                        <input type="file" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" style="padding: 8px;">
                        <small>Max 10MB. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF</small>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" name="document_remarks" placeholder="Optional notes about this document">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn-primary" style="padding: 10px 20px;">
                            Upload Document
                        </button>
                    </div>
                </div>
            </form>

            <!-- Existing Documents -->
            <?php if (!empty($documents)): ?>
            <h4 style="margin-bottom: 15px; color: #495057;">Uploaded Documents (<?= count($documents) ?>)</h4>
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #667eea; color: white;">
                        <th style="padding: 12px; text-align: left;">Type</th>
                        <th style="padding: 12px; text-align: left;">File Name</th>
                        <th style="padding: 12px; text-align: left;">Size</th>
                        <th style="padding: 12px; text-align: left;">Remarks</th>
                        <th style="padding: 12px; text-align: left;">Uploaded</th>
                        <th style="padding: 12px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr style="border-bottom: 1px solid #e9ecef;">
                        <td style="padding: 12px;">
                            <span style="background: #e3e8ff; color: #667eea; padding: 4px 10px; border-radius: 4px; font-size: 0.85em;">
                                <?= htmlspecialchars($doc['document_type']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px;">
                            <span title="<?= htmlspecialchars($doc['document_name']) ?>">
                                <?= htmlspecialchars(strlen($doc['document_name']) > 30 ? substr($doc['document_name'], 0, 30) . '...' : $doc['document_name']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; color: #6c757d;">
                            <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                        </td>
                        <td style="padding: 12px; color: #6c757d; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($doc['remarks'] ?? '') ?>">
                            <?= htmlspecialchars($doc['remarks'] ?? '-') ?>
                        </td>
                        <td style="padding: 12px; color: #6c757d; font-size: 0.9em;">
                            <?= date('d M Y', strtotime($doc['created_at'])) ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm" style="background: #28a745; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px;" title="Download">
                                Download
                            </a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer;" title="Delete">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 30px; color: #6c757d; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0;">No documents uploaded yet.</p>
                <small>Use the form above to attach documents to this customer.</small>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Documents Not Configured -->
        <div class="form-section" style="margin-top: 30px; background: #fff3cd; border-color: #ffc107;">
            <h3><span class="icon">📎</span> Documents & Attachments</h3>
            <p style="color: #856404; margin: 0;">
                Document storage is not configured.
                <a href="/admin/setup_customer_documents.php" style="color: #533f03; font-weight: 600;">Click here to set it up</a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
