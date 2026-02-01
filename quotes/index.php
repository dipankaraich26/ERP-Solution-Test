<?php
include "../db.php";
include "../includes/sidebar.php";

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    try {
        $pdo->beginTransaction();

        // Delete quote items first
        $stmt = $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?");
        $stmt->execute([$delete_id]);

        // Delete the quote
        $stmt = $pdo->prepare("DELETE FROM quote_master WHERE id = ?");
        $stmt->execute([$delete_id]);

        $pdo->commit();

        header("Location: index.php?deleted=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $delete_error = "Failed to delete quotation: " . $e->getMessage();
    }
}

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

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            Quotation has been deleted successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($delete_error)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($delete_error) ?>
        </div>
    <?php endif; ?>

    <a href="add.php" class="btn btn-primary">+ Add Quotation</a>

    <!-- Search Box -->
    <div style="margin: 15px 0;">
        <input type="text" id="searchInput" placeholder="Search by Quote No, Customer, Reference..."
               style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 350px;">
    </div>

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
                <a class="btn btn-danger" href="index.php?delete=<?= $q['id'] ?>" onclick="return confirm('Are you sure you want to delete this quotation (<?= htmlspecialchars($q['quote_no']) ?>)? This action cannot be undone.');">Delete</a>
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

.btn-danger {
    background: #dc3545;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9em;
}
.btn-danger:hover {
    background: #c82333;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tr:not(:first-child)');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let found = false;

            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                    found = true;
                }
            });

            row.style.display = found ? '' : 'none';
        });
    });
});
</script>

</body>
</html>
