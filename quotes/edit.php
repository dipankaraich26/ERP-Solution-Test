<?php
include "../db.php";

$errors = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch company settings for GST state
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$companyState = $settings['state'] ?? 'Maharashtra';

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch existing quotation
$stmt = $pdo->prepare("SELECT * FROM quote_master WHERE id = ?");
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header("Location: index.php");
    exit;
}

// Prevent editing released quotations
if ($quote['status'] === 'released') {
    header("Location: view.php?id=" . $id);
    exit;
}

// Fetch existing items
$itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
$itemsStmt->execute([$id]);
$existingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers for dropdown with state for GST calculation
$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name, state
    FROM customers
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

// Determine current is_igst value from quote
$currentIsIgst = isset($quote['is_igst']) ? (int)$quote['is_igst'] : 0;

// Fetch only YID parts for dropdown (same as add.php)
$parts = $pdo->query("
    SELECT part_no, part_name, part_id, description, hsn_code, uom, rate, gst
    FROM part_master
    WHERE status = 'active' AND UPPER(part_id) = 'YID'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active payment terms
$paymentTerms = [];
try {
    $paymentTerms = $pdo->query("SELECT * FROM payment_terms WHERE is_active = 1 ORDER BY sort_order, term_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = trim($_POST['customer_id'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $quote_date = trim($_POST['quote_date'] ?? '');
    $validity_date = trim($_POST['validity_date'] ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment_details = trim($_POST['payment_details'] ?? '');
    $payment_terms_id = !empty($_POST['payment_terms_id']) ? (int)$_POST['payment_terms_id'] : null;
    $status = trim($_POST['status'] ?? 'draft');

    // Validation
    if ($customer_id === '') {
        $errors[] = "Customer is required";
    }
    if ($quote_date === '') {
        $errors[] = "Quote Date is required";
    }

    // Check for items - ensure at least one valid part is selected
    $hasValidItem = false;
    if (!empty($_POST['part_no']) && is_array($_POST['part_no'])) {
        foreach ($_POST['part_no'] as $partNo) {
            if (!empty(trim($partNo))) {
                $hasValidItem = true;
                break;
            }
        }
    }
    if (!$hasValidItem) {
        $errors[] = "At least one item with a valid part must be selected";
    }

    // Handle file upload
    $attachmentPath = $quote['attachment_path'];
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
            $fileName = "QUOTE_" . preg_replace('/[^a-zA-Z0-9]/', '_', $quote['quote_no']) . "_" . time() . "." . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                $attachmentPath = "uploads/quotes/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Determine if IGST mode
            $is_igst = isset($_POST['is_igst']) && $_POST['is_igst'] == '1' ? 1 : 0;

            // Update quote master
            $stmt = $pdo->prepare("
                UPDATE quote_master
                SET customer_id = ?, reference = ?, quote_date = ?, validity_date = ?,
                    terms_conditions = ?, notes = ?, payment_details = ?, payment_terms_id = ?, attachment_path = ?, is_igst = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $customer_id,
                $reference,
                $quote_date,
                $validity_date ?: null,
                $terms_conditions,
                $notes,
                $payment_details,
                $payment_terms_id,
                $attachmentPath,
                $is_igst,
                $status,
                $id
            ]);

            // Delete existing items
            $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$id]);

            // Insert new items
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
                if ($qty <= 0) $qty = 1;
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
                    $id,
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

            // Note: Lead status is updated to HOT when PI is released (not when quotation is accepted)
            // Workflow: Cold → Warm (quotation created) → Hot (PI released) → Converted (Invoice released)

            $pdo->commit();
            header("Location: view.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Quotation - <?= htmlspecialchars($quote['quote_no']) ?></title>
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

        .btn-add-row { margin-top: 10px; }
        .totals-row { font-weight: bold; background: #e8f4e8 !important; }
    </style>
</head>
<body>

<div class="content">
    <h1>Edit Quotation - <?= htmlspecialchars($quote['quote_no']) ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="quote-form" onsubmit="return validateForm()">
        <input type="hidden" name="is_igst" id="is_igst" value="<?= $currentIsIgst ?>">

        <!-- Quote Header Section -->
        <div class="form-section">
            <h3>Quote Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Quote No</label>
                    <input type="text" value="<?= htmlspecialchars($quote['quote_no']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Customer *</label>
                    <select name="customer_id" id="customer_select" required onchange="updateGSTMode()">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_id']) ?>"
                                data-state="<?= htmlspecialchars($c['state'] ?? '') ?>"
                                <?= $c['customer_id'] === $quote['customer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['company_name']) ?>
                                (<?= htmlspecialchars($c['customer_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reference</label>
                    <input type="text" name="reference" value="<?= htmlspecialchars($quote['reference'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quote Date *</label>
                    <input type="date" name="quote_date" value="<?= htmlspecialchars($quote['quote_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Validity Date</label>
                    <input type="date" name="validity_date" value="<?= htmlspecialchars($quote['validity_date'] ?? '') ?>">
                </div>
                <div class="form-group" id="status">
                    <label style="font-size: 1.1em;">Status</label>
                    <select name="status" style="border: 2px solid #4a90d9; padding: 10px; font-size: 1em;">
                        <option value="draft" <?= $quote['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $quote['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="accepted" <?= $quote['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="rejected" <?= $quote['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="expired" <?= $quote['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                    <small style="color: #155724; background: #d4edda; padding: 5px 10px; border-radius: 4px; display: block; margin-top: 5px;">
                        Set to "Accepted" to enable Release as PI
                    </small>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="form-section">
            <h3>Items</h3>
            <div style="overflow: visible; margin-bottom: 15px;">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th class="col-partno">Part No</th>
                            <th>Product Name</th>
                            <th>Description</th>
                            <th>HSN</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Rate</th>
                            <th>Disc %</th>
                            <th>Taxable</th>
                            <th class="cgst-col">CGST</th>
                            <th class="sgst-col">SGST</th>
                            <th class="igst-col" style="display:none;">IGST</th>
                            <th>Amount</th>
                            <th>Lead Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($existingItems as $item): ?>
                        <tr class="item-row">
                            <td class="part-search-container">
                                <input type="text" class="part-search" placeholder="Search Part No..." autocomplete="off" onfocus="showPartDropdown(this)" oninput="filterParts(this)" value="<?= htmlspecialchars($item['part_no']) ?> - <?= htmlspecialchars($item['part_name']) ?>">
                                <input type="hidden" name="part_no[]" class="part-no-hidden" value="<?= htmlspecialchars($item['part_no']) ?>">
                                <div class="part-dropdown" style="display:none;">
                                    <?php foreach ($parts as $p): ?>
                                        <div class="part-option"
                                            data-part-no="<?= htmlspecialchars($p['part_no']) ?>"
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
                            <td><input type="text" name="part_name[]" class="part-name" value="<?= htmlspecialchars($item['part_name']) ?>" readonly></td>
                            <td><input type="text" name="description[]" class="description" value="<?= htmlspecialchars($item['description'] ?? '') ?>" placeholder="Optional details..."></td>
                            <td><input type="text" name="hsn_code[]" class="hsn-code" value="<?= htmlspecialchars($item['hsn_code'] ?? '') ?>" readonly></td>
                            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0.001" value="<?= $item['qty'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="text" name="unit[]" class="unit" value="<?= htmlspecialchars($item['unit'] ?? '') ?>" readonly></td>
                            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" value="<?= $item['rate'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="<?= $item['discount'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="number" name="taxable[]" class="taxable" step="0.01" value="<?= $item['taxable_amount'] ?>" readonly></td>
                            <td class="cgst-col"><input type="text" name="cgst[]" class="cgst" value="<?= number_format($item['cgst_amount'], 2) ?> (<?= $item['cgst_percent'] ?>%)" readonly></td>
                            <td class="sgst-col"><input type="text" name="sgst[]" class="sgst" value="<?= number_format($item['sgst_amount'], 2) ?> (<?= $item['sgst_percent'] ?>%)" readonly></td>
                            <td class="igst-col" style="display:none;"><input type="text" name="igst[]" class="igst" value="<?= number_format($item['igst_amount'] ?? 0, 2) ?> (<?= $item['igst_percent'] ?? 0 ?>%)" readonly></td>
                            <td><input type="number" name="amount[]" class="amount" step="0.01" value="<?= $item['total_amount'] ?>" readonly></td>
                            <td><input type="text" name="lead_time[]" class="lead-time" value="<?= htmlspecialchars($item['lead_time'] ?? '') ?>"></td>
                            <td>
                                <input type="hidden" name="gst_percent[]" class="gst-percent" value="<?= $currentIsIgst ? $item['igst_percent'] : ($item['cgst_percent'] + $item['sgst_percent']) ?>">
                                <button type="button" onclick="removeRow(this)" class="btn btn-danger" style="padding: 2px 8px;">-</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                                    <?= ($quote['payment_terms_id'] ?? null) == $term['id'] ? 'selected' : '' ?>>
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
                    <textarea name="terms_conditions"><?= htmlspecialchars($quote['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes"><?= htmlspecialchars($quote['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Details / Bank Information</label>
                    <textarea name="payment_details"><?= htmlspecialchars($quote['payment_details'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Attachment</label>
                    <?php if ($quote['attachment_path']): ?>
                        <p><a href="../<?= htmlspecialchars($quote['attachment_path']) ?>" target="_blank">Current attachment</a></p>
                    <?php endif; ?>
                    <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small style="color: #666;">Upload new file to replace existing. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</small>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Update Quotation</button>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
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
            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0.001" value="1" onchange="calcRow(this)" disabled></td>
            <td><input type="text" name="unit[]" class="unit" readonly disabled></td>
            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="taxable[]" class="taxable" step="0.01" readonly disabled></td>
            <td class="cgst-col"><input type="text" name="cgst[]" class="cgst" readonly disabled></td>
            <td class="sgst-col"><input type="text" name="sgst[]" class="sgst" readonly disabled></td>
            <td class="igst-col" style="display:none;"><input type="text" name="igst[]" class="igst" readonly disabled></td>
            <td><input type="number" name="amount[]" class="amount" step="0.01" readonly disabled></td>
            <td><input type="text" name="lead_time[]" class="lead-time" disabled></td>
            <td>
                <input type="hidden" name="gst_percent[]" class="gst-percent" value="0" disabled>
                <button type="button" onclick="removeRow(this)" class="btn btn-danger" style="padding: 2px 8px;">-</button>
            </td>
        </tr>
    </tbody>
</table>

<script>
// Company state from PHP
const companyState = '<?= addslashes($companyState) ?>';
let isIGST = <?= $currentIsIgst ? 'true' : 'false' ?>;

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

// Update GST mode based on customer state
function updateGSTMode() {
    const select = document.getElementById('customer_select');
    const selectedOption = select.options[select.selectedIndex];
    const customerState = selectedOption ? selectedOption.dataset.state || '' : '';
    const isIgstField = document.getElementById('is_igst');

    // Normalize states for comparison (trim and lowercase)
    const normalizedCompanyState = companyState.trim().toLowerCase();
    const normalizedCustomerState = customerState.trim().toLowerCase();

    // If customer state is different from company state, use IGST
    if (normalizedCustomerState && normalizedCompanyState !== normalizedCustomerState) {
        isIGST = true;
        isIgstField.value = '1';

        // Show IGST columns, hide CGST/SGST columns
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'table-cell');

        console.log('GST Mode: IGST (Inter-state) - Customer: ' + customerState + ', Company: ' + companyState);
    } else {
        isIGST = false;
        isIgstField.value = '0';

        // Show CGST/SGST columns, hide IGST columns
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'none');

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

// Initialize GST mode based on current is_igst value
function initGSTMode() {
    if (isIGST) {
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'table-cell');
    } else {
        document.querySelectorAll('.cgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.sgst-col').forEach(el => el.style.display = 'table-cell');
        document.querySelectorAll('.igst-col').forEach(el => el.style.display = 'none');
    }
}

// Initialize GST mode and totals
initGSTMode();
calcTotals();

// Form validation before submit
function validateForm() {
    // Check if at least one item has a valid part number
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
// Call on page load to show current description
updatePaymentTermsDescription();

// Highlight status field if accessed via #status anchor
if (window.location.hash === '#status') {
    const statusDiv = document.getElementById('status');
    if (statusDiv) {
        statusDiv.style.background = '#fff3cd';
        statusDiv.style.padding = '15px';
        statusDiv.style.borderRadius = '8px';
        statusDiv.style.border = '3px solid #ffc107';
        statusDiv.style.animation = 'pulse 1s ease-in-out 3';

        // Scroll to status field
        setTimeout(function() {
            statusDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
}
</script>

<style>
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { box-shadow: 0 0 0 15px rgba(255, 193, 7, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}
</style>

</body>
</html>
