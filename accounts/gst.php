<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get period filter
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$from_date = sprintf('%04d-%02d-01', $year, $month);
$to_date = date('Y-m-t', strtotime($from_date));

// Get GST summary
try {
    // Output GST (Sales)
    $output_stmt = $pdo->prepare("
        SELECT
            SUM(cgst_amount) as total_cgst,
            SUM(sgst_amount) as total_sgst,
            SUM(igst_amount) as total_igst,
            SUM(taxable_amount) as total_taxable,
            COUNT(*) as count
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
    ");
    $output_stmt->execute([$from_date, $to_date]);
    $output_gst = $output_stmt->fetch(PDO::FETCH_ASSOC);

    // Input GST (Purchases/Expenses)
    $input_stmt = $pdo->prepare("
        SELECT
            SUM(cgst_amount) as total_cgst,
            SUM(sgst_amount) as total_sgst,
            SUM(igst_amount) as total_igst,
            SUM(taxable_amount) as total_taxable,
            COUNT(*) as count
        FROM acc_gst_transactions
        WHERE transaction_type = 'Input'
        AND transaction_date BETWEEN ? AND ?
    ");
    $input_stmt->execute([$from_date, $to_date]);
    $input_gst = $input_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $output_gst = ['total_cgst' => 0, 'total_sgst' => 0, 'total_igst' => 0, 'total_taxable' => 0, 'count' => 0];
    $input_gst = ['total_cgst' => 0, 'total_sgst' => 0, 'total_igst' => 0, 'total_taxable' => 0, 'count' => 0];
}

// Calculate liability
$output_total = ($output_gst['total_cgst'] ?? 0) + ($output_gst['total_sgst'] ?? 0) + ($output_gst['total_igst'] ?? 0);
$input_total = ($input_gst['total_cgst'] ?? 0) + ($input_gst['total_sgst'] ?? 0) + ($input_gst['total_igst'] ?? 0);
$net_liability = $output_total - $input_total;

// Get recent transactions
try {
    $trans_stmt = $pdo->prepare("
        SELECT * FROM acc_gst_transactions
        WHERE transaction_date BETWEEN ? AND ?
        ORDER BY transaction_date DESC, id DESC
        LIMIT 50
    ");
    $trans_stmt->execute([$from_date, $to_date]);
    $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transactions = [];
}

// Get GST returns status
try {
    $returns_stmt = $pdo->prepare("
        SELECT * FROM acc_gst_returns
        WHERE return_period = ?
        ORDER BY return_type
    ");
    $returns_stmt->execute([sprintf('%04d-%02d', $year, $month)]);
    $gst_returns = $returns_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $gst_returns = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>GST Management - Accounts</title>
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

        .period-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .period-selector select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
        }

        .gst-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .gst-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .gst-card.output { border-left: 4px solid #e74c3c; }
        .gst-card.input { border-left: 4px solid #27ae60; }
        .gst-card.liability { border-left: 4px solid #667eea; }

        .gst-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1em;
        }
        .gst-card .amount {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .gst-card.output .amount { color: #e74c3c; }
        .gst-card.input .amount { color: #27ae60; }
        .gst-card.liability .amount { color: #667eea; }

        .gst-breakdown {
            font-size: 0.9em;
            color: #666;
        }
        .gst-breakdown div {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .gst-breakdown div:last-child { border-bottom: none; }

        .quick-links {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .quick-link {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .quick-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .quick-link .icon {
            font-size: 2em;
        }
        .quick-link .text { font-weight: 600; }
        .quick-link .sub { color: #666; font-size: 0.85em; font-weight: normal; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .type-output { color: #e74c3c; font-weight: 600; }
        .type-input { color: #27ae60; font-weight: 600; }

        .returns-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .returns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .return-card {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        .return-card .name { font-weight: 600; color: #2c3e50; }
        .return-card .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            margin-top: 10px;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-filed { background: #d4edda; color: #155724; }

        body.dark .gst-card, body.dark .period-selector, body.dark .quick-link,
        body.dark .data-table, body.dark .returns-section { background: #2c3e50; }
        body.dark .gst-card h3, body.dark .quick-link { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>GST Management</h1>
            <p style="color: #666; margin: 5px 0 0;">Track GST collections, ITC, and file returns</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>

    <!-- Period Selector -->
    <form method="get" class="period-selector">
        <label style="font-weight: 600;">Period:</label>
        <select name="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary">View</button>
    </form>

    <!-- GST Summary Cards -->
    <div class="gst-cards">
        <div class="gst-card output">
            <h3>Output GST (Collected)</h3>
            <div class="amount">₹<?= number_format($output_total, 2) ?></div>
            <div class="gst-breakdown">
                <div><span>CGST</span><span>₹<?= number_format($output_gst['total_cgst'] ?? 0, 2) ?></span></div>
                <div><span>SGST</span><span>₹<?= number_format($output_gst['total_sgst'] ?? 0, 2) ?></span></div>
                <div><span>IGST</span><span>₹<?= number_format($output_gst['total_igst'] ?? 0, 2) ?></span></div>
                <div><span>Taxable Value</span><span>₹<?= number_format($output_gst['total_taxable'] ?? 0, 2) ?></span></div>
            </div>
        </div>

        <div class="gst-card input">
            <h3>Input Tax Credit (ITC)</h3>
            <div class="amount">₹<?= number_format($input_total, 2) ?></div>
            <div class="gst-breakdown">
                <div><span>CGST</span><span>₹<?= number_format($input_gst['total_cgst'] ?? 0, 2) ?></span></div>
                <div><span>SGST</span><span>₹<?= number_format($input_gst['total_sgst'] ?? 0, 2) ?></span></div>
                <div><span>IGST</span><span>₹<?= number_format($input_gst['total_igst'] ?? 0, 2) ?></span></div>
                <div><span>Taxable Value</span><span>₹<?= number_format($input_gst['total_taxable'] ?? 0, 2) ?></span></div>
            </div>
        </div>

        <div class="gst-card liability">
            <h3>Net GST Liability</h3>
            <div class="amount">₹<?= number_format(max(0, $net_liability), 2) ?></div>
            <div class="gst-breakdown">
                <div><span>Output GST</span><span>₹<?= number_format($output_total, 2) ?></span></div>
                <div><span>Less: ITC</span><span>₹<?= number_format($input_total, 2) ?></span></div>
                <div style="font-weight: 600;"><span>Payable</span><span>₹<?= number_format(max(0, $net_liability), 2) ?></span></div>
                <?php if ($net_liability < 0): ?>
                    <div style="color: #27ae60;"><span>ITC Carry Forward</span><span>₹<?= number_format(abs($net_liability), 2) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="quick-links">
        <a href="gstr1.php?month=<?= $month ?>&year=<?= $year ?>" class="quick-link">
            <div class="icon">📋</div>
            <div>
                <div class="text">GSTR-1</div>
                <div class="sub">Outward Supplies</div>
            </div>
        </a>
        <a href="gstr3b.php?month=<?= $month ?>&year=<?= $year ?>" class="quick-link">
            <div class="icon">📊</div>
            <div>
                <div class="text">GSTR-3B</div>
                <div class="sub">Summary Return</div>
            </div>
        </a>
        <a href="gst_transactions.php" class="quick-link">
            <div class="icon">📝</div>
            <div>
                <div class="text">Transactions</div>
                <div class="sub">All GST Entries</div>
            </div>
        </a>
        <a href="gst_rates.php" class="quick-link">
            <div class="icon">⚙️</div>
            <div>
                <div class="text">GST Rates</div>
                <div class="sub">Manage Rates</div>
            </div>
        </a>
    </div>

    <!-- Returns Status -->
    <div class="returns-section">
        <h3 style="margin: 0 0 5px 0; color: #2c3e50;">GST Returns Status - <?= date('F Y', strtotime($from_date)) ?></h3>
        <div class="returns-grid">
            <?php
            $return_types = ['GSTR-1', 'GSTR-3B', 'GSTR-9'];
            foreach ($return_types as $type):
                $filed = false;
                foreach ($gst_returns as $ret) {
                    if ($ret['return_type'] === $type && $ret['status'] === 'Filed') {
                        $filed = true;
                        break;
                    }
                }
            ?>
                <div class="return-card">
                    <div class="name"><?= $type ?></div>
                    <span class="status <?= $filed ? 'status-filed' : 'status-pending' ?>">
                        <?= $filed ? 'Filed' : 'Pending' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <h3 style="color: #2c3e50; margin-bottom: 15px;">Recent GST Transactions</h3>
    <?php if (empty($transactions)): ?>
        <div style="background: white; padding: 40px; text-align: center; border-radius: 10px; color: #666;">
            <p>No GST transactions found for this period.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Party GSTIN</th>
                    <th style="text-align: right;">Taxable</th>
                    <th style="text-align: right;">CGST</th>
                    <th style="text-align: right;">SGST</th>
                    <th style="text-align: right;">IGST</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($trans['transaction_date'])) ?></td>
                        <td class="type-<?= strtolower($trans['transaction_type']) ?>"><?= $trans['transaction_type'] ?></td>
                        <td><?= htmlspecialchars($trans['reference_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($trans['party_gstin'] ?: '-') ?></td>
                        <td style="text-align: right;">₹<?= number_format($trans['taxable_amount'], 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($trans['cgst_amount'], 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($trans['sgst_amount'], 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($trans['igst_amount'], 2) ?></td>
                        <td style="text-align: right; font-weight: 600;">
                            ₹<?= number_format($trans['cgst_amount'] + $trans['sgst_amount'] + $trans['igst_amount'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
