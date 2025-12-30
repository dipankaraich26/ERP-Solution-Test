<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? 0;

/* FETCH OLD RECORD */
$stmt = $pdo->prepare("SELECT * FROM depletion WHERE id=?");
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    die("Invalid depletion record");
}

$oldQty = (int)$old['qty'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newQty = (int)$_POST['qty'];

    /* Difference logic (reverse of purchase) */
    $diff = $oldQty - $newQty;

    $pdo->beginTransaction();
    try {
        // Update depletion qty
        $pdo->prepare("
            UPDATE depletion SET qty=?
            WHERE id=?
        ")->execute([$newQty, $id]);

        // Adjust inventory safely
        $pdo->prepare("
            UPDATE inventory SET qty = qty + ?
            WHERE part_no=?
        ")->execute([$diff, $old['part_no']]);

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Depletion</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Edit Depletion</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        Qty
        <input type="number" name="qty" value="<?= $oldQty ?>" min="1" required>
        <button>Update</button>
    </form>
</div>

</body>
</html>
