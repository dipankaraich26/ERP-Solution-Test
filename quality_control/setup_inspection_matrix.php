<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$messages = [];
$errors = [];

try {
    // 1. Inspection Checkpoints Master
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_inspection_checkpoints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checkpoint_name VARCHAR(255) NOT NULL,
            specification TEXT,
            category VARCHAR(100) DEFAULT 'General',
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'qc_inspection_checkpoints' created/verified.";

    // 2. Part ID Inspection Matrix (maps Part ID categories to checkpoints)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_part_inspection_matrix (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_id VARCHAR(50) NOT NULL COMMENT 'Part ID category (RAW, FG, WIP, etc.)',
            checkpoint_id INT NOT NULL,
            stage ENUM('incoming','work_order','so_release','final_inspection') NOT NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_mapping (part_id, checkpoint_id, stage),
            FOREIGN KEY (checkpoint_id) REFERENCES qc_inspection_checkpoints(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "Table 'qc_part_inspection_matrix' created/verified.";

    // Insert default checkpoints if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM qc_inspection_checkpoints")->fetchColumn();
    if ($count == 0) {
        $defaults = [
            // Documentation
            ['Purchase Order Match', 'Verify items match PO specifications', 'Documentation', 1],
            ['Packing List Verification', 'Check packing list against received items', 'Documentation', 2],
            ['Invoice Verification', 'Supplier invoice matches PO and delivery', 'Documentation', 3],
            ['Certificate of Conformance', 'COC/Test certificates provided if required', 'Documentation', 4],
            ['Traceability Records', 'Serial/batch numbers recorded', 'Documentation', 5],

            // Quantity Check
            ['Quantity Verification', 'Received quantity matches delivery note', 'Quantity', 6],
            ['Part Number Verification', 'Part numbers match PO specifications', 'Quantity', 7],

            // Packaging & Condition
            ['Packaging Condition', 'Packaging intact and undamaged', 'Packaging', 8],
            ['Labeling Check', 'Items properly labeled with part no, batch, date', 'Packaging', 9],
            ['Seal Integrity', 'Seals unbroken (if applicable)', 'Packaging', 10],

            // Physical Inspection
            ['Visual Inspection - Surface', 'No visible damage, scratches, rust, or defects', 'Physical', 11],
            ['Dimensional Check', 'Dimensions within specifications / drawing', 'Physical', 12],
            ['Color/Finish Check', 'Color and finish as per specification', 'Physical', 13],
            ['Weight Verification', 'Weight within acceptable range', 'Physical', 14],

            // Functional
            ['Functionality Test', 'Basic functionality verified (if applicable)', 'Functional', 15],
            ['Performance Test', 'Meets performance criteria', 'Functional', 16],
            ['Fit/Assembly Check', 'Components fit correctly in assembly', 'Functional', 17],

            // Quality
            ['Material Verification', 'Material grade/type as specified', 'Quality', 18],
            ['Expiry/Shelf Life', 'Within acceptable shelf life period', 'Quality', 19],
            ['Paint/Coating Check', 'Uniform coverage, no peeling', 'Quality', 20],

            // Safety & Compliance
            ['Safety Standards', 'Meets required safety standards', 'Compliance', 21],
            ['Regulatory Compliance', 'Complies with applicable regulations', 'Compliance', 22],
            ['Sharp Edges Check', 'No dangerous sharp edges', 'Compliance', 23],
            ['Electrical Safety', 'Proper insulation/grounding (if applicable)', 'Compliance', 24],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO qc_inspection_checkpoints (checkpoint_name, specification, category, sort_order)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($defaults as $cp) {
            $stmt->execute($cp);
        }
        $messages[] = "Inserted " . count($defaults) . " default inspection checkpoints.";
    } else {
        $messages[] = "Checkpoints already exist ($count items).";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <h1>Setup Part Inspection Matrix</h1>

    <p style="margin-bottom: 20px;">
        <a href="dashboard.php" class="btn btn-secondary">Back to QC Dashboard</a>
        <a href="inspection_matrix.php" class="btn btn-primary" style="margin-left: 10px;">Go to Inspection Matrix</a>
    </p>

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
        <h2 style="margin-top: 0; color: #1e40af;">Part Inspection Matrix System</h2>
        <p>This module allows you to configure which inspection checkpoints apply to each Part ID category (RAW, FG, WIP, SUB, etc.), organized by inspection stage. All parts under a Part ID will share the same inspection checklist.</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #1e40af;">Incoming Inspection</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Checkpoints for incoming goods/raw materials from suppliers (PO receipt).
                </p>
            </div>
            <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #166534;">Work Order Inspection</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Checkpoints for in-process quality during manufacturing/work orders.
                </p>
            </div>
            <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #854d0e;">SO Release Inspection</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Checkpoints before releasing goods against a Sales Order.
                </p>
            </div>
            <div style="background: #fdf2f8; border-left: 4px solid #ec4899; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #9d174d;">Final Inspection</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9em;">
                    Final quality checkpoints before dispatch/delivery.
                </p>
            </div>
        </div>
    </div>

    <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-top: 0;">Next Steps</h3>
        <ol style="line-height: 2;">
            <li><a href="inspection_checkpoints.php">Manage Inspection Checkpoints</a> - Add, edit, or remove checkpoints</li>
            <li><a href="inspection_matrix.php">Configure Part Inspection Matrix</a> - Assign checkpoints to parts by stage</li>
        </ol>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
