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

// Build query
$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "er.status = ?";
    $params[] = $status_filter;
}
if (!empty($type_filter)) {
    $where[] = "er.review_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM engineering_reviews er $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Get reviews
$sql = "
    SELECT er.*, p.project_name, p.project_no,
           (SELECT COUNT(*) FROM review_findings rf WHERE rf.review_id = er.id) as finding_count,
           (SELECT COUNT(*) FROM review_findings rf WHERE rf.review_id = er.id AND rf.status IN ('Open', 'In Progress')) as open_findings
    FROM engineering_reviews er
    LEFT JOIN projects p ON er.project_id = p.id
    $where_clause
    ORDER BY er.review_date DESC, er.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Get statuses and types for filter dropdowns
$statuses = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];
$types = ['Concept Review', 'Preliminary Design Review', 'Critical Design Review', 'Production Readiness Review', 'Post-Production Review', 'Other'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Engineering Reviews - Product Engineering</title>
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

        .review-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .review-card:hover {
            transform: translateX(5px);
        }
        .review-card.scheduled { border-left-color: #3498db; }
        .review-card.in-progress { border-left-color: #f39c12; }
        .review-card.completed { border-left-color: #27ae60; }
        .review-card.cancelled { border-left-color: #95a5a6; }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .review-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 5px 0;
        }
        .review-no {
            color: #667eea;
            font-weight: 600;
        }
        .review-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            flex-wrap: wrap;
        }
        .review-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-scheduled { background: #e3f2fd; color: #1565c0; }
        .status-in-progress { background: #fff3e0; color: #ef6c00; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #f5f5f5; color: #757575; }

        .outcome-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .outcome-approved { background: #e8f5e9; color: #2e7d32; }
        .outcome-approved-with-comments { background: #e3f2fd; color: #1565c0; }
        .outcome-conditional-approval { background: #fff3e0; color: #ef6c00; }
        .outcome-not-approved { background: #ffebee; color: #c62828; }
        .outcome-pending { background: #f5f5f5; color: #757575; }

        .type-badge {
            background: #f3e5f5;
            color: #7b1fa2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .findings-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            background: #f8f9fa;
        }
        .findings-badge.has-open {
            background: #fff3e0;
            color: #ef6c00;
        }

        .review-actions {
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

        body.dark .review-card { background: #2c3e50; }
        body.dark .review-title { color: #ecf0f1; }
        body.dark .filter-section { background: #34495e; }
        body.dark .filter-section label { color: #ecf0f1; }
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
            <h1>Engineering Reviews</h1>
            <p style="color: #666; margin: 5px 0 0;">Design reviews, PDR, CDR, and production readiness reviews</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="review_add.php" class="btn btn-primary">+ Schedule Review</a>
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
            <?php if ($status_filter || $type_filter): ?>
                <a href="reviews.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear Filters</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> review<?= $total_count != 1 ? 's' : '' ?> found
        </div>
    </div>

    <!-- Reviews List -->
    <?php if ($total_count == 0): ?>
        <div class="empty-state">
            <h3>No Engineering Reviews Found</h3>
            <p>Schedule your first engineering review to get started.</p>
            <a href="review_add.php" class="btn btn-primary" style="margin-top: 15px;">+ Schedule Review</a>
        </div>
    <?php else: ?>
        <?php while ($review = $stmt->fetch()): ?>
            <div class="review-card <?= strtolower(str_replace(' ', '-', $review['status'])) ?>">
                <div class="review-header">
                    <div>
                        <span class="review-no"><?= htmlspecialchars($review['review_no']) ?></span>
                        <h3 class="review-title"><?= htmlspecialchars($review['review_title']) ?></h3>
                        <?php if ($review['project_name']): ?>
                            <small style="color: #666;">Project: <?= htmlspecialchars($review['project_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $review['status'])) ?>">
                            <?= htmlspecialchars($review['status']) ?>
                        </span>
                        <?php if ($review['outcome'] && $review['outcome'] !== 'Pending'): ?>
                            <br>
                            <span class="outcome-badge outcome-<?= strtolower(str_replace(' ', '-', $review['outcome'])) ?>" style="margin-top: 5px; display: inline-block;">
                                <?= htmlspecialchars($review['outcome']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="review-meta">
                    <span>
                        <span class="type-badge"><?= htmlspecialchars($review['review_type']) ?></span>
                    </span>
                    <span>
                        Date: <strong><?= date('d M Y', strtotime($review['review_date'])) ?></strong>
                    </span>
                    <?php if ($review['review_leader']): ?>
                    <span>
                        Leader: <?= htmlspecialchars($review['review_leader']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="findings-badge <?= $review['open_findings'] > 0 ? 'has-open' : '' ?>">
                        Findings: <?= $review['finding_count'] ?>
                        <?php if ($review['open_findings'] > 0): ?>
                            (<?= $review['open_findings'] ?> open)
                        <?php endif; ?>
                    </span>
                </div>

                <div class="review-actions">
                    <a href="review_view.php?id=<?= $review['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                    <a href="review_edit.php?id=<?= $review['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <?php if ($review['status'] === 'Scheduled'): ?>
                        <a href="review_start.php?id=<?= $review['id'] ?>" class="btn btn-sm" style="background: #f39c12; color: white;">Start Review</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1<?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>">First</a>
                <a href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>">Prev</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>">Next</a>
                <a href="?page=<?= $total_pages ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?><?= $type_filter ? '&type=' . urlencode($type_filter) : '' ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
