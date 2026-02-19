<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$success_msg = '';
$error_msg = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        try {
            $maxOrder = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM qc_inspection_checkpoints")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO qc_inspection_checkpoints (checkpoint_name, specification, category, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['checkpoint_name']),
                trim($_POST['specification']),
                trim($_POST['category']) ?: 'General',
                $maxOrder
            ]);
            $success_msg = "Checkpoint added successfully.";
        } catch (PDOException $e) {
            $error_msg = "Error adding checkpoint: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'edit') {
        try {
            $stmt = $pdo->prepare("UPDATE qc_inspection_checkpoints SET checkpoint_name = ?, specification = ?, category = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['checkpoint_name']),
                trim($_POST['specification']),
                trim($_POST['category']) ?: 'General',
                (int)$_POST['id']
            ]);
            $success_msg = "Checkpoint updated successfully.";
        } catch (PDOException $e) {
            $error_msg = "Error updating checkpoint: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'toggle') {
        try {
            $stmt = $pdo->prepare("UPDATE qc_inspection_checkpoints SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $success_msg = "Checkpoint status toggled.";
        } catch (PDOException $e) {
            $error_msg = "Error toggling checkpoint: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'delete') {
        try {
            // Check if used in any matrix
            $usedCount = $pdo->prepare("SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE checkpoint_id = ?");
            $usedCount->execute([(int)$_POST['id']]);
            if ($usedCount->fetchColumn() > 0) {
                $error_msg = "Cannot delete: this checkpoint is assigned to parts in the inspection matrix. Deactivate it instead.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM qc_inspection_checkpoints WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $success_msg = "Checkpoint deleted.";
            }
        } catch (PDOException $e) {
            $error_msg = "Error deleting checkpoint: " . $e->getMessage();
        }
    }
}

// Get all checkpoints
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$where = [];
$params = [];
if ($filter_category) {
    $where[] = "category = ?";
    $params[] = $filter_category;
}
$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $stmt = $pdo->prepare("SELECT * FROM qc_inspection_checkpoints $where_clause ORDER BY category, sort_order");
    $stmt->execute($params);
    $checkpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categories = $pdo->query("SELECT DISTINCT category FROM qc_inspection_checkpoints ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $checkpoints = [];
    $categories = [];
    $error_msg = "Error loading checkpoints: " . $e->getMessage();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inspection Checkpoints - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .filter-section {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-section label { font-weight: 600; color: #495057; }
        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: white;
        }

        .checkpoint-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .checkpoint-table th, .checkpoint-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .checkpoint-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }
        .checkpoint-table tr:hover { background: #f8f9fa; }

        .category-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            background: #e8eaf6;
            color: #3f51b5;
        }
        .status-active { color: #27ae60; font-weight: 600; }
        .status-inactive { color: #e74c3c; font-weight: 600; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-box h3 { margin: 0 0 20px; color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #495057; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

        .category-header {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            font-size: 0.95em;
        }

        .alert-success {
            background: #d1fae5; border: 1px solid #10b981; color: #065f46;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }

        body.dark .checkpoint-table { background: #2c3e50; }
        body.dark .checkpoint-table th { background: #34495e; color: #ecf0f1; }
        body.dark .checkpoint-table tr:hover { background: #34495e; }
        body.dark .modal-box { background: #2c3e50; }
        body.dark .modal-box h3 { color: #ecf0f1; }
        body.dark .filter-section { background: #34495e; }
    </style>
</head>
<body>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>Inspection Checkpoints</h1>
            <p style="color: #666; margin: 5px 0 0;">Master list of all quality inspection checkpoints</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="inspection_matrix.php" class="btn btn-secondary">Inspection Matrix</a>
            <button onclick="openAddModal()" class="btn btn-primary">+ Add Checkpoint</button>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label>Category:</label>
                <select name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filter_category): ?>
                <a href="inspection_checkpoints.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= count($checkpoints) ?> checkpoint<?= count($checkpoints) != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Checkpoints Table -->
    <?php if (empty($checkpoints)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #7f8c8d;">
            <h3>No Checkpoints Found</h3>
            <p>Run <a href="setup_inspection_matrix.php">Setup</a> to create default checkpoints, or add manually.</p>
        </div>
    <?php else: ?>
        <?php
        $currentCategory = '';
        ?>
        <table class="checkpoint-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Checkpoint Name</th>
                    <th>Specification</th>
                    <th>Category</th>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkpoints as $i => $cp): ?>
                    <?php if ($cp['category'] !== $currentCategory):
                        $currentCategory = $cp['category'];
                    ?>
                        <tr><td colspan="6" class="category-header"><?= htmlspecialchars($currentCategory) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td style="color: #999;"><?= $cp['sort_order'] ?></td>
                        <td><strong><?= htmlspecialchars($cp['checkpoint_name']) ?></strong></td>
                        <td style="color: #666; font-size: 0.9em;"><?= htmlspecialchars($cp['specification'] ?: '-') ?></td>
                        <td><span class="category-badge"><?= htmlspecialchars($cp['category']) ?></span></td>
                        <td>
                            <span class="<?= $cp['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $cp['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($cp)) ?>)" class="btn btn-sm btn-secondary">Edit</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $cp['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background: <?= $cp['is_active'] ? '#e74c3c' : '#27ae60' ?>; color: white;">
                                    <?= $cp['is_active'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3>Add New Checkpoint</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Checkpoint Name *</label>
                <input type="text" name="checkpoint_name" required placeholder="e.g., Visual Inspection">
            </div>
            <div class="form-group">
                <label>Specification</label>
                <textarea name="specification" placeholder="e.g., No visible defects, scratches or damage"></textarea>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="General">General</option>
                    <option value="Documentation">Documentation</option>
                    <option value="Quantity">Quantity</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Physical">Physical</option>
                    <option value="Functional">Functional</option>
                    <option value="Quality">Quality</option>
                    <option value="Compliance">Compliance</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Checkpoint</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>Edit Checkpoint</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Checkpoint Name *</label>
                <input type="text" name="checkpoint_name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Specification</label>
                <textarea name="specification" id="edit_spec"></textarea>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="edit_category">
                    <option value="General">General</option>
                    <option value="Documentation">Documentation</option>
                    <option value="Quantity">Quantity</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Physical">Physical</option>
                    <option value="Functional">Functional</option>
                    <option value="Quality">Quality</option>
                    <option value="Compliance">Compliance</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">Cancel</button>
                <form method="post" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <button type="submit" class="btn" style="background: #e74c3c; color: white;" onclick="return confirm('Delete this checkpoint?')">Delete</button>
                </form>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function openEditModal(cp) {
    document.getElementById('edit_id').value = cp.id;
    document.getElementById('edit_name').value = cp.checkpoint_name;
    document.getElementById('edit_spec').value = cp.specification || '';
    document.getElementById('edit_category').value = cp.category;
    document.getElementById('delete_id').value = cp.id;
    document.getElementById('editModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

</body>
</html>
