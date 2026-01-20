<html><head><link rel="stylesheet" href="/assets/style.css"></head></html>


<?php
include "../db.php";
include "../includes/sidebar.php";

$view = $_GET['view'] ?? 'normal';
$qty_filter = isset($_GET['qty']) && $_GET['qty'] !== '' ? (int)$_GET['qty'] : null;

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause based on filters
$where_clause = '';
$bind_params = [];

if ($qty_filter !== null) {
    // If quantity filter is set, use exact match
    $where_clause = "i.qty = :qty_filter";
    $bind_params[':qty_filter'] = $qty_filter;
} elseif ($view === 'zero') {
    $where_clause = "i.qty = 0";
} else {
    $where_clause = "i.qty > 0";
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM inventory i WHERE " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
foreach ($bind_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Get paginated results
$sql = "
    SELECT i.part_no, p.part_name, i.qty
    FROM inventory i
    JOIN part_master p ON p.part_no = i.part_no
    WHERE " . $where_clause . "
    ORDER BY p.part_name
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($bind_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$inv = $stmt;
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
<h1>Inventory</h1>

<!-- Filter Controls -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
    <div>
        <label for="qty_filter" style="font-weight: 600; display: block; margin-bottom: 5px;">Filter by Quantity:</label>
        <form method="get" style="display: flex; gap: 5px;">
            <input type="number" id="qty_filter" name="qty" step="1" min="0"
                   value="<?= isset($_GET['qty']) && $_GET['qty'] !== '' ? htmlspecialchars($_GET['qty']) : '' ?>"
                   placeholder="Enter quantity" style="width: 150px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <div style="display: flex; gap: 5px;">
        <?php if ($view === 'zero'): ?>
            <a href="index.php" class="btn">Show Available Stock</a>
        <?php else: ?>
            <a href="index.php?view=zero" class="btn">Show Zero Stock Data</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($qty_filter !== null): ?>
    <p style="margin-bottom: 10px;"><strong>Showing items with quantity: <?= htmlspecialchars($qty_filter) ?></strong></p>
<?php endif; ?>

<div style="overflow-x: auto;">
<table>
<tr>
    <th>Part No</th>
    <th>Name</th>
    <th>Qty</th>
</tr>

<?php while ($r = $inv->fetch()): ?>
<tr>
    <td><?= htmlspecialchars($r['part_no']) ?></td>
    <td><?= htmlspecialchars($r['part_name']) ?></td>
    <td><?= $r['qty'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<?php
$pagination_params = [];
if ($view !== 'normal') $pagination_params[] = 'view=' . urlencode($view);
if ($qty_filter !== null) $pagination_params[] = 'qty=' . urlencode($qty_filter);
$pagination_base = count($pagination_params) > 0 ? '&' . implode('&', $pagination_params) : '';
?>
<div style="margin-top: 20px; text-align: center;">
    <?php if ($page > 1): ?>
        <a href="?page=1<?= $pagination_base ?>" class="btn btn-secondary">First</a>
        <a href="?page=<?= $page - 1 ?><?= $pagination_base ?>" class="btn btn-secondary">Previous</a>
    <?php endif; ?>

    <span style="margin: 0 10px;">
        Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total items)
    </span>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?><?= $pagination_base ?>" class="btn btn-secondary">Next</a>
        <a href="?page=<?= $total_pages ?><?= $pagination_base ?>" class="btn btn-secondary">Last</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
