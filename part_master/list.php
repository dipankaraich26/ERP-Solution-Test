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

    <table border="1" cellpadding="8">
        <tr>
            <th>Part ID</th>
            <th>Part Name</th>
            <th>Part No</th>
            <th>Category</th>
            <th>Description</th>
            <th>UOM</th>
            <th>Rate</th>
            <th>GST</th>
            <th>Actions</th>
        </tr>

        <?php
        $stmt = $pdo->query("
            SELECT * FROM part_master
            WHERE status='active'
            ORDER BY part_no
        ");

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
            <td><?= htmlspecialchars($row['gst']) ?></td>
            <td>
                
                <a class="btn btn-secondary" href="edit.php?part_no=<?= $row['part_no'] ?>">Edit</a> |
                <a class="btn btn-secondary" href="deactivate.php?id=<?= $row['id'] ?>"
                    onclick="return confirm('Deactivate this part?')">
                    Deactivate
                </a>

            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
