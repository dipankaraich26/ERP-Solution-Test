<?php
include "../db.php";
include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("SELECT COUNT(*) FROM customer_po")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Fetch customer POs
$stmt = $pdo->prepare("
    SELECT cp.*, c.company_name, c.customer_name,
           q.pi_no
    FROM customer_po cp
    LEFT JOIN customers c ON cp.customer_id = c.customer_id
    LEFT JOIN quote_master q ON cp.linked_quote_id = q.id
    ORDER BY cp.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$pos = $stmt;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Purchase Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-active { background: #28a745; color: #fff; }
        .status-completed { background: #17a2b8; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
    </style>
</head>
<body>

<div class="content">
    <h1>Customer Purchase Orders</h1>

    <a href="add.php" class="btn btn-primary">+ Upload Customer PO</a>

    <div style="overflow-x: auto; margin-top: 20px;">
    <table border="1" cellpadding="8">
        <tr>
            <th>PO No</th>
            <th>Customer</th>
            <th>PO Date</th>
            <th>Linked PI</th>
            <th>Status</th>
            <th>PDF</th>
            <th>Actions</th>
        </tr>

        <?php while ($po = $pos->fetch()): ?>
        <tr>
            <td><strong><?= htmlspecialchars($po['po_no']) ?></strong></td>
            <td>
                <?= htmlspecialchars($po['company_name'] ?? '') ?>
                <?php if ($po['customer_name']): ?>
                    (<?= htmlspecialchars($po['customer_name']) ?>)
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($po['po_date'] ?? '-') ?></td>
            <td><?= htmlspecialchars($po['pi_no'] ?? '-') ?></td>
            <td>
                <span class="status-badge status-<?= $po['status'] ?>">
                    <?= ucfirst($po['status']) ?>
                </span>
            </td>
            <td>
                <?php if ($po['attachment_path']): ?>
                    <a href="../<?= htmlspecialchars($po['attachment_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 2px 8px; font-size: 0.85em;">View PDF</a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="view.php?id=<?= $po['id'] ?>">View</a>
                <a class="btn btn-secondary" href="edit.php?id=<?= $po['id'] ?>">Edit</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_count == 0): ?>
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">No Customer POs found. Click "Upload Customer PO" to add one.</td>
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
