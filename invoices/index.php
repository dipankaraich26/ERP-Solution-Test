<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of invoices
$total_count = $pdo->query("SELECT COUNT(*) FROM invoice_master")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

/* =========================
   FETCH INVOICES WITH RELATED DATA
   Invoice -> SO -> Customer PO -> PI
========================= */
$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.invoice_no,
        i.so_no,
        i.invoice_date,
        i.released_at,
        i.status,
        so.customer_po_id,
        so.linked_quote_id,
        c.company_name,
        c.customer_name,
        cp.po_no as customer_po_no,
        q.pi_no,
        q.validity_date,
        -- Get total value from quote items
        (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value
    FROM invoice_master i
    LEFT JOIN (
        SELECT DISTINCT so_no, customer_po_id, linked_quote_id, customer_id
        FROM sales_orders
    ) so ON so.so_no = i.so_no
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tax Invoices</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-draft { background: #ffc107; color: #000; }
        .status-released { background: #28a745; color: #fff; }
    </style>
</head>
<body>

<div class="content">
    <h1>Tax Invoices</h1>

    <p style="margin-bottom: 20px;">
        <a href="add.php" class="btn btn-primary">+ Generate Invoice</a>
    </p>

    <!-- Search Box -->
    <div style="margin-bottom: 15px;">
        <input type="text" id="searchInput" placeholder="Search by Invoice No, SO No, Customer, PO..."
               style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 350px;">
    </div>

    <hr>

    <!-- =========================
         INVOICE LIST
    ========================= -->
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Invoice No</th>
            <th>SO No</th>
            <th>Customer PO</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Validity</th>
            <th>Total Value</th>
            <th>Released At</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($invoices as $inv): ?>
        <tr>
            <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
            <td><?= htmlspecialchars($inv['so_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inv['customer_po_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inv['company_name'] ?? $inv['customer_name'] ?? '-') ?></td>
            <td><?= $inv['invoice_date'] ?></td>
            <td><?= $inv['validity_date'] ?? '-' ?></td>
            <td style="text-align: right;">
                <?= $inv['total_value'] ? number_format($inv['total_value'], 2) : '-' ?>
            </td>
            <td>
                <?= $inv['released_at'] ? date('Y-m-d H:i', strtotime($inv['released_at'])) : '-' ?>
            </td>
            <td>
                <span class="status-badge status-<?= $inv['status'] ?>">
                    <?= ucfirst($inv['status']) ?>
                </span>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="view.php?id=<?= $inv['id'] ?>">View</a>
                <?php if ($inv['status'] === 'draft'): ?>
                    <a class="btn btn-success"
                       href="release.php?id=<?= $inv['id'] ?>"
                       onclick="return confirm('Release this Invoice?\n\nInventory will be deducted for all associated parts.')">
                        Release
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($invoices)): ?>
        <tr>
            <td colspan="10" style="text-align: center; padding: 20px;">No invoices found.</td>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total invoices)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

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
