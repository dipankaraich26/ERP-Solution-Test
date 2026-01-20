<?php
include "../db.php";
include "../includes/dialog.php";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            // Generate tech code
            $maxCode = $pdo->query("SELECT MAX(CAST(SUBSTRING(tech_code, 5) AS UNSIGNED)) FROM service_technicians WHERE tech_code LIKE 'TEC-%'")->fetchColumn();
            $tech_code = 'TEC-' . str_pad(($maxCode ?: 0) + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO service_technicians (tech_code, name, phone, email, specialization, assigned_region, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tech_code,
                $name,
                $_POST['phone'] ?: null,
                $_POST['email'] ?: null,
                $_POST['specialization'] ?: null,
                $_POST['assigned_region'] ?: null,
                $_POST['status'] ?? 'Active'
            ]);
            setModal("Success", "Technician '$name' added successfully!");
        }
        header("Location: technicians.php");
        exit;
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE service_technicians SET name = ?, phone = ?, email = ?, specialization = ?, assigned_region = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $name,
                $_POST['phone'] ?: null,
                $_POST['email'] ?: null,
                $_POST['specialization'] ?: null,
                $_POST['assigned_region'] ?: null,
                $_POST['status'] ?? 'Active',
                $id
            ]);
            setModal("Success", "Technician updated successfully!");
        }
        header("Location: technicians.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Check if technician has complaints
        $hasComplaints = $pdo->prepare("SELECT COUNT(*) FROM service_complaints WHERE assigned_technician_id = ?");
        $hasComplaints->execute([$id]);
        if ($hasComplaints->fetchColumn() > 0) {
            setModal("Error", "Cannot delete technician with assigned complaints!");
        } else {
            $pdo->prepare("DELETE FROM service_technicians WHERE id = ?")->execute([$id]);
            setModal("Success", "Technician deleted!");
        }
        header("Location: technicians.php");
        exit;
    }
}

// Get technicians with stats
$technicians = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM service_complaints WHERE assigned_technician_id = t.id) AS total_complaints,
           (SELECT COUNT(*) FROM service_complaints WHERE assigned_technician_id = t.id AND status NOT IN ('Resolved', 'Closed', 'Cancelled')) AS open_complaints,
           (SELECT COUNT(*) FROM service_complaints WHERE assigned_technician_id = t.id AND status IN ('Resolved', 'Closed')) AS resolved_complaints
    FROM service_technicians t
    ORDER BY t.status, t.name
