<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch quotation
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name, c.contact, c.email,
           c.address1, c.address2, c.city, c.pincode, c.state, c.gstin
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    WHERE q.id = ?
");
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header("Location: index.php");
    exit;
}

// Fetch items
$itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$grandTotal = 0;
foreach ($items as $item) {
    $totalTaxable += $item['taxable_amount'];
    $totalCGST += $item['cgst_amount'];
    $totalSGST += $item['sgst_amount'];
    $grandTotal += $item['total_amount'];
}

$document_title = $quote['pi_no'] ? 'PROFORMA INVOICE' : 'QUOTATION';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $document_title ?> - <?= htmlspecialchars($quote['quote_no']) ?></title>
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
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .doc-info > div {
            padding: 15px;
            flex: 1;
        }
        .doc-info .customer-box {
            border-left: 1px solid #ddd;
        }
        .doc-info h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
        }
        .doc-info p { margin: 3px 0; }
        .doc-info strong { font-size: 14px; }

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
            width: 250px;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .summary-table .label { text-align: right; font-weight: bold; }
        .summary-table .value { text-align: right; width: 100px; }
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
            background: #4a90d9;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover { background: #357abd; }

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

    <?php
    $document_title = $quote['pi_no'] ? 'PROFORMA INVOICE' : 'QUOTATION';
    include "../includes/company_header.php";
    ?>

    <div class="doc-info">
        <div class="quote-box">
            <h4><?= $quote['pi_no'] ? 'Proforma Invoice Details' : 'Quotation Details' ?></h4>
            <p><strong><?= htmlspecialchars($quote['quote_no']) ?></strong></p>
            <?php if ($quote['pi_no']): ?>
                <p>PI No: <strong><?= htmlspecialchars($quote['pi_no']) ?></strong></p>
            <?php endif; ?>
            <p>Date: <?= htmlspecialchars($quote['quote_date']) ?></p>
            <p>Valid Till: <?= htmlspecialchars($quote['validity_date'] ?? '-') ?></p>
            <?php if ($quote['reference']): ?>
                <p>Reference: <?= htmlspecialchars($quote['reference']) ?></p>
            <?php endif; ?>
        </div>
        <div class="customer-box">
            <h4>Bill To</h4>
            <p><strong><?= htmlspecialchars($quote['company_name'] ?? $quote['customer_name'] ?? '') ?></strong></p>
            <?php if ($quote['customer_name'] && $quote['company_name']): ?>
                <p><?= htmlspecialchars($quote['customer_name']) ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars($quote['address1'] ?? '') ?></p>
            <?php if ($quote['address2']): ?>
                <p><?= htmlspecialchars($quote['address2']) ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars(implode(', ', array_filter([
                $quote['city'] ?? '',
                $quote['state'] ?? '',
                $quote['pincode'] ?? ''
            ]))) ?></p>
            <?php if ($quote['gstin']): ?>
                <p>GSTIN: <?= htmlspecialchars($quote['gstin']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Part No</th>
                <th>Description</th>
                <th>HSN</th>
                <th style="width: 50px;">Qty</th>
                <th style="width: 40px;">Unit</th>
                <th>Rate</th>
                <th>Taxable</th>
                <th>GST</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($item['part_no']) ?></td>
                <td><?= htmlspecialchars($item['part_name']) ?></td>
                <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                <td class="number"><?= number_format($item['qty'], 2) ?></td>
                <td><?= htmlspecialchars($item['unit'] ?? 'Nos') ?></td>
                <td class="number"><?= number_format($item['rate'], 2) ?></td>
                <td class="number"><?= number_format($item['taxable_amount'], 2) ?></td>
                <td class="number"><?= number_format($item['cgst_amount'] + $item['sgst_amount'], 2) ?></td>
                <td class="number"><?= number_format($item['total_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" style="text-align: right;">Subtotal:</td>
                <td class="number"><?= number_format($totalTaxable, 2) ?></td>
                <td class="number"><?= number_format($totalCGST + $totalSGST, 2) ?></td>
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
            <tr>
                <td class="label">CGST:</td>
                <td class="value"><?= number_format($totalCGST, 2) ?></td>
            </tr>
            <tr>
                <td class="label">SGST:</td>
                <td class="value"><?= number_format($totalSGST, 2) ?></td>
            </tr>
            <tr class="grand-total">
                <td class="label">Grand Total:</td>
                <td class="value"><?= number_format($grandTotal, 2) ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($settings['bank_name'])): ?>
    <div class="bank-details">
        <h4>Bank Details</h4>
        <p><strong>Bank:</strong> <?= htmlspecialchars($settings['bank_name']) ?></p>
        <p><strong>Account No:</strong> <?= htmlspecialchars($settings['bank_account'] ?? '') ?></p>
        <p><strong>IFSC:</strong> <?= htmlspecialchars($settings['bank_ifsc'] ?? '') ?></p>
        <?php if (!empty($settings['bank_branch'])): ?>
            <p><strong>Branch:</strong> <?= htmlspecialchars($settings['bank_branch']) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($quote['terms_conditions'] || !empty($settings['terms_conditions'])): ?>
    <div class="terms-section">
        <h4>Terms & Conditions</h4>
        <p><?= nl2br(htmlspecialchars($quote['terms_conditions'] ?: $settings['terms_conditions'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Customer Signature</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">For <?= htmlspecialchars($settings['company_name'] ?? 'Company') ?></div>
        </div>
    </div>

</div>

</body>
</html>
