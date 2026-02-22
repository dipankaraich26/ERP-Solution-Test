<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();
require '../includes/procurement_helper.php';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Auto-close plans where all SOs are released
try {
    $activePlans = $pdo->query("SELECT pp.id FROM procurement_plans pp WHERE pp.status NOT IN ('completed', 'cancelled') AND (pp.plan_type = 'procurement' OR pp.plan_type IS NULL OR pp.plan_type = 'wo_planning') AND EXISTS (SELECT 1 FROM procurement_plan_wo_items pwi WHERE pwi.plan_id = pp.id)")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($activePlans as $apId) {
        autoClosePlanIfAllSOsReleased($pdo, (int)$apId);
    }
} catch (Exception $e) {}

// Get total count (only WO planning plans)
$total_count = $pdo->query("SELECT COUNT(*) FROM procurement_plans pp WHERE (pp.plan_type = 'procurement' OR pp.plan_type IS NULL OR pp.plan_type = 'wo_planning') AND EXISTS (SELECT 1 FROM procurement_plan_wo_items pwi WHERE pwi.plan_id = pp.id)")->fetchColumn();
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
        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id) AS wo_total,
        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id AND status IN ('completed', 'closed')) AS wo_done
    FROM procurement_plans pp
    WHERE (pp.plan_type = 'procurement' OR pp.plan_type IS NULL OR pp.plan_type = 'wo_planning')
    AND EXISTS (SELECT 1 FROM procurement_plan_wo_items pwi WHERE pwi.plan_id = pp.id)
    ORDER BY pp.plan_date DESC, pp.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Collect SO numbers per plan
$planSoNos = [];
$planIds = array_column($plans, 'id');

foreach ($plans as $plan) {
    $pid = $plan['id'];
    $planSoNos[$pid] = [];
    if (!empty($plan['so_list'])) {
        foreach (array_map('trim', explode(',', $plan['so_list'])) as $soNo) {
            if ($soNo !== '' && !in_array($soNo, $planSoNos[$pid])) {
                $planSoNos[$pid][] = $soNo;
            }
        }
    }
}

// Also gather from WO items so_list
if (!empty($planIds)) {
    $phIds = implode(',', array_fill(0, count($planIds), '?'));
    try {
        $tblStmt = $pdo->prepare("SELECT plan_id, so_list FROM procurement_plan_wo_items WHERE plan_id IN ($phIds) AND so_list IS NOT NULL AND so_list != ''");
        $tblStmt->execute($planIds);
        foreach ($tblStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = $row['plan_id'];
            foreach (array_map('trim', explode(',', $row['so_list'])) as $soNo) {
                if ($soNo !== '' && !in_array($soNo, $planSoNos[$pid])) {
                    $planSoNos[$pid][] = $soNo;
                }
            }
        }
    } catch (Exception $e) {}
}

// Collect all unique SO numbers and fetch details
$allSoNos = [];
foreach ($planSoNos as $soList) {
    $allSoNos = array_merge($allSoNos, $soList);
}
$allSoNos = array_values(array_unique($allSoNos));

$soCustomers = [];
$soProducts = [];
if (!empty($allSoNos)) {
    $phSo = implode(',', array_fill(0, count($allSoNos), '?'));
    $soStmt = $pdo->prepare("
        SELECT so.so_no, p.part_name, c.company_name AS customer_name
        FROM sales_orders so
        LEFT JOIN part_master p ON so.part_no = p.part_no
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.so_no IN ($phSo)
    ");
    $soStmt->execute($allSoNos);
    foreach ($soStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $soCustomers[$row['so_no']] = $row['customer_name'];
        if (!empty($row['part_name']) && !in_array($row['part_name'], $soProducts[$row['so_no']] ?? [])) {
            $soProducts[$row['so_no']][] = $row['part_name'];
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Work Order Planning</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Work Order Planning</h2>
        <a href="/procurement/create.php" class="btn btn-primary">+ Create New Plan (PPP)</a>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <?php
        $stats = $pdo->query("
            SELECT
                COUNT(CASE WHEN status = 'draft' THEN 1 END) AS draft_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
                COUNT(CASE WHEN status = 'partiallyordered' THEN 1 END) AS ordered_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count
            FROM procurement_plans pp
            WHERE (pp.plan_type = 'procurement' OR pp.plan_type IS NULL OR pp.plan_type = 'wo_planning')
            AND EXISTS (SELECT 1 FROM procurement_plan_wo_items pwi WHERE pwi.plan_id = pp.id)
        ")->fetch(PDO::FETCH_ASSOC);
        ?>

        <div style="padding: 15px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0284c7;">
            <div style="color: #666; font-size: 0.9em;">Draft Plans</div>
            <div style="font-size: 2em; font-weight: bold; color: #0284c7;"><?= $stats['draft_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <div style="color: #666; font-size: 0.9em;">Approved</div>
            <div style="font-size: 2em; font-weight: bold; color: #f59e0b;"><?= $stats['approved_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #dbeafe; border-radius: 8px; border-left: 4px solid #3b82f6;">
            <div style="color: #666; font-size: 0.9em;">In Progress</div>
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
                    <th>WO Items</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
                            No plans with WO items yet.
                            <a href="/procurement/create.php" style="color: #0284c7;">Create a plan in PPP</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($plan['plan_no']) ?></strong></td>
                            <td><?= date('Y-m-d', strtotime($plan['plan_date'])) ?></td>
                            <td>
                                <?php
                                $soNos = $planSoNos[$plan['id']] ?? [];
                                if (!empty($soNos)):
                                    foreach ($soNos as $soNo):
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
                                <?php
                                $yidProducts = [];
                                foreach ($soNos as $soNo) {
                                    foreach ($soProducts[$soNo] ?? [] as $pn) {
                                        if (!in_array($pn, $yidProducts)) $yidProducts[] = $pn;
                                    }
                                }
                                if (!empty($yidProducts)):
                                    foreach ($yidProducts as $pName): ?>
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
                                $statusLabels = [
                                    'draft' => 'Draft',
                                    'approved' => 'Approved',
                                    'partiallyordered' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled'
                                ];
                                ?>
                                <span style="display: inline-block; padding: 4px 8px; background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border-radius: 4px; font-weight: 500; font-size: 0.9em;">
                                    <?= $statusLabels[$plan['status']] ?? ucfirst($plan['status']) ?>
                                </span>
                            </td>
                            <td><?= $plan['wo_total'] ?? 0 ?></td>
                            <td>
                                <?php
                                $woTotal = (int)($plan['wo_total'] ?? 0);
                                $woDone = (int)($plan['wo_done'] ?? 0);
                                $percentage = $woTotal > 0 ? round(($woDone / $woTotal) * 100) : 0;
                                if ($plan['status'] === 'completed') $percentage = 100;
                                if ($plan['status'] === 'cancelled') $percentage = 0;
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
                                    <a href="/procurement/create.php?edit=<?= $plan['id'] ?>" class="btn btn-small btn-warning">Edit</a>
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
