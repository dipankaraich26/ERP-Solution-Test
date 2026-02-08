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

$dockets = [];
try {
    $docketStmt = $pdo->prepare("
        SELECT d.id, d.docket_no, d.dispatch_date, d.transporter as courier_name, d.lr_no as tracking_no,
               d.status as delivery_status, d.expected_date as expected_delivery, d.delivered_date as actual_delivery,
               d.invoice_no, d.so_no
        FROM dispatches d
        LEFT JOIN sales_orders so ON so.so_no = d.so_no
        WHERE so.customer_id = ? ORDER BY d.dispatch_date DESC
    ");
    $docketStmt->execute([$customer_id]);
    $dockets = $docketStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'includes/pwa_head.php'; ?>
    <title>My Delivery Dockets - Customer Portal</title>
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
        .docket-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 20px; margin-bottom: 15px; border-left: 4px solid #11998e; }
        .docket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .docket-header h4 { margin: 0; color: #2c3e50; }
        .docket-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .docket-info-item { font-size: 0.9em; }
        .docket-info-item label { display: block; color: #7f8c8d; font-size: 0.85em; margin-bottom: 3px; }
        .docket-info-item span { font-weight: 600; color: #2c3e50; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-dispatched, .status-in-transit { background: #cce5ff; color: #004085; }
        .status-pending { background: #fff3cd; color: #856404; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; color: #7f8c8d; }
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
        <h1>My Delivery Dockets</h1>
    </div>
    <?php if (empty($dockets)): ?>
        <div class="empty-state"><div class="icon">🚚</div><h3>No Dockets Found</h3><p>Your delivery dockets will appear here.</p></div>
    <?php else: ?>
        <?php foreach ($dockets as $d): ?>
        <div class="docket-card" style="border-left-color: <?= strtolower($d['delivery_status'] ?? '') === 'delivered' ? '#27ae60' : '#11998e' ?>;">
            <div class="docket-header">
                <h4><?= htmlspecialchars($d['docket_no'] ?: 'Docket #' . $d['id']) ?></h4>
                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $d['delivery_status'] ?? 'pending')) ?>"><?= htmlspecialchars($d['delivery_status'] ?: 'Pending') ?></span>
            </div>
            <div class="docket-info">
                <div class="docket-info-item"><label>Dispatch Date</label><span><?= $d['dispatch_date'] ? date('d M Y', strtotime($d['dispatch_date'])) : '-' ?></span></div>
                <div class="docket-info-item"><label>Transporter</label><span><?= htmlspecialchars($d['courier_name'] ?: '-') ?></span></div>
                <div class="docket-info-item"><label>Tracking/LR No</label><span><?= htmlspecialchars($d['tracking_no'] ?: '-') ?></span></div>
                <div class="docket-info-item"><label>Invoice</label><span><?= htmlspecialchars($d['invoice_no'] ?: '-') ?></span></div>
                <div class="docket-info-item"><label>SO No</label><span><?= htmlspecialchars($d['so_no'] ?: '-') ?></span></div>
                <div class="docket-info-item"><label>Expected Delivery</label><span><?= $d['expected_delivery'] ? date('d M Y', strtotime($d['expected_delivery'])) : '-' ?></span></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'includes/whatsapp_button.php'; ?>
<?php include 'includes/pwa_sw.php'; ?>
</body>
</html>
