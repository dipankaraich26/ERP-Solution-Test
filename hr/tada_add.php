<?php
/**
 * Create TADA Claim
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check table
$tableError = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'tada_claims'")->fetch();
    if (!$check) { $tableError = true; }
} catch (PDOException $e) { $tableError = true; }

if ($tableError) {
    setModal("Setup Required", "TADA tables not found. Please visit the TADA list page first to auto-create tables.");
    header("Location: tada.php");
    exit;
}

// Fetch active employees
$employees = $pdo->query("SELECT id, emp_id, first_name, last_name, department FROM employees WHERE status = 'Active' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $travel_date = $_POST['travel_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $from_location = trim($_POST['from_location'] ?? '');
    $to_location = trim($_POST['to_location'] ?? '');
    $travel_mode = $_POST['travel_mode'] ?? 'Bus';
    $purpose = trim($_POST['purpose'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $travel_amount = floatval($_POST['travel_amount'] ?? 0);
    $da_amount = floatval($_POST['da_amount'] ?? 0);
    $accommodation_amount = floatval($_POST['accommodation_amount'] ?? 0);
    $other_amount = floatval($_POST['other_amount'] ?? 0);
    $total_amount = $travel_amount + $da_amount + $accommodation_amount + $other_amount;

    // Validate
    if (!$employee_id) $errors[] = "Please select an employee.";
    if (!$travel_date) $errors[] = "Travel date is required.";
    if (!$from_location) $errors[] = "From location is required.";
    if (!$to_location) $errors[] = "To location is required.";
    if (!$purpose) $errors[] = "Purpose is required.";
    if ($total_amount <= 0) $errors[] = "Total amount must be greater than zero.";

    // Handle receipt upload
    $receipt_path = null;
    if (!empty($_FILES['receipt']['name'])) {
        $file = $_FILES['receipt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $errors[] = "Receipt must be PDF, JPG, or PNG.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "Receipt file must be less than 5MB.";
        } else {
            // Get emp_id for folder
            $empStmt = $pdo->prepare("SELECT emp_id FROM employees WHERE id = ?");
            $empStmt->execute([$employee_id]);
            $empCode = $empStmt->fetchColumn() ?: $employee_id;

            $uploadDir = "../uploads/tada_receipts/" . $empCode . "/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $receipt_path = "uploads/tada_receipts/" . $empCode . "/" . $filename;
            } else {
                $errors[] = "Failed to upload receipt.";
            }
        }
    }

    if (empty($errors)) {
        // Generate claim number
        $lastId = $pdo->query("SELECT MAX(id) FROM tada_claims")->fetchColumn();
        $claimNo = 'TA-' . date('Y') . '-' . str_pad(($lastId + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO tada_claims (
                claim_no, employee_id, travel_date, return_date, from_location, to_location,
                travel_mode, purpose, description,
                travel_amount, da_amount, accommodation_amount, other_amount, total_amount,
                receipt_path, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
        ");
        $stmt->execute([
            $claimNo, $employee_id, $travel_date, $return_date ?: null, $from_location, $to_location,
            $travel_mode, $purpose, $description ?: null,
            $travel_amount, $da_amount, $accommodation_amount, $other_amount, $total_amount,
            $receipt_path, $_SESSION['user_id'] ?? null
        ]);

        setModal("Success", "TADA claim $claimNo created successfully!");
        header("Location: tada.php");
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create TADA Claim - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-panel {
            background: white; border-radius: 10px; padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 800px;
        }
        .form-panel h3 {
            margin: 0 0 20px 0; padding-bottom: 10px;
            border-bottom: 2px solid #30cfd0; color: #2c3e50;
        }
        .form-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            display: block; font-weight: 600; color: #555; margin-bottom: 5px; font-size: 0.9em;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px;
            font-size: 0.95em;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input[readonly] { background: #f8f9fa; font-weight: 600; }
        .amount-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .error-box {
            background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 5px 0 0 20px; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Create TADA Claim</h1>
        <a href="tada.php" class="btn btn-secondary">Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-panel">
        <h3>TADA Claim Details</h3>
        <form method="POST" enctype="multipart/form-data">
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
                    <label>Travel Date *</label>
                    <input type="date" name="travel_date" value="<?= htmlspecialchars($travel_date ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Return Date</label>
                    <input type="date" name="return_date" value="<?= htmlspecialchars($return_date ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>From Location *</label>
                    <input type="text" name="from_location" value="<?= htmlspecialchars($from_location ?? '') ?>" required placeholder="e.g. Mumbai">
                </div>
                <div class="form-group">
                    <label>To Location *</label>
                    <input type="text" name="to_location" value="<?= htmlspecialchars($to_location ?? '') ?>" required placeholder="e.g. Pune">
                </div>

                <div class="form-group">
                    <label>Travel Mode *</label>
                    <select name="travel_mode" required>
                        <?php foreach (['Bus','Train','Flight','Auto','Own Vehicle','Other'] as $mode): ?>
                            <option value="<?= $mode ?>" <?= ($travel_mode ?? '') === $mode ? 'selected' : '' ?>><?= $mode ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Receipt (PDF/JPG/PNG, max 5MB)</label>
                    <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                </div>

                <div class="form-group full">
                    <label>Purpose *</label>
                    <input type="text" name="purpose" value="<?= htmlspecialchars($purpose ?? '') ?>" required placeholder="Purpose of travel">
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" placeholder="Additional details (optional)"><?= htmlspecialchars($description ?? '') ?></textarea>
                </div>
            </div>

            <h3 style="margin-top: 25px;">Amount Details</h3>
            <div class="amount-grid">
                <div class="form-group">
                    <label>Travel Amount (Rs)</label>
                    <input type="number" name="travel_amount" id="travel_amount" step="0.01" min="0" value="<?= $travel_amount ?? 0 ?>" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label>DA Amount (Rs)</label>
                    <input type="number" name="da_amount" id="da_amount" step="0.01" min="0" value="<?= $da_amount ?? 0 ?>" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label>Accommodation (Rs)</label>
                    <input type="number" name="accommodation_amount" id="accommodation_amount" step="0.01" min="0" value="<?= $accommodation_amount ?? 0 ?>" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label>Other (Rs)</label>
                    <input type="number" name="other_amount" id="other_amount" step="0.01" min="0" value="<?= $other_amount ?? 0 ?>" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label>Total Amount (Rs)</label>
                    <input type="text" id="total_display" readonly value="0.00" style="font-size: 1.2em; color: #27ae60;">
                </div>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Submit TADA Claim</button>
                <a href="tada.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function calcTotal() {
    var t = parseFloat(document.getElementById('travel_amount').value) || 0;
    var d = parseFloat(document.getElementById('da_amount').value) || 0;
    var a = parseFloat(document.getElementById('accommodation_amount').value) || 0;
    var o = parseFloat(document.getElementById('other_amount').value) || 0;
    document.getElementById('total_display').value = (t + d + a + o).toFixed(2);
}
calcTotal();
</script>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
