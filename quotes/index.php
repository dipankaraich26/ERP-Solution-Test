<?php
include "../db.php";
include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("SELECT COUNT(*) FROM quote_master")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Fetch quotations with customer info
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name,
           (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = q.id) as total_value
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    ORDER BY q.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$quotes = $stmt;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quotations</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Quotations</h1>

    <a href="add.php" class="btn btn-primary">+ Add Quotation</a>

    <div style="overflow-x: auto; margin-top: 20px;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Quote No</th>
            <th>Customer</th>
            <th>Reference</th>
            <th>Quote Date</th>
            <th>Validity</th>
            <th>Total Value</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($q = $quotes->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($q['quote_no']) ?></td>
            <td>
                <?= htmlspecialchars($q['company_name'] ?? '') ?>
                <?php if ($q['customer_name']): ?>
                    (<?= htmlspecialchars($q['customer_name']) ?>)
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($q['reference'] ?? '') ?></td>
            <td><?= htmlspecialchars($q['quote_date']) ?></td>
            <td><?= htmlspecialchars($q['validity_date'] ?? '-') ?></td>
            <td style="text-align: right;"><?= number_format($q['total_value'] ?? 0, 2) ?></td>
            <td>
                <span class="status-badge status-<?= $q['status'] ?>">
                    <?= ucfirst($q['status']) ?>
                </span>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="view.php?id=<?= $q['id'] ?>">View</a>
                <a class="btn btn-secondary" href="edit.php?id=<?= $q['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px;">No quotations found. Click "Add Quotation" to create one.</td>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total quotations)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: bold;
}
.status-draft { background: #ffc107; color: #000; }
.status-sent { background: #17a2b8; color: #fff; }
.status-accepted { background: #28a745; color: #fff; }
.status-rejected { background: #dc3545; color: #fff; }
.status-expired { background: #6c757d; color: #fff; }
</style>

</body>
</html>
