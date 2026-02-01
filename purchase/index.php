<?php
include "../db.php";
include "../includes/dialog.php";

/* =========================
   HANDLE PO CREATION
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Generate next PO number server-side (PO-1, PO-2, ...)
    $maxNo = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(po_no,4) AS UNSIGNED)), 0) FROM purchase_orders WHERE po_no LIKE 'PO-%'")->fetchColumn();
    $nextNo = ((int)$maxNo) + 1;
    $po_no = 'PO-' . $nextNo;

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $date = $_POST['purchase_date'] ?? '';
    $parts_post = $_POST['part_no'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $rates = $_POST['rate'] ?? [];

    // normalize to arrays
    if (!is_array($parts_post)) $parts_post = [$parts_post];
    if (!is_array($qtys)) $qtys = [$qtys];
    if (!is_array($rates)) $rates = [$rates];

    // build items list (skip empty part values)
    $items = [];
    $max = max(count($parts_post), count($qtys));
    for ($i = 0; $i < $max; $i++) {
        $p = $parts_post[$i] ?? '';
        $q = isset($qtys[$i]) ? (int)$qtys[$i] : 0;
        $r = isset($rates[$i]) ? (float)$rates[$i] : 0;
        if ($p === '') continue;
        $items[] = ['part_no' => $p, 'qty' => $q, 'rate' => $r];
    }

    if ($supplier_id <= 0) {
        setModal("Failed to add PO", "Supplier is required");
        header("Location: index.php");
        exit;
    }

    if ($date === '') {
        setModal("Failed to add PO", "Purchase date is required");
        header("Location: index.php");
        exit;
    }

    if (empty($items)) {
        setModal("Failed to add PO", "Select at least one part with quantity");
        header("Location: index.php");
        exit;
    }

    // validate quantities
    foreach ($items as $it) {
        if ($it['qty'] <= 0) {
            setModal("Failed to add PO", "Quantity must be more than 0 for all parts");
            header("Location: index.php");
            exit;
        }
    }

    // Insert multiple purchase orders in a transaction. All items use the same generated PO number.
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders
            (po_no, part_no, qty, rate, purchase_date, status, supplier_id)
            VALUES (?, ?, ?, ?, ?, 'open', ?)
        ");

        foreach ($items as $it) {
            $stmt->execute([
                $po_no,
                $it['part_no'],
                $it['qty'],
                $it['rate'],
                $date,
                $supplier_id
            ]);
        }

        $pdo->commit();
        setModal("Success", "Purchase Order $po_no created successfully!");
        header("Location: index.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        setModal("Failed to add PO", $e->getMessage());
        header("Location: index.php");
        exit;
    }
}



/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count of grouped purchase orders
$total_count = $pdo->query("
    SELECT COUNT(DISTINCT po_no) FROM purchase_orders
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

/* =========================
   FETCH PURCHASE ORDERS (grouped by PO number)
   Each PO will show all its line items in one row (parts list)
========================= */
$stmt = $pdo->prepare("
    SELECT
        po.po_no,
        po.purchase_date,
        s.supplier_name,
        GROUP_CONCAT(CONCAT(po.id, '::', po.part_no, '::', p.part_name, '::', po.qty) ORDER BY po.id SEPARATOR '|||') AS items,
        GROUP_CONCAT(DISTINCT po.status) AS status_list,
        MAX(po.id) AS max_id
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    JOIN suppliers s ON s.id = po.supplier_id
    GROUP BY po.po_no, po.purchase_date, s.supplier_name
    ORDER BY po.purchase_date DESC, max_id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .po-form-section {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .po-form-section h3 {
            margin-top: 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .parts-selection {
            margin-top: 20px;
            display: none;
        }
        .parts-selection.active {
            display: block;
        }
        .parts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .parts-table th,
        .parts-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .parts-table th {
            background: #3498db;
            color: white;
        }
        .parts-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .parts-table input[type="checkbox"] {
            transform: scale(1.3);
            margin-right: 10px;
        }
        .parts-table input[type="number"] {
            width: 100px;
            padding: 5px;
        }
        .no-parts-msg {
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            color: #856404;
        }
        .supplier-info {
            background: #e8f4fc;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 0.95em;
        }
        /* Dynamic Supplier Search Styles */
        .supplier-search-container {
            position: relative;
        }
        .supplier-search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .supplier-search-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .supplier-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
        }
        .supplier-dropdown.active {
            display: block;
        }
        .supplier-option {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.15s;
        }
        .supplier-option:last-child {
            border-bottom: none;
        }
        .supplier-option:hover,
        .supplier-option.highlighted {
            background: #f0f7ff;
        }
        .supplier-option.selected {
            background: #e3f2fd;
        }
        .supplier-option-code {
            font-weight: bold;
            color: #2c3e50;
        }
        .supplier-option-name {
            color: #555;
            margin-left: 8px;
        }
        .supplier-option-location {
            font-size: 0.85em;
            color: #888;
            margin-top: 2px;
        }
        .supplier-selected-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 4px;
            margin-top: 5px;
        }
        .supplier-selected-display .supplier-details {
            flex: 1;
        }
        .supplier-selected-display .clear-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .supplier-selected-display .clear-btn:hover {
            background: #c0392b;
        }
        .supplier-no-results {
            padding: 15px;
            text-align: center;
            color: #888;
        }
        .supplier-loading {
            padding: 15px;
            text-align: center;
            color: #3498db;
        }
        .badge-preferred {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .selected-count {
            background: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            margin-left: 10px;
        }
    </style>
</head>
<body>

<div class="content">
<h1>Purchase Orders</h1>

<!-- =========================
     CREATE PURCHASE ORDER
========================= -->
<form method="post" id="poForm" onsubmit="return validatePOForm()">
    <div class="po-form-section">
        <h3>Create Purchase Order</h3>

        <div class="form-row">
            <div class="form-group">
                <label>PO Number</label>
                <input type="text" value="Auto-generated (PO-XXX)" readonly style="background: #eee;">
            </div>
            <div class="form-group">
                <label>Purchase Date *</label>
                <input type="date" name="purchase_date" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label>Supplier *</label>
                <div class="supplier-search-container">
                    <input type="hidden" name="supplier_id" id="supplierIdInput" required>
                    <input type="text"
                           id="supplierSearchInput"
                           class="supplier-search-input"
                           placeholder="Type to search suppliers by code, name, or city..."
                           autocomplete="off">
                    <div id="supplierDropdown" class="supplier-dropdown">
                        <div class="supplier-loading">Loading suppliers...</div>
                    </div>
                </div>
                <div id="supplierSelectedDisplay" class="supplier-selected-display" style="display: none;">
                    <div class="supplier-details">
                        <span class="supplier-option-code" id="selectedSupplierCode"></span>
                        <span class="supplier-option-name" id="selectedSupplierName"></span>
                        <div class="supplier-option-location" id="selectedSupplierLocation"></div>
                    </div>
                    <button type="button" class="clear-btn" onclick="clearSupplierSelection()">Clear</button>
                </div>
            </div>
        </div>

        <div id="supplierInfo" class="supplier-info" style="display: none;"></div>
    </div>

    <!-- Parts Selection Section -->
    <div class="po-form-section parts-selection" id="partsSection">
        <h3>Select Parts <span id="selectedCount" class="selected-count" style="display: none;">0 selected</span></h3>

        <div id="partsContainer">
            <p style="color: #666;">Select a supplier to see linked parts...</p>
        </div>
    </div>

    <div id="submitSection" style="display: none;">
        <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
            Create Purchase Order
        </button>
        <button type="button" class="btn btn-secondary" onclick="resetForm()" style="margin-left: 10px;">
            Reset
        </button>
    </div>
</form>

<hr style="margin: 30px 0;">

<!-- =========================
     PURCHASE ORDER LIST (grouped)
========================= -->
<h2>Purchase Order List</h2>
<div style="overflow-x: auto;">
<table>
    <tr>
        <th>PO No</th>
        <th>Parts</th>
        <th>Supplier</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($orders as $o): ?>
    <tr>
        <td><?= htmlspecialchars($o['po_no']) ?></td>
        <td>
            <?php $partsList = $o['items'] ? explode('|||', $o['items']) : []; ?>
            <ul style="margin:0;padding-left:18px;">
            <?php foreach ($partsList as $pitem):
                list($lineId, $partNo, $partName, $partQty) = explode('::', $pitem);
            ?>
                <li>
                    <?= htmlspecialchars($partNo) ?> — <?= htmlspecialchars($partName) ?> (Qty: <?= htmlspecialchars($partQty) ?>)
                    &nbsp; <a href="edit.php?id=<?= $lineId ?>">Edit</a>
                </li>
            <?php endforeach; ?>
            </ul>
        </td>
        <td><?= htmlspecialchars($o['supplier_name']) ?></td>
        <td><?= $o['purchase_date'] ?></td>
        <td><?= htmlspecialchars(implode(', ', explode(',', $o['status_list']))) ?></td>
        <td style="white-space: nowrap;">
            <a class="btn btn-primary" href="view.php?po_no=<?= urlencode($o['po_no']) ?>">View</a>
            <?php if (strpos($o['status_list'], 'open') !== false): ?>
                <a class="btn btn-danger" href="cancel.php?po_no=<?= urlencode($o['po_no']) ?>" onclick="return confirm('Cancel this PO?')">Cancel PO</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
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
        Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total purchase orders)
    </span>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
        <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

<script>
let supplierParts = [];
let selectedSupplierId = null;
let supplierSearchTimeout = null;
let highlightedIndex = -1;
let currentSuppliers = [];

/**
 * Initialize supplier search functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('supplierSearchInput');
    const dropdown = document.getElementById('supplierDropdown');

    // Load initial suppliers when focusing on empty input
    searchInput.addEventListener('focus', function() {
        if (!selectedSupplierId) {
            searchSuppliers('');
            dropdown.classList.add('active');
        }
    });

    // Search as user types
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(supplierSearchTimeout);
        highlightedIndex = -1;

        supplierSearchTimeout = setTimeout(() => {
            searchSuppliers(query);
        }, 200);
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        const options = dropdown.querySelectorAll('.supplier-option');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, options.length - 1);
            updateHighlight(options);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, 0);
            updateHighlight(options);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightedIndex >= 0 && options[highlightedIndex]) {
                options[highlightedIndex].click();
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('active');
            searchInput.blur();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.supplier-search-container')) {
            dropdown.classList.remove('active');
        }
    });
});

/**
 * Update highlighted option
 */
function updateHighlight(options) {
    options.forEach((opt, idx) => {
        opt.classList.toggle('highlighted', idx === highlightedIndex);
    });

    // Scroll highlighted option into view
    if (highlightedIndex >= 0 && options[highlightedIndex]) {
        options[highlightedIndex].scrollIntoView({ block: 'nearest' });
    }
}

/**
 * Search suppliers via API
 */
function searchSuppliers(query) {
    const dropdown = document.getElementById('supplierDropdown');
    dropdown.innerHTML = '<div class="supplier-loading">Searching...</div>';
    dropdown.classList.add('active');

    fetch('../api/search_suppliers.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                dropdown.innerHTML = '<div class="supplier-no-results">Error loading suppliers</div>';
                return;
            }

            currentSuppliers = data.suppliers;

            if (data.suppliers.length === 0) {
                dropdown.innerHTML = '<div class="supplier-no-results">No suppliers found</div>';
                return;
            }

            let html = '';
            data.suppliers.forEach((supplier, index) => {
                const location = [supplier.city, supplier.state].filter(Boolean).join(', ');
                html += `
                    <div class="supplier-option" data-id="${supplier.id}" data-index="${index}" onclick="selectSupplier(${index})">
                        <div>
                            <span class="supplier-option-code">${htmlEscape(supplier.supplier_code)}</span>
                            <span class="supplier-option-name">— ${htmlEscape(supplier.supplier_name)}</span>
                        </div>
                        ${location ? `<div class="supplier-option-location">${htmlEscape(location)}${supplier.phone ? ' | ' + htmlEscape(supplier.phone) : ''}</div>` : ''}
                    </div>
                `;
            });

            dropdown.innerHTML = html;
            highlightedIndex = -1;
        })
        .catch(error => {
            console.error('Error searching suppliers:', error);
            dropdown.innerHTML = '<div class="supplier-no-results">Error searching. Please try again.</div>';
        });
}

