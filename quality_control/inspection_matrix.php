<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

$success_msg = '';
$error_msg = '';

// Handle bulk copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_copy') {
    $source = $_POST['source_part'] ?? '';
    $targets = $_POST['target_parts'] ?? [];
    if ($source && !empty($targets)) {
        try {
            $pdo->beginTransaction();
            // Get source matrix
            $srcStmt = $pdo->prepare("SELECT checkpoint_id, stage FROM qc_part_inspection_matrix WHERE part_no = ?");
            $srcStmt->execute([$source]);
            $srcRows = $srcStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $pdo->prepare("INSERT IGNORE INTO qc_part_inspection_matrix (part_no, checkpoint_id, stage) VALUES (?, ?, ?)");
            $copied = 0;
            foreach ($targets as $target) {
                // Clear existing for target
                $pdo->prepare("DELETE FROM qc_part_inspection_matrix WHERE part_no = ?")->execute([$target]);
                foreach ($srcRows as $row) {
                    $insertStmt->execute([$target, $row['checkpoint_id'], $row['stage']]);
                    $copied++;
                }
            }
            $pdo->commit();
            $success_msg = "Copied " . count($srcRows) . " checkpoint(s) to " . count($targets) . " part(s).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Error copying matrix: " . $e->getMessage();
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['part_category']) ? $_GET['part_category'] : '';
$filter_configured = isset($_GET['configured']) ? $_GET['configured'] : '';

$where = ["p.status = 'active'"];
$params = [];

if ($search) {
    $where[] = "(p.part_no LIKE ? OR p.part_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_category) {
    $where[] = "p.part_id = ?";
    $params[] = $filter_category;
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Stats
try {
    $totalParts = $pdo->query("SELECT COUNT(*) FROM part_master WHERE status = 'active'")->fetchColumn();
    $configuredParts = $pdo->query("SELECT COUNT(DISTINCT part_no) FROM qc_part_inspection_matrix")->fetchColumn();
    $totalCheckpoints = $pdo->query("SELECT COUNT(*) FROM qc_inspection_checkpoints WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    $totalParts = $configuredParts = $totalCheckpoints = 0;
}

// Get parts with matrix counts
try {
    $countParams = $params;
    $countSql = "SELECT COUNT(*) FROM part_master p $where_clause";

    if ($filter_configured === 'yes') {
        $countSql = "SELECT COUNT(DISTINCT p.part_no) FROM part_master p INNER JOIN qc_part_inspection_matrix m ON p.part_no = m.part_no $where_clause";
    } elseif ($filter_configured === 'no') {
        $countSql = "SELECT COUNT(*) FROM part_master p LEFT JOIN qc_part_inspection_matrix m ON p.part_no = m.part_no $where_clause AND m.id IS NULL";
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total_count = $countStmt->fetchColumn();
    $total_pages = ceil($total_count / $per_page);

    $sql = "
        SELECT p.part_no, p.part_name, p.part_id, p.category, p.uom,
            (SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE part_no = p.part_no AND stage = 'incoming') as incoming_count,
            (SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE part_no = p.part_no AND stage = 'work_order') as wo_count,
            (SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE part_no = p.part_no AND stage = 'so_release') as so_count,
            (SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE part_no = p.part_no AND stage = 'final_inspection') as final_count,
            (SELECT COUNT(*) FROM qc_part_inspection_matrix WHERE part_no = p.part_no) as total_checks
        FROM part_master p
    ";

    if ($filter_configured === 'yes') {
        $sql .= " INNER JOIN (SELECT DISTINCT part_no FROM qc_part_inspection_matrix) m ON p.part_no = m.part_no ";
    } elseif ($filter_configured === 'no') {
        $sql .= " LEFT JOIN (SELECT DISTINCT part_no FROM qc_part_inspection_matrix) m ON p.part_no = m.part_no ";
        $where_clause .= " AND m.part_no IS NULL";
    }

    $sql .= " $where_clause ORDER BY p.part_no LIMIT $per_page OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get part categories for filter
    $partCategories = $pdo->query("SELECT DISTINCT part_id FROM part_master WHERE status = 'active' AND part_id IS NOT NULL AND part_id != '' ORDER BY part_id")->fetchAll(PDO::FETCH_COLUMN);

    // Get configured parts for bulk copy source dropdown
    $configuredPartsList = $pdo->query("SELECT DISTINCT m.part_no, p.part_name FROM qc_part_inspection_matrix m LEFT JOIN part_master p ON m.part_no = p.part_no ORDER BY m.part_no")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $parts = [];
    $partCategories = [];
    $configuredPartsList = [];
    $total_count = 0;
    $total_pages = 0;
    $error_msg = "Error: " . $e->getMessage();
}

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
        .filter-section label { font-weight: 600; color: #495057; font-size: 0.9em; }
        .filter-section select, .filter-section input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: white;
            font-size: 0.9em;
        }

        .parts-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .parts-table th, .parts-table td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .parts-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
        }
        .parts-table td:first-child, .parts-table td:nth-child(2) { text-align: left; }
        .parts-table th:first-child, .parts-table th:nth-child(2) { text-align: left; }
        .parts-table tr:hover { background: #f0f4ff; }

        .count-badge {
            display: inline-block;
            min-width: 28px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            text-align: center;
        }
        .count-badge.has-checks { background: #d1fae5; color: #065f46; }
        .count-badge.no-checks { background: #f3f4f6; color: #9ca3af; }

        .category-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
            background: #e8eaf6;
            color: #3f51b5;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 25px;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
        }
        .pagination a { background: #f8f9fa; color: #495057; }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination span { background: #667eea; color: white; }

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
            width: 550px;
            max-width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-box h3 { margin: 0 0 20px; color: #2c3e50; }

        .alert-success {
            background: #d1fae5; border: 1px solid #10b981; color: #065f46;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;
            padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
        }

        body.dark .stat-card, body.dark .parts-table, body.dark .modal-box { background: #2c3e50; }
        body.dark .stat-card .stat-value, body.dark .modal-box h3 { color: #ecf0f1; }
        body.dark .parts-table th { background: #34495e; color: #ecf0f1; }
        body.dark .parts-table tr:hover { background: #34495e; }
        body.dark .filter-section { background: #34495e; }
    </style>
</head>
<body>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>Part Inspection Matrix</h1>
            <p style="color: #666; margin: 5px 0 0;">Configure inspection checkpoints for each part by stage</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">QC Dashboard</a>
            <a href="inspection_checkpoints.php" class="btn btn-secondary">Manage Checkpoints</a>
            <?php if (!empty($configuredPartsList)): ?>
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
            <div class="stat-value"><?= $totalParts ?></div>
            <div class="stat-label">Total Active Parts</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $configuredParts ?></div>
            <div class="stat-label">Parts Configured</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?= $totalParts - $configuredParts ?></div>
            <div class="stat-label">Not Configured</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalCheckpoints ?></div>
            <div class="stat-label">Active Checkpoints</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; width: 100%;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Part No or Name..." style="width: 180px;">
            </div>
            <div>
                <label>Part Category:</label>
                <select name="part_category" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($partCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Matrix Status:</label>
                <select name="configured" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="yes" <?= $filter_configured === 'yes' ? 'selected' : '' ?>>Configured</option>
                    <option value="no" <?= $filter_configured === 'no' ? 'selected' : '' ?>>Not Configured</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($search || $filter_category || $filter_configured): ?>
                <a href="inspection_matrix.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
            <div style="margin-left: auto; color: #666; font-size: 0.9em;">
                <?= $total_count ?> part<?= $total_count != 1 ? 's' : '' ?>
            </div>
        </form>
    </div>

    <!-- Parts Table -->
    <?php if (empty($parts)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #7f8c8d; background: white; border-radius: 10px;">
            <h3>No Parts Found</h3>
            <p>Add parts in <a href="../part_master/list.php">Part Master</a> first, or adjust your filters.</p>
        </div>
    <?php else: ?>
        <table class="parts-table">
            <thead>
                <tr>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Type</th>
                    <th title="Incoming Inspection">Incoming</th>
                    <th title="Work Order Inspection">Work Order</th>
                    <th title="Sales Order Release">SO Release</th>
                    <th title="Final Inspection">Final</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parts as $part): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($part['part_no']) ?></strong></td>
                    <td><?= htmlspecialchars($part['part_name'] ?: '-') ?></td>
                    <td><span class="category-tag"><?= htmlspecialchars($part['part_id'] ?: $part['category'] ?: '-') ?></span></td>
                    <td>
                        <span class="count-badge <?= $part['incoming_count'] > 0 ? 'has-checks' : 'no-checks' ?>">
                            <?= $part['incoming_count'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="count-badge <?= $part['wo_count'] > 0 ? 'has-checks' : 'no-checks' ?>">
                            <?= $part['wo_count'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="count-badge <?= $part['so_count'] > 0 ? 'has-checks' : 'no-checks' ?>">
                            <?= $part['so_count'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="count-badge <?= $part['final_count'] > 0 ? 'has-checks' : 'no-checks' ?>">
                            <?= $part['final_count'] ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color: <?= $part['total_checks'] > 0 ? '#27ae60' : '#999' ?>;">
                            <?= $part['total_checks'] ?>
                        </strong>
                    </td>
                    <td>
                        <a href="inspection_matrix_edit.php?part_no=<?= urlencode($part['part_no']) ?>" class="btn btn-sm btn-primary">
                            <?= $part['total_checks'] > 0 ? 'Edit' : 'Configure' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $qp = http_build_query(array_filter(['search' => $search, 'part_category' => $filter_category, 'configured' => $filter_configured]));
            $qp = $qp ? "&$qp" : '';
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $qp ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $qp ?>">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $qp ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qp ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $qp ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Bulk Copy Modal -->
<div class="modal-overlay" id="bulkCopyModal">
    <div class="modal-box">
        <h3>Copy Inspection Matrix</h3>
        <p style="color: #666; margin-bottom: 20px;">Copy all checkpoint assignments from one part to other parts. This will <strong>replace</strong> existing configurations on target parts.</p>
        <form method="post">
            <input type="hidden" name="action" value="bulk_copy">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Copy from (Source Part):</label>
                <select name="source_part" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="">Select source part...</option>
                    <?php foreach ($configuredPartsList as $cp): ?>
                        <option value="<?= htmlspecialchars($cp['part_no']) ?>">
                            <?= htmlspecialchars($cp['part_no']) ?> - <?= htmlspecialchars($cp['part_name'] ?: 'N/A') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Copy to (Target Parts):</label>
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 10px;">
                    <?php
                    try {
                        $allParts = $pdo->query("SELECT part_no, part_name FROM part_master WHERE status = 'active' ORDER BY part_no")->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) { $allParts = []; }
                    foreach ($allParts as $ap):
                    ?>
                        <label style="display: flex; align-items: center; padding: 5px 0; cursor: pointer;">
                            <input type="checkbox" name="target_parts[]" value="<?= htmlspecialchars($ap['part_no']) ?>" style="margin-right: 8px;">
                            <span><?= htmlspecialchars($ap['part_no']) ?></span>
                            <span style="color: #999; margin-left: 8px; font-size: 0.85em;"><?= htmlspecialchars($ap['part_name'] ?: '') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('bulkCopyModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="return confirm('This will replace existing inspection matrix on target parts. Continue?')">Copy Matrix</button>
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
