<?php
require '../db.php';
require '../includes/header.php';
require '../includes/sidebar.php';

$messages = [];
$errors = [];

// Create wo_quality_checklists table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_quality_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            work_order_id INT NOT NULL,
            checklist_no VARCHAR(30) NOT NULL UNIQUE,
            inspector_name VARCHAR(100),
            inspection_date DATE,
            overall_result ENUM('Pass', 'Fail', 'Pending') DEFAULT 'Pending',
            remarks TEXT,
            status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at DATETIME,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    $messages[] = "Table 'wo_quality_checklists' created/verified successfully.";
} catch (PDOException $e) {
    $errors[] = "Error creating wo_quality_checklists: " . $e->getMessage();
}

// Create wo_quality_checklist_items table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_quality_checklist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT NOT NULL,
            item_no INT NOT NULL,
            checkpoint VARCHAR(255) NOT NULL,
            specification VARCHAR(255),
            result ENUM('OK', 'Not OK', 'NA', 'Pending') DEFAULT 'Pending',
            actual_value VARCHAR(100),
            remarks TEXT,
            FOREIGN KEY (checklist_id) REFERENCES wo_quality_checklists(id) ON DELETE CASCADE
        )
    ");
    $messages[] = "Table 'wo_quality_checklist_items' created/verified successfully.";
} catch (PDOException $e) {
    $errors[] = "Error creating wo_quality_checklist_items: " . $e->getMessage();
}

// Create wo_closing_approvals table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_closing_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            work_order_id INT NOT NULL,
            checklist_id INT,
            requested_by INT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approver_id INT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            approved_at DATETIME,
            remarks TEXT,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (checklist_id) REFERENCES wo_quality_checklists(id) ON DELETE SET NULL,
            FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    ");
    $messages[] = "Table 'wo_closing_approvals' created/verified successfully.";
} catch (PDOException $e) {
    $errors[] = "Error creating wo_closing_approvals: " . $e->getMessage();
}

// Create default quality checkpoints template
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_quality_checkpoint_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_no INT NOT NULL,
            checkpoint VARCHAR(255) NOT NULL,
            specification VARCHAR(255),
            category VARCHAR(100),
            is_mandatory TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $messages[] = "Table 'wo_quality_checkpoint_templates' created/verified successfully.";

    // Check if templates exist
    $count = $pdo->query("SELECT COUNT(*) FROM wo_quality_checkpoint_templates")->fetchColumn();

    if ($count == 0) {
        // Insert default quality checkpoints
        $defaultCheckpoints = [
            // Visual Inspection
            [1, 'Visual Inspection - Surface Finish', 'No scratches, dents, or damage', 'Visual Inspection', 1],
            [2, 'Visual Inspection - Paint/Coating', 'Uniform coverage, no peeling', 'Visual Inspection', 1],
            [3, 'Visual Inspection - Labeling', 'All labels present and legible', 'Visual Inspection', 1],

            // Dimensional Check
            [4, 'Dimensional Check - Overall Size', 'As per drawing specifications', 'Dimensional', 1],
            [5, 'Dimensional Check - Critical Dimensions', 'Within tolerance limits', 'Dimensional', 1],
            [6, 'Dimensional Check - Hole Positions', 'As per drawing', 'Dimensional', 0],

            // Functional Test
            [7, 'Functional Test - Operation Check', 'Operates as designed', 'Functional', 1],
            [8, 'Functional Test - Performance', 'Meets performance criteria', 'Functional', 1],
            [9, 'Functional Test - Noise Level', 'Within acceptable limits', 'Functional', 0],

            // Assembly Check
            [10, 'Assembly Check - All Parts Present', 'Complete assembly', 'Assembly', 1],
            [11, 'Assembly Check - Fasteners Tightened', 'All bolts/screws secured', 'Assembly', 1],
            [12, 'Assembly Check - Alignment', 'Components properly aligned', 'Assembly', 1],

            // Safety Check
            [13, 'Safety Check - Sharp Edges', 'No dangerous sharp edges', 'Safety', 1],
            [14, 'Safety Check - Electrical Safety', 'Proper insulation/grounding', 'Safety', 1],
            [15, 'Safety Check - Warning Labels', 'Safety labels in place', 'Safety', 0],

            // Documentation
            [16, 'Documentation - Test Records', 'All test records complete', 'Documentation', 1],
            [17, 'Documentation - Traceability', 'Serial/batch numbers recorded', 'Documentation', 0],
            [18, 'Documentation - Certificates', 'Required certificates available', 'Documentation', 0]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO wo_quality_checkpoint_templates (item_no, checkpoint, specification, category, is_mandatory)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($defaultCheckpoints as $cp) {
            $stmt->execute($cp);
        }
        $messages[] = "Default quality checkpoints inserted (18 items).";
    } else {
        $messages[] = "Quality checkpoint templates already exist ($count items).";
    }

} catch (PDOException $e) {
    $errors[] = "Error creating wo_quality_checkpoint_templates: " . $e->getMessage();
}

