<?php
require '../db.php';
header('Content-Type: text/html');

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "<h2>Fixing Work Order Status Column</h2>\n\n";

try {
    // Step 1: Alter the status column to include all required values
    echo "Step 1: Updating status ENUM values...\n";
    $pdo->exec("
        ALTER TABLE work_orders
        MODIFY COLUMN status ENUM('open','created','released','in_progress','completed','qc_approval','closed','cancelled')
        DEFAULT 'open'
    ");
    echo "  SUCCESS: Status column updated with all values.\n\n";

    // Step 2: Update existing work orders with empty/null status to 'created'
    echo "Step 2: Fixing existing work orders with empty status...\n";
    $result = $pdo->exec("UPDATE work_orders SET status = 'created' WHERE status IS NULL OR status = ''");
    echo "  SUCCESS: Updated $result work orders to 'created' status.\n\n";

    // Step 3: Verify the fix
    echo "Step 3: Verifying...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM work_orders WHERE Field = 'status'")->fetch(PDO::FETCH_ASSOC);
    echo "  Status column type: " . $cols['Type'] . "\n";
    echo "  Default value: " . ($cols['Default'] ?? 'NULL') . "\n\n";

    // Show current status distribution
    echo "Step 4: Current status distribution:\n";
    $stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM work_orders GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $s) {
        echo "  - " . ($s['status'] ?: '(empty)') . ": " . $s['cnt'] . " work orders\n";
    }

    echo "\n<span style='color: green; font-weight: bold;'>All fixes applied successfully!</span>\n";
    echo "\n<a href='/work_orders/index.php'>Go to Work Orders</a>";

} catch (PDOException $e) {
    echo "<span style='color: red;'>ERROR: " . $e->getMessage() . "</span>\n";
}

echo "</pre>";
