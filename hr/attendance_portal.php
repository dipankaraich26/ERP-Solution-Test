<?php
// TEMPORARY: Debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// EARLY DEBUG: Show session status immediately (before any includes)
if (isset($_GET['debug'])) {
    echo "<h2>ATTENDANCE PORTAL - Debug Mode</h2>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>emp_attendance_id: " . (isset($_SESSION['emp_attendance_id']) ? $_SESSION['emp_attendance_id'] : 'NOT SET') . "</p>";
    echo "<h3>Full Session Data:</h3><pre>" . print_r($_SESSION, true) . "</pre>";
    echo "<h3>Cookies:</h3><pre>" . print_r($_COOKIE, true) . "</pre>";
    if (!isset($_SESSION['emp_attendance_id'])) {
        echo "<p style='color:red;font-weight:bold;'>Session is empty - this is why you get redirected!</p>";
        echo "<p>Possible causes:</p>";
        echo "<ul>";
        echo "<li>Session cookie not being saved</li>";
        echo "<li>Session save path not writable</li>";
        echo "<li>Different session IDs between pages</li>";
        echo "</ul>";
    }
    echo "<p><a href='session_test.php'>Run Full Session Diagnostic</a></p>";
    exit;
}

include "../db.php";
include "../includes/dialog.php";

// Check if logged in
if (!isset($_SESSION['emp_attendance_id'])) {
    // Show debug info before redirect
    echo "<!DOCTYPE html><html><head><title>Session Debug</title></head><body>";
    echo "<h2>Session Not Found</h2>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Session data:</p><pre>" . print_r($_SESSION, true) . "</pre>";
    echo "<p>This means either:</p>";
    echo "<ul>";
    echo "<li>You haven't logged in yet</li>";
    echo "<li>The session was lost between login and this page</li>";
    echo "<li>Cookies are not being saved properly</li>";
    echo "</ul>";
    echo "<p><a href='attendance_login.php'>Go to Login</a> | <a href='session_test.php'>Run Diagnostic Test</a></p>";
    echo "</body></html>";
    exit;
}

$empId = $_SESSION['emp_attendance_id'];
$empName = $_SESSION['emp_attendance_name'];
$empDept = $_SESSION['emp_attendance_dept'];
$empDesignation = $_SESSION['emp_attendance_designation'];
$empPhoto = $_SESSION['emp_attendance_photo'];
$empCode = $_SESSION['emp_attendance_emp_id'];

$today = date('Y-m-d');
$currentTime = date('H:i:s');
$message = '';
$messageType = '';

// Get location settings
function getAttendanceSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM attendance_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$locationRequired = getAttendanceSetting($pdo, 'location_required', '0');
$officeLat = (float) getAttendanceSetting($pdo, 'office_latitude', '0');
$officeLng = (float) getAttendanceSetting($pdo, 'office_longitude', '0');
$allowedRadius = (float) getAttendanceSetting($pdo, 'allowed_radius', '100');
$officeName = getAttendanceSetting($pdo, 'office_name', 'Office');

