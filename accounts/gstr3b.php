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

// Section 3.1 - Outward Supplies
try {
    // 3.1(a) Outward taxable supplies (other than zero rated, nil rated and exempted)
    $taxable_stmt = $pdo->prepare("
        SELECT
            SUM(taxable_amount) as taxable_value,
            SUM(cgst_amount + sgst_amount) as intra_tax,
            SUM(igst_amount) as igst
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND gst_rate > 0
    ");
    $taxable_stmt->execute([$from_date, $to_date]);
    $taxable_supplies = $taxable_stmt->fetch(PDO::FETCH_ASSOC);

    // Zero rated, nil rated, exempted
    $zero_stmt = $pdo->prepare("
        SELECT SUM(taxable_amount) as value
        FROM acc_gst_transactions
        WHERE transaction_type = 'Output'
        AND transaction_date BETWEEN ? AND ?
        AND gst_rate = 0
    ");
    $zero_stmt->execute([$from_date, $to_date]);
    $zero_supplies = $zero_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $taxable_supplies = ['taxable_value' => 0, 'intra_tax' => 0, 'igst' => 0];
    $zero_supplies = ['value' => 0];
}

// Section 4 - ITC Available
try {
    $itc_stmt = $pdo->prepare("
        SELECT
            SUM(cgst_amount) as cgst,
            SUM(sgst_amount) as sgst,
            SUM(igst_amount) as igst,
            SUM(cess_amount) as cess
        FROM acc_gst_transactions
        WHERE transaction_type = 'Input'
        AND transaction_date BETWEEN ? AND ?
    ");
    $itc_stmt->execute([$from_date, $to_date]);
    $itc_available = $itc_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $itc_available = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0];
}

// Calculate totals
$output_cgst = ($taxable_supplies['intra_tax'] ?? 0) / 2;
$output_sgst = ($taxable_supplies['intra_tax'] ?? 0) / 2;
$output_igst = $taxable_supplies['igst'] ?? 0;

$input_cgst = $itc_available['cgst'] ?? 0;
$input_sgst = $itc_available['sgst'] ?? 0;
$input_igst = $itc_available['igst'] ?? 0;

// Net liability
$net_cgst = max(0, $output_cgst - $input_cgst);
$net_sgst = max(0, $output_sgst - $input_sgst);
$net_igst = max(0, $output_igst - $input_igst);
$total_liability = $net_cgst + $net_sgst + $net_igst;

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>GSTR-3B - <?= date('F Y', strtotime($from_date)) ?></title>
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

        .action-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

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
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .section-badge {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .gstr-table {
            width: 100%;
            border-collapse: collapse;
        }
        .gstr-table th, .gstr-table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #e0e0e0;
        }
        .gstr-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .gstr-table tr.sub-row td { padding-left: 30px; background: #fafafa; }
        .gstr-table tr.total-row { background: #e8eaf6; font-weight: 600; }
        .gstr-table .text-right { text-align: right; }
        .gstr-table .nature-col { width: 50%; }

        .liability-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .liability-section h3 {
            margin: 0 0 20px 0;
            color: white;
        }
        .liability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .liability-item {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .liability-item .label { font-size: 0.9em; opacity: 0.9; }
        .liability-item .value { font-size: 1.5em; font-weight: 700; margin-top: 5px; }

        .note-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
            color: #856404;
        }

        body.dark .section-card { background: #2c3e50; }
        body.dark .section-card h3 { color: #ecf0f1; }
        body.dark .gstr-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>GSTR-3B Return</h1>
            <p style="color: #666; margin: 5px 0 0;">Monthly Summary Return</p>
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
        <a href="gstr3b_export.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary">Export JSON</a>
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
    </div>

    <!-- Tax Liability Summary -->
    <div class="liability-section">
        <h3>Tax Liability Summary</h3>
        <div class="liability-grid">
            <div class="liability-item">
                <div class="label">CGST Payable</div>
                <div class="value">₹<?= number_format($net_cgst, 0) ?></div>
            </div>
            <div class="liability-item">
                <div class="label">SGST Payable</div>
                <div class="value">₹<?= number_format($net_sgst, 0) ?></div>
            </div>
            <div class="liability-item">
                <div class="label">IGST Payable</div>
                <div class="value">₹<?= number_format($net_igst, 0) ?></div>
            </div>
            <div class="liability-item" style="background: rgba(255,255,255,0.25);">
                <div class="label">Total Payable</div>
                <div class="value">₹<?= number_format($total_liability, 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Section 3.1 - Outward Supplies -->
    <div class="section-card">
        <h3><span class="section-badge">3.1</span> Tax on Outward and Reverse Charge Inward Supplies</h3>

        <table class="gstr-table">
            <thead>
                <tr>
                    <th class="nature-col">Nature of Supplies</th>
                    <th class="text-right">Total Taxable Value (₹)</th>
                    <th class="text-right">Integrated Tax (₹)</th>
                    <th class="text-right">Central Tax (₹)</th>
                    <th class="text-right">State/UT Tax (₹)</th>
                    <th class="text-right">Cess (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(a) Outward taxable supplies (other than zero rated, nil rated and exempted)</td>
                    <td class="text-right"><?= number_format($taxable_supplies['taxable_value'] ?? 0, 2) ?></td>
                    <td class="text-right"><?= number_format($output_igst, 2) ?></td>
                    <td class="text-right"><?= number_format($output_cgst, 2) ?></td>
                    <td class="text-right"><?= number_format($output_sgst, 2) ?></td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>(b) Outward taxable supplies (zero rated)</td>
                    <td class="text-right"><?= number_format($zero_supplies['value'] ?? 0, 2) ?></td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>(c) Other outward supplies (Nil rated, exempted)</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>(d) Inward supplies (liable to reverse charge)</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>(e) Non-GST outward supplies</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Section 4 - ITC -->
    <div class="section-card">
        <h3><span class="section-badge">4</span> Eligible ITC</h3>

        <table class="gstr-table">
            <thead>
                <tr>
                    <th class="nature-col">Details</th>
                    <th class="text-right">Integrated Tax (₹)</th>
                    <th class="text-right">Central Tax (₹)</th>
                    <th class="text-right">State/UT Tax (₹)</th>
                    <th class="text-right">Cess (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(A) ITC Available (whether in full or part)</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
                <tr class="sub-row">
                    <td>(1) Import of goods</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="sub-row">
                    <td>(2) Import of services</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="sub-row">
                    <td>(3) Inward supplies liable to reverse charge</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="sub-row">
                    <td>(4) Inward supplies from ISD</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="sub-row">
                    <td>(5) All other ITC</td>
                    <td class="text-right"><?= number_format($input_igst, 2) ?></td>
                    <td class="text-right"><?= number_format($input_cgst, 2) ?></td>
                    <td class="text-right"><?= number_format($input_sgst, 2) ?></td>
                    <td class="text-right"><?= number_format($itc_available['cess'] ?? 0, 2) ?></td>
                </tr>
                <tr class="total-row">
                    <td>(B) ITC Reversed</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="total-row">
                    <td>(C) Net ITC Available (A) - (B)</td>
                    <td class="text-right"><?= number_format($input_igst, 2) ?></td>
                    <td class="text-right"><?= number_format($input_cgst, 2) ?></td>
                    <td class="text-right"><?= number_format($input_sgst, 2) ?></td>
                    <td class="text-right"><?= number_format($itc_available['cess'] ?? 0, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Section 5 - Interest & Late Fee -->
    <div class="section-card">
        <h3><span class="section-badge">5</span> Values of Exempt, Nil-Rated and Non-GST Inward Supplies</h3>

        <table class="gstr-table">
            <thead>
                <tr>
                    <th class="nature-col">Nature of Supplies</th>
                    <th class="text-right">Inter-State (₹)</th>
                    <th class="text-right">Intra-State (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>From a supplier under composition scheme, Exempt and Nil rated supply</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>Non GST Supply</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Section 6 - Payment of Tax -->
    <div class="section-card">
        <h3><span class="section-badge">6</span> Payment of Tax</h3>

        <table class="gstr-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Tax Payable (₹)</th>
                    <th class="text-right">Paid through ITC</th>
                    <th class="text-right">Paid in Cash</th>
                    <th class="text-right">Interest</th>
                    <th class="text-right">Late Fee</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Integrated Tax</td>
                    <td class="text-right"><?= number_format($output_igst, 2) ?></td>
                    <td class="text-right"><?= number_format(min($output_igst, $input_igst), 2) ?></td>
                    <td class="text-right"><?= number_format($net_igst, 2) ?></td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>Central Tax</td>
                    <td class="text-right"><?= number_format($output_cgst, 2) ?></td>
                    <td class="text-right"><?= number_format(min($output_cgst, $input_cgst), 2) ?></td>
                    <td class="text-right"><?= number_format($net_cgst, 2) ?></td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>State/UT Tax</td>
                    <td class="text-right"><?= number_format($output_sgst, 2) ?></td>
                    <td class="text-right"><?= number_format(min($output_sgst, $input_sgst), 2) ?></td>
                    <td class="text-right"><?= number_format($net_sgst, 2) ?></td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr>
                    <td>Cess</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                    <td class="text-right">0.00</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="note-box">
        <strong>Note:</strong> This is an auto-generated GSTR-3B summary based on recorded transactions.
        Please verify all figures before filing on the GST portal. Late fee and interest calculations
        are indicative and should be verified with current rules.
    </div>
</div>

</body>
</html>
