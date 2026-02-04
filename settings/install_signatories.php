<?php
include "../db.php";

$messages = [];
$success = true;

try {
    // Create signatories table
    $sql = "CREATE TABLE IF NOT EXISTS `signatories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `designation` varchar(255) DEFAULT NULL,
      `department` varchar(255) DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `sort_order` int(11) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    $messages[] = "✓ Signatories table created successfully";

    // Check if table is empty and add default signatory
    $count = $pdo->query("SELECT COUNT(*) FROM signatories")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO signatories (name, designation, department, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Authorized Signatory', 'Director', 'Management', 1, 1]);
        $messages[] = "✓ Default signatory added";
    } else {
        $messages[] = "ℹ Table already has data, skipping default signatory";
    }

} catch (PDOException $e) {
    $success = false;
    $messages[] = "✗ Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Install Signatories Module</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .install-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .install-container h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        .message {
            padding: 12px 20px;
            margin: 10px 0;
            border-radius: 6px;
            font-size: 14px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .action-buttons {
            margin-top: 30px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="install-container">
    <h1>Signatories Module Installation</h1>

    <?php foreach ($messages as $msg): ?>
        <?php
        $type = 'info';
        if (strpos($msg, '✓') === 0) $type = 'success';
        elseif (strpos($msg, '✗') === 0) $type = 'error';
        ?>
        <div class="message <?= $type ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="message success" style="margin-top: 30px; font-size: 16px; font-weight: 600;">
            🎉 Installation completed successfully!
        </div>
    <?php endif; ?>

    <div class="action-buttons">
        <a href="signatories.php" class="btn btn-success">Go to Signatories Management</a>
        <a href="../" class="btn btn-secondary">Back to Home</a>
    </div>
</div>

</body>
</html>
