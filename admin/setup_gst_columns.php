<?php
/**
 * Setup script to add IGST columns to quote_items and invoice_items tables
 * for state-based GST calculation (CGST/SGST for same state, IGST for different state)
 */

include "../db.php";

$messages = [];
$errors = [];

try {
    // 1. Add IGST columns to quote_items table
    $checkCol = $pdo->query("SHOW COLUMNS FROM quote_items LIKE 'igst_percent'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE quote_items ADD COLUMN igst_percent DECIMAL(5,2) DEFAULT 0 AFTER sgst_amount");
        $pdo->exec("ALTER TABLE quote_items ADD COLUMN igst_amount DECIMAL(12,2) DEFAULT 0 AFTER igst_percent");
        $messages[] = "Added IGST columns to quote_items table";
    } else {
        $messages[] = "IGST columns already exist in quote_items table";
    }

    // 2. Add is_igst flag to quote_master to track GST type
    $checkCol = $pdo->query("SHOW COLUMNS FROM quote_master LIKE 'is_igst'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE quote_master ADD COLUMN is_igst TINYINT(1) DEFAULT 0");
        $messages[] = "Added is_igst column to quote_master table";
    } else {
        $messages[] = "is_igst column already exists in quote_master table";
    }

    // 3. Check if invoice_items table exists and add IGST columns
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'invoice_items'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM invoice_items LIKE 'igst_percent'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN igst_percent DECIMAL(5,2) DEFAULT 0 AFTER sgst_amount");
            $pdo->exec("ALTER TABLE invoice_items ADD COLUMN igst_amount DECIMAL(12,2) DEFAULT 0 AFTER igst_percent");
            $messages[] = "Added IGST columns to invoice_items table";
        } else {
            $messages[] = "IGST columns already exist in invoice_items table";
        }
    }

    // 4. Add is_igst flag to invoice_master
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'invoice_master'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM invoice_master LIKE 'is_igst'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE invoice_master ADD COLUMN is_igst TINYINT(1) DEFAULT 0");
            $messages[] = "Added is_igst column to invoice_master table";
        } else {
            $messages[] = "is_igst column already exists in invoice_master table";
        }
    }

    // 5. Check if proforma_items table exists and add IGST columns
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'proforma_items'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM proforma_items LIKE 'igst_percent'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE proforma_items ADD COLUMN igst_percent DECIMAL(5,2) DEFAULT 0 AFTER sgst_amount");
            $pdo->exec("ALTER TABLE proforma_items ADD COLUMN igst_amount DECIMAL(12,2) DEFAULT 0 AFTER igst_percent");
            $messages[] = "Added IGST columns to proforma_items table";
        } else {
            $messages[] = "IGST columns already exist in proforma_items table";
        }
    }

    // 6. Add is_igst flag to proforma_master
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'proforma_master'")->fetch();
    if ($tableCheck) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM proforma_master LIKE 'is_igst'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE proforma_master ADD COLUMN is_igst TINYINT(1) DEFAULT 0");
            $messages[] = "Added is_igst column to proforma_master table";
        } else {
            $messages[] = "is_igst column already exists in proforma_master table";
        }
    }

    // 7. Ensure company_settings has state column
    $checkCol = $pdo->query("SHOW COLUMNS FROM company_settings LIKE 'state'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE company_settings ADD COLUMN state VARCHAR(100) DEFAULT 'Maharashtra'");
        $messages[] = "Added state column to company_settings table (defaulted to Maharashtra)";
    } else {
        // Check if state is set, if not set to Maharashtra
        $stateCheck = $pdo->query("SELECT state FROM company_settings WHERE id = 1")->fetchColumn();
        if (empty($stateCheck)) {
            $pdo->exec("UPDATE company_settings SET state = 'Maharashtra' WHERE id = 1");
            $messages[] = "Set default company state to Maharashtra";
        } else {
            $messages[] = "Company state already set to: " . $stateCheck;
        }
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>GST Columns Setup</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <h1>GST Columns Setup</h1>
    <p>This script adds IGST columns to support state-based GST calculation.</p>

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

    <h3>GST Logic:</h3>
    <ul>
        <li><strong>Same State (Maharashtra):</strong> CGST + SGST (GST split 50-50)</li>
        <li><strong>Different State:</strong> IGST (Full GST percentage)</li>
    </ul>

    <p><a href="/admin/settings.php" class="btn btn-primary">Go to Admin Settings</a></p>
</div>

</body>
</html>
