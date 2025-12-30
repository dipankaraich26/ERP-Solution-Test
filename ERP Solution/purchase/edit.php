<?php
include "../db.php";

$id = $_GET['id'];

$old = $pdo->prepare("SELECT * FROM purchase_orders WHERE id=?");
$old->execute([$id]);
$old = $old->fetch();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newQty = (int)$_POST['qty'];
    $diff = $newQty - $old['qty'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE purchase_orders SET qty=? WHERE id=?
        ")->execute([$newQty, $id]);

        $pdo->prepare("
            UPDATE inventory SET qty = qty + ?
            WHERE part_no=?
        ")->execute([$diff, $old['part_no']]);

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}
?>
<form method="post">
    Qty <input type="number" name="qty" value="<?= $old['qty'] ?>">
    <button>Update</button>
</form>
