<?php
// Start session first before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../db.php';
require '../includes/auth.php';
requireLogin();
require '../includes/procurement_helper.php';

$planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$planId) {
    $error = "Plan ID is required";
}

$planDetails = null;

if ($planId) {
    // Auto-close plan if all linked SOs are released
    autoClosePlanIfAllSOsReleased($pdo, $planId);

    $planDetails = getProcurementPlanDetails($pdo, $planId);
    if (!$planDetails) {
        $error = "Plan not found";
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        if ($planDetails['status'] === 'draft') {
            if (approveProcurementPlan($pdo, $planId, 1)) {
                $success = "WO Plan approved successfully";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = "Failed to approve plan";
            }
        } else {
            $error = "Only draft plans can be approved";
        }
    }

    if ($action === 'refresh_bom') {
        if (!in_array($planDetails['status'], ['completed', 'cancelled'])) {
            $result = refreshPlanFromBOM($pdo, $planId);
            if ($result['success']) {
                $success = $result['message'];
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Cannot refresh BOM for a " . $planDetails['status'] . " plan";
        }
    }

    if ($action === 'cancel') {
        if (in_array($planDetails['status'], ['draft', 'approved', 'partiallyordered'])) {
            if (cancelProcurementPlan($pdo, $planId)) {
                $success = "WO Plan cancelled successfully.";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = "Failed to cancel plan";
            }
        } else {
            $error = "Only draft, approved, or in-progress plans can be cancelled";
        }
    }

    if ($action === 'create_wo') {
        $woPartNo = $_POST['wo_part_no'] ?? '';
        $woQty = (float)($_POST['wo_qty'] ?? 0);
        if ($woPartNo && $woQty > 0 && $planId && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            $woResult = createWorkOrderWithTracking($pdo, $planId, $woPartNo, $woQty);
            if ($woResult['success']) {
                $success = $woResult['message'];
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = $woResult['error'] ?? 'Failed to create Work Order';
            }
        } else {
            $error = "Invalid Work Order data or plan not in correct status";
        }
    }

    if ($action === 'create_all_wo_for_so') {
        $targetSO = $_POST['target_so'] ?? '';
        if ($targetSO && $planId && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            $freshWoItems = getPlanWorkOrderItems($pdo, $planId);
            foreach ($freshWoItems as &$fwi) {
                try {
                    $fwi['current_stock'] = (int)getAvailableStock($pdo, $fwi['part_no'], $planId);
                    $fwi['shortage'] = max(0, $fwi['required_qty'] - $fwi['current_stock']);
                } catch (Exception $e) {}
            }
            unset($fwi);
            $woResult = createAllWorkOrdersForSO($pdo, $planId, $targetSO, $freshWoItems);
            if ($woResult['success']) {
                $success = $woResult['message'];
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = $woResult['error'] ?? 'Failed to create Work Orders';
            }
        } else {
            $error = "Invalid SO or plan not in correct status";
        }
    }
}

// Load WO items with real-time stock
$woItems = [];
$woItemsBySO = [];
$planIsCompleted = false;
$totalWoParts = 0;
$inStockOrDoneParts = 0;

if ($planDetails) {
    $woItems = getPlanWorkOrderItems($pdo, $planId);
    $planIsCompleted = ($planDetails['status'] === 'completed');

    // Clear stale links if plan is NOT completed
    if (!$planIsCompleted) {
        foreach ($woItems as &$woItem) {
            if (!empty($woItem['created_wo_id'])) {
                try {
                    $chkStmt = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
                    $chkStmt->execute([$woItem['created_wo_id']]);
                    $woRealStatus = $chkStmt->fetchColumn();
                    if ($woRealStatus && in_array($woRealStatus, ['closed', 'cancelled'])) {
                        $pdo->prepare("UPDATE procurement_plan_wo_items SET created_wo_id = NULL, created_wo_no = NULL, status = 'pending' WHERE plan_id = ? AND part_no = ?")
                             ->execute([$planId, $woItem['part_no']]);
                        $woItem['created_wo_id'] = null;
                        $woItem['created_wo_no'] = null;
                        $woItem['status'] = 'pending';
                    }
                } catch (Exception $e) {}
            }
        }
        unset($woItem);
    }

    // Refresh real-time stock for WO items
    foreach ($woItems as &$woItem) {
        try {
            $woItem['current_stock'] = (int)getAvailableStock($pdo, $woItem['part_no'], $planId);
            $woItem['shortage'] = max(0, $woItem['required_qty'] - $woItem['current_stock']);
        } catch (Exception $e) {}
        if (!empty($woItem['created_wo_id'])) {
            try {
                $woStatusStmt = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
                $woStatusStmt->execute([$woItem['created_wo_id']]);
                $actualWoStatus = $woStatusStmt->fetchColumn();
                if ($actualWoStatus) {
                    $woItem['actual_wo_status'] = $actualWoStatus;
                }
            } catch (Exception $e) {}
        }
    }
    unset($woItem);

    // Calculate progress
    $totalWoParts = count($woItems);
    foreach ($woItems as $wi) {
        if ($planIsCompleted) {
            $inStockOrDoneParts++;
        } elseif (!empty($wi['created_wo_id'])) {
            $woSt = $wi['actual_wo_status'] ?? '';
            if (in_array($woSt, ['completed', 'closed', 'qc_approval'])) {
                $inStockOrDoneParts++;
            }
        } elseif ($wi['shortage'] <= 0) {
            $inStockOrDoneParts++;
        }
    }

    // Group WO items by SO
    foreach ($woItems as $item) {
        $soList = $item['so_list'] ?? 'Unknown';
        $soNumbers = array_map('trim', explode(',', $soList));
        foreach ($soNumbers as $soNo) {
            if (!isset($woItemsBySO[$soNo])) {
                $woItemsBySO[$soNo] = [];
            }
            $woItemsBySO[$soNo][] = $item;
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>WO Plan - <?= htmlspecialchars($planDetails['plan_no'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($planDetails): ?>

    <!-- Navigation -->
    <div style="margin-bottom: 15px;">
        <a href="index.php" style="color: #059669; text-decoration: none; font-size: 0.9em;">&larr; Back to Work Order Planning</a>
        <span style="color: #ccc; margin: 0 8px;">|</span>
        <a href="/procurement/view.php?id=<?= $planId ?>" style="color: #9333ea; text-decoration: none; font-size: 0.9em;">View Full Plan in PPP &rarr;</a>
    </div>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0; margin-bottom: 5px;"><?= htmlspecialchars($planDetails['plan_no']) ?></h2>
            <p style="margin: 0; color: #666; font-size: 0.9em;">
                <?= date('Y-m-d H:i', strtotime($planDetails['plan_date'])) ?>
            </p>
        </div>
        <div>
            <?php
            $statusColors = [
                'draft' => ['bg' => '#6366f120', 'color' => '#6366f1', 'text' => 'Draft'],
                'approved' => ['bg' => '#f59e0b20', 'color' => '#f59e0b', 'text' => 'Approved'],
                'partiallyordered' => ['bg' => '#3b82f620', 'color' => '#3b82f6', 'text' => 'In Progress'],
                'completed' => ['bg' => '#16a34a20', 'color' => '#16a34a', 'text' => 'Completed'],
                'cancelled' => ['bg' => '#dc262620', 'color' => '#dc2626', 'text' => 'Cancelled']
            ];
            $status = $planDetails['status'];
            $sc = $statusColors[$status] ?? $statusColors['draft'];
            ?>
            <span style="display: inline-block; padding: 8px 16px; background: <?= $sc['bg'] ?>; color: <?= $sc['color'] ?>; border-radius: 6px; font-weight: 600; font-size: 1.1em;">
                <?= $sc['text'] ?>
            </span>
        </div>
    </div>

    <!-- SO Details & Products -->
    <?php
    $viewSoNos = [];
    if (!empty($planDetails['so_list'])) {
        $viewSoNos = array_filter(array_map('trim', explode(',', $planDetails['so_list'])));
    }
    // Also gather from WO items
    try {
        $soStmt = $pdo->prepare("SELECT DISTINCT so_list FROM procurement_plan_wo_items WHERE plan_id = ? AND so_list IS NOT NULL AND so_list != ''");
        $soStmt->execute([$planId]);
        foreach ($soStmt->fetchAll(PDO::FETCH_COLUMN) as $sl) {
            foreach (array_map('trim', explode(',', $sl)) as $s) {
                if ($s !== '' && !in_array($s, $viewSoNos)) $viewSoNos[] = $s;
            }
        }
    } catch (Exception $e) {}

    $viewSoDetails = [];
    if (!empty($viewSoNos)) {
        $ph = implode(',', array_fill(0, count($viewSoNos), '?'));
        $sdStmt = $pdo->prepare("
            SELECT so.so_no, so.qty, so.status AS so_status, p.part_no, p.part_name, c.company_name AS customer_name
            FROM sales_orders so
            LEFT JOIN part_master p ON so.part_no = p.part_no
            LEFT JOIN customers c ON so.customer_id = c.id
            WHERE so.so_no IN ($ph)
            ORDER BY so.so_no
        ");
        $sdStmt->execute($viewSoNos);
        $viewSoDetails = $sdStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>
    <?php if (!empty($viewSoDetails)): ?>
    <div style="background: #ecfdf5; border: 1px solid #86efac; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px;">
        <h4 style="margin: 0 0 12px 0; color: #059669; font-size: 0.95em;">Sales Orders & Products</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <?php foreach ($viewSoDetails as $sd): ?>
            <div style="background: white; border: 1px solid #d1fae5; border-radius: 6px; padding: 10px 14px; min-width: 220px; flex: 1; max-width: 350px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <a href="/sales_orders/view.php?so_no=<?= urlencode($sd['so_no']) ?>" style="color: #059669; text-decoration: none; font-weight: 600; font-size: 0.95em;">
                        <?= htmlspecialchars($sd['so_no']) ?>
                    </a>
                    <span style="font-size: 0.75em; padding: 2px 8px; border-radius: 10px; background: <?= $sd['so_status'] === 'released' ? '#dcfce7' : '#fef3c7' ?>; color: <?= $sd['so_status'] === 'released' ? '#16a34a' : '#d97706' ?>;">
                        <?= ucfirst($sd['so_status']) ?>
                    </span>
                </div>
                <?php if (!empty($sd['customer_name'])): ?>
                <div style="font-size: 0.8em; color: #666; margin-bottom: 4px;"><?= htmlspecialchars($sd['customer_name']) ?></div>
                <?php endif; ?>
                <div style="font-size: 0.85em; color: #047857; font-weight: 500;">
                    <?= htmlspecialchars($sd['part_no']) ?> — <?= htmlspecialchars($sd['part_name'] ?? '') ?>
                </div>
                <div style="font-size: 0.8em; color: #888; margin-top: 2px;">Qty: <?= $sd['qty'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">WO Items</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #059669;"><?= $totalWoParts ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Progress</div>
            <?php
            if ($planDetails['status'] === 'completed') {
                $percentage = 100;
            } elseif ($planDetails['status'] === 'cancelled') {
                $percentage = 0;
            } else {
                $percentage = $totalWoParts > 0 ? round(($inStockOrDoneParts / $totalWoParts) * 100) : 0;
            }
            $pColor = $percentage >= 100 ? '#16a34a' : ($percentage > 0 ? '#f59e0b' : '#6b7280');
            ?>
            <div style="font-size: 1.8em; font-weight: bold; color: <?= $pColor ?>;"><?= $percentage ?>%</div>
            <?php if ($totalWoParts > 0): ?>
            <div style="font-size: 0.75em; color: #666; margin-top: 2px;">
                <?= $inStockOrDoneParts ?>/<?= $totalWoParts ?> parts available
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <?php if ($planDetails['status'] === 'draft'): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Approve this WO plan?');">
                Approve Plan
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="refresh_bom">
            <button type="submit" class="btn" style="background: #f59e0b; color: white;" onclick="return confirm('Refresh WO items from latest BOM?');">
                Update from BOM
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this WO plan?');">
                Cancel Plan
            </button>
        </form>
    </div>

    <?php elseif (in_array($planDetails['status'], ['approved', 'partiallyordered'])): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="refresh_bom">
            <button type="submit" class="btn" style="background: #f59e0b; color: white;" onclick="return confirm('Refresh WO items from latest BOM?');">
                Update from BOM
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this WO plan?');">
                Cancel Plan
            </button>
        </form>
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
    </div>

    <?php else: ?>
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($woItems)): ?>
    <!-- Work Order Parts Section -->
    <div class="form-section" style="margin-top: 20px; border-top: 3px solid #10b981; padding-top: 20px;">
        <h3 style="color: #059669; margin-bottom: 15px;">
            <span style="background: #d1fae5; padding: 4px 12px; border-radius: 20px;">
                Work Order Parts (Internal Production)
            </span>
            <?php
            $woClosedCount = 0;
            $woCompletedCount = 0;
            $woInProgressCount = 0;
            $woPendingCount = 0;
            $woInStockCount = 0;
            if ($planIsCompleted) {
                $woClosedCount = count($woItems);
            } else {
                foreach ($woItems as $wi) {
                    $woStatus = $wi['actual_wo_status'] ?? '';
                    if ($wi['created_wo_id']) {
                        if (in_array($woStatus, ['closed'])) {
                            $woClosedCount++;
                        } elseif (in_array($woStatus, ['completed', 'qc_approval'])) {
                            $woCompletedCount++;
                        } else {
                            $woInProgressCount++;
                        }
                    } elseif ($wi['shortage'] <= 0) {
                        $woInStockCount++;
                    } else {
                        $woPendingCount++;
                    }
                }
            }
            ?>
            <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                <?php if ($planIsCompleted): ?>
                    <span style="color: #16a34a; font-weight: 600;">All <?= count($woItems) ?> Closed (SOs Released)</span>
                <?php else: ?>
                    <?php if ($woClosedCount): ?><span style="color: #6b7280;"><?= $woClosedCount ?> Closed</span> | <?php endif; ?>
                    <?php if ($woCompletedCount): ?><span style="color: #16a34a;"><?= $woCompletedCount ?> Completed</span> | <?php endif; ?>
                    <span style="color: #3b82f6;"><?= $woInProgressCount ?> In Progress</span> |
                    <span style="color: #10b981;"><?= $woInStockCount ?> In Stock</span> |
                    <span style="color: #f59e0b;"><?= $woPendingCount ?> Pending</span>
                <?php endif; ?>
            </span>
        </h3>

        <!-- WO Filter Buttons -->
        <?php if (!$planIsCompleted): ?>
        <div style="margin-bottom: 12px; display: flex; gap: 6px; flex-wrap: wrap;" id="wo-filters">
            <button type="button" class="filter-btn active" data-filter="all" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: #059669; color: white; cursor: pointer; font-size: 0.85em;">
                All (<?= count($woItems) ?>)
            </button>
            <?php if ($woClosedCount): ?>
            <button type="button" class="filter-btn" data-filter="closed" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #6b7280; cursor: pointer; font-size: 0.85em;">
                Closed (<?= $woClosedCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($woCompletedCount): ?>
            <button type="button" class="filter-btn" data-filter="completed" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #16a34a; cursor: pointer; font-size: 0.85em;">
                Completed (<?= $woCompletedCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($woInProgressCount): ?>
            <button type="button" class="filter-btn" data-filter="in_progress" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #3b82f6; cursor: pointer; font-size: 0.85em;">
                In Progress (<?= $woInProgressCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($woInStockCount): ?>
            <button type="button" class="filter-btn" data-filter="in_stock" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #10b981; cursor: pointer; font-size: 0.85em;">
                In Stock (<?= $woInStockCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($woPendingCount): ?>
            <button type="button" class="filter-btn" data-filter="pending" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #f59e0b; cursor: pointer; font-size: 0.85em;">
                Pending (<?= $woPendingCount ?>)
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="overflow-x: auto; margin-bottom: 20px;">
            <table id="wo-table">
                <thead>
                    <tr style="background: #d1fae5;">
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Part ID</th>
                        <th>SO List</th>
                        <th>Available Stock</th>
                        <th>Required</th>
                        <th>Shortage</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($woItems as $idx => $item):
                        $hasWO = !empty($item['created_wo_id']);
                        $itemWoStatus = $item['actual_wo_status'] ?? '';
                        $isClosed = $planIsCompleted || in_array($itemWoStatus, ['closed']);
                        $isCompleted = in_array($itemWoStatus, ['completed', 'qc_approval']);
                        $rowBg = $isClosed ? '#f3f4f6' : ($isCompleted ? '#dcfce7' : ($hasWO ? '#dbeafe' : ($idx % 2 ? '#ecfdf5' : '#f0fdf4')));
                        if ($planIsCompleted) { $rowFilterStatus = 'closed'; }
                        elseif ($hasWO && in_array($itemWoStatus, ['closed'])) { $rowFilterStatus = 'closed'; }
                        elseif ($hasWO && in_array($itemWoStatus, ['completed', 'qc_approval'])) { $rowFilterStatus = 'completed'; }
                        elseif ($hasWO) { $rowFilterStatus = 'in_progress'; }
                        elseif ($item['shortage'] <= 0) { $rowFilterStatus = 'in_stock'; }
                        else { $rowFilterStatus = 'pending'; }
                    ?>
                        <tr style="background: <?= $rowBg ?>;" data-status="<?= $rowFilterStatus ?>">
                            <td><?= htmlspecialchars($item['part_no']) ?></td>
                            <td><?= htmlspecialchars($item['part_name']) ?></td>
                            <td><strong><?= htmlspecialchars($item['part_id'] ?? '-') ?></strong></td>
                            <td><small style="color: #666;"><?= htmlspecialchars($item['so_list'] ?? '-') ?></small></td>
                            <td><?= $item['current_stock'] ?></td>
                            <td><?= $item['required_qty'] ?></td>
                            <td style="color: <?= $item['shortage'] > 0 ? '#dc2626' : '#16a34a' ?>; font-weight: bold;">
                                <?= $item['shortage'] ?>
                            </td>
                            <td>
                                <?php if ($planIsCompleted): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Closed
                                    </span>
                                <?php elseif ($hasWO):
                                    $woStatus = $item['actual_wo_status'] ?? '';
                                    if (in_array($woStatus, ['closed'])):
                                ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #6b7280; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Closed
                                    </span>
                                <?php elseif (in_array($woStatus, ['completed', 'qc_approval'])): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Completed
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #3b82f6; color: white; border-radius: 15px; font-size: 0.8em;">
                                        In Progress
                                    </span>
                                <?php endif; ?>
                                    <br><small style="color: #059669;"><?= htmlspecialchars($item['created_wo_no']) ?></small>
                                <?php elseif ($item['shortage'] <= 0): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #10b981; color: white; border-radius: 15px; font-size: 0.8em;">
                                        In Stock
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #f59e0b; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($planIsCompleted): ?>
                                    <span style="color: #16a34a;">Done</span>
                                <?php elseif ($hasWO): ?>
                                    <a href="/work_orders/view.php?id=<?= $item['created_wo_id'] ?>"
                                       class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                        View WO
                                    </a>
                                <?php elseif ($item['shortage'] > 0 && in_array($planDetails['status'], ['approved', 'partiallyordered'])): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="create_wo">
                                        <input type="hidden" name="wo_part_no" value="<?= htmlspecialchars($item['part_no']) ?>">
                                        <input type="hidden" name="wo_qty" value="<?= $item['shortage'] ?>">
                                        <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                onclick="return confirm('Create Work Order for <?= htmlspecialchars($item['part_no']) ?> (qty: <?= $item['shortage'] ?>)?');">
                                            Create WO
                                        </button>
                                    </form>
                                <?php elseif ($item['shortage'] > 0): ?>
                                    <span style="color: #f59e0b;">Needs WO</span>
                                <?php else: ?>
                                    <span style="color: #16a34a;">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SO-wise Work Order Summary -->
        <?php if (!empty($woItemsBySO)): ?>
        <div style="padding: 15px; background: #ecfdf5; border-radius: 8px; border: 1px solid #10b981;">
            <h4 style="margin: 0 0 15px 0; color: #059669;">Shop Order Wise Work Order Summary</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($woItemsBySO as $soNo => $soItems):
                    $allDone = $planIsCompleted;
                    $soClosed = 0; $soCompleted = 0; $soInProgress = 0; $soPending = 0; $soInStock = 0;
                    if ($planIsCompleted) {
                        $soClosed = count($soItems);
                    } else {
                        foreach ($soItems as $si) {
                            $siWoStatus = $si['actual_wo_status'] ?? '';
                            if (!empty($si['created_wo_id'])) {
                                if (in_array($siWoStatus, ['closed'])) { $soClosed++; }
                                elseif (in_array($siWoStatus, ['completed', 'qc_approval'])) { $soCompleted++; }
                                else { $soInProgress++; }
                            } elseif (($si['shortage'] ?? 0) <= 0) { $soInStock++; }
                            else { $soPending++; }
                        }
                        $allDone = ($soPending == 0 && $soInProgress == 0);
                    }
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= htmlspecialchars($soNo) ?></strong>
                        <?php if ($planIsCompleted): ?>
                            <span style="background: #16a34a; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">SO Released</span>
                        <?php elseif ($allDone): ?>
                            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">Complete</span>
                        <?php else: ?>
                            <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;"><?= $soPending + $soInProgress ?> Active</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                        <?php if ($planIsCompleted): ?>
                            <?= count($soItems) ?> parts | <span style="color: #16a34a;">All closed</span>
                        <?php else: ?>
                            <?= count($soItems) ?> parts |
                            <?php if ($soClosed): ?><span style="color: #6b7280;"><?= $soClosed ?> closed</span> | <?php endif; ?>
                            <?php if ($soCompleted): ?><span style="color: #16a34a;"><?= $soCompleted ?> completed</span> | <?php endif; ?>
                            <?php if ($soInProgress): ?><span style="color: #3b82f6;"><?= $soInProgress ?> in progress</span> | <?php endif; ?>
                            <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SO-wise Bulk WO Creation -->
        <?php if (!$planIsCompleted && in_array($planDetails['status'], ['approved', 'partiallyordered']) && !empty($woItemsBySO)): ?>
        <div style="padding: 15px; background: #f0fdf4; border-radius: 8px; border: 1px solid #86efac; margin-top: 15px;">
            <h4 style="margin: 0 0 15px 0; color: #059669;">Create Work Orders by Sales Order</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($woItemsBySO as $soNo => $soItems):
                    $soPending = 0;
                    $soTotal = count($soItems);
                    foreach ($soItems as $si) {
                        if (empty($si['created_wo_id']) && ($si['shortage'] ?? 0) > 0) {
                            $soPending++;
                        }
                    }
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $soPending > 0 ? '#f59e0b' : '#10b981' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong><?= htmlspecialchars($soNo) ?></strong>
                        <span style="font-size: 0.8em; color: #666;"><?= $soTotal ?> parts, <?= $soPending ?> pending</span>
                    </div>
                    <?php if ($soPending > 0): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="create_all_wo_for_so">
                        <input type="hidden" name="target_so" value="<?= htmlspecialchars($soNo) ?>">
                        <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 6px 14px; font-size: 0.85em; width: 100%;"
                                onclick="return confirm('Create Work Orders for all <?= $soPending ?> pending items in <?= htmlspecialchars($soNo) ?>?');">
                            Create All WOs for <?= htmlspecialchars($soNo) ?> (<?= $soPending ?> items)
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="color: #10b981; font-size: 0.85em;">All WOs created or in stock</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Metadata -->
    <div class="form-section" style="background: #f9fafb; border-radius: 8px; padding: 15px; margin-top: 20px;">
        <h3 style="margin-top: 0;">Plan Information</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <label style="color: #666; font-size: 0.9em;">Created</label>
                <p style="margin: 5px 0;">
                    <?= date('Y-m-d H:i', strtotime($planDetails['plan_date'])) ?>
                </p>
            </div>
            <?php if (!empty($planDetails['approved_at'])): ?>
            <div>
                <label style="color: #666; font-size: 0.9em;">Approved</label>
                <p style="margin: 5px 0;">
                    <?= date('Y-m-d H:i', strtotime($planDetails['approved_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
            <div>
                <label style="color: #666; font-size: 0.9em;">WO Progress</label>
                <p style="margin: 5px 0;">
                    <span style="font-weight: bold; color: #059669;"><?= $inStockOrDoneParts ?></span> Available,
                    <span style="font-weight: bold; color: #f59e0b;"><?= $totalWoParts - $inStockOrDoneParts ?></span> Pending
                    <span style="color: #666;">(of <?= $totalWoParts ?> total parts)</span>
                </p>
            </div>
        </div>
    </div>

    <?php else: ?>

    <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
        <p><?= htmlspecialchars($error) ?></p>
        <a href="index.php" class="btn btn-secondary" style="margin-top: 10px;">Back to Plans</a>
    </div>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var colorMap = {
        wo: { all: '#059669', closed: '#6b7280', completed: '#16a34a', in_progress: '#3b82f6', in_stock: '#10b981', pending: '#f59e0b' }
    };

    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = this.getAttribute('data-filter');
            var target = this.getAttribute('data-target');
            var table = document.getElementById(target + '-table');
            if (!table) return;

            var container = document.getElementById(target + '-filters');
            container.querySelectorAll('.filter-btn').forEach(function(b) {
                b.style.background = 'white';
                b.style.color = colorMap[target][b.getAttribute('data-filter')] || '#666';
                b.style.fontWeight = 'normal';
                b.classList.remove('active');
            });
            this.style.background = colorMap[target][filter] || '#666';
            this.style.color = 'white';
            this.style.fontWeight = '600';
            this.classList.add('active');

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                if (filter === 'all' || row.getAttribute('data-status') === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});
</script>

</body>
</html>
