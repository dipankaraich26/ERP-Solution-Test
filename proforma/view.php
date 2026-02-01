<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch PI (released quotation)
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name, c.contact, c.email,
           c.address1, c.address2, c.city, c.pincode, c.state, c.gstin,
           pt.term_name as payment_term_name, pt.term_description as payment_term_description, pt.days as payment_term_days
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    LEFT JOIN payment_terms pt ON q.payment_terms_id = pt.id
    WHERE q.id = ? AND q.status = 'released'
");
$stmt->execute([$id]);
$pi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pi) {
    header("Location: index.php");
    exit;
}

// Handle PI attachment upload
$attachment_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_attachment') {
    if (isset($_FILES['pi_pdf']) && $_FILES['pi_pdf']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pi_pdf'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 10 * 1024 * 1024; // 10MB

        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            $attachment_errors[] = "Only PDF and image files (JPG, PNG, GIF) are allowed";
        } elseif ($file['size'] > $max_size) {
            $attachment_errors[] = "File size must be less than 10MB";
        } else {
            // Create upload directory if needed
            $uploadDir = '../uploads/proforma';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename with proper extension
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'PI_' . str_replace('/', '_', $pi['pi_no']) . '_' . time() . '.' . $file_ext;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old file if exists
                if (($pi['pi_attachment'] ?? null) && file_exists('../' . $pi['pi_attachment'])) {
                    unlink('../' . $pi['pi_attachment']);
                }

                // Update database with new attachment path
                try {
                    $updateStmt = $pdo->prepare("UPDATE quote_master SET pi_attachment = ? WHERE id = ?");
                    $updateStmt->execute(['uploads/proforma/' . $filename, $id]);

                    // Refresh PI data
                    $stmt->execute([$id]);
                    $pi = $stmt->fetch(PDO::FETCH_ASSOC);

                    setModal("Success", "Proforma Invoice attachment uploaded successfully");
                } catch (PDOException $e) {
                    $attachment_errors[] = "Database error: " . $e->getMessage();
                }
            } else {
                $attachment_errors[] = "Failed to upload file";
            }
        }
    } elseif (isset($_FILES['pi_pdf']) && $_FILES['pi_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        $attachment_errors[] = "File upload error: " . $_FILES['pi_pdf']['error'];
    }
}

// Handle PI PDF upload
$pdf_upload_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pi_pdf') {
    if (isset($_FILES['pi_pdf_file']) && $_FILES['pi_pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pi_pdf_file'];
        $allowed_types = ['application/pdf'];
        $max_size = 20 * 1024 * 1024; // 20MB for full PDF

        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            $pdf_upload_errors[] = "Only PDF files are allowed for Proforma Invoice PDF";
        } elseif ($file['size'] > $max_size) {
            $pdf_upload_errors[] = "PDF file size must be less than 20MB";
        } else {
            // Create upload directory if needed
            $uploadDir = '../uploads/proforma_pdf';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $filename = 'PI_' . str_replace('/', '_', $pi['pi_no']) . '_' . time() . '.pdf';
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old PDF if exists
                if (($pi['pi_pdf_file'] ?? null) && file_exists('../' . $pi['pi_pdf_file'])) {
                    unlink('../' . $pi['pi_pdf_file']);
                }

                // Update database with new PDF path
                try {
                    $updateStmt = $pdo->prepare("UPDATE quote_master SET pi_pdf_file = ? WHERE id = ?");
                    $updateStmt->execute(['uploads/proforma_pdf/' . $filename, $id]);

                    // Refresh PI data
                    $stmt->execute([$id]);
                    $pi = $stmt->fetch(PDO::FETCH_ASSOC);

                    setModal("Success", "Proforma Invoice PDF uploaded successfully");
                } catch (PDOException $e) {
                    $pdf_upload_errors[] = "Database error: " . $e->getMessage();
                }
            } else {
                $pdf_upload_errors[] = "Failed to upload PDF file";
            }
        }
    } elseif (isset($_FILES['pi_pdf_file']) && $_FILES['pi_pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $pdf_upload_errors[] = "File upload error: " . $_FILES['pi_pdf_file']['error'];
    }
}

