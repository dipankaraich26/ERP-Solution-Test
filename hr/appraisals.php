<?php
/**
 * Appraisals List
 * View and manage all appraisals
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'appraisals'")->fetch();
if (!$tableExists) {
    setModal("Setup Required", "Please run the HR Appraisal setup first.");
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$cycle_id = isset($_GET['cycle_id']) ? intval($_GET['cycle_id']) : 0;
$status_filter = $_GET['status'] ?? '';
$dept_filter = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($cycle_id) {
    $where[] = "a.cycle_id = ?";
    $params[] = $cycle_id;
}

if ($status_filter) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($dept_filter) {
    $where[] = "e.department = ?";
    $params[] = $dept_filter;
}

if ($search) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.emp_id LIKE ? OR a.appraisal_no LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(" AND ", $where);

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM appraisals a
    JOIN employees e ON a.employee_id = e.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Fetch appraisals
$sql = "
    SELECT a.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation,
           ac.cycle_name, ac.cycle_type,
           r.first_name as reviewer_first, r.last_name as reviewer_last
    FROM appraisals a
    JOIN employees e ON a.employee_id = e.id
    JOIN appraisal_cycles ac ON a.cycle_id = ac.id
    LEFT JOIN employees r ON a.reviewer_id = r.id
    WHERE $whereClause
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch cycles for filter
$cycles = $pdo->query("SELECT id, cycle_name FROM appraisal_cycles ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Fetch current cycle info
$currentCycle = null;
if ($cycle_id) {
    $cycleStmt = $pdo->prepare("SELECT * FROM appraisal_cycles WHERE id = ?");
    $cycleStmt->execute([$cycle_id]);
    $currentCycle = $cycleStmt->fetch(PDO::FETCH_ASSOC);
}

// Status counts
$statusCounts = [];
if ($cycle_id) {
    $countsStmt = $pdo->prepare("
        SELECT status, COUNT(*) as cnt
        FROM appraisals
        WHERE cycle_id = ?
        GROUP BY status
    ");
    $countsStmt->execute([$cycle_id]);
    while ($row = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = $row['cnt'];
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appraisals</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 0.85em;
            color: #666;
        }
        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
        }
        .status-cards {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .status-card {
            flex: 1;
            min-width: 120px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .status-card:hover {
            transform: translateY(-2px);
        }
        .status-card.active {
            border: 2px solid #3498db;
        }
        .status-card .count {
            font-size: 1.8em;
            font-weight: bold;
        }
        .status-card .label {
            font-size: 0.85em;
            color: #666;
        }
        .status-card.Draft .count { color: #6c757d; }
        .status-card.Self-Review .count { color: #007bff; }
        .status-card.Manager-Review .count { color: #fd7e14; }
        .status-card.HR-Review .count { color: #6f42c1; }
        .status-card.Completed .count { color: #28a745; }
        .status-card.Acknowledged .count { color: #17a2b8; }
        .appraisal-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .appraisal-table th {
            background: #3498db;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        .appraisal-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .appraisal-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-badge.Draft { background: #e9ecef; color: #495057; }
        .status-badge.Self-Review { background: #cce5ff; color: #004085; }
        .status-badge.Manager-Review { background: #ffe5d0; color: #8a4500; }
        .status-badge.HR-Review { background: #e2d5f1; color: #4a235a; }
        .status-badge.Completed { background: #d4edda; color: #155724; }
        .status-badge.Acknowledged { background: #d1ecf1; color: #0c5460; }
        .rating-stars {
            color: #ffc107;
        }
        .employee-info {
            display: flex;
            flex-direction: column;
        }
        .employee-name {
            font-weight: 500;
        }
        .employee-id {
            font-size: 0.85em;
            color: #666;
        }
        .cycle-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .cycle-info h2 {
            margin: 0 0 10px 0;
        }
        .cycle-info .dates {
            opacity: 0.9;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover {
            background: #f8f9fa;
        }
        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">Appraisals</h1>
        <a href="appraisal_cycles.php" class="btn btn-secondary">Back to Cycles</a>
    </div>

    <?php if ($currentCycle): ?>
    <div class="cycle-info">
        <h2><?= htmlspecialchars($currentCycle['cycle_name']) ?></h2>
        <div class="dates">
            <?= htmlspecialchars($currentCycle['cycle_type']) ?> |
            <?= date('d M Y', strtotime($currentCycle['start_date'])) ?> -
            <?= date('d M Y', strtotime($currentCycle['end_date'])) ?>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="status-cards">
        <?php
        $allStatuses = ['Draft', 'Self Review', 'Manager Review', 'HR Review', 'Completed', 'Acknowledged'];
        foreach ($allStatuses as $s):
            $cnt = $statusCounts[$s] ?? 0;
            $statusClass = str_replace(' ', '-', $s);
            $isActive = $status_filter === $s;
        ?>
        <div class="status-card <?= $statusClass ?> <?= $isActive ? 'active' : '' ?>"
             onclick="window.location='?cycle_id=<?= $cycle_id ?>&status=<?= urlencode($s) ?>'">
            <div class="count"><?= $cnt ?></div>
            <div class="label"><?= $s ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form method="get" class="filter-bar">
        <div class="filter-group">
            <label>Appraisal Cycle</label>
            <select name="cycle_id" onchange="this.form.submit()">
                <option value="">All Cycles</option>
                <?php foreach ($cycles as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cycle_id == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['cycle_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Self Review" <?= $status_filter === 'Self Review' ? 'selected' : '' ?>>Self Review</option>
                <option value="Manager Review" <?= $status_filter === 'Manager Review' ? 'selected' : '' ?>>Manager Review</option>
                <option value="HR Review" <?= $status_filter === 'HR Review' ? 'selected' : '' ?>>HR Review</option>
                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Acknowledged" <?= $status_filter === 'Acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Department</label>
            <select name="department" onchange="this.form.submit()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $dept_filter === $d ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, ID, Appraisal No...">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="?cycle_id=<?= $cycle_id ?>" class="btn btn-secondary">Clear</a>
    </form>

    <!-- Results -->
    <?php if (empty($appraisals)): ?>
    <div class="no-results">
        <h3>No Appraisals Found</h3>
        <p>Try adjusting your filters or create a new appraisal cycle.</p>
    </div>
    <?php else: ?>
    <table class="appraisal-table">
        <thead>
            <tr>
                <th>Appraisal No</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Cycle</th>
                <th>Status</th>
                <th>Self Rating</th>
                <th>Manager Rating</th>
                <th>Final</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appraisals as $a):
                $statusClass = str_replace(' ', '-', $a['status']);
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['appraisal_no']) ?></strong></td>
                <td>
                    <div class="employee-info">
                        <span class="employee-name"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></span>
                        <span class="employee-id"><?= htmlspecialchars($a['emp_id']) ?></span>
                    </div>
                </td>
                <td><?= htmlspecialchars($a['department'] ?: '-') ?></td>
                <td><?= htmlspecialchars($a['cycle_name']) ?></td>
                <td><span class="status-badge <?= $statusClass ?>"><?= $a['status'] ?></span></td>
                <td>
                    <?php if ($a['self_overall_rating']): ?>
                    <span class="rating-stars"><?= str_repeat('★', round($a['self_overall_rating'])) ?></span>
                    <span style="color: #666;">(<?= number_format($a['self_overall_rating'], 1) ?>)</span>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($a['manager_overall_rating']): ?>
                    <span class="rating-stars"><?= str_repeat('★', round($a['manager_overall_rating'])) ?></span>
                    <span style="color: #666;">(<?= number_format($a['manager_overall_rating'], 1) ?>)</span>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($a['final_rating']): ?>
                    <strong style="color: #28a745;"><?= number_format($a['final_rating'], 1) ?></strong>
                    <?php if ($a['final_grade']): ?>
                    <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-left: 5px;">
                        <?= htmlspecialchars($a['final_grade']) ?>
                    </span>
                    <?php endif; ?>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <a href="appraisal_form.php?id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">
                        <?= in_array($a['status'], ['Completed', 'Acknowledged']) ? 'View' : 'Review' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">First</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top: 20px; text-align: center; color: #666;">
        Showing <?= count($appraisals) ?> of <?= $totalCount ?> appraisals
    </div>
</div>

</body>
</html>
