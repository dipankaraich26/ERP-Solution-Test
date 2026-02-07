<?php
/**
 * Create Advance Payment Request
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check table
$tableError = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'advance_payments'")->fetch();
    if (!$check) { $tableError = true; }
} catch (PDOException $e) { $tableError = true; }

if ($tableError) {
    setModal("Setup Required", "Advance payment tables not found. Please visit the Advance Payments list page first.");
    header("Location: advance_payment.php");
    exit;
}

$employees = $pdo->query("SELECT id, emp_id, first_name, last_name, department FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $advance_type = $_POST['advance_type'] ?? 'Salary';
    $amount = floatval($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $repayment_months = intval($_POST['repayment_months'] ?? 1);

    if (!$employee_id) $errors[] = "Please select an employee.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";
    if (!$purpose) $errors[] = "Purpose is required.";
    if ($repayment_months < 1 || $repayment_months > 24) $errors[] = "Repayment months must be between 1 and 24.";

    // Check for existing active advance
    if ($employee_id && empty($errors)) {
        $existing = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ? AND status IN ('Pending','Approved','Disbursed')");
        $existing->execute([$employee_id]);
        if ($existing->fetchColumn() > 0) {
            $errors[] = "This employee already has an active advance. Please close it before creating a new one.";
        }
    }

    if (empty($errors)) {
        $monthly_deduction = round($amount / $repayment_months, 2);

        $lastId = $pdo->query("SELECT MAX(id) FROM advance_payments")->fetchColumn();
        $advanceNo = 'ADV-' . date('Y') . '-' . str_pad(($lastId + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO advance_payments (
                advance_no, employee_id, advance_type, amount, purpose,
                repayment_months, monthly_deduction, balance_remaining,
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
        ");
        $stmt->execute([
            $advanceNo, $employee_id, $advance_type, $amount, $purpose,
            $repayment_months, $monthly_deduction, $amount,
            $_SESSION['user_id'] ?? null
        ]);

        setModal("Success", "Advance request $advanceNo created successfully!");
        header("Location: advance_payment.php");
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Advance Payment - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-panel {
            background: white; border-radius: 10px; padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 700px;
        }
        .form-panel h3 {
            margin: 0 0 20px 0; padding-bottom: 10px;
            border-bottom: 2px solid #30cfd0; color: #2c3e50;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; font-weight: 600; color: #555; margin-bottom: 5px; font-size: 0.9em; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.95em;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input[readonly] { background: #f8f9fa; font-weight: 600; }
        .error-box {
            background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }
        .error-box ul { margin: 5px 0 0 20px; }
        .repayment-preview {
            background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 15px;
            border-left: 4px solid #27ae60;
        }
        .repayment-preview .amount { font-size: 1.5em; font-weight: 700; color: #27ae60; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Create Advance Payment</h1>
        <a href="advance_payment.php" class="btn btn-secondary">Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="form-panel">
        <h3>Advance Details</h3>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Employee *</label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($employee_id ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['emp_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['department'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Advance Type *</label>
                    <select name="advance_type" required>
                        <?php foreach (['Salary','Travel','Project','Medical','Other'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($advance_type ?? 'Salary') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (Rs) *</label>
                    <input type="number" name="amount" id="adv_amount" step="0.01" min="1" value="<?= $amount ?? '' ?>" required onchange="calcDeduction()">
                </div>

                <div class="form-group full">
                    <label>Purpose / Reason *</label>
                    <textarea name="purpose" required placeholder="Explain the reason for this advance"><?= htmlspecialchars($purpose ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Repayment Months (1-24) *</label>
                    <input type="number" name="repayment_months" id="repayment_months" min="1" max="24" value="<?= $repayment_months ?? 1 ?>" required onchange="calcDeduction()">
                </div>

                <div class="form-group">
                    <label>Monthly Deduction (Rs)</label>
                    <input type="text" id="monthly_deduction_display" readonly value="0.00">
                </div>
            </div>

            <div class="repayment-preview" id="repaymentPreview" style="display:none;">
                <strong>Repayment Plan:</strong> Rs <span class="amount" id="previewDeduction">0</span>/month
                for <span id="previewMonths">0</span> months
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Submit Advance Request</button>
                <a href="advance_payment.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function calcDeduction() {
    var amount = parseFloat(document.getElementById('adv_amount').value) || 0;
    var months = parseInt(document.getElementById('repayment_months').value) || 1;
    var ded = months > 0 ? (amount / months).toFixed(2) : '0.00';
    document.getElementById('monthly_deduction_display').value = ded;

    var preview = document.getElementById('repaymentPreview');
    if (amount > 0 && months > 0) {
        document.getElementById('previewDeduction').textContent = ded;
        document.getElementById('previewMonths').textContent = months;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}
calcDeduction();
</script>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
