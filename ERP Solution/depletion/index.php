<html><head><link rel="stylesheet" href="/erp/assets/style.css"></head></html>

<?php
require "../db.php";
include "../includes/sidebar.php";

/* =========================
   GENERATE ISSUE NUMBER
========================= */
$issue_no = "ISS-" . date("Ymd-His");

/* =========================
   HANDLE ADD DEPLETION (BULK)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $issue_no = $_POST['issue_no'];
    $date     = $_POST['issue_date'];
    $reason   = $_POST['reason'];
    $parts    = $_POST['part_no'];
    $qtys     = $_POST['qty'];

    try {
        $pdo->beginTransaction();

        for ($i = 0; $i < count($parts); $i++) {

            $part_no = $parts[$i];
            $qty     = (int)$qtys[$i];

            if ($qty <= 0) continue;

            /* 🔹 Check stock */
            $stmt = $pdo->prepare("SELECT qty FROM inventory WHERE part_no=?");
            $stmt->execute([$part_no]);
            $stock = (int)$stmt->fetchColumn();

            if ($qty > $stock) {
                throw new Exception("Insufficient stock for $part_no (Available: $stock)");
            }

            /* 🔹 Insert depletion */
            $pdo->prepare("
                INSERT INTO depletion
                (issue_no, part_no, qty, issue_date, reason, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ")->execute([
                $issue_no,
                $part_no,
                $qty,
                $date,
                $reason
            ]);

            /* 🔹 Update inventory */
            $pdo->prepare("
                UPDATE inventory
                SET qty = qty - ?
                WHERE part_no = ?
            ")->execute([$qty, $part_no]);
        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

/* =========================
   PARTS WITH STOCK
========================= */
$stmt = $pdo->query("
    SELECT p.part_no, p.part_name, i.qty
    FROM part_master p
    JOIN inventory i ON i.part_no = p.part_no
    WHERE p.status = 'active'
    ORDER BY p.part_name
");
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   DEPLETION TABLE (GROUPED)
========================= */
$issues = $pdo->query("
    SELECT 
        issue_no,
        MIN(issue_date) AS issue_date,
        reason,
        status
    FROM depletion
    GROUP BY issue_no
    ORDER BY issue_no DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Depletion</title>
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
</body>
</html>


<body>

<div class="content">
<h1>Depletion (Issue Stock)</h1>

<?php if (!empty($error)): ?>
<script>alert("<?= htmlspecialchars($error) ?>");</script>
<?php endif; ?>

<!-- =====================
     ADD DEPLETION FORM
===================== -->
<form method="post">

<input type="hidden" name="issue_no" value="<?= $issue_no ?>">

<label>Issue No</label>
<input type="text" value="<?= $issue_no ?>" readonly>

<label>Date</label>
<input type="date" name="issue_date" required>

<label>Reason</label>
<input type="text" name="reason" required>

<h3>Issue Parts</h3>

<div id="items">
    <div>
        <select name="part_no[]" required>
            <option value="">Select Part</option>
            <?php foreach ($parts as $p): ?>
                <option value="<?= $p['part_no'] ?>">
                    <?= htmlspecialchars($p['part_name']) ?> (Stock: <?= $p['qty'] ?>)
                </option>
            <?php endforeach; ?>
        </select>




        <input type="number" name="qty[]" min="1" placeholder="Qty" required>
    </div>
</div>

<button type="button" onclick="addRow()">+ Add Another Part</button>
<br><br>

<button type="submit">Issue Stock</button>
</form>

<hr>

<!-- =====================
     DEPLETION TABLE
===================== -->
<table border="1" cellpadding="8">
<tr>
    <th>Issue No</th>
    <th>Date</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php while ($row = $issues->fetch()): ?>
<tr>
    <td><?= $row['issue_no'] ?></td>
    <td><?= $row['issue_date'] ?></td>
    <td><?= htmlspecialchars($row['reason']) ?></td>
    <td><?= ucfirst($row['status']) ?></td>
    <td>
        <?php if ($row['status'] === 'active'): ?>
            <a href="cancel.php?issue_no=<?= $row['issue_no'] ?>"
               onclick="return confirm('Cancel entire issue?')">
               Cancel
            </a>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

</div>

<script>
const partsData = <?= json_encode($parts) ?>;
</script>


<script>
    function addRow() {
    const div = document.createElement("div");

    let options = '<option value="">Select Part</option>';
    partsData.forEach(p => {
        options += `<option value="${p.part_no}">
            ${p.part_name} (Stock: ${p.qty})
        </option>`;
    });

    div.innerHTML = `
        <select name="part_no[]" required>${options}</select>
        <input type="number" name="qty[]" min="1" required>
    `;

    document.getElementById("items").appendChild(div);
}

</script>

</body>
</html>
