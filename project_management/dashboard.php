<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('project_management');

// Get company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Safe count function
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Safe query function
function safeQuery($pdo, $query) {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Project Stats
$stats = [];

// Project counts
$stats['projects_total'] = safeCount($pdo, "SELECT COUNT(*) FROM projects");
$stats['projects_planning'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status = 'Planning'");
$stats['projects_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status = 'In Progress'");
$stats['projects_completed'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status = 'Completed'");
$stats['projects_on_hold'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status = 'On Hold'");

// Delayed projects
$stats['projects_delayed'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status NOT IN ('Completed', 'Cancelled')");

// Task stats
$stats['tasks_total'] = safeCount($pdo, "SELECT COUNT(*) FROM project_tasks");
$stats['tasks_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM project_tasks WHERE status = 'Pending'");
$stats['tasks_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM project_tasks WHERE status = 'In Progress'");
$stats['tasks_completed'] = safeCount($pdo, "SELECT COUNT(*) FROM project_tasks WHERE status = 'Completed'");

// Engineering Reviews stats
$stats['reviews_total'] = safeCount($pdo, "SELECT COUNT(*) FROM engineering_reviews");
$stats['reviews_scheduled'] = safeCount($pdo, "SELECT COUNT(*) FROM engineering_reviews WHERE status = 'Scheduled'");
$stats['reviews_completed'] = safeCount($pdo, "SELECT COUNT(*) FROM engineering_reviews WHERE status = 'Completed'");
$stats['findings_open'] = safeCount($pdo, "SELECT COUNT(*) FROM review_findings WHERE status IN ('Open', 'In Progress')");

// Change Requests (ECO) stats
$stats['eco_total'] = safeCount($pdo, "SELECT COUNT(*) FROM change_requests");
$stats['eco_draft'] = safeCount($pdo, "SELECT COUNT(*) FROM change_requests WHERE status = 'Draft'");
$stats['eco_under_review'] = safeCount($pdo, "SELECT COUNT(*) FROM change_requests WHERE status = 'Under Review'");
$stats['eco_approved'] = safeCount($pdo, "SELECT COUNT(*) FROM change_requests WHERE status = 'Approved'");
$stats['eco_implemented'] = safeCount($pdo, "SELECT COUNT(*) FROM change_requests WHERE status IN ('Implemented', 'Verified', 'Closed')");

// This month stats
$stats['projects_started_month'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())");
$stats['projects_completed_month'] = safeCount($pdo, "SELECT COUNT(*) FROM projects WHERE status = 'Completed' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");

// Active projects (Planning + In Progress)
$active_projects = safeQuery($pdo, "
    SELECT p.*,
           (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id) as task_count,
           (SELECT COUNT(*) FROM project_tasks pt WHERE pt.project_id = p.id AND pt.status = 'Completed') as completed_tasks
    FROM projects p
    WHERE p.status IN ('Planning', 'In Progress')
    ORDER BY p.end_date
    LIMIT 8
");

// Upcoming Reviews
$upcoming_reviews = safeQuery($pdo, "
    SELECT er.*, p.project_name
    FROM engineering_reviews er
    LEFT JOIN projects p ON er.project_id = p.id
    WHERE er.status = 'Scheduled' AND er.review_date >= CURDATE()
    ORDER BY er.review_date
    LIMIT 8
");

// Recent Change Requests
$recent_ecos = safeQuery($pdo, "
    SELECT cr.*, p.project_name
    FROM change_requests cr
    LEFT JOIN projects p ON cr.project_id = p.id
    WHERE cr.status NOT IN ('Closed', 'Cancelled')
    ORDER BY cr.created_at DESC
    LIMIT 8
");

// Open Review Findings
$open_findings = safeQuery($pdo, "
    SELECT rf.*, er.review_no, er.review_title
    FROM review_findings rf
    JOIN engineering_reviews er ON rf.review_id = er.id
    WHERE rf.status IN ('Open', 'In Progress')
    ORDER BY
        CASE rf.severity
            WHEN 'Critical' THEN 1
            WHEN 'Major' THEN 2
            WHEN 'Minor' THEN 3
            ELSE 4
        END,
        rf.due_date
    LIMIT 10
");

// Delayed projects
$delayed_projects = safeQuery($pdo, "
    SELECT p.*, DATEDIFF(CURDATE(), p.end_date) as days_overdue
    FROM projects p
    WHERE p.end_date < CURDATE() AND p.status NOT IN ('Completed', 'Cancelled')
    ORDER BY days_overdue DESC
    LIMIT 5
");

// Projects by Design Phase
$projects_by_phase = safeQuery($pdo, "
    SELECT
        COALESCE(design_phase, 'Not Set') as design_phase,
        COUNT(*) as count
    FROM projects
    WHERE status NOT IN ('Completed', 'Cancelled')
    GROUP BY design_phase
    ORDER BY
        CASE design_phase
            WHEN 'Concept' THEN 1
            WHEN 'Preliminary Design' THEN 2
            WHEN 'Detailed Design' THEN 3
            WHEN 'Prototype' THEN 4
            WHEN 'Testing' THEN 5
            WHEN 'Production' THEN 6
            WHEN 'Released' THEN 7
            ELSE 8
        END
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Engineering - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .module-header img {
            max-height: 60px;
            max-width: 150px;
            background: white;
            padding: 8px;
            border-radius: 8px;
            object-fit: contain;
        }
        .module-header h1 { margin: 0; font-size: 1.8em; }
        .module-header p { margin: 5px 0 0; opacity: 0.9; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.purple { border-left-color: #9b59b6; }
        .stat-card.teal { border-left-color: #1abc9c; }

        .stat-icon { font-size: 1.8em; margin-bottom: 8px; }
        .stat-value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.85em; margin-top: 5px; }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 1.1em;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 85px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .quick-action-btn.secondary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .quick-action-btn.tertiary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .quick-action-btn .action-icon { font-size: 1.5em; margin-bottom: 6px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .data-table th, .data-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-planning, .status-scheduled { background: #e3f2fd; color: #1565c0; }
        .status-in-progress, .status-under-review { background: #fff3e0; color: #ef6c00; }
        .status-completed, .status-approved, .status-implemented { background: #e8f5e9; color: #2e7d32; }
        .status-on-hold, .status-pending { background: #fafafa; color: #616161; }
        .status-draft { background: #f5f5f5; color: #757575; }
        .status-rejected { background: #ffebee; color: #c62828; }

        .severity-critical { background: #ffebee; color: #c62828; font-weight: bold; }
        .severity-major { background: #fff3e0; color: #e65100; }
        .severity-minor { background: #e3f2fd; color: #1565c0; }
        .severity-observation { background: #f5f5f5; color: #757575; }

        .priority-critical { color: #c62828; font-weight: bold; }
        .priority-high { color: #e65100; font-weight: bold; }
        .priority-medium { color: #1565c0; }
        .priority-low { color: #757575; }

        .progress-bar {
            background: #ecf0f1;
            border-radius: 10px;
            height: 8px;
            width: 100%;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alerts-panel h4 { margin: 0 0 10px 0; color: #856404; }
        .alerts-panel ul { list-style: none; padding: 0; margin: 0; }
        .alerts-panel li { padding: 5px 0; color: #856404; }
        .alerts-panel a { color: #004085; font-weight: 600; }

        .section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .phase-card {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #f8f9fa;
            border-radius: 20px;
            margin: 4px;
            font-size: 0.9em;
        }
        .phase-count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 0.85em;
        }

        .deadline-soon { color: #e74c3c; font-weight: bold; }
        .deadline-ok { color: #27ae60; }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
        body.dark .phase-card { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

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
    <!-- Module Header -->
    <div class="module-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php
                $logo_path = $settings['logo_path'];
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1>Product Engineering</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['projects_delayed'] > 0 || $stats['findings_open'] > 5 || $stats['eco_under_review'] > 0): ?>
    <div class="alerts-panel">
        <h4>Attention Required</h4>
        <ul>
            <?php if ($stats['projects_delayed'] > 0): ?>
            <li><a href="/project_management/index.php?filter=delayed"><?= $stats['projects_delayed'] ?> Delayed Project<?= $stats['projects_delayed'] > 1 ? 's' : '' ?></a> - Past deadline</li>
            <?php endif; ?>
            <?php if ($stats['findings_open'] > 5): ?>
            <li><a href="/project_management/findings.php"><?= $stats['findings_open'] ?> Open Findings</a> - Review action items pending</li>
            <?php endif; ?>
            <?php if ($stats['eco_under_review'] > 0): ?>
            <li><a href="/project_management/change_requests.php?status=Under Review"><?= $stats['eco_under_review'] ?> ECO<?= $stats['eco_under_review'] > 1 ? 's' : '' ?> Under Review</a> - Awaiting approval</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/project_management/add.php" class="quick-action-btn">
            <div class="action-icon">+</div>
            New Project
        </a>
        <a href="/project_management/review_add.php" class="quick-action-btn secondary">
            <div class="action-icon">+</div>
            Schedule Review
        </a>
        <a href="/project_management/eco_add.php" class="quick-action-btn tertiary">
            <div class="action-icon">+</div>
            New Change Request
        </a>
        <a href="/project_management/part_id_series.php" class="quick-action-btn">
            <div class="action-icon">#</div>
            Part ID Series
        </a>
        <a href="/project_management/index.php" class="quick-action-btn">
            <div class="action-icon">P</div>
            All Projects
        </a>
        <a href="/project_management/reviews.php" class="quick-action-btn secondary">
            <div class="action-icon">R</div>
            All Reviews
        </a>
        <a href="/project_management/change_requests.php" class="quick-action-btn tertiary">
            <div class="action-icon">E</div>
            All ECOs
        </a>
    </div>

    <!-- Project Statistics -->
    <div class="section-title">Project Overview</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">P</div>
            <div class="stat-value"><?= $stats['projects_total'] ?></div>
            <div class="stat-label">Total Projects</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">~</div>
            <div class="stat-value"><?= $stats['projects_planning'] ?></div>
            <div class="stat-label">Planning</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">></div>
            <div class="stat-value"><?= $stats['projects_in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">OK</div>
            <div class="stat-value"><?= $stats['projects_completed'] ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <?php if ($stats['projects_delayed'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">!</div>
            <div class="stat-value"><?= $stats['projects_delayed'] ?></div>
            <div class="stat-label">Delayed</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Engineering Reviews & ECO Statistics -->
    <div class="section-title">Engineering Reviews & Change Requests</div>
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-icon">R</div>
            <div class="stat-value"><?= $stats['reviews_total'] ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">S</div>
            <div class="stat-value"><?= $stats['reviews_scheduled'] ?></div>
            <div class="stat-label">Scheduled</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">F</div>
            <div class="stat-value"><?= $stats['findings_open'] ?></div>
            <div class="stat-label">Open Findings</div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon">E</div>
            <div class="stat-value"><?= $stats['eco_total'] ?></div>
            <div class="stat-label">Total ECOs</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">?</div>
            <div class="stat-value"><?= $stats['eco_under_review'] ?></div>
            <div class="stat-label">ECO Under Review</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">OK</div>
            <div class="stat-value"><?= $stats['eco_implemented'] ?></div>
            <div class="stat-label">ECO Implemented</div>
        </div>
    </div>

    <!-- Projects by Design Phase -->
    <?php if (!empty($projects_by_phase)): ?>
    <div class="section-title">Active Projects by Design Phase</div>
    <div style="margin-bottom: 25px; background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <?php foreach ($projects_by_phase as $phase): ?>
        <span class="phase-card">
            <?= htmlspecialchars($phase['design_phase']) ?>
            <span class="phase-count"><?= $phase['count'] ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-row">
        <!-- Active Projects -->
        <div class="dashboard-panel">
            <h3>Active Projects</h3>
            <?php if (empty($active_projects)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No active projects.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Phase</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_projects as $project):
                            $progress = $project['task_count'] > 0 ? round(($project['completed_tasks'] / $project['task_count']) * 100) : (int)($project['progress_percentage'] ?? 0);
                        ?>
                        <tr>
                            <td><a href="/project_management/view.php?id=<?= $project['id'] ?>"><?= htmlspecialchars(substr($project['project_name'], 0, 25)) ?><?= strlen($project['project_name']) > 25 ? '...' : '' ?></a></td>
                            <td><span class="status-badge status-planning"><?= htmlspecialchars($project['design_phase'] ?? 'N/A') ?></span></td>
                            <td style="width: 100px;">
                                <div class="progress-bar">
                                    <div class="progress-bar-fill" style="width: <?= $progress ?>%;"></div>
                                </div>
                                <small><?= $progress ?>%</small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="/project_management/index.php?status=In Progress" class="btn btn-sm">View All</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Reviews -->
        <div class="dashboard-panel">
            <h3>Upcoming Engineering Reviews</h3>
            <?php if (empty($upcoming_reviews)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No upcoming reviews scheduled.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Review</th>
                            <th>Type</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_reviews as $review):
                            $days_until = (strtotime($review['review_date']) - strtotime('today')) / 86400;
                        ?>
                        <tr>
                            <td>
                                <a href="/project_management/review_view.php?id=<?= $review['id'] ?>">
                                    <?= htmlspecialchars(substr($review['review_title'], 0, 20)) ?>
                                </a>
                                <?php if ($review['project_name']): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars(substr($review['project_name'], 0, 20)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge status-scheduled"><?= htmlspecialchars(substr($review['review_type'], 0, 12)) ?></span></td>
                            <td class="<?= $days_until <= 3 ? 'deadline-soon' : '' ?>"><?= date('d M', strtotime($review['review_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="/project_management/reviews.php" class="btn btn-sm">View All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Change Requests -->
        <div class="dashboard-panel">
            <h3>Recent Change Requests (ECO)</h3>
            <?php if (empty($recent_ecos)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No active change requests.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ECO #</th>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_ecos as $eco): ?>
                        <tr>
                            <td><a href="/project_management/eco_view.php?id=<?= $eco['id'] ?>"><?= htmlspecialchars($eco['eco_no']) ?></a></td>
                            <td><?= htmlspecialchars(substr($eco['title'], 0, 25)) ?><?= strlen($eco['title']) > 25 ? '...' : '' ?></td>
                            <td><span class="priority-<?= strtolower($eco['priority']) ?>"><?= $eco['priority'] ?></span></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $eco['status'])) ?>"><?= $eco['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="/project_management/change_requests.php" class="btn btn-sm">View All</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Open Findings -->
        <div class="dashboard-panel">
            <h3>Open Review Findings</h3>
            <?php if (empty($open_findings)): ?>
                <p style="color: #27ae60; text-align: center; padding: 20px;">All review findings resolved!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Finding</th>
                            <th>Severity</th>
                            <th>Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_findings as $finding): ?>
                        <tr>
                            <td>
                                <a href="/project_management/review_view.php?id=<?= $finding['review_id'] ?>"><?= htmlspecialchars(substr($finding['title'], 0, 25)) ?></a>
                                <br><small style="color: #666;"><?= htmlspecialchars($finding['review_no']) ?></small>
                            </td>
                            <td><span class="status-badge severity-<?= strtolower($finding['severity']) ?>"><?= $finding['severity'] ?></span></td>
                            <td><?= $finding['due_date'] ? date('d M', strtotime($finding['due_date'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="/project_management/findings.php" class="btn btn-sm">View All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delayed Projects -->
    <?php if (!empty($delayed_projects)): ?>
    <div class="dashboard-row">
        <div class="dashboard-panel" style="border-left: 4px solid #e74c3c;">
            <h3 style="color: #e74c3c; border-bottom-color: #e74c3c;">Delayed Projects</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delayed_projects as $project): ?>
                    <tr>
                        <td><a href="/project_management/view.php?id=<?= $project['id'] ?>"><?= htmlspecialchars($project['project_name']) ?></a></td>
                        <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $project['status'])) ?>"><?= $project['status'] ?></span></td>
                        <td style="color: #e74c3c; font-weight: bold;"><?= $project['days_overdue'] ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/project_management/index.php" class="quick-action-btn">
            <div class="action-icon">P</div>
            All Projects
        </a>
        <a href="/project_management/reviews.php" class="quick-action-btn secondary">
            <div class="action-icon">R</div>
            Engineering Reviews
        </a>
        <a href="/project_management/change_requests.php" class="quick-action-btn tertiary">
            <div class="action-icon">E</div>
            Change Requests
        </a>
        <a href="/project_management/findings.php" class="quick-action-btn">
            <div class="action-icon">F</div>
            All Findings
        </a>
        <a href="/project_management/part_id_series.php" class="quick-action-btn secondary">
            <div class="action-icon">#</div>
            Part ID Series
        </a>
    </div>
</div>

</body>
</html>
