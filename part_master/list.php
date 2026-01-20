<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

<?php if (isset($_GET['error']) && $_GET['error'] === 'used'): ?>
<script>
alert("Cannot delete part: it is used in inventory or transactions.");
</script>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
<script>
alert("Invalid part selected.");
</script>
<?php endif; ?>


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
    SELECT COUNT(*) FROM part_master WHERE status='active'
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Part Master</title>
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
    <h1>Part Master</h1>

    <a href="add.php" class="btn btn-primary">Add Part</a>
    <a href="inactive.php" class="btn btn-primary">View Inactive Parts</a><br><br>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Part ID</th>
            <th>Part Name</th>
            <th>Part No</th>
            <th>Category</th>
            <th>Description</th>
            <th>UOM</th>
            <th>Rate</th>
            <th>HSN</th>
            <th>GST</th>
            <th>Actions</th>
        </tr>

        <?php
        $stmt = $pdo->prepare("
            SELECT * FROM part_master
            WHERE status='active'
            ORDER BY part_no
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
        ?>
        <tr>
            <td><?= htmlspecialchars($row['part_id']) ?></td>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= htmlspecialchars($row['part_no']) ?></td>
            <td><?= htmlspecialchars($row['category']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= htmlspecialchars($row['uom']) ?></td>
            <td><?= htmlspecialchars($row['rate']) ?></td>
            <td><?= htmlspecialchars($row['hsn_code']) ?></td>
            <td><?= htmlspecialchars($row['gst']) ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="edit.php?part_no=<?= $row['part_no'] ?>">Edit</a> |
                <a class="btn btn-secondary" href="suppliers.php?part_no=<?= urlencode($row['part_no']) ?>">Suppliers</a> |
                <a class="btn btn-secondary" href="min_stock.php?part_no=<?= urlencode($row['part_no']) ?>">Stock</a>

                <?php if (!empty($row['attachment_path'])): ?>
                    | <a class="btn btn-secondary"
                    href="../<?= htmlspecialchars($row['attachment_path']) ?>"
                    target="_blank">
                    PDF
                    </a>
                <?php endif; ?>

                | <a class="btn btn-secondary"
                href="deactivate.php?id=<?= $row['id'] ?>"
                onclick="return confirm('Deactivate this part?')">
                Deactivate
                </a>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total parts)
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
