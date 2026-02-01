<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get period
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$from_date = sprintf('%04d-%02d-01', $year, $month);
$to_date = date('Y-m-t', strtotime($from_date));
$return_period = sprintf('%04d-%02d', $year, $month);

// Get B2B (Business to Business) - Invoices to registered dealers
try {
    $b2b_stmt = $pdo->prepare("
        SELECT
            party_gstin,
            COUNT(*) as invoice_count,
            SUM(taxable_amount) as taxable_value,
            SUM(cgst_amount) as cgst,
            SUM(sgst_amount) as sgst,
            SUM(igst_amount) as igst,
            SUM(cess_amount) as cess
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND party_gstin IS NOT NULL AND party_gstin != ''
        GROUP BY party_gstin
        ORDER BY taxable_value DESC
    ");
    $b2b_stmt->execute([$from_date, $to_date]);
    $b2b_data = $b2b_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $b2b_data = [];
}

// Get B2C Large (Unregistered dealers > 2.5 Lakhs inter-state)
try {
    $b2cl_stmt = $pdo->prepare("
        SELECT
            place_of_supply,
            SUM(taxable_amount) as taxable_value,
            SUM(igst_amount) as igst,
            SUM(cess_amount) as cess
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND (party_gstin IS NULL OR party_gstin = '')
        AND taxable_amount > 250000
        AND igst_amount > 0
        GROUP BY place_of_supply
    ");
    $b2cl_stmt->execute([$from_date, $to_date]);
    $b2cl_data = $b2cl_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $b2cl_data = [];
}

// Get B2C Small summary
try {
    $b2cs_stmt = $pdo->prepare("
        SELECT
            gst_rate,
            SUM(taxable_amount) as taxable_value,
            SUM(cgst_amount) as cgst,
            SUM(sgst_amount) as sgst,
            SUM(igst_amount) as igst,
            SUM(cess_amount) as cess
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND (party_gstin IS NULL OR party_gstin = '')
        AND (taxable_amount <= 250000 OR igst_amount = 0)
        GROUP BY gst_rate
    ");
    $b2cs_stmt->execute([$from_date, $to_date]);
    $b2cs_data = $b2cs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $b2cs_data = [];
}

// Get HSN Summary
try {
    $hsn_stmt = $pdo->prepare("
        SELECT
            hsn_code,
            description as hsn_desc,
            SUM(quantity) as total_qty,
            SUM(taxable_amount) as taxable_value,
            SUM(cgst_amount + sgst_amount + igst_amount) as total_tax
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND hsn_code IS NOT NULL AND hsn_code != ''
        GROUP BY hsn_code, description
        ORDER BY taxable_value DESC
    ");
    $hsn_stmt->execute([$from_date, $to_date]);
    $hsn_data = $hsn_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hsn_data = [];
}

// Get totals
$total_b2b = array_sum(array_column($b2b_data, 'taxable_value'));
$total_b2cl = array_sum(array_column($b2cl_data, 'taxable_value'));
$total_b2cs = array_sum(array_column($b2cs_data, 'taxable_value'));

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>GSTR-1 - <?= date('F Y', strtotime($from_date)) ?></title>
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

        .period-badge {
            background: #667eea;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .summary-card .label { color: #666; font-size: 0.9em; }
        .summary-card .value { font-size: 1.5em; font-weight: 700; color: #667eea; margin-top: 5px; }

        .section-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .section-card h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-card .section-sub { color: #666; font-size: 0.9em; margin-bottom: 15px; }

        .section-badge {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .empty-section {
            padding: 30px;
            text-align: center;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .action-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        body.dark .summary-card, body.dark .section-card { background: #2c3e50; }
        body.dark .section-card h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>GSTR-1 Return</h1>
            <p style="color: #666; margin: 5px 0 0;">Outward Supplies Return</p>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span class="period-badge"><?= date('F Y', strtotime($from_date)) ?></span>
            <a href="gst.php" class="btn btn-secondary">Back to GST</a>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <form method="get" style="display: flex; gap: 10px; align-items: center;">
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
            <button type="submit" class="btn btn-primary">Load</button>
        </form>
        <a href="gstr1_export.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary">Export JSON</a>
        <a href="gstr1_excel.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary">Export Excel</a>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">B2B Taxable Value</div>
            <div class="value">₹<?= number_format($total_b2b, 0) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">B2C Large</div>
            <div class="value">₹<?= number_format($total_b2cl, 0) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">B2C Small</div>
            <div class="value">₹<?= number_format($total_b2cs, 0) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Total Outward Supplies</div>
            <div class="value">₹<?= number_format($total_b2b + $total_b2cl + $total_b2cs, 0) ?></div>
        </div>
    </div>

    <!-- B2B Section -->
    <div class="section-card">
        <h3><span class="section-badge">4A</span> B2B Invoices</h3>
        <p class="section-sub">Supplies to registered dealers (with GSTIN)</p>

        <?php if (empty($b2b_data)): ?>
            <div class="empty-section">No B2B transactions for this period</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>GSTIN</th>
                        <th style="text-align: center;">Invoices</th>
                        <th style="text-align: right;">Taxable Value</th>
                        <th style="text-align: right;">CGST</th>
                        <th style="text-align: right;">SGST</th>
                        <th style="text-align: right;">IGST</th>
                        <th style="text-align: right;">Cess</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b2b_data as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['party_gstin']) ?></strong></td>
                            <td style="text-align: center;"><?= $row['invoice_count'] ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['taxable_value'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['cgst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['sgst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['igst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['cess'] ?? 0, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: 600; background: #f8f9fa;">
                        <td>Total</td>
                        <td style="text-align: center;"><?= array_sum(array_column($b2b_data, 'invoice_count')) ?></td>
                        <td style="text-align: right;">₹<?= number_format($total_b2b, 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format(array_sum(array_column($b2b_data, 'cgst')), 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format(array_sum(array_column($b2b_data, 'sgst')), 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format(array_sum(array_column($b2b_data, 'igst')), 2) ?></td>
                        <td style="text-align: right;">₹<?= number_format(array_sum(array_column($b2b_data, 'cess')), 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <!-- B2C Large Section -->
    <div class="section-card">
        <h3><span class="section-badge">5A</span> B2C Large</h3>
        <p class="section-sub">Inter-state supplies to unregistered dealers (> ₹2.5 Lakhs)</p>

        <?php if (empty($b2cl_data)): ?>
            <div class="empty-section">No B2C Large transactions for this period</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Place of Supply</th>
                        <th style="text-align: right;">Taxable Value</th>
                        <th style="text-align: right;">IGST</th>
                        <th style="text-align: right;">Cess</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b2cl_data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['place_of_supply'] ?: '-') ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['taxable_value'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['igst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['cess'] ?? 0, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- B2C Small Section -->
    <div class="section-card">
        <h3><span class="section-badge">7</span> B2C Small</h3>
        <p class="section-sub">Other supplies to unregistered dealers</p>

        <?php if (empty($b2cs_data)): ?>
            <div class="empty-section">No B2C Small transactions for this period</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rate (%)</th>
                        <th style="text-align: right;">Taxable Value</th>
                        <th style="text-align: right;">CGST</th>
                        <th style="text-align: right;">SGST</th>
                        <th style="text-align: right;">IGST</th>
                        <th style="text-align: right;">Cess</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b2cs_data as $row): ?>
                        <tr>
                            <td><?= $row['gst_rate'] ?>%</td>
                            <td style="text-align: right;">₹<?= number_format($row['taxable_value'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['cgst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['sgst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['igst'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['cess'] ?? 0, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- HSN Summary -->
    <div class="section-card">
        <h3><span class="section-badge">12</span> HSN Summary</h3>
        <p class="section-sub">HSN-wise summary of outward supplies</p>

        <?php if (empty($hsn_data)): ?>
            <div class="empty-section">No HSN data available for this period</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>HSN Code</th>
                        <th>Description</th>
                        <th style="text-align: right;">Quantity</th>
                        <th style="text-align: right;">Taxable Value</th>
                        <th style="text-align: right;">Total Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hsn_data as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['hsn_code']) ?></strong></td>
                            <td><?= htmlspecialchars($row['hsn_desc'] ?: '-') ?></td>
                            <td style="text-align: right;"><?= number_format($row['total_qty'] ?? 0, 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['taxable_value'], 2) ?></td>
                            <td style="text-align: right;">₹<?= number_format($row['total_tax'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
