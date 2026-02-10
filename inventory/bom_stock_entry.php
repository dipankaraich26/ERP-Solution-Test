<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
include "../includes/dialog.php";

showModal();

// Admin only
if (getUserRole() !== 'admin') {
    header("Location: /inventory/index.php");
    exit;
}

// Ensure bom tables exist
$hasBom = $pdo->query("SHOW TABLES LIKE 'bom_master'")->rowCount() > 0;

$message = '';
$messageType = '';
$entryResults = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parent_part_no'])) {
    $parentPartNo = trim($_POST['parent_part_no']);
    $multiplier = max(1, (int)($_POST['multiplier'] ?? 1));
    $reason = trim($_POST['reason'] ?? 'BOM Stock Entry');

    if (empty($parentPartNo)) {
        setModal("Error", "Please select a parent part.");
        header("Location: bom_stock_entry.php");
        exit;
    }

    // Find active BOM for this parent part
    $bomStmt = $pdo->prepare("
        SELECT b.id, b.bom_no, b.parent_part_no, p.part_name as parent_name
        FROM bom_master b
        JOIN part_master p ON p.part_no = b.parent_part_no
        WHERE b.parent_part_no = ? AND b.status = 'active'
        LIMIT 1
    ");
    $bomStmt->execute([$parentPartNo]);
    $bom = $bomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$bom) {
        setModal("Error", "No active BOM found for part: $parentPartNo");
        header("Location: bom_stock_entry.php");
        exit;
    }

    // Get all child parts from BOM (only direct children, not recursive)
    $childStmt = $pdo->prepare("
        SELECT bi.component_part_no, bi.qty, p.part_name, p.uom,
               COALESCE(inv.qty, 0) as current_stock
        FROM bom_items bi
        JOIN part_master p ON p.part_no = bi.component_part_no
        LEFT JOIN inventory inv ON inv.part_no = bi.component_part_no
        WHERE bi.bom_id = ?
        ORDER BY p.part_name
    ");
    $childStmt->execute([$bom['id']]);
    $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($children)) {
        setModal("Error", "BOM {$bom['bom_no']} has no child parts.");
        header("Location: bom_stock_entry.php");
        exit;
    }

    // Add stock for each child part
    $pdo->beginTransaction();
    try {
        $addedCount = 0;
        $totalQtyAdded = 0;
        $batchRef = 'BOM-' . date('YmdHis') . '-' . $bom['bom_no'];

        foreach ($children as $child) {
            $addQty = (int)($child['qty'] * $multiplier);
            if ($addQty <= 0) continue;

            // Check if part exists in inventory
            $invCheck = $pdo->prepare("SELECT qty FROM inventory WHERE part_no = ?");
            $invCheck->execute([$child['component_part_no']]);
            $currentQty = $invCheck->fetchColumn();

            if ($currentQty === false) {
                // Insert new inventory record
                $pdo->prepare("INSERT INTO inventory (part_no, qty) VALUES (?, ?)")
                    ->execute([$child['component_part_no'], $addQty]);
            } else {
                // Update existing
                $pdo->prepare("UPDATE inventory SET qty = qty + ? WHERE part_no = ?")
                    ->execute([$addQty, $child['component_part_no']]);
            }

            // Record in depletion table as addition
            $issueNo = $batchRef . '-' . ($addedCount + 1);
            $adjReason = "BOM Stock Entry: {$bom['bom_no']} x{$multiplier}" . ($reason !== 'BOM Stock Entry' ? " - $reason" : '');
            $pdo->prepare("
                INSERT INTO depletion (part_no, qty, issue_date, reason, status, issue_no, adjustment_type)
                VALUES (?, ?, CURDATE(), ?, 'issued', ?, 'addition')
            ")->execute([$child['component_part_no'], $addQty, $adjReason, $issueNo]);

            $entryResults[] = [
                'part_no' => $child['component_part_no'],
                'part_name' => $child['part_name'],
                'bom_qty' => $child['qty'],
                'added_qty' => $addQty,
                'old_stock' => (int)($currentQty ?: 0),
                'new_stock' => (int)($currentQty ?: 0) + $addQty
            ];

            $addedCount++;
            $totalQtyAdded += $addQty;
        }

        $pdo->commit();
        $message = "Successfully added stock for $addedCount child parts (Total: $totalQtyAdded units) from BOM {$bom['bom_no']} x{$multiplier}.";
        $messageType = 'success';

    } catch (Exception $e) {
        $pdo->rollBack();
        setModal("Error", "Failed to add stock: " . $e->getMessage());
        header("Location: bom_stock_entry.php");
        exit;
    }
}

