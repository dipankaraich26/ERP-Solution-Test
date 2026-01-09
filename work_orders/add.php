<?php
include "../db.php";
include "../includes/sidebar.php";

$parts = $pdo->query("
    SELECT part_no, part_name FROM part_master WHERE status='active'
")->fetchAll();

$boms = $pdo->query("
    SELECT id, bom_no FROM bom_master WHERE status='active'
")->fetchAll();

// generate next WO number like "WO-1", "WO-2", ...
$max = $pdo->query("SELECT MAX(CAST(SUBSTRING(wo_no,4) AS UNSIGNED)) FROM work_orders WHERE wo_no LIKE 'WO-%'")->fetchColumn();
$next = $max ? ((int)$max + 1) : 1;
$wo_no = 'WO-' . $next;

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    // enforce server-side WO number to avoid tampering
    $generated_wo = $wo_no;

    $pdo->prepare("
        INSERT INTO work_orders (wo_no, part_no, bom_id, qty)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $generated_wo,
        $_POST['part_no'],
        $_POST['bom_id'],
        $_POST['qty']
    ]);
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
<h1>Create Work Order</h1>

<form method="post">
WO No<br>
<input name="wo_no" value="<?= htmlspecialchars($wo_no) ?>" readonly required><br><br>

Product<br>
<select name="part_no" required>
<?php foreach ($parts as $p): ?>
<option value="<?= $p['part_no'] ?>"><?= $p['part_name'] ?></option>
<?php endforeach; ?>
</select><br><br>

BOM<br>
<select name="bom_id" required>
<?php foreach ($boms as $b): ?>
<option value="<?= $b['id'] ?>"><?= $b['bom_no'] ?></option>
<?php endforeach; ?>
</select><br><br>

Quantity<br>
<input type="number" step="0.001" name="qty" required><br><br>

<button>Create WO</button>
</form>
</div>
</body>
</html>
