<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("
    SELECT COUNT(*) FROM stock_entries
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Fetch stock entries with related PO number and part name
$stmt = $pdo->prepare(
    "SELECT se.id, se.po_id, se.part_no, COALESCE(pm.part_name, '') AS part_name, se.received_qty, se.invoice_no, se.status, se.received_date, se.remarks, po.po_no,
            se.invoice_attachment, se.material_photo
     FROM stock_entries se
     LEFT JOIN purchase_orders po ON se.po_id = po.id
     LEFT JOIN part_master pm ON se.part_no = pm.part_no
     ORDER BY se.received_date DESC, se.id DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Stock Entry History</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Stock Entry History</h1>
    <a href="index.php" class="btn">⬅ Back to Stock Entry</a>

    <div style="overflow-x: auto; margin-top:12px;">
    <table border="1" cellpadding="8" style="width:100%;">
        <tr>
            <th>ID</th>
            <th>PO No</th>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Received Qty</th>
            <th>Invoice No</th>
            <th>Remarks</th>
            <th>Status</th>
            <th>Date</th>
            <th>Invoice Attachment</th>
            <th>Material Photo</th>
        </tr>
        <?php foreach ($entries as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['id']) ?></td>
            <td><?= htmlspecialchars($e['po_no']) ?></td>
            <td><?= htmlspecialchars($e['part_no']) ?></td>
            <td><?= htmlspecialchars($e['part_name']) ?></td>
            <td><?= htmlspecialchars($e['received_qty']) ?></td>
            <td><?= htmlspecialchars($e['invoice_no'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($e['remarks'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($e['status']) ?></td>
            <td><?= isset($e['received_date']) ? htmlspecialchars($e['received_date']) : '' ?></td>
            <td>
                <?php if (!empty($e['invoice_attachment'])): ?>
                    <a href="../uploads/stock_entry/<?= htmlspecialchars($e['invoice_attachment']) ?>" target="_blank" title="View Invoice">📄 View</a>
                <?php else: ?>
                    <span style="color:#999;">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($e['material_photo'])): ?>
                    <a href="../uploads/stock_entry/<?= htmlspecialchars($e['material_photo']) ?>" target="_blank" title="View Photo">📷 View</a>
                <?php else: ?>
                    <span style="color:#999;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total entries)
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
