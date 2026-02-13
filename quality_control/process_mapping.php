<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// ==========================================
// Auto-create tables
// ==========================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_processes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            process_code VARCHAR(30) NOT NULL UNIQUE,
            process_name VARCHAR(255) NOT NULL,
            description TEXT,
            department VARCHAR(100),
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_process_skills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            process_id INT NOT NULL,
            skill_id INT NOT NULL,
            min_proficiency ENUM('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
            is_mandatory TINYINT(1) DEFAULT 1,
            notes VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_process_skill (process_id, skill_id),
            INDEX idx_process (process_id),
            INDEX idx_skill (skill_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qc_product_processes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part_no VARCHAR(100) NOT NULL,
            process_id INT NOT NULL,
            sequence_order INT DEFAULT 1,
            cycle_time_minutes DECIMAL(10,2) DEFAULT NULL,
            notes VARCHAR(500),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_process (part_no, process_id),
            INDEX idx_part (part_no),
            INDEX idx_sequence (part_no, sequence_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {}

// Check if skills tables exist
$skillsAvailable = true;
try {
    $pdo->query("SELECT 1 FROM skills_master LIMIT 1");
} catch (Exception $e) {
    $skillsAvailable = false;
}

// ==========================================
// POST Handlers
// ==========================================
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['_tab'] ?? 'processes';
    $product = $_POST['_product'] ?? '';

    // --- Add Process ---
    if (isset($_POST['add_process'])) {
        $code = strtoupper(trim($_POST['process_code'] ?? ''));
        $name = trim($_POST['process_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($code && $name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO qc_processes (process_code, process_name, description, department, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $desc ?: null, $dept ?: null, $sort, $_SESSION['user_id'] ?? null]);
                $msg = "Process '$name' added successfully.";
                $msgType = 'success';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $msg = "Process code '$code' already exists.";
                } else {
                    $msg = "Error adding process: " . $e->getMessage();
                }
                $msgType = 'error';
            }
        } else {
            $msg = "Process code and name are required.";
            $msgType = 'error';
        }
        header("Location: process_mapping.php?tab=processes&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Edit Process ---
    if (isset($_POST['edit_process'])) {
        $id = (int)$_POST['process_id'];
        $code = strtoupper(trim($_POST['process_code'] ?? ''));
        $name = trim($_POST['process_name'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($id && $code && $name) {
            try {
                $stmt = $pdo->prepare("UPDATE qc_processes SET process_code=?, process_name=?, description=?, department=?, sort_order=? WHERE id=?");
                $stmt->execute([$code, $name, $desc ?: null, $dept ?: null, $sort, $id]);
                $msg = "Process updated.";
                $msgType = 'success';
            } catch (PDOException $e) {
                $msg = "Error updating: " . $e->getMessage();
                $msgType = 'error';
            }
        }
        header("Location: process_mapping.php?tab=processes&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Delete Process ---
    if (isset($_POST['delete_process'])) {
        $id = (int)$_POST['process_id'];
        // Check if mapped to any products
        $mapped = $pdo->prepare("SELECT COUNT(*) FROM qc_product_processes WHERE process_id = ?");
        $mapped->execute([$id]);
        if ($mapped->fetchColumn() > 0) {
            $msg = "Cannot delete: this process is mapped to products. Remove mappings first.";
            $msgType = 'error';
        } else {
            $pdo->prepare("DELETE FROM qc_process_skills WHERE process_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM qc_processes WHERE id = ?")->execute([$id]);
            $msg = "Process deleted.";
            $msgType = 'success';
        }
        header("Location: process_mapping.php?tab=processes&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Add Skill to Process ---
    if (isset($_POST['add_process_skill'])) {
        $processId = (int)$_POST['process_id'];
        $skillId = (int)$_POST['skill_id'];
        $minProf = $_POST['min_proficiency'] ?? 'Beginner';
        $mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

        if ($processId && $skillId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO qc_process_skills (process_id, skill_id, min_proficiency, is_mandatory) VALUES (?, ?, ?, ?)");
                $stmt->execute([$processId, $skillId, $minProf, $mandatory]);
                $msg = "Skill requirement added.";
                $msgType = 'success';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $msg = "This skill is already assigned to this process.";
                } else {
                    $msg = "Error: " . $e->getMessage();
                }
                $msgType = 'error';
            }
        }
        header("Location: process_mapping.php?tab=processes&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Remove Skill from Process ---
    if (isset($_POST['remove_process_skill'])) {
        $id = (int)$_POST['ps_id'];
        $pdo->prepare("DELETE FROM qc_process_skills WHERE id = ?")->execute([$id]);
        $msg = "Skill requirement removed.";
        $msgType = 'success';
        header("Location: process_mapping.php?tab=processes&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Add Product Process Mapping ---
    if (isset($_POST['add_product_process'])) {
        $partNo = trim($_POST['part_no'] ?? '');
        $processId = (int)$_POST['process_id'];
        $seq = (int)($_POST['sequence_order'] ?? 1);
        $cycleTime = $_POST['cycle_time_minutes'] !== '' ? (float)$_POST['cycle_time_minutes'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if ($partNo && $processId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO qc_product_processes (part_no, process_id, sequence_order, cycle_time_minutes, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$partNo, $processId, $seq, $cycleTime, $notes ?: null]);
                $msg = "Process mapped to product.";
                $msgType = 'success';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $msg = "This process is already mapped to this product.";
                } else {
                    $msg = "Error: " . $e->getMessage();
                }
                $msgType = 'error';
            }
        }
        header("Location: process_mapping.php?tab=product_mapping&product=" . urlencode($partNo) . "&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Remove Product Process Mapping ---
    if (isset($_POST['remove_product_process'])) {
        $id = (int)$_POST['pp_id'];
        $pdo->prepare("DELETE FROM qc_product_processes WHERE id = ?")->execute([$id]);
        $msg = "Process removed from product.";
        $msgType = 'success';
        header("Location: process_mapping.php?tab=product_mapping&product=" . urlencode($product) . "&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }

    // --- Update Sequence Order ---
    if (isset($_POST['update_sequence'])) {
        $ids = $_POST['pp_ids'] ?? [];
        $seqs = $_POST['sequences'] ?? [];
        foreach ($ids as $i => $ppId) {
            if (isset($seqs[$i])) {
                $pdo->prepare("UPDATE qc_product_processes SET sequence_order = ? WHERE id = ?")->execute([(int)$seqs[$i], (int)$ppId]);
            }
        }
        $msg = "Sequence updated.";
        $msgType = 'success';
        header("Location: process_mapping.php?tab=product_mapping&product=" . urlencode($product) . "&msg=" . urlencode($msg) . "&msgType=$msgType");
        exit;
    }
}

// ==========================================
// Data Fetching
// ==========================================
$tab = $_GET['tab'] ?? 'product_mapping';
$msg = $_GET['msg'] ?? $msg;
$msgType = $_GET['msgType'] ?? $msgType;

// Fetch all processes
$processes = $pdo->query("SELECT * FROM qc_processes WHERE is_active = 1 ORDER BY sort_order, process_name")->fetchAll(PDO::FETCH_ASSOC);
$allProcesses = $pdo->query("SELECT * FROM qc_processes ORDER BY sort_order, process_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch YID products
$yidProducts = [];
try {
    $yidProducts = $pdo->query("SELECT part_no, part_name, description, uom FROM part_master WHERE status = 'active' AND UPPER(part_id) = 'YID' ORDER BY part_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch skills (grouped by category)
$skills = [];
$skillsByCategory = [];
if ($skillsAvailable) {
    try {
        $skills = $pdo->query("
            SELECT sm.*, COALESCE(sc.category_name, 'General') as category_name
            FROM skills_master sm
            LEFT JOIN skill_categories sc ON sm.category_id = sc.id
            WHERE sm.is_active = 1
            ORDER BY sc.sort_order, sc.category_name, sm.skill_name
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($skills as $sk) {
            $skillsByCategory[$sk['category_name']][] = $sk;
        }
    } catch (Exception $e) {}
}

// Fetch employees
$employees = [];
try {
    $employees = $pdo->query("SELECT id, emp_id, first_name, last_name, department, designation FROM employees WHERE status = 'Active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Stats
$statProcesses = (int)$pdo->query("SELECT COUNT(*) FROM qc_processes WHERE is_active = 1")->fetchColumn();
$statMappedProducts = (int)$pdo->query("SELECT COUNT(DISTINCT part_no) FROM qc_product_processes WHERE is_active = 1")->fetchColumn();
$statSkillReqs = (int)$pdo->query("SELECT COUNT(*) FROM qc_process_skills")->fetchColumn();
$statTotalYid = count($yidProducts);

// ==========================================
// Tab-specific data
// ==========================================

// Process Master: fetch skills per process
$processSkills = [];
if ($tab === 'processes' || $tab === 'product_mapping') {
    $psRows = $pdo->query("
        SELECT ps.*, sm.skill_name, COALESCE(sc.category_name, 'General') as category_name
        FROM qc_process_skills ps
        JOIN skills_master sm ON ps.skill_id = sm.id
        LEFT JOIN skill_categories sc ON sm.category_id = sc.id
        ORDER BY ps.is_mandatory DESC, sm.skill_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($psRows as $row) {
        $processSkills[$row['process_id']][] = $row;
    }
}

// Product Mapping tab
$selectedProduct = $_GET['product'] ?? '';
$productProcesses = [];
if ($tab === 'product_mapping' && $selectedProduct) {
    $ppStmt = $pdo->prepare("
        SELECT pp.*, p.process_code, p.process_name, p.department, p.description as process_desc
        FROM qc_product_processes pp
        JOIN qc_processes p ON pp.process_id = p.id
        WHERE pp.part_no = ? AND pp.is_active = 1
        ORDER BY pp.sequence_order
    ");
    $ppStmt->execute([$selectedProduct]);
    $productProcesses = $ppStmt->fetchAll(PDO::FETCH_ASSOC);

    // For each process, get qualified employees per skill
    $profOrder = ['Beginner' => 1, 'Intermediate' => 2, 'Advanced' => 3, 'Expert' => 4];
    foreach ($productProcesses as &$pp) {
        $pp['skills'] = $processSkills[$pp['process_id']] ?? [];
        foreach ($pp['skills'] as &$sk) {
            $qualStmt = $pdo->prepare("
                SELECT es.proficiency_level, es.certified, es.certification_name,
                       e.emp_id, e.first_name, e.last_name, e.department, e.designation
                FROM employee_skills es
                JOIN employees e ON es.employee_id = e.id
                WHERE es.skill_id = ? AND e.status = 'Active'
                  AND FIELD(es.proficiency_level, 'Beginner','Intermediate','Advanced','Expert')
                      >= FIELD(?, 'Beginner','Intermediate','Advanced','Expert')
                ORDER BY FIELD(es.proficiency_level, 'Expert','Advanced','Intermediate','Beginner'), e.first_name
            ");
            $qualStmt->execute([$sk['skill_id'], $sk['min_proficiency']]);
            $sk['qualified_employees'] = $qualStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($sk);
    }
    unset($pp);
}

// Skill Matrix tab: pre-fetch all employee skills into memory
$empSkillMap = []; // empId => skillId => proficiency_level
$processSkillReqs = []; // processId => [{skill_id, min_proficiency, is_mandatory}, ...]
if ($tab === 'skill_matrix') {
    // All employee skills
    try {
        $allEmpSkills = $pdo->query("SELECT employee_id, skill_id, proficiency_level FROM employee_skills")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allEmpSkills as $es) {
            $empSkillMap[$es['employee_id']][$es['skill_id']] = $es['proficiency_level'];
        }
    } catch (Exception $e) {}

    // All process skill requirements
    $allPsReqs = $pdo->query("SELECT process_id, skill_id, min_proficiency, is_mandatory FROM qc_process_skills")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allPsReqs as $r) {
        $processSkillReqs[$r['process_id']][] = $r;
    }
}

// Helper: check employee qualification for a process
function getQualification($empId, $processId, $empSkillMap, $processSkillReqs) {
    $profOrder = ['Beginner' => 1, 'Intermediate' => 2, 'Advanced' => 3, 'Expert' => 4];
    $reqs = $processSkillReqs[$processId] ?? [];
    if (empty($reqs)) return 'none';

    $mandatoryTotal = 0;
    $mandatoryMet = 0;
    $totalMet = 0;

    foreach ($reqs as $req) {
        $empLevel = $empSkillMap[$empId][$req['skill_id']] ?? null;
        $meets = $empLevel && ($profOrder[$empLevel] ?? 0) >= ($profOrder[$req['min_proficiency']] ?? 0);
        if ($req['is_mandatory']) {
            $mandatoryTotal++;
            if ($meets) $mandatoryMet++;
        }
        if ($meets) $totalMet++;
    }

    if ($mandatoryTotal > 0 && $mandatoryMet === $mandatoryTotal) return 'full';
    if ($totalMet > 0) return 'partial';
    return 'none';
}

// Edit mode for process
$editProcess = null;
if ($tab === 'processes' && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editStmt = $pdo->prepare("SELECT * FROM qc_processes WHERE id = ?");
    $editStmt->execute([$editId]);
    $editProcess = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// Get selected product info
$selectedProductInfo = null;
if ($selectedProduct) {
    foreach ($yidProducts as $p) {
        if ($p['part_no'] === $selectedProduct) {
            $selectedProductInfo = $p;
            break;
        }
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Process Mapping - Quality Control</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .pm-header {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .pm-header h1 { margin: 0; font-size: 1.8em; }
        .pm-header p { margin: 5px 0 0; opacity: 0.9; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #00b09b;
        }
        .stat-card .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-card .stat-label { color: #7f8c8d; font-size: 0.85em; margin-top: 4px; }
        .stat-card.s2 { border-left-color: #3498db; }
        .stat-card.s3 { border-left-color: #9b59b6; }
        .stat-card.s4 { border-left-color: #e67e22; }

        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0;
        }
        .tab {
            padding: 10px 20px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab:hover { color: #00b09b; }
        .tab.active { color: #00b09b; border-bottom-color: #00b09b; }

        .msg-bar {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .msg-bar.success { background: #d4edda; color: #155724; }
        .msg-bar.error { background: #f8d7da; color: #721c24; }

        .form-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-section h3 { margin: 0 0 15px; color: #2c3e50; }
        .form-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .form-group label { font-weight: 600; font-size: 0.85em; color: #495057; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #00b09b;
            box-shadow: 0 0 0 2px rgba(0,176,155,0.2);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-primary { background: #00b09b; color: white; }
        .btn-primary:hover { background: #009682; }
        .btn-sm { padding: 4px 10px; font-size: 0.8em; }

        /* Process cards */
        .process-card {
            background: white;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #00b09b;
        }
        .process-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .process-code-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 700;
            font-family: monospace;
        }
        .dept-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .mini-table th, .mini-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .mini-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.85em;
        }

        .proficiency-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .prof-Beginner { background: #e8f5e9; color: #2e7d32; }
        .prof-Intermediate { background: #e3f2fd; color: #1565c0; }
        .prof-Advanced { background: #fff3e0; color: #ef6c00; }
        .prof-Expert { background: #fce4ec; color: #c2185b; }

        .mandatory-badge { background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 600; }
        .optional-badge { background: #e2e3e5; color: #383d41; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 600; }

        /* Process flow visualization */
        .process-flow {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow-x: auto;
        }
        .flow-step {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 14px 18px;
            border-radius: 10px;
            text-align: center;
            min-width: 130px;
            flex-shrink: 0;
        }
        .flow-step .step-num {
            background: rgba(255,255,255,0.3);
            width: 26px; height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.85em;
            margin-bottom: 6px;
        }
        .flow-step .step-name { font-weight: 700; font-size: 0.9em; }
        .flow-step .step-code { font-size: 0.75em; opacity: 0.85; margin-top: 3px; }
        .flow-step .step-time { font-size: 0.75em; opacity: 0.8; margin-top: 4px; }
        .flow-arrow { font-size: 1.4em; color: #00b09b; flex-shrink: 0; }

        /* Process detail card */
        .process-detail-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #00b09b;
        }
        .process-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .step-badge {
            background: #00b09b;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 700;
            margin-right: 8px;
        }

        .skill-req-row {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .skill-req-row:last-child { border-bottom: none; }
        .skill-info {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .qualified-employees {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            align-items: center;
            padding-left: 10px;
        }
        .emp-chip {
            background: #e8eaf6;
            color: #3f51b5;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            white-space: nowrap;
        }
        .emp-chip.certified { background: #fff8e1; color: #f57f17; }
        .no-emp-warning { color: #e74c3c; font-weight: 600; font-size: 0.85em; }

        /* Skill Matrix */
        .matrix-wrap { overflow-x: auto; margin-top: 15px; }
        .matrix-table {
            border-collapse: collapse;
            font-size: 0.85em;
            min-width: 100%;
        }
        .matrix-table th, .matrix-table td {
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            text-align: center;
        }
        .matrix-table thead th {
            background: #00b09b;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .matrix-table thead th.rotate {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            max-width: 40px;
            padding: 10px 6px;
            font-size: 0.85em;
        }
        .matrix-table tbody td:first-child,
        .matrix-table tbody td:nth-child(2) {
            text-align: left;
            white-space: nowrap;
            font-weight: 600;
        }
        .matrix-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .matrix-table tbody tr:hover { background: #e8f5e9; }
        .qual-full { color: #27ae60; font-weight: bold; font-size: 1.2em; }
        .qual-partial { color: #f39c12; font-weight: bold; font-size: 1.1em; }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-section label { font-weight: 600; color: #495057; }
        .filter-section select {
            padding: 8px 14px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            min-width: 300px;
        }

        .product-info-bar {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .product-info-bar .info-item { font-size: 0.9em; }
        .product-info-bar .info-label { color: #666; font-size: 0.8em; }
        .product-info-bar .info-value { font-weight: 700; color: #2c3e50; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        .empty-state .icon { font-size: 3em; margin-bottom: 10px; }

        .table-responsive { overflow-x: auto; }

        /* Dark mode */
        body.dark .pm-header { background: linear-gradient(135deg, #006d5b 0%, #5a7a22 100%); }
        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-card .stat-value { color: #ecf0f1; }
        body.dark .form-section, body.dark .process-card, body.dark .process-detail-card,
        body.dark .process-flow, body.dark .filter-section { background: #2c3e50; }
        body.dark .form-section h3, body.dark .process-card-header { color: #ecf0f1; }
        body.dark .form-group label { color: #bdc3c7; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea {
            background: #34495e; border-color: #4a6278; color: #ecf0f1;
        }
        body.dark .mini-table th { background: #34495e; color: #ecf0f1; }
        body.dark .mini-table td { border-bottom-color: #3d566e; }
        body.dark .skill-req-row { border-bottom-color: #3d566e; }
        body.dark .product-info-bar { background: linear-gradient(135deg, #2c3e50, #34495e); }
        body.dark .product-info-bar .info-value { color: #ecf0f1; }
        body.dark .matrix-table td { border-color: #3d566e; }
        body.dark .matrix-table tbody tr:nth-child(even) { background: #34495e; }
        body.dark .matrix-table tbody tr:hover { background: #1e5631; }
        body.dark .tab { color: #bdc3c7; }
        body.dark .tab.active { color: #00b09b; }
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

<div class="content" style="overflow-y: auto; height: 100vh;">

    <!-- Header -->
    <div class="pm-header">
        <h1>Process Mapping</h1>
        <p>Map manufacturing processes, skill requirements, and qualified employees for YID products</p>
    </div>

    <!-- Message -->
    <?php if ($msg): ?>
        <div class="msg-bar <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $statProcesses ?></div>
            <div class="stat-label">Active Processes</div>
        </div>
        <div class="stat-card s2">
            <div class="stat-value"><?= $statMappedProducts ?></div>
            <div class="stat-label">Mapped Products</div>
        </div>
        <div class="stat-card s3">
            <div class="stat-value"><?= $statSkillReqs ?></div>
            <div class="stat-label">Skill Requirements</div>
        </div>
        <div class="stat-card s4">
            <div class="stat-value"><?= $statTotalYid ?></div>
            <div class="stat-label">YID Products</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=product_mapping" class="tab <?= $tab === 'product_mapping' ? 'active' : '' ?>">Product Process Mapping</a>
        <a href="?tab=processes" class="tab <?= $tab === 'processes' ? 'active' : '' ?>">Process Master</a>
        <a href="?tab=skill_matrix" class="tab <?= $tab === 'skill_matrix' ? 'active' : '' ?>">Employee Skill Matrix</a>
    </div>

    <!-- ===================== TAB 1: Product Process Mapping ===================== -->
    <?php if ($tab === 'product_mapping'): ?>

        <!-- Product Selector -->
        <div class="filter-section">
            <label>Select YID Product:</label>
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="tab" value="product_mapping">
                <select name="product" onchange="this.form.submit()" style="min-width: 350px;">
                    <option value="">-- Select a Product --</option>
                    <?php foreach ($yidProducts as $prod): ?>
                        <option value="<?= htmlspecialchars($prod['part_no']) ?>" <?= $selectedProduct === $prod['part_no'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prod['part_no'] . ' - ' . $prod['part_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedProduct && $selectedProductInfo): ?>

            <!-- Product Info Bar -->
            <div class="product-info-bar">
                <div class="info-item">
                    <div class="info-label">Part No</div>
                    <div class="info-value"><?= htmlspecialchars($selectedProductInfo['part_no']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Product Name</div>
                    <div class="info-value"><?= htmlspecialchars($selectedProductInfo['part_name']) ?></div>
                </div>
                <?php if ($selectedProductInfo['description']): ?>
                <div class="info-item">
                    <div class="info-label">Description</div>
                    <div class="info-value"><?= htmlspecialchars($selectedProductInfo['description']) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">UOM</div>
                    <div class="info-value"><?= htmlspecialchars($selectedProductInfo['uom'] ?? 'Nos') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Processes</div>
                    <div class="info-value"><?= count($productProcesses) ?></div>
                </div>
                <?php
                    $totalCycle = 0;
                    foreach ($productProcesses as $pp) $totalCycle += (float)($pp['cycle_time_minutes'] ?? 0);
                ?>
                <?php if ($totalCycle > 0): ?>
                <div class="info-item">
                    <div class="info-label">Total Cycle Time</div>
                    <div class="info-value"><?= number_format($totalCycle, 1) ?> min</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($productProcesses)): ?>
                <!-- Process Flow Visualization -->
                <div class="process-flow">
                    <?php foreach ($productProcesses as $i => $pp): ?>
                        <div class="flow-step">
                            <div class="step-num"><?= $pp['sequence_order'] ?></div>
                            <div class="step-name"><?= htmlspecialchars($pp['process_name']) ?></div>
                            <div class="step-code"><?= htmlspecialchars($pp['process_code']) ?></div>
                            <?php if ($pp['cycle_time_minutes']): ?>
                                <div class="step-time"><?= $pp['cycle_time_minutes'] ?> min</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($i < count($productProcesses) - 1): ?>
                            <div class="flow-arrow">&#10140;</div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Update Sequence Form -->
                <form method="post" style="margin-bottom: 20px;">
                    <input type="hidden" name="_tab" value="product_mapping">
                    <input type="hidden" name="_product" value="<?= htmlspecialchars($selectedProduct) ?>">

                    <!-- Detailed Process Cards -->
                    <?php foreach ($productProcesses as $pp): ?>
                    <div class="process-detail-card">
                        <div class="process-detail-header">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span class="step-badge">#<?= $pp['sequence_order'] ?></span>
                                <strong style="font-size: 1.1em;"><?= htmlspecialchars($pp['process_name']) ?></strong>
                                <span class="process-code-badge"><?= htmlspecialchars($pp['process_code']) ?></span>
                                <?php if ($pp['department']): ?>
                                    <span class="dept-badge"><?= htmlspecialchars($pp['department']) ?></span>
                                <?php endif; ?>
                                <?php if ($pp['cycle_time_minutes']): ?>
                                    <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 12px; font-size: 0.8em;"><?= $pp['cycle_time_minutes'] ?> min</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="pp_ids[]" value="<?= $pp['id'] ?>">
                                <label style="font-size: 0.8em; color: #666;">Seq:</label>
                                <input type="number" name="sequences[]" value="<?= $pp['sequence_order'] ?>" min="1" style="width: 55px; padding: 4px 6px; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
                                <form method="post" style="display: inline;" onsubmit="return confirm('Remove this process from product?');">
                                    <input type="hidden" name="_product" value="<?= htmlspecialchars($selectedProduct) ?>">
                                    <input type="hidden" name="pp_id" value="<?= $pp['id'] ?>">
                                    <button type="submit" name="remove_product_process" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </div>
                        </div>

                        <?php if ($pp['notes']): ?>
                            <div style="font-size: 0.85em; color: #666; margin-bottom: 10px; font-style: italic;"><?= htmlspecialchars($pp['notes']) ?></div>
                        <?php endif; ?>

                        <!-- Skill Requirements -->
                        <div style="margin-top: 10px;">
                            <div style="font-weight: 600; font-size: 0.9em; color: #495057; margin-bottom: 8px;">Skill Requirements:</div>
                            <?php if (empty($pp['skills'])): ?>
                                <p style="color: #999; font-size: 0.85em; margin: 0;">No skill requirements defined.
                                    <a href="?tab=processes" style="color: #00b09b;">Add skills in Process Master</a>
                                </p>
                            <?php else: ?>
                                <?php foreach ($pp['skills'] as $sk): ?>
                                <div class="skill-req-row">
                                    <div class="skill-info">
                                        <strong style="color: #2c3e50;"><?= htmlspecialchars($sk['skill_name']) ?></strong>
                                        <span style="background: #f0f0f0; color: #666; padding: 2px 6px; border-radius: 4px; font-size: 0.75em;"><?= htmlspecialchars($sk['category_name']) ?></span>
                                        <span class="proficiency-badge prof-<?= $sk['min_proficiency'] ?>">Min: <?= $sk['min_proficiency'] ?></span>
                                        <?php if ($sk['is_mandatory']): ?>
                                            <span class="mandatory-badge">Mandatory</span>
                                        <?php else: ?>
                                            <span class="optional-badge">Optional</span>
                                        <?php endif; ?>
                                        <span style="color: #666; font-size: 0.8em; margin-left: 5px;">|</span>
                                        <span style="font-size: 0.8em; color: <?= count($sk['qualified_employees']) > 0 ? '#27ae60' : '#e74c3c' ?>; font-weight: 600;">
                                            <?= count($sk['qualified_employees']) ?> qualified
                                        </span>
                                    </div>
                                    <div class="qualified-employees">
                                        <?php if (empty($sk['qualified_employees'])): ?>
                                            <span class="no-emp-warning">No qualified employees!</span>
                                        <?php else: ?>
                                            <?php foreach ($sk['qualified_employees'] as $emp): ?>
                                                <span class="emp-chip <?= $emp['certified'] ? 'certified' : '' ?>" title="<?= htmlspecialchars($emp['proficiency_level']) . ($emp['certified'] ? ' | Certified: ' . $emp['certification_name'] : '') ?>">
                                                    <?= htmlspecialchars($emp['first_name'] . ' ' . substr($emp['last_name'], 0, 1) . '.') ?>
                                                    <small>(<?= substr($emp['proficiency_level'], 0, 3) ?>)</small>
                                                    <?= $emp['certified'] ? '*' : '' ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="text-align: right; margin-bottom: 20px;">
                        <button type="submit" name="update_sequence" class="btn btn-secondary">Update Sequence Order</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">&#9881;</div>
                    <p>No processes mapped to this product yet.</p>
                    <p style="font-size: 0.9em;">Use the form below to add manufacturing processes.</p>
                </div>
            <?php endif; ?>

            <!-- Add Process to Product -->
            <?php if (!empty($processes)): ?>
            <div class="form-section">
                <h3>Add Process to <?= htmlspecialchars($selectedProduct) ?></h3>
                <form method="post">
                    <input type="hidden" name="_tab" value="product_mapping">
                    <input type="hidden" name="_product" value="<?= htmlspecialchars($selectedProduct) ?>">
                    <input type="hidden" name="part_no" value="<?= htmlspecialchars($selectedProduct) ?>">
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label>Process *</label>
                            <select name="process_id" required>
                                <option value="">-- Select Process --</option>
                                <?php foreach ($processes as $proc): ?>
                                    <option value="<?= $proc['id'] ?>"><?= htmlspecialchars($proc['process_code'] . ' - ' . $proc['process_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 0; min-width: 100px;">
                            <label>Sequence #</label>
                            <input type="number" name="sequence_order" min="1" value="<?= count($productProcesses) + 1 ?>" style="width: 80px;">
                        </div>
                        <div class="form-group" style="flex: 0; min-width: 130px;">
                            <label>Cycle Time (min)</label>
                            <input type="number" name="cycle_time_minutes" step="0.5" min="0" placeholder="0.0" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" name="notes" placeholder="Optional notes...">
                        </div>
                        <div class="form-group" style="flex: 0; min-width: auto;">
                            <label>&nbsp;</label>
                            <button type="submit" name="add_product_process" class="btn btn-success">Add</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="msg-bar error">No processes defined yet. <a href="?tab=processes" style="color: #721c24; font-weight: bold;">Create processes first</a></div>
            <?php endif; ?>

        <?php elseif ($selectedProduct && !$selectedProductInfo): ?>
            <div class="empty-state">
                <div class="icon">&#9888;</div>
                <p>Product "<?= htmlspecialchars($selectedProduct) ?>" not found in YID products.</p>
            </div>
        <?php elseif (empty($yidProducts)): ?>
            <div class="empty-state">
                <div class="icon">&#128230;</div>
                <p>No YID products found in Part Master.</p>
                <p style="font-size: 0.9em;">Please add parts with Part ID = "YID" first.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">&#128270;</div>
                <p>Select a YID product above to view or manage its process mapping.</p>
            </div>
        <?php endif; ?>

    <!-- ===================== TAB 2: Process Master ===================== -->
    <?php elseif ($tab === 'processes'): ?>

        <?php if (!$skillsAvailable): ?>
            <div class="msg-bar error">Skills tables not found. Please run <a href="/admin/setup_hr_appraisal.php" style="color: #721c24; font-weight: bold;">HR Appraisal Setup</a> to create skill categories and skills master.</div>
        <?php endif; ?>

        <!-- Add/Edit Process Form -->
        <div class="form-section">
            <h3><?= $editProcess ? 'Edit Process' : 'Add New Process' ?></h3>
            <form method="post">
                <?php if ($editProcess): ?>
                    <input type="hidden" name="process_id" value="<?= $editProcess['id'] ?>">
                <?php endif; ?>
                <input type="hidden" name="_tab" value="processes">
                <div class="form-row">
                    <div class="form-group" style="min-width: 120px; flex: 0;">
                        <label>Process Code *</label>
                        <input type="text" name="process_code" required placeholder="e.g., CNC-01" value="<?= htmlspecialchars($editProcess['process_code'] ?? '') ?>" style="text-transform: uppercase; width: 130px;">
                    </div>
                    <div class="form-group">
                        <label>Process Name *</label>
                        <input type="text" name="process_name" required placeholder="e.g., CNC Turning" value="<?= htmlspecialchars($editProcess['process_name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="min-width: 140px;">
                        <label>Department</label>
                        <input type="text" name="department" placeholder="e.g., Machining" value="<?= htmlspecialchars($editProcess['department'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Brief description..." value="<?= htmlspecialchars($editProcess['description'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex: 0; min-width: 80px;">
                        <label>Sort #</label>
                        <input type="number" name="sort_order" min="0" value="<?= (int)($editProcess['sort_order'] ?? 0) ?>" style="width: 70px;">
                    </div>
                    <div class="form-group" style="flex: 0; min-width: auto;">
                        <label>&nbsp;</label>
                        <?php if ($editProcess): ?>
                            <button type="submit" name="edit_process" class="btn btn-primary">Update</button>
                        <?php else: ?>
                            <button type="submit" name="add_process" class="btn btn-success">Add</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($editProcess): ?>
                    <div class="form-group" style="flex: 0; min-width: auto;">
                        <label>&nbsp;</label>
                        <a href="?tab=processes" class="btn btn-secondary">Cancel</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Process List -->
        <?php if (empty($allProcesses)): ?>
            <div class="empty-state">
                <div class="icon">&#9881;</div>
                <p>No processes defined yet. Use the form above to create your first process.</p>
            </div>
        <?php else: ?>
            <?php foreach ($allProcesses as $proc):
                $pSkills = $processSkills[$proc['id']] ?? [];
            ?>
            <div class="process-card" style="<?= !$proc['is_active'] ? 'opacity: 0.6;' : '' ?>">
                <div class="process-card-header">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span class="process-code-badge"><?= htmlspecialchars($proc['process_code']) ?></span>
                        <strong style="font-size: 1.05em;"><?= htmlspecialchars($proc['process_name']) ?></strong>
                        <?php if ($proc['department']): ?>
                            <span class="dept-badge"><?= htmlspecialchars($proc['department']) ?></span>
                        <?php endif; ?>
                        <?php if ($proc['description']): ?>
                            <span style="color: #888; font-size: 0.85em;">- <?= htmlspecialchars($proc['description']) ?></span>
                        <?php endif; ?>
                        <?php if (!$proc['is_active']): ?>
                            <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 4px; font-size: 0.75em;">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <a href="?tab=processes&edit=<?= $proc['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this process and all its skill requirements?');">
                            <input type="hidden" name="process_id" value="<?= $proc['id'] ?>">
                            <input type="hidden" name="_tab" value="processes">
                            <button type="submit" name="delete_process" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>

                <!-- Skills for this process -->
                <div style="margin-top: 10px;">
                    <?php if (!empty($pSkills)): ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Skill</th>
                                    <th>Category</th>
                                    <th>Min Proficiency</th>
                                    <th>Required</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pSkills as $ps): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ps['skill_name']) ?></strong></td>
                                    <td style="color: #666;"><?= htmlspecialchars($ps['category_name']) ?></td>
                                    <td><span class="proficiency-badge prof-<?= $ps['min_proficiency'] ?>"><?= $ps['min_proficiency'] ?></span></td>
                                    <td>
                                        <?php if ($ps['is_mandatory']): ?>
                                            <span class="mandatory-badge">Mandatory</span>
                                        <?php else: ?>
                                            <span class="optional-badge">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Remove this skill?');">
                                            <input type="hidden" name="ps_id" value="<?= $ps['id'] ?>">
                                            <input type="hidden" name="_tab" value="processes">
                                            <button type="submit" name="remove_process_skill" class="btn btn-danger btn-sm" style="padding: 2px 6px;">X</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #aaa; font-size: 0.85em; margin: 0 0 8px;">No skill requirements defined.</p>
                    <?php endif; ?>

                    <!-- Add Skill to Process -->
                    <?php if ($skillsAvailable && !empty($skills)): ?>
                    <form method="post" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #dee2e6;">
                        <input type="hidden" name="process_id" value="<?= $proc['id'] ?>">
                        <input type="hidden" name="_tab" value="processes">
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select name="skill_id" required style="padding: 6px 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.9em; min-width: 200px;">
                                <option value="">-- Add Skill --</option>
                                <?php foreach ($skillsByCategory as $catName => $catSkills): ?>
                                    <optgroup label="<?= htmlspecialchars($catName) ?>">
                                        <?php foreach ($catSkills as $sk): ?>
                                            <option value="<?= $sk['id'] ?>"><?= htmlspecialchars($sk['skill_name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <select name="min_proficiency" style="padding: 6px 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.9em;">
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced" selected>Advanced</option>
                                <option value="Expert">Expert</option>
                            </select>
                            <label style="display: flex; align-items: center; gap: 4px; font-size: 0.85em; white-space: nowrap;">
                                <input type="checkbox" name="is_mandatory" value="1" checked> Mandatory
                            </label>
                            <button type="submit" name="add_process_skill" class="btn btn-success btn-sm">+ Add Skill</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- ===================== TAB 3: Employee Skill Matrix ===================== -->
    <?php elseif ($tab === 'skill_matrix'): ?>

        <?php if (empty($processes)): ?>
            <div class="empty-state">
                <div class="icon">&#9881;</div>
                <p>No processes defined yet. <a href="?tab=processes" style="color: #00b09b;">Create processes first</a></p>
            </div>
        <?php elseif (empty($employees)): ?>
            <div class="empty-state">
                <div class="icon">&#128100;</div>
                <p>No active employees found.</p>
            </div>
        <?php else: ?>
            <div class="form-section">
                <h3>Employee Skill Matrix</h3>
                <p style="color: #666; font-size: 0.9em; margin: 0;">
                    <span class="qual-full">&#10003;</span> = Fully qualified (meets all mandatory skills) &nbsp;&nbsp;
                    <span class="qual-partial">~</span> = Partially qualified &nbsp;&nbsp;
                    <span style="color: #ccc;">-</span> = Not qualified
                </p>
            </div>

            <div class="matrix-wrap">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; min-width: 160px;">Employee</th>
                            <th style="text-align: left; min-width: 100px;">Department</th>
                            <?php foreach ($processes as $proc): ?>
                                <th class="rotate" title="<?= htmlspecialchars($proc['process_name']) ?>">
                                    <?= htmlspecialchars($proc['process_code']) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                            <td style="color: #666; font-weight: normal; font-size: 0.85em;"><?= htmlspecialchars($emp['department'] ?: '-') ?></td>
                            <?php foreach ($processes as $proc):
                                $qual = getQualification($emp['id'], $proc['id'], $empSkillMap, $processSkillReqs);
                            ?>
                                <td>
                                    <?php if ($qual === 'full'): ?>
                                        <span class="qual-full" title="Fully Qualified">&#10003;</span>
                                    <?php elseif ($qual === 'partial'): ?>
                                        <span class="qual-partial" title="Partially Qualified">~</span>
                                    <?php else: ?>
                                        <span style="color: #dee2e6;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
