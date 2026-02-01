<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$error = '';
$success = '';

// Get expense categories
try {
    $cat_stmt = $pdo->query("SELECT * FROM acc_expense_categories WHERE is_active = 1 ORDER BY name");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Get ledgers for payment (Bank & Cash accounts)
try {
    $ledger_stmt = $pdo->query("
        SELECT l.*, ag.name as group_name
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.name IN ('Bank Accounts', 'Cash-in-Hand')
        AND l.is_active = 1
        ORDER BY l.name
    ");
    $payment_ledgers = $ledger_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payment_ledgers = [];
}

// Get expense ledgers
try {
    $exp_ledger_stmt = $pdo->query("
        SELECT l.*, ag.name as group_name
        FROM acc_ledgers l
        JOIN acc_account_groups ag ON l.group_id = ag.id
        WHERE ag.parent_id IN (SELECT id FROM acc_account_groups WHERE name = 'Expenses (Direct)' OR name = 'Expenses (Indirect)')
           OR ag.name LIKE '%Expense%'
        AND l.is_active = 1
        ORDER BY l.name
    ");
    $expense_ledgers = $exp_ledger_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expense_ledgers = [];
}

// Get GST rates
try {
    $gst_stmt = $pdo->query("SELECT * FROM acc_gst_rates WHERE is_active = 1 ORDER BY rate");
    $gst_rates = $gst_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $gst_rates = [];
}

// Get TDS sections
try {
    $tds_stmt = $pdo->query("SELECT * FROM acc_tds_sections WHERE is_active = 1 ORDER BY section_code");
    $tds_sections = $tds_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tds_sections = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $expense_date = $_POST['expense_date'];
        $description = trim($_POST['description']);
        $category_id = $_POST['category_id'] ?: null;
        $paid_from_ledger_id = $_POST['paid_from_ledger_id'];
        $expense_ledger_id = $_POST['expense_ledger_id'] ?: null;
        $payment_mode = $_POST['payment_mode'];
        $reference_no = trim($_POST['reference_no']);
        $vendor_name = trim($_POST['vendor_name']);
        $vendor_gstin = trim($_POST['vendor_gstin']);
        $base_amount = (float)$_POST['base_amount'];
        $gst_rate_id = $_POST['gst_rate_id'] ?: null;
        $cgst_amount = (float)($_POST['cgst_amount'] ?? 0);
        $sgst_amount = (float)($_POST['sgst_amount'] ?? 0);
        $igst_amount = (float)($_POST['igst_amount'] ?? 0);
        $tds_section_id = $_POST['tds_section_id'] ?: null;
        $tds_amount = (float)($_POST['tds_amount'] ?? 0);
        $total_amount = (float)$_POST['total_amount'];
        $notes = trim($_POST['notes']);

        // Insert expense
        $stmt = $pdo->prepare("
            INSERT INTO acc_expenses (
                expense_date, description, category_id, paid_from_ledger_id, expense_ledger_id,
                payment_mode, reference_no, vendor_name, vendor_gstin,
                base_amount, gst_rate_id, cgst_amount, sgst_amount, igst_amount,
                tds_section_id, tds_amount, total_amount, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $expense_date, $description, $category_id, $paid_from_ledger_id, $expense_ledger_id,
            $payment_mode, $reference_no, $vendor_name, $vendor_gstin,
            $base_amount, $gst_rate_id, $cgst_amount, $sgst_amount, $igst_amount,
            $tds_section_id, $tds_amount, $total_amount, $notes, $_SESSION['user_id']
        ]);
        $expense_id = $pdo->lastInsertId();

        // Create voucher entry for the expense
        // Get voucher type for Payment
        $vt_stmt = $pdo->query("SELECT id FROM acc_voucher_types WHERE name = 'Payment' LIMIT 1");
        $voucher_type = $vt_stmt->fetch(PDO::FETCH_ASSOC);

        if ($voucher_type) {
            // Generate voucher number
            $num_stmt = $pdo->prepare("SELECT MAX(voucher_number) as max_num FROM acc_vouchers WHERE voucher_type_id = ?");
            $num_stmt->execute([$voucher_type['id']]);
            $max = $num_stmt->fetch(PDO::FETCH_ASSOC);
            $voucher_number = ($max['max_num'] ?? 0) + 1;
            $voucher_no = 'PMT-' . str_pad($voucher_number, 5, '0', STR_PAD_LEFT);

            // Insert voucher
            $v_stmt = $pdo->prepare("
                INSERT INTO acc_vouchers (voucher_type_id, voucher_no, voucher_number, voucher_date, reference_no, narration, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'Posted', ?)
            ");
            $v_stmt->execute([
                $voucher_type['id'],
                $voucher_no,
                $voucher_number,
                $expense_date,
                $reference_no,
                "Expense: $description",
                $_SESSION['user_id']
            ]);
            $voucher_id = $pdo->lastInsertId();

            // Update expense with voucher_id
            $pdo->prepare("UPDATE acc_expenses SET voucher_id = ? WHERE id = ?")->execute([$voucher_id, $expense_id]);

            // Debit expense ledger (if selected) or create general expense entry
            $debit_ledger = $expense_ledger_id ?: $paid_from_ledger_id; // Fallback
            $ve_stmt = $pdo->prepare("
                INSERT INTO acc_voucher_entries (voucher_id, ledger_id, debit_amount, credit_amount, narration)
                VALUES (?, ?, ?, 0, ?)
            ");
            $ve_stmt->execute([$voucher_id, $expense_ledger_id ?: $debit_ledger, $base_amount, $description]);

            // Credit payment ledger (Bank/Cash)
            $ve_stmt->execute([$voucher_id, $paid_from_ledger_id, 0, $total_amount, "Payment for: $description"]);

            // Update ledger balances
            // Debit expense (increase)
            if ($expense_ledger_id) {
                $pdo->prepare("UPDATE acc_ledgers SET current_balance = current_balance + ? WHERE id = ?")
                    ->execute([$base_amount, $expense_ledger_id]);
            }
            // Credit bank/cash (decrease)
            $pdo->prepare("UPDATE acc_ledgers SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$total_amount, $paid_from_ledger_id]);
        }

        $pdo->commit();
        header("Location: expenses.php?success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving expense: " . $e->getMessage();
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Expense - Accounts</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .form-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .amount-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .amount-row:last-child { border-bottom: none; }
        .amount-row.total {
            font-weight: 700;
            font-size: 1.2em;
            color: #667eea;
            padding-top: 15px;
            border-top: 2px solid #667eea;
            margin-top: 10px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        body.dark .form-card { background: #2c3e50; }
        body.dark .form-card h3 { color: #ecf0f1; }
        body.dark .amount-summary { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1 style="margin: 0; color: #2c3e50;">Add Expense</h1>
            <a href="expenses.php" class="btn btn-secondary">Back to Expenses</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="expenseForm">
            <!-- Basic Details -->
            <div class="form-card">
                <h3>Expense Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Expense Date *</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description *</label>
                        <input type="text" name="description" required placeholder="Brief description of expense">
                    </div>
                    <div class="form-group">
                        <label>Expense Ledger</label>
                        <select name="expense_ledger_id">
                            <option value="">Select Expense Ledger</option>
                            <?php foreach ($expense_ledgers as $led): ?>
                                <option value="<?= $led['id'] ?>"><?= htmlspecialchars($led['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Paid From *</label>
                        <select name="paid_from_ledger_id" required>
                            <option value="">Select Payment Account</option>
                            <?php foreach ($payment_ledgers as $led): ?>
                                <option value="<?= $led['id'] ?>"><?= htmlspecialchars($led['name']) ?> (<?= $led['group_name'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="payment_mode">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="UPI">UPI</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" name="reference_no" placeholder="Bill/Invoice number">
                    </div>
                </div>
            </div>

            <!-- Vendor Details -->
            <div class="form-card">
                <h3>Vendor Details (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vendor Name</label>
                        <input type="text" name="vendor_name" placeholder="Vendor/Supplier name">
                    </div>
                    <div class="form-group">
                        <label>Vendor GSTIN</label>
                        <input type="text" name="vendor_gstin" placeholder="15-digit GSTIN" maxlength="15" style="text-transform: uppercase;">
                    </div>
                </div>
            </div>

            <!-- Amount Details -->
            <div class="form-card">
                <h3>Amount Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Base Amount *</label>
                        <input type="number" name="base_amount" id="baseAmount" step="0.01" required onchange="calculateTotal()">
                    </div>
                    <div class="form-group">
                        <label>GST Rate</label>
                        <select name="gst_rate_id" id="gstRate" onchange="calculateGST()">
                            <option value="" data-rate="0">No GST</option>
                            <?php foreach ($gst_rates as $rate): ?>
                                <option value="<?= $rate['id'] ?>" data-rate="<?= $rate['rate'] ?>">
                                    <?= $rate['name'] ?> (<?= $rate['rate'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>GST Type</label>
                        <select name="gst_type" id="gstType" onchange="calculateGST()">
                            <option value="intra">Intra-State (CGST + SGST)</option>
                            <option value="inter">Inter-State (IGST)</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>CGST Amount</label>
                        <input type="number" name="cgst_amount" id="cgstAmount" step="0.01" value="0" readonly>
                    </div>
                    <div class="form-group">
                        <label>SGST Amount</label>
                        <input type="number" name="sgst_amount" id="sgstAmount" step="0.01" value="0" readonly>
                    </div>
                    <div class="form-group">
                        <label>IGST Amount</label>
                        <input type="number" name="igst_amount" id="igstAmount" step="0.01" value="0" readonly>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>TDS Section</label>
                        <select name="tds_section_id" id="tdsSection" onchange="calculateTDS()">
                            <option value="" data-rate="0">No TDS</option>
                            <?php foreach ($tds_sections as $tds): ?>
                                <option value="<?= $tds['id'] ?>" data-rate="<?= $tds['rate_individual'] ?>">
                                    <?= $tds['section_code'] ?> - <?= htmlspecialchars($tds['description']) ?> (<?= $tds['rate_individual'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>TDS Amount</label>
                        <input type="number" name="tds_amount" id="tdsAmount" step="0.01" value="0" readonly>
                    </div>
                </div>

                <div class="amount-summary">
                    <div class="amount-row">
                        <span>Base Amount</span>
                        <span id="dispBase">₹0.00</span>
                    </div>
                    <div class="amount-row" id="gstRow" style="display: none;">
                        <span>GST</span>
                        <span id="dispGST">₹0.00</span>
                    </div>
                    <div class="amount-row" id="tdsRow" style="display: none;">
                        <span>TDS Deducted</span>
                        <span id="dispTDS">- ₹0.00</span>
                    </div>
                    <div class="amount-row total">
                        <span>Total Payable</span>
                        <span id="dispTotal">₹0.00</span>
                    </div>
                </div>

                <input type="hidden" name="total_amount" id="totalAmount" value="0">
            </div>

            <!-- Notes -->
            <div class="form-card">
                <h3>Additional Notes</h3>
                <div class="form-group">
                    <textarea name="notes" rows="3" placeholder="Any additional notes or remarks..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="expenses.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateGST() {
    const baseAmount = parseFloat(document.getElementById('baseAmount').value) || 0;
    const gstSelect = document.getElementById('gstRate');
    const gstType = document.getElementById('gstType').value;
    const gstRate = parseFloat(gstSelect.options[gstSelect.selectedIndex].dataset.rate) || 0;

    let cgst = 0, sgst = 0, igst = 0;

    if (gstRate > 0) {
        if (gstType === 'intra') {
            cgst = (baseAmount * (gstRate / 2)) / 100;
            sgst = (baseAmount * (gstRate / 2)) / 100;
        } else {
            igst = (baseAmount * gstRate) / 100;
        }
    }

    document.getElementById('cgstAmount').value = cgst.toFixed(2);
    document.getElementById('sgstAmount').value = sgst.toFixed(2);
    document.getElementById('igstAmount').value = igst.toFixed(2);

    calculateTotal();
}

function calculateTDS() {
    const baseAmount = parseFloat(document.getElementById('baseAmount').value) || 0;
    const tdsSelect = document.getElementById('tdsSection');
    const tdsRate = parseFloat(tdsSelect.options[tdsSelect.selectedIndex].dataset.rate) || 0;

    const tds = (baseAmount * tdsRate) / 100;
    document.getElementById('tdsAmount').value = tds.toFixed(2);

    calculateTotal();
}

function calculateTotal() {
    const baseAmount = parseFloat(document.getElementById('baseAmount').value) || 0;
    const cgst = parseFloat(document.getElementById('cgstAmount').value) || 0;
    const sgst = parseFloat(document.getElementById('sgstAmount').value) || 0;
    const igst = parseFloat(document.getElementById('igstAmount').value) || 0;
    const tds = parseFloat(document.getElementById('tdsAmount').value) || 0;

    const totalGST = cgst + sgst + igst;
    const total = baseAmount + totalGST - tds;

    document.getElementById('totalAmount').value = total.toFixed(2);

    // Update display
    document.getElementById('dispBase').textContent = '₹' + baseAmount.toFixed(2);
    document.getElementById('dispGST').textContent = '₹' + totalGST.toFixed(2);
    document.getElementById('dispTDS').textContent = '- ₹' + tds.toFixed(2);
    document.getElementById('dispTotal').textContent = '₹' + total.toFixed(2);

    // Show/hide rows
    document.getElementById('gstRow').style.display = totalGST > 0 ? 'flex' : 'none';
    document.getElementById('tdsRow').style.display = tds > 0 ? 'flex' : 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

</body>
</html>
