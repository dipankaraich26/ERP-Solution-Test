<?php
require '../db.php';
require '../includes/procurement_helper.php';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Auto-close plans where all SOs are released
try {
    $activePlans = $pdo->query("SELECT id FROM procurement_plans WHERE status NOT IN ('completed', 'cancelled')")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($activePlans as $apId) {
        autoClosePlanIfAllSOsReleased($pdo, (int)$apId);
    }
} catch (Exception $e) {}

// Sync stock blocks for all active approved/partiallyordered plans
syncStockBlocksForActivePlans($pdo);

// Get total count
$total_count = $pdo->query("SELECT COUNT(*) FROM procurement_plans")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Fetch plans with summary
$stmt = $pdo->prepare("
    SELECT
        pp.id,
        pp.plan_no,
        pp.plan_date,
        pp.status,
        pp.so_list,
        pp.total_parts,
        pp.total_items_to_order,
        pp.total_estimated_cost,
        COUNT(ppi.id) AS item_count,
        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id) AS wo_total,
        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id AND status IN ('completed', 'closed')) AS wo_done,
        (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = pp.id) AS po_total,
        (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = pp.id AND status IN ('received', 'closed')) AS po_done
    FROM procurement_plans pp
    LEFT JOIN procurement_plan_items ppi ON pp.id = ppi.plan_id
    GROUP BY pp.id, pp.plan_no, pp.plan_date, pp.status, pp.so_list, pp.total_parts,
             pp.total_items_to_order, pp.total_estimated_cost
    ORDER BY pp.plan_date DESC, pp.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get SO numbers and product names per plan from WO items + PO items
