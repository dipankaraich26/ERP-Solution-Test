<?php
include "../db.php";

$so_no = $_GET['so_no'] ?? '';

if (!$so_no) {
    header("Location: index.php");
    exit;
}

// Fetch company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch sales order items with part details and current stock
$stmt = $pdo->prepare("
    SELECT so.*, p.part_name, p.hsn_code, p.uom,
           c.company_name, c.customer_name, c.address1, c.address2,
           c.city, c.state, c.pincode, c.gstin, c.contact,
           cp.po_no as customer_po_no, cp.po_date,
           q.pi_no, q.quote_no,
           COALESCE(inv.qty, 0) as current_stock
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    LEFT JOIN inventory inv ON inv.part_no = so.part_no
    WHERE so.so_no = ?
    ORDER BY so.id
");
$stmt->execute([$so_no]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    header("Location: index.php");
    exit;
}

// Get order header info from first item
$order = $items[0];

$document_title = 'SALES ORDER';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Order - <?= htmlspecialchars($so_no) ?></title>
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

        .status-box {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-released { background: #28a745; color: #fff; }
        .status-open { background: #17a2b8; color: #fff; }

        .stock-ok { color: #28a745; font-weight: bold; }
        .stock-low { color: #dc3545; font-weight: bold; }
        .stock-warning { color: #ffc107; font-weight: bold; }

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
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover { background: #138496; }

        @media print {
            body { padding: 0; }
            .print-btn { display: none; }
            .print-container { max-width: 100%; }
        }
    </style>
</head>
<body>

<div style="position: fixed; top: 20px; right: 20px;">
    <button class="print-btn" style="background: #6c757d; margin-right: 10px;" onclick="window.location.href='view.php?so_no=<?= urlencode($so_no) ?>'">Back</button>
    <button class="print-btn" onclick="window.print()">Print</button>
</div>

<div class="print-container">

    <?php include "../includes/company_header.php"; ?>

    <div class="doc-info">
        <div class="order-box">
            <h4>Sales Order Details</h4>
            <p><strong><?= htmlspecialchars($so_no) ?></strong></p>
            <p>Date: <?= htmlspecialchars($order['sales_date']) ?></p>
            <p>Status: <span class="status-box status-<?= strtolower($order['status']) ?>"><?= ucfirst($order['status']) ?></span></p>
            <?php if ($order['customer_po_no']): ?>
                <p style="margin-top: 10px;">Customer PO: <?= htmlspecialchars($order['customer_po_no']) ?></p>
            <?php endif; ?>
            <?php if ($order['pi_no']): ?>
                <p>PI Ref: <?= htmlspecialchars($order['pi_no']) ?></p>
            <?php endif; ?>
        </div>
        <div class="customer-box">
            <h4>Customer</h4>
            <p><strong><?= htmlspecialchars($order['company_name'] ?? $order['customer_name'] ?? '') ?></strong></p>
            <?php if ($order['customer_name'] && $order['company_name']): ?>
                <p><?= htmlspecialchars($order['customer_name']) ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars($order['address1'] ?? '') ?></p>
            <?php if ($order['address2']): ?>
                <p><?= htmlspecialchars($order['address2']) ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars(implode(', ', array_filter([
                $order['city'] ?? '',
                $order['state'] ?? '',
                $order['pincode'] ?? ''
            ]))) ?></p>
            <?php if ($order['gstin']): ?>
                <p>GSTIN: <?= htmlspecialchars($order['gstin']) ?></p>
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
                <th style="width: 70px;">Req Qty</th>
                <th style="width: 50px;">Unit</th>
                <th style="width: 80px;">Current Stock</th>
                <th style="width: 60px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $totalQty = 0; $stockIssues = 0; ?>
            <?php foreach ($items as $i => $item): ?>
            <?php
                $totalQty += $item['qty'];
                $currentStock = $item['current_stock'];
                $requiredQty = $item['qty'];
                $stockClass = 'stock-ok';
                $stockStatus = 'OK';

                if ($currentStock < $requiredQty) {
                    $stockClass = 'stock-low';
                    $stockStatus = 'LOW';
                    $stockIssues++;
                } elseif ($currentStock < ($requiredQty * 1.5)) {
                    $stockClass = 'stock-warning';
                    $stockStatus = 'Limited';
                }
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($item['part_no']) ?></td>
                <td><?= htmlspecialchars($item['part_name']) ?></td>
                <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                <td class="number"><?= number_format($item['qty'], 2) ?></td>
                <td><?= htmlspecialchars($item['uom'] ?? 'Nos') ?></td>
                <td class="number <?= $stockClass ?>"><?= number_format($currentStock, 2) ?></td>
                <td class="<?= $stockClass ?>" style="text-align: center;"><?= $stockStatus ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right;">Total Items: <?= count($items) ?></td>
                <td class="number"><?= number_format($totalQty, 2) ?></td>
                <td></td>
                <td colspan="2" style="text-align: center;">
                    <?php if ($stockIssues > 0): ?>
                        <span class="stock-low"><?= $stockIssues ?> item(s) low</span>
                    <?php else: ?>
                        <span class="stock-ok">All OK</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Prepared By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Approved By</div>
        </div>
    </div>

</div>

</body>
</html>
