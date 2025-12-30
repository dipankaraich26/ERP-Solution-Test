<html><head><link rel="stylesheet" href="/erp/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";

$stmt = $pdo->query("
    SELECT p.part_name, p.part_no, i.qty
    FROM inventory i
    JOIN part_master p ON p.part_no = i.part_no
    ORDER BY p.part_name
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory</title>
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
</body>
</html>

<body>

<div class="content">
    <h1>Inventory</h1>

    <table border="1" cellpadding="8">
        <tr>
            <th>Part Name</th>
            <th>Part No</th>
            <th>Qty</th>
        </tr>

        <?php while ($row = $stmt->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= $row['part_no'] ?></td>
            <td><?= $row['qty'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
