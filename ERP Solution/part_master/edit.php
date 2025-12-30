<?php
include "../db.php";
include "../includes/sidebar.php";

$part_no = $_GET['part_no'] ?? null;
if (!$part_no) die("Part not specified");

$stmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no=?");
$stmt->execute([$part_no]);
$part = $stmt->fetch();
if (!$part) die("Part not found");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("
        UPDATE part_master
        SET part_name=?, part_id=?, description=?, uom=?, category=?, rate=?
        WHERE part_no=?
    ");
    $stmt->execute([
        $_POST['part_name'],
        $_POST['part_id'],
        $_POST['description'],
        $_POST['uom'],
        $_POST['category'],
        $_POST['rate'],
        $part_no
    ]);

    header("Location: list.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Part</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="content">
    <h1>Edit Part</h1>

    <form method="post">
        Part Name <input name="part_name" value="<?= htmlspecialchars($part['part_name']) ?>" required><br><br>
        Part ID <input name="part_id" value="<?= htmlspecialchars($part['part_id']) ?>"><br><br>
        Description <input name="description" value="<?= htmlspecialchars($part['description']) ?>"><br><br>
        UOM <input name="uom" value="<?= htmlspecialchars($part['uom']) ?>"><br><br>
        Category <input name="category" value="<?= htmlspecialchars($part['category']) ?>"><br><br>
        Rate <input name="rate" type="number" step="0.01" value="<?= htmlspecialchars($part['rate']) ?>"><br><br>
        <button type="submit">Update</button>
    </form>
</div>

</body>
</html>
