<?php
/**
 * Session Diagnostic Test
 * Upload this to the online server and access it to debug session issues
 */

// Show all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

echo "<h1>Session Diagnostic Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;max-width:800px;margin:0 auto;}
.ok{color:green;font-weight:bold;} .error{color:red;font-weight:bold;}
pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;}
.test{margin:15px 0;padding:15px;border:1px solid #ddd;border-radius:5px;}
</style>";

// Test 1: PHP Version
echo "<div class='test'>";
echo "<h3>1. PHP Version</h3>";
echo "<p>PHP Version: <strong>" . phpversion() . "</strong></p>";
echo "</div>";

// Test 2: Session Status
echo "<div class='test'>";
echo "<h3>2. Session Status</h3>";
echo "<p>Session ID: <strong>" . session_id() . "</strong></p>";
echo "<p>Session Name: <strong>" . session_name() . "</strong></p>";
echo "<p>Session Save Path: <strong>" . session_save_path() . "</strong></p>";
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}
echo "<p>Path Writable: <strong class='" . (is_writable($savePath) ? "ok'>Yes" : "error'>No") . "</strong></p>";
echo "</div>";

// Test 3: Cookie settings
echo "<div class='test'>";
echo "<h3>3. Cookie Settings</h3>";
$cookieParams = session_get_cookie_params();
echo "<pre>" . print_r($cookieParams, true) . "</pre>";
echo "<p>Received Cookies: </p><pre>" . print_r($_COOKIE, true) . "</pre>";
echo "</div>";

// Test 4: Session Read/Write Test
echo "<div class='test'>";
echo "<h3>4. Session Read/Write Test</h3>";

// Set a test value
$_SESSION['test_timestamp'] = time();
$_SESSION['test_value'] = 'Hello from session!';

if (isset($_SESSION['visit_count'])) {
    $_SESSION['visit_count']++;
} else {
    $_SESSION['visit_count'] = 1;
}

echo "<p>Visit Count: <strong>" . $_SESSION['visit_count'] . "</strong></p>";
echo "<p>Test Value: <strong>" . $_SESSION['test_value'] . "</strong></p>";
echo "<p>If visit count increases on refresh, sessions are working correctly.</p>";
echo "</div>";

// Test 5: Database connection
echo "<div class='test'>";
echo "<h3>5. Database Connection</h3>";
try {
    include "../db.php";
    echo "<p class='ok'>Database connected successfully!</p>";

    // Check employees table
    $result = $pdo->query("SELECT COUNT(*) as cnt,
                           GROUP_CONCAT(emp_id ORDER BY id LIMIT 5) as sample_ids
                           FROM employees WHERE status = 'Active'");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "<p>Active Employees: <strong>" . $row['cnt'] . "</strong></p>";
    echo "<p>Sample emp_ids: <strong>" . ($row['sample_ids'] ?: 'None') . "</strong></p>";

    // Show first 5 employees with phone
    $emps = $pdo->query("SELECT id, emp_id, first_name, phone FROM employees WHERE status = 'Active' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Sample employees:</p>";
    echo "<pre>";
    foreach ($emps as $e) {
        echo "ID: " . $e['id'] . " | emp_id: " . $e['emp_id'] . " | Name: " . $e['first_name'] . " | Phone: " . $e['phone'] . "\n";
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "<p class='error'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Test 6: Simulate Login Test
echo "<div class='test'>";
echo "<h3>6. Attendance Login Simulation</h3>";

if (isset($_POST['test_login'])) {
    $emp_id = trim($_POST['emp_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    echo "<p>Attempting login with: emp_id='" . htmlspecialchars($emp_id) . "', phone='" . htmlspecialchars($phone) . "'</p>";

    $stmt = $pdo->prepare("
        SELECT id, emp_id, first_name, last_name, phone, department, designation, photo_path, status
        FROM employees
        WHERE emp_id = ? AND phone = ?
    ");
    $stmt->execute([$emp_id, $phone]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        echo "<p class='ok'>Employee FOUND!</p>";
        echo "<pre>" . print_r($employee, true) . "</pre>";

        if ($employee['status'] === 'Active') {
            // Set session like attendance_login.php does
            $_SESSION['emp_attendance_id'] = $employee['id'];
            $_SESSION['emp_attendance_emp_id'] = $employee['emp_id'];
            $_SESSION['emp_attendance_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
            $_SESSION['emp_attendance_dept'] = $employee['department'];
            $_SESSION['emp_attendance_designation'] = $employee['designation'];
            $_SESSION['emp_attendance_photo'] = $employee['photo_path'];

            echo "<p class='ok'>Session variables set successfully!</p>";
            echo "<p>Click 'Check Portal Session' below to verify session persists on attendance_portal.php</p>";
        } else {
            echo "<p class='error'>Employee is not Active (status: " . $employee['status'] . ")</p>";
        }
    } else {
        echo "<p class='error'>Employee NOT FOUND</p>";

        // Check why
        $checkId = $pdo->prepare("SELECT emp_id, phone, status FROM employees WHERE emp_id = ?");
        $checkId->execute([$emp_id]);
        $found = $checkId->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            echo "<p>Found emp_id but: phone in DB = '" . $found['phone'] . "', status = '" . $found['status'] . "'</p>";
            if ($found['phone'] !== $phone) {
                echo "<p class='error'>Phone number mismatch!</p>";
            }
        } else {
            echo "<p class='error'>emp_id '" . htmlspecialchars($emp_id) . "' does not exist in database</p>";
        }
    }
} else {
    echo "<p>Enter employee credentials to test login:</p>";
}

echo "<form method='post'>";
echo "<input type='text' name='emp_id' placeholder='e.g. EMP-0001' style='padding:10px;margin:5px;' value='" . htmlspecialchars($_POST['emp_id'] ?? '') . "'>";
echo "<input type='text' name='phone' placeholder='Phone number' style='padding:10px;margin:5px;' value='" . htmlspecialchars($_POST['phone'] ?? '') . "'>";
echo "<button type='submit' name='test_login' style='padding:10px 20px;margin:5px;background:#4CAF50;color:white;border:none;cursor:pointer;'>Test Login</button>";
echo "</form>";
echo "</div>";

// Test 7: Current Session Data
echo "<div class='test'>";
echo "<h3>7. Current Session Data</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

if (isset($_SESSION['emp_attendance_id'])) {
    echo "<p class='ok'>Attendance session is SET!</p>";
    echo "<p><a href='attendance_portal.php' style='padding:10px 20px;background:#2196F3;color:white;text-decoration:none;border-radius:5px;'>Check Portal Session</a></p>";
} else {
    echo "<p class='error'>No attendance session found</p>";
}
echo "</div>";

// Test 8: Clear Session
echo "<div class='test'>";
echo "<h3>8. Session Actions</h3>";
if (isset($_GET['clear'])) {
    session_destroy();
    echo "<p class='ok'>Session cleared! <a href='session_test.php'>Refresh</a></p>";
} else {
    echo "<p><a href='?clear=1' style='padding:10px 20px;background:#f44336;color:white;text-decoration:none;border-radius:5px;'>Clear All Session Data</a></p>";
}
echo "</div>";

// Test 9: Headers already sent check
echo "<div class='test'>";
echo "<h3>9. Headers Status</h3>";
if (headers_sent($file, $line)) {
    echo "<p>Headers already sent in <strong>$file</strong> on line <strong>$line</strong></p>";
} else {
    echo "<p class='ok'>Headers not sent yet (good for redirects)</p>";
}
echo "</div>";
?>
