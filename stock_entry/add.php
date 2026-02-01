<?php
include "../db.php";
include "../includes/dialog.php";

$po_id = $_GET['po_id'] ?? 0;

if (!$po_id) {
    header("Location: index.php");
    exit;
}

/* Fetch PO with part name */
$po = $pdo->prepare("
    SELECT p.*, pm.part_name
    FROM purchase_orders p
    JOIN part_master pm ON p.part_no = pm.part_no
    WHERE p.id = ?
");
$po->execute([$po_id]);
$po = $po->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    setModal("Error", "Invalid PO");
    header("Location: index.php");
    exit;
}

/* Already received qty */
$received = $pdo->prepare("
    SELECT SUM(received_qty)
    FROM stock_entries
    WHERE po_id=? AND status='posted'
");
$received->execute([$po_id]);
$receivedQty = $received->fetchColumn() ?? 0;

$remaining = $po['qty'] - $receivedQty;

$error = '';
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $qty = (float)$_POST['received_qty'];

    if ($qty <= 0 || $qty > $remaining) {
        $error = "Invalid received quantity. Must be between 0 and $remaining";
    } else {
        $pdo->beginTransaction();

        /* Insert stock entry */
        $pdo->prepare("
            INSERT INTO stock_entries
            (po_id, part_no, received_qty, invoice_no)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $po_id,
            $po['part_no'],
            $qty,
            $_POST['invoice_no']
        ]);

        /* Update inventory */
        $pdo->prepare("
            INSERT INTO inventory (part_no, qty)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
        ")->execute([$po['part_no'], $qty]);

        /* Update PO status */
        $newStatus = ($qty + $receivedQty) >= $po['qty']
            ? 'closed'
            : 'partial';

        $pdo->prepare("
            UPDATE purchase_orders SET status=?
            WHERE id=?
        ")->execute([$newStatus, $po_id]);

        $pdo->commit();
        header("Location: index.php");
        exit;
    }
}

// Include header and sidebar AFTER all redirects
include "../includes/header.php";
include "../includes/sidebar.php";
?>

<style>
    .stock-form {
        max-width: 600px;
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .stock-form .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 25px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }
    .stock-form .info-item label {
        font-size: 0.85em;
        color: #666;
        display: block;
    }
    .stock-form .info-item strong {
        font-size: 1.1em;
        color: #333;
    }
    .stock-form .form-group {
        margin-bottom: 20px;
    }
    .stock-form .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    .stock-form .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1em;
        box-sizing: border-box;
    }
    .stock-form .form-group input:focus {
        border-color: #007bff;
        outline: none;
    }
    .stock-form .btn-group {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    body.dark .stock-form {
        background: #2c3e50;
    }
    body.dark .stock-form .info-grid {
        background: #34495e;
    }
    body.dark .stock-form .info-item label {
        color: #bdc3c7;
    }
    body.dark .stock-form .info-item strong {
        color: #ecf0f1;
    }
    body.dark .stock-form .form-group input {
        background: #34495e;
        border-color: #4a6278;
        color: #ecf0f1;
    }
</style>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<div class="content">
    <h1>Receive Stock</h1>

    <a href="index.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Stock Entries</a>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stock-form">
        <div class="info-grid">
            <div class="info-item">
                <label>PO Number</label>
                <strong><?= htmlspecialchars($po['po_no']) ?></strong>
            </div>
            <div class="info-item">
                <label>Part No</label>
                <strong><?= htmlspecialchars($po['part_no']) ?></strong>
            </div>
            <div class="info-item">
                <label>Part Name</label>
                <strong><?= htmlspecialchars($po['part_name']) ?></strong>
            </div>
            <div class="info-item">
                <label>Ordered Qty</label>
                <strong><?= htmlspecialchars($po['qty']) ?></strong>
            </div>
            <div class="info-item">
                <label>Already Received</label>
                <strong><?= htmlspecialchars($receivedQty) ?></strong>
            </div>
            <div class="info-item">
                <label>Remaining</label>
                <strong style="color: <?= $remaining > 0 ? '#28a745' : '#dc3545' ?>;"><?= htmlspecialchars($remaining) ?></strong>
            </div>
        </div>

        <?php if ($remaining > 0): ?>
        <form method="post">
            <div class="form-group">
                <label>Invoice No</label>
                <input type="text" name="invoice_no" placeholder="Enter supplier invoice number">
            </div>

            <div class="form-group">
                <label>Received Qty *</label>
                <input type="number" step="0.001" name="received_qty" required max="<?= $remaining ?>" placeholder="Max: <?= $remaining ?>">
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-success">Post Stock Entry</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 6px;">
            <strong style="color: #155724;">All quantity received for this PO</strong>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
