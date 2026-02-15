<?php
include "../db.php";
include "../includes/sidebar.php";
include "../includes/procurement_helper.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid Work Order ID");
}

// Auto-add closing_image column if missing
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM work_orders LIKE 'closing_image'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE work_orders ADD COLUMN closing_image VARCHAR(255) DEFAULT NULL AFTER status");
    }
} catch (Exception $e) { /* ignore */ }

$success = '';
$error = '';

// Handle Edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'edit') {
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $bomId = !empty($_POST['bom_id']) ? (int)$_POST['bom_id'] : null;

        try {
            $updateStmt = $pdo->prepare("UPDATE work_orders SET assigned_to = ?, bom_id = ? WHERE id = ?");
            $updateStmt->execute([$assignedTo, $bomId, $id]);
            $success = "Work Order updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update: " . $e->getMessage();
        }
    }

    if ($action === 'release') {
        try {
            // Release approves the WO for production — no inventory is deducted here.
            // Stock is only checked and deducted at close time.
            $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'released' WHERE id = ?");
            $updateStmt->execute([$id]);
            syncWoStatusToPlan($pdo, (int)$id, 'released');

            // Auto-create task for assigned engineer
            $woData = $pdo->prepare("SELECT wo_no, part_no, qty, assigned_to FROM work_orders WHERE id = ?");
            $woData->execute([$id]);
            $woInfo = $woData->fetch();
            if ($woInfo && !empty($woInfo['assigned_to'])) {
                include_once "../includes/auto_task.php";
                $pn = $pdo->prepare("SELECT part_name FROM part_master WHERE part_no = ?");
                $pn->execute([$woInfo['part_no']]);
                $woPartName = $pn->fetchColumn() ?: $woInfo['part_no'];
                createAutoTask($pdo, [
                    'task_name' => "Work Order {$woInfo['wo_no']} - Production",
                    'task_description' => "Work Order {$woInfo['wo_no']} has been released. Complete production for Part: {$woInfo['part_no']} - $woPartName, Qty: {$woInfo['qty']}",
                    'priority' => 'High',
                    'assigned_to' => $woInfo['assigned_to'],
                    'start_date' => date('Y-m-d'),
                    'related_module' => 'Work Order',
                    'related_id' => $id,
                    'related_reference' => $woInfo['wo_no'],
                    'created_by' => $_SESSION['user_id'] ?? null
                ]);
            }

            // Fire auto-task event for admin-configured rules
            include_once "../includes/auto_task_engine.php";
            fireAutoTaskEvent($pdo, 'work_order', 'released', [
                'reference' => $woInfo['wo_no'] ?? '', 'record_id' => $id,
                'module' => 'Work Order', 'event' => 'released'
            ]);

            $success = "Work Order released successfully!";
        } catch (PDOException $e) {
            $error = "Failed to release: " . $e->getMessage();
        }
    }

    if ($action === 'start') {
        try {
            $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'in_progress' WHERE id = ?");
            $updateStmt->execute([$id]);
            syncWoStatusToPlan($pdo, (int)$id, 'in_progress');
            $success = "Work Order started!";
        } catch (PDOException $e) {
            $error = "Failed to start: " . $e->getMessage();
        }
    }

    if ($action === 'complete') {
        try {
            $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'completed' WHERE id = ?");
            $updateStmt->execute([$id]);
            syncWoStatusToPlan($pdo, (int)$id, 'completed');

            // Fire auto-task event
            $woRef = $pdo->prepare("SELECT wo_no, part_no FROM work_orders WHERE id = ?");
            $woRef->execute([$id]);
            $woData = $woRef->fetch(PDO::FETCH_ASSOC);
            if ($woData) {
                include_once "../includes/auto_task_engine.php";
                fireAutoTaskEvent($pdo, 'work_order', 'completed', [
                    'reference' => $woData['wo_no'], 'record_id' => $id,
                    'module' => 'Work Order', 'event' => 'completed'
                ]);
            }

            $success = "Work Order completed!";
        } catch (PDOException $e) {
            $error = "Failed to complete: " . $e->getMessage();
        }
    }

    if ($action === 'cancel') {
        try {
            $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'cancelled' WHERE id = ?");
            $updateStmt->execute([$id]);
            syncWoStatusToPlan($pdo, (int)$id, 'cancelled');
            $success = "Work Order cancelled!";
        } catch (PDOException $e) {
            $error = "Failed to cancel: " . $e->getMessage();
        }
    }

    if ($action === 'close') {
        // Validate mandatory closing image
        if (empty($_FILES['closing_image']) || $_FILES['closing_image']['error'] !== UPLOAD_ERR_OK) {
            $error = "A picture of the completed task is mandatory to close the Work Order. Please upload an image.";
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['closing_image']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Please upload a JPG, PNG, GIF, or WEBP image.";
            } elseif ($_FILES['closing_image']['size'] > 10 * 1024 * 1024) {
                $error = "Image size must be less than 10MB.";
            }
        }

        // Must be in qc_approval status
        if (empty($error)) {
        $statusCheck = $pdo->prepare("SELECT status FROM work_orders WHERE id = ?");
        $statusCheck->execute([$id]);
        $currentStatus = $statusCheck->fetchColumn();

        if (!in_array($currentStatus, ['completed', 'qc_approval'])) {
            $error = "Work Order must complete Quality Check & Approval before closing.";
        }

        // Check if approval exists and is approved
        if (empty($error)) {
            $approvalCheck = $pdo->prepare("
                SELECT * FROM wo_closing_approvals
                WHERE work_order_id = ? AND status = 'Approved'
                ORDER BY id DESC LIMIT 1
            ");
            $approvalCheck->execute([$id]);
            $approvedRequest = $approvalCheck->fetch();
        }

        if (empty($error) && !$approvedRequest) {
            $error = "Cannot close: Work Order closing has not been approved. Please complete the quality checklist and get approval first.";
        }

        if (empty($error)) {
            $woStmt = $pdo->prepare("SELECT wo_no, part_no, qty, bom_id FROM work_orders WHERE id = ?");
            $woStmt->execute([$id]);
            $woData = $woStmt->fetch();
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                $depleteMessages = [];
                $shortParts = [];

                if ($woData) {
                    $woQty = (float)$woData['qty'];

                    // STEP 1: Deplete child parts (BOM components) with stock check inside transaction
                    if ($woData['bom_id']) {
                        $componentsStmt = $pdo->prepare("
                            SELECT bi.component_part_no, bi.qty as component_qty, pm.part_name
                            FROM bom_items bi
                            LEFT JOIN part_master pm ON bi.component_part_no = pm.part_no
                            WHERE bi.bom_id = ?
                        ");
                        $componentsStmt->execute([$woData['bom_id']]);
                        $components = $componentsStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Stock check inside transaction with row locks
                        foreach ($components as $comp) {
                            $depleteQty = (float)$comp['component_qty'] * $woQty;

                            // Ensure inventory row exists
                            $pdo->prepare("
                                INSERT IGNORE INTO inventory (part_no, qty) VALUES (?, 0)
                            ")->execute([$comp['component_part_no']]);

                            // Lock and check current stock
                            $stockStmt = $pdo->prepare("SELECT qty FROM inventory WHERE part_no = ? FOR UPDATE");
                            $stockStmt->execute([$comp['component_part_no']]);
                            $currentStock = (float)$stockStmt->fetchColumn();

                            if ($currentStock < $depleteQty) {
                                $shortParts[] = $comp['component_part_no'] . ' (' . ($comp['part_name'] ?? 'N/A') . ') - Need: ' . $depleteQty . ', Available: ' . $currentStock . ', Short: ' . round($depleteQty - $currentStock, 4);
                            }
                        }

                        // If any parts are short, rollback and show error
                        if (!empty($shortParts)) {
                            $pdo->rollBack();
                            $error = "Cannot close Work Order. Insufficient stock for the following components:<br><ul style='margin: 10px 0; padding-left: 20px;'>";
                            foreach ($shortParts as $sp) {
                                $error .= "<li>" . htmlspecialchars($sp) . "</li>";
                            }
                            $error .= "</ul>Please ensure adequate stock before closing.";
                        } else {
                            // All stock verified — proceed with depletion
                            foreach ($components as $comp) {
                                $depleteQty = (float)$comp['component_qty'] * $woQty;

                                $pdo->prepare("
                                    UPDATE inventory SET qty = qty - ? WHERE part_no = ?
                                ")->execute([$depleteQty, $comp['component_part_no']]);

                                $pdo->prepare("
                                    INSERT INTO depletion (part_no, qty, reason, issue_no, issue_date)
                                    VALUES (?, ?, ?, ?, CURDATE())
                                ")->execute([
                                    $comp['component_part_no'],
                                    $depleteQty,
                                    'Work Order Close: ' . $woData['wo_no'],
                                    $woData['wo_no']
                                ]);

                                $depleteMessages[] = $comp['component_part_no'] . ' (-' . $depleteQty . ')';
                            }
                        }
                    }
                }

                // Only proceed if no stock errors
                if (empty($error) && $woData) {
                    // STEP 2: Add parent part (finished product)
                    $pdo->prepare("
                        INSERT INTO inventory (part_no, qty)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
                    ")->execute([$woData['part_no'], $woData['qty']]);

                    $pdo->prepare("
                        INSERT INTO stock_entries (part_no, received_qty, invoice_no, status)
                        VALUES (?, ?, ?, 'posted')
                    ")->execute([
                        $woData['part_no'],
                        $woData['qty'],
                        'WO: ' . $woData['wo_no']
                    ]);

                    // Save closing image
                    $ext = pathinfo($_FILES['closing_image']['name'], PATHINFO_EXTENSION);
                    $closingFileName = 'wo_close_' . $id . '_' . time() . '.' . $ext;
                    $uploadDir = __DIR__ . '/../uploads/work_orders/';
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
                    move_uploaded_file($_FILES['closing_image']['tmp_name'], $uploadDir . $closingFileName);

                    $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'closed', closing_image = ? WHERE id = ?");
                    $updateStmt->execute([$closingFileName, $id]);

                    $pdo->commit();

                    syncWoStatusToPlan($pdo, (int)$id, 'closed');

                    $success = "Work Order closed successfully!<br>";
                    $success .= "<strong>Added:</strong> " . $woData['qty'] . " units of " . $woData['part_no'] . "<br>";
                    if (!empty($depleteMessages)) {
                        $success .= "<strong>Depleted:</strong> " . implode(', ', $depleteMessages);
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Friendly message for constraint violation
                if (strpos($e->getMessage(), 'chk_inventory_non_negative') !== false || strpos($e->getMessage(), 'Integrity constraint') !== false) {
                    $error = "Cannot close Work Order: One or more component parts have insufficient stock. Please check inventory levels and try again.";
                } else {
                    $error = "Failed to close: " . $e->getMessage();
                }
            }
        }
        } // end if empty($error) - closing image validated
    }

    if ($action === 'create_task') {
        try {
            include_once "../includes/auto_task.php";
            $woData = $pdo->prepare("SELECT wo_no, part_no, qty, assigned_to FROM work_orders WHERE id = ?");
            $woData->execute([$id]);
            $woInfo = $woData->fetch();
            if ($woInfo && !empty($woInfo['assigned_to'])) {
                $pn = $pdo->prepare("SELECT part_name FROM part_master WHERE part_no = ?");
                $pn->execute([$woInfo['part_no']]);
                $woPartName = $pn->fetchColumn() ?: $woInfo['part_no'];
                $taskId = createAutoTask($pdo, [
                    'task_name' => "Work Order {$woInfo['wo_no']} - Production",
                    'task_description' => "Work Order {$woInfo['wo_no']} has been released. Complete production for Part: {$woInfo['part_no']} - $woPartName, Qty: {$woInfo['qty']}",
                    'priority' => 'High',
                    'assigned_to' => $woInfo['assigned_to'],
                    'start_date' => date('Y-m-d'),
                    'related_module' => 'Work Order',
                    'related_id' => $id,
                    'related_reference' => $woInfo['wo_no'],
                    'created_by' => $_SESSION['user_id'] ?? null
                ]);
                if ($taskId) {
                    $success = "Task created successfully for this Work Order!";
                } else {
                    $error = "Failed to create task. Please try again.";
                }
            } else {
                $error = "Cannot create task: No engineer assigned to this Work Order.";
            }
        } catch (Exception $e) {
            $error = "Failed to create task: " . $e->getMessage();
        }
    }

    if ($action === 'reopen') {
        try {
            $checkStmt = $pdo->prepare("SELECT wo_no, part_no, qty, status, bom_id FROM work_orders WHERE id = ?");
            $checkStmt->execute([$id]);
            $woData = $checkStmt->fetch();

            $pdo->beginTransaction();

            $restoreMessages = [];

            if ($woData && $woData['status'] === 'closed') {
                $woQty = (float)$woData['qty'];

                // STEP 1: Restore child parts (reverse depletion)
                if ($woData['bom_id']) {
                    $componentsStmt = $pdo->prepare("
                        SELECT bi.component_part_no, bi.qty as component_qty, pm.part_name
                        FROM bom_items bi
                        LEFT JOIN part_master pm ON bi.component_part_no = pm.part_no
                        WHERE bi.bom_id = ?
                    ");
                    $componentsStmt->execute([$woData['bom_id']]);
                    $components = $componentsStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($components as $comp) {
                        $restoreQty = (float)$comp['component_qty'] * $woQty;

                        $pdo->prepare("
                            INSERT INTO inventory (part_no, qty)
                            VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE qty = qty + ?
                        ")->execute([$comp['component_part_no'], $restoreQty, $restoreQty]);

                        $pdo->prepare("
                            UPDATE depletion
                            SET reason = CONCAT(reason, ' [REVERSED]')
                            WHERE part_no = ? AND issue_no = ? AND reason NOT LIKE '%REVERSED%'
                            ORDER BY id DESC LIMIT 1
                        ")->execute([$comp['component_part_no'], $woData['wo_no']]);

                        $restoreMessages[] = $comp['component_part_no'] . ' (+' . $restoreQty . ')';
                    }
                }

                // STEP 2: Deduct parent part (finished product)
                $pdo->prepare("
                    UPDATE inventory SET qty = qty - ? WHERE part_no = ?
                ")->execute([$woData['qty'], $woData['part_no']]);

                $pdo->prepare("
                    UPDATE stock_entries
                    SET status = 'reversed'
                    WHERE part_no = ? AND invoice_no = ? AND status = 'posted'
                    ORDER BY id DESC LIMIT 1
                ")->execute([$woData['part_no'], 'WO: ' . $woData['wo_no']]);

                // Reset approval and checklist status
                $pdo->prepare("DELETE FROM wo_closing_approvals WHERE work_order_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE wo_quality_checklists SET status = 'Draft' WHERE work_order_id = ?")->execute([$id]);
            }

            $updateStmt = $pdo->prepare("UPDATE work_orders SET status = 'open' WHERE id = ?");
            $updateStmt->execute([$id]);

            $pdo->commit();

            syncWoStatusToPlan($pdo, (int)$id, 'open');

            if ($woData && $woData['status'] === 'closed') {
                $success = "Work Order reopened!<br>";
                $success .= "<strong>Deducted:</strong> " . $woData['qty'] . " units of " . $woData['part_no'] . "<br>";
                if (!empty($restoreMessages)) {
                    $success .= "<strong>Restored:</strong> " . implode(', ', $restoreMessages);
                }
            } else {
                $success = "Work Order reopened!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to reopen: " . $e->getMessage();
        }
    }
}

/* --- Fetch Work Order Header --- */
$woStmt = $pdo->prepare("
    SELECT w.wo_no, w.part_no, w.qty, w.status, w.closing_image, w.assigned_to, w.bom_id, w.plan_id, w.created_at,
           COALESCE(p.part_name, w.part_no) as part_name,
           b.id AS bom_master_id, b.bom_no, b.description,
           e.emp_id, e.first_name, e.last_name, e.department, e.designation
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    LEFT JOIN bom_master b ON w.bom_id = b.id
    LEFT JOIN employees e ON w.assigned_to = e.id
    WHERE w.id = ?
");
$woStmt->execute([$id]);
$wo = $woStmt->fetch();

if (!$wo) {
    die("Work Order not found");
}

// Check if a task exists for this WO
$woTaskExists = false;
$woTask = null;
try {
    $taskCheck = $pdo->prepare("SELECT id, task_no, status FROM tasks WHERE related_module = 'Work Order' AND related_id = ? LIMIT 1");
    $taskCheck->execute([$id]);
    $woTask = $taskCheck->fetch();
    $woTaskExists = !empty($woTask);
} catch (Exception $e) {
    // tasks table might not exist
}

// Fetch all employees for assignment dropdown
$employees = $pdo->query("SELECT id, emp_id, first_name, last_name, department, designation FROM employees WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch available BOMs for this part
$bomStmt = $pdo->prepare("SELECT id, bom_no, description FROM bom_master WHERE parent_part_no = ? AND status = 'active' ORDER BY bom_no");
$bomStmt->execute([$wo['part_no']]);
$availableBoms = $bomStmt->fetchAll(PDO::FETCH_ASSOC);

/* --- Fetch BOM Items for this WO (if BOM exists) --- */
$bomItems = [];
if ($wo['bom_id']) {
    $itemsStmt = $pdo->prepare("
        SELECT i.qty, p.part_name, p.part_no, COALESCE(inv.qty, 0) AS current_stock
        FROM bom_items i
        JOIN part_master p ON i.component_part_no = p.part_no
        LEFT JOIN inventory inv ON inv.part_no = p.part_no
        WHERE i.bom_id = ?
    ");
    $itemsStmt->execute([$wo['bom_id']]);
    $bomItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* --- Fetch Quality Checklist Status --- */
$checklistStmt = $pdo->prepare("
    SELECT * FROM wo_quality_checklists
    WHERE work_order_id = ?
    ORDER BY id DESC LIMIT 1
");
$checklistStmt->execute([$id]);
$checklist = $checklistStmt->fetch();

/* --- Fetch Approval Status --- */
$approvalStmt = $pdo->prepare("
    SELECT a.*, e.first_name as approver_first, e.last_name as approver_last, e.emp_id as approver_emp_id
    FROM wo_closing_approvals a
    JOIN employees e ON a.approver_id = e.id
    WHERE a.work_order_id = ?
    ORDER BY a.id DESC LIMIT 1
");
$approvalStmt->execute([$id]);
$approval = $approvalStmt->fetch();

// Determine if closing is allowed
$canClose = $approval && $approval['status'] === 'Approved';
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Work Order</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            table {
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }
            table th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            table td {
                border: 1px solid #000 !important;
            }
        }
        .closing-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .closing-card h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .closing-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .closing-step:last-child {
            border-bottom: none;
        }
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
            flex-shrink: 0;
        }
        .step-pending {
            background: #f3f4f6;
            color: #6b7280;
        }
        .step-done {
            background: #d1fae5;
            color: #065f46;
        }
        .step-current {
            background: #dbeafe;
            color: #1e40af;
        }
        .step-content {
            flex: 1;
        }
        .step-content h4 {
            margin: 0 0 3px 0;
            font-size: 0.95em;
        }
        .step-content p {
            margin: 0;
            font-size: 0.85em;
            color: #6b7280;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .pill-draft { background: #fef3c7; color: #92400e; }
        .pill-submitted { background: #dbeafe; color: #1e40af; }
        .pill-approved { background: #d1fae5; color: #065f46; }
        .pill-rejected { background: #fee2e2; color: #991b1b; }
        .pill-pending { background: #f3e8ff; color: #7e22ce; }
        .pill-pass { background: #d1fae5; color: #065f46; }
        .pill-fail { background: #fee2e2; color: #991b1b; }

        /* Ensure page scrolls vertically */
        html, body {
            height: auto !important;
            min-height: 100vh;
            overflow-y: auto !important;
        }
        .app-container {
            overflow: visible !important;
            height: auto !important;
            min-height: 100vh;
        }
        .content {
            overflow-y: auto;
            min-height: 100vh;
            padding-bottom: 60px;
        }
    </style>
</head>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<body>

<div class="content">
    <?php if ($success): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h1 style="margin: 0;">Work Order <?= htmlspecialchars($wo['wo_no']) ?></h1>
        <div class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="window.print()" class="btn btn-primary">Print</button>
            <button onclick="exportToExcel()" class="btn btn-success">Export to Excel</button>
            <button onclick="shareToWhatsApp()" class="btn btn-secondary">Share via WhatsApp</button>
        </div>
    </div>

    <!-- Status Badge -->
    <?php
    $statusColors = [
        'open' => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'color' => '#92400e', 'text' => 'Open'],
        'created' => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'color' => '#1e40af', 'text' => 'Created'],
        'released' => ['bg' => '#e0e7ff', 'border' => '#6366f1', 'color' => '#3730a3', 'text' => 'Released'],
        'in_progress' => ['bg' => '#fae8ff', 'border' => '#d946ef', 'color' => '#86198f', 'text' => 'In Progress'],
        'completed' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'color' => '#166534', 'text' => 'Completed'],
        'qc_approval' => ['bg' => '#cffafe', 'border' => '#0891b2', 'color' => '#155e75', 'text' => 'QC & Approval'],
        'closed' => ['bg' => '#f3f4f6', 'border' => '#6b7280', 'color' => '#374151', 'text' => 'Closed'],
        'cancelled' => ['bg' => '#fee2e2', 'border' => '#ef4444', 'color' => '#991b1b', 'text' => 'Cancelled']
    ];
    $sc = $statusColors[$wo['status']] ?? $statusColors['open'];
    ?>
    <div style="display: inline-block; padding: 8px 20px; background: <?= $sc['bg'] ?>; border: 2px solid <?= $sc['border'] ?>; color: <?= $sc['color'] ?>; border-radius: 25px; font-weight: 600; font-size: 1.1em; margin-bottom: 20px;">
        <?= $sc['text'] ?>
    </div>

    <!-- Action Buttons -->
    <div class="no-print" style="margin-bottom: 25px; padding: 15px; background: #f3f4f6; border-radius: 8px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">

        <!-- Edit Button - Available for all non-terminal statuses -->
        <?php if (!in_array($wo['status'], ['completed', 'qc_approval', 'closed', 'cancelled'])): ?>
            <button onclick="openEditModal()" class="btn" style="background: #6366f1; color: white;">Edit WO</button>
        <?php endif; ?>

        <?php if (in_array($wo['status'], ['open', 'created'])): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="release">
                <button type="submit" class="btn" style="background: #10b981; color: white;"
                        onclick="return confirm('Release this Work Order?');">
                    Release WO
                </button>
            </form>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn" style="background: #ef4444; color: white;"
                        onclick="return confirm('Cancel this Work Order?');">
                    Cancel WO
                </button>
            </form>

        <?php elseif ($wo['status'] === 'released'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="start">
                <button type="submit" class="btn" style="background: #d946ef; color: white;"
                        onclick="return confirm('Start production on this Work Order?');">
                    Start Production
                </button>
            </form>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn" style="background: #ef4444; color: white;"
                        onclick="return confirm('Cancel this Work Order?');">
                    Cancel WO
                </button>
            </form>
            <?php if (!$woTaskExists && !empty($wo['assigned_to'])): ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="create_task">
                    <button type="submit" class="btn" style="background: #3b82f6; color: white;">
                        Create Task
                    </button>
                </form>
            <?php elseif ($woTaskExists): ?>
                <a href="/tasks/index.php?search=<?= urlencode($wo['wo_no']) ?>" class="btn" style="background: #6366f1; color: white;">
                    View Task (<?= htmlspecialchars($woTask['task_no']) ?>)
                </a>
            <?php endif; ?>

        <?php elseif ($wo['status'] === 'in_progress'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="btn" style="background: #16a34a; color: white;"
                        onclick="return confirm('Mark this Work Order as completed?');">
                    Complete WO
                </button>
            </form>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn" style="background: #ef4444; color: white;"
                        onclick="return confirm('Cancel this Work Order?');">
                    Cancel WO
                </button>
            </form>

        <?php elseif (in_array($wo['status'], ['completed', 'qc_approval'])): ?>
            <!-- Completed/QC Approval: Show close button if approved -->
            <?php if ($canClose): ?>
                <button type="button" class="btn" style="background: #6b7280; color: white;" onclick="openCloseModal()">
                    Close WO
                </button>
            <?php else: ?>
                <span style="color: #0891b2; font-weight: 500;">Complete Quality Check & Approval below to proceed</span>
            <?php endif; ?>

        <?php elseif ($wo['status'] === 'closed'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="reopen">
                <button type="submit" class="btn" style="background: #f59e0b; color: white;"
                        onclick="return confirm('Reopen this Work Order? This will reverse inventory changes.');">
                    Reopen WO
                </button>
            </form>
            <span style="color: #6b7280; font-weight: 600; margin-left: 10px;">Work Order Closed</span>

        <?php elseif ($wo['status'] === 'cancelled'): ?>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="reopen">
                <button type="submit" class="btn" style="background: #f59e0b; color: white;"
                        onclick="return confirm('Reopen this Work Order?');">
                    Reopen WO
                </button>
            </form>
            <span style="color: #ef4444; font-weight: 600; margin-left: 10px;">Work Order Cancelled</span>
        <?php endif; ?>
    </div>

    <!-- Status Workflow Guide -->
    <div class="no-print" style="margin-bottom: 20px; padding: 12px 15px; background: #e0e7ff; border-radius: 8px; font-size: 0.85em; color: #4338ca;">
        <strong>Workflow:</strong> Open/Created → Released → In Progress → Completed → <strong>Quality Check + Approval</strong> → Closed
    </div>

    <!-- Work Order Details -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Part No</label>
            <span style="font-weight: 600; font-size: 1.1em;"><?= htmlspecialchars($wo['part_no']) ?></span>
        </div>
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Product Name</label>
            <span style="font-weight: 600; font-size: 1.1em;"><?= htmlspecialchars($wo['part_name']) ?></span>
        </div>
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Quantity</label>
            <span style="font-weight: 600; font-size: 1.1em;"><?= htmlspecialchars($wo['qty']) ?></span>
        </div>
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">BOM</label>
            <span style="font-weight: 600; font-size: 1.1em;">
                <?= $wo['bom_no'] ? htmlspecialchars($wo['bom_no']) : '<span style="color: #999;">Not assigned</span>' ?>
            </span>
        </div>
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Assigned Engineer</label>
            <span style="font-weight: 600; font-size: 1.1em;">
                <?php if ($wo['assigned_to']): ?>
                    <?= htmlspecialchars($wo['first_name'] . ' ' . $wo['last_name']) ?>
                    <?php if ($wo['designation']): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($wo['designation']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #999;">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Created Date</label>
            <span style="font-weight: 600; font-size: 1.1em;"><?= date('Y-m-d H:i', strtotime($wo['created_at'])) ?></span>
        </div>
        <?php if ($wo['plan_id']): ?>
        <div style="padding: 15px; background: #e0e7ff; border: 1px solid #6366f1; border-radius: 8px;">
            <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Source</label>
            <a href="/procurement/view.php?id=<?= $wo['plan_id'] ?>" style="font-weight: 600; font-size: 1.1em; color: #4f46e5;">
                Procurement Plan
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($wo['description'])): ?>
    <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 25px;">
        <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 5px;">Description</label>
        <p style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($wo['description']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($wo['closing_image'])): ?>
    <div style="padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 25px;">
        <label style="color: #6b7280; font-size: 0.85em; display: block; margin-bottom: 8px;">Closing Task Picture</label>
        <a href="../uploads/work_orders/<?= htmlspecialchars($wo['closing_image']) ?>" target="_blank">
            <img src="../uploads/work_orders/<?= htmlspecialchars($wo['closing_image']) ?>" alt="Closing Task Picture"
                 style="max-width: 400px; max-height: 300px; border-radius: 8px; border: 1px solid #e5e7eb; cursor: pointer;">
        </a>
    </div>
    <?php endif; ?>

    <!-- Work Order Closing Workflow (show when status is completed or qc_approval) -->
    <?php if (in_array($wo['status'], ['completed', 'qc_approval'])): ?>
    <div class="closing-card no-print">
        <h3 style="color: #1e40af;">Work Order Closing Workflow</h3>

        <!-- Step 1: Quality Checklist -->
        <div class="closing-step">
            <?php
            $step1Done = $checklist && in_array($checklist['status'], ['Submitted', 'Approved']);
            $step1Class = $step1Done ? 'step-done' : 'step-current';
            ?>
            <div class="step-number <?= $step1Class ?>"><?= $step1Done ? '✓' : '1' ?></div>
            <div class="step-content">
                <h4>Quality Checklist</h4>
                <?php if (!$checklist): ?>
                    <p>Generate and fill the quality inspection checklist</p>
                <?php else: ?>
                    <p>
                        <?= htmlspecialchars($checklist['checklist_no']) ?>
                        <span class="status-pill pill-<?= strtolower($checklist['status']) ?>"><?= $checklist['status'] ?></span>
                        <?php if ($checklist['overall_result'] && $checklist['overall_result'] !== 'Pending'): ?>
                            <span class="status-pill pill-<?= strtolower($checklist['overall_result']) ?>"><?= $checklist['overall_result'] ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <?php if (!$checklist): ?>
                    <a href="quality_checklist.php?id=<?= $id ?>" class="btn btn-primary">Generate Checklist</a>
                <?php elseif ($checklist['status'] === 'Draft'): ?>
                    <a href="quality_checklist.php?id=<?= $id ?>" class="btn btn-primary">Fill Checklist</a>
                <?php else: ?>
                    <a href="quality_checklist.php?id=<?= $id ?>" class="btn btn-secondary">View Checklist</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Request Approval -->
        <div class="closing-step">
            <?php
            $step2Done = $approval && $approval['status'] === 'Approved';
            $step2Pending = $approval && $approval['status'] === 'Pending';
            $step2Class = $step2Done ? 'step-done' : ($step1Done ? 'step-current' : 'step-pending');
            ?>
            <div class="step-number <?= $step2Class ?>"><?= $step2Done ? '✓' : '2' ?></div>
            <div class="step-content">
                <h4>Approval</h4>
                <?php if (!$approval): ?>
                    <p>Request approval from authorized person</p>
                <?php else: ?>
                    <p>
                        Approver: <?= htmlspecialchars($approval['approver_first'] . ' ' . $approval['approver_last']) ?>
                        <span class="status-pill pill-<?= strtolower($approval['status']) ?>"><?= $approval['status'] ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <?php if (!$step1Done): ?>
                    <span style="color: #9ca3af; font-size: 0.85em;">Complete Step 1 first</span>
                <?php elseif (!$approval || $approval['status'] === 'Rejected'): ?>
                    <a href="request_approval.php?id=<?= $id ?>" class="btn btn-primary">Request Approval</a>
                <?php elseif ($approval['status'] === 'Pending'): ?>
                    <a href="request_approval.php?id=<?= $id ?>" class="btn btn-secondary">View Status</a>
                <?php else: ?>
                    <a href="request_approval.php?id=<?= $id ?>" class="btn btn-secondary">View Details</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 3: Close Work Order -->
        <div class="closing-step">
            <?php $step3Class = $step2Done ? 'step-current' : 'step-pending'; ?>
            <div class="step-number <?= $step3Class ?>">3</div>
            <div class="step-content">
                <h4>Close Work Order</h4>
                <p>Finalize and update inventory</p>
            </div>
            <div>
                <?php if ($canClose): ?>
                    <button type="button" class="btn btn-primary" onclick="openCloseModal()">
                        Close WO
                    </button>
                <?php else: ?>
                    <span style="color: #9ca3af; font-size: 0.85em;">Complete Steps 1 & 2 first</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Show closed/qc status info -->
    <?php if (in_array($wo['status'], ['closed', 'qc_approval']) && ($checklist || $approval)): ?>
    <div class="closing-card" style="background: #f0fdf4; border-color: #10b981;">
        <h3 style="color: #065f46;">Closing Details</h3>
        <?php if ($checklist): ?>
        <div style="margin-bottom: 15px;">
            <strong>Quality Checklist:</strong>
            <a href="quality_checklist.php?id=<?= $id ?>"><?= htmlspecialchars($checklist['checklist_no']) ?></a>
            - Result: <span class="status-pill pill-<?= strtolower($checklist['overall_result'] ?? 'pending') ?>"><?= $checklist['overall_result'] ?? 'N/A' ?></span>
            - Inspector: <?= htmlspecialchars($checklist['inspector_name'] ?? 'N/A') ?>
        </div>
        <?php endif; ?>
        <?php if ($approval): ?>
        <div>
            <strong>Approved By:</strong>
            <?= htmlspecialchars($approval['approver_first'] . ' ' . $approval['approver_last']) ?>
            on <?= $approval['approved_at'] ? date('d-M-Y H:i', strtotime($approval['approved_at'])) : 'N/A' ?>
            <?php if ($approval['remarks']): ?>
                <br><strong>Remarks:</strong> <?= htmlspecialchars($approval['remarks']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($bomItems)): ?>
    <?php
    // Check for stock shortages
    $hasShortage = false;
    $shortageCount = 0;
    foreach ($bomItems as $item) {
        $totalRequired = $item['qty'] * $wo['qty'];
        if ($item['current_stock'] < $totalRequired) {
            $hasShortage = true;
            $shortageCount++;
        }
    }
    ?>

    <h2>BOM Components</h2>

    <?php if ($hasShortage && in_array($wo['status'], ['open', 'created'])): ?>
    <div style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
        <strong>&#9888; Stock Warning:</strong> <?= $shortageCount ?> component(s) have insufficient stock. Work Order cannot be released until stock is available.
    </div>
    <?php endif; ?>

    <div style="overflow-x: auto; border-radius: 8px;">
        <table border="1" cellpadding="8" id="woTable" style="min-width: 600px;">
            <tr>
                <th>Part No</th>
                <th>Component</th>
                <th>Qty per Assembly</th>
                <th>Total Required</th>
                <th>Current Stock</th>
                <th>Status</th>
            </tr>

            <?php foreach ($bomItems as $i):
                $totalRequired = $i['qty'] * $wo['qty'];
                $shortage = $totalRequired - $i['current_stock'];
                $hasEnough = $i['current_stock'] >= $totalRequired;
            ?>
            <tr style="<?= !$hasEnough ? 'background: #fee2e2;' : '' ?>">
                <td><?= htmlspecialchars($i['part_no']) ?></td>
                <td><?= htmlspecialchars($i['part_name']) ?></td>
                <td><?= htmlspecialchars($i['qty']) ?></td>
                <td><?= htmlspecialchars($totalRequired) ?></td>
                <td style="<?= !$hasEnough ? 'color: #dc2626; font-weight: bold;' : 'color: #16a34a;' ?>">
                    <?= $i['current_stock'] ?>
                </td>
                <td>
                    <?php if ($hasEnough): ?>
                        <span style="background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 4px; font-size: 0.85em;">&#10004; OK</span>
                    <?php else: ?>
                        <span style="background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 4px; font-size: 0.85em;">&#9888; Short: <?= $shortage ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php else: ?>
    <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">
        <strong>Note:</strong> This work order does not have a BOM assigned. No component breakdown available.
    </div>
    <?php endif; ?>

    <div style="margin-top: 25px;" class="no-print">
        <a href="index.php" class="btn btn-secondary">Back to Work Orders</a>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Edit Work Order</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="edit">

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">Assign Engineer</label>
                <select name="assigned_to" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                    <option value="">-- Select Engineer --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($wo['assigned_to'] == $emp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            <?php if ($emp['emp_id']): ?>(<?= htmlspecialchars($emp['emp_id']) ?>)<?php endif; ?>
                            <?php if ($emp['designation']): ?> - <?= htmlspecialchars($emp['designation']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">BOM Number</label>
                <?php if (!empty($availableBoms)): ?>
                    <select name="bom_id" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="">-- Select BOM --</option>
                        <?php foreach ($availableBoms as $bom): ?>
                            <option value="<?= $bom['id'] ?>" <?= ($wo['bom_id'] == $bom['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bom['bom_no']) ?>
                                <?php if ($bom['description']): ?> - <?= htmlspecialchars($bom['description']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <p style="color: #999; margin: 0;">No BOM available for part <?= htmlspecialchars($wo['part_no']) ?></p>
                    <input type="hidden" name="bom_id" value="<?= $wo['bom_id'] ?>">
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<!-- Close WO Modal (with mandatory image) -->
<div id="closeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Close Work Order</h3>
            <button onclick="closeCloseModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>

        <form method="post" enctype="multipart/form-data" id="closeForm">
            <input type="hidden" name="action" value="close">

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                    Upload Picture of Completed Task <span style="color: #ef4444;">*</span>
                </label>
                <p style="font-size: 0.85em; color: #6b7280; margin: 0 0 10px 0;">
                    A photo of the completed task/finished product is mandatory before closing the Work Order.
                </p>
                <input type="file" name="closing_image" id="closingImageInput" accept="image/*" capture="environment" required
                       style="width: 100%; padding: 10px; border: 2px dashed #d1d5db; border-radius: 6px; font-size: 14px; cursor: pointer;"
                       onchange="previewClosingImage(this)">
            </div>

            <!-- Image Preview -->
            <div id="closingImagePreview" style="display: none; margin-bottom: 20px; text-align: center;">
                <img id="closingPreviewImg" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid #e5e7eb;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" onclick="closeCloseModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn" style="background: #6b7280; color: white;" id="closeSubmitBtn"
                        onclick="return validateCloseForm();">
                    Close WO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCloseModal() {
    document.getElementById('closeModal').style.display = 'flex';
}

function closeCloseModal() {
    document.getElementById('closeModal').style.display = 'none';
    document.getElementById('closingImageInput').value = '';
    document.getElementById('closingImagePreview').style.display = 'none';
}

document.getElementById('closeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCloseModal();
    }
});

function previewClosingImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('closingPreviewImg').src = e.target.result;
            document.getElementById('closingImagePreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        document.getElementById('closingImagePreview').style.display = 'none';
    }
}

function validateCloseForm() {
    var fileInput = document.getElementById('closingImageInput');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please upload a picture of the completed task before closing the Work Order.');
        return false;
    }
    return confirm('Close this Work Order? This will update inventory.');
}
</script>

<script>
const woData = {
    woNo: <?= json_encode($wo['wo_no']) ?>,
    partNo: <?= json_encode($wo['part_no']) ?>,
    product: <?= json_encode($wo['part_name']) ?>,
    bom: <?= json_encode($wo['bom_no'] ?? 'No BOM') ?>,
    qty: <?= json_encode($wo['qty']) ?>,
    status: <?= json_encode($wo['status']) ?>,
    assignedTo: <?= json_encode($wo['assigned_to'] ? $wo['emp_id'] . ' - ' . $wo['first_name'] . ' ' . $wo['last_name'] . ($wo['designation'] ? ' (' . $wo['designation'] . ')' : '') : '') ?>,
    description: <?= json_encode($wo['description'] ?? '') ?>,
    components: <?= json_encode(!empty($bomItems) ? array_map(function($i) use ($wo) {
        $totalReq = $i['qty'] * $wo['qty'];
        $shortage = $totalReq - $i['current_stock'];
        return [
            'part_no' => $i['part_no'],
            'part_name' => $i['part_name'],
            'qty_per_assembly' => $i['qty'],
            'total_required' => $totalReq,
            'current_stock' => $i['current_stock'],
            'status' => $i['current_stock'] >= $totalReq ? 'OK' : 'Short: ' . $shortage
        ];
    }, $bomItems) : []) ?>
};

function exportToExcel() {
    const wb = XLSX.utils.book_new();
    const headerData = [
        ['Work Order', woData.woNo],
        ['Part No', woData.partNo],
        ['Product', woData.product],
        ['BOM', woData.bom],
        ['Quantity', woData.qty],
        ['Status', woData.status],
        ['Assigned To', woData.assignedTo || 'Not Assigned'],
        ['Description', woData.description || ''],
        []
    ];

    let wsData = [...headerData];
    if (woData.components && woData.components.length > 0) {
        wsData.push(['Part No', 'Component', 'Qty per Assembly', 'Total Required', 'Current Stock', 'Status']);
        const tableData = woData.components.map(comp => [
            comp.part_no, comp.part_name, comp.qty_per_assembly, comp.total_required, comp.current_stock, comp.status
        ]);
        wsData = [...wsData, ...tableData];
    } else {
        wsData.push(['No BOM components assigned']);
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [{ wch: 15 }, { wch: 40 }, { wch: 18 }, { wch: 15 }, { wch: 15 }, { wch: 15 }];
    XLSX.utils.book_append_sheet(wb, ws, 'Work Order');
    XLSX.writeFile(wb, 'WO_' + woData.woNo + '.xlsx');
}

function shareToWhatsApp() {
    let message = `*Work Order: ${woData.woNo}*\n\n`;
    message += `*Part No:* ${woData.partNo}\n`;
    message += `*Product:* ${woData.product}\n`;
    message += `*BOM:* ${woData.bom}\n`;
    message += `*Quantity:* ${woData.qty}\n`;
    message += `*Status:* ${woData.status}\n`;
    message += `*Assigned To:* ${woData.assignedTo || 'Not Assigned'}\n`;

    if (woData.description) {
        message += `*Description:* ${woData.description}\n`;
    }

    if (woData.components && woData.components.length > 0) {
        message += `\n*BOM Components:*\n`;
        message += `------------------------\n`;
        woData.components.forEach((comp, index) => {
            message += `${index + 1}. ${comp.part_no} - ${comp.part_name}\n`;
            message += `   Qty/Assembly: ${comp.qty_per_assembly}\n`;
            message += `   Total Required: ${comp.total_required}\n`;
            message += `   Current Stock: ${comp.current_stock}\n`;
            message += `   Status: ${comp.status}\n\n`;
        });
    } else {
        message += `\n_No BOM components assigned_\n`;
    }

    message += `\n_Generated from ERP System_`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
}
</script>

</body>
</html>
