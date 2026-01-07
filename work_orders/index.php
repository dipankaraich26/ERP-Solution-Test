<?php
include "../db.php";
include "../includes/sidebar.php";

$wos = $pdo->query("
    SELECT w.id, w.wo_no, w.qty, w.status,
           p.part_name, b.bom_no
    FROM work_orders w
    JOIN part_master p ON w.part_no = p.part_no
    JOIN bom_master b ON w.bom_id = b.id
    ORDER BY w.id DESC
");
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

<table border="1" cellpadding="8">
<tr>
    <th>WO No</th>
    <th>Product</th>
    <th>BOM</th>
    <th>Qty</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php while ($w = $wos->fetch()): ?>
<tr>
    <td><?= $w['wo_no'] ?></td>
    <td><?= $w['part_name'] ?></td>
    <td><?= $w['bom_no'] ?></td>
    <td><?= $w['qty'] ?></td>
    <td><?= $w['status'] ?></td>
    <td>
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
</body>
</html>
