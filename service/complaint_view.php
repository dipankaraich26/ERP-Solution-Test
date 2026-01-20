<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: complaints.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $newStatus = $_POST['new_status'];
        $remarks = $_POST['remarks'] ?? '';

        // Get old status
        $oldStatus = $pdo->prepare("SELECT status FROM service_complaints WHERE id = ?");
        $oldStatus->execute([$id]);
        $old = $oldStatus->fetchColumn();

        // Update complaint
        $updateFields = ["status = ?"];
        $updateParams = [$newStatus];

        if ($newStatus === 'Resolved' || $newStatus === 'Closed') {
            $updateFields[] = "resolution_date = NOW()";
            if ($_POST['resolution_notes']) {
                $updateFields[] = "resolution_notes = ?";
                $updateParams[] = $_POST['resolution_notes'];
            }
        }

        $updateParams[] = $id;
        $pdo->prepare("UPDATE service_complaints SET " . implode(", ", $updateFields) . " WHERE id = ?")->execute($updateParams);

        // Log history
        $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, remarks) VALUES (?, ?, ?, ?)")
            ->execute([$id, $old, $newStatus, $remarks]);

        setModal("Success", "Status updated to '$newStatus'");
        header("Location: complaint_view.php?id=$id");
        exit;
    }

    if ($action === 'assign') {
        $techId = $_POST['technician_id'];
        $visitDate = $_POST['visit_date'] ?: null;
        $visitTime = $_POST['visit_time'] ?: null;

        $pdo->prepare("UPDATE service_complaints SET assigned_technician_id = ?, assigned_date = NOW(), scheduled_visit_date = ?, scheduled_visit_time = ?, status = 'Assigned' WHERE id = ?")
            ->execute([$techId, $visitDate, $visitTime, $id]);

        $pdo->prepare("INSERT INTO complaint_status_history (complaint_id, old_status, new_status, remarks) VALUES (?, 'Open', 'Assigned', 'Technician assigned')")
            ->execute([$id]);

        setModal("Success", "Technician assigned successfully!");
        header("Location: complaint_view.php?id=$id");
        exit;
    }
}

// Fetch complaint
$stmt = $pdo->prepare("
    SELECT c.*,
           cat.name AS category_name,
           t.name AS technician_name,
           t.phone AS technician_phone,
           s.state_name
    FROM service_complaints c
    LEFT JOIN service_issue_categories cat ON c.issue_category_id = cat.id
    LEFT JOIN service_technicians t ON c.assigned_technician_id = t.id
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    header("Location: complaints.php");
    exit;
}

