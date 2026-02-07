<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get all active parts for autocomplete
$parts = [];
$boms = [];
try {
    $parts = $pdo->query("
        SELECT part_no, part_name, part_id FROM part_master WHERE status='active' ORDER BY part_no
    ")->fetchAll(PDO::FETCH_ASSOC);

    $boms = $pdo->query("
        SELECT id, bom_no, parent_part_no FROM bom_master WHERE status='active' ORDER BY bom_no
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables may not exist
}

// Create a lookup array for JavaScript
$partsJson = json_encode($parts);

// Get employees/engineers for assignment
$employees = [];
try {
    $employees = $pdo->query("
        SELECT id, emp_id, first_name, last_name, department, designation
        FROM employees
        WHERE status = 'Active'
        ORDER BY department, first_name, last_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist - continue without employees
}

// Ensure assigned_to column exists in work_orders table
try {
    $pdo->exec("ALTER TABLE work_orders ADD COLUMN assigned_to INT NULL AFTER qty");
} catch (PDOException $e) {
    // Column already exists, ignore
}

// Generate next WO number like "WO-1", "WO-2", ...
$wo_no = 'WO-1';
try {
    $max = $pdo->query("SELECT MAX(CAST(SUBSTRING(wo_no,4) AS UNSIGNED)) FROM work_orders WHERE wo_no LIKE 'WO-%'")->fetchColumn();
    $next = $max ? ((int)$max + 1) : 1;
    $wo_no = 'WO-' . $next;
} catch (PDOException $e) {
    // Table may not exist
}

// Get prefilled values from URL (from procurement page)
$prefilledPartNo = $_GET['part_no'] ?? '';
$prefilledQty = $_GET['qty'] ?? '';

// Find part name if prefilled
$prefilledPartName = '';
if ($prefilledPartNo) {
    foreach ($parts as $p) {
        if ($p['part_no'] === $prefilledPartNo) {
            $prefilledPartName = $p['part_name'];
            break;
        }
    }
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $partNo = trim($_POST['part_no'] ?? '');
    $bomId = $_POST['bom_id'] ?? '';
    $qty = (float)($_POST['qty'] ?? 0);
    $assignedTo = $_POST['assigned_to'] ?? '';

    // Validate part exists
    $partCheck = $pdo->prepare("SELECT part_no FROM part_master WHERE part_no = ? AND status = 'active'");
    $partCheck->execute([$partNo]);
    if (!$partCheck->fetch()) {
        $error = "Invalid part number selected";
    } elseif ($qty <= 0) {
        $error = "Quantity must be greater than 0";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO work_orders (wo_no, part_no, bom_id, qty, assigned_to, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $wo_no,
                $partNo,
                $bomId ?: null,
                $qty,
                $assignedTo ?: null,
                'created'
            ]);

            $insertedId = $pdo->lastInsertId();

            // Auto-create task for assigned employee
            include_once "../includes/auto_task.php";
            createAutoTask($pdo, [
                'task_name' => "Work Order $wo_no - Production",
                'task_description' => "Complete production for Work Order $wo_no, Part: $partNo, Qty: $qty",
                'priority' => 'High',
                'assigned_to' => $assignedTo ?: null,
                'start_date' => date('Y-m-d'),
                'related_module' => 'Work Order',
                'related_id' => $insertedId,
                'related_reference' => $wo_no,
                'created_by' => $_SESSION['user_id'] ?? null
            ]);

            // Verify the status was saved
            $verify = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
            $verify->execute([$insertedId]);
            $savedStatus = $verify->fetchColumn();

            if ($savedStatus !== 'created') {
                throw new Exception("Status was not saved correctly");
            }

            header("Location: view.php?id=" . $insertedId . "&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Error creating work order: " . $e->getMessage();
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Work Order</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .form-header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #666;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Autocomplete styles */
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
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .autocomplete-results.show {
            display: block;
        }
        .autocomplete-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item:hover,
        .autocomplete-item.selected {
            background: #f0f4ff;
        }
        .autocomplete-item .part-no {
            font-weight: 600;
            color: #2c3e50;
        }
        .autocomplete-item .part-name {
            font-size: 0.9em;
            color: #666;
            margin-top: 2px;
        }
        .autocomplete-item .part-id {
            font-size: 0.8em;
            color: #999;
            float: right;
        }
        .no-results {
            padding: 15px;
            color: #666;
            text-align: center;
        }

        /* Part info badge */
        .part-info-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            margin-left: 10px;
        }
        .part-info-badge.work-order {
            background: #d1fae5;
            color: #059669;
        }
        .part-info-badge.purchase-order {
            background: #fef3c7;
            color: #d97706;
        }

        body.dark .form-card {
            background: #2c3e50;
        }
        body.dark .form-header h1 {
            color: #ecf0f1;
        }
        body.dark .form-group label {
            color: #bdc3c7;
        }
        body.dark .autocomplete-results {
            background: #34495e;
            border-color: #4a5568;
        }
        body.dark .autocomplete-item {
            border-color: #4a5568;
        }
        body.dark .autocomplete-item:hover {
            background: #4a5568;
        }
        body.dark .autocomplete-item .part-no {
            color: #ecf0f1;
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h1>Create Work Order</h1>
                <a href="index.php" class="btn btn-secondary">Back to List</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" id="woForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>WO Number</label>
                        <input type="text" value="<?= htmlspecialchars($wo_no) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="text" value="<?= date('Y-m-d') ?>" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Part Number <span style="color: #dc2626;">*</span></label>
                    <div class="autocomplete-wrapper">
                        <input type="text"
                               name="part_no"
                               id="partNoInput"
                               value="<?= htmlspecialchars($prefilledPartNo) ?>"
                               placeholder="Type to search part number..."
                               autocomplete="off"
                               required>
                        <div class="autocomplete-results" id="autocompleteResults"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Part Name</label>
                    <input type="text"
                           id="partNameInput"
                           value="<?= htmlspecialchars($prefilledPartName) ?>"
                           readonly
                           placeholder="Will auto-fill when part is selected">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>BOM (Optional)</label>
                        <select name="bom_id" id="bomSelect">
                            <option value="">-- Select BOM --</option>
                            <?php foreach ($boms as $b): ?>
                                <option value="<?= $b['id'] ?>" data-parent="<?= htmlspecialchars($b['parent_part_no']) ?>">
                                    <?= htmlspecialchars($b['bom_no']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity <span style="color: #dc2626;">*</span></label>
                        <input type="number"
                               name="qty"
                               id="qtyInput"
                               value="<?= htmlspecialchars($prefilledQty) ?>"
                               step="0.001"
                               min="0.001"
                               placeholder="Enter quantity"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign To (Employee/Engineer)</label>
                    <select name="assigned_to" id="assignedTo" style="width: 100%;">
                        <option value="">-- Not Assigned --</option>
                        <?php
                        $currentDept = '';
                        foreach ($employees as $emp):
                            // Group by department
                            if ($emp['department'] !== $currentDept):
                                if ($currentDept !== '') echo '</optgroup>';
                                $currentDept = $emp['department'];
                                echo '<optgroup label="' . htmlspecialchars($currentDept ?: 'No Department') . '">';
                            endif;
                        ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['emp_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                                <?= $emp['designation'] ? ' (' . htmlspecialchars($emp['designation']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($currentDept !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Work Order</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Parts data from PHP
const partsData = <?= $partsJson ?>;

// Work order part IDs (internal production) - parts with these IDs always go to Work Order
const workOrderPartIds = ['YID', '99', '46', '91', '83', '44', '42', '52'];

const partNoInput = document.getElementById('partNoInput');
const partNameInput = document.getElementById('partNameInput');
const autocompleteResults = document.getElementById('autocompleteResults');
const bomSelect = document.getElementById('bomSelect');

let selectedIndex = -1;
let filteredParts = [];

// Filter parts based on input
function filterParts(query) {
    if (!query || query.length < 1) {
        return [];
    }
    query = query.toLowerCase();
    return partsData.filter(p =>
        p.part_no.toLowerCase().includes(query) ||
        p.part_name.toLowerCase().includes(query)
    ).slice(0, 20); // Limit to 20 results
}

// Render autocomplete results
function renderResults(parts) {
    if (parts.length === 0) {
        autocompleteResults.innerHTML = '<div class="no-results">No parts found</div>';
        autocompleteResults.classList.add('show');
        return;
    }

    autocompleteResults.innerHTML = parts.map((p, idx) => {
        const isWorkOrder = workOrderPartIds.includes(p.part_id);
        return `
            <div class="autocomplete-item ${idx === selectedIndex ? 'selected' : ''}"
                 data-part-no="${escapeHtml(p.part_no)}"
                 data-part-name="${escapeHtml(p.part_name)}"
                 data-part-id="${escapeHtml(p.part_id || '')}">
                <span class="part-id">${escapeHtml(p.part_id || '-')}</span>
                <div class="part-no">${escapeHtml(p.part_no)}</div>
                <div class="part-name">${escapeHtml(p.part_name)}</div>
            </div>
        `;
    }).join('');
    autocompleteResults.classList.add('show');
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Select a part
function selectPart(partNo, partName) {
    partNoInput.value = partNo;
    partNameInput.value = partName;
    autocompleteResults.classList.remove('show');
    selectedIndex = -1;

    // Auto-select matching BOM if available
    const bomOptions = bomSelect.options;
    for (let i = 0; i < bomOptions.length; i++) {
        if (bomOptions[i].dataset.parent === partNo) {
            bomSelect.selectedIndex = i;
            break;
        }
    }
}

// Input event handler
partNoInput.addEventListener('input', function() {
    const query = this.value.trim();
    filteredParts = filterParts(query);
    selectedIndex = -1;

    if (query.length >= 1) {
        renderResults(filteredParts);
    } else {
        autocompleteResults.classList.remove('show');
    }

    // Clear part name if input doesn't match exactly
    const exactMatch = partsData.find(p => p.part_no === query);
    if (exactMatch) {
        partNameInput.value = exactMatch.part_name;
    } else {
        partNameInput.value = '';
    }
});

// Keyboard navigation
partNoInput.addEventListener('keydown', function(e) {
    const items = autocompleteResults.querySelectorAll('.autocomplete-item');

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        updateSelection(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, 0);
        updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
        e.preventDefault();
        const selected = items[selectedIndex];
        if (selected) {
            selectPart(selected.dataset.partNo, selected.dataset.partName);
        }
    } else if (e.key === 'Escape') {
        autocompleteResults.classList.remove('show');
    }
});

function updateSelection(items) {
    items.forEach((item, idx) => {
        item.classList.toggle('selected', idx === selectedIndex);
    });
    // Scroll into view
    if (items[selectedIndex]) {
        items[selectedIndex].scrollIntoView({ block: 'nearest' });
    }
}

// Click on result
autocompleteResults.addEventListener('click', function(e) {
    const item = e.target.closest('.autocomplete-item');
    if (item) {
        selectPart(item.dataset.partNo, item.dataset.partName);
    }
});

// Hide results when clicking outside
document.addEventListener('click', function(e) {
    if (!partNoInput.contains(e.target) && !autocompleteResults.contains(e.target)) {
        autocompleteResults.classList.remove('show');
    }
});

// Focus shows results if there's input
partNoInput.addEventListener('focus', function() {
    if (this.value.trim().length >= 1) {
        filteredParts = filterParts(this.value.trim());
        renderResults(filteredParts);
    }
});

// Form validation
document.getElementById('woForm').addEventListener('submit', function(e) {
    const partNo = partNoInput.value.trim();
    const partExists = partsData.some(p => p.part_no === partNo);

    if (!partExists) {
        e.preventDefault();
        alert('Please select a valid part number from the list');
        partNoInput.focus();
    }
});
</script>

</body>
</html>
