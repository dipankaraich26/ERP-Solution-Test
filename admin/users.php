<?php
session_start();
include "../db.php";
include "../includes/dialog.php";

$errors = [];
$editUser = null;

// Auto-add employee_id column if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'employee_id'")->rowCount();
    if ($cols === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN employee_id INT DEFAULT NULL");
    }
} catch (Exception $e) {}

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
        $employee_id = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;

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
                            INSERT INTO users (username, password_hash, full_name, email, phone, role, is_active, employee_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $hash, $full_name, $email ?: null, $phone ?: null, $role, $is_active, $employee_id]);
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
                            email = ?, phone = ?, role = ?, is_active = ?, employee_id = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $hash, $full_name, $email ?: null, $phone ?: null, $role, $is_active, $employee_id, $user_id]);
                    } else {
                        // Update without password
                        $stmt = $pdo->prepare("
                            UPDATE users SET username = ?, full_name = ?,
                            email = ?, phone = ?, role = ?, is_active = ?, employee_id = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $full_name, $email ?: null, $phone ?: null, $role, $is_active, $employee_id, $user_id]);
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

// Fetch all users with linked employee info
$users = $pdo->query("
    SELECT u.*, e.emp_id as linked_emp_id, CONCAT(e.first_name, ' ', e.last_name) as linked_emp_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    ORDER BY u.role, u.username
")->fetchAll(PDO::FETCH_ASSOC);

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
            <a href="user_permissions.php" class="btn btn-primary">User Permissions</a>
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

                <!-- Employee Search Section -->
                <?php if (!$editUser): ?>
                <div class="employee-search-section" style="margin-bottom: 20px; padding: 15px; background: #e8f4fd; border-radius: 8px; border: 1px solid #3498db;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #2980b9;">
                        Search Employee (Optional)
                    </label>
                    <div style="position: relative;">
                        <input type="text" id="employeeSearch" placeholder="Type employee name or ID to search..."
                               style="width: 100%; padding: 10px; border: 1px solid #3498db; border-radius: 4px; font-size: 14px;"
                               autocomplete="off">
                        <div id="employeeResults" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Select an employee to auto-fill their name, email, and phone. Leave empty to enter manually.
                    </small>
                </div>
                <?php endif; ?>

                <input type="hidden" name="employee_id" id="employee_id" value="<?= htmlspecialchars($editUser['employee_id'] ?? '') ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="username" required
                               value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="full_name" required
                               value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Password <?= $editUser ? '(leave blank to keep)' : '*' ?></label>
                        <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="email"
                               value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="phone"
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
                    <th>Linked Employee</th>
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
                        <?php if ($u['linked_emp_id']): ?>
                            <span style="font-size: 0.9em;"><?= htmlspecialchars($u['linked_emp_id']) ?> - <?= htmlspecialchars($u['linked_emp_name']) ?></span>
                        <?php else: ?>
                            <span style="color: #999;">Not linked</span>
                        <?php endif; ?>
                    </td>
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
                        <?php if ($u['role'] !== 'admin'): ?>
                        <a href="user_permissions.php?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">Permissions</a>
                        <?php endif; ?>
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
            <p style="margin-top: 15px; color: #3498db;">
                <strong>Tip:</strong> Use the <a href="user_permissions.php" style="color: #2980b9;">User Permissions</a> page to set module-specific permissions for individual users (View, Create, Edit, Delete per module).
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('employeeSearch');
    const resultsDiv = document.getElementById('employeeResults');

    if (!searchInput) return; // Only on add form

    let searchTimeout = null;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 1) {
            resultsDiv.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch('/api/search_employees.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        resultsDiv.innerHTML = data.data.map(emp => `
                            <div class="employee-result" data-employee='${JSON.stringify(emp)}'
                                 style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;">
                                <div style="font-weight: bold; color: #2c3e50;">${emp.full_name}</div>
                                <div style="font-size: 0.85em; color: #666;">
                                    ${emp.emp_id}${emp.department ? ' • ' + emp.department : ''}${emp.email ? ' • ' + emp.email : ''}
                                </div>
                            </div>
                        `).join('');
                        resultsDiv.style.display = 'block';

                        // Add hover effects and click handlers
                        resultsDiv.querySelectorAll('.employee-result').forEach(item => {
                            item.addEventListener('mouseenter', () => item.style.background = '#f0f7ff');
                            item.addEventListener('mouseleave', () => item.style.background = 'white');
                            item.addEventListener('click', () => selectEmployee(JSON.parse(item.dataset.employee)));
                        });
                    } else {
                        resultsDiv.innerHTML = '<div style="padding: 12px; color: #666; text-align: center;">No employees found</div>';
                        resultsDiv.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Search error:', err);
                    resultsDiv.style.display = 'none';
                });
        }, 300);
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });

    // Show results on focus if there's text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 1 && resultsDiv.innerHTML) {
            resultsDiv.style.display = 'block';
        }
    });

    function selectEmployee(emp) {
        // Fill in the form fields
        document.getElementById('employee_id').value = emp.id;
        document.getElementById('full_name').value = emp.full_name;
        document.getElementById('email').value = emp.email || '';
        document.getElementById('phone').value = emp.phone || '';

        // Suggest username from employee ID (lowercase, no special chars)
        const usernameField = document.getElementById('username');
        if (!usernameField.value) {
            usernameField.value = emp.emp_id.toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        // Update search field to show selected
        searchInput.value = emp.display;
        resultsDiv.style.display = 'none';

        // Highlight filled fields briefly
        ['full_name', 'email', 'phone'].forEach(id => {
            const field = document.getElementById(id);
            if (field && field.value) {
                field.style.background = '#d4edda';
                setTimeout(() => field.style.background = '', 1500);
            }
        });
    }
});
</script>

</body>
</html>
