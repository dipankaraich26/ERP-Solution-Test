<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

include "../includes/sidebar.php";

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with search
$whereClause = "WHERE p.status='active'";
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

// Get total count
$countSql = "SELECT COUNT(*) FROM part_master p $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);
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
            <a href="download_parts.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>" class="btn btn-success">Download Excel</a>
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

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8" id="partsTable">
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
                $whereClause ORDER BY p.part_no LIMIT :limit OFFSET :offset";
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
            <td><?= htmlspecialchars($row['category']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= htmlspecialchars($row['uom']) ?></td>
            <td title="<?= $row['supplier_count'] > 0 ? 'From supplier' : 'From part master' ?>">
                <?= htmlspecialchars($row['display_rate']) ?>
                <?php if ($row['supplier_count'] > 0): ?>
                    <span style="color: #2563eb; font-size: 0.8em;" title="Rate from supplier">(S)</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['hsn_code']) ?></td>
            <td><?= htmlspecialchars($row['gst']) ?></td>
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
    </table>
    </div>

    <!-- JavaScript for Selection -->
    <script>
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

        // Also filter current table rows (for instant feedback on loaded data)
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const tableBody = document.querySelector('table');
            const rows = tableBody.querySelectorAll('tr:not(:first-child)');

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

            updateSelection();
        });
    });
    </script>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <?php $searchParam = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $searchParam ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $searchParam ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total parts)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $searchParam ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $searchParam ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
