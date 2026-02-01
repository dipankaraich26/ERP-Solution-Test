<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

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

// HR Stats
$stats = [];

// Employee stats
$stats['employees_total'] = safeCount($pdo, "SELECT COUNT(*) FROM employees");
$stats['employees_active'] = safeCount($pdo, "SELECT COUNT(*) FROM employees WHERE status = 'Active'");
$stats['employees_on_leave'] = safeCount($pdo, "SELECT COUNT(*) FROM employees WHERE status = 'On Leave'");

// Attendance stats
$stats['attendance_today'] = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURDATE()");
$stats['attendance_month'] = safeCount($pdo, "SELECT COUNT(DISTINCT employee_id, DATE(check_in)) FROM attendance WHERE MONTH(check_in) = MONTH(CURDATE()) AND YEAR(check_in) = YEAR(CURDATE())");
$stats['late_today'] = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURDATE() AND TIME(check_in) > '09:30:00'");

// Payroll stats
$stats['payroll_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM payroll WHERE status = 'Pending'");
$stats['payroll_processed_month'] = safeCount($pdo, "SELECT COUNT(*) FROM payroll WHERE status = 'Processed' AND MONTH(pay_date) = MONTH(CURDATE()) AND YEAR(pay_date) = YEAR(CURDATE())");
$stats['total_salary_month'] = safeCount($pdo, "SELECT COALESCE(SUM(net_salary), 0) FROM payroll WHERE MONTH(pay_date) = MONTH(CURDATE()) AND YEAR(pay_date) = YEAR(CURDATE())");

// Leave stats
$stats['leave_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'");
$stats['on_leave_today'] = safeCount($pdo, "SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date");
$stats['leave_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM leave_requests WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");

// Department stats
$stats['departments'] = safeCount($pdo, "SELECT COUNT(DISTINCT department) FROM employees WHERE department IS NOT NULL AND department != ''");

// Recent employees
$recent_employees = safeQuery($pdo, "
    SELECT id, emp_id, full_name, department, designation, status, joining_date
    FROM employees
    ORDER BY id DESC
    LIMIT 10
");

// Today's attendance
$todays_attendance = safeQuery($pdo, "
    SELECT a.*, e.full_name, e.emp_id, e.department
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE DATE(a.check_in) = CURDATE()
    ORDER BY a.check_in DESC
    LIMIT 15
");

// Employees by department
$employees_by_dept = safeQuery($pdo, "
    SELECT department, COUNT(*) as count
    FROM employees
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY count DESC
    LIMIT 8
");

// Pending payroll
$pending_payroll = safeQuery($pdo, "
    SELECT p.*, e.full_name, e.emp_id
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    WHERE p.status = 'Pending'
    ORDER BY p.pay_date
    LIMIT 10
");

// Upcoming birthdays (next 30 days)
$upcoming_birthdays = safeQuery($pdo, "
    SELECT emp_id, full_name, date_of_birth, department
    FROM employees
    WHERE status = 'Active'
      AND DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(date_of_birth, '%m-%d')
    LIMIT 5
");

// Pending leave requests
$pending_leaves = safeQuery($pdo, "
    SELECT lr.*, e.full_name, e.emp_id, e.department, lt.leave_code, lt.leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'Pending'
    ORDER BY lr.created_at DESC
    LIMIT 10
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>HR Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
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
            border-left: 4px solid #30cfd0;
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
            border-bottom: 2px solid #30cfd0;
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
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
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
            box-shadow: 0 4px 12px rgba(48, 207, 208, 0.4);
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
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-on-leave { background: #fff3e0; color: #ef6c00; }
        .status-inactive { background: #fafafa; color: #616161; }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-processed { background: #e8f5e9; color: #2e7d32; }

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

        .attendance-present { color: #27ae60; }
        .attendance-late { color: #e74c3c; }

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
            <h1>HR Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['payroll_pending'] > 0 || $stats['leave_pending'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ HR Alerts</h4>
        <ul>
            <?php if ($stats['leave_pending'] > 0): ?>
            <li><a href="/hr/leaves.php?status=Pending"><?= $stats['leave_pending'] ?> Pending Leave Request<?= $stats['leave_pending'] > 1 ? 's' : '' ?></a> - Awaiting approval</li>
            <?php endif; ?>
            <?php if ($stats['payroll_pending'] > 0): ?>
            <li><a href="/hr/payroll.php"><?= $stats['payroll_pending'] ?> Pending Payroll<?= $stats['payroll_pending'] > 1 ? 's' : '' ?></a> - Process salaries</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/hr/employee_add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            Add Employee
        </a>
        <a href="/hr/employee_import.php" class="quick-action-btn">
            <div class="action-icon">📥</div>
            Import Employees
        </a>
        <a href="/hr/attendance_mark.php" class="quick-action-btn">
            <div class="action-icon">✓</div>
            Mark Attendance
        </a>
        <a href="/hr/payroll_generate.php" class="quick-action-btn">
            <div class="action-icon">💵</div>
            Generate Payroll
        </a>
        <a href="/hr/holidays.php" class="quick-action-btn">
            <div class="action-icon">📅</div>
            Holidays
        </a>
        <a href="/hr/leave_apply.php" class="quick-action-btn">
            <div class="action-icon">🏖️</div>
            Apply Leave
        </a>
        <a href="/hr/leaves.php?status=Pending" class="quick-action-btn">
            <div class="action-icon">✔️</div>
            Approve Leaves
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">HR Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $stats['employees_total'] ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['employees_active'] ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">🏖️</div>
            <div class="stat-value"><?= $stats['on_leave_today'] ?></div>
            <div class="stat-label">On Leave Today</div>
        </div>
        <?php if ($stats['leave_pending'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?= $stats['leave_pending'] ?></div>
            <div class="stat-label">Pending Leaves</div>
        </div>
        <?php endif; ?>
        <div class="stat-card success">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?= $stats['attendance_today'] ?></div>
            <div class="stat-label">Present Today</div>
        </div>
        <?php if ($stats['late_today'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">⏰</div>
            <div class="stat-value"><?= $stats['late_today'] ?></div>
            <div class="stat-label">Late Today</div>
        </div>
        <?php endif; ?>
        <div class="stat-card info">
            <div class="stat-icon">🏢</div>
            <div class="stat-value"><?= $stats['departments'] ?></div>
            <div class="stat-label">Departments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?= number_format($stats['total_salary_month']) ?></div>
            <div class="stat-label">Salary (Month)</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Today's Attendance -->
        <div class="dashboard-panel">
            <h3>📋 Today's Attendance</h3>
            <?php if (empty($todays_attendance)): ?>
                <p style="color: #7f8c8d;">No attendance recorded today.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todays_attendance as $att):
                            $is_late = strtotime($att['check_in']) > strtotime(date('Y-m-d 09:30:00'));
                        ?>
                        <tr>
                            <td><a href="/hr/employee_view.php?id=<?= $att['employee_id'] ?>"><?= htmlspecialchars($att['full_name']) ?></a></td>
                            <td><?= htmlspecialchars($att['department'] ?? 'N/A') ?></td>
                            <td class="<?= $is_late ? 'attendance-late' : 'attendance-present' ?>">
                                <?= date('h:i A', strtotime($att['check_in'])) ?>
                                <?= $is_late ? ' (Late)' : '' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Employees by Department -->
        <div class="dashboard-panel">
            <h3>🏢 Employees by Department</h3>
            <?php if (empty($employees_by_dept)): ?>
                <p style="color: #7f8c8d;">No department data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Employees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees_by_dept as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['department']) ?></td>
                            <td><?= $dept['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Employees -->
        <div class="dashboard-panel">
            <h3>🆕 Recent Employees</h3>
            <?php if (empty($recent_employees)): ?>
                <p style="color: #7f8c8d;">No employees found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_employees as $emp): ?>
                        <tr>
                            <td><a href="/hr/employee_view.php?id=<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></a></td>
                            <td><?= htmlspecialchars($emp['designation'] ?? 'N/A') ?></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $emp['status'])) ?>"><?= $emp['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pending Payroll -->
        <div class="dashboard-panel">
            <h3>💵 Pending Payroll</h3>
            <?php if (empty($pending_payroll)): ?>
                <p style="color: #27ae60;">All payroll processed!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Amount</th>
                            <th>Pay Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_payroll as $pay): ?>
                        <tr>
                            <td><?= htmlspecialchars($pay['full_name']) ?></td>
                            <td>₹<?= number_format($pay['net_salary'], 2) ?></td>
                            <td><?= date('d M Y', strtotime($pay['pay_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leave Requests Panel -->
    <?php if (!empty($pending_leaves)): ?>
    <div class="dashboard-row">
        <div class="dashboard-panel" style="grid-column: span 2;">
            <h3>🏖️ Pending Leave Requests</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_leaves as $leave): ?>
                    <tr>
                        <td><a href="/hr/employee_view.php?id=<?= $leave['employee_id'] ?>"><?= htmlspecialchars($leave['full_name']) ?></a></td>
                        <td><?= htmlspecialchars($leave['department'] ?? 'N/A') ?></td>
                        <td><span class="status-badge" style="background: #e3f2fd; color: #1565c0;"><?= htmlspecialchars($leave['leave_code']) ?></span></td>
                        <td><?= date('d M', strtotime($leave['start_date'])) ?></td>
                        <td><?= date('d M', strtotime($leave['end_date'])) ?></td>
                        <td><?= number_format($leave['total_days'], 1) ?></td>
                        <td><a href="/hr/leave_view.php?id=<?= $leave['id'] ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.85em;">Review</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px; text-align: right;">
                <a href="/hr/leaves.php?status=Pending">View all pending requests →</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/hr/employees.php" class="quick-action-btn">
            <div class="action-icon">👥</div>
            All Employees
        </a>
        <a href="/hr/attendance.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            Attendance
        </a>
        <a href="/hr/leaves.php" class="quick-action-btn">
            <div class="action-icon">🏖️</div>
            Leave Management
        </a>
        <a href="/hr/leave_balance.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Leave Balances
        </a>
        <a href="/hr/payroll.php" class="quick-action-btn">
            <div class="action-icon">💵</div>
            Payroll
        </a>
    </div>
</div>

</body>
</html>
