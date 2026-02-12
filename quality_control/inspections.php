<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$result_filter = $_GET['result'] ?? '';
$status_filter = $_GET['status'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($result_filter !== '') {
    $where[] = "pic.overall_result = ?";
    $params[] = $result_filter;
}
if ($status_filter !== '') {
    $where[] = "pic.status = ?";
    $params[] = $status_filter;
}
if ($supplier_filter !== '') {
    $where[] = "po.supplier_id = ?";
    $params[] = $supplier_filter;
}
if ($search !== '') {
    $where[] = "(pic.checklist_no LIKE ? OR pic.po_no LIKE ? OR pic.inspector_name LIKE ? OR pic.supplier_invoice_no LIKE ?)";
    $searchLike = "%$search%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$total_count = 0;
try {
    $count_sql = "
        SELECT COUNT(DISTINCT pic.id)
        FROM po_inspection_checklists pic
        LEFT JOIN purchase_orders po ON po.po_no = pic.po_no
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = max(1, ceil($total_count / $per_page));

// Get inspections with supplier & PO details
$inspections = [];
try {
    $sql = "
        SELECT pic.*,
               s.supplier_name,
               (SELECT COUNT(*) FROM po_inspection_checklist_items WHERE checklist_id = pic.id) as item_count,
               (SELECT COUNT(*) FROM po_inspection_checklist_items WHERE checklist_id = pic.id AND result = 'OK') as ok_count,
               (SELECT COUNT(*) FROM po_inspection_checklist_items WHERE checklist_id = pic.id AND result = 'Not OK') as not_ok_count,
               (SELECT GROUP_CONCAT(DISTINCT po2.part_no SEPARATOR ', ')
                FROM purchase_orders po2 WHERE po2.po_no = pic.po_no) as parts
        FROM po_inspection_checklists pic
        LEFT JOIN purchase_orders po ON po.po_no = pic.po_no
        LEFT JOIN suppliers s ON s.id = po.supplier_id
        $where_clause
        GROUP BY pic.id
        ORDER BY pic.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inspections = [];
}

// Get suppliers for filter dropdown
$suppliers = [];
try {
    $suppliers = $pdo->query("
        SELECT DISTINCT s.id, s.supplier_name
        FROM po_inspection_checklists pic
        JOIN purchase_orders po ON po.po_no = pic.po_no
        JOIN suppliers s ON s.id = po.supplier_id
        ORDER BY s.supplier_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Summary stats
$stats = ['total' => 0, 'pass' => 0, 'fail' => 0, 'pending' => 0, 'conditional' => 0, 'approved' => 0, 'draft' => 0];
try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(overall_result = 'Pass') as pass_count,
            SUM(overall_result = 'Fail') as fail_count,
            SUM(overall_result = 'Pending') as pending_count,
            SUM(overall_result = 'Conditional') as conditional_count,
            SUM(status = 'Approved') as approved_count,
            SUM(status = 'Draft') as draft_count
        FROM po_inspection_checklists
    ")->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) {
        $stats['total'] = (int)$statsRow['total'];
        $stats['pass'] = (int)$statsRow['pass_count'];
        $stats['fail'] = (int)$statsRow['fail_count'];
        $stats['pending'] = (int)$statsRow['pending_count'];
        $stats['conditional'] = (int)$statsRow['conditional_count'];
        $stats['approved'] = (int)$statsRow['approved_count'];
        $stats['draft'] = (int)$statsRow['draft_count'];
    }
} catch (Exception $e) {}

$results = ['Pass', 'Fail', 'Pending', 'Conditional'];
$statuses = ['Draft', 'Submitted', 'Approved', 'Rejected'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Incoming Inspections - QC</title>
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
        .stat-card {
            flex: 1;
            min-width: 130px;
            padding: 15px 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-align: center;
        }
        .stat-card .stat-value {
            font-size: 1.8em;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-card .stat-label {
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

        .table-scroll-wrapper {
            max-height: 65vh;
            overflow-y: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9em;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table td { font-size: 0.9em; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }
        .result-pass { background: #d4edda; color: #155724; }
        .result-fail { background: #f8d7da; color: #721c24; }
        .result-pending { background: #e2e3e5; color: #383d41; }
        .result-conditional { background: #fff3cd; color: #856404; }

        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .check-counts { font-size: 0.85em; }
        .check-counts .ok { color: #27ae60; font-weight: 600; }
        .check-counts .nok { color: #e74c3c; font-weight: 600; }

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
        .pagination span { background: #667eea; color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        body.dark .table-scroll-wrapper { box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .filter-section { background: #34495e; }
        body.dark .stat-card { background: #34495e; color: #ecf0f1; }
        body.dark .stat-card .stat-label { color: #bdc3c7; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

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
            <h1>Incoming Inspections</h1>
            <p style="color: #666; margin: 5px 0 0;">PO incoming inspection checklists from stock entry</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value" style="color: #2c3e50;"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Inspections</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #27ae60;"><?= $stats['pass'] ?></div>
            <div class="stat-label">Passed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #e74c3c;"><?= $stats['fail'] ?></div>
            <div class="stat-label">Failed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f39c12;"><?= $stats['conditional'] ?></div>
            <div class="stat-label">Conditional</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #7f8c8d;"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #3498db;"><?= $stats['approved'] ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; width: 100%;">
            <div>
                <label>Search:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Checklist/PO/Inspector..." style="width: 180px;">
            </div>
            <div>
                <label>Result:</label>
                <select name="result" onchange="this.form.submit()">
                    <option value="">All Results</option>
                    <?php foreach ($results as $r): ?>
                        <option value="<?= $r ?>" <?= $result_filter === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Supplier:</label>
                <select name="supplier" onchange="this.form.submit()">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $supplier_filter == $sup['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($result_filter || $status_filter || $supplier_filter || $search): ?>
                <a href="inspections.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
            <div style="margin-left: auto; color: #666;">
                <?= $total_count ?> record<?= $total_count != 1 ? 's' : '' ?>
            </div>
        </form>
    </div>

    <!-- Inspections Table -->
    <?php if (empty($inspections)): ?>
        <div class="empty-state">
            <h3>No Inspections Found</h3>
            <p>Incoming inspection checklists will appear here when generated during stock entry.</p>
            <p style="margin-top: 10px; color: #999;">Go to Stock Entry &rarr; Receive All &rarr; Generate Inspection Checklist</p>
        </div>
    <?php else: ?>
        <div class="table-scroll-wrapper" style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Checklist No</th>
                    <th>PO No</th>
                    <th>Supplier</th>
                    <th>Inspector</th>
                    <th>Inspection Date</th>
                    <th>Supplier Invoice</th>
                    <th>Checkpoints</th>
                    <th>Result</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspections as $insp): ?>
                    <?php
                        $resultClass = 'result-' . strtolower($insp['overall_result'] ?: 'pending');
                        $statusClass = 'status-' . strtolower($insp['status'] ?: 'draft');
                    ?>
                    <tr>
                        <td>
                            <a href="/stock_entry/inspection_checklist.php?po_no=<?= urlencode($insp['po_no']) ?>" style="font-weight: 600;">
                                <?= htmlspecialchars($insp['checklist_no']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="/stock_entry/receive_all.php?po_no=<?= urlencode($insp['po_no']) ?>">
                                <?= htmlspecialchars($insp['po_no']) ?>
                            </a>
                            <?php if ($insp['parts']): ?>
                                <br><small style="color: #888;"><?= htmlspecialchars($insp['parts']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($insp['supplier_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($insp['inspector_name'] ?: '-') ?></td>
                        <td><?= $insp['inspection_date'] ? date('d M Y', strtotime($insp['inspection_date'])) : '-' ?></td>
                        <td><?= htmlspecialchars($insp['supplier_invoice_no'] ?: '-') ?></td>
                        <td>
                            <span class="check-counts">
                                <span class="ok"><?= (int)$insp['ok_count'] ?> OK</span> /
                                <span class="nok"><?= (int)$insp['not_ok_count'] ?> NOK</span> /
                                <?= (int)$insp['item_count'] ?> Total
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $resultClass ?>">
                                <?= htmlspecialchars($insp['overall_result'] ?: 'Pending') ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($insp['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="/stock_entry/inspection_checklist.php?po_no=<?= urlencode($insp['po_no']) ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($insp['status'] === 'Submitted'): ?>
                                <a href="/stock_entry/request_inspection_approval.php?po_no=<?= urlencode($insp['po_no']) ?>" class="btn btn-sm btn-primary" style="margin-top: 3px;">Approve</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <?php
            $queryParams = [];
            if ($result_filter) $queryParams['result'] = $result_filter;
            if ($status_filter) $queryParams['status'] = $status_filter;
            if ($supplier_filter) $queryParams['supplier'] = $supplier_filter;
            if ($search) $queryParams['search'] = $search;
            $qString = http_build_query($queryParams);
            $qPrefix = $qString ? "&$qString" : '';
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $qPrefix ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $qPrefix ?>">Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $qPrefix ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qPrefix ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $qPrefix ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
