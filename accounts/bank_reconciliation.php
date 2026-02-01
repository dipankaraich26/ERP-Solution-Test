<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$success = '';
$error = '';

// Get bank accounts
try {
    $banks_stmt = $pdo->query("
        SELECT ba.*, l.name as ledger_name, l.current_balance
        FROM acc_bank_accounts ba
        LEFT JOIN acc_ledgers l ON ba.ledger_id = l.id
        WHERE ba.is_active = 1
        ORDER BY l.name
    ");
    $bank_accounts = $banks_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bank_accounts = [];
}

// Selected bank and date range
$selected_bank = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$transactions = [];
$bank_balance = 0;
$book_balance = 0;

if ($selected_bank) {
    try {
        // Get bank account details
        $bank_stmt = $pdo->prepare("
            SELECT ba.*, l.name as ledger_name, l.current_balance, l.id as ledger_id
            FROM acc_bank_accounts ba
            LEFT JOIN acc_ledgers l ON ba.ledger_id = l.id
            WHERE ba.id = ?
        ");
        $bank_stmt->execute([$selected_bank]);
        $bank_info = $bank_stmt->fetch(PDO::FETCH_ASSOC);
        $book_balance = $bank_info['current_balance'] ?? 0;

        // Get transactions from voucher entries
        $trans_stmt = $pdo->prepare("
            SELECT ve.*, v.voucher_no, v.voucher_date, v.narration, v.reference_no,
                   vt.name as voucher_type,
                   br.is_reconciled, br.reconciled_date, br.bank_date, br.id as recon_id
            FROM acc_voucher_entries ve
            JOIN acc_vouchers v ON ve.voucher_id = v.id
            JOIN acc_voucher_types vt ON v.voucher_type_id = vt.id
            LEFT JOIN acc_bank_reconciliation br ON ve.id = br.voucher_entry_id
            WHERE ve.ledger_id = ?
            AND v.voucher_date BETWEEN ? AND ?
            AND v.status = 'Posted'
            ORDER BY v.voucher_date, v.id
        ");
        $trans_stmt->execute([$bank_info['ledger_id'], $from_date, $to_date]);
        $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get latest bank statement balance (if exists)
        $last_recon = $pdo->prepare("
            SELECT closing_balance FROM acc_bank_reconciliation
            WHERE bank_account_id = ?
            ORDER BY reconciled_date DESC LIMIT 1
        ");
        $last_recon->execute([$selected_bank]);
        $last_balance = $last_recon->fetch(PDO::FETCH_ASSOC);
        $bank_balance = $last_balance ? $last_balance['closing_balance'] : 0;

    } catch (Exception $e) {
        $error = "Error loading transactions: " . $e->getMessage();
    }
}

// Handle reconciliation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reconcile'])) {
    try {
        $pdo->beginTransaction();

        $entry_ids = $_POST['entry_ids'] ?? [];
        $bank_dates = $_POST['bank_dates'] ?? [];
        $bank_account_id = $_POST['bank_account_id'];
        $closing_balance = $_POST['closing_balance'] ?? 0;

        foreach ($entry_ids as $entry_id) {
            $bank_date = $bank_dates[$entry_id] ?? date('Y-m-d');

            // Check if already reconciled
            $check = $pdo->prepare("SELECT id FROM acc_bank_reconciliation WHERE voucher_entry_id = ?");
            $check->execute([$entry_id]);

            if ($check->fetch()) {
                // Update
                $update = $pdo->prepare("
                    UPDATE acc_bank_reconciliation
                    SET is_reconciled = 1, reconciled_date = NOW(), bank_date = ?,
                        closing_balance = ?, reconciled_by = ?
                    WHERE voucher_entry_id = ?
                ");
                $update->execute([$bank_date, $closing_balance, $_SESSION['user_id'], $entry_id]);
            } else {
                // Insert
                $insert = $pdo->prepare("
                    INSERT INTO acc_bank_reconciliation
                    (bank_account_id, voucher_entry_id, bank_date, is_reconciled, reconciled_date, closing_balance, reconciled_by)
                    VALUES (?, ?, ?, 1, NOW(), ?, ?)
                ");
                $insert->execute([$bank_account_id, $entry_id, $bank_date, $closing_balance, $_SESSION['user_id']]);
            }
        }

        $pdo->commit();
        $success = count($entry_ids) . " transaction(s) reconciled successfully.";

        // Refresh page
        header("Location: bank_reconciliation.php?bank_id=$selected_bank&from_date=$from_date&to_date=$to_date&success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error reconciling: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $success = "Transactions reconciled successfully.";
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bank Reconciliation - Accounts</title>
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

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .filter-group select, .filter-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            min-width: 150px;
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
        .summary-card .value {
            font-size: 1.5em;
            font-weight: 700;
            margin-top: 5px;
        }
        .summary-card.book .value { color: #667eea; }
        .summary-card.bank .value { color: #27ae60; }
        .summary-card.diff .value { color: #e74c3c; }
        .summary-card.diff.balanced .value { color: #27ae60; }

        .recon-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .recon-table th, .recon-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .recon-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .recon-table tr:hover { background: #f8f9fa; }
        .recon-table tr.reconciled { background: #e8f5e9; }

        .debit { color: #c62828; }
        .credit { color: #2e7d32; }

        .status-reconciled {
            background: #d4edda;
            color: #155724;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.85em;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.85em;
        }

        .recon-actions {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        body.dark .filter-card, body.dark .summary-card, body.dark .recon-table { background: #2c3e50; }
        body.dark .recon-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Bank Reconciliation</h1>
            <p style="color: #666; margin: 5px 0 0;">Match bank statements with book entries</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-card">
        <form method="get" class="filter-row">
            <div class="filter-group">
                <label>Bank Account</label>
                <select name="bank_id" required>
                    <option value="">Select Bank Account</option>
                    <?php foreach ($bank_accounts as $bank): ?>
                        <option value="<?= $bank['id'] ?>" <?= $selected_bank == $bank['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bank['ledger_name']) ?> - <?= htmlspecialchars($bank['account_no']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="<?= $from_date ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="to_date" value="<?= $to_date ?>">
            </div>
            <button type="submit" class="btn btn-primary">Load Transactions</button>
        </form>
    </div>

    <?php if ($selected_bank && isset($bank_info)): ?>
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card book">
                <div class="label">Book Balance</div>
                <div class="value">₹<?= number_format($book_balance, 2) ?></div>
            </div>
            <div class="summary-card bank">
                <div class="label">Bank Statement Balance</div>
                <div class="value">₹<?= number_format($bank_balance, 2) ?></div>
            </div>
            <?php
            $unrecon_debit = 0;
            $unrecon_credit = 0;
            foreach ($transactions as $t) {
                if (!$t['is_reconciled']) {
                    $unrecon_debit += $t['debit_amount'];
                    $unrecon_credit += $t['credit_amount'];
                }
            }
            ?>
            <div class="summary-card">
                <div class="label">Unreconciled Debits</div>
                <div class="value" style="color: #c62828;">₹<?= number_format($unrecon_debit, 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Unreconciled Credits</div>
                <div class="value" style="color: #2e7d32;">₹<?= number_format($unrecon_credit, 2) ?></div>
            </div>
            <div class="summary-card diff <?= abs($book_balance - $bank_balance) < 0.01 ? 'balanced' : '' ?>">
                <div class="label">Difference</div>
                <div class="value">₹<?= number_format(abs($book_balance - $bank_balance), 2) ?></div>
            </div>
        </div>

        <!-- Transactions Table -->
        <form method="post">
            <input type="hidden" name="bank_account_id" value="<?= $selected_bank ?>">

            <table class="recon-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Reference</th>
                        <th>Narration</th>
                        <th style="text-align: right;">Debit</th>
                        <th style="text-align: right;">Credit</th>
                        <th>Bank Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                No transactions found for the selected period.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $trans): ?>
                            <tr class="<?= $trans['is_reconciled'] ? 'reconciled' : '' ?>">
                                <td>
                                    <?php if (!$trans['is_reconciled']): ?>
                                        <input type="checkbox" name="entry_ids[]" value="<?= $trans['id'] ?>" class="entry-checkbox">
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($trans['voucher_date'])) ?></td>
                                <td>
                                    <a href="voucher_view.php?id=<?= $trans['voucher_id'] ?>">
                                        <?= htmlspecialchars($trans['voucher_no']) ?>
                                    </a>
                                    <div style="font-size: 0.85em; color: #666;"><?= $trans['voucher_type'] ?></div>
                                </td>
                                <td><?= htmlspecialchars($trans['reference_no'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(substr($trans['narration'] ?: '-', 0, 50)) ?><?= strlen($trans['narration']) > 50 ? '...' : '' ?></td>
                                <td style="text-align: right;" class="debit">
                                    <?= $trans['debit_amount'] > 0 ? number_format($trans['debit_amount'], 2) : '-' ?>
                                </td>
                                <td style="text-align: right;" class="credit">
                                    <?= $trans['credit_amount'] > 0 ? number_format($trans['credit_amount'], 2) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($trans['is_reconciled']): ?>
                                        <?= date('d M Y', strtotime($trans['bank_date'])) ?>
                                    <?php else: ?>
                                        <input type="date" name="bank_dates[<?= $trans['id'] ?>]" value="<?= date('Y-m-d') ?>" style="padding: 5px;">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trans['is_reconciled']): ?>
                                        <span class="status-reconciled">Reconciled</span>
                                    <?php else: ?>
                                        <span class="status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($transactions)): ?>
                <div class="recon-actions">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-weight: 600;">Bank Statement Closing Balance:</label>
                        <input type="number" name="closing_balance" step="0.01" value="<?= $bank_balance ?>"
                               style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 150px;">
                    </div>
                    <button type="submit" name="reconcile" class="btn btn-primary">Reconcile Selected</button>
                </div>
            <?php endif; ?>
        </form>
    <?php elseif (!$selected_bank): ?>
        <div style="background: white; padding: 60px; text-align: center; border-radius: 10px; color: #666;">
            <h3>Select a Bank Account</h3>
            <p>Choose a bank account and date range to view transactions for reconciliation.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.entry-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
});
</script>

</body>
</html>
