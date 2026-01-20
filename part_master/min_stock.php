<?php
require '../db.php';
require '../includes/procurement_helper.php';

$part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';
$success = false;
$error = '';

// Fetch part details
if ($part_no) {
    $partStmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no = ?");
    $partStmt->execute([$part_no]);
    $part = $partStmt->fetch(PDO::FETCH_ASSOC);

    if (!$part) {
        $error = "Part not found";
    }
} else {
    $error = "No part selected";
}

// Fetch current min stock config
$minStockConfig = null;
if ($part_no) {
    $configStmt = $pdo->prepare("SELECT * FROM part_min_stock WHERE part_no = ?");
    $configStmt->execute([$part_no]);
    $minStockConfig = $configStmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch current inventory level
$currentStock = 0;
if ($part_no) {
    $invStmt = $pdo->prepare("SELECT qty FROM inventory WHERE part_no = ?");
    $invStmt->execute([$part_no]);
    $inv = $invStmt->fetch();
    $currentStock = $inv ? (int)$inv['qty'] : 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_stock_qty = (int)($_POST['min_stock_qty'] ?? 0);
    $reorder_qty = (int)($_POST['reorder_qty'] ?? 0);

    if ($min_stock_qty < 0 || $reorder_qty < 0) {
        $error = "Quantities must be non-negative";
    } else {
        if (updatePartMinStock($pdo, $part_no, $min_stock_qty, $reorder_qty)) {
            $success = "Minimum stock configuration updated successfully";
            // Refresh config
            $configStmt = $pdo->prepare("SELECT * FROM part_min_stock WHERE part_no = ?");
            $configStmt->execute([$part_no]);
            $minStockConfig = $configStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update configuration";
        }
    }
}

// Get view type for table
$view = isset($_GET['view']) ? $_GET['view'] : 'all';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Minimum Stock - <?= htmlspecialchars($part_no) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <h2>Minimum Stock Configuration</h2>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($part): ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">

        <!-- Part Info Card -->
        <div class="form-section">
            <h3>Part Information</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <label style="color: #666; font-size: 0.9em;">Part No</label>
                    <p style="margin: 0; font-weight: bold; font-size: 1.1em;">
                        <?= htmlspecialchars($part['part_no']) ?>
                    </p>
                </div>
                <div>
                    <label style="color: #666; font-size: 0.9em;">Part Name</label>
                    <p style="margin: 0; font-weight: bold;">
                        <?= htmlspecialchars($part['part_name']) ?>
                    </p>
                </div>
                <div>
                    <label style="color: #666; font-size: 0.9em;">UOM</label>
                    <p style="margin: 0;">
                        <?= htmlspecialchars($part['uom']) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stock Status Card -->
        <div class="form-section">
            <h3>Current Stock Status</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <label style="color: #666; font-size: 0.9em;">Current Inventory Level</label>
                    <p style="margin: 0; font-weight: bold; font-size: 1.5em; color: #2563eb;">
                        <?= $currentStock ?> <?= htmlspecialchars($part['uom']) ?>
                    </p>
                </div>
                <div>
                    <label style="color: #666; font-size: 0.9em;">Minimum Threshold</label>
                    <p style="margin: 0; font-weight: bold; font-size: 1.2em;">
                        <?= $minStockConfig ? $minStockConfig['min_stock_qty'] : 0 ?> <?= htmlspecialchars($part['uom']) ?>
                    </p>
                </div>
                <div>
                    <label style="color: #666; font-size: 0.9em;">Status</label>
                    <?php
                        $threshold = $minStockConfig ? (int)$minStockConfig['min_stock_qty'] : 0;
                        $isLow = $currentStock < $threshold;
                    ?>
                    <p style="margin: 0; font-weight: bold; color: <?= $isLow ? '#dc2626' : '#16a34a' ?>;">
                        <?= $isLow ? '⚠️ Below Minimum' : '✓ Adequate' ?>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Configuration Form -->
    <div class="form-section">
        <h3>Update Minimum Stock Settings</h3>

        <form method="post" class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 600px;">

            <div>
                <label for="min_stock_qty">Minimum Stock Quantity *</label>
                <input
                    type="number"
                    id="min_stock_qty"
                    name="min_stock_qty"
                    min="0"
                    value="<?= $minStockConfig ? $minStockConfig['min_stock_qty'] : 0 ?>"
                    required
                    placeholder="Reorder when stock falls below this level"
                >
                <small style="color: #666;">When inventory drops below this, the part will be flagged for ordering in procurement plans</small>
            </div>

            <div>
                <label for="reorder_qty">Suggested Reorder Quantity *</label>
                <input
                    type="number"
                    id="reorder_qty"
                    name="reorder_qty"
                    min="0"
                    value="<?= $minStockConfig ? $minStockConfig['reorder_qty'] : 0 ?>"
                    required
                    placeholder="Typical quantity to order"
                >
                <small style="color: #666;">Default order quantity when restocking this part</small>
            </div>

            <div style="grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 10px;">
                <button type="submit" class="btn btn-primary">Save Configuration</button>
                <a href="list.php" class="btn btn-secondary">Back to Parts</a>
            </div>
        </form>
    </div>

    <!-- Info Section -->
    <div class="form-section" style="background: #f0f9ff; border-left: 4px solid #0284c7;">
        <h3>How This Works</h3>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li><strong>Minimum Stock Quantity:</strong> When current inventory falls below this level, the part will be included in procurement planning with high priority</li>
            <li><strong>Reorder Quantity:</strong> The system will recommend ordering at least this quantity to optimize procurement (e.g., due to MOQ)</li>
            <li><strong>Procurement Planning:</strong> When you generate a procurement plan, the system calculates:
                <ul>
                    <li>Shortage = max(0, Demand from Sales Orders - Current Stock)</li>
                    <li>Order Quantity = max(Shortage, Minimum Stock Gap)</li>
                </ul>
            </li>
        </ul>
    </div>

    <!-- All Parts Min Stock -->
    <div class="form-section" style="margin-top: 30px;">
        <h3>All Parts - Minimum Stock Overview</h3>
        <p style="margin-bottom: 15px; color: #666;">
            <small>Quick view of minimum stock settings for all parts.
            <a href="<?= ($view === 'all') ? 'min_stock.php?part_no='.urlencode($part_no).'&view=low' : 'min_stock.php?part_no='.urlencode($part_no).'&view=all' ?>" style="color: #0284c7;">
                <?= ($view === 'low') ? 'Show All Parts' : 'Show Only Low Stock' ?>
            </a></small>
        </p>

        <?php
        // Get all parts with their stock levels
        $query = "
            SELECT
                pm.part_no,
                pm.part_name,
                pm.uom,
                COALESCE(i.qty, 0) AS current_stock,
                COALESCE(pms.min_stock_qty, 0) AS min_stock_qty,
                COALESCE(pms.reorder_qty, 0) AS reorder_qty,
                CASE
                    WHEN COALESCE(i.qty, 0) < COALESCE(pms.min_stock_qty, 0) THEN 'low'
                    ELSE 'ok'
                END AS status
            FROM part_master pm
            LEFT JOIN inventory i ON pm.part_no = i.part_no
            LEFT JOIN part_min_stock pms ON pm.part_no = pms.part_no
            WHERE pm.status = 'active'
        ";

        if ($view === 'low') {
            $query .= " HAVING status = 'low'";
        }

        $query .= " ORDER BY pm.part_name LIMIT 20";

        $allPartsStmt = $pdo->query($query);
        $allParts = $allPartsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Reorder Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allParts)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666; padding: 20px;">
                                <?= ($view === 'low') ? 'No parts with low stock' : 'No parts found' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allParts as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['part_no']) ?></td>
                                <td><?= htmlspecialchars($p['part_name']) ?></td>
                                <td><?= $p['current_stock'] ?> <?= htmlspecialchars($p['uom']) ?></td>
                                <td><?= $p['min_stock_qty'] ?> <?= htmlspecialchars($p['uom']) ?></td>
                                <td><?= $p['reorder_qty'] ?> <?= htmlspecialchars($p['uom']) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'low'): ?>
                                        <span style="color: #dc2626; font-weight: bold;">⚠️ Low Stock</span>
                                    <?php else: ?>
                                        <span style="color: #16a34a;">✓ Ok</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
        <p><?= htmlspecialchars($error) ?></p>
        <a href="list.php" class="btn btn-secondary">Back to Parts</a>
    <?php endif; ?>

</div>

</body>
</html>
