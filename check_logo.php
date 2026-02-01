<?php
include "db.php";
include "includes/auth.php";
requireLogin();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logo Diagnostic Tool</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .diagnostic-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        .diagnostic-section {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .diagnostic-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
        }
        .check-item .status {
            font-weight: bold;
            margin-right: 15px;
            min-width: 60px;
        }
        .status.success {
            color: #27ae60;
        }
        .status.warning {
            color: #f39c12;
        }
        .status.error {
            color: #e74c3c;
        }
        .check-details {
            flex: 1;
            font-family: monospace;
            font-size: 0.9em;
            color: #555;
        }
        .logo-preview {
            max-width: 300px;
            padding: 15px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .logo-preview img {
            max-height: 150px;
            max-width: 100%;
            object-fit: contain;
        }
        .logo-preview .no-logo {
            color: #95a5a6;
            padding: 40px 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<div class="diagnostic-container">
    <h1>🔍 Logo Diagnostic Tool</h1>
    <p>This tool helps diagnose logo display issues on the ERP dashboard.</p>

    <?php
    $logo_path = '';
    $company_name = '';
    $issues = [];
    $checks = [];

    // 1. Check Database Connection
    try {
        $result = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch();
        if ($result) {
            $logo_path = $result['logo_path'];
            $company_name = $result['company_name'];
            $checks['database'] = ['status' => 'success', 'message' => 'Database connection OK'];
        } else {
            $checks['database'] = ['status' => 'error', 'message' => 'No company settings found'];
            $issues[] = 'No company settings in database. Please configure in Admin Settings.';
        }
    } catch (Exception $e) {
        $checks['database'] = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        $issues[] = 'Database connection error.';
    }

    // 2. Check Logo Path in Database
    if (!empty($logo_path)) {
        $checks['logo_stored'] = ['status' => 'success', 'message' => 'Logo path in database: ' . htmlspecialchars($logo_path)];
    } else {
        $checks['logo_stored'] = ['status' => 'warning', 'message' => 'No logo path stored in database'];
        $issues[] = 'No logo has been uploaded yet. Please upload one in Admin Settings.';
    }

    // 3. Check if File Exists
    if (!empty($logo_path)) {
        // Try multiple possible paths
        $possible_paths = [
            $_SERVER['DOCUMENT_ROOT'] . '/' . $logo_path,
            dirname(__FILE__) . '/' . $logo_path,
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/company/' . basename($logo_path),
        ];

        $file_exists = false;
        $actual_path = '';
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $file_exists = true;
                $actual_path = $path;
                break;
            }
        }

        if ($file_exists) {
            $file_size = filesize($actual_path);
            $checks['file_exists'] = ['status' => 'success', 'message' => 'Logo file found (' . formatBytes($file_size) . ')'];
        } else {
            $checks['file_exists'] = ['status' => 'error', 'message' => 'Logo file not found in expected locations'];
            $issues[] = 'Logo file missing. Re-upload logo in Admin Settings.';
        }
    }

    // 4. Check File Permissions
    if (!empty($logo_path) && $file_exists) {
        if (is_readable($actual_path)) {
            $checks['permissions'] = ['status' => 'success', 'message' => 'File is readable'];
        } else {
            $checks['permissions'] = ['status' => 'error', 'message' => 'File is not readable'];
            $issues[] = 'Logo file permissions issue. Check server file permissions.';
        }
    }

    // 5. Check Upload Directory
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/company/';
    if (is_dir($upload_dir)) {
        if (is_writable($upload_dir)) {
            $checks['upload_dir'] = ['status' => 'success', 'message' => 'Upload directory exists and is writable'];
        } else {
            $checks['upload_dir'] = ['status' => 'warning', 'message' => 'Upload directory exists but not writable'];
        }
    } else {
        $checks['upload_dir'] = ['status' => 'error', 'message' => 'Upload directory does not exist'];
        $issues[] = 'Upload directory missing. Create /uploads/company/ directory.';
    }

    // 6. Check URL Format
    if (!empty($logo_path)) {
        $has_protocol = preg_match('~^(https?:|/)~', $logo_path);
        if ($has_protocol || strpos($logo_path, 'uploads/') === 0) {
            $checks['url_format'] = ['status' => 'success', 'message' => 'URL format looks correct'];
        } else {
            $checks['url_format'] = ['status' => 'warning', 'message' => 'URL format might need adjustment'];
        }
    }

    // Helper function
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    ?>

    <!-- Diagnostics -->
    <div class="diagnostic-section">
        <h3>📋 Diagnostic Checks</h3>
        <?php foreach ($checks as $key => $check): ?>
            <div class="check-item">
                <div class="status <?= $check['status'] ?>">
                    <?php
                    if ($check['status'] === 'success') echo '✅';
                    elseif ($check['status'] === 'warning') echo '⚠️';
                    else echo '❌';
                    ?>
                </div>
                <div class="check-details">
                    <?= $check['message'] ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Issues -->
    <?php if (!empty($issues)): ?>
    <div class="diagnostic-section" style="border-left-color: #e74c3c;">
        <h3>⚠️ Issues Found</h3>
        <ul>
            <?php foreach ($issues as $issue): ?>
                <li><?= htmlspecialchars($issue) ?></li>
            <?php endforeach; ?>
        </ul>
        <p><strong>Recommended Actions:</strong></p>
        <ol>
            <li>Go to <a href="/admin/settings.php">Admin Settings</a></li>
            <li>Upload your company logo (PNG/JPG/GIF)</li>
            <li>Click Save</li>
            <li>Return to <a href="/index.php">Dashboard</a> and refresh (Ctrl+F5)</li>
        </ol>
    </div>
    <?php else: ?>
    <div class="diagnostic-section" style="border-left-color: #27ae60;">
        <h3>✅ All Checks Passed!</h3>
        <p>Your logo setup looks good. It should display on the dashboard.</p>
        <p>If still not showing:</p>
        <ul>
            <li>Hard refresh browser: <strong>Ctrl + F5</strong> (or Cmd + Shift + R on Mac)</li>
            <li>Clear browser cache</li>
            <li>Try different browser</li>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Current Settings -->
    <div class="diagnostic-section">
        <h3>📊 Current Settings</h3>
        <div class="check-item">
            <div class="check-details">
                <strong>Company Name:</strong> <?= !empty($company_name) ? htmlspecialchars($company_name) : '<span style="color: #95a5a6;">Not set</span>' ?>
            </div>
        </div>
        <div class="check-item">
            <div class="check-details">
                <strong>Logo Path (DB):</strong> <?= !empty($logo_path) ? htmlspecialchars($logo_path) : '<span style="color: #95a5a6;">Not set</span>' ?>
            </div>
        </div>
        <div class="check-item">
            <div class="check-details">
                <strong>Server Root:</strong> <?= htmlspecialchars($_SERVER['DOCUMENT_ROOT']) ?>
            </div>
        </div>
    </div>

    <!-- Logo Preview -->
    <div class="diagnostic-section">
        <h3>👁️ Logo Preview</h3>
        <div class="logo-preview">
            <?php if (!empty($logo_path) && $file_exists): ?>
                <img src="/<?= htmlspecialchars($logo_path) ?>" alt="Company Logo">
                <p style="margin-top: 10px; color: #27ae60;">Logo displays correctly ✓</p>
            <?php else: ?>
                <div class="no-logo">
                    No logo to preview. Upload one in Admin Settings.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="diagnostic-section">
        <h3>🔧 Quick Actions</h3>
        <p>
            <a href="/admin/settings.php" class="btn btn-primary">Upload/Change Logo</a>
            <a href="/index.php" class="btn btn-secondary">Go to Dashboard</a>
            <a href="javascript:location.reload(true)" class="btn btn-secondary">Hard Refresh</a>
        </p>
    </div>

</div>

</body>
</html>