// Get visit history
$visits = $pdo->prepare("
    SELECT v.*, t.name AS technician_name
    FROM service_visits v
    LEFT JOIN service_technicians t ON v.technician_id = t.id
    WHERE v.complaint_id = ?
    ORDER BY v.visit_date DESC
");
$visits->execute([$id]);
$visitHistory = $visits->fetchAll(PDO::FETCH_ASSOC);

// Get status history
$history = $pdo->prepare("SELECT * FROM complaint_status_history WHERE complaint_id = ? ORDER BY changed_at DESC");
$history->execute([$id]);
$statusHistory = $history->fetchAll(PDO::FETCH_ASSOC);

// Get technicians for assignment
$technicians = $pdo->query("SELECT id, tech_code, name FROM service_technicians WHERE status = 'Active' ORDER BY name")->fetchAll();

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complaint <?= htmlspecialchars($complaint['complaint_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .complaint-view { max-width: 1000px; }

        .complaint-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .complaint-header h1 { margin: 0 0 10px 0; }
        .complaint-header .complaint-no { font-size: 1.3em; opacity: 0.9; }
        .complaint-header .meta { margin-top: 15px; display: flex; gap: 30px; flex-wrap: wrap; }
        .complaint-header .meta-item label { opacity: 0.8; font-size: 0.85em; }
        .complaint-header .meta-item .value { font-weight: bold; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .status-Open { background: #ffeaa7; color: #d68910; }
        .status-Assigned { background: #dfe6e9; color: #636e72; }
        .status-In-Progress { background: #e8daef; color: #8e44ad; }
        .status-On-Hold { background: #fad7a0; color: #d35400; }
        .status-Resolved { background: #d5f4e6; color: #27ae60; }
        .status-Closed { background: #d4e6f1; color: #2980b9; }

        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .priority-Critical { background: #e74c3c; color: white; }
        .priority-High { background: #e67e22; color: white; }
        .priority-Medium { background: #f1c40f; color: #333; }
        .priority-Low { background: #95a5a6; color: white; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .info-card h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 1em;
        }
        .info-card .content { padding: 20px; }
        .info-card .item { margin-bottom: 12px; }
        .info-card .item:last-child { margin-bottom: 0; }
        .info-card .item label { display: block; color: #7f8c8d; font-size: 0.85em; }
        .info-card .item .value { font-weight: 500; }

        .description-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .assign-form, .status-form {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .assign-form h4, .status-form h4 { margin: 0 0 15px 0; }
        .assign-form .form-row, .status-form .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .assign-form select, .assign-form input,
        .status-form select, .status-form input, .status-form textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .history-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .history-section h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .history-section .content { padding: 20px; }

        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f5f5f5;
        }
        .timeline-item:last-child { margin-bottom: 0; border-bottom: none; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #3498db;
        }
        .timeline-item .date { color: #7f8c8d; font-size: 0.85em; }
        .timeline-item .status-change { font-weight: bold; margin: 5px 0; }
        .timeline-item .remarks { color: #555; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="content">
    <div class="complaint-view">

        <div class="action-buttons">
            <a href="complaints.php" class="btn btn-secondary">Back to Complaints</a>
            <a href="complaint_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <?php if (!in_array($complaint['status'], ['Resolved', 'Closed', 'Cancelled'])): ?>
                <a href="#status-update" class="btn btn-warning">Update Status</a>
            <?php endif; ?>
        </div>

        <div class="complaint-header">
            <div class="complaint-no"><?= htmlspecialchars($complaint['complaint_no']) ?></div>
            <h1><?= htmlspecialchars($complaint['customer_name']) ?></h1>
            <div class="meta">
                <div class="meta-item">
                    <label>Status</label>
                    <div class="value"><span class="status-badge status-<?= str_replace(' ', '-', $complaint['status']) ?>"><?= $complaint['status'] ?></span></div>
                </div>
                <div class="meta-item">
                    <label>Priority</label>
                    <div class="value"><span class="priority-badge priority-<?= $complaint['priority'] ?>"><?= $complaint['priority'] ?></span></div>
                </div>
                <div class="meta-item">
                    <label>Registered</label>
                    <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['registered_date'])) ?></div>
                </div>
                <?php if ($complaint['scheduled_visit_date']): ?>
                <div class="meta-item">
                    <label>Scheduled Visit</label>
                    <div class="value"><?= date('d M Y', strtotime($complaint['scheduled_visit_date'])) ?> <?= $complaint['scheduled_visit_time'] ? date('h:i A', strtotime($complaint['scheduled_visit_time'])) : '' ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($complaint['status'] === 'Open' && empty($complaint['assigned_technician_id'])): ?>
        <div class="assign-form">
            <h4>Assign Technician</h4>
            <form method="post">
                <input type="hidden" name="action" value="assign">
                <div class="form-row">
                    <div class="form-group">
                        <label>Technician *</label>
                        <select name="technician_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?> (<?= $tech['tech_code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Visit Date</label>
                        <input type="date" name="visit_date">
                    </div>
                    <div class="form-group">
                        <label>Visit Time</label>
                        <input type="time" name="visit_time">
                    </div>
                    <button type="submit" class="btn btn-success">Assign</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-card">
                <h3>Customer Details</h3>
                <div class="content">
                    <div class="item">
                        <label>Name</label>
                        <div class="value"><?= htmlspecialchars($complaint['customer_name']) ?></div>
                    </div>
                    <div class="item">
                        <label>Phone</label>
                        <div class="value"><a href="tel:<?= $complaint['customer_phone'] ?>"><?= htmlspecialchars($complaint['customer_phone']) ?></a></div>
                    </div>
                    <?php if ($complaint['customer_email']): ?>
                    <div class="item">
                        <label>Email</label>
                        <div class="value"><a href="mailto:<?= $complaint['customer_email'] ?>"><?= htmlspecialchars($complaint['customer_email']) ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['customer_address']): ?>
                    <div class="item">
                        <label>Address</label>
                        <div class="value">
                            <?= nl2br(htmlspecialchars($complaint['customer_address'])) ?>
                            <?php if ($complaint['city']): ?><br><?= htmlspecialchars($complaint['city']) ?><?php endif; ?>
                            <?php if ($complaint['state_name']): ?>, <?= htmlspecialchars($complaint['state_name']) ?><?php endif; ?>
                            <?php if ($complaint['pincode']): ?> - <?= htmlspecialchars($complaint['pincode']) ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <h3>Product Details</h3>
                <div class="content">
                    <div class="item">
                        <label>Product</label>
                        <div class="value"><?= htmlspecialchars($complaint['product_name'] ?? 'Not specified') ?></div>
                    </div>
                    <?php if ($complaint['product_model']): ?>
                    <div class="item">
                        <label>Model</label>
                        <div class="value"><?= htmlspecialchars($complaint['product_model']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['serial_number']): ?>
                    <div class="item">
                        <label>Serial Number</label>
                        <div class="value"><?= htmlspecialchars($complaint['serial_number']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['purchase_date']): ?>
                    <div class="item">
                        <label>Purchase Date</label>
                        <div class="value"><?= date('d M Y', strtotime($complaint['purchase_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="item">
                        <label>Warranty Status</label>
                        <div class="value"><?= htmlspecialchars($complaint['warranty_status']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Issue Details</h3>
                <div class="content">
                    <div class="item">
                        <label>Category</label>
                        <div class="value"><?= htmlspecialchars($complaint['category_name'] ?? 'Not categorized') ?></div>
                    </div>
                    <div class="item">
                        <label>Description</label>
                        <div class="description-box"><?= htmlspecialchars($complaint['complaint_description']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Assignment</h3>
                <div class="content">
                    <div class="item">
                        <label>Assigned Technician</label>
                        <div class="value"><?= htmlspecialchars($complaint['technician_name'] ?? 'Not assigned') ?></div>
                    </div>
                    <?php if ($complaint['technician_phone']): ?>
                    <div class="item">
                        <label>Technician Phone</label>
                        <div class="value"><a href="tel:<?= $complaint['technician_phone'] ?>"><?= htmlspecialchars($complaint['technician_phone']) ?></a></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['assigned_date']): ?>
                    <div class="item">
                        <label>Assigned On</label>
                        <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['assigned_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['resolution_date']): ?>
                    <div class="item">
                        <label>Resolved On</label>
                        <div class="value"><?= date('d M Y, h:i A', strtotime($complaint['resolution_date'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($complaint['resolution_notes']): ?>
        <div class="info-card" style="margin-bottom: 25px;">
            <h3>Resolution Notes</h3>
            <div class="content">
                <div class="description-box"><?= nl2br(htmlspecialchars($complaint['resolution_notes'])) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!in_array($complaint['status'], ['Resolved', 'Closed', 'Cancelled'])): ?>
        <div class="status-form" id="status-update">
            <h4>Update Status</h4>
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <div class="form-row">
                    <div class="form-group">
                        <label>New Status *</label>
                        <select name="new_status" required onchange="toggleResolutionNotes(this.value)">
                            <option value="">-- Select --</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Remarks</label>
                        <input type="text" name="remarks" placeholder="Optional remarks...">
                    </div>
                </div>
                <div class="form-group" id="resolution-notes-group" style="display: none; margin-top: 15px;">
                    <label>Resolution Notes</label>
                    <textarea name="resolution_notes" rows="3" placeholder="Describe how the issue was resolved..."></textarea>
                </div>
                <button type="submit" class="btn btn-warning" style="margin-top: 15px;">Update Status</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($statusHistory)): ?>
        <div class="history-section">
            <h3>Status History</h3>
            <div class="content">
                <div class="timeline">
                    <?php foreach ($statusHistory as $h): ?>
                    <div class="timeline-item">
                        <div class="date"><?= date('d M Y, h:i A', strtotime($h['changed_at'])) ?></div>
                        <div class="status-change">
                            <?php if ($h['old_status']): ?>
                                <?= $h['old_status'] ?> &rarr; <?= $h['new_status'] ?>
                            <?php else: ?>
                                <?= $h['new_status'] ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($h['remarks']): ?>
                        <div class="remarks"><?= htmlspecialchars($h['remarks']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function toggleResolutionNotes(status) {
    const notesGroup = document.getElementById('resolution-notes-group');
    if (status === 'Resolved' || status === 'Closed') {
        notesGroup.style.display = 'block';
    } else {
        notesGroup.style.display = 'none';
    }
}
</script>

</body>
</html>
