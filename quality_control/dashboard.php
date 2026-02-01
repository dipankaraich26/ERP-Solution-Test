<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Get summary statistics
$stats = [];

// Checklists stats
try {
    $stats['active_checklists'] = $pdo->query("SELECT COUNT(*) FROM qc_checklists WHERE status = 'Active'")->fetchColumn();
    $stats['pending_inspections'] = $pdo->query("SELECT COUNT(*) FROM qc_checklist_records WHERE status IN ('Draft', 'Completed') AND overall_result = 'Pending'")->fetchColumn();
} catch (Exception $e) {
    $stats['active_checklists'] = 0;
    $stats['pending_inspections'] = 0;
}

// PPAP stats
try {
    $stats['ppap_in_progress'] = $pdo->query("SELECT COUNT(*) FROM qc_ppap_submissions WHERE overall_status IN ('Draft', 'In Progress')")->fetchColumn();
    $stats['ppap_pending_approval'] = $pdo->query("SELECT COUNT(*) FROM qc_ppap_submissions WHERE overall_status = 'Submitted'")->fetchColumn();
} catch (Exception $e) {
    $stats['ppap_in_progress'] = 0;
    $stats['ppap_pending_approval'] = 0;
}

// Part Submissions stats
try {
    $stats['part_submissions_pending'] = $pdo->query("SELECT COUNT(*) FROM qc_part_submissions WHERE status IN ('Submitted', 'Under Review')")->fetchColumn();
} catch (Exception $e) {
    $stats['part_submissions_pending'] = 0;
}

// Incoming Inspection stats
try {
    $stats['incoming_today'] = $pdo->query("SELECT COUNT(*) FROM qc_incoming_inspections WHERE inspection_date = CURDATE()")->fetchColumn();
    $stats['incoming_pending'] = $pdo->query("SELECT COUNT(*) FROM qc_incoming_inspections WHERE status IN ('Draft', 'In Progress')")->fetchColumn();
    $stats['rejected_this_month'] = $pdo->query("SELECT COUNT(*) FROM qc_incoming_inspections WHERE inspection_result = 'Reject' AND MONTH(inspection_date) = MONTH(CURDATE()) AND YEAR(inspection_date) = YEAR(CURDATE())")->fetchColumn();
} catch (Exception $e) {
    $stats['incoming_today'] = 0;
    $stats['incoming_pending'] = 0;
    $stats['rejected_this_month'] = 0;
}

// Supplier NCR stats
try {
    $stats['open_ncrs'] = $pdo->query("SELECT COUNT(*) FROM qc_supplier_ncrs WHERE status NOT IN ('Closed')")->fetchColumn();
    $stats['critical_ncrs'] = $pdo->query("SELECT COUNT(*) FROM qc_supplier_ncrs WHERE severity = 'Critical' AND status NOT IN ('Closed')")->fetchColumn();
} catch (Exception $e) {
    $stats['open_ncrs'] = 0;
    $stats['critical_ncrs'] = 0;
}

