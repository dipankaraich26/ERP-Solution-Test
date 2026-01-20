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
    SELECT COUNT(*) FROM customers
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT customer_id, company_name, customer_name, contact, email,
           address1, address2, city, pincode, state, gstin, status
    FROM customers
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt;
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

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Customer ID</th>
            <th>Company</th>
            <th>Customer Name</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Address 1</th>
            <th>Address 2</th>
            <th>City</th>
            <th>Pincode</th>
            <th>State</th>
            <th>GSTIN</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($c = $customers->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($c['customer_id']) ?></td>
            <td><?= htmlspecialchars($c['company_name']) ?></td>
            <td><?= htmlspecialchars($c['customer_name']) ?></td>
            <td><?= htmlspecialchars($c['contact']) ?></td>
            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['address1']) ?></td>
            <td><?= htmlspecialchars($c['address2']) ?></td>
            <td><?= htmlspecialchars($c['city']) ?></td>
            <td><?= htmlspecialchars($c['pincode']) ?></td>
            <td><?= htmlspecialchars($c['state']) ?></td>
            <td><?= htmlspecialchars($c['gstin']) ?></td>
            <td><?= htmlspecialchars($c['status']) ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="edit.php?customer_id=<?= urlencode($c['customer_id']) ?>">Edit</a>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total customers)
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