$planSoData = []; // plan_id => ['so_numbers' => [...], 'products' => [...]]
$planIds = array_column($plans, 'id');
if (!empty($planIds)) {
    $phIds = implode(',', array_fill(0, count($planIds), '?'));

    // Get from WO items (has so_list and part_name per item)
    $woStmt = $pdo->prepare("SELECT plan_id, part_name, so_list FROM procurement_plan_wo_items WHERE plan_id IN ($phIds)");
    $woStmt->execute($planIds);
    foreach ($woStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = $row['plan_id'];
        if (!isset($planSoData[$pid])) $planSoData[$pid] = ['so_numbers' => [], 'products' => []];
        if (!empty($row['part_name']) && !in_array($row['part_name'], $planSoData[$pid]['products'])) {
            $planSoData[$pid]['products'][] = $row['part_name'];
        }
        if (!empty($row['so_list'])) {
            foreach (array_map('trim', explode(',', $row['so_list'])) as $soNo) {
                if ($soNo !== '' && !in_array($soNo, $planSoData[$pid]['so_numbers'])) {
                    $planSoData[$pid]['so_numbers'][] = $soNo;
                }
            }
        }
    }

    // Also get from PO items (in case some parts only have PO, not WO)
    $poStmt = $pdo->prepare("SELECT plan_id, part_name, so_list FROM procurement_plan_po_items WHERE plan_id IN ($phIds)");
    $poStmt->execute($planIds);
    foreach ($poStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = $row['plan_id'];
        if (!isset($planSoData[$pid])) $planSoData[$pid] = ['so_numbers' => [], 'products' => []];
        if (!empty($row['part_name']) && !in_array($row['part_name'], $planSoData[$pid]['products'])) {
            $planSoData[$pid]['products'][] = $row['part_name'];
        }
        if (!empty($row['so_list'])) {
            foreach (array_map('trim', explode(',', $row['so_list'])) as $soNo) {
                if ($soNo !== '' && !in_array($soNo, $planSoData[$pid]['so_numbers'])) {
                    $planSoData[$pid]['so_numbers'][] = $soNo;
                }
            }
        }
    }

    // Fallback: also check pp.so_list if WO/PO items didn't have data
    foreach ($plans as $plan) {
        $pid = $plan['id'];
        if ((!isset($planSoData[$pid]) || empty($planSoData[$pid]['so_numbers'])) && !empty($plan['so_list'])) {
            if (!isset($planSoData[$pid])) $planSoData[$pid] = ['so_numbers' => [], 'products' => []];
            foreach (array_map('trim', explode(',', $plan['so_list'])) as $soNo) {
                if ($soNo !== '' && !in_array($soNo, $planSoData[$pid]['so_numbers'])) {
                    $planSoData[$pid]['so_numbers'][] = $soNo;
                }
            }
        }
    }

    // Get customer names for all SO numbers
    $allSoNos = [];
    foreach ($planSoData as $data) {
        $allSoNos = array_merge($allSoNos, $data['so_numbers']);
    }
    $allSoNos = array_unique($allSoNos);

    $soCustomers = [];
    if (!empty($allSoNos)) {
        $phSo = implode(',', array_fill(0, count($allSoNos), '?'));
        $custStmt = $pdo->prepare("
            SELECT so.so_no, MAX(c.company_name) AS customer_name
            FROM sales_orders so
            LEFT JOIN customers c ON so.customer_id = c.id
            WHERE so.so_no IN ($phSo)
            GROUP BY so.so_no
        ");
        $custStmt->execute(array_values($allSoNos));
        foreach ($custStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $soCustomers[$row['so_no']] = $row['customer_name'];
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Procurement Planning</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Procurement Planning</h2>
        <a href="create.php" class="btn btn-primary">+ Create New Plan</a>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <?php
        // Get summary stats
        $stats = $pdo->query("
            SELECT
                COUNT(CASE WHEN status = 'draft' THEN 1 END) AS draft_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
                COUNT(CASE WHEN status = 'partiallyordered' THEN 1 END) AS ordered_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
                COALESCE(SUM(CASE WHEN status IN ('draft', 'approved') THEN total_estimated_cost ELSE 0 END), 0) AS pending_cost
            FROM procurement_plans
        ")->fetch(PDO::FETCH_ASSOC);
        ?>

        <div style="padding: 15px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0284c7;">
            <div style="color: #666; font-size: 0.9em;">Draft Plans</div>
            <div style="font-size: 2em; font-weight: bold; color: #0284c7;"><?= $stats['draft_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <div style="color: #666; font-size: 0.9em;">Pending Approval</div>
            <div style="font-size: 2em; font-weight: bold; color: #f59e0b;"><?= $stats['approved_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #dbeafe; border-radius: 8px; border-left: 4px solid #3b82f6;">
            <div style="color: #666; font-size: 0.9em;">Ordered</div>
            <div style="font-size: 2em; font-weight: bold; color: #3b82f6;"><?= $stats['ordered_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #dcfce7; border-radius: 8px; border-left: 4px solid #16a34a;">
            <div style="color: #666; font-size: 0.9em;">Completed</div>
            <div style="font-size: 2em; font-weight: bold; color: #16a34a;"><?= $stats['completed_count'] ?></div>
        </div>
    </div>

    <!-- Plans Table -->
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Plan No</th>
                    <th>Date</th>
                    <th>Sales Orders</th>
                    <th>Product</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Order Qty</th>
                    <th>Est. Cost</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 20px; color: #666;">
                            No procurement plans yet.
                            <a href="create.php" style="color: #0284c7;">Create one now</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($plan['plan_no']) ?></strong></td>
                            <td><?= date('Y-m-d', strtotime($plan['plan_date'])) ?></td>
                            <td>
                                <?php
                                $psd = $planSoData[$plan['id']] ?? null;
                                if ($psd && !empty($psd['so_numbers'])):
                                    foreach ($psd['so_numbers'] as $soNo):
                                        $custName = $soCustomers[$soNo] ?? '';
                                ?>
                                    <div style="margin-bottom: 3px;">
                                        <a href="/sales_orders/view.php?so_no=<?= urlencode($soNo) ?>" style="color: #2563eb; text-decoration: none; font-weight: 500; font-size: 0.9em;" title="<?= htmlspecialchars($custName) ?>">
                                            <?= htmlspecialchars($soNo) ?>
                                        </a>
                                        <?php if ($custName): ?>
                                            <div style="font-size: 0.75em; color: #888;"><?= htmlspecialchars($custName) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach;
                                else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($psd && !empty($psd['products'])):
                                    foreach ($psd['products'] as $pName): ?>
                                    <div style="font-size: 0.9em; margin-bottom: 2px;"><?= htmlspecialchars($pName) ?></div>
                                <?php endforeach;
                                else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'draft' => '#6366f1',
                                    'approved' => '#f59e0b',
                                    'partiallyordered' => '#3b82f6',
                                    'completed' => '#16a34a',
                                    'cancelled' => '#dc2626'
                                ];
                                $statusColor = $statusColors[$plan['status']] ?? '#6b7280';
                                ?>
                                <span style="display: inline-block; padding: 4px 8px; background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border-radius: 4px; font-weight: 500; font-size: 0.9em;">
                                    <?= ucfirst(str_replace('partially', 'Partially ', str_replace('_', ' ', $plan['status']))) ?>
                                </span>
                            </td>
                            <td><?= $plan['item_count'] ?? 0 ?></td>
                            <td><?= $plan['total_items_to_order'] ?? 0 ?> units</td>
                            <td>₹ <?= number_format($plan['total_estimated_cost'] ?? 0, 2) ?></td>
                            <td>
                                <?php
                                $progress = calculatePlanProgress($pdo, $plan['id'], $plan['status']);
                                $percentage = $progress['percentage'];
                                $barColor = $percentage >= 100 ? '#16a34a' : ($percentage > 0 ? '#f59e0b' : '#e5e7eb');
                                ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="background: #e5e7eb; border-radius: 4px; width: 60px; height: 20px; position: relative; overflow: hidden;">
                                        <div style="background: <?= $barColor ?>; height: 100%; width: <?= $percentage ?>%;"></div>
                                    </div>
                                    <span style="font-size: 0.9em; color: #666;"><?= $percentage ?>%</span>
                                </div>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $plan['id'] ?>" class="btn btn-small">View</a>
                                <?php if ($plan['status'] === 'draft'): ?>
                                    <a href="create.php?edit=<?= $plan['id'] ?>" class="btn btn-small btn-warning">Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
