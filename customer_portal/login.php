<?php
session_start();
include "../db.php";

// If already logged in as customer, redirect to dashboard
if (isset($_SESSION['customer_id'])) {
    header("Location: my_portal.php");
    exit;
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_id) || empty($password)) {
        $error = "Please enter both Customer ID/Email and Password.";
    } else {
        try {
            // Find customer by customer_id, email, or gstin
            $stmt = $pdo->prepare("
                SELECT id, customer_id, company_name, customer_name, email, contact, portal_password
                FROM customers
                WHERE (customer_id = ? OR email = ? OR gstin = ?)
                AND status = 'Active'
            ");
            $stmt->execute([$login_id, $login_id, $login_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                // Check password
                if ($customer['portal_password'] && password_verify($password, $customer['portal_password'])) {
                    // Login successful
                    $_SESSION['customer_id'] = $customer['id'];
                    $_SESSION['customer_code'] = $customer['customer_id'];
                    $_SESSION['customer_name'] = $customer['company_name'] ?: $customer['customer_name'];
                    $_SESSION['customer_email'] = $customer['email'];
                    $_SESSION['customer_logged_in'] = true;

                    header("Location: my_portal.php");
                    exit;
                } else {
                    $error = "Invalid password. Please try again.";
                }
            } else {
                $error = "Customer not found or account is inactive.";
            }
        } catch (Exception $e) {
            $error = "Login failed. Please try again.";
        }
    }
}

// Get company settings
$company_settings = null;
try {
    $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal Login - <?= htmlspecialchars($company_settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .login-header img {
            max-height: 60px;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-size: 1.5em;
            margin-bottom: 5px;
        }
        .login-header p {
            opacity: 0.9;
            font-size: 0.95em;
        }
        .login-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #11998e;
            box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
        }
        .form-group input::placeholder {
            color: #adb5bd;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(17, 153, 142, 0.4);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        .login-footer a {
            color: #11998e;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .help-text {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 8px;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e9ecef;
        }
        .divider span {
            padding: 0 15px;
            color: #6c757d;
            font-size: 0.85em;
        }
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9em;
        }
        .back-link:hover {
            color: #11998e;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Company Logo">
        <?php endif; ?>
        <h1>Customer Portal</h1>
        <p><?= htmlspecialchars($company_settings['company_name'] ?? 'Welcome') ?></p>
    </div>

    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="login_id">Customer ID / Email / GSTIN</label>
                <input type="text" id="login_id" name="login_id" placeholder="Enter your Customer ID, Email or GSTIN" required
                       value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <div class="help-text">Contact us if you forgot your password</div>
            </div>

            <button type="submit" class="btn-login">Login to Portal</button>
        </form>

        <div class="divider">
            <span>Need Help?</span>
        </div>

        <p style="text-align: center; color: #6c757d; font-size: 0.9em;">
            Contact your account manager to get your portal credentials
        </p>
    </div>

    <div class="login-footer">
        <a href="/">&larr; Back to Main Site</a>
    </div>
</div>

</body>
</html>
