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

// Date filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-04-01'); // Default FY start
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Get ledger entries for this customer
// This joins vouchers/transactions with the customer
$ledger_entries = [];
$opening_balance = 0;

try {
    // Check if customer has a linked ledger
    $ledgerCheck = $pdo->prepare("SELECT id FROM acc_ledgers WHERE customer_id = ?");
    $ledgerCheck->execute([$customer_id]);
    $ledger_id = $ledgerCheck->fetchColumn();

    if ($ledger_id) {
        // Get opening balance (sum of all transactions before from_date)
        $openingStmt = $pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN vt.dr_cr = 'Dr' THEN vt.amount ELSE -vt.amount END), 0)
            FROM acc_voucher_transactions vt
            JOIN acc_vouchers v ON v.id = vt.voucher_id
            WHERE vt.ledger_id = ? AND v.voucher_date < ?
        ");
        $openingStmt->execute([$ledger_id, $from_date]);
        $opening_balance = $openingStmt->fetchColumn() ?: 0;

        // Get transactions within date range
        $txnStmt = $pdo->prepare("
            SELECT
                v.voucher_no,
                v.voucher_date,
                v.voucher_type,
                v.narration,
                vt.dr_cr,
                vt.amount,
                v.reference_no
            FROM acc_voucher_transactions vt
            JOIN acc_vouchers v ON v.id = vt.voucher_id
            WHERE vt.ledger_id = ? AND v.voucher_date BETWEEN ? AND ?
            ORDER BY v.voucher_date, v.id
        ");
        $txnStmt->execute([$ledger_id, $from_date, $to_date]);
        $ledger_entries = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Tables might not exist
}

