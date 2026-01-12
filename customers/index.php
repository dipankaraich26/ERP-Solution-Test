<?php
include "../db.php";
include "../includes/sidebar.php";

$customers = $pdo->query("
    SELECT customer_id, company_name, customer_name, contact,
           state, gstin, status
    FROM customers
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customers</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

<div class="content">
    <h1>Customers</h1>

    <a href="add.php" class="btn btn-primary">➕ Add Customer</a>

    <table border="1" cellpadding="8">
        <tr>
            <th>Customer ID</th>
            <th>Company</th>
            <th>Customer Name</th>
            <th>Contact</th>
            <th>State</th>
            <th>GSTIN</th>
            <th>Status</th>
        </tr>

        <?php while ($c = $customers->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($c['customer_id']) ?></td>
            <td><?= htmlspecialchars($c['company_name']) ?></td>
            <td><?= htmlspecialchars($c['customer_name']) ?></td>
            <td><?= htmlspecialchars($c['contact']) ?></td>
            <td><?= htmlspecialchars($c['state']) ?></td>
            <td><?= htmlspecialchars($c['gstin']) ?></td>
            <td><?= htmlspecialchars($c['status']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
