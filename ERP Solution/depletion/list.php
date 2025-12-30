<?php
include "../db.php";
include "../includes/sidebar.php";

$stmt = $pdo->query("
    SELECT p.part_name, d.part_no, d.qty, d.issue_date, d.reason
    FROM depletion d
    JOIN part_master p ON p.part_no = d.part_no
    ORDER BY d.id DESC
");
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

</body>
</html>
