<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];
$success = false;

// Fetch leads for reference dropdown (COLD and WARM leads without existing quotations)
// Cold leads will require conversion to Warm before quotation can be created
$leads = $pdo->query("
    SELECT id, lead_no, company_name, contact_person, phone, email,
           address1, address2, city, state, pincode, country, lead_status
    FROM crm_leads
    WHERE UPPER(lead_status) IN ('COLD', 'WARM')
      AND lead_no NOT IN (SELECT DISTINCT reference FROM quote_master WHERE reference IS NOT NULL)
    ORDER BY FIELD(UPPER(lead_status), 'WARM', 'COLD'), lead_no DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Separate leads by status for display
$warmLeads = array_filter($leads, fn($l) => strtoupper($l['lead_status']) === 'WARM');
$coldLeads = array_filter($leads, fn($l) => strtoupper($l['lead_status']) === 'COLD');

// Fetch customers for dropdown (if needed for fallback)
$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name
    FROM customers
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch only YID parts for dropdown
$parts = $pdo->query("
    SELECT part_no, part_name, part_id, description, hsn_code, uom, rate, gst
    FROM part_master
    WHERE status = 'active' AND UPPER(part_id) = 'YID'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch admin settings for Terms & Conditions and Bank Details
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Get company state for GST calculation (default to Maharashtra if not set)
$companyState = $settings['state'] ?? 'Maharashtra';

// Fetch active payment terms
$paymentTerms = [];
try {
    $paymentTerms = $pdo->query("SELECT * FROM payment_terms WHERE is_active = 1 ORDER BY sort_order, term_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
}

// Generate next quote number
function getNextQuoteNo($pdo) {
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    // Financial year: April to March
    // If current month is Jan-Mar, FY started previous year
    if ($currentMonth >= 4) {
        $fyStart = $currentYear;
        $fyEnd = $currentYear + 1;
    } else {
        $fyStart = $currentYear - 1;
        $fyEnd = $currentYear;
    }

    // Format: YY/YY (e.g., 25/26)
    $fyString = substr($fyStart, 2) . '/' . substr($fyEnd, 2);

    // Find the MAX serial number for this FY (extract number before the /)
    // Quote format: SERIAL/YY/YY (e.g., 34/25/26)
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(quote_no, '/', 1) AS UNSIGNED)) as max_serial
        FROM quote_master
        WHERE quote_no LIKE ?
    ");
    $stmt->execute(['%/' . $fyString]);
    $maxSerial = $stmt->fetchColumn();

    $serial = ($maxSerial ? (int)$maxSerial : 0) + 1;

    return $serial . '/' . $fyString;
}

$nextQuoteNo = getNextQuoteNo($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quote_no = trim($_POST['quote_no'] ?? '');
    $customer_id = trim($_POST['customer_id'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $quote_date = trim($_POST['quote_date'] ?? '');
    $validity_date = trim($_POST['validity_date'] ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment_details = trim($_POST['payment_details'] ?? '');
    $payment_terms_id = !empty($_POST['payment_terms_id']) ? (int)$_POST['payment_terms_id'] : null;

    // Validation
    if ($quote_no === '') {
        $errors[] = "Quote No is required";
    }
    if ($quote_date === '') {
        $errors[] = "Quote Date is required";
    }

    // Validate lead reference is required (lead must be selected)
    if (empty($reference)) {
        $errors[] = "Lead Reference is required. Please select a WARM lead.";
    } else {
        // Validate lead status is "WARM" (case insensitive)
        $leadCheck = $pdo->prepare("SELECT lead_status FROM crm_leads WHERE lead_no = ?");
        $leadCheck->execute([$reference]);
        $leadStatus = $leadCheck->fetchColumn();
        if (strtoupper($leadStatus) !== 'WARM') {
            $errors[] = "Quotation can only be created for leads with status 'WARM'. Current lead status: " . ($leadStatus ?: 'Not found');
        }
    }

    // customer_id is now optional - will be linked via lead reference

    // Check for items - ensure at least one valid part is selected
    $hasValidItem = false;
    if (!empty($_POST['part_no']) && is_array($_POST['part_no'])) {
        foreach ($_POST['part_no'] as $pn) {
            if (!empty(trim($pn))) {
                $hasValidItem = true;
                break;
            }
        }
    }
    if (!$hasValidItem) {
        $errors[] = "At least one item with a valid part must be selected";
    }

    // Handle file upload
    $attachmentPath = null;
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "../uploads/quotes/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowedExts)) {
            $errors[] = "File type not allowed. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG";
        } else {
            $fileName = "QUOTE_" . preg_replace('/[^a-zA-Z0-9]/', '_', $quote_no) . "_" . time() . "." . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                $attachmentPath = "uploads/quotes/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        // Ensure customer_id column allows NULL (run once, outside transaction)
        // ALTER TABLE causes implicit commit in MySQL, so it must be outside the transaction
        try {
            $pdo->exec("ALTER TABLE quote_master MODIFY COLUMN customer_id VARCHAR(50) NULL");
        } catch (PDOException $e) {
            // Ignore if already modified or column doesn't exist
        }

        $pdo->beginTransaction();

        try {
            // Determine if IGST mode
            $is_igst = isset($_POST['is_igst']) && $_POST['is_igst'] == '1' ? 1 : 0;

            // Insert quote master
            $stmt = $pdo->prepare("
                INSERT INTO quote_master
                (quote_no, customer_id, reference, quote_date, validity_date, terms_conditions, notes, payment_details, payment_terms_id, attachment_path, is_igst, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([
                $quote_no,
                $customer_id ?: null,  // NULL if no customer_id from lead
                $reference,
                $quote_date,
                $validity_date ?: null,
                $terms_conditions,
                $notes,
                $payment_details,
                $payment_terms_id,
                $attachmentPath,
                $is_igst
            ]);

            $quote_id = $pdo->lastInsertId();

            // Insert quote items
            $itemStmt = $pdo->prepare("
                INSERT INTO quote_items
                (quote_id, part_no, part_name, description, hsn_code, qty, unit, rate, discount, taxable_amount, cgst_percent, cgst_amount, sgst_percent, sgst_amount, igst_percent, igst_amount, total_amount, lead_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['part_no'] as $i => $part_no) {
                if (empty($part_no)) continue;

                $part_name = $_POST['part_name'][$i] ?? '';
                $description = $_POST['description'][$i] ?? '';
                $hsn_code = $_POST['hsn_code'][$i] ?? '';
                $qty = floatval($_POST['qty'][$i] ?? 1);
                $unit = $_POST['unit'][$i] ?? '';
                $rate = floatval($_POST['rate'][$i] ?? 0);
                $discount = floatval($_POST['discount'][$i] ?? 0);
                $lead_time = $_POST['lead_time'][$i] ?? '';
                $gst_percent = floatval($_POST['gst_percent'][$i] ?? 0);

                // Calculate amounts
                $gross = $qty * $rate;
                $discountAmt = $gross * ($discount / 100);
                $taxable = $gross - $discountAmt;

                // Calculate GST based on mode (IGST for inter-state, CGST/SGST for intra-state)
                $cgst_percent = 0;
                $cgst_amount = 0;
                $sgst_percent = 0;
                $sgst_amount = 0;
                $igst_percent = 0;
                $igst_amount = 0;

                if ($is_igst) {
                    // Inter-state: Full GST as IGST
                    $igst_percent = $gst_percent;
                    $igst_amount = $taxable * ($igst_percent / 100);
                    $total = $taxable + $igst_amount;
                } else {
                    // Intra-state: Split GST 50-50 as CGST/SGST
                    $cgst_percent = $gst_percent / 2;
                    $sgst_percent = $gst_percent / 2;
                    $cgst_amount = $taxable * ($cgst_percent / 100);
                    $sgst_amount = $taxable * ($sgst_percent / 100);
                    $total = $taxable + $cgst_amount + $sgst_amount;
                }

                $itemStmt->execute([
                    $quote_id,
                    $part_no,
                    $part_name,
                    $description,
                    $hsn_code,
                    $qty,
                    $unit,
                    $rate,
                    $discount,
                    $taxable,
                    $cgst_percent,
                    $cgst_amount,
                    $sgst_percent,
                    $sgst_amount,
                    $igst_percent,
                    $igst_amount,
                    $total,
                    $lead_time
                ]);
            }

            $pdo->commit();
            setModal("Success", "Quotation $quote_no created successfully!");
            header("Location: view.php?id=" . $quote_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Quotation</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .quote-form { max-width: 1200px; }
        .form-section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }
        .form-section h3 { margin-top: 0; color: #4a90d9; }
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }

        #itemsTable { width: 100%; border-collapse: collapse; margin-top: 10px; }
        #itemsTable th, #itemsTable td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 0.9em;
        }
        #itemsTable th { background: #4a90d9; color: white; }
        #itemsTable input, #itemsTable select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }
        #itemsTable input[readonly] { background: #f0f0f0; }
        .col-partid { width: 100px; }
        .col-partno { width: 120px; }
        .col-name { width: 150px; }
        .col-desc { width: 150px; }
        .col-hsn { width: 80px; }
        .col-qty { width: 70px; }
        .col-unit { width: 60px; }
        .col-rate { width: 90px; }
        .col-disc { width: 60px; }
        .col-taxable { width: 100px; }
        .col-cgst { width: 80px; }
        .col-sgst { width: 80px; }
        .col-igst { width: 80px; }
        .igst-mode .cgst-col, .igst-mode .sgst-col { display: none; }
        .igst-mode .igst-col { display: table-cell; }
        .cgst-sgst-mode .cgst-col, .cgst-sgst-mode .sgst-col { display: table-cell; }
        .cgst-sgst-mode .igst-col { display: none; }
        .col-amt { width: 100px; }
        .col-lead { width: 100px; }
        .col-action { width: 50px; text-align: center; }

        .btn-add-row { margin-top: 25px; }
        .totals-row { font-weight: bold; background: #e8f4e8 !important; }

        /* Searchable Part Dropdown Styles */
        .part-search-container { position: relative; overflow: visible; }
        .part-search {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .part-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 350px;
            max-height: 250px;
            overflow-y: scroll;
            overflow-x: hidden;
            background: white;
            border: 2px solid #4a90d9;
            border-radius: 4px;
            z-index: 9999;
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        /* Custom scrollbar for better visibility */
        .part-dropdown::-webkit-scrollbar {
            width: 12px;
        }
        .part-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .part-dropdown::-webkit-scrollbar-thumb {
            background: #4a90d9;
            border-radius: 4px;
            border: 2px solid #f1f1f1;
        }
        .part-dropdown::-webkit-scrollbar-thumb:hover {
            background: #357abd;
        }
        .part-option {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .part-option:hover { background: #e8f4fc; }
        .part-option.hidden { display: none; }
        .col-partno { width: 180px; }

        /* Ensure table doesn't clip dropdown */
        #itemsTable { overflow: visible; }
        #itemsTable td.part-search-container { overflow: visible; }
    </style>
</head>
<body>

<div class="content">
    <h1>Add Quotation</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #721c24;">
            <strong>Error:</strong>
            <ul style="margin: 5px 0 0 15px;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($leads)): ?>
        <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 20px; color: #856404;">
            <strong style="font-size: 1.1em;">No Warm Leads Available</strong>
            <p style="margin: 10px 0 5px 0;">To create a quotation, you need a lead with "WARM" status that doesn't already have a quotation linked to it.</p>
            <p style="margin: 0 0 15px 0; font-size: 0.9em; opacity: 0.85;"><em>Note: Leads with existing quotations are not displayed in the selection list.</em></p>
            <a href="/crm/index.php" class="btn btn-primary">Go to CRM Leads</a>
        </div>
    <?php endif; ?>

    <?php if (empty($parts)): ?>
        <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #856404;">
            <strong>No YID Parts Available!</strong><br>
            To create a quotation, you must first add parts with Part ID = "YID" to the Part Master.<br>
            <a href="/part_master/list.php" class="btn btn-primary" style="margin-top: 10px;">Go to Part Master</a>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="quote-form" onsubmit="return validateForm()">
        <input type="hidden" name="is_igst" id="is_igst" value="0">

        <!-- Quote Header Section -->
        <div class="form-section">
            <h3>Quote Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Quote No</label>
                    <input type="text" name="quote_no" value="<?= htmlspecialchars($nextQuoteNo) ?>" readonly required>
                </div>
                <div class="form-group">
                    <label>Lead Reference *</label>
                    <select id="lead_reference" name="reference" onchange="handleLeadSelection()">
                        <option value="">-- Select Lead --</option>
                        <?php if (!empty($warmLeads)): ?>
                        <optgroup label="WARM Leads (Ready for Quotation)">
                            <?php foreach ($warmLeads as $l): ?>
                            <option value="<?= htmlspecialchars($l['lead_no']) ?>"
                                    data-lead-id="<?= $l['id'] ?>"
                                    data-status="warm">
                                <?= htmlspecialchars($l['lead_no']) ?> - <?= htmlspecialchars($l['company_name'] ?? $l['contact_person']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($coldLeads)): ?>
                        <optgroup label="COLD Leads (Requires Conversion)">
                            <?php foreach ($coldLeads as $l): ?>
                            <option value="<?= htmlspecialchars($l['lead_no']) ?>"
                                    data-lead-id="<?= $l['id'] ?>"
                                    data-status="cold">
                                [COLD] <?= htmlspecialchars($l['lead_no']) ?> - <?= htmlspecialchars($l['company_name'] ?? $l['contact_person']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                    <div id="cold_lead_warning" style="display: none; margin-top: 10px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
                        <strong style="color: #856404;">⚠️ Cold Lead Selected</strong>
                        <p style="margin: 8px 0; color: #856404;">This is a Cold lead. To create a quotation, you must first convert it to Warm.</p>
                        <button type="button" id="convert_to_warm_btn" class="btn btn-warning" onclick="convertToWarm()">
                            Convert to Warm & Continue
                        </button>
                    </div>
                    <input type="hidden" id="lead_converted" name="lead_converted" value="0">
                </div>
                <div class="form-group">
                    <label>Customer ID (auto-populated)</label>
                    <input type="hidden" id="customer_id_field" name="customer_id">
                    <input type="text" id="customer_id_display" placeholder="Auto-filled from lead" readonly>
                </div>
            </div>

            <!-- Auto-populated Customer Details -->
            <div class="form-row">
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" id="company_name" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" id="contact_person" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="phone" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" id="email" placeholder="Auto-filled from lead" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>Address Line 1</label>
                    <input type="text" id="address1" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Address Line 2</label>
                    <input type="text" id="address2" placeholder="Auto-filled from lead" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="city" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" id="state" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" id="pincode" placeholder="Auto-filled from lead" readonly>
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" id="country" placeholder="Auto-filled from lead" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>GST Type</label>
                    <div id="gst-mode-indicator" style="padding: 8px 12px; border-radius: 4px; background: #d4edda; color: #155724; font-weight: bold;">
                        CGST + SGST (Intra-State: <?= htmlspecialchars($companyState) ?>)
                    </div>
                    <small style="color: #666;">Company State: <?= htmlspecialchars($companyState) ?> - GST type auto-selected based on customer state</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Quote Date *</label>
                    <input type="date" name="quote_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Validity Date</label>
                    <input type="date" name="validity_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="form-section">
            <h3>Items</h3>
            <div id="items-loaded-notice" style="display: none; background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
                <strong>Products loaded from lead!</strong> <span id="items-loaded-count"></span> item(s) have been added. You can edit, add more, or remove items below.
            </div>
            <div style="overflow: visible; margin-bottom: 15px;">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th class="col-partno">Part No</th>
                            <th class="col-name">Product Name</th>
                            <th class="col-desc">Description</th>
                            <th class="col-hsn">HSN</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-unit">Unit</th>
                            <th class="col-rate">Rate</th>
                            <th class="col-disc">Disc %</th>
                            <th class="col-taxable">Taxable</th>
                            <th class="col-cgst cgst-col">CGST</th>
                            <th class="col-sgst sgst-col">SGST</th>
                            <th class="col-igst igst-col" style="display:none;">IGST</th>
                            <th class="col-amt">Amount</th>
                            <th class="col-lead">Lead Time</th>
                            <th class="col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row">
                            <td class="part-search-container">
                                <input type="text" class="part-search" placeholder="Search Part No..." autocomplete="off" onfocus="showPartDropdown(this)" oninput="filterParts(this)">
                                <input type="hidden" name="part_no[]" class="part-no-hidden">
                                <div class="part-dropdown" style="display:none;">
                                    <?php foreach ($parts as $p): ?>
                                        <div class="part-option"
                                            data-part-no="<?= htmlspecialchars($p['part_no']) ?>"
                                            data-part-id="<?= htmlspecialchars($p['part_id'] ?? '') ?>"
                                            data-name="<?= htmlspecialchars($p['part_name']) ?>"
                                            data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                            data-hsn="<?= htmlspecialchars($p['hsn_code'] ?? '') ?>"
                                            data-uom="<?= htmlspecialchars($p['uom']) ?>"
                                            data-rate="<?= $p['rate'] ?>"
                                            data-gst="<?= $p['gst'] ?>"
                                            onclick="selectPart(this)">
                                            <?= htmlspecialchars($p['part_no']) ?> - <?= htmlspecialchars($p['part_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><input type="text" name="part_name[]" class="part-name" readonly></td>
                            <td><input type="text" name="description[]" class="description" placeholder="Optional details..."></td>
                            <td><input type="text" name="hsn_code[]" class="hsn-code" readonly></td>
                            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0" value="1" onchange="calcRow(this)"></td>
                            <td><input type="text" name="unit[]" class="unit" readonly></td>
                            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" onchange="calcRow(this)"></td>
                            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="0" onchange="calcRow(this)"></td>
                            <td><input type="number" name="taxable[]" class="taxable" step="0.01" readonly></td>
                            <td class="cgst-col"><input type="text" name="cgst[]" class="cgst" readonly></td>
                            <td class="sgst-col"><input type="text" name="sgst[]" class="sgst" readonly></td>
                            <td class="igst-col" style="display:none;"><input type="text" name="igst[]" class="igst" readonly></td>
                            <td><input type="number" name="amount[]" class="amount" step="0.01" readonly></td>
                            <td><input type="text" name="lead_time[]" class="lead-time" placeholder="e.g., 2 weeks"></td>
                            <td>
                                <input type="hidden" name="gst_percent[]" class="gst-percent" value="0">
                                <button type="button" onclick="removeRow(this)" class="btn btn-danger" style="padding: 2px 8px;">-</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td colspan="8" style="text-align: right;"><strong>Totals:</strong></td>
                            <td><input type="text" id="totalTaxable" readonly></td>
                            <td class="cgst-col"><input type="text" id="totalCGST" readonly></td>
                            <td class="sgst-col"><input type="text" id="totalSGST" readonly></td>
                            <td class="igst-col" style="display:none;"><input type="text" id="totalIGST" readonly></td>
                            <td><input type="text" id="grandTotal" readonly></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="button" onclick="addRow()" class="btn btn-secondary btn-add-row">+ Add Item</button>
        </div>

        <!-- Terms, Notes, Payment -->
        <div class="form-section">
            <h3>Additional Information</h3>
            <?php if (!empty($paymentTerms)): ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Terms</label>
                    <select name="payment_terms_id" id="payment_terms_select" onchange="updatePaymentTermsDescription()">
                        <option value="">-- Select Payment Terms --</option>
                        <?php foreach ($paymentTerms as $term): ?>
                            <option value="<?= $term['id'] ?>"
                                    data-description="<?= htmlspecialchars($term['term_description'] ?? '') ?>"
                                    <?= $term['is_default'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term['term_name']) ?>
                                <?php if ($term['days'] > 0): ?> (<?= $term['days'] ?> days)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="payment_terms_desc" style="color: #666; display: block; margin-top: 5px;"></small>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Terms & Conditions</label>
                    <textarea name="terms_conditions" placeholder="Enter terms and conditions..."><?= htmlspecialchars($settings['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Details / Bank Information</label>
                    <textarea name="payment_details" placeholder="Bank details..."><?php
                        $bankDetails = [];
                        if (!empty($settings['bank_name'])) $bankDetails[] = "Bank: " . htmlspecialchars($settings['bank_name']);
                        if (!empty($settings['bank_account'])) $bankDetails[] = "Account: " . htmlspecialchars($settings['bank_account']);
                        if (!empty($settings['bank_ifsc'])) $bankDetails[] = "IFSC: " . htmlspecialchars($settings['bank_ifsc']);
                        if (!empty($settings['bank_branch'])) $bankDetails[] = "Branch: " . htmlspecialchars($settings['bank_branch']);
                        echo implode("\n", $bankDetails);
                    ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Attachment</label>
                    <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small style="color: #666;">Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</small>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Save Quotation</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<!-- Hidden template row -->
<table style="display:none;">
    <tbody>
        <tr id="templateRow" class="item-row">
            <td class="part-search-container">
                <input type="text" class="part-search" placeholder="Search Part No..." autocomplete="off" onfocus="showPartDropdown(this)" oninput="filterParts(this)" disabled>
                <input type="hidden" name="part_no[]" class="part-no-hidden" disabled>
                <div class="part-dropdown" style="display:none;">
                    <?php foreach ($parts as $p): ?>
                        <div class="part-option"
                            data-part-no="<?= htmlspecialchars($p['part_no']) ?>"
                            data-part-id="<?= htmlspecialchars($p['part_id'] ?? '') ?>"
                            data-name="<?= htmlspecialchars($p['part_name']) ?>"
                            data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                            data-hsn="<?= htmlspecialchars($p['hsn_code'] ?? '') ?>"
                            data-uom="<?= htmlspecialchars($p['uom']) ?>"
                            data-rate="<?= $p['rate'] ?>"
                            data-gst="<?= $p['gst'] ?>"
                            onclick="selectPart(this)">
                            <?= htmlspecialchars($p['part_no']) ?> - <?= htmlspecialchars($p['part_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>
            <td><input type="text" name="part_name[]" class="part-name" readonly disabled></td>
            <td><input type="text" name="description[]" class="description" placeholder="Optional details..." disabled></td>
            <td><input type="text" name="hsn_code[]" class="hsn-code" readonly disabled></td>
            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0" value="1" onchange="calcRow(this)" disabled></td>
            <td><input type="text" name="unit[]" class="unit" readonly disabled></td>
            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="taxable[]" class="taxable" step="0.01" readonly disabled></td>
            <td class="cgst-col"><input type="text" name="cgst[]" class="cgst" readonly disabled></td>
            <td class="sgst-col"><input type="text" name="sgst[]" class="sgst" readonly disabled></td>
            <td class="igst-col" style="display:none;"><input type="text" name="igst[]" class="igst" readonly disabled></td>
            <td><input type="number" name="amount[]" class="amount" step="0.01" readonly disabled></td>
            <td><input type="text" name="lead_time[]" class="lead-time" placeholder="e.g., 2 weeks" disabled></td>
            <td>
                <input type="hidden" name="gst_percent[]" class="gst-percent" value="0" disabled>
                <button type="button" onclick="removeRow(this)" class="btn btn-danger" style="padding: 2px 8px;">-</button>
            </td>
        </tr>
    </tbody>
</table>

<script>
// Track if selected lead is cold and needs conversion
let selectedLeadStatus = '';
let selectedLeadId = '';

// Form validation - ensure at least one valid part is selected AND lead is not cold
function validateForm() {
    // Check if lead is still cold (not converted)
    if (selectedLeadStatus === 'cold' && document.getElementById('lead_converted').value !== '1') {
        alert('You must convert the Cold lead to Warm before creating a quotation.\n\nPlease click "Convert to Warm & Continue" button.');
        return false;
    }

    const partNoInputs = document.querySelectorAll('input[name="part_no[]"]');
    let hasValidItem = false;

    partNoInputs.forEach(input => {
        if (input.value && input.value.trim() !== '') {
            hasValidItem = true;
        }
    });

    if (!hasValidItem) {
        alert('At least one item with a valid part must be selected.\n\nPlease select a part from the dropdown for at least one item row.');
        return false;
    }

    return true;
}

// Handle lead selection - check if cold and show warning
function handleLeadSelection() {
    const select = document.getElementById('lead_reference');
    const selectedOption = select.options[select.selectedIndex];
    const warning = document.getElementById('cold_lead_warning');

    if (!selectedOption || !selectedOption.value) {
        warning.style.display = 'none';
        selectedLeadStatus = '';
        selectedLeadId = '';
        document.getElementById('lead_converted').value = '0';
        clearLeadFields();
        return;
    }

    selectedLeadStatus = selectedOption.getAttribute('data-status');
    selectedLeadId = selectedOption.getAttribute('data-lead-id');

    if (selectedLeadStatus === 'cold') {
        warning.style.display = 'block';
        document.getElementById('lead_converted').value = '0';
    } else {
        warning.style.display = 'none';
        document.getElementById('lead_converted').value = '1';
    }

    // Load lead details
    loadLeadDetails();
}

// Convert cold lead to warm via AJAX
function convertToWarm() {
    if (!selectedLeadId) {
        alert('No lead selected');
        return;
    }

    const btn = document.getElementById('convert_to_warm_btn');
    btn.disabled = true;
    btn.textContent = 'Converting...';

    fetch('/api/convert_lead_to_warm.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ lead_id: selectedLeadId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI to show conversion successful
            document.getElementById('cold_lead_warning').innerHTML = `
                <div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">
                    <strong>✓ Lead Converted to Warm</strong>
                    <p style="margin: 5px 0 0 0;">You can now continue creating the quotation.</p>
                </div>
            `;
            document.getElementById('lead_converted').value = '1';
            selectedLeadStatus = 'warm';

            // Update the option text to remove [COLD] prefix
            const select = document.getElementById('lead_reference');
            const selectedOption = select.options[select.selectedIndex];
            selectedOption.setAttribute('data-status', 'warm');
            selectedOption.textContent = selectedOption.textContent.replace('[COLD] ', '');
        } else {
            alert('Failed to convert lead: ' + (data.error || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Convert to Warm & Continue';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error converting lead. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Convert to Warm & Continue';
    });
}

// Searchable Part Dropdown Functions
function showPartDropdown(input) {
    const container = input.closest('.part-search-container');
    const dropdown = container.querySelector('.part-dropdown');
    dropdown.style.display = 'block';

    // Show all options when first focused
    const options = dropdown.querySelectorAll('.part-option');
    options.forEach(opt => opt.classList.remove('hidden'));
}

function filterParts(input) {
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

function selectPart(option) {
    const container = option.closest('.part-search-container');
    const row = option.closest('tr');
    const searchInput = container.querySelector('.part-search');
    const hiddenInput = container.querySelector('.part-no-hidden');
    const dropdown = container.querySelector('.part-dropdown');

    // Set values
    searchInput.value = option.dataset.partNo + ' - ' + option.dataset.name;
    hiddenInput.value = option.dataset.partNo;

    // Populate other fields
    row.querySelector('.part-name').value = option.dataset.name || '';
    row.querySelector('.description').value = option.dataset.description || '';
    row.querySelector('.hsn-code').value = option.dataset.hsn || '';
    row.querySelector('.unit').value = option.dataset.uom || '';
    row.querySelector('.rate').value = option.dataset.rate || 0;
    row.querySelector('.gst-percent').value = option.dataset.gst || 0;

    // Hide dropdown
    dropdown.style.display = 'none';

    // Calculate row
    calcRow(searchInput);
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.part-search-container')) {
        document.querySelectorAll('.part-dropdown').forEach(d => d.style.display = 'none');
    }
});

function partChanged(select) {
    const row = select.closest('tr');
    const opt = select.options[select.selectedIndex];

    if (opt.value) {
        row.querySelector('.part-name').value = opt.dataset.name || '';
        row.querySelector('.hsn-code').value = opt.dataset.hsn || '';
        row.querySelector('.unit').value = opt.dataset.uom || '';
        row.querySelector('.rate').value = opt.dataset.rate || 0;
        row.querySelector('.gst-percent').value = opt.dataset.gst || 0;
    } else {
        row.querySelector('.part-name').value = '';
        row.querySelector('.hsn-code').value = '';
        row.querySelector('.unit').value = '';
        row.querySelector('.rate').value = '';
        row.querySelector('.gst-percent').value = 0;
    }

    calcRow(select);
}

// Company state from PHP
const companyState = '<?= addslashes($companyState) ?>';
let isIGST = false;

function calcRow(elem) {
    const row = elem.closest('tr');

    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const rate = parseFloat(row.querySelector('.rate').value) || 0;
    const discount = parseFloat(row.querySelector('.discount').value) || 0;
    const gstPercent = parseFloat(row.querySelector('.gst-percent').value) || 0;

    const gross = qty * rate;
    const discountAmt = gross * (discount / 100);
    const taxable = gross - discountAmt;

    let cgstAmt = 0, sgstAmt = 0, igstAmt = 0, total = 0;

    if (isIGST) {
        // Inter-state: Full GST as IGST
        igstAmt = taxable * (gstPercent / 100);
        total = taxable + igstAmt;
        row.querySelector('.cgst').value = '0.00 (0%)';
        row.querySelector('.sgst').value = '0.00 (0%)';
        row.querySelector('.igst').value = igstAmt.toFixed(2) + ' (' + gstPercent.toFixed(1) + '%)';
    } else {
        // Intra-state: Split GST 50-50 as CGST/SGST
        const cgstPercent = gstPercent / 2;
        const sgstPercent = gstPercent / 2;
        cgstAmt = taxable * (cgstPercent / 100);
        sgstAmt = taxable * (sgstPercent / 100);
        total = taxable + cgstAmt + sgstAmt;
        row.querySelector('.cgst').value = cgstAmt.toFixed(2) + ' (' + cgstPercent.toFixed(1) + '%)';
        row.querySelector('.sgst').value = sgstAmt.toFixed(2) + ' (' + sgstPercent.toFixed(1) + '%)';
        row.querySelector('.igst').value = '0.00 (0%)';
    }

    row.querySelector('.taxable').value = taxable.toFixed(2);
    row.querySelector('.amount').value = total.toFixed(2);

    calcTotals();
}

function calcTotals() {
    let totalTaxable = 0;
    let totalCGST = 0;
    let totalSGST = 0;
    let totalIGST = 0;
    let grandTotal = 0;

    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const taxable = parseFloat(row.querySelector('.taxable').value) || 0;
        const amount = parseFloat(row.querySelector('.amount').value) || 0;
        const gstPercent = parseFloat(row.querySelector('.gst-percent').value) || 0;

        if (isIGST) {
            const igstAmt = taxable * (gstPercent / 100);
            totalIGST += igstAmt;
        } else {
            const cgstAmt = taxable * ((gstPercent / 2) / 100);
            const sgstAmt = taxable * ((gstPercent / 2) / 100);
            totalCGST += cgstAmt;
            totalSGST += sgstAmt;
        }

        totalTaxable += taxable;
        grandTotal += amount;
    });

    document.getElementById('totalTaxable').value = totalTaxable.toFixed(2);
    document.getElementById('totalCGST').value = totalCGST.toFixed(2);
    document.getElementById('totalSGST').value = totalSGST.toFixed(2);
    document.getElementById('totalIGST').value = totalIGST.toFixed(2);
    document.getElementById('grandTotal').value = grandTotal.toFixed(2);
}

function addRow() {
    const template = document.getElementById('templateRow');
    const clone = template.cloneNode(true);
    clone.removeAttribute('id');

    // Enable all inputs
    clone.querySelectorAll('input, select').forEach(el => {
        el.disabled = false;
    });

    // Clear values for searchable part dropdown
    clone.querySelector('.part-search').value = '';
    clone.querySelector('.part-no-hidden').value = '';
    clone.querySelector('.part-name').value = '';
    clone.querySelector('.description').value = '';
    clone.querySelector('.hsn-code').value = '';
    clone.querySelector('.qty').value = '1';
    clone.querySelector('.unit').value = '';
    clone.querySelector('.rate').value = '';
    clone.querySelector('.discount').value = '0';
    clone.querySelector('.taxable').value = '';
    clone.querySelector('.cgst').value = '';
    clone.querySelector('.sgst').value = '';
    clone.querySelector('.igst').value = '';
    clone.querySelector('.amount').value = '';
    clone.querySelector('.lead-time').value = '';
    clone.querySelector('.gst-percent').value = '0';

    // Apply current GST mode visibility
    if (isIGST) {
        clone.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'none');
        clone.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'none');
        clone.querySelectorAll('.igst-col').forEach(el => el.style.display = 'table-cell');
    } else {
        clone.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'table-cell');
        clone.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'table-cell');
        clone.querySelectorAll('.igst-col').forEach(el => el.style.display = 'none');
    }

    document.getElementById('itemsBody').appendChild(clone);
}

function removeRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('itemsBody');
    const rows = tbody.querySelectorAll('.item-row');

    if (rows.length <= 1) {
        // Clear values instead of removing
        row.querySelector('.part-search').value = '';
        row.querySelector('.part-no-hidden').value = '';
        row.querySelector('.part-name').value = '';
        row.querySelector('.description').value = '';
        row.querySelector('.hsn-code').value = '';
        row.querySelector('.qty').value = '1';
        row.querySelector('.unit').value = '';
        row.querySelector('.rate').value = '';
        row.querySelector('.discount').value = '0';
        row.querySelector('.taxable').value = '';
        row.querySelector('.cgst').value = '';
        row.querySelector('.sgst').value = '';
        row.querySelector('.igst').value = '';
        row.querySelector('.amount').value = '';
        row.querySelector('.lead-time').value = '';
        row.querySelector('.gst-percent').value = '0';
        calcTotals();
        return;
    }

    row.remove();
    calcTotals();
}

// Load lead details when a lead is selected
function loadLeadDetails() {
    const select = document.getElementById('lead_reference');
    const selectedOption = select.options[select.selectedIndex];
    const leadId = selectedOption.getAttribute('data-lead-id');

    if (!leadId) {
        // Clear all fields if no lead selected
        clearLeadFields();
        return;
    }

    // Fetch lead details from API
    fetch(`/api/get_lead_details.php?lead_id=${encodeURIComponent(leadId)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.id) {
                // Populate all fields
                document.getElementById('company_name').value = data.company_name || '';
                document.getElementById('contact_person').value = data.contact_person || '';
                document.getElementById('phone').value = data.phone || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('address1').value = data.address1 || '';
                document.getElementById('address2').value = data.address2 || '';
                document.getElementById('city').value = data.city || '';
                document.getElementById('state').value = data.state || '';
                document.getElementById('pincode').value = data.pincode || '';
                document.getElementById('country').value = data.country || 'India';

                // Set customer_id if it exists (lead from customer database)
                if (data.customer_id) {
                    document.getElementById('customer_id_field').value = data.customer_id;
                    document.getElementById('customer_id_display').value = data.customer_id;
                } else {
                    document.getElementById('customer_id_field').value = '';
                    document.getElementById('customer_id_display').value = '';
                }

                // Update GST mode based on customer state
                updateGSTMode(data.state || '');

                // Auto-populate items from lead requirements
                if (data.requirements && data.requirements.length > 0) {
                    populateItemsFromRequirements(data.requirements);
                }
            } else {
                clearLeadFields();
            }
        })
        .catch(error => {
            console.error('Error loading lead details:', error);
            clearLeadFields();
        });
}

// Populate quotation items from lead requirements
function populateItemsFromRequirements(requirements) {
    const tbody = document.getElementById('itemsBody');
    const notice = document.getElementById('items-loaded-notice');
    const countSpan = document.getElementById('items-loaded-count');

    // Clear existing rows
    tbody.innerHTML = '';

    requirements.forEach((req, index) => {
        // Clone template row
        const template = document.getElementById('templateRow');
        const clone = template.cloneNode(true);
        clone.removeAttribute('id');
        clone.classList.add('item-row');

        // Enable all inputs
        clone.querySelectorAll('input, select').forEach(el => {
            el.disabled = false;
        });

        // Set values from requirement
        const partNo = req.part_no || '';
        const partName = req.part_name || req.product_name || '';
        const hsnCode = req.hsn_code || '';
        const qty = req.estimated_qty || 1;
        const unit = req.uom || req.unit || '';
        const rate = req.rate || req.target_price || 0;
        const gstPercent = req.gst || 0;

        // Set display value for part search
        clone.querySelector('.part-search').value = partNo + (partName ? ' - ' + partName : '');
        clone.querySelector('.part-no-hidden').value = partNo;
        clone.querySelector('.part-name').value = partName;
        clone.querySelector('.hsn-code').value = hsnCode;
        clone.querySelector('.qty').value = qty;
        clone.querySelector('.unit').value = unit;
        clone.querySelector('.rate').value = rate;
        clone.querySelector('.discount').value = 0;
        clone.querySelector('.gst-percent').value = gstPercent;

        tbody.appendChild(clone);

        // Calculate row after appending
        const rateInput = clone.querySelector('.rate');
        calcRow(rateInput);
    });

    // If no requirements, add an empty row
    if (requirements.length === 0) {
        addRow();
        notice.style.display = 'none';
    } else {
        // Show notification
        countSpan.textContent = requirements.length;
        notice.style.display = 'block';
    }

    calcTotals();
}

function clearLeadFields() {
    document.getElementById('company_name').value = '';
    document.getElementById('contact_person').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('email').value = '';
    document.getElementById('address1').value = '';
    document.getElementById('address2').value = '';
    document.getElementById('city').value = '';
    document.getElementById('state').value = '';
    document.getElementById('pincode').value = '';
    document.getElementById('country').value = '';
    document.getElementById('customer_id_field').value = '';
    document.getElementById('customer_id_display').value = '';

    // Hide the items loaded notice
    const notice = document.getElementById('items-loaded-notice');
    if (notice) {
        notice.style.display = 'none';
    }

    // Reset to CGST/SGST mode (same state)
    updateGSTMode('');
}

// Update GST mode based on customer state
function updateGSTMode(customerState) {
    const table = document.getElementById('itemsTable');
    const isIgstField = document.getElementById('is_igst');
    const indicator = document.getElementById('gst-mode-indicator');

    // Normalize states for comparison (trim and lowercase)
    const normalizedCompanyState = companyState.trim().toLowerCase();
    const normalizedCustomerState = customerState ? customerState.trim().toLowerCase() : '';

    // If customer state is different from company state, use IGST
    if (normalizedCustomerState && normalizedCompanyState !== normalizedCustomerState) {
        isIGST = true;
        isIgstField.value = '1';
        table.classList.add('igst-mode');
        table.classList.remove('cgst-sgst-mode');

        // Show IGST columns, hide CGST/SGST columns
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'table-cell');

        // Update indicator
        indicator.innerHTML = 'IGST (Inter-State: ' + customerState + ')';
        indicator.style.background = '#fff3cd';
        indicator.style.color = '#856404';

        console.log('GST Mode: IGST (Inter-state) - Customer: ' + customerState + ', Company: ' + companyState);
    } else {
        isIGST = false;
        isIgstField.value = '0';
        table.classList.add('cgst-sgst-mode');
        table.classList.remove('igst-mode');

        // Show CGST/SGST columns, hide IGST columns
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'none');

        // Update indicator
        indicator.innerHTML = 'CGST + SGST (Intra-State: ' + companyState + ')';
        indicator.style.background = '#d4edda';
        indicator.style.color = '#155724';

        console.log('GST Mode: CGST/SGST (Intra-state) - Customer: ' + (customerState || 'Not set') + ', Company: ' + companyState);
    }

    // Recalculate all rows
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const rateInput = row.querySelector('.rate');
        if (rateInput) {
            calcRow(rateInput);
        }
    });
}

// Initialize
calcTotals();

// Payment terms description display
function updatePaymentTermsDescription() {
    var select = document.getElementById('payment_terms_select');
    var descElement = document.getElementById('payment_terms_desc');
    if (select && descElement) {
        var selectedOption = select.options[select.selectedIndex];
        var description = selectedOption.getAttribute('data-description') || '';
        descElement.textContent = description;
    }
}
// Call on page load to show default description
updatePaymentTermsDescription();
</script>

</body>
</html>
