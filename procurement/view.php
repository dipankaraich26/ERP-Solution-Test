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
