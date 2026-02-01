<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get date filter
$as_on_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get all ledgers with their balances
try {
    $stmt = $pdo->prepare("
        SELECT
            l.id, l.name as ledger_name, l.code, l.opening_balance, l.current_balance,
            ag.name as group_name, ag.type as group_type, ag.parent_id
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE l.is_active = 1
        ORDER BY ag.type, ag.name, l.name
    ");
    $stmt->execute();
    $ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ledgers = [];
}

// Calculate totals
$total_debit = 0;
$total_credit = 0;

// Group ledgers by type
$grouped = [
    'Assets' => [],
    'Liabilities' => [],
    'Income' => [],
    'Expenses' => []
];

foreach ($ledgers as $ledger) {
    $balance = $ledger['current_balance'];
    $type = $ledger['group_type'];

    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }

    // Determine if debit or credit based on account type and balance
    $is_debit = false;
    if ($type === 'Assets' || $type === 'Expenses') {
        // Assets and Expenses have debit balance
        $is_debit = $balance >= 0;
    } else {
        // Liabilities and Income have credit balance
        $is_debit = $balance < 0;
    }

    $ledger['debit'] = $is_debit ? abs($balance) : 0;
    $ledger['credit'] = !$is_debit ? abs($balance) : 0;

    $total_debit += $ledger['debit'];
    $total_credit += $ledger['credit'];

    $grouped[$type][] = $ledger;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Trial Balance - Accounts</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-bar input, .filter-bar select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            text-align: center;
        }
        .report-header h2 { margin: 0; font-size: 1.5em; }
        .report-header .date { margin-top: 5px; opacity: 0.9; }

        .tb-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tb-table th, .tb-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .tb-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            text-align: left;
        }
        .tb-table th.amount { text-align: right; }
        .tb-table td.amount { text-align: right; font-family: monospace; font-size: 1em; }

        .group-header {
            background: #e8eaf6;
            font-weight: 600;
            color: #3f51b5;
        }
        .group-header td { padding: 10px 15px; }

        .tb-table tr:hover { background: #f8f9fa; }
        .tb-table tr.total {
            background: #f8f9fa;
            font-weight: 700;
        }
        .tb-table tr.grand-total {
            background: #667eea;
            color: white;
            font-weight: 700;
            font-size: 1.1em;
        }

        .ledger-code {
            color: #666;
            font-size: 0.85em;
            margin-left: 10px;
        }

        .balance-status {
            text-align: center;
            padding: 20px;
            font-size: 1.2em;
        }
        .balance-status.balanced { color: #27ae60; }
        .balance-status.unbalanced { color: #e74c3c; }

        @media print {
            .filter-bar, .page-header .btn, .no-print { display: none; }
            .content { margin: 0; padding: 0; }
            .report-card { box-shadow: none; }
        }

        body.dark .filter-bar, body.dark .report-card { background: #2c3e50; }
        body.dark .tb-table th { background: #34495e; color: #ecf0f1; }
        body.dark .group-header { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Trial Balance</h1>
            <p style="color: #666; margin: 5px 0 0;">Summary of all ledger balances</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary no-print">Print</button>
        </div>
    </div>

    <form method="get" class="filter-bar no-print">
        <label style="font-weight: 600;">As on Date:</label>
        <input type="date" name="date" value="<?= $as_on_date ?>">
        <button type="submit" class="btn btn-primary">Generate</button>
        <a href="profit_loss.php" class="btn btn-secondary">P&L Statement</a>
        <a href="balance_sheet.php" class="btn btn-secondary">Balance Sheet</a>
    </form>

    <div class="report-card">
        <div class="report-header">
            <h2>Trial Balance</h2>
            <div class="date">As on <?= date('d F Y', strtotime($as_on_date)) ?></div>
        </div>

        <table class="tb-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Particulars</th>
                    <th class="amount" style="width: 25%;">Debit (₹)</th>
                    <th class="amount" style="width: 25%;">Credit (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped as $type => $type_ledgers): ?>
                    <?php if (!empty($type_ledgers)): ?>
                        <tr class="group-header">
                            <td colspan="3"><?= $type ?></td>
                        </tr>
                        <?php
                        $type_debit = 0;
                        $type_credit = 0;
                        foreach ($type_ledgers as $ledger):
                            $type_debit += $ledger['debit'];
                            $type_credit += $ledger['credit'];
                            if ($ledger['debit'] == 0 && $ledger['credit'] == 0) continue;
                        ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($ledger['ledger_name']) ?>
                                    <?php if ($ledger['code']): ?>
                                        <span class="ledger-code">(<?= htmlspecialchars($ledger['code']) ?>)</span>
                                    <?php endif; ?>
                                    <div style="color: #666; font-size: 0.85em; margin-left: 15px;"><?= htmlspecialchars($ledger['group_name']) ?></div>
                                </td>
                                <td class="amount"><?= $ledger['debit'] > 0 ? number_format($ledger['debit'], 2) : '' ?></td>
                                <td class="amount"><?= $ledger['credit'] > 0 ? number_format($ledger['credit'], 2) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td style="text-align: right;">Total <?= $type ?>:</td>
                            <td class="amount"><?= $type_debit > 0 ? number_format($type_debit, 2) : '' ?></td>
                            <td class="amount"><?= $type_credit > 0 ? number_format($type_credit, 2) : '' ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>

                <tr class="grand-total">
                    <td style="text-align: right;">Grand Total:</td>
                    <td class="amount"><?= number_format($total_debit, 2) ?></td>
                    <td class="amount"><?= number_format($total_credit, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <?php
        $diff = abs($total_debit - $total_credit);
        $is_balanced = $diff < 0.01;
        ?>
        <div class="balance-status <?= $is_balanced ? 'balanced' : 'unbalanced' ?>">
            <?php if ($is_balanced): ?>
                Trial Balance is Balanced
            <?php else: ?>
                Difference: ₹<?= number_format($diff, 2) ?> (<?= $total_debit > $total_credit ? 'Debit' : 'Credit' ?> side is higher)
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
