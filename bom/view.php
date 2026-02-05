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

// Calculate total BOM cost
$totalBomCost = 0;
foreach ($itemsData as $item) {
    $totalBomCost += (float)$item['qty'] * (float)$item['rate'];
}

// Function to get sub-BOM items for an Assembly part
function getSubBomItems($pdo, $part_no) {
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

    // Calculate sub-BOM cost
    $subBomCost = 0;
    foreach ($subItems as $si) {
        $subBomCost += (float)$si['qty'] * (float)$si['rate'];
    }

    // Only return sub-BOM if it actually has items
    if (empty($subItems)) {
        return null;
    }

    return [
        'bom_no' => $subBom['bom_no'],
        'bom_id' => $subBom['id'],
        'status' => $subBom['status'],
        'items' => $subItems,
        'total_cost' => $subBomCost
    ];
}

// Check ALL items if they have a sub-BOM (part is parent in another BOM)
// This detects sub-assemblies by checking if the part_no exists as parent_part_no in bom_master
foreach ($itemsData as &$item) {
    $item['sub_bom'] = getSubBomItems($pdo, $item['part_no']);
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
        <div class="no-print" style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
            <button onclick="exportToExcel()" class="btn btn-success">📊 Export to Excel</button>
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
            $itemCost = (float)$i['qty'] * (float)$i['rate'];
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
            <td style="text-align: right;">₹ <?= number_format((float)$i['rate'], 2) ?></td>
            <td style="text-align: right; font-weight: bold;">₹ <?= number_format($itemCost, 2) ?></td>
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
                    <?php foreach ($i['sub_bom']['items'] as $subItem):
                        $subItemCost = (float)$subItem['qty'] * (float)$subItem['rate'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($subItem['part_no']) ?></td>
                        <td><?= htmlspecialchars($subItem['part_name']) ?></td>
                        <td><?= htmlspecialchars($subItem['category'] ?? '') ?></td>
                        <td><?= $subItem['qty'] ?> <?= htmlspecialchars($subItem['uom'] ?? '') ?></td>
                        <td style="text-align: right;">₹ <?= number_format((float)$subItem['rate'], 2) ?></td>
                        <td style="text-align: right; font-weight: bold;">₹ <?= number_format($subItemCost, 2) ?></td>
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
        const rate = parseFloat(item.rate) || 0;
        const qty = parseFloat(item.qty) || 0;
        const cost = qty * rate;

        // Add main item
        tableData.push([
            item.part_no,
            item.part_name,
            item.category || '',
            qty + ' ' + (item.uom || ''),
            rate.toFixed(2),
            cost.toFixed(2),
            item.current_stock
        ]);

        // Add sub-BOM items if present
        if (item.sub_bom && item.sub_bom.items) {
            tableData.push(['', '--- Sub-BOM: ' + item.sub_bom.bom_no + ' (Cost: ₹' + parseFloat(item.sub_bom.total_cost).toFixed(2) + ') ---', '', '', '', '', '']);
            item.sub_bom.items.forEach(subItem => {
                const subRate = parseFloat(subItem.rate) || 0;
                const subQty = parseFloat(subItem.qty) || 0;
                const subCost = subQty * subRate;
                tableData.push([
                    '    ' + subItem.part_no,
                    '    ' + subItem.part_name,
                    subItem.category || '',
                    subQty + ' ' + (subItem.uom || ''),
                    subRate.toFixed(2),
                    subCost.toFixed(2),
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

</body>
</html>
