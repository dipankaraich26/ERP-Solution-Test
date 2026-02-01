<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if ($status_filter === 'overdue') {
    $where[] = "next_calibration_date < CURDATE() AND status = 'Active'";
} elseif ($status_filter === 'due') {
    $where[] = "next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND next_calibration_date >= CURDATE() AND status = 'Active'";
} elseif (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_calibration_records $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get calibration records
try {
    $sql = "
        SELECT *,
               CASE
                   WHEN next_calibration_date < CURDATE() THEN 'Overdue'
                   WHEN next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due Soon'
                   ELSE 'OK'
               END as due_status
        FROM qc_calibration_records
        $where_clause
        ORDER BY
            CASE
                WHEN next_calibration_date < CURDATE() THEN 1
                WHEN next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2
                ELSE 3
            END,
            next_calibration_date ASC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $records = [];
}

// Get summary counts
try {
    $overdue_count = $pdo->query("SELECT COUNT(*) FROM qc_calibration_records WHERE next_calibration_date < CURDATE() AND status = 'Active'")->fetchColumn();
    $due_count = $pdo->query("SELECT COUNT(*) FROM qc_calibration_records WHERE next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND next_calibration_date >= CURDATE() AND status = 'Active'")->fetchColumn();
    $active_count = $pdo->query("SELECT COUNT(*) FROM qc_calibration_records WHERE status = 'Active'")->fetchColumn();
} catch (Exception $e) {
    $overdue_count = 0;
    $due_count = 0;
    $active_count = 0;
}

$statuses = ['Active', 'Due', 'Overdue', 'Out of Service'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Calibration Records - QC</title>
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

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .summary-card.danger { border-top: 4px solid #e74c3c; }
        .summary-card.warning { border-top: 4px solid #f39c12; }
        .summary-card.success { border-top: 4px solid #27ae60; }
        .summary-value { font-size: 2em; font-weight: bold; }
        .summary-label { color: #666; font-size: 0.9em; margin-top: 5px; }

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
        .data-table tr.overdue { background: #fff5f5; }
        .data-table tr.due-soon { background: #fffdf5; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-due-soon { background: #fff3cd; color: #856404; }
        .status-out-of-service { background: #e2e3e5; color: #383d41; }

        .result-pass { color: #27ae60; font-weight: 600; }
        .result-fail { color: #e74c3c; font-weight: 600; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        body.dark .summary-card, body.dark .data-table { background: #2c3e50; }
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
            <h1>Calibration Records</h1>
            <p style="color: #666; margin: 5px 0 0;">Measuring instruments calibration tracking</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="calibration_add.php" class="btn btn-primary">+ Add Instrument</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <a href="?status=overdue" class="summary-card danger" style="text-decoration: none; color: inherit;">
            <div class="summary-value" style="color: #e74c3c;"><?= $overdue_count ?></div>
            <div class="summary-label">Overdue</div>
        </a>
        <a href="?status=due" class="summary-card warning" style="text-decoration: none; color: inherit;">
            <div class="summary-value" style="color: #f39c12;"><?= $due_count ?></div>
            <div class="summary-label">Due in 30 Days</div>
        </a>
        <a href="?status=Active" class="summary-card success" style="text-decoration: none; color: inherit;">
            <div class="summary-value" style="color: #27ae60;"><?= $active_count ?></div>
            <div class="summary-label">Active Instruments</div>
        </a>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center;">
            <label style="font-weight: 600;">Filter:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Instruments</option>
                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="due" <?= $status_filter === 'due' ? 'selected' : '' ?>>Due Soon (30 days)</option>
                <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Out of Service" <?= $status_filter === 'Out of Service' ? 'selected' : '' ?>>Out of Service</option>
            </select>
            <?php if ($status_filter): ?>
                <a href="calibration.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> record<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Calibration Table -->
    <?php if (empty($records)): ?>
        <div class="empty-state">
            <h3>No Calibration Records Found</h3>
            <p>Add your first measuring instrument.</p>
            <a href="calibration_add.php" class="btn btn-primary" style="margin-top: 15px;">+ Add Instrument</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Instrument ID</th>
                    <th>Name / Type</th>
                    <th>Location</th>
                    <th>Range</th>
                    <th>Last Calibration</th>
                    <th>Next Due</th>
                    <th>Result</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                    <tr class="<?= $rec['due_status'] === 'Overdue' ? 'overdue' : ($rec['due_status'] === 'Due Soon' ? 'due-soon' : '') ?>">
                        <td><strong><?= htmlspecialchars($rec['instrument_id']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($rec['instrument_name']) ?>
                            <?php if ($rec['instrument_type']): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars($rec['instrument_type']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($rec['location'] ?: '-') ?></td>
                        <td>
                            <?php if ($rec['range_min'] !== null && $rec['range_max'] !== null): ?>
                                <?= $rec['range_min'] ?> - <?= $rec['range_max'] ?> <?= $rec['unit'] ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($rec['calibration_date'])) ?></td>
                        <td>
                            <?php if ($rec['next_calibration_date']): ?>
                                <strong><?= date('d M Y', strtotime($rec['next_calibration_date'])) ?></strong>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="result-<?= strtolower($rec['calibration_result']) ?>"><?= $rec['calibration_result'] ?></span>
                        </td>
                        <td>
                            <?php if ($rec['due_status'] === 'Overdue'): ?>
                                <span class="status-badge status-overdue">Overdue</span>
                            <?php elseif ($rec['due_status'] === 'Due Soon'): ?>
                                <span class="status-badge status-due-soon">Due Soon</span>
                            <?php elseif ($rec['status'] === 'Out of Service'): ?>
                                <span class="status-badge status-out-of-service">Out of Service</span>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="calibration_view.php?id=<?= $rec['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="calibration_record.php?id=<?= $rec['id'] ?>" class="btn btn-sm btn-primary">Record Cal.</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
