<?php
// Start session first before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../db.php';
require '../includes/auth.php';
requireLogin();
require '../includes/procurement_helper.php';

$step = isset($_GET['step']) ? $_GET['step'] : 1;
$error = '';
$success = '';
$planId = null;
$planNo = null;

// Auto-migrate: convert legacy 'completed' SO status to 'closed'
try {
    $pdo->exec("UPDATE sales_orders SET status = 'closed' WHERE status = 'completed'");
} catch (PDOException $e) {}

// Step 1: Select Sales Orders
if ($step == 1) {
    ensureStockBlocksTable($pdo);
    $openSOs = $pdo->query("
        SELECT
            so.so_no,
            so.part_no,
            so.qty,
            so.sales_date,
            so.stock_status,
            COALESCE(c.company_name, 'N/A') AS company_name,
            COALESCE(p.part_name, so.part_no) AS part_name,
            COALESCE(i.qty, 0) AS actual_stock,
            GREATEST(0, COALESCE(i.qty, 0) - COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0)) AS current_stock,
            COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0) AS blocked_qty
        FROM sales_orders so
        LEFT JOIN customers c ON c.id = so.customer_id
        LEFT JOIN part_master p ON p.part_no = so.part_no
        LEFT JOIN inventory i ON so.part_no = i.part_no
        LEFT JOIN invoice_master inv ON inv.so_no = so.so_no AND inv.status = 'released'
        WHERE so.status NOT IN ('cancelled', 'closed', 'completed')
        AND inv.id IS NULL
        ORDER BY so.status ASC, so.sales_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_so') {
        $rawSelections = $_POST['selected_so'] ?? [];

        if (empty($rawSelections)) {
            $error = "Please select at least one sales order / product";
        } else {
            $uniqueSOs = [];
            $selectedPartNos = [];
            foreach ($rawSelections as $val) {
                if (strpos($val, '::') !== false) {
                    list($soNo, $partNo) = explode('::', $val, 2);
                    $soNo = trim($soNo);
                    $partNo = trim($partNo);
                    if (!in_array($soNo, $uniqueSOs)) {
                        $uniqueSOs[] = $soNo;
                    }
                    $selectedPartNos[] = $partNo;
                } else {
                    $soNo = trim($val);
                    if (!in_array($soNo, $uniqueSOs)) {
                        $uniqueSOs[] = $soNo;
                    }
                }
            }
            $selectedPartNos = array_values(array_unique($selectedPartNos));

            $_SESSION['wo_selected_sos'] = $uniqueSOs;
            $_SESSION['wo_selected_part_nos'] = $selectedPartNos;
            header("Location: create.php?step=2");
            exit;
        }
    }
}

