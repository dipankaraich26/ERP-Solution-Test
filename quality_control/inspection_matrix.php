<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$success_msg = '';
$error_msg = '';

// Handle bulk copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_copy') {
    $source = $_POST['source_part_id'] ?? '';
    $targets = $_POST['target_part_ids'] ?? [];
    if ($source && !empty($targets)) {
        try {
            $pdo->beginTransaction();
            $srcStmt = $pdo->prepare("SELECT checkpoint_id, stage FROM qc_part_inspection_matrix WHERE part_id = ?");
            $srcStmt->execute([$source]);
            $srcRows = $srcStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $pdo->prepare("INSERT IGNORE INTO qc_part_inspection_matrix (part_id, checkpoint_id, stage) VALUES (?, ?, ?)");
            foreach ($targets as $target) {
                $pdo->prepare("DELETE FROM qc_part_inspection_matrix WHERE part_id = ?")->execute([$target]);
                foreach ($srcRows as $row) {
                    $insertStmt->execute([$target, $row['checkpoint_id'], $row['stage']]);
                }
            }
            $pdo->commit();
            $success_msg = "Copied " . count($srcRows) . " checkpoint(s) to " . count($targets) . " Part ID(s).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Error copying matrix: " . $e->getMessage();
        }
    }
}

