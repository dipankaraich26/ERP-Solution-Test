<?php
include "../db.php";

$so_no = $_GET['so_no'] ?? '';

if (!$so_no) {
    header("Location: index.php");
    exit;
}

// Fetch sales order items
$stmt = $pdo->prepare("
    SELECT so.*, p.part_name, p.hsn_code, p.uom,
           COALESCE(inv.qty, 0) as current_stock,
           c.company_name, c.customer_name,
           cp.po_no as customer_po_no,
           q.pi_no
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    LEFT JOIN inventory inv ON inv.part_no = so.part_no
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
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

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Order - <?= htmlspecialchars($so_no) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .so-view { max-width: 1000px; }
        .so-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .so-info h2 { margin: 0 0 15px 0; color: #4a90d9; }
        .so-info p { margin: 5px 0; }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-open { background: #3498db; color: #fff; }
        .status-pending { background: #ffc107; color: #000; }
        .status-released { background: #28a745; color: #fff; }
        .status-completed { background: #17a2b8; color: #fff; }

        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 10px; border: 1px solid #ddd; }
        .items-table th { background: #4a90d9; color: white; }
        .items-table tr:nth-child(even) { background: #f9f9f9; }
        .items-table td.number { text-align: right; }

        .stock-ok { color: #28a745; font-weight: bold; }
        .stock-low { color: #dc3545; font-weight: bold; }
        .stock-indicator {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .stock-indicator.ok { background: #d4edda; color: #155724; }
        .stock-indicator.insufficient { background: #f8d7da; color: #721c24; }

        .action-buttons { margin: 20px 0; }
        .action-buttons .btn { margin-right: 10px; }

        @media print {
            .sidebar, .action-buttons, .btn { display: none !important; }
            .content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="so-view">

        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">Back to List</a>
            <?php if ($order['status'] === 'open'): ?>
                <a class="btn btn-success" href="release_checklist.php?so_no=<?= urlencode($so_no) ?>">
                    Release Checklist
                </a>
            <?php endif; ?>
            <a href="print.php?so_no=<?= urlencode($so_no) ?>" target="_blank" class="btn btn-secondary">Print View</a>
        </div>

        <div class="so-header">
            <div class="so-info">
                <h2>Sales Order: <?= htmlspecialchars($so_no) ?></h2>
                <p><strong>Customer PO:</strong> <?= htmlspecialchars($order['customer_po_no'] ?? '-') ?></p>
                <p><strong>Proforma Invoice:</strong>
                    <?php if ($order['pi_no']): ?>
                        <a href="/proforma/view.php?id=<?= $order['linked_quote_id'] ?>">
                            <?= htmlspecialchars($order['pi_no']) ?>
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </p>
                <p><strong>Date:</strong> <?= htmlspecialchars($order['sales_date']) ?></p>
                <p>
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= $order['status'] ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </p>
            </div>
            <div style="text-align: right;">
                <h3>Customer</h3>
                <p><strong><?= htmlspecialchars($order['company_name'] ?? '') ?></strong></p>
                <p><?= htmlspecialchars($order['customer_name'] ?? '') ?></p>
            </div>
        </div>

        <h3>Items</h3>
        <div style="overflow-x: auto;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>HSN</th>
                        <th>UOM</th>
                        <th>Required Qty</th>
                        <th>Current Stock</th>
                        <th>Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <?php
                        $stockOk = $item['current_stock'] >= $item['qty'];
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['part_no']) ?></td>
                        <td><?= htmlspecialchars($item['part_name']) ?></td>
                        <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['uom'] ?? '') ?></td>
                        <td class="number"><?= number_format($item['qty']) ?></td>
                        <td class="number <?= $stockOk ? 'stock-ok' : 'stock-low' ?>">
                            <?= number_format($item['current_stock']) ?>
                        </td>
                        <td>
                            <?php if ($stockOk): ?>
                                <span class="stock-indicator ok">Sufficient</span>
                            <?php else: ?>
                                <span class="stock-indicator insufficient">
                                    Insufficient (Need <?= $item['qty'] - $item['current_stock'] ?> more)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalRequired = array_sum(array_column($items, 'qty'));
        $insufficientCount = count(array_filter($items, fn($i) => $i['current_stock'] < $i['qty']));
        ?>

        <div style="margin-top: 20px; padding: 15px; background: <?= $insufficientCount ? '#fff3cd' : '#d4edda' ?>; border-radius: 8px;">
            <strong>Summary:</strong>
            <?= count($items) ?> items |
            Total Qty: <?= number_format($totalRequired) ?> |
            <?php if ($insufficientCount): ?>
                <span style="color: #856404;"><?= $insufficientCount ?> items have insufficient stock</span>
            <?php else: ?>
                <span style="color: #155724;">All items have sufficient stock</span>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>
