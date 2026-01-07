<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>


<?php
include "../db.php";
include "../includes/sidebar.php";

$inv = $pdo->query("
    SELECT i.part_no, p.part_name, i.qty
    FROM inventory i
    JOIN part_master p ON p.part_no = i.part_no
    ORDER BY p.part_name
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
<h1>Inventory</h1>

<table>
<tr>
    <th>Part No</th>
    <th>Name</th>
    <th>Qty</th>
</tr>

<?php while ($r = $inv->fetch()): ?>
<tr>
    <td><?= htmlspecialchars($r['part_no']) ?></td>
    <td><?= htmlspecialchars($r['part_name']) ?></td>
    <td><?= $r['qty'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
