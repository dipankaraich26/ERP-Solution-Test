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
    // Auto-close plan if all linked SOs are released
    autoClosePlanIfAllSOsReleased($pdo, $planId);

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
        if (in_array($planDetails['status'], ['draft', 'approved', 'partiallyordered'])) {
            if (cancelProcurementPlan($pdo, $planId)) {
                $success = "Plan cancelled successfully. Any blocked stock has been released.";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } else {
                $error = "Failed to cancel plan";
            }
        } else {
            $error = "Only draft, approved, or partially ordered plans can be cancelled";
        }
    }

    if ($action === 'refresh_bom') {
        if (!in_array($planDetails['status'], ['completed', 'cancelled'])) {
            $result = refreshPlanFromBOM($pdo, $planId);
            if ($result['success']) {
                $success = $result['message'];
                $planDetails = getProcurementPlanDetails($pdo, $planId);
                $planItems = getProcurementPlanItems($pdo, $planId);
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Cannot refresh BOM for a " . $planDetails['status'] . " plan";
        }
    }

    // Handle regenerate PO for cancelled PO items
    if ($action === 'regenerate_po') {
        $regenPartNo = $_POST['regen_part_no'] ?? '';
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');

        if ($regenPartNo && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            try {
                // Get the PO item details from plan
                $poItemStmt = $pdo->prepare("SELECT * FROM procurement_plan_po_items WHERE plan_id = ? AND part_no = ?");
                $poItemStmt->execute([$planId, $regenPartNo]);
                $poItemData = $poItemStmt->fetch(PDO::FETCH_ASSOC);

                if ($poItemData) {
                    $supplierId = $poItemData['supplier_id'] ?? null;
                    $qty = $poItemData['required_qty'] ?? $poItemData['ordered_qty'] ?? 0;

                    // Get supplier from part_supplier_mapping if not stored
                    if (!$supplierId) {
                        $bestSupplier = getBestSupplier($pdo, $regenPartNo);
                        $supplierId = $bestSupplier ? $bestSupplier['supplier_id'] : null;
                    }

                    if ($supplierId && $qty > 0) {
                        // Clear old cancelled PO reference
                        $pdo->prepare("UPDATE procurement_plan_po_items SET created_po_id = NULL, created_po_no = NULL, ordered_qty = NULL, status = 'pending' WHERE plan_id = ? AND part_no = ?")
                             ->execute([$planId, $regenPartNo]);

                        // Create new PO using the tracking function
                        $subletItems = [[
                            'part_no' => $regenPartNo,
                            'qty' => $qty,
                            'supplier_id' => $supplierId
                        ]];
                        $result = createSubletPurchaseOrdersWithTracking($pdo, $planId, $subletItems, $purchaseDate);

                        if ($result['success']) {
                            $createdPoNos = array_keys($result['created_pos'] ?? []);
                            $success = "New PO created: " . implode(', ', $createdPoNos) . " for " . $regenPartNo;
                            $planDetails = getProcurementPlanDetails($pdo, $planId);
                        } else {
                            $error = $result['error'] ?? 'Failed to create new PO';
                        }
                    } else {
                        $error = "No supplier or quantity found for " . $regenPartNo;
                    }
                } else {
                    $error = "PO item not found for " . $regenPartNo;
                }
            } catch (Exception $e) {
                $error = "Error regenerating PO: " . $e->getMessage();
            }
        } else {
            $error = "Plan must be approved or partially ordered to regenerate PO";
        }
    }
}

// Load WO/PO items with real-time stock (after POST handlers so data is fresh)
$woItems = [];
$poItems = [];
$woItemsBySO = [];
$poItemsBySO = [];
$planIsCompleted = false;
$totalWoPoParts = 0;
$inStockOrDoneParts = 0;

