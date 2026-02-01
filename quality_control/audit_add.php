<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

$error = '';
$success = '';

// Get suppliers
try {
    $suppliers_stmt = $pdo->query("SELECT id, supplier_name as name FROM suppliers WHERE status = 'Active' ORDER BY supplier_name");
    $suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suppliers = [];
}

$statuses = ['Planned', 'In Progress', 'Completed', 'Closed'];
$audit_types = ['Initial', 'Periodic', 'Process', 'Product', 'Special', 'Re-audit'];
$results = ['Pending', 'Approved', 'Conditional', 'Not Approved'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $supplier_id = $_POST['supplier_id'];
        $audit_type = $_POST['audit_type'];
        $audit_date = $_POST['audit_date'];
        $lead_auditor = trim($_POST['lead_auditor']);
        $audit_team = trim($_POST['audit_team']);
        $audit_scope = trim($_POST['audit_scope']);
        $status = $_POST['status'];
        $audit_result = $_POST['audit_result'] ?: 'Pending';
        $audit_score = (float)($_POST['audit_score'] ?? 0);
        $next_audit_date = $_POST['next_audit_date'] ?: null;
        $notes = trim($_POST['notes']);

        // Generate audit number
        $year = date('Y');
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM qc_supplier_audits WHERE YEAR(created_at) = $year");
        $count = $count_stmt->fetchColumn() + 1;
        $audit_no = 'AUD-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO qc_supplier_audits (
                audit_no, supplier_id, audit_type, audit_date, lead_auditor, audit_team,
                audit_scope, status, audit_result, audit_score, next_audit_date, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $audit_no, $supplier_id, $audit_type, $audit_date, $lead_auditor, $audit_team,
            $audit_scope, $status, $audit_result, $audit_score, $next_audit_date, $notes, $_SESSION['user_id']
        ]);

        header("Location: audits.php?success=1");
        exit;
    } catch (Exception $e) {
        $error = "Error creating audit: " . $e->getMessage();
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Schedule Supplier Audit - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 800px; margin: 0 auto; }
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .form-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
        }
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        body.dark .form-card { background: #2c3e50; }
        body.dark .form-card h3 { color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="form-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1 style="margin: 0; color: #2c3e50;">Schedule Supplier Audit</h1>
            <a href="audits.php" class="btn btn-secondary">Back to Audits</a>
        </div>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-card">
                <h3>Audit Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Supplier *</label>
                        <select name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Audit Type *</label>
                        <select name="audit_type" required>
                            <?php foreach ($audit_types as $type): ?>
                                <option value="<?= $type ?>"><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Audit Date *</label>
                        <input type="date" name="audit_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h3>Audit Team</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Lead Auditor *</label>
                        <input type="text" name="lead_auditor" required placeholder="Name of lead auditor">
                    </div>
                    <div class="form-group">
                        <label>Audit Team Members</label>
                        <input type="text" name="audit_team" placeholder="Other team members (comma separated)">
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h3>Scope & Results</h3>
                <div class="form-group">
                    <label>Audit Scope</label>
                    <textarea name="audit_scope" rows="4" placeholder="Describe the scope of the audit..."></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Audit Result</label>
                        <select name="audit_result">
                            <?php foreach ($results as $r): ?>
                                <option value="<?= $r ?>"><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Audit Score (%)</label>
                        <input type="number" name="audit_score" min="0" max="100" step="0.1" placeholder="0-100">
                    </div>
                    <div class="form-group">
                        <label>Next Audit Date</label>
                        <input type="date" name="next_audit_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <a href="audits.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Schedule Audit</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
