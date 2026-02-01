<?php
/**
 * Appraisal Cycles Management
 * Create and manage appraisal periods
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'appraisal_cycles'")->fetch();
if (!$tableExists) {
    setModal("Setup Required", "Please run the HR Appraisal setup first.");
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$message = '';
$error = '';

// Handle create cycle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_cycle'])) {
    $name = trim($_POST['cycle_name']);
    $type = $_POST['cycle_type'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $selfDeadline = $_POST['self_review_deadline'] ?: null;
    $managerDeadline = $_POST['manager_review_deadline'] ?: null;
    $description = trim($_POST['description']);

    if ($name && $startDate && $endDate) {
        try {
            $pdo->prepare("
                INSERT INTO appraisal_cycles
                (cycle_name, cycle_type, start_date, end_date, self_review_deadline, manager_review_deadline, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $type, $startDate, $endDate, $selfDeadline, $managerDeadline, $description, $_SESSION['user_id'] ?? 1]);
            $message = "Appraisal cycle created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating cycle: " . $e->getMessage();
        }
    } else {
        $error = "Please fill all required fields";
    }
}

// Handle status change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    $validStatuses = ['Draft', 'Active', 'In Review', 'Completed', 'Cancelled'];

    if (in_array($action, $validStatuses)) {
        $pdo->prepare("UPDATE appraisal_cycles SET status = ? WHERE id = ?")->execute([$action, $id]);
        $message = "Cycle status updated to $action";
    }

    if ($action === 'delete') {
        // Check if any appraisals exist
        $count = $pdo->prepare("SELECT COUNT(*) FROM appraisals WHERE cycle_id = ?");
        $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            $error = "Cannot delete cycle with existing appraisals";
        } else {
            $pdo->prepare("DELETE FROM appraisal_cycles WHERE id = ?")->execute([$id]);
            $message = "Cycle deleted successfully";
        }
    }

    if ($action === 'generate') {
        // Generate appraisals for all active employees
        $employees = $pdo->query("SELECT id FROM employees WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);

        $insertStmt = $pdo->prepare("
            INSERT IGNORE INTO appraisals (appraisal_no, cycle_id, employee_id, status)
            VALUES (?, ?, ?, 'Draft')
        ");

        $generated = 0;
        foreach ($employees as $empId) {
            // Generate appraisal number
            $appraisalNo = 'APR-' . $id . '-' . str_pad($empId, 4, '0', STR_PAD_LEFT);
            try {
                $insertStmt->execute([$appraisalNo, $id, $empId]);
                if ($insertStmt->rowCount() > 0) $generated++;
            } catch (Exception $e) {
                // Skip duplicates
            }
        }
        $message = "Generated $generated appraisals for active employees";
    }
}

// Fetch cycles
$cycles = $pdo->query("
    SELECT ac.*,
           (SELECT COUNT(*) FROM appraisals WHERE cycle_id = ac.id) as appraisal_count,
           (SELECT COUNT(*) FROM appraisals WHERE cycle_id = ac.id AND status = 'Completed') as completed_count
    FROM appraisal_cycles ac
    ORDER BY ac.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch criteria for display
$criteria = $pdo->query("
    SELECT * FROM appraisal_criteria WHERE is_active = 1 ORDER BY sort_order
")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appraisal Cycles</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .cycles-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        .cycle-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #ddd;
        }
        .cycle-card.Draft { border-left-color: #6c757d; }
        .cycle-card.Active { border-left-color: #28a745; }
        .cycle-card.In.Review { border-left-color: #ffc107; }
        .cycle-card.Completed { border-left-color: #17a2b8; }
        .cycle-card.Cancelled { border-left-color: #dc3545; }
        .cycle-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .cycle-name {
            font-size: 1.2em;
            font-weight: bold;
            margin: 0;
        }
        .cycle-type {
            font-size: 0.9em;
            color: #666;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-badge.Draft { background: #e9ecef; color: #495057; }
        .status-badge.Active { background: #d4edda; color: #155724; }
        .status-badge.In-Review { background: #fff3cd; color: #856404; }
        .status-badge.Completed { background: #d1ecf1; color: #0c5460; }
        .status-badge.Cancelled { background: #f8d7da; color: #721c24; }
        .cycle-dates {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            font-size: 0.9em;
        }
        .cycle-dates .date-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .cycle-dates .date-label {
            color: #666;
        }
        .cycle-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 1.5em;
            font-weight: bold;
            color: #3498db;
        }
        .stat-box .label {
            font-size: 0.8em;
            color: #666;
        }
        .cycle-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .sidebar-panel {
            position: sticky;
            top: 20px;
        }
        .create-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .create-form h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s;
        }
        .criteria-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .criteria-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .criteria-item:last-child {
            border-bottom: none;
        }
        .criteria-name {
            font-weight: 500;
        }
        .criteria-category {
            font-size: 0.85em;
            color: #666;
        }
        .criteria-weight {
            font-weight: bold;
            color: #3498db;
        }
        @media (max-width: 900px) {
            .cycles-container {
                grid-template-columns: 1fr;
            }
            .sidebar-panel {
                position: static;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Appraisal Cycles</h1>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="cycles-container">
        <!-- Cycles List -->
        <div class="cycles-list">
            <?php if (empty($cycles)): ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 8px;">
                <h3>No Appraisal Cycles</h3>
                <p>Create your first appraisal cycle to get started.</p>
            </div>
            <?php else: ?>
                <?php foreach ($cycles as $cycle):
                    $progress = $cycle['appraisal_count'] > 0
                        ? round(($cycle['completed_count'] / $cycle['appraisal_count']) * 100)
                        : 0;
                    $statusClass = str_replace(' ', '-', $cycle['status']);
                ?>
                <div class="cycle-card <?= $cycle['status'] ?>">
                    <div class="cycle-header">
                        <div>
                            <h3 class="cycle-name"><?= htmlspecialchars($cycle['cycle_name']) ?></h3>
                            <div class="cycle-type"><?= htmlspecialchars($cycle['cycle_type']) ?> Appraisal</div>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= $cycle['status'] ?></span>
                    </div>

                    <div class="cycle-dates">
                        <div class="date-item">
                            <span class="date-label">Period:</span>
                            <strong><?= date('d M Y', strtotime($cycle['start_date'])) ?></strong>
                            to
                            <strong><?= date('d M Y', strtotime($cycle['end_date'])) ?></strong>
                        </div>
                    </div>

                    <?php if ($cycle['self_review_deadline'] || $cycle['manager_review_deadline']): ?>
                    <div class="cycle-dates" style="margin-top: 5px;">
                        <?php if ($cycle['self_review_deadline']): ?>
                        <div class="date-item">
                            <span class="date-label">Self Review:</span>
                            <strong><?= date('d M Y', strtotime($cycle['self_review_deadline'])) ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($cycle['manager_review_deadline']): ?>
                        <div class="date-item">
                            <span class="date-label">Manager Review:</span>
                            <strong><?= date('d M Y', strtotime($cycle['manager_review_deadline'])) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="cycle-stats">
                        <div class="stat-box">
                            <div class="number"><?= $cycle['appraisal_count'] ?></div>
                            <div class="label">Total Appraisals</div>
                        </div>
                        <div class="stat-box">
                            <div class="number"><?= $cycle['completed_count'] ?></div>
                            <div class="label">Completed</div>
                        </div>
                        <div class="stat-box">
                            <div class="number"><?= $progress ?>%</div>
                            <div class="label">Progress</div>
                        </div>
                    </div>

                    <?php if ($cycle['appraisal_count'] > 0): ?>
                    <div class="progress-bar">
                        <div class="fill" style="width: <?= $progress ?>%;"></div>
                    </div>
                    <?php endif; ?>

                    <div class="cycle-actions">
                        <a href="appraisals.php?cycle_id=<?= $cycle['id'] ?>" class="btn btn-primary">View Appraisals</a>

                        <?php if ($cycle['status'] === 'Draft'): ?>
                        <a href="?action=generate&id=<?= $cycle['id'] ?>"
                           class="btn btn-success"
                           onclick="return confirm('Generate appraisals for all active employees?')">
                            Generate Appraisals
                        </a>
                        <a href="?action=Active&id=<?= $cycle['id'] ?>" class="btn btn-info">Activate</a>
                        <a href="?action=delete&id=<?= $cycle['id'] ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Delete this cycle?')">Delete</a>
                        <?php endif; ?>

                        <?php if ($cycle['status'] === 'Active'): ?>
                        <a href="?action=In Review&id=<?= $cycle['id'] ?>" class="btn btn-warning">Move to Review</a>
                        <?php endif; ?>

                        <?php if ($cycle['status'] === 'In Review'): ?>
                        <a href="?action=Completed&id=<?= $cycle['id'] ?>" class="btn btn-success">Mark Completed</a>
                        <?php endif; ?>

                        <?php if ($cycle['status'] !== 'Completed' && $cycle['status'] !== 'Cancelled'): ?>
                        <a href="?action=Cancelled&id=<?= $cycle['id'] ?>"
                           class="btn btn-secondary"
                           onclick="return confirm('Cancel this cycle?')">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-panel">
            <!-- Create Form -->
            <div class="create-form">
                <h3>Create New Cycle</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Cycle Name *</label>
                        <input type="text" name="cycle_name" required placeholder="e.g., FY 2024-25 Annual">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="cycle_type" required>
                            <option value="Annual">Annual</option>
                            <option value="Half-Yearly">Half-Yearly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Probation">Probation</option>
                            <option value="Special">Special</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Period Start *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>Period End *</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label>Self Review Deadline</label>
                        <input type="date" name="self_review_deadline">
                    </div>
                    <div class="form-group">
                        <label>Manager Review Deadline</label>
                        <input type="date" name="manager_review_deadline">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                    <button type="submit" name="create_cycle" class="btn btn-success" style="width: 100%;">
                        Create Cycle
                    </button>
                </form>
            </div>

            <!-- Appraisal Criteria -->
            <div class="criteria-list">
                <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #3498db;">
                    Appraisal Criteria
                </h3>
                <?php if (empty($criteria)): ?>
                <p style="color: #666;">No criteria configured.</p>
                <?php else: ?>
                    <?php foreach ($criteria as $c): ?>
                    <div class="criteria-item">
                        <div>
                            <div class="criteria-name"><?= htmlspecialchars($c['criteria_name']) ?></div>
                            <div class="criteria-category"><?= htmlspecialchars($c['category']) ?></div>
                        </div>
                        <div class="criteria-weight"><?= $c['weightage'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top: 15px;">
                    <a href="appraisal_criteria.php" class="btn btn-secondary" style="width: 100%;">
                        Manage Criteria
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
