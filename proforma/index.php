<?php
include "../db.php";
include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of released quotations (PIs)
$total_count = $pdo->query("SELECT COUNT(*) FROM quote_master WHERE status = 'released'")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Fetch released quotations (Proforma Invoices)
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name,
           (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = q.id) as total_value
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    WHERE q.status = 'released'
    ORDER BY q.released_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Proforma Invoices</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Proforma Invoices</h1>

    <p style="color: #666; margin-bottom: 20px;">
        Proforma Invoices are created by releasing Quotations. Go to <a href="/quotes/index.php">Quotations</a> to create and release new PIs.
    </p>

    <div style="overflow-x: auto; margin-top: 20px;">
    <table border="1" cellpadding="8">
        <tr>
            <th>PI No</th>
            <th>Quote No</th>
            <th>Customer</th>
            <th>Reference</th>
            <th>Quote Date</th>
            <th>Released At</th>
            <th>Total Value</th>
            <th>Actions</th>
        </tr>

        <?php while ($pi = $invoices->fetch()): ?>
        <tr>
            <td><strong><?= htmlspecialchars($pi['pi_no']) ?></strong></td>
            <td><?= htmlspecialchars($pi['quote_no']) ?></td>
            <td>
                <?= htmlspecialchars($pi['company_name'] ?? '') ?>
                <?php if ($pi['customer_name']): ?>
                    (<?= htmlspecialchars($pi['customer_name']) ?>)
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($pi['reference'] ?? '') ?></td>
            <td><?= htmlspecialchars($pi['quote_date']) ?></td>
            <td><?= htmlspecialchars($pi['released_at'] ?? '-') ?></td>
            <td style="text-align: right;"><?= number_format($pi['total_value'] ?? 0, 2) ?></td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="view.php?id=<?= $pi['id'] ?>">View</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px;">No Proforma Invoices found. Release a Quotation to create one.</td>
        </tr>
        <?php endif; ?>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total)
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