/**
 * Select a supplier from the dropdown
 */
function selectSupplier(index) {
    const supplier = currentSuppliers[index];
    if (!supplier) return;

    selectedSupplierId = supplier.id;

    // Set hidden input value
    document.getElementById('supplierIdInput').value = supplier.id;

    // Hide search input, show selected display
    document.getElementById('supplierSearchInput').style.display = 'none';
    document.getElementById('supplierDropdown').classList.remove('active');

    // Update selected display
    const selectedDisplay = document.getElementById('supplierSelectedDisplay');
    document.getElementById('selectedSupplierCode').textContent = supplier.supplier_code;
    document.getElementById('selectedSupplierName').textContent = '— ' + supplier.supplier_name;
    const location = [supplier.city, supplier.state].filter(Boolean).join(', ');
    document.getElementById('selectedSupplierLocation').textContent = location + (supplier.phone ? ' | ' + supplier.phone : '');
    selectedDisplay.style.display = 'flex';

    // Load parts for this supplier
    loadPartsForSupplier(supplier.id);
}

/**
 * Clear supplier selection
 */
function clearSupplierSelection() {
    selectedSupplierId = null;
    document.getElementById('supplierIdInput').value = '';
    document.getElementById('supplierSearchInput').value = '';
    document.getElementById('supplierSearchInput').style.display = 'block';
    document.getElementById('supplierSelectedDisplay').style.display = 'none';

    // Reset parts section
    const partsSection = document.getElementById('partsSection');
    const partsContainer = document.getElementById('partsContainer');
    const submitSection = document.getElementById('submitSection');
    const supplierInfo = document.getElementById('supplierInfo');

    partsSection.classList.remove('active');
    partsContainer.innerHTML = '<p style="color: #666;">Select a supplier to see linked parts...</p>';
    submitSection.style.display = 'none';
    supplierInfo.style.display = 'none';
    supplierParts = [];

    // Focus search input
    document.getElementById('supplierSearchInput').focus();
}

