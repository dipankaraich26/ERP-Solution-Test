<?php
session_start();
include "../db.php";
include "../includes/dialog.php";

$errors = [];
$editUser = null;

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        // Validation
        if ($username === '') $errors[] = "Username is required";
        if ($full_name === '') $errors[] = "Full name is required";

        if (empty($errors)) {
            if ($action === 'add') {
                if ($password === '') {
                    $errors[] = "Password is required for new users";
                } else {
                    // Check username exists
                    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $check->execute([$username]);
                    if ($check->fetch()) {
                        $errors[] = "Username already exists";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, password_hash, full_name, email, phone, role, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $hash, $full_name, $email ?: null, $phone ?: null, $role, $is_active]);
                        setModal("Success", "User '$username' created successfully!");
                        header("Location: users.php");
                        exit;
                    }
                }
            } else {
                // Edit
                $user_id = (int)$_POST['user_id'];

                // Check username unique (excluding current user)
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$username, $user_id]);
                if ($check->fetch()) {
                    $errors[] = "Username already taken by another user";
                } else {
                    if ($password !== '') {
                        // Update with new password
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET username = ?, password_hash = ?, full_name = ?,
                            email = ?, phone = ?, role = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $hash, $full_name, $email ?: null, $phone ?: null, $role, $is_active, $user_id]);
                    } else {
                        // Update without password
                        $stmt = $pdo->prepare("
                            UPDATE users SET username = ?, full_name = ?,
                            email = ?, phone = ?, role = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $full_name, $email ?: null, $phone ?: null, $role, $is_active, $user_id]);
                    }
                    setModal("Success", "User '$username' updated successfully!");
                    header("Location: users.php");
                    exit;
                }
            }
        }
    }

    if ($action === 'delete') {
        $user_id = (int)$_POST['user_id'];
        // Prevent deleting admin user with id=1
        if ($user_id === 1) {
            setModal("Error", "Cannot delete the primary admin user.");
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            setModal("Success", "User deleted successfully!");
        }
        header("Location: users.php");
        exit;
    }
}

// Check if editing
if (isset($_GET['edit'])) {
    $editUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $editUser->execute([(int)$_GET['edit']]);
    $editUser = $editUser->fetch(PDO::FETCH_ASSOC);
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container { max-width: 1100px; }
        .user-form {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .user-form h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-top: 25px;
        }
        .checkbox-group input { width: auto; }

        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .user-table th { background: #f5f5f5; font-weight: bold; }
        .user-table tr:hover { background: #fafafa; }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .role-admin { background: #e74c3c; color: white; }
        .role-manager { background: #3498db; color: white; }
        .role-user { background: #27ae60; color: white; }
        .role-viewer { background: #95a5a6; color: white; }

        .status-active { color: #27ae60; }
        .status-inactive { color: #e74c3c; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="admin-container">
        <h1>User Management</h1>

        <p>
            <a href="settings.php" class="btn btn-secondary">Company Settings</a>
            <a href="/" class="btn btn-secondary">Back to Dashboard</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <div class="user-form">
            <h3><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required
                               value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required
                               value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Password <?= $editUser ? '(leave blank to keep)' : '*' ?></label>
                        <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone"
                               value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="manager" <?= ($editUser['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="user" <?= ($editUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="viewer" <?= ($editUser['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active"
                               <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" style="margin: 0; font-weight: normal;">Active</label>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-success">
                        <?= $editUser ? 'Update User' : 'Add User' ?>
                    </button>
                    <?php if ($editUser): ?>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <h2>All Users</h2>
        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td>
                        <span class="role-badge role-<?= $u['role'] ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="status-active">Active</span>
                        <?php else: ?>
                            <span class="status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['last_login'] ? date('d-M-Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                    <td>
                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm">Edit</a>
                        <?php if ($u['id'] !== 1): ?>
                        <form method="post" style="display: inline;"
                              onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px;">
            <strong>Role Permissions:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong>Admin:</strong> Full access to all modules including user management</li>
                <li><strong>Manager:</strong> Can view/create/edit most modules, limited delete access</li>
                <li><strong>User:</strong> Can view and create in limited modules</li>
                <li><strong>Viewer:</strong> Read-only access to allowed modules</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>