showModal();

// Fetch items
$itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if this PI uses IGST
$isIGST = isset($pi['is_igst']) && $pi['is_igst'] == 1;

// Calculate totals
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$totalIGST = 0;
$grandTotal = 0;
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

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Proforma Invoice - <?= htmlspecialchars($pi['pi_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <style>
        .pi-view { max-width: 1200px; }
        .pi-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .pi-info h2 { margin: 0 0 10px 0; color: #28a745; }
        .pi-info p { margin: 5px 0; }
        .customer-info { text-align: right; }
        .customer-info h3 { margin: 0 0 10px 0; }

        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 10px; border: 1px solid #ddd; }
        .items-table th { background: #28a745; color: white; }
        .items-table tr:nth-child(even) { background: #f9f9f9; }
        .items-table .totals-row { background: #e8f4e8 !important; font-weight: bold; }
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

        @media print {
            .sidebar, .action-buttons, .btn { display: none !important; }
            .content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="pi-view">

        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">Back to List</a>
            <a href="../quotes/print.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary">Print View</a>
            <button onclick="exportToExcel()" class="btn btn-secondary">Export Excel</button>
            <?php if ($pi['attachment_path']): ?>
                <a href="../<?= htmlspecialchars($pi['attachment_path']) ?>" target="_blank" class="btn btn-secondary">View Quote Attachment</a>
            <?php endif; ?>
            <?php if ($pi['pi_pdf_file'] ?? null): ?>
                <a href="../<?= htmlspecialchars($pi['pi_pdf_file']) ?>" target="_blank" class="btn btn-success">Download PI PDF</a>
                <button type="button" onclick="openWhatsApp()" class="btn btn-success" style="background: #25d366; border-color: #25d366;">📱 Send PDF via WhatsApp</button>
            <?php endif; ?>
            <button onclick="togglePdfForm()" class="btn btn-primary">Upload PI PDF</button>
        </div>

        <!-- PI PDF Upload Form -->
        <div id="pdfForm" style="display: none; background: #f0f7ff; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #003366; margin-top: 0;">Upload Proforma Invoice PDF</h3>

            <?php if (!empty($pdf_upload_errors)): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                <strong>Errors:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php foreach ($pdf_upload_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_pi_pdf">

                <div style="margin-bottom: 20px;">
                    <label for="pi_pdf_file" style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                        Proforma Invoice PDF File <span style="color: #e74c3c;">*</span>
                    </label>
                    <input type="file" id="pi_pdf_file" name="pi_pdf_file" accept=".pdf" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    <small style="color: #666; display: block; margin-top: 5px;">Upload the generated Proforma Invoice PDF - Max 20MB</small>
                </div>

                <?php if ($pi['pi_pdf_file'] ?? null): ?>
                <div style="margin-bottom: 20px; padding: 10px; background: #e8f5e9; border-radius: 4px;">
                    <strong style="color: #2e7d32;">Current PDF:</strong>
                    <a href="../<?= htmlspecialchars($pi['pi_pdf_file']) ?>"
                       target="_blank" style="color: #0056b3; text-decoration: none; display: block; margin-top: 5px;">
                        Download Current PDF
                    </a>
                    <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #666;">Choose a new file above to replace it</p>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                        Upload PDF
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="togglePdfForm()" style="padding: 10px 20px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <div class="pi-header">
            <div class="pi-info">
                <h2>Proforma Invoice #<?= htmlspecialchars($pi['pi_no']) ?></h2>
                <p><strong>Quotation:</strong> <?= htmlspecialchars($pi['quote_no']) ?></p>
                <p><strong>Reference:</strong> <?= htmlspecialchars($pi['reference'] ?? '-') ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($pi['quote_date']) ?></p>
                <p><strong>Released:</strong> <?= htmlspecialchars($pi['released_at'] ?? '-') ?></p>
            </div>
            <div class="customer-info">
                <h3>Customer</h3>
                <p><strong><?= htmlspecialchars($pi['company_name'] ?? '') ?></strong></p>
                <p><?= htmlspecialchars($pi['customer_name'] ?? '') ?></p>
                <p><?= htmlspecialchars($pi['address1'] ?? '') ?></p>
                <?php if ($pi['address2']): ?>
                    <p><?= htmlspecialchars($pi['address2']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($pi['city'] ?? '') ?> - <?= htmlspecialchars($pi['pincode'] ?? '') ?></p>
                <p><?= htmlspecialchars($pi['state'] ?? '') ?></p>
                <?php if ($pi['gstin']): ?>
                    <p><strong>GSTIN:</strong> <?= htmlspecialchars($pi['gstin']) ?></p>
                <?php endif; ?>
                <?php if ($pi['contact']): ?>
                    <p><strong>Contact:</strong> <?= htmlspecialchars($pi['contact']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <h3>Items</h3>
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
                </tbody>
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
            </table>
        </div>

        <?php if ($pi['payment_term_name'] ?? null): ?>
        <div class="details-section" style="background: #e8f5e9; border-color: #a5d6a7;">
            <h3 style="color: #2e7d32;">Payment Terms</h3>
            <p style="font-size: 1.1em; font-weight: bold; margin-bottom: 5px;"><?= htmlspecialchars($pi['payment_term_name']) ?></p>
            <?php if ($pi['payment_term_description']): ?>
                <p style="color: #555;"><?= htmlspecialchars($pi['payment_term_description']) ?></p>
            <?php endif; ?>
            <?php if ($pi['payment_term_days'] > 0): ?>
                <p style="color: #777; font-size: 0.9em;">Payment due within <?= $pi['payment_term_days'] ?> days</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($pi['terms_conditions']): ?>
        <div class="details-section">
            <h3>Terms & Conditions</h3>
            <p><?= nl2br(htmlspecialchars($pi['terms_conditions'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($pi['notes']): ?>
        <div class="details-section">
            <h3>Notes</h3>
            <p><?= nl2br(htmlspecialchars($pi['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($pi['payment_details']): ?>
        <div class="details-section">
            <h3>Payment Details</h3>
            <p><?= nl2br(htmlspecialchars($pi['payment_details'])) ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('itemsTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Proforma Invoice" });
    XLSX.writeFile(wb, "PI_<?= $pi['pi_no'] ?>.xlsx");
}

function toggleAttachmentForm() {
    const form = document.getElementById('attachmentForm');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        form.style.display = 'none';
    }
}

function togglePdfForm() {
    const form = document.getElementById('pdfForm');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        form.style.display = 'none';
    }
}

// WhatsApp functionality
var whatsappFileType = '';
<?php
    // Extract phone number from contact field (remove non-digits)
    $rawPhone = preg_replace('/[^\d]/', '', $pi['contact'] ?? '');
    // If phone is 10 digits, prepend India code (91)
    if (strlen($rawPhone) === 10) {
        $rawPhone = '91' . $rawPhone;
    }
    // Escape for JavaScript
    $jsCustomerName = addslashes($pi['customer_name'] ?? 'Customer');
    $jsCompanyName = addslashes($pi['company_name'] ?? '');
    $jsPiNo = addslashes($pi['pi_no'] ?? '');
?>
var customerPhone = '<?= $rawPhone ?>';
var piNo = '<?= $jsPiNo ?>';
var customerName = '<?= $jsCustomerName ?>';
var companyName = '<?= $jsCompanyName ?>';

function sendViaWhatsApp(type) {
    whatsappFileType = type;

    // Pre-fill with customer's phone number
    var phoneInput = document.getElementById('whatsappPhone');
    if (customerPhone) {
        phoneInput.value = customerPhone;
    }

    document.getElementById('whatsappModal').style.display = 'flex';
}

function closeWhatsappModal() {
    document.getElementById('whatsappModal').style.display = 'none';
}

function quickSendWhatsApp() {
    // Quick send function for PDF
    var phone = customerPhone;

    // If no customer phone, prompt for it
    if (!phone) {
        phone = prompt('Enter customer WhatsApp number with country code:\n(Example: 919876543210 for India)');
        if (!phone) return;
        phone = phone.replace(/[^\d]/g, '');
    }

    if (phone.length < 10) {
        alert('Invalid phone number. Please enter a valid number.');
        return;
    }

    var pdfPath = '<?= ($pi['pi_pdf_file'] ?? null) ? addslashes($pi['pi_pdf_file']) : '' ?>';
    if (!pdfPath) {
        alert('PDF file not found. Please upload the PDF first using "Upload PI PDF" button.');
        return;
    }

    var message = 'Hi ' + customerName + ',\n\nPlease find attached the Proforma Invoice #' + piNo + '.\n\nBest regards';
    var encodedMessage = encodeURIComponent(message);
    var whatsappURL = 'https://wa.me/' + phone + '?text=' + encodedMessage;

    // Open WhatsApp
    window.open(whatsappURL, '_blank');

    // Show instructions
    alert('WhatsApp opened!\n\nPlease manually attach the PDF file:\nPI_' + piNo + '.pdf\n\nClick the attachment icon in WhatsApp to send the file.');
}

// Simple openWhatsApp function for the button
function openWhatsApp() {
    var phone = customerPhone;

    // If no customer phone, prompt for it
    if (!phone) {
        phone = prompt('Enter customer WhatsApp number with country code:\n(Example: 919876543210 for India)');
        if (!phone) return;
        phone = phone.replace(/[^\d]/g, '');
    }

    if (phone.length < 10) {
        alert('Invalid phone number. Please enter a valid number.');
        return;
    }

    var pdfPath = '<?= ($pi['pi_pdf_file'] ?? null) ? addslashes($pi['pi_pdf_file']) : '' ?>';
    if (!pdfPath) {
        alert('PDF file not found. Please upload the PDF first using "Upload PI PDF" button.');
        return;
    }

    var displayName = companyName ? companyName : customerName;
    var message = 'Hi ' + displayName + ',\n\nPlease find attached the Proforma Invoice #' + piNo + '.\n\nBest regards';
    var encodedMessage = encodeURIComponent(message);
    var whatsappURL = 'https://wa.me/' + phone + '?text=' + encodedMessage;

    // Open WhatsApp
    window.open(whatsappURL, '_blank');

    // Show instructions
    alert('WhatsApp opened!\n\nPlease manually attach the PDF file from:\nuploads/proforma_pdf/\n\nClick the attachment icon in WhatsApp to send the file.');
}

function sendWhatsappMessage() {
    var phone = document.getElementById('whatsappPhone').value.trim();
    var message = document.getElementById('whatsappMessage').value.trim();

    // Validate phone number
    if (!phone) {
        alert('Please enter a phone number');
        return;
    }

    // Remove any non-numeric characters except + at start
    var cleanPhone = phone.replace(/[^\d+]/g, '');

    if (cleanPhone.length < 10) {
        alert('Please enter a valid phone number');
        return;
    }

    // Get file path based on type
    var filePath = '';
    var fileName = '';

    if (whatsappFileType === 'attachment') {
        filePath = '<?= ($pi['pi_attachment'] ?? null) ? addslashes($pi['pi_attachment']) : '' ?>';
        fileName = 'PI_Attachment_' + piNo;
    } else if (whatsappFileType === 'pdf') {
        filePath = '<?= ($pi['pi_pdf_file'] ?? null) ? addslashes($pi['pi_pdf_file']) : '' ?>';
        fileName = 'PI_' + piNo + '.pdf';
    }

    if (!filePath) {
        alert('File not found. Please upload the file first.');
        return;
    }

    // Build WhatsApp link with message
    var encodedMessage = encodeURIComponent(message);
    var whatsappURL = 'https://wa.me/' + cleanPhone + '?text=' + encodedMessage;

    // Open WhatsApp with message
    window.open(whatsappURL, '_blank');

    // Provide instructions for manual file attachment
    alert('WhatsApp will open now.\n\nPlease attach the PDF file manually:\n\n' + fileName + '\n\nThe message has been pre-filled. Once in WhatsApp, click the attachment button and select the file.');

    closeWhatsappModal();
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    var modal = document.getElementById('whatsappModal');
    if (event.target === modal) {
        closeWhatsappModal();
    }
});
</script>

</body>
</html>