/**
 * Load parts linked to a specific supplier via AJAX
 */
function loadPartsForSupplier(supplierId) {
    const partsSection = document.getElementById('partsSection');
    const partsContainer = document.getElementById('partsContainer');
    const submitSection = document.getElementById('submitSection');
    const supplierInfo = document.getElementById('supplierInfo');

    // Reset
    supplierParts = [];
    partsContainer.innerHTML = '<p style="color: #666;">Loading parts...</p>';
    submitSection.style.display = 'none';
    supplierInfo.style.display = 'none';

    if (!supplierId) {
        partsSection.classList.remove('active');
        partsContainer.innerHTML = '<p style="color: #666;">Select a supplier to see linked parts...</p>';
        return;
    }

    partsSection.classList.add('active');

    // Fetch parts for this supplier
    fetch('../api/get_supplier_parts.php?supplier_id=' + encodeURIComponent(supplierId))
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.parts.length === 0) {
                partsContainer.innerHTML = `
                    <div class="no-parts-msg">
                        <strong>No parts linked to this supplier.</strong><br>
                        <p style="margin: 10px 0 0 0;">
                            Parts need to be mapped to suppliers in Part Master.
                            <a href="/part_master/list.php">Manage Part-Supplier Mappings</a>
                        </p>
                    </div>
                `;
                return;
            }

            supplierParts = data.parts;

            // Show supplier info
            supplierInfo.innerHTML = '<strong>' + data.parts.length + ' parts</strong> available from this supplier';
            supplierInfo.style.display = 'block';

            // Build parts table
            let html = `
                <div style="margin-bottom: 10px;">
                    <label style="display: inline-flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)" style="margin-right: 8px; transform: scale(1.3);">
                        <strong>Select All Parts</strong>
                    </label>
                </div>
                <table class="parts-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Select</th>
                            <th>Part No</th>
                            <th>Part Name</th>
                            <th>HSN Code</th>
                            <th>Unit</th>
                            <th style="width: 120px;">Rate</th>
                            <th style="width: 100px;">Quantity *</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.parts.forEach((part, index) => {
                const preferred = part.is_preferred == 1 ? '<span class="badge-preferred">Preferred</span>' : '';
                html += `
                    <tr id="partRow_${index}">
                        <td>
                            <input type="checkbox" class="part-checkbox"
                                   data-index="${index}"
                                   onchange="togglePartSelection(${index})">
                        </td>
                        <td>
                            ${htmlEscape(part.part_no)} ${preferred}
                            <input type="hidden" name="part_no[]" value="" id="partNo_${index}" disabled>
                        </td>
                        <td>${htmlEscape(part.part_name)}</td>
                        <td>${htmlEscape(part.hsn_code || '-')}</td>
                        <td>${htmlEscape(part.uom || 'Nos')}</td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                   id="rate_${index}"
                                   name="rate[]"
                                   value="${part.supplier_rate ? parseFloat(part.supplier_rate).toFixed(2) : '0.00'}"
                                   style="width: 100px;" disabled>
                        </td>
                        <td>
                            <input type="number" min="1"
                                   id="qty_${index}"
                                   name="qty[]"
                                   value="${part.min_order_qty || 1}"
                                   placeholder="Qty"
                                   style="width: 80px;" disabled>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            partsContainer.innerHTML = html;

            updateSelectedCount();
        })
        .catch(error => {
            console.error('Error loading parts:', error);
            partsContainer.innerHTML = '<p style="color: #e74c3c;">Error loading parts. Please try again.</p>';
        });
}

