<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? null;
if (!$id) die("Invalid request");

/* Fetch SO line */
$stmt = $pdo->prepare("
    SELECT so.*, p.part_name, i.qty AS stock_qty
    FROM sales_orders so
    JOIN part_master p ON p.part_no = so.part_no
    JOIN inventory i ON i.part_no = so.part_no
    WHERE so.id = ?
");
$stmt->execute([$id]);
$line = $stmt->fetch();

if (!$line || $line['status'] !== 'open') {
    die("Editing not allowed");
}

$maxAllowed = $line['stock_qty'] + $line['qty'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newQty = (int)$_POST['qty'];

    if (($maxAllowed - $newQty) < 0) {
        die("Quantity would deplete stock. Not allowed.");
    }

    $pdo->prepare("
        UPDATE sales_orders SET qty = ? WHERE id = ?
    ")->execute([$newQty, $id]);

    header("Location: index.php");
    exit;
}
?>

<script src="/assets/script.js"></script>
<div class="content">
    <h1>Edit Sales Order Line</h1>

    <p><strong>Part:</strong> <?= htmlspecialchars($line['part_name']) ?></p>
    <p><strong>Available Stock:</strong> <?= $line['stock_qty'] ?></p>
    <p><strong>Current Qty:</strong> <?= $line['qty'] ?></p>

    <form method="post" class="form-box">
        <label>New Quantity</label>
        <input type="number"
               name="qty"
               min="1"
               max="<?= $maxAllowed - 1 ?>"
               value="<?= $line['qty'] ?>"
               required
               oninput="checkQty(this)">

        <button class="btn btn-primary">Update</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
function checkQty(input) {
    const max = <?= $maxAllowed - 1 ?>;
    if (parseInt(input.value, 10) > max) {
        alert("❌ Quantity exceeds available stock.");
        input.value = max;
    }
}
</script>
