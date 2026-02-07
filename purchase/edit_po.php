<?php
include "../db.php";
include "../includes/sidebar.php";

$po_no = $_GET['po_no'] ?? null;
if (!$po_no) {
    die("Invalid Purchase Order");
}

// Get PO details
$stmt = $pdo->prepare("
    SELECT po.*, s.supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.po_no = ?
    LIMIT 1
");
$stmt->execute([$po_no]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    die("Purchase Order not found");
}

// Get all suppliers for dropdown
$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);

// Get all parts for autocomplete
$parts = $pdo->query("
    SELECT part_no, part_name, hsn_code, rate, gst
    FROM part_master
    WHERE status = 'active'
    ORDER BY part_no
")->fetchAll(PDO::FETCH_ASSOC);
$partsJson = json_encode($parts);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? [];
    $newSupplierId = (int)($_POST['supplier_id'] ?? $po['supplier_id']);
    $errors = [];

    // Validate supplier
    if ($newSupplierId <= 0) {
        $errors[] = "Please select a valid supplier";
    }

    // Validate items
    foreach ($items as $idx => $item) {
        $partNo = trim($item['part_no'] ?? '');
        $qty = (float)($item['qty'] ?? 0);

        if (empty($partNo)) {
            $errors[] = "Row " . ($idx + 1) . ": Part number is required";
        }
        if ($qty <= 0) {
            $errors[] = "Row " . ($idx + 1) . ": Quantity must be greater than 0";
        }
    }

    if (empty($items)) {
        $errors[] = "At least one item is required";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Delete existing items for this PO
            $pdo->prepare("DELETE FROM purchase_orders WHERE po_no = ?")->execute([$po_no]);

            // Insert updated items (preserve rate and plan_id)
            $insertStmt = $pdo->prepare("
                INSERT INTO purchase_orders (po_no, supplier_id, part_no, qty, rate, purchase_date, status, plan_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $partNo = trim($item['part_no']);
                $qty = (float)$item['qty'];
                $rate = (float)($item['rate'] ?? 0);
                $planId = $item['plan_id'] ?? null;

                // If rate is 0, try to get supplier-specific rate
                if ($rate <= 0) {
                    try {
                        $rateStmt = $pdo->prepare("
                            SELECT supplier_rate FROM part_supplier_mapping
                            WHERE part_no = ? AND supplier_id = ? AND active = 1
                            LIMIT 1
                        ");
                        $rateStmt->execute([$partNo, $newSupplierId]);
                        $supplierRate = $rateStmt->fetchColumn();
                        if ($supplierRate) {
                            $rate = (float)$supplierRate;
                        }
                    } catch (Exception $e) {}
                }

                $insertStmt->execute([
                    $po_no,
                    $newSupplierId,
                    $partNo,
                    $qty,
                    $rate,
                    $po['purchase_date'],
                    $po['status'],
                    $planId
                ]);
            }

            $pdo->commit();
            header("Location: view.php?po_no=" . urlencode($po_no));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}

// Get current line items (include rate and plan_id)
// Use po.rate first, fallback to supplier rate from part_supplier_mapping, then p.rate (base rate)
$itemsStmt = $pdo->prepare("
    SELECT po.id, po.part_no, p.part_name, po.qty,
           CASE
               WHEN COALESCE(po.rate, 0) > 0 THEN po.rate
               WHEN psm.supplier_rate IS NOT NULL AND psm.supplier_rate > 0 THEN psm.supplier_rate
               ELSE COALESCE(p.rate, 0)
           END AS rate,
           po.plan_id
    FROM purchase_orders po
    LEFT JOIN part_master p ON p.part_no = po.part_no
    LEFT JOIN part_supplier_mapping psm ON psm.part_no = po.part_no AND psm.supplier_id = po.supplier_id AND psm.active = 1
    WHERE po.po_no = ?
    ORDER BY po.id
");
$itemsStmt->execute([$po_no]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Purchase Order - <?= htmlspecialchars($po_no) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 100%;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0 0 15px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .po-info {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }

        .po-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .po-info-label {
            font-weight: 600;
            opacity: 0.9;
        }

        .items-table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
            padding-bottom: 350px;
            margin: 60px 0 20px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .items-table th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 700;
            font-size: 14px;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            overflow: visible;
        }

        .items-table tr:hover {
            background: #f8f9fa;
        }

        .items-table input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .items-table input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .items-table .part-input {
            min-width: 200px;
        }

        .items-table .qty-input {
            width: 120px;
        }

        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 400px;
            background: white;
            border: 2px solid #3498db;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 999999;
            display: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            margin-top: 4px;
        }

        .autocomplete-results.show {
            display: block;
        }

        .autocomplete-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
            font-size: 14px;
        }

        .autocomplete-item strong {
            color: #2c3e50;
            font-weight: 600;
        }

        .autocomplete-item:hover {
            background: #e3f2fd;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .btn-add-row {
            margin-top: 50px;
            margin-bottom: 15px;
            padding: 10px 20px;
            font-size: 15px;
        }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-actions {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .part-selector-wrapper {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        #globalSearchResults {
            margin-top: 10px;
            background: white;
            border: 2px solid #3498db;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        #globalSearchResults table {
            width: 100%;
            border-collapse: collapse;
        }

        #globalSearchResults th {
            background: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        #globalSearchResults td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        #globalSearchResults tr:hover {
            background: #f0f8ff;
        }

        .add-part-btn {
            padding: 6px 15px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .add-part-btn:hover {
            background: #229954;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <div class="page-header">
            <h1>Edit Purchase Order: <?= htmlspecialchars($po_no) ?></h1>
            <div class="po-info">
                <div class="po-info-item">
                    <span class="po-info-label">Supplier:</span>
                    <select name="supplier_id" form="poForm" style="padding: 6px 12px; border-radius: 6px; border: none; font-size: 14px; min-width: 200px;">
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $sup['id'] == $po['supplier_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="po-info-item">
                    <span class="po-info-label">Date:</span>
                    <span><?= date('d M Y', strtotime($po['purchase_date'])) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" id="poForm">
            <div class="items-table-wrapper">
                <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Part Number *</th>
                        <th>Part Name</th>
                        <th style="width: 130px;">Rate</th>
                        <th style="width: 150px;">Quantity *</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <?php foreach ($items as $idx => $item): ?>
                        <tr class="item-row">
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <input type="text"
                                       name="items[<?= $idx ?>][part_no]"
                                       class="part-input"
                                       value="<?= htmlspecialchars($item['part_no']) ?>"
                                       placeholder="Part Number"
                                       required>
                            </td>
                            <td>
                                <input type="text"
                                       name="items[<?= $idx ?>][part_name]"
                                       class="part-input"
                                       value="<?= htmlspecialchars($item['part_name'] ?? '') ?>"
                                       placeholder="Part Name">
                            </td>
                            <td>
                                <input type="number"
                                       name="items[<?= $idx ?>][rate]"
                                       class="qty-input"
                                       value="<?= (float)($item['rate'] ?? 0) ?>"
                                       step="0.01"
                                       min="0"
                                       placeholder="Rate">
                                <input type="hidden" name="items[<?= $idx ?>][plan_id]" value="<?= htmlspecialchars($item['plan_id'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="number"
                                       name="items[<?= $idx ?>][qty]"
                                       class="qty-input"
                                       value="<?= $item['qty'] ?>"
                                       step="0.001"
                                       min="0.001"
                                       required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Part Selector Section -->
            <div class="part-selector-wrapper" style="margin-top: 30px;">
                <h3 style="margin: 0 0 15px 0; color: #2c3e50;">Search & Add Parts</h3>
                <div style="position: relative;">
                    <input type="text"
                           id="globalPartSearch"
                           placeholder="Search by part number or part name..."
                           autocomplete="off"
                           style="width: 100%; padding: 12px 15px; border: 2px solid #3498db; border-radius: 8px; font-size: 15px;">
                    <div id="globalSearchResults" style="display: none;">
                        <!-- Search results will appear here -->
                    </div>
                </div>
            </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 16px;">Save Changes</button>
                <a href="view.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary" style="padding: 12px 30px; font-size: 16px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const partsData = <?= $partsJson ?>;
let rowIndex = <?= count($items) ?>;

function addRow() {
    const tbody = document.getElementById('itemsTableBody');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <td>${rowIndex + 1}</td>
        <td>
            <input type="text"
                   name="items[${rowIndex}][part_no]"
                   class="part-input"
                   placeholder="Part Number"
                   required>
        </td>
        <td>
            <input type="text"
                   name="items[${rowIndex}][part_name]"
                   class="part-input"
                   placeholder="Part Name">
        </td>
        <td>
            <input type="number"
                   name="items[${rowIndex}][rate]"
                   class="qty-input"
                   step="0.01"
                   min="0"
                   placeholder="Rate">
            <input type="hidden" name="items[${rowIndex}][plan_id]" value="">
        </td>
        <td>
            <input type="number"
                   name="items[${rowIndex}][qty]"
                   class="qty-input"
                   step="0.001"
                   min="0.001"
                   required>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button>
        </td>
    `;
    tbody.appendChild(newRow);
    rowIndex++;
    updateRowNumbers();
}

function removeRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateRowNumbers();
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, idx) => {
        row.querySelector('td:first-child').textContent = idx + 1;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Global part search functionality
function initGlobalSearch() {
    const searchInput = document.getElementById('globalPartSearch');
    const resultsDiv = document.getElementById('globalSearchResults');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();

        if (query.length < 1) {
            resultsDiv.style.display = 'none';
            return;
        }

        const matches = partsData.filter(p =>
            p.part_no.toLowerCase().includes(query) ||
            p.part_name.toLowerCase().includes(query)
        ).slice(0, 20);

        if (matches.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No parts found</div>';
            resultsDiv.style.display = 'block';
            return;
        }

        const tableHtml = `
            <table>
                <thead>
                    <tr>
                        <th>Part Number</th>
                        <th>Part Name</th>
                        <th style="width: 100px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${matches.map(p => `
                        <tr>
                            <td><strong>${escapeHtml(p.part_no)}</strong></td>
                            <td>${escapeHtml(p.part_name)}</td>
                            <td style="text-align: center;">
                                <button type="button" class="add-part-btn"
                                        onclick="addPartToTable('${escapeHtml(p.part_no)}', '${escapeHtml(p.part_name)}')">
                                    + Add
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        resultsDiv.innerHTML = tableHtml;
        resultsDiv.style.display = 'block';
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
}

// Add selected part to the main table
function addPartToTable(partNo, partName) {
    // Find rate from partsData (base rate from part_master)
    const partData = partsData.find(p => p.part_no === partNo);
    const baseRate = partData ? parseFloat(partData.rate || 0).toFixed(2) : '0.00';

    const currentRowIndex = rowIndex;
    const tbody = document.getElementById('itemsTableBody');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <td>${rowIndex + 1}</td>
        <td>
            <input type="text"
                   name="items[${rowIndex}][part_no]"
                   class="part-input"
                   value="${partNo}"
                   placeholder="Part Number"
                   required>
        </td>
        <td>
            <input type="text"
                   name="items[${rowIndex}][part_name]"
                   class="part-input"
                   value="${partName}"
                   placeholder="Part Name">
        </td>
        <td>
            <input type="number"
                   name="items[${rowIndex}][rate]"
                   class="qty-input rate-input"
                   value="${baseRate}"
                   step="0.01"
                   min="0"
                   placeholder="Rate">
            <input type="hidden" name="items[${rowIndex}][plan_id]" value="">
        </td>
        <td>
            <input type="number"
                   name="items[${rowIndex}][qty]"
                   class="qty-input"
                   step="0.001"
                   min="0.001"
                   required>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button>
        </td>
    `;
    tbody.appendChild(newRow);
    rowIndex++;
    updateRowNumbers();

    // Try to fetch supplier-specific rate
    const supplierId = document.querySelector('select[name="supplier_id"]').value;
    if (supplierId && partNo) {
        fetch(`get_part_suppliers.php?part_no=${encodeURIComponent(partNo)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.suppliers) {
                    const match = data.suppliers.find(s => s.supplier_id == supplierId);
                    if (match && parseFloat(match.supplier_rate) > 0) {
                        const rateInput = newRow.querySelector('.rate-input');
                        if (rateInput) rateInput.value = parseFloat(match.supplier_rate).toFixed(2);
                    }
                }
            })
            .catch(() => {});
    }

    // Focus on the quantity input
    setTimeout(() => {
        newRow.querySelector('input[name$="[qty]"]').focus();
    }, 10);

    // Clear search and hide results
    document.getElementById('globalPartSearch').value = '';
    document.getElementById('globalSearchResults').style.display = 'none';
}

// Initialize global search on page load
document.addEventListener('DOMContentLoaded', function() {
    initGlobalSearch();
});
</script>

</body>
</html>
