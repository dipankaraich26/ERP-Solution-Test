<?php
include "../db.php";

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

// Fetch PI items if PI exists
$items = [];
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$grandTotal = 0;

if ($chain && $chain['pi_id']) {
    $itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
    $itemsStmt->execute([$chain['pi_id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $totalTaxable += $item['taxable_amount'];
        $totalCGST += $item['cgst_amount'];
        $totalSGST += $item['sgst_amount'];
        $grandTotal += $item['total_amount'];
    }
}

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
        .customer-info { text-align: right; }
        .customer-info h3 { margin: 0 0 10px 0; }

        .chain-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        .chain-box.so h4 { border-color: #17a2b8; color: #17a2b8; }
        .chain-box.po h4 { border-color: #ffc107; color: #856404; }
        .chain-box.pi h4 { border-color: #28a745; color: #28a745; }
        .chain-box p { margin: 5px 0; font-size: 0.9em; }

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
            .customer-info { text-align: left; margin-top: 20px; }
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
            <?php if ($invoice['status'] === 'draft'): ?>
                <a href="release.php?id=<?= $invoice['id'] ?>" class="btn btn-success"
                   onclick="return confirm('Release this Invoice?\n\nInventory will be deducted for all associated parts.')">
                    Release Invoice
                </a>
            <?php endif; ?>
        </div>

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
            <div class="customer-info">
                <h3>Customer</h3>
                <?php if ($chain): ?>
                    <p><strong><?= htmlspecialchars($chain['company_name'] ?? '') ?></strong></p>
                    <p><?= htmlspecialchars($chain['customer_name'] ?? '') ?></p>
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
                    <p>-</p>
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
                <p><strong>Reference:</strong> <?= htmlspecialchars($chain['reference'] ?? '-') ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($chain['quote_date'] ?? '-') ?></p>
                <p><strong>Validity:</strong> <?= htmlspecialchars($chain['validity_date'] ?? '-') ?></p>
                <?php if ($chain && $chain['pi_id']): ?>
                    <p><a href="/proforma/view.php?id=<?= $chain['pi_id'] ?>">View PI Details</a></p>
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
                        <th>Description</th>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center;">No items found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['part_no']) ?></td>
                        <td><?= htmlspecialchars($item['part_name']) ?></td>
                        <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                        <td class="number"><?= number_format($item['qty'], 3) ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                        <td class="number"><?= number_format($item['rate'], 2) ?></td>
                        <td class="number"><?= number_format($item['discount'], 2) ?>%</td>
                        <td class="number"><?= number_format($item['taxable_amount'], 2) ?></td>
                        <td class="number">
                            <?= number_format($item['cgst_amount'], 2) ?>
                            <small>(<?= number_format($item['cgst_percent'], 1) ?>%)</small>
                        </td>
                        <td class="number">
                            <?= number_format($item['sgst_amount'], 2) ?>
                            <small>(<?= number_format($item['sgst_percent'], 1) ?>%)</small>
                        </td>
                        <td class="number"><?= number_format($item['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($item['lead_time'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($items)): ?>
                <tfoot>
                    <tr class="totals-row">
                        <td colspan="8" style="text-align: right;"><strong>Totals:</strong></td>
                        <td class="number"><?= number_format($totalTaxable, 2) ?></td>
                        <td class="number"><?= number_format($totalCGST, 2) ?></td>
                        <td class="number"><?= number_format($totalSGST, 2) ?></td>
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

    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('itemsTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Tax Invoice" });
    XLSX.writeFile(wb, "Invoice_<?= str_replace('/', '_', $invoice['invoice_no']) ?>.xlsx");
}
</script>

</body>
</html>
