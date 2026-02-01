<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';

$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "overall_status = ?";
    $params[] = $status_filter;
}
if (!empty($level_filter)) {
    $where[] = "submission_level = ?";
    $params[] = $level_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_ppap_submissions $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get PPAP submissions
try {
    $sql = "
        SELECT p.*,
               s.name as supplier_name,
               proj.project_name,
               (SELECT COUNT(*) FROM qc_ppap_elements WHERE ppap_id = p.id AND status = 'Completed') as completed_elements,
               (SELECT COUNT(*) FROM qc_ppap_elements WHERE ppap_id = p.id AND required = 1) as total_required
        FROM qc_ppap_submissions p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN projects proj ON p.project_id = proj.id
        $where_clause
        ORDER BY
            CASE p.overall_status
                WHEN 'Submitted' THEN 1
                WHEN 'In Progress' THEN 2
                WHEN 'Draft' THEN 3
                ELSE 4
            END,
            p.required_date ASC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $submissions = [];
}

$statuses = ['Draft', 'In Progress', 'Submitted', 'Approved', 'Rejected', 'Interim'];
$levels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>PPAP Submissions - QC</title>
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

        .info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-banner h4 { margin: 0 0 5px 0; }
        .info-banner p { margin: 0; opacity: 0.9; font-size: 0.9em; }

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

        .ppap-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        .ppap-card.status-approved { border-left-color: #27ae60; }
        .ppap-card.status-rejected { border-left-color: #e74c3c; }
        .ppap-card.status-submitted { border-left-color: #3498db; }
        .ppap-card.status-in-progress { border-left-color: #f39c12; }

        .ppap-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .ppap-no { color: #667eea; font-weight: 600; }
        .ppap-title { font-size: 1.1em; font-weight: 600; color: #2c3e50; margin: 5px 0 0; }

        .ppap-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-in-progress { background: #fff3cd; color: #856404; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-interim { background: #fff3e0; color: #e65100; }

        .level-badge {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
        }

        .ppap-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        body.dark .ppap-card { background: #2c3e50; }
        body.dark .ppap-title { color: #ecf0f1; }
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
            <h1>PPAP Submissions</h1>
            <p style="color: #666; margin: 5px 0 0;">Production Part Approval Process</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="ppap_add.php" class="btn btn-primary">+ New PPAP</a>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <h4>PPAP - Production Part Approval Process</h4>
        <p>Industry standard process to ensure suppliers can consistently meet production requirements. Contains 18 elements including design records, control plans, and process capability studies.</p>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
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
                <label>Level:</label>
                <select name="level" onchange="this.form.submit()">
                    <option value="">All Levels</option>
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= $l ?>" <?= $level_filter === $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($status_filter || $level_filter): ?>
                <a href="ppap.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> submission<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- PPAP List -->
    <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <h3>No PPAP Submissions Found</h3>
            <p>Create your first Production Part Approval Process submission.</p>
            <a href="ppap_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New PPAP</a>
        </div>
    <?php else: ?>
        <?php foreach ($submissions as $ppap): ?>
            <?php
            $progress = 0;
            if ($ppap['total_required'] > 0) {
                $progress = round(($ppap['completed_elements'] / $ppap['total_required']) * 100);
            }
            ?>
            <div class="ppap-card status-<?= strtolower(str_replace(' ', '-', $ppap['overall_status'])) ?>">
                <div class="ppap-header">
                    <div>
                        <span class="ppap-no"><?= htmlspecialchars($ppap['ppap_no']) ?></span>
                        <span class="level-badge" style="margin-left: 10px;"><?= $ppap['submission_level'] ?></span>
                        <h3 class="ppap-title"><?= htmlspecialchars($ppap['part_no']) ?> - <?= htmlspecialchars($ppap['part_name'] ?: 'N/A') ?></h3>
                    </div>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ppap['overall_status'])) ?>">
                        <?= $ppap['overall_status'] ?>
                    </span>
                </div>

                <div class="ppap-meta">
                    <?php if ($ppap['customer_name']): ?>
                        <span>Customer: <strong><?= htmlspecialchars($ppap['customer_name']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($ppap['supplier_name']): ?>
                        <span>Supplier: <strong><?= htmlspecialchars($ppap['supplier_name']) ?></strong></span>
                    <?php endif; ?>
                    <span>Reason: <?= htmlspecialchars($ppap['submission_reason']) ?></span>
                    <?php if ($ppap['required_date']): ?>
                        <span>Due: <strong><?= date('d M Y', strtotime($ppap['required_date'])) ?></strong></span>
                    <?php endif; ?>
                </div>

                <!-- Progress Bar -->
                <div style="margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85em; margin-bottom: 5px;">
                        <span>Element Completion</span>
                        <span><strong><?= $ppap['completed_elements'] ?></strong> / <?= $ppap['total_required'] ?> (<?= $progress ?>%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progress ?>%;"></div>
                    </div>
                </div>

                <div class="ppap-actions">
                    <a href="ppap_view.php?id=<?= $ppap['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                    <?php if (in_array($ppap['overall_status'], ['Draft', 'In Progress'])): ?>
                        <a href="ppap_edit.php?id=<?= $ppap['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <?php endif; ?>
                    <?php if ($ppap['overall_status'] === 'In Progress' && $progress >= 100): ?>
                        <a href="ppap_submit.php?id=<?= $ppap['id'] ?>" class="btn btn-sm" style="background: #3498db; color: white;">Submit</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
