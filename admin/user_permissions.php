<?php
session_start();
include "../db.php";
include "../includes/dialog.php";

// Check if tables exist, if not redirect to setup
try {
    $pdo->query("SELECT 1 FROM modules LIMIT 1");
    $pdo->query("SELECT 1 FROM user_permissions LIMIT 1");
} catch (Exception $e) {
    header("Location: setup_user_permissions.php");
    exit;
}

$selectedUserId = $_GET['user_id'] ?? null;
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $userId = (int)$_POST['user_id'];
    $permissions = $_POST['permissions'] ?? [];

    try {
        $pdo->beginTransaction();

        // Delete existing permissions for this user
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);

        // Insert new permissions
        $insertStmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, module, can_view, can_create, can_edit, can_delete)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($permissions as $module => $perms) {
            $canView = isset($perms['view']) ? 1 : 0;
            $canCreate = isset($perms['create']) ? 1 : 0;
            $canEdit = isset($perms['edit']) ? 1 : 0;
            $canDelete = isset($perms['delete']) ? 1 : 0;

            // Only insert if at least one permission is granted
            if ($canView || $canCreate || $canEdit || $canDelete) {
                $insertStmt->execute([$userId, $module, $canView, $canCreate, $canEdit, $canDelete]);
            }
        }

        $pdo->commit();
        setModal("Success", "Permissions updated successfully!");
        header("Location: user_permissions.php?user_id=" . $userId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error saving permissions: " . $e->getMessage();
    }
}

// Fetch all non-admin users
$users = $pdo->query("
    SELECT id, username, full_name, role, is_active
    FROM users
    WHERE role != 'admin'
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch modules grouped
$modules = $pdo->query("
    SELECT module_key, module_name, module_group
    FROM modules
    WHERE is_active = 1
    ORDER BY display_order
")->fetchAll(PDO::FETCH_ASSOC);

// Group modules
$moduleGroups = [];
foreach ($modules as $m) {
    $moduleGroups[$m['module_group']][] = $m;
}

// Fetch current permissions for selected user
$userPermissions = [];
if ($selectedUserId) {
    $permStmt = $pdo->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$selectedUserId]);
    while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
        $userPermissions[$row['module']] = $row;
    }
}

