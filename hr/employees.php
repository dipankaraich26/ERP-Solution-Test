<?php
require "../db.php";
require "../includes/auth.php";
requireLogin();
requirePermission('hr_employees');
require "../includes/header.php";
require "../includes/sidebar.php";

// Filters
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "e.status = ?";
    $params[] = $status;
}
if ($department) {
    $where[] = "e.department = ?";
    $params[] = $department;
}
if ($search) {
    $where[] = "(e.emp_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT e.*, CONCAT(m.first_name, ' ', m.last_name) as manager_name
    FROM employees e
    LEFT JOIN employees m ON e.reporting_to = m.id
    WHERE $whereClause
    ORDER BY e.emp_id
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn(),
    'on_leave' => $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'On Leave'")->fetchColumn(),
];
?>

<div class="content" style="overflow-y: auto; height: calc(100vh - 60px);">
    <style>
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 120px;
        }
        .stat-box .number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-box .label { color: #7f8c8d; }
        .stat-box.active .number { color: #27ae60; }
        .stat-box.leave .number { color: #f39c12; }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filters input[type="text"] { width: 250px; }

        /* Table container for horizontal scroll */
        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .emp-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px; /* Ensures horizontal scroll on small screens */
        }
        .emp-table th, .emp-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            white-space: nowrap;
        }
        .emp-table th {
            background: #f5f5f5;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .emp-table tr:hover { background: #fafafa; }

        .emp-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #ddd;
        }
        .emp-info { display: flex; align-items: center; gap: 12px; }
        .emp-name { font-weight: bold; white-space: nowrap; }
        .emp-id { color: #7f8c8d; font-size: 0.85em; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            white-space: nowrap;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #f8d7da; color: #721c24; }
        .status-On-Leave { background: #fff3cd; color: #856404; }
        .status-Resigned { background: #e2e3e5; color: #383d41; }
        .status-Terminated { background: #f8d7da; color: #721c24; }

        /* Scrollbar styling */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>

    <h1>Employee Management</h1>

    <div class="stats-row">
        <div class="stat-box">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total Employees</div>
        </div>
        <div class="stat-box active">
            <div class="number"><?= $stats['active'] ?></div>
            <div class="label">Active</div>
        </div>
        <div class="stat-box leave">
            <div class="number"><?= $stats['on_leave'] ?></div>
            <div class="label">On Leave</div>
        </div>
    </div>

    <div class="filters">
        <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <input type="text" name="search" placeholder="Search by ID, name, phone..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="status">
                <option value="">All Status</option>
                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="On Leave" <?= $status === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="Resigned" <?= $status === 'Resigned' ? 'selected' : '' ?>>Resigned</option>
                <option value="Terminated" <?= $status === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
            </select>

            <select name="department">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= $department === $dept ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="employees.php" class="btn btn-secondary">Reset</a>
        </form>

        <div style="margin-left: auto; display: flex; gap: 10px;">
            <a href="download_employees.php<?= $status || $department || $search ? '?' . http_build_query(['status' => $status, 'department' => $department, 'search' => $search]) : '' ?>" class="btn btn-success">Download Excel</a>
            <a href="employee_import.php" class="btn btn-primary">Import from Excel</a>
            <a href="employee_add.php" class="btn" style="background: #27ae60; color: white;">+ Add Employee</a>
        </div>
    </div>

    <!-- Table with horizontal scroll -->
    <div class="table-container">
        <table class="emp-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No employees found</td></tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <div class="emp-info">
                                <?php if (!empty($emp['photo_path'])): ?>
                                    <img src="../<?= htmlspecialchars($emp['photo_path']) ?>" class="emp-photo" alt="">
                                <?php else: ?>
                                    <div class="emp-photo" style="display: flex; align-items: center; justify-content: center; color: #fff; background: #3498db;">
                                        <?= strtoupper(substr($emp['first_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="emp-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                    <div class="emp-id"><?= htmlspecialchars($emp['emp_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emp['designation'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emp['phone']) ?></td>
                        <td><?= $emp['date_of_joining'] ? date('d M Y', strtotime($emp['date_of_joining'])) : '-' ?></td>
                        <td>
                            <span class="status-badge status-<?= str_replace(' ', '-', $emp['status']) ?>">
                                <?= $emp['status'] ?>
                            </span>
                        </td>
                        <td style="white-space: nowrap;">
                            <a href="employee_view.php?id=<?= $emp['id'] ?>" class="btn btn-sm">View</a>
                            <a href="employee_edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
