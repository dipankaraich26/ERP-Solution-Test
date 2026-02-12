<?php
include "../db.php";
include "../includes/header.php";
include "../includes/sidebar.php";

// Filters
$supplierFilter = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$search = trim($_GET['search'] ?? '');
$qtyCheck = isset($_GET['qty_check']) && $_GET['qty_check'] !== '' ? (float)$_GET['qty_check'] : null;
$statusFilter = $_GET['po_status'] ?? '';

// Fetch all suppliers for dropdown
$suppliers = $pdo->query("SELECT id, supplier_name, supplier_code FROM suppliers ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query: supplier-wise PO shortage (ordered - received)
$where = ["po.status NOT IN ('closed','cancelled')"];
$params = [];

if ($supplierFilter > 0) {
    $where[] = "po.supplier_id = :supplier_id";
    $params[':supplier_id'] = $supplierFilter;
}
if ($search !== '') {
    $where[] = "(po.po_no LIKE :search OR pm.part_no LIKE :search OR pm.part_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter === 'open') {
    $where[] = "po.status = 'open'";
} elseif ($statusFilter === 'partial') {
    $where[] = "po.status = 'partial'";
} elseif ($statusFilter === 'pending') {
    $where[] = "po.status IN ('open','pending')";
}

$whereClause = implode(' AND ', $where);

// Main query: get all open PO lines with received qty
$sql = "
    SELECT
        po.id AS po_line_id,
        po.po_no,
        po.part_no,
        pm.part_name,
        po.qty AS ordered_qty,
        COALESCE(recv.total_received, 0) AS received_qty,
        (po.qty - COALESCE(recv.total_received, 0)) AS shortage_qty,
        po.status AS po_status,
        po.purchase_date,
        po.supplier_id,
        s.supplier_name,
        s.supplier_code,
        COALESCE(i.qty, 0) AS current_stock
    FROM purchase_orders po
    JOIN part_master pm ON po.part_no = pm.part_no
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN inventory i ON po.part_no = i.part_no
    LEFT JOIN (
        SELECT po_id, SUM(received_qty) AS total_received
        FROM stock_entries
        WHERE status = 'posted'
        GROUP BY po_id
    ) recv ON recv.po_id = po.id
    WHERE $whereClause
    HAVING shortage_qty > 0
    ORDER BY s.supplier_name, po.po_no, pm.part_name
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by supplier
$bySupplier = [];
$grandTotalOrdered = 0;
$grandTotalReceived = 0;
$grandTotalShortage = 0;

foreach ($rows as $r) {
    $sid = $r['supplier_id'] ?: 0;
    $sName = $r['supplier_name'] ?: 'Unknown Supplier';
    $sCode = $r['supplier_code'] ?? '';

    if (!isset($bySupplier[$sid])) {
        $bySupplier[$sid] = [
            'name' => $sName,
            'code' => $sCode,
            'items' => [],
            'total_ordered' => 0,
            'total_received' => 0,
            'total_shortage' => 0,
            'po_count' => 0
        ];
    }

    // Qty check: if user entered a qty, flag whether current stock covers it
    $r['qty_status'] = null;
    if ($qtyCheck !== null) {
        $r['qty_status'] = $r['current_stock'] >= $qtyCheck ? 'sufficient' : 'short';
        $r['qty_deficit'] = max(0, $qtyCheck - $r['current_stock']);
    }

    $bySupplier[$sid]['items'][] = $r;
    $bySupplier[$sid]['total_ordered'] += $r['ordered_qty'];
    $bySupplier[$sid]['total_received'] += $r['received_qty'];
    $bySupplier[$sid]['total_shortage'] += $r['shortage_qty'];
    $bySupplier[$sid]['po_count']++;

    $grandTotalOrdered += $r['ordered_qty'];
    $grandTotalReceived += $r['received_qty'];
    $grandTotalShortage += $r['shortage_qty'];
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
    .supplier-header:hover {
        background: #3a7ec5;
    }
    .supplier-header .badge {
        background: rgba(255,255,255,0.25);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
    }
    .supplier-body {
        overflow-x: auto;
    }
    .supplier-body table {
        width: 100%;
        border-collapse: collapse;
    }
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
    .supplier-body tr:hover {
        background: #f8fafc;
    }
    .supplier-footer {
        background: #f0f4f8;
        padding: 12px 20px;
        display: flex;
        gap: 30px;
        font-weight: 600;
        font-size: 0.9em;
        color: #333;
    }
    .shortage-bar {
        display: inline-block;
        height: 8px;
        border-radius: 4px;
        margin-left: 8px;
        vertical-align: middle;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 0.82em;
        font-weight: 600;
    }
    .qty-sufficient { background: #d1fae5; color: #065f46; }
    .qty-short { background: #fee2e2; color: #991b1b; }

    body.dark .shortage-filters,
    body.dark .summary-card,
    body.dark .supplier-section { background: #2c3e50; }
    body.dark .shortage-filters label { color: #bdc3c7; }
    body.dark .shortage-filters select,
    body.dark .shortage-filters input { background: #34495e; border-color: #4a6278; color: #ecf0f1; }
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
    <h1>Shortage Checking — Supplier Wise</h1>

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
            <label>PO Status</label>
            <select name="po_status">
                <option value="">All Open</option>
                <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Search (PO / Part)</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="PO no, part no or name...">
        </div>

        <div class="filter-group">
            <label>Qty to Check</label>
            <input type="number" step="0.001" name="qty_check" value="<?= $qtyCheck !== null ? htmlspecialchars($qtyCheck) : '' ?>" placeholder="Enter qty..." style="width: 130px;">
        </div>

        <div class="filter-group">
            <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Filter</button>
            <a href="shortage.php" class="btn btn-secondary" style="padding: 8px 14px;">Reset</a>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="value" style="color: #3b82f6;"><?= count($bySupplier) ?></div>
            <div class="label">Suppliers with Shortages</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #f59e0b;"><?= count($rows) ?></div>
            <div class="label">PO Lines Pending</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #10b981;"><?= number_format($grandTotalOrdered) ?></div>
            <div class="label">Total Ordered</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #6366f1;"><?= number_format($grandTotalReceived) ?></div>
            <div class="label">Total Received</div>
        </div>
        <div class="summary-card">
            <div class="value" style="color: #ef4444;"><?= number_format($grandTotalShortage) ?></div>
            <div class="label">Total Shortage</div>
        </div>
    </div>

    <?php if (empty($bySupplier)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <p style="font-size: 1.2em; color: #666;">No shortages found for the selected filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($bySupplier as $sid => $sup): ?>
        <div class="supplier-section">
            <div class="supplier-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? '' : 'none'; this.querySelector('.toggle-icon').textContent = this.nextElementSibling.style.display === 'none' ? '▶' : '▼';">
                <span>
                    <span class="toggle-icon">▼</span>
                    &nbsp;<?= htmlspecialchars($sup['name']) ?>
                    <?php if ($sup['code']): ?>
                        <span style="opacity: 0.8; font-size: 0.85em;">(<?= htmlspecialchars($sup['code']) ?>)</span>
                    <?php endif; ?>
                </span>
                <span>
                    <span class="badge"><?= $sup['po_count'] ?> line<?= $sup['po_count'] > 1 ? 's' : '' ?></span>
                    <span class="badge" style="background: rgba(239,68,68,0.3); margin-left: 6px;">Shortage: <?= number_format($sup['total_shortage']) ?></span>
                </span>
            </div>

            <div>
                <div class="supplier-body">
                    <table>
                        <thead>
                            <tr>
                                <th>PO No</th>
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Shortage</th>
                                <th>%</th>
                                <th>Current Stock</th>
                                <?php if ($qtyCheck !== null): ?>
                                    <th>Qty Check (<?= htmlspecialchars($qtyCheck) ?>)</th>
                                <?php endif; ?>
                                <th>PO Status</th>
                                <th>PO Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sup['items'] as $item):
                            $pct = $item['ordered_qty'] > 0 ? round(($item['received_qty'] / $item['ordered_qty']) * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['po_no']) ?></strong></td>
                                <td><?= htmlspecialchars($item['part_no']) ?></td>
                                <td><?= htmlspecialchars($item['part_name']) ?></td>
                                <td style="text-align:right;"><?= number_format($item['ordered_qty']) ?></td>
                                <td style="text-align:right;"><?= number_format($item['received_qty']) ?></td>
                                <td style="text-align:right; color: #dc3545; font-weight: 600;"><?= number_format($item['shortage_qty']) ?></td>
                                <td style="text-align:right;">
                                    <?= $pct ?>%
                                    <span class="shortage-bar" style="width: <?= $pct ?>px; background: <?= $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444') ?>;"></span>
                                </td>
                                <td style="text-align:right;"><?= number_format($item['current_stock']) ?></td>
                                <?php if ($qtyCheck !== null): ?>
                                    <td style="text-align:center;">
                                        <?php if ($item['qty_status'] === 'sufficient'): ?>
                                            <span class="status-badge qty-sufficient">Sufficient</span>
                                        <?php else: ?>
                                            <span class="status-badge qty-short">Short by <?= number_format($item['qty_deficit']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="status-badge" style="background: <?= $item['po_status'] === 'partial' ? '#fef3c7' : '#dbeafe' ?>; color: <?= $item['po_status'] === 'partial' ? '#92400e' : '#1e40af' ?>;">
                                        <?= ucfirst(htmlspecialchars($item['po_status'])) ?>
                                    </span>
                                </td>
                                <td><?= $item['purchase_date'] ? date('d M Y', strtotime($item['purchase_date'])) : '—' ?></td>
                                <td>
                                    <a href="receive_all.php?po_no=<?= urlencode($item['po_no']) ?>" class="btn btn-success" style="padding: 4px 12px; font-size: 0.85em;">Receive</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="supplier-footer">
                    <span>Ordered: <span style="color: #3b82f6;"><?= number_format($sup['total_ordered']) ?></span></span>
                    <span>Received: <span style="color: #10b981;"><?= number_format($sup['total_received']) ?></span></span>
                    <span>Shortage: <span style="color: #ef4444;"><?= number_format($sup['total_shortage']) ?></span></span>
                    <span>Fulfillment: <span style="color: #6366f1;"><?= $sup['total_ordered'] > 0 ? round(($sup['total_received'] / $sup['total_ordered']) * 100) : 0 ?>%</span></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
