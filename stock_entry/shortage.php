<?php
include "../db.php";
include "../includes/header.php";
include "../includes/sidebar.php";

// Filters
$supplierFilter = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$search = trim($_GET['search'] ?? '');
$stockQty = isset($_GET['stock_qty']) && $_GET['stock_qty'] !== '' ? (int)$_GET['stock_qty'] : null;

// Fetch all suppliers for dropdown
$suppliers = $pdo->query("SELECT id, supplier_name, supplier_code FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query: all parts mapped to suppliers, with current stock and on-order qty
$where = ["pm.status = 'active'", "psm.active = 1"];
$params = [];

if ($supplierFilter > 0) {
    $where[] = "psm.supplier_id = :supplier_id";
    $params[':supplier_id'] = $supplierFilter;
}
if ($search !== '') {
    $where[] = "(pm.part_no LIKE :search OR pm.part_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Stock qty filter: filter by exact current stock level
if ($stockQty !== null) {
    $where[] = "COALESCE(i.qty, 0) = :stock_qty";
    $params[':stock_qty'] = $stockQty;
}

$whereClause = implode(' AND ', $where);

$sql = "
    SELECT
        pm.part_no,
        pm.part_name,
        pm.uom,
        COALESCE(i.qty, 0) AS current_stock,
        psm.supplier_id,
        s.supplier_name,
        s.supplier_code,
        COALESCE(on_order.ordered_qty, 0) AS ordered_qty
    FROM part_supplier_mapping psm
    JOIN part_master pm ON psm.part_no = pm.part_no
    LEFT JOIN suppliers s ON psm.supplier_id = s.id
    LEFT JOIN inventory i ON pm.part_no = i.part_no
    LEFT JOIN (
        SELECT po.part_no, po.supplier_id,
               SUM(po.qty) - COALESCE(SUM(recv.total_received), 0) AS ordered_qty
        FROM purchase_orders po
        LEFT JOIN (
            SELECT po_id, SUM(received_qty) AS total_received
            FROM stock_entries
            WHERE status = 'posted'
            GROUP BY po_id
        ) recv ON recv.po_id = po.id
        WHERE po.status NOT IN ('closed','cancelled')
        GROUP BY po.part_no, po.supplier_id
    ) on_order ON on_order.part_no = pm.part_no AND on_order.supplier_id = psm.supplier_id
    WHERE $whereClause
    ORDER BY s.supplier_name, pm.part_name
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by supplier
$bySupplier = [];
$totalParts = 0;
$totalZeroStock = 0;
$totalWithOrders = 0;

foreach ($rows as $r) {
    $sid = $r['supplier_id'] ?: 0;
    $sName = $r['supplier_name'] ?: 'Unknown Supplier';
    $sCode = $r['supplier_code'] ?? '';

    if (!isset($bySupplier[$sid])) {
        $bySupplier[$sid] = [
            'name' => $sName,
            'code' => $sCode,
            'items' => [],
            'part_count' => 0,
            'zero_count' => 0,
            'with_orders' => 0
        ];
    }

    $bySupplier[$sid]['items'][] = $r;
    $bySupplier[$sid]['part_count']++;
    if ($r['current_stock'] == 0) $bySupplier[$sid]['zero_count']++;
    if ($r['ordered_qty'] > 0) $bySupplier[$sid]['with_orders']++;

    $totalParts++;
    if ($r['current_stock'] == 0) $totalZeroStock++;
    if ($r['ordered_qty'] > 0) $totalWithOrders++;
}
?>

<style>
    .shortage-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
        margin-bottom: 20px;
        background: #fff;
        padding: 18px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .shortage-filters .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .shortage-filters label {
        font-size: 0.82em;
        font-weight: 600;
        color: #555;
    }
    .shortage-filters select,
    .shortage-filters input {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.95em;
    }
    .qty-btns { display: flex; gap: 6px; margin-top: 2px; }
    .qty-btns a {
        padding: 5px 14px;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #ccc;
        color: #333;
        background: #f8f9fa;
    }
    .qty-btns a.active, .qty-btns a:hover {
        background: #4a90d9;
        color: #fff;
        border-color: #4a90d9;
    }
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .summary-card {
        background: #fff;
        border-radius: 8px;
        padding: 18px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .summary-card .value {
        font-size: 1.8em;
        font-weight: 700;
    }
    .summary-card .label {
        font-size: 0.85em;
        color: #666;
        margin-top: 4px;
    }
    .supplier-section {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .supplier-header {
        background: #4a90d9;
        color: #fff;
        padding: 14px 20px;
        font-size: 1.1em;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    .supplier-header:hover { background: #3a7ec5; }
    .supplier-header .badge {
        background: rgba(255,255,255,0.25);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
    }
    .supplier-body { overflow-x: auto; }
    .supplier-body table { width: 100%; border-collapse: collapse; }
    .supplier-body th {
        background: #f0f4f8;
        padding: 10px 14px;
        text-align: left;
        font-size: 0.85em;
        color: #555;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .supplier-body td {
        padding: 10px 14px;
        border-bottom: 1px solid #eee;
        font-size: 0.92em;
    }
    .supplier-body tr:hover { background: #f8fafc; }
    .supplier-footer {
        background: #f0f4f8;
        padding: 12px 20px;
        display: flex;
        gap: 30px;
        font-weight: 600;
        font-size: 0.9em;
        color: #333;
    }
    .stock-zero { color: #dc3545; font-weight: 700; }
    .stock-low { color: #f59e0b; font-weight: 600; }
    .stock-ok { color: #10b981; }
    .order-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.85em;
        font-weight: 600;
        background: #dbeafe;
        color: #1e40af;
    }

    body.dark .shortage-filters,
    body.dark .summary-card,
    body.dark .supplier-section { background: #2c3e50; }
    body.dark .shortage-filters label { color: #bdc3c7; }
    body.dark .shortage-filters select,
    body.dark .shortage-filters input { background: #34495e; border-color: #4a6278; color: #ecf0f1; }
    body.dark .qty-btns a { background: #34495e; border-color: #4a6278; color: #bdc3c7; }
    body.dark .qty-btns a.active, body.dark .qty-btns a:hover { background: #2980b9; color: #fff; border-color: #2980b9; }
    body.dark .supplier-header { background: #2980b9; }
    body.dark .supplier-body th { background: #34495e; color: #bdc3c7; border-color: #4a6278; }
    body.dark .supplier-body td { border-color: #4a6278; color: #ecf0f1; }
    body.dark .supplier-body tr:hover { background: #34495e; }
    body.dark .supplier-footer { background: #34495e; color: #ecf0f1; }
    body.dark .summary-card .label { color: #95a5a6; }
</style>

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

<div class="content" style="overflow-x: auto;">
    <h1>Shortage Check — Supplier Wise</h1>

    <!-- Filters -->
    <form method="get" class="shortage-filters">
        <div class="filter-group">
            <label>Supplier</label>
            <select name="supplier_id">
                <option value="0">All Suppliers</option>
                <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['id'] ?>" <?= $supplierFilter == $sup['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sup['supplier_name']) ?> (<?= htmlspecialchars($sup['supplier_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Search (Part)</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Part no or name...">
        </div>

        <div class="filter-group">
            <label>Stock Qty</label>
            <input type="number" name="stock_qty" min="0" value="<?= $stockQty !== null ? (int)$stockQty : '' ?>" placeholder="Any" style="width: 100px;">
        </div>

        <div class="filter-group">
            <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filter</button>
            <a href="shortage.php" class="btn btn-secondary" style="padding: 8px 14px;">Reset</a>
        </div>
    </form>

    <!-- Quick qty filter buttons -->
    <div class="qty-btns" style="margin-bottom: 18px;">
        <a href="?supplier_id=<?= $supplierFilter ?>&search=<?= urlencode($search) ?>" class="<?= $stockQty === null ? 'active' : '' ?>">All</a>
        <a href="?supplier_id=<?= $supplierFilter ?>&search=<?= urlencode($search) ?>&stock_qty=0" class="<?= $stockQty === 0 ? 'active' : '' ?>">Zero Stock</a>
        <a href="?supplier_id=<?= $supplierFilter ?>&search=<?= urlencode($search) ?>&stock_qty=1" class="<?= $stockQty === 1 ? 'active' : '' ?>">Stock = 1</a>
        <a href="?supplier_id=<?= $supplierFilter ?>&search=<?= urlencode($search) ?>&stock_qty=2" class="<?= $stockQty === 2 ? 'active' : '' ?>">Stock = 2</a>
        <a href="?supplier_id=<?= $supplierFilter ?>&search=<?= urlencode($search) ?>&stock_qty=5" class="<?= $stockQty === 5 ? 'active' : '' ?>">Stock = 5</a>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="value" style="color: #3b82f6;"><?= count($bySupplier) ?></div>
            <div class="label">Suppliers</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #f59e0b;"><?= $totalParts ?></div>
            <div class="label">Total Parts</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #ef4444;"><?= $totalZeroStock ?></div>
            <div class="label">Zero Stock Parts</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #10b981;"><?= $totalWithOrders ?></div>
            <div class="label">With Open Orders</div>
        </div>
    </div>

    <?php if (empty($bySupplier)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <p style="font-size: 1.2em; color: #666;">No parts found for the selected filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($bySupplier as $sid => $sup): ?>
        <div class="supplier-section">
            <div class="supplier-header" onclick="var b=this.nextElementSibling; b.style.display=b.style.display==='none'?'':'none'; this.querySelector('.ti').textContent=b.style.display==='none'?'▶':'▼';">
                <span>
                    <span class="ti">▼</span>
                    &nbsp;<?= htmlspecialchars($sup['name']) ?>
                    <?php if ($sup['code']): ?>
                        <span style="opacity: 0.8; font-size: 0.85em;">(<?= htmlspecialchars($sup['code']) ?>)</span>
                    <?php endif; ?>
                </span>
                <span>
                    <span class="badge"><?= $sup['part_count'] ?> part<?= $sup['part_count'] > 1 ? 's' : '' ?></span>
                    <?php if ($sup['zero_count'] > 0): ?>
                        <span class="badge" style="background: rgba(239,68,68,0.3); margin-left: 6px;">Zero: <?= $sup['zero_count'] ?></span>
                    <?php endif; ?>
                    <?php if ($sup['with_orders'] > 0): ?>
                        <span class="badge" style="background: rgba(16,185,129,0.3); margin-left: 6px;">On Order: <?= $sup['with_orders'] ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div>
                <div class="supplier-body">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>UOM</th>
                                <th>Current Stock</th>
                                <th>Ordered (On Order)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $idx = 0; foreach ($sup['items'] as $item): $idx++; ?>
                            <tr>
                                <td><?= $idx ?></td>
                                <td><strong><?= htmlspecialchars($item['part_no']) ?></strong></td>
                                <td><?= htmlspecialchars($item['part_name']) ?></td>
                                <td><?= htmlspecialchars($item['uom'] ?? 'Nos') ?></td>
                                <td style="text-align:right;">
                                    <span class="<?= $item['current_stock'] == 0 ? 'stock-zero' : ($item['current_stock'] <= 5 ? 'stock-low' : 'stock-ok') ?>">
                                        <?= number_format($item['current_stock']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($item['ordered_qty'] > 0): ?>
                                        <span class="order-badge"><?= number_format($item['ordered_qty']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="supplier-footer">
                    <span>Parts: <strong><?= $sup['part_count'] ?></strong></span>
                    <span>Zero Stock: <span style="color: #ef4444;"><?= $sup['zero_count'] ?></span></span>
                    <span>With Orders: <span style="color: #10b981;"><?= $sup['with_orders'] ?></span></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
