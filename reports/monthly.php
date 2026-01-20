<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>

<?php
include "../db.php";
include "../includes/sidebar.php";

/* GET SELECTED MONTH/YEAR OR DEFAULT TO CURRENT */
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
list($year, $month) = explode('-', $selected_month);

/* MONTHLY CONSUMPTION REPORT */
$stmt = $pdo->prepare("
    SELECT
        p.part_name,
        d.part_no,
        SUM(d.qty) AS total_issued
    FROM depletion d
    JOIN part_master p ON p.part_no = d.part_no
    WHERE MONTH(d.issue_date) = ?
      AND YEAR(d.issue_date) = ?
    GROUP BY d.part_no
");
$stmt->execute([$month, $year]);
$rows = $stmt;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Monthly Report</title>
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
    <h1>Monthly Consumption Report</h1>

    <!-- Month Filter -->
    <form method="get" style="margin-bottom: 20px;">
        <label for="month" style="font-weight: 600; margin-right: 10px;">Select Month:</label>
        <input type="month" id="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" required>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="monthly.php" class="btn btn-secondary">Reset</a>
    </form>

    <p><strong>Showing data for: <?= date('F Y', strtotime($selected_month . '-01')) ?></strong></p>

    <table border="1" cellpadding="8">
        <tr>
            <th>Part</th>
            <th>Part No</th>
            <th>Total Issued</th>
        </tr>

        <?php while ($r = $rows->fetch()): ?>
        <tr>
            <td><?= htmlspecialchars($r['part_name']) ?></td>
            <td><?= $r['part_no'] ?></td>
            <td><?= $r['total_issued'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
