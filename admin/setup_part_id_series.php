<?php
/**
 * Part ID Series Generator - Database Setup
 * Creates table for managing part ID series in Product Engineering module
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // Part ID Series Master Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS part_id_series (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_id VARCHAR(50) NOT NULL UNIQUE,
            series_prefix VARCHAR(50) NOT NULL,
            current_number INT DEFAULT 0,
            number_padding INT DEFAULT 4,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_part_id (part_id),
            INDEX idx_series_prefix (series_prefix),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created table: part_id_series";

    // Insert some default series examples
    $pdo->exec("
        INSERT IGNORE INTO part_id_series (part_id, series_prefix, current_number, number_padding, description) VALUES
        ('RAW', 'RAW-', 0, 4, 'Raw Materials - Components and materials purchased for manufacturing'),
        ('FG', 'FG-', 0, 4, 'Finished Goods - Final assembled products'),
        ('WIP', 'WIP-', 0, 4, 'Work in Progress - Semi-finished items'),
        ('SUB', 'SUB-', 0, 4, 'Sub-assemblies - Intermediate assembled components'),
        ('PKG', 'PKG-', 0, 4, 'Packaging Materials - Boxes, labels, and packaging items'),
        ('SPA', 'SPA-', 0, 4, 'Spare Parts - Service and replacement parts'),
        ('CON', 'CON-', 0, 4, 'Consumables - Items consumed during manufacturing'),
        ('TOL', 'TOL-', 0, 4, 'Tools - Manufacturing tools and fixtures')
    ");
    $messages[] = "Inserted default part ID series";

} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Part ID Series</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .setup-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 900px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .message-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 5px 0;
        }
        .message-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 5px 0;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .feature-list h4 { margin-top: 0; color: #667eea; }
        .feature-list ul { margin: 0; padding-left: 20px; }
        .feature-list li { margin: 8px 0; }
        .series-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .series-table th, .series-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .series-table th {
            background: #667eea;
            color: white;
        }
        .series-table tr:hover {
            background: #f8f9fa;
        }
        body.dark .setup-container { background: #2c3e50; color: #ecf0f1; }
        body.dark .feature-list { background: #34495e; }
        body.dark .series-table th { background: #34495e; }
        body.dark .series-table td { border-bottom-color: #34495e; }
        body.dark .series-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Part ID Series Generator Setup</h1>
    <a href="../project_management/part_id_series.php" class="btn btn-secondary" style="margin-bottom: 20px;">Go to Part ID Series</a>

    <div class="setup-container">
        <h3>Setup Results</h3>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="message-error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="feature-list">
            <h4>Part ID Series Features</h4>
            <ul>
                <li><strong>Part ID:</strong> Unique identifier for each part category (e.g., RAW, FG, WIP)</li>
                <li><strong>Series Prefix:</strong> Prefix used when generating new part numbers (e.g., RAW-, FG-)</li>
                <li><strong>Auto-increment:</strong> Automatically tracks and increments the next number in series</li>
                <li><strong>Number Padding:</strong> Configurable zero-padding for consistent part number format</li>
                <li><strong>Description:</strong> Detailed description of what the series is used for</li>
            </ul>
        </div>

        <h4 style="margin-top: 25px;">Default Series Created</h4>
        <table class="series-table">
            <thead>
                <tr>
                    <th>Part ID</th>
                    <th>Series Prefix</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>RAW</td><td>RAW-</td><td>Raw Materials</td></tr>
                <tr><td>FG</td><td>FG-</td><td>Finished Goods</td></tr>
                <tr><td>WIP</td><td>WIP-</td><td>Work in Progress</td></tr>
                <tr><td>SUB</td><td>SUB-</td><td>Sub-assemblies</td></tr>
                <tr><td>PKG</td><td>PKG-</td><td>Packaging Materials</td></tr>
                <tr><td>SPA</td><td>SPA-</td><td>Spare Parts</td></tr>
                <tr><td>CON</td><td>CON-</td><td>Consumables</td></tr>
                <tr><td>TOL</td><td>TOL-</td><td>Tools</td></tr>
            </tbody>
        </table>

        <p style="margin-top: 20px;">
            <a href="../project_management/part_id_series.php" class="btn btn-primary">Go to Part ID Series</a>
            <a href="../project_management/dashboard.php" class="btn btn-secondary">Product Engineering Dashboard</a>
        </p>
    </div>
</div>

</body>
</html>
