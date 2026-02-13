<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('service_complaints');

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

// Service Stats
$stats = [];

// Complaint stats
$stats['complaints_total'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints");
$stats['complaints_open'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE status = 'Open'");
$stats['complaints_in_progress'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'");
$stats['complaints_resolved'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'");
$stats['complaints_closed'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE status = 'Closed'");

// This month stats
$stats['complaints_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['resolved_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE status IN ('Resolved', 'Closed') AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())");

// Technician stats
$stats['technicians_total'] = safeCount($pdo, "SELECT COUNT(*) FROM technicians");
$stats['technicians_available'] = safeCount($pdo, "SELECT COUNT(*) FROM technicians WHERE status = 'Available'");

// Priority breakdown
$stats['complaints_high'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE priority = 'High' AND status NOT IN ('Resolved', 'Closed')");
$stats['complaints_medium'] = safeCount($pdo, "SELECT COUNT(*) FROM complaints WHERE priority = 'Medium' AND status NOT IN ('Resolved', 'Closed')");

// Recent complaints
$recent_complaints = safeQuery($pdo, "
    SELECT c.*, t.technician_name
    FROM complaints c
    LEFT JOIN technicians t ON c.technician_id = t.id
    ORDER BY c.created_at DESC
    LIMIT 10
");

// Open complaints
$open_complaints = safeQuery($pdo, "
    SELECT c.*, t.technician_name
    FROM complaints c
    LEFT JOIN technicians t ON c.technician_id = t.id
    WHERE c.status = 'Open'
    ORDER BY
        CASE c.priority
            WHEN 'High' THEN 1
            WHEN 'Medium' THEN 2
            ELSE 3
        END,
        c.created_at
    LIMIT 10
");

// In progress complaints
$in_progress_complaints = safeQuery($pdo, "
    SELECT c.*, t.technician_name
    FROM complaints c
    LEFT JOIN technicians t ON c.technician_id = t.id
    WHERE c.status = 'In Progress'
    ORDER BY c.created_at
    LIMIT 10
");

// Technicians with assignments
$technician_workload = safeQuery($pdo, "
    SELECT t.id, t.technician_name, t.status,
           (SELECT COUNT(*) FROM complaints c WHERE c.technician_id = t.id AND c.status IN ('Open', 'In Progress')) as active_complaints
    FROM technicians t
    ORDER BY active_complaints DESC
    LIMIT 8
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #6c5ce7;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }

        .stat-icon { font-size: 2em; margin-bottom: 10px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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
            border-bottom: 2px solid #6c5ce7;
            padding-bottom: 10px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(108, 92, 231, 0.4);
        }
        .quick-action-btn .action-icon { font-size: 1.6em; margin-bottom: 8px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
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
        }
        .status-open { background: #ffebee; color: #c62828; }
        .status-in-progress { background: #e3f2fd; color: #1565c0; }
        .status-resolved { background: #e8f5e9; color: #2e7d32; }
        .status-closed { background: #fafafa; color: #616161; }
        .status-available { background: #e8f5e9; color: #2e7d32; }
        .status-busy { background: #fff3e0; color: #ef6c00; }

        .priority-high { color: #e74c3c; font-weight: bold; }
        .priority-medium { color: #f39c12; }
        .priority-low { color: #27ae60; }

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
        }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "☀️ Light Mode" : "🌙 Dark Mode";
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
            <h1>Service Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['complaints_open'] > 0 || $stats['complaints_high'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Service Alerts</h4>
        <ul>
            <?php if ($stats['complaints_high'] > 0): ?>
            <li><a href="/service/complaints.php?priority=High"><?= $stats['complaints_high'] ?> High Priority Complaint<?= $stats['complaints_high'] > 1 ? 's' : '' ?></a> - Requires immediate attention</li>
            <?php endif; ?>
            <?php if ($stats['complaints_open'] > 0): ?>
            <li><a href="/service/complaints.php?status=Open"><?= $stats['complaints_open'] ?> Open Complaint<?= $stats['complaints_open'] > 1 ? 's' : '' ?></a> - Awaiting assignment</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/service/complaint_add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New Complaint
        </a>
        <a href="/service/complaints.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            All Complaints
        </a>
        <a href="/service/technicians.php" class="quick-action-btn">
            <div class="action-icon">👷</div>
            Technicians
        </a>
        <a href="/service/analytics.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Analytics
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Service Overview</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?= $stats['complaints_total'] ?></div>
            <div class="stat-label">Total Complaints</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon">🔴</div>
            <div class="stat-value"><?= $stats['complaints_open'] ?></div>
            <div class="stat-label">Open</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">🔧</div>
            <div class="stat-value"><?= $stats['complaints_in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['complaints_resolved'] ?></div>
            <div class="stat-label">Resolved</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">👷</div>
            <div class="stat-value"><?= $stats['technicians_total'] ?></div>
            <div class="stat-label">Technicians</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✓</div>
            <div class="stat-value"><?= $stats['technicians_available'] ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?= $stats['complaints_this_month'] ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Open Complaints -->
        <div class="dashboard-panel">
            <h3>🔴 Open Complaints</h3>
            <?php if (empty($open_complaints)): ?>
                <p style="color: #27ae60;">No open complaints. All clear!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_complaints as $complaint): ?>
                        <tr>
                            <td><a href="/service/complaint_view.php?id=<?= $complaint['id'] ?>">#<?= $complaint['id'] ?></a></td>
                            <td><?= htmlspecialchars($complaint['customer_name'] ?? 'N/A') ?></td>
                            <td class="priority-<?= strtolower($complaint['priority'] ?? 'low') ?>"><?= $complaint['priority'] ?? 'Normal' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- In Progress Complaints -->
        <div class="dashboard-panel">
            <h3>🔧 In Progress</h3>
            <?php if (empty($in_progress_complaints)): ?>
                <p style="color: #7f8c8d;">No complaints in progress.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Technician</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($in_progress_complaints as $complaint): ?>
                        <tr>
                            <td><a href="/service/complaint_view.php?id=<?= $complaint['id'] ?>">#<?= $complaint['id'] ?></a></td>
                            <td><?= htmlspecialchars($complaint['customer_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($complaint['technician_name'] ?? 'Unassigned') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Complaints -->
        <div class="dashboard-panel">
            <h3>📝 Recent Complaints</h3>
            <?php if (empty($recent_complaints)): ?>
                <p style="color: #7f8c8d;">No complaints found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_complaints as $complaint): ?>
                        <tr>
                            <td><a href="/service/complaint_view.php?id=<?= $complaint['id'] ?>">#<?= $complaint['id'] ?></a></td>
                            <td><?= htmlspecialchars($complaint['customer_name'] ?? 'N/A') ?></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $complaint['status'])) ?>"><?= $complaint['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Technician Workload -->
        <div class="dashboard-panel">
            <h3>👷 Technician Workload</h3>
            <?php if (empty($technician_workload)): ?>
                <p style="color: #7f8c8d;">No technicians found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th>Status</th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($technician_workload as $tech): ?>
                        <tr>
                            <td><?= htmlspecialchars($tech['technician_name']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($tech['status']) ?>"><?= $tech['status'] ?></span></td>
                            <td><?= $tech['active_complaints'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/service/complaints.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            All Complaints
        </a>
        <a href="/service/technicians.php" class="quick-action-btn">
            <div class="action-icon">👷</div>
            Technicians
        </a>
        <a href="/service/analytics.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Analytics
        </a>
    </div>
</div>

</body>
</html>
