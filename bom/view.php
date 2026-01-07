<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'];

$bom = $pdo->prepare("
    SELECT b.bom_no, b.description, b.status, p.part_name
    FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    WHERE b.id=?
");
$bom->execute([$id]);
$bom = $bom->fetch();

$items = $pdo->prepare("
    SELECT i.qty, p.part_name
    FROM bom_items i
    JOIN part_master p ON i.component_part_no = p.part_no
    WHERE i.bom_id=?
");
$items->execute([$id]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View BOM</title>
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
    <h1>BOM <?= htmlspecialchars($bom['bom_no']) ?></h1>

    <p><strong>Parent:</strong> <?= htmlspecialchars($bom['part_name']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($bom['status']) ?></p>
    <p><?= htmlspecialchars($bom['description']) ?></p>

    <table border="1" cellpadding="8">
        <tr>
            <th>Component</th>
            <th>Qty</th>
        </tr>
        <?php while ($i = $items->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($i['part_name']) ?></td>
            <td><?= $i['qty'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
