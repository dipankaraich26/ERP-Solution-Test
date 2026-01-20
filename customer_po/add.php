<?php
include "../db.php";

$errors = [];
$success = false;

// Fetch customers for dropdown
$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name
    FROM customers
    WHERE status = 'active'
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch released PIs for optional linking
$pis = $pdo->query("
    SELECT id, pi_no, quote_no, customer_id
    FROM quote_master
    WHERE status = 'released'
    ORDER BY released_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_no = trim($_POST['po_no'] ?? '');
    $customer_id = trim($_POST['customer_id'] ?? '');
    $po_date = trim($_POST['po_date'] ?? '');
    $linked_quote_id = !empty($_POST['linked_quote_id']) ? (int)$_POST['linked_quote_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($po_no === '') {
        $errors[] = "PO Number is required";
    }

    // Handle file upload (required for customer PO)
    $attachmentPath = null;
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "../uploads/customer_po/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowedExts)) {
            $errors[] = "File type not allowed. Allowed: PDF, JPG, PNG";
        } else {
            $fileName = "CPO_" . preg_replace('/[^a-zA-Z0-9]/', '_', $po_no) . "_" . time() . "." . $ext;
            $fullPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
                $attachmentPath = "uploads/customer_po/" . $fileName;
            } else {
                $errors[] = "Failed to upload attachment";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO customer_po
            (po_no, customer_id, po_date, linked_quote_id, attachment_path, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $po_no,
            $customer_id ?: null,
            $po_date ?: null,
            $linked_quote_id,
            $attachmentPath,
            $notes
        ]);

        header("Location: index.php");
        exit;
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Customer PO</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-section {
            max-width: 600px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }
    </style>
</head>
<body>

<div class="content">
    <h1>Upload Customer PO</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-section">

        <div class="form-group">
            <label>Customer PO Number *</label>
            <input type="text" name="po_no" required placeholder="Enter customer's PO number">
        </div>

        <div class="form-group">
            <label>Customer</label>
            <select name="customer_id">
                <option value="">-- Select Customer (Optional) --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= htmlspecialchars($c['customer_id']) ?>">
                        <?= htmlspecialchars($c['company_name']) ?>
                        (<?= htmlspecialchars($c['customer_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>PO Date</label>
            <input type="date" name="po_date" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label>Link to Proforma Invoice (Optional)</label>
            <select name="linked_quote_id">
                <option value="">-- No Link --</option>
                <?php foreach ($pis as $pi): ?>
                    <option value="<?= $pi['id'] ?>">
                        <?= htmlspecialchars($pi['pi_no']) ?> (Quote: <?= htmlspecialchars($pi['quote_no']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #666;">Optionally link this PO to a released Proforma Invoice</small>
        </div>

        <div class="form-group">
            <label>Attachment (PDF/Image)</label>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
            <small style="color: #666;">Upload the customer's PO document. Allowed: PDF, JPG, PNG</small>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" placeholder="Any additional notes..."></textarea>
        </div>

        <button type="submit" class="btn btn-success">Save Customer PO</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

</body>
</html>