// Get parent parts that have active BOMs (for search)
$parentParts = [];
if ($hasBom) {
    $parentParts = $pdo->query("
        SELECT DISTINCT b.parent_part_no, p.part_name, b.bom_no,
               (SELECT COUNT(*) FROM bom_items WHERE bom_id = b.id) as child_count
        FROM bom_master b
        JOIN part_master p ON p.part_no = b.parent_part_no
        WHERE b.status = 'active'
        ORDER BY p.part_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>BOM Stock Entry</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .bom-entry-form {
            max-width: 600px;
            margin-bottom: 30px;
        }
        .parent-search-container {
            position: relative;
        }
        .parent-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .parent-dropdown.active { display: block; }
        .parent-option {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.15s;
        }
        .parent-option:hover { background: #f0f7ff; }
        .parent-option:last-child { border-bottom: none; }
        .parent-option .part-no { font-weight: bold; color: #2c3e50; }
        .parent-option .part-name { font-size: 0.9em; color: #666; margin-top: 2px; }
        .parent-option .bom-info { font-size: 0.8em; color: #999; margin-top: 3px; }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .preview-table th, .preview-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
        }
        .preview-table th {
            background: #4a90d9;
            color: white;
            font-size: 0.9em;
        }
        .preview-table tr:nth-child(even) { background: #f9f9f9; }
        .preview-table td.number { text-align: right; }

        .result-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .result-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .result-table th, .result-table td { padding: 8px 12px; border: 1px solid #c3e6cb; }
        .result-table th { background: #28a745; color: white; }
        .result-table tr:nth-child(even) { background: #e8f5e9; }

        .multiplier-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .multiplier-group input[type="number"] {
            width: 100px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .multiplier-hint {
            font-size: 0.85em;
            color: #666;
        }

        .preview-section {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background: #f0f7ff;
            border: 1px solid #b8d4f0;
            border-radius: 8px;
        }
        .preview-section.visible { display: block; }
        .preview-section h3 { margin: 0 0 10px; color: #2c5282; }

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>BOM Stock Entry</h1>

    <div class="info-box">
        <strong>How it works:</strong> Select a parent part with an active BOM. All child/component parts will get stock added based on BOM quantities.
        The parent part itself will NOT receive any stock — only its child components.
    </div>

    <a href="/inventory/index.php" class="btn btn-secondary">Back to Inventory</a>
    <a href="/depletion/stock_adjustment.php" class="btn btn-secondary" style="margin-left: 5px;">Stock Adjustment</a>
    <br><br>

    <?php if ($messageType === 'success' && !empty($entryResults)): ?>
    <div class="result-success">
        <strong><?= htmlspecialchars($message) ?></strong>
        <table class="result-table">
            <tr>
                <th>#</th>
                <th>Part No</th>
                <th>Part Name</th>
                <th>BOM Qty</th>
                <th>Added Qty</th>
                <th>Old Stock</th>
                <th>New Stock</th>
            </tr>
            <?php foreach ($entryResults as $idx => $r): ?>
            <tr>
                <td><?= $idx + 1 ?></td>
                <td><?= htmlspecialchars($r['part_no']) ?></td>
                <td><?= htmlspecialchars($r['part_name']) ?></td>
                <td class="number"><?= $r['bom_qty'] ?></td>
                <td class="number" style="font-weight: bold; color: #28a745;">+<?= $r['added_qty'] ?></td>
                <td class="number"><?= $r['old_stock'] ?></td>
                <td class="number" style="font-weight: bold;"><?= $r['new_stock'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!$hasBom): ?>
        <div style="background: #f8d7da; padding: 15px; border-radius: 8px; color: #721c24;">
            BOM module is not set up. Please create BOMs first.
        </div>
    <?php else: ?>

    <form method="post" class="bom-entry-form" id="bomForm">
        <div class="form-grid" style="max-width: 500px;">

            <label>Parent Part (Assembly)</label>
            <div class="parent-search-container">
                <input type="text" id="parentSearch" placeholder="Search by part no or name..."
                       autocomplete="off"
                       style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
                <div class="parent-dropdown" id="parentDropdown"></div>
            </div>
            <input type="hidden" name="parent_part_no" id="parentPartNo" required>

            <div></div>
            <div id="selectedPartInfo" style="display: none; background: #e8f5e9; padding: 10px; border-radius: 6px; margin-bottom: 5px;">
                <strong id="selectedPartLabel"></strong>
                <span id="selectedBomInfo" style="color: #666; font-size: 0.9em;"></span>
            </div>

            <label>Multiplier (Units to produce)</label>
            <div class="multiplier-group">
                <input type="number" name="multiplier" id="multiplier" value="1" min="1" required>
                <span class="multiplier-hint">BOM qty x multiplier = added stock</span>
            </div>

            <label>Reason / Reference</label>
            <input type="text" name="reason" value="BOM Stock Entry" placeholder="Reason for stock entry"
                   style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">

            <div></div>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="button" class="btn btn-primary" id="previewBtn" onclick="loadPreview()" style="display: none;">Preview</button>
                <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;"
                        onclick="return confirm('Add stock for all child parts? This action will update inventory.');">
                    Add Stock for All Child Parts
                </button>
            </div>
        </div>
    </form>

    <!-- Preview Section (loaded via AJAX-like fetch) -->
    <div class="preview-section" id="previewSection">
        <h3>BOM Child Parts Preview</h3>
        <p id="previewSummary"></p>
        <table class="preview-table" id="previewTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>UOM</th>
                    <th>BOM Qty</th>
                    <th>Multiplier</th>
                    <th>Stock to Add</th>
                    <th>Current Stock</th>
                    <th>New Stock</th>
                </tr>
            </thead>
            <tbody id="previewBody"></tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<script>
// Parent parts data
const parentParts = <?= json_encode($parentParts) ?>;

const searchInput = document.getElementById('parentSearch');
const dropdown = document.getElementById('parentDropdown');
const hiddenInput = document.getElementById('parentPartNo');
const selectedInfo = document.getElementById('selectedPartInfo');
const selectedLabel = document.getElementById('selectedPartLabel');
const selectedBomInfo = document.getElementById('selectedBomInfo');
const previewBtn = document.getElementById('previewBtn');
const submitBtn = document.getElementById('submitBtn');
const multiplierInput = document.getElementById('multiplier');

let bomChildrenCache = {};

// Search parent parts
searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    if (!query) {
        dropdown.classList.remove('active');
        return;
    }

    const filtered = parentParts.filter(p =>
        p.parent_part_no.toLowerCase().includes(query) ||
        p.part_name.toLowerCase().includes(query) ||
        p.bom_no.toLowerCase().includes(query)
    );

    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding: 12px; color: #888; text-align: center;">No parent parts with active BOM found</div>';
    } else {
        dropdown.innerHTML = filtered.map(p => `
            <div class="parent-option" data-part-no="${escHtml(p.parent_part_no)}" data-bom-no="${escHtml(p.bom_no)}" data-child-count="${p.child_count}">
                <div class="part-no">${escHtml(p.parent_part_no)}</div>
                <div class="part-name">${escHtml(p.part_name)}</div>
                <div class="bom-info">BOM: ${escHtml(p.bom_no)} | ${p.child_count} child parts</div>
            </div>
        `).join('');

        dropdown.querySelectorAll('.parent-option').forEach(opt => {
            opt.addEventListener('click', function() {
                selectParent(this.dataset.partNo, this.querySelector('.part-name').textContent, this.dataset.bomNo, this.dataset.childCount);
            });
        });
    }
    dropdown.classList.add('active');
});

searchInput.addEventListener('focus', function() {
    if (this.value.trim()) {
        this.dispatchEvent(new Event('input'));
    }
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
    }
});

function selectParent(partNo, partName, bomNo, childCount) {
    hiddenInput.value = partNo;
    searchInput.value = partNo + ' — ' + partName;
    dropdown.classList.remove('active');

    selectedLabel.textContent = partNo + ' — ' + partName;
    selectedBomInfo.textContent = ' | BOM: ' + bomNo + ' | ' + childCount + ' child parts';
    selectedInfo.style.display = 'block';

    previewBtn.style.display = 'inline-block';
    submitBtn.style.display = 'inline-block';

    // Auto-load preview
    loadPreview();
}

function loadPreview() {
    const partNo = hiddenInput.value;
    const mult = parseInt(multiplierInput.value) || 1;

    if (!partNo) return;

    // Fetch BOM children via inline PHP data
    const bomData = parentParts.find(p => p.parent_part_no === partNo);
    if (!bomData) return;

    // Use fetch to get children data
    fetch('bom_stock_preview.php?parent_part_no=' + encodeURIComponent(partNo) + '&multiplier=' + mult)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const tbody = document.getElementById('previewBody');
            tbody.innerHTML = '';
            let totalToAdd = 0;

            data.children.forEach((child, idx) => {
                const addQty = Math.floor(child.bom_qty * mult);
                const newStock = child.current_stock + addQty;
                totalToAdd += addQty;

                tbody.innerHTML += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td>${escHtml(child.part_no)}</td>
                        <td>${escHtml(child.part_name)}</td>
                        <td>${escHtml(child.uom || '')}</td>
                        <td class="number">${child.bom_qty}</td>
                        <td class="number">x${mult}</td>
                        <td class="number" style="font-weight: bold; color: #28a745;">+${addQty}</td>
                        <td class="number">${child.current_stock}</td>
                        <td class="number" style="font-weight: bold;">${newStock}</td>
                    </tr>
                `;
            });

            document.getElementById('previewSummary').innerHTML =
                `<strong>BOM:</strong> ${escHtml(data.bom_no)} | ` +
                `<strong>Parent:</strong> ${escHtml(data.parent_name)} | ` +
                `<strong>${data.children.length}</strong> child parts | ` +
                `<strong>Total stock to add:</strong> ${totalToAdd} units`;

            document.getElementById('previewSection').classList.add('visible');
        })
        .catch(err => {
            console.error(err);
        });
}

// Update preview when multiplier changes
multiplierInput.addEventListener('change', loadPreview);
multiplierInput.addEventListener('input', function() {
    clearTimeout(this._debounce);
    this._debounce = setTimeout(loadPreview, 300);
});

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

</body>
</html>
