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
    $errors = [];

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

            // Insert updated items
            $insertStmt = $pdo->prepare("
                INSERT INTO purchase_orders (po_no, supplier_id, part_no, qty, purchase_date, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $partNo = trim($item['part_no']);
                $qty = (float)$item['qty'];

                $insertStmt->execute([
                    $po_no,
                    $po['supplier_id'],
                    $partNo,
                    $qty,
                    $po['purchase_date'],
                    $po['status']
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

// Get current line items
$itemsStmt = $pdo->prepare("
    SELECT po.id, po.part_no, p.part_name, po.qty
    FROM purchase_orders po
    LEFT JOIN part_master p ON p.part_no = po.part_no
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
            max-width: 1200px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .items-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .items-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .items-table .part-input {
            min-width: 150px;
        }
        .items-table .qty-input {
            width: 100px;
        }
        .autocomplete-wrapper {
            position: relative;
        }
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .autocomplete-results.show {
            display: block;
        }
        .autocomplete-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .autocomplete-item:hover {
            background: #f0f0f0;
        }
        .btn-add-row {
            margin: 10px 0;
        }
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Purchase Order: <?= htmlspecialchars($po_no) ?></h1>

        <p><strong>Supplier:</strong> <?= htmlspecialchars($po['supplier_name']) ?></p>
        <p><strong>Date:</strong> <?= date('d M Y', strtotime($po['purchase_date'])) ?></p>

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
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Part Number *</th>
                        <th>Part Name</th>
                        <th style="width: 150px;">Quantity *</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <?php foreach ($items as $idx => $item): ?>
                        <tr class="item-row">
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="autocomplete-wrapper">
                                    <input type="text"
                                           name="items[<?= $idx ?>][part_no]"
                                           class="part-input part-no-input"
                                           value="<?= htmlspecialchars($item['part_no']) ?>"
                                           placeholder="Type to search..."
                                           required>
                                    <div class="autocomplete-results"></div>
                                </div>
                            </td>
                            <td>
                                <input type="text"
                                       class="part-name-display"
                                       value="<?= htmlspecialchars($item['part_name'] ?? '') ?>"
                                       readonly
                                       style="background: #f5f5f5;">
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

            <button type="button" class="btn btn-secondary btn-add-row" onclick="addRow()">Add Row</button>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Save Changes</button>
                <a href="view.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
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
            <div class="autocomplete-wrapper">
                <input type="text"
                       name="items[${rowIndex}][part_no]"
                       class="part-input part-no-input"
                       placeholder="Type to search..."
                       required>
                <div class="autocomplete-results"></div>
            </div>
        </td>
        <td>
            <input type="text" class="part-name-display" readonly style="background: #f5f5f5;">
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
    initAutocomplete(newRow);
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

function initAutocomplete(container = document) {
    const inputs = container.querySelectorAll('.part-no-input');

    inputs.forEach(input => {
        if (input.dataset.initialized) return;
        input.dataset.initialized = 'true';

        const wrapper = input.closest('.autocomplete-wrapper');
        const results = wrapper.querySelector('.autocomplete-results');
        const row = input.closest('tr');
        const nameDisplay = row.querySelector('.part-name-display');

        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();

            if (query.length < 1) {
                results.classList.remove('show');
                nameDisplay.value = '';
                return;
            }

            const matches = partsData.filter(p =>
                p.part_no.toLowerCase().includes(query) ||
                p.part_name.toLowerCase().includes(query)
            ).slice(0, 10);

            if (matches.length === 0) {
                results.innerHTML = '<div class="autocomplete-item">No parts found</div>';
                results.classList.add('show');
                nameDisplay.value = '';
                return;
            }

            results.innerHTML = matches.map(p => `
                <div class="autocomplete-item"
                     data-part-no="${escapeHtml(p.part_no)}"
                     data-part-name="${escapeHtml(p.part_name)}">
                    <strong>${escapeHtml(p.part_no)}</strong> - ${escapeHtml(p.part_name)}
                </div>
            `).join('');
            results.classList.add('show');
        });

        results.addEventListener('click', function(e) {
            const item = e.target.closest('.autocomplete-item');
            if (item && item.dataset.partNo) {
                input.value = item.dataset.partNo;
                nameDisplay.value = item.dataset.partName;
                results.classList.remove('show');
            }
        });

        input.addEventListener('blur', function() {
            setTimeout(() => results.classList.remove('show'), 200);
        });
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Initialize autocomplete on page load
document.addEventListener('DOMContentLoaded', function() {
    initAutocomplete();
});
</script>

</body>
</html>
