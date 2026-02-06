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

// Get e-way bills for this customer
$eway_bills = [];
try {
    // Try to get from eway_bills table
    $ewayStmt = $pdo->prepare("
        SELECT
            e.id,
            e.eway_bill_no,
            e.eway_bill_date,
            e.valid_upto,
            e.vehicle_no,
            e.transporter_name,
            e.transporter_id,
            e.distance_km,
            e.mode_of_transport,
            e.status,
            e.invoice_value,
            i.invoice_no,
            so.so_no
        FROM eway_bills e
        LEFT JOIN invoice_master i ON i.id = e.invoice_id
        LEFT JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ?
        ORDER BY e.eway_bill_date DESC
    ");
    $ewayStmt->execute([$customer_id]);
    $eway_bills = $ewayStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>E-Way Bills - <?= htmlspecialchars($customer['company_name']) ?></title>
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
        .status-active { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        .status-generated { background: #cce5ff; color: #004085; }

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

        .validity-warning {
            color: #e74c3c;
            font-size: 0.85em;
        }
        .validity-ok {
            color: #27ae60;
            font-size: 0.85em;
        }

        .eway-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .eway-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .eway-header h4 {
            margin: 0;
            color: #2c3e50;
            font-family: monospace;
            font-size: 1.2em;
        }
        .eway-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .eway-info-item {
            font-size: 0.9em;
        }
        .eway-info-item label {
            display: block;
            color: #7f8c8d;
            font-size: 0.85em;
            margin-bottom: 3px;
        }
        .eway-info-item span {
            font-weight: 600;
            color: #2c3e50;
        }
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

    <?php
    $total_bills = count($eway_bills);
    $active_count = count(array_filter($eway_bills, function($e) {
        if (!$e['valid_upto']) return false;
        return strtotime($e['valid_upto']) >= time();
    }));
    $total_value = array_sum(array_column($eway_bills, 'invoice_value'));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_bills ?></div>
            <div class="label">Total E-Way Bills</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $active_count ?></div>
            <div class="label">Active</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #667eea;"><?= number_format($total_value, 2) ?></div>
            <div class="label">Total Value (INR)</div>
        </div>
    </div>

    <?php if (empty($eway_bills)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">📃</div>
            <h3 style="color: #2c3e50;">No E-Way Bills Found</h3>
            <p style="color: #7f8c8d;">No e-way bills have been generated for this customer yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($eway_bills as $e):
            $isExpired = $e['valid_upto'] && strtotime($e['valid_upto']) < time();
            $statusClass = $isExpired ? 'expired' : 'active';
        ?>
            <div class="eway-card" style="border-left-color: <?= $isExpired ? '#e74c3c' : '#27ae60' ?>;">
                <div class="eway-header">
                    <h4><?= htmlspecialchars($e['eway_bill_no']) ?></h4>
                    <span class="status-badge status-<?= $statusClass ?>">
                        <?= $isExpired ? 'Expired' : 'Active' ?>
                    </span>
                </div>
                <div class="eway-info">
                    <div class="eway-info-item">
                        <label>Generated Date</label>
                        <span><?= $e['eway_bill_date'] ? date('d M Y', strtotime($e['eway_bill_date'])) : '-' ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Valid Until</label>
                        <span style="color: <?= $isExpired ? '#e74c3c' : '#27ae60' ?>;">
                            <?= $e['valid_upto'] ? date('d M Y H:i', strtotime($e['valid_upto'])) : '-' ?>
                        </span>
                    </div>
                    <div class="eway-info-item">
                        <label>Invoice No</label>
                        <span><?= htmlspecialchars($e['invoice_no'] ?: '-') ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Invoice Value</label>
                        <span><?= $e['invoice_value'] ? number_format($e['invoice_value'], 2) : '-' ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Vehicle No</label>
                        <span><?= htmlspecialchars($e['vehicle_no'] ?: '-') ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Transporter</label>
                        <span><?= htmlspecialchars($e['transporter_name'] ?: '-') ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Mode of Transport</label>
                        <span><?= htmlspecialchars($e['mode_of_transport'] ?: '-') ?></span>
                    </div>
                    <div class="eway-info-item">
                        <label>Distance (KM)</label>
                        <span><?= $e['distance_km'] ? number_format($e['distance_km']) . ' km' : '-' ?></span>
                    </div>
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
