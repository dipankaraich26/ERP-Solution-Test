<?php
/**
 * Employee Leave Balance Management
 * View and edit leave balances for all employees
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$currentYear = date('Y');
$selectedYear = $_GET['year'] ?? $currentYear;
$department = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';
$tableError = false;

// Check if required tables exist with proper structure
$leaveTypesOk = false;
$leaveBalancesOk = false;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'leave_types'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
        $leaveTypesOk = in_array('leave_code', $cols) && in_array('is_active', $cols);
    }

    $check = $pdo->query("SHOW TABLES LIKE 'leave_balances'")->fetch();
    if ($check) {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_balances")->fetchAll(PDO::FETCH_COLUMN);
        $leaveBalancesOk = in_array('balance', $cols) && in_array('employee_id', $cols);
    }
} catch (PDOException $e) {
    // Tables don't exist
}

$tableError = !$leaveTypesOk || !$leaveBalancesOk;

// Handle balance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance']) && !$tableError) {
    $empId = $_POST['employee_id'] ?? 0;
    $leaveTypeId = $_POST['leave_type_id'] ?? 0;
    $allocated = floatval($_POST['allocated'] ?? 0);

    if ($empId && $leaveTypeId) {
        // Get current used balance
        $current = $pdo->prepare("
            SELECT used FROM leave_balances
            WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        ");
        $current->execute([$empId, $leaveTypeId, $selectedYear]);
        $used = $current->fetchColumn() ?: 0;

        // Update or insert balance
        $stmt = $pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated, used, balance)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                allocated = VALUES(allocated),
                balance = VALUES(allocated) - used
        ");
        $stmt->execute([$empId, $leaveTypeId, $selectedYear, $allocated, $used, $allocated - $used]);

        setModal("Success", "Leave balance updated successfully!");
        header("Location: leave_balance.php?year=$selectedYear&department=" . urlencode($department) . "&search=" . urlencode($search));
        exit;
    }
}

// Initialize balances for new year
if (isset($_GET['init_year']) && !$tableError) {
    $initYear = intval($_GET['init_year']);
    $leaveTypesInit = $pdo->query("SELECT id, max_days_per_year FROM leave_types WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $employeesInit = $pdo->query("SELECT id FROM employees WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);

    $insertBalance = $pdo->prepare("
        INSERT IGNORE INTO leave_balances (employee_id, leave_type_id, year, allocated, balance)
        VALUES (?, ?, ?, ?, ?)
    ");

    $count = 0;
    foreach ($employeesInit as $empId) {
        foreach ($leaveTypesInit as $lt) {
            $insertBalance->execute([$empId, $lt['id'], $initYear, $lt['max_days_per_year'], $lt['max_days_per_year']]);
            if ($insertBalance->rowCount() > 0) $count++;
        }
    }

    setModal("Success", "Initialized $count leave balances for year $initYear");
    header("Location: leave_balance.php?year=$initYear");
    exit;
}

// Fetch leave types
$leaveTypes = [];
if (!$tableError) {
    try {
        $leaveTypes = $pdo->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_code")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableError = true;
    }
}

// Fetch departments
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND status = 'Active' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Build employee query
$where = ["e.status = 'Active'"];
$params = [];

if ($department) {
    $where[] = "e.department = ?";
    $params[] = $department;
}
if ($search) {
    $where[] = "(e.emp_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$employees = $pdo->prepare("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.department
    FROM employees e
    WHERE $whereClause
    ORDER BY e.department, e.first_name
");
$employees->execute($params);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

// Fetch balances for all employees
$balances = [];
if (!empty($employees)) {
    $empIds = array_column($employees, 'id');
    $placeholders = str_repeat('?,', count($empIds) - 1) . '?';

    $balanceStmt = $pdo->prepare("
        SELECT employee_id, leave_type_id, allocated, used, balance
        FROM leave_balances
        WHERE employee_id IN ($placeholders) AND year = ?
    ");
    $balanceStmt->execute(array_merge($empIds, [$selectedYear]));

    foreach ($balanceStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $balances[$b['employee_id']][$b['leave_type_id']] = $b;
    }
}

// Available years
$years = range($currentYear - 2, $currentYear + 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Balances - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-size: 0.9em;
        }
        .balance-table th, .balance-table td {
            padding: 10px 8px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        .balance-table th {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85em;
        }
        .balance-table th.employee-col {
            text-align: left;
            min-width: 180px;
        }
        .balance-table td.employee-col {
            text-align: left;
            background: #f8f9fa;
        }
        .balance-table tr:hover { background: #f0f8ff; }
        .balance-cell {
            cursor: pointer;
            position: relative;
        }
        .balance-cell:hover { background: #e3f2fd; }
        .balance-available { color: #27ae60; font-weight: bold; }
        .balance-low { color: #f39c12; font-weight: bold; }
        .balance-zero { color: #e74c3c; font-weight: bold; }
        .balance-details {
            font-size: 0.75em;
            color: #666;
        }
        .edit-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .edit-modal.show { display: flex; }
        .edit-modal-content {
            background: white;
            padding: 25px;
            border-radius: 10px;
            min-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .edit-modal h3 { margin: 0 0 20px 0; color: #2c3e50; }
        .edit-modal .form-group { margin-bottom: 15px; }
        .edit-modal label { display: block; margin-bottom: 5px; font-weight: 600; }
        .edit-modal input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .edit-modal-btns { display: flex; gap: 10px; margin-top: 20px; }
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            min-width: 150px;
        }
        .stat-box .label { font-size: 0.85em; color: #666; }
        .stat-box .value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Leave Balances - <?= $selectedYear ?></h1>
        <div style="display: flex; gap: 10px;">
            <a href="leave_apply.php" class="btn btn-primary">+ Apply Leave</a>
            <a href="leaves.php" class="btn btn-secondary">View Requests</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="label">Total Employees</div>
            <div class="value"><?= count($employees) ?></div>
        </div>
        <div class="stat-box">
            <div class="label">Leave Types</div>
            <div class="value"><?= count($leaveTypes) ?></div>
        </div>
        <div class="stat-box">
            <div class="label">Year</div>
            <div class="value"><?= $selectedYear ?></div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters">
        <select name="year" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>

        <select name="department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="search" placeholder="Search employee..." value="<?= htmlspecialchars($search) ?>">

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="leave_balance.php?year=<?= $selectedYear ?>" class="btn btn-secondary">Reset</a>

        <?php if ($selectedYear == $currentYear): ?>
            <a href="?init_year=<?= $currentYear ?>" class="btn btn-success"
               onclick="return confirm('Initialize balances for all active employees for <?= $currentYear ?>?')"
               style="margin-left: auto;">
                Initialize <?= $currentYear ?> Balances
            </a>
        <?php endif; ?>
    </form>

    <!-- Balance Table -->
    <?php if ($tableError): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; color: #856404;">
            <strong>Setup Required</strong><br>
            Leave management tables are not properly configured.<br><br>
            <a href="/admin/setup_leave_management.php" class="btn btn-primary">Run Setup Script</a>
        </div>
    <?php elseif (empty($leaveTypes)): ?>
        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; color: #856404;">
            No leave types configured. <a href="/admin/setup_leave_management.php">Run setup</a> first.
        </div>
    <?php elseif (empty($employees)): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center; color: #666;">
            No employees found matching your filters.
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="balance-table">
                <thead>
                    <tr>
                        <th class="employee-col">Employee</th>
                        <th>Dept</th>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <th title="<?= htmlspecialchars($lt['leave_type_name']) ?>">
                                <?= htmlspecialchars($lt['leave_code']) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td class="employee-col">
                            <a href="employee_view.php?id=<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['emp_id']) ?> - <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($emp['department'] ?? '-') ?></td>
                        <?php foreach ($leaveTypes as $lt):
                            $bal = $balances[$emp['id']][$lt['id']] ?? null;
                            $allocated = $bal['allocated'] ?? $lt['max_days_per_year'];
                            $used = $bal['used'] ?? 0;
                            $available = $bal['balance'] ?? $allocated;

                            $class = 'balance-available';
                            if ($available <= 0) $class = 'balance-zero';
                            elseif ($available <= 3) $class = 'balance-low';
                        ?>
                            <td class="balance-cell"
                                onclick="editBalance(<?= $emp['id'] ?>, <?= $lt['id'] ?>, '<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>', '<?= htmlspecialchars($lt['leave_code']) ?>', <?= $allocated ?>, <?= $used ?>)">
                                <span class="<?= $class ?>"><?= number_format($available, 1) ?></span>
                                <div class="balance-details"><?= $used ?>/<?= $allocated ?></div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 15px; font-size: 0.85em; color: #666;">
            <strong>Legend:</strong>
            <span style="color: #27ae60;">Green = Available</span> |
            <span style="color: #f39c12;">Orange = Low (3 or less)</span> |
            <span style="color: #e74c3c;">Red = Zero/Negative</span> |
            Click any cell to edit balance
        </div>
    <?php endif; ?>
</div>

<!-- Edit Balance Modal -->
<div id="editModal" class="edit-modal">
    <div class="edit-modal-content">
        <h3>Edit Leave Balance</h3>
        <form method="POST">
            <input type="hidden" name="update_balance" value="1">
            <input type="hidden" name="employee_id" id="editEmpId">
            <input type="hidden" name="leave_type_id" id="editLeaveTypeId">

            <div class="form-group">
                <label>Employee</label>
                <input type="text" id="editEmpName" readonly style="background: #f8f9fa;">
            </div>

            <div class="form-group">
                <label>Leave Type</label>
                <input type="text" id="editLeaveType" readonly style="background: #f8f9fa;">
            </div>

            <div class="form-group">
                <label>Used (Cannot Edit)</label>
                <input type="text" id="editUsed" readonly style="background: #f8f9fa;">
            </div>

            <div class="form-group">
                <label>Allocated Days</label>
                <input type="number" name="allocated" id="editAllocated" min="0" step="0.5" required>
            </div>

            <div class="edit-modal-btns">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBalance(empId, leaveTypeId, empName, leaveCode, allocated, used) {
    document.getElementById('editEmpId').value = empId;
    document.getElementById('editLeaveTypeId').value = leaveTypeId;
    document.getElementById('editEmpName').value = empName;
    document.getElementById('editLeaveType').value = leaveCode;
    document.getElementById('editAllocated').value = allocated;
    document.getElementById('editUsed').value = used;
    document.getElementById('editModal').classList.add('show');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('show');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include "../includes/dialog.php"; ?>
</body>
</html>
