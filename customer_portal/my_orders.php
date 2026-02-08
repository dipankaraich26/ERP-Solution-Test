<?php
session_start();
include "../db.php";

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) { header("Location: logout.php"); exit; }

$orders = [];
try {
    $orderStmt = $pdo->prepare("
        SELECT so.id, so.so_no, so.created_at, so.status, cp.po_no as customer_po_no,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value,
               (SELECT COUNT(*) FROM invoice_master WHERE so_no = so.so_no) as invoice_count
        FROM sales_orders so
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        WHERE so.customer_id = ? GROUP BY so.so_no ORDER BY so.created_at DESC
    ");
    $orderStmt->execute([$customer_id]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get procurement plan progress for each SO
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
            if ($pp['status'] === 'completed') {
                $o['pp_progress'] = 100;
            } else {
                $totalStmt = $pdo->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = ?) +
                        (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = ?) as total
                ");
                $totalStmt->execute([$pp['id'], $pp['id']]);
                $totalParts = (int)$totalStmt->fetchColumn();
                if ($totalParts > 0) {
                    $doneStmt = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = ? AND (status IN ('completed', 'closed') OR (created_wo_id IS NULL AND shortage <= 0))) +
                            (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = ? AND (status IN ('received', 'closed') OR (created_po_id IS NULL AND shortage <= 0))) as done
                    ");
                    $doneStmt->execute([$pp['id'], $pp['id']]);
                    $doneParts = (int)$doneStmt->fetchColumn();
                    $o['pp_progress'] = round(($doneParts / $totalParts) * 100);
                } else {
                    $o['pp_progress'] = 0;
                }
            }
        }
    } catch (Exception $e) {}
}
unset($o);

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <?php $total = count($orders); $total_value = array_sum(array_column($orders, 'total_value')); ?>
    <div class="summary-bar">
        <div class="summary-item"><div class="value"><?= $total ?></div><div class="label">Total Orders</div></div>
        <div class="summary-item"><div class="value" style="color: #3498db;"><?= number_format($total_value, 2) ?></div><div class="label">Total Value (INR)</div></div>
    </div>
    <?php if (empty($orders)): ?>
        <div class="table-container"><div class="empty-state"><div class="icon">📦</div><h3>No Orders Found</h3></div></div>
    <?php else: ?>
        <div class="table-container"><div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>SO No</th><th>Your PO</th><th>Date</th><th class="text-right">Value</th><th class="text-center">Invoice</th><th>Status</th><th>Production</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $i => $o): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($o['so_no']) ?></strong></td>
                    <td><?= htmlspecialchars($o['customer_po_no'] ?: '-') ?></td>
                    <td><?= $o['created_at'] ? date('d M Y', strtotime($o['created_at'])) : '-' ?></td>
                    <td class="text-right" style="font-weight: bold;"><?= $o['total_value'] ? number_format($o['total_value'], 2) : '-' ?></td>
                    <td class="text-center"><?= $o['invoice_count'] > 0 ? '<span class="invoice-badge">' . $o['invoice_count'] . '</span>' : '-' ?></td>
                    <td><span class="status-badge status-<?= strtolower($o['status'] ?: 'pending') ?>"><?= htmlspecialchars($o['status'] ?: 'Pending') ?></span></td>
                    <td>
                        <?php if ($o['pp_progress'] !== null): ?>
                            <div style="display: flex; align-items: center; gap: 8px; min-width: 120px;">
                                <div style="background: #e9ecef; border-radius: 4px; width: 70px; height: 20px; overflow: hidden; flex-shrink: 0;">
                                    <div style="background: <?= $o['pp_progress'] >= 100 ? '#27ae60' : ($o['pp_progress'] > 0 ? '#3498db' : '#e9ecef') ?>; height: 100%; width: <?= $o['pp_progress'] ?>%; border-radius: 4px;"></div>
                                </div>
                                <span style="font-size: 0.85em; font-weight: 600; color: <?= $o['pp_progress'] >= 100 ? '#27ae60' : '#2c3e50' ?>;"><?= $o['pp_progress'] ?>%</span>
                            </div>
                        <?php else: ?>
                            <span style="color: #999; font-size: 0.85em;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="/sales_orders/view.php?so_no=<?= urlencode($o['so_no']) ?>" class="btn" target="_blank">View</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    <?php endif; ?>
</div>
</body>
</html>
