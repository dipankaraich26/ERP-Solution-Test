<?php
require '../db.php';
require '../includes/procurement_helper.php';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

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
        pp.total_parts,
        pp.total_items_to_order,
        pp.total_estimated_cost,
        COUNT(ppi.id) AS item_count,
        SUM(CASE WHEN ppi.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN ppi.status = 'ordered' THEN 1 ELSE 0 END) AS ordered_count,
        SUM(CASE WHEN ppi.status = 'received' THEN 1 ELSE 0 END) AS received_count
    FROM procurement_plans pp
    LEFT JOIN procurement_plan_items ppi ON pp.id = ppi.plan_id
    GROUP BY pp.id, pp.plan_no, pp.plan_date, pp.status, pp.total_parts,
             pp.total_items_to_order, pp.total_estimated_cost
    ORDER BY pp.plan_date DESC, pp.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
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
                                $total = ($plan['pending_count'] ?? 0) + ($plan['ordered_count'] ?? 0) + ($plan['received_count'] ?? 0);
                                $ordered = ($plan['ordered_count'] ?? 0) + ($plan['received_count'] ?? 0);
                                $percentage = $total > 0 ? round(($ordered / $total) * 100) : 0;
                                ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="background: #e5e7eb; border-radius: 4px; width: 60px; height: 20px; position: relative; overflow: hidden;">
                                        <div style="background: #10b981; height: 100%; width: <?= $percentage ?>%;"></div>
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
