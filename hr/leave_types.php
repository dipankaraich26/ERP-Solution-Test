<?php
/**
 * Leave Types Management
 * Add, edit, activate/deactivate leave types
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$errors = [];
$editType = null;

// Check if table exists before handling forms
$tableExists = false;
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();
    $tableExists = (bool)$tableCheck;
} catch (PDOException $e) {}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $leave_type_name = trim($_POST['leave_type_name'] ?? '');
        $leave_code = strtoupper(trim($_POST['leave_code'] ?? ''));
        $max_days_per_year = intval($_POST['max_days_per_year'] ?? 0);
        $is_paid = isset($_POST['is_paid']) ? 1 : 0;
        $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');

        // Validation
        if ($leave_type_name === '') $errors[] = "Leave type name is required";
        if ($leave_code === '') $errors[] = "Leave code is required";
        if (strlen($leave_code) > 10) $errors[] = "Leave code must be 10 characters or less";

        // Check duplicate code
        if (empty($errors)) {
            $checkSql = "SELECT id FROM leave_types WHERE leave_code = ?";
            $params = [$leave_code];
            if ($id) {
                $checkSql .= " AND id != ?";
                $params[] = $id;
            }
            $check = $pdo->prepare($checkSql);
            $check->execute($params);
            if ($check->fetch()) {
                $errors[] = "Leave code '$leave_code' already exists";
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO leave_types (leave_type_name, leave_code, max_days_per_year, is_paid, requires_approval, description)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$leave_type_name, $leave_code, $max_days_per_year, $is_paid, $requires_approval, $description]);
                    setModal("Success", "Leave type '$leave_type_name' added successfully!");
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE leave_types SET
                            leave_type_name = ?,
                            leave_code = ?,
                            max_days_per_year = ?,
                            is_paid = ?,
                            requires_approval = ?,
                            description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$leave_type_name, $leave_code, $max_days_per_year, $is_paid, $requires_approval, $description, $id]);
                    setModal("Success", "Leave type updated successfully!");
                }
                header("Location: leave_types.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $pdo->prepare("UPDATE leave_types SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            header("Location: leave_types.php");
            exit;
        }
    }
}

// Load type for editing
if (isset($_GET['edit']) && $tableExists) {
    $editType = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
    $editType->execute([$_GET['edit']]);
    $editType = $editType->fetch(PDO::FETCH_ASSOC);
}

// Fetch all leave types
$leaveTypes = [];
try {
    if ($tableExists) {
        $leaveTypes = $pdo->query("SELECT * FROM leave_types ORDER BY leave_code")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Table doesn't exist
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Types - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .leave-types-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .leave-types-container { grid-template-columns: 1fr; }
        }
        .form-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-panel h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #30cfd0;
            padding-bottom: 10px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        .types-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .types-table th, .types-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .types-table th {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            font-weight: 600;
        }
        .types-table tr:hover { background: #f8f9fa; }
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e9ecef; color: #495057; }
        .action-btns { display: flex; gap: 5px; }
        .btn-sm { padding: 5px 10px; font-size: 0.85em; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Leave Types</h1>
        <a href="leaves.php" class="btn btn-secondary">View Leave Requests</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #721c24;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="leave-types-container">
        <!-- Add/Edit Form -->
        <?php if ($tableExists): ?>
        <div class="form-panel">
            <h3><?= $editType ? 'Edit Leave Type' : 'Add New Leave Type' ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editType ? 'edit' : 'add' ?>">
                <?php if ($editType): ?>
                    <input type="hidden" name="id" value="<?= $editType['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Leave Type Name *</label>
                    <input type="text" name="leave_type_name" required
                           value="<?= htmlspecialchars($editType['leave_type_name'] ?? $_POST['leave_type_name'] ?? '') ?>"
                           placeholder="e.g., Casual Leave">
                </div>

                <div class="form-group">
                    <label>Leave Code *</label>
                    <input type="text" name="leave_code" required maxlength="10"
                           value="<?= htmlspecialchars($editType['leave_code'] ?? $_POST['leave_code'] ?? '') ?>"
                           placeholder="e.g., CL" style="text-transform: uppercase;">
                </div>

                <div class="form-group">
                    <label>Max Days Per Year (0 = Unlimited)</label>
                    <input type="number" name="max_days_per_year" min="0"
                           value="<?= $editType['max_days_per_year'] ?? $_POST['max_days_per_year'] ?? '0' ?>">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Optional description..."><?= htmlspecialchars($editType['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="is_paid"
                                   <?= ($editType['is_paid'] ?? $_POST['is_paid'] ?? 1) ? 'checked' : '' ?>>
                            Paid Leave
                        </label>
                        <label>
                            <input type="checkbox" name="requires_approval"
                                   <?= ($editType['requires_approval'] ?? $_POST['requires_approval'] ?? 1) ? 'checked' : '' ?>>
                            Requires Approval
                        </label>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary"><?= $editType ? 'Update' : 'Add' ?> Leave Type</button>
                    <?php if ($editType): ?>
                        <a href="leave_types.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Leave Types Table -->
        <div style="<?= !$tableExists ? 'grid-column: span 2;' : '' ?>">
            <table class="types-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Leave Type</th>
                        <th>Days/Year</th>
                        <th>Paid</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$tableExists): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">
                                <div style="color: #856404; background: #fff3cd; padding: 20px; border-radius: 8px;">
                                    <strong>Setup Required</strong><br>
                                    Leave management tables not found.<br><br>
                                    <a href="/admin/setup_leave_management.php" class="btn btn-primary">Run Setup Script</a>
                                </div>
                            </td>
                        </tr>
                    <?php elseif (empty($leaveTypes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666;">
                                No leave types found. <a href="/admin/setup_leave_management.php">Run setup</a> to create default types.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($lt['leave_code']) ?></strong></td>
                            <td><?= htmlspecialchars($lt['leave_type_name']) ?></td>
                            <td><?= $lt['max_days_per_year'] ?: 'Unlimited' ?></td>
                            <td>
                                <span class="badge <?= $lt['is_paid'] ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $lt['requires_approval'] ? 'badge-info' : 'badge-secondary' ?>">
                                    <?= $lt['requires_approval'] ? 'Required' : 'Not Required' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $lt['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $lt['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="?edit=<?= $lt['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $lt['id'] ?>">
                                        <button type="submit" class="btn <?= $lt['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm"
                                                onclick="return confirm('<?= $lt['is_active'] ? 'Deactivate' : 'Activate' ?> this leave type?')">
                                            <?= $lt['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
