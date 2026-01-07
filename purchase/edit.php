<html>
<head>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id=?");
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    die("Invalid Purchase Order");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newQty = (int)$_POST['qty'];
    $diff = $newQty - $old['qty'];

    if ($newQty <= 0) {
        die("Quantity must be greater than zero");
    }

    $pdo->beginTransaction();
    try {
        /* UPDATE PO QTY ONLY */
        $pdo->prepare("
            UPDATE purchase_orders
            SET qty = ?
            WHERE id = ?
        ")->execute([$newQty, $id]);

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Update failed");
    }
}
?>

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

<!-- ✅ CONTENT WRAPPER (THIS IS THE FIX) -->
<div class="content">

    <h1>Edit Purchase Order</h1>

    <form method="post" class="form-box">
        <label>Quantity</label>
        <input type="number" name="qty" value="<?= htmlspecialchars($old['qty']) ?>" min="1" required>

        <button class="btn btn-primary">Update</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>

</div>

</body>
</html>
