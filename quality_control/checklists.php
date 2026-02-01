<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Active';

$where = [];
$params = [];

if (!empty($type_filter)) {
    $where[] = "checklist_type = ?";
    $params[] = $type_filter;
}
if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_checklists $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get checklists with item count
try {
    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM qc_checklist_items WHERE checklist_id = c.id) as item_count,
               u.full_name as created_by_name
        FROM qc_checklists c
        LEFT JOIN users u ON c.created_by = u.id
        $where_clause
        ORDER BY c.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $checklists = [];
}

$types = ['Incoming Inspection', 'In-Process', 'Final Inspection', 'Outgoing', 'Supplier Audit', 'Process Audit', 'Product Audit', 'Other'];
$statuses = ['Active', 'Draft', 'Obsolete'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quality Checklists - QC</title>
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
            background: white;
        }

        .checklist-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .checklist-card:hover { transform: translateX(5px); }
        .checklist-card.status-draft { border-left-color: #95a5a6; }
        .checklist-card.status-obsolete { border-left-color: #e74c3c; }

        .checklist-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .checklist-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .checklist-no {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9em;
        }

        .checklist-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-obsolete { background: #f8d7da; color: #721c24; }

        .type-badge {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .checklist-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        }

        body.dark .checklist-card { background: #2c3e50; }
        body.dark .checklist-title { color: #ecf0f1; }
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
            <h1>Quality Checklists</h1>
            <p style="color: #666; margin: 5px 0 0;">Inspection and audit checklist templates</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="checklist_add.php" class="btn btn-primary">+ New Checklist</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label>Type:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($type_filter || $status_filter !== 'Active'): ?>
                <a href="checklists.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> checklist<?= $total_count != 1 ? 's' : '' ?> found
        </div>
    </div>

    <!-- Checklists List -->
    <?php if (empty($checklists)): ?>
        <div class="empty-state">
            <h3>No Checklists Found</h3>
            <p>Create your first quality checklist template.</p>
            <a href="checklist_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Checklist</a>
        </div>
    <?php else: ?>
        <?php foreach ($checklists as $cl): ?>
            <div class="checklist-card status-<?= strtolower($cl['status']) ?>">
                <div class="checklist-header">
                    <div>
                        <span class="checklist-no"><?= htmlspecialchars($cl['checklist_no']) ?></span>
                        <span style="color: #999; margin: 0 8px;">|</span>
                        <span style="color: #666;"><?= htmlspecialchars($cl['revision']) ?></span>
                        <h3 class="checklist-title"><?= htmlspecialchars($cl['checklist_name']) ?></h3>
                    </div>
                    <span class="status-badge status-<?= strtolower($cl['status']) ?>"><?= $cl['status'] ?></span>
                </div>

                <div class="checklist-meta">
                    <span><span class="type-badge"><?= htmlspecialchars($cl['checklist_type']) ?></span></span>
                    <span><strong><?= $cl['item_count'] ?></strong> check points</span>
                    <?php if ($cl['applicable_to']): ?>
                        <span>Applicable to: <?= htmlspecialchars($cl['applicable_to']) ?></span>
                    <?php endif; ?>
                    <?php if ($cl['created_by_name']): ?>
                        <span>Created by: <?= htmlspecialchars($cl['created_by_name']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($cl['description']): ?>
                    <p style="margin: 10px 0 0; color: #666; font-size: 0.9em;">
                        <?= htmlspecialchars(substr($cl['description'], 0, 150)) ?><?= strlen($cl['description']) > 150 ? '...' : '' ?>
                    </p>
                <?php endif; ?>

                <div class="checklist-actions">
                    <a href="checklist_view.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-primary">View</a>
                    <?php if ($cl['status'] !== 'Obsolete'): ?>
                        <a href="checklist_edit.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <a href="inspection_add.php?checklist=<?= $cl['id'] ?>" class="btn btn-sm" style="background: #27ae60; color: white;">Use Checklist</a>
                    <?php endif; ?>
                    <a href="checklist_duplicate.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-secondary">Duplicate</a>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
