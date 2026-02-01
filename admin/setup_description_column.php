<?php
/**
 * Setup script to add description column to quote_items, invoice_items, and proforma_items tables
 * This allows adding a separate description field alongside the product name
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // 1. Add description column to quote_items table
    $checkCol = $pdo->query("SHOW COLUMNS FROM quote_items LIKE 'description'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE quote_items ADD COLUMN description TEXT NULL AFTER part_name");
        $messages[] = "Added description column to quote_items table";
    } else {
        $messages[] = "description column already exists in quote_items table";
    }

    // 2. Add description column to invoice_items table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'invoice_items'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'description'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN description TEXT NULL AFTER part_name");
            $messages[] = "Added description column to invoice_items table";
        } else {
            $messages[] = "description column already exists in invoice_items table";
        }
    }

    // 3. Add description column to proforma_items table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'proforma_items'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM proforma_items LIKE 'description'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE proforma_items ADD COLUMN description TEXT NULL AFTER part_name");
            $messages[] = "Added description column to proforma_items table";
        } else {
            $messages[] = "description column already exists in proforma_items table";
        }
    }

    // 4. Add description column to crm_lead_requirements table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'crm_lead_requirements'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM crm_lead_requirements LIKE 'description'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE crm_lead_requirements ADD COLUMN description TEXT NULL AFTER product_name");
            $messages[] = "Added description column to crm_lead_requirements table";
        } else {
            $messages[] = "description column already exists in crm_lead_requirements table";
        }
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Description Column Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <h1>Description Column Setup</h1>
    <p>This script adds a description column to quote_items, invoice_items, and proforma_items tables.</p>

    <h3>Setup Results:</h3>

    <?php if (!empty($errors)): ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #721c24;">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #155724;">
            <strong>Success:</strong>
            <ul>
                <?php foreach ($messages as $m): ?>
                    <li><?= htmlspecialchars($m) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h3>Usage:</h3>
    <ul>
        <li><strong>Product Name:</strong> Auto-filled from part master when selecting a part</li>
        <li><strong>Description:</strong> Optional free-text field for additional details about the item</li>
    </ul>

    <p><a href="/admin/settings.php" class="btn btn-primary">Go to Admin Settings</a></p>
</div>

</body>
</html>
