<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$error = '';
$success = '';

// Get TDS sections
try {
    $sections_stmt = $pdo->query("SELECT * FROM acc_tds_sections WHERE is_active = 1 ORDER BY section_code");
    $tds_sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tds_sections = [];
}

// Get payment ledgers (Bank & Cash)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $transaction_date = $_POST['transaction_date'];
        $section_id = $_POST['section_id'];
        $deductee_name = trim($_POST['deductee_name']);
        $deductee_pan = strtoupper(trim($_POST['deductee_pan']));
        $deductee_type = $_POST['deductee_type'];
        $payment_amount = (float)$_POST['payment_amount'];
        $tds_rate = (float)$_POST['tds_rate'];
        $tds_amount = (float)$_POST['tds_amount'];
        $surcharge = (float)($_POST['surcharge'] ?? 0);
        $cess = (float)($_POST['cess'] ?? 0);
        $total_tds = $tds_amount + $surcharge + $cess;
        $net_payment = $payment_amount - $total_tds;
        $nature_of_payment = trim($_POST['nature_of_payment']);
        $reference_no = trim($_POST['reference_no']);
        $remarks = trim($_POST['remarks']);

        // Validate PAN format
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $deductee_pan)) {
            throw new Exception("Invalid PAN format. PAN should be in format: ABCDE1234F");
        }

        // Insert TDS transaction
        $stmt = $pdo->prepare("
            INSERT INTO acc_tds_transactions (
                transaction_date, section_id, deductee_name, deductee_pan, deductee_type,
                payment_amount, tds_rate, tds_amount, surcharge, education_cess,
                total_tds, net_payment, nature_of_payment, reference_no, remarks,
                is_deposited, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([
            $transaction_date, $section_id, $deductee_name, $deductee_pan, $deductee_type,
            $payment_amount, $tds_rate, $tds_amount, $surcharge, $cess,
            $total_tds, $net_payment, $nature_of_payment, $reference_no, $remarks,
            $_SESSION['user_id']
        ]);

        $pdo->commit();
        header("Location: tds.php?success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving TDS entry: " . $e->getMessage();
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add TDS Entry - Accounts</title>
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
        .form-group input:focus, .form-group select:focus {
            border-color: #667eea;
            outline: none;
        }
        .form-group small {
            color: #666;
            font-size: 0.85em;
        }

        .amount-display {
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
            font-size: 1.1em;
            padding-top: 15px;
            border-top: 2px solid #667eea;
            margin-top: 10px;
        }
        .amount-row.net { color: #27ae60; }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .section-info {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9em;
            display: none;
        }
        .section-info.visible { display: block; }

        body.dark .form-card { background: #2c3e50; }
        body.dark .form-card h3 { color: #ecf0f1; }
        body.dark .amount-display { background: #34495e; }
        body.dark .section-info { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1 style="margin: 0; color: #2c3e50;">Add TDS Entry</h1>
            <a href="tds.php" class="btn btn-secondary">Back to TDS</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="tdsForm">
            <!-- Transaction Details -->
            <div class="form-card">
                <h3>Transaction Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Transaction Date *</label>
                        <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>TDS Section *</label>
                        <select name="section_id" id="sectionSelect" required onchange="updateSectionInfo()">
                            <option value="">Select Section</option>
                            <?php foreach ($tds_sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>"
                                        data-rate-ind="<?= $sec['rate_individual'] ?>"
                                        data-rate-comp="<?= $sec['rate_company'] ?>"
                                        data-threshold="<?= $sec['threshold_limit'] ?>"
                                        data-desc="<?= htmlspecialchars($sec['description']) ?>">
                                    <?= $sec['section_code'] ?> - <?= htmlspecialchars(substr($sec['description'], 0, 50)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="section-info" id="sectionInfo"></div>
                    </div>
                    <div class="form-group">
                        <label>Nature of Payment *</label>
                        <input type="text" name="nature_of_payment" required placeholder="e.g., Professional fees, Rent, etc.">
                    </div>
                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" name="reference_no" placeholder="Invoice/Bill number">
                    </div>
                </div>
            </div>

            <!-- Deductee Details -->
            <div class="form-card">
                <h3>Deductee Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Deductee Name *</label>
                        <input type="text" name="deductee_name" required placeholder="Name as per PAN">
                    </div>
                    <div class="form-group">
                        <label>Deductee PAN *</label>
                        <input type="text" name="deductee_pan" required placeholder="ABCDE1234F"
                               maxlength="10" style="text-transform: uppercase;"
                               pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}">
                        <small>Format: ABCDE1234F</small>
                    </div>
                    <div class="form-group">
                        <label>Deductee Type *</label>
                        <select name="deductee_type" id="deducteeType" required onchange="updateTDSRate()">
                            <option value="Individual">Individual/HUF</option>
                            <option value="Company">Company</option>
                            <option value="Firm">Partnership Firm</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Amount Details -->
            <div class="form-card">
                <h3>Amount Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Payment Amount *</label>
                        <input type="number" name="payment_amount" id="paymentAmount" step="0.01" required
                               onchange="calculateTDS()" placeholder="Gross payment amount">
                    </div>
                    <div class="form-group">
                        <label>TDS Rate (%) *</label>
                        <input type="number" name="tds_rate" id="tdsRate" step="0.01" required
                               onchange="calculateTDS()" placeholder="TDS rate">
                    </div>
                    <div class="form-group">
                        <label>TDS Amount</label>
                        <input type="number" name="tds_amount" id="tdsAmount" step="0.01" readonly>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Surcharge</label>
                        <input type="number" name="surcharge" id="surcharge" step="0.01" value="0"
                               onchange="calculateTotals()">
                        <small>If applicable (for payments > threshold)</small>
                    </div>
                    <div class="form-group">
                        <label>Education Cess (4%)</label>
                        <input type="number" name="cess" id="cess" step="0.01" value="0"
                               onchange="calculateTotals()">
                        <small>4% on TDS + Surcharge</small>
                    </div>
                </div>

                <div class="amount-display">
                    <div class="amount-row">
                        <span>Payment Amount</span>
                        <span id="dispPayment">₹0.00</span>
                    </div>
                    <div class="amount-row">
                        <span>TDS Amount</span>
                        <span id="dispTDS">₹0.00</span>
                    </div>
                    <div class="amount-row">
                        <span>Surcharge</span>
                        <span id="dispSurcharge">₹0.00</span>
                    </div>
                    <div class="amount-row">
                        <span>Education Cess</span>
                        <span id="dispCess">₹0.00</span>
                    </div>
                    <div class="amount-row total">
                        <span>Total TDS Deduction</span>
                        <span id="dispTotalTDS">₹0.00</span>
                    </div>
                    <div class="amount-row net">
                        <span>Net Payment to Deductee</span>
                        <span id="dispNetPayment">₹0.00</span>
                    </div>
                </div>
            </div>

            <!-- Remarks -->
            <div class="form-card">
                <h3>Remarks</h3>
                <div class="form-group">
                    <textarea name="remarks" rows="3" placeholder="Any additional remarks..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="tds.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save TDS Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateSectionInfo() {
    const select = document.getElementById('sectionSelect');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('sectionInfo');

    if (option.value) {
        const rateInd = option.dataset.rateInd;
        const rateComp = option.dataset.rateComp;
        const threshold = option.dataset.threshold;
        const desc = option.dataset.desc;

        infoDiv.innerHTML = `
            <strong>${desc}</strong><br>
            <span>Individual Rate: ${rateInd}% | Company Rate: ${rateComp}%</span><br>
            <span>Threshold: ₹${parseInt(threshold).toLocaleString()}</span>
        `;
        infoDiv.classList.add('visible');

        // Auto-set TDS rate based on deductee type
        updateTDSRate();
    } else {
        infoDiv.classList.remove('visible');
    }
}

function updateTDSRate() {
    const select = document.getElementById('sectionSelect');
    const option = select.options[select.selectedIndex];
    const deducteeType = document.getElementById('deducteeType').value;

    if (option.value) {
        const rate = deducteeType === 'Company' ? option.dataset.rateComp : option.dataset.rateInd;
        document.getElementById('tdsRate').value = rate;
        calculateTDS();
    }
}

function calculateTDS() {
    const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const rate = parseFloat(document.getElementById('tdsRate').value) || 0;

    const tds = (payment * rate) / 100;
    document.getElementById('tdsAmount').value = tds.toFixed(2);

    // Calculate cess (4% on TDS)
    const cess = tds * 0.04;
    document.getElementById('cess').value = cess.toFixed(2);

    calculateTotals();
}

function calculateTotals() {
    const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
    const tds = parseFloat(document.getElementById('tdsAmount').value) || 0;
    const surcharge = parseFloat(document.getElementById('surcharge').value) || 0;
    const cess = parseFloat(document.getElementById('cess').value) || 0;

    const totalTDS = tds + surcharge + cess;
    const netPayment = payment - totalTDS;

    // Update display
    document.getElementById('dispPayment').textContent = '₹' + payment.toFixed(2);
    document.getElementById('dispTDS').textContent = '₹' + tds.toFixed(2);
    document.getElementById('dispSurcharge').textContent = '₹' + surcharge.toFixed(2);
    document.getElementById('dispCess').textContent = '₹' + cess.toFixed(2);
    document.getElementById('dispTotalTDS').textContent = '₹' + totalTDS.toFixed(2);
    document.getElementById('dispNetPayment').textContent = '₹' + netPayment.toFixed(2);
}
</script>

</body>
</html>
