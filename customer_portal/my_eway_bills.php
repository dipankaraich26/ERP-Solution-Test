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

// E-Way bills are stored in invoice_master as eway_bill_no + eway_bill_attachment
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

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'includes/pwa_head.php'; ?>
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

        .eway-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .eway-card-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .eway-card-header h4 { margin: 0; color: #2c3e50; }
        .eway-card-header .invoice-ref { color: #7f8c8d; font-size: 0.9em; }

        .pdf-section {
            padding: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95em;
            transition: all 0.3s;
        }
        .pdf-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .pdf-btn .pdf-icon { font-size: 1.5em; }
        .pdf-btn-eway {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        .pdf-btn-invoice {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        .pdf-btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; color: #7f8c8d; }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }
        .summary-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 40px; flex-wrap: wrap; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .summary-item .label { color: #7f8c8d; font-size: 0.9em; }
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

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= count($eway_bills) ?></div>
            <div class="label">Total E-Way Bills</div>
        </div>
    </div>

    <?php if (empty($eway_bills)): ?>
        <div class="empty-state"><div class="icon">📃</div><h3>No E-Way Bills Found</h3><p>Your e-way bills will appear here once generated.</p></div>
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
                        <span class="pdf-icon">📃</span>
                        <span>View E-Way Bill PDF</span>
                    </a>
                <?php else: ?>
                    <span class="pdf-btn pdf-btn-disabled">
                        <span class="pdf-icon">📃</span>
                        <span>E-Way Bill PDF Not Available</span>
                    </span>
                <?php endif; ?>

                <a href="/invoices/print.php?id=<?= $e['id'] ?>" class="pdf-btn pdf-btn-invoice" target="_blank">
                    <span class="pdf-icon">🧾</span>
                    <span>View Invoice PDF</span>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'includes/whatsapp_button.php'; ?>
<?php include 'includes/pwa_sw.php'; ?>
</body>
</html>
