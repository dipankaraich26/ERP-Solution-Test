<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['category_name'] ?? '');
            $code = strtoupper(trim($_POST['category_code'] ?? ''));
            $color = $_POST['color_code'] ?? '#3498db';
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $errors[] = "Category name is required";
            }
            if (empty($code)) {
                $errors[] = "Category code is required";
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO task_categories (category_name, category_code, color_code, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $code, $color, $description]);
                    setModal("Success", "Category '$name' created successfully!");
                    header("Location: categories.php");
                    exit;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $errors[] = "Category code '$code' already exists";
                    } else {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                }
            }
        }

        if ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['category_name'] ?? '');
            $color = $_POST['color_code'] ?? '#3498db';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                $errors[] = "Category name is required";
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE task_categories SET category_name = ?, color_code = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $color, $description, $is_active, $id]);
                setModal("Success", "Category updated successfully!");
                header("Location: categories.php");
                exit;
            }
        }

        if ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            // Check if category is in use
            $count = $pdo->query("SELECT COUNT(*) FROM tasks WHERE category_id = $id")->fetchColumn();
            if ($count > 0) {
                setModal("Error", "Cannot delete: Category is used by $count task(s). Set it to inactive instead.");
            } else {
                $pdo->prepare("DELETE FROM task_categories WHERE id = ?")->execute([$id]);
                setModal("Success", "Category deleted successfully!");
            }
            header("Location: categories.php");
            exit;
        }
    }
}

// Get all categories with task count
$categories = $pdo->query("
    SELECT tc.*, COUNT(t.id) as task_count
    FROM task_categories tc
    LEFT JOIN tasks t ON tc.id = t.category_id
    GROUP BY tc.id
    ORDER BY tc.category_name
")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Categories - Task Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .category-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #3498db;
            position: relative;
        }
        .category-card.inactive {
            opacity: 0.6;
        }
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .category-name {
            font-size: 1.1em;
            font-weight: bold;
            color: #2c3e50;
        }
        .category-code {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-family: monospace;
        }
        .category-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .task-count {
            color: #999;
            font-size: 0.9em;
        }
        .category-actions {
            display: flex;
            gap: 5px;
        }
        .category-actions button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
        }
        .btn-edit { background: #3498db; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .inactive-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff9800;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
        }

        .add-category-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .add-category-section h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .form-group input[type="color"] {
            height: 40px;
            cursor: pointer;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Edit Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Task Categories</h1>
            <p style="color: #666; margin: 5px 0 0;">Organize tasks by department or type</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">All Tasks</a>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px;">Dashboard</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="error-box">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="add-category-section">
        <h2>Add New Category</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="category_name" required placeholder="e.g., Marketing">
                </div>
                <div class="form-group">
                    <label>Code *</label>
                    <input type="text" name="category_code" required placeholder="e.g., MKT" maxlength="20"
                           style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color_code" value="#3498db">
                </div>
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description of this category..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-success" style="margin-top: 10px;">Add Category</button>
        </form>
    </div>

    <!-- Categories Grid -->
    <h2 style="margin-bottom: 15px;">All Categories (<?= count($categories) ?>)</h2>
    <div class="categories-grid">
        <?php foreach ($categories as $cat): ?>
        <div class="category-card <?= !$cat['is_active'] ? 'inactive' : '' ?>" style="border-left-color: <?= htmlspecialchars($cat['color_code']) ?>">
            <?php if (!$cat['is_active']): ?>
            <span class="inactive-badge">Inactive</span>
            <?php endif; ?>
            <div class="category-header">
                <div class="category-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                <div class="category-code"><?= htmlspecialchars($cat['category_code']) ?></div>
            </div>
            <div class="category-description">
                <?= $cat['description'] ? htmlspecialchars($cat['description']) : '<em style="color: #999;">No description</em>' ?>
            </div>
            <div class="category-stats">
                <span class="task-count"><?= $cat['task_count'] ?> task<?= $cat['task_count'] != 1 ? 's' : '' ?></span>
                <div class="category-actions">
                    <button class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)">Edit</button>
                    <?php if ($cat['task_count'] == 0): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn-delete" onclick="return confirm('Delete this category?')">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <h2>Edit Category</h2>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="category_name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Code (cannot be changed)</label>
                <input type="text" id="edit_code" disabled style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>Color</label>
                <input type="color" name="color_code" id="edit_color">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_active" value="1">
                    Active
                </label>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(cat) {
    document.getElementById('edit_id').value = cat.id;
    document.getElementById('edit_name').value = cat.category_name;
    document.getElementById('edit_code').value = cat.category_code;
    document.getElementById('edit_color').value = cat.color_code;
    document.getElementById('edit_description').value = cat.description || '';
    document.getElementById('edit_active').checked = cat.is_active == 1;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

</body>
</html>
