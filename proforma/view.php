<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch PI (released quotation)
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name, c.contact, c.email,
           c.address1, c.address2, c.city, c.pincode, c.state, c.gstin
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    WHERE q.id = ? AND q.status = 'released'
");
$stmt->execute([$id]);
$pi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pi) {
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
                <a href="../<?= htmlspecialchars($pi['attachment_path']) ?>" target="_blank" class="btn btn-secondary">View Attachment</a>
            <?php endif; ?>
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
                </tbody>
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
            </table>
        </div>

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
</script>

</body>
</html>
