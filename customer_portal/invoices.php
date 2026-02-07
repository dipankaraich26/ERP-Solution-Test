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

// Get invoices for this customer
$invoices = [];
try {
    $invStmt = $pdo->prepare("
        SELECT
            i.id,
            i.invoice_no,
            i.so_no,
            i.invoice_date,
            i.released_at,
            i.status,
            i.eway_bill_no,
            i.eway_bill_attachment,
            cp.po_no as customer_po_no,
            q.pi_no,
            (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value
        FROM invoice_master i
        JOIN (
            SELECT DISTINCT so_no, customer_po_id, linked_quote_id, customer_id
            FROM sales_orders
        ) so ON so.so_no = i.so_no
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        LEFT JOIN quote_master q ON q.id = so.linked_quote_id
        WHERE so.customer_id = ?
        ORDER BY i.invoice_date DESC, i.id DESC
    ");
    $invStmt->execute([$customer_id]);
    $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Invoices - <?= htmlspecialchars($customer['company_name']) ?></title>
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
        .status-draft { background: #fff3cd; color: #856404; }
        .status-released { background: #d4edda; color: #155724; }
        .status-paid { background: #cce5ff; color: #004085; }

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
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        Invoices
    </div>

    <div class="page-header">
        <h1>Invoices</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <?php
    $total_invoices = count($invoices);
    $total_value = array_sum(array_column($invoices, 'total_value'));
    $released_count = count(array_filter($invoices, fn($i) => $i['status'] === 'Released'));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_invoices ?></div>
            <div class="label">Total Invoices</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $released_count ?></div>
            <div class="label">Released</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #667eea;"><?= number_format($total_value, 2) ?></div>
            <div class="label">Total Value (INR)</div>
        </div>
    </div>

    <?php if (empty($invoices)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">🧾</div>
            <h3 style="color: #2c3e50;">No Invoices Found</h3>
            <p style="color: #7f8c8d;">No invoices have been generated for this customer yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>SO No</th>
                        <th>Customer PO</th>
                        <th>PI No</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $index => $inv): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
                        <td><?= $inv['invoice_date'] ? date('d M Y', strtotime($inv['invoice_date'])) : '-' ?></td>
                        <td><?= htmlspecialchars($inv['so_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($inv['customer_po_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($inv['pi_no'] ?: '-') ?></td>
                        <td class="text-right" style="font-weight: bold;">
                            <?= $inv['total_value'] ? number_format($inv['total_value'], 2) : '-' ?>
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-<?= strtolower($inv['status']) ?>">
                                <?= htmlspecialchars($inv['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="/invoices/print.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary" target="_blank">Invoice PDF</a>
                            <?php if (!empty($inv['eway_bill_attachment'])): ?>
                                <a href="/<?= htmlspecialchars($inv['eway_bill_attachment']) ?>" class="btn btn-sm" style="background: #e74c3c; color: white;" target="_blank">E-Way Bill</a>
                            <?php endif; ?>
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
