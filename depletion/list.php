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
    SELECT COUNT(*) FROM depletion
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT p.part_name, d.part_no, d.qty, d.issue_date, d.reason
    FROM depletion d
    JOIN part_master p ON p.part_no = d.part_no
    ORDER BY d.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Depletion</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Depletion Records</h1>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Part</th>
            <th>Part No</th>
            <th>Qty</th>
            <th>Date</th>
            <th>Reason</th>
        </tr>

        <?php while ($row = $stmt->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= $row['part_no'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['issue_date'] ?></td>
            <td><?= htmlspecialchars($row['reason']) ?></td>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total depletion records)
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
