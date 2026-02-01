<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Current Financial Year
$current_month = date('n');
$fy_start = $current_month >= 4 ? date('Y') . '-04-01' : (date('Y') - 1) . '-04-01';
$fy_end = $current_month >= 4 ? (date('Y') + 1) . '-03-31' : date('Y') . '-03-31';
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Get Total Income
try {
    $income_stmt = $pdo->query("
        SELECT COALESCE(SUM(ABS(current_balance)), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Income' AND l.is_active = 1
    ");
    $total_income = $income_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_income = 0;
}

// Get Total Expenses
try {
    $expense_stmt = $pdo->query("
        SELECT COALESCE(SUM(ABS(current_balance)), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.type = 'Expenses' AND l.is_active = 1
    ");
    $total_expenses = $expense_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_expenses = 0;
}

$net_profit = $total_income - $total_expenses;

// Get Bank Balances
try {
    $bank_stmt = $pdo->query("
        SELECT COALESCE(SUM(current_balance), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.name = 'Bank Accounts' AND l.is_active = 1
    ");
    $bank_balance = $bank_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $bank_balance = 0;
}

// Get Cash Balance
try {
    $cash_stmt = $pdo->query("
        SELECT COALESCE(SUM(current_balance), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.name = 'Cash-in-Hand' AND l.is_active = 1
    ");
    $cash_balance = $cash_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $cash_balance = 0;
}

// Get Receivables
try {
    $recv_stmt = $pdo->query("
        SELECT COALESCE(SUM(current_balance), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.name = 'Sundry Debtors' AND l.is_active = 1
    ");
    $receivables = $recv_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $receivables = 0;
}

// Get Payables
try {
    $payable_stmt = $pdo->query("
        SELECT COALESCE(SUM(ABS(current_balance)), 0) as total
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.name = 'Sundry Creditors' AND l.is_active = 1
    ");
    $payables = $payable_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $payables = 0;
}

// Get Recent Vouchers
try {
    $vouchers_stmt = $pdo->query("
        SELECT v.*, vt.name as type_name
        FROM acc_vouchers v
        LEFT JOIN acc_voucher_types vt ON v.voucher_type_id = vt.id
        ORDER BY v.voucher_date DESC, v.id DESC
        LIMIT 10
    ");
    $recent_vouchers = $vouchers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_vouchers = [];
}

// Get This Month's Expenses
try {
    $month_exp_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM acc_expenses
        WHERE expense_date BETWEEN ? AND ?
    ");
    $month_exp_stmt->execute([$month_start, $month_end]);
    $month_expenses = $month_exp_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $month_expenses = 0;
}

// Get GST Liability
try {
    $gst_out = $pdo->query("SELECT COALESCE(SUM(cgst_amount + sgst_amount + igst_amount), 0) as total FROM acc_gst_transactions WHERE transaction_type = 'Output'")->fetch(PDO::FETCH_ASSOC)['total'];
    $gst_in = $pdo->query("SELECT COALESCE(SUM(cgst_amount + sgst_amount + igst_amount), 0) as total FROM acc_gst_transactions WHERE transaction_type = 'Input'")->fetch(PDO::FETCH_ASSOC)['total'];
    $gst_liability = max(0, $gst_out - $gst_in);
} catch (Exception $e) {
    $gst_liability = 0;
}

// Get Pending TDS
try {
    $tds_stmt = $pdo->query("SELECT COALESCE(SUM(tds_amount), 0) as total FROM acc_tds_transactions WHERE is_deposited = 0");
    $pending_tds = $tds_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $pending_tds = 0;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Accounts Dashboard</title>
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
        .fy-badge {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-card.income::before { background: #27ae60; }
        .stat-card.expense::before { background: #e74c3c; }
        .stat-card.profit::before { background: #667eea; }
        .stat-card.bank::before { background: #3498db; }
        .stat-card.cash::before { background: #f39c12; }
        .stat-card.receivable::before { background: #9b59b6; }
        .stat-card.payable::before { background: #e67e22; }

        .stat-card .label { color: #666; font-size: 0.9em; margin-bottom: 5px; }
        .stat-card .value { font-size: 1.6em; font-weight: 700; }
        .stat-card.income .value { color: #27ae60; }
        .stat-card.expense .value { color: #e74c3c; }
        .stat-card.profit .value { color: #667eea; }
        .stat-card.bank .value { color: #3498db; }
        .stat-card.cash .value { color: #f39c12; }
        .stat-card.receivable .value { color: #9b59b6; }
        .stat-card.payable .value { color: #e67e22; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .quick-action {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .quick-action .icon { font-size: 2em; margin-bottom: 10px; }
        .quick-action .text { font-weight: 600; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 { margin: 0; color: #2c3e50; }
        .card-body { padding: 20px; }

        .voucher-list { list-style: none; padding: 0; margin: 0; }
        .voucher-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .voucher-list li:last-child { border-bottom: none; }
        .voucher-no { font-weight: 600; color: #667eea; }
        .voucher-type { font-size: 0.85em; color: #666; }
        .voucher-date { color: #999; font-size: 0.85em; }

        .compliance-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .compliance-item .label { font-weight: 600; color: #495057; }
        .compliance-item .value { font-weight: 700; }
        .compliance-item.warning { background: #fff3cd; }
        .compliance-item.warning .value { color: #856404; }
        .compliance-item.danger { background: #f8d7da; }
        .compliance-item.danger .value { color: #721c24; }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .module-link {
            background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            text-align: center;
            border: 1px solid #eee;
            transition: all 0.2s;
        }
        .module-link:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .module-link .icon { font-size: 1.8em; margin-bottom: 8px; }
        .module-link .name { font-weight: 600; }

        body.dark .stat-card, body.dark .quick-action, body.dark .card { background: #2c3e50; }
        body.dark .card-header h3, body.dark .quick-action { color: #ecf0f1; }
        body.dark .compliance-item { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Accounts & Finance</h1>
            <p style="color: #666; margin: 5px 0 0;">Tally-like Accounting Dashboard</p>
        </div>
        <span class="fy-badge">FY <?= date('Y', strtotime($fy_start)) ?>-<?= date('y', strtotime($fy_end)) ?></span>
    </div>

    <!-- Key Financial Stats -->
    <div class="stats-grid">
        <div class="stat-card income">
            <div class="label">Total Income</div>
            <div class="value">₹<?= number_format($total_income, 0) ?></div>
        </div>
        <div class="stat-card expense">
            <div class="label">Total Expenses</div>
            <div class="value">₹<?= number_format($total_expenses, 0) ?></div>
        </div>
        <div class="stat-card profit">
            <div class="label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
            <div class="value">₹<?= number_format(abs($net_profit), 0) ?></div>
        </div>
        <div class="stat-card bank">
            <div class="label">Bank Balance</div>
            <div class="value">₹<?= number_format($bank_balance, 0) ?></div>
        </div>
        <div class="stat-card cash">
            <div class="label">Cash in Hand</div>
            <div class="value">₹<?= number_format($cash_balance, 0) ?></div>
        </div>
        <div class="stat-card receivable">
            <div class="label">Receivables</div>
            <div class="value">₹<?= number_format($receivables, 0) ?></div>
        </div>
        <div class="stat-card payable">
            <div class="label">Payables</div>
            <div class="value">₹<?= number_format($payables, 0) ?></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h3 style="color: #2c3e50; margin-bottom: 15px;">Quick Actions</h3>
    <div class="quick-actions">
        <a href="voucher_add.php?type=1" class="quick-action">
            <div class="icon">💸</div>
            <div class="text">Payment</div>
        </a>
        <a href="voucher_add.php?type=2" class="quick-action">
            <div class="icon">💰</div>
            <div class="text">Receipt</div>
        </a>
        <a href="voucher_add.php?type=4" class="quick-action">
            <div class="icon">📝</div>
            <div class="text">Journal</div>
        </a>
        <a href="expense_add.php" class="quick-action">
            <div class="icon">🧾</div>
            <div class="text">Expense</div>
        </a>
        <a href="ledger_add.php" class="quick-action">
            <div class="icon">📒</div>
            <div class="text">New Ledger</div>
        </a>
        <a href="bank_reconciliation.php" class="quick-action">
            <div class="icon">🏦</div>
            <div class="text">Reconcile</div>
        </a>
    </div>

    <div class="dashboard-grid">
        <!-- Left Column -->
        <div>
            <!-- Recent Vouchers -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3>Recent Transactions</h3>
                    <a href="vouchers.php" class="btn btn-sm btn-secondary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_vouchers)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No transactions yet</p>
                    <?php else: ?>
                        <ul class="voucher-list">
                            <?php foreach ($recent_vouchers as $v): ?>
                                <li>
                                    <div>
                                        <a href="voucher_view.php?id=<?= $v['id'] ?>" class="voucher-no"><?= htmlspecialchars($v['voucher_no']) ?></a>
                                        <div class="voucher-type"><?= htmlspecialchars($v['type_name']) ?></div>
                                    </div>
                                    <div class="voucher-date"><?= date('d M Y', strtotime($v['voucher_date'])) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modules -->
            <div class="card">
                <div class="card-header">
                    <h3>Accounting Modules</h3>
                </div>
                <div class="card-body">
                    <div class="module-grid">
                        <a href="ledgers.php" class="module-link">
                            <div class="icon">📚</div>
                            <div class="name">Chart of Accounts</div>
                        </a>
                        <a href="vouchers.php" class="module-link">
                            <div class="icon">📋</div>
                            <div class="name">Vouchers</div>
                        </a>
                        <a href="expenses.php" class="module-link">
                            <div class="icon">💳</div>
                            <div class="name">Expenses</div>
                        </a>
                        <a href="bank_reconciliation.php" class="module-link">
                            <div class="icon">🏦</div>
                            <div class="name">Bank Recon</div>
                        </a>
                        <a href="gst.php" class="module-link">
                            <div class="icon">📊</div>
                            <div class="name">GST</div>
                        </a>
                        <a href="tds.php" class="module-link">
                            <div class="icon">📑</div>
                            <div class="name">TDS</div>
                        </a>
                        <a href="trial_balance.php" class="module-link">
                            <div class="icon">⚖️</div>
                            <div class="name">Trial Balance</div>
                        </a>
                        <a href="profit_loss.php" class="module-link">
                            <div class="icon">📈</div>
                            <div class="name">P&L Statement</div>
                        </a>
                        <a href="balance_sheet.php" class="module-link">
                            <div class="icon">📉</div>
                            <div class="name">Balance Sheet</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Tax Compliance -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h3>Tax Compliance</h3>
                </div>
                <div class="card-body">
                    <div class="compliance-item <?= $gst_liability > 0 ? 'warning' : '' ?>">
                        <div class="label">GST Liability</div>
                        <div class="value">₹<?= number_format($gst_liability, 0) ?></div>
                    </div>
                    <div class="compliance-item <?= $pending_tds > 0 ? 'danger' : '' ?>">
                        <div class="label">Pending TDS</div>
                        <div class="value">₹<?= number_format($pending_tds, 0) ?></div>
                    </div>
                    <div class="compliance-item">
                        <div class="label">This Month Expenses</div>
                        <div class="value">₹<?= number_format($month_expenses, 0) ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Reports -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Reports</h3>
                </div>
                <div class="card-body">
                    <a href="trial_balance.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">Trial Balance</a>
                    <a href="profit_loss.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">Profit & Loss</a>
                    <a href="balance_sheet.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">Balance Sheet</a>
                    <a href="gstr1.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">GSTR-1</a>
                    <a href="gstr3b.php" class="btn btn-secondary" style="width: 100%;">GSTR-3B</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
