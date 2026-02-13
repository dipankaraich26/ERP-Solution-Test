<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('project_management');
include "../includes/dialog.php";

showModal();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter by status if provided
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get total count
if (!empty($status_filter)) {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE p.status = ?");
    $count_stmt->execute([$status_filter]);
    $total_count = $count_stmt->fetchColumn();
} else {
    $total_count = $pdo->query("SELECT COUNT(*) FROM projects p")->fetchColumn();
}

$total_pages = ceil($total_count / $per_page);

// Get projects
if (!empty($status_filter)) {
    $projects_stmt = $pdo->prepare("
        SELECT p.id, p.project_no, p.project_name, p.project_manager, p.project_engineer,
               p.customer_id, p.start_date, p.end_date, p.status, p.priority, p.progress_percentage,
               p.project_type, p.design_phase
        FROM projects p
        WHERE p.status = :status
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $projects_stmt->bindValue(':status', $status_filter);
    $projects_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $projects_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $projects_stmt->execute();
} else {
    $projects_stmt = $pdo->prepare("
        SELECT p.id, p.project_no, p.project_name, p.project_manager, p.project_engineer,
               p.customer_id, p.start_date, p.end_date, p.status, p.priority, p.progress_percentage,
               p.project_type, p.design_phase
        FROM projects p
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $projects_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $projects_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $projects_stmt->execute();
}

// Get all statuses for filter dropdown
$statuses = ['Planning', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Projects - Product Engineering</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .project-card {
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .status-planning {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-in-progress {
            background: #fff3e0;
            color: #f57c00;
        }
        .status-on-hold {
            background: #fce4ec;
            color: #c2185b;
        }
        .status-completed {
            background: #e8f5e9;
            color: #388e3c;
        }
        .status-cancelled {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .priority-high {
            color: #e74c3c;
            font-weight: bold;
        }
        .priority-critical {
            color: #c0392b;
            font-weight: bold;
            text-transform: uppercase;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60 0%, #2ecc71 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: bold;
        }
        .filter-section {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        body.dark .project-card {
            background: #2c3e50;
        }
        body.dark .filter-section {
            background: #34495e;
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

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
        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "Dark Mode";
        }
    });
}
</script>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0;">Engineering Projects</h1>
            <p style="color: #666; margin: 5px 0 0;">Product development and engineering projects</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="add.php" class="btn btn-primary">+ New Project</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 10px;">
            <label>Filter by Status:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">-- All Projects --</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Projects List -->
    <div>
        <?php if ($total_count > 0): ?>
            <?php while ($project = $projects_stmt->fetch()): ?>
                <div class="project-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0 0 5px 0;">
                                <?= htmlspecialchars($project['project_no']) ?> - <?= htmlspecialchars($project['project_name']) ?>
                            </h3>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>Manager:</strong> <?= htmlspecialchars($project['project_manager'] ?: 'N/A') ?> |
                                <strong>Engineer:</strong> <?= htmlspecialchars($project['project_engineer'] ?: 'N/A') ?>
                                <?php if (!empty($project['design_phase'])): ?>
                                | <span style="background: #e8eaf6; color: #3f51b5; padding: 2px 8px; border-radius: 10px; font-size: 0.85em;"><?= htmlspecialchars($project['design_phase']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $project['status'])) ?>">
                                <?= htmlspecialchars($project['status']) ?>
                            </span>
                            <?php if ($project['priority']): ?>
                                <div style="margin-top: 5px; font-size: 0.9em;">
                                    <span class="priority-<?= strtolower($project['priority']) ?>">
                                        <?= htmlspecialchars($project['priority']) ?> Priority
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= (int)$project['progress_percentage'] ?>%;">
                            <?= (int)$project['progress_percentage'] ?>%
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <a href="view.php?id=<?= $project['id'] ?>" class="btn btn-secondary">View Details</a>
                        <a href="edit.php?id=<?= $project['id'] ?>" class="btn btn-secondary">Edit</a>
                        <a href="delete.php?id=<?= $project['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this project?')">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; padding: 40px;">No projects found.</p>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 30px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total projects)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