// Step 2: Generate WO Plan & Show Work Order Items
if ($step == 2) {
    $selectedSOs = $_SESSION['wo_selected_sos'] ?? [];
    $selectedPartNos = $_SESSION['wo_selected_part_nos'] ?? [];

    if (empty($selectedSOs)) {
        header("Location: create.php?step=1");
        exit;
    }

    // Get or create a WO planning plan for these selected SOs
    $planResult = getOrCreatePlanForSOs($pdo, $selectedSOs, 'wo_planning');
    $currentPlanId = $planResult['plan_id'] ?? null;
    $currentPlanNo = $planResult['plan_no'] ?? '';
    $isExistingPlan = $planResult['is_existing'] ?? false;

    // Auto-close plan if all linked SOs are released
    $planIsCompleted = false;
    if ($currentPlanId) {
        autoClosePlanIfAllSOsReleased($pdo, $currentPlanId);
        $planStatusStmt = $pdo->prepare("SELECT status FROM procurement_plans WHERE id = ?");
        $planStatusStmt->execute([$currentPlanId]);
        $currentPlanStatus = $planStatusStmt->fetchColumn();
        $planIsCompleted = ($currentPlanStatus === 'completed');
    }

    // Get work order parts (child parts that go to Work Order)
    $workOrderParts = getWorkOrderPartsForSalesOrders($pdo, $selectedSOs, $selectedPartNos);

    // Prepare work order items
    $workOrderItems = [];
    foreach ($workOrderParts as $wp) {
        $currentStock = (int)getAvailableStock($pdo, $wp['part_no']);
        $shortage = max(0, $wp['total_required_qty'] - $currentStock);

        $workOrderItems[] = [
            'part_no' => $wp['part_no'],
            'part_name' => $wp['part_name'],
            'part_id' => $wp['part_id'] ?? 0,
            'uom' => $wp['uom'] ?? 'PCS',
            'so_list' => $wp['so_list'],
            'current_stock' => $currentStock,
            'demand_qty' => $wp['total_required_qty'],
            'shortage' => $shortage,
            'source' => $wp['source'] ?? 'BOM Child',
            'parent_part_no' => $wp['parent_part_no'] ?? '-',
            'parent_part_name' => $wp['parent_part_name'] ?? '-'
        ];
    }

    // Save WO items to tracking tables for this plan
    if ($currentPlanId && !empty($workOrderItems)) {
        savePlanWorkOrderItems($pdo, $currentPlanId, $workOrderItems);
    }

    // Load existing tracking status for WO items
    $woItemStatus = [];
    if ($currentPlanId) {
        $woTracking = getPlanWorkOrderItems($pdo, $currentPlanId);
        foreach ($woTracking as $wt) {
            $woItemStatus[$wt['part_no']] = $wt;
        }

        // Only clear stale links if plan is NOT completed
        if (!$planIsCompleted) {
            foreach ($woItemStatus as $partNo => &$ws) {
                if (!empty($ws['created_wo_id'])) {
                    try {
                        $chkStmt = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
                        $chkStmt->execute([$ws['created_wo_id']]);
                        $woRealStatus = $chkStmt->fetchColumn();
                        if ($woRealStatus && in_array($woRealStatus, ['closed', 'cancelled'])) {
                            $pdo->prepare("UPDATE procurement_plan_wo_items SET created_wo_id = NULL, created_wo_no = NULL, status = 'pending' WHERE plan_id = ? AND part_no = ?")
                                 ->execute([$currentPlanId, $partNo]);
                            $ws['created_wo_id'] = null;
                            $ws['created_wo_no'] = null;
                            $ws['status'] = 'pending';
                        }
                    } catch (Exception $e) {}
                }
            }
            unset($ws);
        }
    }

    // Detect work orders created outside (from work_orders module)
    // Skip WOs already fully committed to another plan
    if (!empty($workOrderItems)) {
        foreach ($workOrderItems as $wi) {
            $partNo = $wi['part_no'];
            if (isset($woItemStatus[$partNo]) && !empty($woItemStatus[$partNo]['created_wo_id'])) {
                continue;
            }
            $extWoStmt = $pdo->prepare("
                SELECT id, wo_no, qty, status, plan_id FROM work_orders
                WHERE part_no = ? AND status NOT IN ('closed', 'cancelled')
                ORDER BY id DESC
            ");
            $extWoStmt->execute([$partNo]);
            $extWoCandidates = $extWoStmt->fetchAll(PDO::FETCH_ASSOC);
            $extWo = null;
            foreach ($extWoCandidates as $candidate) {
                $woId = (int)$candidate['id'];
                $thisPlanId = $currentPlanId ?? 0;
                $belongsToOtherPlan = (!empty($candidate['plan_id']) && (int)$candidate['plan_id'] !== $thisPlanId);
                $committedQty = getCommittedQtyForWo($pdo, $woId, $thisPlanId);

                if ($belongsToOtherPlan || $committedQty > 0) {
                    $surplus = (float)$candidate['qty'] - $committedQty;
                    if ($surplus <= 0) {
                        continue;
                    }
                }
                $extWo = $candidate;
                break;
            }
            if ($extWo) {
                if ($currentPlanId) {
                    updatePlanWoItemStatus($pdo, $currentPlanId, $partNo, (int)$extWo['id'], $extWo['wo_no']);
                    try {
                        $pdo->prepare("UPDATE work_orders SET plan_id = ? WHERE id = ? AND (plan_id IS NULL OR plan_id = 0)")
                             ->execute([$currentPlanId, $extWo['id']]);
                    } catch (Exception $e) {
                        try {
                            $pdo->exec("ALTER TABLE work_orders ADD COLUMN plan_id INT NULL");
                            $pdo->prepare("UPDATE work_orders SET plan_id = ? WHERE id = ?")
                                 ->execute([$currentPlanId, $extWo['id']]);
                        } catch (Exception $e2) {}
                    }
                }
                $woItemStatus[$partNo] = [
                    'part_no' => $partNo,
                    'created_wo_id' => $extWo['id'],
                    'created_wo_no' => $extWo['wo_no'],
                    'status' => 'in_progress',
                    'actual_wo_status' => $extWo['status']
                ];
            }
        }
    }

    // Fetch actual WO status for tracked items
    foreach ($woItemStatus as $partNo => &$ws) {
        if (!empty($ws['created_wo_id']) && !isset($ws['actual_wo_status'])) {
            try {
                $woActualStmt = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
                $woActualStmt->execute([$ws['created_wo_id']]);
                $actualStatus = $woActualStmt->fetchColumn();
                if ($actualStatus) {
                    $ws['actual_wo_status'] = $actualStatus;
                }
            } catch (Exception $e) {}
        }
    }
    unset($ws);

    // Handle Work Order creation from this page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_wo') {
        $woPartNo = $_POST['wo_part_no'] ?? '';
        $woQty = (float)($_POST['wo_qty'] ?? 0);

        if ($woPartNo && $woQty > 0 && $currentPlanId) {
            $woResult = createWorkOrderWithTracking($pdo, $currentPlanId, $woPartNo, $woQty);
            if ($woResult['success']) {
                $success = $woResult['message'];
                $woTracking = getPlanWorkOrderItems($pdo, $currentPlanId);
                foreach ($woTracking as $wt) {
                    $woItemStatus[$wt['part_no']] = $wt;
                }
            } else {
                $error = $woResult['error'] ?? 'Failed to create Work Order';
            }
        } else {
            $error = "Invalid Work Order data";
        }
    }

    // Handle SO-wise bulk Work Order creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_all_wo_for_so') {
        $targetSO = $_POST['target_so'] ?? '';

        if ($targetSO && $currentPlanId && !empty($workOrderItems)) {
            $woResult = createAllWorkOrdersForSO($pdo, $currentPlanId, $targetSO, $workOrderItems);
            if ($woResult['success']) {
                $success = $woResult['message'];
                if (!empty($woResult['created_wos'])) {
                    $_SESSION['created_wos_for_so'] = $woResult['created_wos'];
                }
                $woTracking = getPlanWorkOrderItems($pdo, $currentPlanId);
                foreach ($woTracking as $wt) {
                    $woItemStatus[$wt['part_no']] = $wt;
                }
            } else {
                $error = $woResult['error'] ?? 'Failed to create Work Orders';
            }
        } else {
            $error = "Invalid SO or no Work Order items";
        }
    }

    // Group items by SO for SO-wise planning view
    $woItemsBySO = groupWorkOrderItemsBySO($workOrderItems ?? []);

    // Handle plan approval (set to approved so WOs can be tracked)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_plan') {
        if ($currentPlanId) {
            try {
                $pdo->prepare("UPDATE procurement_plans SET status = 'approved', approved_at = NOW() WHERE id = ? AND status = 'draft'")
                     ->execute([$currentPlanId]);
                $success = "WO Plan approved successfully";
                $currentPlanStatus = 'approved';
            } catch (Exception $e) {
                $error = "Failed to approve plan: " . $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Work Order Planning - Create Plan</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script>
        function selectAll(checkbox) {
            document.querySelectorAll('input[name="selected_so[]"]').forEach(cb => cb.checked = checkbox.checked);
            document.querySelectorAll('.so-group-toggle').forEach(cb => cb.checked = checkbox.checked);
        }

        function toggleSoGroup(groupCheckbox, soId) {
            document.querySelectorAll('.so-group-' + soId).forEach(cb => cb.checked = groupCheckbox.checked);
        }

        function updateGroupToggle(soId) {
            var children = document.querySelectorAll('.so-group-' + soId);
            var toggle = document.querySelector('.so-group-toggle[data-so="' + soId + '"]');
            if (!toggle) return;
            var allChecked = true;
            var anyChecked = false;
            children.forEach(cb => {
                if (cb.checked) anyChecked = true;
                else allChecked = false;
            });
            toggle.checked = allChecked;
            toggle.indeterminate = anyChecked && !allChecked;
        }
    </script>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <h2>Create Work Order Plan</h2>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- STEP 1: Select Sales Orders -->
    <?php if ($step == 1): ?>

    <div class="form-section">
        <h3>Step 1: Select Open Sales Orders & Products</h3>
        <p style="color: #666; margin-bottom: 15px;">
            Choose which sales orders and products to include in this WO plan. Work Orders will be generated for parts that need internal production.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="select_so">

            <?php if (empty($openSOs)): ?>
                <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
                    <p>No open sales orders found. Create a sales order first.</p>
                    <a href="/sales_orders/index.php" class="btn btn-primary" style="margin-top: 10px;">Go to Sales Orders</a>
                </div>
            <?php else: ?>
                <?php
                $soGroups = [];
                foreach ($openSOs as $so) {
                    $soGroups[$so['so_no']][] = $so;
                }
                ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" onchange="selectAll(this)" title="Select/Deselect all">
                        Select All
                    </label>
                </div>

                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th>
                                <th>SO No</th>
                                <th>Customer</th>
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Qty</th>
                                <th>Stock</th>
                                <th>Blocked</th>
                                <th>Available</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($soGroups as $soNo => $soItems):
                                $isMultiProduct = count($soItems) > 1;
                                $soId = preg_replace('/[^a-zA-Z0-9]/', '_', $soNo);
                            ?>
                                <?php if ($isMultiProduct): ?>
                                    <tr style="background: #ecfdf5; border-top: 2px solid #10b981;">
                                        <td>
                                            <input type="checkbox" class="so-group-toggle" data-so="<?= htmlspecialchars($soId) ?>"
                                                   onchange="toggleSoGroup(this, '<?= htmlspecialchars($soId) ?>')">
                                        </td>
                                        <td colspan="2">
                                            <strong style="color: #059669; font-size: 1.05em;"><?= htmlspecialchars($soNo) ?></strong>
                                            <span style="color: #10b981; font-size: 0.85em; margin-left: 8px;"><?= htmlspecialchars($soItems[0]['company_name']) ?></span>
                                            <span style="display: inline-block; padding: 2px 8px; background: #10b98120; color: #10b981; border-radius: 10px; font-size: 0.8em; margin-left: 8px;">
                                                <?= count($soItems) ?> products
                                            </span>
                                        </td>
                                        <td colspan="7" style="color: #10b981; font-size: 0.85em;">
                                            Select all or choose specific products below
                                        </td>
                                    </tr>
                                    <?php foreach ($soItems as $so): ?>
                                    <tr style="background: #f0fdf4;">
                                        <td style="padding-left: 25px;">
                                            <input type="checkbox" name="selected_so[]"
                                                   value="<?= htmlspecialchars($so['so_no']) ?>::<?= htmlspecialchars($so['part_no']) ?>"
                                                   class="so-product-cb so-group-<?= htmlspecialchars($soId) ?>"
                                                   onchange="updateGroupToggle('<?= htmlspecialchars($soId) ?>')">
                                        </td>
                                        <td style="padding-left: 25px; color: #888; font-size: 0.9em;"><?= htmlspecialchars($so['so_no']) ?></td>
                                        <td style="color: #888; font-size: 0.9em;"><?= htmlspecialchars($so['company_name']) ?></td>
                                        <td><strong><?= htmlspecialchars($so['part_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($so['part_name']) ?></td>
                                        <td><?= $so['qty'] ?></td>
                                        <td><?= $so['actual_stock'] ?></td>
                                        <td style="color: <?= $so['blocked_qty'] > 0 ? '#dc2626' : '#666' ?>; font-weight: <?= $so['blocked_qty'] > 0 ? '600' : 'normal' ?>;">
                                            <?= $so['blocked_qty'] > 0 ? $so['blocked_qty'] : '-' ?>
                                        </td>
                                        <td style="font-weight: 600; color: <?= $so['current_stock'] > 0 ? '#16a34a' : '#dc2626' ?>;">
                                            <?= $so['current_stock'] ?>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($so['sales_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php $so = $soItems[0]; ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_so[]"
                                                   value="<?= htmlspecialchars($so['so_no']) ?>::<?= htmlspecialchars($so['part_no']) ?>"
                                                   class="so-product-cb">
                                        </td>
                                        <td><strong><?= htmlspecialchars($so['so_no']) ?></strong></td>
                                        <td><?= htmlspecialchars($so['company_name']) ?></td>
                                        <td><?= htmlspecialchars($so['part_no']) ?></td>
                                        <td><?= htmlspecialchars($so['part_name']) ?></td>
                                        <td><?= $so['qty'] ?></td>
                                        <td><?= $so['actual_stock'] ?></td>
                                        <td style="color: <?= $so['blocked_qty'] > 0 ? '#dc2626' : '#666' ?>; font-weight: <?= $so['blocked_qty'] > 0 ? '600' : 'normal' ?>;">
                                            <?= $so['blocked_qty'] > 0 ? $so['blocked_qty'] : '-' ?>
                                        </td>
                                        <td style="font-weight: 600; color: <?= $so['current_stock'] > 0 ? '#16a34a' : '#dc2626' ?>;">
                                            <?= $so['current_stock'] ?>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($so['sales_date'])) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Next: Generate WO Plan</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- STEP 2: Review Work Order Items -->
    <?php elseif ($step == 2): ?>

    <div class="form-section">
        <h3>Step 2: Review Work Order Items</h3>
        <p style="color: #666; margin-bottom: 15px;">
            These parts need internal production via Work Orders. You can create WOs individually or by Sales Order.
        </p>

        <!-- Plan Info Banner -->
        <?php if ($currentPlanId): ?>
        <div style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 15px; border-radius: 8px; margin-bottom: 15px; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="font-size: 1.1em;">WO Plan: <?= htmlspecialchars($currentPlanNo) ?></strong>
                    <?php if ($isExistingPlan): ?>
                        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 10px;">Existing Plan</span>
                    <?php else: ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 10px;">New Plan</span>
                    <?php endif; ?>
                    <?php
                    $planStatusStmt2 = $pdo->prepare("SELECT status FROM procurement_plans WHERE id = ?");
                    $planStatusStmt2->execute([$currentPlanId]);
                    $displayStatus = $planStatusStmt2->fetchColumn();
                    ?>
                    <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 5px;">
                        <?= ucfirst($displayStatus) ?>
                    </span>
                </div>
                <div style="font-size: 0.9em;">
                    SOs: <?= htmlspecialchars(implode(', ', $selectedSOs)) ?>
                    <?php if (!empty($selectedPartNos)): ?>
                        <br><span style="opacity: 0.8; font-size: 0.9em;">Products: <?= htmlspecialchars(implode(', ', $selectedPartNos)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top: 10px; font-size: 0.85em; opacity: 0.9;">
                WO Items: <?= count($workOrderItems ?? []) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($workOrderItems)): ?>
            <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
                <p>No Work Order items found for the selected sales orders. The selected products may not have BOM parts that require internal production.</p>
                <a href="create.php?step=1" class="btn btn-secondary" style="margin-top: 10px;">Back to Select Orders</a>
            </div>
        <?php endif; ?>

        <?php if ($planIsCompleted): ?>
        <!-- Plan Completed Banner -->
        <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #dcfce7, #d1fae5); border: 2px solid #16a34a; border-radius: 10px; text-align: center;">
            <h3 style="color: #16a34a; margin: 0 0 8px 0;">WO Plan Completed - All SOs Released</h3>
            <p style="color: #059669; margin: 0;">All linked Sales Orders have been released. This WO plan is now closed.</p>
            <a href="view.php?id=<?= $currentPlanId ?>" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">View Plan Details</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($workOrderItems)): ?>
        <!-- Work Order Parts Section -->
        <div style="margin-top: 20px; padding-top: 15px; border-top: 3px solid #10b981;">
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
                    $woClosedCount = count($workOrderItems);
                } else {
                    foreach ($workOrderItems as $wi) {
                        $woSt = $woItemStatus[$wi['part_no']] ?? null;
                        $actualWoSt = $woSt['actual_wo_status'] ?? '';
                        if ($woSt && $woSt['created_wo_id']) {
                            if (in_array($actualWoSt, ['closed'])) {
                                $woClosedCount++;
                            } elseif (in_array($actualWoSt, ['completed', 'qc_approval'])) {
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
                        <span style="color: #16a34a; font-weight: 600;">All <?= count($workOrderItems) ?> Closed (SOs Released)</span>
                    <?php else: ?>
                        <?php if ($woClosedCount): ?><span style="color: #6b7280;"><?= $woClosedCount ?> Closed</span> | <?php endif; ?>
                        <?php if ($woCompletedCount): ?><span style="color: #16a34a;"><?= $woCompletedCount ?> Completed</span> | <?php endif; ?>
                        <span style="color: #3b82f6;"><?= $woInProgressCount ?> In Progress</span> |
                        <span style="color: #10b981;"><?= $woInStockCount ?> In Stock</span> |
                        <span style="color: #f59e0b;"><?= $woPendingCount ?> Pending</span>
                    <?php endif; ?>
                </span>
            </h3>
            <p style="color: #666; margin-bottom: 15px;">
                Parts with Part ID in [<?= htmlspecialchars(implode(', ', getWorkOrderPartIds())) ?>] will be produced <strong>internally via Work Orders</strong>.
            </p>

            <!-- WO Filter Buttons -->
            <?php if (!$planIsCompleted): ?>
            <div style="margin-bottom: 12px; display: flex; gap: 6px; flex-wrap: wrap;" id="wo-filters">
                <button type="button" class="filter-btn active" data-filter="all" data-target="wo" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: #059669; color: white; cursor: pointer; font-size: 0.85em;">
                    All (<?= count($workOrderItems) ?>)
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
                            <th>Source</th>
                            <th>Parent/SO</th>
                            <th>Available Stock</th>
                            <th>Required</th>
                            <th>Shortage</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workOrderItems as $idx => $item):
                            $woStatus = $woItemStatus[$item['part_no']] ?? null;
                            $hasWO = $woStatus && $woStatus['created_wo_id'];
                            $itemActualWoSt = $woStatus['actual_wo_status'] ?? '';
                            $itemIsClosed = $planIsCompleted || in_array($itemActualWoSt, ['closed']);
                            $itemIsCompleted = in_array($itemActualWoSt, ['completed', 'qc_approval']);
                            $rowBg = $itemIsClosed ? '#f3f4f6' : ($itemIsCompleted ? '#dcfce7' : ($hasWO ? '#dbeafe' : ($idx % 2 ? '#ecfdf5' : '#f0fdf4')));
                            if ($planIsCompleted) { $rowFilterStatus = 'closed'; }
                            elseif ($hasWO && in_array($itemActualWoSt, ['closed'])) { $rowFilterStatus = 'closed'; }
                            elseif ($hasWO && in_array($itemActualWoSt, ['completed', 'qc_approval'])) { $rowFilterStatus = 'completed'; }
                            elseif ($hasWO) { $rowFilterStatus = 'in_progress'; }
                            elseif ($item['shortage'] <= 0) { $rowFilterStatus = 'in_stock'; }
                            else { $rowFilterStatus = 'pending'; }
                        ?>
                            <tr style="background: <?= $rowBg ?>;" data-status="<?= $rowFilterStatus ?>">
                                <td><?= htmlspecialchars($item['part_no']) ?></td>
                                <td><?= htmlspecialchars($item['part_name']) ?></td>
                                <td><strong><?= $item['part_id'] ?></strong></td>
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;
                                        background: <?= ($item['source'] ?? '') === 'Direct SO Part' ? '#dbeafe' : '#fef3c7' ?>;
                                        color: <?= ($item['source'] ?? '') === 'Direct SO Part' ? '#1e40af' : '#92400e' ?>;">
                                        <?= htmlspecialchars($item['source'] ?? 'BOM Child') ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: #666;">
                                        <?php if (($item['parent_part_no'] ?? '-') !== '-'): ?>
                                            Parent: <?= htmlspecialchars($item['parent_part_no']) ?><br>
                                        <?php endif; ?>
                                        SO: <?= htmlspecialchars($item['so_list']) ?>
                                    </small>
                                </td>
                                <td><?= $item['current_stock'] ?></td>
                                <td><?= $item['demand_qty'] ?></td>
                                <td style="color: <?= $item['shortage'] > 0 ? '#dc2626' : '#16a34a' ?>; font-weight: bold;">
                                    <?= $item['shortage'] ?>
                                </td>
                                <td>
                                    <?php if ($planIsCompleted): ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                            Closed
                                        </span>
                                    <?php elseif ($hasWO):
                                        $actualWoSt = $woStatus['actual_wo_status'] ?? '';
                                        if (in_array($actualWoSt, ['closed'])):
                                    ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #6b7280; color: white; border-radius: 15px; font-size: 0.8em;">
                                            Closed
                                        </span>
                                    <?php elseif (in_array($actualWoSt, ['completed', 'qc_approval'])): ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                            Completed
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #3b82f6; color: white; border-radius: 15px; font-size: 0.8em;">
                                            In Progress
                                        </span>
                                    <?php endif; ?>
                                        <br><small style="color: #059669;"><?= htmlspecialchars($woStatus['created_wo_no']) ?></small>
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
                                        <a href="/work_orders/view.php?id=<?= $woStatus['created_wo_id'] ?>"
                                           class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;">
                                            View WO
                                        </a>
                                    <?php elseif ($item['shortage'] > 0): ?>
                                        <form method="post" action="create.php?step=2" style="display: inline;">
                                            <input type="hidden" name="action" value="create_wo">
                                            <input type="hidden" name="wo_part_no" value="<?= htmlspecialchars($item['part_no']) ?>">
                                            <input type="hidden" name="wo_qty" value="<?= $item['shortage'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 4px 10px; font-size: 0.85em;"
                                                    onclick="return confirm('Create Work Order for <?= htmlspecialchars($item['part_no']) ?>?');">
                                                Create WO
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #16a34a;">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- SO-wise Work Order Planning -->
            <?php if (!empty($woItemsBySO) && count($woItemsBySO) > 0): ?>
            <div style="margin-top: 25px; padding: 20px; background: #ecfdf5; border-radius: 8px; border: 2px solid #10b981;">
                <h4 style="margin: 0 0 15px 0; color: #059669;">
                    Shop Order Wise Work Order Planning
                </h4>

                <?php if (isset($_SESSION['created_wos_for_so']) && !empty($_SESSION['created_wos_for_so'])): ?>
                    <div style="background: #dcfce7; border: 1px solid #16a34a; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <strong style="color: #16a34a;">Work Orders Created:</strong>
                        <?php foreach ($_SESSION['created_wos_for_so'] as $wo): ?>
                            <span style="display: inline-block; background: #16a34a; color: white; padding: 2px 10px; border-radius: 12px; margin: 0 5px;">
                                <?= htmlspecialchars($wo) ?>
                            </span>
                        <?php endforeach; ?>
                        <a href="/work_orders/index.php" class="btn btn-sm" style="margin-left: 10px;">View WOs</a>
                    </div>
                    <?php unset($_SESSION['created_wos_for_so']); ?>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach ($woItemsBySO as $soNo => $soItems):
                        $allDone = $planIsCompleted;
                        $soClosed = 0; $soCompleted = 0; $soInProgress = 0; $soPending = 0; $soInStock = 0;
                        if ($planIsCompleted) {
                            $soClosed = count($soItems);
                        } else {
                            foreach ($soItems as $si) {
                                $status = $woItemStatus[$si['part_no']] ?? null;
                                $siActualSt = $status['actual_wo_status'] ?? '';
                                if ($status && $status['created_wo_id']) {
                                    if (in_array($siActualSt, ['closed'])) { $soClosed++; }
                                    elseif (in_array($siActualSt, ['completed', 'qc_approval'])) { $soCompleted++; }
                                    else { $soInProgress++; }
                                } elseif (($si['shortage'] ?? 0) <= 0) { $soInStock++; }
                                else { $soPending++; }
                            }
                            $allDone = ($soPending == 0 && $soInProgress == 0);
                        }
                    ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 1.1em; color: #1f2937;"><?= htmlspecialchars($soNo) ?></strong>
                            <?php if ($planIsCompleted): ?>
                                <span style="background: #16a34a; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;">SO Released</span>
                            <?php elseif ($allDone): ?>
                                <span style="background: #10b981; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;">Complete</span>
                            <?php else: ?>
                                <span style="background: #f59e0b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;"><?= $soPending + $soInProgress ?> Active</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 12px;">
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
                        <?php if ($planIsCompleted): ?>
                        <span style="color: #16a34a; font-weight: 500;">Plan completed - SO released</span>
                        <?php elseif ($soPending > 0): ?>
                        <form method="post" action="create.php?step=2" style="display: inline;">
                            <input type="hidden" name="action" value="create_all_wo_for_so">
                            <input type="hidden" name="target_so" value="<?= htmlspecialchars($soNo) ?>">
                            <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 6px 15px; font-size: 0.85em; width: 100%;"
                                    onclick="return confirm('Create all Work Orders for <?= htmlspecialchars($soNo) ?>?');">
                                Create All WOs for <?= htmlspecialchars($soNo) ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color: #10b981; font-weight: 500;">All WOs created or in stock</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="create.php?step=1" class="btn btn-secondary">Back to Select Orders</a>
            <?php if ($currentPlanId): ?>
                <a href="view.php?id=<?= $currentPlanId ?>" class="btn btn-primary">View Plan Details</a>
            <?php endif; ?>
        </div>
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
