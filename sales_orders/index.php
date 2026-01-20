<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

/* =========================
   FETCH CUSTOMER POs (for creating new SO)
========================= */
$customerPOs = $pdo->query("
    SELECT cp.id, cp.po_no, cp.customer_id, c.company_name, c.customer_name,
           cp.linked_quote_id, q.pi_no
    FROM customer_po cp
    LEFT JOIN customers c ON cp.customer_id = c.customer_id
    LEFT JOIN quote_master q ON cp.linked_quote_id = q.id
    WHERE cp.status = 'active'
    ORDER BY cp.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE SALES ORDER CREATE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_so'])) {
    $customer_po_id = (int)($_POST['customer_po_id'] ?? 0);
    $sales_date = $_POST['sales_date'] ?? '';

    if (!$customer_po_id || !$sales_date) {
        setModal("Failed", "Customer PO and Sales Date are required");
        header("Location: index.php");
        exit;
    }

    // Get Customer PO details
    $poStmt = $pdo->prepare("
        SELECT cp.*, q.id as quote_id, q.pi_no
        FROM customer_po cp
        LEFT JOIN quote_master q ON cp.linked_quote_id = q.id
        WHERE cp.id = ?
    ");
    $poStmt->execute([$customer_po_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        setModal("Failed", "Customer PO not found");
        header("Location: index.php");
        exit;
    }

    // Get linked PI (quote) ID - either from direct link or from POST
    $linked_quote_id = $po['quote_id'];

    if (!$linked_quote_id) {
        setModal("Failed", "Customer PO is not linked to a Proforma Invoice. Please link it first.");
        header("Location: index.php");
        exit;
    }

    // Get parts from the PI
    $partsStmt = $pdo->prepare("
        SELECT part_no, part_name, qty
        FROM quote_items
        WHERE quote_id = ?
    ");
    $partsStmt->execute([$linked_quote_id]);
    $parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($parts)) {
        setModal("Failed", "No parts found in the linked Proforma Invoice");
        header("Location: index.php");
        exit;
    }

    // Generate SO number
    $maxNo = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(so_no,4) AS UNSIGNED)),0)
        FROM sales_orders WHERE so_no LIKE 'SO-%'
    ")->fetchColumn();
    $so_no = 'SO-' . ((int)$maxNo + 1);

    // Get customer integer ID from PO's varchar customer_id
    // sales_orders.customer_id is INT referencing customers.id
    $customer_id = null;
    if (!empty($po['customer_id'])) {
        $custStmt = $pdo->prepare("SELECT id FROM customers WHERE customer_id = ?");
        $custStmt->execute([$po['customer_id']]);
        $customer_id = $custStmt->fetchColumn();
    }

    // Check stock for all parts
    $stockIssues = [];
    foreach ($parts as $part) {
        $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
        $stockStmt->execute([$part['part_no']]);
        $available = (int)$stockStmt->fetchColumn();

        if ($available < $part['qty']) {
            $stockIssues[] = $part['part_no'] . " (Need: " . $part['qty'] . ", Available: " . $available . ")";
        }
    }

    // Create the sales order anyway (with stock status indicator)
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sales_orders
            (so_no, part_no, qty, sales_date, customer_id, customer_po_id, linked_quote_id, status, stock_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        foreach ($parts as $part) {
            // Check individual stock status
            $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stockStmt->execute([$part['part_no']]);
            $available = (int)$stockStmt->fetchColumn();
            $itemStockStatus = ($available >= $part['qty']) ? 'ok' : 'insufficient';

            $stmt->execute([
                $so_no,
                $part['part_no'],
                $part['qty'],
                $sales_date,
                $customer_id,
                $customer_po_id,
                $linked_quote_id,
                $itemStockStatus
            ]);
        }

        $pdo->commit();

        if (!empty($stockIssues)) {
            setModal("Warning", "Sales Order created but stock is insufficient for: " . implode(", ", $stockIssues));
        }

        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", "Sales Order creation failed: " . $e->getMessage());
        header("Location: index.php");
        exit;
    }
}

