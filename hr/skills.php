<?php
/**
 * Skills Management System
 * Manage skill categories, skills master, and employee skills
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'skills_master'")->fetch();
if (!$tableExists) {
    setModal("Setup Required", "Please run the HR Appraisal setup first.");
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$tab = $_GET['tab'] ?? 'employee_skills';
$message = '';
$error = '';

// Handle adding skill category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    $desc = trim($_POST['description']);

    if ($name) {
        try {
            $pdo->prepare("INSERT INTO skill_categories (category_name, description) VALUES (?, ?)")
                ->execute([$name, $desc]);
            $message = "Category added successfully!";
        } catch (PDOException $e) {
            $error = "Category already exists or error occurred";
        }
    }
}

// Handle adding skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {
    $name = trim($_POST['skill_name']);
    $catId = intval($_POST['category_id']);
    $desc = trim($_POST['skill_description']);

    if ($name) {
        try {
            $pdo->prepare("INSERT INTO skills_master (skill_name, category_id, description) VALUES (?, ?, ?)")
                ->execute([$name, $catId ?: null, $desc]);
            $message = "Skill added successfully!";
        } catch (PDOException $e) {
            $error = "Skill already exists in this category or error occurred";
        }
    }
}

// Handle adding employee skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee_skill'])) {
    $empId = intval($_POST['employee_id']);
    $skillId = intval($_POST['skill_id']);
    $level = $_POST['proficiency_level'];
    $years = floatval($_POST['years_experience']);
    $certified = isset($_POST['certified']) ? 1 : 0;
    $certName = trim($_POST['certification_name']);
    $notes = trim($_POST['notes']);

    if ($empId && $skillId) {
        try {
            $pdo->prepare("
                INSERT INTO employee_skills
                (employee_id, skill_id, proficiency_level, years_experience, certified, certification_name, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                proficiency_level = VALUES(proficiency_level),
                years_experience = VALUES(years_experience),
                certified = VALUES(certified),
                certification_name = VALUES(certification_name),
                notes = VALUES(notes)
            ")->execute([$empId, $skillId, $level, $years, $certified, $certName, $notes]);
            $message = "Employee skill updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    $redirectUrl = "skills.php?tab=employee_skills";
    if (!empty($_POST['_filter_emp'])) $redirectUrl .= "&filter_emp=" . (int)$_POST['_filter_emp'];
    header("Location: $redirectUrl&msg=" . urlencode($message ?: $error) . "&msgType=" . ($message ? 'success' : 'error'));
    exit;
}

// Handle editing employee skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee_skill'])) {
    $esId = intval($_POST['es_id']);
    $level = $_POST['proficiency_level'];
    $years = floatval($_POST['years_experience']);
    $certified = isset($_POST['certified']) ? 1 : 0;
    $certName = trim($_POST['certification_name']);
    $notes = trim($_POST['notes']);

    if ($esId) {
        try {
            $pdo->prepare("
                UPDATE employee_skills SET
                    proficiency_level = ?, years_experience = ?,
                    certified = ?, certification_name = ?, notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$level, $years, $certified, $certName, $notes, $esId]);
            $message = "Skill updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    $redirectUrl = "skills.php?tab=employee_skills";
    if (!empty($_POST['_filter_emp'])) $redirectUrl .= "&filter_emp=" . (int)$_POST['_filter_emp'];
    header("Location: $redirectUrl&msg=" . urlencode($message ?: $error) . "&msgType=" . ($message ? 'success' : 'error'));
    exit;
}

// Handle delete
if (isset($_GET['delete_skill']) && is_numeric($_GET['delete_skill'])) {
    $pdo->prepare("DELETE FROM skills_master WHERE id = ?")->execute([$_GET['delete_skill']]);
    $message = "Skill deleted";
}

if (isset($_GET['delete_emp_skill']) && is_numeric($_GET['delete_emp_skill'])) {
    $pdo->prepare("DELETE FROM employee_skills WHERE id = ?")->execute([$_GET['delete_emp_skill']]);
    $redirectUrl = "skills.php?tab=employee_skills";
    if (!empty($_GET['filter_emp'])) $redirectUrl .= "&filter_emp=" . (int)$_GET['filter_emp'];
    header("Location: $redirectUrl&msg=" . urlencode("Employee skill removed") . "&msgType=success");
    exit;
}

// Fetch data
$categories = $pdo->query("SELECT * FROM skill_categories WHERE is_active = 1 ORDER BY sort_order, category_name")->fetchAll(PDO::FETCH_ASSOC);

$skills = $pdo->query("
    SELECT sm.*, sc.category_name,
           (SELECT COUNT(*) FROM employee_skills WHERE skill_id = sm.id) as employee_count
    FROM skills_master sm
    LEFT JOIN skill_categories sc ON sm.category_id = sc.id
    WHERE sm.is_active = 1
    ORDER BY sc.sort_order, sc.category_name, sm.skill_name
")->fetchAll(PDO::FETCH_ASSOC);

$employees = $pdo->query("
    SELECT id, emp_id, first_name, last_name, department
    FROM employees WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Employee filter
$filterEmp = isset($_GET['filter_emp']) && $_GET['filter_emp'] !== '' ? (int)$_GET['filter_emp'] : null;

// Fetch employee skills with details (filtered if filter_emp set)
if ($filterEmp) {
    $esStmt = $pdo->prepare("
        SELECT es.*, e.emp_id, e.first_name, e.last_name, e.department,
               sm.skill_name, sc.category_name
        FROM employee_skills es
        JOIN employees e ON es.employee_id = e.id
        JOIN skills_master sm ON es.skill_id = sm.id
        LEFT JOIN skill_categories sc ON sm.category_id = sc.id
        WHERE es.employee_id = ?
        ORDER BY sc.category_name, sm.skill_name
    ");
    $esStmt->execute([$filterEmp]);
    $employeeSkills = $esStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employeeSkills = $pdo->query("
        SELECT es.*, e.emp_id, e.first_name, e.last_name, e.department,
               sm.skill_name, sc.category_name
        FROM employee_skills es
        JOIN employees e ON es.employee_id = e.id
        JOIN skills_master sm ON es.skill_id = sm.id
        LEFT JOIN skill_categories sc ON sm.category_id = sc.id
        ORDER BY e.first_name, e.last_name, sc.category_name, sm.skill_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Redirect-based messages
if (isset($_GET['msg']) && $_GET['msg']) {
    if ($_GET['msgType'] === 'success') $message = $_GET['msg'];
    else $error = $_GET['msg'];
}

// Edit mode: fetch the skill record being edited
$editEmpSkill = null;
if (isset($_GET['edit_es']) && is_numeric($_GET['edit_es'])) {
    $editStmt = $pdo->prepare("
        SELECT es.*, e.first_name, e.last_name, e.emp_id, sm.skill_name
        FROM employee_skills es
        JOIN employees e ON es.employee_id = e.id
        JOIN skills_master sm ON es.skill_id = sm.id
        WHERE es.id = ?
    ");
    $editStmt->execute([(int)$_GET['edit_es']]);
    $editEmpSkill = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// Get skill summary by employee
$skillSummary = $pdo->query("
    SELECT e.id, e.emp_id, e.first_name, e.last_name, e.department,
           COUNT(es.id) as total_skills,
           SUM(CASE WHEN es.proficiency_level = 'Expert' THEN 1 ELSE 0 END) as expert_count,
           SUM(CASE WHEN es.certified = 1 THEN 1 ELSE 0 END) as certified_count
    FROM employees e
    LEFT JOIN employee_skills es ON e.id = es.employee_id
    WHERE e.status = 'Active'
    GROUP BY e.id
    ORDER BY total_skills DESC, e.first_name
")->fetchAll(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Skills Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 0;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            background: #f8f9fa;
            text-decoration: none;
            color: #333;
        }
        .tab.active {
            background: white;
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            font-weight: bold;
            color: #3498db;
        }
        .tab-content {
            background: white;
            padding: 20px;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .skill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .skill-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        .skill-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .skill-card .category {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 10px;
        }
        .skill-card .count {
            font-size: 0.9em;
            color: #3498db;
        }
        .proficiency-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .proficiency-badge.Beginner { background: #e8f5e9; color: #2e7d32; }
        .proficiency-badge.Intermediate { background: #e3f2fd; color: #1565c0; }
        .proficiency-badge.Advanced { background: #fff3e0; color: #ef6c00; }
        .proficiency-badge.Expert { background: #fce4ec; color: #c2185b; }
        .certified-badge {
            background: #ffd700;
            color: #333;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin-top: 0;
        }
        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
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
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
        }
        .summary-card .label {
            opacity: 0.9;
        }
        .employee-skill-table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-skill-table th, .employee-skill-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .employee-skill-table tr:hover {
            background: #f8f9fa;
        }
        .skill-matrix {
            overflow-x: auto;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .matrix-table th, .matrix-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .matrix-table th {
            background: #3498db;
            color: white;
        }
        .matrix-table .emp-name {
            text-align: left;
            font-weight: 500;
        }
        .matrix-cell {
            width: 30px;
            height: 30px;
        }
        .has-skill {
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Skills Management</h1>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=employee_skills" class="tab <?= $tab === 'employee_skills' ? 'active' : '' ?>">Employee Skills</a>
        <a href="?tab=skills_master" class="tab <?= $tab === 'skills_master' ? 'active' : '' ?>">Skills Master</a>
        <a href="?tab=categories" class="tab <?= $tab === 'categories' ? 'active' : '' ?>">Categories</a>
        <a href="?tab=matrix" class="tab <?= $tab === 'matrix' ? 'active' : '' ?>">Skills Matrix</a>
    </div>

    <div class="tab-content">
        <?php if ($tab === 'employee_skills'): ?>
        <!-- Employee Skills Tab -->
        <h2>Employee Skills</h2>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="number"><?= count($employeeSkills) ?></div>
                <div class="label"><?= $filterEmp ? 'Skills Assigned' : 'Total Skills Assigned' ?></div>
            </div>
            <div class="summary-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="number"><?= count(array_filter($employeeSkills, fn($s) => $s['proficiency_level'] === 'Expert')) ?></div>
                <div class="label">Expert Level</div>
            </div>
            <div class="summary-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="number"><?= count(array_filter($employeeSkills, fn($s) => $s['certified'])) ?></div>
                <div class="label">Certified</div>
            </div>
        </div>

        <!-- Employee Filter -->
        <div style="background: #f8f9fa; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label style="font-weight: 600; color: #495057; white-space: nowrap;">Filter by Employee:</label>
            <form method="get" style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <input type="hidden" name="tab" value="employee_skills">
                <select name="filter_emp" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; min-width: 280px; font-size: 0.95em;">
                    <option value="">-- All Employees --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $filterEmp == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['emp_id'] ?>) - <?= htmlspecialchars($emp['department'] ?: 'N/A') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filterEmp): ?>
                    <a href="?tab=employee_skills" class="btn btn-secondary btn-sm" style="white-space: nowrap;">Clear Filter</a>
                <?php endif; ?>
            </form>
            <?php if ($filterEmp):
                $filteredEmpName = '';
                foreach ($employees as $emp) {
                    if ($emp['id'] == $filterEmp) { $filteredEmpName = $emp['first_name'] . ' ' . $emp['last_name']; break; }
                }
            ?>
                <span style="background: #3498db; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                    Showing: <?= htmlspecialchars($filteredEmpName) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($editEmpSkill): ?>
        <!-- Edit Employee Skill Form -->
        <div class="form-section" style="border: 2px solid #3498db; background: #eaf4fd;">
            <h3 style="color: #2c3e50;">Edit Skill: <?= htmlspecialchars($editEmpSkill['first_name'] . ' ' . $editEmpSkill['last_name']) ?> - <?= htmlspecialchars($editEmpSkill['skill_name']) ?></h3>
            <form method="post">
                <input type="hidden" name="es_id" value="<?= $editEmpSkill['id'] ?>">
                <input type="hidden" name="_filter_emp" value="<?= $filterEmp ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee</label>
                        <input type="text" value="<?= htmlspecialchars($editEmpSkill['first_name'] . ' ' . $editEmpSkill['last_name'] . ' (' . $editEmpSkill['emp_id'] . ')') ?>" disabled style="background: #e9ecef;">
                    </div>
                    <div class="form-group">
                        <label>Skill</label>
                        <input type="text" value="<?= htmlspecialchars($editEmpSkill['skill_name']) ?>" disabled style="background: #e9ecef;">
                    </div>
                    <div class="form-group">
                        <label>Proficiency Level *</label>
                        <select name="proficiency_level">
                            <?php foreach (['Beginner', 'Intermediate', 'Advanced', 'Expert'] as $lvl): ?>
                                <option value="<?= $lvl ?>" <?= $editEmpSkill['proficiency_level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="min-width: 120px;">
                        <label>Years Exp.</label>
                        <input type="number" name="years_experience" step="0.5" min="0" value="<?= $editEmpSkill['years_experience'] ?>">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 10px;">
                    <div class="form-group" style="min-width: 100px; flex: 0;">
                        <label>
                            <input type="checkbox" name="certified" value="1" <?= $editEmpSkill['certified'] ? 'checked' : '' ?>> Certified
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Certification Name</label>
                        <input type="text" name="certification_name" value="<?= htmlspecialchars($editEmpSkill['certification_name'] ?? '') ?>" placeholder="e.g., AWS Solutions Architect">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" value="<?= htmlspecialchars($editEmpSkill['notes'] ?? '') ?>" placeholder="Additional notes...">
                    </div>
                    <div class="form-group" style="flex: 0; display: flex; gap: 8px;">
                        <button type="submit" name="edit_employee_skill" class="btn btn-success">Update</button>
                        <a href="?tab=employee_skills<?= $filterEmp ? '&filter_emp=' . $filterEmp : '' ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Add Employee Skill Form -->
        <div class="form-section">
            <h3>Add Employee Skill</h3>
            <form method="post">
                <input type="hidden" name="_filter_emp" value="<?= $filterEmp ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select name="employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filterEmp == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['emp_id'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Skill *</label>
                        <select name="skill_id" required>
                            <option value="">-- Select Skill --</option>
                            <?php
                            $currentCat = '';
                            foreach ($skills as $sk):
                                if ($sk['category_name'] !== $currentCat):
                                    if ($currentCat) echo '</optgroup>';
                                    $currentCat = $sk['category_name'];
                                    echo '<optgroup label="' . htmlspecialchars($currentCat ?: 'Uncategorized') . '">';
                                endif;
                            ?>
                            <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['skill_name']) ?></option>
                            <?php endforeach; ?>
                            <?php if ($currentCat) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Proficiency Level</label>
                        <select name="proficiency_level">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                            <option value="Expert">Expert</option>
                        </select>
                    </div>
                    <div class="form-group" style="min-width: 120px;">
                        <label>Years Exp.</label>
                        <input type="number" name="years_experience" step="0.5" min="0" value="0">
                    </div>
                </div>
                <div class="form-row" style="margin-top: 10px;">
                    <div class="form-group" style="min-width: 100px; flex: 0;">
                        <label>
                            <input type="checkbox" name="certified" value="1"> Certified
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Certification Name</label>
                        <input type="text" name="certification_name" placeholder="e.g., AWS Solutions Architect">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Additional notes...">
                    </div>
                    <div class="form-group" style="flex: 0;">
                        <button type="submit" name="add_employee_skill" class="btn btn-success">Add Skill</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Employee Skills Table -->
        <div style="overflow-x: auto;">
        <table class="employee-skill-table">
            <thead>
                <tr>
                    <?php if (!$filterEmp): ?><th>Employee</th><th>Department</th><?php endif; ?>
                    <th>Skill</th>
                    <th>Category</th>
                    <th>Proficiency</th>
                    <th>Experience</th>
                    <th>Certification</th>
                    <th>Notes</th>
                    <th style="min-width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employeeSkills)): ?>
                <tr>
                    <td colspan="<?= $filterEmp ? '7' : '9' ?>" style="text-align: center; padding: 30px; color: #999;">
                        <?= $filterEmp ? 'No skills assigned to this employee yet.' : 'No employee skills found.' ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($employeeSkills as $es): ?>
                <tr <?= ($editEmpSkill && $editEmpSkill['id'] == $es['id']) ? 'style="background: #eaf4fd;"' : '' ?>>
                    <?php if (!$filterEmp): ?>
                    <td>
                        <a href="?tab=employee_skills&filter_emp=<?= $es['employee_id'] ?>" style="text-decoration: none;">
                            <strong><?= htmlspecialchars($es['first_name'] . ' ' . $es['last_name']) ?></strong><br>
                            <small style="color: #666;"><?= htmlspecialchars($es['emp_id']) ?></small>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($es['department'] ?: '-') ?></td>
                    <?php endif; ?>
                    <td>
                        <?= htmlspecialchars($es['skill_name']) ?>
                    </td>
                    <td><?= htmlspecialchars($es['category_name'] ?: '-') ?></td>
                    <td>
                        <span class="proficiency-badge <?= $es['proficiency_level'] ?>">
                            <?= $es['proficiency_level'] ?>
                        </span>
                    </td>
                    <td><?= $es['years_experience'] ?> yrs</td>
                    <td>
                        <?php if ($es['certified']): ?>
                            <span class="certified-badge">Certified</span>
                            <?php if ($es['certification_name']): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars($es['certification_name']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.85em; color: #666;"><?= htmlspecialchars($es['notes'] ?: '-') ?></td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="?tab=employee_skills<?= $filterEmp ? '&filter_emp=' . $filterEmp : '' ?>&edit_es=<?= $es['id'] ?>"
                               class="btn btn-sm" style="background: #3498db; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8em;">Edit</a>
                            <a href="?tab=employee_skills<?= $filterEmp ? '&filter_emp=' . $filterEmp : '' ?>&delete_emp_skill=<?= $es['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Remove this skill?')" style="padding: 4px 10px; font-size: 0.8em;">Remove</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php elseif ($tab === 'skills_master'): ?>
        <!-- Skills Master Tab -->
        <h2>Skills Master</h2>

        <div class="form-section">
            <h3>Add New Skill</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Skill Name *</label>
                        <input type="text" name="skill_name" required placeholder="e.g., Python">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">-- No Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="skill_description" placeholder="Brief description...">
                    </div>
                    <div class="form-group" style="flex: 0;">
                        <button type="submit" name="add_skill" class="btn btn-success">Add Skill</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="skill-grid">
            <?php foreach ($skills as $sk): ?>
            <div class="skill-card">
                <h4><?= htmlspecialchars($sk['skill_name']) ?></h4>
                <div class="category"><?= htmlspecialchars($sk['category_name'] ?: 'Uncategorized') ?></div>
                <?php if ($sk['description']): ?>
                <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                    <?= htmlspecialchars($sk['description']) ?>
                </div>
                <?php endif; ?>
                <div class="count"><?= $sk['employee_count'] ?> employee(s)</div>
                <div style="margin-top: 10px;">
                    <a href="?tab=skills_master&delete_skill=<?= $sk['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this skill?')">Delete</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif ($tab === 'categories'): ?>
        <!-- Categories Tab -->
        <h2>Skill Categories</h2>

        <div class="form-section">
            <h3>Add New Category</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" required placeholder="e.g., Programming Languages">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Brief description...">
                    </div>
                    <div class="form-group" style="flex: 0;">
                        <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                    </div>
                </div>
            </form>
        </div>

        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Skills Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat):
                    $skillCount = count(array_filter($skills, fn($s) => $s['category_id'] == $cat['id']));
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                    <td><?= htmlspecialchars($cat['description'] ?: '-') ?></td>
                    <td><?= $skillCount ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'matrix'): ?>
        <!-- Skills Matrix Tab -->
        <h2>Skills Matrix</h2>
        <p style="color: #666;">Overview of employee skills across the organization.</p>

        <div class="skill-matrix">
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Employee</th>
                        <th>Dept</th>
                        <th>Total Skills</th>
                        <th>Expert</th>
                        <th>Certified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skillSummary as $sum): ?>
                    <tr>
                        <td class="emp-name">
                            <a href="?tab=employee_skills&filter_emp=<?= $sum['id'] ?>">
                                <?= htmlspecialchars($sum['first_name'] . ' ' . $sum['last_name']) ?>
                            </a>
                            <br><small style="color: #666;"><?= htmlspecialchars($sum['emp_id']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($sum['department'] ?: '-') ?></td>
                        <td><strong><?= $sum['total_skills'] ?></strong></td>
                        <td>
                            <?php if ($sum['expert_count'] > 0): ?>
                            <span style="color: #c2185b; font-weight: bold;"><?= $sum['expert_count'] ?></span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sum['certified_count'] > 0): ?>
                            <span style="color: #ffc107; font-weight: bold;"><?= $sum['certified_count'] ?></span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
