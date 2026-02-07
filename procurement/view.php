<?php
// Start session first before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../db.php';
require '../includes/procurement_helper.php';

$planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$planId) {
    $error = "Plan ID is required";
}

$planDetails = $planDetails ?? null;
$planItems = [];

if ($planId) {
    $planDetails = getProcurementPlanDetails($pdo, $planId);
    if (!$planDetails) {
        $error = "Plan not found";
    } else {
        $planItems = getProcurementPlanItems($pdo, $planId);
    }
}

// Handle approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        if ($planDetails['status'] === 'draft') {
            if (approveProcurementPlan($pdo, $planId, 1)) { // User ID = 1 (admin)
                $success = "Plan approved successfully";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = "Failed to approve plan";
            }
        } else {
            $error = "Only draft plans can be approved";
        }
    }

    if ($action === 'convert_to_po') {
        if ($planDetails['status'] === 'approved') {
            $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');

            $result = convertPlanToPurchaseOrders($pdo, $planId, $purchaseDate);

            if ($result['success']) {
                $success = $result['message'];
                $planDetails = getProcurementPlanDetails($pdo, $planId);
                $planItems = getProcurementPlanItems($pdo, $planId);
            } else {
                $error = $result['error'] ?? 'Failed to convert to PO';
            }
        } else {
            $error = "Plan must be approved before converting to PO";
        }
    }

    if ($action === 'cancel') {
        if ($planDetails['status'] === 'draft') {
            if (cancelProcurementPlan($pdo, $planId)) {
                $success = "Plan cancelled";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = "Failed to cancel plan";
            }
        } else {
            $error = "Only draft plans can be cancelled";
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Procurement Plan - <?= htmlspecialchars($planDetails['plan_no'] ?? '') ?></title>
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
                'partiallyordered' => ['bg' => '#3b82f620', 'color' => '#3b82f6', 'text' => 'Partially Ordered'],
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

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Total Items</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #2563eb;"><?= $planDetails['item_count'] ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Total Order Qty</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #2563eb;"><?= $planDetails['total_items_to_order'] ?? 0 ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Est. Cost</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #059669;">₹ <?= number_format($planDetails['total_estimated_cost'] ?? 0, 2) ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Progress</div>
            <?php
            $pending = $planDetails['pending_count'] ?? 0;
            $ordered = $planDetails['ordered_count'] ?? 0;
            $received = $planDetails['received_count'] ?? 0;
            $total = $pending + $ordered + $received;
            $percentage = $total > 0 ? round((($ordered + $received) / $total) * 100) : 0;
            ?>
            <div style="font-size: 1.8em; font-weight: bold; color: #059669;"><?= $percentage ?>%</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <?php if ($planDetails['status'] === 'draft'): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Approve this plan?');">
                ✓ Approve Plan
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this plan?');">
                ✕ Cancel Plan
            </button>
        </form>
    </div>

    <?php elseif ($planDetails['status'] === 'approved'): ?>
    <div style="margin-bottom: 20px;">
        <form method="post">
            <input type="hidden" name="action" value="convert_to_po">
            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label for="purchase_date">Purchase Date</label>
                    <input type="date" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Convert this plan to Purchase Orders?');">
                    → Convert to PO
                </button>
                <a href="index.php" class="btn btn-secondary">Back to Plans</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
    </div>
    <?php endif; ?>

    <!-- Plan Items Table -->
    <div class="form-section">
        <h3>Procurement Items</h3>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>UOM</th>
                        <th>Current Stock</th>
                        <th>Required Qty</th>
                        <th>Min Stock</th>
                        <th>Order Qty</th>
                        <th>Supplier</th>
                        <th>Rate (₹)</th>
                        <th>Line Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalEstimated = 0;
                    foreach ($planItems as $item):
                        $lineTotal = $item['line_total'] ?? ($item['recommended_qty'] * $item['suggested_rate']);
                        $totalEstimated += $lineTotal;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($item['part_no']) ?></td>
                            <td><?= htmlspecialchars($item['part_name']) ?></td>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= $item['current_stock'] ?></td>
                            <td><?= $item['required_qty'] ?></td>
                            <td><?= $item['min_stock_threshold'] ?></td>
                            <td><strong><?= $item['recommended_qty'] ?></strong></td>
                            <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                            <td>₹ <?= number_format($item['suggested_rate'], 2) ?></td>
                            <td>₹ <?= number_format($lineTotal, 2) ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                    'pending' => ['icon' => '⏳', 'color' => '#6366f1', 'text' => 'Pending'],
                                    'ordered' => ['icon' => '📦', 'color' => '#3b82f6', 'text' => 'Ordered'],
                                    'received' => ['icon' => '✓', 'color' => '#16a34a', 'text' => 'Received'],
                                    'skipped' => ['icon' => '⊘', 'color' => '#dc2626', 'text' => 'Skipped']
                                ];
                                $st = $statusMap[$item['status']] ?? $statusMap['pending'];
                                ?>
                                <span style="display: inline-block; padding: 4px 8px; background: <?= $st['color'] ?>20; color: <?= $st['color'] ?>; border-radius: 4px; font-size: 0.85em;">
                                    <?= $st['icon'] ?> <?= $st['text'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 15px; background: #f0f9ff; border-radius: 8px; margin-top: 15px; font-weight: 600;">
            <strong>Total Estimated Cost: ₹ <?= number_format($totalEstimated, 2) ?></strong>
        </div>
    </div>

    <?php
    // Load WO and PO items for this plan
    $woItems = getPlanWorkOrderItems($pdo, $planId);
    $poItems = getPlanPurchaseOrderItems($pdo, $planId);

    // Clear stale links to closed/cancelled WOs (from old released SOs)
    foreach ($woItems as &$woItem) {
        if (!empty($woItem['created_wo_id'])) {
            try {
                $chkStmt = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
                $chkStmt->execute([$woItem['created_wo_id']]);
                $woRealStatus = $chkStmt->fetchColumn();
                if ($woRealStatus && in_array($woRealStatus, ['closed', 'cancelled'])) {
                    // Unlink the closed/cancelled WO
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

    // Refresh real-time stock and shortage for WO items
    foreach ($woItems as &$woItem) {
        try {
            $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stockStmt->execute([$woItem['part_no']]);
            $realStock = (int)$stockStmt->fetchColumn();
            $woItem['current_stock'] = $realStock;
            $woItem['shortage'] = max(0, $woItem['required_qty'] - $realStock);
        } catch (Exception $e) {}

        // Also get actual WO status from work_orders table if WO exists
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

    // Refresh real-time stock and shortage for PO items
    foreach ($poItems as &$poItem) {
        try {
            $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stockStmt->execute([$poItem['part_no']]);
            $realStock = (int)$stockStmt->fetchColumn();
            $poItem['current_stock'] = $realStock;
            $poItem['shortage'] = max(0, $poItem['required_qty'] - $realStock);
        } catch (Exception $e) {}
    }
    unset($poItem);

    // Group WO items by SO
    $woItemsBySO = [];
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

    // Group PO items by SO
    $poItemsBySO = [];
    foreach ($poItems as $item) {
        $soList = $item['so_list'] ?? 'Unknown';
        $soNumbers = array_map('trim', explode(',', $soList));
        foreach ($soNumbers as $soNo) {
            if (!isset($poItemsBySO[$soNo])) {
                $poItemsBySO[$soNo] = [];
            }
            $poItemsBySO[$soNo][] = $item;
        }
    }
    ?>

    <?php if (!empty($woItems)): ?>
    <!-- Work Order Parts Section -->
    <div class="form-section" style="margin-top: 30px; border-top: 3px solid #10b981; padding-top: 20px;">
        <h3 style="color: #059669; margin-bottom: 15px;">
            <span style="background: #d1fae5; padding: 4px 12px; border-radius: 20px;">
                ⚙️ Work Order Parts (Internal Production)
            </span>
            <?php
            $woClosedCount = 0;
            $woCompletedCount = 0;
            $woInProgressCount = 0;
            $woPendingCount = 0;
            $woInStockCount = 0;
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
            ?>
            <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                <?php if ($woClosedCount): ?><span style="color: #6b7280;"><?= $woClosedCount ?> Closed</span> | <?php endif; ?>
                <?php if ($woCompletedCount): ?><span style="color: #16a34a;"><?= $woCompletedCount ?> Completed</span> | <?php endif; ?>
                <span style="color: #3b82f6;"><?= $woInProgressCount ?> In Progress</span> |
                <span style="color: #10b981;"><?= $woInStockCount ?> In Stock</span> |
                <span style="color: #f59e0b;"><?= $woPendingCount ?> Pending</span>
            </span>
        </h3>

        <div style="overflow-x: auto; margin-bottom: 20px;">
            <table>
                <thead>
                    <tr style="background: #d1fae5;">
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Part ID</th>
                        <th>SO List</th>
                        <th>Stock</th>
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
                        $isClosed = in_array($itemWoStatus, ['closed']);
                        $isCompleted = in_array($itemWoStatus, ['completed', 'qc_approval']);
                        $rowBg = $isClosed ? '#f3f4f6' : ($isCompleted ? '#dcfce7' : ($hasWO ? '#dbeafe' : ($idx % 2 ? '#ecfdf5' : '#f0fdf4')));
                    ?>
                        <tr style="background: <?= $rowBg ?>;">
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
                                <?php if ($hasWO):
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
                                <?php if ($hasWO): ?>
                                    <a href="/work_orders/view.php?id=<?= $item['created_wo_id'] ?>"
                                       class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                        View WO
                                    </a>
                                <?php elseif ($item['shortage'] > 0): ?>
                                    <span style="color: #f59e0b;">Needs WO</span>
                                <?php else: ?>
                                    <span style="color: #16a34a;">✓</span>
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
            <h4 style="margin: 0 0 15px 0; color: #059669;">📋 Shop Order Wise Work Order Summary</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($woItemsBySO as $soNo => $soItems):
                    $soClosed = 0;
                    $soCompleted = 0;
                    $soInProgress = 0;
                    $soPending = 0;
                    $soInStock = 0;
                    foreach ($soItems as $si) {
                        $siWoStatus = $si['actual_wo_status'] ?? '';
                        if (!empty($si['created_wo_id'])) {
                            if (in_array($siWoStatus, ['closed'])) {
                                $soClosed++;
                            } elseif (in_array($siWoStatus, ['completed', 'qc_approval'])) {
                                $soCompleted++;
                            } else {
                                $soInProgress++;
                            }
                        } elseif (($si['shortage'] ?? 0) <= 0) {
                            $soInStock++;
                        } else {
                            $soPending++;
                        }
                    }
                    $allDone = ($soPending == 0 && $soInProgress == 0);
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= htmlspecialchars($soNo) ?></strong>
                        <?php if ($allDone): ?>
                            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">Complete</span>
                        <?php else: ?>
                            <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;"><?= $soPending + $soInProgress ?> Active</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                        <?= count($soItems) ?> parts |
                        <?php if ($soClosed): ?><span style="color: #6b7280;"><?= $soClosed ?> closed</span> | <?php endif; ?>
                        <?php if ($soCompleted): ?><span style="color: #16a34a;"><?= $soCompleted ?> completed</span> | <?php endif; ?>
                        <?php if ($soInProgress): ?><span style="color: #3b82f6;"><?= $soInProgress ?> in progress</span> | <?php endif; ?>
                        <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($poItems)): ?>
    <!-- Purchase Order Parts Section -->
    <div class="form-section" style="margin-top: 30px; border-top: 3px solid #f59e0b; padding-top: 20px;">
        <h3 style="color: #d97706; margin-bottom: 15px;">
            <span style="background: #fef3c7; padding: 4px 12px; border-radius: 20px;">
                🔧 Purchase Order Parts (Sublet/External)
            </span>
            <?php
            $poOrderedCount = 0;
            $poPendingCount = 0;
            $poInStockCount = 0;
            foreach ($poItems as $pi) {
                if ($pi['created_po_id']) {
                    $poOrderedCount++;
                } elseif ($pi['shortage'] <= 0) {
                    $poInStockCount++;
                } else {
                    $poPendingCount++;
                }
            }
            ?>
            <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                <span style="color: #16a34a;"><?= $poOrderedCount ?> Ordered</span> |
                <span style="color: #10b981;"><?= $poInStockCount ?> In Stock</span> |
                <span style="color: #f59e0b;"><?= $poPendingCount ?> Pending</span>
            </span>
        </h3>

        <div style="overflow-x: auto; margin-bottom: 20px;">
            <table>
                <thead>
                    <tr style="background: #fef3c7;">
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Part ID</th>
                        <th>SO List</th>
                        <th>Stock</th>
                        <th>Required</th>
                        <th>Shortage</th>
                        <th>Ordered Qty</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poItems as $idx => $item):
                        $hasPO = !empty($item['created_po_id']);
                    ?>
                        <tr style="background: <?= $hasPO ? '#dcfce7' : ($idx % 2 ? '#fffbeb' : '#fef9e7') ?>;">
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
                                <?php if ($hasPO): ?>
                                    <span style="color: #16a34a; font-weight: bold;"><?= $item['ordered_qty'] ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($hasPO): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Ordered
                                    </span>
                                    <br><small style="color: #059669;"><?= htmlspecialchars($item['created_po_no']) ?></small>
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
                                <?php if ($hasPO): ?>
                                    <a href="/purchase/view.php?po_no=<?= urlencode($item['created_po_no']) ?>"
                                       class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                        View PO
                                    </a>
                                <?php elseif ($item['shortage'] > 0): ?>
                                    <span style="color: #f59e0b;">Needs PO</span>
                                <?php else: ?>
                                    <span style="color: #16a34a;">✓</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SO-wise Purchase Order Summary -->
        <?php if (!empty($poItemsBySO)): ?>
        <div style="padding: 15px; background: #fffbeb; border-radius: 8px; border: 1px solid #f59e0b;">
            <h4 style="margin: 0 0 15px 0; color: #d97706;">📋 Shop Order Wise Purchase Order Summary</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($poItemsBySO as $soNo => $soItems):
                    $soOrdered = 0;
                    $soPending = 0;
                    $soInStock = 0;
                    foreach ($soItems as $si) {
                        if (!empty($si['created_po_id'])) {
                            $soOrdered++;
                        } elseif (($si['shortage'] ?? 0) <= 0) {
                            $soInStock++;
                        } else {
                            $soPending++;
                        }
                    }
                    $allDone = ($soPending == 0);
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= htmlspecialchars($soNo) ?></strong>
                        <?php if ($allDone): ?>
                            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">Complete</span>
                        <?php else: ?>
                            <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;"><?= $soPending ?> Pending</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                        <?= count($soItems) ?> parts |
                        <span style="color: #16a34a;"><?= $soOrdered ?> ordered</span> |
                        <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Notes -->
    <?php if (!empty($planDetails['notes'])): ?>
    <div class="form-section">
        <h3>Notes</h3>
        <p style="white-space: pre-wrap; color: #555;">
            <?= htmlspecialchars($planDetails['notes']) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Metadata -->
    <div class="form-section" style="background: #f9fafb; border-radius: 8px; padding: 15px;">
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
                <label style="color: #666; font-size: 0.9em;">Items</label>
                <p style="margin: 5px 0;">
                    <span style="font-weight: bold; color: #dc2626;"><?= $pending ?></span> Pending,
                    <span style="font-weight: bold; color: #3b82f6;"><?= $ordered ?></span> Ordered,
                    <span style="font-weight: bold; color: #16a34a;"><?= $received ?></span> Received
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

</body>
</html>