/**
 * Toggle selection of a part
 */
function togglePartSelection(index) {
    const checkbox = document.querySelector(`#partRow_${index} .part-checkbox`);
    const partNoInput = document.getElementById(`partNo_${index}`);
    const qtyInput = document.getElementById(`qty_${index}`);
    const rateInput = document.getElementById(`rate_${index}`);
    const row = document.getElementById(`partRow_${index}`);

    if (checkbox.checked) {
        // Enable inputs and set part_no value
        partNoInput.value = supplierParts[index].part_no;
        partNoInput.disabled = false;
        qtyInput.disabled = false;
        rateInput.disabled = false;
        row.style.background = '#e8f5e9';
    } else {
        // Disable and clear
        partNoInput.value = '';
        partNoInput.disabled = true;
        qtyInput.disabled = true;
        rateInput.disabled = true;
        row.style.background = '';
    }

    updateSelectedCount();

    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.part-checkbox');
    const checkedCount = document.querySelectorAll('.part-checkbox:checked').length;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = checkedCount === allCheckboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }
}

/**
 * Toggle select all parts
 */
function toggleSelectAll(checked) {
    const checkboxes = document.querySelectorAll('.part-checkbox');
    checkboxes.forEach((cb, index) => {
        if (cb.checked !== checked) {
            cb.checked = checked;
            togglePartSelection(index);
        }
    });
}

