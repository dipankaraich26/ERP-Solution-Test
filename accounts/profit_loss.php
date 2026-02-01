<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get date range filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-04-01'); // FY start
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Get Income accounts
try {
    $income_stmt = $pdo->prepare("
        SELECT
            l.id, l.name as ledger_name, l.code,
            ag.name as group_name,
            ABS(l.current_balance) as balance
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Income'
        AND l.is_active = 1
        AND l.current_balance != 0
        ORDER BY ag.name, l.name
    ");
    $income_stmt->execute();
    $income_accounts = $income_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $income_accounts = [];
}

// Get Expense accounts
try {
    $expense_stmt = $pdo->prepare("
        SELECT
            l.id, l.name as ledger_name, l.code,
            ag.name as group_name,
            ABS(l.current_balance) as balance
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Expenses'
        AND l.is_active = 1
        AND l.current_balance != 0
        ORDER BY ag.name, l.name
    ");
    $expense_stmt->execute();
    $expense_accounts = $expense_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expense_accounts = [];
}

// Calculate totals
$total_income = array_sum(array_column($income_accounts, 'balance'));
$total_expense = array_sum(array_column($expense_accounts, 'balance'));
$gross_profit = $total_income - $total_expense;

// Group accounts by group name
$income_grouped = [];
foreach ($income_accounts as $acc) {
    $income_grouped[$acc['group_name']][] = $acc;
}

$expense_grouped = [];
foreach ($expense_accounts as $acc) {
    $expense_grouped[$acc['group_name']][] = $acc;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profit & Loss Statement - Accounts</title>
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
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 20px 25px;
            text-align: center;
        }
        .report-header h2 { margin: 0; font-size: 1.5em; }
        .report-header .date { margin-top: 5px; opacity: 0.9; }

        .pl-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .pl-section {
            padding: 20px;
            border-right: 1px solid #eee;
        }
        .pl-section:last-child { border-right: none; }
        .pl-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        .pl-section.income h3 { color: #27ae60; border-color: #27ae60; }
        .pl-section.expense h3 { color: #e74c3c; border-color: #e74c3c; }

        .pl-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pl-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .pl-table .amount { text-align: right; font-family: monospace; }
        .pl-table .group-row {
            font-weight: 600;
            background: #f8f9fa;
            color: #495057;
        }
        .pl-table .item-row td:first-child { padding-left: 25px; }
        .pl-table .total-row {
            font-weight: 700;
            background: #f8f9fa;
        }

        .result-section {
            background: #f8f9fa;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #667eea;
        }
        .result-label { font-weight: 600; color: #495057; font-size: 1.1em; }
        .result-value {
            font-size: 2em;
            font-weight: 700;
        }
        .result-value.profit { color: #27ae60; }
        .result-value.loss { color: #e74c3c; }

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
        .summary-card.income .value { color: #27ae60; }
        .summary-card.expense .value { color: #e74c3c; }
        .summary-card.profit .value { color: #667eea; }

        @media print {
            .filter-bar, .page-header .btn, .no-print { display: none; }
            .content { margin: 0; padding: 0; }
            .report-card { box-shadow: none; }
        }

        @media (max-width: 768px) {
            .pl-container { grid-template-columns: 1fr; }
            .pl-section { border-right: none; border-bottom: 1px solid #eee; }
        }

        body.dark .filter-bar, body.dark .report-card, body.dark .summary-card { background: #2c3e50; }
        body.dark .pl-section h3 { color: #ecf0f1; }
        body.dark .result-section, body.dark .pl-table .group-row, body.dark .pl-table .total-row { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Profit & Loss Statement</h1>
            <p style="color: #666; margin: 5px 0 0;">Income and Expense Summary</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary no-print">Print</button>
        </div>
    </div>

    <form method="get" class="filter-bar no-print">
        <label style="font-weight: 600;">From:</label>
        <input type="date" name="from_date" value="<?= $from_date ?>">
        <label style="font-weight: 600;">To:</label>
        <input type="date" name="to_date" value="<?= $to_date ?>">
        <button type="submit" class="btn btn-primary">Generate</button>
        <a href="trial_balance.php" class="btn btn-secondary">Trial Balance</a>
        <a href="balance_sheet.php" class="btn btn-secondary">Balance Sheet</a>
    </form>

    <!-- Summary Cards -->
    <div class="summary-cards no-print">
        <div class="summary-card income">
            <div class="label">Total Income</div>
            <div class="value">₹<?= number_format($total_income, 0) ?></div>
        </div>
        <div class="summary-card expense">
            <div class="label">Total Expenses</div>
            <div class="value">₹<?= number_format($total_expense, 0) ?></div>
        </div>
        <div class="summary-card profit">
            <div class="label"><?= $gross_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
            <div class="value">₹<?= number_format(abs($gross_profit), 0) ?></div>
        </div>
    </div>

    <div class="report-card">
        <div class="report-header">
            <h2>Profit & Loss Statement</h2>
            <div class="date">For the period <?= date('d M Y', strtotime($from_date)) ?> to <?= date('d M Y', strtotime($to_date)) ?></div>
        </div>

        <div class="pl-container">
            <!-- Income Section -->
            <div class="pl-section income">
                <h3>Income</h3>
                <table class="pl-table">
                    <?php foreach ($income_grouped as $group => $accounts): ?>
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

                    <?php if (empty($income_accounts)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center; color: #666; padding: 30px;">
                                No income recorded
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="total-row">
                        <td>Total Income</td>
                        <td class="amount"><?= number_format($total_income, 2) ?></td>
                    </tr>

                    <?php if ($gross_profit < 0): ?>
                        <tr class="total-row" style="color: #e74c3c;">
                            <td>Net Loss</td>
                            <td class="amount"><?= number_format(abs($gross_profit), 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Expense Section -->
            <div class="pl-section expense">
                <h3>Expenses</h3>
                <table class="pl-table">
                    <?php foreach ($expense_grouped as $group => $accounts): ?>
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

                    <?php if (empty($expense_accounts)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center; color: #666; padding: 30px;">
                                No expenses recorded
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="total-row">
                        <td>Total Expenses</td>
                        <td class="amount"><?= number_format($total_expense, 2) ?></td>
                    </tr>

                    <?php if ($gross_profit >= 0): ?>
                        <tr class="total-row" style="color: #27ae60;">
                            <td>Net Profit</td>
                            <td class="amount"><?= number_format($gross_profit, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="result-section">
            <div class="result-label"><?= $gross_profit >= 0 ? 'Net Profit for the Period' : 'Net Loss for the Period' ?></div>
            <div class="result-value <?= $gross_profit >= 0 ? 'profit' : 'loss' ?>">
                ₹<?= number_format(abs($gross_profit), 2) ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
