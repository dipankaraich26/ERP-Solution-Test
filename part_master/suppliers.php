<?php
require '../db.php';
require '../includes/procurement_helper.php';

$part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';
$success = false;
$error = '';

/**
 * Update part_master rate from first/preferred supplier
 * If no active suppliers, leave rate unchanged
 */
function updatePartMasterRateFromSupplier($pdo, $part_no) {
    // Get the first active supplier rate (prefer preferred suppliers)
    $stmt = $pdo->prepare("
        SELECT supplier_rate
        FROM part_supplier_mapping
        WHERE part_no = ? AND active = 1
        ORDER BY is_preferred DESC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$part_no]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['supplier_rate'] > 0) {
        // Update part_master rate with supplier rate
        $updateStmt = $pdo->prepare("UPDATE part_master SET rate = ? WHERE part_no = ?");
        $updateStmt->execute([$result['supplier_rate'], $part_no]);
        return true;
    }
    return false;
}

// Fetch part details
if ($part_no) {
    $partStmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no = ?");
    $partStmt->execute([$part_no]);
    $part = $partStmt->fetch(PDO::FETCH_ASSOC);

    if (!$part) {
        $error = "Part not found";
    }
} else {
    $error = "No part selected";
}

// Fetch all suppliers
$allSuppliers = $pdo->query("
    SELECT id, supplier_name, supplier_code, contact_person, email
    FROM suppliers
    ORDER BY supplier_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch current suppliers for this part
$currentSuppliers = [];
if ($part_no) {
    $currentSuppliers = getPartSuppliers($pdo, $part_no);
}

// Handle add supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_supplier') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $rate = (float)($_POST['supplier_rate'] ?? 0);
        $lead_days = (int)($_POST['lead_time_days'] ?? 5);
        $min_order_qty = (int)($_POST['min_order_qty'] ?? 1);
        $supplier_sku = trim($_POST['supplier_sku'] ?? '');
        $is_preferred = isset($_POST['is_preferred']) ? 1 : 0;

        if (!$supplier_id || $rate <= 0) {
            $error = "Supplier and rate are required";
        } else {
            if (addSupplierToPart($pdo, $part_no, $supplier_id, $rate, $lead_days, $min_order_qty, $supplier_sku, (bool)$is_preferred)) {
                // Auto-update part_master rate from first/preferred supplier
                updatePartMasterRateFromSupplier($pdo, $part_no);
                $success = "Supplier added successfully. Part rate updated.";
                // Refresh current suppliers
                $currentSuppliers = getPartSuppliers($pdo, $part_no);
            } else {
                $error = "Failed to add supplier";
            }
        }
    }

    if ($action === 'update_supplier') {
        $mapping_id = (int)($_POST['mapping_id'] ?? 0);
        $rate = (float)($_POST['supplier_rate'] ?? 0);
        $lead_days = (int)($_POST['lead_time_days'] ?? 5);
        $min_order_qty = (int)($_POST['min_order_qty'] ?? 1);
        $is_preferred = isset($_POST['is_preferred']) ? 1 : 0;

        if ($mapping_id && $rate > 0) {
            $updateStmt = $pdo->prepare("
                UPDATE part_supplier_mapping
                SET supplier_rate = ?, lead_time_days = ?, min_order_qty = ?, is_preferred = ?
                WHERE id = ? AND part_no = ?
            ");
            if ($updateStmt->execute([$rate, $lead_days, $min_order_qty, $is_preferred, $mapping_id, $part_no])) {
                // Auto-update part_master rate from first/preferred supplier
                updatePartMasterRateFromSupplier($pdo, $part_no);
                $success = "Supplier updated successfully. Part rate updated.";
                $currentSuppliers = getPartSuppliers($pdo, $part_no);
            } else {
                $error = "Failed to update supplier";
            }
        }
    }

    if ($action === 'delete_supplier') {
        $mapping_id = (int)($_POST['mapping_id'] ?? 0);
        if ($mapping_id) {
            $deleteStmt = $pdo->prepare("
                DELETE FROM part_supplier_mapping
                WHERE id = ? AND part_no = ?
            ");
            if ($deleteStmt->execute([$mapping_id, $part_no])) {
                // Auto-update part_master rate from remaining first/preferred supplier
                updatePartMasterRateFromSupplier($pdo, $part_no);
                $success = "Supplier removed successfully. Part rate updated.";
                $currentSuppliers = getPartSuppliers($pdo, $part_no);
            } else {
                $error = "Failed to remove supplier";
            }
        }
    }

    if ($action === 'toggle_active') {
        $mapping_id = (int)($_POST['mapping_id'] ?? 0);
        if ($mapping_id) {
            $toggleStmt = $pdo->prepare("
                UPDATE part_supplier_mapping
                SET active = NOT active
                WHERE id = ? AND part_no = ?
            ");
            if ($toggleStmt->execute([$mapping_id, $part_no])) {
                // Auto-update part_master rate from first/preferred active supplier
                updatePartMasterRateFromSupplier($pdo, $part_no);
                $success = "Supplier status updated. Part rate updated.";
                $currentSuppliers = getPartSuppliers($pdo, $part_no);
            } else {
                $error = "Failed to update supplier status";
            }
        }
    }

    // Refresh part details to show updated rate
    if ($success && $part_no) {
        $partStmt = $pdo->prepare("SELECT * FROM part_master WHERE part_no = ?");
        $partStmt->execute([$part_no]);
        $part = $partStmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Part Suppliers - <?= htmlspecialchars($part_no) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <h2>Manage Suppliers for Part: <?= htmlspecialchars($part_no) ?></h2>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($part): ?>

    <div class="form-section">
        <h3>Part Details</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <label>Part Name:</label>
                <p><?= htmlspecialchars($part['part_name']) ?></p>
            </div>
            <div>
                <label>UOM:</label>
                <p><?= htmlspecialchars($part['uom']) ?></p>
            </div>
            <div>
                <label>Master Rate:</label>
                <p>₹ <?= number_format($part['rate'], 2) ?></p>
            </div>
            <div>
                <label>Category:</label>
                <p><?= htmlspecialchars($part['category']) ?></p>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3>Current Suppliers</h3>

        <?php if (!empty($currentSuppliers)): ?>
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>SKU</th>
                            <th>Rate (₹)</th>
                            <th>Lead Time (days)</th>
                            <th>Min Order Qty</th>
                            <th>Preferred</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentSuppliers as $supplier): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($supplier['supplier_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($supplier['supplier_code'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($supplier['supplier_sku'] ?? '-') ?></td>
                                <td>₹ <?= number_format($supplier['supplier_rate'], 2) ?></td>
                                <td><?= $supplier['lead_time_days'] ?> days</td>
                                <td><?= $supplier['min_order_qty'] ?></td>
                                <td>
                                    <?php if ($supplier['is_preferred']): ?>
                                        <span style="color: green; font-weight: bold;">✓ Yes</span>
                                    <?php else: ?>
                                        No
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($supplier['active']): ?>
                                        <span style="color: green;">Active</span>
                                    <?php else: ?>
                                        <span style="color: red;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editSupplier(<?= $supplier['id'] ?>)" class="btn btn-small">Edit</button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="mapping_id" value="<?= $supplier['id'] ?>">
                                        <button type="submit" class="btn btn-small btn-warning">
                                            <?= $supplier['active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Remove this supplier?');">
                                        <input type="hidden" name="action" value="delete_supplier">
                                        <input type="hidden" name="mapping_id" value="<?= $supplier['id'] ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #666; font-style: italic;">No suppliers assigned yet</p>
        <?php endif; ?>
    </div>

    <div class="form-section">
        <h3>Add / Update Supplier</h3>

        <form method="post" class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <input type="hidden" name="action" value="add_supplier">

            <div>
                <label>Select Supplier *</label>
                <select name="supplier_id" required onchange="updateSupplierFields()">
                    <option value="">-- Choose Supplier --</option>
                    <?php foreach ($allSuppliers as $s): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Supplier SKU</label>
                <input type="text" name="supplier_sku" placeholder="e.g., SKU-12345">
            </div>

            <div>
                <label>Rate (₹) *</label>
                <input type="number" name="supplier_rate" step="0.01" min="0" required placeholder="0.00">
            </div>

            <div>
                <label>Lead Time (days)</label>
                <input type="number" name="lead_time_days" value="5" min="1">
            </div>

            <div>
                <label>Min Order Qty</label>
                <input type="number" name="min_order_qty" value="1" min="1">
            </div>

            <div style="display: flex; align-items: flex-end;">
                <label style="flex: 1;">
                    <input type="checkbox" name="is_preferred">
                    Mark as Preferred Supplier
                </label>
            </div>

            <div style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">Add Supplier</button>
                <a href="list.php" class="btn btn-secondary">Back to Parts</a>
            </div>
        </form>
    </div>

    <?php else: ?>
        <p><?= htmlspecialchars($error) ?></p>
        <a href="list.php" class="btn btn-secondary">Back to Parts</a>
    <?php endif; ?>

</div>

<script>
function editSupplier(mappingId) {
    // In a real app, this would open an edit modal or redirect to edit page
    alert('Edit functionality would be implemented with a modal or separate page');
}

function updateSupplierFields() {
    // Could auto-populate supplier info here if needed
}
</script>

</body>
</html>
