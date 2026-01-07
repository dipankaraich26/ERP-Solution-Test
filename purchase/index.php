<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

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
")->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $pdo->query("
    SELECT id, supplier_name
    FROM suppliers
    ORDER BY supplier_name
");

/* =========================
   HANDLE PO CREATION
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Generate next PO number server-side (PO-1, PO-2, ...)
    $maxNo = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(po_no,4) AS UNSIGNED)), 0) FROM purchase_orders WHERE po_no LIKE 'PO-%'")->fetchColumn();
    $nextNo = ((int)$maxNo) + 1;
    $po_no = 'PO-' . $nextNo;
    $parts_post  = $_POST['part_no'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $date        = $_POST['purchase_date'] ?? '';

    // normalize to arrays
    if (!is_array($parts_post)) $parts_post = [$parts_post];
    if (!is_array($qtys)) $qtys = [$qtys];

    // build items list (skip empty part values)
    $items = [];
    $max = max(count($parts_post), count($qtys));
    for ($i = 0; $i < $max; $i++) {
        $p = $parts_post[$i] ?? '';
        $q = isset($qtys[$i]) ? (int)$qtys[$i] : 0;
        if ($p === '') continue;
        $items[] = ['part_no' => $p, 'qty' => $q];
    }

    if (empty($items)) {
        setModal("Failed to add PO", "Use at least one part with quantity");
        header("Location: index.php");
        exit;
    }

    if ($supplier_id <= 0 || $date === '') {
        setModal("Failed to add PO", "Supplier and purchase date are required");
        header("Location: index.php");
        exit;
    }

    // validate quantities
    foreach ($items as $it) {
        if ($it['qty'] <= 0) {
            setModal("Failed to add PO", "Quantity must be more than 0");
            header("Location: index.php");
            exit;
        }
    }

    // Insert multiple purchase orders in a transaction. All items use the same generated PO number.
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("\n            INSERT INTO purchase_orders\n            (po_no, part_no, qty, purchase_date, status, supplier_id)\n            VALUES (?, ?, ?, ?, 'open', ?)\n        ");

        foreach ($items as $it) {
            $stmt->execute([
                $po_no,
                $it['part_no'],
                $it['qty'],
                $date,
                $supplier_id
            ]);
        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        setModal("Failed to add PO", $e->getMessage());
        header("Location: index.php");
        exit;
    }
}



/* =========================
   FETCH PURCHASE ORDERS (grouped by PO number)
   Each PO will show all its line items in one row (parts list)
========================= */
$orders = $pdo->query("
    SELECT
        po.po_no,
        po.purchase_date,
        s.supplier_name,
        GROUP_CONCAT(CONCAT(po.id, '::', po.part_no, '::', p.part_name, '::', po.qty) ORDER BY po.id SEPARATOR '|||') AS items,
        GROUP_CONCAT(DISTINCT po.status) AS status_list,
        MAX(po.id) AS max_id
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    JOIN suppliers s ON s.id = po.supplier_id
    GROUP BY po.po_no, po.purchase_date, s.supplier_name
    ORDER BY po.purchase_date DESC, max_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
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
    <div><em>Automatically assigned on create (e.g. PO-1)</em></div>

    <label>Parts</label>
    <table border="1" cellpadding="6" id="poTable">
        <tr>
            <th>Part</th>
            <th>Qty</th>
            <th></th>
        </tr>
        <tr>
            <td>
                <select name="part_no[]" required>
                    <option value="">Select Part</option>
                    <?php foreach ($parts as $p): ?>
                        <option value="<?= htmlspecialchars($p['part_no']) ?>"><?= htmlspecialchars($p['part_no']) ?> — <?= htmlspecialchars($p['part_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="qty[]" min="1" required></td>
            <td><button type="button" onclick="addRow()">➕</button></td>
        </tr>

        <!-- Hidden template row used by addRow(); controls disabled so they don't trigger validation while hidden -->
        <tr id="templateRow" style="display:none;">
            <td>
                <select name="part_no[]" disabled>
                    <option value="">Select Part</option>
                    <?php foreach ($parts as $p): ?>
                        <option value="<?= htmlspecialchars($p['part_no']) ?>"><?= htmlspecialchars($p['part_no']) ?> — <?= htmlspecialchars($p['part_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="qty[]" min="1" disabled></td>
            <td><button type="button" onclick="removeRow(this)">➖</button></td>
        </tr>
    </table>

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

    <button type="submit" class="btn btn-primary">Create PO</button>
</form>

<hr>

<!-- =========================
     PURCHASE ORDER LIST (grouped)
========================= -->
<table>
    <tr>
        <th>PO No</th>
        <th>Parts</th>
        <th>Supplier</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($orders as $o): ?>
    <tr>
        <td><?= htmlspecialchars($o['po_no']) ?></td>
        <td>
            <?php $partsList = $o['items'] ? explode('|||', $o['items']) : []; ?>
            <ul style="margin:0;padding-left:18px;">
            <?php foreach ($partsList as $pitem):
                list($lineId, $partNo, $partName, $partQty) = explode('::', $pitem);
            ?>
                <li>
                    <?= htmlspecialchars($partNo) ?> — <?= htmlspecialchars($partName) ?> (Qty: <?= htmlspecialchars($partQty) ?>)
                    &nbsp; <a href="edit.php?id=<?= $lineId ?>">Edit</a>
                </li>
            <?php endforeach; ?>
            </ul>
        </td>
        <td><?= htmlspecialchars($o['supplier_name']) ?></td>
        <td><?= $o['purchase_date'] ?></td>
        <td><?= htmlspecialchars(implode(', ', explode(',', $o['status_list']))) ?></td>
        <td>
            <?php if (strpos($o['status_list'], 'open') !== false): ?>
                <a class="btn btn-danger" href="cancel.php?po_no=<?= urlencode($o['po_no']) ?>" onclick="return confirm('Cancel this PO?')">Cancel PO</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<script>
function addRow() {
    const tpl = document.getElementById('templateRow');
    if (!tpl) return;
    const clone = tpl.cloneNode(true);
    clone.removeAttribute('id');
    clone.style.display = '';

    // enable and clear inputs/selects
    const sel = clone.querySelector('select[name="part_no[]"]');
    const qty = clone.querySelector('input[name="qty[]"]');
    if (sel) { sel.disabled = false; sel.required = true; sel.selectedIndex = 0; }
    if (qty) { qty.disabled = false; qty.required = true; qty.value = ''; }

    tpl.parentNode.insertBefore(clone, tpl);
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    const table = document.getElementById('poTable');
    const visibleRows = Array.from(table.querySelectorAll('tr')).filter(r => r.style.display !== 'none' && r.querySelector('select[name="part_no[]"]'));
    if (visibleRows.length <= 1) {
        // clear instead of removing last row
        const sel = tr.querySelector('select[name="part_no[]"]');
        const qty = tr.querySelector('input[name="qty[]"]');
        if (sel) sel.selectedIndex = 0;
        if (qty) qty.value = '';
        return;
    }
    tr.remove();
}
</script>
