<?php
include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Get current user info
$currentUserId = getUserId();
$currentUserRole = getUserRole();
$isAdmin = ($currentUserRole === 'admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch lead
$stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    header("Location: index.php");
    exit;
}

// Auto-populate missing fields from customers table if empty
if (!empty($lead['phone'])) {
    try {
        $customerStmt = $pdo->prepare("
            SELECT designation, industry, state, city, address1, address2, pincode
            FROM customers
            WHERE contact = ?
            LIMIT 1
        ");
        $customerStmt->execute([$lead['phone']]);
        $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);

        if ($customerData) {
            $updateFields = [];
            $updateValues = [];

            // Auto-populate designation
            if (empty($lead['designation']) && !empty($customerData['designation'])) {
                $updateFields[] = "designation = ?";
                $updateValues[] = $customerData['designation'];
                $lead['designation'] = $customerData['designation'];
            }

            // Auto-populate industry
            if (empty($lead['industry']) && !empty($customerData['industry'])) {
                $updateFields[] = "industry = ?";
                $updateValues[] = $customerData['industry'];
                $lead['industry'] = $customerData['industry'];
            }

            // Auto-populate state
            if (empty($lead['state']) && !empty($customerData['state'])) {
                $updateFields[] = "state = ?";
                $updateValues[] = $customerData['state'];
                $lead['state'] = $customerData['state'];
            }

            // Auto-populate city
            if (empty($lead['city']) && !empty($customerData['city'])) {
                $updateFields[] = "city = ?";
                $updateValues[] = $customerData['city'];
                $lead['city'] = $customerData['city'];
            }

            // Auto-populate address1
            if (empty($lead['address1']) && !empty($customerData['address1'])) {
                $updateFields[] = "address1 = ?";
                $updateValues[] = $customerData['address1'];
                $lead['address1'] = $customerData['address1'];
            }

            // Auto-populate address2
            if (empty($lead['address2']) && !empty($customerData['address2'])) {
                $updateFields[] = "address2 = ?";
                $updateValues[] = $customerData['address2'];
                $lead['address2'] = $customerData['address2'];
            }

            // Auto-populate pincode
            if (empty($lead['pincode']) && !empty($customerData['pincode'])) {
                $updateFields[] = "pincode = ?";
                $updateValues[] = $customerData['pincode'];
                $lead['pincode'] = $customerData['pincode'];
            }

            // Update the lead record with customer data
            if (!empty($updateFields)) {
                $updateValues[] = $id;
                $updateSql = "UPDATE crm_leads SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $pdo->prepare($updateSql)->execute($updateValues);
            }
        }
    } catch (PDOException $e) {
        // Silently continue if customer lookup fails
    }
}

// Access control: Any logged-in user can edit leads
// Note: assigned_user_id is from employees table, not users table
// For stricter control, link users to employees via employee_id column in users table

