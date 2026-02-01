<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get quarter filter
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : ceil(date('n') / 3);
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate quarter dates
$quarter_months = [
    1 => ['start' => '04-01', 'end' => '06-30', 'label' => 'Q1 (Apr-Jun)'],
    2 => ['start' => '07-01', 'end' => '09-30', 'label' => 'Q2 (Jul-Sep)'],
    3 => ['start' => '10-01', 'end' => '12-31', 'label' => 'Q3 (Oct-Dec)'],
    4 => ['start' => '01-01', 'end' => '03-31', 'label' => 'Q4 (Jan-Mar)']
];

// For Q4, the year should be FY+1
$fy_start = $quarter == 4 ? $year + 1 : $year;
$from_date = $fy_start . '-' . $quarter_months[$quarter]['start'];
$to_date = $fy_start . '-' . $quarter_months[$quarter]['end'];

// Get TDS sections
try {
    $sections_stmt = $pdo->query("SELECT * FROM acc_tds_sections WHERE is_active = 1 ORDER BY section_code");
    $tds_sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tds_sections = [];
}

// Get TDS summary by section
try {
    $summary_stmt = $pdo->prepare("
        SELECT
            t.section_id,
            s.section_code,
            s.description as section_desc,
            COUNT(*) as transaction_count,
            SUM(t.payment_amount) as total_payment,
            SUM(t.tds_amount) as total_tds,
            SUM(CASE WHEN t.is_deposited = 1 THEN t.tds_amount ELSE 0 END) as deposited_tds,
            SUM(CASE WHEN t.is_deposited = 0 THEN t.tds_amount ELSE 0 END) as pending_tds
        FROM acc_tds_transactions t
        LEFT JOIN acc_tds_sections s ON t.section_id = s.id
        WHERE t.transaction_date BETWEEN ? AND ?
        GROUP BY t.section_id, s.section_code, s.description
        ORDER BY s.section_code
    ");
    $summary_stmt->execute([$from_date, $to_date]);
    $tds_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tds_summary = [];
}

// Get recent TDS transactions
try {
    $trans_stmt = $pdo->prepare("
        SELECT t.*, s.section_code, s.description as section_desc
        FROM acc_tds_transactions t
        LEFT JOIN acc_tds_sections s ON t.section_id = s.id
        WHERE t.transaction_date BETWEEN ? AND ?
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 50
    ");
    $trans_stmt->execute([$from_date, $to_date]);
    $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transactions = [];
}

// Calculate totals
$total_payment = array_sum(array_column($tds_summary, 'total_payment'));
$total_tds = array_sum(array_column($tds_summary, 'total_tds'));
$total_deposited = array_sum(array_column($tds_summary, 'deposited_tds'));
$total_pending = array_sum(array_column($tds_summary, 'pending_tds'));

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>TDS Management - Accounts</title>
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

        .tds-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .tds-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .tds-card .label { color: #666; font-size: 0.9em; }
        .tds-card .value { font-size: 1.5em; font-weight: 700; margin-top: 5px; }
        .tds-card.payment .value { color: #3498db; }
        .tds-card.deducted .value { color: #e74c3c; }
        .tds-card.deposited .value { color: #27ae60; }
        .tds-card.pending .value { color: #f39c12; }

        .quick-links {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .quick-link {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s;
        }
        .quick-link:hover { transform: translateY(-2px); }
        .quick-link .icon { font-size: 1.5em; }

        .section-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .section-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
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

        .section-badge {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-deposited { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 10px; font-size: 0.85em; }
        .status-pending { background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 10px; font-size: 0.85em; }

        body.dark .tds-card, body.dark .quick-link, body.dark .section-card { background: #2c3e50; }
        body.dark .section-card h3, body.dark .quick-link { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>TDS Management</h1>
            <p style="color: #666; margin: 5px 0 0;">Tax Deducted at Source - Tracking & Returns</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>

    <!-- Period Selector -->
    <form method="get" class="period-selector">
        <label style="font-weight: 600;">Financial Year:</label>
        <select name="year">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>>FY <?= $y ?>-<?= substr($y + 1, 2) ?></option>
            <?php endfor; ?>
        </select>
        <label style="font-weight: 600;">Quarter:</label>
        <select name="quarter">
            <?php foreach ($quarter_months as $q => $info): ?>
                <option value="<?= $q ?>" <?= $quarter == $q ? 'selected' : '' ?>><?= $info['label'] ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">View</button>
    </form>

    <!-- Summary Cards -->
    <div class="tds-cards">
        <div class="tds-card payment">
            <div class="label">Total Payments</div>
            <div class="value">₹<?= number_format($total_payment, 0) ?></div>
        </div>
        <div class="tds-card deducted">
            <div class="label">TDS Deducted</div>
            <div class="value">₹<?= number_format($total_tds, 0) ?></div>
        </div>
        <div class="tds-card deposited">
            <div class="label">TDS Deposited</div>
            <div class="value">₹<?= number_format($total_deposited, 0) ?></div>
        </div>
        <div class="tds-card pending">
            <div class="label">TDS Pending</div>
            <div class="value">₹<?= number_format($total_pending, 0) ?></div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="quick-links">
        <a href="tds_add.php" class="quick-link">
            <span class="icon">+</span>
            <span>Add TDS Entry</span>
        </a>
        <a href="tds_challan.php" class="quick-link">
            <span class="icon">📜</span>
            <span>TDS Challan</span>
        </a>
        <a href="form_26q.php?quarter=<?= $quarter ?>&year=<?= $year ?>" class="quick-link">
            <span class="icon">📋</span>
            <span>Form 26Q</span>
        </a>
        <a href="form_27q.php?quarter=<?= $quarter ?>&year=<?= $year ?>" class="quick-link">
            <span class="icon">📋</span>
            <span>Form 27Q</span>
        </a>
        <a href="tds_sections.php" class="quick-link">
            <span class="icon">⚙️</span>
            <span>TDS Sections</span>
        </a>
    </div>

    <!-- Section-wise Summary -->
    <div class="section-card">
        <h3>Section-wise TDS Summary</h3>
        <?php if (empty($tds_summary)): ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                No TDS transactions for this quarter.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Description</th>
                        <th style="text-align: center;">Transactions</th>
                        <th style="text-align: right;">Total Payment</th>
                        <th style="text-align: right;">TDS Deducted</th>
                        <th style="text-align: right;">Deposited</th>
                        <th style="text-align: right;">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tds_summary as $row): ?>
                        <tr>
                            <td><span class="section-badge"><?= htmlspecialchars($row['section_code']) ?></span></td>
                            <td><?= htmlspecialchars(substr($row['section_desc'], 0, 40)) ?>...</td>
                            <td style="text-align: center;"><?= $row['transaction_count'] ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['total_payment'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['total_tds'], 2) ?></td>
                            <td style="text-align: right; color: #27ae60;">₹<?= number_format($row['deposited_tds'], 2) ?></td>
                            <td style="text-align: right; color: #f39c12;">₹<?= number_format($row['pending_tds'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: 600; background: #f8f9fa;">
                        <td colspan="3">Total</td>
                        <td style="text-align: right;">₹<?= number_format($total_payment, 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format($total_tds, 2) ?></td>
                        <td style="text-align: right; color: #27ae60;">₹<?= number_format($total_deposited, 2) ?></td>
                        <td style="text-align: right; color: #f39c12;">₹<?= number_format($total_pending, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Transactions -->
    <div class="section-card">
        <h3>Recent TDS Transactions</h3>
        <?php if (empty($transactions)): ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                No TDS transactions found.
                <br><br>
                <a href="tds_add.php" class="btn btn-primary">+ Add First TDS Entry</a>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Deductee</th>
                        <th>PAN</th>
                        <th>Section</th>
                        <th style="text-align: right;">Payment</th>
                        <th style="text-align: right;">TDS</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($trans['transaction_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($trans['deductee_name']) ?></strong></td>
                            <td><?= htmlspecialchars($trans['deductee_pan']) ?></td>
                            <td><span class="section-badge"><?= htmlspecialchars($trans['section_code']) ?></span></td>
                            <td style="text-align: right;">₹<?= number_format($trans['payment_amount'], 2) ?></td>
                            <td style="text-align: right; color: #e74c3c;">₹<?= number_format($trans['tds_amount'], 2) ?></td>
                            <td>
                                <?php if ($trans['is_deposited']): ?>
                                    <span class="status-deposited">Deposited</span>
                                <?php else: ?>
                                    <span class="status-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="tds_view.php?id=<?= $trans['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Due Dates Info -->
    <div class="section-card" style="background: #fff3e0; border-left: 4px solid #ff9800;">
        <h3 style="color: #e65100;">TDS Payment Due Dates</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <div>
                <strong>Monthly TDS (Non-Government)</strong>
                <p style="color: #666; margin: 5px 0 0;">7th of following month</p>
            </div>
            <div>
                <strong>Quarterly Return (26Q/27Q)</strong>
                <p style="color: #666; margin: 5px 0 0;">31st of month following quarter end</p>
            </div>
            <div>
                <strong>March TDS</strong>
                <p style="color: #666; margin: 5px 0 0;">30th April (extended deadline)</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
