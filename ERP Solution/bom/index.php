<?php
include "../db.php";
include "../includes/sidebar.php";

$boms = $pdo->query("
    SELECT b.id, b.bom_no, b.description, b.status,
           p.part_name
    FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    ORDER BY b.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>BOM</title>
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
    <h1>Bill of Materials</h1>

    <a href="add.php" class="btn btn-primary">➕ Add BOM</a>

    <table border="1" cellpadding="8">
        <tr>
            <th>BOM No</th>
            <th>Parent Part</th>
            <th>Description</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php while ($b = $boms->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($b['bom_no']) ?></td>
            <td><?= htmlspecialchars($b['part_name']) ?></td>
            <td><?= htmlspecialchars($b['description']) ?></td>
            <td><?= htmlspecialchars($b['status']) ?></td>
            <td>
                <a href="view.php?id=<?= $b['id'] ?>">View</a>
                <?php if ($b['status'] === 'active'): ?>
                    | <a class="btn btn-secondary" href="edit.php?id=<?= $b['id'] ?>">Edit</a>
                    | <a class="btn btn-secondary" href="deactivate.php?id=<?= $b['id'] ?>"
                         onclick="return confirm('Deactivate BOM?')">Deactivate</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