/**
 * Update selected count display
 */
function updateSelectedCount() {
    const count = document.querySelectorAll('.part-checkbox:checked').length;
    const countSpan = document.getElementById('selectedCount');
    const submitSection = document.getElementById('submitSection');

    if (count > 0) {
        countSpan.textContent = count + ' selected';
        countSpan.style.display = 'inline-block';
        submitSection.style.display = 'block';
    } else {
        countSpan.style.display = 'none';
        submitSection.style.display = 'none';
    }
}

/**
 * Validate form before submit
 */
function validatePOForm() {
    // Check supplier is selected
    if (!selectedSupplierId) {
        alert('Please select a supplier');
        document.getElementById('supplierSearchInput').focus();
        return false;
    }

    const checkedParts = document.querySelectorAll('.part-checkbox:checked');

    if (checkedParts.length === 0) {
        alert('Please select at least one part');
        return false;
    }

    // Check all quantities are valid
    let valid = true;
    checkedParts.forEach(cb => {
        const index = cb.dataset.index;
        const qty = parseInt(document.getElementById(`qty_${index}`).value) || 0;
        if (qty <= 0) {
            valid = false;
        }
    });

    if (!valid) {
        alert('Please enter a valid quantity (greater than 0) for all selected parts');
        return false;
    }

    return true;
}

/**
 * Reset the form
 */
function resetForm() {
    clearSupplierSelection();
}

/**
 * HTML escape helper
 */
function htmlEscape(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>

</body>
</html>
