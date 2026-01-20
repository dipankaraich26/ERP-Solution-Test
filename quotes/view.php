<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if (!$id) {
    header("Location: index.php");
    exit;
}

// Handle Release action
if (isset($_POST['release']) && $_POST['release'] === '1') {
    // Generate PI number
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    if ($currentMonth >= 4) {
        $fyStart = $currentYear;
        $fyEnd = $currentYear + 1;
    } else {
        $fyStart = $currentYear - 1;
        $fyEnd = $currentYear;
    }
    $fyString = substr($fyStart, 2) . '/' . substr($fyEnd, 2);

    // Count existing PIs for this FY
    $piCountStmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE pi_no LIKE ?");
    $piCountStmt->execute(['PI/%/' . $fyString]);
    $piCount = $piCountStmt->fetchColumn();
    $piSerial = $piCount + 1;
    $pi_no = 'PI/' . $piSerial . '/' . $fyString;

    // Update quote to released status
    $updateStmt = $pdo->prepare("
        UPDATE quote_master
        SET status = 'released', pi_no = ?, released_at = NOW()
        WHERE id = ? AND status != 'released'
    ");
    $updateStmt->execute([$pi_no, $id]);

    if ($updateStmt->rowCount() > 0) {
        $message = "Quotation released as Proforma Invoice: " . $pi_no;
    }
}

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

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Quotation - <?= htmlspecialchars($quote['quote_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <style>
        .quote-view { max-width: 1200px; }
        .quote-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .quote-info h2 { margin: 0 0 10px 0; color: #4a90d9; }
        .quote-info p { margin: 5px 0; }
        .customer-info { text-align: right; }
        .customer-info h3 { margin: 0 0 10px 0; }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-draft { background: #ffc107; color: #000; }
        .status-sent { background: #17a2b8; color: #fff; }
        .status-accepted { background: #28a745; color: #fff; }
        .status-rejected { background: #dc3545; color: #fff; }
        .status-expired { background: #6c757d; color: #fff; }
        .status-released { background: #28a745; color: #fff; }

        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 10px; border: 1px solid #ddd; }
        .items-table th { background: #4a90d9; color: white; }
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

        .btn-release { background: #28a745; color: white; }
        .btn-release:hover { background: #218838; }

        .pi-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        @media print {
            .sidebar, .action-buttons, .btn { display: none !important; }
            .content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="quote-view">

        <?php if ($message): ?>
        <div class="alert success" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">Back to List</a>
            <?php if ($quote['status'] !== 'released'): ?>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
                <button onclick="confirmRelease()" class="btn btn-release">Release as PI</button>
            <?php endif; ?>
            <a href="print.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary">Print View</a>
            <button onclick="exportToExcel()" class="btn btn-secondary">Export Excel</button>
            <?php if ($quote['attachment_path']): ?>
                <a href="../<?= htmlspecialchars($quote['attachment_path']) ?>" target="_blank" class="btn btn-secondary">View Attachment</a>
            <?php endif; ?>
        </div>

        <?php if ($quote['status'] === 'released' && $quote['pi_no']): ?>
        <div class="pi-info">
            <strong>Proforma Invoice:</strong> <?= htmlspecialchars($quote['pi_no']) ?>
            &nbsp;|&nbsp;
            <strong>Released:</strong> <?= htmlspecialchars($quote['released_at'] ?? '') ?>
        </div>
        <?php endif; ?>

        <div class="quote-header">
            <div class="quote-info">
                <h2>Quotation #<?= htmlspecialchars($quote['quote_no']) ?></h2>
                <?php if ($quote['pi_no']): ?>
                    <p><strong>PI No:</strong> <?= htmlspecialchars($quote['pi_no']) ?></p>
                <?php endif; ?>
                <p><strong>Reference:</strong> <?= htmlspecialchars($quote['reference'] ?? '-') ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($quote['quote_date']) ?></p>
                <p><strong>Valid Till:</strong> <?= htmlspecialchars($quote['validity_date'] ?? '-') ?></p>
                <p>
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= $quote['status'] ?>">
                        <?= ucfirst($quote['status']) ?>
                    </span>
                </p>
            </div>
            <div class="customer-info">
                <h3>Customer</h3>
                <p><strong><?= htmlspecialchars($quote['company_name'] ?? '') ?></strong></p>
                <p><?= htmlspecialchars($quote['customer_name'] ?? '') ?></p>
                <p><?= htmlspecialchars($quote['address1'] ?? '') ?></p>
                <?php if ($quote['address2']): ?>
                    <p><?= htmlspecialchars($quote['address2']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($quote['city'] ?? '') ?> - <?= htmlspecialchars($quote['pincode'] ?? '') ?></p>
                <p><?= htmlspecialchars($quote['state'] ?? '') ?></p>
                <?php if ($quote['gstin']): ?>
                    <p><strong>GSTIN:</strong> <?= htmlspecialchars($quote['gstin']) ?></p>
                <?php endif; ?>
                <?php if ($quote['contact']): ?>
                    <p><strong>Contact:</strong> <?= htmlspecialchars($quote['contact']) ?></p>
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

        <?php if ($quote['terms_conditions']): ?>
        <div class="details-section">
            <h3>Terms & Conditions</h3>
            <p><?= nl2br(htmlspecialchars($quote['terms_conditions'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($quote['notes']): ?>
        <div class="details-section">
            <h3>Notes</h3>
            <p><?= nl2br(htmlspecialchars($quote['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($quote['payment_details']): ?>
        <div class="details-section">
            <h3>Payment Details</h3>
            <p><?= nl2br(htmlspecialchars($quote['payment_details'])) ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Hidden form for release -->
<form id="releaseForm" method="post" style="display:none;">
    <input type="hidden" name="release" value="1">
</form>

<script>
function exportToExcel() {
    const table = document.getElementById('itemsTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "Quotation" });
    XLSX.writeFile(wb, "Quotation_<?= $quote['quote_no'] ?>.xlsx");
}

function confirmRelease() {
    if (confirm('Are you sure you want to release this quotation as a Proforma Invoice?\n\nThis action cannot be undone.')) {
        document.getElementById('releaseForm').submit();
    }
}
</script>

</body>
</html>
