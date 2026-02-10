<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

include "../includes/sidebar.php";

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Column filters
$f_part_id = isset($_GET['f_part_id']) ? trim($_GET['f_part_id']) : '';
$f_part_name = isset($_GET['f_part_name']) ? trim($_GET['f_part_name']) : '';
$f_part_no = isset($_GET['f_part_no']) ? trim($_GET['f_part_no']) : '';
$f_category = isset($_GET['f_category']) ? trim($_GET['f_category']) : '';
$f_description = isset($_GET['f_description']) ? trim($_GET['f_description']) : '';
$f_uom = isset($_GET['f_uom']) ? trim($_GET['f_uom']) : '';
$f_rate = isset($_GET['f_rate']) ? trim($_GET['f_rate']) : '';
$f_hsn = isset($_GET['f_hsn']) ? trim($_GET['f_hsn']) : '';
$f_gst = isset($_GET['f_gst']) ? trim($_GET['f_gst']) : '';
$f_stock = isset($_GET['f_stock']) ? trim($_GET['f_stock']) : '';
$f_on_order = isset($_GET['f_on_order']) ? trim($_GET['f_on_order']) : '';
$f_in_wo = isset($_GET['f_in_wo']) ? trim($_GET['f_in_wo']) : '';

// Check if any filter is active
$hasFilters = $f_part_id !== '' || $f_part_name !== '' || $f_part_no !== '' || $f_category !== '' ||
              $f_description !== '' || $f_uom !== '' || $f_rate !== '' || $f_hsn !== '' ||
              $f_gst !== '' || $f_stock !== '' || $f_on_order !== '' || $f_in_wo !== '';

