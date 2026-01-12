<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid Work Order ID");
}

/* --- Fetch Work Order Header --- */
$woStmt = $pdo->prepare("
    SELECT w.wo_no, w.qty, w.status,
           p.part_name,
           b.id AS bom_id, b.bom_no, b.description
    FROM work_orders w
    JOIN part_master p ON w.part_no = p.part_no
    JOIN bom_master b ON w.bom_id = b.id
    WHERE w.id = ?
");
$woStmt->execute([$id]);
$wo = $woStmt->fetch();

if (!$wo) {
    die("Work Order not found");
}

/* --- Fetch BOM Items for this WO --- */
$itemsStmt = $pdo->prepare("
    SELECT i.qty, p.part_name
    FROM bom_items i
    JOIN part_master p ON i.component_part_no = p.part_no
    WHERE i.bom_id = ?
");
$itemsStmt->execute([$wo['bom_id']]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Work Order</title>
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
    <h1>Work Order <?= htmlspecialchars($wo['wo_no']) ?></h1>

    <p><strong>Product:</strong> <?= htmlspecialchars($wo['part_name']) ?></p>
    <p><strong>BOM:</strong> <?= htmlspecialchars($wo['bom_no']) ?></p>
    <p><strong>Quantity:</strong> <?= htmlspecialchars($wo['qty']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($wo['status']) ?></p>
    <p><strong>Description:</strong>
    <?php if (!empty($wo['description'])): ?>
        <p><?= htmlspecialchars($wo['description']) ?></p>
    <?php endif; ?>
    </p>

    <h2>BOM Components</h2>

    <table border="1" cellpadding="8">
        <tr>
            <th>Component</th>
            <th>Qty per Assembly</th>
            <th>Total Required</th>
        </tr>

        <?php while ($i = $itemsStmt->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($i['part_name']) ?></td>
            <td><?= htmlspecialchars($i['qty']) ?></td>
            <td><?= htmlspecialchars($i['qty'] * $wo['qty']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <br>
    <a href="index.php" class="btn btn-secondary">⬅ Back to Work Orders</a>
</div>

</body>
</html>
