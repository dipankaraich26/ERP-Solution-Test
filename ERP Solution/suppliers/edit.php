
<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->prepare("
        UPDATE suppliers SET
            supplier_name=?,
            contact_person=?,
            phone=?,
            email=?,
            address=?
        WHERE id=?
    ")->execute([
        $_POST['supplier_name'],
        $_POST['contact_person'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['address'],
        $id
    ]);

    header("Location: index.php");
    exit;
}
?>
<form method="post">
    Name <input name="supplier_name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>">
    Contact <input name="contact_person" value="<?= htmlspecialchars($supplier['contact_person']) ?>">
    Phone <input name="phone" value="<?= htmlspecialchars($supplier['phone']) ?>">
    Email <input name="email" value="<?= htmlspecialchars($supplier['email']) ?>">
    Address <input name="address" value="<?= htmlspecialchars($supplier['address']) ?>">
    <button>Update</button>
</form>
