<?php
/**
 * Appraisal Criteria Management
 * Configure evaluation criteria for appraisals
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'appraisal_criteria'")->fetch();
if (!$tableExists) {
    setModal("Setup Required", "Please run the HR Appraisal setup first.");
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$message = '';
$error = '';

// Handle add criteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_criteria'])) {
    $name = trim($_POST['criteria_name']);
    $code = strtoupper(trim($_POST['criteria_code']));
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $weightage = floatval($_POST['weightage']);
    $maxRating = intval($_POST['max_rating']) ?: 5;
    $sortOrder = intval($_POST['sort_order']);

    if ($name && $category) {
        try {
            $pdo->prepare("
                INSERT INTO appraisal_criteria
                (criteria_name, criteria_code, category, description, weightage, max_rating, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $code, $category, $description, $weightage, $maxRating, $sortOrder]);
            $message = "Criteria added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding criteria";
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_criteria'])) {
    $id = intval($_POST['criteria_id']);
    $weightage = floatval($_POST['weightage']);
    $sortOrder = intval($_POST['sort_order']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $pdo->prepare("
        UPDATE appraisal_criteria
        SET weightage = ?, sort_order = ?, is_active = ?
        WHERE id = ?
    ")->execute([$weightage, $sortOrder, $isActive, $id]);

    $message = "Criteria updated!";
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Check if used in any ratings
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM appraisal_ratings WHERE criteria_id = ?");
    $checkStmt->execute([$_GET['delete']]);
    if ($checkStmt->fetchColumn() > 0) {
        $error = "Cannot delete criteria that has been used in appraisals. Deactivate it instead.";
    } else {
        $pdo->prepare("DELETE FROM appraisal_criteria WHERE id = ?")->execute([$_GET['delete']]);
        $message = "Criteria deleted!";
    }
}

// Fetch criteria grouped by category
$criteria = $pdo->query("
    SELECT * FROM appraisal_criteria ORDER BY sort_order, criteria_name
")->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$byCategory = [];
foreach ($criteria as $c) {
    $cat = $c['category'] ?: 'Other';
    if (!isset($byCategory[$cat])) {
        $byCategory[$cat] = [];
    }
    $byCategory[$cat][] = $c;
}

// Calculate total weightage
$totalWeight = array_sum(array_column(array_filter($criteria, fn($c) => $c['is_active']), 'weightage'));

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appraisal Criteria</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .criteria-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        .criteria-list {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-section {
            margin-bottom: 25px;
        }
        .category-header {
            background: #f8f9fa;
            padding: 10px 15px;
            margin: 0 0 15px 0;
            border-left: 4px solid #3498db;
            font-weight: bold;
        }
        .category-header.Performance { border-left-color: #28a745; }
        .category-header.Competency { border-left-color: #007bff; }
        .category-header.Behavior { border-left-color: #ffc107; }
        .category-header.Development { border-left-color: #17a2b8; }
        .category-header.Goal { border-left-color: #6f42c1; }
        .criteria-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
            background: white;
        }
        .criteria-item.inactive {
            opacity: 0.5;
            background: #f8f9fa;
        }
        .criteria-info {
            flex: 1;
        }
        .criteria-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        .criteria-name .code {
            font-size: 0.85em;
            color: #666;
            margin-left: 5px;
        }
        .criteria-desc {
            font-size: 0.85em;
            color: #666;
        }
        .criteria-weight {
            font-size: 1.2em;
            font-weight: bold;
            color: #3498db;
            margin: 0 20px;
            min-width: 60px;
            text-align: center;
        }
        .criteria-actions {
            display: flex;
            gap: 5px;
        }
        .add-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        .add-form h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .weight-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .weight-summary .total {
            font-size: 1.8em;
            font-weight: bold;
        }
        .weight-warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .edit-form {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .edit-form.active {
            display: block;
        }
        @media (max-width: 900px) {
            .criteria-container {
                grid-template-columns: 1fr;
            }
            .add-form {
                position: static;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Appraisal Criteria</h1>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Weight Summary -->
    <div class="weight-summary">
        <div>
            <strong>Total Weightage</strong><br>
            <span style="opacity: 0.9; font-size: 0.9em;">Active criteria only</span>
        </div>
        <div class="total"><?= $totalWeight ?>%</div>
    </div>

    <?php if ($totalWeight != 100): ?>
    <div class="weight-warning">
        Total weightage should equal 100%. Current total: <?= $totalWeight ?>%
    </div>
    <?php endif; ?>

    <div class="criteria-container">
        <!-- Criteria List -->
        <div class="criteria-list">
            <?php foreach ($byCategory as $category => $items): ?>
            <div class="category-section">
                <h4 class="category-header <?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></h4>

                <?php foreach ($items as $c): ?>
                <div class="criteria-item <?= $c['is_active'] ? '' : 'inactive' ?>">
                    <div class="criteria-info">
                        <div class="criteria-name">
                            <?= htmlspecialchars($c['criteria_name']) ?>
                            <?php if ($c['criteria_code']): ?>
                            <span class="code">[<?= htmlspecialchars($c['criteria_code']) ?>]</span>
                            <?php endif; ?>
                            <?php if (!$c['is_active']): ?>
                            <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 5px;">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <div class="criteria-desc"><?= htmlspecialchars($c['description']) ?></div>
                    </div>
                    <div class="criteria-weight"><?= $c['weightage'] ?>%</div>
                    <div class="criteria-actions">
                        <button class="btn btn-secondary btn-sm" onclick="toggleEdit(<?= $c['id'] ?>)">Edit</button>
                        <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this criteria?')">Delete</a>
                    </div>
                </div>
                <form method="post" class="edit-form" id="edit-<?= $c['id'] ?>">
                    <input type="hidden" name="criteria_id" value="<?= $c['id'] ?>">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div>
                            <label>Weightage %</label>
                            <input type="number" name="weightage" value="<?= $c['weightage'] ?>" step="0.5" min="0" max="100" style="width: 80px;">
                        </div>
                        <div>
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $c['sort_order'] ?>" style="width: 60px;">
                        </div>
                        <div>
                            <label><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>> Active</label>
                        </div>
                        <button type="submit" name="update_criteria" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit(<?= $c['id'] ?>)">Cancel</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add Form -->
        <div class="add-form">
            <h3>Add New Criteria</h3>
            <form method="post">
                <div class="form-group">
                    <label>Criteria Name *</label>
                    <input type="text" name="criteria_name" required placeholder="e.g., Team Leadership">
                </div>
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="criteria_code" placeholder="e.g., TL" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">-- Select --</option>
                        <option value="Performance">Performance</option>
                        <option value="Competency">Competency</option>
                        <option value="Goal">Goal</option>
                        <option value="Behavior">Behavior</option>
                        <option value="Development">Development</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Weightage (%)</label>
                    <input type="number" name="weightage" step="0.5" min="0" max="100" value="5">
                </div>
                <div class="form-group">
                    <label>Max Rating</label>
                    <select name="max_rating">
                        <option value="5" selected>5 (1-5 scale)</option>
                        <option value="10">10 (1-10 scale)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0">
                </div>
                <button type="submit" name="add_criteria" class="btn btn-success" style="width: 100%;">
                    Add Criteria
                </button>
            </form>

            <div style="margin-top: 20px;">
                <a href="appraisal_cycles.php" class="btn btn-secondary" style="width: 100%;">Back to Cycles</a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEdit(id) {
    const form = document.getElementById('edit-' + id);
    form.classList.toggle('active');
}
</script>

</body>
</html>
