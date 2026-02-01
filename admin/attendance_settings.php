<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$message = '';
$messageType = '';

if (isset($_GET['setup'])) {
    $message = "Location tracking setup completed successfully!";
    $messageType = 'success';
}

// Get current settings
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM attendance_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'office_name' => $_POST['office_name'] ?? 'Main Office',
            'office_latitude' => $_POST['office_latitude'] ?? '0',
            'office_longitude' => $_POST['office_longitude'] ?? '0',
            'allowed_radius' => $_POST['allowed_radius'] ?? '100',
            'location_required' => isset($_POST['location_required']) ? '1' : '0'
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE attendance_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }

        $message = "Settings saved successfully!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error saving settings: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Load current settings
$officeName = getSetting($pdo, 'office_name', 'Main Office');
$officeLat = getSetting($pdo, 'office_latitude', '0');
$officeLng = getSetting($pdo, 'office_longitude', '0');
$allowedRadius = getSetting($pdo, 'allowed_radius', '100');
$locationRequired = getSetting($pdo, 'location_required', '0');

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Location Settings</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 100%; margin: 0; }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .header-bar h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.4em;
        }
        .header-bar p {
            color: #666;
            margin: 3px 0 0;
            font-size: 0.9em;
        }

        .landscape-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 15px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #495057;
            font-size: 0.9em;
        }
        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .toggle-switch input[type="checkbox"] {
            width: 50px;
            height: 26px;
            appearance: none;
            background: #ddd;
            border-radius: 13px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
            flex-shrink: 0;
        }
        .toggle-switch input[type="checkbox"]:checked {
            background: #27ae60;
        }
        .toggle-switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: left 0.3s;
        }
        .toggle-switch input[type="checkbox"]:checked::before {
            left: 26px;
        }
        .toggle-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .toggle-desc {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 3px;
        }

        .help-text {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 4px;
        }

        .btn-location {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95em;
            margin-bottom: 15px;
        }
        .btn-location:hover {
            opacity: 0.9;
        }

        .map-container {
            height: 180px;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ddd;
        }
        .map-container.active {
            border: 2px solid #27ae60;
            background: #f0fff4;
        }

        .location-info {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .location-info p {
            margin: 4px 0;
            color: #2e7d32;
            font-size: 0.9em;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .status-indicator.enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-indicator.disabled {
            background: #f8f9fa;
            color: #6c757d;
        }
        .status-indicator .icon {
            font-size: 1.2em;
        }

        .quick-setup {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .quick-setup p {
            margin: 0 0 10px 0;
            color: #856404;
            font-size: 0.9em;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        body.dark .form-card { background: #2c3e50; }
        body.dark .form-card h3 { color: #ecf0f1; }
        body.dark .header-bar { background: #2c3e50; }
        body.dark .header-bar h1 { color: #ecf0f1; }
        body.dark .form-actions { background: #2c3e50; }
        body.dark .toggle-switch { background: #34495e; }
        body.dark .toggle-label { color: #ecf0f1; }

        @media (max-width: 1200px) {
            .landscape-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .landscape-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div class="header-bar">
            <div>
                <h1>Attendance Location Settings</h1>
                <p>Configure GPS-based attendance verification for employees</p>
            </div>
            <a href="../hr/employees.php" class="btn btn-secondary">Back to Employees</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="landscape-grid">
                <!-- Left Column: Enable/Disable & Status -->
                <div class="form-card">
                    <h3>Location Verification</h3>

                    <div class="toggle-switch">
                        <input type="checkbox" name="location_required" id="location_required"
                               <?= $locationRequired == '1' ? 'checked' : '' ?>>
                        <div>
                            <div class="toggle-label">Enable GPS Verification</div>
                            <div class="toggle-desc">Require location check for attendance</div>
                        </div>
                    </div>

                    <div class="status-indicator <?= $locationRequired == '1' ? 'enabled' : 'disabled' ?>" id="statusIndicator">
                        <span class="icon"><?= $locationRequired == '1' ? '✅' : '⚪' ?></span>
                        <span><?= $locationRequired == '1' ? 'Location verification is ENABLED' : 'Location verification is DISABLED' ?></span>
                    </div>

                    <div class="form-group">
                        <label>Allowed Radius (meters)</label>
                        <input type="number" name="allowed_radius" value="<?= htmlspecialchars($allowedRadius) ?>"
                               min="10" max="5000" placeholder="100">
                        <p class="help-text">Distance from office within which attendance is allowed. Recommended: 50-200m</p>
                    </div>

                    <div class="quick-setup">
                        <p><strong>First time setup?</strong> Run database setup to create required tables.</p>
                        <a href="setup_attendance_location.php" class="btn btn-secondary" style="width: 100%;">Run Database Setup</a>
                    </div>
                </div>

                <!-- Middle Column: Office Location -->
                <div class="form-card">
                    <h3>Office Location</h3>

                    <div class="form-group">
                        <label>Office Name</label>
                        <input type="text" name="office_name" value="<?= htmlspecialchars($officeName) ?>"
                               placeholder="e.g., Main Office, Head Quarters">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Latitude</label>
                            <input type="text" name="office_latitude" id="office_latitude"
                                   value="<?= htmlspecialchars($officeLat) ?>" placeholder="e.g., 28.6139">
                        </div>
                        <div class="form-group">
                            <label>Longitude</label>
                            <input type="text" name="office_longitude" id="office_longitude"
                                   value="<?= htmlspecialchars($officeLng) ?>" placeholder="e.g., 77.2090">
                        </div>
                    </div>

                    <button type="button" class="btn-location" onclick="getCurrentLocation()">
                        📍 Use My Current Location
                    </button>

                    <div class="location-info" id="locationInfo" style="display: none;">
                        <p id="currentCoords">Detecting...</p>
                    </div>

                    <p class="help-text" style="margin-top: 10px;">
                        <strong>Tip:</strong> Open this page on your computer at the office location and click "Use My Current Location" to automatically set the coordinates.
                    </p>
                </div>

                <!-- Right Column: Map Preview -->
                <div class="form-card">
                    <h3>Location Preview</h3>

                    <div class="map-container <?= ($officeLat != '0' && $officeLng != '0') ? 'active' : '' ?>" id="mapContainer">
                        <?php if ($officeLat != '0' && $officeLng != '0'): ?>
                        <div style="text-align: center; color: #27ae60;">
                            <p style="font-size: 2.5em; margin: 0;">📍</p>
                            <p style="font-weight: 600; margin: 5px 0;">Office Location Set</p>
                            <p style="font-size: 0.85em; color: #666;">
                                <?= number_format((float)$officeLat, 6) ?>, <?= number_format((float)$officeLng, 6) ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; color: #999;">
                            <p style="font-size: 2.5em; margin: 0;">📍</p>
                            <p style="margin: 5px 0;">No location set</p>
                            <p style="font-size: 0.85em;">Click "Use My Current Location"</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($officeLat != '0' && $officeLng != '0'): ?>
                    <a href="https://www.google.com/maps?q=<?= $officeLat ?>,<?= $officeLng ?>"
                       target="_blank" class="btn btn-secondary" style="width: 100%; margin-top: 15px; text-align: center;">
                        View on Google Maps
                    </a>
                    <?php endif; ?>

                    <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #2c3e50; font-size: 0.9em;">Current Settings:</p>
                        <p style="margin: 3px 0; font-size: 0.85em; color: #666;">
                            <strong>Office:</strong> <?= htmlspecialchars($officeName) ?>
                        </p>
                        <p style="margin: 3px 0; font-size: 0.85em; color: #666;">
                            <strong>Radius:</strong> <?= htmlspecialchars($allowedRadius) ?> meters
                        </p>
                        <p style="margin: 3px 0; font-size: 0.85em; color: #666;">
                            <strong>Status:</strong> <?= $locationRequired == '1' ? 'Enabled' : 'Disabled' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="../hr/employees.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle status indicator
document.getElementById('location_required').addEventListener('change', function() {
    const indicator = document.getElementById('statusIndicator');
    if (this.checked) {
        indicator.className = 'status-indicator enabled';
        indicator.innerHTML = '<span class="icon">✅</span><span>Location verification is ENABLED</span>';
    } else {
        indicator.className = 'status-indicator disabled';
        indicator.innerHTML = '<span class="icon">⚪</span><span>Location verification is DISABLED</span>';
    }
});

function getCurrentLocation() {
    const infoDiv = document.getElementById('locationInfo');
    const coordsP = document.getElementById('currentCoords');

    infoDiv.style.display = 'block';
    coordsP.textContent = 'Detecting your location...';

    if (!navigator.geolocation) {
        coordsP.textContent = 'Geolocation is not supported by your browser';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            document.getElementById('office_latitude').value = lat.toFixed(8);
            document.getElementById('office_longitude').value = lng.toFixed(8);

            coordsP.innerHTML = `
                <strong>✅ Location captured!</strong><br>
                Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}<br>
                Accuracy: ±${accuracy.toFixed(0)}m
            `;

            // Update map container
            const mapContainer = document.getElementById('mapContainer');
            mapContainer.className = 'map-container active';
            mapContainer.innerHTML = `
                <div style="text-align: center; color: #27ae60;">
                    <p style="font-size: 2.5em; margin: 0;">✅</p>
                    <p style="font-weight: 600; margin: 5px 0;">Location Captured!</p>
                    <p style="font-size: 0.85em; color: #666;">
                        ${lat.toFixed(6)}, ${lng.toFixed(6)}
                    </p>
                </div>
            `;
        },
        function(error) {
            let errorMsg = 'Unknown error';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = 'Location permission denied. Please allow location access.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMsg = 'Location request timed out.';
                    break;
            }
            coordsP.innerHTML = '<span style="color: #dc3545;">❌ ' + errorMsg + '</span>';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
</script>

</body>
</html>
