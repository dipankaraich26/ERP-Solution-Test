<?php
/**
 * Setup script for user permissions table
 * Run once to create the required database structure
 */
include "../db.php";

$messages = [];

// Create user_permissions table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            module VARCHAR(50) NOT NULL,
            can_view TINYINT(1) DEFAULT 0,
            can_create TINYINT(1) DEFAULT 0,
            can_edit TINYINT(1) DEFAULT 0,
            can_delete TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_module (user_id, module),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = ['success', 'user_permissions table created/verified'];
} catch (Exception $e) {
    $messages[] = ['error', 'Error creating user_permissions table: ' . $e->getMessage()];
}

// Create modules table for reference
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(50) NOT NULL UNIQUE,
            module_name VARCHAR(100) NOT NULL,
            module_group VARCHAR(50) NOT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = ['success', 'modules table created/verified'];

    // Insert default modules if not exist
    $modules = [
        // Sales & CRM
        ['crm', 'CRM / Leads', 'Sales & CRM', 1],
        ['customers', 'Customers', 'Sales & CRM', 2],
        ['quotes', 'Quotations', 'Sales & CRM', 3],
        ['proforma', 'Proforma Invoice', 'Sales & CRM', 4],
        ['customer_po', 'Customer PO', 'Sales & CRM', 5],
        ['sales_orders', 'Sales Orders', 'Sales & CRM', 6],
        ['invoices', 'Invoices', 'Sales & CRM', 7],
        ['installations', 'Installations', 'Sales & CRM', 8],

        // Purchase & SCM
        ['suppliers', 'Suppliers', 'Purchase & SCM', 10],
        ['purchase', 'Purchase Orders', 'Purchase & SCM', 11],
        ['procurement', 'Procurement Planning', 'Purchase & SCM', 12],

        // Inventory
        ['part_master', 'Part Master', 'Inventory', 20],
        ['stock_entry', 'Stock Entries', 'Inventory', 21],
        ['depletion', 'Stock Adjustment', 'Inventory', 22],
        ['inventory', 'Current Stock', 'Inventory', 23],
        ['reports', 'Reports', 'Inventory', 24],

        // Operations
        ['bom', 'Bill of Materials', 'Operations', 30],
        ['work_orders', 'Work Orders', 'Operations', 31],

        // HR
        ['hr_employees', 'Employees', 'HR', 40],
        ['hr_attendance', 'Attendance', 'HR', 41],
        ['hr_payroll', 'Payroll', 'HR', 42],

        // Marketing
        ['marketing_catalogs', 'Catalogs', 'Marketing', 50],
        ['marketing_campaigns', 'Campaigns', 'Marketing', 51],
        ['marketing_whatsapp', 'WhatsApp', 'Marketing', 52],
        ['marketing_analytics', 'Marketing Analytics', 'Marketing', 53],

        // Service
        ['service_complaints', 'Complaints', 'Service', 60],
        ['service_technicians', 'Technicians', 'Service', 61],
        ['service_analytics', 'Service Analytics', 'Service', 62],

        // Quality Control
        ['quality_control', 'Quality Control', 'Quality Control', 55],

        // QMS
        ['qms', 'QMS (Quality Management System)', 'QMS', 57],

        // Accounts & Finance
        ['accounts', 'Accounts & Finance', 'Accounts & Finance', 58],

        // Tasks & Projects
        ['tasks', 'Tasks', 'Tasks & Projects', 70],
        ['project_management', 'Product Engineering', 'Tasks & Projects', 71],

        // Admin
        ['admin_settings', 'Company Settings', 'Admin', 80],
        ['admin_users', 'User Management', 'Admin', 81],
        ['admin_locations', 'Location Management', 'Admin', 82],

        // Other
        ['approvals', 'Approvals', 'Admin', 83],
        ['customer_portal', 'Customer Portal', 'Sales & CRM', 9],
    ];

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO modules (module_key, module_name, module_group, display_order)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($modules as $m) {
        $insertStmt->execute($m);
    }
    $messages[] = ['success', 'Default modules inserted'];

} catch (Exception $e) {
    $messages[] = ['error', 'Error with modules table: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup User Permissions</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: auto; }
        .success { color: #27ae60; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #c0392b; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>User Permissions Setup</h1>

    <?php foreach ($messages as $msg): ?>
        <div class="<?= $msg[0] ?>"><?= htmlspecialchars($msg[1]) ?></div>
    <?php endforeach; ?>

    <a href="user_permissions.php" class="btn">Go to User Permissions</a>
    <a href="users.php" class="btn">Go to User Management</a>
</body>
</html>
