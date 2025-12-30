<html><head><link rel="stylesheet" href="/erp/assets/style.css"></head></html>

<?php
require "../db.php";
require "../includes/sidebar.php";

$error = "";

/* =========================
   HANDLE ADD PURCHASE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplier_id   = $_POST['supplier_id'];
    $purchase_date = $_POST['purchase_date'];
    $invoice_no    = $_POST['invoice_no'];

    $part_nos = $_POST['part_no'];
    $qtys     = $_POST['qty'];

    $pdo->beginTransaction();

    try {
        /* 🔹 Generate PO Number (ONCE) */
        $lastId = $pdo->query("SELECT MAX(id) FROM purchase_orders")->fetchColumn();
        $nextId = $lastId ? $lastId + 1 : 1;
        $po_no  = 'PO-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

        foreach ($part_nos as $i => $part_no) {

            $part_no = trim($part_no);
            $qty     = (int)$qtys[$i];

            if ($part_no === '' || $qty <= 0) {
                continue;
            }

            // Insert purchase line
            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_no, part_no, supplier_id, qty, purchase_date, invoice_no, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $po_no,
                $part_no,
                $supplier_id,
                $qty,
                $purchase_date,
                $invoice_no
            ]);

            // Update inventory
            $stmt = $pdo->prepare("
                UPDATE inventory
                SET qty = qty + ?
                WHERE part_no = ?
            ");
            $stmt->execute([$qty, $part_no]);
        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Purchase failed";
    }
}

/* =========================
   DROPDOWN DATA
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
   PURCHASE TABLE
========================= */
$purchases = $pdo->query("
    SELECT 
        po.po_no,
        po.id,
        po.part_no,
        po.qty,
        po.purchase_date,
        po.invoice_no,
        po.status,
        pm.part_name,
        s.supplier_name
    FROM purchase_orders po
    JOIN part_master pm ON pm.part_no = po.part_no
    JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Purchase</title>
    <link rel="stylesheet" href="/erp/assets/style.css">
</head>
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
</body>
</html>

<body>

<div class="content">
    <h1>Purchase</h1>

    <?php if ($error): ?>
        <script>alert("<?= htmlspecialchars($error) ?>");</script>
    <?php endif; ?>

    <!-- =====================
         ADD PURCHASE FORM
    ====================== -->
    <form method="post">

        <label>Supplier</label>
        <select name="supplier_id" required>
            <option value="">Select Supplier</option>
            <?php while ($s = $suppliers->fetch()): ?>
                <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['supplier_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Date</label>
        <input type="date" name="purchase_date" required>

        <label>Invoice No</label>
        <input type="text" name="invoice_no">

        <hr>

        <table id="itemsTable" border="1" cellpadding="6">
            <tr>
                <th>Part</th>
                <th>Qty</th>
                <th>Action</th>
            </tr>

            <tr>
                <td>
                    <select name="part_no[]" required>
                        <option value="">Select</option>
                        <?php
                        $parts->execute();
                        while ($p = $parts->fetch()):
                        ?>
                            <option value="<?= $p['part_no'] ?>">
                                <?= htmlspecialchars($p['part_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="qty[]" min="1" required>
                </td>
                <td>—</td>
            </tr>
        </table>

        <br>
        <button type="button" onclick="addRow()">+ Add Another Part</button>
        <br><br>

        <button type="submit">Add Purchase</button>
    </form>

    <hr>

    <!-- =====================
         PURCHASE LIST
    ====================== -->
    <table border="1" cellpadding="8">
        <tr>
            <th>PO No</th>
            <th>Part</th>
            <th>Part No</th>
            <th>Supplier</th>
            <th>Qty</th>
            <th>Date</th>
            <th>Invoice</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php while ($row = $purchases->fetch()): ?>
        <tr>
            <td><?= $row['po_no'] ?></td>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= $row['part_no'] ?></td>
            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['purchase_date'] ?></td>
            <td><?= htmlspecialchars($row['invoice_no']) ?></td>
            <td><?= ucfirst($row['status']) ?></td>
            <td>
                <?php if ($row['status'] === 'active'): ?>
                    <a href="cancel.php?po_no=<?= $row['po_no'] ?>"
                       onclick="return confirm('Cancel entire PO?')">Cancel PO</a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<script>
function addRow() {
    const table = document.getElementById('itemsTable');
    const row = table.insertRow();

    row.innerHTML = `
        <td>
            <select name="part_no[]" required>
                <option value="">Select</option>
                <?php
                $stmt = $pdo->query("
                    SELECT part_no, part_name
                    FROM part_master
                    WHERE status='active'
                    ORDER BY part_name
                ");
                while ($p = $stmt->fetch()):
                ?>
                    <option value="<?= $p['part_no'] ?>">
                        <?= htmlspecialchars($p['part_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </td>
        <td>
            <input type="number" name="qty[]" min="1" required>
        </td>
        <td>
            <button type="button" onclick="this.closest('tr').remove()">Remove</button>
        </td>
    `;
}
</script>

</body>
</html>
