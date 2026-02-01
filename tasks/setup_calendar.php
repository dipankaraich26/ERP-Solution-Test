<?php
/**
 * Task Calendar Setup
 * Adds time-related columns to tasks table for calendar scheduling
 */

include "../db.php";
include "../includes/sidebar.php";

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    try {
        // Add time columns to tasks table
        $columns = [
            "ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL AFTER start_date",
            "ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL AFTER start_time",
            "ADD COLUMN IF NOT EXISTS all_day TINYINT(1) DEFAULT 1 AFTER end_time",
            "ADD COLUMN IF NOT EXISTS recurrence_type ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none' AFTER all_day",
            "ADD COLUMN IF NOT EXISTS recurrence_end_date DATE DEFAULT NULL AFTER recurrence_type",
            "ADD COLUMN IF NOT EXISTS color_code VARCHAR(7) DEFAULT NULL AFTER recurrence_end_date"
        ];

        foreach ($columns as $col) {
            try {
                $pdo->exec("ALTER TABLE tasks $col");
                $messages[] = "Added column: " . explode(' ', $col)[2];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') === false) {
                    $errors[] = "Error adding column: " . $e->getMessage();
                }
            }
        }

        // Create index for calendar queries
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_task_calendar ON tasks (start_date, start_time, assigned_to)");
            $messages[] = "Created calendar index";
        } catch (PDOException $e) {
            // Index might already exist
        }

        if (empty($errors)) {
            $messages[] = "Calendar setup completed successfully!";
        }

    } catch (Exception $e) {
        $errors[] = "Setup error: " . $e->getMessage();
    }
}

// Check current columns
$columnsExist = [];
try {
    $result = $pdo->query("SHOW COLUMNS FROM tasks");
    $existingColumns = $result->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['start_time', 'end_time', 'all_day', 'recurrence_type', 'recurrence_end_date', 'color_code'];
    foreach ($requiredColumns as $col) {
        $columnsExist[$col] = in_array($col, $existingColumns);
    }
} catch (Exception $e) {
    $errors[] = "Could not check columns: " . $e->getMessage();
}

$allColumnsExist = !in_array(false, $columnsExist);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Calendar Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .setup-container { max-width: 700px; margin: 0 auto; }
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-card h3 { margin-top: 0; }
        .column-status {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        .column-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .column-item.exists { background: #d4edda; }
        .column-item.missing { background: #fff3cd; }
        .status-icon { margin-right: 10px; font-weight: bold; }
        .message-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .message-success { background: #d4edda; color: #155724; }
        .message-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="content">
    <div class="setup-container">
        <h1>Task Calendar Setup</h1>

        <?php if (!empty($messages)): ?>
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
            <div class="message-item message-success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="messages">
            <?php foreach ($errors as $err): ?>
            <div class="message-item message-error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="status-card">
            <h3>Calendar Columns Status</h3>
            <div class="column-status">
                <?php foreach ($columnsExist as $col => $exists): ?>
                <div class="column-item <?= $exists ? 'exists' : 'missing' ?>">
                    <span class="status-icon"><?= $exists ? '✓' : '✗' ?></span>
                    <span><?= $col ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$allColumnsExist): ?>
        <div class="status-card">
            <h3>Run Setup</h3>
            <p>Click the button below to add calendar columns to the tasks table.</p>
            <form method="post">
                <button type="submit" name="run_setup" class="btn btn-primary" style="padding: 12px 30px;">
                    Run Setup
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="status-card" style="background: #d4edda;">
            <h3 style="color: #155724;">Setup Complete</h3>
            <p style="color: #155724;">All calendar columns are ready.</p>
            <a href="calendar.php" class="btn btn-primary">Go to Task Calendar</a>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
