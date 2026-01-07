<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>
<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/dialog.php";

showModal();

$parts = $pdo->query("
    SELECT i.part_no, i.qty, p.part_name
    FROM inventory i
    JOIN part_master p ON p.part_no = i.part_no
    WHERE i.qty > 0
");
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

<div class="content">
<h1>Stock Depletion</h1>

<form method="post">
    <label>Part</label>
    <select name="part_no" required>
        <?php while ($p = $parts->fetch()): ?>
            <option value="<?= $p['part_no'] ?>">
                <?= $p['part_no'] ?> — <?= $p['part_name'] ?> (<?= $p['qty'] ?>)
            </option>
        <?php endwhile; ?>
    </select>

    <label>Qty</label>
    <input type="number" name="qty" min="1" required>

    <label>Reason</label>
    <input name="reason" required>

    <button class="btn btn-danger">Issue</button>
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $part = $_POST['part_no'];
    $qty = (int)$_POST['qty'];
    $reason = $_POST['reason'];

    $stmt = $pdo->prepare("SELECT qty FROM inventory WHERE part_no=? FOR UPDATE");
    $stmt->execute([$part]);
    $available = $stmt->fetchColumn();

    if ($available === false || $available < $qty)
        setModal("Failed to deplete", "Insufficient Stock");
        header("Location: index.php"); 

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE inventory SET qty = qty - ?
            WHERE part_no = ?
        ")->execute([$qty, $part]);

        $pdo->prepare("
            INSERT INTO depletion (part_no, qty, qissue_date, reason, status, issue_no)
            VALUES (?, ?, CURDATE(), ?, 'issued', CONCAT('ISS-', UNIX_TIMESTAMP()))
        ")->execute([$part, $qty, $reason]);

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Failed to deplete", $e->getMessage());
        header("Location: index.php"); 
    }
}
?>
</div>
