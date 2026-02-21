<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/procurement_helper.php";

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

// Auto-add attachment columns if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM stock_entries LIKE 'invoice_attachment'")->rowCount();
    if ($cols === 0) {
        $pdo->exec("ALTER TABLE stock_entries ADD COLUMN invoice_attachment VARCHAR(255) DEFAULT NULL");
    }
    $cols2 = $pdo->query("SHOW COLUMNS FROM stock_entries LIKE 'material_photo'")->rowCount();
    if ($cols2 === 0) {
        $pdo->exec("ALTER TABLE stock_entries ADD COLUMN material_photo VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) { /* columns may already exist */ }

$error = '';
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $qty = (float)$_POST['received_qty'];

    if ($qty <= 0 || $qty > $remaining) {
        $error = "Invalid received quantity. Must be between 0 and $remaining";
    } else {
        // Handle file uploads
        $invoiceAttachment = null;
        $materialPhoto = null;
        $uploadDir = __DIR__ . '/../uploads/stock_entry/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!empty($_FILES['invoice_attachment']['name'])) {
            $ext = strtolower(pathinfo($_FILES['invoice_attachment']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','jpg','jpeg','png','gif','doc','docx','xls','xlsx'];
            if (in_array($ext, $allowed) && $_FILES['invoice_attachment']['size'] <= 10 * 1024 * 1024) {
                $fname = time() . '_invoice_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['invoice_attachment']['name']);
                if (move_uploaded_file($_FILES['invoice_attachment']['tmp_name'], $uploadDir . $fname)) {
                    $invoiceAttachment = $fname;
                }
            }
        }
        if (!empty($_FILES['material_photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['material_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $_FILES['material_photo']['size'] <= 10 * 1024 * 1024) {
                $fname = time() . '_material_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['material_photo']['name']);
                if (move_uploaded_file($_FILES['material_photo']['tmp_name'], $uploadDir . $fname)) {
                    $materialPhoto = $fname;
                }
            }
        }

        $pdo->beginTransaction();

        /* Insert stock entry */
        $pdo->prepare("
            INSERT INTO stock_entries
            (po_id, part_no, received_qty, invoice_no, invoice_attachment, material_photo)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $po_id,
            $po['part_no'],
            $qty,
            $_POST['invoice_no'],
            $invoiceAttachment,
            $materialPhoto
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

        // Sync PO closure back to procurement plan tracking
        if ($newStatus === 'closed') {
            syncPoStatusToPlan($pdo, (int)$po_id, $po['part_no'], 'closed');
        }

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
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Invoice No</label>
                <input type="text" name="invoice_no" placeholder="Enter supplier invoice number">
            </div>

            <div class="form-group">
                <label>Received Qty *</label>
                <input type="number" step="0.001" name="received_qty" required max="<?= $remaining ?>" placeholder="Max: <?= $remaining ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Invoice Attachment</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="file" name="invoice_attachment" id="se_invoice_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="display: none;"
                               onchange="var n=document.getElementById('se_inv_fname'); n.textContent=this.files[0]?.name||''; n.style.display=this.files[0]?'block':'none';">
                        <button type="button" onclick="document.getElementById('se_invoice_file').removeAttribute('capture'); document.getElementById('se_invoice_file').click();"
                                style="flex: 1; padding: 8px; border: 2px dashed #ddd; border-radius: 6px; background: #f8f9fa; cursor: pointer; font-size: 0.85em; color: #555;">&#128193; File</button>
                        <button type="button" onclick="document.getElementById('se_invoice_file').setAttribute('accept','image/*'); document.getElementById('se_invoice_file').setAttribute('capture','environment'); document.getElementById('se_invoice_file').click(); setTimeout(function(){ document.getElementById('se_invoice_file').setAttribute('accept','.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx'); },500);"
                                style="flex: 1; padding: 8px; border: 2px dashed #0d6efd; border-radius: 6px; background: #e8f0fe; cursor: pointer; font-size: 0.85em; color: #0d6efd; font-weight: 600;">&#128247; Camera</button>
                    </div>
                    <small id="se_inv_fname" style="display:none; margin-top:4px; color:#27ae60; font-size:0.8em;"></small>
                    <small style="color: #888; display: block; margin-top: 4px;">PDF, Image, or Document (max 10MB)</small>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Material Photo</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="file" name="material_photo" id="se_material_photo" accept="image/*" style="display: none;"
                               onchange="var n=document.getElementById('se_mat_fname'); n.textContent=this.files[0]?.name||''; n.style.display=this.files[0]?'block':'none';">
                        <button type="button" onclick="document.getElementById('se_material_photo').removeAttribute('capture'); document.getElementById('se_material_photo').click();"
                                style="flex: 1; padding: 8px; border: 2px dashed #ddd; border-radius: 6px; background: #f8f9fa; cursor: pointer; font-size: 0.85em; color: #555;">&#128193; Gallery</button>
                        <button type="button" onclick="document.getElementById('se_material_photo').setAttribute('capture','environment'); document.getElementById('se_material_photo').click();"
                                style="flex: 1; padding: 8px; border: 2px dashed #0d6efd; border-radius: 6px; background: #e8f0fe; cursor: pointer; font-size: 0.85em; color: #0d6efd; font-weight: 600;">&#128247; Camera</button>
                    </div>
                    <small id="se_mat_fname" style="display:none; margin-top:4px; color:#27ae60; font-size:0.8em;"></small>
                    <small style="color: #888; display: block; margin-top: 4px;">JPG, PNG, or WebP (max 10MB)</small>
                </div>
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
