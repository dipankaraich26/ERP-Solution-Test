<?php
include "../db.php";
include "../includes/dialog.php";

showModal();

// Auto-migrate: add rate column to bom_items if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM bom_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('rate', $cols)) {
        $pdo->exec("ALTER TABLE bom_items ADD COLUMN rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER qty");
        $pdo->exec("UPDATE bom_items bi JOIN part_master pm ON bi.component_part_no = pm.part_no SET bi.rate = pm.rate");
    }
} catch (PDOException $e) {}

/* =========================
   FETCH ALL ACTIVE PARTS FOR PARENT SELECTION
========================= */
$parts = $pdo->query("
    SELECT id, part_no, part_name
    FROM part_master
    WHERE status='active'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH CHILD PARTS (ALL CATEGORIES) - with ID for search
========================= */
$child_parts = $pdo->query("
    SELECT id, part_no, part_name, category
    FROM part_master
    WHERE status='active'
    ORDER BY part_name
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH EXISTING BOM LINKS
========================= */
$boms = $pdo->query("
    SELECT bom_no, parent_part_no
    FROM bom_master
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

/* =========================
   HANDLE SAVE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (empty($_POST['component_part_no'])) {
        setModal("Failed to add BOM", "Use at least one component");
        header("Location: add.php");
        exit;
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare("
            INSERT INTO bom_master (bom_no, parent_part_no, description)
            VALUES (?, ?, ?)
        ")->execute([
            $_POST['bom_no'],
            $_POST['parent_part_no'],
            $_POST['description']
        ]);

        $bom_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO bom_items (bom_id, component_part_no, qty, rate)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($_POST['component_part_no'] as $i => $part) {

            if ($part === $_POST['parent_part_no']) {
                setModal("Failed to add BOM", "Parent part cannot be a component part.");
                header("Location: add.php");
                exit;
            }

            // Auto-calculate rate from supplier pricing
            $rate = getPartRate($pdo, $part);

            $stmt->execute([
                $bom_id,
                $part,
                $_POST['qty'][$i],
                $rate
            ]);
        }

        $pdo->commit();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Failed to add BOM", $e->getMessage());
        header("Location: add.php");
        exit;
    }
}

// Convert parts to JSON for JavaScript
$partsJson = json_encode($child_parts);
$parentPartsJson = json_encode($parts); // All active parts for parent selection
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add BOM</title>
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
        .search-result-item.selected {
            background: #e3f2fd;
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
        .selected-part-display {
            background: #e8f5e9;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 5px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        .selected-part-display.active {
            display: flex;
        }
        .clear-selection {
            background: #f44336;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .clear-selection:hover {
            background: #d32f2f;
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
        .parent-part-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .parent-field {
            flex: 1;
            min-width: 200px;
        }
        .parent-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .parent-field .search-hint {
            margin-bottom: 5px;
        }
        .parent-action {
            flex: 0 0 80px;
            min-width: 80px;
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

<?php include "../includes/sidebar.php"; ?>

<div class="content">
<h1>Add BOM</h1>

<form method="post" id="bomForm">

    <!-- PARENT PART with Search -->
    <div class="parent-part-row">
        <div class="parent-field">
            <label>Parent Part No (BOM No)</label>
            <p class="search-hint">Search by Part ID, Part No, or Part Name</p>
            <div class="search-container" id="parentPartContainer">
                <input type="text" class="part-search-input" id="parent_search_input"
                       placeholder="Search by ID, Part No, or Name..."
                       onkeyup="searchParentParts(this)"
                       onfocus="showParentResults()"
                       autocomplete="off">
                <div class="search-results" id="parent_results"></div>
            </div>
            <input type="hidden" name="parent_part_no" id="parent_part_no" required>
            <input type="hidden" name="bom_no" id="bom_no">
        </div>
        <div class="parent-field">
            <label>Part Name</label>
            <input type="text" id="parent_part_name_display" class="part-name-display" readonly placeholder="Auto-filled when part selected">
        </div>
        <div class="parent-field parent-action">
            <button type="button" class="btn btn-secondary" id="clear_parent_btn" onclick="clearParentSelection()" style="display:none;">Clear</button>
        </div>
    </div>
    <br>

    <label>Description</label><br>
    <textarea name="description"></textarea><br><br>

    <h3>Components</h3>
    <p class="search-hint">Search by Part ID, Part Number, or Part Name. You can enter multiple IDs separated by commas (e.g., 42, 44, 46)</p>

    <div id="componentsContainer">
        <!-- Component rows will be added here -->
    </div>

    <button type="button" class="btn btn-primary add-component-btn" onclick="addComponentRow()">+ Add Component</button>

    <br><br>
    <button type="submit" class="btn btn-success">Save BOM</button>
</form>
</div>

<script>
// Parts data from PHP
const allParts = <?= $partsJson ?>;
const parentParts = <?= $parentPartsJson ?>; // All active parts for parent selection

// Initialize with one component row
document.addEventListener('DOMContentLoaded', function() {
    addComponentRow();
});

// ================== PARENT PART SEARCH ==================
function searchParentParts(input) {
    // Don't search if already selected (readonly)
    if (input.readOnly) return;

    const query = input.value.trim().toLowerCase();
    const resultsDiv = document.getElementById('parent_results');

    if (!query) {
        // Show all parent parts when empty
        showAllParentParts();
        return;
    }

    // Search by ID (exact match), part_no, or part_name
    const results = parentParts.filter(part => {
        const idMatch = part.id.toString() === query;
        const partNoMatch = part.part_no.toLowerCase().includes(query);
        const nameMatch = part.part_name.toLowerCase().includes(query);
        return idMatch || partNoMatch || nameMatch;
    });

    if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="no-results">No parts found</div>';
    } else {
        resultsDiv.innerHTML = results.map(part => `
            <div class="search-result-item" onclick="selectParentPart('${part.part_no}', '${escapeHtml(part.part_name)}', ${part.id})">
                <div class="part-info">
                    <div class="part-name">${escapeHtml(part.part_name)}</div>
                    <div class="part-details">${escapeHtml(part.part_no)}</div>
                </div>
                <span class="part-id-badge">ID: ${part.id}</span>
            </div>
        `).join('');
    }

    resultsDiv.classList.add('active');
}

function showAllParentParts() {
    const resultsDiv = document.getElementById('parent_results');
    resultsDiv.innerHTML = parentParts.map(part => `
        <div class="search-result-item" onclick="selectParentPart('${part.part_no}', '${escapeHtml(part.part_name)}', ${part.id})">
            <div class="part-info">
                <div class="part-name">${escapeHtml(part.part_name)}</div>
                <div class="part-details">${escapeHtml(part.part_no)}</div>
            </div>
            <span class="part-id-badge">ID: ${part.id}</span>
        </div>
    `).join('');
    resultsDiv.classList.add('active');
}

function showParentResults() {
    const input = document.getElementById('parent_search_input');
    // Don't show results if already selected (readonly)
    if (input.readOnly) return;

    if (input.value.trim()) {
        searchParentParts(input);
    } else {
        showAllParentParts();
    }
}

function selectParentPart(partNo, partName, partId) {
    const hidden = document.getElementById('parent_part_no');
    const input = document.getElementById('parent_search_input');
    const results = document.getElementById('parent_results');
    const bomField = document.getElementById('bom_no');
    const partNameDisplay = document.getElementById('parent_part_name_display');
    const clearBtn = document.getElementById('clear_parent_btn');

    // Set values - BOM No is same as parent part no
    hidden.value = partNo;
    bomField.value = partNo;
    partNameDisplay.value = partName + ' (ID: ' + partId + ')';
    partNameDisplay.style.background = '#e8f5e9';

    // Update search input to show selected part_no
    input.value = partNo;
    input.style.background = '#e8f5e9';
    input.readOnly = true;
    results.classList.remove('active');

    // Show clear button
    clearBtn.style.display = 'block';
}

function clearParentSelection() {
    const hidden = document.getElementById('parent_part_no');
    const input = document.getElementById('parent_search_input');
    const bomField = document.getElementById('bom_no');
    const partNameDisplay = document.getElementById('parent_part_name_display');
    const clearBtn = document.getElementById('clear_parent_btn');

    // Clear all values
    hidden.value = '';
    bomField.value = '';
    partNameDisplay.value = '';
    partNameDisplay.style.background = '#fff';

    // Reset search input
    input.value = '';
    input.style.background = '#fff';
    input.readOnly = false;
    input.focus();

    // Hide clear button
    clearBtn.style.display = 'none';
}

// ================== COMPONENT PART SEARCH ==================

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
                    <input type="number" step="0.001" name="qty[]" placeholder="Qty" required min="0.001">
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
        // Clear the row instead of removing
        clearSelection(rowId);
        const row = document.getElementById(rowId);
        const qty = row.querySelector('input[name="qty[]"]');
        if (qty) qty.value = '';
        return;
    }

    document.getElementById(rowId).remove();
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

    // Check if it's a comma-separated list of IDs
    if (query.includes(',')) {
        const ids = query.split(',').map(id => id.trim()).filter(id => id);
        handleMultipleIds(ids, rowId);
        return;
    }

    // Search by ID (exact match), part_no, or part_name
    const results = allParts.filter(part => {
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

function handleMultipleIds(ids, firstRowId) {
    // For multiple IDs, add each as a separate component row
    const matchedParts = [];

    ids.forEach(id => {
        const part = allParts.find(p => p.id.toString() === id);
        if (part) {
            matchedParts.push(part);
        }
    });

    if (matchedParts.length === 0) {
        const resultsDiv = document.getElementById('results_' + firstRowId);
        resultsDiv.innerHTML = '<div class="no-results">No parts found for these IDs</div>';
        resultsDiv.classList.add('active');
        return;
    }

    // Select first part in current row
    selectPart(matchedParts[0].part_no, matchedParts[0].part_name, matchedParts[0].id, firstRowId);

    // Add remaining parts as new rows
    for (let i = 1; i < matchedParts.length; i++) {
        addComponentRow();
        // Small delay to ensure row is added
        const part = matchedParts[i];
        setTimeout(() => {
            const container = document.getElementById('componentsContainer');
            const rows = container.querySelectorAll('.component-row');
            const lastRow = rows[rows.length - 1];
            const lastRowId = lastRow.id;
            selectPart(part.part_no, part.part_name, part.id, lastRowId);
        }, 50 * i);
    }
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
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container') && !e.target.closest('#parentPartContainer')) {
        document.querySelectorAll('.search-results').forEach(el => {
            el.classList.remove('active');
        });
    }
});

// Form validation
document.getElementById('bomForm').addEventListener('submit', function(e) {
    // Check parent part
    const parentPart = document.getElementById('parent_part_no').value;
    if (!parentPart) {
        e.preventDefault();
        alert('Please select a parent part');
        return false;
    }

    // Check component parts
    const hiddenInputs = document.querySelectorAll('input[name="component_part_no[]"]');
    let hasEmpty = false;

    hiddenInputs.forEach(input => {
        if (!input.value) {
            hasEmpty = true;
        }
    });

    if (hasEmpty) {
        e.preventDefault();
        alert('Please select a part for all components');
        return false;
    }
});
</script>

</body>
</html>
