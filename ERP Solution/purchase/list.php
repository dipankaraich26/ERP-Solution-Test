<?php
include "../db.php";
include "../includes/sidebar.php";

$stmt = $pdo->query("
    SELECT p.part_name, po.part_no, po.qty, po.purchase_date, po.invoice_no
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    ORDER BY po.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Purchase List</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Purchase Orders</h1>

    <table border="1" cellpadding="8">
        <tr>
            <th>Part</th>
            <th>Part No</th>
            <th>Qty</th>
            <th>Date</th>
            <th>Invoice</th>
        </tr>

        <?php while ($row = $stmt->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= $row['part_no'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td><?= $row['purchase_date'] ?></td>
            <td><?= $row['invoice_no'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