// Add approver designation/role table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_approvers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            can_approve_wo_closing TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_employee (employee_id)
        )
    ");
    $messages[] = "Table 'wo_approvers' created/verified successfully.";
} catch (PDOException $e) {
    $errors[] = "Error creating wo_approvers: " . $e->getMessage();
}
?>

<div class="content">
    <h1>Setup Work Order Quality Checklist & Approval System</h1>

    <p style="margin-bottom: 20px;">
        <a href="../work_orders/index.php" class="btn btn-secondary">Back to Work Orders</a>
    </p>

    <?php if (!empty($messages)): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">Setup Completed</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">Errors</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #0369a1;">Tables Created</h3>
        <table class="table" style="margin-bottom: 0;">
            <tr>
                <th>Table Name</th>
                <th>Purpose</th>
            </tr>
            <tr>
                <td><code>wo_quality_checklists</code></td>
                <td>Stores quality checklist headers for each work order</td>
            </tr>
            <tr>
                <td><code>wo_quality_checklist_items</code></td>
                <td>Individual quality checkpoint items with results</td>
            </tr>
            <tr>
                <td><code>wo_quality_checkpoint_templates</code></td>
                <td>Master template of quality checkpoints</td>
            </tr>
            <tr>
                <td><code>wo_closing_approvals</code></td>
                <td>Approval workflow for work order closing</td>
            </tr>
            <tr>
                <td><code>wo_approvers</code></td>
                <td>Employees authorized to approve WO closings</td>
            </tr>
        </table>
    </div>

    <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #92400e;">Work Order Closing Workflow</h3>
        <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
            <li><strong>Complete Work Order</strong> - Mark work order as "Completed"</li>
            <li><strong>Generate Quality Checklist</strong> - Create checklist form from template</li>
            <li><strong>Fill Quality Checklist</strong> - Inspector fills all checkpoints (OK/Not OK)</li>
            <li><strong>Submit Checklist</strong> - Submit completed checklist for approval</li>
            <li><strong>Request Approval</strong> - Select approver and request closing approval</li>
            <li><strong>Approver Reviews</strong> - Approver can Approve or Reject with remarks</li>
            <li><strong>Close Work Order</strong> - Once approved, work order can be closed</li>
        </ol>
    </div>

    <div style="background: #f3e8ff; border: 1px solid #a855f7; border-radius: 8px; padding: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #7e22ce;">Next Steps</h3>
        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
            <li><a href="wo_approvers.php">Manage WO Approvers</a> - Add employees who can approve closings</li>
            <li><a href="wo_checkpoint_templates.php">Manage Checkpoint Templates</a> - Customize quality checkpoints</li>
            <li>Go to any completed work order to generate and fill the quality checklist</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
