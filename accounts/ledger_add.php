<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get account groups
try {
    $groups = $pdo->query("SELECT id, group_name, group_type, nature FROM acc_account_groups ORDER BY group_type, group_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $groups = [];
}

// Get TDS sections
try {
    $tds_sections = $pdo->query("SELECT id, section_code, section_name FROM acc_tds_sections WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tds_sections = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ledger_name = trim($_POST['ledger_name'] ?? '');
    $ledger_code = trim($_POST['ledger_code'] ?? '');
    $group_id = (int)($_POST['group_id'] ?? 0);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    $opening_balance_type = $_POST['opening_balance_type'] ?? 'Debit';
    $is_bank_account = isset($_POST['is_bank_account']) ? 1 : 0;
    $is_cash_account = isset($_POST['is_cash_account']) ? 1 : 0;
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account_no = trim($_POST['bank_account_no'] ?? '');
    $bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
    $bank_branch = trim($_POST['bank_branch'] ?? '');
    $gst_applicable = isset($_POST['gst_applicable']) ? 1 : 0;
    $gstin = trim($_POST['gstin'] ?? '');
    $pan = trim($_POST['pan'] ?? '');
    $tds_applicable = isset($_POST['tds_applicable']) ? 1 : 0;
    $tds_section = trim($_POST['tds_section'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $credit_limit = (float)($_POST['credit_limit'] ?? 0);
    $credit_days = (int)($_POST['credit_days'] ?? 0);

    // Validation
    if (empty($ledger_name)) $errors[] = "Ledger name is required";
    if ($group_id <= 0) $errors[] = "Account group is required";

    // Check duplicate code
    if ($ledger_code) {
        $check = $pdo->prepare("SELECT id FROM acc_ledgers WHERE ledger_code = ?");
        $check->execute([$ledger_code]);
        if ($check->fetch()) {
            $errors[] = "Ledger code already exists";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate code if not provided
            if (empty($ledger_code)) {
                $maxId = $pdo->query("SELECT MAX(id) FROM acc_ledgers")->fetchColumn();
                $ledger_code = 'LED' . str_pad(($maxId ?: 0) + 1, 5, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO acc_ledgers (
                    ledger_code, ledger_name, group_id, opening_balance, opening_balance_type,
                    current_balance, balance_type, is_bank_account, is_cash_account,
                    bank_name, bank_account_no, bank_ifsc, bank_branch,
                    gst_applicable, gstin, pan, tds_applicable, tds_section,
                    address, contact_person, phone, email, credit_limit, credit_days
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ledger_code, $ledger_name, $group_id, $opening_balance, $opening_balance_type,
                $opening_balance, $opening_balance_type, $is_bank_account, $is_cash_account,
                $bank_name ?: null, $bank_account_no ?: null, $bank_ifsc ?: null, $bank_branch ?: null,
                $gst_applicable, $gstin ?: null, $pan ?: null, $tds_applicable, $tds_section ?: null,
                $address ?: null, $contact_person ?: null, $phone ?: null, $email ?: null,
                $credit_limit, $credit_days
            ]);
            $ledger_id = $pdo->lastInsertId();

            // If bank account, create bank account record
            if ($is_bank_account && $bank_account_no) {
                $bank_stmt = $pdo->prepare("
                    INSERT INTO acc_bank_accounts (ledger_id, bank_name, account_no, ifsc_code, branch_name)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $bank_stmt->execute([$ledger_id, $bank_name, $bank_account_no, $bank_ifsc, $bank_branch]);
            }

            $pdo->commit();
            setModal("Success", "Ledger '$ledger_name' created successfully.");
            header("Location: ledgers.php");
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
    <title>New Ledger - Accounts</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 900px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child { border-bottom: none; }
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #667eea;
            font-size: 1.1em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group { margin-bottom: 0; }
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
        .form-group.full-width { grid-column: 1 / -1; }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] { width: auto; }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }

        .bank-details, .party-details { display: none; }
        .bank-details.show, .party-details.show { display: block; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
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
            <h1>Create New Ledger</h1>
            <p style="color: #666; margin: 5px 0 0;">Add a new account to Chart of Accounts</p>
        </div>
        <a href="ledgers.php" class="btn btn-secondary">Back to Ledgers</a>
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
        <form method="post">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Ledger Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Ledger Name <span class="required">*</span></label>
                        <input type="text" name="ledger_name" value="<?= htmlspecialchars($_POST['ledger_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Ledger Code</label>
                        <input type="text" name="ledger_code" value="<?= htmlspecialchars($_POST['ledger_code'] ?? '') ?>" placeholder="Auto-generated if blank">
                    </div>

                    <div class="form-group">
                        <label>Account Group <span class="required">*</span></label>
                        <select name="group_id" id="group_id" required onchange="updateGroupType()">
                            <option value="">-- Select Group --</option>
                            <?php
                            $currentType = '';
                            foreach ($groups as $g):
                                if ($g['group_type'] !== $currentType):
                                    if ($currentType) echo '</optgroup>';
                                    echo '<optgroup label="' . $g['group_type'] . '">';
                                    $currentType = $g['group_type'];
                                endif;
                            ?>
                                <option value="<?= $g['id'] ?>" data-type="<?= $g['group_type'] ?>" data-nature="<?= $g['nature'] ?>" <?= ($_POST['group_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['group_name']) ?></option>
                            <?php endforeach; ?>
                            <?php if ($currentType) echo '</optgroup>'; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Opening Balance</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" name="opening_balance" value="<?= $_POST['opening_balance'] ?? '0' ?>" step="0.01" style="flex: 1;">
                            <select name="opening_balance_type" style="width: 100px;">
                                <option value="Debit" <?= ($_POST['opening_balance_type'] ?? '') === 'Debit' ? 'selected' : '' ?>>Dr</option>
                                <option value="Credit" <?= ($_POST['opening_balance_type'] ?? '') === 'Credit' ? 'selected' : '' ?>>Cr</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_bank_account" id="is_bank_account" onchange="toggleBankDetails()">
                            <label for="is_bank_account" style="margin: 0;">This is a Bank Account</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_cash_account" id="is_cash_account">
                            <label for="is_cash_account" style="margin: 0;">This is a Cash Account</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="form-section bank-details" id="bank-details">
                <h3>Bank Account Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account_no" value="<?= htmlspecialchars($_POST['bank_account_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="bank_ifsc" value="<?= htmlspecialchars($_POST['bank_ifsc'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" name="bank_branch" value="<?= htmlspecialchars($_POST['bank_branch'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Party Details (for Debtors/Creditors) -->
            <div class="form-section party-details" id="party-details">
                <h3>Party Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Credit Limit (Rs.)</label>
                        <input type="number" name="credit_limit" value="<?= $_POST['credit_limit'] ?? '0' ?>" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Credit Days</label>
                        <input type="number" name="credit_days" value="<?= $_POST['credit_days'] ?? '0' ?>">
                    </div>
                </div>
            </div>

            <!-- Tax Details -->
            <div class="form-section">
                <h3>Tax Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="gst_applicable" id="gst_applicable">
                            <label for="gst_applicable" style="margin: 0;">GST Applicable</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input type="text" name="gstin" value="<?= htmlspecialchars($_POST['gstin'] ?? '') ?>" maxlength="15" placeholder="22AAAAA0000A1Z5">
                    </div>
                    <div class="form-group">
                        <label>PAN</label>
                        <input type="text" name="pan" value="<?= htmlspecialchars($_POST['pan'] ?? '') ?>" maxlength="10" placeholder="AAAAA0000A">
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="tds_applicable" id="tds_applicable">
                            <label for="tds_applicable" style="margin: 0;">TDS Applicable</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>TDS Section</label>
                        <select name="tds_section">
                            <option value="">-- Select --</option>
                            <?php foreach ($tds_sections as $tds): ?>
                                <option value="<?= $tds['section_code'] ?>" <?= ($_POST['tds_section'] ?? '') === $tds['section_code'] ? 'selected' : '' ?>>
                                    <?= $tds['section_code'] ?> - <?= $tds['section_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Ledger</button>
                <a href="ledgers.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleBankDetails() {
    const checkbox = document.getElementById('is_bank_account');
    const details = document.getElementById('bank-details');
    details.classList.toggle('show', checkbox.checked);
}

function updateGroupType() {
    const select = document.getElementById('group_id');
    const option = select.options[select.selectedIndex];
    const partyDetails = document.getElementById('party-details');

    // Show party details for Sundry Debtors/Creditors
    const groupName = option.textContent.toLowerCase();
    if (groupName.includes('debtor') || groupName.includes('creditor')) {
        partyDetails.classList.add('show');
    } else {
        partyDetails.classList.remove('show');
    }
}

// Initialize on page load
updateGroupType();
</script>

</body>
</html>
