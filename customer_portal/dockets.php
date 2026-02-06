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

// Get delivery dockets for this customer
$dockets = [];
try {
    // Try to get dockets from delivery/dispatch table
    $docketStmt = $pdo->prepare("
        SELECT
            d.id,
            d.docket_no,
            d.dispatch_date,
            d.courier_name,
            d.tracking_no,
            d.delivery_status,
            d.expected_delivery,
            d.actual_delivery,
            d.remarks,
            i.invoice_no,
            so.so_no
        FROM delivery_dockets d
        LEFT JOIN invoice_master i ON i.id = d.invoice_id
        LEFT JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ?
        ORDER BY d.dispatch_date DESC
    ");
    $docketStmt->execute([$customer_id]);
    $dockets = $docketStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist, try alternate table structure
    try {
        $docketStmt = $pdo->prepare("
            SELECT
                d.id,
                d.docket_no,
                d.dispatch_date,
                d.transporter as courier_name,
                d.lr_no as tracking_no,
                d.status as delivery_status,
                d.expected_date as expected_delivery,
                d.delivered_date as actual_delivery,
                d.notes as remarks,
                d.invoice_no,
                d.so_no
            FROM dispatches d
            LEFT JOIN sales_orders so ON so.so_no = d.so_no
            WHERE so.customer_id = ?
            ORDER BY d.dispatch_date DESC
        ");
        $docketStmt->execute([$customer_id]);
        $dockets = $docketStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        // Neither table exists
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Docket Details - <?= htmlspecialchars($customer['company_name']) ?></title>
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
        .data-table .text-center { text-align: center; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-dispatched { background: #cce5ff; color: #004085; }
        .status-in-transit { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-returned { background: #f8d7da; color: #721c24; }

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

        .tracking-link {
            color: #3498db;
            text-decoration: none;
        }
        .tracking-link:hover {
            text-decoration: underline;
        }

        .docket-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 15px;
        }
        .docket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .docket-header h4 {
            margin: 0;
            color: #2c3e50;
        }
        .docket-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .docket-info-item {
            font-size: 0.9em;
        }
        .docket-info-item label {
            display: block;
            color: #7f8c8d;
            font-size: 0.85em;
            margin-bottom: 3px;
        }
        .docket-info-item span {
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
        Docket Details
    </div>

    <div class="page-header">
        <h1>Delivery Dockets</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <?php
    $total_dockets = count($dockets);
    $delivered_count = count(array_filter($dockets, fn($d) => strtolower($d['delivery_status'] ?? '') === 'delivered'));
    $in_transit_count = count(array_filter($dockets, fn($d) => in_array(strtolower($d['delivery_status'] ?? ''), ['dispatched', 'in-transit', 'in transit'])));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_dockets ?></div>
            <div class="label">Total Shipments</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $delivered_count ?></div>
            <div class="label">Delivered</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #3498db;"><?= $in_transit_count ?></div>
            <div class="label">In Transit</div>
        </div>
    </div>

    <?php if (empty($dockets)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">🚚</div>
            <h3 style="color: #2c3e50;">No Dockets Found</h3>
            <p style="color: #7f8c8d;">No delivery dockets have been created for this customer yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($dockets as $d): ?>
            <div class="docket-card">
                <div class="docket-header">
                    <h4>
                        <?= htmlspecialchars($d['docket_no'] ?: 'Docket #' . $d['id']) ?>
                    </h4>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $d['delivery_status'] ?? 'pending')) ?>">
                        <?= htmlspecialchars($d['delivery_status'] ?: 'Pending') ?>
                    </span>
                </div>
                <div class="docket-info">
                    <div class="docket-info-item">
                        <label>Dispatch Date</label>
                        <span><?= $d['dispatch_date'] ? date('d M Y', strtotime($d['dispatch_date'])) : '-' ?></span>
                    </div>
                    <div class="docket-info-item">
                        <label>Courier / Transporter</label>
                        <span><?= htmlspecialchars($d['courier_name'] ?: '-') ?></span>
                    </div>
                    <div class="docket-info-item">
                        <label>Tracking / LR No</label>
                        <span>
                            <?php if ($d['tracking_no']): ?>
                                <a href="#" class="tracking-link"><?= htmlspecialchars($d['tracking_no']) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="docket-info-item">
                        <label>Invoice No</label>
                        <span><?= htmlspecialchars($d['invoice_no'] ?: '-') ?></span>
                    </div>
                    <div class="docket-info-item">
                        <label>SO No</label>
                        <span><?= htmlspecialchars($d['so_no'] ?: '-') ?></span>
                    </div>
                    <div class="docket-info-item">
                        <label>Expected Delivery</label>
                        <span><?= $d['expected_delivery'] ? date('d M Y', strtotime($d['expected_delivery'])) : '-' ?></span>
                    </div>
                    <div class="docket-info-item">
                        <label>Actual Delivery</label>
                        <span style="color: <?= $d['actual_delivery'] ? '#27ae60' : '#7f8c8d' ?>;">
                            <?= $d['actual_delivery'] ? date('d M Y', strtotime($d['actual_delivery'])) : 'Pending' ?>
                        </span>
                    </div>
                    <?php if ($d['remarks']): ?>
                    <div class="docket-info-item" style="grid-column: 1 / -1;">
                        <label>Remarks</label>
                        <span><?= htmlspecialchars($d['remarks']) ?></span>
                    </div>
                    <?php endif; ?>
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
