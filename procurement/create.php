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
    // Get all open/pending sales orders (exclude closed, completed, cancelled, and SOs with released invoices)
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
            // Parse so_no::part_no format
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
                    // Backward compatibility: plain so_no
                    $soNo = trim($val);
                    if (!in_array($soNo, $uniqueSOs)) {
                        $uniqueSOs[] = $soNo;
                    }
                }
            }
            $selectedPartNos = array_values(array_unique($selectedPartNos));

            // Store selection in session and proceed to step 2
            $_SESSION['selected_sos'] = $uniqueSOs;
            $_SESSION['selected_part_nos'] = $selectedPartNos;
            header("Location: create.php?step=2");
            exit;
        }
    }
}

// Step 2: Generate Plan & Show Recommendations
if ($step == 2) {
    $selectedSOs = $_SESSION['selected_sos'] ?? [];
    $selectedPartNos = $_SESSION['selected_part_nos'] ?? [];

    if (empty($selectedSOs)) {
        header("Location: create.php?step=1");
        exit;
    }

    // Get or create a plan for these selected SOs
    $planResult = getOrCreatePlanForSOs($pdo, $selectedSOs);
    $currentPlanId = $planResult['plan_id'] ?? null;
    $currentPlanNo = $planResult['plan_no'] ?? '';
    $isExistingPlan = $planResult['is_existing'] ?? false;

    // Auto-close plan if all linked SOs are released
    $planIsCompleted = false;
    if ($currentPlanId) {
        autoClosePlanIfAllSOsReleased($pdo, $currentPlanId);
        // Check current plan status
        $planStatusStmt = $pdo->prepare("SELECT status FROM procurement_plans WHERE id = ?");
        $planStatusStmt->execute([$currentPlanId]);
        $currentPlanStatus = $planStatusStmt->fetchColumn();
        $planIsCompleted = ($currentPlanStatus === 'completed');
    }

    // Get selected sales orders by part (filtered to selected products)
    $sosByPart = getSelectedSalesOrdersByPart($pdo, $selectedSOs, $selectedPartNos);

    if (empty($sosByPart)) {
        $error = "No matching sales orders found";
    }

    // Prepare plan items with recommendations
    $planItems = [];
    foreach ($sosByPart as $so) {
        $recommendation = calculateProcurementRecommendation(
            $pdo,
            $so['part_no'],
            $so['total_demand_qty']
        );

        $bestSupplier = getBestSupplier($pdo, $so['part_no']);

        if (!$bestSupplier) {
            continue; // Skip if no supplier configured
        }

        $planItems[] = [
            'part_no' => $so['part_no'],
            'part_name' => $so['part_name'],
            'so_list' => $so['so_list'],
            'current_stock' => $recommendation['current_stock'],
            'demand_qty' => $recommendation['demand_qty'],
            'shortage' => $recommendation['shortage'],
            'min_stock_threshold' => $recommendation['min_stock_qty'],
            'recommended_qty' => $recommendation['recommended_qty'],
            'supplier_id' => $bestSupplier['supplier_id'],
            'supplier_name' => $bestSupplier['supplier_name'],
            'suggested_rate' => $bestSupplier['supplier_rate'],
            'uom' => $so['uom'] ?? 'PCS'
        ];
    }

    // Get sublet parts (child parts that go to PO - NOT in Work Order list)
    $subletParts = getSubletPartsForSalesOrders($pdo, $selectedSOs, $selectedPartNos);

    // Get work order parts (child parts that go to Work Order - IDs: 99, 42, 44, 46, 83, 91)
    $workOrderParts = getWorkOrderPartsForSalesOrders($pdo, $selectedSOs, $selectedPartNos);

    // Prepare sublet items with supplier info (these go to Purchase Order)
    $subletItems = [];
    foreach ($subletParts as $sp) {
        $bestSupplier = getBestSupplier($pdo, $sp['part_no']);

        // Get available stock (actual - blocked by other approved plans)
        $currentStock = (int)getAvailableStock($pdo, $sp['part_no']);
        $shortage = max(0, $sp['total_required_qty'] - $currentStock);

        $subletItems[] = [
            'part_no' => $sp['part_no'],
            'part_name' => $sp['part_name'],
            'part_id' => $sp['part_id'] ?? 0,
            'uom' => $sp['uom'] ?? 'PCS',
            'so_list' => $sp['so_list'],
            'current_stock' => $currentStock,
            'demand_qty' => $sp['total_required_qty'],
            'shortage' => $shortage,
            'recommended_qty' => $shortage,
            'supplier_id' => $bestSupplier ? $bestSupplier['supplier_id'] : null,
            'supplier_name' => $bestSupplier ? $bestSupplier['supplier_name'] : 'No Supplier',
            'suggested_rate' => $bestSupplier ? $bestSupplier['supplier_rate'] : 0,
            'is_sublet' => true,
            'source' => $sp['source'] ?? 'BOM Child',
            'parent_parts' => $sp['parent_parts'] ?? []
        ];
    }

    // Prepare work order items (these go to Work Order)
    $workOrderItems = [];
    foreach ($workOrderParts as $wp) {
        // Get available stock (actual - blocked by other approved plans)
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

    // Adjust PO items: if parent WO parts are "In Stock", child PO parts don't need ordering
    adjustPoItemsForInStockWoParents($subletItems, $workOrderItems);

    // Save WO and PO items to tracking tables for this plan
    if ($currentPlanId && !empty($workOrderItems)) {
        savePlanWorkOrderItems($pdo, $currentPlanId, $workOrderItems);
    }
    if ($currentPlanId && !empty($subletItems)) {
        savePlanPurchaseOrderItems($pdo, $currentPlanId, $subletItems);
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
            // Clear stale links to closed/cancelled WOs from old released SOs
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

    // Also detect work orders created outside the procurement page (from work_orders module)
    // Only pick up ACTIVE WOs - closed/cancelled WOs belong to completed SO cycles and should NOT be linked
    // IMPORTANT: Skip WOs already fully committed to another procurement plan
    if (!empty($workOrderItems)) {
        foreach ($workOrderItems as $wi) {
            $partNo = $wi['part_no'];
            // Skip if already tracked via procurement
            if (isset($woItemStatus[$partNo]) && !empty($woItemStatus[$partNo]['created_wo_id'])) {
                continue;
            }
            // Only find active (not closed/cancelled) work orders for this part
            $extWoStmt = $pdo->prepare("
                SELECT id, wo_no, qty, status, plan_id FROM work_orders
                WHERE part_no = ? AND status NOT IN ('closed', 'cancelled')
                ORDER BY id DESC
            ");
            $extWoStmt->execute([$partNo]);
            $extWoCandidates = $extWoStmt->fetchAll(PDO::FETCH_ASSOC);
            $extWo = null;
            foreach ($extWoCandidates as $candidate) {
                // Skip WOs that are already fully committed to another plan
                if (!empty($candidate['plan_id']) && (int)$candidate['plan_id'] !== ($currentPlanId ?? 0)) {
                    $committedQty = getCommittedQtyForWo($pdo, (int)$candidate['id'], $currentPlanId ?? 0);
                    $surplus = (float)$candidate['qty'] - $committedQty;
                    if ($surplus <= 0) {
                        continue; // Fully committed to another PP — skip
                    }
                }
                $extWo = $candidate;
                break; // Use first available WO with surplus
            }
            if ($extWo) {
                // Link it to the tracking table if plan exists
                if ($currentPlanId) {
                    updatePlanWoItemStatus($pdo, $currentPlanId, $partNo, (int)$extWo['id'], $extWo['wo_no']);
                    // Also set plan_id on the work_orders row so status sync works
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

    // For existing tracked WO items, also fetch the actual WO status
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

    // Load existing tracking status for PO items
    $poItemStatus = [];
    if ($currentPlanId) {
        $poTracking = getPlanPurchaseOrderItems($pdo, $currentPlanId);
        foreach ($poTracking as $pt) {
            $poItemStatus[$pt['part_no']] = $pt;
        }

        // Detect cancelled POs and mark them accordingly
        if (!$planIsCompleted) {
            foreach ($poItemStatus as $partNo => &$ps) {
                if (!empty($ps['created_po_id'])) {
                    try {
                        $chkStmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                        $chkStmt->execute([$ps['created_po_id']]);
                        $poRealStatus = $chkStmt->fetchColumn();
                        if ($poRealStatus && $poRealStatus === 'cancelled') {
                            // Mark as cancelled but keep old PO ref for display
                            $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'po_cancelled' WHERE plan_id = ? AND part_no = ?")
                                 ->execute([$currentPlanId, $partNo]);
                            $ps['status'] = 'po_cancelled';
                            $ps['cancelled_po_no'] = $ps['created_po_no'];
                        }
                    } catch (Exception $e) {}
                }
            }
            unset($ps);

            // Clear po_cancelled for items that now have sufficient stock
            $subletShortageMap = [];
            foreach ($subletItems as $si) {
                $subletShortageMap[$si['part_no']] = $si['shortage'];
            }
            foreach ($poItemStatus as $partNo => &$ps) {
                if (($ps['status'] ?? '') === 'po_cancelled' && isset($subletShortageMap[$partNo]) && $subletShortageMap[$partNo] <= 0) {
                    try {
                        $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'pending', created_po_id = NULL, created_po_no = NULL, ordered_qty = NULL WHERE plan_id = ? AND part_no = ?")
                             ->execute([$currentPlanId, $partNo]);
                        $ps['status'] = 'pending';
                        $ps['created_po_id'] = null;
                        $ps['created_po_no'] = null;
                        unset($ps['cancelled_po_no']);
                    } catch (Exception $e) {}
                }
            }
            unset($ps);

            // Auto-link / unlink PO items based on stock and existing POs
            foreach ($poItemStatus as $partNo => &$ps) {
                $shortage = $subletShortageMap[$partNo] ?? 0;

                // Unlink auto-linked POs when stock is now sufficient
                if (!empty($ps['created_po_id']) && ($ps['status'] ?? '') === 'ordered' && $shortage <= 0) {
                    try {
                        $chk = $pdo->prepare("SELECT plan_id FROM purchase_orders WHERE id = ?");
                        $chk->execute([$ps['created_po_id']]);
                        $poRow = $chk->fetch(PDO::FETCH_ASSOC);
                        if ($poRow && ((int)($poRow['plan_id'] ?? 0) !== $currentPlanId)) {
                            $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'pending', created_po_id = NULL, created_po_no = NULL, ordered_qty = NULL WHERE plan_id = ? AND part_no = ?")
                                 ->execute([$currentPlanId, $partNo]);
                            $ps['status'] = 'pending';
                            $ps['created_po_id'] = null;
                            $ps['created_po_no'] = null;
                            $ps['ordered_qty'] = null;
                        }
                    } catch (Exception $e) {}
                    continue;
                }

                // Auto-link pending items with shortage to existing active POs
                if (!empty($ps['created_po_id'])) continue;
                if (($ps['status'] ?? '') === 'po_cancelled') continue;
                if ($shortage <= 0) continue;

                $existingPo = findExistingActivePo($pdo, $partNo, $currentPlanId);
                if ($existingPo) {
                    updatePlanPoItemStatus($pdo, $currentPlanId, $partNo, (int)$existingPo['id'], $existingPo['po_no'], (float)$existingPo['qty']);
                    $ps['status'] = 'ordered';
                    $ps['created_po_id'] = $existingPo['id'];
                    $ps['created_po_no'] = $existingPo['po_no'];
                    $ps['ordered_qty'] = $existingPo['qty'];
                }
            }
            unset($ps);
        }
    }

    // Handle Work Order creation from this page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_wo') {
        $woPartNo = $_POST['wo_part_no'] ?? '';
        $woQty = (float)($_POST['wo_qty'] ?? 0);

        if ($woPartNo && $woQty > 0 && $currentPlanId) {
            $woResult = createWorkOrderWithTracking($pdo, $currentPlanId, $woPartNo, $woQty);
            if ($woResult['success']) {
                $success = $woResult['message'];
                // Refresh tracking status
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
                // Refresh tracking status
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

    // Handle SO-wise bulk Purchase Order creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_all_po_for_so') {
        $targetSO = $_POST['target_so'] ?? '';
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');

        if ($targetSO && $currentPlanId && !empty($subletItems)) {
            $poResult = createAllPurchaseOrdersForSO($pdo, $currentPlanId, $targetSO, $subletItems, $purchaseDate);
            if ($poResult['success']) {
                $success = $poResult['message'];
                if (!empty($poResult['created_pos'])) {
                    $_SESSION['created_pos_for_so'] = $poResult['created_pos'];
                }
                // Refresh tracking status
                $poTracking = getPlanPurchaseOrderItems($pdo, $currentPlanId);
                foreach ($poTracking as $pt) {
                    $poItemStatus[$pt['part_no']] = $pt;
                }
            } else {
                $error = $poResult['error'] ?? 'Failed to create Purchase Orders';
            }
        } else {
            $error = "Invalid SO or no Purchase Order items";
        }
    }

    // Group items by SO for SO-wise planning view
    $woItemsBySO = groupWorkOrderItemsBySO($workOrderItems ?? []);
    $poItemsBySO = groupPurchaseOrderItemsBySO($subletItems ?? []);

    // Handle plan creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_plan') {
        $notes = trim($_POST['notes'] ?? '');

        // Get modified items from form
        $modifiedItems = [];
        $partNos = $_POST['part_no'] ?? [];
        $quantities = $_POST['recommended_qty'] ?? [];
        $supplierIds = $_POST['supplier_id'] ?? [];
        $rates = $_POST['suggested_rate'] ?? [];

        for ($i = 0; $i < count($partNos); $i++) {
            $modifiedItems[] = [
                'part_no' => $partNos[$i],
                'current_stock' => $_POST['current_stock'][$i] ?? 0,
                'required_qty' => $_POST['demand_qty'][$i] ?? 0,
                'recommended_qty' => (int)($quantities[$i] ?? 0),
                'min_stock_threshold' => $_POST['min_stock_threshold'][$i] ?? 0,
                'supplier_id' => (int)($supplierIds[$i] ?? 0),
                'suggested_rate' => (float)($rates[$i] ?? 0)
            ];
        }

        $result = createProcurementPlan($pdo, $modifiedItems, $notes);

        if ($result['success']) {
            $planId = $result['plan_id'];
            $planNo = $result['plan_no'];
            $success = "Procurement plan {$planNo} created successfully with " . count($modifiedItems) . " items";
            $step = 3; // Show success view
        } else {
            $error = $result['error'] ?? 'Failed to create plan';
        }
    }

    // Handle sublet PO creation (with tracking)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sublet_po') {
        $subletNotes = trim($_POST['sublet_notes'] ?? '');
        $purchaseDate = $_POST['sublet_purchase_date'] ?? date('Y-m-d');

        // Get sublet items from form
        $subletItemsForPO = [];
        $subletPartNos = $_POST['sublet_part_no'] ?? [];
        $subletQtys = $_POST['sublet_qty'] ?? [];
        $subletSupplierIds = $_POST['sublet_supplier_id'] ?? [];

        for ($i = 0; $i < count($subletPartNos); $i++) {
            if (!empty($subletSupplierIds[$i]) && (int)$subletQtys[$i] > 0) {
                $subletItemsForPO[] = [
                    'part_no' => $subletPartNos[$i],
                    'qty' => (int)$subletQtys[$i],
                    'supplier_id' => (int)$subletSupplierIds[$i]
                ];
            }
        }

        if (!empty($subletItemsForPO) && $currentPlanId) {
            // Use tracking-enabled function
            $subletResult = createSubletPurchaseOrdersWithTracking($pdo, $currentPlanId, $subletItemsForPO, $purchaseDate);

            if ($subletResult['success']) {
                $success = $subletResult['message'];
                $_SESSION['sublet_pos_created'] = $subletResult['created_pos'];
                // Refresh tracking status
                $poTracking = getPlanPurchaseOrderItems($pdo, $currentPlanId);
                foreach ($poTracking as $pt) {
                    $poItemStatus[$pt['part_no']] = $pt;
                }
            } else {
                $error = $subletResult['error'] ?? 'Failed to create sublet POs';
            }
        } else {
            $error = "No valid sublet items to create PO";
        }
    }
}

// Step 3: Plan Created (Success)
if ($step == 3 && $planId) {
    $planDetails = getProcurementPlanDetails($pdo, $planId);
    $planItems = getProcurementPlanItems($pdo, $planId);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Procurement Planning - Create Plan</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script>
        function selectAll(checkbox) {
            // Check/uncheck all product checkboxes AND group toggles
            document.querySelectorAll('input[name="selected_so[]"]').forEach(cb => cb.checked = checkbox.checked);
            document.querySelectorAll('.so-group-toggle').forEach(cb => cb.checked = checkbox.checked);
        }

        function toggleSoGroup(groupCheckbox, soId) {
            // Toggle all product checkboxes in this SO group
            document.querySelectorAll('.so-group-' + soId).forEach(cb => cb.checked = groupCheckbox.checked);
        }

        function updateGroupToggle(soId) {
            // Update group header checkbox based on child checkboxes
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

        function updateSupplier(partNo, select) {
            // Could be extended to fetch supplier details via AJAX
        }
    </script>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <h2>Create Procurement Plan</h2>

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
            Choose which sales orders and products to include in this procurement plan. For SOs with multiple products, you can select specific products.
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
                // Group SOs by so_no
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
                                    <!-- SO Group Header for multi-product SO -->
                                    <tr style="background: #eef2ff; border-top: 2px solid #6366f1;">
                                        <td>
                                            <input type="checkbox" class="so-group-toggle" data-so="<?= htmlspecialchars($soId) ?>"
                                                   onchange="toggleSoGroup(this, '<?= htmlspecialchars($soId) ?>')">
                                        </td>
                                        <td colspan="2">
                                            <strong style="color: #4f46e5; font-size: 1.05em;"><?= htmlspecialchars($soNo) ?></strong>
                                            <span style="color: #6366f1; font-size: 0.85em; margin-left: 8px;"><?= htmlspecialchars($soItems[0]['company_name']) ?></span>
                                            <span style="display: inline-block; padding: 2px 8px; background: #6366f120; color: #6366f1; border-radius: 10px; font-size: 0.8em; margin-left: 8px;">
                                                <?= count($soItems) ?> products
                                            </span>
                                        </td>
                                        <td colspan="7" style="color: #6366f1; font-size: 0.85em;">
                                            Select all or choose specific products below
                                        </td>
                                    </tr>
                                    <?php foreach ($soItems as $so): ?>
                                    <tr style="background: #f8f9ff;">
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
                                    <!-- Single product SO -->
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
                    <button type="submit" class="btn btn-primary">Next: Generate Plan</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- STEP 2: Review & Approve Plan Items -->
    <?php elseif ($step == 2): ?>

    <div class="form-section">
        <h3>Step 2: Review & Approve Procurement Items</h3>
        <p style="color: #666; margin-bottom: 15px;">
            Review the recommended procurement items below. You can adjust quantities and suppliers before creating the plan.
        </p>

        <!-- Action Buttons at Top -->
        <?php if (!empty($planItems)): ?>
        <div style="display: flex; gap: 10px; margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; align-items: center;">
            <button type="submit" form="planForm" class="btn btn-primary" style="background: #10b981; color: white; font-weight: 600; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                Create Plan
            </button>
            <a href="create.php?step=1" class="btn btn-secondary" style="background: white; color: #333; font-weight: 600; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none;">
                Back to Select Orders
            </a>
        </div>
        <?php endif; ?>

        <!-- Plan Info Banner -->
        <?php if ($currentPlanId): ?>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 8px; margin-bottom: 15px; color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="font-size: 1.1em;">Plan: <?= htmlspecialchars($currentPlanNo) ?></strong>
                    <?php if ($isExistingPlan): ?>
                        <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 10px;">Existing Plan</span>
                    <?php else: ?>
                        <span style="background: rgba(16,185,129,0.3); padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 10px;">New Plan</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.9em;">
                    SOs: <?= htmlspecialchars(implode(', ', $selectedSOs)) ?>
                    <?php if (!empty($selectedPartNos)): ?>
                        <br><span style="opacity: 0.8; font-size: 0.9em;">Products: <?= htmlspecialchars(implode(', ', $selectedPartNos)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top: 10px; font-size: 0.85em; opacity: 0.9;">
                WO Items: <?= count($workOrderItems ?? []) ?> |
                PO Items: <?= count($subletItems ?? []) ?> |
                Main Items: <?= count($planItems) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($planItems) && empty($subletItems) && empty($workOrderItems)): ?>
            <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
                <p>No procurement items to generate. Selected sales orders may not have suppliers configured or parent parts with BOM.</p>
                <a href="create.php?step=1" class="btn btn-secondary" style="margin-top: 10px;">Back to Select Orders</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($planItems)): ?>

        <form method="post" action="create.php?step=2" id="planForm">
            <input type="hidden" name="action" value="create_plan">

            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Part No</th>
                            <th>Part Name</th>
                            <th>From SO</th>
                            <th>Available Stock</th>
                            <th>Demand</th>
                            <th>Min Stock</th>
                            <th>Order Qty</th>
                            <th>Supplier</th>
                            <th>Rate (₹)</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalEstimated = 0;
                        foreach ($planItems as $idx => $item):
                            $lineTotal = $item['recommended_qty'] * $item['suggested_rate'];
                            $totalEstimated += $lineTotal;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($item['part_no']) ?></td>
                                <td><?= htmlspecialchars($item['part_name']) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($item['so_list']) ?></small>
                                    <input type="hidden" name="part_no[]" value="<?= htmlspecialchars($item['part_no']) ?>">
                                    <input type="hidden" name="current_stock[]" value="<?= $item['current_stock'] ?>">
                                    <input type="hidden" name="demand_qty[]" value="<?= $item['demand_qty'] ?>">
                                    <input type="hidden" name="min_stock_threshold[]" value="<?= $item['min_stock_threshold'] ?>">
                                </td>
                                <td><?= $item['current_stock'] ?></td>
                                <td><?= $item['demand_qty'] ?></td>
                                <td><?= $item['min_stock_threshold'] ?></td>
                                <td>
                                    <input type="number" name="recommended_qty[]" value="<?= $item['recommended_qty'] ?>" min="0" style="width: 80px; padding: 4px;">
                                </td>
                                <td>
                                    <?php
                                    $allSuppliers = getPartSuppliers($pdo, $item['part_no']);
                                    ?>
                                    <select name="supplier_id[]" style="width: 150px; padding: 4px;">
                                        <?php foreach ($allSuppliers as $sup): ?>
                                            <option value="<?= $sup['supplier_id'] ?>" <?= ($sup['supplier_id'] == $item['supplier_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sup['supplier_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="suggested_rate[]" value="<?= $item['suggested_rate'] ?>" step="0.01" min="0" style="width: 100px; padding: 4px;">
                                </td>
                                <td>₹ <?= number_format($lineTotal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="padding: 15px; background: #f0f9ff; border-radius: 8px; margin-bottom: 20px;">
                <strong>Estimated Total Cost: ₹ <?= number_format($totalEstimated, 2) ?></strong>
            </div>

            <div style="margin-bottom: 20px;">
                <label>Notes (Optional)</label>
                <textarea name="notes" style="width: 100%; height: 80px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;" placeholder="Add any notes about this procurement plan..."></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Create Plan</button>
                <a href="create.php?step=1" class="btn btn-secondary">Back to Select Orders</a>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($planIsCompleted): ?>
        <!-- Plan Completed Banner -->
        <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #dcfce7, #d1fae5); border: 2px solid #16a34a; border-radius: 10px; text-align: center;">
            <h3 style="color: #16a34a; margin: 0 0 8px 0;">Plan Completed - All SOs Released</h3>
            <p style="color: #059669; margin: 0;">All linked Sales Orders have been released. This procurement plan is now closed.</p>
            <a href="view.php?id=<?= $currentPlanId ?>" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">View Plan Details</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($workOrderItems)): ?>
        <!-- Work Order Parts Section -->
        <div style="margin-top: 40px; padding-top: 30px; border-top: 3px solid #10b981;">
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
                Any part (direct or BOM child) with Part ID in [<?= htmlspecialchars(implode(', ', getWorkOrderPartIds())) ?>] will be produced <strong>internally via Work Orders</strong>.
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
                            // Determine row filter status
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
                                        <span style="color: #16a34a;">✓ Done</span>
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
                                        <span style="color: #16a34a;">✓ In Stock</span>
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
                    📋 Shop Order Wise Work Order Planning
                </h4>

                <?php if (isset($_SESSION['created_wos_for_so']) && !empty($_SESSION['created_wos_for_so'])): ?>
                    <div style="background: #dcfce7; border: 1px solid #16a34a; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <strong style="color: #16a34a;">✓ Work Orders Created:</strong>
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
                        <span style="color: #16a34a; font-weight: 500;">✓ Plan completed - SO released</span>
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
                        <span style="color: #10b981; font-weight: 500;">✓ All WOs created or in stock</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($subletItems)): ?>
        <!-- Sublet Parts Section -->
        <div style="margin-top: 40px; padding-top: 30px; border-top: 3px solid #f59e0b;">
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
                    $poOrderedCount = count($subletItems);
                } else {
                    foreach ($subletItems as $si) {
                        $poSt = $poItemStatus[$si['part_no']] ?? null;
                        if ($poSt && ($poSt['status'] ?? '') === 'po_cancelled') {
                            $poCancelledCount++;
                        } elseif ($poSt && $poSt['created_po_id'] && ($poSt['status'] ?? '') !== 'po_cancelled') {
                            $poOrderedCount++;
                        } elseif ($si['shortage'] <= 0) {
                            $poInStockCount++;
                        } else {
                            $poPendingCount++;
                        }
                    }
                }
                ?>
                <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                    <?php if ($planIsCompleted): ?>
                        <span style="color: #16a34a; font-weight: 600;">All <?= count($subletItems) ?> Closed (SOs Released)</span>
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
            <p style="color: #666; margin-bottom: 15px;">
                Any part (direct or BOM child) with Part ID <strong>NOT</strong> in [<?= htmlspecialchars(implode(', ', getWorkOrderPartIds())) ?>] should be procured <strong>externally via Purchase Orders</strong>.
            </p>

            <!-- PO Filter Buttons -->
            <?php if (!$planIsCompleted): ?>
            <div style="margin-bottom: 12px; display: flex; gap: 6px; flex-wrap: wrap;" id="po-filters">
                <button type="button" class="filter-btn active" data-filter="all" data-target="po" style="padding: 4px 12px; border-radius: 15px; border: 1px solid #d1d5db; background: #d97706; color: white; cursor: pointer; font-size: 0.85em;">
                    All (<?= count($subletItems) ?>)
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

            <?php if (isset($_SESSION['sublet_pos_created']) && !empty($_SESSION['sublet_pos_created'])): ?>
                <div style="background: #dcfce7; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong style="color: #16a34a;">✓ Purchase Orders Created:</strong>
                    <?php foreach ($_SESSION['sublet_pos_created'] as $po): ?>
                        <span style="display: inline-block; background: #16a34a; color: white; padding: 2px 10px; border-radius: 12px; margin: 0 5px;">
                            <?= htmlspecialchars($po) ?>
                        </span>
                    <?php endforeach; ?>
                    <a href="/purchase/index.php" class="btn btn-sm" style="margin-left: 10px;">View POs</a>
                </div>
                <?php unset($_SESSION['sublet_pos_created']); ?>
            <?php endif; ?>

            <form method="post" action="create.php?step=2">
                <input type="hidden" name="action" value="create_sublet_po">
                <input type="hidden" name="sublet_purchase_date" value="<?= date('Y-m-d') ?>">

                <div style="overflow-x: auto; margin-bottom: 20px;">
                    <table id="po-table">
                        <thead>
                            <tr style="background: #fef3c7;">
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Part ID</th>
                                <th>Source</th>
                                <th>Parent/SO</th>
                                <th>Available Stock</th>
                                <th>Required</th>
                                <th>Shortage</th>
                                <th>Status</th>
                                <th>Order Qty</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subletItems as $idx => $item):
                                $poStatus = $poItemStatus[$item['part_no']] ?? null;
                                $isPoCancelled = $poStatus && ($poStatus['status'] ?? '') === 'po_cancelled';
                                $hasPO = $poStatus && $poStatus['created_po_id'] && !$isPoCancelled;
                                $cancelledPoNo = $poStatus['cancelled_po_no'] ?? ($poStatus['created_po_no'] ?? '');
                                if ($planIsCompleted) { $poRowBg = '#f3f4f6'; }
                                elseif ($isPoCancelled) { $poRowBg = '#fef2f2'; }
                                elseif ($hasPO) { $poRowBg = '#dcfce7'; }
                                else { $poRowBg = $idx % 2 ? '#fffbeb' : '#fef9e7'; }
                                // Determine row filter status
                                if ($planIsCompleted) { $poRowFilter = 'closed'; }
                                elseif ($isPoCancelled) { $poRowFilter = 'cancelled'; }
                                elseif ($hasPO) { $poRowFilter = 'ordered'; }
                                elseif ($item['shortage'] <= 0) { $poRowFilter = 'in_stock'; }
                                else { $poRowFilter = 'pending'; }
                            ?>
                                <tr style="background: <?= $poRowBg ?>;" data-status="<?= $poRowFilter ?>">
                                    <td>
                                        <?= htmlspecialchars($item['part_no']) ?>
                                        <?php if (!$hasPO || $isPoCancelled): ?>
                                        <input type="hidden" name="sublet_part_no[]" value="<?= htmlspecialchars($item['part_no']) ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['part_name']) ?></td>
                                    <td><strong><?= $item['part_id'] ?? '-' ?></strong></td>
                                    <td>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;
                                            background: <?= ($item['source'] ?? '') === 'Direct SO Part' ? '#dbeafe' : '#fef3c7' ?>;
                                            color: <?= ($item['source'] ?? '') === 'Direct SO Part' ? '#1e40af' : '#92400e' ?>;">
                                            <?= htmlspecialchars($item['source'] ?? 'BOM Child') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small style="color: #666;">
                                            <?php if (($item['source'] ?? '') === 'Direct SO Part'): ?>
                                                SO: <?= htmlspecialchars($item['so_list']) ?>
                                            <?php else: ?>
                                                <?php
                                                $parentInfo = [];
                                                foreach ($item['parent_parts'] ?? [] as $p) {
                                                    $parentInfo[] = $p['part_no'] . ' (SO: ' . $p['so_no'] . ')';
                                                }
                                                echo 'Parent: ' . htmlspecialchars(implode(', ', $parentInfo));
                                                ?>
                                            <?php endif; ?>
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
                                        <?php elseif ($isPoCancelled): ?>
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
                                            <br><small style="color: #059669;"><?= htmlspecialchars($poStatus['created_po_no']) ?></small>
                                        <?php elseif ($item['shortage'] <= 0): ?>
                                            <span style="display: inline-block; padding: 4px 10px; background: #10b981; color: white; border-radius: 15px; font-size: 0.8em;">
                                                In Stock
                                            </span>
                                            <?php if (!empty($item['parent_in_stock'])): ?>
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
                                        <?php elseif ($hasPO): ?>
                                            <span style="color: #16a34a; font-weight: bold;"><?= $poStatus['ordered_qty'] ?></span>
                                        <?php elseif ($isPoCancelled): ?>
                                            <input type="number" name="sublet_qty[]"
                                                   value="<?= $item['shortage'] > 0 ? $item['shortage'] : $item['demand_qty'] ?>"
                                                   min="0" style="width: 80px; padding: 4px; border-color: #dc2626;">
                                        <?php else: ?>
                                            <input type="number" name="sublet_qty[]"
                                                   value="<?= $item['shortage'] > 0 ? $item['shortage'] : 0 ?>"
                                                   min="0" style="width: 80px; padding: 4px;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasPO): ?>
                                            <a href="/purchase/view.php?po_no=<?= urlencode($poStatus['created_po_no']) ?>"
                                               class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 8px; font-size: 0.8em;">
                                                View PO
                                            </a>
                                        <?php else:
                                            $allSuppliers = getPartSuppliers($pdo, $item['part_no']);
                                            if (empty($allSuppliers)):
                                        ?>
                                            <a href="/part_master/suppliers.php?part_no=<?= urlencode($item['part_no']) ?>"
                                               style="color: #dc2626; text-decoration: underline; font-weight: 500;"
                                               title="Click to add supplier for this part"
                                               target="_blank">
                                                + Add Supplier
                                            </a>
                                            <input type="hidden" name="sublet_supplier_id[]" value="">
                                        <?php else: ?>
                                            <select name="sublet_supplier_id[]" style="width: 150px; padding: 4px;">
                                                <?php foreach ($allSuppliers as $sup): ?>
                                                    <option value="<?= $sup['supplier_id'] ?>" <?= ($sup['supplier_id'] == $item['supplier_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($sup['supplier_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($poPendingCount > 0): ?>
                <div style="margin-bottom: 20px;">
                    <label>Sublet Notes (Optional)</label>
                    <textarea name="sublet_notes" style="width: 100%; height: 60px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;" placeholder="Notes for sublet purchase orders..."></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn" style="background: #f59e0b; color: white;">
                        Create Sublet PO(s) for Pending Items
                    </button>
                    <?php if (empty($planItems)): ?>
                        <a href="create.php?step=1" class="btn btn-secondary">Back to Select Orders</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div style="background: #dcfce7; padding: 15px; border-radius: 8px; text-align: center;">
                    <span style="color: #059669; font-weight: 600;">✓ All Purchase Order items have been ordered!</span>
                </div>
                <?php endif; ?>
            </form>

            <!-- SO-wise Purchase Order Planning -->
            <?php if (!empty($poItemsBySO) && count($poItemsBySO) > 0): ?>
            <div style="margin-top: 25px; padding: 20px; background: #fffbeb; border-radius: 8px; border: 2px solid #f59e0b;">
                <h4 style="margin: 0 0 15px 0; color: #d97706;">
                    📋 Shop Order Wise Purchase Order Planning
                </h4>

                <?php if (isset($_SESSION['created_pos_for_so']) && !empty($_SESSION['created_pos_for_so'])): ?>
                    <div style="background: #dcfce7; border: 1px solid #16a34a; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <strong style="color: #16a34a;">✓ Purchase Orders Created:</strong>
                        <?php foreach ($_SESSION['created_pos_for_so'] as $po): ?>
                            <span style="display: inline-block; background: #16a34a; color: white; padding: 2px 10px; border-radius: 12px; margin: 0 5px;">
                                <?= htmlspecialchars($po) ?>
                            </span>
                        <?php endforeach; ?>
                        <a href="/purchase/index.php" class="btn btn-sm" style="margin-left: 10px;">View POs</a>
                    </div>
                    <?php unset($_SESSION['created_pos_for_so']); ?>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php foreach ($poItemsBySO as $soNo => $soItems):
                        $allDone = $planIsCompleted;
                        $soPending = 0; $soOrdered = 0; $soInStock = 0; $soNoSupplier = 0;
                        if (!$planIsCompleted) {
                            foreach ($soItems as $si) {
                                $status = $poItemStatus[$si['part_no']] ?? null;
                                if ($status && $status['created_po_id']) { $soOrdered++; }
                                elseif (($si['shortage'] ?? 0) <= 0) { $soInStock++; }
                                elseif (empty($si['supplier_id'])) { $soNoSupplier++; }
                                else { $soPending++; }
                            }
                            $allDone = ($soPending == 0);
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
                                <span style="background: #f59e0b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;"><?= $soPending ?> Pending</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 12px;">
                            <?php if ($planIsCompleted): ?>
                                <?= count($soItems) ?> parts | <span style="color: #16a34a;">All closed</span>
                            <?php else: ?>
                                <?= count($soItems) ?> parts |
                                <span style="color: #16a34a;"><?= $soOrdered ?> ordered</span> |
                                <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                                <?php if ($soNoSupplier > 0): ?>
                                    | <span style="color: #dc2626;"><?= $soNoSupplier ?> no supplier</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($planIsCompleted): ?>
                        <span style="color: #16a34a; font-weight: 500;">✓ Plan completed - SO released</span>
                        <?php elseif ($soPending > 0): ?>
                        <form method="post" action="create.php?step=2" style="display: inline;">
                            <input type="hidden" name="action" value="create_all_po_for_so">
                            <input type="hidden" name="target_so" value="<?= htmlspecialchars($soNo) ?>">
                            <input type="hidden" name="purchase_date" value="<?= date('Y-m-d') ?>">
                            <button type="submit" class="btn btn-sm" style="background: #f59e0b; color: white; padding: 6px 15px; font-size: 0.85em; width: 100%;"
                                    onclick="return confirm('Create all Purchase Orders for <?= htmlspecialchars($soNo) ?>?');">
                                Create All POs for <?= htmlspecialchars($soNo) ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color: #10b981; font-weight: 500;">✓ All POs created or in stock</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- STEP 3: Plan Created Successfully -->
    <?php elseif ($step == 3 && $planId): ?>

    <div class="form-section" style="background: #dcfce7; border: 2px solid #16a34a; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h3 style="color: #16a34a; margin: 0;">✓ Procurement Plan Created Successfully</h3>
        <p style="margin: 10px 0 0 0; color: #166534;">
            Plan <strong><?= htmlspecialchars($planNo) ?></strong> has been created with <strong><?= count($planItems) ?></strong> items
        </p>
    </div>

    <div class="form-section">
        <h3>Plan Summary</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div>
                <label style="color: #666; font-size: 0.9em;">Plan No</label>
                <p style="margin: 5px 0; font-weight: bold;"><?= htmlspecialchars($planNo) ?></p>
            </div>
            <div>
                <label style="color: #666; font-size: 0.9em;">Status</label>
                <p style="margin: 5px 0; font-weight: bold; color: #6366f1;">Draft</p>
            </div>
            <div>
                <label style="color: #666; font-size: 0.9em;">Total Items</label>
                <p style="margin: 5px 0; font-weight: bold;"><?= $planDetails['item_count'] ?></p>
            </div>
            <div>
                <label style="color: #666; font-size: 0.9em;">Est. Cost</label>
                <p style="margin: 5px 0; font-weight: bold;">₹ <?= number_format($planDetails['total_estimated_cost'] ?? 0, 2) ?></p>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3>Procurement Items</h3>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th>Stock</th>
                        <th>Demand</th>
                        <th>Min Stock</th>
                        <th>Order Qty</th>
                        <th>Supplier</th>
                        <th>Rate (₹)</th>
                        <th>Line Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['part_no']) ?></td>
                            <td><?= htmlspecialchars($item['part_name']) ?></td>
                            <td><?= $item['current_stock'] ?> <?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= $item['required_qty'] ?></td>
                            <td><?= $item['min_stock_threshold'] ?></td>
                            <td><strong><?= $item['recommended_qty'] ?></strong></td>
                            <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                            <td>₹ <?= number_format($item['suggested_rate'], 2) ?></td>
                            <td>₹ <?= number_format($item['line_total'], 2) ?></td>
                            <td>
                                <span style="display: inline-block; padding: 4px 8px; background: #6366f120; color: #6366f1; border-radius: 4px; font-size: 0.9em;">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 20px;">
        <a href="view.php?id=<?= $planId ?>" class="btn btn-primary">View & Approve Plan</a>
        <a href="index.php" class="btn btn-secondary">Back to Plans</a>
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