")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Technicians</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-container { max-width: 1100px; }

        .add-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .add-form h3 { margin: 0 0 15px 0; }
        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-row .form-group { margin: 0; }
        .form-row input, .form-row select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .tech-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .tech-table th, .tech-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .tech-table th { background: #f8f9fa; font-weight: bold; }
        .tech-table tr:hover { background: #fafafa; }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-Active { background: #d5f4e6; color: #27ae60; }
        .status-On-Leave { background: #ffeaa7; color: #d68910; }
        .status-Inactive { background: #fadbd8; color: #c0392b; }

        .stats-mini {
            display: flex;
            gap: 10px;
        }
        .stats-mini .stat {
            text-align: center;
            padding: 5px 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .stats-mini .stat .num { font-weight: bold; }

        .edit-form {
            display: none;
            background: #fff3cd;
            padding: 15px;
        }
        .edit-form.active { display: table-row; }

        .action-btns { display: flex; gap: 5px; }
    </style>
</head>
<body>

<div class="content">
    <div class="page-container">
        <h1>Service Technicians</h1>
        <p><a href="complaints.php" class="btn btn-secondary">Back to Complaints</a></p>

        <div class="add-form">
            <h3>Add New Technician</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" required placeholder="Full Name">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" placeholder="Phone Number">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email">
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" placeholder="e.g., Electronics, Mechanical">
                    </div>
                    <div class="form-group">
                        <label>Region</label>
                        <select name="assigned_region">
                            <option value="">-- Select --</option>
                            <option value="North">North</option>
                            <option value="South">South</option>
                            <option value="East">East</option>
                            <option value="West">West</option>
                            <option value="Central">Central</option>
                            <option value="Northeast">Northeast</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active">Active</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">Add Technician</button>
                </div>
            </form>
        </div>

        <?php if (empty($technicians)): ?>
            <p style="text-align: center; color: #7f8c8d; padding: 40px;">No technicians added yet.</p>
        <?php else: ?>
            <table class="tech-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Specialization</th>
                        <th>Region</th>
                        <th>Complaints</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($technicians as $tech): ?>
                    <tr id="row-<?= $tech['id'] ?>">
                        <td><strong><?= htmlspecialchars($tech['tech_code']) ?></strong></td>
                        <td><?= htmlspecialchars($tech['name']) ?></td>
                        <td>
                            <?php if ($tech['phone']): ?>
                                <a href="tel:<?= $tech['phone'] ?>"><?= htmlspecialchars($tech['phone']) ?></a><br>
                            <?php endif; ?>
                            <?php if ($tech['email']): ?>
                                <small><?= htmlspecialchars($tech['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($tech['specialization'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($tech['assigned_region'] ?? '-') ?></td>
                        <td>
                            <div class="stats-mini">
                                <div class="stat" title="Open">
                                    <div class="num" style="color: #e74c3c;"><?= $tech['open_complaints'] ?></div>
                                    <div>Open</div>
                                </div>
                                <div class="stat" title="Resolved">
                                    <div class="num" style="color: #27ae60;"><?= $tech['resolved_complaints'] ?></div>
                                    <div>Done</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="status-badge status-<?= str_replace(' ', '-', $tech['status']) ?>"><?= $tech['status'] ?></span></td>
                        <td class="action-btns">
                            <button class="btn btn-sm btn-primary" onclick="showEdit(<?= $tech['id'] ?>)">Edit</button>
                            <?php if ($tech['total_complaints'] == 0): ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this technician?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $tech['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="edit-form" id="edit-<?= $tech['id'] ?>">
                        <td colspan="8">
                            <form method="post">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?= $tech['id'] ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Name *</label>
                                        <input type="text" name="name" required value="<?= htmlspecialchars($tech['name']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="tel" name="phone" value="<?= htmlspecialchars($tech['phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($tech['email'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Specialization</label>
                                        <input type="text" name="specialization" value="<?= htmlspecialchars($tech['specialization'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Region</label>
                                        <select name="assigned_region">
                                            <option value="">-- Select --</option>
                                            <option value="North" <?= $tech['assigned_region'] === 'North' ? 'selected' : '' ?>>North</option>
                                            <option value="South" <?= $tech['assigned_region'] === 'South' ? 'selected' : '' ?>>South</option>
                                            <option value="East" <?= $tech['assigned_region'] === 'East' ? 'selected' : '' ?>>East</option>
                                            <option value="West" <?= $tech['assigned_region'] === 'West' ? 'selected' : '' ?>>West</option>
                                            <option value="Central" <?= $tech['assigned_region'] === 'Central' ? 'selected' : '' ?>>Central</option>
                                            <option value="Northeast" <?= $tech['assigned_region'] === 'Northeast' ? 'selected' : '' ?>>Northeast</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status">
                                            <option value="Active" <?= $tech['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="On Leave" <?= $tech['status'] === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                                            <option value="Inactive" <?= $tech['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success">Save</button>
                                    <button type="button" class="btn btn-secondary" onclick="hideEdit(<?= $tech['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function showEdit(id) {
    document.querySelectorAll('.edit-form').forEach(el => el.classList.remove('active'));
    document.getElementById('edit-' + id).classList.add('active');
}
function hideEdit(id) {
    document.getElementById('edit-' + id).classList.remove('active');
}
</script>

</body>
</html>
