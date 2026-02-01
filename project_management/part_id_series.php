<?php
/**
 * Part ID Series Generator
 * Manage part ID series for generating consistent part numbers
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";
requireLogin();

showModal();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_id = trim($_POST['part_id'] ?? '');
    $series_prefix = trim($_POST['series_prefix'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $current_number = (int)($_POST['current_number'] ?? 0);
    $number_padding = (int)($_POST['number_padding'] ?? 4);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $edit_id = $_POST['edit_id'] ?? 0;

    if (empty($part_id)) {
        $error = "Part ID is required.";
    } elseif (empty($series_prefix)) {
        $error = "Series Prefix is required.";
    } else {
        try {
            if ($edit_id) {
                // Update existing
                $stmt = $pdo->prepare("
                    UPDATE part_id_series
                    SET part_id = ?, series_prefix = ?, description = ?,
                        current_number = ?, number_padding = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$part_id, $series_prefix, $description, $current_number, $number_padding, $is_active, $edit_id]);
                setModal("Success", "Part ID Series updated successfully.");
            } else {
                // Insert new
                $stmt = $pdo->prepare("
                    INSERT INTO part_id_series (part_id, series_prefix, description, current_number, number_padding, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$part_id, $series_prefix, $description, $current_number, $number_padding, $is_active]);
                setModal("Success", "Part ID Series created successfully.");
            }
            header("Location: part_id_series.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "Part ID '$part_id' already exists.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle delete
if ($action === 'delete' && $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM part_id_series WHERE id = ?");
        $stmt->execute([$id]);
        setModal("Success", "Part ID Series deleted successfully.");
        header("Location: part_id_series.php");
        exit;
    } catch (PDOException $e) {
        $error = "Cannot delete: " . $e->getMessage();
    }
}

// Handle generate next number (AJAX endpoint)
if ($action === 'generate' && $id) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT * FROM part_id_series WHERE id = ?");
        $stmt->execute([$id]);
        $series = $stmt->fetch();

        if ($series) {
            $nextNumber = $series['current_number'] + 1;
            $paddedNumber = str_pad($nextNumber, $series['number_padding'], '0', STR_PAD_LEFT);
            $generatedPartNo = $series['series_prefix'] . $paddedNumber;

            // Update the current number
            $pdo->prepare("UPDATE part_id_series SET current_number = ? WHERE id = ?")
                ->execute([$nextNumber, $id]);

            echo json_encode([
                'success' => true,
                'part_no' => $generatedPartNo,
                'next_number' => $nextNumber
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Series not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch edit data
$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM part_id_series WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

// Fetch all series
$series_list = [];
try {
    $series_list = $pdo->query("
        SELECT * FROM part_id_series
        ORDER BY is_active DESC, part_id ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = "Table not found. Please run setup first: <a href='/admin/setup_part_id_series.php'>Setup Part ID Series</a>";
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Part ID Series Generator - Product Engineering</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; }
        .page-header p { margin: 5px 0 0; color: #666; }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group .hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-inactive {
            background: #f5f5f5;
            color: #757575;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .preview-box {
            background: #e3f2fd;
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-top: 10px;
        }
        .preview-box .label {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }
        .preview-box .value {
            font-size: 1.4em;
            font-weight: bold;
            color: #667eea;
            font-family: monospace;
        }

        .generate-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .generated-number {
            background: #e8f5e9;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.2em;
            font-weight: bold;
            color: #2e7d32;
            display: inline-block;
            margin-top: 10px;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        body.dark .card { background: #2c3e50; }
        body.dark .card h3 { color: #ecf0f1; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .form-group input, body.dark .form-group textarea {
            background: #34495e;
            border-color: #34495e;
            color: #ecf0f1;
        }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .preview-box { background: #34495e; border-color: #667eea; }
    </style>
</head>
<body>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Part ID Series Generator</h1>
            <p>Manage part number series for consistent part numbering</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card">
        <h3><?= $editData ? 'Edit Part ID Series' : 'Add New Part ID Series' ?></h3>
        <form method="post">
            <?php if ($editData): ?>
                <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Part ID *</label>
                    <input type="text" name="part_id" id="part_id"
                           value="<?= htmlspecialchars($editData['part_id'] ?? '') ?>"
                           required placeholder="e.g., RAW, FG, WIP"
                           style="text-transform: uppercase;"
                           oninput="this.value = this.value.toUpperCase(); updatePreview();">
                    <div class="hint">Unique identifier for this part category</div>
                </div>

                <div class="form-group">
                    <label>Series Prefix *</label>
                    <input type="text" name="series_prefix" id="series_prefix"
                           value="<?= htmlspecialchars($editData['series_prefix'] ?? '') ?>"
                           required placeholder="e.g., RAW-, FG-"
                           oninput="updatePreview();">
                    <div class="hint">Prefix for generated part numbers</div>
                </div>

                <div class="form-group">
                    <label>Number Padding</label>
                    <input type="number" name="number_padding" id="number_padding"
                           value="<?= htmlspecialchars($editData['number_padding'] ?? 4) ?>"
                           min="1" max="10"
                           oninput="updatePreview();">
                    <div class="hint">Minimum digits (zero-padded)</div>
                </div>

                <div class="form-group">
                    <label>Current Number</label>
                    <input type="number" name="current_number" id="current_number"
                           value="<?= htmlspecialchars($editData['current_number'] ?? 0) ?>"
                           min="0"
                           oninput="updatePreview();">
                    <div class="hint">Last used number in series</div>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Describe what this part series is used for..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active"
                           <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="is_active" style="margin: 0; cursor: pointer;">Active</label>
                </div>
            </div>

            <!-- Preview Box -->
            <div class="preview-box">
                <div class="label">Next Generated Part Number Preview</div>
                <div class="value" id="preview-number">-</div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><?= $editData ? 'Update Series' : 'Add Series' ?></button>
                <?php if ($editData): ?>
                    <a href="part_id_series.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Series List -->
    <div class="card">
        <h3>Part ID Series</h3>

        <?php if (empty($series_list)): ?>
            <p style="text-align: center; padding: 30px; color: #666;">
                No part ID series defined yet. Add your first series above.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Part ID</th>
                        <th>Series Prefix</th>
                        <th>Current #</th>
                        <th>Next Number</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($series_list as $s):
                        $nextNum = $s['current_number'] + 1;
                        $paddedNum = str_pad($nextNum, $s['number_padding'], '0', STR_PAD_LEFT);
                        $nextPartNo = $s['series_prefix'] . $paddedNum;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['part_id']) ?></strong></td>
                        <td><code><?= htmlspecialchars($s['series_prefix']) ?></code></td>
                        <td><?= $s['current_number'] ?></td>
                        <td>
                            <code style="background: #e8f5e9; padding: 4px 8px; border-radius: 4px; color: #2e7d32;">
                                <?= htmlspecialchars($nextPartNo) ?>
                            </code>
                        </td>
                        <td><?= htmlspecialchars(substr($s['description'] ?? '', 0, 50)) ?><?= strlen($s['description'] ?? '') > 50 ? '...' : '' ?></td>
                        <td>
                            <span class="<?= $s['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button type="button" class="btn btn-primary btn-sm generate-btn"
                                        onclick="generateNumber(<?= $s['id'] ?>, this)"
                                        <?= !$s['is_active'] ? 'disabled style="opacity:0.5"' : '' ?>>
                                    Generate
                                </button>
                                <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this series? This cannot be undone.')">Delete</a>
                            </div>
                            <div id="generated-<?= $s['id'] ?>" style="margin-top: 8px;"></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Help Section -->
    <div class="card">
        <h3>How to Use Part ID Series</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div>
                <h4 style="margin: 0 0 10px 0; color: #667eea;">1. Create a Series</h4>
                <p style="margin: 0; color: #666;">
                    Define a unique Part ID (e.g., "RAW" for raw materials) and a series prefix
                    (e.g., "RAW-"). The prefix will be used when generating part numbers.
                </p>
            </div>
            <div>
                <h4 style="margin: 0 0 10px 0; color: #667eea;">2. Generate Numbers</h4>
                <p style="margin: 0; color: #666;">
                    Click "Generate" to get the next sequential part number. The system
                    automatically increments the counter after each generation.
                </p>
            </div>
            <div>
                <h4 style="margin: 0 0 10px 0; color: #667eea;">3. Use in Part Master</h4>
                <p style="margin: 0; color: #666;">
                    Use the generated part numbers when creating new parts in the Part Master
                    to maintain consistent numbering across your inventory.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function updatePreview() {
    const prefix = document.getElementById('series_prefix').value || '';
    const padding = parseInt(document.getElementById('number_padding').value) || 4;
    const current = parseInt(document.getElementById('current_number').value) || 0;
    const next = current + 1;
    const paddedNum = String(next).padStart(padding, '0');
    document.getElementById('preview-number').textContent = prefix + paddedNum;
}

function generateNumber(id, btn) {
    btn.disabled = true;
    btn.textContent = 'Generating...';

    fetch('?action=generate&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('generated-' + id);
                container.innerHTML = '<div class="generated-number">' + data.part_no + '</div>' +
                                      '<button onclick="copyToClipboard(\'' + data.part_no + '\')" ' +
                                      'class="btn btn-secondary btn-sm" style="margin-left: 8px;">Copy</button>';
                // Update the table
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => {
            alert('Error generating number');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Generate';
        });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied: ' + text);
    });
}

// Initialize preview on page load
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

</body>
</html>
