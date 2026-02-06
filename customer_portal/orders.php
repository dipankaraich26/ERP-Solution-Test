<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$customer_id) {
    header("Location: index.php");
    exit;
}

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: index.php");
    exit;
}

// Get sales orders for this customer
$orders = [];
try {
    $orderStmt = $pdo->prepare("
        SELECT
            so.id,
            so.so_no,
            so.customer_po_id,
            so.created_at,
            so.status,
            cp.po_no as customer_po_no,
            cp.po_date as customer_po_date,
            q.pi_no,
            q.quote_no,
            (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value,
            (SELECT COUNT(*) FROM invoice_master WHERE so_no = so.so_no) as invoice_count
        FROM sales_orders so
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        LEFT JOIN quote_master q ON q.id = so.linked_quote_id
        WHERE so.customer_id = ?
        GROUP BY so.so_no
        ORDER BY so.created_at DESC
    ");
    $orderStmt->execute([$customer_id]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Status - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .breadcrumb {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .customer-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }

        .table-scroll-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .data-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .summary-bar {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .summary-item {
            text-align: center;
        }
        .summary-item .value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        .summary-item .label {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .order-progress {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        .progress-step {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            color: white;
        }
        .progress-step.done { background: #27ae60; }
        .progress-step.active { background: #3498db; }
        .progress-line {
            width: 20px;
            height: 3px;
            background: #e9ecef;
        }
        .progress-line.done { background: #27ae60; }
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        Order Status
    </div>

    <div class="page-header">
        <h1>Order Status</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <?php
    $total_orders = count($orders);
    $total_value = array_sum(array_column($orders, 'total_value'));
    $completed_count = count(array_filter($orders, fn($o) => in_array(strtolower($o['status'] ?? ''), ['delivered', 'completed'])));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_orders ?></div>
            <div class="label">Total Orders</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $completed_count ?></div>
            <div class="label">Completed</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #3498db;"><?= number_format($total_value, 2) ?></div>
            <div class="label">Total Value (INR)</div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">📦</div>
            <h3 style="color: #2c3e50;">No Orders Found</h3>
            <p style="color: #7f8c8d;">No sales orders have been created for this customer yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SO No</th>
                        <th>Customer PO</th>
                        <th>Quote / PI</th>
                        <th>Date</th>
                        <th class="text-right">Value</th>
                        <th class="text-center">Invoice</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $index => $o): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= htmlspecialchars($o['so_no']) ?></strong></td>
                        <td>
                            <?php if ($o['customer_po_no']): ?>
                                <?= htmlspecialchars($o['customer_po_no']) ?>
                                <?php if ($o['customer_po_date']): ?>
                                    <br><small style="color: #7f8c8d;"><?= date('d M Y', strtotime($o['customer_po_date'])) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['pi_no']): ?>
                                <?= htmlspecialchars($o['pi_no']) ?>
                            <?php elseif ($o['quote_no']): ?>
                                <?= htmlspecialchars($o['quote_no']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $o['created_at'] ? date('d M Y', strtotime($o['created_at'])) : '-' ?></td>
                        <td class="text-right" style="font-weight: bold;">
                            <?= $o['total_value'] ? number_format($o['total_value'], 2) : '-' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($o['invoice_count'] > 0): ?>
                                <span style="background: #27ae60; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.85em;">
                                    <?= $o['invoice_count'] ?> Invoice(s)
                                </span>
                            <?php else: ?>
                                <span style="color: #adb5bd;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-<?= strtolower($o['status'] ?: 'pending') ?>">
                                <?= htmlspecialchars($o['status'] ?: 'Pending') ?>
                            </span>
                        </td>
                        <td>
                            <a href="/sales_orders/view.php?so_no=<?= urlencode($o['so_no']) ?>" class="btn btn-sm" target="_blank">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="index.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary">&larr; Back to Portal</a>
    </div>
</div>

</body>
</html>
