<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

/* =========================
   FETCH INVENTORY PARTS
========================= */
$parts = $pdo->query("
    SELECT i.part_no, p.part_name, i.qty AS stock_qty
    FROM inventory i
    JOIN part_master p ON p.part_no = i.part_no
    ORDER BY p.part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH CUSTOMERS
========================= */
$customers = $pdo->query("
    SELECT id, company_name, customer_name
    FROM customers
    WHERE status='active'
    ORDER BY company_name
");

/* =========================
   HANDLE SALES ORDER CREATE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $maxNo = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(so_no,4) AS UNSIGNED)),0)
        FROM sales_orders WHERE so_no LIKE 'SO-%'
    ")->fetchColumn();

    $so_no = 'SO-' . ((int)$maxNo + 1);

    $parts_post = $_POST['part_no'] ?? [];
    $qtys       = $_POST['qty'] ?? [];
    $customer   = (int)($_POST['customer_id'] ?? 0);
    $date       = $_POST['sales_date'] ?? '';

    $items = [];
    for ($i=0; $i<count($parts_post); $i++) {
        if (!$parts_post[$i]) continue;
        $items[] = [
            'part_no' => $parts_post[$i],
            'qty'     => (int)$qtys[$i]
        ];
    }

    if (!$items || !$customer || !$date) {
        setModal("Failed", "Customer, date and at least one part required");
        header("Location: index.php");
        exit;
    }

    foreach ($items as $it) {
        $stmt = $pdo->prepare("
            SELECT qty FROM inventory WHERE part_no = ?
        ");
        $stmt->execute([$it['part_no']]);
        $available = (int)$stmt->fetchColumn();

        if (($available - $it['qty']) < 0) {
            setModal(
                "Stock Blocked",
                "Sales Order cannot be created. Part {$it['part_no']} will deplete stock to zero or below."
            );
            header("Location: index.php");
            exit;
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sales_orders
            (so_no, part_no, qty, sales_date, customer_id)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $it) {
            $stmt->execute([
                $so_no,
                $it['part_no'],
                $it['qty'],
                $date,
                $customer
            ]);


        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", "Sales Order creation failed");
        header("Location: index.php");
        exit;
    }
}

/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of grouped sales orders
$total_count = $pdo->query("
    SELECT COUNT(DISTINCT so_no) FROM sales_orders
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

/* =========================
   FETCH SALES ORDERS (GROUPED)
========================= */
$stmt = $pdo->prepare("
    SELECT
        so.so_no,
        so.sales_date,
        c.company_name,
        GROUP_CONCAT(
            CONCAT(so.id,'::',so.part_no,'::',p.part_name,'::',so.qty)
            SEPARATOR '|||'
        ) AS items,
        GROUP_CONCAT(DISTINCT so.status) AS status_list,
        MAX(so.id) AS max_id
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    JOIN customers c ON c.id = so.customer_id
    GROUP BY so.so_no, so.sales_date, c.company_name
    ORDER BY so.sales_date DESC, max_id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<script src="/assets/script.js"></script>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

<div class="content">
    <h1>Sales Orders</h1>

    <!-- =========================
         CREATE SALES ORDER
    ========================= -->
    <form method="post" class="form-box" id="soForm">
        <h3>Create Sales Order</h3>

        <label>Sales Order No</label>
        <div><em>Automatically assigned (SO-1, SO-2, ...)</em></div>

        <label>Customer</label>
        <select name="customer_id" required>
            <option value="">Select Customer</option>
            <?php while ($c = $customers->fetch()): ?>
                <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <label>Sales Date</label>
        <input type="date" name="sales_date" required>

        <label>Items</label>
        <table border="1" cellpadding="6" id="soTable">
            <tr>
                <th>Part</th>
                <th>Available</th>
                <th>Qty</th>
                <th></th>
            </tr>

            <!-- First row -->
            <tr>
                <td>
                    <select name="part_no[]" required onchange="checkStock(this)">
                        <option value="">Select Part</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= htmlspecialchars($p['part_no']) ?>"
                                    data-stock="<?= $p['stock_qty'] ?>">
                                <?= htmlspecialchars($p['part_no']) ?> —
                                <?= htmlspecialchars($p['part_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="stock-cell">–</td>
                <td>
                    <input type="number" name="qty[]" min="1" required>
                </td>
                <td>
                    <button type="button" onclick="addRow()">➕</button>
                </td>
            </tr>

            <!-- Template row -->
            <tr id="templateRow" style="display:none;">
                <td>
                    <select name="part_no[]" disabled onchange="checkStock(this)">
                        <option value="">Select Part</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= htmlspecialchars($p['part_no']) ?>"
                                    data-stock="<?= $p['stock_qty'] ?>">
                                <?= htmlspecialchars($p['part_no']) ?> —
                                <?= htmlspecialchars($p['part_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="stock-cell">–</td>
                <td>
                    <input type="number" name="qty[]" min="1" disabled>
                </td>
                <td>
                    <button type="button" onclick="removeRow(this)">➖</button>
                </td>
            </tr>
        </table>

        <button type="submit" class="btn btn-primary">
            Create Sales Order
        </button>
    </form>

    <hr>

    <!-- =========================
         SALES ORDER LIST
    ========================= -->
    <div style="overflow-x: auto;">
    <table>
        <tr>
            <th>SO No</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['so_no']) ?></td>
            <td><?= htmlspecialchars($o['company_name']) ?></td>
            <td>
                <?php $items = explode('|||', $o['items']); ?>
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($items as $it):
                        list($id,$pn,$name,$qty) = explode('::',$it);
                    ?>
                        <li>
                            <?= htmlspecialchars($pn) ?> —
                            <?= htmlspecialchars($name) ?>
                            (Qty: <?= $qty ?>)
                            <a href="edit.php?id=<?= $id ?>">Edit</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </td>
            <td><?= $o['sales_date'] ?></td>
            <td><?= htmlspecialchars($o['status_list']) ?></td>
            <td>
                <?php if (strpos($o['status_list'],'open') !== false): ?>
                    <a class="btn btn-danger"
                       href="cancel.php?so_no=<?= urlencode($o['so_no']) ?>"
                       onclick="return confirm('Cancel this Sales Order?')">
                        Cancel
                    </a>
                <?php endif; ?> |
                <?php if ($o['status_list'] === 'open'): ?>
                    <a class="btn btn-primary"
                    href="release.php?so_no=<?= urlencode($o['so_no']) ?>"
                    onclick="return confirm('Release this Sales Order? Inventory will be updated.')">
                    Release
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total orders)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- =========================
     JAVASCRIPT
========================= -->
<script>
function addRow() {
    const tpl = document.getElementById('templateRow');
    const clone = tpl.cloneNode(true);
    clone.removeAttribute('id');
    clone.style.display = '';

    const sel = clone.querySelector('select');
    const qty = clone.querySelector('input');

    sel.disabled = false;
    sel.required = true;
    sel.selectedIndex = 0;

    qty.disabled = false;
    qty.required = true;
    qty.value = '';

    tpl.parentNode.insertBefore(clone, tpl);
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    const table = document.getElementById('soTable');

    const activeRows = table.querySelectorAll(
        'select[name="part_no[]"]:not([disabled])'
    );

    if (activeRows.length <= 1) {
        tr.querySelector('select').selectedIndex = 0;
        tr.querySelector('.stock-cell').innerText = '–';
        tr.querySelector('input').value = '';
        return;
    }

    tr.remove();
}

function checkStock(select) {
    const opt = select.selectedOptions[0];
    if (!opt) return;

    const stock = parseInt(opt.dataset.stock || '0', 10);
    const row = select.closest('tr');
    const qtyInput = row.querySelector('input[name="qty[]"]');
    const cell = row.querySelector('.stock-cell');

    cell.innerText = stock;

    qtyInput.oninput = function () {
        const q = parseInt(this.value || '0', 10);
        if ((stock - q) < 0) {
            alert("❌ This quantity will deplete stock completely. Not allowed.");
        }
    };
}

</script>

</body>
</html>
