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

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-04-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$ledger_entries = [];
$opening_balance = 0;

// Get invoice entries as receivables
try {
    $invStmt = $pdo->prepare("
        SELECT i.invoice_no as voucher_no, i.invoice_date as voucher_date, 'Invoice' as voucher_type,
               CONCAT('Tax Invoice - ', i.so_no) as narration, 'Dr' as dr_cr,
               (SELECT SUM(total_amount) FROM quote_items qi JOIN quote_master qm ON qm.id = qi.quote_id
                JOIN sales_orders so ON so.linked_quote_id = qm.id WHERE so.so_no = i.so_no) as amount
        FROM invoice_master i
        JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ? AND i.invoice_date BETWEEN ? AND ?
        ORDER BY i.invoice_date
    ");
    $invStmt->execute([$customer_id, $from_date, $to_date]);
    $ledger_entries = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account Ledger - Customer Portal</title>
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
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-bar input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .filter-bar button { padding: 10px 20px; background: #11998e; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .summary-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-card .label { color: #7f8c8d; font-size: 0.9em; }
        .summary-card .value { font-size: 1.6em; font-weight: bold; margin-top: 5px; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-scroll { max-height: 500px; overflow-y: auto; }
        .ledger-table { width: 100%; border-collapse: collapse; }
        .ledger-table th, .ledger-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .ledger-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .ledger-table .text-right { text-align: right; }
        .debit { color: #27ae60; }
        .credit { color: #e74c3c; }
        .opening-row { background: #e8f5e9 !important; }
        .closing-row { background: #fff3e0 !important; font-weight: bold; }
        .empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; }
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
        <h1>My Account Ledger</h1>
    </div>
    <form method="get" class="filter-bar">
        <label>From:</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
        <label>To:</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
        <button type="submit">Apply Filter</button>
    </form>
    <?php
    $total_debit = 0; $total_credit = 0;
    foreach ($ledger_entries as $e) {
        if ($e['dr_cr'] === 'Dr') $total_debit += $e['amount'];
        else $total_credit += $e['amount'];
    }
    $closing = $opening_balance + $total_debit - $total_credit;
    ?>
    <div class="summary-cards">
        <div class="summary-card"><div class="label">Opening Balance</div><div class="value"><?= number_format(abs($opening_balance), 2) ?></div></div>
        <div class="summary-card"><div class="label">Total Debit</div><div class="value" style="color: #27ae60;"><?= number_format($total_debit, 2) ?></div></div>
        <div class="summary-card"><div class="label">Total Credit</div><div class="value" style="color: #e74c3c;"><?= number_format($total_credit, 2) ?></div></div>
        <div class="summary-card"><div class="label">Closing Balance</div><div class="value"><?= number_format(abs($closing), 2) ?> <?= $closing >= 0 ? 'Dr' : 'Cr' ?></div></div>
    </div>
    <div class="table-container">
        <div class="table-scroll">
            <table class="ledger-table">
                <thead><tr><th>Date</th><th>Voucher</th><th>Type</th><th>Narration</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                    <tr class="opening-row"><td><?= date('d M Y', strtotime($from_date)) ?></td><td>-</td><td>Opening</td><td>Opening Balance</td><td class="text-right">-</td><td class="text-right">-</td><td class="text-right"><?= number_format(abs($opening_balance), 2) ?></td></tr>
                    <?php $running = $opening_balance; foreach ($ledger_entries as $e): $running += ($e['dr_cr'] === 'Dr' ? $e['amount'] : -$e['amount']); ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['voucher_date'])) ?></td>
                        <td><?= htmlspecialchars($e['voucher_no']) ?></td>
                        <td><?= htmlspecialchars($e['voucher_type']) ?></td>
                        <td><?= htmlspecialchars($e['narration'] ?: '-') ?></td>
                        <td class="text-right debit"><?= $e['dr_cr'] === 'Dr' ? number_format($e['amount'], 2) : '-' ?></td>
                        <td class="text-right credit"><?= $e['dr_cr'] === 'Cr' ? number_format($e['amount'], 2) : '-' ?></td>
                        <td class="text-right"><?= number_format(abs($running), 2) ?> <?= $running >= 0 ? 'Dr' : 'Cr' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="closing-row"><td><?= date('d M Y', strtotime($to_date)) ?></td><td>-</td><td>Closing</td><td>Closing Balance</td><td class="text-right"><?= number_format($total_debit, 2) ?></td><td class="text-right"><?= number_format($total_credit, 2) ?></td><td class="text-right"><?= number_format(abs($closing), 2) ?> <?= $closing >= 0 ? 'Dr' : 'Cr' ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div style="margin-top: 20px;"><button onclick="window.print();" style="padding: 10px 20px; background: #11998e; color: white; border: none; border-radius: 6px; cursor: pointer;">Print Statement</button></div>
</div>
<?php include 'includes/whatsapp_button.php'; ?>
</body>
</html>
