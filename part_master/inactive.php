<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";

$parts = $pdo->query("
    SELECT * FROM part_master
    WHERE status='inactive'
");
?>

<div class="content">
    <h2>Inactive Parts</h2>

    <table border="1">
    <tr>
        <th>Part No</th>
        <th>Name</th>
        <th>Action</th>
    </tr>

    <?php while ($p = $parts->fetch()): ?>
    <tr>
        <td><?= htmlspecialchars($p['part_no']) ?></td>
        <td><?= htmlspecialchars($p['part_name']) ?></td>
        <td>
            <a href="reactivate.php?id=<?= $p['id'] ?>" class="btn btn-secondary">Reactivate</a>
            | <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-danger"
               onclick="return confirm('Are you sure you want to DELETE this part permanently? This cannot be undone.')">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
    </table>
</div>
