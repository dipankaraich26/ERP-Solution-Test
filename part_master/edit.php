<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

$part_no = $_GET['part_no'] ?? null;

if (!$part_no) {
    setModal("Failed to edit part", "Part not specified");
    header("Location: list.php");
    exit;
} 

$stmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no=?");
$stmt->execute([$part_no]);
$part = $stmt->fetch();

if (!$part) {
    setModal("Failed to edit part", "Part not found");
    header("Location: list.php");
    exit;
}

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
    <link rel="stylesheet" href="/assets/style.css">
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