// Get all Part ID series
try {
    $partIdSeries = $pdo->query("
        SELECT ps.part_id, ps.description, ps.series_prefix, ps.is_active,
            (SELECT COUNT(*) FROM part_master WHERE part_id = ps.part_id AND status = 'active') as part_count
        FROM part_id_series ps
        ORDER BY ps.part_id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $partIdSeries = [];
    $error_msg = "Error loading Part IDs: " . $e->getMessage();
}

// Get matrix counts per part_id per stage
$matrixCounts = [];
try {
    $rows = $pdo->query("
        SELECT part_id, stage, COUNT(*) as cnt
        FROM qc_part_inspection_matrix
        GROUP BY part_id, stage
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $matrixCounts[$row['part_id']][$row['stage']] = $row['cnt'];
    }
} catch (Exception $e) {}

// Stats
try {
    $totalPartIds = count($partIdSeries);
    $configuredIds = $pdo->query("SELECT COUNT(DISTINCT part_id) FROM qc_part_inspection_matrix")->fetchColumn();
    $totalCheckpoints = $pdo->query("SELECT COUNT(*) FROM qc_inspection_checkpoints WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    $totalPartIds = $configuredIds = $totalCheckpoints = 0;
}

// Configured Part IDs for copy dropdown
$configuredList = [];
try {
    $configuredList = $pdo->query("
        SELECT DISTINCT m.part_id, ps.description
        FROM qc_part_inspection_matrix m
        LEFT JOIN part_id_series ps ON m.part_id = ps.part_id
        ORDER BY m.part_id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Part Inspection Matrix - QC</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            text-align: center;
        }
        .stat-card .stat-value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .stat-card .stat-label { color: #7f8c8d; font-size: 0.85em; margin-top: 3px; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .matrix-table th, .matrix-table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .matrix-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
        }
        .matrix-table td:first-child, .matrix-table td:nth-child(2), .matrix-table td:nth-child(3) { text-align: left; }
        .matrix-table th:first-child, .matrix-table th:nth-child(2), .matrix-table th:nth-child(3) { text-align: left; }
        .matrix-table tr:hover { background: #f0f4ff; }

        .part-id-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1em;
            background: #667eea;
            color: white;
            letter-spacing: 0.5px;
        }

        .count-badge {
            display: inline-block;
            min-width: 30px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 600;
            text-align: center;
        }
        .count-badge.has-checks { background: #d1fae5; color: #065f46; }
        .count-badge.no-checks { background: #f3f4f6; color: #9ca3af; }

        .total-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95em;
        }
        .total-badge.configured { background: #d1fae5; color: #065f46; }
        .total-badge.not-configured { background: #fef3c7; color: #92400e; }

        .alert-success {
            background: #d1fae5; border: 1px solid #10b981; color: #065f46;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }

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
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-box h3 { margin: 0 0 20px; color: #2c3e50; }

        body.dark .stat-card, body.dark .matrix-table, body.dark .modal-box { background: #2c3e50; }
        body.dark .stat-card .stat-value, body.dark .modal-box h3 { color: #ecf0f1; }
        body.dark .matrix-table th { background: #34495e; color: #ecf0f1; }
        body.dark .matrix-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>Part Inspection Matrix</h1>
            <p style="color: #666; margin: 5px 0 0;">Configure inspection checkpoints per Part ID category</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">QC Dashboard</a>
            <a href="inspection_checkpoints.php" class="btn btn-secondary">Manage Checkpoints</a>
            <?php if (!empty($configuredList)): ?>
                <button onclick="openBulkCopyModal()" class="btn btn-primary">Copy Matrix</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-value"><?= $totalPartIds ?></div>
            <div class="stat-label">Total Part IDs</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $configuredIds ?></div>
            <div class="stat-label">Configured</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?= $totalPartIds - $configuredIds ?></div>
            <div class="stat-label">Not Configured</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalCheckpoints ?></div>
            <div class="stat-label">Active Checkpoints</div>
        </div>
    </div>

    <!-- Matrix Table -->
    <?php if (empty($partIdSeries)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #7f8c8d; background: white; border-radius: 10px;">
            <h3>No Part IDs Found</h3>
            <p>Set up Part ID Series in <a href="../admin/setup_part_id_series.php">Admin Setup</a> first.</p>
        </div>
    <?php else: ?>
        <table class="matrix-table">
            <thead>
                <tr>
                    <th>Part ID</th>
                    <th>Description</th>
                    <th>Parts</th>
                    <th title="Incoming Inspection">Incoming</th>
                    <th title="Work Order Inspection">Work Order</th>
                    <th title="Sales Order Release">SO Release</th>
                    <th title="Final Inspection">Final</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partIdSeries as $pid):
                    $incoming = $matrixCounts[$pid['part_id']]['incoming'] ?? 0;
                    $wo = $matrixCounts[$pid['part_id']]['work_order'] ?? 0;
                    $so = $matrixCounts[$pid['part_id']]['so_release'] ?? 0;
                    $final = $matrixCounts[$pid['part_id']]['final_inspection'] ?? 0;
                    $total = $incoming + $wo + $so + $final;
                ?>
                <tr>
                    <td><span class="part-id-badge"><?= htmlspecialchars($pid['part_id']) ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($pid['description'] ?: '-') ?></strong>
                        <div style="font-size: 0.8em; color: #999;">Prefix: <?= htmlspecialchars($pid['series_prefix']) ?></div>
                    </td>
                    <td style="text-align: center;">
                        <span style="font-weight: 600; color: #3498db;"><?= $pid['part_count'] ?></span>
                    </td>
                    <td>
                        <span class="count-badge <?= $incoming > 0 ? 'has-checks' : 'no-checks' ?>"><?= $incoming ?></span>
                    </td>
                    <td>
                        <span class="count-badge <?= $wo > 0 ? 'has-checks' : 'no-checks' ?>"><?= $wo ?></span>
                    </td>
                    <td>
                        <span class="count-badge <?= $so > 0 ? 'has-checks' : 'no-checks' ?>"><?= $so ?></span>
                    </td>
                    <td>
                        <span class="count-badge <?= $final > 0 ? 'has-checks' : 'no-checks' ?>"><?= $final ?></span>
                    </td>
                    <td>
                        <span class="total-badge <?= $total > 0 ? 'configured' : 'not-configured' ?>">
                            <?= $total ?>
                        </span>
                    </td>
                    <td>
                        <a href="inspection_matrix_edit.php?part_id=<?= urlencode($pid['part_id']) ?>" class="btn btn-sm btn-primary">
                            <?= $total > 0 ? 'Edit' : 'Configure' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Bulk Copy Modal -->
<div class="modal-overlay" id="bulkCopyModal">
    <div class="modal-box">
        <h3>Copy Inspection Matrix</h3>
        <p style="color: #666; margin-bottom: 20px;">Copy all checkpoint assignments from one Part ID to others. This will <strong>replace</strong> existing configurations on target Part IDs.</p>
        <form method="post">
            <input type="hidden" name="action" value="bulk_copy">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Copy from (Source):</label>
                <select name="source_part_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="">Select source Part ID...</option>
                    <?php foreach ($configuredList as $cp): ?>
                        <option value="<?= htmlspecialchars($cp['part_id']) ?>">
                            <?= htmlspecialchars($cp['part_id']) ?> - <?= htmlspecialchars($cp['description'] ?: 'N/A') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Copy to (Targets):</label>
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 10px;">
                    <?php foreach ($partIdSeries as $pid): ?>
                        <label style="display: flex; align-items: center; padding: 6px 0; cursor: pointer;">
                            <input type="checkbox" name="target_part_ids[]" value="<?= htmlspecialchars($pid['part_id']) ?>" style="margin-right: 10px;">
                            <span class="part-id-badge" style="font-size: 0.85em; padding: 3px 10px;"><?= htmlspecialchars($pid['part_id']) ?></span>
                            <span style="color: #666; margin-left: 10px; font-size: 0.9em;"><?= htmlspecialchars($pid['description'] ?: '') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('bulkCopyModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="return confirm('This will replace existing inspection matrix on target Part IDs. Continue?')">Copy Matrix</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBulkCopyModal() {
    document.getElementById('bulkCopyModal').classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

</body>
</html>
