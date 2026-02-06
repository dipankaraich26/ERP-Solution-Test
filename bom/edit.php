<?php
include "../db.php";
include "../includes/dialog.php";

// Auto-migrate: add rate column to bom_items if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM bom_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('rate', $cols)) {
        $pdo->exec("ALTER TABLE bom_items ADD COLUMN rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER qty");
        $pdo->exec("UPDATE bom_items bi JOIN part_master pm ON bi.component_part_no = pm.part_no SET bi.rate = pm.rate");
    }
} catch (PDOException $e) {}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Fetch BOM details
$bom = $pdo->prepare("SELECT * FROM bom_master WHERE id = ?");
$bom->execute([$id]);
$bom = $bom->fetch(PDO::FETCH_ASSOC);

if (!$bom) {
    setModal("Error", "BOM not found.");
    header("Location: index.php");
    exit;
}

// Get parent part name
$parentPart = $pdo->prepare("SELECT part_no, part_name FROM part_master WHERE part_no = ?");
$parentPart->execute([$bom['parent_part_no']]);
$parentPart = $parentPart->fetch(PDO::FETCH_ASSOC);

// Fetch existing BOM items with part names
$items = $pdo->prepare("
    SELECT bi.*, pm.part_name
    FROM bom_items bi
    LEFT JOIN part_master pm ON bi.component_part_no = pm.part_no
    WHERE bi.bom_id = ?
");
$items->execute([$id]);
$existingItems = $items->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active parts for component selection
$child_parts = $pdo->query("
    SELECT id, part_no, part_name, category
    FROM part_master
    WHERE status='active'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Get rate for a part based on supplier pricing:
 * 1. If preferred supplier exists → use that rate
 * 2. Else if any active supplier exists → use lowest rate
 * 3. Else → use part_master rate
 */
function getPartRate($pdo, $part_no) {
    // First check for preferred supplier
    $prefStmt = $pdo->prepare("
        SELECT supplier_rate FROM part_supplier_mapping
        WHERE part_no = ? AND active = 1 AND is_preferred = 1
        LIMIT 1
    ");
    $prefStmt->execute([$part_no]);
    $preferred = $prefStmt->fetchColumn();
    if ($preferred && $preferred > 0) {
        return (float)$preferred;
    }

    // Check for lowest active supplier rate
    $supStmt = $pdo->prepare("
        SELECT MIN(supplier_rate) FROM part_supplier_mapping
        WHERE part_no = ? AND active = 1 AND supplier_rate > 0
    ");
    $supStmt->execute([$part_no]);
    $lowestRate = $supStmt->fetchColumn();
    if ($lowestRate && $lowestRate > 0) {
        return (float)$lowestRate;
    }

    // Fallback to part_master rate
    $pmStmt = $pdo->prepare("SELECT rate FROM part_master WHERE part_no = ?");
    $pmStmt->execute([$part_no]);
    return (float)$pmStmt->fetchColumn() ?: 0;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (empty($_POST['component_part_no'])) {
        setModal("Error", "At least one component is required.");
        header("Location: edit.php?id=" . $id);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update description only (parent part stays same)
        $pdo->prepare("
            UPDATE bom_master SET description = ? WHERE id = ?
        ")->execute([
            $_POST['description'],
            $id
        ]);

        // Delete all existing items
        $pdo->prepare("DELETE FROM bom_items WHERE bom_id = ?")->execute([$id]);

        // Insert new items
        $stmt = $pdo->prepare("
            INSERT INTO bom_items (bom_id, component_part_no, qty, rate)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($_POST['component_part_no'] as $i => $part) {
            if (!empty($part)) {
                // Validate component is not same as parent
                if ($part === $bom['parent_part_no']) {
                    throw new Exception("Component part cannot be same as parent part.");
                }
                // Auto-calculate rate from supplier pricing
                $rate = getPartRate($pdo, $part);
                $stmt->execute([$id, $part, $_POST['qty'][$i], $rate]);
            }
        }

        $pdo->commit();
        setModal("Success", "BOM updated successfully.");
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", "Failed to update BOM: " . $e->getMessage());
        header("Location: edit.php?id=" . $id);
        exit;
    }
}

// Convert parts to JSON for JavaScript
$partsJson = json_encode($child_parts);
$existingItemsJson = json_encode($existingItems);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit BOM - <?= htmlspecialchars($bom['bom_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .search-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .part-search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .search-results.active {
            display: block;
        }
        .search-result-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-result-item:hover {
            background: #f0f7ff;
        }
        .part-info {
            flex: 1;
        }
        .part-name {
            font-weight: 500;
            color: #333;
        }
        .part-details {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        .part-id-badge {
            background: #e0e0e0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: #555;
        }
        .component-row {
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .component-row-header {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .component-search-wrapper {
            flex: 1.5;
            min-width: 180px;
        }
        .component-name-wrapper {
            flex: 2;
            min-width: 200px;
        }
        .component-qty-wrapper {
            flex: 0 0 100px;
        }
        .component-qty-wrapper input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .component-actions {
            flex: 0 0 140px;
            display: flex;
            gap: 5px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .no-results {
            padding: 15px;
            text-align: center;
            color: #666;
        }
        .search-hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        #componentsContainer {
            margin-top: 15px;
        }
        .add-component-btn {
            margin-top: 10px;
        }
        .parent-part-display {
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
            border: 1px solid #90caf9;
            margin-bottom: 20px;
        }
        .parent-part-display h4 {
            margin: 0 0 10px 0;
            color: #1565c0;
        }
        .parent-info-row {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .parent-info-item {
            min-width: 150px;
        }
        .parent-info-item label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .parent-info-item span {
            font-size: 14px;
            color: #333;
        }
        .part-name-display {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            background: #e8f5e9;
            font-weight: 500;
        }
        .part-name-display:empty, .part-name-display[value=""] {
            background: #fff;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Edit BOM</h1>

    <!-- Parent Part Display (Read-only) -->
    <div class="parent-part-display">
        <h4>Parent Part (Cannot be changed)</h4>
        <div class="parent-info-row">
            <div class="parent-info-item">
                <label>BOM No</label>
                <span><?= htmlspecialchars($bom['bom_no']) ?></span>
            </div>
            <div class="parent-info-item">
                <label>Parent Part No</label>
                <span><?= htmlspecialchars($bom['parent_part_no']) ?></span>
            </div>
            <div class="parent-info-item">
                <label>Part Name</label>
                <span><?= htmlspecialchars($parentPart['part_name'] ?? 'N/A') ?></span>
            </div>
            <div class="parent-info-item">
                <label>Status</label>
                <span><?= htmlspecialchars($bom['status']) ?></span>
            </div>
        </div>
    </div>

    <form method="post" id="bomForm">
        <label>Description</label><br>
        <textarea name="description" style="width: 100%; height: 80px;"><?= htmlspecialchars($bom['description']) ?></textarea><br><br>

        <h3>Components (Child Parts)</h3>
        <p class="search-hint">Search by Part ID, Part Number, or Part Name. Use Clear to change selection, Remove to delete component.</p>

        <div id="componentsContainer">
            <!-- Existing and new component rows will be added here -->
        </div>

        <button type="button" class="btn btn-primary add-component-btn" onclick="addComponentRow()">+ Add Component</button>

        <br><br>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success">Update BOM</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Parts data from PHP
const allParts = <?= $partsJson ?>;
const existingItems = <?= $existingItemsJson ?>;
const parentPartNo = '<?= htmlspecialchars($bom['parent_part_no']) ?>';

// Initialize with existing components
document.addEventListener('DOMContentLoaded', function() {
    if (existingItems.length > 0) {
        existingItems.forEach(item => {
            addComponentRowWithData(item.component_part_no, item.part_name || 'Unknown Part', item.qty);
        });
    } else {
        addComponentRow();
    }
});

function addComponentRow() {
    const container = document.getElementById('componentsContainer');
    const rowId = 'component_' + Date.now();

    const rowHtml = `
        <div class="component-row" id="${rowId}">
            <div class="component-row-header">
                <div class="component-search-wrapper" style="flex: 1.5;">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Part No</label>
                    <div class="search-container">
                        <input type="text" class="part-search-input"
                               placeholder="Search by ID, Part No, or Name..."
                               onkeyup="searchParts(this, '${rowId}')"
                               onfocus="showResults(this, '${rowId}')"
                               autocomplete="off">
                        <div class="search-results" id="results_${rowId}"></div>
                    </div>
                    <input type="hidden" name="component_part_no[]" id="hidden_${rowId}" required>
                </div>
                <div class="component-name-wrapper" style="flex: 2;">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Part Name</label>
                    <input type="text" class="part-name-display" id="name_${rowId}" readonly placeholder="Auto-filled when part selected">
                </div>
                <div class="component-qty-wrapper">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Qty</label>
                    <input type="number" step="0.001" name="qty[]" id="qty_${rowId}" placeholder="Qty" required min="0.001">
                </div>
                <div class="component-actions" style="padding-top: 25px; display: flex; gap: 5px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection('${rowId}')" title="Clear selection">Clear</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeComponentRow('${rowId}')" title="Remove row">Remove</button>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', rowHtml);
}

function addComponentRowWithData(partNo, partName, qty) {
    const container = document.getElementById('componentsContainer');
    const rowId = 'component_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    const rowHtml = `
        <div class="component-row" id="${rowId}">
            <div class="component-row-header">
                <div class="component-search-wrapper" style="flex: 1.5;">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Part No</label>
                    <div class="search-container">
                        <input type="text" class="part-search-input"
                               value="${escapeHtml(partNo)}"
                               style="background: #e8f5e9;"
                               readonly
                               onkeyup="searchParts(this, '${rowId}')"
                               onfocus="showResults(this, '${rowId}')"
                               autocomplete="off">
                        <div class="search-results" id="results_${rowId}"></div>
                    </div>
                    <input type="hidden" name="component_part_no[]" id="hidden_${rowId}" value="${escapeHtml(partNo)}" required>
                </div>
                <div class="component-name-wrapper" style="flex: 2;">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Part Name</label>
                    <input type="text" class="part-name-display" id="name_${rowId}" readonly value="${escapeHtml(partName)}" style="background: #e8f5e9;">
                </div>
                <div class="component-qty-wrapper">
                    <label style="font-weight: 600; margin-bottom: 5px; display: block;">Qty</label>
                    <input type="number" step="0.001" name="qty[]" id="qty_${rowId}" value="${qty}" required min="0.001">
                </div>
                <div class="component-actions" style="padding-top: 25px; display: flex; gap: 5px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection('${rowId}')" title="Clear selection">Clear</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeComponentRow('${rowId}')" title="Remove row">Remove</button>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', rowHtml);
}

function removeComponentRow(rowId) {
    const container = document.getElementById('componentsContainer');
    const rows = container.querySelectorAll('.component-row');

    if (rows.length <= 1) {
        // Clear the row instead of removing if it's the last one
        clearSelection(rowId);
        const row = document.getElementById(rowId);
        const qty = row.querySelector('input[name="qty[]"]');
        if (qty) qty.value = '';
        alert('At least one component is required. Clear the selection to change it.');
        return;
    }

    if (confirm('Remove this component?')) {
        document.getElementById(rowId).remove();
    }
}

function searchParts(input, rowId) {
    // Don't search if already selected (readonly)
    if (input.readOnly) return;

    const query = input.value.trim().toLowerCase();
    const resultsDiv = document.getElementById('results_' + rowId);

    if (!query) {
        resultsDiv.innerHTML = '';
        resultsDiv.classList.remove('active');
        return;
    }

    // Filter out parent part from results
    const results = allParts.filter(part => {
        if (part.part_no === parentPartNo) return false; // Exclude parent part

        const idMatch = part.id.toString() === query;
        const partNoMatch = part.part_no.toLowerCase().includes(query);
        const nameMatch = part.part_name.toLowerCase().includes(query);
        return idMatch || partNoMatch || nameMatch;
    }).slice(0, 20); // Limit results

    if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="no-results">No parts found</div>';
    } else {
        resultsDiv.innerHTML = results.map(part => `
            <div class="search-result-item" onclick="selectPart('${part.part_no}', '${escapeHtml(part.part_name)}', ${part.id}, '${rowId}')">
                <div class="part-info">
                    <div class="part-name">${escapeHtml(part.part_name)}</div>
                    <div class="part-details">${escapeHtml(part.part_no)} | ${escapeHtml(part.category || 'N/A')}</div>
                </div>
                <span class="part-id-badge">ID: ${part.id}</span>
            </div>
        `).join('');
    }

    resultsDiv.classList.add('active');
}

function selectPart(partNo, partName, partId, rowId) {
    const hidden = document.getElementById('hidden_' + rowId);
    const input = document.querySelector(`#${rowId} .part-search-input`);
    const results = document.getElementById('results_' + rowId);
    const nameDisplay = document.getElementById('name_' + rowId);

    // Set values
    hidden.value = partNo;
    nameDisplay.value = partName + ' (ID: ' + partId + ')';
    nameDisplay.style.background = '#e8f5e9';

    // Update search input to show selected part_no
    input.value = partNo;
    input.style.background = '#e8f5e9';
    input.readOnly = true;
    results.classList.remove('active');
}

function clearSelection(rowId) {
    const hidden = document.getElementById('hidden_' + rowId);
    const input = document.querySelector(`#${rowId} .part-search-input`);
    const nameDisplay = document.getElementById('name_' + rowId);

    // Clear values
    hidden.value = '';
    nameDisplay.value = '';
    nameDisplay.style.background = '#fff';

    // Reset search input
    input.value = '';
    input.style.background = '#fff';
    input.readOnly = false;
    input.focus();
}

function showResults(input, rowId) {
    // Don't show results if already selected (readonly)
    if (input.readOnly) return;

    if (input.value.trim()) {
        searchParts(input, rowId);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        document.querySelectorAll('.search-results').forEach(el => {
            el.classList.remove('active');
        });
    }
});

// Form validation
document.getElementById('bomForm').addEventListener('submit', function(e) {
    const hiddenInputs = document.querySelectorAll('input[name="component_part_no[]"]');
    let hasEmpty = false;
    let hasValidComponent = false;

    hiddenInputs.forEach(input => {
        if (!input.value) {
            hasEmpty = true;
        } else {
            hasValidComponent = true;
        }
    });

    if (!hasValidComponent) {
        e.preventDefault();
        alert('At least one component is required');
        return false;
    }

    if (hasEmpty) {
        e.preventDefault();
        alert('Please select a part for all component rows, or remove empty rows');
        return false;
    }
});
</script>

</body>
</html>