// Get dropdown options from database (for entire database, not just current page)
$categoryOptions = $pdo->query("SELECT DISTINCT category FROM part_master WHERE status='active' AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$uomOptions = $pdo->query("SELECT DISTINCT uom FROM part_master WHERE status='active' AND uom IS NOT NULL AND uom != '' ORDER BY uom")->fetchAll(PDO::FETCH_COLUMN);
$gstOptions = $pdo->query("SELECT DISTINCT gst FROM part_master WHERE status='active' AND gst IS NOT NULL AND gst != '' ORDER BY gst")->fetchAll(PDO::FETCH_COLUMN);

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with search and filters
$whereClause = "WHERE p.status='active'";
$havingClause = "";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (p.part_no LIKE :search
                    OR p.part_name LIKE :search
                    OR p.part_id LIKE :search
                    OR p.category LIKE :search
                    OR p.description LIKE :search
                    OR p.hsn_code LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Apply column filters
if ($f_part_id !== '') {
    $whereClause .= " AND p.part_id LIKE :f_part_id";
    $params[':f_part_id'] = '%' . $f_part_id . '%';
}
if ($f_part_name !== '') {
    $whereClause .= " AND p.part_name LIKE :f_part_name";
    $params[':f_part_name'] = '%' . $f_part_name . '%';
}
if ($f_part_no !== '') {
    $whereClause .= " AND p.part_no LIKE :f_part_no";
    $params[':f_part_no'] = '%' . $f_part_no . '%';
}
if ($f_category !== '') {
    $whereClause .= " AND p.category = :f_category";
    $params[':f_category'] = $f_category;
}
if ($f_description !== '') {
    $whereClause .= " AND p.description LIKE :f_description";
    $params[':f_description'] = '%' . $f_description . '%';
}
if ($f_uom !== '') {
    $whereClause .= " AND p.uom = :f_uom";
    $params[':f_uom'] = $f_uom;
}
if ($f_rate !== '') {
    $whereClause .= " AND p.rate LIKE :f_rate";
    $params[':f_rate'] = '%' . $f_rate . '%';
}
if ($f_hsn !== '') {
    $whereClause .= " AND p.hsn_code LIKE :f_hsn";
    $params[':f_hsn'] = '%' . $f_hsn . '%';
}
if ($f_gst !== '') {
    $whereClause .= " AND p.gst = :f_gst";
    $params[':f_gst'] = $f_gst;
}

// Stock, On Order, In WO filters need HAVING clause since they use aggregated/computed columns
$havingConditions = [];
if ($f_stock === 'in-stock') {
    $havingConditions[] = "current_stock > 0";
} elseif ($f_stock === 'out-of-stock') {
    $havingConditions[] = "(current_stock <= 0 OR current_stock IS NULL)";
}
if ($f_on_order === 'has-orders') {
    $havingConditions[] = "on_order > 0";
} elseif ($f_on_order === 'no-orders') {
    $havingConditions[] = "(on_order <= 0 OR on_order IS NULL)";
}
if ($f_in_wo === 'in-wo') {
    $havingConditions[] = "in_wo > 0";
} elseif ($f_in_wo === 'not-in-wo') {
    $havingConditions[] = "(in_wo <= 0 OR in_wo IS NULL)";
}
if (!empty($havingConditions)) {
    $havingClause = "HAVING " . implode(" AND ", $havingConditions);
}

// Get total count - need to use subquery if we have HAVING clause
if (!empty($havingConditions)) {
    $countSql = "SELECT COUNT(*) FROM (
        SELECT p.id,
            COALESCE(i.qty, 0) as current_stock,
            COALESCE((
                SELECT SUM(po.qty) - COALESCE(SUM((SELECT COALESCE(SUM(se.received_qty),0) FROM stock_entries se WHERE se.po_id = po.id AND se.status='posted')),0)
                FROM purchase_orders po
                WHERE po.part_no = p.part_no AND po.status NOT IN ('closed', 'cancelled')
            ), 0) as on_order,
            COALESCE((
                SELECT SUM(wo.qty)
                FROM work_orders wo
                WHERE wo.part_no = p.part_no AND wo.status NOT IN ('completed', 'cancelled', 'closed')
            ), 0) as in_wo
        FROM part_master p
        LEFT JOIN inventory i ON p.part_no = i.part_no
        $whereClause
        $havingClause
    ) as filtered_parts";
} else {
    $countSql = "SELECT COUNT(*) FROM part_master p $whereClause";
}
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Build filter query string for pagination links
$filterParams = [];
if ($search !== '') $filterParams[] = 'search=' . urlencode($search);
if ($f_part_id !== '') $filterParams[] = 'f_part_id=' . urlencode($f_part_id);
if ($f_part_name !== '') $filterParams[] = 'f_part_name=' . urlencode($f_part_name);
if ($f_part_no !== '') $filterParams[] = 'f_part_no=' . urlencode($f_part_no);
if ($f_category !== '') $filterParams[] = 'f_category=' . urlencode($f_category);
if ($f_description !== '') $filterParams[] = 'f_description=' . urlencode($f_description);
if ($f_uom !== '') $filterParams[] = 'f_uom=' . urlencode($f_uom);
if ($f_rate !== '') $filterParams[] = 'f_rate=' . urlencode($f_rate);
if ($f_hsn !== '') $filterParams[] = 'f_hsn=' . urlencode($f_hsn);
if ($f_gst !== '') $filterParams[] = 'f_gst=' . urlencode($f_gst);
if ($f_stock !== '') $filterParams[] = 'f_stock=' . urlencode($f_stock);
if ($f_on_order !== '') $filterParams[] = 'f_on_order=' . urlencode($f_on_order);
if ($f_in_wo !== '') $filterParams[] = 'f_in_wo=' . urlencode($f_in_wo);
$filterQueryString = !empty($filterParams) ? '&' . implode('&', $filterParams) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Part Master</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .bulk-actions {
            display: none;
            align-items: center;
            gap: 15px;
            padding: 10px 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .bulk-actions.active {
            display: flex;
        }
        .selected-count {
            font-weight: bold;
            color: #856404;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        tr.selected {
            background-color: #fff3cd !important;
        }
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
        .result-category {
            color: #888;
            font-size: 0.85em;
            margin-top: 2px;
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

        /* Column Filter Styles */
        .filter-row th {
            background: #f8f9fa;
            padding: 5px 8px;
            vertical-align: top;
        }
        .column-filter {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            box-sizing: border-box;
        }
        .column-filter:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        .column-filter::placeholder {
            color: #aaa;
            font-style: italic;
        }
        .filter-select {
            width: 100%;
            padding: 5px 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-active {
            background-color: #fff3cd !important;
            border-color: #ffc107 !important;
        }
        .clear-filters-btn {
            padding: 4px 10px;
            font-size: 11px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .clear-filters-btn:hover {
            background: #c82333;
        }
        .filter-info {
            padding: 8px 12px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 10px;
            display: none;
            font-size: 0.9em;
            color: #155724;
        }
        .filter-info.active {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Part Master</h1>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <a href="add.php" class="btn btn-primary">Add Part</a>
            <a href="inactive.php" class="btn btn-primary">View Inactive Parts</a>
            <a href="import.php" class="btn btn-primary">Import from Excel</a>
            <a href="download_template.php" class="btn btn-secondary">Download Template</a>
            <a href="download_parts.php?<?= ltrim($filterQueryString, '&') ?>" class="btn btn-success">Download Excel</a>
        </div>

        <!-- Search Form with Dynamic Filtering -->
        <form method="get" style="display: flex; gap: 10px; align-items: center;">
            <div class="search-container">
                <input type="text" name="search" id="searchInput"
                       placeholder="Search by part no, name, category, HSN..."
                       value="<?= htmlspecialchars($search) ?>"
                       autocomplete="off"
                       style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; width: 320px;">
                <div class="search-dropdown" id="searchDropdown">
                    <div class="search-loading" id="searchLoading" style="display:none;">Searching...</div>
                    <div id="searchResults"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="list.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($search !== ''): ?>
    <div style="margin-bottom: 15px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
        Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
        (<?= $total_count ?> part<?= $total_count != 1 ? 's' : '' ?> found)
    </div>
    <?php endif; ?>

    <!-- Bulk Actions Bar -->
    <div class="bulk-actions" id="bulkActions">
        <span class="selected-count"><span id="selectedCount">0</span> part(s) selected</span>
        <button type="button" class="btn btn-danger" onclick="deleteSelected()">Delete Selected</button>
        <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
    </div>

    <form id="bulkDeleteForm" method="post" action="bulk_delete.php">
        <input type="hidden" name="ids" id="selectedIds">
    </form>

    <!-- Filter Info Bar -->
    <?php if ($hasFilters): ?>
    <div class="filter-info active">
        <span><strong><?= $total_count ?></strong> parts found with current filters</span>
        <a href="list.php" class="clear-filters-btn">Clear All Filters</a>
    </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8" id="partsTable">
        <thead>
        <tr>
            <th class="checkbox-cell">
                <input type="checkbox" id="selectAll" title="Select All" onclick="toggleSelectAll()">
            </th>
            <th>Part ID</th>
            <th>Part Name</th>
            <th>Part No</th>
            <th>Category</th>
            <th>Description</th>
            <th>UOM</th>
            <th>Rate</th>
            <th>HSN</th>
            <th>GST</th>
            <th>Stock</th>
            <th>On Order</th>
            <th>In WO</th>
            <th>Actions</th>
        </tr>
        <tr class="filter-row">
            <th class="checkbox-cell">
                <?php if ($hasFilters): ?>
                <a href="list.php" class="clear-filters-btn" title="Clear all filters">✕</a>
                <?php endif; ?>
            </th>
            <th><input type="text" class="column-filter <?= $f_part_id !== '' ? 'filter-active' : '' ?>" name="f_part_id" placeholder="Filter..." value="<?= htmlspecialchars($f_part_id) ?>" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th><input type="text" class="column-filter <?= $f_part_name !== '' ? 'filter-active' : '' ?>" name="f_part_name" placeholder="Filter..." value="<?= htmlspecialchars($f_part_name) ?>" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th><input type="text" class="column-filter <?= $f_part_no !== '' ? 'filter-active' : '' ?>" name="f_part_no" placeholder="Filter..." value="<?= htmlspecialchars($f_part_no) ?>" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th>
                <select class="filter-select <?= $f_category !== '' ? 'filter-active' : '' ?>" name="f_category" onchange="applyFilters()">
                    <option value="">All</option>
                    <?php foreach ($categoryOptions as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $f_category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </th>
            <th><input type="text" class="column-filter <?= $f_description !== '' ? 'filter-active' : '' ?>" name="f_description" placeholder="Filter..." value="<?= htmlspecialchars($f_description) ?>" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th>
                <select class="filter-select <?= $f_uom !== '' ? 'filter-active' : '' ?>" name="f_uom" onchange="applyFilters()">
                    <option value="">All</option>
                    <?php foreach ($uomOptions as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= $f_uom === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </th>
            <th><input type="text" class="column-filter <?= $f_rate !== '' ? 'filter-active' : '' ?>" name="f_rate" placeholder="Filter..." value="<?= htmlspecialchars($f_rate) ?>" style="width: 60px;" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th><input type="text" class="column-filter <?= $f_hsn !== '' ? 'filter-active' : '' ?>" name="f_hsn" placeholder="Filter..." value="<?= htmlspecialchars($f_hsn) ?>" style="width: 70px;" onkeydown="if(event.key==='Enter'){applyFilters();}"></th>
            <th>
                <select class="filter-select <?= $f_gst !== '' ? 'filter-active' : '' ?>" name="f_gst" onchange="applyFilters()">
                    <option value="">All</option>
                    <?php foreach ($gstOptions as $g): ?>
                    <option value="<?= htmlspecialchars($g) ?>" <?= $f_gst === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </th>
            <th>
                <select class="filter-select <?= $f_stock !== '' ? 'filter-active' : '' ?>" name="f_stock" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="in-stock" <?= $f_stock === 'in-stock' ? 'selected' : '' ?>>In Stock</option>
                    <option value="out-of-stock" <?= $f_stock === 'out-of-stock' ? 'selected' : '' ?>>Out of Stock</option>
                </select>
            </th>
            <th>
                <select class="filter-select <?= $f_on_order !== '' ? 'filter-active' : '' ?>" name="f_on_order" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="has-orders" <?= $f_on_order === 'has-orders' ? 'selected' : '' ?>>Has Orders</option>
                    <option value="no-orders" <?= $f_on_order === 'no-orders' ? 'selected' : '' ?>>No Orders</option>
                </select>
            </th>
            <th>
                <select class="filter-select <?= $f_in_wo !== '' ? 'filter-active' : '' ?>" name="f_in_wo" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="in-wo" <?= $f_in_wo === 'in-wo' ? 'selected' : '' ?>>In WO</option>
                    <option value="not-in-wo" <?= $f_in_wo === 'not-in-wo' ? 'selected' : '' ?>>Not in WO</option>
                </select>
            </th>
            <th><button type="button" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;" onclick="applyFilters()">Filter</button></th>
        </tr>
        </thead>
        <tbody>

        <?php
        $sql = "SELECT p.*,
                COALESCE(i.qty, 0) as current_stock,
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
                    SELECT psm.supplier_rate
                    FROM part_supplier_mapping psm
                    WHERE psm.part_no = p.part_no AND psm.active = 1
                    ORDER BY psm.is_preferred DESC, psm.id ASC
                    LIMIT 1
                ), p.rate) as display_rate,
                (SELECT COUNT(*) FROM part_supplier_mapping psm WHERE psm.part_no = p.part_no AND psm.active = 1) as supplier_count
                FROM part_master p
                LEFT JOIN inventory i ON p.part_no = i.part_no
                $whereClause $havingClause ORDER BY p.part_no LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()):
        ?>
        <tr data-id="<?= $row['id'] ?>">
            <td class="checkbox-cell">
                <input type="checkbox" class="part-checkbox" value="<?= $row['id'] ?>" onclick="updateSelection()">
            </td>
            <td><?= htmlspecialchars($row['part_id']) ?></td>
            <td><?= htmlspecialchars($row['part_name']) ?></td>
            <td><?= htmlspecialchars($row['part_no']) ?></td>
            <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['uom']) ?></td>
            <td title="<?= $row['supplier_count'] > 0 ? 'From supplier' : 'From part master' ?>">
                <?= htmlspecialchars($row['display_rate']) ?>
                <?php if ($row['supplier_count'] > 0): ?>
                    <span style="color: #2563eb; font-size: 0.8em;" title="Rate from supplier">(S)</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['hsn_code'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['gst'] ?? '') ?></td>
            <td style="text-align: center; font-weight: bold; color: <?= $row['current_stock'] > 0 ? '#16a34a' : '#dc2626' ?>;">
                <?= $row['current_stock'] ?>
            </td>
            <td style="text-align: center; font-weight: bold; color: <?= $row['on_order'] > 0 ? '#2563eb' : '#888' ?>;">
                <?= $row['on_order'] > 0 ? $row['on_order'] : '-' ?>
            </td>
            <td style="text-align: center; font-weight: bold; color: <?= $row['in_wo'] > 0 ? '#9333ea' : '#888' ?>;">
                <?= $row['in_wo'] > 0 ? $row['in_wo'] : '-' ?>
            </td>
            <td style="white-space: nowrap;">
                <a class="btn btn-secondary" href="edit.php?part_no=<?= $row['part_no'] ?>">Edit</a> |
                <a class="btn btn-secondary" href="suppliers.php?part_no=<?= urlencode($row['part_no']) ?>">Suppliers</a> |
                <a class="btn btn-secondary" href="min_stock.php?part_no=<?= urlencode($row['part_no']) ?>">Stock</a>

                <?php if (!empty($row['attachment_path'])): ?>
                    | <a class="btn btn-secondary"
                    href="../<?= htmlspecialchars($row['attachment_path']) ?>"
                    target="_blank">
                    PDF
                    </a>
                <?php endif; ?>

                | <a class="btn btn-secondary"
                href="deactivate.php?id=<?= $row['id'] ?>"
                onclick="return confirm('Deactivate this part?')">
                Deactivate
                </a>
                | <a class="btn btn-danger"
                href="delete.php?id=<?= $row['id'] ?>"
                onclick="return confirm('Are you sure you want to DELETE this part permanently? This cannot be undone.')">
                Delete
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <!-- JavaScript for Selection and Filtering -->
    <script>
    // Server-side filtering - collect filter values and redirect
    function applyFilters() {
        const params = new URLSearchParams();

        // Get all filter inputs
        const filters = document.querySelectorAll('.column-filter, .filter-select');
        filters.forEach(filter => {
            const name = filter.name;
            const value = filter.value.trim();
            if (name && value !== '') {
                params.set(name, value);
            }
        });

        // Preserve search if exists
        const searchInput = document.getElementById('searchInput');
        if (searchInput && searchInput.value.trim() !== '') {
            params.set('search', searchInput.value.trim());
        }

        // Navigate with filters
        const queryString = params.toString();
        window.location.href = 'list.php' + (queryString ? '?' + queryString : '');
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.part-checkbox');

        checkboxes.forEach(cb => {
            // Only select visible rows
            const row = cb.closest('tr');
            if (row.style.display !== 'none') {
                cb.checked = selectAll.checked;
                if (selectAll.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            }
        });

        updateSelection();
    }

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.part-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulkActions');
        const countDisplay = document.getElementById('selectedCount');
        const selectAll = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.part-checkbox');

        countDisplay.textContent = count;

        if (count > 0) {
            bulkActions.classList.add('active');
        } else {
            bulkActions.classList.remove('active');
        }

        // Update row highlighting
        allCheckboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (cb.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });

        // Update select all checkbox state
        const visibleCheckboxes = Array.from(allCheckboxes).filter(cb => cb.closest('tr').style.display !== 'none');
        const checkedVisible = visibleCheckboxes.filter(cb => cb.checked);
        selectAll.checked = visibleCheckboxes.length > 0 && checkedVisible.length === visibleCheckboxes.length;
        selectAll.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visibleCheckboxes.length;
    }

    function clearSelection() {
        const checkboxes = document.querySelectorAll('.part-checkbox');
        const selectAll = document.getElementById('selectAll');

        checkboxes.forEach(cb => {
            cb.checked = false;
            cb.closest('tr').classList.remove('selected');
        });
        selectAll.checked = false;
        selectAll.indeterminate = false;

        updateSelection();
    }

    function deleteSelected() {
        const checkboxes = document.querySelectorAll('.part-checkbox:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);

        if (ids.length === 0) {
            alert('Please select at least one part to delete.');
            return;
        }

        const confirmMsg = ids.length === 1
            ? 'Are you sure you want to DELETE this part permanently? This cannot be undone.'
            : `Are you sure you want to DELETE ${ids.length} parts permanently? This cannot be undone.`;

        if (confirm(confirmMsg)) {
            document.getElementById('selectedIds').value = ids.join(',');
            document.getElementById('bulkDeleteForm').submit();
        }
    }

    // Dynamic AJAX Search
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchDropdown = document.getElementById('searchDropdown');
        const searchResults = document.getElementById('searchResults');
        const searchLoading = document.getElementById('searchLoading');

        let debounceTimer;
        let highlightedIndex = -1;
        let currentResults = [];

        // Debounced search function
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
                        searchResults.innerHTML = '<div class="search-no-results">No parts found</div>';
                    } else {
                        let html = '';
                        data.results.forEach((part, index) => {
                            const partNo = highlightText(part.part_no || '', query);
                            const partName = highlightText(part.part_name || '', query);
                            const category = highlightText(part.category || '', query);
                            const stock = part.current_stock || 0;
                            const onOrder = part.on_order || 0;
                            const inWo = part.in_wo || 0;
                            const stockColor = stock > 0 ? '#16a34a' : '#dc2626';

                            let stockInfo = `<span style="color: ${stockColor};">Stock: ${stock}</span>`;
                            if (onOrder > 0) stockInfo += ` | <span style="color: #2563eb;">On Order: ${onOrder}</span>`;
                            if (inWo > 0) stockInfo += ` | <span style="color: #9333ea;">In WO: ${inWo}</span>`;

                            const displayRate = part.display_rate || part.rate || 0;
                            const rateFromSupplier = part.supplier_count > 0;
                            const rateInfo = rateFromSupplier
                                ? `Rate: ${displayRate} <span style="color: #2563eb;">(S)</span>`
                                : `Rate: ${displayRate}`;

                            html += `<div class="search-result-item" data-index="${index}"
                                         onclick="selectPart('${escapeHtml(part.part_no)}', ${part.id})">
                                <div class="result-part-no">${partNo}</div>
                                <div class="result-part-name">${partName}</div>
                                <div class="result-category">${category} | HSN: ${part.hsn_code || '-'} | ${rateInfo}</div>
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

        // Highlight matching text
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

        // Select a part and navigate to edit page
        window.selectPart = function(partNo, id) {
            window.location.href = 'edit.php?part_no=' + encodeURIComponent(partNo);
        };

        // Input event with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            debounceTimer = setTimeout(() => {
                performSearch(query);
            }, 200); // 200ms debounce
        });

        // Keyboard navigation
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
                const part = currentResults[highlightedIndex];
                selectPart(part.part_no, part.id);
            } else if (e.key === 'Escape') {
                hideDropdown();
            }
        });

        // Focus events
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 1 && currentResults.length > 0) {
                searchDropdown.classList.add('active');
            }
        });

        // Click outside to close
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                hideDropdown();
            }
        });

        // Note: Column-based filtering is now handled by filterTable() function
    });
    </script>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $filterQueryString ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $filterQueryString ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total parts)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $filterQueryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $filterQueryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
