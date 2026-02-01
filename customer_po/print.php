<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("Invalid Customer PO ID");
}

// Fetch customer PO with all related data
$stmt = $pdo->prepare("
    SELECT cp.*,
           c.company_name, c.customer_name, c.contact, c.email, c.address1, c.address2,
           c.city, c.state, c.pincode, c.gstin,
           q.pi_no, q.quote_no, q.quote_date, q.validity_date
    FROM customer_po cp
    LEFT JOIN customers c ON cp.customer_id = c.customer_id
    LEFT JOIN quote_master q ON cp.linked_quote_id = q.id
    WHERE cp.id = ?
");
$stmt->execute([$id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    die("Customer PO not found");
}

// Fetch items from linked PI
$items = [];
if ($po['linked_quote_id']) {
    $itemsStmt = $pdo->prepare("
        SELECT qi.*, p.part_name as product_name
        FROM quote_items qi
        LEFT JOIN part_master p ON qi.part_no = p.part_no
        WHERE qi.quote_id = ?
        ORDER BY qi.id
    ");
    $itemsStmt->execute([$po['linked_quote_id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate totals from items
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$totalAmount = 0;

foreach ($items as $item) {
    $taxable = ($item['qty'] * $item['rate']) * (1 - ($item['discount'] ?? 0) / 100);
    $gst = $item['gst'] ?? 0;
    $cgst = $taxable * ($gst / 2) / 100;
    $sgst = $taxable * ($gst / 2) / 100;

    $totalTaxable += $taxable;
    $totalCGST += $cgst;
    $totalSGST += $sgst;
    $totalAmount += $taxable + $cgst + $sgst;
}

// Fetch company settings for header
$companySettings = [];
try {
    $companySettings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    // Table may not exist
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer PO: <?= htmlspecialchars($po['po_no']) ?> - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
            max-width: 210mm;
            margin: 0 auto;
        }

        /* Header */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .company-info p {
            font-size: 11px;
            color: #666;
        }
        .po-info-header {
            text-align: right;
        }
        .po-info-header .po-no {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .po-info-header .po-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            margin-top: 5px;
        }
        .status-active { background: #28a745; color: #fff; }
        .status-completed { background: #17a2b8; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }

        /* Title */
        .document-title {
            text-align: center;
            background: #2c3e50;
            color: #fff;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
        }

        /* Section */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #34495e;
            color: #fff;
            padding: 8px 15px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-box {
            border: 1px solid #ddd;
            padding: 12px;
            background: #fafafa;
        }
        .info-box h4 {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            padding: 4px 0;
            font-size: 11px;
        }
        .info-label {
            width: 120px;
            color: #666;
            font-weight: 500;
        }
        .info-value {
            flex: 1;
            color: #2c3e50;
        }

        /* Items Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table th {
            background: #34495e;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totals */
        .totals-section {
            margin-top: 15px;
            display: flex;
            justify-content: flex-end;
        }
        .totals-box {
            width: 300px;
            border: 1px solid #ddd;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        .totals-row:last-child {
            border-bottom: none;
            background: #2c3e50;
            color: #fff;
            font-weight: bold;
            font-size: 13px;
        }

        /* Notes */
        .notes-box {
            background: #fffde7;
            border: 1px solid #fff59d;
            padding: 12px;
            font-size: 11px;
            white-space: pre-wrap;
        }

        /* PO Attachment Section */
        .attachment-section {
            margin-top: 30px;
            page-break-before: always;
        }
        .attachment-title {
            background: #e74c3c;
            color: #fff;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .attachment-preview {
            border: 2px solid #ddd;
            padding: 10px;
            background: #fff;
        }
        .attachment-preview img {
            max-width: 100%;
            display: block;
            margin: 0 auto;
        }
        .attachment-preview iframe {
            width: 100%;
            height: 800px;
            border: none;
        }
        .no-attachment {
            padding: 20px;
            text-align: center;
            color: #999;
            background: #f9f9f9;
            border: 1px dashed #ddd;
        }

        /* Footer */
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        /* Print Controls */
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .print-controls button {
            padding: 10px 20px;
            margin-left: 10px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-print {
            background: #3498db;
            color: #fff;
        }
        .btn-back {
            background: #95a5a6;
            color: #fff;
        }

        @media print {
            .print-controls { display: none; }
            body { padding: 0; }
            .section { page-break-inside: avoid; }
            .attachment-section { page-break-before: always; }
        }
    </style>
</head>
<body>

<!-- Print Controls -->
<div class="print-controls">
    <button class="btn-back" onclick="window.location.href='view.php?id=<?= $id ?>'">Back</button>
    <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<!-- Header -->
<div class="print-header">
    <div class="company-info">
        <h1><?= htmlspecialchars($companySettings['company_name'] ?? 'Company Name') ?></h1>
        <?php if (!empty($companySettings['address'])): ?>
            <p><?= htmlspecialchars($companySettings['address']) ?></p>
        <?php endif; ?>
        <?php if (!empty($companySettings['phone']) || !empty($companySettings['email'])): ?>
            <p>
                <?= !empty($companySettings['phone']) ? 'Ph: ' . htmlspecialchars($companySettings['phone']) : '' ?>
                <?= !empty($companySettings['email']) ? ' | ' . htmlspecialchars($companySettings['email']) : '' ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="po-info-header">
        <div class="po-no"><?= htmlspecialchars($po['po_no']) ?></div>
        <div class="po-date">
            <?php if ($po['po_date']): ?>
                Date: <?= date('d M Y', strtotime($po['po_date'])) ?>
            <?php endif; ?>
        </div>
        <div>
            <span class="status-badge status-<?= $po['status'] ?>">
                <?= ucfirst($po['status']) ?>
            </span>
        </div>
    </div>
</div>

<!-- Document Title -->
<div class="document-title">CUSTOMER PURCHASE ORDER</div>

<!-- PO Information Grid -->
<div class="section">
    <div class="info-grid">
        <!-- Customer Information -->
        <div class="info-box">
            <h4>Customer Information</h4>
            <div class="info-row">
                <span class="info-label">Company</span>
                <span class="info-value"><strong><?= htmlspecialchars($po['company_name'] ?? '-') ?></strong></span>
            </div>
            <?php if ($po['customer_name']): ?>
            <div class="info-row">
                <span class="info-label">Contact Person</span>
                <span class="info-value"><?= htmlspecialchars($po['customer_name']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($po['contact'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($po['email'] ?? '-') ?></span>
            </div>
            <?php if ($po['gstin']): ?>
            <div class="info-row">
                <span class="info-label">GSTIN</span>
                <span class="info-value"><?= htmlspecialchars($po['gstin']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- PO & PI Reference -->
        <div class="info-box">
            <h4>Order Reference</h4>
            <div class="info-row">
                <span class="info-label">Customer PO No</span>
                <span class="info-value"><strong><?= htmlspecialchars($po['po_no']) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">PO Date</span>
                <span class="info-value"><?= $po['po_date'] ? date('d M Y', strtotime($po['po_date'])) : '-' ?></span>
            </div>
            <?php if ($po['pi_no']): ?>
            <div class="info-row">
                <span class="info-label">Linked PI</span>
                <span class="info-value"><?= htmlspecialchars($po['pi_no']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Quote No</span>
                <span class="info-value"><?= htmlspecialchars($po['quote_no']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Created</span>
                <span class="info-value"><?= date('d M Y, h:i A', strtotime($po['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Customer Address -->
<?php if ($po['address1'] || $po['city']): ?>
<div class="section">
    <div class="section-title">Billing Address</div>
    <div style="padding: 10px; background: #fafafa; border: 1px solid #ddd;">
        <?= htmlspecialchars($po['address1'] ?? '') ?>
        <?= $po['address2'] ? '<br>' . htmlspecialchars($po['address2']) : '' ?>
        <br>
        <?= htmlspecialchars($po['city'] ?? '') ?>
        <?= $po['pincode'] ? ' - ' . htmlspecialchars($po['pincode']) : '' ?>
        <br>
        <?= htmlspecialchars($po['state'] ?? '') ?>
    </div>
</div>
<?php endif; ?>

<!-- Items from Linked PI -->
<?php if (!empty($items)): ?>
<div class="section">
    <div class="section-title">Order Items (from Linked PI: <?= htmlspecialchars($po['pi_no'] ?? 'N/A') ?>)</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Part No</th>
                <th>Product Name</th>
                <th class="text-center">Qty</th>
                <th>Unit</th>
                <th class="text-right">Rate</th>
                <th class="text-center">Disc %</th>
                <th class="text-right">Taxable</th>
                <th class="text-center">GST %</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $sno = 1; foreach ($items as $item):
                $taxable = ($item['qty'] * $item['rate']) * (1 - ($item['discount'] ?? 0) / 100);
                $gst = $item['gst'] ?? 0;
                $gstAmount = $taxable * $gst / 100;
                $amount = $taxable + $gstAmount;
            ?>
            <tr>
                <td><?= $sno++ ?></td>
                <td><?= htmlspecialchars($item['part_no']) ?></td>
                <td><?= htmlspecialchars($item['product_name'] ?? $item['part_name'] ?? '') ?></td>
                <td class="text-center"><?= number_format($item['qty'], 2) ?></td>
                <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                <td class="text-right"><?= number_format($item['rate'], 2) ?></td>
                <td class="text-center"><?= number_format($item['discount'] ?? 0, 1) ?>%</td>
                <td class="text-right"><?= number_format($taxable, 2) ?></td>
                <td class="text-center"><?= number_format($gst, 1) ?>%</td>
                <td class="text-right"><?= number_format($amount, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <div class="totals-box">
            <div class="totals-row">
                <span>Taxable Amount:</span>
                <span><?= number_format($totalTaxable, 2) ?></span>
            </div>
            <div class="totals-row">
                <span>CGST:</span>
                <span><?= number_format($totalCGST, 2) ?></span>
            </div>
            <div class="totals-row">
                <span>SGST:</span>
                <span><?= number_format($totalSGST, 2) ?></span>
            </div>
            <div class="totals-row">
                <span>Grand Total:</span>
                <span><?= number_format($totalAmount, 2) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Notes -->
<?php if ($po['notes']): ?>
<div class="section">
    <div class="section-title">Notes</div>
    <div class="notes-box"><?= htmlspecialchars($po['notes']) ?></div>
</div>
<?php endif; ?>

<!-- Footer for first page -->
<div class="print-footer" style="page-break-after: always;">
    <p>Customer PO: <?= htmlspecialchars($po['po_no']) ?> | Generated on <?= date('d M Y, h:i A') ?></p>
</div>

<!-- PO Attachment Section (New Page) -->
<div class="attachment-section">
    <div class="attachment-title">CUSTOMER PO COPY - Original Document</div>

    <?php if ($po['attachment_path']): ?>
        <?php
        $ext = strtolower(pathinfo($po['attachment_path'], PATHINFO_EXTENSION));
        ?>
        <div class="attachment-preview">
            <?php if ($ext === 'pdf'): ?>
                <iframe src="../<?= htmlspecialchars($po['attachment_path']) ?>"></iframe>
                <p style="text-align: center; margin-top: 10px; color: #666; font-size: 10px;">
                    PDF Document: <?= basename($po['attachment_path']) ?>
                </p>
            <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                <img src="../<?= htmlspecialchars($po['attachment_path']) ?>" alt="Customer PO Document">
                <p style="text-align: center; margin-top: 10px; color: #666; font-size: 10px;">
                    Image: <?= basename($po['attachment_path']) ?>
                </p>
            <?php else: ?>
                <div class="no-attachment">
                    <p>Attachment available but cannot be previewed.</p>
                    <p><strong>File:</strong> <?= basename($po['attachment_path']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="no-attachment">
            <p>No PO document attached.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Final Footer -->
<div class="print-footer">
    <p>Customer PO Report: <?= htmlspecialchars($po['po_no']) ?> | Generated on <?= date('d M Y, h:i A') ?></p>
    <p>This is a system-generated document.</p>
</div>

</body>
</html>
