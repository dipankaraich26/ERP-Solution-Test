<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: vouchers.php");
    exit;
}

// Get voucher details
try {
    $stmt = $pdo->prepare("
        SELECT v.*, vt.name as voucher_type_name, vt.prefix,
               u.username as created_by_name
        FROM acc_vouchers v
        LEFT JOIN acc_voucher_types vt ON v.voucher_type_id = vt.id
        LEFT JOIN users u ON v.created_by = u.id
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        header("Location: vouchers.php");
        exit;
    }

    // Get voucher entries
    $entries_stmt = $pdo->prepare("
        SELECT ve.*, l.name as ledger_name, l.code as ledger_code,
               ag.name as group_name
        FROM acc_voucher_entries ve
        LEFT JOIN acc_ledgers l ON ve.ledger_id = l.id
        LEFT JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ve.voucher_id = ?
        ORDER BY ve.id
    ");
    $entries_stmt->execute([$id]);
    $entries = $entries_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error loading voucher: " . $e->getMessage();
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Voucher - <?= htmlspecialchars($voucher['voucher_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .voucher-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .voucher-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .voucher-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .voucher-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-item label {
            display: block;
            color: #666;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        .info-item .value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        .voucher-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }
        .type-payment { background: #ffebee; color: #c62828; }
        .type-receipt { background: #e8f5e9; color: #2e7d32; }
        .type-contra { background: #e3f2fd; color: #1565c0; }
        .type-journal { background: #fff3e0; color: #ef6c00; }
        .type-sales { background: #f3e5f5; color: #7b1fa2; }
        .type-purchase { background: #e0f2f1; color: #00695c; }

        .entries-table {
            width: 100%;
            border-collapse: collapse;
        }
        .entries-table th, .entries-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .entries-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .entries-table .debit { color: #c62828; }
        .entries-table .credit { color: #2e7d32; }
        .entries-table tfoot td {
            font-weight: 600;
            background: #f8f9fa;
        }
        .ledger-code {
            color: #666;
            font-size: 0.85em;
        }
        .narration-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .narration-section label {
            font-weight: 600;
            color: #666;
        }
        .narration-text {
            margin-top: 8px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #2c3e50;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-posted { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        body.dark .voucher-card { background: #2c3e50; }
        body.dark .info-item .value { color: #ecf0f1; }
        body.dark .entries-table th { background: #34495e; color: #ecf0f1; }
        body.dark .narration-text { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="voucher-container">
        <div class="voucher-header">
            <div>
                <h1 style="margin: 0 0 10px 0; color: #2c3e50;">
                    <?= htmlspecialchars($voucher['voucher_no']) ?>
                </h1>
                <span class="voucher-type-badge type-<?= strtolower($voucher['voucher_type_name']) ?>">
                    <?= htmlspecialchars($voucher['voucher_type_name']) ?> Voucher
                </span>
                <span class="status-badge status-<?= strtolower($voucher['status']) ?>" style="margin-left: 10px;">
                    <?= $voucher['status'] ?>
                </span>
            </div>
            <div class="action-buttons">
                <a href="vouchers.php" class="btn btn-secondary">Back to List</a>
                <?php if ($voucher['status'] === 'Draft'): ?>
                    <a href="voucher_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
                <?php endif; ?>
                <a href="voucher_print.php?id=<?= $id ?>" class="btn btn-secondary" target="_blank">Print</a>
            </div>
        </div>

        <div class="voucher-card">
            <div class="voucher-info">
                <div class="info-item">
                    <label>Voucher Date</label>
                    <div class="value"><?= date('d M Y', strtotime($voucher['voucher_date'])) ?></div>
                </div>
                <div class="info-item">
                    <label>Reference Number</label>
                    <div class="value"><?= htmlspecialchars($voucher['reference_no'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Created By</label>
                    <div class="value"><?= htmlspecialchars($voucher['created_by_name'] ?: 'System') ?></div>
                </div>
                <div class="info-item">
                    <label>Created At</label>
                    <div class="value"><?= date('d M Y H:i', strtotime($voucher['created_at'])) ?></div>
                </div>
            </div>

            <h3 style="margin: 0 0 15px 0; color: #2c3e50;">Voucher Entries</h3>

            <table class="entries-table">
                <thead>
                    <tr>
                        <th>Ledger</th>
                        <th>Account Group</th>
                        <th style="text-align: right;">Debit (₹)</th>
                        <th style="text-align: right;">Credit (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_debit = 0;
                    $total_credit = 0;
                    foreach ($entries as $entry):
                        $total_debit += $entry['debit_amount'];
                        $total_credit += $entry['credit_amount'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($entry['ledger_name']) ?></strong>
                                <?php if ($entry['ledger_code']): ?>
                                    <div class="ledger-code"><?= htmlspecialchars($entry['ledger_code']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($entry['group_name'] ?: '-') ?></td>
                            <td style="text-align: right;" class="debit">
                                <?= $entry['debit_amount'] > 0 ? number_format($entry['debit_amount'], 2) : '-' ?>
                            </td>
                            <td style="text-align: right;" class="credit">
                                <?= $entry['credit_amount'] > 0 ? number_format($entry['credit_amount'], 2) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align: right;">Total:</td>
                        <td style="text-align: right;" class="debit"><?= number_format($total_debit, 2) ?></td>
                        <td style="text-align: right;" class="credit"><?= number_format($total_credit, 2) ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($voucher['narration']): ?>
                <div class="narration-section">
                    <label>Narration</label>
                    <div class="narration-text"><?= nl2br(htmlspecialchars($voucher['narration'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Amount in Words -->
        <div class="voucher-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <label style="color: #666; font-size: 0.9em;">Amount in Words</label>
                    <div style="font-size: 1.1em; font-weight: 600; color: #2c3e50; margin-top: 5px;">
                        <?= convertToWords($total_debit) ?> Only
                    </div>
                </div>
                <div style="text-align: right;">
                    <label style="color: #666; font-size: 0.9em;">Total Amount</label>
                    <div style="font-size: 1.8em; font-weight: 700; color: #667eea;">
                        ₹<?= number_format($total_debit, 2) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Convert number to words function
function convertToWords($number) {
    $ones = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen'
    );
    $tens = array(
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
    );

    $number = round($number);

    if ($number == 0) return 'Zero';

    $words = '';

    if ($number >= 10000000) {
        $words .= convertToWords(floor($number / 10000000)) . ' Crore ';
        $number %= 10000000;
    }

    if ($number >= 100000) {
        $words .= convertToWords(floor($number / 100000)) . ' Lakh ';
        $number %= 100000;
    }

    if ($number >= 1000) {
        $words .= convertToWords(floor($number / 1000)) . ' Thousand ';
        $number %= 1000;
    }

    if ($number >= 100) {
        $words .= $ones[floor($number / 100)] . ' Hundred ';
        $number %= 100;
    }

    if ($number >= 20) {
        $words .= $tens[floor($number / 10)] . ' ';
        $number %= 10;
    }

    if ($number > 0) {
        $words .= $ones[$number] . ' ';
    }

    return trim($words) . ' Rupees';
}
?>

</body>
</html>
