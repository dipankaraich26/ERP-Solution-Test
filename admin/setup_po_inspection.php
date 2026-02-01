<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$messages = [];
$errors = [];

// Create tables
try {
    // 1. PO Inspection Checkpoint Templates
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_inspection_checkpoint_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_no INT NOT NULL,
            checkpoint VARCHAR(255) NOT NULL,
            specification TEXT,
            category VARCHAR(100) DEFAULT 'General',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $messages[] = "Table 'po_inspection_checkpoint_templates' created/verified.";

    // 2. PO Inspection Checklists (header)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_inspection_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_no VARCHAR(50) NOT NULL,
            checklist_no VARCHAR(50) NOT NULL UNIQUE,
            inspector_name VARCHAR(100),
            inspection_date DATE,
            supplier_invoice_no VARCHAR(100),
            status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
            overall_result ENUM('Pass', 'Fail', 'Pending', 'Conditional') DEFAULT 'Pending',
            remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at DATETIME,
            INDEX idx_po_no (po_no),
            INDEX idx_status (status)
        )
    ");
    $messages[] = "Table 'po_inspection_checklists' created/verified.";

    // 3. PO Inspection Checklist Items
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_inspection_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT NOT NULL,
            item_no INT NOT NULL,
            checkpoint VARCHAR(255) NOT NULL,
            specification TEXT,
            result ENUM('Pending', 'OK', 'Not OK', 'NA', 'Conditional') DEFAULT 'Pending',
            actual_value VARCHAR(255),
            remarks TEXT,
            FOREIGN KEY (checklist_id) REFERENCES po_inspection_checklists(id) ON DELETE CASCADE
        )
    ");
    $messages[] = "Table 'po_inspection_checklist_items' created/verified.";

    // 4. PO Inspection Approvers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_inspection_approvers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee (employee_id),
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    ");
    $messages[] = "Table 'po_inspection_approvers' created/verified.";

    // 5. PO Inspection Approvals
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_inspection_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_no VARCHAR(50) NOT NULL,
            checklist_id INT,
            requested_by INT,
            approver_id INT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            remarks TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME,
            INDEX idx_po_no (po_no),
            INDEX idx_status (status),
            FOREIGN KEY (checklist_id) REFERENCES po_inspection_checklists(id) ON DELETE SET NULL,
            FOREIGN KEY (approver_id) REFERENCES employees(id)
        )
    ");
    $messages[] = "Table 'po_inspection_approvals' created/verified.";

    // Insert default checkpoint templates if empty
    $count = $pdo->query("SELECT COUNT(*) FROM po_inspection_checkpoint_templates")->fetchColumn();
    if ($count == 0) {
        $defaultCheckpoints = [
            // Documentation
            [1, 'Purchase Order Match', 'Verify items match PO specifications', 'Documentation'],
            [2, 'Packing List Verification', 'Check packing list against received items', 'Documentation'],
            [3, 'Invoice Verification', 'Supplier invoice matches PO and delivery', 'Documentation'],
            [4, 'Certificate of Conformance', 'COC/Test certificates provided if required', 'Documentation'],

            // Quantity Check
            [5, 'Quantity Verification', 'Received quantity matches delivery note', 'Quantity'],
            [6, 'Part Number Verification', 'Part numbers match PO specifications', 'Quantity'],

            // Packaging & Condition
            [7, 'Packaging Condition', 'Packaging intact and undamaged', 'Packaging'],
            [8, 'Labeling Check', 'Items properly labeled with part no, batch, date', 'Packaging'],
            [9, 'Seal Integrity', 'Seals unbroken (if applicable)', 'Packaging'],

            // Physical Inspection
            [10, 'Visual Inspection', 'No visible damage, rust, or defects', 'Physical'],
            [11, 'Dimensional Check', 'Dimensions within specifications', 'Physical'],
            [12, 'Color/Finish Check', 'Color and finish as per specification', 'Physical'],
            [13, 'Weight Verification', 'Weight within acceptable range', 'Physical'],

            // Quality Check
            [14, 'Material Verification', 'Material grade/type as specified', 'Quality'],
            [15, 'Functionality Test', 'Basic functionality verified (if applicable)', 'Quality'],
            [16, 'Expiry/Shelf Life', 'Within acceptable shelf life period', 'Quality'],

            // Compliance
            [17, 'Safety Standards', 'Meets required safety standards', 'Compliance'],
            [18, 'Regulatory Compliance', 'Complies with applicable regulations', 'Compliance'],
        ];

        $insertStmt = $pdo->prepare("
            INSERT INTO po_inspection_checkpoint_templates (item_no, checkpoint, specification, category)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($defaultCheckpoints as $cp) {
            $insertStmt->execute($cp);
        }
        $messages[] = "Inserted " . count($defaultCheckpoints) . " default inspection checkpoints.";
    } else {
        $messages[] = "Checkpoint templates already exist ($count items).";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Setup PO Incoming Inspection System</h1>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">Errors:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">Setup Complete:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="margin-top: 0; color: #1e40af;">PO Incoming Inspection System</h2>
        <p>This setup creates the following components:</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #1e40af;">Inspection Checklists</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Generate inspection checklists for incoming goods. Each PO can have one inspection checklist that must be completed before stock receipt.
                </p>
            </div>

            <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #166534;">Checkpoint Templates</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    <?php echo $count; ?> predefined checkpoints covering documentation, quantity, packaging, physical inspection, quality, and compliance.
                </p>
            </div>

            <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #854d0e;">Approval Workflow</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Submitted checklists require approval from designated approvers before stock can be received into inventory.
                </p>
            </div>
        </div>
    </div>

    <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0;">Next Steps</h3>
        <ol style="line-height: 2;">
            <li><a href="po_inspection_approvers.php">Configure Inspection Approvers</a> - Designate employees who can approve inspections</li>
            <li>When receiving stock, go to <strong>Stock Entry → Receive</strong> and complete the inspection checklist</li>
            <li>Submit the checklist for approval</li>
            <li>Once approved, stock can be received into inventory</li>
        </ol>

        <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="po_inspection_approvers.php" class="btn btn-primary">Configure Approvers</a>
            <a href="../stock_entry/index.php" class="btn btn-secondary">Go to Stock Entry</a>
            <a href="index.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
