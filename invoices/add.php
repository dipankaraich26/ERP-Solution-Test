<?php
include "../db.php";
include "../includes/dialog.php";

// Auto-migrate: convert any 'fulfilled' SO status to 'released'
try {
    $pdo->exec("UPDATE sales_orders SET status = 'released' WHERE status = 'fulfilled'");
} catch (PDOException $e) {}

/* =========================
   FETCH SALES ORDERS (only RELEASED ones that don't have an invoice yet)
   Note: Pending SOs cannot be invoiced as they have stock issues
========================= */

$salesOrders = $pdo->query("
    SELECT
        so.so_no,
        so.sales_date,
        so.status,
        c.company_name,
        c.customer_name,
        cp.po_no as customer_po_no,
        q.pi_no,
        (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value
    FROM (
        SELECT so_no,
               MAX(sales_date) as sales_date,
               MAX(status) as status,
               MAX(customer_id) as customer_id,
               MAX(customer_po_id) as customer_po_id,
               MAX(linked_quote_id) as linked_quote_id
        FROM sales_orders
        WHERE status = 'released'
        GROUP BY so_no
    ) so
    LEFT JOIN customers c ON c.id = so.customer_id
    LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    LEFT JOIN invoice_master im ON im.so_no = so.so_no
    WHERE im.id IS NULL
    ORDER BY so.sales_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE INVOICE GENERATION
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $so_no = $_POST['so_no'] ?? '';
    $invoice_date = $_POST['invoice_date'] ?? '';

    if (!$so_no || !$invoice_date) {
        setModal("Failed", "Sales Order and Invoice Date are required");
        header("Location: add.php");
        exit;
    }

    // Get customer_id from SO
    $soStmt = $pdo->prepare("SELECT customer_id FROM sales_orders WHERE so_no = ? LIMIT 1");
    $soStmt->execute([$so_no]);
    $soData = $soStmt->fetch(PDO::FETCH_ASSOC);

    if (!$soData) {
        setModal("Failed", "Sales Order not found");
        header("Location: add.php");
        exit;
    }

    // Generate Invoice number (INV/serial/FY format like PI)
    $month = (int)date('n');
    $year = (int)date('Y');
    if ($month < 4) {
        $fyStart = $year - 1;
        $fyEnd = $year;
    } else {
        $fyStart = $year;
        $fyEnd = $year + 1;
    }
    $fyString = substr($fyStart, -2) . '/' . substr($fyEnd, -2);

    // Get max serial for this FY
    $likePattern = 'INV/%/' . $fyString;
    $maxStmt = $pdo->prepare("
        SELECT invoice_no FROM invoice_master
        WHERE invoice_no LIKE ?
        ORDER BY id DESC LIMIT 1
    ");
    $maxStmt->execute([$likePattern]);
    $lastInvoice = $maxStmt->fetchColumn();

    if ($lastInvoice) {
        // Extract serial: INV/123/26/27 -> 123
        preg_match('/INV\/(\d+)\//', $lastInvoice, $matches);
        $invSerial = isset($matches[1]) ? ((int)$matches[1] + 1) : 1;
    } else {
        $invSerial = 165;
    }

    $invoice_no = 'INV/' . $invSerial . '/' . $fyString;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO invoice_master
            (invoice_no, so_no, customer_id, invoice_date, status)
            VALUES (?, ?, ?, ?, 'draft')
        ");
        $stmt->execute([
            $invoice_no,
            $so_no,
            $soData['customer_id'],
            $invoice_date
        ]);

        setModal("Success", "Invoice $invoice_no created successfully");
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        setModal("Error", "Invoice creation failed: " . $e->getMessage());
        header("Location: add.php");
        exit;
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate Invoice</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-box {
            max-width: 600px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-box h3 { margin-top: 0; }
        .form-box label { display: block; margin: 15px 0 5px; font-weight: bold; }
        .form-box select, .form-box input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        #soDetails {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #e8f4e8;
            border-radius: 5px;
            border: 1px solid #c3e6c3;
        }
        #soDetails strong { color: #2c3e50; }
    </style>
</head>
<body>

<div class="content">
    <h1>Generate Tax Invoice</h1>

    <p><a href="index.php" class="btn btn-secondary">Back to Invoices</a></p>

    <form method="post" class="form-box">
        <h3>Create Invoice from Sales Order</h3>

        <label>Select Sales Order *</label>
        <select name="so_no" required onchange="showSODetails(this)">
            <option value="">-- Select Sales Order --</option>
            <?php foreach ($salesOrders as $so): ?>
                <option value="<?= htmlspecialchars($so['so_no']) ?>"
                    data-customer="<?= htmlspecialchars($so['company_name'] ?? $so['customer_name'] ?? '') ?>"
                    data-po="<?= htmlspecialchars($so['customer_po_no'] ?? '') ?>"
                    data-pi="<?= htmlspecialchars($so['pi_no'] ?? '') ?>"
                    data-date="<?= htmlspecialchars($so['sales_date']) ?>"
                    data-total="<?= $so['total_value'] ? number_format($so['total_value'], 2) : '-' ?>"
                    data-status="<?= htmlspecialchars($so['status']) ?>">
                    <?= htmlspecialchars($so['so_no']) ?>
                    <?php if ($so['company_name']): ?>
                        - <?= htmlspecialchars($so['company_name']) ?>
                    <?php endif; ?>
                    (<?= ucfirst($so['status']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <small style="color: #666;">Only Sales Orders without an invoice are shown</small>

        <div id="soDetails">
            <strong>Customer:</strong> <span id="detailCustomer"></span><br>
            <strong>Customer PO:</strong> <span id="detailPO"></span><br>
            <strong>PI No:</strong> <span id="detailPI"></span><br>
            <strong>SO Date:</strong> <span id="detailDate"></span><br>
            <strong>Total Value:</strong> <span id="detailTotal"></span><br>
            <strong>Status:</strong> <span id="detailStatus"></span>
        </div>

        <label>Invoice Date *</label>
        <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>

        <p style="color: #666; font-size: 0.9em; margin-top: 15px;">
            Invoice number will be auto-generated (e.g., INV/1/26/27)
        </p>

        <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
            Generate Invoice
        </button>
    </form>

    <?php if (empty($salesOrders)): ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
        <strong>No Sales Orders available for invoicing.</strong><br>
        All existing Sales Orders either already have invoices or are in completed/cancelled status.
    </div>
    <?php endif; ?>
</div>

<script>
function showSODetails(select) {
    const opt = select.selectedOptions[0];
    const details = document.getElementById('soDetails');

    if (opt && opt.value) {
        document.getElementById('detailCustomer').textContent = opt.dataset.customer || '-';
        document.getElementById('detailPO').textContent = opt.dataset.po || '-';
        document.getElementById('detailPI').textContent = opt.dataset.pi || '-';
        document.getElementById('detailDate').textContent = opt.dataset.date || '-';
        document.getElementById('detailTotal').textContent = opt.dataset.total || '-';
        document.getElementById('detailStatus').textContent = opt.dataset.status ? opt.dataset.status.charAt(0).toUpperCase() + opt.dataset.status.slice(1) : '-';
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}
</script>

</body>
</html>
