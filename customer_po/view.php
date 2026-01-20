<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch customer PO
$stmt = $pdo->prepare("
    SELECT cp.*, c.company_name, c.customer_name, c.contact, c.email,
           q.pi_no, q.quote_no
    FROM customer_po cp
    LEFT JOIN customers c ON cp.customer_id = c.customer_id
    LEFT JOIN quote_master q ON cp.linked_quote_id = q.id
    WHERE cp.id = ?
");
$stmt->execute([$id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    header("Location: index.php");
    exit;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Customer PO - <?= htmlspecialchars($po['po_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .po-view { max-width: 800px; }
        .po-header {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .po-header h2 { margin: 0 0 15px 0; color: #4a90d9; }
        .po-detail { margin: 10px 0; }
        .po-detail strong { display: inline-block; width: 150px; }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-active { background: #28a745; color: #fff; }
        .status-completed { background: #17a2b8; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        .action-buttons { margin: 20px 0; }
        .action-buttons .btn { margin-right: 10px; }
        .pdf-preview {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .pdf-preview iframe {
            width: 100%;
            height: 600px;
            border: none;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="po-view">

        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary">Back to List</a>
            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php if ($po['attachment_path']): ?>
                <a href="../<?= htmlspecialchars($po['attachment_path']) ?>" target="_blank" class="btn btn-secondary">Download Attachment</a>
            <?php endif; ?>
        </div>

        <div class="po-header">
            <h2>Customer PO: <?= htmlspecialchars($po['po_no']) ?></h2>

            <div class="po-detail">
                <strong>Status:</strong>
                <span class="status-badge status-<?= $po['status'] ?>">
                    <?= ucfirst($po['status']) ?>
                </span>
            </div>

            <div class="po-detail">
                <strong>PO Date:</strong> <?= htmlspecialchars($po['po_date'] ?? '-') ?>
            </div>

            <div class="po-detail">
                <strong>Customer:</strong>
                <?php if ($po['company_name']): ?>
                    <?= htmlspecialchars($po['company_name']) ?>
                    <?php if ($po['customer_name']): ?>
                        (<?= htmlspecialchars($po['customer_name']) ?>)
                    <?php endif; ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>

            <?php if ($po['contact']): ?>
            <div class="po-detail">
                <strong>Contact:</strong> <?= htmlspecialchars($po['contact']) ?>
            </div>
            <?php endif; ?>

            <?php if ($po['pi_no']): ?>
            <div class="po-detail">
                <strong>Linked PI:</strong>
                <a href="/proforma/view.php?id=<?= $po['linked_quote_id'] ?>">
                    <?= htmlspecialchars($po['pi_no']) ?>
                </a>
                (Quote: <?= htmlspecialchars($po['quote_no']) ?>)
            </div>
            <?php endif; ?>

            <?php if ($po['notes']): ?>
            <div class="po-detail">
                <strong>Notes:</strong><br>
                <?= nl2br(htmlspecialchars($po['notes'])) ?>
            </div>
            <?php endif; ?>

            <div class="po-detail">
                <strong>Created:</strong> <?= htmlspecialchars($po['created_at']) ?>
            </div>
        </div>

        <?php if ($po['attachment_path']): ?>
            <?php
            $ext = strtolower(pathinfo($po['attachment_path'], PATHINFO_EXTENSION));
            if ($ext === 'pdf'):
            ?>
            <h3>PO Document</h3>
            <div class="pdf-preview">
                <iframe src="../<?= htmlspecialchars($po['attachment_path']) ?>"></iframe>
            </div>
            <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
            <h3>PO Document</h3>
            <div style="margin-top: 20px;">
                <img src="../<?= htmlspecialchars($po['attachment_path']) ?>" style="max-width: 100%; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
