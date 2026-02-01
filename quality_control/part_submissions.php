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

$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "ps.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_part_submissions ps $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get part submissions
try {
    $sql = "
        SELECT ps.*, s.name as supplier_name, c.name as customer_name_display
        FROM qc_part_submissions ps
        LEFT JOIN suppliers s ON ps.supplier_id = s.id
        LEFT JOIN customers c ON ps.customer_id = c.id
        $where_clause
        ORDER BY ps.submission_date DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $submissions = [];
}

$statuses = ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Conditional'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Part Submissions - QC</title>
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
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-under-review { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-conditional { background: #fff3e0; color: #e65100; }

        .type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            background: #e8eaf6;
            color: #3f51b5;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
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
            <h1>Part Submissions</h1>
            <p style="color: #666; margin: 5px 0 0;">Part approval and validation submissions</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="part_submission_add.php" class="btn btn-primary">+ New Submission</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center;">
            <label style="font-weight: 600;">Status:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($status_filter): ?>
                <a href="part_submissions.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> submission<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Part Submissions Table -->
    <?php if (empty($submissions)): ?>
        <div class="empty-state">
            <h3>No Part Submissions Found</h3>
            <p>Submit your first part for approval.</p>
            <a href="part_submission_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Submission</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Submission #</th>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th>Type</th>
                    <th>Supplier/Customer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td><a href="part_submission_view.php?id=<?= $sub['id'] ?>"><strong><?= htmlspecialchars($sub['submission_no']) ?></strong></a></td>
                        <td><?= htmlspecialchars($sub['part_no']) ?></td>
                        <td><?= htmlspecialchars($sub['part_name'] ?: '-') ?></td>
                        <td><span class="type-badge"><?= $sub['submission_type'] ?></span></td>
                        <td>
                            <?php if ($sub['supplier_name']): ?>
                                S: <?= htmlspecialchars($sub['supplier_name']) ?>
                            <?php elseif ($sub['customer_name_display']): ?>
                                C: <?= htmlspecialchars($sub['customer_name_display']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($sub['submission_date'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $sub['status'])) ?>">
                                <?= $sub['status'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="part_submission_view.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if (in_array($sub['status'], ['Draft', 'Submitted'])): ?>
                                <a href="part_submission_edit.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
