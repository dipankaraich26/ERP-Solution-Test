<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: index.php");
    exit;
}

// Get SO -> Customer PO -> PI chain and customer info
$chainStmt = $pdo->prepare("
    SELECT
        so.so_no, so.sales_date, so.status as so_status,
        so.customer_po_id, so.linked_quote_id,
        cp.po_no as customer_po_no, cp.po_date,
        q.id as pi_id, q.pi_no, q.quote_no, q.reference,
        q.terms_conditions, q.notes, q.payment_details,
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

// Fetch items
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

$document_title = 'TAX INVOICE';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tax Invoice - <?= htmlspecialchars($invoice['invoice_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            background: white;
            padding: 20px;
            font-size: 12px;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .doc-info {
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .doc-info > div {
            padding: 15px;
        }
        .doc-info h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
        }
        .doc-info p { margin: 3px 0; }
        .doc-info strong { font-size: 14px; }

        .ref-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        .ref-row span { display: inline-block; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        .items-table th, .items-table td {
            padding: 8px 5px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .items-table .number { text-align: right; }
        .items-table tfoot tr { background: #f9f9f9; font-weight: bold; }

        .summary-box {
            display: flex;
            justify-content: flex-end;
            margin: 20px 0;
        }
        .summary-table {
            width: 280px;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .summary-table .label { text-align: right; font-weight: bold; }
        .summary-table .value { text-align: right; width: 120px; }
        .summary-table .grand-total { background: #f5f5f5; font-size: 14px; }

        .terms-section {
            margin: 20px 0;
            padding: 15px;
            background: #fafafa;
            border: 1px solid #ddd;
        }
        .terms-section h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
        }
        .terms-section p {
            margin: 0;
            font-size: 11px;
            white-space: pre-wrap;
        }

        .bank-details {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        .bank-details h4 { margin: 0 0 10px 0; font-size: 12px; }
        .bank-details p { margin: 3px 0; font-size: 11px; }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover { background: #0056b3; }

        @media print {
            body { padding: 0; }
            .print-btn { display: none; }
            .print-container { max-width: 100%; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print</button>

<div class="print-container">

    <?php include "../includes/company_header.php"; ?>

    <div class="doc-info" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0;">
        <div class="invoice-box" style="border-right: 1px solid #ddd;">
            <h4>Invoice Details</h4>
            <p><strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></p>
            <p>Date: <?= htmlspecialchars($invoice['invoice_date']) ?></p>
            <p>Status: <?= ucfirst(htmlspecialchars($invoice['status'])) ?></p>
            <?php if ($chain): ?>
                <p style="margin-top: 10px;">
                    SO: <?= htmlspecialchars($chain['so_no']) ?><br>
                    <?php if ($chain['customer_po_no']): ?>
                        Customer PO: <?= htmlspecialchars($chain['customer_po_no']) ?><br>
                    <?php endif; ?>
                    <?php if ($chain['pi_no']): ?>
                        PI: <?= htmlspecialchars($chain['pi_no']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="customer-box" style="border-right: 1px solid #ddd;">
            <h4>Bill To</h4>
            <?php if ($chain): ?>
                <p><strong><?= htmlspecialchars($chain['company_name'] ?? $chain['customer_name'] ?? '') ?></strong></p>
                <?php if ($chain['customer_name'] && $chain['company_name']): ?>
                    <p><?= htmlspecialchars($chain['customer_name']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($chain['address1'] ?? '') ?></p>
                <?php if ($chain['address2']): ?>
                    <p><?= htmlspecialchars($chain['address2']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars(implode(', ', array_filter([
                    $chain['city'] ?? '',
                    $chain['state'] ?? '',
                    $chain['pincode'] ?? ''
                ]))) ?></p>
                <?php if ($chain['gstin']): ?>
                    <p>GSTIN: <?= htmlspecialchars($chain['gstin']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>Customer information not available</p>
            <?php endif; ?>
        </div>
        <div class="customer-box">
            <h4>Ship To</h4>
            <?php if (!empty($invoice['ship_to_address1'])): ?>
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
                <p><?= htmlspecialchars(implode(', ', array_filter([
                    $invoice['ship_to_city'] ?? '',
                    $invoice['ship_to_state'] ?? '',
                    $invoice['ship_to_pincode'] ?? ''
                ]))) ?></p>
                <?php if ($invoice['ship_to_contact_no'] ?? ''): ?>
                    <p>Contact: <?= htmlspecialchars($invoice['ship_to_contact_no']) ?></p>
                <?php endif; ?>
                <?php if ($invoice['ship_to_gstin']): ?>
                    <p>GSTIN: <?= htmlspecialchars($invoice['ship_to_gstin']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p style="color: #999; font-style: italic;">Same as Bill To</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($items)): ?>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Part No</th>
                <th>Product Name</th>
                <th>Description</th>
                <th>HSN</th>
                <th style="width: 50px;">Qty</th>
                <th style="width: 40px;">Unit</th>
                <th>Rate</th>
                <th>Taxable</th>
                <?php if ($isIGST): ?>
                <th>IGST</th>
                <?php else: ?>
                <th>CGST</th>
                <th>SGST</th>
                <?php endif; ?>
                <th>Amount</th>
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
                <td class="number"><?= number_format($item['qty'], 2) ?></td>
                <td><?= htmlspecialchars($item['unit'] ?? 'Nos') ?></td>
                <td class="number"><?= number_format($item['rate'], 2) ?></td>
                <td class="number"><?= number_format($item['taxable_amount'], 2) ?></td>
                <?php if ($isIGST): ?>
                <td class="number"><?= number_format($item['igst_amount'] ?? 0, 2) ?></td>
                <?php else: ?>
                <td class="number"><?= number_format($item['cgst_amount'], 2) ?></td>
                <td class="number"><?= number_format($item['sgst_amount'], 2) ?></td>
                <?php endif; ?>
                <td class="number"><?= number_format($item['total_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align: right;">Totals:</td>
                <td class="number"><?= number_format($totalTaxable, 2) ?></td>
                <?php if ($isIGST): ?>
                <td class="number"><?= number_format($totalIGST, 2) ?></td>
                <?php else: ?>
                <td class="number"><?= number_format($totalCGST, 2) ?></td>
                <td class="number"><?= number_format($totalSGST, 2) ?></td>
                <?php endif; ?>
                <td class="number"><?= number_format($grandTotal, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td class="label">Taxable Amount:</td>
                <td class="value"><?= number_format($totalTaxable, 2) ?></td>
            </tr>
            <?php if ($isIGST): ?>
            <tr>
                <td class="label">IGST:</td>
                <td class="value"><?= number_format($totalIGST, 2) ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td class="label">CGST:</td>
                <td class="value"><?= number_format($totalCGST, 2) ?></td>
            </tr>
            <tr>
                <td class="label">SGST:</td>
                <td class="value"><?= number_format($totalSGST, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="grand-total">
                <td class="label">Grand Total:</td>
                <td class="value"><?= number_format($grandTotal, 2) ?></td>
            </tr>
        </table>
    </div>
    <?php else: ?>
        <p style="padding: 20px; background: #f5f5f5; text-align: center;">No item details available</p>
    <?php endif; ?>

    <?php if (!empty($settings['bank_name'])): ?>
    <div class="bank-details">
        <h4>Bank Details for Payment</h4>
        <p><strong>Bank:</strong> <?= htmlspecialchars($settings['bank_name']) ?></p>
        <p><strong>Account No:</strong> <?= htmlspecialchars($settings['bank_account'] ?? '') ?></p>
        <p><strong>IFSC:</strong> <?= htmlspecialchars($settings['bank_ifsc'] ?? '') ?></p>
        <?php if (!empty($settings['bank_branch'])): ?>
            <p><strong>Branch:</strong> <?= htmlspecialchars($settings['bank_branch']) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $terms = $chain['terms_conditions'] ?? $settings['terms_conditions'] ?? '';
    if ($terms):
    ?>
    <div class="terms-section">
        <h4>Terms & Conditions</h4>
        <p><?= nl2br(htmlspecialchars($terms)) ?></p>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Received By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">For <?= htmlspecialchars($settings['company_name'] ?? 'Company') ?></div>
        </div>
    </div>

</div>

</body>
</html>