/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of grouped sales orders
$total_count = $pdo->query("
    SELECT COUNT(DISTINCT so_no) FROM sales_orders
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

/* =========================
   FETCH SALES ORDERS (GROUPED)
========================= */
$stmt = $pdo->prepare("
    SELECT
        so.so_no,
        so.sales_date,
        so.status,
        so.customer_po_id,
        so.linked_quote_id,
        c.company_name,
        cp.po_no as customer_po_no,
        q.pi_no,
        GROUP_CONCAT(
            CONCAT(so.part_no,'::',p.part_name,'::',so.qty,'::',so.stock_status)
            SEPARATOR '|||'
        ) AS items,
        MAX(so.id) AS max_id,
        SUM(CASE WHEN so.stock_status = 'insufficient' THEN 1 ELSE 0 END) as insufficient_count
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    GROUP BY so.so_no, so.sales_date, so.status, so.customer_po_id, so.linked_quote_id,
             c.company_name, cp.po_no, q.pi_no
    ORDER BY so.sales_date DESC, max_id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-box {
            max-width: 500px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-box h3 { margin-top: 0; }
        .form-box label { display: block; margin: 10px 0 5px; font-weight: bold; }
        .form-box select, .form-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-released { background: #28a745; color: #fff; }
        .status-completed { background: #17a2b8; color: #fff; }
        .stock-ok { color: #28a745; }
        .stock-insufficient { color: #dc3545; font-weight: bold; }
        .stock-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
    </style>
</head>

<body>

<div class="content">
    <h1>Sales Orders</h1>

    <!-- =========================
         CREATE SALES ORDER
    ========================= -->
    <form method="post" class="form-box">
        <h3>Create Sales Order from Customer PO</h3>

        <label>Select Customer PO *</label>
        <select name="customer_po_id" required onchange="showPODetails(this)">
            <option value="">-- Select Customer PO --</option>
            <?php foreach ($customerPOs as $cpo): ?>
                <?php if ($cpo['linked_quote_id']): ?>
                <option value="<?= $cpo['id'] ?>"
                    data-pi="<?= htmlspecialchars($cpo['pi_no'] ?? '') ?>"
                    data-customer="<?= htmlspecialchars($cpo['company_name'] ?? '') ?>">
                    <?= htmlspecialchars($cpo['po_no']) ?>
                    <?php if ($cpo['company_name']): ?>
                        - <?= htmlspecialchars($cpo['company_name']) ?>
                    <?php endif; ?>
                    (PI: <?= htmlspecialchars($cpo['pi_no'] ?? 'Not linked') ?>)
                </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <small style="color: #666;">Only Customer POs linked to a PI are shown</small>

        <div id="poDetails" style="display: none; margin-top: 15px; padding: 10px; background: #e8f4e8; border-radius: 5px;">
            <strong>Customer:</strong> <span id="detailCustomer"></span><br>
            <strong>Linked PI:</strong> <span id="detailPI"></span>
        </div>

        <label>Sales Date *</label>
        <input type="date" name="sales_date" value="<?= date('Y-m-d') ?>" required>

        <p style="color: #666; font-size: 0.9em; margin-top: 15px;">
            Parts will be automatically fetched from the linked Proforma Invoice.
        </p>

        <button type="submit" name="create_so" value="1" class="btn btn-primary" style="margin-top: 15px;">
            Create Sales Order
        </button>
    </form>

    <hr>

    <!-- =========================
         SALES ORDER LIST
    ========================= -->
    <h3>Sales Orders</h3>
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>SO No</th>
            <th>Customer PO</th>
            <th>PI No</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Stock Status</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($orders as $o): ?>
        <tr>
            <td><strong><?= htmlspecialchars($o['so_no']) ?></strong></td>
            <td><?= htmlspecialchars($o['customer_po_no'] ?? '-') ?></td>
            <td>
                <?php if ($o['pi_no']): ?>
                    <a href="/proforma/view.php?id=<?= $o['linked_quote_id'] ?>">
                        <?= htmlspecialchars($o['pi_no']) ?>
                    </a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($o['company_name'] ?? '-') ?></td>
            <td><?= $o['sales_date'] ?></td>
            <td>
                <?php if ($o['insufficient_count'] > 0): ?>
                    <span class="stock-warning">Stock Low (<?= $o['insufficient_count'] ?> items)</span>
                <?php else: ?>
                    <span class="stock-ok">OK</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge status-<?= $o['status'] ?>">
                    <?= ucfirst($o['status']) ?>
                </span>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="view.php?so_no=<?= urlencode($o['so_no']) ?>">View</a>
                <?php if ($o['status'] === 'pending'): ?>
                    <a class="btn btn-success"
                       href="release.php?so_no=<?= urlencode($o['so_no']) ?>"
                       onclick="return confirm('Release this Sales Order?\n\nInventory will be deducted.')">
                        Release
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($orders)): ?>
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px;">No Sales Orders found.</td>
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
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total orders)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function showPODetails(select) {
    const opt = select.selectedOptions[0];
    const details = document.getElementById('poDetails');

    if (opt && opt.value) {
        document.getElementById('detailCustomer').textContent = opt.dataset.customer || '-';
        document.getElementById('detailPI').textContent = opt.dataset.pi || '-';
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}
</script>

</body>
</html>
