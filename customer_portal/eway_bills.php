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

// E-Way bills are stored in invoice_master
$eway_bills = [];
try {
    $ewayStmt = $pdo->prepare("
        SELECT i.id, i.invoice_no, i.invoice_date, i.eway_bill_no, i.eway_bill_attachment, i.status
        FROM invoice_master i
        JOIN (SELECT DISTINCT so_no, customer_id FROM sales_orders) so ON so.so_no = i.so_no
        WHERE so.customer_id = ?
          AND i.eway_bill_no IS NOT NULL AND i.eway_bill_no != ''
        ORDER BY i.invoice_date DESC
    ");
    $ewayStmt->execute([$customer_id]);
    $eway_bills = $ewayStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>E-Way Bills - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .breadcrumb { color: #7f8c8d; margin-bottom: 10px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .customer-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; }
        .summary-bar { background: #f8f9fa; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 30px; flex-wrap: wrap; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .summary-item .label { font-size: 0.85em; color: #7f8c8d; }

        .eway-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 15px; overflow: hidden; }
        .eway-card-header { padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #f0f0f0; }
        .eway-card-header h4 { margin: 0; color: #2c3e50; }
        .eway-card-header .invoice-ref { color: #7f8c8d; font-size: 0.9em; }

        .pdf-section { padding: 20px; display: flex; gap: 15px; flex-wrap: wrap; }
        .pdf-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95em; transition: all 0.3s; }
        .pdf-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .pdf-btn .pdf-icon { font-size: 1.5em; }
        .pdf-btn-eway { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; }
        .pdf-btn-invoice { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; }
        .pdf-btn-disabled { background: #e9ecef; color: #6c757d; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        E-Way Bills
    </div>

    <div class="page-header">
        <h1>E-Way Bills</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= count($eway_bills) ?></div>
            <div class="label">Total E-Way Bills</div>
        </div>
    </div>

    <?php if (empty($eway_bills)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">📃</div>
            <h3 style="color: #2c3e50;">No E-Way Bills Found</h3>
            <p style="color: #7f8c8d;">No e-way bills have been generated for this customer yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($eway_bills as $e): ?>
        <div class="eway-card">
            <div class="eway-card-header">
                <div>
                    <h4>E-Way Bill: <?= htmlspecialchars($e['eway_bill_no']) ?></h4>
                    <span class="invoice-ref">Invoice: <?= htmlspecialchars($e['invoice_no']) ?> | Date: <?= $e['invoice_date'] ? date('d M Y', strtotime($e['invoice_date'])) : '-' ?></span>
                </div>
            </div>
            <div class="pdf-section">
                <?php if (!empty($e['eway_bill_attachment'])): ?>
                    <a href="/<?= htmlspecialchars($e['eway_bill_attachment']) ?>" class="pdf-btn pdf-btn-eway" target="_blank">
                        <span class="pdf-icon">📃</span> View E-Way Bill PDF
                    </a>
                <?php else: ?>
                    <span class="pdf-btn pdf-btn-disabled"><span class="pdf-icon">📃</span> E-Way Bill PDF Not Available</span>
                <?php endif; ?>
                <a href="/invoices/print.php?id=<?= $e['id'] ?>" class="pdf-btn pdf-btn-invoice" target="_blank">
                    <span class="pdf-icon">🧾</span> View Invoice PDF
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="index.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary">&larr; Back to Portal</a>
    </div>
</div>

</body>
</html>
