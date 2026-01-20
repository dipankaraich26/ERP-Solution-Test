<?php
include "../db.php";
include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("
    SELECT COUNT(*) FROM work_orders
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT w.id, w.wo_no, w.qty, w.status, w.created_at,
           p.part_name, b.bom_no
    FROM work_orders w
    JOIN part_master p ON w.part_no = p.part_no
    JOIN bom_master b ON w.bom_id = b.id
    ORDER BY w.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Work Orders</title>
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
<h1>Work Orders</h1>

<a href="add.php" class="btn btn-primary">➕ Create Work Order</a>

<div style="overflow-x: auto;">
<table border="1" cellpadding="8">
<tr>
    <th>WO No</th>
    <th>Product</th>
    <th>BOM</th>
    <th>Qty</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while ($w = $stmt->fetch()): ?>
<tr>
    <td><?= $w['wo_no'] ?></td>
    <td><?= $w['part_name'] ?></td>
    <td><?= $w['bom_no'] ?></td>
    <td><?= $w['qty'] ?></td>
    <td><?= $w['status'] ?></td>
    <td><?= date('Y-m-d', strtotime($w['created_at'])) ?></td>
    <td style="white-space: nowrap;">
        <a class="btn btn-secondary" href="view.php?id=<?= $w['id'] ?>">View</a>
        <?php if ($w['status']==='created'): ?>
            | <a class="btn btn-secondary" href="release.php?id=<?= $w['id'] ?>"
                 onclick="return confirm('Issue materials?')">Release</a>
            | <a class="btn btn-secondary" href="cancel.php?id=<?= $w['id'] ?>">Cancel</a>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total work orders)
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
