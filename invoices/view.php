<?php
include "../db.php";
include "../includes/dialog.php";

// Fetch company settings for From address
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: index.php");
    exit;
}

// Auto-add release photo columns
try {
    $pdo->exec("ALTER TABLE invoice_master ADD COLUMN photo_complete_mc VARCHAR(255) NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE invoice_master ADD COLUMN photo_packaging_items VARCHAR(255) NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE invoice_master ADD COLUMN photo_box_materials VARCHAR(255) NULL");
} catch (PDOException $e) {}

// Handle Release Photos upload
$photo_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_release_photos') {
    if ($invoice['status'] !== 'draft') {
        $photo_errors[] = "Can only upload photos for draft invoices";
    } else {
        $uploadDir = '../uploads/invoices/release_photos';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $photoFields = [
            'photo_complete_mc' => 'Complete MC',
            'photo_packaging_items' => 'Packaging Items',
            'photo_box_materials' => 'BOX with Materials'
        ];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        foreach ($photoFields as $field => $label) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                if (!in_array($file['type'], $allowed)) {
                    $photo_errors[] = "$label: Only image files (JPG, PNG, GIF, WebP) allowed";
                    continue;
                }
                if ($file['size'] > $maxSize) {
                    $photo_errors[] = "$label: File too large (max 10MB)";
                    continue;
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fname = strtoupper(str_replace('photo_', '', $field)) . '_' . str_replace('/', '_', $invoice['invoice_no']) . '_' . time() . '.' . $ext;
                $fpath = $uploadDir . '/' . $fname;

                if (move_uploaded_file($file['tmp_name'], $fpath)) {
                    // Delete old file
                    if (!empty($invoice[$field]) && file_exists('../' . $invoice[$field])) {
                        unlink('../' . $invoice[$field]);
                    }
                    $pdo->prepare("UPDATE invoice_master SET $field = ? WHERE id = ?")->execute(['uploads/invoices/release_photos/' . $fname, $id]);
                    $invoice[$field] = 'uploads/invoices/release_photos/' . $fname;
                } else {
                    $photo_errors[] = "$label: Upload failed";
                }
            }
        }

        if (empty($photo_errors)) {
            setModal("Success", "Release photos saved successfully");
            header("Location: view.php?id=$id");
            exit;
        }
    }
}

// Handle individual photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_release_photo') {
    $field = $_POST['photo_field'] ?? '';
    $validFields = ['photo_complete_mc', 'photo_packaging_items', 'photo_box_materials'];
    if (in_array($field, $validFields) && $invoice['status'] === 'draft') {
        if (!empty($invoice[$field]) && file_exists('../' . $invoice[$field])) {
            unlink('../' . $invoice[$field]);
        }
        $pdo->prepare("UPDATE invoice_master SET $field = NULL WHERE id = ?")->execute([$id]);
        setModal("Success", "Photo removed");
        header("Location: view.php?id=$id");
        exit;
    }
}

// Handle Ship-to Address form submission
$shipto_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_shipto') {
    if ($invoice['status'] !== 'draft') {
        $shipto_errors[] = "Can only update Ship-to Address for draft invoices";
    } else {
        try {
            // Ensure ship_to_contact_no column exists
            try {
                $pdo->exec("ALTER TABLE invoice_master ADD COLUMN ship_to_contact_no VARCHAR(50) NULL AFTER ship_to_contact_name");
            } catch (PDOException $ignore) {
                // Column already exists
            }

            $updateStmt = $pdo->prepare("
                UPDATE invoice_master
                SET ship_to_company_name = ?,
                    ship_to_contact_name = ?,
                    ship_to_contact_no = ?,
                    ship_to_address1 = ?,
                    ship_to_address2 = ?,
                    ship_to_city = ?,
                    ship_to_pincode = ?,
                    ship_to_state = ?,
                    ship_to_gstin = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                trim($_POST['ship_to_company_name'] ?? ''),
                trim($_POST['ship_to_contact_name'] ?? ''),
                trim($_POST['ship_to_contact_no'] ?? ''),
                trim($_POST['ship_to_address1'] ?? ''),
                trim($_POST['ship_to_address2'] ?? ''),
                trim($_POST['ship_to_city'] ?? ''),
                trim($_POST['ship_to_pincode'] ?? ''),
                trim($_POST['ship_to_state'] ?? ''),
                trim($_POST['ship_to_gstin'] ?? ''),
                $id
            ]);

            setModal("Success", "Ship-to Address saved successfully");
            header("Location: view.php?id=$id");
            exit;
        } catch (PDOException $e) {
            $shipto_errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle E-Way Bill form submission
$eway_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_eway') {
    $eway_bill_no = trim($_POST['eway_bill_no'] ?? '');

    // Validation
    if ($invoice['status'] !== 'draft') {
        $eway_errors[] = "Can only update E-Way Bill for draft invoices";
    }

    if (empty($eway_bill_no)) {
        $eway_errors[] = "E-Way Bill number is required";
    }

    // Handle file upload
    $eway_bill_attachment = $invoice['eway_bill_attachment'] ?? null; // Keep existing path if not uploading

    if (isset($_FILES['eway_bill_file']) && $_FILES['eway_bill_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['eway_bill_file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 10 * 1024 * 1024; // 10MB

        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            $eway_errors[] = "Only PDF and image files (JPG, PNG, GIF) are allowed";
        } elseif ($file['size'] > $max_size) {
            $eway_errors[] = "File size must be less than 10MB";
        } else {
            // Create upload directory if needed
            $uploadDir = '../uploads/invoices';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename with proper extension
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'EWAY_' . str_replace('/', '_', $invoice['invoice_no']) . '_' . time() . '.' . $file_ext;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old file if exists
                if (($invoice['eway_bill_attachment'] ?? null) && file_exists('../' . $invoice['eway_bill_attachment'])) {
                    unlink('../' . $invoice['eway_bill_attachment']);
                }
                $eway_bill_attachment = 'uploads/invoices/' . $filename;
            } else {
                $eway_errors[] = "Failed to upload E-Way Bill document";
            }
        }
    } elseif (isset($_FILES['eway_bill_file']) && $_FILES['eway_bill_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $eway_errors[] = "File upload error: " . $_FILES['eway_bill_file']['error'];
    }

    // Save if no errors
    if (empty($eway_errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE invoice_master
                SET eway_bill_no = ?, eway_bill_attachment = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$eway_bill_no, $eway_bill_attachment, $id]);

            setModal("Success", "E-Way Bill details saved successfully");
            header("Location: view.php?id=$id");
            exit;
        } catch (PDOException $e) {
            $eway_errors[] = "Database error: " . $e->getMessage();
        }
    }
}

