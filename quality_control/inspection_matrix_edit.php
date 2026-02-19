<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$part_id = isset($_GET['part_id']) ? trim($_GET['part_id']) : '';
if (!$part_id) {
    header('Location: inspection_matrix.php');
    exit;
}

$success_msg = '';
$error_msg = '';

// Get Part ID info
try {
    $stmt = $pdo->prepare("SELECT * FROM part_id_series WHERE part_id = ?");
    $stmt->execute([$part_id]);
    $pidInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pidInfo) {
        header('Location: inspection_matrix.php');
        exit;
    }
    // Count parts under this Part ID
    $partCount = $pdo->prepare("SELECT COUNT(*) FROM part_master WHERE part_id = ? AND status = 'active'");
    $partCount->execute([$part_id]);
    $partCount = $partCount->fetchColumn();
} catch (Exception $e) {
    header('Location: inspection_matrix.php');
    exit;
}

$stages = [
    'incoming' => ['label' => 'Incoming Inspection', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'desc' => 'When receiving goods from suppliers (PO receipt)'],
    'work_order' => ['label' => 'Work Order Inspection', 'color' => '#22c55e', 'bg' => '#f0fdf4', 'desc' => 'During manufacturing / in-process quality check'],
    'so_release' => ['label' => 'SO Release Inspection', 'color' => '#eab308', 'bg' => '#fefce8', 'desc' => 'Before releasing goods against a Sales Order'],
    'final_inspection' => ['label' => 'Final Inspection', 'color' => '#ec4899', 'bg' => '#fdf2f8', 'desc' => 'Final quality check before dispatch/delivery'],
];

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM qc_part_inspection_matrix WHERE part_id = ?")->execute([$part_id]);

            $insertStmt = $pdo->prepare("INSERT INTO qc_part_inspection_matrix (part_id, checkpoint_id, stage) VALUES (?, ?, ?)");
            $count = 0;
            foreach ($stages as $stageKey => $stageInfo) {
                $fieldName = 'checks_' . $stageKey;
                if (isset($_POST[$fieldName]) && is_array($_POST[$fieldName])) {
                    foreach ($_POST[$fieldName] as $checkpointId) {
                        $insertStmt->execute([$part_id, (int)$checkpointId, $stageKey]);
                        $count++;
                    }
                }
            }
            $pdo->commit();
            $success_msg = "Saved $count checkpoint(s) across all stages for Part ID: $part_id.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Error saving: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'copy_from') {
        $source = $_POST['source_part_id'] ?? '';
        if ($source) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM qc_part_inspection_matrix WHERE part_id = ?")->execute([$part_id]);
                $pdo->prepare("INSERT INTO qc_part_inspection_matrix (part_id, checkpoint_id, stage) SELECT ?, checkpoint_id, stage FROM qc_part_inspection_matrix WHERE part_id = ?")->execute([$part_id, $source]);
                $pdo->commit();
                $success_msg = "Copied inspection matrix from $source to $part_id.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Error copying: " . $e->getMessage();
            }
        }
    }
}

