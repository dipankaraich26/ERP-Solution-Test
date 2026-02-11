<?php
session_start();
include "../db.php";
include "../includes/procurement_helper.php";

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) { header("Location: logout.php"); exit; }

$customer_code = $customer['customer_id'] ?? ($_SESSION['customer_code'] ?? '');

$orders = [];
try {
    $orderStmt = $pdo->prepare("
        SELECT MIN(so.id) as id, so.so_no, MAX(so.created_at) as created_at, MAX(so.status) as status,
               MAX(cp.po_no) as customer_po_no,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = MAX(so.linked_quote_id)) as total_value,
               (SELECT COUNT(*) FROM invoice_master WHERE so_no = so.so_no) as invoice_count
        FROM sales_orders so
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        WHERE so.customer_id = ?
           OR so.customer_id = ?
           OR so.customer_po_id IN (SELECT cpo.id FROM customer_po cpo WHERE cpo.customer_id = ?)
        GROUP BY so.so_no ORDER BY MAX(so.created_at) DESC
    ");
    $orderStmt->execute([$customer_id, $customer_code, $customer_code]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders_error = $e->getMessage();
}

// Get procurement plan progress + stock status + lifecycle steps for each SO
foreach ($orders as &$o) {
    $o['pp_progress'] = null;
    $o['pp_no'] = null;
    $o['pp_status'] = null;
    try {
        $ppStmt = $pdo->prepare("
            SELECT pp.id, pp.plan_no, pp.status
            FROM procurement_plans pp
            WHERE FIND_IN_SET(?, REPLACE(pp.so_list, ' ', '')) > 0
            AND pp.status NOT IN ('cancelled')
            LIMIT 1
        ");
        $ppStmt->execute([$o['so_no']]);
        $pp = $ppStmt->fetch(PDO::FETCH_ASSOC);
        if ($pp) {
            $o['pp_no'] = $pp['plan_no'];
            $o['pp_status'] = $pp['status'];
            try {
                $progress = calculatePlanProgress($pdo, (int)$pp['id'], $pp['status']);
                $o['pp_progress'] = $progress['percentage'];
            } catch (\Throwable $e) {
                $o['pp_progress'] = 0;
            }
        }
    } catch (\Throwable $e) {}

    // Check real-time stock for this SO
    $o['stock_ok'] = false;
    try {
        $soLines = $pdo->prepare("SELECT part_no, qty FROM sales_orders WHERE so_no = ? AND status NOT IN ('cancelled')");
        $soLines->execute([$o['so_no']]);
        $lines = $soLines->fetchAll(PDO::FETCH_ASSOC);
        $allOk = true;
        foreach ($lines as $line) {
            $stkQ = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stkQ->execute([$line['part_no']]);
            if ((int)$stkQ->fetchColumn() < (int)$line['qty']) { $allOk = false; break; }
        }
        $o['stock_ok'] = $allOk;
    } catch (\Throwable $e) {}

    // Build lifecycle steps
    $status = strtolower($o['status'] ?? '');
    $ppExists = !empty($o['pp_no']);
    $ppPct = $o['pp_progress'] ?? 0;
    $steps = [
        'ordered'    => true,
        'planning'   => $ppExists,
        'production' => $ppExists && ($ppPct >= 100 || $o['pp_status'] === 'completed'),
        'stock_ready'=> $o['stock_ok'] || $status === 'released',
        'released'   => $status === 'released',
        'invoiced'   => ($o['invoice_count'] ?? 0) > 0,
    ];
    $o['production_active'] = $ppExists && !$steps['production'];
    $o['steps'] = $steps;

    $totalSteps = count($steps);
    $doneSteps = count(array_filter($steps));
    if ($o['production_active']) { $doneSteps += $ppPct / 100; }
    $o['so_progress'] = round(($doneSteps / $totalSteps) * 100);
}
unset($o);

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'includes/pwa_head.php'; ?>
    <title>My Orders - Customer Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .portal-navbar { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .portal-navbar .brand { display: flex; align-items: center; gap: 15px; color: white; }
        .portal-navbar .brand img { height: 40px; }
        .portal-navbar .user-info { display: flex; align-items: center; gap: 20px; color: white; }
        .portal-navbar .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; }
        .portal-content { max-width: 1200px; margin: 0 auto; padding: 30px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #2c3e50; margin-top: 10px; }
        .back-link { color: #11998e; text-decoration: none; font-weight: 500; }
        .summary-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 40px; flex-wrap: wrap; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .summary-item .label { color: #7f8c8d; font-size: 0.9em; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-scroll { max-height: 600px; overflow-y: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .data-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed, .status-delivered { background: #d4edda; color: #155724; }
        .btn { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.9em; background: #11998e; color: white; }
        .empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }
        .invoice-badge { background: #27ae60; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.85em; }
        .status-open, .status-active { background: #cce5ff; color: #004085; }
        .status-released { background: #d4edda; color: #155724; }

        /* Step tracker */
        .order-progress { display: flex; align-items: center; gap: 0; justify-content: center; }
        .progress-step {
            width: 30px; height: 30px; border-radius: 50%; background: #e9ecef;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7em; color: white; flex-shrink: 0; position: relative; cursor: default;
        }
        .progress-step.done { background: #27ae60; }
        .progress-step.active { background: #3498db; animation: pulse 1.5s infinite; }
        .progress-step .step-label {
            position: absolute; top: 34px; white-space: nowrap;
            font-size: 0.8em; color: #999; font-weight: normal;
        }
        .progress-step.done .step-label { color: #27ae60; font-weight: 500; }
        .progress-step.active .step-label { color: #3498db; font-weight: 500; }
        .progress-line { width: 18px; height: 3px; background: #e9ecef; flex-shrink: 0; }
        .progress-line.done { background: #27ae60; }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(52,152,219,0.4); }
            50% { box-shadow: 0 0 0 6px rgba(52,152,219,0); }
        }
        .so-progress-cell { min-width: 280px; padding-bottom: 28px !important; }
        .so-progress-pct { font-size: 0.8em; font-weight: bold; text-align: center; margin-bottom: 6px; }
    </style>
</head>
<body>
<nav class="portal-navbar">
    <div class="brand">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?><img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo"><?php endif; ?>
        <h2>Customer Portal</h2>
    </div>
    <div class="user-info">
        <span><?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<div class="portal-content">
    <div class="page-header">
        <a href="my_portal.php" class="back-link">&larr; Back to Portal</a>
        <h1>My Orders</h1>
    </div>
    <?php
    $total = count($orders);
    $total_value = array_sum(array_column($orders, 'total_value'));
    $inProduction = count(array_filter($orders, fn($o) => !empty($o['pp_no']) && ($o['pp_progress'] ?? 0) < 100));
    $releasedCount = count(array_filter($orders, fn($o) => strtolower($o['status'] ?? '') === 'released'));
    $invoicedCount = count(array_filter($orders, fn($o) => ($o['invoice_count'] ?? 0) > 0));
    ?>
    <div class="summary-bar">
        <div class="summary-item"><div class="value"><?= $total ?></div><div class="label">Total Orders</div></div>
        <div class="summary-item"><div class="value" style="color: #f39c12;"><?= $inProduction ?></div><div class="label">In Production</div></div>
        <div class="summary-item"><div class="value" style="color: #27ae60;"><?= $releasedCount ?></div><div class="label">Released</div></div>
        <div class="summary-item"><div class="value" style="color: #8e44ad;"><?= $invoicedCount ?></div><div class="label">Invoiced</div></div>
        <div class="summary-item"><div class="value" style="color: #3498db;"><?= number_format($total_value, 2) ?></div><div class="label">Total Value (INR)</div></div>
    </div>
    <?php if (!empty($orders_error)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <strong>Debug:</strong> <?= htmlspecialchars($orders_error) ?>
            <br><small>customer_id=<?= htmlspecialchars($customer_id) ?> | customer_code=<?= htmlspecialchars($customer_code) ?></small>
        </div>
    <?php endif; ?>
    <?php if (empty($orders)): ?>
        <div class="table-container"><div class="empty-state"><div class="icon">📦</div><h3>No Orders Found</h3></div></div>
    <?php else: ?>
        <div class="table-container"><div class="table-scroll">
            <table class="data-table">
                <thead><tr>
                    <th>#</th>
                    <th>SO No</th>
                    <th>Your PO</th>
                    <th>Date</th>
                    <th class="text-right">Value</th>
                    <th class="text-center">Invoice</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Order Progress</th>
                </tr></thead>
                <tbody>
                <?php foreach ($orders as $i => $o):
                    $soPct = $o['so_progress'];
                    $soPctColor = $soPct >= 100 ? '#27ae60' : ($soPct >= 50 ? '#f39c12' : '#3498db');
                    $st = $o['steps'];
                    $ppPctVal = $o['pp_progress'] ?? 0;
                    $prodActive = $o['production_active'] ?? false;
                    $stepDefs = [
                        ['key' => 'ordered',     'label' => 'Order',   'icon' => '1'],
                        ['key' => 'planning',    'label' => 'Plan',    'icon' => '2'],
                        ['key' => 'production',  'label' => 'Prod',    'icon' => '3'],
                        ['key' => 'stock_ready', 'label' => 'Stock',   'icon' => '4'],
                        ['key' => 'released',    'label' => 'Release', 'icon' => '5'],
                        ['key' => 'invoiced',    'label' => 'Invoice', 'icon' => '6'],
                    ];
                    $activeIdx = -1;
                    foreach ($stepDefs as $si => $sd) {
                        if (!$st[$sd['key']]) { $activeIdx = $si; break; }
                    }
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($o['so_no']) ?></strong></td>
                    <td><?= htmlspecialchars($o['customer_po_no'] ?: '-') ?></td>
                    <td><?= $o['created_at'] ? date('d M Y', strtotime($o['created_at'])) : '-' ?></td>
                    <td class="text-right" style="font-weight: bold;"><?= $o['total_value'] ? number_format($o['total_value'], 2) : '-' ?></td>
                    <td class="text-center"><?= $o['invoice_count'] > 0 ? '<span class="invoice-badge">' . $o['invoice_count'] . '</span>' : '-' ?></td>
                    <td class="text-center">
                        <span class="status-badge status-<?= strtolower($o['status'] ?: 'pending') ?>"><?= htmlspecialchars($o['status'] ?: 'Pending') ?></span>
                        <?php if ($o['pp_no']): ?>
                            <br><small style="color: #666; font-size: 0.75em;"><?= htmlspecialchars($o['pp_no']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center so-progress-cell">
                        <div class="so-progress-pct" style="color: <?= $soPctColor ?>;">
                            <?= $soPct ?>%
                            <?php if ($prodActive): ?>
                                <span style="color: #999; font-weight: normal;">(Prod <?= $ppPctVal ?>%)</span>
                            <?php endif; ?>
                        </div>
                        <div class="order-progress">
                            <?php foreach ($stepDefs as $si => $sd):
                                $isDone = $st[$sd['key']];
                                $isActive = ($si === $activeIdx);
                                if ($sd['key'] === 'production' && $prodActive) { $isActive = true; }
                                $cls = $isDone ? 'done' : ($isActive ? 'active' : '');
                            ?>
                                <?php if ($si > 0): ?>
                                    <div class="progress-line <?= $isDone ? 'done' : ($isActive ? 'done' : '') ?>"></div>
                                <?php endif; ?>
                                <div class="progress-step <?= $cls ?>" title="<?= $sd['label'] ?><?= $sd['key'] === 'production' && $prodActive ? ' (' . $ppPctVal . '%)' : '' ?>">
                                    <?= $isDone ? '&#10003;' : $sd['icon'] ?>
                                    <span class="step-label"><?= $sd['label'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    <?php endif; ?>
</div>
<?php include 'includes/whatsapp_button.php'; ?>
<?php include 'includes/pwa_sw.php'; ?>
</body>
</html>
