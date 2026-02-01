<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

showModal();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';

// Build query
$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "cr.status = ?";
    $params[] = $status_filter;
}
if (!empty($type_filter)) {
    $where[] = "cr.change_type = ?";
    $params[] = $type_filter;
}
if (!empty($priority_filter)) {
    $where[] = "cr.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM change_requests cr $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Get ECOs
$sql = "
    SELECT cr.*, p.project_name, p.project_no,
           (SELECT COUNT(*) FROM eco_affected_parts ap WHERE ap.eco_id = cr.id) as part_count
    FROM change_requests cr
    LEFT JOIN projects p ON cr.project_id = p.id
    $where_clause
    ORDER BY
        CASE cr.priority
            WHEN 'Critical' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            WHEN 'Low' THEN 4
        END,
        cr.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Filter options
$statuses = ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Implemented', 'Verified', 'Closed', 'Cancelled'];
$types = ['Design Change', 'Material Change', 'Process Change', 'Document Change', 'Supplier Change', 'Specification Change', 'Other'];
$priorities = ['Critical', 'High', 'Medium', 'Low'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Requests (ECO) - Product Engineering</title>
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
        .filter-section label {
            font-weight: 600;
            color: #495057;
        }
        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: white;
        }

        .eco-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .eco-card:hover {
            transform: translateX(5px);
        }
        .eco-card.priority-critical { border-left-color: #c62828; }
        .eco-card.priority-high { border-left-color: #e65100; }
        .eco-card.priority-medium { border-left-color: #1565c0; }
        .eco-card.priority-low { border-left-color: #757575; }

        .eco-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .eco-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 5px 0;
        }
        .eco-no {
            color: #667eea;
            font-weight: 600;
            font-size: 0.95em;
        }
        .eco-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-draft { background: #f5f5f5; color: #757575; }
        .status-submitted { background: #e3f2fd; color: #1565c0; }
        .status-under-review { background: #fff3e0; color: #ef6c00; }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .status-rejected { background: #ffebee; color: #c62828; }
        .status-implemented { background: #e0f2f1; color: #00695c; }
        .status-verified { background: #f3e5f5; color: #7b1fa2; }
        .status-closed { background: #eceff1; color: #455a64; }
        .status-cancelled { background: #fafafa; color: #9e9e9e; }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .priority-critical { background: #ffebee; color: #c62828; }
        .priority-high { background: #fff3e0; color: #e65100; }
        .priority-medium { background: #e3f2fd; color: #1565c0; }
        .priority-low { background: #f5f5f5; color: #757575; }

        .type-badge {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .eco-actions {
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
        .pagination a {
            background: #f8f9fa;
            color: #495057;
        }
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        .pagination span {
            background: #667eea;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        .empty-state h3 { margin: 0 0 10px 0; color: #95a5a6; }

        body.dark .eco-card { background: #2c3e50; }
        body.dark .eco-title { color: #ecf0f1; }
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
            <h1>Change Requests (ECO)</h1>
            <p style="color: #666; margin: 5px 0 0;">Engineering Change Orders and change management</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="eco_add.php" class="btn btn-primary">+ New Change Request</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label>Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Type:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $type_filter === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Priority:</label>
                <select name="priority" onchange="this.form.submit()">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $priority_filter === $p ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($status_filter || $type_filter || $priority_filter): ?>
                <a href="change_requests.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear Filters</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> ECO<?= $total_count != 1 ? 's' : '' ?> found
        </div>
    </div>

    <!-- ECO List -->
    <?php if ($total_count == 0): ?>
        <div class="empty-state">
            <h3>No Change Requests Found</h3>
            <p>Create your first engineering change request to get started.</p>
            <a href="eco_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Change Request</a>
        </div>
    <?php else: ?>
        <?php while ($eco = $stmt->fetch()): ?>
            <div class="eco-card priority-<?= strtolower($eco['priority']) ?>">
                <div class="eco-header">
                    <div>
                        <span class="eco-no"><?= htmlspecialchars($eco['eco_no']) ?></span>
                        <h3 class="eco-title"><?= htmlspecialchars($eco['title']) ?></h3>
                        <?php if ($eco['project_name']): ?>
                            <small style="color: #666;">Project: <?= htmlspecialchars($eco['project_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $eco['status'])) ?>">
                            <?= htmlspecialchars($eco['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="eco-meta">
                    <span>
                        <span class="priority-badge priority-<?= strtolower($eco['priority']) ?>"><?= $eco['priority'] ?></span>
                    </span>
                    <span>
                        <span class="type-badge"><?= htmlspecialchars($eco['change_type']) ?></span>
                    </span>
                    <?php if ($eco['requested_by']): ?>
                    <span>
                        Requested by: <?= htmlspecialchars($eco['requested_by']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($eco['request_date']): ?>
                    <span>
                        Date: <?= date('d M Y', strtotime($eco['request_date'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($eco['part_count'] > 0): ?>
                    <span style="background: #f8f9fa; padding: 3px 10px; border-radius: 10px;">
                        <?= $eco['part_count'] ?> affected part<?= $eco['part_count'] != 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($eco['reason_for_change']): ?>
                <p style="margin: 12px 0 0; color: #666; font-size: 0.9em;">
                    <?= htmlspecialchars(substr($eco['reason_for_change'], 0, 150)) ?><?= strlen($eco['reason_for_change']) > 150 ? '...' : '' ?>
                </p>
                <?php endif; ?>

                <div class="eco-actions">
                    <a href="eco_view.php?id=<?= $eco['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                    <?php if (in_array($eco['status'], ['Draft', 'Submitted'])): ?>
                        <a href="eco_edit.php?id=<?= $eco['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <?php endif; ?>
                    <?php if ($eco['status'] === 'Draft'): ?>
                        <a href="eco_submit.php?id=<?= $eco['id'] ?>" class="btn btn-sm" style="background: #3498db; color: white;">Submit for Review</a>
                    <?php elseif ($eco['status'] === 'Under Review'): ?>
                        <a href="eco_approve.php?id=<?= $eco['id'] ?>" class="btn btn-sm" style="background: #27ae60; color: white;">Review</a>
                    <?php elseif ($eco['status'] === 'Approved'): ?>
                        <a href="eco_implement.php?id=<?= $eco['id'] ?>" class="btn btn-sm" style="background: #9b59b6; color: white;">Implement</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $priority_filter ? '&priority=' . urlencode($priority_filter) : '' ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $priority_filter ? '&priority=' . urlencode($priority_filter) : '' ?>">Prev</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $priority_filter ? '&priority=' . urlencode($priority_filter) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $priority_filter ? '&priority=' . urlencode($priority_filter) : '' ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?><?= $priority_filter ? '&priority=' . urlencode($priority_filter) : '' ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