// Get today's attendance
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$stmt->execute([$empId, $today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Check-In/Check-Out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userLat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $userLng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $distance = null;

    // Calculate distance if location is provided
    if ($userLat && $userLng && $officeLat && $officeLng) {
        $distance = calculateDistance($userLat, $userLng, $officeLat, $officeLng);
    }

    // Check location restriction
    $locationOk = true;
    if ($locationRequired == '1') {
        if (!$userLat || !$userLng) {
            $locationOk = false;
            $message = "Location access is required. Please enable GPS and try again.";
            $messageType = 'error';
        } elseif ($distance > $allowedRadius) {
            $locationOk = false;
            $message = "You are " . round($distance) . " meters away from $officeName. Maximum allowed distance is " . round($allowedRadius) . " meters.";
            $messageType = 'error';
        }
    }

    if ($locationOk && $action === 'checkin') {
        if ($todayAttendance && $todayAttendance['check_in']) {
            $message = "You have already checked in today at " . date('h:i A', strtotime($todayAttendance['check_in']));
            $messageType = 'warning';
        } else {
            $checkInTime = date('H:i:s');

            // Determine status based on time (9:00 AM is standard, after 9:30 is late)
            $status = 'Present';
            if (strtotime($checkInTime) > strtotime('09:30:00')) {
                $status = 'Late';
            }

            // Check if location columns exist
            $hasLocationCols = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'check_in_lat'")->fetch();
                $hasLocationCols = (bool)$cols;
            } catch (Exception $e) {}

            if ($todayAttendance) {
                if ($hasLocationCols) {
                    $stmt = $pdo->prepare("UPDATE attendance SET check_in = ?, status = ?, check_in_lat = ?, check_in_lng = ?, check_in_distance = ? WHERE id = ?");
                    $stmt->execute([$checkInTime, $status, $userLat, $userLng, $distance, $todayAttendance['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE attendance SET check_in = ?, status = ? WHERE id = ?");
                    $stmt->execute([$checkInTime, $status, $todayAttendance['id']]);
                }
            } else {
                if ($hasLocationCols) {
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, status, check_in_lat, check_in_lng, check_in_distance) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$empId, $today, $checkInTime, $status, $userLat, $userLng, $distance]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$empId, $today, $checkInTime, $status]);
                }
            }

            $message = "Check-in successful at " . date('h:i A');
            if ($distance !== null) {
                $message .= " (Location: " . round($distance) . "m from office)";
            }
            $messageType = 'success';

            // Refresh attendance data
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$empId, $today]);
            $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($locationOk && $action === 'checkout') {
        if (!$todayAttendance || !$todayAttendance['check_in']) {
            $message = "Please check-in first before checking out";
            $messageType = 'error';
        } elseif ($todayAttendance['check_out']) {
            $message = "You have already checked out today at " . date('h:i A', strtotime($todayAttendance['check_out']));
            $messageType = 'warning';
        } else {
            $checkOutTime = date('H:i:s');

            // Calculate working hours
            $inTime = strtotime($todayAttendance['check_in']);
            $outTime = strtotime($checkOutTime);
            $workingHours = round(($outTime - $inTime) / 3600, 2);

            // Update status if half day (less than 4 hours)
            $status = $todayAttendance['status'];
            if ($workingHours < 4) {
                $status = 'Half Day';
            }

            // Check if location columns exist
            $hasLocationCols = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'check_out_lat'")->fetch();
                $hasLocationCols = (bool)$cols;
            } catch (Exception $e) {}

            if ($hasLocationCols) {
                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, working_hours = ?, status = ?, check_out_lat = ?, check_out_lng = ?, check_out_distance = ? WHERE id = ?");
                $stmt->execute([$checkOutTime, $workingHours, $status, $userLat, $userLng, $distance, $todayAttendance['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ?, working_hours = ?, status = ? WHERE id = ?");
                $stmt->execute([$checkOutTime, $workingHours, $status, $todayAttendance['id']]);
            }

            $message = "Check-out successful at " . date('h:i A') . ". Total hours: " . $workingHours;
            if ($distance !== null) {
                $message .= " (Location: " . round($distance) . "m from office)";
            }
            $messageType = 'success';

            // Refresh attendance data
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$empId, $today]);
            $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Calculate distance between two GPS coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// Get this month's attendance summary
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthStats = $pdo->prepare("
    SELECT
        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
        COUNT(CASE WHEN status = 'Half Day' THEN 1 END) as half_day,
        COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as on_leave,
        SUM(working_hours) as total_hours
    FROM attendance
    WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
");
$monthStats->execute([$empId, $monthStart, $monthEnd]);
$stats = $monthStats->fetch(PDO::FETCH_ASSOC);

// Get recent attendance (last 7 days)
$recentAttendance = $pdo->prepare("
    SELECT * FROM attendance
    WHERE employee_id = ?
    ORDER BY attendance_date DESC
    LIMIT 7
");
$recentAttendance->execute([$empId]);
$recentDays = $recentAttendance->fetchAll(PDO::FETCH_ASSOC);

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: attendance_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Portal - <?= htmlspecialchars($empName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Attendance">
    <link rel="manifest" href="/hr/manifest.json">
    <link rel="apple-touch-icon" href="/hr/icons/icon.php?size=192">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            border: 3px solid rgba(255,255,255,0.3);
            overflow: hidden;
        }
        .user-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-details h2 {
            font-size: 1.3em;
            margin-bottom: 3px;
        }
        .user-details p {
            opacity: 0.9;
            font-size: 0.9em;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95em;
            text-decoration: none;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .message.warning { background: #fff3cd; color: #856404; }

        .location-status {
            background: #e3f2fd;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }
        .location-status.ok { background: #d4edda; color: #155724; }
        .location-status.error { background: #f8d7da; color: #721c24; }
        .location-status.warning { background: #fff3cd; color: #856404; }
        .location-status .icon { font-size: 1.3em; }

        .clock-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .current-time {
            font-size: 4em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .current-date {
            color: #7f8c8d;
            font-size: 1.2em;
            margin-bottom: 25px;
        }

        .attendance-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .att-btn {
            padding: 20px 50px;
            font-size: 1.3em;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            min-width: 200px;
        }
        .att-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .att-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .checkin-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }
        .checkout-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .today-status {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .status-item .label {
            color: #7f8c8d;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        .status-item .value {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .status-Present { background: #d4edda; color: #155724; }
        .status-Late { background: #fff3cd; color: #856404; }
        .status-Absent { background: #f8d7da; color: #721c24; }
        .status-Half { background: #cce5ff; color: #004085; }
        .status-On { background: #d1ecf1; color: #0c5460; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .stat-card.present { border-left: 4px solid #27ae60; }
        .stat-card.late { border-left: 4px solid #f39c12; }
        .stat-card.absent { border-left: 4px solid #e74c3c; }
        .stat-card.hours { border-left: 4px solid #3498db; }

        .recent-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .recent-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-table th, .recent-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .recent-table th {
            background: #f8f9fa;
            color: #7f8c8d;
            font-weight: 600;
        }
        .recent-table tr:last-child td {
            border-bottom: none;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }
        .loading-box {
            background: white;
            padding: 30px 40px;
            border-radius: 15px;
            text-align: center;
        }
        .loading-box .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 600px) {
            .current-time { font-size: 2.5em; }
            .att-btn { padding: 15px 30px; font-size: 1.1em; min-width: 150px; }
            .portal-header { padding: 15px; }
            .user-details h2 { font-size: 1.1em; }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner"></div>
        <p>Getting your location...</p>
    </div>
</div>

<div class="portal-header">
    <div class="user-info">
        <div class="user-photo">
            <?php if ($empPhoto): ?>
                <img src="../<?= htmlspecialchars($empPhoto) ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($empName, 0, 2)) ?>
            <?php endif; ?>
        </div>
        <div class="user-details">
            <h2><?= htmlspecialchars($empName) ?></h2>
            <p><?= htmlspecialchars($empCode) ?> | <?= htmlspecialchars($empDesignation ?: $empDept) ?></p>
        </div>
    </div>
    <a href="?logout=1" class="logout-btn">Logout</a>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($locationRequired == '1'): ?>
    <!-- Location Status -->
    <div class="location-status" id="locationStatus">
        <span class="icon">📍</span>
        <span id="locationText">Checking location...</span>
    </div>
    <?php endif; ?>

    <!-- Clock and Check-in/out Section -->
    <div class="clock-section">
        <div class="current-time" id="currentTime"></div>
        <div class="current-date" id="currentDate"></div>

        <div class="attendance-buttons">
            <form method="post" id="checkinForm" style="display: inline;">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="latitude" id="checkinLat">
                <input type="hidden" name="longitude" id="checkinLng">
                <button type="button" onclick="submitWithLocation('checkin')" class="att-btn checkin-btn"
                    <?= ($todayAttendance && $todayAttendance['check_in']) ? 'disabled' : '' ?>>
                    Check In
                </button>
            </form>

            <form method="post" id="checkoutForm" style="display: inline;">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="latitude" id="checkoutLat">
                <input type="hidden" name="longitude" id="checkoutLng">
                <button type="button" onclick="submitWithLocation('checkout')" class="att-btn checkout-btn"
                    <?= (!$todayAttendance || !$todayAttendance['check_in'] || $todayAttendance['check_out']) ? 'disabled' : '' ?>>
                    Check Out
                </button>
            </form>
        </div>

        <?php if ($todayAttendance): ?>
        <div class="today-status">
            <h4>Today's Attendance</h4>
            <div class="status-grid">
                <div class="status-item">
                    <div class="label">Status</div>
                    <div class="value">
                        <span class="status-badge status-<?= explode(' ', $todayAttendance['status'])[0] ?>">
                            <?= htmlspecialchars($todayAttendance['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Check In</div>
                    <div class="value"><?= $todayAttendance['check_in'] ? date('h:i A', strtotime($todayAttendance['check_in'])) : '--:--' ?></div>
                </div>
                <div class="status-item">
                    <div class="label">Check Out</div>
                    <div class="value"><?= $todayAttendance['check_out'] ? date('h:i A', strtotime($todayAttendance['check_out'])) : '--:--' ?></div>
                </div>
                <div class="status-item">
                    <div class="label">Hours</div>
                    <div class="value"><?= $todayAttendance['working_hours'] ? number_format($todayAttendance['working_hours'], 1) . ' hrs' : '--' ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Monthly Stats -->
    <h3 style="margin-bottom: 15px; color: #2c3e50;"><?= date('F Y') ?> Summary</h3>
    <div class="stats-grid">
        <div class="stat-card present">
            <div class="number"><?= ($stats['present'] ?? 0) + ($stats['late'] ?? 0) ?></div>
            <div class="label">Present Days</div>
        </div>
        <div class="stat-card late">
            <div class="number"><?= $stats['late'] ?? 0 ?></div>
            <div class="label">Late Days</div>
        </div>
        <div class="stat-card absent">
            <div class="number"><?= ($stats['absent'] ?? 0) + ($stats['half_day'] ?? 0) ?></div>
            <div class="label">Absent/Half</div>
        </div>
        <div class="stat-card hours">
            <div class="number"><?= number_format($stats['total_hours'] ?? 0, 1) ?></div>
            <div class="label">Total Hours</div>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="recent-section">
        <h3>Recent Attendance</h3>
        <table class="recent-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentDays)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999;">No attendance records yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentDays as $day): ?>
                    <tr>
                        <td>
                            <?= date('D, d M', strtotime($day['attendance_date'])) ?>
                            <?php if ($day['attendance_date'] === $today): ?>
                                <span style="color: #27ae60; font-size: 0.8em;">(Today)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= explode(' ', $day['status'])[0] ?>">
                                <?= htmlspecialchars($day['status']) ?>
                            </span>
                        </td>
                        <td><?= $day['check_in'] ? date('h:i A', strtotime($day['check_in'])) : '-' ?></td>
                        <td><?= $day['check_out'] ? date('h:i A', strtotime($day['check_out'])) : '-' ?></td>
                        <td><?= $day['working_hours'] ? number_format($day['working_hours'], 1) . ' hrs' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// Location settings from server
const locationRequired = <?= $locationRequired == '1' ? 'true' : 'false' ?>;
const officeLat = <?= $officeLat ?>;
const officeLng = <?= $officeLng ?>;
const allowedRadius = <?= $allowedRadius ?>;
const officeName = "<?= addslashes($officeName) ?>";

let currentLat = null;
let currentLng = null;
let currentDistance = null;

function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    document.getElementById('currentTime').textContent = timeStr;
    document.getElementById('currentDate').textContent = dateStr;
}

updateTime();
setInterval(updateTime, 1000);

// Calculate distance using Haversine formula
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Earth's radius in meters
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Check if page is secure (HTTPS required for geolocation on mobile)
function isSecureContext() {
    return window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
}

// Update location status
function updateLocationStatus() {
    if (!locationRequired) return;

    const statusDiv = document.getElementById('locationStatus');
    const textSpan = document.getElementById('locationText');

    // Check for HTTPS on mobile
    if (!isSecureContext()) {
        statusDiv.className = 'location-status error';
        textSpan.innerHTML = '<strong>HTTPS Required:</strong> Location only works on secure connections. Please access via HTTPS or localhost.';
        return;
    }

    if (!navigator.geolocation) {
        statusDiv.className = 'location-status error';
        textSpan.textContent = 'Geolocation not supported by your browser';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            currentLat = position.coords.latitude;
            currentLng = position.coords.longitude;
            currentDistance = calculateDistance(currentLat, currentLng, officeLat, officeLng);

            if (currentDistance <= allowedRadius) {
                statusDiv.className = 'location-status ok';
                textSpan.textContent = `You are ${Math.round(currentDistance)}m from ${officeName} - Within range`;
            } else {
                statusDiv.className = 'location-status warning';
                textSpan.textContent = `You are ${Math.round(currentDistance)}m from ${officeName} - Too far (max ${allowedRadius}m)`;
            }
        },
        function(error) {
            statusDiv.className = 'location-status error';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    textSpan.innerHTML = '<strong>Permission Denied:</strong> Please allow location access in your browser settings, then refresh.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    textSpan.textContent = 'Location unavailable. Please enable GPS on your device.';
                    break;
                case error.TIMEOUT:
                    textSpan.textContent = 'Location request timed out. Please try again.';
                    break;
                default:
                    textSpan.textContent = 'Unable to get location.';
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// Submit form with location
function submitWithLocation(action) {
    const form = document.getElementById(action + 'Form');
    const latInput = document.getElementById(action + 'Lat');
    const lngInput = document.getElementById(action + 'Lng');

    if (locationRequired) {
        // Check for HTTPS first
        if (!isSecureContext()) {
            alert('Location access requires HTTPS.\n\nPlease access this page via:\n• https:// URL, or\n• localhost\n\nContact your admin to enable HTTPS.');
            return;
        }

        document.getElementById('loadingOverlay').style.display = 'flex';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                latInput.value = position.coords.latitude;
                lngInput.value = position.coords.longitude;
                document.getElementById('loadingOverlay').style.display = 'none';
                form.submit();
            },
            function(error) {
                document.getElementById('loadingOverlay').style.display = 'none';
                let msg = 'Unable to get your location.\n\n';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        msg += 'You denied location access.\n\nTo fix:\n1. Open browser settings\n2. Allow location for this site\n3. Refresh and try again';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        msg += 'GPS is not available.\n\nPlease:\n1. Enable GPS/Location on your phone\n2. Try again';
                        break;
                    case error.TIMEOUT:
                        msg += 'Location request timed out.\n\nPlease:\n1. Go outside or near a window\n2. Try again';
                        break;
                    default:
                        msg += 'Please enable GPS and try again.';
                }
                alert(msg);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    } else {
        // Location not required, just submit
        if (navigator.geolocation && isSecureContext()) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    latInput.value = position.coords.latitude;
                    lngInput.value = position.coords.longitude;
                    form.submit();
                },
                function(error) {
                    // Submit without location
                    form.submit();
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        } else {
            form.submit();
        }
    }
}

// Check location on page load
if (locationRequired) {
    updateLocationStatus();
    setInterval(updateLocationStatus, 30000); // Update every 30 seconds
}

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/hr/sw.js')
            .then(function(registration) {
                console.log('SW registered: ', registration);
            })
            .catch(function(error) {
                console.log('SW registration failed: ', error);
            });
    });
}
</script>

</body>
</html>
