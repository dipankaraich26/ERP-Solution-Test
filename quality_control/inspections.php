<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$result_filter = isset($_GET['result']) ? $_GET['result'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if (!empty($result_filter)) {
    $where[] = "i.inspection_result = ?";
    $params[] = $result_filter;
}
if (!empty($status_filter)) {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_incoming_inspections i $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get inspections
try {
    $sql = "
        SELECT i.*, s.name as supplier_name,
               (SELECT COUNT(*) FROM qc_incoming_inspection_items WHERE inspection_id = i.id) as item_count
        FROM qc_incoming_inspections i
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        $where_clause
        ORDER BY i.inspection_date DESC, i.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inspections = [];
}

$results = ['Accept', 'Reject', 'Conditional Accept', 'Pending'];
$statuses = ['Draft', 'In Progress', 'Completed', 'Closed'];

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
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
        }
        .data-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-accept { background: #d4edda; color: #155724; }
        .status-reject { background: #f8d7da; color: #721c24; }
        .status-conditional-accept { background: #fff3cd; color: #856404; }
        .status-pending { background: #e2e3e5; color: #383d41; }

        .inspection-type {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            background: #e8eaf6;
            color: #3f51b5;
        }

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

        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .filter-section { background: #34495e; }
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
            <p style="color: #666; margin: 5px 0 0;">Material and parts receiving inspection records</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="inspection_add.php" class="btn btn-primary">+ New Inspection</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
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
            <?php if ($result_filter || $status_filter): ?>
                <a href="inspections.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> inspection<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Inspections Table -->
    <?php if (empty($inspections)): ?>
        <div class="empty-state">
            <h3>No Inspections Found</h3>
            <p>Record your first incoming inspection.</p>
            <a href="inspection_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Inspection</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Inspection #</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>PO/GRN</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Result</th>
                    <th>Disposition</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspections as $insp): ?>
                    <tr>
                        <td><a href="inspection_view.php?id=<?= $insp['id'] ?>"><strong><?= htmlspecialchars($insp['inspection_no']) ?></strong></a></td>
                        <td><?= date('d M Y', strtotime($insp['inspection_date'])) ?></td>
                        <td><?= htmlspecialchars($insp['supplier_name'] ?: 'N/A') ?></td>
                        <td>
                            <?php if ($insp['po_no']): ?>PO: <?= htmlspecialchars($insp['po_no']) ?><br><?php endif; ?>
                            <?php if ($insp['grn_no']): ?>GRN: <?= htmlspecialchars($insp['grn_no']) ?><?php endif; ?>
                        </td>
                        <td><span class="inspection-type"><?= $insp['inspection_type'] ?></span></td>
                        <td>
                            <span style="color: #27ae60;"><?= $insp['accepted_qty'] ?></span> /
                            <span style="color: #e74c3c;"><?= $insp['rejected_qty'] ?></span> /
                            <?= $insp['sample_qty'] ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $insp['inspection_result'])) ?>">
                                <?= $insp['inspection_result'] ?>
                            </span>
                        </td>
                        <td><?= $insp['disposition'] ?></td>
                        <td>
                            <a href="inspection_view.php?id=<?= $insp['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($insp['status'] !== 'Closed'): ?>
                                <a href="inspection_edit.php?id=<?= $insp['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $result_filter ? '&result=' . urlencode($result_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $result_filter ? '&result=' . urlencode($result_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $result_filter ? '&result=' . urlencode($result_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $result_filter ? '&result=' . urlencode($result_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $result_filter ? '&result=' . urlencode($result_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