// Get all active checkpoints grouped by category
try {
    $checkpoints = $pdo->query("SELECT * FROM qc_inspection_checkpoints WHERE is_active = 1 ORDER BY category, sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $checkpoints = [];
}

// Get current matrix for this part_id
$currentMatrix = [];
try {
    $stmt = $pdo->prepare("SELECT checkpoint_id, stage FROM qc_part_inspection_matrix WHERE part_id = ?");
    $stmt->execute([$part_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $currentMatrix[$row['stage']][$row['checkpoint_id']] = true;
    }
} catch (Exception $e) {}

// Configured Part IDs for "copy from"
try {
    $configuredParts = $pdo->query("SELECT DISTINCT m.part_id, ps.description FROM qc_part_inspection_matrix m LEFT JOIN part_id_series ps ON m.part_id = ps.part_id ORDER BY m.part_id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $configuredParts = [];
}

// Group checkpoints by category
$grouped = [];
foreach ($checkpoints as $cp) {
    $grouped[$cp['category']][] = $cp;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Inspection Matrix - <?= htmlspecialchars($part_id) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .part-info {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            align-items: center;
        }
        .part-info .field-label { font-size: 0.8em; color: #7f8c8d; text-transform: uppercase; font-weight: 600; }
        .part-info .field-value { font-size: 1.1em; color: #2c3e50; font-weight: 600; }
        .part-id-big {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.4em;
            background: #667eea;
            color: white;
            letter-spacing: 1px;
        }

        .stage-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 0;
            flex-wrap: wrap;
        }
        .stage-tab {
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9em;
            border: 2px solid #ddd;
            border-bottom: none;
            background: #f8f9fa;
            color: #666;
            transition: all 0.2s;
        }
        .stage-tab.active {
            background: white;
            border-color: currentColor;
            border-bottom-color: white;
            position: relative;
            z-index: 1;
        }
        .stage-tab .tab-count {
            display: inline-block;
            min-width: 20px;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.85em;
            margin-left: 5px;
            background: #e2e8f0;
            color: #475569;
        }
        .stage-tab.active .tab-count { background: currentColor; color: white; }

        .stage-panel {
            display: none;
            background: white;
            border-radius: 0 10px 10px 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 2px solid #ddd;
            margin-top: -2px;
        }
        .stage-panel.active { display: block; }

        .stage-desc {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .stage-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .category-group { margin-bottom: 20px; }
        .category-title {
            font-weight: 600;
            color: #475569;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 2px solid #e2e8f0;
        }

        .checkpoint-row {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 3px;
            transition: background 0.15s;
        }
        .checkpoint-row:hover { background: #f0f4ff; }
        .checkpoint-row label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            flex: 1;
        }
        .checkpoint-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        .checkpoint-name { font-weight: 500; color: #2c3e50; }
        .checkpoint-spec { font-size: 0.85em; color: #7f8c8d; margin-left: 28px; }

        .save-bar {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px 25px;
            border-top: 2px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            border-radius: 10px 10px 0 0;
            margin-top: 20px;
        }

        .alert-success {
            background: #d1fae5; border: 1px solid #10b981; color: #065f46;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }

        .copy-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        body.dark .part-info, body.dark .stage-panel, body.dark .save-bar { background: #2c3e50; }
        body.dark .checkpoint-name { color: #ecf0f1; }
        body.dark .checkpoint-row:hover { background: #34495e; }
        body.dark .stage-tab { background: #34495e; color: #aaa; border-color: #4a5568; }
        body.dark .stage-tab.active { background: #2c3e50; }
        body.dark .stage-panel { border-color: #4a5568; }
    </style>
</head>
<body>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>Inspection Matrix - <span class="part-id-big"><?= htmlspecialchars($part_id) ?></span></h1>
            <p style="color: #666; margin: 5px 0 0;">Configure inspection checkpoints for this Part ID</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="inspection_matrix.php" class="btn btn-secondary">Back to Matrix</a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Part ID Info -->
    <div class="part-info">
        <div>
            <div class="field-label">Part ID</div>
            <div class="field-value"><?= htmlspecialchars($pidInfo['part_id']) ?></div>
        </div>
        <div>
            <div class="field-label">Description</div>
            <div class="field-value"><?= htmlspecialchars($pidInfo['description'] ?: '-') ?></div>
        </div>
        <div>
            <div class="field-label">Series Prefix</div>
            <div class="field-value"><?= htmlspecialchars($pidInfo['series_prefix']) ?></div>
        </div>
        <div>
            <div class="field-label">Active Parts</div>
            <div class="field-value" style="color: #3498db;"><?= $partCount ?></div>
        </div>
    </div>

    <!-- Copy From Section -->
    <?php if (!empty($configuredParts)): ?>
    <div class="copy-section">
        <span style="font-weight: 600; color: #0369a1;">Copy from another Part ID:</span>
        <form method="post" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="copy_from">
            <select name="source_part_id" required style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; min-width: 250px;">
                <option value="">Select Part ID...</option>
                <?php foreach ($configuredParts as $cp):
                    if ($cp['part_id'] === $part_id) continue;
                ?>
                    <option value="<?= htmlspecialchars($cp['part_id']) ?>">
                        <?= htmlspecialchars($cp['part_id']) ?> - <?= htmlspecialchars($cp['description'] ?: 'N/A') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('This will replace current checkpoints. Continue?')">Copy</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (empty($checkpoints)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #7f8c8d; background: white; border-radius: 10px;">
            <h3>No Checkpoints Available</h3>
            <p>Run <a href="setup_inspection_matrix.php">Setup</a> first, or <a href="inspection_checkpoints.php">add checkpoints</a> manually.</p>
        </div>
    <?php else: ?>
        <form method="post" id="matrixForm">
            <input type="hidden" name="action" value="save">

            <!-- Stage Tabs -->
            <div class="stage-tabs">
                <?php $first = true; foreach ($stages as $stageKey => $stageInfo):
                    $stageCount = isset($currentMatrix[$stageKey]) ? count($currentMatrix[$stageKey]) : 0;
                ?>
                    <div class="stage-tab <?= $first ? 'active' : '' ?>"
                         data-stage="<?= $stageKey ?>"
                         style="<?= $first ? "color: {$stageInfo['color']}; border-color: {$stageInfo['color']};" : '' ?>"
                         data-color="<?= $stageInfo['color'] ?>"
                         onclick="switchTab('<?= $stageKey ?>')">
                        <?= $stageInfo['label'] ?>
                        <span class="tab-count" id="count_<?= $stageKey ?>"><?= $stageCount ?></span>
                    </div>
                <?php $first = false; endforeach; ?>
            </div>

            <!-- Stage Panels -->
            <?php $first = true; foreach ($stages as $stageKey => $stageInfo): ?>
                <div class="stage-panel <?= $first ? 'active' : '' ?>" id="panel_<?= $stageKey ?>" style="<?= $first ? "border-color: {$stageInfo['color']};" : '' ?>">
                    <div class="stage-desc"><?= $stageInfo['desc'] ?></div>

                    <div class="stage-actions">
                        <button type="button" class="btn btn-sm btn-primary" onclick="selectAll('<?= $stageKey ?>')">Select All</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll('<?= $stageKey ?>')">Deselect All</button>
                        <span style="color: #666; font-size: 0.9em; margin-left: 10px;">
                            <span id="selected_<?= $stageKey ?>"><?= isset($currentMatrix[$stageKey]) ? count($currentMatrix[$stageKey]) : 0 ?></span> / <?= count($checkpoints) ?> selected
                        </span>
                    </div>

                    <?php foreach ($grouped as $category => $catCheckpoints): ?>
                        <div class="category-group">
                            <div class="category-title"><?= htmlspecialchars($category) ?></div>
                            <?php foreach ($catCheckpoints as $cp):
                                $isChecked = isset($currentMatrix[$stageKey][$cp['id']]);
                            ?>
                                <div class="checkpoint-row">
                                    <label>
                                        <input type="checkbox"
                                               name="checks_<?= $stageKey ?>[]"
                                               value="<?= $cp['id'] ?>"
                                               <?= $isChecked ? 'checked' : '' ?>
                                               onchange="updateCount('<?= $stageKey ?>')">
                                        <span class="checkpoint-name"><?= htmlspecialchars($cp['checkpoint_name']) ?></span>
                                    </label>
                                </div>
                                <?php if ($cp['specification']): ?>
                                    <div class="checkpoint-spec"><?= htmlspecialchars($cp['specification']) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php $first = false; endforeach; ?>

            <!-- Save Bar -->
            <div class="save-bar">
                <div style="color: #666; font-size: 0.9em;">
                    Total selected: <strong id="totalCount"><?= array_sum(array_map('count', $currentMatrix)) ?></strong> checkpoints across all stages
                    &nbsp;|&nbsp; Applies to <strong><?= $partCount ?></strong> parts under <strong><?= htmlspecialchars($part_id) ?></strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="inspection_matrix.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">Save Matrix</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function switchTab(stage) {
    document.querySelectorAll('.stage-panel').forEach(p => {
        p.classList.remove('active');
        p.style.borderColor = '#ddd';
    });
    document.querySelectorAll('.stage-tab').forEach(t => {
        t.classList.remove('active');
        t.style.color = '#666';
        t.style.borderColor = '#ddd';
    });

    var panel = document.getElementById('panel_' + stage);
    var tab = document.querySelector('.stage-tab[data-stage="' + stage + '"]');
    var color = tab.dataset.color;

    panel.classList.add('active');
    panel.style.borderColor = color;
    tab.classList.add('active');
    tab.style.color = color;
    tab.style.borderColor = color;
}

function selectAll(stage) {
    document.querySelectorAll('#panel_' + stage + ' input[type="checkbox"]').forEach(cb => cb.checked = true);
    updateCount(stage);
}

function deselectAll(stage) {
    document.querySelectorAll('#panel_' + stage + ' input[type="checkbox"]').forEach(cb => cb.checked = false);
    updateCount(stage);
}

function updateCount(stage) {
    var checked = document.querySelectorAll('#panel_' + stage + ' input[type="checkbox"]:checked').length;
    document.getElementById('count_' + stage).textContent = checked;
    document.getElementById('selected_' + stage).textContent = checked;

    var total = 0;
    <?php foreach (array_keys($stages) as $sk): ?>
    total += document.querySelectorAll('#panel_<?= $sk ?> input[type="checkbox"]:checked').length;
    <?php endforeach; ?>
    document.getElementById('totalCount').textContent = total;
}
</script>

</body>
</html>
