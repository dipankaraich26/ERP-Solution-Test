<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('stock_entry');
include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of grouped purchase orders (exclude closed and cancelled)
$total_count = $pdo->query("
    SELECT COUNT(DISTINCT po_no)
    FROM purchase_orders
    WHERE status NOT IN ('closed', 'cancelled')
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT
        po.po_no,
        GROUP_CONCAT(CONCAT(po.id, '::', po.part_no, '::', pm.part_name, '::', po.qty) SEPARATOR '|||') AS items,
        GROUP_CONCAT(DISTINCT po.status) AS status_list,
        MAX(po.id) AS max_id
    FROM purchase_orders po
    JOIN part_master pm ON po.part_no = pm.part_no
    WHERE po.status NOT IN ('closed', 'cancelled')
    GROUP BY po.po_no
    ORDER BY max_id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/style.css">
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
<body>

<div class="content">
<h1>Stock Entry (Goods Receipt)</h1>
<a href="history.php" class="btn" style="margin-bottom:8px; display:inline-block;">📜 Stock Entry History</a>

<div style="overflow-x: auto;">
<table border="1" cellpadding="8">
<tr>
    <th>PO No</th>
    <th>Parts</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php foreach ($pos as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['po_no']) ?></td>
    <td>
        <?php
            $items = $p['items'] ? explode('|||', $p['items']) : [];
        ?>
        <ul style="margin:0;padding-left:18px;">
        <?php foreach ($items as $it):
            list($lineId, $partNo, $partName, $qty) = explode('::', $it);
        ?>
            <li>
                <?= htmlspecialchars($partNo) ?> — <?= htmlspecialchars($partName) ?> (<?= htmlspecialchars($qty) ?>)
                &nbsp; <a href="add.php?po_id=<?= $lineId ?>">Receive</a>
            </li>
        <?php endforeach; ?>
        </ul>
    </td>
    <td><?= htmlspecialchars($p['status_list']) ?></td>
    <td>
        <a class="btn btn-primary" href="receive_all.php?po_no=<?= urlencode($p['po_no']) ?>" onclick="return confirm('Receive ALL remaining parts for <?= htmlspecialchars($p['po_no']) ?>?')">Receive All</a>
        &nbsp;
        <a class="btn btn-danger" href="../purchase/cancel.php?po_no=<?= urlencode($p['po_no']) ?>" onclick="return confirm('Cancel this PO?')">Cancel PO</a>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total purchase orders)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
