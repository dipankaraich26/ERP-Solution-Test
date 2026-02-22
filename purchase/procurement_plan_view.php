<?php
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
    die("Plan ID is required");
}

$planDetails = getProcurementPlanDetails($pdo, $planId);
if (!$planDetails) {
    die("Plan not found");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_po') {
        $poPartNo = $_POST['po_part_no'] ?? '';
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
        if ($poPartNo && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            try {
                $poItemStmt = $pdo->prepare("SELECT * FROM procurement_plan_po_items WHERE plan_id = ? AND part_no = ?");
                $poItemStmt->execute([$planId, $poPartNo]);
                $poItemData = $poItemStmt->fetch(PDO::FETCH_ASSOC);
                if ($poItemData) {
                    $supplierId = $poItemData['supplier_id'] ?? null;
                    $qty = $poItemData['required_qty'] ?? 0;
                    if (!$supplierId) {
                        $bestSupplier = getBestSupplier($pdo, $poPartNo);
                        $supplierId = $bestSupplier ? $bestSupplier['supplier_id'] : null;
                    }
                    if ($supplierId && $qty > 0) {
                        $subletItems = [['part_no' => $poPartNo, 'qty' => $qty, 'supplier_id' => $supplierId]];
                        $result = createSubletPurchaseOrdersWithTracking($pdo, $planId, $subletItems, $purchaseDate);
                        if ($result['success']) {
                            $createdPoNos = $result['created_pos'] ?? [];
                            $success = "PO created: " . implode(', ', $createdPoNos) . " for " . $poPartNo;
                            $planDetails = getProcurementPlanDetails($pdo, $planId);
                        } else {
                            $error = $result['error'] ?? 'Failed to create PO';
                        }
                    } else {
                        $error = "No supplier or quantity found for " . $poPartNo;
                    }
                } else {
                    $error = "PO item not found for " . $poPartNo;
                }
            } catch (Exception $e) {
                $error = "Error creating PO: " . $e->getMessage();
            }
        } else {
            $error = "Plan must be approved or partially ordered to create PO";
        }
    }

    if ($action === 'regenerate_po') {
        $regenPartNo = $_POST['regen_part_no'] ?? '';
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
        if ($regenPartNo && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            try {
                // Reset the cancelled item to pending first
                $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'pending', created_po_id = NULL, created_po_no = NULL, ordered_qty = NULL WHERE plan_id = ? AND part_no = ? AND status = 'po_cancelled'")
                     ->execute([$planId, $regenPartNo]);

                $poItemStmt = $pdo->prepare("SELECT * FROM procurement_plan_po_items WHERE plan_id = ? AND part_no = ?");
                $poItemStmt->execute([$planId, $regenPartNo]);
                $poItemData = $poItemStmt->fetch(PDO::FETCH_ASSOC);
                if ($poItemData) {
                    $supplierId = $poItemData['supplier_id'] ?? null;
                    $qty = $poItemData['required_qty'] ?? 0;
                    if (!$supplierId) {
                        $bestSupplier = getBestSupplier($pdo, $regenPartNo);
                        $supplierId = $bestSupplier ? $bestSupplier['supplier_id'] : null;
                    }
                    if ($supplierId && $qty > 0) {
                        $subletItems = [['part_no' => $regenPartNo, 'qty' => $qty, 'supplier_id' => $supplierId]];
                        $result = createSubletPurchaseOrdersWithTracking($pdo, $planId, $subletItems, $purchaseDate);
                        if ($result['success']) {
                            $success = "New PO created for " . $regenPartNo;
                            $planDetails = getProcurementPlanDetails($pdo, $planId);
                        } else {
                            $error = $result['error'] ?? 'Failed to create PO';
                        }
                    } else {
                        $error = "No supplier found for " . $regenPartNo;
                    }
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }

    if ($action === 'cancel_linked_po') {
        $cancelPoNo = $_POST['cancel_po_no'] ?? '';
        if ($cancelPoNo && in_array($planDetails['status'], ['approved', 'partiallyordered'])) {
            try {
                $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE po_no = ?")->execute([$cancelPoNo]);
                $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'po_cancelled' WHERE plan_id = ? AND created_po_no = ?")->execute([$planId, $cancelPoNo]);
                $success = "PO " . $cancelPoNo . " cancelled.";
                $planDetails = getProcurementPlanDetails($pdo, $planId);
            } catch (Exception $e) {
                $error = "Error cancelling PO: " . $e->getMessage();
            }
        }
    }
}

$poItems = getPlanPurchaseOrderItems($pdo, $planId);
$planIsCompleted = ($planDetails['status'] === 'completed');

// Clear stale links if plan is NOT completed
if (!$planIsCompleted) {
    foreach ($poItems as &$poItem) {
        if (!empty($poItem['created_po_id'])) {
            try {
                $chkStmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                $chkStmt->execute([$poItem['created_po_id']]);
                $poRealStatus = $chkStmt->fetchColumn();
                if ($poRealStatus && $poRealStatus === 'cancelled') {
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

// Refresh real-time stock for PO items + fetch actual PO status
foreach ($poItems as &$poItem) {
    try {
        $poItem['current_stock'] = (int)getAvailableStock($pdo, $poItem['part_no'], $planId);
        $poItem['raw_shortage'] = max(0, $poItem['required_qty'] - $poItem['current_stock']);
        $provisional = getProvisionalStockForPlan($pdo, $poItem['part_no'], $planId);
        $poItem['provisional_stock'] = $provisional;
        $effectiveStock = $poItem['current_stock'] + $provisional;
        $poItem['shortage'] = max(0, $poItem['required_qty'] - $effectiveStock);
    } catch (Exception $e) {}
    if (!empty($poItem['created_po_id'])) {
        try {
            $poStatusStmt = $pdo->prepare("SELECT po.status, po.supplier_id, s.supplier_name FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?");
            $poStatusStmt->execute([$poItem['created_po_id']]);
            $poLive = $poStatusStmt->fetch(PDO::FETCH_ASSOC);
            $actualPoStatus = $poLive['status'] ?? null;
            if ($actualPoStatus) {
                $poItem['actual_po_status'] = $actualPoStatus;
            }
            if ($poLive && !empty($poLive['supplier_name'])) {
                $poItem['supplier_name'] = $poLive['supplier_name'];
                $poItem['supplier_id'] = $poLive['supplier_id'];
            }
            if (!in_array($actualPoStatus, ['closed', 'received']) && !empty($poItem['created_po_no'])) {
                $fallbackStmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE po_no = ? AND part_no = ? AND status IN ('closed', 'received') LIMIT 1");
                $fallbackStmt->execute([$poItem['created_po_no'], $poItem['part_no']]);
                $fallbackStatus = $fallbackStmt->fetchColumn();
                if ($fallbackStatus) {
                    $poItem['actual_po_status'] = $fallbackStatus;
                } else {
                    $allClosedStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('closed', 'received') THEN 1 ELSE 0 END) as closed_count FROM purchase_orders WHERE po_no = ?");
                    $allClosedStmt->execute([$poItem['created_po_no']]);
                    $poSummary = $allClosedStmt->fetch(PDO::FETCH_ASSOC);
                    if ($poSummary && $poSummary['total'] > 0 && $poSummary['total'] == $poSummary['closed_count']) {
                        $poItem['actual_po_status'] = 'closed';
                    }
                }
            }
        } catch (Exception $e) {}
    }
}
unset($poItem);

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

// Calculate PO progress
$poTotal = count($poItems);
$poDoneParts = 0;
foreach ($poItems as $pi) {
    if ($planIsCompleted) {
        $poDoneParts++;
    } elseif (!empty($pi['created_po_id'])) {
        $actualPoSt = $pi['actual_po_status'] ?? '';
        $poSt = $pi['status'] ?? '';
        if (in_array($actualPoSt, ['closed', 'received']) || in_array($poSt, ['received', 'closed'])) {
            $poDoneParts++;
        } elseif ($pi['shortage'] <= 0) {
            $poDoneParts++;
        }
    } elseif ($pi['shortage'] <= 0) {
        $poDoneParts++;
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

// Fetch SO details
$viewSoNos = [];
if (!empty($planDetails['so_list'])) {
    $viewSoNos = array_filter(array_map('trim', explode(',', $planDetails['so_list'])));
}
try {
    $soStmt = $pdo->prepare("SELECT DISTINCT so_list FROM procurement_plan_po_items WHERE plan_id = ? AND so_list IS NOT NULL AND so_list != ''");
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

<!DOCTYPE html>
<html>
<head>
    <title>Procurement Planning - <?= htmlspecialchars($planDetails['plan_no']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">

    <!-- Navigation -->
    <div style="margin-bottom: 15px;">
        <a href="procurement_planning.php" style="color: #2563eb; text-decoration: none; font-size: 0.9em;">&larr; Back to Procurement Planning</a>
        <span style="color: #ccc; margin: 0 8px;">|</span>
        <a href="/procurement/view.php?id=<?= $planId ?>" style="color: #9333ea; text-decoration: none; font-size: 0.9em;">View Full Plan in PPP &rarr;</a>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0; margin-bottom: 5px;"><?= htmlspecialchars($planDetails['plan_no']) ?> — Purchase Orders</h2>
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
        <div style="padding: 15px; background: #fffbeb; border-radius: 8px; border-left: 4px solid #f59e0b;">
            <div style="color: #666; font-size: 0.9em;">Total PO Items</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #d97706;"><?= $poTotal ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">Est. Cost</div>
            <div style="font-size: 1.8em; font-weight: bold; color: #059669;">&pound; <?= number_format($planDetails['total_estimated_cost'] ?? 0, 2) ?></div>
        </div>

        <div style="padding: 15px; background: #f3f4f6; border-radius: 8px;">
            <div style="color: #666; font-size: 0.9em;">PO Progress</div>
            <?php
            if ($planDetails['status'] === 'completed') {
                $percentage = 100;
            } elseif ($planDetails['status'] === 'cancelled') {
                $percentage = 0;
            } else {
                $percentage = $poTotal > 0 ? round(($poDoneParts / $poTotal) * 100) : 0;
            }
            $pColor = $percentage >= 100 ? '#16a34a' : ($percentage > 0 ? '#f59e0b' : '#6b7280');
            ?>
            <div style="font-size: 1.8em; font-weight: bold; color: <?= $pColor ?>;"><?= $percentage ?>%</div>
            <div style="font-size: 0.75em; color: #666; margin-top: 2px;">
                <?= $poDoneParts ?>/<?= $poTotal ?> parts done
            </div>
        </div>
    </div>

    <?php if (!empty($poItems)): ?>
    <!-- Purchase Order Parts Section -->
    <div class="form-section" style="border-top: 3px solid #f59e0b; padding-top: 20px;">
        <h3 style="color: #d97706; margin-bottom: 15px;">
            <span style="background: #fef3c7; padding: 4px 12px; border-radius: 20px;">
                Purchase Order Parts (Sublet/External)
            </span>
            <?php
            $poOrderedCount = 0;
            $poPendingCount = 0;
            $poInStockCount = 0;
            $poCancelledCount = 0;
            $poReceivedCount = 0;
            if ($planIsCompleted) {
                $poOrderedCount = count($poItems);
            } else {
                foreach ($poItems as $pi) {
                    $piActualPoSt = $pi['actual_po_status'] ?? '';
                    $piHasPO = !empty($pi['created_po_id']) && ($pi['status'] ?? '') !== 'po_cancelled';
                    if (($pi['status'] ?? '') === 'po_cancelled') {
                        $poCancelledCount++;
                    } elseif ($piHasPO && in_array($piActualPoSt, ['closed', 'received'])) {
                        $poReceivedCount++;
                    } elseif ($piHasPO) {
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
                    <?php if ($poReceivedCount): ?>
                        <span style="color: #059669; font-weight: 600;"><?= $poReceivedCount ?> Received</span> |
                    <?php endif; ?>
                    <span style="color: #16a34a;"><?= $poOrderedCount ?> Ordered</span> |
                    <span style="color: #10b981;"><?= $poInStockCount ?> In Stock</span> |
                    <?php if ($poCancelledCount): ?>
                        <span style="color: #dc2626;"><?= $poCancelledCount ?> PO Cancelled</span> |
                    <?php endif; ?>
                    <span style="color: #f59e0b;"><?= $poPendingCount ?> Pending</span>
                <?php endif; ?>
            </span>
        </h3>

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
            <?php if ($poReceivedCount): ?>
            <button type="button" class="filter-btn" data-filter="received" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: white; color: #059669; cursor: pointer; font-size: 0.85em;">
                Received (<?= $poReceivedCount ?>)
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
                        <th>PO No</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poItems as $idx => $item):
                        $isCancelled = ($item['status'] ?? '') === 'po_cancelled';
                        $hasPO = !empty($item['created_po_id']) && !$isCancelled;
                        $isPOClosed = $hasPO && in_array($item['actual_po_status'] ?? '', ['closed', 'received']);
                        $cancelledPoNo = $item['cancelled_po_no'] ?? $item['created_po_no'] ?? '';
                        if ($planIsCompleted) { $poRowBg = '#f3f4f6'; }
                        elseif ($isCancelled) { $poRowBg = '#fef2f2'; }
                        elseif ($isPOClosed) { $poRowBg = '#dcfce7'; }
                        elseif ($hasPO) { $poRowBg = '#dcfce7'; }
                        else { $poRowBg = $idx % 2 ? '#fffbeb' : '#fef9e7'; }
                        $poCoveredByProvisional = $hasPO && !$isPOClosed && $item['shortage'] <= 0 && ($item['provisional_stock'] ?? 0) > 0;
                        if ($planIsCompleted) { $poRowFilter = 'closed'; }
                        elseif ($isCancelled) { $poRowFilter = 'cancelled'; }
                        elseif ($isPOClosed) { $poRowFilter = 'received'; }
                        elseif ($poCoveredByProvisional) { $poRowFilter = 'in_stock'; }
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
                                <?php if (($item['provisional_stock'] ?? 0) > 0 && ($item['raw_shortage'] ?? 0) > 0): ?>
                                    <br><small style="color: #2563eb; font-weight: normal; font-size: 0.75em;" title="PO on order covers shortage">
                                        PO on order: <?= $item['provisional_stock'] ?>
                                    </small>
                                <?php endif; ?>
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
                                <?php if ($hasPO || $isCancelled): ?>
                                    <?php if ($isCancelled && $cancelledPoNo): ?>
                                        <span style="color: #dc2626; text-decoration: line-through; font-size: 0.85em;"><?= htmlspecialchars($cancelledPoNo) ?></span>
                                    <?php elseif ($hasPO): ?>
                                        <a href="/purchase/view.php?po_no=<?= urlencode($item['created_po_no']) ?>" style="color: #2563eb; font-weight: 600; font-size: 0.85em;">
                                            <?= htmlspecialchars($item['created_po_no']) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($planIsCompleted): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Closed
                                    </span>
                                <?php elseif ($isCancelled): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #dc2626; color: white; border-radius: 15px; font-size: 0.8em;">
                                        PO Cancelled
                                    </span>
                                <?php elseif ($isPOClosed): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #10b981; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Received
                                    </span>
                                <?php elseif ($hasPO): ?>
                                    <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                        Ordered
                                    </span>
                                    <?php if ($poCoveredByProvisional): ?>
                                        <br><small style="color: #2563eb; font-weight: 600;">Covered by PO</small>
                                    <?php endif; ?>
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
                                <?php elseif ($isCancelled && in_array($planDetails['status'], ['approved', 'partiallyordered'])): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="regenerate_po">
                                        <input type="hidden" name="regen_part_no" value="<?= htmlspecialchars($item['part_no']) ?>">
                                        <input type="hidden" name="purchase_date" value="<?= date('Y-m-d') ?>">
                                        <button type="submit" class="btn btn-sm" style="background: #dc2626; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                onclick="return confirm('Generate new PO for <?= htmlspecialchars($item['part_no']) ?>?');">
                                            New PO
                                        </button>
                                    </form>
                                <?php elseif ($isPOClosed): ?>
                                    <a href="/purchase/view.php?po_no=<?= urlencode($item['created_po_no']) ?>"
                                       class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                        View PO
                                    </a>
                                <?php elseif ($hasPO): ?>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <a href="/purchase/view.php?po_no=<?= urlencode($item['created_po_no']) ?>"
                                           class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                            View PO
                                        </a>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_linked_po">
                                            <input type="hidden" name="cancel_po_no" value="<?= htmlspecialchars($item['created_po_no']) ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #dc2626; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                    onclick="return confirm('Cancel PO <?= htmlspecialchars($item['created_po_no']) ?>?');">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($item['shortage'] > 0 && in_array($planDetails['status'], ['approved', 'partiallyordered'])): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="create_po">
                                        <input type="hidden" name="po_part_no" value="<?= htmlspecialchars($item['part_no']) ?>">
                                        <input type="hidden" name="purchase_date" value="<?= date('Y-m-d') ?>">
                                        <button type="submit" class="btn btn-sm" style="background: #f59e0b; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                onclick="return confirm('Create PO for <?= htmlspecialchars($item['part_no']) ?>?');">
                                            Create PO
                                        </button>
                                    </form>
                                <?php elseif ($item['shortage'] > 0): ?>
                                    <span style="color: #f59e0b;">Needs PO</span>
                                <?php else: ?>
                                    <span style="color: #16a34a;">OK</span>
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
            <h4 style="margin: 0 0 15px 0; color: #d97706;">Shop Order Wise Purchase Order Summary</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($poItemsBySO as $soNo => $soItems):
                    $allDone = $planIsCompleted;
                    $soOrdered = 0; $soPending = 0; $soInStock = 0; $soCancelled = 0;
                    $soReceived = 0;
                    if (!$planIsCompleted) {
                        foreach ($soItems as $si) {
                            $siActualPoStatus = $si['actual_po_status'] ?? '';
                            if (($si['status'] ?? '') === 'po_cancelled') { $soCancelled++; }
                            elseif (!empty($si['created_po_id']) && in_array($siActualPoStatus, ['closed', 'received'])) { $soReceived++; $soInStock++; }
                            elseif (!empty($si['created_po_id']) && ($si['status'] ?? '') !== 'po_cancelled') { $soOrdered++; }
                            elseif (($si['shortage'] ?? 0) <= 0) { $soInStock++; }
                            else { $soPending++; }
                        }
                        $allDone = ($soPending == 0 && $soCancelled == 0);
                    }
                ?>
                <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid <?= $soCancelled > 0 ? '#dc2626' : ($allDone ? '#10b981' : '#f59e0b') ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <a href="/sales_orders/view.php?so_no=<?= urlencode($soNo) ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                            <?= htmlspecialchars($soNo) ?>
                        </a>
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
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #666;">
        <p>No purchase order items in this plan.</p>
        <a href="/procurement/view.php?id=<?= $planId ?>" class="btn btn-primary">View Full Plan in PPP</a>
    </div>
    <?php endif; ?>

</div>

<script>
// Filter functionality
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.dataset.filter;
        const table = document.getElementById('po-table');
        if (!table) return;

        // Update active button
        document.querySelectorAll('#po-filters .filter-btn').forEach(b => {
            b.style.background = 'white';
            b.style.color = b.dataset.filter === 'ordered' ? '#16a34a' :
                            b.dataset.filter === 'received' ? '#059669' :
                            b.dataset.filter === 'cancelled' ? '#dc2626' :
                            b.dataset.filter === 'in_stock' ? '#10b981' :
                            b.dataset.filter === 'pending' ? '#f59e0b' : '#333';
        });
        this.style.background = '#d97706';
        this.style.color = 'white';

        // Filter rows
        table.querySelectorAll('tbody tr').forEach(row => {
            if (filter === 'all') {
                row.style.display = '';
            } else {
                row.style.display = row.dataset.status === filter ? '' : 'none';
            }
        });
    });
});
</script>

</body>
</html>
