<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

// Fetch states for dropdown
$states = [];
try {
    $states = $pdo->query("SELECT id, state_name FROM states WHERE is_active = 1 ORDER BY state_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist, will use text input as fallback
}

// Fetch YID parts for product requirements
// Match parts where part_no OR part_id starts with 'YID'
$yidParts = [];
try {
    $yidParts = $pdo->query("
        SELECT part_no, part_name, part_id, description, hsn_code, uom, rate
        FROM part_master
        WHERE status = 'active'
          AND (UPPER(part_no) LIKE 'YID%' OR UPPER(part_id) LIKE 'YID%')
        ORDER BY part_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
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

    if (empty($errors)) {
        // Generate lead number
        $maxNo = $pdo->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(lead_no, 6) AS UNSIGNED)), 0)
            FROM crm_leads WHERE lead_no LIKE 'LEAD-%'
        ")->fetchColumn();
        $lead_no = 'LEAD-' . ($maxNo + 1);

        $stmt = $pdo->prepare("
            INSERT INTO crm_leads (
                lead_no, customer_type, company_name, contact_person, designation,
                phone, email,
                address1, address2, city, state, pincode, country,
                lead_status, lead_source, market_classification, industry,
                buying_timeline, budget_range, decision_maker,
                next_followup_date, assigned_to, assigned_user_id, notes
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $lead_no, $customer_type, $company_name ?: null, $contact_person, $designation ?: null,
            $phone, $email ?: null,
            $address1 ?: null, $address2 ?: null, $city ?: null, $state ?: null, $pincode ?: null, $country,
            $lead_status, $lead_source ?: null, $market_classification ?: null, $industry ?: null,
            $buying_timeline, $budget_range ?: null, $decision_maker,
            $next_followup_date ?: null, $assigned_to ?: null, $assigned_user_id, $notes ?: null
        ]);

        $newId = $pdo->lastInsertId();

        // Insert product requirements if any
        if (!empty($_POST['req_part_no']) && is_array($_POST['req_part_no'])) {
            $reqStmt = $pdo->prepare("
                INSERT INTO crm_lead_requirements
                (lead_id, part_no, product_name, description, estimated_qty, unit, target_price, priority)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'medium')
            ");

            foreach ($_POST['req_part_no'] as $i => $partNo) {
                if (empty(trim($partNo))) continue;

                $reqStmt->execute([
                    $newId,
                    $partNo,
                    $_POST['req_product_name'][$i] ?? '',
                    $_POST['req_description'][$i] ?? '',
                    $_POST['req_qty'][$i] ?? null,
                    $_POST['req_unit'][$i] ?? null,
                    $_POST['req_price'][$i] ?? null
                ]);
            }
        }

        setModal("Success", "Lead $lead_no created successfully!");
        header("Location: view.php?id=$newId");
        exit;
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Lead - CRM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
        }
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
        .form-group small { color: #666; font-size: 0.85em; }

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
        .status-options .status-cold { border-color: #bdc3c7; }
        .status-options .status-warm { border-color: #f39c12; }
        .status-options .status-hot { border-color: #e74c3c; }
        .status-options label.status-cold:has(input:checked) { background: #ecf0f1; }
        .status-options label.status-warm:has(input:checked) { background: #fef5e7; }
        .status-options label.status-hot:has(input:checked) { background: #fdedec; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        /* Product Requirements Styles */
        .req-table { width: 100%; border-collapse: collapse; margin-top: 10px; overflow: visible; }
        .req-table th, .req-table td { padding: 8px; border: 1px solid #ddd; text-align: left; overflow: visible; }
        .req-table td.part-search-container { position: relative; overflow: visible; }
        .req-table th { background: #3498db; color: white; font-size: 0.9em; }
        .req-table input { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .req-table input[readonly] { background: #f5f5f5; }

        .part-search-container { position: relative; }
        .part-search { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .part-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 9999;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .part-dropdown.show { display: block !important; }
        .part-option {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .part-option:hover { background: #e8f4fc; }
        .part-option.hidden { display: none; }

        /* Customer Search Dropdown */
        #customer_search_results {
            margin-top: 10px;
            background: white;
            border: 2px solid #2196f3;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        #customer_search_results table {
            width: 100%;
            border-collapse: collapse;
        }

        #customer_search_results th {
            background: #2196f3;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 0.9em;
        }

        #customer_search_results td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }

        #customer_search_results tr:hover {
            background: #e3f2fd;
        }

        .import-customer-btn {
            padding: 5px 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85em;
            transition: background 0.2s;
        }

        .import-customer-btn:hover {
            background: #229954;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Add New Lead</h1>

        <p><a href="index.php" class="btn btn-secondary">Back to Leads</a></p>

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
                        <input type="radio" name="customer_type" value="B2B" checked>
                        <strong>B2B</strong> (Business to Business)
                    </label>
                    <label>
                        <input type="radio" name="customer_type" value="B2C">
                        <strong>B2C</strong> (Business to Consumer)
                    </label>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>

                <!-- Import Customer Section -->
                <div style="background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <strong>Import Existing Customer Data</strong>
                    <div style="position: relative; margin-top: 10px;">
                        <input type="text"
                               id="customer_search"
                               placeholder="Search by customer name, company, phone, or customer ID..."
                               autocomplete="off"
                               style="width: 100%; padding: 10px; border: 2px solid #2196f3; border-radius: 4px; font-size: 14px;">
                        <div id="customer_search_results" style="display: none;"></div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name <span id="companyRequired">*</span></label>
                        <input type="text" name="company_name" id="company_name"
                               placeholder="Company / Organization name">
                    </div>
                    <div class="form-group">
                        <label>Contact Person *</label>
                        <input type="text" name="contact_person" id="contact_person" required
                               placeholder="Primary contact name">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation" id="designation">
                            <option value="">-- Select Designation --</option>
                            <option value="Chairman">Chairman</option>
                            <option value="Managing Director">Managing Director</option>
                            <option value="Director">Director</option>
                            <option value="Owner">Owner</option>
                            <option value="Partner">Partner</option>
                            <option value="CEO">CEO</option>
                            <option value="CFO">CFO</option>
                            <option value="COO">COO</option>
                            <option value="General Manager">General Manager</option>
                            <option value="Manager">Manager</option>
                            <option value="Assistant Manager">Assistant Manager</option>
                            <option value="Salesperson">Salesperson</option>
                            <option value="Purchase Manager">Purchase Manager</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Administrator">Administrator</option>
                            <option value="Receptionist">Receptionist</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <select name="industry" id="industry">
                            <option value="">-- Select Industry --</option>
                            <option value="Multi-Specialty Hospital">Multi-Specialty Hospital</option>
                            <option value="Super-Specialty Hospital">Super-Specialty Hospital</option>
                            <option value="Medical College">Medical College</option>
                            <option value="Nursing Home">Nursing Home</option>
                            <option value="Eye Hospital">Eye Hospital</option>
                            <option value="Medical Equipment Dealer">Medical Equipment Dealer</option>
                            <option value="Hospital Supply Chain">Hospital Supply Chain</option>
                            <option value="Lab Equipment Supplier">Lab Equipment Supplier</option>
                            <option value="Surgical Instrument Dealer">Surgical Instrument Dealer</option>
                            <option value="Medical Device Manufacturing">Medical Device Manufacturing</option>
                            <option value="Medical E-commerce">Medical E-commerce</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="phone" id="phone" required placeholder="Primary phone number">
                        <div id="phone_lookup_result"></div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email" placeholder="email@example.com">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 1</label>
                        <input type="text" name="address1" id="address1" placeholder="Street address, building">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 2</label>
                        <input type="text" name="address2" id="address2" placeholder="Area, landmark">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <?php if (!empty($states)): ?>
                        <select name="state" id="state" onchange="loadCitiesByState(this.value)">
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= htmlspecialchars($st['state_name']) ?>"><?= htmlspecialchars($st['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" name="state" id="state" placeholder="Enter state">
                        <small style="color: #e74c3c;">Run <a href="/admin/install_locations.php">location installer</a> to enable dropdown</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <?php if (!empty($states)): ?>
                        <select name="city" id="city">
                            <option value="">-- Select State First --</option>
                        </select>
                        <?php else: ?>
                        <input type="text" name="city" id="city" placeholder="Enter city">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="pincode">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="country" value="India">
                    </div>
                </div>
            </div>

            <!-- Lead Classification -->
            <div class="form-section">
                <h3>Lead Classification</h3>

                <div class="form-group">
                    <label>Lead Status</label>
                    <div class="status-options">
                        <label class="status-cold">
                            <input type="radio" name="lead_status" value="cold" checked>
                            <span>Cold</span>
                        </label>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        <strong>Status Progression:</strong><br>
                        • New leads start as <strong>Cold</strong><br>
                        • Cold → Warm: When creating a quotation<br>
                        • Warm → Hot: When PI is released<br>
                        • Hot → Converted: When Invoice is generated
                    </small>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Lead Source</label>
                        <select name="lead_source">
                            <option value="">-- Select Source --</option>
                            <option value="Existing Customer">Existing Customer</option>
                            <option value="Existing Lead">Existing Lead</option>
                            <option value="Website">Website</option>
                            <option value="Referral">Referral</option>
                            <option value="Cold Call">Cold Call</option>
                            <option value="Trade Show">Trade Show</option>
                            <option value="Exhibition">Exhibition</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Email Campaign">Email Campaign</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Newspaper Ad">Newspaper Ad</option>
                            <option value="Online Ad">Online Ad</option>
                            <option value="Direct Mail">Direct Mail</option>
                            <option value="Partner">Partner</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Market Classification</label>
                        <select name="market_classification">
                            <option value="">-- Select Market --</option>
                            <option value="GEMS or Tenders">GEMS or Tenders</option>
                            <option value="Export Orders">Export Orders</option>
                            <option value="Corporate Customers">Corporate Customers</option>
                            <option value="Private Hospitals">Private Hospitals</option>
                            <option value="Medical Colleges">Medical Colleges</option>
                            <option value="NGO or Others">NGO or Others</option>
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
                            <option value="uncertain">Uncertain</option>
                            <option value="immediate">Immediate</option>
                            <option value="1_month">Within 1 Month</option>
                            <option value="3_months">Within 3 Months</option>
                            <option value="6_months">Within 6 Months</option>
                            <option value="1_year">Within 1 Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget Range</label>
                        <input type="text" name="budget_range" placeholder="e.g., 1-5 Lakhs">
                    </div>
                    <div class="form-group">
                        <label>Decision Maker?</label>
                        <select name="decision_maker">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                            <option value="influencer">Influencer</option>
                        </select>
                        <small>Is this contact the decision maker for purchases?</small>
                    </div>
                </div>
            </div>

            <!-- Follow-up & Assignment -->
            <div class="form-section">
                <h3>Follow-up & Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Next Follow-up Date</label>
                        <input type="date" name="next_followup_date">
                    </div>
                    <div class="form-group">
                        <label>Assigned To *</label>
                        <select name="assigned_user_id" id="assigned_engineer" required>
                            <option value="">-- Select Person --</option>
                            <?php
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
                                echo '<option value="' . $emp['id'] . '" data-name="' . htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) . '">' . $empName . '</option>';
                            }
                            ?>
                        </select>
                        <small style="color: #666;">Select the person responsible for this lead</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Any initial notes about this lead..."></textarea>
                </div>
            </div>

            <!-- Product Requirements Section -->
            <div class="form-section">
                <h3>Product Requirements (Optional)</h3>
                <p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
                    Add product requirements for this lead. Only YID parts are available.
                </p>

                <?php if (empty($yidParts)): ?>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;">
                        No YID parts available. <a href="/part_master/add.php">Add parts first</a>.
                    </div>
                <?php else: ?>
                <table class="req-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Part Number</th>
                            <th style="width: 18%;">Product Name</th>
                            <th style="width: 20%;">Description</th>
                            <th style="width: 10%;">Qty</th>
                            <th style="width: 8%;">Unit</th>
                            <th style="width: 14%;">Target Price</th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="reqTableBody">
                        <tr class="req-row">
                            <td class="part-search-container">
                                <input type="text" class="part-search" placeholder="Search part..." autocomplete="off"
                                       onfocus="showReqPartDropdown(this)" oninput="filterReqParts(this)">
                                <input type="hidden" name="req_part_no[]" class="req-part-no-hidden">
                                <div class="part-dropdown" style="display:none;">
                                    <?php foreach ($yidParts as $p): ?>
                                        <div class="part-option"
                                             data-part-no="<?= htmlspecialchars($p['part_no']) ?>"
                                             data-name="<?= htmlspecialchars($p['part_name']) ?>"
                                             data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                             data-uom="<?= htmlspecialchars($p['uom'] ?? '') ?>"
                                             data-rate="<?= $p['rate'] ?? 0 ?>"
                                             onclick="selectReqPart(this)">
                                            <?= htmlspecialchars($p['part_no']) ?> - <?= htmlspecialchars($p['part_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><input type="text" name="req_product_name[]" class="req-product-name" readonly placeholder="Auto-filled"></td>
                            <td><input type="text" name="req_description[]" class="req-description" placeholder="Optional details..."></td>
                            <td><input type="number" name="req_qty[]" class="req-qty" step="0.01" min="0" placeholder="Qty"></td>
                            <td><input type="text" name="req_unit[]" class="req-unit" readonly placeholder="Unit"></td>
                            <td><input type="number" name="req_price[]" class="req-price" step="0.01" min="0" placeholder="Price"></td>
                            <td style="text-align: center;">
                                <button type="button" onclick="removeReqRow(this)" class="btn btn-danger" style="padding: 3px 8px;">-</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" onclick="addReqRow()" class="btn btn-secondary" style="margin-top: 10px;">+ Add Requirement</button>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                    Create Lead
                </button>
                <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </div>

        </form>
    </div>
</div>

<script>
// Toggle company name requirement based on customer type
document.querySelectorAll('input[name="customer_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const companyField = document.getElementById('company_name');
        const requiredSpan = document.getElementById('companyRequired');
        if (this.value === 'B2B') {
            companyField.required = true;
            requiredSpan.style.display = 'inline';
        } else {
            companyField.required = false;
            requiredSpan.style.display = 'none';
        }
    });
});

// Customer search functionality
const customersData = <?php
$customers = $pdo->query("SELECT id, customer_id, customer_name, company_name, contact, email FROM customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($customers);
?>;

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customer_search');
    const resultsDiv = document.getElementById('customer_search_results');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();

        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        const matches = customersData.filter(c => {
            const customerName = (c.customer_name || '').toLowerCase();
            const companyName = (c.company_name || '').toLowerCase();
            const customerId = (c.customer_id || '').toLowerCase();
            const contact = (c.contact || '').toLowerCase();

            return customerName.includes(query) ||
                   companyName.includes(query) ||
                   customerId.includes(query) ||
                   contact.includes(query);
        }).slice(0, 20);

        if (matches.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No customers found</div>';
            resultsDiv.style.display = 'block';
            return;
        }

        const tableHtml = `
            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Phone</th>
                        <th style="width: 80px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${matches.map(c => `
                        <tr>
                            <td><strong>${escapeHtml(c.customer_id || '-')}</strong></td>
                            <td>${escapeHtml(c.customer_name || '-')}</td>
                            <td>${escapeHtml(c.company_name || '-')}</td>
                            <td>${escapeHtml(c.contact || '-')}</td>
                            <td style="text-align: center;">
                                <button type="button" class="import-customer-btn"
                                        onclick="importCustomerData(${c.id})">
                                    Import
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        resultsDiv.innerHTML = tableHtml;
        resultsDiv.style.display = 'block';
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Import customer data
function importCustomerData(customerId) {
    if (!customerId) {
        alert('Invalid customer ID');
        return;
    }

    fetch(`/api/get_customer_by_id.php?id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data) {
                // Set customer type based on company name
                if (data.company_name) {
                    document.querySelector('input[name="customer_type"][value="B2B"]').checked = true;
                    document.querySelector('input[name="customer_type"][value="B2B"]').dispatchEvent(new Event('change'));
                } else {
                    document.querySelector('input[name="customer_type"][value="B2C"]').checked = true;
                    document.querySelector('input[name="customer_type"][value="B2C"]').dispatchEvent(new Event('change'));
                }

                // Fill basic info
                document.getElementById('company_name').value = data.company_name || '';
                document.getElementById('contact_person').value = data.customer_name || '';
                document.getElementById('phone').value = data.contact || '';
                document.getElementById('email').value = data.email || '';

                // Fill address
                document.getElementById('address1').value = data.address1 || '';
                document.getElementById('address2').value = data.address2 || '';
                document.getElementById('pincode').value = data.pincode || '';

                // Set state first, then load cities and set city value
                const stateSelect = document.getElementById('state');
                const cityValue = data.city || '';

                if (stateSelect.tagName === 'SELECT') {
                    // State is a dropdown
                    stateSelect.value = data.state || '';
                    if (data.state) {
                        // Load cities and then set the city value
                        loadCitiesByState(data.state, cityValue);
                    }
                } else {
                    // State is a text input (fallback)
                    stateSelect.value = data.state || '';
                    document.getElementById('city').value = cityValue;
                }

                // Clear search and hide results
                document.getElementById('customer_search').value = '';
                document.getElementById('customer_search_results').style.display = 'none';

                alert('Customer data imported successfully!');
            }
        })
        .catch(error => {
            console.error('Error importing customer data:', error);
            alert('Error loading customer data');
        });
}

// Check for phone number duplicate
function checkPhoneDuplicate() {
    const phone = document.getElementById('phone').value;
    const lookupResult = document.getElementById('phone_lookup_result');

    if (!phone || phone.length < 10) {
        lookupResult.innerHTML = '';
        return;
    }

    fetch(`/api/check_customer.php?contact=${encodeURIComponent(phone)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                lookupResult.innerHTML = `
                    <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-top: 5px; border-left: 3px solid #ffc107; font-size: 0.9em;">
                        <strong>⚠ Warning:</strong> Customer exists with this phone number:<br>
                        <strong>${data.customer_name}</strong> (${data.customer_id})
                    </div>
                `;
            } else {
                lookupResult.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error checking phone:', error);
            lookupResult.innerHTML = '';
        });
}

// Add phone number check on input
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    let timeout;
    phoneInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(checkPhoneDuplicate, 500);
    });
});

// Load cities based on selected state
// Optional selectedCity parameter to auto-select a city after loading
function loadCitiesByState(stateName, selectedCity = null) {
    const citySelect = document.getElementById('city');

    if (!stateName) {
        citySelect.innerHTML = '<option value="">-- Select State First --</option>';
        return;
    }

    citySelect.innerHTML = '<option value="">Loading...</option>';

    fetch('../api/get_cities.php?state=' + encodeURIComponent(stateName))
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">-- Select City --</option>';

            if (data.success && data.cities && data.cities.length > 0) {
                data.cities.forEach(city => {
                    const opt = document.createElement('option');
                    opt.value = city.city_name;
                    opt.textContent = city.city_name;
                    // Auto-select the city if it matches
                    if (selectedCity && city.city_name === selectedCity) {
                        opt.selected = true;
                    }
                    citySelect.appendChild(opt);
                });
            } else {
                // Allow manual entry if no cities found
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = '-- No cities found, type below --';
                citySelect.appendChild(opt);
            }

            // If selectedCity was provided but not found in the list, add it as an option
            if (selectedCity && citySelect.value !== selectedCity) {
                const opt = document.createElement('option');
                opt.value = selectedCity;
                opt.textContent = selectedCity;
                opt.selected = true;
                citySelect.appendChild(opt);
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            citySelect.innerHTML = '<option value="">-- Error loading cities --</option>';
        });
}

// ========== Product Requirements Functions ==========
function showReqPartDropdown(input) {
    // First hide all other dropdowns
    document.querySelectorAll('.part-dropdown').forEach(d => {
        d.style.display = 'none';
        d.classList.remove('show');
    });

    const container = input.closest('.part-search-container');
    const dropdown = container.querySelector('.part-dropdown');
    dropdown.style.display = 'block';
    dropdown.classList.add('show');

    // Show all options when first focused
    const options = dropdown.querySelectorAll('.part-option');
    options.forEach(opt => opt.classList.remove('hidden'));
}

function filterReqParts(input) {
    const searchTerm = input.value.toLowerCase().trim();
    const container = input.closest('.part-search-container');
    const dropdown = container.querySelector('.part-dropdown');
    const options = dropdown.querySelectorAll('.part-option');

    dropdown.style.display = 'block';

    options.forEach(opt => {
        const partNo = opt.dataset.partNo.toLowerCase();
        const partName = opt.dataset.name.toLowerCase();

        if (partNo.includes(searchTerm) || partName.includes(searchTerm)) {
            opt.classList.remove('hidden');
        } else {
            opt.classList.add('hidden');
        }
    });
}

function selectReqPart(option) {
    const container = option.closest('.part-search-container');
    const row = option.closest('tr');
    const searchInput = container.querySelector('.part-search');
    const hiddenInput = container.querySelector('.req-part-no-hidden');
    const dropdown = container.querySelector('.part-dropdown');

    // Set values
    searchInput.value = option.dataset.partNo + ' - ' + option.dataset.name;
    hiddenInput.value = option.dataset.partNo;

    // Populate product name, description and unit
    row.querySelector('.req-product-name').value = option.dataset.name || '';
    row.querySelector('.req-description').value = option.dataset.description || '';
    row.querySelector('.req-unit').value = option.dataset.uom || '';

    // Hide dropdown
    dropdown.style.display = 'none';
    dropdown.classList.remove('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.part-search-container')) {
        document.querySelectorAll('.part-dropdown').forEach(d => {
            d.style.display = 'none';
            d.classList.remove('show');
        });
    }
});

function addReqRow() {
    const tbody = document.getElementById('reqTableBody');
    const firstRow = tbody.querySelector('.req-row');
    const clone = firstRow.cloneNode(true);

    // Clear all values
    clone.querySelector('.part-search').value = '';
    clone.querySelector('.req-part-no-hidden').value = '';
    clone.querySelector('.req-product-name').value = '';
    clone.querySelector('.req-description').value = '';
    clone.querySelector('.req-qty').value = '';
    clone.querySelector('.req-unit').value = '';
    clone.querySelector('.req-price').value = '';

    // Hide dropdown and reset its state
    const dropdown = clone.querySelector('.part-dropdown');
    dropdown.style.display = 'none';
    dropdown.classList.remove('show');

    // Re-show all part options (in case they were filtered)
    dropdown.querySelectorAll('.part-option').forEach(opt => opt.classList.remove('hidden'));

    tbody.appendChild(clone);
}

function removeReqRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('reqTableBody');
    const rows = tbody.querySelectorAll('.req-row');

    if (rows.length <= 1) {
        // Clear values instead of removing the last row
        row.querySelector('.part-search').value = '';
        row.querySelector('.req-part-no-hidden').value = '';
        row.querySelector('.req-product-name').value = '';
        row.querySelector('.req-description').value = '';
        row.querySelector('.req-qty').value = '';
        row.querySelector('.req-unit').value = '';
        row.querySelector('.req-price').value = '';
        return;
    }

    row.remove();
}
</script>

</body>
</html>