// Get selected user info
$selectedUser = null;
if ($selectedUserId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$selectedUserId]);
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Permissions - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1200px; }

        .user-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
        }
        .user-selector select {
            padding: 10px 15px;
            font-size: 1em;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 300px;
        }

        .permissions-container {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .module-group {
            margin-bottom: 30px;
        }
        .module-group-header {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;
            border-radius: 6px 6px 0 0;
            font-weight: bold;
            font-size: 1.1em;
        }

        .permissions-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        .permissions-table th {
            background: #ecf0f1;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        .permissions-table th:first-child {
            text-align: left;
            width: 40%;
        }
        .permissions-table td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        .permissions-table td:not(:first-child) {
            text-align: center;
        }
        .permissions-table tr:hover {
            background: #f8f9fa;
        }
        .permissions-table input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .permission-icon {
            font-size: 0.9em;
            color: #666;
        }

        .btn-group {
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        .quick-actions {
            margin-bottom: 15px;
            padding: 10px;
            background: #e8f4fd;
            border-radius: 4px;
        }
        .quick-actions button {
            margin-right: 10px;
            padding: 8px 15px;
            border: 1px solid #3498db;
            background: white;
            color: #3498db;
            border-radius: 4px;
            cursor: pointer;
        }
        .quick-actions button:hover {
            background: #3498db;
            color: white;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        .role-manager { background: #3498db; color: white; }
        .role-user { background: #27ae60; color: white; }
        .role-viewer { background: #95a5a6; color: white; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .select-col-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.8em;
            color: #3498db;
            text-decoration: underline;
            padding: 2px 5px;
        }
        .select-col-btn:hover {
            color: #2980b9;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="admin-container">
        <h1>User Permissions</h1>

        <p>
            <a href="users.php" class="btn btn-secondary">User Management</a>
            <a href="settings.php" class="btn btn-secondary">Company Settings</a>
            <a href="/" class="btn btn-secondary">Back to Dashboard</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $e): ?>
                <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Note:</strong> Admin users automatically have full access to all modules.
            Configure permissions for Manager, User, and Viewer roles below.
            User-specific permissions override role-based defaults.
        </div>

        <!-- User Selector -->
        <div class="user-selector">
            <form method="get">
                <label><strong>Select User:</strong></label>
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">-- Choose a user --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $selectedUserId == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['full_name']) ?> (<?= $u['username'] ?>) - <?= ucfirst($u['role']) ?>
                            <?= !$u['is_active'] ? ' [INACTIVE]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedUser): ?>
        <div class="permissions-container">
            <h2>
                Permissions for: <?= htmlspecialchars($selectedUser['full_name']) ?>
                <span class="role-badge role-<?= $selectedUser['role'] ?>"><?= ucfirst($selectedUser['role']) ?></span>
            </h2>

            <form method="post">
                <input type="hidden" name="user_id" value="<?= $selectedUser['id'] ?>">

                <div class="quick-actions">
                    <strong>Quick Actions:</strong>
                    <button type="button" onclick="selectAll()">Select All</button>
                    <button type="button" onclick="clearAll()">Clear All</button>
                    <button type="button" onclick="selectAllView()">View Only</button>
                    <button type="button" onclick="selectViewCreate()">View + Create</button>
                </div>

                <?php foreach ($moduleGroups as $groupName => $groupModules): ?>
                <div class="module-group">
                    <div class="module-group-header"><?= htmlspecialchars($groupName) ?></div>
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>
                                    View
                                    <br><button type="button" class="select-col-btn" onclick="toggleColumn('view', '<?= htmlspecialchars($groupName) ?>')">toggle</button>
                                </th>
                                <th>
                                    Create
                                    <br><button type="button" class="select-col-btn" onclick="toggleColumn('create', '<?= htmlspecialchars($groupName) ?>')">toggle</button>
                                </th>
                                <th>
                                    Edit
                                    <br><button type="button" class="select-col-btn" onclick="toggleColumn('edit', '<?= htmlspecialchars($groupName) ?>')">toggle</button>
                                </th>
                                <th>
                                    Delete
                                    <br><button type="button" class="select-col-btn" onclick="toggleColumn('delete', '<?= htmlspecialchars($groupName) ?>')">toggle</button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupModules as $mod):
                                $perm = $userPermissions[$mod['module_key']] ?? [];
                            ?>
                            <tr data-group="<?= htmlspecialchars($groupName) ?>">
                                <td><?= htmlspecialchars($mod['module_name']) ?></td>
                                <td>
                                    <input type="checkbox"
                                           name="permissions[<?= $mod['module_key'] ?>][view]"
                                           class="perm-view"
                                           <?= !empty($perm['can_view']) ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <input type="checkbox"
                                           name="permissions[<?= $mod['module_key'] ?>][create]"
                                           class="perm-create"
                                           <?= !empty($perm['can_create']) ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <input type="checkbox"
                                           name="permissions[<?= $mod['module_key'] ?>][edit]"
                                           class="perm-edit"
                                           <?= !empty($perm['can_edit']) ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <input type="checkbox"
                                           name="permissions[<?= $mod['module_key'] ?>][delete]"
                                           class="perm-delete"
                                           <?= !empty($perm['can_delete']) ? 'checked' : '' ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>

                <div class="btn-group">
                    <button type="submit" name="save_permissions" class="btn btn-success">Save Permissions</button>
                    <a href="user_permissions.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px;">
            <p style="font-size: 1.2em; color: #666;">Select a user from the dropdown above to manage their permissions.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.permissions-table input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function clearAll() {
    document.querySelectorAll('.permissions-table input[type="checkbox"]').forEach(cb => cb.checked = false);
}

function selectAllView() {
    clearAll();
    document.querySelectorAll('.perm-view').forEach(cb => cb.checked = true);
}

function selectViewCreate() {
    clearAll();
    document.querySelectorAll('.perm-view, .perm-create').forEach(cb => cb.checked = true);
}

function toggleColumn(permType, groupName) {
    const rows = document.querySelectorAll(`tr[data-group="${groupName}"]`);
    let allChecked = true;

    rows.forEach(row => {
        const cb = row.querySelector(`.perm-${permType}`);
        if (cb && !cb.checked) allChecked = false;
    });

    rows.forEach(row => {
        const cb = row.querySelector(`.perm-${permType}`);
        if (cb) cb.checked = !allChecked;
    });
}
</script>

</body>
</html>