if ($planDetails) {
    $woItems = getPlanWorkOrderItems($pdo, $planId);
    $poItems = getPlanPurchaseOrderItems($pdo, $planId);
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

        foreach ($poItems as &$poItem) {
            if (!empty($poItem['created_po_id'])) {
                try {
                    $chkStmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                    $chkStmt->execute([$poItem['created_po_id']]);
                    $poRealStatus = $chkStmt->fetchColumn();
                    if ($poRealStatus && $poRealStatus === 'cancelled') {
                        // Mark as po_cancelled - keep old PO ref for display
                        $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'po_cancelled' WHERE plan_id = ? AND part_no = ?")
                             ->execute([$planId, $poItem['part_no']]);
                        $poItem['status'] = 'po_cancelled';
                        $poItem['cancelled_po_no'] = $poItem['created_po_no'];
                    }
                } catch (Exception $e) {}
            }
        }
        unset($poItem);
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

    // Refresh real-time stock for PO items
    foreach ($poItems as &$poItem) {
        try {
            $poItem['current_stock'] = (int)getAvailableStock($pdo, $poItem['part_no'], $planId);
            $poItem['shortage'] = max(0, $poItem['required_qty'] - $poItem['current_stock']);
        } catch (Exception $e) {}
    }
    unset($poItem);

    // Cascade "In Stock" from parent WO parts to child WO/PO parts
    if (!$planIsCompleted) {
        $woPartMap = [];
        foreach ($woItems as $wi) {
            $woPartMap[$wi['part_no']] = [
                'shortage' => $wi['shortage'],
                'has_wo' => !empty($wi['created_wo_id']),
            ];
        }
        $poPartIndex = [];
        foreach ($poItems as $idx => $pi) {
            $poPartIndex[$pi['part_no']] = $idx;
        }
        $woPartIndex = [];
        foreach ($woItems as $idx => $wi) {
            $woPartIndex[$wi['part_no']] = $idx;
        }
        $inStockWoParts = [];
        foreach ($woPartMap as $partNo => $info) {
            if (!$info['has_wo'] && $info['shortage'] <= 0) {
                $inStockWoParts[] = $partNo;
            }
        }
        $processed = [];
        while (!empty($inStockWoParts)) {
            $nextInStock = [];
            foreach ($inStockWoParts as $parentPartNo) {
                if (isset($processed[$parentPartNo])) continue;
                $processed[$parentPartNo] = true;
                try {
                    $childParts = getBomChildParts($pdo, $parentPartNo);
                    foreach ($childParts as $child) {
                        $childPartNo = $child['part_no'];
                        if (isset($woPartIndex[$childPartNo])) {
                            $idx = $woPartIndex[$childPartNo];
                            if (empty($woItems[$idx]['created_wo_id']) && $woItems[$idx]['shortage'] > 0) {
                                $woItems[$idx]['shortage'] = 0;
                                $nextInStock[] = $childPartNo;
                            }
                        }
                        if (isset($poPartIndex[$childPartNo])) {
                            $idx = $poPartIndex[$childPartNo];
                            if (empty($poItems[$idx]['created_po_id']) && $poItems[$idx]['shortage'] > 0) {
                                $poItems[$idx]['shortage'] = 0;
                            }
                        }
                    }
                } catch (Exception $e) {}
            }
            $inStockWoParts = $nextInStock;
        }
    }

    // Clear po_cancelled status for items that now have sufficient stock
    if (!$planIsCompleted) {
        foreach ($poItems as &$poItem) {
            if (($poItem['status'] ?? '') === 'po_cancelled' && $poItem['shortage'] <= 0) {
                try {
                    $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'pending', created_po_id = NULL, created_po_no = NULL, ordered_qty = NULL WHERE plan_id = ? AND part_no = ?")
                         ->execute([$planId, $poItem['part_no']]);
                    $poItem['status'] = 'pending';
                    $poItem['created_po_id'] = null;
                    $poItem['created_po_no'] = null;
                    $poItem['ordered_qty'] = null;
                    unset($poItem['cancelled_po_no']);
                } catch (Exception $e) {}
            }
        }
        unset($poItem);
    }

    // Calculate stock-based progress: parts "in stock" or with completed WO/PO
    $totalWoPoParts = count($woItems) + count($poItems);
    $inStockOrDoneParts = 0;
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
    foreach ($poItems as $pi) {
        if ($planIsCompleted) {
            $inStockOrDoneParts++;
        } elseif (!empty($pi['created_po_id'])) {
            $poSt = $pi['status'] ?? '';
            if (in_array($poSt, ['received', 'closed'])) {
                $inStockOrDoneParts++;
            }
        } elseif ($pi['shortage'] <= 0) {
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

    // Group PO items by SO
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

    <!-- SO Details & Products -->
    <?php
    $viewSoNos = [];
    if (!empty($planDetails['so_list'])) {
        $viewSoNos = array_filter(array_map('trim', explode(',', $planDetails['so_list'])));
    }
    // Also gather from WO/PO items
    foreach (['procurement_plan_wo_items', 'procurement_plan_po_items'] as $tbl) {
        try {
            $soStmt = $pdo->prepare("SELECT DISTINCT so_list FROM $tbl WHERE plan_id = ? AND so_list IS NOT NULL AND so_list != ''");
            $soStmt->execute([$planId]);
            foreach ($soStmt->fetchAll(PDO::FETCH_COLUMN) as $sl) {
                foreach (array_map('trim', explode(',', $sl)) as $s) {
                    if ($s !== '' && !in_array($s, $viewSoNos)) $viewSoNos[] = $s;
                }
            }
        } catch (Exception $e) {}
    }

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
    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px;">
        <h4 style="margin: 0 0 12px 0; color: #0369a1; font-size: 0.95em;">Sales Orders & Products</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <?php foreach ($viewSoDetails as $sd): ?>
            <div style="background: white; border: 1px solid #e0f2fe; border-radius: 6px; padding: 10px 14px; min-width: 220px; flex: 1; max-width: 350px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <a href="/sales_orders/view.php?so_no=<?= urlencode($sd['so_no']) ?>" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 0.95em;">
                        <?= htmlspecialchars($sd['so_no']) ?>
                    </a>
                    <span style="font-size: 0.75em; padding: 2px 8px; border-radius: 10px; background: <?= $sd['so_status'] === 'released' ? '#dcfce7' : '#fef3c7' ?>; color: <?= $sd['so_status'] === 'released' ? '#16a34a' : '#d97706' ?>;">
                        <?= ucfirst($sd['so_status']) ?>
                    </span>
                </div>
                <?php if (!empty($sd['customer_name'])): ?>
                <div style="font-size: 0.8em; color: #666; margin-bottom: 4px;"><?= htmlspecialchars($sd['customer_name']) ?></div>
                <?php endif; ?>
                <div style="font-size: 0.85em; color: #1e40af; font-weight: 500;">
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
            if ($planDetails['status'] === 'completed') {
                $percentage = 100;
            } elseif ($planDetails['status'] === 'cancelled') {
                $percentage = 0;
            } else {
                $percentage = $totalWoPoParts > 0 ? round(($inStockOrDoneParts / $totalWoPoParts) * 100) : 0;
            }
            $pColor = $percentage >= 100 ? '#16a34a' : ($percentage > 0 ? '#f59e0b' : '#6b7280');
            ?>
            <div style="font-size: 1.8em; font-weight: bold; color: <?= $pColor ?>;"><?= $percentage ?>%</div>
            <?php if ($totalWoPoParts > 0): ?>
            <div style="font-size: 0.75em; color: #666; margin-top: 2px;">
                <?= $inStockOrDoneParts ?>/<?= $totalWoPoParts ?> parts available
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stock Blocking Info -->
    <?php
    if (in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
        try {
            ensureStockBlocksTable($pdo);
            $blockedStmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(blocked_qty), 0) as total FROM stock_blocks WHERE plan_id = ?");
            $blockedStmt->execute([$planId]);
            $blockedInfo = $blockedStmt->fetch(PDO::FETCH_ASSOC);
            if ($blockedInfo && $blockedInfo['cnt'] > 0):
    ?>
    <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
        <span style="font-size: 1.5em;">🔒</span>
        <div>
            <strong style="color: #92400e;">Stock Blocked by this Plan</strong><br>
            <span style="color: #78350f; font-size: 0.9em;">
                <?= $blockedInfo['cnt'] ?> part(s) with total <?= number_format($blockedInfo['total']) ?> qty blocked.
                This stock is reserved and won't be available for other procurement plans.
            </span>
        </div>
    </div>
    <?php
            endif;
        } catch (Exception $e) {}
    }
    ?>

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
            <input type="hidden" name="action" value="refresh_bom">
            <button type="submit" class="btn" style="background: #f59e0b; color: white;" onclick="return confirm('Refresh WO/PO items from latest BOM?');">
                ↻ Update from BOM
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
    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <form method="post" style="display: inline-flex; gap: 10px; align-items: flex-end;">
            <input type="hidden" name="action" value="convert_to_po">
            <div>
                <label for="purchase_date">Purchase Date</label>
                <input type="date" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Convert this plan to Purchase Orders?');">
                → Convert to PO
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="refresh_bom">
            <button type="submit" class="btn" style="background: #f59e0b; color: white;" onclick="return confirm('Refresh WO/PO items from latest BOM?');">
                ↻ Update from BOM
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this plan? This will release all blocked stock.');">
                ✕ Cancel Plan
            </button>
        </form>
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
    </div>

    <?php elseif ($planDetails['status'] === 'partiallyordered'): ?>
    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="refresh_bom">
            <button type="submit" class="btn" style="background: #f59e0b; color: white;" onclick="return confirm('Refresh WO/PO items from latest BOM?');">
                ↻ Update from BOM
            </button>
        </form>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this plan? This will release all blocked stock.');">
                ✕ Cancel Plan
            </button>
        </form>
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
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
    // WO/PO items already loaded and processed at the top of the file
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
                        // Determine row filter status
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
                                    <span style="color: #16a34a;">✓ Done</span>
                                <?php elseif ($hasWO): ?>
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
            $poCancelledCount = 0;
            if ($planIsCompleted) {
                $poOrderedCount = count($poItems);
            } else {
                foreach ($poItems as $pi) {
                    if (($pi['status'] ?? '') === 'po_cancelled') {
                        $poCancelledCount++;
                    } elseif ($pi['created_po_id'] && ($pi['status'] ?? '') !== 'po_cancelled') {
                        $poOrderedCount++;
                    } elseif ($pi['shortage'] <= 0) {
                        $poInStockCount++;
                    } else {
                        $poPendingCount++;
                    }
                }
            }
            ?>
            <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                <?php if ($planIsCompleted): ?>
                    <span style="color: #16a34a; font-weight: 600;">All <?= count($poItems) ?> Closed (SOs Released)</span>
                <?php else: ?>
                    <span style="color: #16a34a;"><?= $poOrderedCount ?> Ordered</span> |
                    <span style="color: #10b981;"><?= $poInStockCount ?> In Stock</span> |
                    <?php if ($poCancelledCount): ?>
                        <span style="color: #dc2626;"><?= $poCancelledCount ?> PO Cancelled</span> |
                    <?php endif; ?>
                    <span style="color: #f59e0b;"><?= $poPendingCount ?> Pending</span>
                <?php endif; ?>
            </span>
        </h3>

        <?php if ($poCancelledCount > 0 && !$planIsCompleted): ?>
        <div style="background: #fef2f2; border: 1px solid #dc2626; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 1.3em; color: #dc2626;">&#9888;</span>
            <div>
                <strong style="color: #991b1b;"><?= $poCancelledCount ?> Purchase Order(s) Cancelled</strong><br>
                <span style="color: #7f1d1d; font-size: 0.9em;">
                    The linked PO(s) have been cancelled. You can generate new PO(s) for these items below.
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- PO Filter Buttons -->
        <?php if (!$planIsCompleted): ?>
        <div style="margin-bottom: 12px; display: flex; gap: 6px; flex-wrap: wrap;" id="po-filters">
            <button type="button" class="filter-btn active" data-filter="all" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: #d97706; color: white; cursor: pointer; font-size: 0.85em;">
                All (<?= count($poItems) ?>)
            </button>
            <?php if ($poOrderedCount): ?>
            <button type="button" class="filter-btn" data-filter="ordered" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #16a34a; cursor: pointer; font-size: 0.85em;">
                Ordered (<?= $poOrderedCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($poCancelledCount): ?>
            <button type="button" class="filter-btn" data-filter="cancelled" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #dc2626; cursor: pointer; font-size: 0.85em;">
                Cancelled (<?= $poCancelledCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($poInStockCount): ?>
            <button type="button" class="filter-btn" data-filter="in_stock" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #10b981; cursor: pointer; font-size: 0.85em;">
                In Stock (<?= $poInStockCount ?>)
            </button>
            <?php endif; ?>
            <?php if ($poPendingCount): ?>
            <button type="button" class="filter-btn" data-filter="pending" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #f59e0b; cursor: pointer; font-size: 0.85em;">
                Pending (<?= $poPendingCount ?>)
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="overflow-x: auto; margin-bottom: 20px;">
            <table id="po-table">
                <thead>
                    <tr style="background: #fef3c7;">
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Part ID</th>
                        <th>SO List</th>
                        <th>Available Stock</th>
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
                        $isCancelled = ($item['status'] ?? '') === 'po_cancelled';
                        $hasPO = !empty($item['created_po_id']) && !$isCancelled;
                        $cancelledPoNo = $item['cancelled_po_no'] ?? $item['created_po_no'] ?? '';
                        if ($planIsCompleted) { $poRowBg = '#f3f4f6'; }
                        elseif ($isCancelled) { $poRowBg = '#fef2f2'; }
                        elseif ($hasPO) { $poRowBg = '#dcfce7'; }
                        else { $poRowBg = $idx % 2 ? '#fffbeb' : '#fef9e7'; }
                        // Determine row filter status
                        if ($planIsCompleted) { $poRowFilter = 'closed'; }
                        elseif ($isCancelled) { $poRowFilter = 'cancelled'; }
                        elseif ($hasPO) { $poRowFilter = 'ordered'; }
                        elseif ($item['shortage'] <= 0) { $poRowFilter = 'in_stock'; }
                        else { $poRowFilter = 'pending'; }
                    ?>
                        <tr style="background: <?= $poRowBg ?>;" data-status="<?= $poRowFilter ?>">
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
                                <?php elseif ($isCancelled): ?>
                                    <span style="color: #dc2626; text-decoration: line-through;"><?= $item['ordered_qty'] ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($planIsCompleted): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Closed
                                    </span>
                                <?php elseif ($isCancelled): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #dc2626; color: white; border-radius: 15px; font-size: 0.8em;">
                                        PO Cancelled
                                    </span>
                                    <?php if ($cancelledPoNo): ?>
                                        <br><small style="color: #dc2626; text-decoration: line-through;"><?= htmlspecialchars($cancelledPoNo) ?></small>
                                    <?php endif; ?>
                                <?php elseif ($hasPO): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Ordered
                                    </span>
                                    <br><small style="color: #059669;"><?= htmlspecialchars($item['created_po_no']) ?></small>
                                <?php elseif ($item['shortage'] <= 0): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #10b981; color: white; border-radius: 15px; font-size: 0.8em;">
                                        In Stock
                                    </span>
                                    <?php if ($item['current_stock'] < $item['required_qty']): ?>
                                        <br><small style="color: #059669;">Parent has stock</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #f59e0b; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($planIsCompleted): ?>
                                    <span style="color: #16a34a;">✓ Done</span>
                                <?php elseif ($isCancelled): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="regenerate_po">
                                        <input type="hidden" name="regen_part_no" value="<?= htmlspecialchars($item['part_no']) ?>">
                                        <input type="hidden" name="purchase_date" value="<?= date('Y-m-d') ?>">
                                        <button type="submit" class="btn btn-sm" style="background: #dc2626; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                onclick="return confirm('Generate new PO for <?= htmlspecialchars($item['part_no']) ?>?');">
                                            Generate New PO
                                        </button>
                                    </form>
                                <?php elseif ($hasPO): ?>
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
                    $allDone = $planIsCompleted;
                    $soOrdered = 0; $soPending = 0; $soInStock = 0; $soCancelled = 0;
                    if (!$planIsCompleted) {
                        foreach ($soItems as $si) {
                            if (($si['status'] ?? '') === 'po_cancelled') { $soCancelled++; }
                            elseif (!empty($si['created_po_id']) && ($si['status'] ?? '') !== 'po_cancelled') { $soOrdered++; }
                            elseif (($si['shortage'] ?? 0) <= 0) { $soInStock++; }
                            else { $soPending++; }
                        }
                        $allDone = ($soPending == 0 && $soCancelled == 0);
                    }
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $soCancelled > 0 ? '#dc2626' : ($allDone ? '#10b981' : '#f59e0b') ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= htmlspecialchars($soNo) ?></strong>
                        <?php if ($planIsCompleted): ?>
                            <span style="background: #16a34a; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">SO Released</span>
                        <?php elseif ($soCancelled > 0): ?>
                            <span style="background: #dc2626; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;"><?= $soCancelled ?> PO Cancelled</span>
                        <?php elseif ($allDone): ?>
                            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">Complete</span>
                        <?php else: ?>
                            <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;"><?= $soPending ?> Pending</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 5px;">
                        <?php if ($planIsCompleted): ?>
                            <?= count($soItems) ?> parts | <span style="color: #16a34a;">All closed</span>
                        <?php else: ?>
                            <?= count($soItems) ?> parts |
                            <span style="color: #16a34a;"><?= $soOrdered ?> ordered</span> |
                            <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                            <?php if ($soCancelled > 0): ?>
                                | <span style="color: #dc2626;"><?= $soCancelled ?> cancelled</span>
                            <?php endif; ?>
                        <?php endif; ?>
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
                <label style="color: #666; font-size: 0.9em;">Stock Progress</label>
                <p style="margin: 5px 0;">
                    <span style="font-weight: bold; color: #059669;"><?= $inStockOrDoneParts ?></span> Available,
                    <span style="font-weight: bold; color: #f59e0b;"><?= $totalWoPoParts - $inStockOrDoneParts ?></span> Pending
                    <span style="color: #666;">(of <?= $totalWoPoParts ?> total parts)</span>
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
        wo: { all: '#059669', closed: '#6b7280', completed: '#16a34a', in_progress: '#3b82f6', in_stock: '#10b981', pending: '#f59e0b' },
        po: { all: '#d97706', ordered: '#16a34a', cancelled: '#dc2626', in_stock: '#10b981', pending: '#f59e0b' }
    };

    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = this.getAttribute('data-filter');
            var target = this.getAttribute('data-target');
            var table = document.getElementById(target + '-table');
            if (!table) return;

            // Update active button styling
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

            // Filter rows
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
