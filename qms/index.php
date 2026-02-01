<?php
// QMS Module - Redirect to dashboard or installation
include "../db.php";

// Check if tables exist
try {
    $result = $pdo->query("SHOW TABLES LIKE 'qms_cdsco_products'");
    if ($result->rowCount() > 0) {
        // Tables exist, redirect to dashboard
        header("Location: dashboard.php");
        exit;
    }
} catch (Exception $e) {
    // Database error, continue to show installation option
}

// Tables don't exist, show installation option
?>
<!DOCTYPE html>
<html>
<head>
    <title>QMS Module Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .setup-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .setup-container h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .setup-container p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .setup-btn {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .setup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="background: #f5f6fa;">
    <div class="setup-container">
        <div class="icon">🔧</div>
        <h1>QMS Module Setup Required</h1>
        <p>
            The Quality Management System (QMS) module needs to be installed.<br>
            This will create the necessary database tables for CDSCO, ISO, and ICMED compliance tracking.
        </p>
        <a href="install.php" class="setup-btn">Run Installation</a>
    </div>
</body>
</html>
