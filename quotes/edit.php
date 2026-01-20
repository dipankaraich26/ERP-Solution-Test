<?php
include "../db.php";

$errors = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Fetch existing items
$itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
$itemsStmt->execute([$id]);
$existingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers for dropdown
$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name
    FROM customers
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch parts for dropdown
$parts = $pdo->query("
    SELECT part_no, part_name, hsn_code, uom, rate, gst
    FROM part_master
    WHERE status = 'active'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = trim($_POST['customer_id'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $quote_date = trim($_POST['quote_date'] ?? '');
    $validity_date = trim($_POST['validity_date'] ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment_details = trim($_POST['payment_details'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');

    // Validation
    if ($customer_id === '') {
        $errors[] = "Customer is required";
    }
    if ($quote_date === '') {
        $errors[] = "Quote Date is required";
    }

    // Check for items
    if (empty($_POST['part_no']) || !is_array($_POST['part_no'])) {
        $errors[] = "At least one item is required";
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
            // Update quote master
            $stmt = $pdo->prepare("
                UPDATE quote_master
                SET customer_id = ?, reference = ?, quote_date = ?, validity_date = ?,
                    terms_conditions = ?, notes = ?, payment_details = ?, attachment_path = ?, status = ?
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
                $attachmentPath,
                $status,
                $id
            ]);

            // Delete existing items
            $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$id]);

            // Insert new items
            $itemStmt = $pdo->prepare("
                INSERT INTO quote_items
                (quote_id, part_no, part_name, hsn_code, qty, unit, rate, discount, taxable_amount, cgst_percent, cgst_amount, sgst_percent, sgst_amount, total_amount, lead_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['part_no'] as $i => $part_no) {
                if (empty($part_no)) continue;

                $part_name = $_POST['part_name'][$i] ?? '';
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
                $cgst_percent = $gst_percent / 2;
                $sgst_percent = $gst_percent / 2;
                $cgst_amount = $taxable * ($cgst_percent / 100);
                $sgst_amount = $taxable * ($sgst_percent / 100);
                $total = $taxable + $cgst_amount + $sgst_amount;

                $itemStmt->execute([
                    $id,
                    $part_no,
                    $part_name,
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
                    $total,
                    $lead_time
                ]);
            }

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

    <form method="post" enctype="multipart/form-data" class="quote-form">

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
                    <select name="customer_id" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_id']) ?>"
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
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="draft" <?= $quote['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $quote['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="accepted" <?= $quote['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="rejected" <?= $quote['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="expired" <?= $quote['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="form-section">
            <h3>Items</h3>
            <div style="overflow-x: auto;">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>Part No</th>
                            <th>Name</th>
                            <th>HSN</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Rate</th>
                            <th>Disc %</th>
                            <th>Taxable</th>
                            <th>CGST</th>
                            <th>SGST</th>
                            <th>Amount</th>
                            <th>Lead Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($existingItems as $item): ?>
                        <tr class="item-row">
                            <td>
                                <select name="part_no[]" class="part-select" onchange="partChanged(this)" required>
                                    <option value="">Select</option>
                                    <?php foreach ($parts as $p): ?>
                                        <option value="<?= htmlspecialchars($p['part_no']) ?>"
                                            data-name="<?= htmlspecialchars($p['part_name']) ?>"
                                            data-hsn="<?= htmlspecialchars($p['hsn_code'] ?? '') ?>"
                                            data-uom="<?= htmlspecialchars($p['uom']) ?>"
                                            data-rate="<?= $p['rate'] ?>"
                                            data-gst="<?= $p['gst'] ?>"
                                            <?= $p['part_no'] === $item['part_no'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['part_no']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="part_name[]" class="part-name" value="<?= htmlspecialchars($item['part_name']) ?>" readonly></td>
                            <td><input type="text" name="hsn_code[]" class="hsn-code" value="<?= htmlspecialchars($item['hsn_code'] ?? '') ?>" readonly></td>
                            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0" value="<?= $item['qty'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="text" name="unit[]" class="unit" value="<?= htmlspecialchars($item['unit'] ?? '') ?>" readonly></td>
                            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" value="<?= $item['rate'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="<?= $item['discount'] ?>" onchange="calcRow(this)"></td>
                            <td><input type="number" name="taxable[]" class="taxable" step="0.01" value="<?= $item['taxable_amount'] ?>" readonly></td>
                            <td><input type="text" name="cgst[]" class="cgst" value="<?= number_format($item['cgst_amount'], 2) ?> (<?= $item['cgst_percent'] ?>%)" readonly></td>
                            <td><input type="text" name="sgst[]" class="sgst" value="<?= number_format($item['sgst_amount'], 2) ?> (<?= $item['sgst_percent'] ?>%)" readonly></td>
                            <td><input type="number" name="amount[]" class="amount" step="0.01" value="<?= $item['total_amount'] ?>" readonly></td>
                            <td><input type="text" name="lead_time[]" class="lead-time" value="<?= htmlspecialchars($item['lead_time'] ?? '') ?>"></td>
                            <td>
                                <input type="hidden" name="gst_percent[]" class="gst-percent" value="<?= ($item['cgst_percent'] + $item['sgst_percent']) ?>">
                                <button type="button" onclick="removeRow(this)" class="btn btn-danger" style="padding: 2px 8px;">-</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td colspan="7" style="text-align: right;"><strong>Totals:</strong></td>
                            <td><input type="text" id="totalTaxable" readonly></td>
                            <td><input type="text" id="totalCGST" readonly></td>
                            <td><input type="text" id="totalSGST" readonly></td>
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
                    <label>Payment Details</label>
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
            <td>
                <select name="part_no[]" class="part-select" onchange="partChanged(this)" disabled>
                    <option value="">Select</option>
                    <?php foreach ($parts as $p): ?>
                        <option value="<?= htmlspecialchars($p['part_no']) ?>"
                            data-name="<?= htmlspecialchars($p['part_name']) ?>"
                            data-hsn="<?= htmlspecialchars($p['hsn_code'] ?? '') ?>"
                            data-uom="<?= htmlspecialchars($p['uom']) ?>"
                            data-rate="<?= $p['rate'] ?>"
                            data-gst="<?= $p['gst'] ?>">
                            <?= htmlspecialchars($p['part_no']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="part_name[]" class="part-name" readonly disabled></td>
            <td><input type="text" name="hsn_code[]" class="hsn-code" readonly disabled></td>
            <td><input type="number" name="qty[]" class="qty" step="0.001" min="0" value="1" onchange="calcRow(this)" disabled></td>
            <td><input type="text" name="unit[]" class="unit" readonly disabled></td>
            <td><input type="number" name="rate[]" class="rate" step="0.01" min="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="discount[]" class="discount" step="0.01" min="0" max="100" value="0" onchange="calcRow(this)" disabled></td>
            <td><input type="number" name="taxable[]" class="taxable" step="0.01" readonly disabled></td>
            <td><input type="text" name="cgst[]" class="cgst" readonly disabled></td>
            <td><input type="text" name="sgst[]" class="sgst" readonly disabled></td>
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

function calcRow(elem) {
    const row = elem.closest('tr');

    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const rate = parseFloat(row.querySelector('.rate').value) || 0;
    const discount = parseFloat(row.querySelector('.discount').value) || 0;
    const gstPercent = parseFloat(row.querySelector('.gst-percent').value) || 0;

    const gross = qty * rate;
    const discountAmt = gross * (discount / 100);
    const taxable = gross - discountAmt;

    const cgstPercent = gstPercent / 2;
    const sgstPercent = gstPercent / 2;
    const cgstAmt = taxable * (cgstPercent / 100);
    const sgstAmt = taxable * (sgstPercent / 100);
    const total = taxable + cgstAmt + sgstAmt;

    row.querySelector('.taxable').value = taxable.toFixed(2);
    row.querySelector('.cgst').value = cgstAmt.toFixed(2) + ' (' + cgstPercent.toFixed(1) + '%)';
    row.querySelector('.sgst').value = sgstAmt.toFixed(2) + ' (' + sgstPercent.toFixed(1) + '%)';
    row.querySelector('.amount').value = total.toFixed(2);

    calcTotals();
}

function calcTotals() {
    let totalTaxable = 0;
    let totalCGST = 0;
    let totalSGST = 0;
    let grandTotal = 0;

    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const taxable = parseFloat(row.querySelector('.taxable').value) || 0;
        const amount = parseFloat(row.querySelector('.amount').value) || 0;
        const gstPercent = parseFloat(row.querySelector('.gst-percent').value) || 0;

        const cgstAmt = taxable * ((gstPercent / 2) / 100);
        const sgstAmt = taxable * ((gstPercent / 2) / 100);

        totalTaxable += taxable;
        totalCGST += cgstAmt;
        totalSGST += sgstAmt;
        grandTotal += amount;
    });

    document.getElementById('totalTaxable').value = totalTaxable.toFixed(2);
    document.getElementById('totalCGST').value = totalCGST.toFixed(2);
    document.getElementById('totalSGST').value = totalSGST.toFixed(2);
    document.getElementById('grandTotal').value = grandTotal.toFixed(2);
}

function addRow() {
    const template = document.getElementById('templateRow');
    const clone = template.cloneNode(true);
    clone.removeAttribute('id');

    // Enable all inputs
    clone.querySelectorAll('input, select').forEach(el => {
        el.disabled = false;
        if (el.classList.contains('part-select')) {
            el.required = true;
        }
    });

    // Clear values
    clone.querySelector('.part-select').selectedIndex = 0;
    clone.querySelector('.part-name').value = '';
    clone.querySelector('.hsn-code').value = '';
    clone.querySelector('.qty').value = '1';
    clone.querySelector('.unit').value = '';
    clone.querySelector('.rate').value = '';
    clone.querySelector('.discount').value = '0';
    clone.querySelector('.taxable').value = '';
    clone.querySelector('.cgst').value = '';
    clone.querySelector('.sgst').value = '';
    clone.querySelector('.amount').value = '';
    clone.querySelector('.lead-time').value = '';
    clone.querySelector('.gst-percent').value = '0';

    document.getElementById('itemsBody').appendChild(clone);
}

function removeRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('itemsBody');
    const rows = tbody.querySelectorAll('.item-row');

    if (rows.length <= 1) {
        // Clear values instead of removing
        row.querySelector('.part-select').selectedIndex = 0;
        row.querySelector('.part-name').value = '';
        row.querySelector('.hsn-code').value = '';
        row.querySelector('.qty').value = '1';
        row.querySelector('.unit').value = '';
        row.querySelector('.rate').value = '';
        row.querySelector('.discount').value = '0';
        row.querySelector('.taxable').value = '';
        row.querySelector('.cgst').value = '';
        row.querySelector('.sgst').value = '';
        row.querySelector('.amount').value = '';
        row.querySelector('.lead-time').value = '';
        row.querySelector('.gst-percent').value = '0';
        calcTotals();
        return;
    }

    row.remove();
    calcTotals();
}

// Initialize totals
calcTotals();
</script>

</body>
</html>