showModal();

// Get SO -> Customer PO -> PI chain
$chainStmt = $pdo->prepare("
    SELECT
        so.so_no, so.sales_date, so.status as so_status,
        so.customer_po_id, so.linked_quote_id,
        cp.po_no as customer_po_no, cp.po_date, cp.attachment_path as po_attachment,
        q.id as pi_id, q.pi_no, q.quote_no, q.reference, q.quote_date,
        q.validity_date, q.terms_conditions, q.notes, q.payment_details,
        q.attachment_path as pi_attachment, q.released_at as pi_released_at,
        c.company_name, c.customer_name, c.contact, c.email,
        c.address1, c.address2, c.city, c.pincode, c.state, c.gstin
    FROM (
        SELECT DISTINCT so_no, sales_date, status, customer_id, customer_po_id, linked_quote_id
        FROM sales_orders
        WHERE so_no = ?
    ) so
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    LEFT JOIN customers c ON c.id = so.customer_id
");
$chainStmt->execute([$invoice['so_no']]);
$chain = $chainStmt->fetch(PDO::FETCH_ASSOC);

// Check linked lead status for release eligibility
// Lead must be "hot" or "converted" to release invoice (will auto-convert on release)
$linkedLead = null;
if ($chain && $chain['reference']) {
    $leadCheckStmt = $pdo->prepare("SELECT id, lead_no, lead_status, company_name FROM crm_leads WHERE lead_no = ?");
    $leadCheckStmt->execute([$chain['reference']]);
    $linkedLead = $leadCheckStmt->fetch(PDO::FETCH_ASSOC);
}
$leadStatusLower = strtolower($linkedLead['lead_status'] ?? '');
$leadOk = !$linkedLead || in_array($leadStatusLower, ['hot', 'converted']);

// Fetch PI items if PI exists
$items = [];
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$totalIGST = 0;
$grandTotal = 0;
$isIGST = false;

if ($chain && $chain['pi_id']) {
    // Check if this PI uses IGST (handle case where column doesn't exist yet)
    try {
        $piStmt = $pdo->prepare("SELECT is_igst FROM quote_master WHERE id = ?");
        $piStmt->execute([$chain['pi_id']]);
        $piData = $piStmt->fetch(PDO::FETCH_ASSOC);
        $isIGST = isset($piData['is_igst']) && $piData['is_igst'] == 1;
    } catch (PDOException $e) {
        // Column doesn't exist yet - default to CGST/SGST mode
        $isIGST = false;
    }

    $itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
    $itemsStmt->execute([$chain['pi_id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $totalTaxable += $item['taxable_amount'];
        if ($isIGST) {
            $totalIGST += $item['igst_amount'] ?? 0;
        } else {
            $totalCGST += $item['cgst_amount'];
            $totalSGST += $item['sgst_amount'];
        }
        $grandTotal += $item['total_amount'];
    }
}

// E-Way Bill is mandatory only if invoice value >= 50,000
$ewayRequired = $grandTotal >= 50000;
$ewayOk = !$ewayRequired || (!empty($invoice['eway_bill_no']) && !empty($invoice['eway_bill_attachment']));

// Release photos mandatory check
$photosOk = !empty($invoice['photo_complete_mc']) && !empty($invoice['photo_packaging_items']) && !empty($invoice['photo_box_materials']);
$canRelease = $leadOk && $ewayOk && $photosOk;

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tax Invoice - <?= htmlspecialchars($invoice['invoice_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <style>
        .invoice-view { max-width: 1200px; }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .invoice-info h2 { margin: 0 0 10px 0; color: #007bff; }
        .invoice-info p { margin: 5px 0; }

        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .address-box {
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .address-box.bill-to { border-left: 4px solid #007bff; }
        .address-box.ship-to { border-left: 4px solid #28a745; }
        .address-box h3 {
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .address-box.bill-to h3 { color: #007bff; }
        .address-box.ship-to h3 { color: #28a745; }
        .address-box p { margin: 5px 0; font-size: 0.95em; }

        .chain-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .chain-box {
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .chain-box h4 {
            margin: 0 0 10px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid;
        }
        .chain-box.lead h4 { border-color: #6f42c1; color: #6f42c1; }
        .chain-box.so h4 { border-color: #17a2b8; color: #17a2b8; }
        .chain-box.po h4 { border-color: #ffc107; color: #856404; }
        .chain-box.pi h4 { border-color: #28a745; color: #28a745; }
        .chain-box p { margin: 5px 0; font-size: 0.9em; }
        .lead-status-converted { background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; }
        .lead-status-other { background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; }

        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 10px; border: 1px solid #ddd; }
        .items-table th { background: #007bff; color: white; }
        .items-table tr:nth-child(even) { background: #f9f9f9; }
        .items-table .totals-row { background: #e8f0fe !important; font-weight: bold; }
        .items-table td.number { text-align: right; }

        .details-section {
            margin: 20px 0;
            padding: 15px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .details-section h3 { margin-top: 0; color: #333; }
        .details-section p { white-space: pre-wrap; }

        .action-buttons { margin: 20px 0; }
        .action-buttons .btn { margin-right: 10px; margin-bottom: 5px; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-draft { background: #ffc107; color: #000; }
        .status-released { background: #28a745; color: #fff; }

        @media print {
            .sidebar, .action-buttons, .btn { display: none !important; }
            .content { margin-left: 0 !important; }
        }

        @media (max-width: 768px) {
            .chain-info { grid-template-columns: 1fr; }
            .invoice-header { flex-direction: column; }
            .address-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="invoice-view">

        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">Back to List</a>
            <a href="print.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-secondary">Print View</a>
            <button onclick="exportToExcel()" class="btn btn-secondary">Export Excel</button>
            <?php if (!empty($invoice['eway_bill_attachment'])): ?>
                <a href="../<?= htmlspecialchars($invoice['eway_bill_attachment']) ?>" target="_blank" class="btn btn-primary" download>Download E-Way Bill</a>
            <?php endif; ?>
            <?php if ($invoice['status'] === 'draft'): ?>
                <?php if ($canRelease): ?>
                    <?php
                    $confirmMsg = "Release this Invoice?\n\nInventory will be deducted for all associated parts.";
                    if ($linkedLead && $leadStatusLower === 'hot') {
                        $confirmMsg .= "\n\nLead will be automatically converted to 'Converted' status.";
                    }
                    ?>
                    <a href="release.php?id=<?= $invoice['id'] ?>" class="btn btn-success"
                       onclick="return confirm('<?= addslashes($confirmMsg) ?>')">
                        Release Invoice
                    </a>
                    <?php if ($linkedLead && $leadStatusLower === 'hot'): ?>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        <em>Note: Lead will be automatically marked as "Converted" when invoice is released.</em>
                    </small>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled style="cursor: not-allowed;" title="<?php
                        $reasons = [];
                        if (!$leadOk) $reasons[] = 'Lead must be HOT or Converted';
                        if ($ewayRequired && !$ewayOk) $reasons[] = 'E-Way Bill required (Invoice ≥ ₹50,000)';
                        if (!$photosOk) $reasons[] = 'Release photos required (MC, Packaging, Box)';
                        echo implode(' | ', $reasons);
                    ?>">
                        Release Invoice (Blocked)
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($invoice['status'] === 'draft' && !$canRelease): ?>
        <div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong style="color: #721c24;">Cannot Release Invoice — Requirements not met:</strong>
            <ul style="margin: 10px 0 0; padding-left: 20px; color: #721c24;">
                <?php if ($linkedLead && !$leadOk): ?>
                <li style="margin-bottom: 8px;">
                    <span style="color: #dc3545;">✗</span>
                    Lead <strong><?= htmlspecialchars($linkedLead['lead_no']) ?></strong>
                    (<?= htmlspecialchars($linkedLead['company_name'] ?? 'Unknown') ?>)
                    status: <strong style="color: #dc3545;"><?= ucfirst($linkedLead['lead_status']) ?></strong>
                    → must be <strong style="color: #28a745;">"HOT"</strong> or <strong style="color: #28a745;">"Converted"</strong>
                    <br><small style="color: #666;">(Follow workflow: Quote → PI release → Lead becomes HOT)</small>
                </li>
                <?php endif; ?>
                <?php if ($ewayRequired && !$ewayOk): ?>
                <li>
                    E-Way Bill is required (Invoice ≥ ₹50,000) —
                    <?php if (empty($invoice['eway_bill_no'])): ?>Number missing<?php endif; ?>
                    <?php if (empty($invoice['eway_bill_no']) && empty($invoice['eway_bill_attachment'])): ?> & <?php endif; ?>
                    <?php if (empty($invoice['eway_bill_attachment'])): ?>Attachment missing<?php endif; ?>
                    <a href="#ewaySection" class="btn btn-sm btn-primary" style="margin-left: 10px; padding: 3px 10px; font-size: 0.85em;">Add E-Way Bill</a>
                </li>
                <?php endif; ?>
                <?php if (!$photosOk): ?>
                <li style="margin-top: 8px;">
                    <span style="color: #dc3545;">&#10007;</span>
                    <strong>Release Photos Required</strong> —
                    <?php
                    $missing = [];
                    if (empty($invoice['photo_complete_mc'])) $missing[] = 'Complete MC';
                    if (empty($invoice['photo_packaging_items'])) $missing[] = 'Packaging Items';
                    if (empty($invoice['photo_box_materials'])) $missing[] = 'BOX with Materials';
                    echo implode(', ', $missing) . ' photo(s) missing';
                    ?>
                    <a href="#releasePhotosSection" class="btn btn-sm btn-primary" style="margin-left: 10px; padding: 3px 10px; font-size: 0.85em;">Upload Photos</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="invoice-header">
            <div class="invoice-info">
                <h2>Tax Invoice #<?= htmlspecialchars($invoice['invoice_no']) ?></h2>
                <p><strong>Invoice Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?></p>
                <p>
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= $invoice['status'] ?>">
                        <?= ucfirst($invoice['status']) ?>
                    </span>
                </p>
                <?php if ($invoice['released_at']): ?>
                    <p><strong>Released At:</strong> <?= date('Y-m-d H:i', strtotime($invoice['released_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bill-to and Ship-to Addresses -->
        <div class="address-section">
            <!-- Bill-to Address (from Customer/PI) -->
            <div class="address-box bill-to">
                <h3>Bill-to Address</h3>
                <?php if ($chain): ?>
                    <p><strong><?= htmlspecialchars($chain['company_name'] ?? '') ?></strong></p>
                    <?php if ($chain['customer_name']): ?>
                        <p><?= htmlspecialchars($chain['customer_name']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($chain['address1'] ?? '') ?></p>
                    <?php if ($chain['address2']): ?>
                        <p><?= htmlspecialchars($chain['address2']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($chain['city'] ?? '') ?> - <?= htmlspecialchars($chain['pincode'] ?? '') ?></p>
                    <p><?= htmlspecialchars($chain['state'] ?? '') ?></p>
                    <?php if ($chain['gstin']): ?>
                        <p><strong>GSTIN:</strong> <?= htmlspecialchars($chain['gstin']) ?></p>
                    <?php endif; ?>
                    <?php if ($chain['contact']): ?>
                        <p><strong>Contact:</strong> <?= htmlspecialchars($chain['contact']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999;">No billing address available</p>
                <?php endif; ?>
                <p style="font-size: 0.85em; color: #666; margin-top: 10px; font-style: italic;">
                    (From customer record via PI/quotation)
                </p>
            </div>

            <!-- Ship-to Address (Can be different per invoice) -->
            <div class="address-box ship-to">
                <h3>
                    Ship-to Address
                    <span style="float: right; display: flex; gap: 5px;">
                        <?php if (!empty($invoice['ship_to_address1'])): ?>
                            <button onclick="printShipToAddress()" class="btn btn-sm btn-secondary" style="font-size: 0.8em; padding: 4px 10px;">
                                Print
                            </button>
                        <?php endif; ?>
                        <?php if ($invoice['status'] === 'draft'): ?>
                            <a href="#shiptoSection" class="btn btn-sm btn-success" style="font-size: 0.8em; padding: 4px 10px;">
                                <?= empty($invoice['ship_to_address1']) ? 'Add' : 'Edit' ?>
                            </a>
                        <?php endif; ?>
                    </span>
                </h3>
                <?php if (!empty($invoice['ship_to_address1'])): ?>
                    <div id="shipToContent">
                        <?php if ($invoice['ship_to_company_name']): ?>
                            <p><strong><?= htmlspecialchars($invoice['ship_to_company_name']) ?></strong></p>
                        <?php endif; ?>
                        <?php if ($invoice['ship_to_contact_name']): ?>
                            <p><?= htmlspecialchars($invoice['ship_to_contact_name']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($invoice['ship_to_address1']) ?></p>
                        <?php if ($invoice['ship_to_address2']): ?>
                            <p><?= htmlspecialchars($invoice['ship_to_address2']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($invoice['ship_to_city'] ?? '') ?> - <?= htmlspecialchars($invoice['ship_to_pincode'] ?? '') ?></p>
                        <p><?= htmlspecialchars($invoice['ship_to_state'] ?? '') ?></p>
                        <?php if ($invoice['ship_to_contact_no'] ?? ''): ?>
                            <p><strong>Contact No:</strong> <?= htmlspecialchars($invoice['ship_to_contact_no']) ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['ship_to_gstin']): ?>
                            <p><strong>GSTIN:</strong> <?= htmlspecialchars($invoice['ship_to_gstin']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #999;">No shipping address specified</p>
                    <?php if ($invoice['status'] === 'draft'): ?>
                        <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                            <a href="#shiptoSection">Click here</a> to add a shipping address
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Document Chain: SO -> Customer PO -> PI -->
        <h3>Document Chain</h3>
        <div class="chain-info">
            <div class="chain-box so">
                <h4>Sales Order</h4>
                <p><strong>SO No:</strong> <?= htmlspecialchars($chain['so_no'] ?? '-') ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($chain['sales_date'] ?? '-') ?></p>
                <p><strong>Status:</strong> <?= ucfirst($chain['so_status'] ?? '-') ?></p>
                <p><a href="/sales_orders/view.php?so_no=<?= urlencode($invoice['so_no']) ?>">View SO Details</a></p>
            </div>
            <div class="chain-box po">
                <h4>Customer PO</h4>
                <p><strong>PO No:</strong> <?= htmlspecialchars($chain['customer_po_no'] ?? '-') ?></p>
                <p><strong>PO Date:</strong> <?= htmlspecialchars($chain['po_date'] ?? '-') ?></p>
                <?php if ($chain && $chain['po_attachment']): ?>
                    <p><a href="../<?= htmlspecialchars($chain['po_attachment']) ?>" target="_blank">View PO Attachment</a></p>
                <?php endif; ?>
                <?php if ($chain && $chain['customer_po_id']): ?>
                    <p><a href="/customer_po/view.php?id=<?= $chain['customer_po_id'] ?>">View PO Details</a></p>
                <?php endif; ?>
            </div>
            <div class="chain-box pi">
                <h4>Proforma Invoice</h4>
                <p><strong>PI No:</strong> <?= htmlspecialchars($chain['pi_no'] ?? '-') ?></p>
                <p><strong>Quote No:</strong> <?= htmlspecialchars($chain['quote_no'] ?? '-') ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($chain['quote_date'] ?? '-') ?></p>
                <p><strong>Validity:</strong> <?= htmlspecialchars($chain['validity_date'] ?? '-') ?></p>
                <?php if ($chain && $chain['pi_id']): ?>
                    <p><a href="/proforma/view.php?id=<?= $chain['pi_id'] ?>">View PI Details</a></p>
                <?php endif; ?>
            </div>
            <div class="chain-box lead">
                <h4>CRM Lead</h4>
                <?php if ($linkedLead): ?>
                    <p><strong>Lead No:</strong> <?= htmlspecialchars($linkedLead['lead_no']) ?></p>
                    <p><strong>Company:</strong> <?= htmlspecialchars($linkedLead['company_name'] ?? '-') ?></p>
                    <p><strong>Status:</strong>
                        <span class="lead-status-<?= strtolower($linkedLead['lead_status']) === 'converted' ? 'converted' : 'other' ?>">
                            <?= ucfirst($linkedLead['lead_status']) ?>
                        </span>
                    </p>
                    <?php
                    $leadIdStmt2 = $pdo->prepare("SELECT id FROM crm_leads WHERE lead_no = ?");
                    $leadIdStmt2->execute([$linkedLead['lead_no']]);
                    $leadId = $leadIdStmt2->fetchColumn();
                    ?>
                    <p><a href="/crm/view.php?id=<?= $leadId ?>">View Lead Details</a></p>
                <?php else: ?>
                    <p>No linked lead</p>
                <?php endif; ?>
            </div>
        </div>

        <h3>Items (from Proforma Invoice)</h3>
        <div style="overflow-x: auto;">
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Part No</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>HSN</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Rate</th>
                        <th>Disc %</th>
                        <th>Taxable</th>
                        <?php if ($isIGST): ?>
                        <th>IGST</th>
                        <?php else: ?>
                        <th>CGST</th>
                        <th>SGST</th>
                        <?php endif; ?>
                        <th>Amount</th>
                        <th>Lead Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="<?= $isIGST ? 13 : 14 ?>" style="text-align: center;">No items found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['part_no']) ?></td>
                        <td><?= htmlspecialchars($item['part_name']) ?></td>
                        <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                        <td class="number"><?= number_format($item['qty'], 3) ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                        <td class="number"><?= number_format($item['rate'], 2) ?></td>
                        <td class="number"><?= number_format($item['discount'], 2) ?>%</td>
                        <td class="number"><?= number_format($item['taxable_amount'], 2) ?></td>
                        <?php if ($isIGST): ?>
                        <td class="number">
                            <?= number_format($item['igst_amount'] ?? 0, 2) ?>
                            <small>(<?= number_format($item['igst_percent'] ?? 0, 1) ?>%)</small>
                        </td>
                        <?php else: ?>
                        <td class="number">
                            <?= number_format($item['cgst_amount'], 2) ?>
                            <small>(<?= number_format($item['cgst_percent'], 1) ?>%)</small>
                        </td>
                        <td class="number">
                            <?= number_format($item['sgst_amount'], 2) ?>
                            <small>(<?= number_format($item['sgst_percent'], 1) ?>%)</small>
                        </td>
                        <?php endif; ?>
                        <td class="number"><?= number_format($item['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($item['lead_time'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($items)): ?>
                <tfoot>
                    <tr class="totals-row">
                        <td colspan="9" style="text-align: right;"><strong>Totals:</strong></td>
                        <td class="number"><?= number_format($totalTaxable, 2) ?></td>
                        <?php if ($isIGST): ?>
                        <td class="number"><?= number_format($totalIGST, 2) ?></td>
                        <?php else: ?>
                        <td class="number"><?= number_format($totalCGST, 2) ?></td>
                        <td class="number"><?= number_format($totalSGST, 2) ?></td>
                        <?php endif; ?>
                        <td class="number"><strong><?= number_format($grandTotal, 2) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php if ($chain && $chain['terms_conditions']): ?>
        <div class="details-section">
            <h3>Terms & Conditions</h3>
            <p><?= nl2br(htmlspecialchars($chain['terms_conditions'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($chain && $chain['notes']): ?>
        <div class="details-section">
            <h3>Notes</h3>
            <p><?= nl2br(htmlspecialchars($chain['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($chain && $chain['payment_details']): ?>
        <div class="details-section">
            <h3>Payment Details</h3>
            <p><?= nl2br(htmlspecialchars($chain['payment_details'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Release Photos (read-only for released invoices) -->
        <?php if ($invoice['status'] === 'released' && (!empty($invoice['photo_complete_mc']) || !empty($invoice['photo_packaging_items']) || !empty($invoice['photo_box_materials']))): ?>
        <div class="details-section" style="margin-top: 30px;">
            <h3 style="color: #155724;">Release Photos</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php
                $viewPhotos = [
                    'photo_complete_mc' => ['label' => 'Complete MC', 'color' => '#3498db'],
                    'photo_packaging_items' => ['label' => 'Packaging Items', 'color' => '#27ae60'],
                    'photo_box_materials' => ['label' => 'BOX with Materials', 'color' => '#e67e22']
                ];
                foreach ($viewPhotos as $f => $info):
                    if (!empty($invoice[$f])):
                ?>
                <div style="text-align: center;">
                    <div style="font-weight: 600; font-size: 0.9em; color: <?= $info['color'] ?>; margin-bottom: 8px;"><?= $info['label'] ?></div>
                    <a href="../<?= htmlspecialchars($invoice[$f]) ?>" target="_blank">
                        <img src="../<?= htmlspecialchars($invoice[$f]) ?>" alt="<?= $info['label'] ?>"
                             style="max-width: 100%; max-height: 200px; border-radius: 6px; border: 1px solid #ddd; object-fit: contain;">
                    </a>
                </div>
                <?php endif; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Release Photos Section (mandatory for invoice release) -->
        <?php if ($invoice['status'] === 'draft'): ?>
        <div id="releasePhotosSection" class="details-section" style="background: #fef9e7; border: 2px solid #f39c12; border-radius: 8px; padding: 20px; margin-top: 30px;">
            <h3 style="color: #7d6608; margin-top: 0;">
                Release Photos <span style="color: #e74c3c;">(Mandatory)</span>
            </h3>
            <p style="color: #666; margin-bottom: 15px;">
                Upload all 3 photos before the invoice can be released for approval.
            </p>

            <?php if (!empty($photo_errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>Errors:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php foreach ($photo_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Current Photos Preview -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                <?php
                $photoFields = [
                    'photo_complete_mc' => ['label' => 'Complete MC', 'icon' => '&#9881;', 'color' => '#3498db'],
                    'photo_packaging_items' => ['label' => 'Packaging Items', 'icon' => '&#128230;', 'color' => '#27ae60'],
                    'photo_box_materials' => ['label' => 'BOX with Materials', 'icon' => '&#128206;', 'color' => '#e67e22']
                ];
                foreach ($photoFields as $field => $info):
                    $hasPhoto = !empty($invoice[$field]);
                ?>
                <div style="border: 2px solid <?= $hasPhoto ? '#27ae60' : '#dc3545' ?>; border-radius: 8px; padding: 12px; text-align: center; background: <?= $hasPhoto ? '#f0fff0' : '#fff5f5' ?>;">
                    <div style="font-size: 0.9em; font-weight: 700; color: <?= $info['color'] ?>; margin-bottom: 8px;">
                        <span style="font-size: 1.3em;"><?= $info['icon'] ?></span> <?= $info['label'] ?>
                    </div>
                    <?php if ($hasPhoto): ?>
                        <a href="../<?= htmlspecialchars($invoice[$field]) ?>" target="_blank">
                            <img src="../<?= htmlspecialchars($invoice[$field]) ?>" alt="<?= $info['label'] ?>"
                                 style="max-width: 100%; max-height: 180px; border-radius: 6px; border: 1px solid #ddd; object-fit: contain; cursor: pointer;">
                        </a>
                        <div style="margin-top: 8px;">
                            <span style="color: #27ae60; font-weight: 600; font-size: 0.85em;">&#10003; Uploaded</span>
                            <form method="post" style="display: inline; margin-left: 8px;">
                                <input type="hidden" name="action" value="remove_release_photo">
                                <input type="hidden" name="photo_field" value="<?= $field ?>">
                                <button type="submit" onclick="return confirm('Remove this photo?')"
                                        style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.8em; text-decoration: underline;">Remove</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="height: 180px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 6px; border: 2px dashed #dee2e6;">
                            <span style="color: #adb5bd; font-size: 0.9em;">No photo uploaded</span>
                        </div>
                        <div style="margin-top: 8px;">
                            <span style="color: #dc3545; font-weight: 600; font-size: 0.85em;">&#10007; Required</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Upload Form -->
            <form method="post" enctype="multipart/form-data" style="background: white; padding: 18px; border-radius: 8px; border: 1px solid #eee;">
                <input type="hidden" name="action" value="save_release_photos">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                    <?php foreach ($photoFields as $field => $info): ?>
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9em; color: <?= $info['color'] ?>;">
                            <?= $info['label'] ?> <?= empty($invoice[$field]) ? '<span style="color: #e74c3c;">*</span>' : '<span style="color:#27ae60;">(Replace)</span>' ?>
                        </label>
                        <input type="file" name="<?= $field ?>" accept="image/*"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 0.88em;"
                               <?= empty($invoice[$field]) ? '' : '' ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <small style="color: #888;">Accepted: JPG, PNG, GIF, WebP (Max 10MB each)</small>
                    <button type="submit" class="btn btn-success" style="padding: 10px 24px;">Upload Photos</button>
                </div>
            </form>

            <?php if ($photosOk): ?>
            <div style="margin-top: 15px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                <strong>&#10003; All Release Photos Complete</strong> — Invoice is ready for release (photos requirement met).
            </div>
            <?php else: ?>
            <div style="margin-top: 15px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                <strong>&#9888; Photos Incomplete</strong> — All 3 photos must be uploaded before invoice can be released.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Ship-to Address Section (only for draft invoices) -->
        <?php if ($invoice['status'] === 'draft'): ?>
        <div id="shiptoSection" class="details-section" style="background: #e8f8e8; border: 2px solid #28a745; border-radius: 8px; padding: 20px; margin-top: 30px;">
            <h3 style="color: #155724; margin-top: 0;">Ship-to Address</h3>
            <p style="color: #666; margin-bottom: 15px;">
                Add or update the shipping address for this invoice. This can be different from the billing address.
            </p>

            <?php if (!empty($shipto_errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>Errors:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php foreach ($shipto_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="save_shipto">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Company Name</label>
                        <input type="text" name="ship_to_company_name"
                               value="<?= htmlspecialchars($invoice['ship_to_company_name'] ?? '') ?>"
                               placeholder="Shipping destination company"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Contact Name</label>
                        <input type="text" name="ship_to_contact_name"
                               value="<?= htmlspecialchars($invoice['ship_to_contact_name'] ?? '') ?>"
                               placeholder="Contact person at shipping address"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Contact No</label>
                    <input type="text" name="ship_to_contact_no"
                           value="<?= htmlspecialchars($invoice['ship_to_contact_no'] ?? '') ?>"
                           placeholder="Phone / Mobile number"
                           style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Address Line 1 *</label>
                    <input type="text" name="ship_to_address1"
                           value="<?= htmlspecialchars($invoice['ship_to_address1'] ?? '') ?>"
                           placeholder="Street address, building, etc."
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Address Line 2</label>
                    <input type="text" name="ship_to_address2"
                           value="<?= htmlspecialchars($invoice['ship_to_address2'] ?? '') ?>"
                           placeholder="Area, landmark, etc."
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">City</label>
                        <input type="text" name="ship_to_city"
                               value="<?= htmlspecialchars($invoice['ship_to_city'] ?? '') ?>"
                               placeholder="City"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Pincode</label>
                        <input type="text" name="ship_to_pincode"
                               value="<?= htmlspecialchars($invoice['ship_to_pincode'] ?? '') ?>"
                               placeholder="Pincode"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">State</label>
                        <input type="text" name="ship_to_state"
                               value="<?= htmlspecialchars($invoice['ship_to_state'] ?? '') ?>"
                               placeholder="State"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">GSTIN (if applicable)</label>
                    <input type="text" name="ship_to_gstin"
                           value="<?= htmlspecialchars($invoice['ship_to_gstin'] ?? '') ?>"
                           placeholder="GSTIN at shipping location"
                           style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-success" style="padding: 10px 20px;">
                        Save Ship-to Address
                    </button>
                    <?php if ($chain): ?>
                    <button type="button" class="btn btn-secondary" onclick="copyBillToAddress()" style="padding: 10px 15px;">
                        Copy from Bill-to
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- E-Way Bill Section (only for draft invoices) -->
        <?php if ($invoice['status'] === 'draft'): ?>
        <div id="ewaySection" class="details-section" style="background: #e8f4f8; border: 2px solid #0088cc; border-radius: 8px; padding: 20px; margin-top: 30px;">
            <h3 style="color: #004080; margin-top: 0;">E-Way Bill Details
                <?php if ($ewayRequired): ?>
                    <span style="color: #e74c3c;">(Required — Invoice ≥ ₹50,000)</span>
                <?php else: ?>
                    <span style="color: #28a745;">(Optional — Invoice < ₹50,000)</span>
                <?php endif; ?>
            </h3>

            <?php if (!empty($eway_errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>Errors:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php foreach ($eway_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_eway">

                <div style="margin-bottom: 20px;">
                    <label for="eway_bill_no" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        E-Way Bill Number <span style="color: #e74c3c;">*</span>
                    </label>
                    <input type="text" id="eway_bill_no" name="eway_bill_no"
                           value="<?= htmlspecialchars($invoice['eway_bill_no'] ?? '') ?>"
                           placeholder="e.g., 402050000000001"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
                           required>
                    <small style="color: #666; display: block; margin-top: 5px;">Enter the 16-digit E-Way Bill number from the portal</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="eway_bill_file" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        E-Way Bill Attachment <span style="color: #e74c3c;">*</span>
                    </label>
                    <input type="file" id="eway_bill_file" name="eway_bill_file" accept=".pdf,.jpg,.jpeg,.png,.gif"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    <small style="color: #666; display: block; margin-top: 5px;">Upload E-Way Bill document (PDF or Image - Max 10MB)</small>

                    <?php if ($invoice['eway_bill_attachment'] ?? null): ?>
                    <div style="margin-top: 10px; padding: 10px; background: #d4edda; border-radius: 4px;">
                        <strong style="color: #155724;">Current Attachment:</strong>
                        <a href="../<?= htmlspecialchars($invoice['eway_bill_attachment']) ?>"
                           target="_blank" style="color: #0056b3; text-decoration: none;">
                            View Uploaded Document
                        </a>
                        <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #666;">Replace with new file above to update</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                        Save E-Way Bill Details
                    </button>
                </div>
            </form>

            <?php if (($invoice['eway_bill_no'] ?? null) && ($invoice['eway_bill_attachment'] ?? null)): ?>
            <div style="margin-top: 15px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                <strong>✓ E-Way Bill Complete</strong><br>
                E-Way Bill number and attachment are complete.
            </div>
            <?php else: ?>
            <div style="margin-top: 15px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                <strong>ℹ E-Way Bill Not Complete</strong><br>
                <?php if (!($invoice['eway_bill_no'] ?? null)): ?>Please enter E-Way Bill number. <?php endif; ?>
                <?php if (!($invoice['eway_bill_attachment'] ?? null)): ?>Please upload E-Way Bill attachment. <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('itemsTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Tax Invoice" });
    XLSX.writeFile(wb, "Invoice_<?= str_replace('/', '_', $invoice['invoice_no']) ?>.xlsx");
}

function printShipToAddress() {
    const shipToContent = document.getElementById('shipToContent');
    if (!shipToContent) {
        alert('No ship-to address to print');
        return;
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ship-to Address - <?= htmlspecialchars($invoice['invoice_no']) ?></title>
            <style>
                @page {
                    size: A4;
                    margin: 20mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    padding: 30px;
                    margin: 0;
                    min-height: 100vh;
                    box-sizing: border-box;
                }
                .label-container {
                    border: 4px solid #333;
                    padding: 50px;
                    width: 100%;
                    max-width: 180mm;
                    min-height: 240mm;
                    margin: 0 auto;
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                }
                .label-header {
                    text-align: center;
                    border-bottom: 3px solid #333;
                    padding-bottom: 25px;
                    margin-bottom: 40px;
                    font-weight: bold;
                    font-size: 36px;
                    letter-spacing: 5px;
                }
                .label-content {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                .label-content p {
                    margin: 15px 0;
                    font-size: 28px;
                    line-height: 1.6;
                }
                .label-content strong {
                    font-size: 36px;
                    display: block;
                    margin-bottom: 10px;
                }
                .invoice-ref {
                    margin-top: 40px;
                    padding-top: 25px;
                    border-top: 2px dashed #666;
                    font-size: 20px;
                    color: #333;
                }
                .invoice-ref strong {
                    font-size: 20px;
                    display: inline;
                }
                @media print {
                    body {
                        padding: 0;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print { display: none !important; }
                    .label-container {
                        border-width: 4px;
                        page-break-inside: avoid;
                    }
                }
                @media screen {
                    body {
                        background: #f0f0f0;
                    }
                    .label-container {
                        background: white;
                        box-shadow: 0 0 20px rgba(0,0,0,0.2);
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 30px; text-align: center; background: #333; padding: 15px; border-radius: 8px;">
                <button onclick="window.print()" style="padding: 15px 40px; font-size: 18px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 5px; margin-right: 15px;">Print A4 Label</button>
                <button onclick="window.close()" style="padding: 15px 40px; font-size: 18px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px;">Close</button>
            </div>
            <div class="label-container">
                <div class="label-header">SHIPPING LABEL</div>
                <div class="label-content">
                    <div style="border-bottom: 2px solid #999; padding-bottom: 20px; margin-bottom: 25px;">
                        <p style="font-size: 18px; color: #666; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 2px;">From:</p>
                        <p style="margin: 5px 0;"><strong style="font-size: 28px;"><?= htmlspecialchars($settings['company_name'] ?? 'Yashka Infotronics') ?></strong></p>
                        <p style="margin: 5px 0; font-size: 20px;"><?= htmlspecialchars($settings['address_line1'] ?? '') ?></p>
                        <?php if (!empty($settings['address_line2'])): ?>
                            <p style="margin: 5px 0; font-size: 20px;"><?= htmlspecialchars($settings['address_line2']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['city'])): ?>
                            <p style="margin: 5px 0; font-size: 20px;">City: <?= htmlspecialchars($settings['city']) ?><?= !empty($settings['pincode']) ? ' - ' . htmlspecialchars($settings['pincode']) : '' ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['state'])): ?>
                            <p style="margin: 5px 0; font-size: 20px;">State: <?= htmlspecialchars($settings['state']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['phone'])): ?>
                            <p style="margin: 5px 0; font-size: 20px;">Contact No: <?= htmlspecialchars($settings['phone']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($settings['gstin'])): ?>
                            <p style="margin: 5px 0; font-size: 18px;">GSTIN: <?= htmlspecialchars($settings['gstin']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p style="font-size: 18px; color: #666; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 2px;">Ship To:</p>
                        ${shipToContent.innerHTML}
                    </div>
                </div>
                <div class="invoice-ref">
                    <strong>Invoice:</strong> <?= htmlspecialchars($invoice['invoice_no']) ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                    <strong>Date:</strong> <?= htmlspecialchars($invoice['invoice_date']) ?>
                </div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function copyBillToAddress() {
    <?php if ($chain): ?>
    document.querySelector('input[name="ship_to_company_name"]').value = <?= json_encode($chain['company_name'] ?? '') ?>;
    document.querySelector('input[name="ship_to_contact_name"]').value = <?= json_encode($chain['customer_name'] ?? '') ?>;
    document.querySelector('input[name="ship_to_contact_no"]').value = <?= json_encode($chain['contact'] ?? '') ?>;
    document.querySelector('input[name="ship_to_address1"]').value = <?= json_encode($chain['address1'] ?? '') ?>;
    document.querySelector('input[name="ship_to_address2"]').value = <?= json_encode($chain['address2'] ?? '') ?>;
    document.querySelector('input[name="ship_to_city"]').value = <?= json_encode($chain['city'] ?? '') ?>;
    document.querySelector('input[name="ship_to_pincode"]').value = <?= json_encode($chain['pincode'] ?? '') ?>;
    document.querySelector('input[name="ship_to_state"]').value = <?= json_encode($chain['state'] ?? '') ?>;
    document.querySelector('input[name="ship_to_gstin"]').value = <?= json_encode($chain['gstin'] ?? '') ?>;
    <?php endif; ?>
}
</script>

</body>
</html>
