<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// ==========================================
// Auto-create tables
// ==========================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS org_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(150) NOT NULL,
            department VARCHAR(100),
            level INT DEFAULT 5,
            description TEXT,
            responsibilities TEXT,
            qualifications TEXT,
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS org_role_responsibilities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            responsibility VARCHAR(500) NOT NULL,
            category ENUM('Primary','Secondary','Occasional') DEFAULT 'Primary',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_role (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {}

// ==========================================
// POST Handlers
// ==========================================
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Add Role ---
    if (isset($_POST['add_role'])) {
        $name = trim($_POST['role_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $level = (int)($_POST['level'] ?? 5);
        $desc = trim($_POST['description'] ?? '');
        $quals = trim($_POST['qualifications'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($name) {
            try {
                $pdo->prepare("INSERT INTO org_roles (role_name, department, level, description, qualifications, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$name, $dept ?: null, $level, $desc ?: null, $quals ?: null, $sort]);
                $msg = "Role '$name' added.";
                $msgType = 'success';
            } catch (PDOException $e) {
                $msg = "Error: " . $e->getMessage();
                $msgType = 'error';
            }
        }
        header("Location: org_structure.php?tab=roles&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Edit Role ---
    if (isset($_POST['edit_role'])) {
        $id = (int)$_POST['role_id'];
        $name = trim($_POST['role_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $level = (int)($_POST['level'] ?? 5);
        $desc = trim($_POST['description'] ?? '');
        $quals = trim($_POST['qualifications'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($id && $name) {
            $pdo->prepare("UPDATE org_roles SET role_name=?, department=?, level=?, description=?, qualifications=?, sort_order=? WHERE id=?")
                ->execute([$name, $dept ?: null, $level, $desc ?: null, $quals ?: null, $sort, $id]);
            $msg = "Role updated.";
            $msgType = 'success';
        }
        header("Location: org_structure.php?tab=roles&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Delete Role ---
    if (isset($_POST['delete_role'])) {
        $id = (int)$_POST['role_id'];
        $pdo->prepare("DELETE FROM org_role_responsibilities WHERE role_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM org_roles WHERE id = ?")->execute([$id]);
        $msg = "Role deleted.";
        $msgType = 'success';
        header("Location: org_structure.php?tab=roles&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Add Responsibility ---
    if (isset($_POST['add_responsibility'])) {
        $roleId = (int)$_POST['role_id'];
        $resp = trim($_POST['responsibility'] ?? '');
        $cat = $_POST['category'] ?? 'Primary';
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($roleId && $resp) {
            $pdo->prepare("INSERT INTO org_role_responsibilities (role_id, responsibility, category, sort_order) VALUES (?, ?, ?, ?)")
                ->execute([$roleId, $resp, $cat, $sort]);
            $msg = "Responsibility added.";
            $msgType = 'success';
        }
        header("Location: org_structure.php?tab=roles&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Remove Responsibility ---
    if (isset($_POST['remove_responsibility'])) {
        $id = (int)$_POST['resp_id'];
        $pdo->prepare("DELETE FROM org_role_responsibilities WHERE id = ?")->execute([$id]);
        $msg = "Responsibility removed.";
        $msgType = 'success';
        header("Location: org_structure.php?tab=roles&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }
}

// ==========================================
// Data Fetching
// ==========================================
$tab = $_GET['tab'] ?? 'org_chart';
$msg = $_GET['msg'] ?? '';
$msgType = $_GET['msgType'] ?? '';

$levelLabels = [0 => 'CXO / Director', 1 => 'VP / GM', 2 => 'Sr. Manager', 3 => 'Manager', 4 => 'Team Lead', 5 => 'Executive / Staff'];
$levelColors = [0 => '#e74c3c', 1 => '#e67e22', 2 => '#f39c12', 3 => '#27ae60', 4 => '#3498db', 5 => '#7f8c8d'];

// Fetch all active employees
$allEmps = $pdo->query("
    SELECT id, emp_id, first_name, last_name, designation, department,
           reporting_to, photo_path, date_of_joining
    FROM employees WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Build employee lookup
$empById = [];
foreach ($allEmps as $e) {
    $empById[$e['id']] = $e;
}

// Departments
$departments = [];
try {
    $departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
if (empty($departments)) {
    $departments = array_unique(array_filter(array_column($allEmps, 'department')));
    sort($departments);
}

// Roles
$roles = $pdo->query("
    SELECT r.*, COUNT(e.id) as employee_count
    FROM org_roles r
    LEFT JOIN employees e ON LOWER(TRIM(e.designation)) = LOWER(TRIM(r.role_name)) AND e.status = 'Active'
    WHERE r.is_active = 1
    GROUP BY r.id
    ORDER BY r.level, r.sort_order, r.role_name
")->fetchAll(PDO::FETCH_ASSOC);

$allRoles = $pdo->query("SELECT * FROM org_roles ORDER BY level, sort_order, role_name")->fetchAll(PDO::FETCH_ASSOC);

// Responsibilities per role
$roleResps = [];
$respRows = $pdo->query("SELECT * FROM org_role_responsibilities ORDER BY sort_order, category, id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($respRows as $r) {
    $roleResps[$r['role_id']][] = $r;
}

// Edit role
$editRole = null;
if ($tab === 'roles' && isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM org_roles WHERE id = ?");
    $editStmt->execute([(int)$_GET['edit']]);
    $editRole = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// Dept filter for org chart
$filterDept = $_GET['dept'] ?? '';

// ==========================================
// Build Org Tree
// ==========================================
function buildTree($employees, $parentId = null) {
    $branch = [];
    foreach ($employees as $e) {
        $rto = $e['reporting_to'] ? (int)$e['reporting_to'] : null;
        if ($rto === $parentId) {
            $e['children'] = buildTree($employees, (int)$e['id']);
            $branch[] = $e;
        }
    }
    return $branch;
}

// For dept filtering, we need to show the tree only for employees in the dept + their chain
$treeEmps = $allEmps;
if ($filterDept) {
    // Get all employees in the department
    $deptEmpIds = [];
    foreach ($allEmps as $e) {
        if ($e['department'] === $filterDept) $deptEmpIds[] = $e['id'];
    }
    // Also include their managers up the chain
    $includeIds = $deptEmpIds;
    foreach ($deptEmpIds as $eid) {
        $current = $eid;
        while (isset($empById[$current]) && $empById[$current]['reporting_to']) {
            $mgr = (int)$empById[$current]['reporting_to'];
            if (!in_array($mgr, $includeIds)) $includeIds[] = $mgr;
            $current = $mgr;
        }
    }
    $treeEmps = array_filter($allEmps, fn($e) => in_array($e['id'], $includeIds));
}

$orgTree = buildTree($treeEmps);

// Stats
$statDepts = count($departments);
$statEmps = count($allEmps);
$statRoles = count($roles);

// Dept colors
$deptColorMap = [];
$deptPalette = ['#e74c3c','#3498db','#27ae60','#9b59b6','#e67e22','#1abc9c','#f39c12','#2980b9','#c0392b','#16a085','#8e44ad','#d35400','#2c3e50','#7f8c8d','#2ecc71','#e91e63','#00bcd4','#ff5722','#607d8b','#795548'];
$di = 0;
foreach ($departments as $d) {
    $deptColorMap[$d] = $deptPalette[$di % count($deptPalette)];
    $di++;
}

// Employees grouped by department
$empsByDept = [];
foreach ($allEmps as $e) {
    $d = $e['department'] ?: 'Unassigned';
    $empsByDept[$d][] = $e;
}
ksort($empsByDept);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Org Structure - HR</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .org-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .org-header h1 { margin: 0; font-size: 1.8em; }
        .org-header p { margin: 5px 0 0; opacity: 0.9; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white; border-radius: 10px; padding: 18px; text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #667eea;
        }
        .stat-card .sv { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-card .sl { color: #7f8c8d; font-size: 0.85em; margin-top: 4px; }
        .stat-card.s2 { border-left-color: #27ae60; }
        .stat-card.s3 { border-left-color: #e67e22; }

        .tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #dee2e6; }
        .tab { padding: 10px 20px; text-decoration: none; color: #666; font-weight: 600;
               border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab:hover { color: #667eea; }
        .tab.active { color: #667eea; border-bottom-color: #667eea; }

        .msg-bar { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .msg-bar.success { background: #d4edda; color: #155724; }
        .msg-bar.error { background: #f8d7da; color: #721c24; }

        .filter-bar {
            background: white; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .filter-bar label { font-weight: 600; color: #495057; }
        .filter-bar select { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95em; }

        /* ======= ORG CHART TREE ======= */
        .tree-wrap { overflow-x: auto; padding: 20px 0; }
        .org-tree { display: flex; flex-direction: column; align-items: center; }
        .org-tree ul {
            display: flex; gap: 0; padding-top: 25px; position: relative;
            list-style: none; margin: 0; padding-left: 0;
        }
        .org-tree ul::before {
            content: ''; position: absolute; top: 0; left: 50%;
            width: 0; height: 25px; border-left: 2px solid #bdc3c7;
        }
        .org-tree li {
            display: flex; flex-direction: column; align-items: center;
            position: relative; padding: 0 12px;
        }
        .org-tree li::before, .org-tree li::after {
            content: ''; position: absolute; top: 0;
            width: 50%; height: 25px; border-top: 2px solid #bdc3c7;
        }
        .org-tree li::before { left: 0; border-left: 2px solid #bdc3c7; }
        .org-tree li::after { right: 0; border-right: 2px solid #bdc3c7; }
        .org-tree li:first-child::before { left: 50%; }
        .org-tree li:last-child::after { right: 50%; }
        .org-tree li:only-child::before, .org-tree li:only-child::after {
            border-top: none;
        }
        .org-tree li:only-child::before { border-left: 2px solid #bdc3c7; left: 50%; }

        .org-node {
            background: white; border-radius: 10px; padding: 12px 16px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.12); text-align: center;
            min-width: 140px; max-width: 180px; cursor: pointer;
            transition: all 0.2s; text-decoration: none; color: inherit; display: block;
            border-top: 4px solid #667eea;
        }
        .org-node:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.18); }
        .org-node .node-photo {
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover;
            margin: 0 auto 8px; display: block; border: 2px solid #eee;
        }
        .org-node .node-initials {
            width: 48px; height: 48px; border-radius: 50%;
            margin: 0 auto 8px; display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white; font-size: 1.1em;
        }
        .org-node .node-name { font-weight: 700; font-size: 0.9em; color: #2c3e50; margin-bottom: 3px; }
        .org-node .node-desig { font-size: 0.75em; color: #666; margin-bottom: 2px; }
        .org-node .node-dept {
            font-size: 0.7em; padding: 2px 6px; border-radius: 8px;
            display: inline-block; color: white; font-weight: 600;
        }
        .org-node .node-count {
            font-size: 0.7em; color: #999; margin-top: 4px;
        }

        /* ======= ROLES SECTION ======= */
        .form-section {
            background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-section h3 { margin: 0 0 15px; color: #2c3e50; }
        .form-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .form-group label { font-weight: 600; font-size: 0.85em; color: #495057; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95em;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #667eea; box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }

        .btn { padding: 8px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9em; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-primary { background: #667eea; color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.8em; }

        .role-card {
            background: white; border-radius: 10px; padding: 18px; margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #667eea;
        }
        .role-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 12px; flex-wrap: wrap; gap: 10px;
        }
        .level-badge {
            padding: 3px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 600; color: white;
        }
        .emp-count-badge {
            background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 600;
        }
        .cat-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 600; }
        .cat-Primary { background: #e3f2fd; color: #1565c0; }
        .cat-Secondary { background: #fff3e0; color: #ef6c00; }
        .cat-Occasional { background: #f3e5f5; color: #7b1fa2; }

        .resp-list { list-style: none; padding: 0; margin: 10px 0; }
        .resp-list li {
            padding: 6px 10px; border-bottom: 1px solid #f0f0f0;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.9em;
        }
        .resp-list li:last-child { border-bottom: none; }

        /* ======= DEPARTMENT VIEW ======= */
        .dept-accordion {
            background: white; border-radius: 10px; margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden;
        }
        .dept-accordion-header {
            padding: 15px 20px; cursor: pointer; display: flex;
            justify-content: space-between; align-items: center;
            transition: background 0.2s; user-select: none;
        }
        .dept-accordion-header:hover { background: #f8f9fa; }
        .dept-accordion-body { padding: 0 20px 20px; display: none; }
        .dept-accordion.open .dept-accordion-body { display: block; }
        .dept-accordion.open .dept-arrow { transform: rotate(90deg); }
        .dept-arrow { transition: transform 0.2s; font-size: 0.9em; color: #666; }

        .emp-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px; margin-top: 12px;
        }
        .emp-mini-card {
            border: 1px solid #eee; border-radius: 8px; padding: 12px;
            display: flex; gap: 10px; align-items: center; text-decoration: none; color: inherit;
            transition: all 0.2s;
        }
        .emp-mini-card:hover { background: #f8f9fa; border-color: #667eea; }
        .emp-mini-card .mini-photo {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        }
        .emp-mini-card .mini-initials {
            width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white; font-size: 0.9em;
        }
        .emp-mini-card .mini-info { overflow: hidden; }
        .emp-mini-card .mini-name { font-weight: 600; font-size: 0.9em; color: #2c3e50; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .emp-mini-card .mini-desig { font-size: 0.78em; color: #666; }

        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
        .empty-state .icon { font-size: 3em; margin-bottom: 10px; }

        /* Dark mode */
        body.dark .stat-card, body.dark .filter-bar, body.dark .form-section,
        body.dark .role-card, body.dark .dept-accordion { background: #2c3e50; }
        body.dark .stat-card .sv { color: #ecf0f1; }
        body.dark .org-node { background: #34495e; border-top-color: #667eea; }
        body.dark .org-node .node-name { color: #ecf0f1; }
        body.dark .org-tree li::before, body.dark .org-tree li::after,
        body.dark .org-tree ul::before { border-color: #4a6278; }
        body.dark .form-group label { color: #bdc3c7; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea {
            background: #34495e; border-color: #4a6278; color: #ecf0f1;
        }
        body.dark .form-section h3, body.dark .role-header { color: #ecf0f1; }
        body.dark .resp-list li { border-bottom-color: #3d566e; }
        body.dark .dept-accordion-header:hover { background: #34495e; }
        body.dark .emp-mini-card { border-color: #3d566e; }
        body.dark .emp-mini-card:hover { background: #34495e; }
        body.dark .emp-mini-card .mini-name { color: #ecf0f1; }
        body.dark .tab { color: #bdc3c7; }
        body.dark .tab.active { color: #667eea; }
    </style>
</head>
<body>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") { body.classList.add("dark"); toggle.textContent = "Light Mode"; }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}

function toggleAccordion(el) {
    el.closest('.dept-accordion').classList.toggle('open');
}
</script>

<div class="content" style="overflow-y: auto; height: 100vh;">

    <div class="org-header">
        <h1>Organization Structure</h1>
        <p>Org chart, roles & responsibilities, and department structure</p>
    </div>

    <?php if ($msg): ?>
        <div class="msg-bar <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="sv"><?= $statEmps ?></div>
            <div class="sl">Active Employees</div>
        </div>
        <div class="stat-card s2">
            <div class="sv"><?= $statDepts ?></div>
            <div class="sl">Departments</div>
        </div>
        <div class="stat-card s3">
            <div class="sv"><?= $statRoles ?></div>
            <div class="sl">Roles Defined</div>
        </div>
    </div>

    <div class="tabs">
        <a href="?tab=org_chart" class="tab <?= $tab === 'org_chart' ? 'active' : '' ?>">Org Chart</a>
        <a href="?tab=roles" class="tab <?= $tab === 'roles' ? 'active' : '' ?>">Roles & Responsibilities</a>
        <a href="?tab=departments" class="tab <?= $tab === 'departments' ? 'active' : '' ?>">Department Structure</a>
    </div>

    <!-- ===================== TAB 1: ORG CHART ===================== -->
    <?php if ($tab === 'org_chart'): ?>

        <div class="filter-bar">
            <label>Filter by Department:</label>
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="tab" value="org_chart">
                <select name="dept" onchange="this.form.submit()">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filterDept): ?>
                    <a href="?tab=org_chart" class="btn btn-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($orgTree)): ?>
            <div class="empty-state">
                <div class="icon">&#128101;</div>
                <p>No hierarchy data found. Set the "Reporting To" field on employees to build the org chart.</p>
            </div>
        <?php else: ?>
            <div class="tree-wrap">
                <div class="org-tree">
                    <?php
                    function renderTree($nodes, $deptColorMap) {
                        if (empty($nodes)) return;
                        echo '<ul>';
                        foreach ($nodes as $node) {
                            $dept = $node['department'] ?: 'Unassigned';
                            $color = $deptColorMap[$dept] ?? '#7f8c8d';
                            $initials = strtoupper(substr($node['first_name'],0,1) . substr($node['last_name'],0,1));
                            $childCount = count($node['children'] ?? []);

                            echo '<li>';
                            echo '<a href="/hr/employee_view.php?id=' . $node['id'] . '" class="org-node" style="border-top-color: ' . $color . ';">';

                            if ($node['photo_path'] && file_exists(__DIR__ . '/../' . $node['photo_path'])) {
                                echo '<img src="/' . htmlspecialchars($node['photo_path']) . '" class="node-photo" alt="">';
                            } else {
                                echo '<div class="node-initials" style="background: ' . $color . ';">' . $initials . '</div>';
                            }

                            echo '<div class="node-name">' . htmlspecialchars($node['first_name'] . ' ' . $node['last_name']) . '</div>';
                            echo '<div class="node-desig">' . htmlspecialchars($node['designation'] ?: 'N/A') . '</div>';
                            echo '<div class="node-dept" style="background: ' . $color . ';">' . htmlspecialchars($dept) . '</div>';

                            if ($childCount > 0) {
                                echo '<div class="node-count">' . $childCount . ' report' . ($childCount > 1 ? 's' : '') . '</div>';
                            }

                            echo '</a>';

                            if (!empty($node['children'])) {
                                renderTree($node['children'], $deptColorMap);
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    renderTree($orgTree, $deptColorMap);
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <!-- ===================== TAB 2: ROLES & RESPONSIBILITIES ===================== -->
    <?php elseif ($tab === 'roles'): ?>

        <!-- Add/Edit Role Form -->
        <div class="form-section">
            <h3><?= $editRole ? 'Edit Role' : 'Add New Role' ?></h3>
            <form method="post">
                <?php if ($editRole): ?>
                    <input type="hidden" name="role_id" value="<?= $editRole['id'] ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role / Designation Name *</label>
                        <input type="text" name="role_name" required placeholder="e.g., Production Manager" value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="min-width: 160px;">
                        <label>Department</label>
                        <select name="department">
                            <option value="">-- All / General --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= ($editRole['department'] ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="min-width: 150px;">
                        <label>Level</label>
                        <select name="level">
                            <?php foreach ($levelLabels as $lv => $ll): ?>
                                <option value="<?= $lv ?>" <?= (int)($editRole['level'] ?? 5) === $lv ? 'selected' : '' ?>><?= $ll ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 0; min-width: 70px;">
                        <label>Sort #</label>
                        <input type="number" name="sort_order" min="0" value="<?= (int)($editRole['sort_order'] ?? 0) ?>" style="width: 65px;">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 10px;">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Role summary..."><?= htmlspecialchars($editRole['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Qualifications / Requirements</label>
                        <textarea name="qualifications" rows="2" placeholder="Required education, experience..."><?= htmlspecialchars($editRole['qualifications'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group" style="flex: 0; min-width: auto; align-self: flex-end;">
                        <?php if ($editRole): ?>
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" name="edit_role" class="btn btn-primary">Update</button>
                                <a href="?tab=roles" class="btn btn-secondary">Cancel</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="add_role" class="btn btn-success">Add Role</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Roles List -->
        <?php if (empty($allRoles)): ?>
            <div class="empty-state">
                <div class="icon">&#128188;</div>
                <p>No roles defined yet. Use the form above to create roles.</p>
            </div>
        <?php else: ?>
            <?php foreach ($allRoles as $role):
                $resps = $roleResps[$role['id']] ?? [];
                $lvColor = $levelColors[$role['level']] ?? '#7f8c8d';
            ?>
            <div class="role-card" style="border-left-color: <?= $lvColor ?>;">
                <div class="role-header">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span class="level-badge" style="background: <?= $lvColor ?>;"><?= $levelLabels[$role['level']] ?? 'Staff' ?></span>
                        <strong style="font-size: 1.1em;"><?= htmlspecialchars($role['role_name']) ?></strong>
                        <?php if ($role['department']): ?>
                            <span style="background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 12px; font-size: 0.8em;"><?= htmlspecialchars($role['department']) ?></span>
                        <?php endif; ?>
                        <?php
                            // Count employees with this designation
                            $empCountStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE LOWER(TRIM(designation)) = LOWER(TRIM(?)) AND status = 'Active'");
                            $empCountStmt->execute([$role['role_name']]);
                            $empCount = (int)$empCountStmt->fetchColumn();
                        ?>
                        <span class="emp-count-badge"><?= $empCount ?> employee<?= $empCount !== 1 ? 's' : '' ?></span>
                        <?php if (!$role['is_active']): ?>
                            <span style="background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 4px; font-size: 0.75em;">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <a href="?tab=roles&edit=<?= $role['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this role and all responsibilities?');">
                            <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                            <button type="submit" name="delete_role" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>

                <?php if ($role['description']): ?>
                    <div style="font-size: 0.9em; color: #555; margin-bottom: 8px;"><?= nl2br(htmlspecialchars($role['description'])) ?></div>
                <?php endif; ?>
                <?php if ($role['qualifications']): ?>
                    <div style="font-size: 0.85em; color: #888; margin-bottom: 8px;"><strong>Qualifications:</strong> <?= nl2br(htmlspecialchars($role['qualifications'])) ?></div>
                <?php endif; ?>

                <!-- Responsibilities -->
                <div style="margin-top: 10px;">
                    <div style="font-weight: 600; font-size: 0.9em; color: #495057; margin-bottom: 5px;">Responsibilities:</div>
                    <?php if (!empty($resps)): ?>
                        <ul class="resp-list">
                            <?php foreach ($resps as $resp): ?>
                            <li>
                                <div>
                                    <span class="cat-badge cat-<?= $resp['category'] ?>"><?= $resp['category'] ?></span>
                                    <span style="margin-left: 6px;"><?= htmlspecialchars($resp['responsibility']) ?></span>
                                </div>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Remove?');">
                                    <input type="hidden" name="resp_id" value="<?= $resp['id'] ?>">
                                    <button type="submit" name="remove_responsibility" class="btn btn-danger btn-sm" style="padding: 2px 6px;">X</button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #aaa; font-size: 0.85em; margin: 5px 0;">No responsibilities defined yet.</p>
                    <?php endif; ?>

                    <!-- Add Responsibility -->
                    <form method="post" style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #dee2e6;">
                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="responsibility" required placeholder="Add a responsibility..." style="flex: 2; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.9em; min-width: 200px;">
                            <select name="category" style="padding: 6px 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.9em;">
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                                <option value="Occasional">Occasional</option>
                            </select>
                            <button type="submit" name="add_responsibility" class="btn btn-success btn-sm">+ Add</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- ===================== TAB 3: DEPARTMENT STRUCTURE ===================== -->
    <?php elseif ($tab === 'departments'): ?>

        <?php if (empty($empsByDept)): ?>
            <div class="empty-state">
                <div class="icon">&#127970;</div>
                <p>No departments found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($empsByDept as $deptName => $deptEmps):
                $deptColor = $deptColorMap[$deptName] ?? '#7f8c8d';
                $headCount = count($deptEmps);

                // Group employees by designation
                $byDesig = [];
                foreach ($deptEmps as $e) {
                    $desig = $e['designation'] ?: 'Unassigned';
                    $byDesig[$desig][] = $e;
                }
                ksort($byDesig);

                // Find dept head (employee with no reporting_to in this dept, or first in list)
                $deptHead = null;
                foreach ($deptEmps as $e) {
                    if (!$e['reporting_to'] || !isset($empById[$e['reporting_to']]) || $empById[$e['reporting_to']]['department'] !== $deptName) {
                        $deptHead = $e;
                        break;
                    }
                }

                // Roles defined for this dept
                $deptRoles = array_filter($allRoles, fn($r) => $r['department'] === $deptName || !$r['department']);
            ?>
            <div class="dept-accordion open">
                <div class="dept-accordion-header" onclick="toggleAccordion(this)">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="background: <?= $deptColor ?>; color: white; padding: 5px 14px; border-radius: 8px; font-weight: 700; font-size: 1em;"><?= htmlspecialchars($deptName) ?></span>
                        <span style="font-size: 0.9em; color: #666;"><?= $headCount ?> employee<?= $headCount !== 1 ? 's' : '' ?></span>
                        <?php if ($deptHead): ?>
                            <span style="font-size: 0.85em; color: #999;">Head: <strong style="color: #2c3e50;"><?= htmlspecialchars($deptHead['first_name'] . ' ' . $deptHead['last_name']) ?></strong></span>
                        <?php endif; ?>
                    </div>
                    <span class="dept-arrow">&#9654;</span>
                </div>
                <div class="dept-accordion-body">
                    <?php foreach ($byDesig as $desig => $desigEmps):
                        // Check if this designation has a role defined
                        $hasRole = false;
                        foreach ($allRoles as $r) {
                            if (strtolower(trim($r['role_name'])) === strtolower(trim($desig))) { $hasRole = true; break; }
                        }
                    ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <strong style="font-size: 0.95em; color: #2c3e50;"><?= htmlspecialchars($desig) ?></strong>
                            <span style="background: #e9ecef; color: #495057; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;"><?= count($desigEmps) ?></span>
                            <?php if ($hasRole): ?>
                                <a href="?tab=roles" style="font-size: 0.75em; color: #667eea; text-decoration: none;">View Role</a>
                            <?php else: ?>
                                <span style="font-size: 0.75em; color: #ccc;">No role defined</span>
                            <?php endif; ?>
                        </div>
                        <div class="emp-grid">
                            <?php foreach ($desigEmps as $e):
                                $initials = strtoupper(substr($e['first_name'],0,1) . substr($e['last_name'],0,1));
                                $eColor = $deptColor;
                                $mgrName = '';
                                if ($e['reporting_to'] && isset($empById[$e['reporting_to']])) {
                                    $m = $empById[$e['reporting_to']];
                                    $mgrName = $m['first_name'] . ' ' . $m['last_name'];
                                }
                            ?>
                            <a href="/hr/employee_view.php?id=<?= $e['id'] ?>" class="emp-mini-card">
                                <?php if ($e['photo_path'] && file_exists(__DIR__ . '/../' . $e['photo_path'])): ?>
                                    <img src="/<?= htmlspecialchars($e['photo_path']) ?>" class="mini-photo" alt="">
                                <?php else: ?>
                                    <div class="mini-initials" style="background: <?= $eColor ?>;"><?= $initials ?></div>
                                <?php endif; ?>
                                <div class="mini-info">
                                    <div class="mini-name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></div>
                                    <div class="mini-desig"><?= htmlspecialchars($e['emp_id']) ?></div>
                                    <?php if ($mgrName): ?>
                                        <div class="mini-desig" style="font-size: 0.72em; color: #999;">Reports to: <?= htmlspecialchars($mgrName) ?></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
