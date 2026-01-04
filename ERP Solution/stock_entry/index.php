<?php
include "../db.php";
include "../includes/sidebar.php";

$pos = $pdo->query("
    SELECT p.id, p.po_no, p.qty, p.status,
           pm.part_name
    FROM purchase_orders p
    JOIN part_master pm ON p.part_no = pm.part_no
    WHERE p.status != 'closed'
    ORDER BY p.id DESC
");
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

<table border="1" cellpadding="8">
<tr>
    <th>PO No</th>
    <th>Part</th>
    <th>Ordered Qty</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php while ($p = $pos->fetch()): ?>
<tr>
    <td><?= $p['po_no'] ?></td>
    <td><?= $p['part_name'] ?></td>
    <td><?= $p['qty'] ?></td>
    <td><?= $p['status'] ?></td>
    <td>
        <a class="btn btn-secondary" href="add.php?po_id=<?= $p['id'] ?>">Receive</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
</div>

</body>
</html>
