<?php
require '../db.php';
require '../includes/procurement_helper.php';

$part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';
$success = false;
$error = '';

// Auto-migrate: add active column if missing and set NULL values to 1
try {
    $cols = $pdo->query("SHOW COLUMNS FROM part_supplier_mapping")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('active', $cols)) {
        $pdo->exec("ALTER TABLE part_supplier_mapping ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER is_preferred");
    }
    $pdo->exec("UPDATE part_supplier_mapping SET active = 1 WHERE active IS NULL");
} catch (PDOException $e) {
    // Table may not exist yet
}

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
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                        <button type="button" onclick="editSupplier(<?= $supplier['id'] ?>)" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Edit</button>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="mapping_id" value="<?= $supplier['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                                                <?= $supplier['active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Remove this supplier?');">
                                            <input type="hidden" name="action" value="delete_supplier">
                                            <input type="hidden" name="mapping_id" value="<?= $supplier['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                        </form>
                                    </div>
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
                            <?= htmlspecialchars($s['supplier_code'] ? $s['supplier_code'] . ' - ' . $s['supplier_name'] : $s['supplier_name']) ?>
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

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 25px; border-radius: 10px; width: 100%; max-width: 500px; margin: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <h3 style="margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #3498db;">Edit Supplier Details</h3>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="update_supplier">
            <input type="hidden" name="mapping_id" id="edit_mapping_id">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Supplier</label>
                <input type="text" id="edit_supplier_name" disabled style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Rate (₹) *</label>
                <input type="number" name="supplier_rate" id="edit_rate" step="0.01" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Lead Time (days)</label>
                    <input type="number" name="lead_time_days" id="edit_lead_time" min="1" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Min Order Qty</label>
                    <input type="number" name="min_order_qty" id="edit_min_qty" min="1" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_preferred" id="edit_preferred">
                    <span>Mark as Preferred Supplier</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Store supplier data for edit modal
const supplierData = <?= json_encode($currentSuppliers) ?>;

function editSupplier(mappingId) {
    // Find supplier by mapping id
    const supplier = supplierData.find(s => s.id == mappingId);
    if (!supplier) {
        alert('Supplier not found');
        return;
    }

    // Populate modal fields
    document.getElementById('edit_mapping_id').value = mappingId;
    document.getElementById('edit_supplier_name').value = supplier.supplier_name + (supplier.supplier_code ? ' (' + supplier.supplier_code + ')' : '');
    document.getElementById('edit_rate').value = parseFloat(supplier.supplier_rate).toFixed(2);
    document.getElementById('edit_lead_time').value = supplier.lead_time_days || 5;
    document.getElementById('edit_min_qty').value = supplier.min_order_qty || 1;
    document.getElementById('edit_preferred').checked = supplier.is_preferred == 1;

    // Show modal
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

function updateSupplierFields() {
    // Could auto-populate supplier info here if needed
}
</script>

</body>
</html>