// Also include invoices as receivables
$invoice_entries = [];
try {
    $invStmt = $pdo->prepare("
        SELECT
            i.invoice_no as voucher_no,
            i.invoice_date as voucher_date,
            'Invoice' as voucher_type,
            CONCAT('Tax Invoice - ', i.so_no) as narration,
            'Dr' as dr_cr,
            (SELECT SUM(total_amount) FROM quote_items qi
             JOIN quote_master qm ON qm.id = qi.quote_id
             JOIN sales_orders so ON so.linked_quote_id = qm.id
             WHERE so.so_no = i.so_no) as amount,
            i.so_no as reference_no
        FROM invoice_master i
        JOIN sales_orders so ON so.so_no = i.so_no
        WHERE so.customer_id = ? AND i.invoice_date BETWEEN ? AND ?
        ORDER BY i.invoice_date
    ");
    $invStmt->execute([$customer_id, $from_date, $to_date]);
    $invoice_entries = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

// Merge and sort entries
$all_entries = array_merge($ledger_entries, $invoice_entries);
usort($all_entries, function($a, $b) {
    return strtotime($a['voucher_date']) - strtotime($b['voucher_date']);
});

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Account Ledger - <?= htmlspecialchars($customer['company_name']) ?></title>
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

        .filter-section {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-section label {
            font-weight: 600;
            color: #2c3e50;
        }
        .filter-section input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .table-scroll-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        .ledger-table { width: 100%; border-collapse: collapse; }
        .ledger-table th, .ledger-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .ledger-table th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .ledger-table tr:hover { background: #f8f9fa; }
        .ledger-table .text-right { text-align: right; }
        .ledger-table .text-center { text-align: center; }
        .ledger-table .debit { color: #27ae60; }
        .ledger-table .credit { color: #e74c3c; }

        .balance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .balance-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        .balance-card .label { color: #7f8c8d; font-size: 0.9em; }
        .balance-card .value { font-size: 1.8em; font-weight: bold; margin-top: 5px; }

        .opening-row { background: #e8f5e9 !important; }
        .closing-row { background: #fff3e0 !important; font-weight: bold; }
    </style>
</head>
<body>

<div class="content">
    <div class="breadcrumb">
        <a href="index.php">Customer Portal</a> &rarr;
        <a href="index.php?customer_id=<?= $customer_id ?>"><?= htmlspecialchars($customer['company_name']) ?></a> &rarr;
        Account Ledger
    </div>

    <div class="page-header">
        <h1>Account Ledger</h1>
        <span class="customer-badge"><?= htmlspecialchars($customer['company_name']) ?></span>
    </div>

    <form method="get" class="filter-section">
        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
        <label>From:</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
        <label>To:</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
        <button type="submit" class="btn btn-primary">Apply Filter</button>
        <a href="ledger.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary">Reset</a>
    </form>

    <?php
    // Calculate totals
    $total_debit = 0;
    $total_credit = 0;
    $running_balance = $opening_balance;

    foreach ($all_entries as $entry) {
        if ($entry['dr_cr'] === 'Dr') {
            $total_debit += $entry['amount'];
        } else {
            $total_credit += $entry['amount'];
        }
    }
    $closing_balance = $opening_balance + $total_debit - $total_credit;
    ?>

    <div class="balance-summary">
        <div class="balance-card">
            <div class="label">Opening Balance</div>
            <div class="value" style="color: <?= $opening_balance >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                <?= number_format(abs($opening_balance), 2) ?>
                <?= $opening_balance >= 0 ? 'Dr' : 'Cr' ?>
            </div>
        </div>
        <div class="balance-card">
            <div class="label">Total Debit</div>
            <div class="value" style="color: #27ae60;"><?= number_format($total_debit, 2) ?></div>
        </div>
        <div class="balance-card">
            <div class="label">Total Credit</div>
            <div class="value" style="color: #e74c3c;"><?= number_format($total_credit, 2) ?></div>
        </div>
        <div class="balance-card">
            <div class="label">Closing Balance</div>
            <div class="value" style="color: <?= $closing_balance >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                <?= number_format(abs($closing_balance), 2) ?>
                <?= $closing_balance >= 0 ? 'Dr' : 'Cr' ?>
            </div>
        </div>
    </div>

    <div class="table-scroll-container">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher No</th>
                    <th>Type</th>
                    <th>Narration</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening-row">
                    <td><?= date('d M Y', strtotime($from_date)) ?></td>
                    <td>-</td>
                    <td>Opening</td>
                    <td>Opening Balance</td>
                    <td class="text-right"><?= $opening_balance > 0 ? number_format($opening_balance, 2) : '-' ?></td>
                    <td class="text-right"><?= $opening_balance < 0 ? number_format(abs($opening_balance), 2) : '-' ?></td>
                    <td class="text-right">
                        <?= number_format(abs($opening_balance), 2) ?>
                        <?= $opening_balance >= 0 ? 'Dr' : 'Cr' ?>
                    </td>
                </tr>

                <?php
                $running_balance = $opening_balance;
                foreach ($all_entries as $entry):
                    if ($entry['dr_cr'] === 'Dr') {
                        $running_balance += $entry['amount'];
                    } else {
                        $running_balance -= $entry['amount'];
                    }
                ?>
                <tr>
                    <td><?= date('d M Y', strtotime($entry['voucher_date'])) ?></td>
                    <td><?= htmlspecialchars($entry['voucher_no']) ?></td>
                    <td><?= htmlspecialchars($entry['voucher_type']) ?></td>
                    <td><?= htmlspecialchars($entry['narration'] ?: '-') ?></td>
                    <td class="text-right debit">
                        <?= $entry['dr_cr'] === 'Dr' ? number_format($entry['amount'], 2) : '-' ?>
                    </td>
                    <td class="text-right credit">
                        <?= $entry['dr_cr'] === 'Cr' ? number_format($entry['amount'], 2) : '-' ?>
                    </td>
                    <td class="text-right">
                        <?= number_format(abs($running_balance), 2) ?>
                        <?= $running_balance >= 0 ? 'Dr' : 'Cr' ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr class="closing-row">
                    <td><?= date('d M Y', strtotime($to_date)) ?></td>
                    <td>-</td>
                    <td>Closing</td>
                    <td>Closing Balance</td>
                    <td class="text-right"><?= number_format($total_debit, 2) ?></td>
                    <td class="text-right"><?= number_format($total_credit, 2) ?></td>
                    <td class="text-right">
                        <?= number_format(abs($closing_balance), 2) ?>
                        <?= $closing_balance >= 0 ? 'Dr' : 'Cr' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px; display: flex; gap: 10px;">
        <a href="index.php?customer_id=<?= $customer_id ?>" class="btn btn-secondary">&larr; Back to Portal</a>
        <button onclick="window.print();" class="btn btn-primary">Print Statement</button>
    </div>
</div>

</body>
</html>