// Fetch states for dropdown
$states = [];
try {
    $states = $pdo->query("SELECT id, state_name FROM states WHERE is_active = 1 ORDER BY state_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
}

// Fetch cities for current state
$cities = [];
if (!empty($lead['state']) && !empty($states)) {
    try {
        $cityStmt = $pdo->prepare("
            SELECT c.city_name FROM cities c
            JOIN states s ON c.state_id = s.id
            WHERE s.state_name = ? AND c.is_active = 1
            ORDER BY c.city_name
        ");
        $cityStmt->execute([$lead['state']]);
        $cities = $cityStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Table may not exist
    }
}

$errors = [];
$customerAddSuccess = false;
$customerAddError = '';
$existingCustomerId = null;

// Check if customer already exists with same phone number
$leadPhone = $lead['phone'] ?? '';
if (!empty($leadPhone)) {
    try {
        $existingCustomerStmt = $pdo->prepare("SELECT customer_id, company_name, customer_name FROM customers WHERE contact = ? LIMIT 1");
        $existingCustomerStmt->execute([$leadPhone]);
        $existingCustomer = $existingCustomerStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingCustomer) {
            $existingCustomerId = $existingCustomer['customer_id'];
        }
    } catch (PDOException $e) {
        // Ignore errors
    }
}

// Handle "Add to Customers" request
if (isset($_POST['add_to_customers']) && $_POST['add_to_customers'] === '1') {
    $phone = trim($lead['phone'] ?? '');

    if (empty($phone)) {
        $customerAddError = "Cannot add to customers: Lead has no phone number.";
    } else {
        // Check if customer with this phone already exists
        try {
            $checkStmt = $pdo->prepare("SELECT customer_id, company_name, customer_name FROM customers WHERE contact = ? LIMIT 1");
            $checkStmt->execute([$phone]);
            $existingCust = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingCust) {
                $customerAddError = "A customer with this phone number already exists: " .
                    htmlspecialchars($existingCust['company_name'] ?: $existingCust['customer_name']) .
                    " (ID: " . $existingCust['customer_id'] . ")";
                $existingCustomerId = $existingCust['customer_id'];
            } else {
                // Generate new customer_id (same format as customers/add.php)
                $lastIdStmt = $pdo->query("
                    SELECT MAX(CAST(SUBSTRING(customer_id, 6) AS UNSIGNED)) as max_id
                    FROM customers
                    WHERE customer_id LIKE 'CUST-%'
                ");
                $lastId = $lastIdStmt->fetch()['max_id'] ?? 0;
                $newCustomerId = 'CUST-' . ($lastId + 1);

                // Insert the lead as a new customer
                $insertStmt = $pdo->prepare("
                    INSERT INTO customers (
                        customer_id, customer_type, company_name, customer_name, designation,
                        contact, email, address1, address2, city, state, pincode,
                        industry, gstin, status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, 'Active', NOW()
                    )
                ");

                $insertStmt->execute([
                    $newCustomerId,
                    $lead['customer_type'] ?? 'B2B',
                    $lead['company_name'] ?: null,
                    $lead['contact_person'] ?: null,
                    $lead['designation'] ?: null,
                    $lead['phone'],
                    $lead['email'] ?: null,
                    $lead['address1'] ?: null,
                    $lead['address2'] ?: null,
                    $lead['city'] ?: null,
                    $lead['state'] ?: null,
                    $lead['pincode'] ?: null,
                    $lead['industry'] ?: null,
                    null // GSTIN - not available in leads
                ]);

                $customerAddSuccess = true;
                $existingCustomerId = $newCustomerId;

                // Refresh lead data
                $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
                $stmt->execute([$id]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $customerAddError = "Failed to add customer: " . $e->getMessage();
        }
    }
}

// Check if this lead's address came from existing customer database
// by checking if there's a matching customer with same phone number and address
$isFromCustomerDB = false;
$phone = $lead['phone'] ?? '';

if (!empty($phone)) {
    try {
        $customerStmt = $pdo->prepare("
            SELECT id, customer_id FROM customers
            WHERE contact = ?
            AND address1 = ?
            AND city = ?
            AND state = ?
            LIMIT 1
        ");
        $customerStmt->execute([
            $phone,
            $lead['address1'] ?? '',
            $lead['city'] ?? '',
            $lead['state'] ?? ''
        ]);
        $matchingCustomer = $customerStmt->fetch();

        if ($matchingCustomer) {
            $isFromCustomerDB = true;
        }
    } catch (PDOException $e) {
        // customers table might not exist or query error
    }
}

// Address editing rules:
// 1. If address came from existing customer database -> ALWAYS read-only (cannot be edited)
// 2. If address is new in CRM -> Can edit only for Cold/Warm leads, read-only for Hot/Converted/Lost
$allowAddressEdit = !$isFromCustomerDB && in_array($lead['lead_status'], ['cold', 'warm']);
$isRestricted = $isFromCustomerDB || in_array($lead['lead_status'], ['hot', 'converted', 'lost']);

// ===========================================
// CHECK IF LEAD HAS A RELEASED PI
// This determines if "Converted" status option should be available
// ===========================================
$hasReleasedPI = false;
$releasedPINo = null;
try {
    $piExistsStmt = $pdo->prepare("
        SELECT pi_no
        FROM quote_master
        WHERE reference = ? AND status = 'released'
        LIMIT 1
    ");
    $piExistsStmt->execute([$lead['lead_no']]);
    $piExists = $piExistsStmt->fetch(PDO::FETCH_ASSOC);
    if ($piExists) {
        $hasReleasedPI = true;
        $releasedPINo = $piExists['pi_no'];
    }
} catch (Exception $e) {
    // Silently continue if query fails
}

// ===========================================
// CHECK IF LEAD STATUS IS LOCKED
// Two scenarios prevent status change:
// 1. Converted lead + Released Invoice → cannot change status
// 2. Hot lead + Released PI → cannot change status
// ===========================================
$statusLocked = false;
$lockReason = '';
$lockDocNo = null;

// Scenario 1: Converted lead with released invoice
if (strtolower($lead['lead_status']) === 'converted') {
    try {
        $invoiceCheckStmt = $pdo->prepare("
            SELECT im.invoice_no
            FROM invoice_master im
            JOIN sales_orders so ON so.so_no = im.so_no
            JOIN quote_master q ON q.id = so.linked_quote_id
            WHERE q.reference = ? AND im.status = 'released'
            LIMIT 1
        ");
        $invoiceCheckStmt->execute([$lead['lead_no']]);
        $releasedInvoice = $invoiceCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($releasedInvoice) {
            $statusLocked = true;
            $lockReason = 'invoice';
            $lockDocNo = $releasedInvoice['invoice_no'];
        }
    } catch (Exception $e) {
        // Silently continue if query fails
    }
}

// Scenario 2: Hot lead with released PI
if (strtolower($lead['lead_status']) === 'hot') {
    try {
        $piCheckStmt = $pdo->prepare("
            SELECT pi_no
            FROM quote_master
            WHERE reference = ? AND status = 'released'
            LIMIT 1
        ");
        $piCheckStmt->execute([$lead['lead_no']]);
        $releasedPI = $piCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($releasedPI) {
            $statusLocked = true;
            $lockReason = 'pi';
            $lockDocNo = $releasedPI['pi_no'];
        }
    } catch (Exception $e) {
        // Silently continue if query fails
    }
}

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic info
    $customer_type = $_POST['customer_type'] ?? 'B2B';
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Address
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $country = trim($_POST['country'] ?? 'India');

    // Lead details
    $lead_status = $_POST['lead_status'] ?? 'cold';
    $lead_source = trim($_POST['lead_source'] ?? '');
    $market_classification = trim($_POST['market_classification'] ?? '');
    $industry = trim($_POST['industry'] ?? '');

    // Buying intent
    $buying_timeline = $_POST['buying_timeline'] ?? 'uncertain';
    $budget_range = trim($_POST['budget_range'] ?? '');
    $decision_maker = $_POST['decision_maker'] ?? 'no';

    // Follow-up & notes
    $next_followup_date = $_POST['next_followup_date'] ?? '';

    // Server-side enforcement: non-admin cannot change next_followup_date if it was set via interaction
    if (!$isAdmin && !empty($lead['next_followup_date'])) {
        try {
            $fupLockCheck = $pdo->prepare("
                SELECT id FROM crm_lead_interactions
                WHERE lead_id = ? AND next_action_date = ?
                LIMIT 1
            ");
            $fupLockCheck->execute([$id, $lead['next_followup_date']]);
            if ($fupLockCheck->fetch()) {
                // Force keep the original date - non-admin cannot change it
                $next_followup_date = $lead['next_followup_date'];
            }
        } catch (PDOException $e) {}
    }

    $assigned_user_id = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
    $assigned_to = null;

    // Fetch assigned person name from employees table
    if ($assigned_user_id) {
        $empStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE id = ?");
        $empStmt->execute([$assigned_user_id]);
        $empData = $empStmt->fetch();
        $assigned_to = $empData ? $empData['full_name'] : null;
    }

    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($contact_person === '') {
        $errors[] = "Contact person name is required";
    }
    if ($phone === '') {
        $errors[] = "Contact number is required";
    }
    if ($customer_type === 'B2B' && $company_name === '') {
        $errors[] = "Company name is required for B2B leads";
    }
    if (!$assigned_user_id) {
        $errors[] = "Assigning to a person is mandatory";
    }

    // Prevent status change if locked (released invoice for converted, or released PI for hot)
    // Rule 1: CONVERTED + Released Invoice = Fully locked (no changes)
    // Rule 2: HOT + Released PI = Can only change to CONVERTED (to enable invoice release)
    if ($statusLocked && strtolower($lead_status) !== strtolower($lead['lead_status'])) {
        if ($lockReason === 'invoice') {
            // Fully locked - no changes allowed
            $errors[] = "Cannot change status from 'Converted' because Invoice " . $lockDocNo . " has already been released for this lead.";
        } else if ($lockReason === 'pi') {
            // HOT with released PI - only allow change to CONVERTED
            if (strtolower($lead_status) !== 'converted') {
                $errors[] = "With a released PI (" . $lockDocNo . "), you can only change status to 'Converted'. Other status changes are not allowed.";
            }
            // If changing to 'converted', no error - allow it
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE crm_leads SET
                customer_type = ?, company_name = ?, contact_person = ?, designation = ?,
                phone = ?, email = ?,
                address1 = ?, address2 = ?, city = ?, state = ?, pincode = ?, country = ?,
                lead_status = ?, lead_source = ?, market_classification = ?, industry = ?,
                buying_timeline = ?, budget_range = ?, decision_maker = ?,
                next_followup_date = ?, assigned_to = ?, assigned_user_id = ?, notes = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $customer_type, $company_name ?: null, $contact_person, $designation ?: null,
            $phone, $email ?: null,
            $address1 ?: null, $address2 ?: null, $city ?: null, $state ?: null, $pincode ?: null, $country,
            $lead_status, $lead_source ?: null, $market_classification ?: null, $industry ?: null,
            $buying_timeline, $budget_range ?: null, $decision_maker,
            $next_followup_date ?: null, $assigned_to ?: null, $assigned_user_id, $notes ?: null,
            $id
        ]);

        setModal("Success", "Lead updated successfully!");
        header("Location: view.php?id=$id");
        exit;
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Lead - <?= htmlspecialchars($lead['lead_no']) ?></title>
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
        .form-group textarea { min-height: 100px; resize: vertical; }

        /* Styling for readonly and disabled fields */
        .form-group input[readonly], .form-group select[disabled] {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
            border-color: #ddd;
        }
        .form-group input[readonly]:focus, .form-group select[disabled]:focus {
            outline: none;
            box-shadow: none;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        .radio-group input[type="radio"] { width: auto; }

        .status-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .status-options label {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: normal;
        }
        .status-options input[type="radio"] { display: none; }
        .status-options input[type="radio"]:checked + span { font-weight: bold; }
        .status-options label:has(input:checked) { border-color: #3498db; background: #ebf5fb; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        .delete-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff5f5;
            border: 1px solid #ffcccc;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Lead: <?= htmlspecialchars($lead['lead_no']) ?></h1>

        <p>
            <a href="index.php" class="btn btn-secondary">Back to Leads</a>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">View Lead</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">

            <!-- Customer Type -->
            <div class="form-section">
                <h3>Customer Type</h3>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="customer_type" value="B2B"
                               <?= $lead['customer_type'] === 'B2B' ? 'checked' : '' ?>>
                        <strong>B2B</strong> (Business to Business)
                    </label>
                    <label>
                        <input type="radio" name="customer_type" value="B2C"
                               <?= $lead['customer_type'] === 'B2C' ? 'checked' : '' ?>>
                        <strong>B2C</strong> (Business to Consumer)
                    </label>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" id="company_name"
                               value="<?= htmlspecialchars($lead['company_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Person *</label>
                        <input type="text" name="contact_person" required
                               value="<?= htmlspecialchars($lead['contact_person']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <?php
                        $designations = ['Chairman', 'Managing Director', 'Director', 'Owner', 'Partner', 'CEO', 'CFO', 'COO', 'General Manager', 'Manager', 'Assistant Manager', 'Salesperson', 'Purchase Manager', 'Accountant', 'Administrator', 'Receptionist', 'Other'];
                        ?>
                        <select name="designation">
                            <option value="">-- Select Designation --</option>
                            <?php foreach ($designations as $d): ?>
                                <option value="<?= $d ?>" <?= ($lead['designation'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <select name="industry">
                            <option value="">-- Select Industry --</option>
                            <?php
                            $industries = ['Multi-Specialty Hospital', 'Super-Specialty Hospital', 'Medical College', 'Nursing Home', 'Eye Hospital', 'Medical Equipment Dealer', 'Hospital Supply Chain', 'Lab Equipment Supplier', 'Surgical Instrument Dealer', 'Medical Device Manufacturing', 'Medical E-commerce', 'Other'];
                            foreach ($industries as $ind): ?>
                                <option value="<?= $ind ?>" <?= ($lead['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= $ind ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="phone" required
                               value="<?= htmlspecialchars($lead['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address
                    <?php if ($isFromCustomerDB): ?>
                    <span style="color: #e74c3c; font-size: 0.8em; font-weight: normal;">(Read-only - From Customer Database)</span>
                    <?php elseif (in_array($lead['lead_status'], ['hot', 'converted', 'lost'])): ?>
                    <span style="color: #e74c3c; font-size: 0.8em; font-weight: normal;">(Read-only for <?= ucfirst($lead['lead_status']) ?> leads)</span>
                    <?php endif; ?>
                </h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 1</label>
                        <input type="text" name="address1" id="address1"
                               value="<?= htmlspecialchars($lead['address1'] ?? '') ?>"
                               <?= $allowAddressEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 2</label>
                        <input type="text" name="address2" id="address2"
                               value="<?= htmlspecialchars($lead['address2'] ?? '') ?>"
                               <?= $allowAddressEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <?php if (!empty($states)): ?>
                        <select name="state" id="state" onchange="loadCitiesByState(this.value)" <?= $allowAddressEdit ? '' : 'disabled' ?>>
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= htmlspecialchars($st['state_name']) ?>" <?= ($lead['state'] ?? '') === $st['state_name'] ? 'selected' : '' ?>><?= htmlspecialchars($st['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" name="state" id="state" value="<?= htmlspecialchars($lead['state'] ?? '') ?>" <?= $allowAddressEdit ? '' : 'readonly' ?>>
                        <small style="color: #e74c3c;">Run <a href="/admin/install_locations.php" target="_blank">location installer</a> for dropdown</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <?php if (!empty($states)): ?>
                        <select name="city" id="city" <?= $allowAddressEdit ? '' : 'disabled' ?>>
                            <option value="">-- Select City --</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= ($lead['city'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" name="city" id="city" value="<?= htmlspecialchars($lead['city'] ?? '') ?>" <?= $allowAddressEdit ? '' : 'readonly' ?>>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="pincode"
                               value="<?= htmlspecialchars($lead['pincode'] ?? '') ?>"
                               <?= $allowAddressEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="country"
                               value="<?= htmlspecialchars($lead['country'] ?? 'India') ?>"
                               <?= $allowAddressEdit ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>

            <!-- Lead Classification -->
            <div class="form-section">
                <h3>Lead Classification</h3>

                <?php
                /**
                 * Lead Status Workflow:
                 * - Cold: New lead, can only go to Warm (via quotation) or Lost
                 * - Warm: Can go to Cold (back) or Lost. Hot is automatic when PI is released
                 * - Hot: Can only go to Lost. Converted is automatic when Invoice is generated
                 * - Converted: Fully locked, no changes allowed
                 * - Lost: Can go back to Cold or Warm
                 */
                $currentStatus = strtolower($lead['lead_status']);

                // Define allowed status transitions based on current status
                $allowedStatuses = [];
                switch ($currentStatus) {
                    case 'cold':
                        $allowedStatuses = ['cold', 'warm', 'lost'];
                        break;
                    case 'warm':
                        $allowedStatuses = ['cold', 'warm', 'lost'];
                        // Hot is shown only if current status is already hot (shouldn't happen here)
                        break;
                    case 'hot':
                        $allowedStatuses = ['hot', 'lost'];
                        // Converted only shown if invoice is released (handled below)
                        if ($hasReleasedPI) {
                            $allowedStatuses[] = 'converted';
                        }
                        break;
                    case 'converted':
                        $allowedStatuses = ['converted']; // Fully locked
                        break;
                    case 'lost':
                        $allowedStatuses = ['cold', 'warm', 'lost'];
                        break;
                    default:
                        $allowedStatuses = ['cold', 'warm', 'lost'];
                }
                ?>

                <?php if ($currentStatus === 'converted'): ?>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                        <strong>Lead Converted:</strong> This lead has been successfully converted. Status cannot be changed.
                    </div>
                <?php elseif ($currentStatus === 'hot' && $hasReleasedPI): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                        <strong>Ready for Conversion:</strong> PI has been released. Generate an Invoice to automatically convert this lead.
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Lead Status</label>
                    <div class="status-options">
                        <?php foreach (['cold', 'warm', 'hot', 'converted', 'lost'] as $s): ?>
                        <?php
                            // Skip statuses not allowed for current workflow
                            if (!in_array($s, $allowedStatuses)) {
                                continue;
                            }

                            // Determine if option should be disabled
                            $isDisabled = ($currentStatus === 'converted' && $s !== 'converted');
                            $highlight = ($currentStatus === 'hot' && $s === 'converted' && $hasReleasedPI);
                        ?>
                        <label style="<?= $isDisabled ? 'opacity: 0.5; cursor: not-allowed;' : '' ?><?= $highlight ? 'background: #d4edda; border-color: #28a745;' : '' ?>">
                            <input type="radio" name="lead_status" value="<?= $s ?>"
                                   <?= $currentStatus === $s ? 'checked' : '' ?>
                                   <?= $isDisabled ? 'disabled' : '' ?>>
                            <span><?= ucfirst($s) ?><?= $highlight ? ' ←' : '' ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($currentStatus === 'converted'): ?>
                    <!-- Hidden field to ensure converted status is submitted (fully locked) -->
                    <input type="hidden" name="lead_status" value="converted">
                    <?php endif; ?>
                    <small style="color: #666; margin-top: 10px; display: block;">
                        <strong>Status Workflow:</strong><br>
                        • Cold → Warm: When creating a quotation<br>
                        • Warm → Hot: Automatic when PI is released<br>
                        • Hot → Converted: Automatic when Invoice is generated
                    </small>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Lead Source</label>
                        <select name="lead_source">
                            <option value="">-- Select Source --</option>
                            <?php
                            $sources = ['Existing Customer', 'Existing Lead', 'Website', 'Referral', 'Cold Call', 'Trade Show', 'Exhibition', 'Social Media', 'Email Campaign', 'WhatsApp', 'Walk-in', 'Newspaper Ad', 'Online Ad', 'Direct Mail', 'Partner', 'Other'];
                            foreach ($sources as $src):
                            ?>
                                <option value="<?= $src ?>" <?= ($lead['lead_source'] ?? '') === $src ? 'selected' : '' ?>>
                                    <?= $src ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Market Classification</label>
                        <select name="market_classification">
                            <option value="">-- Select Market --</option>
                            <?php
                            $markets = ['GEMS or Tenders', 'Export Orders', 'Corporate Customers', 'Private Hospitals', 'Medical Colleges', 'NGO or Others'];
                            foreach ($markets as $mkt):
                            ?>
                                <option value="<?= $mkt ?>" <?= ($lead['market_classification'] ?? '') === $mkt ? 'selected' : '' ?>>
                                    <?= $mkt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Buying Intent -->
            <div class="form-section">
                <h3>Buying Intent</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Buying Timeline</label>
                        <select name="buying_timeline">
                            <?php
                            $timelines = [
                                'uncertain' => 'Uncertain',
                                'immediate' => 'Immediate',
                                '1_month' => 'Within 1 Month',
                                '3_months' => 'Within 3 Months',
                                '6_months' => 'Within 6 Months',
                                '1_year' => 'Within 1 Year'
                            ];
                            foreach ($timelines as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= $lead['buying_timeline'] === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget Range</label>
                        <input type="text" name="budget_range"
                               value="<?= htmlspecialchars($lead['budget_range'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Decision Maker?</label>
                        <select name="decision_maker">
                            <option value="no" <?= $lead['decision_maker'] === 'no' ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= $lead['decision_maker'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                            <option value="influencer" <?= $lead['decision_maker'] === 'influencer' ? 'selected' : '' ?>>Influencer</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Follow-up & Assignment -->
            <div class="form-section">
                <h3>Follow-up & Assignment</h3>
                <?php
                // Check if next_followup_date was set via an interaction (non-admin cannot change)
                $followupLocked = false;
                if (!$isAdmin && !empty($lead['next_followup_date'])) {
                    // Check if there's an interaction that set this date
                    try {
                        $followupCheckStmt = $pdo->prepare("
                            SELECT id FROM crm_lead_interactions
                            WHERE lead_id = ? AND next_action_date = ?
                            LIMIT 1
                        ");
                        $followupCheckStmt->execute([$id, $lead['next_followup_date']]);
                        if ($followupCheckStmt->fetch()) {
                            $followupLocked = true;
                        }
                    } catch (PDOException $e) {}
                }
                ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Next Follow-up Date
                            <?php if ($followupLocked): ?>
                                <span style="color: #e74c3c; font-size: 0.8em; font-weight: normal;">(Locked - Only admin can change)</span>
                            <?php endif; ?>
                        </label>
                        <input type="date" name="next_followup_date"
                               value="<?= htmlspecialchars($lead['next_followup_date'] ?? '') ?>"
                               <?= $followupLocked ? 'readonly style="background-color: #f5f5f5; color: #666; cursor: not-allowed;"' : '' ?>>
                        <?php if ($followupLocked): ?>
                            <small style="color: #e74c3c;">This date was set via an interaction log. Contact admin to change it.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Assigned To *</label>
                        <select name="assigned_user_id" required>
                            <option value="">-- Select Person --</option>
                            <?php
                            // Get the currently assigned employee ID
                            $currentAssignedUserId = $lead['assigned_user_id'] ?? null;

                            // Fetch all active employees
                            $allStaff = $pdo->query("
                                SELECT id, emp_id, first_name, last_name, department, designation
                                FROM employees
                                WHERE status = 'Active'
                                ORDER BY first_name, last_name
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($allStaff as $emp) {
                                $empName = htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']);
                                $empInfo = $emp['designation'] ?: $emp['department'];
                                if ($empInfo) {
                                    $empName .= ' (' . htmlspecialchars($empInfo) . ')';
                                }
                                $selected = ($currentAssignedUserId && $emp['id'] == $currentAssignedUserId) ? 'selected' : '';
                                echo '<option value="' . $emp['id'] . '" ' . $selected . '>' . $empName . '</option>';
                            }
                            ?>
                        </select>
                        <small style="color: #666;">Select the person responsible for this lead</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes"><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                    Update Lead
                </button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </div>

        </form>

        <!-- Add to Customers Section -->
        <div class="form-section" style="margin-top: 30px; background: #e8f4fd; border-color: #3498db;">
            <h3 style="border-bottom-color: #3498db;">Add Lead to Customer Database</h3>

            <?php if ($customerAddSuccess): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <strong>Success!</strong> Lead has been added to the customer database with ID: <strong><?= htmlspecialchars($existingCustomerId) ?></strong>
                    <br><a href="/customers/edit.php?id=<?= urlencode($existingCustomerId) ?>" class="btn btn-primary" style="margin-top: 10px;">View Customer Record</a>
                </div>
            <?php endif; ?>

            <?php if ($customerAddError): ?>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <?= $customerAddError ?>
                </div>
            <?php endif; ?>

            <?php if ($existingCustomerId && !$customerAddSuccess): ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <strong>Customer Already Exists:</strong> A customer with this phone number (<?= htmlspecialchars($lead['phone']) ?>) is already in the database.
                    <br><a href="/customers/edit.php?id=<?= urlencode($existingCustomerId) ?>" class="btn btn-secondary" style="margin-top: 10px;">View Existing Customer</a>
                </div>
            <?php elseif (!$customerAddSuccess): ?>
                <p style="margin-bottom: 15px; color: #555;">
                    Click the button below to add this lead's information to the main customer database.
                    The system will check if a customer with the same phone number already exists.
                </p>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                    <strong>Lead Information to be Added:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #666;">
                        <li><strong>Type:</strong> <?= htmlspecialchars($lead['customer_type'] ?? 'B2B') ?></li>
                        <?php if ($lead['company_name']): ?>
                            <li><strong>Company:</strong> <?= htmlspecialchars($lead['company_name']) ?></li>
                        <?php endif; ?>
                        <li><strong>Contact Person:</strong> <?= htmlspecialchars($lead['contact_person']) ?></li>
                        <li><strong>Phone:</strong> <?= htmlspecialchars($lead['phone'] ?? 'Not provided') ?></li>
                        <?php if ($lead['email']): ?>
                            <li><strong>Email:</strong> <?= htmlspecialchars($lead['email']) ?></li>
                        <?php endif; ?>
                        <?php if ($lead['city'] || $lead['state']): ?>
                            <li><strong>Location:</strong> <?= htmlspecialchars(implode(', ', array_filter([$lead['city'], $lead['state']]))) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <form method="post">
                    <input type="hidden" name="add_to_customers" value="1">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 25px;"
                            onclick="return confirm('Add this lead to the customer database?');">
                        Add to Customer Database
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Delete Section -->
        <div class="delete-section">
            <h4 style="margin-top: 0; color: #c0392b;">Danger Zone</h4>
            <p>Deleting a lead will also remove all its requirements and interactions.</p>
            <form method="post" action="delete.php" onsubmit="return confirm('Are you sure you want to delete this lead? This action cannot be undone.');">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn" style="background: #e74c3c; color: #fff;">
                    Delete This Lead
                </button>
            </form>
        </div>

    </div>
</div>

<script>
// Toggle address field state based on lead status
function updateAddressFieldState() {
    const leadStatus = document.querySelector('input[name="lead_status"]:checked').value;
    const restrictedStatuses = ['hot', 'converted', 'lost'];
    const isFromCustomerDB = <?= $isFromCustomerDB ? 'true' : 'false' ?>;
    const isRestricted = isFromCustomerDB || restrictedStatuses.includes(leadStatus);

    // Get all address fields
    const addressFields = [
        document.getElementById('address1'),
        document.getElementById('address2'),
        document.getElementById('state'),
        document.getElementById('city'),
        document.getElementById('pincode'),
        document.getElementById('country')
    ];

    // Disable or enable based on lead status and customer DB origin
    addressFields.forEach(field => {
        if (field) {
            if (field.tagName === 'SELECT') {
                field.disabled = isRestricted;
            } else {
                field.readOnly = isRestricted;
            }
        }
    });

    // Update read-only message based on reason
    const addressHeaders = document.querySelectorAll('.form-section h3');
    addressHeaders.forEach(header => {
        if (header.textContent.includes('Address')) {
            let message = 'Address';

            if (isFromCustomerDB) {
                message += '<span style="color: #e74c3c; font-size: 0.8em; font-weight: normal;"> (Read-only - From Customer Database)</span>';
            } else if (restrictedStatuses.includes(leadStatus)) {
                message += '<span style="color: #e74c3c; font-size: 0.8em; font-weight: normal;"> (Read-only for ' + leadStatus + ' leads)</span>';
            }

            header.innerHTML = message;
        }
    });
}

// Initialize address field state on page load
document.addEventListener('DOMContentLoaded', function() {
    updateAddressFieldState();

    // Update address field state when lead status changes
    const leadStatusRadios = document.querySelectorAll('input[name="lead_status"]');
    leadStatusRadios.forEach(radio => {
        radio.addEventListener('change', updateAddressFieldState);
    });
});

// Load cities based on selected state
function loadCitiesByState(stateName) {
    const citySelect = document.getElementById('city');

    if (!citySelect || citySelect.tagName !== 'SELECT') return;

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select State First --</option>';
        return;
    }

    // Show loading state
    citySelect.innerHTML = '<option value="">Loading cities...</option>';

    // Fetch cities from API - use relative path from crm folder
    const apiUrl = '../api/get_cities.php?state=' + encodeURIComponent(stateName);

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('API returned status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // Clear and add default option
            citySelect.innerHTML = '<option value="">-- Select City --</option>';

            if (data.success && data.cities && data.cities.length > 0) {
                // Add all cities for the selected state
                data.cities.forEach(city => {
                    const opt = document.createElement('option');
                    opt.value = city.city_name;
                    opt.textContent = city.city_name;
                    citySelect.appendChild(opt);
                });
                console.log('Loaded ' + data.cities.length + ' cities for ' + stateName);
            } else {
                // No cities found for this state
                citySelect.innerHTML = '<option value="">-- No cities available --</option>';
                console.log('No cities found for state: ' + stateName);
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            citySelect.innerHTML = '<option value="">-- Error loading cities --</option>';
        });
}
</script>

</body>
</html>
