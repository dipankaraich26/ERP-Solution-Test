<?php
include "../db.php";
include "../includes/sidebar.php";

$po_id = $_GET['po_id'];

/* Fetch PO */
$po = $pdo->prepare("
    SELECT * FROM purchase_orders WHERE id=?
");
$po->execute([$po_id]);
$po = $po->fetch();

if (!$po) die("Invalid PO");

/* Already received qty */
$received = $pdo->prepare("
    SELECT SUM(received_qty)
    FROM stock_entries
    WHERE po_id=? AND status='posted'
");
$received->execute([$po_id]);
$receivedQty = $received->fetchColumn() ?? 0;

$remaining = $po['qty'] - $receivedQty;

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $qty = $_POST['received_qty'];

    if ($qty <= 0 || $qty > $remaining) {
        die("Invalid received quantity");
    }

    $pdo->beginTransaction();

    /* Insert stock entry */
    $pdo->prepare("
        INSERT INTO stock_entries
        (po_id, part_no, received_qty, invoice_no)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $po_id,
        $po['part_no'],
        $qty,
        $_POST['invoice_no']
    ]);

    /* Update inventory */
    $pdo->prepare("
        INSERT INTO inventory (part_no, qty)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
    ")->execute([$po['part_no'], $qty]);

    /* Update PO status */
    $newStatus = ($qty + $receivedQty) >= $po['qty']
        ? 'closed'
        : 'partial';

    $pdo->prepare("
        UPDATE purchase_orders SET status=?
        WHERE id=?
    ")->execute([$newStatus, $po_id]);

    $pdo->commit();
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/style.css">
</head>
<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>
<body>

<div class="content">
<h1>Receive Stock</h1>

<p><strong>PO:</strong> <?= $po['po_no'] ?></p>
<p><strong>Ordered:</strong> <?= $po['qty'] ?></p>
<p><strong>Already Received:</strong> <?= $receivedQty ?></p>
<p><strong>Remaining:</strong> <?= $remaining ?></p>

<form method="post">
Invoice No<br>
<input name="invoice_no"><br><br>

Received Qty<br>
<input type="number" step="0.001" name="received_qty" required><br><br>

<button>Post Stock Entry</button>
</form>
</div>

</body>
</html>
