<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_supplier_audits a $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get audits
try {
    $sql = "
        SELECT a.*, s.name as supplier_name,
               (SELECT COUNT(*) FROM qc_supplier_audit_findings WHERE audit_id = a.id) as finding_count
        FROM qc_supplier_audits a
        LEFT JOIN suppliers s ON a.supplier_id = s.id
        $where_clause
        ORDER BY a.audit_date DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $audits = [];
}

$statuses = ['Planned', 'In Progress', 'Completed', 'Closed'];
$audit_types = ['Initial', 'Periodic', 'Process', 'Product', 'Special', 'Re-audit'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Audits - QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .filter-section {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .audit-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        .audit-card.result-approved { border-left-color: #27ae60; }
        .audit-card.result-conditional { border-left-color: #f39c12; }
        .audit-card.result-not-approved { border-left-color: #e74c3c; }

        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .audit-no { color: #667eea; font-weight: 600; }
        .audit-supplier { font-size: 1.1em; font-weight: 600; color: #2c3e50; margin: 5px 0 0; }

        .audit-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9em;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-planned { background: #cce5ff; color: #004085; }
        .status-in-progress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-closed { background: #e2e3e5; color: #383d41; }

        .result-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .result-approved { background: #d4edda; color: #155724; }
        .result-conditional { background: #fff3cd; color: #856404; }
        .result-not-approved { background: #f8d7da; color: #721c24; }
        .result-pending { background: #e2e3e5; color: #383d41; }

        .audit-score {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .audit-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        body.dark .audit-card { background: #2c3e50; }
        body.dark .audit-supplier { color: #ecf0f1; }
        body.dark .filter-section { background: #34495e; }
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

<div class="content">
    <div class="page-header">
        <div>
            <h1>Supplier Audits</h1>
            <p style="color: #666; margin: 5px 0 0;">Supplier quality audits and assessments</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="audit_add.php" class="btn btn-primary">+ New Audit</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center;">
            <label style="font-weight: 600;">Status:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($status_filter): ?>
                <a href="audits.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> audit<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Audits List -->
    <?php if (empty($audits)): ?>
        <div class="empty-state">
            <h3>No Audits Found</h3>
            <p>Schedule your first supplier audit.</p>
            <a href="audit_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Audit</a>
        </div>
    <?php else: ?>
        <?php foreach ($audits as $audit): ?>
            <div class="audit-card result-<?= strtolower(str_replace(' ', '-', $audit['audit_result'])) ?>">
                <div class="audit-header">
                    <div>
                        <span class="audit-no"><?= htmlspecialchars($audit['audit_no']) ?></span>
                        <span style="margin-left: 10px; background: #e8eaf6; color: #3f51b5; padding: 3px 10px; border-radius: 10px; font-size: 0.85em;"><?= $audit['audit_type'] ?></span>
                        <p class="audit-supplier"><?= htmlspecialchars($audit['supplier_name'] ?: 'Unknown Supplier') ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $audit['status'])) ?>"><?= $audit['status'] ?></span>
                        <?php if ($audit['audit_result'] && $audit['audit_result'] !== 'Pending'): ?>
                            <div style="margin-top: 8px;">
                                <span class="result-badge result-<?= strtolower(str_replace(' ', '-', $audit['audit_result'])) ?>"><?= $audit['audit_result'] ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="audit-meta">
                    <span>Date: <strong><?= date('d M Y', strtotime($audit['audit_date'])) ?></strong></span>
                    <?php if ($audit['lead_auditor']): ?>
                        <span>Lead Auditor: <?= htmlspecialchars($audit['lead_auditor']) ?></span>
                    <?php endif; ?>
                    <?php if ($audit['audit_score'] > 0): ?>
                        <span>Score: <strong><?= number_format($audit['audit_score'], 1) ?>%</strong></span>
                    <?php endif; ?>
                    <?php if ($audit['finding_count'] > 0): ?>
                        <span style="background: #fff3cd; padding: 3px 10px; border-radius: 10px;">
                            <?= $audit['finding_count'] ?> finding<?= $audit['finding_count'] > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($audit['next_audit_date']): ?>
                        <span>Next Audit: <?= date('d M Y', strtotime($audit['next_audit_date'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($audit['audit_scope']): ?>
                    <p style="margin: 10px 0 0; color: #666; font-size: 0.9em;">
                        <strong>Scope:</strong> <?= htmlspecialchars(substr($audit['audit_scope'], 0, 150)) ?><?= strlen($audit['audit_scope']) > 150 ? '...' : '' ?>
                    </p>
                <?php endif; ?>

                <div class="audit-actions">
                    <a href="audit_view.php?id=<?= $audit['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                    <?php if ($audit['status'] !== 'Closed'): ?>
                        <a href="audit_edit.php?id=<?= $audit['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php if ($audit['status'] === 'Completed'): ?>
                            <a href="audit_findings.php?id=<?= $audit['id'] ?>" class="btn btn-sm" style="background: #f39c12; color: white;">Manage Findings</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
