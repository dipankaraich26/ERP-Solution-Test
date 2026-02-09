<html><head>
<link rel="stylesheet" href="/assets/style.css">
<style>
    /* Dynamic Search Styles */
    .search-container {
        position: relative;
        display: inline-block;
    }
    .search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ccc;
        border-top: none;
        border-radius: 0 0 4px 4px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: none;
    }
    .search-dropdown.active {
        display: block;
    }
    .search-result-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background 0.15s;
    }
    .search-result-item:hover,
    .search-result-item.highlighted {
        background: #f0f7ff;
    }
    .search-result-item:last-child {
        border-bottom: none;
    }
    .result-part-no {
        font-weight: bold;
        color: #333;
    }
    .result-part-name {
        color: #666;
        font-size: 0.9em;
    }
    .result-qty {
        color: #16a34a;
        font-size: 0.85em;
        margin-top: 2px;
    }
    .result-qty.zero {
        color: #dc2626;
    }
    .search-loading {
        padding: 15px;
        text-align: center;
        color: #666;
    }
    .search-no-results {
        padding: 15px;
        text-align: center;
        color: #888;
    }
    .search-total {
        padding: 8px 12px;
        background: #f5f5f5;
        font-size: 0.85em;
        color: #666;
        border-top: 1px solid #ddd;
    }
    .highlight-match {
        background: #fff3cd;
        font-weight: bold;
    }
</style>
</head></html>


<?php
include "../db.php";

$view = $_GET['view'] ?? 'normal';
$qty_filter = isset($_GET['qty']) && $_GET['qty'] !== '' ? (int)$_GET['qty'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause based on filters
$where_conditions = [];
$bind_params = [];

if ($search !== '') {
    $where_conditions[] = "(p.part_no LIKE :search OR p.part_name LIKE :search)";
    $bind_params[':search'] = '%' . $search . '%';
}

if ($qty_filter !== null) {
    $where_conditions[] = "COALESCE(i.qty, 0) = :qty_filter";
    $bind_params[':qty_filter'] = $qty_filter;
} elseif ($view === 'zero') {
    $where_conditions[] = "(i.qty IS NULL OR i.qty = 0)";
} elseif ($search === '') {
    $where_conditions[] = "i.qty > 0";
}

$where_clause = count($where_conditions) > 0 ? implode(' AND ', $where_conditions) : '1=1';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "
        SELECT p.part_no, p.part_name, COALESCE(i.qty, 0) as qty,
               COALESCE((
                   SELECT SUM(po.qty) - COALESCE(SUM((SELECT COALESCE(SUM(se.received_qty),0) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted')),0)
                   FROM purchase_orders po
                   WHERE po.part_no = p.part_no AND po.status NOT IN ('closed', 'cancelled')
               ), 0) as on_order,
               COALESCE((
                   SELECT SUM(wo.qty)
                   FROM work_orders wo
                   WHERE wo.part_no = p.part_no AND wo.status NOT IN ('completed', 'cancelled', 'closed')
               ), 0) as in_wo,
               COALESCE((
                   SELECT SUM(so.qty)
                   FROM sales_orders so
                   WHERE so.part_no = p.part_no AND so.status NOT IN ('completed', 'cancelled')
               ), 0) as on_so
        FROM part_master p
        LEFT JOIN inventory i ON p.part_no = i.part_no
        WHERE p.status = 'active' AND " . $where_clause . "
        ORDER BY p.part_name
    ";
    $export_stmt = $pdo->prepare($export_sql);
    foreach ($bind_params as $key => $value) {
        $export_stmt->bindValue($key, $value);
    }
    $export_stmt->execute();

    $filename = 'inventory_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Part No', 'Part Name', 'Stock', 'On Order', 'In WO', 'On SO']);
    while ($row = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['part_no'], $row['part_name'], $row['qty'], $row['on_order'], $row['in_wo'], $row['on_so']]);
    }
    fclose($output);
    exit;
}

