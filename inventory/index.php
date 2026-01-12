<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>


<?php
include "../db.php";
include "../includes/sidebar.php";

$view = $_GET['view'] ?? 'normal';

if ($view === 'zero') {
    $stmt = $pdo->prepare("
        SELECT i.part_no, p.part_name, i.qty
        FROM inventory i
        JOIN part_master p ON p.part_no = i.part_no
        WHERE i.qty = 0
        ORDER BY p.part_name
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT i.part_no, p.part_name, i.qty
        FROM inventory i
        JOIN part_master p ON p.part_no = i.part_no
        WHERE i.qty > 0
        ORDER BY p.part_name
    ");
}

$stmt->execute();
$inv = $stmt;
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

<div style="margin-bottom: 12px;">
<?php if ($view === 'zero'): ?>
    <a href="index.php" class="btn">Show Available Stock</a>
<?php else: ?>
    <a href="index.php?view=zero" class="btn">Show Zero Stock Data</a>
<?php endif; ?>
</div>


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
