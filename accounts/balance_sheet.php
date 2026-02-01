<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get date filter
$as_on_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get Assets
try {
    $assets_stmt = $pdo->prepare("
        SELECT
            l.id, l.name as ledger_name, l.code,
            ag.name as group_name,
            ABS(l.current_balance) as balance,
            ag.parent_id
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Assets'
        AND l.is_active = 1
        AND l.current_balance != 0
        ORDER BY ag.name, l.name
    ");
    $assets_stmt->execute();
    $assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assets = [];
}

// Get Liabilities
try {
    $liab_stmt = $pdo->prepare("
        SELECT
            l.id, l.name as ledger_name, l.code,
            ag.name as group_name,
            ABS(l.current_balance) as balance,
            ag.parent_id
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Liabilities'
        AND l.is_active = 1
        AND l.current_balance != 0
        ORDER BY ag.name, l.name
    ");
    $liab_stmt->execute();
    $liabilities = $liab_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $liabilities = [];
}

// Calculate P&L (for retained earnings)
try {
    $income_stmt = $pdo->query("
        SELECT SUM(ABS(current_balance)) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Income' AND l.is_active = 1
    ");
    $total_income = $income_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $expense_stmt = $pdo->query("
        SELECT SUM(ABS(current_balance)) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Expenses' AND l.is_active = 1
    ");
    $total_expense = $expense_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $net_profit = $total_income - $total_expense;
} catch (Exception $e) {
    $net_profit = 0;
}

// Calculate totals
$total_assets = array_sum(array_column($assets, 'balance'));
$total_liabilities = array_sum(array_column($liabilities, 'balance'));

// Add net profit to liabilities (as retained earnings)
$total_liabilities_with_profit = $total_liabilities + $net_profit;

// Group accounts
$assets_grouped = [];
foreach ($assets as $acc) {
    $assets_grouped[$acc['group_name']][] = $acc;
}

$liab_grouped = [];
foreach ($liabilities as $acc) {
    $liab_grouped[$acc['group_name']][] = $acc;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Balance Sheet - Accounts</title>
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
        .filter-bar input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .report-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 20px 25px;
            text-align: center;
        }
        .report-header h2 { margin: 0; font-size: 1.5em; }
        .report-header .date { margin-top: 5px; opacity: 0.9; }

        .bs-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .bs-section {
            padding: 20px;
            border-right: 1px solid #eee;
        }
        .bs-section:last-child { border-right: none; }
        .bs-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        .bs-section.liabilities h3 { color: #e74c3c; border-color: #e74c3c; }
        .bs-section.assets h3 { color: #3498db; border-color: #3498db; }

        .bs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bs-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .bs-table .amount { text-align: right; font-family: monospace; }
        .bs-table .group-row {
            font-weight: 600;
            background: #f8f9fa;
            color: #495057;
        }
        .bs-table .item-row td:first-child { padding-left: 25px; }
        .bs-table .total-row {
            font-weight: 700;
            background: #f8f9fa;
        }
        .bs-table .grand-total {
            font-weight: 700;
            background: #667eea;
            color: white;
            font-size: 1.1em;
        }
        .bs-table .profit-row {
            color: #27ae60;
            font-style: italic;
        }
        .bs-table .loss-row {
            color: #e74c3c;
            font-style: italic;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .summary-card .label { color: #666; font-size: 0.9em; }
        .summary-card .value { font-size: 1.5em; font-weight: 700; margin-top: 5px; }
        .summary-card.assets .value { color: #3498db; }
        .summary-card.liabilities .value { color: #e74c3c; }

        @media print {
            .filter-bar, .page-header .btn, .no-print { display: none; }
            .content { margin: 0; padding: 0; }
            .report-card { box-shadow: none; }
        }

        @media (max-width: 768px) {
            .bs-container { grid-template-columns: 1fr; }
            .bs-section { border-right: none; border-bottom: 1px solid #eee; }
        }

        body.dark .filter-bar, body.dark .report-card, body.dark .summary-card { background: #2c3e50; }
        body.dark .bs-section h3 { color: #ecf0f1; }
        body.dark .bs-table .group-row, body.dark .bs-table .total-row { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Balance Sheet</h1>
            <p style="color: #666; margin: 5px 0 0;">Financial Position Statement</p>
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
        <a href="trial_balance.php" class="btn btn-secondary">Trial Balance</a>
        <a href="profit_loss.php" class="btn btn-secondary">P&L Statement</a>
    </form>

    <!-- Summary Cards -->
    <div class="summary-cards no-print">
        <div class="summary-card assets">
            <div class="label">Total Assets</div>
            <div class="value">₹<?= number_format($total_assets, 0) ?></div>
        </div>
        <div class="summary-card liabilities">
            <div class="label">Total Liabilities</div>
            <div class="value">₹<?= number_format($total_liabilities, 0) ?></div>
        </div>
        <div class="summary-card">
            <div class="label"><?= $net_profit >= 0 ? 'Retained Earnings' : 'Accumulated Loss' ?></div>
            <div class="value" style="color: <?= $net_profit >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                ₹<?= number_format(abs($net_profit), 0) ?>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <h2>Balance Sheet</h2>
            <div class="date">As on <?= date('d F Y', strtotime($as_on_date)) ?></div>
        </div>

        <div class="bs-container">
            <!-- Liabilities & Capital -->
            <div class="bs-section liabilities">
                <h3>Liabilities & Capital</h3>
                <table class="bs-table">
                    <!-- Capital / Equity -->
                    <tr class="group-row">
                        <td>Capital Account</td>
                        <td class="amount"></td>
                    </tr>

                    <?php foreach ($liab_grouped as $group => $accounts): ?>
                        <tr class="group-row">
                            <td><?= htmlspecialchars($group) ?></td>
                            <td class="amount"></td>
                        </tr>
                        <?php foreach ($accounts as $acc): ?>
                            <tr class="item-row">
                                <td><?= htmlspecialchars($acc['ledger_name']) ?></td>
                                <td class="amount"><?= number_format($acc['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <!-- Add Profit/Loss to Equity -->
                    <?php if ($net_profit != 0): ?>
                        <tr class="item-row <?= $net_profit >= 0 ? 'profit-row' : 'loss-row' ?>">
                            <td><?= $net_profit >= 0 ? 'Current Year Profit' : 'Current Year Loss' ?></td>
                            <td class="amount"><?= $net_profit >= 0 ? '' : '(' ?><?= number_format(abs($net_profit), 2) ?><?= $net_profit >= 0 ? '' : ')' ?></td>
                        </tr>
                    <?php endif; ?>

                    <tr class="total-row">
                        <td>Total Liabilities</td>
                        <td class="amount"><?= number_format($total_liabilities, 2) ?></td>
                    </tr>

                    <tr class="grand-total">
                        <td>Total Liabilities & Capital</td>
                        <td class="amount"><?= number_format($total_liabilities_with_profit, 2) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Assets -->
            <div class="bs-section assets">
                <h3>Assets</h3>
                <table class="bs-table">
                    <?php foreach ($assets_grouped as $group => $accounts): ?>
                        <tr class="group-row">
                            <td><?= htmlspecialchars($group) ?></td>
                            <td class="amount"></td>
                        </tr>
                        <?php foreach ($accounts as $acc): ?>
                            <tr class="item-row">
                                <td><?= htmlspecialchars($acc['ledger_name']) ?></td>
                                <td class="amount"><?= number_format($acc['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center; color: #666; padding: 30px;">
                                No assets recorded
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="total-row">
                        <td>Total Assets</td>
                        <td class="amount"><?= number_format($total_assets, 2) ?></td>
                    </tr>

                    <tr class="grand-total">
                        <td>Total Assets</td>
                        <td class="amount"><?= number_format($total_assets, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <?php
    $difference = abs($total_assets - $total_liabilities_with_profit);
    if ($difference > 0.01):
    ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 0 8px 8px 0; color: #856404;">
            <strong>Note:</strong> Balance Sheet shows a difference of ₹<?= number_format($difference, 2) ?>.
            Please verify all entries and ensure opening balances are correctly entered.
        </div>
    <?php endif; ?>
</div>

</body>
</html>
