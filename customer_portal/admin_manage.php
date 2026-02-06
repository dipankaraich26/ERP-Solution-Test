<?php
include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";
requireLogin();

showModal();

// Ensure portal_password column exists
try {
    $pdo->exec("ALTER TABLE customers ADD COLUMN portal_password VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE customers ADD COLUMN portal_enabled TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cust_id = (int)($_POST['customer_id'] ?? 0);

    if ($action === 'set_password' && $cust_id > 0) {
        $new_password = trim($_POST['new_password'] ?? '');
        if (strlen($new_password) >= 6) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE customers SET portal_password = ?, portal_enabled = 1 WHERE id = ?");
            $updateStmt->execute([$hashed, $cust_id]);
            setModal("Success", "Portal password has been set successfully.");
        } else {
            setModal("Error", "Password must be at least 6 characters.");
        }
        header("Location: admin_manage.php");
        exit;
    }

    if ($action === 'disable_portal' && $cust_id > 0) {
        $updateStmt = $pdo->prepare("UPDATE customers SET portal_enabled = 0 WHERE id = ?");
        $updateStmt->execute([$cust_id]);
        setModal("Success", "Portal access has been disabled for this customer.");
        header("Location: admin_manage.php");
        exit;
    }

    if ($action === 'enable_portal' && $cust_id > 0) {
        $updateStmt = $pdo->prepare("UPDATE customers SET portal_enabled = 1 WHERE id = ?");
        $updateStmt->execute([$cust_id]);
        setModal("Success", "Portal access has been enabled for this customer.");
        header("Location: admin_manage.php");
        exit;
    }
}

// Search
$search = trim($_GET['search'] ?? '');

// Get customers
$where = "";
$params = [];
if ($search) {
    $where = "WHERE company_name LIKE ? OR customer_name LIKE ? OR customer_id LIKE ? OR email LIKE ? OR gstin LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
}

$sql = "SELECT id, customer_id, company_name, customer_name, email, contact, gstin, portal_password, portal_enabled
        FROM customers
        $where
        ORDER BY company_name
        LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Customer Portal Access</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { color: #2c3e50; margin: 0; }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        .data-table tr:hover { background: #f8f9fa; }

        .portal-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .portal-enabled { background: #d4edda; color: #155724; }
        .portal-disabled { background: #f8d7da; color: #721c24; }
        .portal-no-password { background: #e2e3e5; color: #383d41; }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            cursor: pointer;
            border: none;
            margin-right: 5px;
        }
        .btn-set-password { background: #11998e; color: white; }
        .btn-disable { background: #dc3545; color: white; }
        .btn-enable { background: #28a745; color: white; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .modal-box h3 { margin: 0 0 20px 0; color: #2c3e50; }
        .modal-box .form-group { margin-bottom: 20px; }
        .modal-box label { display: block; margin-bottom: 8px; font-weight: 600; }
        .modal-box input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; }
        .modal-box .btn-group { display: flex; gap: 10px; }
        .modal-box .btn-group button { flex: 1; padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .modal-box .btn-save { background: #11998e; color: white; }
        .modal-box .btn-cancel { background: #6c757d; color: white; }

        .info-text {
            background: #e7f5ff;
            border: 1px solid #74c0fc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #1971c2;
        }

        .login-url {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            font-family: monospace;
            color: #495057;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <h1>Manage Customer Portal Access</h1>
    </div>

    <div class="info-text">
        <strong>Customer Portal Login URL:</strong>
        <div class="login-url"><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/customer_portal/login.php</div>
        <p style="margin-top: 10px; margin-bottom: 0;">Customers can log in using their Customer ID, Email, or GSTIN along with their portal password.</p>
    </div>

    <div class="search-box">
        <form method="get">
            <input type="text" name="search" placeholder="Search by name, customer ID, email, GSTIN..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="admin_manage.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Company / Name</th>
                    <th>Email</th>
                    <th>GSTIN</th>
                    <th>Portal Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">
                            No customers found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['customer_id']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($c['company_name'] ?: $c['customer_name'] ?: '-') ?>
                            <?php if ($c['company_name'] && $c['customer_name']): ?>
                                <br><small style="color: #7f8c8d;"><?= htmlspecialchars($c['customer_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($c['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['gstin'] ?: '-') ?></td>
                        <td>
                            <?php if (!$c['portal_password']): ?>
                                <span class="portal-status portal-no-password">No Password Set</span>
                            <?php elseif ($c['portal_enabled']): ?>
                                <span class="portal-status portal-enabled">Enabled</span>
                            <?php else: ?>
                                <span class="portal-status portal-disabled">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="action-btn btn-set-password" onclick="openPasswordModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'] ?: $c['customer_name'])) ?>')">
                                Set Password
                            </button>
                            <?php if ($c['portal_password']): ?>
                                <?php if ($c['portal_enabled']): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="disable_portal">
                                        <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="action-btn btn-disable" onclick="return confirm('Disable portal access for this customer?')">Disable</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="enable_portal">
                                        <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="action-btn btn-enable">Enable</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Password Modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box">
        <h3>Set Portal Password</h3>
        <p id="customerNameDisplay" style="color: #7f8c8d; margin-bottom: 20px;"></p>
        <form method="post">
            <input type="hidden" name="action" value="set_password">
            <input type="hidden" name="customer_id" id="modalCustomerId">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" placeholder="Minimum 6 characters" required minlength="6">
            </div>
            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="closePasswordModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPasswordModal(customerId, customerName) {
    document.getElementById('modalCustomerId').value = customerId;
    document.getElementById('customerNameDisplay').textContent = 'Customer: ' + customerName;
    document.getElementById('new_password').value = '';
    document.getElementById('passwordModal').classList.add('active');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('passwordModal').addEventListener('click', function(e) {
    if (e.target === this) closePasswordModal();
});
</script>

</body>
</html>
