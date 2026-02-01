<?php
session_start();
include "../db.php";

$error = '';

// If already logged in, redirect to portal
if (isset($_SESSION['emp_attendance_id'])) {
    header("Location: attendance_portal.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['emp_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($emp_id) || empty($phone)) {
        $error = "Please enter both Employee ID and Phone Number";
    } else {
        // Verify employee credentials
        $stmt = $pdo->prepare("
            SELECT id, emp_id, first_name, last_name, phone, department, designation, photo_path
            FROM employees
            WHERE emp_id = ? AND phone = ? AND status = 'Active'
        ");
        $stmt->execute([$emp_id, $phone]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            // Set session
            $_SESSION['emp_attendance_id'] = $employee['id'];
            $_SESSION['emp_attendance_emp_id'] = $employee['emp_id'];
            $_SESSION['emp_attendance_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
            $_SESSION['emp_attendance_dept'] = $employee['department'];
            $_SESSION['emp_attendance_designation'] = $employee['designation'];
            $_SESSION['emp_attendance_photo'] = $employee['photo_path'];

            header("Location: attendance_portal.php");
            exit;
        } else {
            $error = "Invalid Employee ID or Phone Number";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Attendance Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Attendance">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="Employee Attendance App - Mark your daily attendance">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- App Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon.php?size=192">
    <link rel="apple-touch-icon" href="icons/icon.php?size=180">

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
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5em;
            color: white;
        }
        .login-header h1 {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 5px;
        }
        .login-header p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .error-message {
            background: #fee;
            color: #c00;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .login-btn:active {
            transform: translateY(0);
        }
        .current-time {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .current-time .time {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .current-time .date {
            color: #7f8c8d;
            margin-top: 5px;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <div class="icon">&#128100;</div>
        <h1>Employee Attendance</h1>
        <p>Login to mark your attendance</p>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" name="emp_id" placeholder="Enter your Employee ID"
                   value="<?= htmlspecialchars($_POST['emp_id'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="Enter your registered phone" required>
        </div>

        <button type="submit" class="login-btn">Login</button>
    </form>

    <div class="current-time">
        <div class="time" id="currentTime"></div>
        <div class="date" id="currentDate"></div>
    </div>

    <a href="../index.php" class="back-link">Back to Main System</a>
</div>

<script>
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    document.getElementById('currentTime').textContent = timeStr;
    document.getElementById('currentDate').textContent = dateStr;
}

updateTime();
setInterval(updateTime, 1000);

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/hr/sw.js')
            .then(registration => {
                console.log('ServiceWorker registered:', registration.scope);
            })
            .catch(error => {
                console.log('ServiceWorker registration failed:', error);
            });
    });
}

// Install prompt for PWA
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Show install button
    const installBanner = document.createElement('div');
    installBanner.id = 'installBanner';
    installBanner.innerHTML = `
        <div style="position: fixed; bottom: 20px; left: 20px; right: 20px; background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: space-between; z-index: 9999;">
            <span style="color: #2c3e50; font-weight: 500;">Install Attendance App</span>
            <div>
                <button onclick="dismissInstall()" style="background: none; border: none; color: #999; padding: 8px 15px; cursor: pointer;">Later</button>
                <button onclick="installApp()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">Install</button>
            </div>
        </div>
    `;
    document.body.appendChild(installBanner);
});

function installApp() {
    const banner = document.getElementById('installBanner');
    if (banner) banner.remove();

    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted install');
            }
            deferredPrompt = null;
        });
    }
}

function dismissInstall() {
    const banner = document.getElementById('installBanner');
    if (banner) banner.remove();
}
</script>

</body>
</html>
