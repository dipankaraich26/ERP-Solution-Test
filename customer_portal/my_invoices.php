<?php
session_start();
include "../db.php";

// Check if customer is logged in
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: logout.php");
    exit;
}

// Get invoices for this customer
$invoices = [];
try {
    $invStmt = $pdo->prepare("
        SELECT
            i.id,
            i.invoice_no,
            i.so_no,
            i.invoice_date,
            i.released_at,
            i.status,
            cp.po_no as customer_po_no,
            q.pi_no,
            (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value
        FROM invoice_master i
        JOIN (
            SELECT DISTINCT so_no, customer_po_id, linked_quote_id, customer_id
            FROM sales_orders
        ) so ON so.so_no = i.so_no
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        LEFT JOIN quote_master q ON q.id = so.linked_quote_id
        WHERE so.customer_id = ?
        ORDER BY i.invoice_date DESC, i.id DESC
    ");
    $invStmt->execute([$customer_id]);
    $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$company_settings = null;
try {
    $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - Customer Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .portal-navbar {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .portal-navbar .brand {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }
        .portal-navbar .brand img { height: 40px; }
        .portal-navbar .brand h2 { font-size: 1.2em; }
        .portal-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
        }
        .portal-navbar .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
        }

        .portal-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { color: #2c3e50; }
        .back-link {
            color: #11998e;
            text-decoration: none;
            font-weight: 500;
        }

        .summary-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .summary-item .label { color: #7f8c8d; font-size: 0.9em; }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .table-scroll {
            max-height: 600px;
            overflow-y: auto;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .data-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table .text-right { text-align: right; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-released { background: #d4edda; color: #155724; }

        .btn { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.9em; }
        .btn-primary { background: #11998e; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }
    </style>
</head>
<body>

<nav class="portal-navbar">
    <div class="brand">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo">
        <?php endif; ?>
        <h2>Customer Portal</h2>
    </div>
    <div class="user-info">
        <span><?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="portal-content">
    <div class="page-header">
        <div>
            <a href="my_portal.php" class="back-link">&larr; Back to Portal</a>
            <h1 style="margin-top: 10px;">My Invoices</h1>
        </div>
    </div>

    <?php
    $total_invoices = count($invoices);
    $total_value = array_sum(array_column($invoices, 'total_value'));
    $released_count = count(array_filter($invoices, fn($i) => $i['status'] === 'Released'));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_invoices ?></div>
            <div class="label">Total Invoices</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $released_count ?></div>
            <div class="label">Released</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #11998e;"><?= number_format($total_value, 2) ?></div>
            <div class="label">Total Value (INR)</div>
        </div>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="table-container">
            <div class="empty-state">
                <div class="icon">🧾</div>
                <h3>No Invoices Found</h3>
                <p>Your invoices will appear here once generated.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>SO No</th>
                            <th>Your PO</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $index => $inv): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
                            <td><?= $inv['invoice_date'] ? date('d M Y', strtotime($inv['invoice_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($inv['so_no'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($inv['customer_po_no'] ?: '-') ?></td>
                            <td class="text-right" style="font-weight: bold;">
                                <?= $inv['total_value'] ? number_format($inv['total_value'], 2) : '-' ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower($inv['status']) ?>">
                                    <?= htmlspecialchars($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-primary" target="_blank">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