// Supplier Audit stats
try {
    $stats['audits_planned'] = $pdo->query("SELECT COUNT(*) FROM qc_supplier_audits WHERE status = 'Planned'")->fetchColumn();
    $stats['audits_overdue'] = $pdo->query("SELECT COUNT(*) FROM qc_supplier_audits WHERE status = 'Planned' AND audit_date < CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $stats['audits_planned'] = 0;
    $stats['audits_overdue'] = 0;
}

// Calibration stats
try {
    $stats['calibration_due'] = $pdo->query("SELECT COUNT(*) FROM qc_calibration_records WHERE next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Active'")->fetchColumn();
    $stats['calibration_overdue'] = $pdo->query("SELECT COUNT(*) FROM qc_calibration_records WHERE next_calibration_date < CURDATE() AND status = 'Active'")->fetchColumn();
} catch (Exception $e) {
    $stats['calibration_due'] = 0;
    $stats['calibration_overdue'] = 0;
}

// Quality Issues stats
try {
    $stats['quality_issues_open'] = $pdo->query("SELECT COUNT(*) FROM qc_quality_issues WHERE status NOT IN ('Closed', 'Cancelled')")->fetchColumn();
    $stats['quality_issues_critical'] = $pdo->query("SELECT COUNT(*) FROM qc_quality_issues WHERE priority = 'Critical' AND status NOT IN ('Closed', 'Cancelled')")->fetchColumn();
    $stats['quality_issues_overdue'] = $pdo->query("SELECT COUNT(*) FROM qc_quality_issues WHERE target_closure_date < CURDATE() AND status NOT IN ('Closed', 'Cancelled')")->fetchColumn();
    $stats['quality_issues_field'] = $pdo->query("SELECT COUNT(*) FROM qc_quality_issues WHERE issue_type = 'Field Issue' AND status NOT IN ('Closed', 'Cancelled')")->fetchColumn();
    $stats['quality_issues_internal'] = $pdo->query("SELECT COUNT(*) FROM qc_quality_issues WHERE issue_type = 'Internal Issue' AND status NOT IN ('Closed', 'Cancelled')")->fetchColumn();
    $stats['quality_actions_pending'] = $pdo->query("SELECT COUNT(*) FROM qc_issue_actions WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
} catch (Exception $e) {
    $stats['quality_issues_open'] = 0;
    $stats['quality_issues_critical'] = 0;
    $stats['quality_issues_overdue'] = 0;
    $stats['quality_issues_field'] = 0;
    $stats['quality_issues_internal'] = 0;
    $stats['quality_actions_pending'] = 0;
}

// Recent inspections
try {
    $recentInspections = $pdo->query("
        SELECT i.*, s.name as supplier_name
        FROM qc_incoming_inspections i
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        ORDER BY i.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentInspections = [];
}

// Recent NCRs
try {
    $recentNCRs = $pdo->query("
        SELECT n.*, s.name as supplier_name
        FROM qc_supplier_ncrs n
        LEFT JOIN suppliers s ON n.supplier_id = s.id
        WHERE n.status NOT IN ('Closed')
        ORDER BY
            CASE n.severity WHEN 'Critical' THEN 1 WHEN 'Major' THEN 2 ELSE 3 END,
            n.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentNCRs = [];
}

// PPAP submissions in progress
try {
    $activePPAP = $pdo->query("
        SELECT * FROM qc_ppap_submissions
        WHERE overall_status IN ('Draft', 'In Progress', 'Submitted')
        ORDER BY required_date ASC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activePPAP = [];
}

// Recent Quality Issues
try {
    $recentQualityIssues = $pdo->query("
        SELECT * FROM qc_quality_issues
        WHERE status NOT IN ('Closed', 'Cancelled')
        ORDER BY
            CASE priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END,
            issue_date DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentQualityIssues = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quality Control Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .qc-dashboard { padding: 0; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }
        .page-header p { margin: 5px 0 0; color: #666; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.info { border-left-color: #3498db; }

        .stat-icon { font-size: 2em; margin-bottom: 10px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .mini-table th, .mini-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .mini-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .mini-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-pass { background: #d4edda; color: #155724; }
        .status-fail, .status-reject { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-open { background: #cce5ff; color: #004085; }

        .severity-critical { background: #f8d7da; color: #721c24; font-weight: bold; }
        .severity-major { background: #fff3cd; color: #856404; }
        .severity-minor { background: #d1ecf1; color: #0c5460; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .quick-action-btn .action-icon { font-size: 1.8em; margin-bottom: 8px; }

        .alert-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #fff3cd;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
        }
        .alert-item.critical {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .alert-item .alert-icon { font-size: 1.5em; margin-right: 12px; }
        .alert-item .alert-text { flex: 1; }

        body.dark .stat-card, body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .stat-value, body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .mini-table th { background: #34495e; color: #ecf0f1; }
        body.dark .mini-table tr:hover { background: #34495e; }
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

<div class="content qc-dashboard">
    <div class="page-header">
        <div>
            <h1>Quality Control</h1>
            <p>Quality assurance, inspections, and supplier quality management</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="issue_add.php" class="btn btn-primary">+ Quality Issue</a>
            <a href="inspection_add.php" class="btn btn-secondary">+ Inspection</a>
            <a href="ncr_add.php" class="btn btn-secondary">+ NCR</a>
        </div>
    </div>

    <!-- Alert Section -->
    <?php if ($stats['critical_ncrs'] > 0 || $stats['calibration_overdue'] > 0 || $stats['audits_overdue'] > 0 || $stats['quality_issues_critical'] > 0 || $stats['quality_issues_overdue'] > 0): ?>
    <div style="margin-bottom: 25px;">
        <?php if ($stats['quality_issues_critical'] > 0): ?>
        <div class="alert-item critical">
            <span class="alert-icon">🔴</span>
            <span class="alert-text"><strong><?= $stats['quality_issues_critical'] ?> Critical Quality Issue<?= $stats['quality_issues_critical'] > 1 ? 's' : '' ?></strong> require immediate attention</span>
            <a href="issues.php?priority=Critical" class="btn btn-sm" style="background: #dc3545; color: white;">View</a>
        </div>
        <?php endif; ?>
        <?php if ($stats['quality_issues_overdue'] > 0): ?>
        <div class="alert-item">
            <span class="alert-icon">⏰</span>
            <span class="alert-text"><strong><?= $stats['quality_issues_overdue'] ?> Quality Issue<?= $stats['quality_issues_overdue'] > 1 ? 's' : '' ?></strong> overdue for closure</span>
            <a href="issues.php" class="btn btn-sm btn-secondary">View</a>
        </div>
        <?php endif; ?>
        <?php if ($stats['critical_ncrs'] > 0): ?>
        <div class="alert-item critical">
            <span class="alert-icon">⚠️</span>
            <span class="alert-text"><strong><?= $stats['critical_ncrs'] ?> Critical NCR<?= $stats['critical_ncrs'] > 1 ? 's' : '' ?></strong> require immediate attention</span>
            <a href="ncrs.php?severity=Critical" class="btn btn-sm" style="background: #dc3545; color: white;">View</a>
        </div>
        <?php endif; ?>
        <?php if ($stats['calibration_overdue'] > 0): ?>
        <div class="alert-item">
            <span class="alert-icon">📏</span>
            <span class="alert-text"><strong><?= $stats['calibration_overdue'] ?> instrument<?= $stats['calibration_overdue'] > 1 ? 's' : '' ?></strong> overdue for calibration</span>
            <a href="calibration.php?status=overdue" class="btn btn-sm btn-secondary">View</a>
        </div>
        <?php endif; ?>
        <?php if ($stats['audits_overdue'] > 0): ?>
        <div class="alert-item">
            <span class="alert-icon">📋</span>
            <span class="alert-text"><strong><?= $stats['audits_overdue'] ?> supplier audit<?= $stats['audits_overdue'] > 1 ? 's' : '' ?></strong> overdue</span>
            <a href="audits.php" class="btn btn-sm btn-secondary">View</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quality Issues Stats -->
    <div class="stats-grid">
        <div class="stat-card <?= $stats['quality_issues_open'] > 10 ? 'danger' : ($stats['quality_issues_open'] > 0 ? 'warning' : 'success') ?>">
            <div class="stat-icon">🔴</div>
            <div class="stat-value"><?= $stats['quality_issues_open'] ?></div>
            <div class="stat-label">Open Quality Issues</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">🏭</div>
            <div class="stat-value"><?= $stats['quality_issues_internal'] ?></div>
            <div class="stat-label">Internal Issues</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🌍</div>
            <div class="stat-value"><?= $stats['quality_issues_field'] ?></div>
            <div class="stat-label">Field Issues</div>
        </div>
        <div class="stat-card <?= $stats['quality_actions_pending'] > 0 ? 'warning' : 'success' ?>">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?= $stats['quality_actions_pending'] ?></div>
            <div class="stat-label">Pending Actions</div>
        </div>
        <div class="stat-card <?= $stats['open_ncrs'] > 5 ? 'danger' : ($stats['open_ncrs'] > 0 ? 'warning' : 'success') ?>">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?= $stats['open_ncrs'] ?></div>
            <div class="stat-label">Open NCRs</div>
        </div>
        <div class="stat-card <?= $stats['calibration_due'] > 0 ? 'warning' : 'success' ?>">
            <div class="stat-icon">📏</div>
            <div class="stat-value"><?= $stats['calibration_due'] ?></div>
            <div class="stat-label">Calibrations Due</div>
        </div>
    </div>

    <!-- Dashboard Panels -->
    <div class="dashboard-grid">
        <!-- Open Quality Issues -->
        <div class="dashboard-panel">
            <h3>🔴 Open Quality Issues</h3>
            <?php if (empty($recentQualityIssues)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 30px;">No open quality issues. Excellent!</p>
            <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Issue #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentQualityIssues as $qi):
                            $priorityClass = 'status-' . strtolower($qi['priority']);
                            if ($qi['priority'] === 'Critical') $priorityClass = 'severity-critical';
                            elseif ($qi['priority'] === 'High') $priorityClass = 'severity-major';
                        ?>
                        <tr>
                            <td><a href="issue_view.php?id=<?= $qi['id'] ?>"><?= htmlspecialchars($qi['issue_no']) ?></a></td>
                            <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($qi['title']) ?>">
                                <?= htmlspecialchars(strlen($qi['title']) > 25 ? substr($qi['title'], 0, 22) . '...' : $qi['title']) ?>
                            </td>
                            <td>
                                <span style="font-size: 0.8em; color: <?= $qi['issue_type'] === 'Field Issue' ? '#3949ab' : '#ff8f00' ?>;">
                                    <?= $qi['issue_type'] === 'Field Issue' ? 'Field' : ($qi['issue_type'] === 'Internal Issue' ? 'Internal' : 'Other') ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $priorityClass ?>">
                                    <?= $qi['priority'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div style="margin-top: 15px; text-align: right;">
                <a href="issue_add.php" class="btn btn-sm btn-primary">+ New Issue</a>
                <a href="issues.php" class="btn btn-sm btn-secondary" style="margin-left: 5px;">View All</a>
            </div>
        </div>

        <!-- Recent Inspections -->
        <div class="dashboard-panel">
            <h3>📦 Recent Incoming Inspections</h3>
            <?php if (empty($recentInspections)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 30px;">No inspections recorded yet.</p>
            <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Inspection #</th>
                            <th>Supplier</th>
                            <th>Result</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInspections as $insp): ?>
                        <tr>
                            <td><a href="inspection_view.php?id=<?= $insp['id'] ?>"><?= htmlspecialchars($insp['inspection_no']) ?></a></td>
                            <td><?= htmlspecialchars($insp['supplier_name'] ?: 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($insp['inspection_result']) ?>">
                                    <?= $insp['inspection_result'] ?>
                                </span>
                            </td>
                            <td><?= date('d M', strtotime($insp['inspection_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div style="margin-top: 15px; text-align: right;">
                <a href="inspections.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
        </div>

        <!-- Open NCRs -->
        <div class="dashboard-panel">
            <h3>⚠️ Open Supplier NCRs</h3>
            <?php if (empty($recentNCRs)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 30px;">No open NCRs. Great job!</p>
            <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>NCR #</th>
                            <th>Supplier</th>
                            <th>Severity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentNCRs as $ncr): ?>
                        <tr>
                            <td><a href="ncr_view.php?id=<?= $ncr['id'] ?>"><?= htmlspecialchars($ncr['ncr_no']) ?></a></td>
                            <td><?= htmlspecialchars($ncr['supplier_name'] ?: 'N/A') ?></td>
                            <td>
                                <span class="status-badge severity-<?= strtolower($ncr['severity']) ?>">
                                    <?= $ncr['severity'] ?>
                                </span>
                            </td>
                            <td><?= $ncr['status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div style="margin-top: 15px; text-align: right;">
                <a href="ncrs.php" class="btn btn-sm btn-secondary">View All NCRs</a>
            </div>
        </div>

        <!-- Active PPAP -->
        <div class="dashboard-panel">
            <h3>📄 Active PPAP Submissions</h3>
            <?php if (empty($activePPAP)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 30px;">No active PPAP submissions.</p>
            <?php else: ?>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>PPAP #</th>
                            <th>Part</th>
                            <th>Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activePPAP as $ppap): ?>
                        <tr>
                            <td><a href="ppap_view.php?id=<?= $ppap['id'] ?>"><?= htmlspecialchars($ppap['ppap_no']) ?></a></td>
                            <td><?= htmlspecialchars($ppap['part_no']) ?></td>
                            <td><?= $ppap['submission_level'] ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ppap['overall_status'])) ?>">
                                    <?= $ppap['overall_status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div style="margin-top: 15px; text-align: right;">
                <a href="ppap.php" class="btn btn-sm btn-secondary">View All PPAP</a>
            </div>
        </div>

        <!-- Quality Metrics Summary -->
        <div class="dashboard-panel">
            <h3>📊 This Month's Quality Metrics</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #2e7d32;"><?= $stats['incoming_today'] ?></div>
                    <div style="font-size: 0.85em; color: #666;">Inspections Today</div>
                </div>
                <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #c62828;"><?= $stats['rejected_this_month'] ?></div>
                    <div style="font-size: 0.85em; color: #666;">Lots Rejected (Month)</div>
                </div>
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #1565c0;"><?= $stats['audits_planned'] ?></div>
                    <div style="font-size: 0.85em; color: #666;">Audits Planned</div>
                </div>
                <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #e65100;"><?= $stats['ppap_pending_approval'] ?></div>
                    <div style="font-size: 0.85em; color: #666;">PPAP Awaiting Approval</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="issues.php" class="quick-action-btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <div class="action-icon">🔴</div>
            Quality Issues
        </a>
        <a href="checklists.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            Checklists
        </a>
        <a href="inspections.php" class="quick-action-btn">
            <div class="action-icon">🔍</div>
            Inspections
        </a>
        <a href="ppap.php" class="quick-action-btn">
            <div class="action-icon">📄</div>
            PPAP
        </a>
        <a href="part_submissions.php" class="quick-action-btn">
            <div class="action-icon">🔧</div>
            Part Submissions
        </a>
        <a href="ncrs.php" class="quick-action-btn">
            <div class="action-icon">⚠️</div>
            Supplier NCRs
        </a>
        <a href="audits.php" class="quick-action-btn">
            <div class="action-icon">📝</div>
            Supplier Audits
        </a>
        <a href="supplier_ratings.php" class="quick-action-btn">
            <div class="action-icon">⭐</div>
            Supplier Ratings
        </a>
        <a href="calibration.php" class="quick-action-btn">
            <div class="action-icon">📏</div>
            Calibration
        </a>
    </div>
</div>

</body>
</html>
