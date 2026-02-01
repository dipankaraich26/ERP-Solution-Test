<?php
/**
 * Setup script to add market_classification column to crm_leads table
 */
include "../db.php";

echo "<h2>Adding Market Classification Column</h2>";

try {
    // Check if column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM crm_leads LIKE 'market_classification'");

    if ($checkCol->rowCount() == 0) {
        // Add the column
        $pdo->exec("ALTER TABLE crm_leads ADD COLUMN market_classification VARCHAR(50) DEFAULT NULL AFTER lead_source");
        echo "<p style='color: green;'>✓ Column 'market_classification' added successfully to crm_leads table.</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Column 'market_classification' already exists.</p>";
    }

    echo "<p><a href='/crm/index.php'>Go to CRM</a> | <a href='/admin/settings.php'>Go to Settings</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
