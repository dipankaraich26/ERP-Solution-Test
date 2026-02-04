<?php
include "../db.php";
include "../includes/dialog.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $designation = trim($_POST['designation'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = (int)($_POST['sort_order'] ?? 0);

            if (empty($name)) {
                setModal("Error", "Name is required");
            } else {
                try {
                    if ($id) {
                        // Update existing
                        $stmt = $pdo->prepare("
                            UPDATE signatories
                            SET name = ?, designation = ?, department = ?, is_active = ?, sort_order = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $designation, $department, $is_active, $sort_order, $id]);
                        setModal("Success", "Signatory updated successfully");
                    } else {
                        // Add new
                        $stmt = $pdo->prepare("
                            INSERT INTO signatories (name, designation, department, is_active, sort_order)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $designation, $department, $is_active, $sort_order]);
                        setModal("Success", "Signatory added successfully");
                    }
                } catch (PDOException $e) {
                    setModal("Error", "Database error: " . $e->getMessage());
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM signatories WHERE id = ?");
                $stmt->execute([$id]);
                setModal("Success", "Signatory deleted successfully");
            } catch (PDOException $e) {
                setModal("Error", "Failed to delete: " . $e->getMessage());
            }
        }
    }
    header("Location: signatories.php");
    exit;
}

// Fetch all signatories
$signatories = $pdo->query("SELECT * FROM signatories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Get signatory for editing
$editSignatory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM signatories WHERE id = ?");
    $stmt->execute([$editId]);
    $editSignatory = $stmt->fetch(PDO::FETCH_ASSOC);
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Signatories</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus {
            outline: none;
            border-color: #3498db;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .signatories-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .signatories-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .signatories-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .signatories-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info-box strong {
            color: #1565c0;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Manage Signatories</h1>

    <div class="info-box">
        <strong>Note:</strong> Signatories will appear on quotations, proforma invoices, and other documents.
        Active signatories are displayed in the order specified by Sort Order.
    </div>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h2><?= $editSignatory ? 'Edit Signatory' : 'Add New Signatory' ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="<?= $editSignatory ? 'edit' : 'add' ?>">
            <?php if ($editSignatory): ?>
                <input type="hidden" name="id" value="<?= $editSignatory['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editSignatory['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="designation" value="<?= htmlspecialchars($editSignatory['designation'] ?? '') ?>" placeholder="e.g., Director, Manager">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" value="<?= htmlspecialchars($editSignatory['department'] ?? '') ?>" placeholder="e.g., Sales, Operations">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editSignatory['sort_order'] ?? '0') ?>" min="0">
                    <small style="color: #666; margin-top: 4px;">Lower numbers appear first</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= ($editSignatory['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" style="margin: 0;">Active (Show in documents)</label>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">
                    <?= $editSignatory ? 'Update Signatory' : 'Add Signatory' ?>
                </button>
                <?php if ($editSignatory): ?>
                    <a href="signatories.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Signatories List -->
    <h2>All Signatories</h2>
    <table class="signatories-table">
        <thead>
            <tr>
                <th>Sort Order</th>
                <th>Name</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($signatories)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                        No signatories added yet. Add your first signatory above.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($signatories as $sig): ?>
                <tr>
                    <td><strong><?= $sig['sort_order'] ?></strong></td>
                    <td><strong><?= htmlspecialchars($sig['name']) ?></strong></td>
                    <td><?= htmlspecialchars($sig['designation'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($sig['department'] ?: '-') ?></td>
                    <td>
                        <span class="status-badge status-<?= $sig['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $sig['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?edit=<?= $sig['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this signatory?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $sig['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
