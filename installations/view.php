<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch installation details
$stmt = $pdo->prepare("
    SELECT
        i.*,
        c.customer_id as cust_code,
        c.company_name,
        c.customer_name,
        c.contact as customer_phone,
        c.email as customer_email,
        c.address1, c.address2, c.city, c.state, c.pincode,
        CASE
            WHEN i.engineer_type = 'internal' THEN CONCAT(e.first_name, ' ', e.last_name)
            ELSE i.external_engineer_name
        END as engineer_name,
        e.phone as engineer_phone,
        e.emp_id as engineer_emp_id
    FROM installations i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN employees e ON i.engineer_id = e.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$installation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$installation) {
    setModal("Error", "Installation not found");
    header("Location: index.php");
    exit;
}

// Fetch attachments
$attachStmt = $pdo->prepare("
    SELECT * FROM installation_attachments
    WHERE installation_id = ?
    ORDER BY uploaded_at DESC
");
$attachStmt->execute([$id]);
$attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products installed
$prodStmt = $pdo->prepare("
    SELECT ip.*, pm.part_name as master_part_name
    FROM installation_products ip
    LEFT JOIN part_master pm ON ip.part_no = pm.part_no
    WHERE ip.installation_id = ?
    ORDER BY ip.id
");
$prodStmt->execute([$id]);
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch linked invoice details
$invoiceData = null;
$invoiceItems = [];
if (!empty($installation['invoice_id'])) {
    try {
        $invStmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ?");
        $invStmt->execute([$installation['invoice_id']]);
        $invoiceData = $invStmt->fetch(PDO::FETCH_ASSOC);

        if ($invoiceData && !empty($invoiceData['so_no'])) {
            $chainStmt = $pdo->prepare("
                SELECT q.id as quote_id FROM sales_orders so
                LEFT JOIN quote_master q ON q.id = so.linked_quote_id
                WHERE so.so_no = ? LIMIT 1
            ");
            $chainStmt->execute([$invoiceData['so_no']]);
            $chain = $chainStmt->fetch(PDO::FETCH_ASSOC);

            if ($chain && $chain['quote_id']) {
                $itemStmt = $pdo->prepare("
                    SELECT qi.* FROM quote_items qi WHERE qi.quote_id = ? ORDER BY qi.id
                ");
                $itemStmt->execute([$chain['quote_id']]);
                $invoiceItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // invoice tables might not exist
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_file' && !empty($_FILES['attachment']['name'])) {
        $uploadDir = '../uploads/installations/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = $installation['installation_no'] . '_' . time() . '.' . $ext;
            $filePath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $attachType = $_POST['attachment_type'] ?? 'document';
                $description = trim($_POST['description'] ?? '');

                $insertStmt = $pdo->prepare("
                    INSERT INTO installation_attachments
                    (installation_id, file_name, file_path, file_type, file_size, attachment_type, description, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $insertStmt->execute([
                    $id,
                    $file['name'],
                    'uploads/installations/' . $newFileName,
                    $file['type'],
                    $file['size'],
                    $attachType,
                    $description
                ]);

                setModal("Success", "File uploaded successfully");
                header("Location: view.php?id=$id");
                exit;
            }
        }
    }

    if ($_POST['action'] === 'delete_attachment') {
        $attachId = (int)($_POST['attachment_id'] ?? 0);
        if ($attachId) {
            // Get file path first
            $fileStmt = $pdo->prepare("SELECT file_path FROM installation_attachments WHERE id = ? AND installation_id = ?");
            $fileStmt->execute([$attachId, $id]);
            $filePath = $fileStmt->fetchColumn();

            if ($filePath && file_exists('../' . $filePath)) {
                unlink('../' . $filePath);
            }

            $delStmt = $pdo->prepare("DELETE FROM installation_attachments WHERE id = ? AND installation_id = ?");
            $delStmt->execute([$attachId, $id]);

            setModal("Success", "Attachment deleted");
            header("Location: view.php?id=$id");
            exit;
        }
    }

    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['new_status'] ?? '';
        if ($newStatus) {
            $updateStmt = $pdo->prepare("UPDATE installations SET status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $id]);

            if ($newStatus === 'completed') {
                $pdo->prepare("UPDATE installations SET completion_date = CURDATE() WHERE id = ?")->execute([$id]);
            }

            setModal("Success", "Status updated to " . ucwords(str_replace('_', ' ', $newStatus)));
            header("Location: view.php?id=$id");
            exit;
        }
    }

    if ($_POST['action'] === 'create_task') {
        try {
            include_once "../includes/auto_task.php";
            $taskAssignee = ($installation['engineer_type'] === 'internal' && $installation['engineer_id']) ? $installation['engineer_id'] : null;
            $taskId = createAutoTask($pdo, [
                'task_name' => "Installation {$installation['installation_no']}",
                'task_description' => "Complete installation {$installation['installation_no']} scheduled on {$installation['installation_date']}" .
                    ($installation['company_name'] ? " for {$installation['company_name']}" : ''),
                'priority' => 'High',
                'assigned_to' => $taskAssignee,
                'start_date' => $installation['installation_date'],
                'due_date' => $installation['installation_date'],
                'related_module' => 'Installation',
                'related_id' => $id,
                'related_reference' => $installation['installation_no'],
                'customer_id' => $installation['customer_id'],
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);
            if ($taskId) {
                setModal("Success", "Task created successfully for this installation!");
            } else {
                setModal("Error", "Failed to create task. Please try again.");
            }
        } catch (Exception $e) {
            setModal("Error", "Failed to create task: " . $e->getMessage());
        }
        header("Location: view.php?id=$id");
        exit;
    }
}

// Check if a task exists for this installation
$instTaskExists = false;
$instTask = null;
try {
    $taskCheck = $pdo->prepare("SELECT id, task_no, status FROM tasks WHERE related_module = 'Installation' AND related_id = ? LIMIT 1");
    $taskCheck->execute([$id]);
    $instTask = $taskCheck->fetch();
    $instTaskExists = !empty($instTask);
} catch (Exception $e) {
    // tasks table might not exist
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Installation <?= htmlspecialchars($installation['installation_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .detail-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .detail-card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .detail-item {
            padding: 10px 0;
        }
        .detail-item label {
            display: block;
            font-size: 0.85em;
            color: #666;
            margin-bottom: 3px;
        }
        .detail-item .value {
            font-size: 1em;
            color: #2c3e50;
            font-weight: 500;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-in_progress { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e9; color: #388e3c; }
        .status-cancelled { background: #ffebee; color: #d32f2f; }
        .status-on_hold { background: #f3e5f5; color: #7b1fa2; }
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .attachment-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .attachment-item .icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .attachment-item .name {
            font-size: 0.9em;
            word-break: break-word;
            margin-bottom: 10px;
        }
        .attachment-item .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            background: #e3f2fd;
            color: #1976d2;
            margin-bottom: 10px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .status-form {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .upload-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .upload-form .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .products-table th, .products-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
            font-size: 0.8em;
            text-transform: uppercase;
        }
        .products-table tr:hover {
            background: #f8f9fa;
        }
        .products-table .number {
            text-align: right;
        }
        .invoice-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        .invoice-link:hover {
            background: #bbdefb;
        }
        .warranty-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .warranty-active { background: #e8f5e9; color: #388e3c; }
        .warranty-expired { background: #ffebee; color: #d32f2f; }
    </style>
</head>
<body>

<div class="content">
    <!-- Header -->
    <div class="header-actions">
        <div>
            <h1 style="margin: 0;">
                <?= htmlspecialchars($installation['installation_no']) ?>
                <span class="status-badge status-<?= $installation['status'] ?>">
                    <?= ucwords(str_replace('_', ' ', $installation['status'])) ?>
                </span>
            </h1>
        </div>
        <div>
            <form method="post" class="status-form" style="display: inline;">
                <input type="hidden" name="action" value="update_status">
                <select name="new_status" onchange="this.form.submit()">
                    <option value="">Change Status...</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="on_hold">On Hold</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </form>
            <?php if (!$instTaskExists && in_array($installation['status'], ['scheduled', 'in_progress'])): ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="create_task">
                    <button type="submit" class="btn" style="background: #3b82f6; color: white;">Create Task</button>
                </form>
            <?php elseif ($instTaskExists): ?>
                <a href="/tasks/index.php?search=<?= urlencode($installation['installation_no']) ?>" class="btn" style="background: #6366f1; color: white;">
                    View Task (<?= htmlspecialchars($instTask['task_no']) ?>)
                </a>
            <?php endif; ?>
            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <a href="index.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <!-- Customer Details -->
    <div class="detail-card">
        <h3>Customer Details</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <label>Customer ID</label>
                <div class="value"><?= htmlspecialchars($installation['cust_code']) ?></div>
            </div>
            <div class="detail-item">
                <label>Company Name</label>
                <div class="value"><?= htmlspecialchars($installation['company_name'] ?: '-') ?></div>
            </div>
            <div class="detail-item">
                <label>Contact Person</label>
                <div class="value"><?= htmlspecialchars($installation['customer_name']) ?></div>
            </div>
            <div class="detail-item">
                <label>Phone</label>
                <div class="value"><?= htmlspecialchars($installation['customer_phone'] ?: '-') ?></div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?= htmlspecialchars($installation['customer_email'] ?: '-') ?></div>
            </div>
            <div class="detail-item">
                <label>Address</label>
                <div class="value">
                    <?= htmlspecialchars(trim($installation['address1'] . ' ' . $installation['address2'])) ?><br>
                    <?= htmlspecialchars($installation['city'] . ', ' . $installation['state'] . ' - ' . $installation['pincode']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Installation Details -->
    <div class="detail-card">
        <h3>Installation Details</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <label>Installation Date</label>
                <div class="value"><?= date('d M Y', strtotime($installation['installation_date'])) ?></div>
            </div>
            <div class="detail-item">
                <label>Installation Time</label>
                <div class="value"><?= $installation['installation_time'] ? date('h:i A', strtotime($installation['installation_time'])) : '-' ?></div>
            </div>
            <div class="detail-item">
                <label>Completion Date</label>
                <div class="value"><?= $installation['completion_date'] ? date('d M Y', strtotime($installation['completion_date'])) : '-' ?></div>
            </div>
            <div class="detail-item">
                <label>Created At</label>
                <div class="value"><?= date('d M Y h:i A', strtotime($installation['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Engineer Details -->
    <div class="detail-card">
        <h3>Engineer Details</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <label>Engineer Type</label>
                <div class="value"><?= ucfirst($installation['engineer_type']) ?></div>
            </div>
            <?php if ($installation['engineer_type'] === 'internal'): ?>
                <div class="detail-item">
                    <label>Employee ID</label>
                    <div class="value"><?= htmlspecialchars($installation['engineer_emp_id'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <label>Engineer Name</label>
                    <div class="value"><?= htmlspecialchars($installation['engineer_name'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <label>Phone</label>
                    <div class="value"><?= htmlspecialchars($installation['engineer_phone'] ?: '-') ?></div>
                </div>
            <?php else: ?>
                <div class="detail-item">
                    <label>Engineer Name</label>
                    <div class="value"><?= htmlspecialchars($installation['external_engineer_name'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <label>Phone</label>
                    <div class="value"><?= htmlspecialchars($installation['external_engineer_phone'] ?: '-') ?></div>
                </div>
                <div class="detail-item">
                    <label>Company</label>
                    <div class="value"><?= htmlspecialchars($installation['external_engineer_company'] ?: '-') ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Site Details -->
    <?php if ($installation['site_address'] || $installation['site_contact_person']): ?>
    <div class="detail-card">
        <h3>Site Details</h3>
        <div class="detail-grid">
            <?php if ($installation['site_address']): ?>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <label>Site Address</label>
                <div class="value"><?= nl2br(htmlspecialchars($installation['site_address'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Site Contact Person</label>
                <div class="value"><?= htmlspecialchars($installation['site_contact_person'] ?: '-') ?></div>
            </div>
            <div class="detail-item">
                <label>Site Contact Phone</label>
                <div class="value"><?= htmlspecialchars($installation['site_contact_phone'] ?: '-') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Products Installed -->
    <div class="detail-card">
        <h3>Products Installed</h3>
        <?php if (empty($products)): ?>
            <p style="color: #666; font-style: italic;">No products recorded for this installation</p>
        <?php else: ?>
            <table class="products-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Part No</th>
                        <th>Product Name</th>
                        <th>Serial Number</th>
                        <th class="number">Qty</th>
                        <th>Warranty</th>
                        <th>Warranty End</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $i => $prod): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($prod['part_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($prod['product_name'] ?: ($prod['master_part_name'] ?: '-')) ?></td>
                        <td><?= htmlspecialchars($prod['serial_number'] ?: '-') ?></td>
                        <td class="number"><?= (int)$prod['quantity'] ?></td>
                        <td><?= $prod['warranty_months'] ? $prod['warranty_months'] . ' months' : '-' ?></td>
                        <td>
                            <?php if ($prod['warranty_end_date']): ?>
                                <?= date('d M Y', strtotime($prod['warranty_end_date'])) ?>
                                <?php
                                $isExpired = strtotime($prod['warranty_end_date']) < time();
                                ?>
                                <span class="warranty-badge <?= $isExpired ? 'warranty-expired' : 'warranty-active' ?>">
                                    <?= $isExpired ? 'Expired' : 'Active' ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($prod['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Invoice Details -->
    <?php if ($invoiceData): ?>
    <div class="detail-card">
        <h3>Invoice Details</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <label>Invoice No</label>
                <div class="value">
                    <a href="/invoices/view.php?id=<?= (int)$invoiceData['id'] ?>" class="invoice-link">
                        <?= htmlspecialchars($invoiceData['invoice_no']) ?>
                    </a>
                </div>
            </div>
            <div class="detail-item">
                <label>Invoice Date</label>
                <div class="value"><?= !empty($invoiceData['invoice_date']) ? date('d M Y', strtotime($invoiceData['invoice_date'])) : '-' ?></div>
            </div>
            <div class="detail-item">
                <label>Sales Order</label>
                <div class="value"><?= htmlspecialchars($invoiceData['so_no'] ?: '-') ?></div>
            </div>
            <div class="detail-item">
                <label>Status</label>
                <div class="value">
                    <span class="status-badge status-<?= $invoiceData['status'] === 'released' ? 'completed' : 'scheduled' ?>">
                        <?= ucfirst($invoiceData['status'] ?? 'Draft') ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($invoiceItems)): ?>
        <h4 style="margin: 20px 0 5px; color: #2c3e50; font-size: 0.95em;">Invoice Line Items</h4>
        <table class="products-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Description</th>
                    <th>HSN</th>
                    <th class="number">Qty</th>
                    <th>Unit</th>
                    <th class="number">Rate</th>
                    <th class="number">Taxable Amt</th>
                    <th class="number">Total Amt</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $invTotal = 0;
                foreach ($invoiceItems as $j => $item):
                    $invTotal += $item['total_amount'] ?? 0;
                ?>
                <tr>
                    <td><?= $j + 1 ?></td>
                    <td><?= htmlspecialchars($item['part_no'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['part_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
                    <td class="number"><?= number_format($item['qty'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                    <td class="number"><?= number_format($item['rate'] ?? 0, 2) ?></td>
                    <td class="number"><?= number_format($item['taxable_amount'] ?? 0, 2) ?></td>
                    <td class="number"><?= number_format($item['total_amount'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; border-top: 2px solid #ddd;">
                    <td colspan="9" style="text-align: right;">Grand Total:</td>
                    <td class="number"><?= number_format($invTotal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Notes -->
    <?php if ($installation['installation_notes']): ?>
    <div class="detail-card">
        <h3>Installation Notes</h3>
        <p><?= nl2br(htmlspecialchars($installation['installation_notes'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Attachments -->
    <div class="detail-card">
        <h3>Reports & Attachments</h3>

        <!-- Upload Form -->
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="action" value="upload_file">
            <div class="form-row">
                <div>
                    <label>File</label><br>
                    <input type="file" name="attachment" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx">
                </div>
                <div>
                    <label>Type</label><br>
                    <select name="attachment_type">
                        <option value="report">Report</option>
                        <option value="photo">Photo</option>
                        <option value="document">Document</option>
                        <option value="signature">Signature</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label>Description</label><br>
                    <input type="text" name="description" placeholder="Brief description">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </form>

        <?php if (empty($attachments)): ?>
            <p style="color: #666; font-style: italic;">No attachments yet</p>
        <?php else: ?>
            <div class="attachments-grid">
                <?php foreach ($attachments as $att): ?>
                    <div class="attachment-item">
                        <div class="icon">
                            <?php
                            $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                echo '<img src="../' . htmlspecialchars($att['file_path']) . '" style="max-width: 100%; max-height: 100px; border-radius: 4px;">';
                            } elseif ($ext === 'pdf') {
                                echo '📄';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                echo '📝';
                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                echo '📊';
                            } else {
                                echo '📎';
                            }
                            ?>
                        </div>
                        <div class="type-badge"><?= ucfirst($att['attachment_type']) ?></div>
                        <div class="name"><?= htmlspecialchars($att['file_name']) ?></div>
                        <?php if ($att['description']): ?>
                            <div style="font-size: 0.8em; color: #666; margin-bottom: 10px;">
                                <?= htmlspecialchars($att['description']) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <a href="../<?= htmlspecialchars($att['file_path']) ?>" target="_blank" class="btn btn-small btn-primary">View</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this attachment?');">
                                <input type="hidden" name="action" value="delete_attachment">
                                <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Customer Feedback -->
    <?php if ($installation['customer_feedback'] || $installation['rating']): ?>
    <div class="detail-card">
        <h3>Customer Feedback</h3>
        <div class="detail-grid">
            <div class="detail-item">
                <label>Rating</label>
                <div class="value">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?= $i <= $installation['rating'] ? '★' : '☆' ?>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="detail-item">
                <label>Customer Signature</label>
                <div class="value"><?= $installation['customer_signature'] ? 'Yes' : 'No' ?></div>
            </div>
            <?php if ($installation['customer_feedback']): ?>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <label>Feedback</label>
                <div class="value"><?= nl2br(htmlspecialchars($installation['customer_feedback'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