include "../includes/sidebar.php";

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count - use LEFT JOIN from part_master to include parts not in inventory
$count_sql = "SELECT COUNT(*) FROM part_master p LEFT JOIN inventory i ON p.part_no = i.part_no WHERE p.status = 'active' AND " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
foreach ($bind_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_count = $count_stmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Get paginated results - LEFT JOIN to include parts not in inventory
$sql = "
    SELECT p.part_no, p.part_name, COALESCE(i.qty, 0) as qty,
           COALESCE((
               SELECT SUM(po.qty) - COALESCE(SUM((SELECT COALESCE(SUM(se.received_qty),0) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted')),0)
               FROM purchase_orders po
               WHERE po.part_no = p.part_no AND po.status NOT IN ('closed', 'cancelled')
           ), 0) as on_order,
           COALESCE((
               SELECT SUM(wo.qty)
               FROM work_orders wo
               WHERE wo.part_no = p.part_no AND wo.status NOT IN ('completed', 'cancelled', 'closed')
           ), 0) as in_wo,
           COALESCE((
               SELECT SUM(so.qty)
               FROM sales_orders so
               WHERE so.part_no = p.part_no AND so.status NOT IN ('completed', 'cancelled')
           ), 0) as on_so
    FROM part_master p
    LEFT JOIN inventory i ON p.part_no = i.part_no
    WHERE p.status = 'active' AND " . $where_clause . "
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
        toggle.textContent = "Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "Dark Mode";
        }
    });
}
</script>

<div class="content">
<h1>Inventory</h1>

<!-- Filter Controls -->
<div style="margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
    <!-- Dynamic Search -->
    <div>
        <label style="font-weight: 600; display: block; margin-bottom: 5px;">Search Part:</label>
        <form method="get" style="display: flex; gap: 5px;">
            <div class="search-container">
                <input type="text" name="search" id="searchInput"
                       placeholder="Search by part no or name..."
                       value="<?= htmlspecialchars($search) ?>"
                       autocomplete="off"
                       style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 280px;">
                <div class="search-dropdown" id="searchDropdown">
                    <div class="search-loading" id="searchLoading" style="display:none;">Searching...</div>
                    <div id="searchResults"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="index.php<?= $view !== 'normal' ? '?view=' . urlencode($view) : '' ?>" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Quantity Filter -->
    <div>
        <label for="qty_filter" style="font-weight: 600; display: block; margin-bottom: 5px;">Filter by Qty:</label>
        <form method="get" style="display: flex; gap: 5px;">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <input type="number" id="qty_filter" name="qty" step="1" min="0"
                   value="<?= isset($_GET['qty']) && $_GET['qty'] !== '' ? htmlspecialchars($_GET['qty']) : '' ?>"
                   placeholder="Qty" style="width: 80px;">
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>

    <!-- View Toggle -->
    <div style="display: flex; gap: 5px;">
        <?php if ($view === 'zero'): ?>
            <a href="index.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>" class="btn">Show Available Stock</a>
        <?php else: ?>
            <a href="index.php?view=zero<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="btn">Show Zero Stock</a>
        <?php endif; ?>
    </div>

    <!-- Export -->
    <div style="margin-left: auto;">
        <?php
        $export_params = ['export=csv'];
        if ($view !== 'normal') $export_params[] = 'view=' . urlencode($view);
        if ($qty_filter !== null) $export_params[] = 'qty=' . urlencode($qty_filter);
        if ($search !== '') $export_params[] = 'search=' . urlencode($search);
        $export_url = 'index.php?' . implode('&', $export_params);
        ?>
        <a href="<?= $export_url ?>" class="btn btn-success" title="Export filtered items to CSV">Export CSV (<?= $total_count ?> items)</a>
    </div>
</div>

<?php if ($search !== ''): ?>
    <div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
        Search results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
        (<?= $total_count ?> item<?= $total_count != 1 ? 's' : '' ?> found)
    </div>
<?php endif; ?>

<?php if ($qty_filter !== null): ?>
    <p style="margin-bottom: 10px;"><strong>Showing items with quantity: <?= htmlspecialchars($qty_filter) ?></strong></p>
<?php endif; ?>

<div style="overflow-x: auto;">
<table id="inventoryTable">
<tr>
    <th>Part No</th>
    <th>Name</th>
    <th>Stock</th>
    <th>On Order</th>
    <th>In WO</th>
    <th>On SO</th>
</tr>

<?php while ($r = $inv->fetch()): ?>
<tr>
    <td><?= htmlspecialchars($r['part_no']) ?></td>
    <td><?= htmlspecialchars($r['part_name']) ?></td>
    <td style="text-align: center; color: <?= $r['qty'] > 0 ? '#16a34a' : '#dc2626' ?>; font-weight: bold;"><?= $r['qty'] ?></td>
    <td style="text-align: center; font-weight: bold; color: <?= $r['on_order'] > 0 ? '#2563eb' : '#888' ?>;"><?= $r['on_order'] > 0 ? $r['on_order'] : '-' ?></td>
    <td style="text-align: center; font-weight: bold; color: <?= $r['in_wo'] > 0 ? '#9333ea' : '#888' ?>;"><?= $r['in_wo'] > 0 ? $r['in_wo'] : '-' ?></td>
    <td style="text-align: center; font-weight: bold; color: <?= $r['on_so'] > 0 ? '#ea580c' : '#888' ?>;"><?= $r['on_so'] > 0 ? $r['on_so'] : '-' ?></td>
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
if ($search !== '') $pagination_params[] = 'search=' . urlencode($search);
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

