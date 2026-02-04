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

// Step 1: Select Sales Orders
if ($step == 1) {
    // Get all open/pending sales orders
    $openSOs = $pdo->query("
        SELECT
            so.so_no,
            so.part_no,
            so.qty,
            so.sales_date,
            so.stock_status,
            COALESCE(c.company_name, 'N/A') AS company_name,
            COALESCE(p.part_name, so.part_no) AS part_name,
            COALESCE(i.qty, 0) AS current_stock
        FROM sales_orders so
        LEFT JOIN customers c ON c.id = so.customer_id
        LEFT JOIN part_master p ON p.part_no = so.part_no
        LEFT JOIN inventory i ON so.part_no = i.part_no
        WHERE so.status IN ('pending', 'open')
        ORDER BY so.sales_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_so') {
        $selectedSOs = $_POST['selected_so'] ?? [];

        if (empty($selectedSOs)) {
            $error = "Please select at least one sales order";
        } else {
            // Store selection in session and proceed to step 2
            $_SESSION['selected_sos'] = $selectedSOs;
            header("Location: create.php?step=2");
            exit;
        }
    }
}

// Step 2: Generate Plan & Show Recommendations
if ($step == 2) {
    $selectedSOs = $_SESSION['selected_sos'] ?? [];

    if (empty($selectedSOs)) {
        header("Location: create.php?step=1");
        exit;
    }

    // Get or create a plan for these selected SOs
    $planResult = getOrCreatePlanForSOs($pdo, $selectedSOs);
    $currentPlanId = $planResult['plan_id'] ?? null;
    $currentPlanNo = $planResult['plan_no'] ?? '';
    $isExistingPlan = $planResult['is_existing'] ?? false;

    // Get selected sales orders by part
    $sosByPart = getSelectedSalesOrdersByPart($pdo, $selectedSOs);

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
    $subletParts = getSubletPartsForSalesOrders($pdo, $selectedSOs);

    // Get work order parts (child parts that go to Work Order - IDs: 99, 42, 44, 46, 83, 91)
    $workOrderParts = getWorkOrderPartsForSalesOrders($pdo, $selectedSOs);

    // Prepare sublet items with supplier info (these go to Purchase Order)
    $subletItems = [];
    foreach ($subletParts as $sp) {
        $bestSupplier = getBestSupplier($pdo, $sp['part_no']);

        // Get current stock
        $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
        $stockStmt->execute([$sp['part_no']]);
        $currentStock = $stockStmt->fetchColumn() ?: 0;

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
        // Get current stock
        $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
        $stockStmt->execute([$wp['part_no']]);
        $currentStock = $stockStmt->fetchColumn() ?: 0;

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
    }

    // Load existing tracking status for PO items
    $poItemStatus = [];
    if ($currentPlanId) {
        $poTracking = getPlanPurchaseOrderItems($pdo, $currentPlanId);
        foreach ($poTracking as $pt) {
            $poItemStatus[$pt['part_no']] = $pt;
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
            const checkboxes = document.querySelectorAll('input[name="selected_so[]"]');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
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
        <h3>Step 1: Select Open Sales Orders</h3>
        <p style="color: #666; margin-bottom: 15px;">
            Choose which sales orders to include in this procurement plan. All open/pending orders are shown below.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="select_so">

            <?php if (empty($openSOs)): ?>
                <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
                    <p>No open sales orders found. Create a sales order first.</p>
                    <a href="/sales_orders/index.php" class="btn btn-primary" style="margin-top: 10px;">Go to Sales Orders</a>
                </div>
            <?php else: ?>
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
                                <th>Current Stock</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openSOs as $so): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_so[]" value="<?= htmlspecialchars($so['so_no']) ?>">
                                    </td>
                                    <td><strong><?= htmlspecialchars($so['so_no']) ?></strong></td>
                                    <td><?= htmlspecialchars($so['company_name']) ?></td>
                                    <td><?= htmlspecialchars($so['part_no']) ?></td>
                                    <td><?= htmlspecialchars($so['part_name']) ?></td>
                                    <td><?= $so['qty'] ?></td>
                                    <td><?= $so['current_stock'] ?></td>
                                    <td><?= date('Y-m-d', strtotime($so['sales_date'])) ?></td>
                                </tr>
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
                            <th>Current Stock</th>
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

        <?php if (!empty($workOrderItems)): ?>
        <!-- Work Order Parts Section -->
        <div style="margin-top: 40px; padding-top: 30px; border-top: 3px solid #10b981;">
            <h3 style="color: #059669; margin-bottom: 15px;">
                <span style="background: #d1fae5; padding: 4px 12px; border-radius: 20px;">
                    ⚙️ Work Order Parts (Internal Production)
                </span>
                <?php
                $woCreatedCount = 0;
                $woPendingCount = 0;
                foreach ($workOrderItems as $wi) {
                    $woStatus = $woItemStatus[$wi['part_no']] ?? null;
                    if ($woStatus && $woStatus['created_wo_id']) {
                        $woCreatedCount++;
                    } else if ($wi['shortage'] > 0) {
                        $woPendingCount++;
                    }
                }
                ?>
                <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                    <span style="color: #16a34a;"><?= $woCreatedCount ?> Created</span> |
                    <span style="color: #f59e0b;"><?= $woPendingCount ?> Pending</span>
                </span>
            </h3>
            <p style="color: #666; margin-bottom: 15px;">
                Any part (direct or BOM child) with Part ID in [<?= htmlspecialchars(implode(', ', getWorkOrderPartIds())) ?>] will be produced <strong>internally via Work Orders</strong>.
            </p>

            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr style="background: #d1fae5;">
                            <th>Part No</th>
                            <th>Part Name</th>
                            <th>Part ID</th>
                            <th>Source</th>
                            <th>Parent/SO</th>
                            <th>Stock</th>
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
                        ?>
                            <tr style="background: <?= $hasWO ? '#dcfce7' : ($idx % 2 ? '#ecfdf5' : '#f0fdf4') ?>;">
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
                                    <?php if ($hasWO): ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                            In Progress
                                        </span>
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
                                    <?php if ($hasWO): ?>
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
                        // Count pending vs created for this SO
                        $soPending = 0;
                        $soCreated = 0;
                        $soInStock = 0;
                        foreach ($soItems as $si) {
                            $status = $woItemStatus[$si['part_no']] ?? null;
                            if ($status && $status['created_wo_id']) {
                                $soCreated++;
                            } elseif (($si['shortage'] ?? 0) <= 0) {
                                $soInStock++;
                            } else {
                                $soPending++;
                            }
                        }
                        $allDone = ($soPending == 0);
                    ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 1.1em; color: #1f2937;"><?= htmlspecialchars($soNo) ?></strong>
                            <?php if ($allDone): ?>
                                <span style="background: #10b981; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;">Complete</span>
                            <?php else: ?>
                                <span style="background: #f59e0b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;"><?= $soPending ?> Pending</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 12px;">
                            <?= count($soItems) ?> parts |
                            <span style="color: #16a34a;"><?= $soCreated ?> created</span> |
                            <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                        </div>
                        <?php if ($soPending > 0): ?>
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
                foreach ($subletItems as $si) {
                    $poStatus = $poItemStatus[$si['part_no']] ?? null;
                    if ($poStatus && $poStatus['created_po_id']) {
                        $poOrderedCount++;
                    } else if ($si['shortage'] > 0) {
                        $poPendingCount++;
                    }
                }
                ?>
                <span style="margin-left: 15px; font-size: 0.85em; font-weight: normal;">
                    <span style="color: #16a34a;"><?= $poOrderedCount ?> Ordered</span> |
                    <span style="color: #f59e0b;"><?= $poPendingCount ?> Pending</span>
                </span>
            </h3>
            <p style="color: #666; margin-bottom: 15px;">
                Any part (direct or BOM child) with Part ID <strong>NOT</strong> in [<?= htmlspecialchars(implode(', ', getWorkOrderPartIds())) ?>] should be procured <strong>externally via Purchase Orders</strong>.
            </p>

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
                    <table>
                        <thead>
                            <tr style="background: #fef3c7;">
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Part ID</th>
                                <th>Source</th>
                                <th>Parent/SO</th>
                                <th>Stock</th>
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
                                $hasPO = $poStatus && $poStatus['created_po_id'];
                            ?>
                                <tr style="background: <?= $hasPO ? '#dcfce7' : ($idx % 2 ? '#fffbeb' : '#fef9e7') ?>;">
                                    <td>
                                        <?= htmlspecialchars($item['part_no']) ?>
                                        <?php if (!$hasPO): ?>
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
                                        <?php if ($hasPO): ?>
                                            <span style="display: inline-block; padding: 4px 10px; background: #16a34a; color: white; border-radius: 15px; font-size: 0.8em;">
                                                Ordered
                                            </span>
                                            <br><small style="color: #059669;"><?= htmlspecialchars($poStatus['created_po_no']) ?></small>
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
                                            <span style="color: #16a34a; font-weight: bold;"><?= $poStatus['ordered_qty'] ?></span>
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
                        // Count pending vs ordered for this SO
                        $soPending = 0;
                        $soOrdered = 0;
                        $soInStock = 0;
                        $soNoSupplier = 0;
                        foreach ($soItems as $si) {
                            $status = $poItemStatus[$si['part_no']] ?? null;
                            if ($status && $status['created_po_id']) {
                                $soOrdered++;
                            } elseif (($si['shortage'] ?? 0) <= 0) {
                                $soInStock++;
                            } elseif (empty($si['supplier_id'])) {
                                $soNoSupplier++;
                            } else {
                                $soPending++;
                            }
                        }
                        $allDone = ($soPending == 0);
                    ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid <?= $allDone ? '#10b981' : '#f59e0b' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <strong style="font-size: 1.1em; color: #1f2937;"><?= htmlspecialchars($soNo) ?></strong>
                            <?php if ($allDone): ?>
                                <span style="background: #10b981; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;">Complete</span>
                            <?php else: ?>
                                <span style="background: #f59e0b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8em;"><?= $soPending ?> Pending</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: #666; margin-bottom: 12px;">
                            <?= count($soItems) ?> parts |
                            <span style="color: #16a34a;"><?= $soOrdered ?> ordered</span> |
                            <span style="color: #10b981;"><?= $soInStock ?> in stock</span>
                            <?php if ($soNoSupplier > 0): ?>
                                | <span style="color: #dc2626;"><?= $soNoSupplier ?> no supplier</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($soPending > 0): ?>
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

</body>
</html>
