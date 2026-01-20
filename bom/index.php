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
    SELECT COUNT(*) FROM bom_master
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT b.id, b.bom_no, b.description, b.status,
           p.part_name
    FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    ORDER BY b.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
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

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>BOM No</th>
            <th>Parent Part</th>
            <th>Description</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php while ($b = $stmt->fetch()): ?>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total BOMs)
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
