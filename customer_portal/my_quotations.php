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

$quotations = [];
try {
    $quoteStmt = $pdo->prepare("
        SELECT q.id, q.quote_no, q.quote_date, q.validity_date, q.status, q.pi_no,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = q.id) as total_value
        FROM quote_master q WHERE q.customer_id = ? ORDER BY q.quote_date DESC
    ");
    $quoteStmt->execute([$customer_id]);
    $quotations = $quoteStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quotations - Customer Portal</title>
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
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-sent { background: #cce5ff; color: #004085; }
        .status-accepted { background: #d4edda; color: #155724; }
        .btn { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.9em; background: #11998e; color: white; white-space: nowrap; }
        .btn-download { background: #e67e22; }
        .btn-download:hover { background: #d35400; }
        .empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }
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
        <h1>My Quotations</h1>
    </div>
    <?php $total = count($quotations); $total_value = array_sum(array_column($quotations, 'total_value')); ?>
    <div class="summary-bar">
        <div class="summary-item"><div class="value"><?= $total ?></div><div class="label">Total Quotations</div></div>
        <div class="summary-item"><div class="value" style="color: #f39c12;"><?= number_format($total_value, 2) ?></div><div class="label">Total Value (INR)</div></div>
    </div>
    <?php if (empty($quotations)): ?>
        <div class="table-container"><div class="empty-state"><div class="icon">📋</div><h3>No Quotations Found</h3></div></div>
    <?php else: ?>
        <div class="table-container"><div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Quote No</th><th>Date</th><th>Validity</th><th>PI No</th><th class="text-right">Amount</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($quotations as $i => $q): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($q['quote_no']) ?></strong></td>
                    <td><?= $q['quote_date'] ? date('d M Y', strtotime($q['quote_date'])) : '-' ?></td>
                    <td><?= $q['validity_date'] ? date('d M Y', strtotime($q['validity_date'])) : '-' ?></td>
                    <td><?= htmlspecialchars($q['pi_no'] ?: '-') ?></td>
                    <td class="text-right" style="font-weight: bold;"><?= $q['total_value'] ? number_format($q['total_value'], 2) : '-' ?></td>
                    <td><span class="status-badge status-<?= strtolower($q['status'] ?: 'draft') ?>"><?= htmlspecialchars($q['status'] ?: 'Draft') ?></span></td>
                    <td style="white-space: nowrap;">
                        <a href="/quotes/print.php?id=<?= $q['id'] ?>" class="btn" target="_blank">View</a>
                        <a href="/quotes/print.php?id=<?= $q['id'] ?>&download=1" class="btn btn-download" target="_blank">Download</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    <?php endif; ?>
</div>
<?php include 'includes/whatsapp_button.php'; ?>
</body>
</html>
