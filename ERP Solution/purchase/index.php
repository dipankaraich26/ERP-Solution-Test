<html><head><link rel="stylesheet" href="/erp/ERP Solution/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

/* =========================
   FETCH PARTS & SUPPLIERS
========================= */
$parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    WHERE status = 'active'
    ORDER BY part_name
");

$suppliers = $pdo->query("
    SELECT id, supplier_name
    FROM suppliers
    ORDER BY supplier_name
");

/* =========================
   HANDLE PO CREATION
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $po_no        = trim($_POST['po_no']);
    $part_no     = $_POST['part_no'];
    $qty         = (int)$_POST['qty'];
    $supplier_id = (int)$_POST['supplier_id'];
    $date        = $_POST['purchase_date'];

    if ($qty <= 0) {
        setModal("Failed to add part", "Quantity must be more than 0");
        header("Location: index.php"); 
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders
            (po_no, part_no, qty, purchase_date, status, supplier_id)
            VALUES (?, ?, ?, ?, 'open', ?)
        ");
        $stmt->execute([
            $po_no,
            $part_no,
            $qty,
            $date,
            $supplier_id
        ]);

        header("Location: index.php");
        exit;

    } catch (PDOException $e) {
        setModal("Failed to add part", "Part number must be unique");
        header("Location: index.php"); 
    }
}



/* =========================
   FETCH PURCHASE ORDERS
========================= */
$orders = $pdo->query("
    SELECT
        po.id,
        po.po_no,
        po.part_no,
        p.part_name,
        po.qty,
        po.purchase_date,
        po.status,
        s.supplier_name
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.purchase_date DESC, po.id DESC
");
?>

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
<h1>Purchase Orders</h1>

<!-- =========================
     ADD PURCHASE ORDER
========================= -->
<form method="post" class="form-box">
    <h3>Create Purchase Order</h3>

    <label>PO Number</label>
    <input name="po_no" required>

    <label>Part</label>
    <select name="part_no" required>
        <option value="">Select Part</option>
        <?php while ($p = $parts->fetch()): ?>
            <option value="<?= htmlspecialchars($p['part_no']) ?>">
                <?= htmlspecialchars($p['part_no']) ?> — <?= htmlspecialchars($p['part_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Quantity</label>
    <input type="number" name="qty" min="1" required>

    <label>Supplier</label>
    <select name="supplier_id" required>
        <option value="">Select Supplier</option>
        <?php while ($s = $suppliers->fetch()): ?>
            <option value="<?= $s['id'] ?>">
                <?= htmlspecialchars($s['supplier_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Purchase Date</label>
    <input type="date" name="purchase_date" required>

    <button class="btn btn-primary">Create PO</button>
</form>

<hr>

<!-- =========================
     PURCHASE ORDER LIST
========================= -->
<table>
    <tr>
        <th>PO No</th>
        <th>Part</th>
        <th>Qty</th>
        <th>Supplier</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

    <?php while ($o = $orders->fetch()): ?>
    <tr>
        <td><?= htmlspecialchars($o['po_no']) ?></td>
        <td><?= htmlspecialchars($o['part_name']) ?></td>
        <td><?= $o['qty'] ?></td>
        <td><?= htmlspecialchars($o['supplier_name']) ?></td>
        <td><?= $o['purchase_date'] ?></td>
        <td><?= ucfirst($o['status']) ?></td>
        <td>
            <a class="btn btn-secondary"
               href="edit.php?id=<?= $o['id'] ?>">Edit</a>

            <?php if ($o['status'] === 'open'): ?>
                <a class="btn btn-danger"
                   href="cancel.php?id=<?= $o['id'] ?>"
                   onclick="return confirm('Cancel this PO?')">
                   Cancel
                </a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</div>
