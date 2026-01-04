<?php
include "../db.php";
include "../includes/sidebar.php";

$parts = $pdo->query("
    SELECT part_no, part_name
    FROM part_master
    WHERE status='active'
    ORDER BY part_name
")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_POST['component_part_no'])) {
        die("At least one component required");
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO bom_master (bom_no, parent_part_no, description)
        VALUES (?, ?, ?)
    ")->execute([
        $_POST['bom_no'],
        $_POST['parent_part_no'],
        $_POST['description']
    ]);

    $bom_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO bom_items (bom_id, component_part_no, qty)
        VALUES (?, ?, ?)
    ");

    foreach ($_POST['component_part_no'] as $i => $part) {
        if ($part === $_POST['parent_part_no']) {
            $pdo->rollBack();
            die("Parent part cannot be a component");
        }

        $stmt->execute([
            $bom_id,
            $part,
            $_POST['qty'][$i]
        ]);
    }

    $pdo->commit();
    header("Location: index.php");
    exit;
}
?>



<!DOCTYPE html>
<html>
<head>
    <title>Add BOM</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

<div class="content">
    <h1>Add BOM</h1>

    <form method="post">
        BOM No<br>
        <input name="bom_no" required><br><br>

        Parent Part<br>
        <select name="parent_part_no" required>
            <?php foreach ($parts as $p): ?>
                <option value="<?= $p['part_no'] ?>">
                    <?= htmlspecialchars($p['part_name']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        Description<br>
        <textarea name="description"></textarea><br><br>

        <h3>Components</h3>

        <table border="1" cellpadding="8" id="bomTable">
            <tr>
                <th>Component</th>
                <th>Qty</th>
                <th></th>
            </tr>
            <tr>
                <td>
                    <select name="component_part_no[]" required>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= $p['part_no'] ?>">
                                <?= htmlspecialchars($p['part_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.001" name="qty[]" required></td>
                <td><button type="button" onclick="addRow()">➕</button></td>
            </tr>
        </table>

        <br>
        <button class="btn btn-success">Save BOM</button>
    </form>
</div>

<script>
function addRow() {
    const table = document.getElementById('bomTable');
    const row = table.rows[1].cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    table.appendChild(row);
}

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

</body>
</html>
