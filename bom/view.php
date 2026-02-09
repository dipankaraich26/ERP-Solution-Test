<?php
include "../db.php";
include "../includes/sidebar.php";

// Auto-migrate: add rate column to bom_items if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM bom_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('rate', $cols)) {
        $pdo->exec("ALTER TABLE bom_items ADD COLUMN rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER qty");
        $pdo->exec("UPDATE bom_items bi JOIN part_master pm ON bi.component_part_no = pm.part_no SET bi.rate = pm.rate");
    }
} catch (PDOException $e) {}

/**
 * Get LATEST rate for a part based on supplier pricing:
 * 1. If preferred supplier exists → use that rate
 * 2. Else if any active supplier exists → use lowest rate
 * 3. Else → use part_master rate
 */
function getPartRate($pdo, $part_no) {
    try {
        // First check for preferred supplier
        $prefStmt = $pdo->prepare("
            SELECT supplier_rate FROM part_supplier_mapping
            WHERE part_no = ? AND (active = 1 OR active IS NULL) AND is_preferred = 1
            AND supplier_rate > 0
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
            WHERE part_no = ? AND (active = 1 OR active IS NULL) AND supplier_rate > 0
        ");
        $supStmt->execute([$part_no]);
        $lowestRate = $supStmt->fetchColumn();
        if ($lowestRate && $lowestRate > 0) {
            return (float)$lowestRate;
        }
    } catch (PDOException $e) {
        // Table might not exist
    }

    // Fallback to part_master rate
    $pmStmt = $pdo->prepare("SELECT rate FROM part_master WHERE part_no = ?");
    $pmStmt->execute([$part_no]);
    return (float)$pmStmt->fetchColumn() ?: 0;
}

$id = $_GET['id'];

$bom = $pdo->prepare("
    SELECT b.bom_no, b.description, b.status, p.part_name, p.part_no AS parent_part_no
    FROM bom_master b
    JOIN part_master p ON b.parent_part_no = p.part_no
    WHERE b.id=?
");
$bom->execute([$id]);
$bom = $bom->fetch();

$items = $pdo->prepare("
    SELECT i.qty, i.rate, p.part_name, p.part_no, p.category, p.uom,
           COALESCE(inv.qty, 0) AS current_stock
    FROM bom_items i
    JOIN part_master p ON i.component_part_no = p.part_no
    LEFT JOIN inventory inv ON inv.part_no = p.part_no
    WHERE i.bom_id=?
");
$items->execute([$id]);
$itemsData = $items->fetchAll(PDO::FETCH_ASSOC);

// Function to get sub-BOM items for an Assembly part (recursive cost calculation)
function getSubBomItems($pdo, $part_no, $depth = 0) {
    if ($depth > 10) return null; // Prevent infinite recursion

    // Find BOM where this part is the parent (check both active and inactive)
    $bomStmt = $pdo->prepare("
        SELECT b.id, b.bom_no, b.status
        FROM bom_master b
        WHERE b.parent_part_no = ?
        ORDER BY b.status = 'active' DESC
        LIMIT 1
    ");
    $bomStmt->execute([$part_no]);
    $subBom = $bomStmt->fetch();

    if (!$subBom) {
        return null;
    }

    // Get the sub-BOM items
    $subItemsStmt = $pdo->prepare("
        SELECT i.qty, i.rate, p.part_name, p.part_no, p.category, p.uom,
               COALESCE(inv.qty, 0) AS current_stock
        FROM bom_items i
        JOIN part_master p ON i.component_part_no = p.part_no
        LEFT JOIN inventory inv ON inv.part_no = p.part_no
        WHERE i.bom_id = ?
    ");
    $subItemsStmt->execute([$subBom['id']]);

    $subItems = $subItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Only return sub-BOM if it actually has items
    if (empty($subItems)) {
        return null;
    }

    // Calculate sub-BOM cost recursively using LATEST supplier rates
    $subBomCost = 0;
    foreach ($subItems as &$si) {
        // Get latest rate from supplier pricing
        $si['rate'] = getPartRate($pdo, $si['part_no']);

        // Check if this sub-item itself has a child BOM
        $si['sub_bom'] = getSubBomItems($pdo, $si['part_no'], $depth + 1);
        if (!empty($si['sub_bom'])) {
            // Use child BOM total cost as effective rate
            $si['effective_rate'] = $si['sub_bom']['total_cost'];
        } else {
            // Use latest supplier rate
            $si['effective_rate'] = (float)$si['rate'];
        }
        $si['effective_cost'] = (float)$si['qty'] * $si['effective_rate'];
        $subBomCost += $si['effective_cost'];
    }
    unset($si);

    return [
        'bom_no' => $subBom['bom_no'],
        'bom_id' => $subBom['id'],
        'status' => $subBom['status'],
        'items' => $subItems,
        'total_cost' => $subBomCost
    ];
}

// Check ALL items if they have a sub-BOM (recursive cost)
foreach ($itemsData as &$item) {
    $item['sub_bom'] = getSubBomItems($pdo, $item['part_no']);
}
unset($item);

// Calculate total BOM cost using LATEST supplier rates and sub-BOM costs
$totalBomCost = 0;
foreach ($itemsData as &$item) {
    // Get latest rate from supplier pricing for this part
    $item['rate'] = getPartRate($pdo, $item['part_no']);

    if (!empty($item['sub_bom'])) {
        // Sub-assembly: cost = qty × sub-BOM total cost
        $item['effective_rate'] = $item['sub_bom']['total_cost'];
    } else {
        // Regular part: cost = qty × latest supplier rate
        $item['effective_rate'] = (float)$item['rate'];
    }
    $item['effective_cost'] = (float)$item['qty'] * $item['effective_rate'];
    $totalBomCost += $item['effective_cost'];
}
unset($item);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View BOM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            table {
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }
            table th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            table td {
                border: 1px solid #000 !important;
            }
        }
        .assembly-row {
            cursor: pointer;
        }
        .assembly-row:hover {
            background-color: #f0f8ff;
        }
        .assembly-toggle {
            display: inline-block;
            width: 20px;
            text-align: center;
            font-weight: bold;
            margin-right: 5px;
        }
        .sub-bom-row {
            display: none;
        }
        .sub-bom-row.expanded {
            display: table-row;
        }
        .sub-bom-cell {
            padding-left: 30px !important;
            background-color: #f9f9f9;
        }
        .sub-bom-table {
            margin: 0;
            width: 100%;
            border-collapse: collapse;
        }
        .sub-bom-table td, .sub-bom-table th {
            padding: 5px 8px;
            border: 1px solid #ddd;
            font-size: 0.9em;
        }
        .sub-bom-table th {
            background-color: #4a90d9;
            color: white;
        }
        .assembly-badge {
            background-color: #007bff;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-left: 5px;
        }
    </style>
</head>
<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">BOM <?= htmlspecialchars($bom['bom_no']) ?></h1>
        <div class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="edit.php?id=<?= $id ?>" class="btn btn-warning" style="display: inline-flex; align-items: center; gap: 5px;">✏️ Edit</a>
            <a href="duplicate.php?id=<?= $id ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 5px;" onclick="return confirm('Create a duplicate of this BOM?');">📋 Duplicate</a>
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
            <button onclick="exportToExcel()" class="btn btn-success">📊 Export</button>
            <button onclick="openAllLevelsBom()" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">🔍 All Level BOM</button>
        </div>
    </div>

    <p><strong>Parent Part:</strong> <?= htmlspecialchars($bom['part_name']) ?> (<?= htmlspecialchars($bom['parent_part_no']) ?>)</p>
    <p><strong>Status:</strong> <?= htmlspecialchars($bom['status']) ?></p>
    <p><strong>Description:</strong> <?= htmlspecialchars($bom['description']) ?></p>

    <!-- BOM Cost Summary -->
    <div style="background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%); color: white; padding: 15px 20px; border-radius: 8px; margin: 20px 0; display: inline-block;">
        <span style="font-size: 0.9em; opacity: 0.9;">Total BOM Cost:</span>
        <span style="font-size: 1.5em; font-weight: bold; margin-left: 10px;">₹ <?= number_format($totalBomCost, 2) ?></span>
    </div>

    <table border="1" cellpadding="8" id="bomTable">
        <tr>
            <th>Part Number</th>
            <th>Component</th>
            <th>Category</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Cost</th>
            <th>Current Stock</th>
        </tr>
        <?php foreach ($itemsData as $index => $i): ?>
        <?php
            $hasSubBom = !empty($i['sub_bom']);
        ?>
        <tr class="<?= $hasSubBom ? 'assembly-row' : '' ?>" <?= $hasSubBom ? 'onclick="toggleSubBom(' . $index . ')"' : '' ?>>
            <td><?= htmlspecialchars($i['part_no']) ?></td>
            <td>
                <?php if ($hasSubBom): ?>
                    <span class="assembly-toggle" id="toggle-<?= $index ?>">+</span>
                <?php endif; ?>
                <?= htmlspecialchars($i['part_name']) ?>
                <?php if ($hasSubBom): ?>
                    <span class="assembly-badge">Has Sub-BOM</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($i['category'] ?? '') ?></td>
            <td><?= $i['qty'] ?> <?= htmlspecialchars($i['uom'] ?? '') ?></td>
            <td style="text-align: right;">
                <?php if ($hasSubBom): ?>
                    <span title="Sub-BOM Cost">₹ <?= number_format($i['effective_rate'], 2) ?></span>
                <?php else: ?>
                    ₹ <?= number_format((float)$i['rate'], 2) ?>
                <?php endif; ?>
            </td>
            <td style="text-align: right; font-weight: bold;">₹ <?= number_format($i['effective_cost'], 2) ?></td>
            <td><?= $i['current_stock'] ?></td>
        </tr>
        <?php if ($hasSubBom): ?>
        <tr class="sub-bom-row" id="sub-bom-<?= $index ?>">
            <td colspan="7" class="sub-bom-cell">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Sub-BOM: <?= htmlspecialchars($i['sub_bom']['bom_no']) ?></strong>
                    <span style="background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.9em;">
                        Sub-BOM Cost: ₹ <?= number_format($i['sub_bom']['total_cost'], 2) ?>
                    </span>
                </div>
                <table class="sub-bom-table">
                    <tr>
                        <th>Part Number</th>
                        <th>Component</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Cost</th>
                        <th>Current Stock</th>
                    </tr>
                    <?php foreach ($i['sub_bom']['items'] as $subItem): ?>
                    <tr>
                        <td><?= htmlspecialchars($subItem['part_no']) ?></td>
                        <td>
                            <?= htmlspecialchars($subItem['part_name']) ?>
                            <?php if (!empty($subItem['sub_bom'])): ?>
                                <span class="assembly-badge">Has Sub-BOM</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($subItem['category'] ?? '') ?></td>
                        <td><?= $subItem['qty'] ?> <?= htmlspecialchars($subItem['uom'] ?? '') ?></td>
                        <td style="text-align: right;">₹ <?= number_format($subItem['effective_rate'], 2) ?></td>
                        <td style="text-align: right; font-weight: bold;">₹ <?= number_format($subItem['effective_cost'], 2) ?></td>
                        <td><?= $subItem['current_stock'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <a href="view.php?id=<?= $i['sub_bom']['bom_id'] ?>" class="btn btn-secondary" style="display: inline-block; margin-top: 10px; font-size: 0.85em; padding: 4px 8px;">View Full Sub-BOM</a>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <script>
    function toggleSubBom(index) {
        const subBomRow = document.getElementById('sub-bom-' + index);
        const toggleIcon = document.getElementById('toggle-' + index);

        if (subBomRow.classList.contains('expanded')) {
            subBomRow.classList.remove('expanded');
            toggleIcon.textContent = '+';
        } else {
            subBomRow.classList.add('expanded');
            toggleIcon.textContent = '-';
        }
    }
    </script>

    <br>
    <a href="index.php" class="btn btn-secondary no-print">⬅ Back to BOM</a>
</div>

<script>
function exportToExcel() {
    const bomNo = <?= json_encode($bom['bom_no']) ?>;
    const parentPart = <?= json_encode($bom['part_name'] . ' (' . $bom['parent_part_no'] . ')') ?>;
    const status = <?= json_encode($bom['status']) ?>;
    const description = <?= json_encode($bom['description']) ?>;

    // Create workbook
    const wb = XLSX.utils.book_new();

    const totalBomCost = <?= json_encode($totalBomCost) ?>;

    // Header data
    const headerData = [
        ['BOM Number', bomNo],
        ['Parent Part', parentPart],
        ['Status', status],
        ['Description', description],
        ['Total BOM Cost', '₹ ' + parseFloat(totalBomCost).toLocaleString('en-IN', {minimumFractionDigits: 2})],
        [], // Empty row
        ['Part Number', 'Component', 'Category', 'Qty', 'Rate', 'Cost', 'Current Stock']
    ];

    // Get table data with sub-BOM support
    const itemsData = <?= json_encode($itemsData) ?>;
    const tableData = [];

    itemsData.forEach(item => {
        const effectiveRate = parseFloat(item.effective_rate) || 0;
        const qty = parseFloat(item.qty) || 0;
        const effectiveCost = parseFloat(item.effective_cost) || 0;

        // Add main item
        tableData.push([
            item.part_no,
            item.part_name + (item.sub_bom ? ' [Sub-BOM]' : ''),
            item.category || '',
            qty + ' ' + (item.uom || ''),
            effectiveRate.toFixed(2),
            effectiveCost.toFixed(2),
            item.current_stock
        ]);

        // Add sub-BOM items if present
        if (item.sub_bom && item.sub_bom.items) {
            tableData.push(['', '--- Sub-BOM: ' + item.sub_bom.bom_no + ' (Cost: ₹' + parseFloat(item.sub_bom.total_cost).toFixed(2) + ') ---', '', '', '', '', '']);
            item.sub_bom.items.forEach(subItem => {
                const subEffRate = parseFloat(subItem.effective_rate) || 0;
                const subEffCost = parseFloat(subItem.effective_cost) || 0;
                const subQty = parseFloat(subItem.qty) || 0;
                tableData.push([
                    '    ' + subItem.part_no,
                    '    ' + subItem.part_name,
                    subItem.category || '',
                    subQty + ' ' + (subItem.uom || ''),
                    subEffRate.toFixed(2),
                    subEffCost.toFixed(2),
                    subItem.current_stock
                ]);
            });
            tableData.push(['', '', '', '', '', '', '']); // Empty row after sub-BOM
        }
    });

    // Combine all data
    const wsData = [...headerData, ...tableData];

    // Create worksheet
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Set column widths
    ws['!cols'] = [
        { wch: 18 },
        { wch: 40 },
        { wch: 12 },
        { wch: 12 },
        { wch: 12 },
        { wch: 14 },
        { wch: 12 }
    ];

    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'BOM');

    // Generate filename
    const filename = 'BOM_' + bomNo + '.xlsx';

    // Save file
    XLSX.writeFile(wb, filename);
}
</script>

<!-- All Level BOM Modal -->
<div id="allLevelBomModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,0.6);">
    <div style="position:absolute; top:20px; left:50%; transform:translateX(-50%); width:95%; max-width:1200px; max-height:calc(100vh - 40px); background:var(--card, #fff); border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.3); display:flex; flex-direction:column;">
        <!-- Header -->
        <div style="padding:15px 20px; border-bottom:1px solid var(--border, #e2e8f0); display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <div>
                <h2 style="margin:0; font-size:1.3em;">All Level BOM - <?= htmlspecialchars($bom['bom_no']) ?></h2>
                <p style="margin:4px 0 0; color:#7f8c8d; font-size:0.9em;"><?= htmlspecialchars($bom['part_name']) ?> (<?= htmlspecialchars($bom['parent_part_no']) ?>)</p>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button onclick="exportAllLevelBom()" class="btn btn-sm" style="padding:6px 12px; font-size:0.85em; background:#d97706; color:#fff; border:none; border-radius:6px; cursor:pointer;">Export Excel</button>
                <button onclick="expandAllLevels()" class="btn btn-sm" style="padding:6px 12px; font-size:0.85em; background:#28a745; color:#fff; border:none; border-radius:6px; cursor:pointer;">Expand All</button>
                <button onclick="collapseAllLevels()" class="btn btn-sm" style="padding:6px 12px; font-size:0.85em; background:#6c757d; color:#fff; border:none; border-radius:6px; cursor:pointer;">Collapse All</button>
                <button onclick="closeAllLevelsBom()" style="background:none; border:none; font-size:1.5em; cursor:pointer; color:#999; padding:0 5px;">&times;</button>
            </div>
        </div>
        <!-- Cost Summary -->
        <div style="padding:10px 20px; background:linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%); color:white; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <span>Total BOM Cost (All Levels)</span>
            <span style="font-size:1.3em; font-weight:bold;">₹ <?= number_format($totalBomCost, 2) ?></span>
        </div>
        <!-- Level Legend -->
        <div style="padding:8px 20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; border-bottom:1px solid var(--border, #e2e8f0); flex-shrink:0; font-size:0.85em;">
            <span style="color:#666;">Levels:</span>
            <span style="background:#3498db; color:#fff; padding:2px 8px; border-radius:4px;">L0 - Parent</span>
            <span style="background:#27ae60; color:#fff; padding:2px 8px; border-radius:4px;">L1</span>
            <span style="background:#e67e22; color:#fff; padding:2px 8px; border-radius:4px;">L2</span>
            <span style="background:#e74c3c; color:#fff; padding:2px 8px; border-radius:4px;">L3</span>
            <span style="background:#9b59b6; color:#fff; padding:2px 8px; border-radius:4px;">L4+</span>
        </div>
        <!-- Table Content -->
        <div style="overflow:auto; flex:1; padding:0;">
            <table id="allLevelTable" style="width:100%; border-collapse:collapse;">
                <thead style="position:sticky; top:0; z-index:1;">
                    <tr style="background:var(--card, #f8f9fa);">
                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid var(--border, #dee2e6); min-width:60px;">Level</th>
                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid var(--border, #dee2e6); min-width:140px;">Part Number</th>
                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid var(--border, #dee2e6); min-width:200px;">Component</th>
                        <th style="padding:10px 12px; text-align:left; border-bottom:2px solid var(--border, #dee2e6);">Category</th>
                        <th style="padding:10px 12px; text-align:center; border-bottom:2px solid var(--border, #dee2e6);">Qty</th>
                        <th style="padding:10px 12px; text-align:right; border-bottom:2px solid var(--border, #dee2e6);">Rate</th>
                        <th style="padding:10px 12px; text-align:right; border-bottom:2px solid var(--border, #dee2e6);">Cost</th>
                        <th style="padding:10px 12px; text-align:center; border-bottom:2px solid var(--border, #dee2e6);">Stock</th>
                    </tr>
                </thead>
                <tbody id="allLevelBody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .alb-row { transition: background 0.15s; }
    .alb-row:hover { background: rgba(52,152,219,0.08) !important; }
    .alb-toggle { cursor:pointer; display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:4px; font-size:0.8em; font-weight:bold; margin-right:4px; transition:all 0.2s; border:1px solid #ccc; background:#f0f0f0; }
    .alb-toggle:hover { background:#ddd; }
    .alb-toggle.open { transform:rotate(90deg); }
    .alb-level-0 { background:#ebf5fb; }
    .alb-level-1 { background:#eafaf1; }
    .alb-level-2 { background:#fef5e7; }
    .alb-level-3 { background:#fdedec; }
    .alb-level-4 { background:#f4ecf7; }
    .alb-badge { display:inline-block; padding:2px 8px; border-radius:4px; color:#fff; font-size:0.75em; font-weight:600; }
    .alb-badge-0 { background:#3498db; }
    .alb-badge-1 { background:#27ae60; }
    .alb-badge-2 { background:#e67e22; }
    .alb-badge-3 { background:#e74c3c; }
    .alb-badge-4 { background:#9b59b6; }
    .alb-sub-badge { background:#007bff; color:white; padding:1px 6px; border-radius:3px; font-size:0.75em; margin-left:5px; }
    .alb-hidden { display:none; }
</style>

<script>
const allLevelData = <?= json_encode($itemsData) ?>;
const bomNo = <?= json_encode($bom['bom_no']) ?>;

function openAllLevelsBom() {
    document.getElementById('allLevelBomModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    renderAllLevelBom();
}

function closeAllLevelsBom() {
    document.getElementById('allLevelBomModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close on outside click
document.getElementById('allLevelBomModal').addEventListener('click', function(e) {
    if (e.target === this) closeAllLevelsBom();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllLevelsBom();
});

function renderAllLevelBom() {
    const tbody = document.getElementById('allLevelBody');
    tbody.innerHTML = '';
    let rowId = 0;

    function addRows(items, level, parentId, parentQty) {
        items.forEach(item => {
            const id = rowId++;
            const hasChildren = item.sub_bom && item.sub_bom.items && item.sub_bom.items.length > 0;
            const levelClass = level > 4 ? 4 : level;
            const indent = level * 24;
            const effectiveRate = parseFloat(item.effective_rate) || parseFloat(item.rate) || 0;
            const effectiveCost = parseFloat(item.effective_cost) || (parseFloat(item.qty) * effectiveRate);

            const tr = document.createElement('tr');
            tr.className = 'alb-row alb-level-' + levelClass;
            tr.dataset.id = id;
            tr.dataset.level = level;
            tr.dataset.parent = parentId;
            if (parentId !== -1 && level > 0) tr.classList.add('alb-hidden');

            tr.innerHTML = `
                <td style="padding:8px 12px;">
                    <span class="alb-badge alb-badge-${levelClass}">L${level}</span>
                </td>
                <td style="padding:8px 12px;">${escHtml(item.part_no)}</td>
                <td style="padding:8px 12px 8px ${12 + indent}px;">
                    ${hasChildren ? '<span class="alb-toggle" data-id="' + id + '" onclick="toggleLevel(this)">▶</span>' : '<span style="display:inline-block;width:26px;"></span>'}
                    ${escHtml(item.part_name)}
                    ${hasChildren ? '<span class="alb-sub-badge">Sub-BOM: ' + escHtml(item.sub_bom.bom_no) + '</span>' : ''}
                </td>
                <td style="padding:8px 12px;">${escHtml(item.category || '')}</td>
                <td style="padding:8px 12px; text-align:center;">${parseFloat(item.qty)} ${escHtml(item.uom || '')}</td>
                <td style="padding:8px 12px; text-align:right;">₹ ${effectiveRate.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td style="padding:8px 12px; text-align:right; font-weight:600;">₹ ${effectiveCost.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td style="padding:8px 12px; text-align:center;">${item.current_stock}</td>
            `;
            tbody.appendChild(tr);

            if (hasChildren) {
                addRows(item.sub_bom.items, level + 1, id, parseFloat(item.qty));
            }
        });
    }

    addRows(allLevelData, 0, -1, 1);
}

function toggleLevel(toggleEl) {
    const parentId = toggleEl.dataset.id;
    const isOpen = toggleEl.classList.contains('open');
    toggleEl.classList.toggle('open');

    const rows = document.querySelectorAll('#allLevelBody tr');
    if (isOpen) {
        // Collapse: hide all descendants
        hideDescendants(parentId, rows);
    } else {
        // Expand: show direct children only
        rows.forEach(row => {
            if (row.dataset.parent === parentId) {
                row.classList.remove('alb-hidden');
            }
        });
    }
}

function hideDescendants(parentId, rows) {
    rows.forEach(row => {
        if (row.dataset.parent === parentId) {
            row.classList.add('alb-hidden');
            // Also collapse toggle if it's open
            const toggle = row.querySelector('.alb-toggle');
            if (toggle) toggle.classList.remove('open');
            // Recursively hide children
            hideDescendants(row.dataset.id, rows);
        }
    });
}

function expandAllLevels() {
    const rows = document.querySelectorAll('#allLevelBody tr');
    rows.forEach(row => row.classList.remove('alb-hidden'));
    document.querySelectorAll('.alb-toggle').forEach(t => t.classList.add('open'));
}

function collapseAllLevels() {
    const rows = document.querySelectorAll('#allLevelBody tr');
    rows.forEach(row => {
        if (parseInt(row.dataset.level) > 0) {
            row.classList.add('alb-hidden');
        }
    });
    document.querySelectorAll('.alb-toggle').forEach(t => t.classList.remove('open'));
}

function exportAllLevelBom() {
    const parentPart = <?= json_encode($bom['part_name'] . ' (' . $bom['parent_part_no'] . ')') ?>;
    const status = <?= json_encode($bom['status']) ?>;
    const description = <?= json_encode($bom['description']) ?>;
    const totalCost = <?= json_encode($totalBomCost) ?>;

    const wb = XLSX.utils.book_new();

    // Header rows
    const wsData = [
        ['All Level BOM - ' + bomNo],
        ['Parent Part', parentPart],
        ['Status', status],
        ['Description', description],
        ['Total BOM Cost', parseFloat(totalCost)],
        [],
        ['Level', 'Part Number', 'Component', 'Category', 'Qty', 'UOM', 'Rate', 'Cost', 'Stock', 'Sub-BOM']
    ];

    // Recursive flatten
    function flattenItems(items, level) {
        items.forEach(function(item) {
            var effectiveRate = parseFloat(item.effective_rate) || parseFloat(item.rate) || 0;
            var effectiveCost = parseFloat(item.effective_cost) || (parseFloat(item.qty) * effectiveRate);
            var indent = '';
            for (var i = 0; i < level; i++) indent += '  ';

            wsData.push([
                'L' + level,
                item.part_no,
                indent + item.part_name,
                item.category || '',
                parseFloat(item.qty) || 0,
                item.uom || '',
                effectiveRate,
                effectiveCost,
                parseInt(item.current_stock) || 0,
                (item.sub_bom && item.sub_bom.bom_no) ? item.sub_bom.bom_no : ''
            ]);

            if (item.sub_bom && item.sub_bom.items && item.sub_bom.items.length > 0) {
                flattenItems(item.sub_bom.items, level + 1);
            }
        });
    }

    flattenItems(allLevelData, 0);

    var ws = XLSX.utils.aoa_to_sheet(wsData);

    // Column widths
    ws['!cols'] = [
        { wch: 8 },
        { wch: 18 },
        { wch: 40 },
        { wch: 14 },
        { wch: 8 },
        { wch: 8 },
        { wch: 14 },
        { wch: 14 },
        { wch: 10 },
        { wch: 16 }
    ];

    // Format cost column as number
    var headerRows = 7;
    for (var r = headerRows; r < wsData.length; r++) {
        var rateCell = XLSX.utils.encode_cell({r: r, c: 6});
        var costCell = XLSX.utils.encode_cell({r: r, c: 7});
        if (ws[rateCell]) ws[rateCell].t = 'n';
        if (ws[costCell]) ws[costCell].t = 'n';
    }

    XLSX.utils.book_append_sheet(wb, ws, 'All Level BOM');
    XLSX.writeFile(wb, 'BOM_AllLevel_' + bomNo + '.xlsx');
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

</body>
</html>
