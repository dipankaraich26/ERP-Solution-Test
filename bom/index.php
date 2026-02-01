<?php
include "../db.php";
include "../includes/sidebar.php";

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'beginning';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause for search
$where_clause = "";
$params = [];

if (!empty($search)) {
    $search_pattern = ($search_type === 'beginning') ? "$search%" : "%$search%";

    if ($search_field === 'bom_no') {
        $where_clause = "WHERE b.bom_no LIKE :search";
    } elseif ($search_field === 'part_name') {
        $where_clause = "WHERE p.part_name LIKE :search";
    } elseif ($search_field === 'description') {
        $where_clause = "WHERE b.description LIKE :search";
    } else {
        // Search all fields
        $where_clause = "WHERE (b.bom_no LIKE :search OR p.part_name LIKE :search2 OR b.description LIKE :search3)";
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
    }
    $params[':search'] = $search_pattern;
}

// Get total count with search
$count_sql = "
    SELECT COUNT(*) FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total_count = $count_stmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Main query with search
$sql = "
    SELECT b.id, b.bom_no, b.description, b.status,
           p.part_name,
           (SELECT COALESCE(SUM(bi.qty * pm.rate), 0)
            FROM bom_items bi
            JOIN part_master pm ON bi.component_part_no = pm.part_no
            WHERE bi.bom_id = b.id) AS bom_cost
    FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    $where_clause
    ORDER BY b.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();

// Build query string for pagination links
$query_params = [];
if (!empty($search)) $query_params['search'] = $search;
if ($search_type !== 'beginning') $query_params['search_type'] = $search_type;
if ($search_field !== 'all') $query_params['search_field'] = $search_field;
$query_string = http_build_query($query_params);
$query_string = $query_string ? '&' . $query_string : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>BOM</title>
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

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Bill of Materials</h1>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <a href="add.php" class="btn btn-primary">+ Add BOM</a>

        <!-- Search Form -->
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 8px;">
            <div>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search BOMs..."
                       style="padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; width: 200px;">
            </div>
            <div>
                <select name="search_field" style="padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px;">
                    <option value="all" <?= $search_field === 'all' ? 'selected' : '' ?>>All Fields</option>
                    <option value="bom_no" <?= $search_field === 'bom_no' ? 'selected' : '' ?>>BOM No</option>
                    <option value="part_name" <?= $search_field === 'part_name' ? 'selected' : '' ?>>Part Name</option>
                    <option value="description" <?= $search_field === 'description' ? 'selected' : '' ?>>Description</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                    <input type="radio" name="search_type" value="beginning" <?= $search_type === 'beginning' ? 'checked' : '' ?>>
                    <span>Begins with</span>
                </label>
                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                    <input type="radio" name="search_type" value="contains" <?= $search_type === 'contains' ? 'checked' : '' ?>>
                    <span>Contains</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">Search</button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($search)): ?>
    <div style="margin-bottom: 15px; padding: 10px 15px; background: #e3f2fd; border-radius: 6px; color: #1565c0;">
        Found <strong><?= $total_count ?></strong> result(s) for "<strong><?= htmlspecialchars($search) ?></strong>"
        <?= $search_type === 'beginning' ? '(begins with)' : '(contains)' ?>
        <?= $search_field !== 'all' ? 'in ' . ucfirst(str_replace('_', ' ', $search_field)) : '' ?>
    </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>BOM No</th>
            <th>Parent Part</th>
            <th>Description</th>
            <th>BOM Cost</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php
        $hasResults = false;
        while ($b = $stmt->fetch()):
            $hasResults = true;
        ?>
        <tr>
            <td><?= htmlspecialchars($b['bom_no']) ?></td>
            <td><?= htmlspecialchars($b['part_name']) ?></td>
            <td><?= htmlspecialchars($b['description']) ?></td>
            <td style="text-align: right; font-weight: bold; color: #1e3a5f;">₹ <?= number_format((float)$b['bom_cost'], 2) ?></td>
            <td><?= htmlspecialchars($b['status']) ?></td>
            <td>
                <a href="view.php?id=<?= $b['id'] ?>">View</a>
                <?php if ($b['status'] === 'active'): ?>
                    | <a class="btn btn-secondary" href="edit.php?id=<?= $b['id'] ?>">Edit</a>
                    | <a class="btn btn-secondary" href="deactivate.php?id=<?= $b['id'] ?>"
                         onclick="return confirm('Deactivate BOM?')">Deactivate</a>
                <?php endif; ?>
                | <a class="btn btn-danger" href="delete.php?id=<?= $b['id'] ?>"
                     onclick="return confirm('Are you sure you want to DELETE this BOM? This action cannot be undone.')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if (!$hasResults): ?>
        <tr>
            <td colspan="6" style="text-align: center; padding: 30px; color: #6c757d;">
                <?php if (!empty($search)): ?>
                    No BOMs found matching your search criteria.
                <?php else: ?>
                    No BOMs found. <a href="add.php">Create your first BOM</a>.
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $query_string ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $query_string ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total BOMs)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $query_string ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
