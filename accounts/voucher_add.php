<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];
$type_id = isset($_GET['type']) ? (int)$_GET['type'] : 4; // Default to Journal

// Get voucher type info
try {
    $type_stmt = $pdo->prepare("SELECT * FROM acc_voucher_types WHERE id = ?");
    $type_stmt->execute([$type_id]);
    $voucher_type = $type_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voucher_type) {
        header("Location: vouchers.php");
        exit;
    }
} catch (Exception $e) {
    header("Location: vouchers.php");
    exit;
}

// Get all ledgers
try {
    $ledgers = $pdo->query("
        SELECT l.id, l.ledger_code, l.ledger_name, g.group_name, g.group_type, l.is_bank_account, l.is_cash_account
        FROM acc_ledgers l
        INNER JOIN acc_account_groups g ON l.group_id = g.id
        WHERE l.is_active = 1
        ORDER BY g.group_type, l.ledger_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ledgers = [];
}

// Get bank/cash ledgers for payment/receipt
try {
    $bank_cash_ledgers = $pdo->query("
        SELECT l.id, l.ledger_name, l.is_bank_account, l.is_cash_account, l.current_balance, l.balance_type
        FROM acc_ledgers l
        WHERE l.is_active = 1 AND (l.is_bank_account = 1 OR l.is_cash_account = 1)
        ORDER BY l.ledger_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bank_cash_ledgers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucher_date = trim($_POST['voucher_date'] ?? date('Y-m-d'));
    $reference_no = trim($_POST['reference_no'] ?? '');
    $reference_date = trim($_POST['reference_date'] ?? '');
    $narration = trim($_POST['narration'] ?? '');
    $party_ledger_id = !empty($_POST['party_ledger_id']) ? (int)$_POST['party_ledger_id'] : null;

    // Get entries
    $entry_ledger_ids = $_POST['entry_ledger_id'] ?? [];
    $entry_debits = $_POST['entry_debit'] ?? [];
    $entry_credits = $_POST['entry_credit'] ?? [];
    $entry_narrations = $_POST['entry_narration'] ?? [];

    // Validation
    if (empty($voucher_date)) $errors[] = "Voucher date is required";

    // Calculate totals and validate double entry
    $total_debit = 0;
    $total_credit = 0;
    $valid_entries = [];

    foreach ($entry_ledger_ids as $idx => $ledger_id) {
        if (!empty($ledger_id)) {
            $debit = (float)($entry_debits[$idx] ?? 0);
            $credit = (float)($entry_credits[$idx] ?? 0);

            if ($debit > 0 || $credit > 0) {
                $valid_entries[] = [
                    'ledger_id' => (int)$ledger_id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'narration' => $entry_narrations[$idx] ?? ''
                ];
                $total_debit += $debit;
                $total_credit += $credit;
            }
        }
    }

    if (empty($valid_entries)) {
        $errors[] = "At least one entry is required";
    }

    if (round($total_debit, 2) !== round($total_credit, 2)) {
        $errors[] = "Debit and Credit totals must be equal. Debit: " . number_format($total_debit, 2) . ", Credit: " . number_format($total_credit, 2);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate voucher number
            $prefix = $voucher_type['numbering_prefix'] ?: $voucher_type['type_code'] . '/';
            $year = date('Y', strtotime($voucher_date));
            $month = date('m', strtotime($voucher_date));

            $max_stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(voucher_no, '/', -1) AS UNSIGNED))
                FROM acc_vouchers
                WHERE voucher_type_id = ? AND voucher_no LIKE ?
            ");
            $max_stmt->execute([$type_id, $prefix . $year . $month . '%']);
            $max_no = $max_stmt->fetchColumn() ?: 0;

            $voucher_no = $prefix . $year . $month . '/' . str_pad($max_no + 1, 4, '0', STR_PAD_LEFT);

            // Insert voucher
            $stmt = $pdo->prepare("
                INSERT INTO acc_vouchers (
                    voucher_no, voucher_type_id, voucher_date, reference_no, reference_date,
                    narration, total_amount, party_ledger_id, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $voucher_no,
                $type_id,
                $voucher_date,
                $reference_no ?: null,
                $reference_date ?: null,
                $narration ?: null,
                $total_debit,
                $party_ledger_id,
                $_SESSION['user_id'] ?? null
            ]);
            $voucher_id = $pdo->lastInsertId();

            // Insert entries and update ledger balances
            $entry_stmt = $pdo->prepare("
                INSERT INTO acc_voucher_entries (voucher_id, ledger_id, debit_amount, credit_amount, narration)
                VALUES (?, ?, ?, ?, ?)
            ");

            $update_balance_stmt = $pdo->prepare("
                UPDATE acc_ledgers
                SET current_balance = current_balance + ?,
                    balance_type = CASE
                        WHEN current_balance + ? >= 0 THEN 'Debit'
                        ELSE 'Credit'
                    END
                WHERE id = ?
            ");

            foreach ($valid_entries as $entry) {
                $entry_stmt->execute([
                    $voucher_id,
                    $entry['ledger_id'],
                    $entry['debit'],
                    $entry['credit'],
                    $entry['narration'] ?: null
                ]);

                // Update ledger balance (Debit increases, Credit decreases for Debit-nature accounts)
                $balance_change = $entry['debit'] - $entry['credit'];
                $update_balance_stmt->execute([$balance_change, $balance_change, $entry['ledger_id']]);
            }

            $pdo->commit();
            setModal("Success", "Voucher '$voucher_no' created successfully.");
            header("Location: voucher_view.php?id=$voucher_id");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>New <?= htmlspecialchars($voucher_type['type_name']) ?> - Accounts</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .voucher-type-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-left: 10px;
        }
        .type-pmt { background: #ffebee; color: #c62828; }
        .type-rct { background: #e8f5e9; color: #2e7d32; }
        .type-cnt { background: #e3f2fd; color: #1565c0; }
        .type-jrn { background: #f3e5f5; color: #7b1fa2; }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-section {
            margin-bottom: 25px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            color: #667eea;
            font-size: 1.1em;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
        }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }

        .entries-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .entries-table th {
            background: #f8f9fa;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .entries-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .entries-table select, .entries-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .entries-table input[type="number"] {
            text-align: right;
        }

        .totals-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .totals-row td { padding: 12px 10px; }

        .balance-ok { color: #27ae60; }
        .balance-error { color: #e74c3c; }

        .remove-row {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .add-row-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .entries-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content">
    <div class="page-header">
        <div>
            <h1>
                New Voucher
                <span class="voucher-type-badge type-<?= strtolower($voucher_type['type_code']) ?>">
                    <?= htmlspecialchars($voucher_type['type_name']) ?>
                </span>
            </h1>
            <p style="color: #666; margin: 5px 0 0;">Double-entry voucher entry</p>
        </div>
        <a href="vouchers.php" class="btn btn-secondary">Back to Vouchers</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 10px 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="post" id="voucherForm">
            <!-- Header Details -->
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label>Voucher Date <span class="required">*</span></label>
                        <input type="date" name="voucher_date" value="<?= htmlspecialchars($_POST['voucher_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Reference No</label>
                        <input type="text" name="reference_no" value="<?= htmlspecialchars($_POST['reference_no'] ?? '') ?>" placeholder="Cheque No / Bill No">
                    </div>
                    <div class="form-group">
                        <label>Reference Date</label>
                        <input type="date" name="reference_date" value="<?= htmlspecialchars($_POST['reference_date'] ?? '') ?>">
                    </div>
                    <?php if (in_array($type_id, [1, 2, 5, 6])): // Payment, Receipt, Sales, Purchase ?>
                    <div class="form-group">
                        <label>Party Account</label>
                        <select name="party_ledger_id">
                            <option value="">-- Select Party --</option>
                            <?php foreach ($ledgers as $l): ?>
                                <?php if (strpos(strtolower($l['group_name']), 'debtor') !== false || strpos(strtolower($l['group_name']), 'creditor') !== false): ?>
                                    <option value="<?= $l['id'] ?>" <?= ($_POST['party_ledger_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['ledger_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Entry Lines -->
            <div class="form-section">
                <h3>Accounting Entries</h3>
                <table class="entries-table" id="entriesTable">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Account (Ledger)</th>
                            <th style="width: 20%;">Debit (Rs.)</th>
                            <th style="width: 20%;">Credit (Rs.)</th>
                            <th style="width: 15%;">Particulars</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="entriesBody">
                        <tr>
                            <td>
                                <select name="entry_ledger_id[]" class="ledger-select" onchange="updateTotals()">
                                    <option value="">-- Select Ledger --</option>
                                    <?php
                                    $currentGroup = '';
                                    foreach ($ledgers as $l):
                                        $groupLabel = $l['group_type'] . ' > ' . $l['group_name'];
                                        if ($groupLabel !== $currentGroup):
                                            if ($currentGroup) echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
                                            $currentGroup = $groupLabel;
                                        endif;
                                    ?>
                                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['ledger_name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($currentGroup) echo '</optgroup>'; ?>
                                </select>
                            </td>
                            <td><input type="number" name="entry_debit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
                            <td><input type="number" name="entry_credit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
                            <td><input type="text" name="entry_narration[]" placeholder="Details"></td>
                            <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
                        </tr>
                        <tr>
                            <td>
                                <select name="entry_ledger_id[]" class="ledger-select" onchange="updateTotals()">
                                    <option value="">-- Select Ledger --</option>
                                    <?php
                                    $currentGroup = '';
                                    foreach ($ledgers as $l):
                                        $groupLabel = $l['group_type'] . ' > ' . $l['group_name'];
                                        if ($groupLabel !== $currentGroup):
                                            if ($currentGroup) echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
                                            $currentGroup = $groupLabel;
                                        endif;
                                    ?>
                                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['ledger_name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($currentGroup) echo '</optgroup>'; ?>
                                </select>
                            </td>
                            <td><input type="number" name="entry_debit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
                            <td><input type="number" name="entry_credit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
                            <td><input type="text" name="entry_narration[]" placeholder="Details"></td>
                            <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row">
                            <td style="text-align: right;"><strong>Total:</strong></td>
                            <td style="text-align: right;"><span id="totalDebit">0.00</span></td>
                            <td style="text-align: right;"><span id="totalCredit">0.00</span></td>
                            <td colspan="2">
                                <span id="balanceStatus" class="balance-ok">Balanced</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <button type="button" class="add-row-btn" onclick="addRow()">+ Add Entry Line</button>
            </div>

            <!-- Narration -->
            <div class="form-section">
                <div class="form-group">
                    <label>Narration / Remarks</label>
                    <textarea name="narration" rows="2" placeholder="Transaction description..."><?= htmlspecialchars($_POST['narration'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">Save Voucher</button>
                <a href="vouchers.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const ledgerOptions = `<?php
$currentGroup = '';
$options = '<option value="">-- Select Ledger --</option>';
foreach ($ledgers as $l):
    $groupLabel = $l['group_type'] . ' > ' . $l['group_name'];
    if ($groupLabel !== $currentGroup):
        if ($currentGroup) $options .= '</optgroup>';
        $options .= '<optgroup label="' . htmlspecialchars($groupLabel) . '">';
        $currentGroup = $groupLabel;
    endif;
    $options .= '<option value="' . $l['id'] . '">' . htmlspecialchars($l['ledger_name']) . '</option>';
endforeach;
if ($currentGroup) $options .= '</optgroup>';
echo addslashes($options);
?>`;

function addRow() {
    const tbody = document.getElementById('entriesBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><select name="entry_ledger_id[]" class="ledger-select" onchange="updateTotals()">${ledgerOptions}</select></td>
        <td><input type="number" name="entry_debit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
        <td><input type="number" name="entry_credit[]" step="0.01" min="0" value="0" onchange="updateTotals()" onkeyup="updateTotals()"></td>
        <td><input type="text" name="entry_narration[]" placeholder="Details"></td>
        <td><button type="button" class="remove-row" onclick="removeRow(this)">X</button></td>
    `;
    tbody.appendChild(newRow);
}

function removeRow(btn) {
    const tbody = document.getElementById('entriesBody');
    if (tbody.rows.length > 2) {
        btn.closest('tr').remove();
        updateTotals();
    }
}

function updateTotals() {
    let totalDebit = 0;
    let totalCredit = 0;

    document.querySelectorAll('input[name="entry_debit[]"]').forEach(input => {
        totalDebit += parseFloat(input.value) || 0;
    });

    document.querySelectorAll('input[name="entry_credit[]"]').forEach(input => {
        totalCredit += parseFloat(input.value) || 0;
    });

    document.getElementById('totalDebit').textContent = totalDebit.toFixed(2);
    document.getElementById('totalCredit').textContent = totalCredit.toFixed(2);

    const statusEl = document.getElementById('balanceStatus');
    const submitBtn = document.getElementById('submitBtn');

    if (Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0) {
        statusEl.textContent = 'Balanced ✓';
        statusEl.className = 'balance-ok';
        submitBtn.disabled = false;
    } else if (totalDebit === 0 && totalCredit === 0) {
        statusEl.textContent = 'Enter amounts';
        statusEl.className = 'balance-error';
        submitBtn.disabled = true;
    } else {
        const diff = Math.abs(totalDebit - totalCredit).toFixed(2);
        statusEl.textContent = 'Difference: Rs. ' + diff;
        statusEl.className = 'balance-error';
        submitBtn.disabled = true;
    }
}

// Initialize
updateTotals();
</script>

</body>
</html>
