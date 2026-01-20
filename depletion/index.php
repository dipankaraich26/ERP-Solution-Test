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

// Pagination setup for depletion records
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("
    SELECT COUNT(*) FROM depletion
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$depletions_stmt = $pdo->prepare("
    SELECT d.id, d.issue_no, p.part_name, d.part_no, d.qty, d.issue_date, d.reason, d.status
    FROM depletion d
    JOIN part_master p ON p.part_no = d.part_no
    ORDER BY d.id DESC
    LIMIT :limit OFFSET :offset
");
$depletions_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$depletions_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$depletions_stmt->execute();
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
            INSERT INTO depletion (part_no, qty, issue_date, reason, status, issue_no)
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

<hr>

<!-- Depletion Records Table -->
<h2>Depletion Records</h2>
<div style="overflow-x: auto;">
<table border="1" cellpadding="8">
    <tr>
        <th>Issue No</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>Qty</th>
        <th>Issue Date</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

    <?php while ($d = $depletions_stmt->fetch()): ?>
    <tr>
        <td><?= htmlspecialchars($d['issue_no']) ?></td>
        <td><?= htmlspecialchars($d['part_no']) ?></td>
        <td><?= htmlspecialchars($d['part_name']) ?></td>
        <td><?= htmlspecialchars($d['qty']) ?></td>
        <td><?= htmlspecialchars($d['issue_date']) ?></td>
        <td><?= htmlspecialchars($d['reason']) ?></td>
        <td><?= htmlspecialchars($d['status']) ?></td>
        <td>
            <?php if ($d['status'] === 'issued'): ?>
                <a class="btn btn-secondary" href="edit.php?id=<?= $d['id'] ?>">Edit</a>
                | <a class="btn btn-danger" href="cancel.php?id=<?= $d['id'] ?>" onclick="return confirm('Cancel this depletion?')">Cancel</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="margin-top: 20px; text-align: center;">
    <?php if ($page > 1): ?>
        <a href="?page=1" class="btn btn-secondary">First</a>
        <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
    <?php endif; ?>

    <span style="margin: 0 10px;">
        Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total depletion records)
    </span>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
        <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
