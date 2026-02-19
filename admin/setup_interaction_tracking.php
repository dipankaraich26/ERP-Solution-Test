<?php
/**
 * Migration: Add created_by_user_id to crm_lead_interactions
 * This tracks which user created each interaction, enabling the date lock feature.
 * Run this once: /admin/setup_interaction_tracking.php
 */
include "../db.php";

echo "<h2>Setup Interaction Tracking</h2>";

try {
    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM crm_lead_interactions LIKE 'created_by_user_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE crm_lead_interactions ADD COLUMN created_by_user_id INT NULL AFTER handled_by");
        echo "<p style='color:green;'>Added 'created_by_user_id' column to crm_lead_interactions.</p>";
    } else {
        echo "<p style='color:blue;'>Column 'created_by_user_id' already exists.</p>";
    }

    echo "<p style='color:green;'><strong>Migration complete!</strong></p>";
    echo "<p><a href='/crm/index.php'>Go to CRM</a></p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
