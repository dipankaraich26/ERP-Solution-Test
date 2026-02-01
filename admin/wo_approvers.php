<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' && !empty($_POST['employee_id'])) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wo_approvers (employee_id, can_approve_wo_closing, is_active)
                    VALUES (?, 1, 1)
                    ON DUPLICATE KEY UPDATE is_active = 1, can_approve_wo_closing = 1
                ");
                $stmt->execute([$_POST['employee_id']]);
                $success = "Approver added successfully!";
            } catch (PDOException $e) {
                $error = "Failed to add approver: " . $e->getMessage();
            }
        }

        if ($action === 'remove' && !empty($_POST['approver_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE wo_approvers SET is_active = 0 WHERE id = ?");
                $stmt->execute([$_POST['approver_id']]);
                $success = "Approver removed successfully!";
            } catch (PDOException $e) {
                $error = "Failed to remove approver: " . $e->getMessage();
            }
        }

        if ($action === 'activate' && !empty($_POST['approver_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE wo_approvers SET is_active = 1 WHERE id = ?");
                $stmt->execute([$_POST['approver_id']]);
                $success = "Approver activated successfully!";
            } catch (PDOException $e) {
                $error = "Failed to activate approver: " . $e->getMessage();
            }
        }
    }
}

// Fetch current approvers
$approvers = $pdo->query("
    SELECT a.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation, e.email
    FROM wo_approvers a
    JOIN employees e ON a.employee_id = e.id
    ORDER BY a.is_active DESC, e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees not yet approvers
$nonApprovers = $pdo->query("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.department, e.designation
    FROM employees e
    WHERE e.status = 'Active'
    AND e.id NOT IN (SELECT employee_id FROM wo_approvers WHERE is_active = 1)
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <style>
        .approver-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .approver-card.inactive {
            background: #f9fafb;
            opacity: 0.7;
        }
        .approver-info h4 {
            margin: 0 0 5px 0;
            color: #1f2937;
        }
        .approver-info p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9em;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <h1>Manage Work Order Approvers</h1>

    <p style="margin-bottom: 20px;">
        <a href="setup_wo_quality_checklist.php" class="btn btn-secondary">Back to Setup</a>
        <a href="../work_orders/index.php" class="btn btn-secondary">Work Orders</a>
    </p>

    <?php if ($success): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Add New Approver -->
    <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
        <h3 style="margin: 0 0 15px 0; color: #0369a1;">Add New Approver</h3>
        <form method="post" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add">
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Select Employee</label>
                <select name="employee_id" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="">-- Select Employee --</option>
                    <?php foreach ($nonApprovers as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            <?php if ($emp['emp_id']): ?>(<?= htmlspecialchars($emp['emp_id']) ?>)<?php endif; ?>
                            <?php if ($emp['designation']): ?> - <?= htmlspecialchars($emp['designation']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add Approver</button>
        </form>
    </div>

    <!-- Current Approvers -->
    <h2>Current Approvers</h2>

    <?php if (empty($approvers)): ?>
        <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 20px; border-radius: 8px; text-align: center; color: #92400e;">
            <p style="margin: 0;">No approvers configured yet. Add employees who can approve work order closings.</p>
        </div>
    <?php else: ?>
        <?php foreach ($approvers as $approver): ?>
            <div class="approver-card <?= $approver['is_active'] ? '' : 'inactive' ?>">
                <div class="approver-info">
                    <h4>
                        <?= htmlspecialchars($approver['first_name'] . ' ' . $approver['last_name']) ?>
                        <?php if ($approver['emp_id']): ?>
                            <span style="color: #6b7280; font-weight: normal;">(<?= htmlspecialchars($approver['emp_id']) ?>)</span>
                        <?php endif; ?>
                    </h4>
                    <p>
                        <?= htmlspecialchars($approver['designation'] ?? 'No designation') ?>
                        <?php if ($approver['department']): ?> | <?= htmlspecialchars($approver['department']) ?><?php endif; ?>
                        <?php if ($approver['email']): ?> | <?= htmlspecialchars($approver['email']) ?><?php endif; ?>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span class="status-badge <?= $approver['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $approver['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <?php if ($approver['is_active']): ?>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="approver_id" value="<?= $approver['id'] ?>">
                            <button type="submit" class="btn" style="background: #ef4444; color: white; padding: 6px 12px; font-size: 0.85em;"
                                    onclick="return confirm('Remove this approver?');">
                                Remove
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="approver_id" value="<?= $approver['id'] ?>">
                            <button type="submit" class="btn" style="background: #10b981; color: white; padding: 6px 12px; font-size: 0.85em;">
                                Activate
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="background: #e0e7ff; border: 1px solid #6366f1; border-radius: 8px; padding: 15px; margin-top: 25px;">
        <strong style="color: #4338ca;">Note:</strong>
        <span style="color: #3730a3;">
            Only active approvers will appear in the approval request dropdown when closing work orders.
            Approvers can view and approve/reject work order closing requests from the Work Orders section.
        </span>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
