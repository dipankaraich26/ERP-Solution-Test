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

$eway_bills = [];
try {
    $ewayStmt = $pdo->prepare("
        SELECT e.id, e.eway_bill_no, e.eway_bill_date, e.valid_upto, e.vehicle_no, e.transporter_name,
               e.distance_km, e.mode_of_transport, e.invoice_value, i.invoice_no
        FROM eway_bills e
        LEFT JOIN invoice_master i ON i.id = e.invoice_id
        LEFT JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ? ORDER BY e.eway_bill_date DESC
    ");
    $ewayStmt->execute([$customer_id]);
    $eway_bills = $ewayStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My E-Way Bills - Customer Portal</title>
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
        .eway-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 20px; margin-bottom: 15px; border-left: 4px solid #27ae60; }
        .eway-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .eway-header h4 { margin: 0; color: #2c3e50; font-family: monospace; font-size: 1.2em; }
        .eway-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .eway-info-item { font-size: 0.9em; }
        .eway-info-item label { display: block; color: #7f8c8d; font-size: 0.85em; margin-bottom: 3px; }
        .eway-info-item span { font-weight: 600; color: #2c3e50; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
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
        <h1>My E-Way Bills</h1>
    </div>
    <?php if (empty($eway_bills)): ?>
        <div class="empty-state"><div class="icon">📃</div><h3>No E-Way Bills Found</h3><p>Your e-way bills will appear here once generated.</p></div>
    <?php else: ?>
        <?php foreach ($eway_bills as $e): $isExpired = $e['valid_upto'] && strtotime($e['valid_upto']) < time(); ?>
        <div class="eway-card" style="border-left-color: <?= $isExpired ? '#e74c3c' : '#27ae60' ?>;">
            <div class="eway-header">
                <h4><?= htmlspecialchars($e['eway_bill_no']) ?></h4>
                <span class="status-badge status-<?= $isExpired ? 'expired' : 'active' ?>"><?= $isExpired ? 'Expired' : 'Active' ?></span>
            </div>
            <div class="eway-info">
                <div class="eway-info-item"><label>Generated</label><span><?= $e['eway_bill_date'] ? date('d M Y', strtotime($e['eway_bill_date'])) : '-' ?></span></div>
                <div class="eway-info-item"><label>Valid Until</label><span style="color: <?= $isExpired ? '#e74c3c' : '#27ae60' ?>;"><?= $e['valid_upto'] ? date('d M Y H:i', strtotime($e['valid_upto'])) : '-' ?></span></div>
                <div class="eway-info-item"><label>Invoice</label><span><?= htmlspecialchars($e['invoice_no'] ?: '-') ?></span></div>
                <div class="eway-info-item"><label>Value</label><span><?= $e['invoice_value'] ? number_format($e['invoice_value'], 2) : '-' ?></span></div>
                <div class="eway-info-item"><label>Vehicle</label><span><?= htmlspecialchars($e['vehicle_no'] ?: '-') ?></span></div>
                <div class="eway-info-item"><label>Transporter</label><span><?= htmlspecialchars($e['transporter_name'] ?: '-') ?></span></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
