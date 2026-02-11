<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Ensure WO quality checklist tables exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wo_quality_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            work_order_id INT NOT NULL,
            checklist_no VARCHAR(30) NOT NULL UNIQUE,
            inspector_name VARCHAR(100),
            inspection_date DATE,
            overall_result ENUM('Pass','Fail','Pending') DEFAULT 'Pending',
            remarks TEXT,
            status ENUM('Draft','Submitted','Approved','Rejected') DEFAULT 'Draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at DATETIME
        )
    ");
} catch (Exception $e) {}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$qc_result_filter = $_GET['qc_result'] ?? '';
$wo_status_filter = $_GET['wo_status'] ?? '';
$pp_search = trim($_GET['pp'] ?? '');
$so_search = trim($_GET['so'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($qc_result_filter === 'no_checklist') {
    $where[] = "qc.id IS NULL";
} elseif (!empty($qc_result_filter)) {
    $where[] = "qc.overall_result = ?";
    $params[] = $qc_result_filter;
}

if (!empty($wo_status_filter)) {
    $where[] = "w.status = ?";
    $params[] = $wo_status_filter;
}

if (!empty($pp_search)) {
    $where[] = "pp.plan_no LIKE ?";
    $params[] = "%$pp_search%";
}

if (!empty($so_search)) {
    $where[] = "pp.so_list LIKE ?";
    $params[] = "%$so_search%";
}

if (!empty($search)) {
    $where[] = "(w.wo_no LIKE ? OR w.part_no LIKE ? OR p.part_name LIKE ? OR qc.checklist_no LIKE ? OR qc.inspector_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_sql = "SELECT COUNT(*) FROM work_orders w
        LEFT JOIN part_master p ON w.part_no = p.part_no
        LEFT JOIN procurement_plans pp ON w.plan_id = pp.id
        LEFT JOIN wo_quality_checklists qc ON qc.work_order_id = w.id
        $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = max(1, ceil($total_count / $per_page));

// Get WO list with QC info
try {
    $sql = "SELECT w.id, w.wo_no, w.part_no, w.qty, w.status as wo_status, w.plan_id, w.created_at,
               p.part_name,
               pp.plan_no, pp.so_list,
               qc.id as checklist_id, qc.checklist_no, qc.inspector_name, qc.inspection_date,
               qc.overall_result, qc.status as qc_status
        FROM work_orders w
        LEFT JOIN part_master p ON w.part_no = p.part_no
        LEFT JOIN procurement_plans pp ON w.plan_id = pp.id
        LEFT JOIN wo_quality_checklists qc ON qc.work_order_id = w.id
        $where_clause
        ORDER BY w.id DESC
        LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// Summary stats
try {
    $stat_total = $pdo->query("SELECT COUNT(*) FROM work_orders")->fetchColumn();
    $stat_with_qc = $pdo->query("SELECT COUNT(DISTINCT qc.work_order_id) FROM wo_quality_checklists qc")->fetchColumn();
    $stat_pass = $pdo->query("SELECT COUNT(*) FROM wo_quality_checklists WHERE overall_result = 'Pass'")->fetchColumn();
    $stat_fail = $pdo->query("SELECT COUNT(*) FROM wo_quality_checklists WHERE overall_result = 'Fail'")->fetchColumn();
    $stat_pending_qc = $pdo->query("SELECT COUNT(*) FROM work_orders w LEFT JOIN wo_quality_checklists qc ON qc.work_order_id = w.id WHERE w.status IN ('completed','qc_approval') AND qc.id IS NULL")->fetchColumn();
} catch (Exception $e) {
    $stat_total = $stat_with_qc = $stat_pass = $stat_fail = $stat_pending_qc = 0;
}

// Get distinct WO statuses for filter
try {
    $wo_statuses = $pdo->query("SELECT DISTINCT status FROM work_orders ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $wo_statuses = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>WO Quality Inspections - Quality Control</title>
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

        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-chip {
            background: white;
            border-radius: 8px;
            padding: 12px 18px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stat-chip .stat-num {
            font-size: 1.5em;
            font-weight: bold;
        }
        .stat-chip .stat-lbl {
            font-size: 0.85em;
            color: #666;
        }

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
            font-size: 0.9em;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-size: 0.9em;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-draft { background: #e2e3e5; color: #383d41; }
        .badge-submitted { background: #cce5ff; color: #004085; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-no-qc { background: #fce4ec; color: #c62828; }

        .wo-status {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .wo-pending { background: #fff3cd; color: #856404; }
        .wo-in_progress, .wo-in-progress { background: #cce5ff; color: #004085; }
        .wo-completed { background: #d4edda; color: #155724; }
        .wo-qc_approval, .wo-qc-approval { background: #e8eaf6; color: #3f51b5; }
        .wo-closed { background: #e2e3e5; color: #383d41; }
        .wo-cancelled { background: #f8d7da; color: #721c24; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        .pagination a { background: #f8f9fa; color: #495057; }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination span.current { background: #667eea; color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        .so-tags { display: flex; flex-wrap: wrap; gap: 3px; }
        .so-tag {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 0.8em;
        }

        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .filter-section { background: #34495e; }
        body.dark .stat-chip { background: #2c3e50; }
        body.dark .stat-chip .stat-lbl { color: #bdc3c7; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>WO Quality Inspections</h1>
            <p style="color: #666; margin: 5px 0 0;">Work order quality checklists linked to procurement plans and sales orders</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-row">
        <div class="stat-chip">
            <div class="stat-num" style="color: #2c3e50;"><?= $stat_total ?></div>
            <div class="stat-lbl">Total WOs</div>
        </div>
        <div class="stat-chip">
            <div class="stat-num" style="color: #3498db;"><?= $stat_with_qc ?></div>
            <div class="stat-lbl">With Checklist</div>
        </div>
        <div class="stat-chip">
            <div class="stat-num" style="color: #27ae60;"><?= $stat_pass ?></div>
            <div class="stat-lbl">Passed</div>
        </div>
        <div class="stat-chip">
            <div class="stat-num" style="color: #e74c3c;"><?= $stat_fail ?></div>
            <div class="stat-lbl">Failed</div>
        </div>
        <div class="stat-chip">
            <div class="stat-num" style="color: #f39c12;"><?= $stat_pending_qc ?></div>
            <div class="stat-lbl">Pending QC</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; width: 100%;">
            <div>
                <label>QC Result:</label>
                <select name="qc_result" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Pass" <?= $qc_result_filter === 'Pass' ? 'selected' : '' ?>>Pass</option>
                    <option value="Fail" <?= $qc_result_filter === 'Fail' ? 'selected' : '' ?>>Fail</option>
                    <option value="Pending" <?= $qc_result_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="no_checklist" <?= $qc_result_filter === 'no_checklist' ? 'selected' : '' ?>>No Checklist</option>
                </select>
            </div>
            <div>
                <label>WO Status:</label>
                <select name="wo_status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($wo_statuses as $ws): ?>
                        <option value="<?= htmlspecialchars($ws) ?>" <?= $wo_status_filter === $ws ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $ws)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>PP No:</label>
                <input type="text" name="pp" value="<?= htmlspecialchars($pp_search) ?>" placeholder="e.g. PP-019" style="width: 100px;">
            </div>
            <div>
                <label>SO No:</label>
                <input type="text" name="so" value="<?= htmlspecialchars($so_search) ?>" placeholder="e.g. SO-001" style="width: 100px;">
            </div>
            <div>
                <label>Search:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="WO, part, inspector..." style="width: 160px;">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <?php if ($qc_result_filter || $wo_status_filter || $pp_search || $so_search || $search): ?>
                <a href="wo_inspections.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
            <div style="margin-left: auto; color: #666; font-size: 0.9em;">
                <?= $total_count ?> record<?= $total_count != 1 ? 's' : '' ?>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <h3>No Work Orders Found</h3>
            <p>No work orders match the current filters.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>WO No</th>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Qty</th>
                    <th>PP No</th>
                    <th>SO(s)</th>
                    <th>QC Checklist</th>
                    <th>Inspector</th>
                    <th>Date</th>
                    <th>Result</th>
                    <th>QC Status</th>
                    <th>WO Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="/work_orders/view.php?id=<?= $row['id'] ?>" style="color: #667eea; font-weight: 600;"><?= htmlspecialchars($row['wo_no']) ?></a></td>
                    <td><?= htmlspecialchars($row['part_no']) ?></td>
                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['part_name'] ?? '') ?>">
                        <?= htmlspecialchars($row['part_name'] ?? '-') ?>
                    </td>
                    <td><?= $row['qty'] ?></td>
                    <td>
                        <?php if ($row['plan_id']): ?>
                            <a href="/procurement/view.php?id=<?= $row['plan_id'] ?>" style="color: #9333ea; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars($row['plan_no'] ?? 'PP-' . $row['plan_id']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['so_list'])): ?>
                            <div class="so-tags">
                                <?php foreach (explode(',', $row['so_list']) as $so): $so = trim($so); if ($so): ?>
                                    <span class="so-tag"><?= htmlspecialchars($so) ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['checklist_id']): ?>
                            <a href="/work_orders/quality_checklist.php?id=<?= $row['id'] ?>" style="color: #2563eb; font-weight: 500;">
                                <?= htmlspecialchars($row['checklist_no']) ?>
                            </a>
                        <?php else: ?>
                            <a href="/work_orders/quality_checklist.php?id=<?= $row['id'] ?>" class="status-badge badge-no-qc" style="text-decoration: none;">
                                No QC
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['inspector_name'] ?? '-') ?></td>
                    <td><?= $row['inspection_date'] ? date('d M Y', strtotime($row['inspection_date'])) : '-' ?></td>
                    <td>
                        <?php if ($row['checklist_id']): ?>
                            <span class="status-badge badge-<?= strtolower($row['overall_result'] ?? 'pending') ?>">
                                <?= $row['overall_result'] ?? 'Pending' ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['checklist_id']): ?>
                            <span class="status-badge badge-<?= strtolower($row['qc_status'] ?? 'draft') ?>">
                                <?= $row['qc_status'] ?? 'Draft' ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="wo-status wo-<?= strtolower(str_replace('_', '-', $row['wo_status'])) ?>">
                            <?= ucfirst(str_replace('_', ' ', $row['wo_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = $_GET;
            unset($query_params['page']);
            $base_url = 'wo_inspections.php?' . http_build_query($query_params);
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base_url ?>&page=<?= $page - 1 ?>">Prev</a>
            <?php endif; ?>
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $base_url ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base_url ?>&page=<?= $page + 1 ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
