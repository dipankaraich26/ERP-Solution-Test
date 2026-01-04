<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'];

$parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    WHERE status='active'
")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE bom_master
        SET bom_no=?, parent_part_no=?, description=?
        WHERE id=?
    ")->execute([
        $_POST['bom_no'],
        $_POST['parent_part_no'],
        $_POST['description'],
        $id
    ]);

    $pdo->prepare("DELETE FROM bom_items WHERE bom_id=?")->execute([$id]);

    $stmt = $pdo->prepare("
        INSERT INTO bom_items (bom_id, component_part_no, qty)
        VALUES (?, ?, ?)
    ");

    foreach ($_POST['component_part_no'] as $i => $part) {
        $stmt->execute([$id, $part, $_POST['qty'][$i]]);
    }

    $pdo->commit();
    header("Location: index.php");
    exit;
}

$bom = $pdo->query("SELECT * FROM bom_master WHERE id=$id")->fetch();
$items = $pdo->query("SELECT * FROM bom_items WHERE bom_id=$id")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit BOM</title>
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
    <h1>Edit BOM</h1>

    <form method="post">
        BOM No<br>
        <input name="bom_no" value="<?= $bom['bom_no'] ?>"><br><br>

        Parent Part<br>
        <select name="parent_part_no">
            <?php foreach ($parts as $p): ?>
                <option value="<?= $p['part_no'] ?>"
                    <?= $p['part_no']===$bom['parent_part_no']?'selected':'' ?>>
                    <?= $p['part_name'] ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <table border="1" cellpadding="8">
            <tr><th>Component</th><th>Qty</th></tr>
            <?php foreach ($items as $i): ?>
            <tr>
                <td><?= $i['component_part_no'] ?></td>
                <td><?= $i['qty'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <br>
        <button>Update BOM</button>
    </form>
</div>

</body>
</html>
