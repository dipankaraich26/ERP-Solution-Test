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

// Get quotations for this customer
$quotations = [];
try {
    $quoteStmt = $pdo->prepare("
        SELECT
            q.id,
            q.quote_no,
            q.quote_date,
            q.validity_date,
            q.status,
            q.pi_no,
            (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = q.id) as total_value
        FROM quote_master q
        WHERE q.customer_id = ?
        ORDER BY q.quote_date DESC, q.id DESC
    ");
    $quoteStmt->execute([$customer_id]);
    $quotations = $quoteStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Quotations - <?= htmlspecialchars($customer['company_name']) ?></title>
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
        .status-draft { background: #fff3cd; color: #856404; }
        .status-sent { background: #cce5ff; color: #004085; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #383d41; }

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

        .validity-warning {
            color: #e74c3c;
            font-size: 0.85em;
        }
        .validity-ok {
            color: #27ae60;
            font-size: 0.85em;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        Quotations
    </div>

    <div class="page-header">
        <h1>Quotations</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <?php
    $total_quotes = count($quotations);
    $total_value = array_sum(array_column($quotations, 'total_value'));
    $accepted_count = count(array_filter($quotations, fn($q) => strtolower($q['status']) === 'accepted'));
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="value"><?= $total_quotes ?></div>
            <div class="label">Total Quotations</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #27ae60;"><?= $accepted_count ?></div>
            <div class="label">Accepted</div>
        </div>
        <div class="summary-item">
            <div class="value" style="color: #f39c12;"><?= number_format($total_value, 2) ?></div>
            <div class="label">Total Value (INR)</div>
        </div>
    </div>

    <?php if (empty($quotations)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 3em; margin-bottom: 15px;">📋</div>
            <h3 style="color: #2c3e50;">No Quotations Found</h3>
            <p style="color: #7f8c8d;">No quotations have been created for this customer yet.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Quote No</th>
                        <th>Date</th>
                        <th>Validity</th>
                        <th>PI No</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $index => $q):
                        $isExpired = $q['validity_date'] && strtotime($q['validity_date']) < time();
                    ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= htmlspecialchars($q['quote_no']) ?></strong></td>
                        <td><?= $q['quote_date'] ? date('d M Y', strtotime($q['quote_date'])) : '-' ?></td>
                        <td>
                            <?php if ($q['validity_date']): ?>
                                <?= date('d M Y', strtotime($q['validity_date'])) ?>
                                <?php if ($isExpired): ?>
                                    <br><span class="validity-warning">Expired</span>
                                <?php else: ?>
                                    <br><span class="validity-ok">Valid</span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($q['pi_no'] ?: '-') ?></td>
                        <td class="text-right" style="font-weight: bold;">
                            <?= $q['total_value'] ? number_format($q['total_value'], 2) : '-' ?>
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-<?= strtolower($q['status'] ?: 'draft') ?>">
                                <?= htmlspecialchars($q['status'] ?: 'Draft') ?>
                            </span>
                        </td>
                        <td>
                            <a href="/quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-sm" target="_blank">View</a>
                            <a href="/quotes/print.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">Print</a>
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
