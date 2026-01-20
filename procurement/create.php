<?php
// Start session first before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../db.php';
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
            c.company_name,
            p.part_name,
            COALESCE(i.qty, 0) AS current_stock
        FROM sales_orders so
        JOIN customers c ON so.customer_id = c.id
        JOIN part_master p ON so.part_no = p.part_no
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

        <?php if (empty($planItems)): ?>
            <div style="padding: 20px; background: #f3f4f6; border-radius: 8px; text-align: center; color: #666;">
                <p>No procurement items to generate. Selected sales orders may not have suppliers configured.</p>
                <a href="create.php?step=1" class="btn btn-secondary" style="margin-top: 10px;">Back to Select Orders</a>
            </div>
        <?php else: ?>

        <form method="post">
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