<!-- Dynamic Search JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchResults = document.getElementById('searchResults');
    const searchLoading = document.getElementById('searchLoading');

    let debounceTimer;
    let highlightedIndex = -1;
    let currentResults = [];

    function performSearch(query) {
        if (query.length < 1) {
            hideDropdown();
            return;
        }

        searchLoading.style.display = 'block';
        searchResults.innerHTML = '';
        searchDropdown.classList.add('active');

        fetch('search_api.php?q=' + encodeURIComponent(query) + '&limit=15')
            .then(response => response.json())
            .then(data => {
                searchLoading.style.display = 'none';
                currentResults = data.results;
                highlightedIndex = -1;

                if (data.results.length === 0) {
                    searchResults.innerHTML = '<div class="search-no-results">No items found</div>';
                } else {
                    let html = '';
                    data.results.forEach((item, index) => {
                        const partNo = highlightText(item.part_no || '', query);
                        const partName = highlightText(item.part_name || '', query);
                        const qtyClass = item.qty > 0 ? '' : ' zero';
                        const onOrder = item.on_order || 0;
                        const inWo = item.in_wo || 0;
                        const onSo = item.on_so || 0;

                        let stockInfo = `<span style="color: ${item.qty > 0 ? '#16a34a' : '#dc2626'};">Stock: ${item.qty}</span>`;
                        if (onOrder > 0) stockInfo += ` | <span style="color: #2563eb;">On Order: ${onOrder}</span>`;
                        if (inWo > 0) stockInfo += ` | <span style="color: #9333ea;">In WO: ${inWo}</span>`;
                        if (onSo > 0) stockInfo += ` | <span style="color: #ea580c;">On SO: ${onSo}</span>`;

                        html += `<div class="search-result-item" data-index="${index}"
                                     onclick="selectItem('${escapeHtml(item.part_no)}')">
                            <div class="result-part-no">${partNo}</div>
                            <div class="result-part-name">${partName}</div>
                            <div style="font-size: 0.85em; margin-top: 3px;">${stockInfo}</div>
                        </div>`;
                    });

                    if (data.total > data.count) {
                        html += `<div class="search-total">Showing ${data.count} of ${data.total} results. Press Enter to see all.</div>`;
                    }

                    searchResults.innerHTML = html;
                }
            })
            .catch(err => {
                searchLoading.style.display = 'none';
                searchResults.innerHTML = '<div class="search-no-results">Search error. Please try again.</div>';
            });
    }

    function highlightText(text, query) {
        if (!text || !query) return escapeHtml(text);
        const regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        return escapeHtml(text).replace(regex, '<span class="highlight-match">$1</span>');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function hideDropdown() {
        searchDropdown.classList.remove('active');
        highlightedIndex = -1;
    }

    function updateHighlight() {
        const items = searchResults.querySelectorAll('.search-result-item');
        items.forEach((item, index) => {
            if (index === highlightedIndex) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    window.selectItem = function(partNo) {
        searchInput.value = partNo;
        hideDropdown();
        searchInput.closest('form').submit();
    };

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        // Also filter current table rows instantly
        filterTableRows(query);

        debounceTimer = setTimeout(() => {
            performSearch(query);
        }, 200);
    });

    searchInput.addEventListener('keydown', function(e) {
        const items = searchResults.querySelectorAll('.search-result-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (highlightedIndex < items.length - 1) {
                highlightedIndex++;
                updateHighlight();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (highlightedIndex > 0) {
                highlightedIndex--;
                updateHighlight();
            }
        } else if (e.key === 'Enter' && highlightedIndex >= 0 && currentResults[highlightedIndex]) {
            e.preventDefault();
            selectItem(currentResults[highlightedIndex].part_no);
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    });

    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 1 && currentResults.length > 0) {
            searchDropdown.classList.add('active');
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
            hideDropdown();
        }
    });

    // Filter table rows instantly
    function filterTableRows(searchTerm) {
        searchTerm = searchTerm.toLowerCase();
        const table = document.getElementById('inventoryTable');
        const rows = table.querySelectorAll('tr:not(:first-child)');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let found = false;

            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                    found = true;
                }
            });

            row.style.display = found ? '' : 'none';
        });
    }
});
</script>
