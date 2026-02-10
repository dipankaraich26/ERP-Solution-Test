<?php
include "../db.php";
include "../includes/auth.php";
include "../includes/procurement_helper.php";
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

// Look up production progress for each SO via linked procurement plans
try {
    $ppStmt = $pdo->query("
        SELECT id, plan_no, status, so_list
        FROM procurement_plans
        WHERE status NOT IN ('cancelled')
        ORDER BY id DESC
    ");
    $allPlans = $ppStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allPlans = [];
}

foreach ($orders as &$o) {
    $o['pp_progress'] = null;
    $o['pp_id'] = null;
    $o['pp_no'] = null;
    $o['pp_status'] = null;
    foreach ($allPlans as $pp) {
        $soList = array_map('trim', explode(',', $pp['so_list'] ?? ''));
        if (in_array($o['so_no'], $soList)) {
            $o['pp_id'] = $pp['id'];
            $o['pp_no'] = $pp['plan_no'];
            $o['pp_status'] = $pp['status'];
            try {
                $progress = calculatePlanProgress($pdo, (int)$pp['id'], $pp['status']);
                $o['pp_progress'] = $progress['percentage'];
            } catch (Exception $e) {
                $o['pp_progress'] = 0;
            }
            break;
        }
    }

    // Check real-time stock status for this SO
    $o['stock_ok'] = false;
    try {
        $soLines = $pdo->prepare("SELECT part_no, qty FROM sales_orders WHERE so_no = ? AND status NOT IN ('cancelled')");
        $soLines->execute([$o['so_no']]);
        $lines = $soLines->fetchAll(PDO::FETCH_ASSOC);
        $allOk = true;
        foreach ($lines as $line) {
            $stkQ = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stkQ->execute([$line['part_no']]);
            if ((int)$stkQ->fetchColumn() < (int)$line['qty']) {
                $allOk = false;
                break;
            }
        }
        $o['stock_ok'] = $allOk;
    } catch (Exception $e) {}

    // Determine SO lifecycle step (1-6)
    $status = strtolower($o['status'] ?? '');
    $steps = [
        'ordered'    => true, // always done
        'pp_created' => !empty($o['pp_id']),
        'production' => $o['pp_progress'] !== null && $o['pp_progress'] > 0,
        'stock_ready'=> $o['stock_ok'] || $status === 'released',
        'released'   => $status === 'released',
        'invoiced'   => ($o['invoice_count'] ?? 0) > 0,
    ];
    $o['steps'] = $steps;

    // Calculate overall SO progress percentage
    $totalSteps = count($steps);
    $doneSteps = count(array_filter($steps));
    $o['so_progress'] = round(($doneSteps / $totalSteps) * 100);
}
unset($o);

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
            gap: 0;
            font-size: 0.9em;
            justify-content: center;
        }
        .progress-step {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65em;
            color: white;
            position: relative;
            flex-shrink: 0;
            cursor: default;
        }
        .progress-step.done { background: #27ae60; }
        .progress-step.active { background: #3498db; animation: pulse 1.5s infinite; }
        .progress-step .step-label {
            position: absolute;
            top: 32px;
            white-space: nowrap;
            font-size: 0.85em;
            color: #888;
            font-weight: normal;
        }
        .progress-step.done .step-label { color: #27ae60; font-weight: 500; }
        .progress-step.active .step-label { color: #3498db; font-weight: 500; }
        .progress-line {
            width: 24px;
            height: 3px;
            background: #e9ecef;
            flex-shrink: 0;
        }
        .progress-line.done { background: #27ae60; }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(52,152,219,0.4); }
            50% { box-shadow: 0 0 0 6px rgba(52,152,219,0); }
        }

        .so-progress-cell {
            min-width: 320px;
            padding-bottom: 25px !important;
        }
        .so-progress-pct {
            font-size: 0.8em;
            font-weight: bold;
            margin-bottom: 6px;
            text-align: center;
        }

        .order-detail-row td {
            padding: 0 12px 12px 12px !important;
            border-bottom: 2px solid #e9ecef;
        }
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
    $released_count = count(array_filter($orders, fn($o) => strtolower($o['status'] ?? '') === 'released'));
    $in_production = count(array_filter($orders, fn($o) => $o['pp_id'] && $o['pp_progress'] !== null && $o['pp_progress'] < 100));
    $invoiced_count = count(array_filter($orders, fn($o) => ($o['invoice_count'] ?? 0) > 0));
    $pending_count = count(array_filter($orders, fn($o) => !in_array(strtolower($o['status'] ?? ''), ['released', 'cancelled'])));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_orders ?></div>
            <div class="label">Total Orders</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #f39c12;"><?= $in_production ?></div>
            <div class="label">In Production</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $released_count ?></div>
            <div class="label">Released</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #8e44ad;"><?= $invoiced_count ?></div>
            <div class="label">Invoiced</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #e67e22;"><?= $pending_count ?></div>
            <div class="label">Pending</div>
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
                        <th class="text-center">SO Progress</th>
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
                        <td class="text-center so-progress-cell">
                            <?php
                            $soPct = $o['so_progress'];
                            $soPctColor = $soPct >= 100 ? '#27ae60' : ($soPct >= 50 ? '#f39c12' : '#3498db');
                            $st = $o['steps'];
                            $stepDefs = [
                                ['key' => 'ordered',     'label' => 'Order',   'icon' => '1'],
                                ['key' => 'pp_created',  'label' => 'Plan',    'icon' => '2'],
                                ['key' => 'production',  'label' => 'Prod',    'icon' => '3'],
                                ['key' => 'stock_ready', 'label' => 'Stock',   'icon' => '4'],
                                ['key' => 'released',    'label' => 'Release', 'icon' => '5'],
                                ['key' => 'invoiced',    'label' => 'Invoice', 'icon' => '6'],
                            ];
                            // Find current active step (first not-done step)
                            $activeIdx = -1;
                            foreach ($stepDefs as $i => $sd) {
                                if (!$st[$sd['key']]) { $activeIdx = $i; break; }
                            }
                            ?>
                            <div class="so-progress-pct" style="color: <?= $soPctColor ?>;">
                                <?= $soPct ?>% Complete
                                <?php if ($o['pp_no']): ?>
                                    <span style="color: #999; font-weight: normal;">(<?= htmlspecialchars($o['pp_no']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="order-progress">
                                <?php foreach ($stepDefs as $i => $sd):
                                    $isDone = $st[$sd['key']];
                                    $isActive = ($i === $activeIdx);
                                    $cls = $isDone ? 'done' : ($isActive ? 'active' : '');
                                ?>
                                    <?php if ($i > 0): ?>
                                        <div class="progress-line <?= $isDone ? 'done' : '' ?>"></div>
                                    <?php endif; ?>
                                    <div class="progress-step <?= $cls ?>" title="<?= $sd['label'] ?>">
                                        <?= $isDone ? '&#10003;' : $sd['icon'] ?>
                                        <span class="step-label"><?= $sd['label'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
