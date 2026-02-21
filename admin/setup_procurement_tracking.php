<?php
/**
 * Setup script for Procurement Plan Tracking
 * Adds tables and columns to track Work Orders and Purchase Orders created from procurement plans
 *
 * Run this script once to set up the database structure
 */

require '../db.php';

$messages = [];
$errors = [];

try {
    // 1. Add so_list column to procurement_plans table (stores which SOs the plan is for)
    try {
        $pdo->exec("ALTER TABLE procurement_plans ADD COLUMN so_list TEXT NULL AFTER plan_no");
        $messages[] = "Added so_list column to procurement_plans table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $messages[] = "Column so_list already exists in procurement_plans";
        } else {
            $errors[] = "Error adding so_list column: " . $e->getMessage();
        }
    }

    // Add plan_type column to procurement_plans table
    try {
        $pdo->exec("ALTER TABLE procurement_plans ADD COLUMN plan_type ENUM('procurement', 'wo_planning') DEFAULT 'procurement' AFTER status");
        $messages[] = "Added plan_type column to procurement_plans table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $messages[] = "Column plan_type already exists in procurement_plans";
        } else {
            $errors[] = "Error adding plan_type column: " . $e->getMessage();
        }
    }

    // 2. Create procurement_plan_wo_items table to track work order items
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS procurement_plan_wo_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            part_no VARCHAR(100) NOT NULL,
            part_name VARCHAR(255),
            part_id VARCHAR(50),
            so_list VARCHAR(500),
            required_qty DECIMAL(15,3) DEFAULT 0,
            current_stock DECIMAL(15,3) DEFAULT 0,
            shortage DECIMAL(15,3) DEFAULT 0,
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            created_wo_id INT NULL,
            created_wo_no VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_plan_id (plan_id),
            INDEX idx_part_no (part_no),
            INDEX idx_status (status),
            INDEX idx_created_wo_id (created_wo_id),
            UNIQUE KEY unique_plan_part (plan_id, part_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created procurement_plan_wo_items table";

    // 3. Create procurement_plan_po_items table to track purchase order items (sublet)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS procurement_plan_po_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            part_no VARCHAR(100) NOT NULL,
            part_name VARCHAR(255),
            part_id VARCHAR(50),
            so_list VARCHAR(500),
            required_qty DECIMAL(15,3) DEFAULT 0,
            current_stock DECIMAL(15,3) DEFAULT 0,
            shortage DECIMAL(15,3) DEFAULT 0,
            ordered_qty DECIMAL(15,3) DEFAULT 0,
            supplier_id INT NULL,
            supplier_name VARCHAR(255),
            status ENUM('pending', 'ordered', 'received', 'cancelled') DEFAULT 'pending',
            created_po_id INT NULL,
            created_po_no VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_plan_id (plan_id),
            INDEX idx_part_no (part_no),
            INDEX idx_status (status),
            INDEX idx_created_po_id (created_po_id),
            INDEX idx_supplier_id (supplier_id),
            UNIQUE KEY unique_plan_part (plan_id, part_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Created procurement_plan_po_items table";

    // 4. Add created_wo_id column to work_orders table to track plan linkage (if not exists)
    try {
        $pdo->exec("ALTER TABLE work_orders ADD COLUMN plan_id INT NULL AFTER assigned_to");
        $messages[] = "Added plan_id column to work_orders table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $messages[] = "Column plan_id already exists in work_orders";
        } else {
            $errors[] = "Error adding plan_id to work_orders: " . $e->getMessage();
        }
    }

    // 5. Add plan_id column to purchase_orders table (if not exists)
    try {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN plan_id INT NULL AFTER supplier_id");
        $messages[] = "Added plan_id column to purchase_orders table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $messages[] = "Column plan_id already exists in purchase_orders";
        } else {
            $errors[] = "Error adding plan_id to purchase_orders: " . $e->getMessage();
        }
    }

    // 6. Add item_type column to procurement_plan_items (to differentiate main/sublet/wo items)
    try {
        $pdo->exec("ALTER TABLE procurement_plan_items ADD COLUMN item_type ENUM('main', 'wo', 'po') DEFAULT 'main' AFTER plan_id");
        $messages[] = "Added item_type column to procurement_plan_items table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $messages[] = "Column item_type already exists in procurement_plan_items";
        } else {
            $errors[] = "Error adding item_type column: " . $e->getMessage();
        }
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Procurement Plan Tracking</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <h2>Setup Procurement Plan Tracking</h2>

    <div class="form-section">
        <h3>Setup Results</h3>

        <?php if (!empty($messages)): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong style="color: #059669;">Success Messages:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li style="color: #047857;"><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong style="color: #dc2626;">Errors:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li style="color: #b91c1c;"><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div style="background: #dbeafe; border: 1px solid #3b82f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong style="color: #1d4ed8;">Setup Complete!</strong>
            <p style="margin: 10px 0 0 0; color: #1e40af;">
                The procurement plan tracking system has been set up. You can now:
            </p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #1e40af;">
                <li>Track which Sales Orders each procurement plan is for</li>
                <li>Track Work Order creation status for each plan item</li>
                <li>Track Purchase Order creation status for each sublet item</li>
                <li>Prevent duplicate Work Order/Purchase Order creation</li>
            </ul>
        </div>
        <?php endif; ?>

        <h4>Database Tables Created/Modified:</h4>
        <table style="width: 100%; margin-top: 15px;">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>procurement_plans</code></td>
                    <td>Added <code>so_list</code> column to store linked Sales Orders</td>
                </tr>
                <tr>
                    <td><code>procurement_plan_wo_items</code></td>
                    <td>New table to track Work Order items for each plan</td>
                </tr>
                <tr>
                    <td><code>procurement_plan_po_items</code></td>
                    <td>New table to track Purchase Order items (sublet) for each plan</td>
                </tr>
                <tr>
                    <td><code>work_orders</code></td>
                    <td>Added <code>plan_id</code> column to link to procurement plan</td>
                </tr>
                <tr>
                    <td><code>purchase_orders</code></td>
                    <td>Added <code>plan_id</code> column to link to procurement plan</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px;">
            <a href="/procurement/create.php" class="btn btn-primary">Go to Procurement Planning</a>
            <a href="/admin/settings.php" class="btn btn-secondary">Back to Settings</a>
        </div>
    </div>
</div>

</body>
</html>
