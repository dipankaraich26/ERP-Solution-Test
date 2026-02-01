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
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';

$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "n.status = ?";
    $params[] = $status_filter;
}
if (!empty($severity_filter)) {
    $where[] = "n.severity = ?";
    $params[] = $severity_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qc_supplier_ncrs n $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get NCRs
try {
    $sql = "
        SELECT n.*, s.name as supplier_name
        FROM qc_supplier_ncrs n
        LEFT JOIN suppliers s ON n.supplier_id = s.id
        $where_clause
        ORDER BY
            CASE n.severity WHEN 'Critical' THEN 1 WHEN 'Major' THEN 2 ELSE 3 END,
            CASE n.status WHEN 'Open' THEN 1 WHEN 'Supplier Notified' THEN 2 WHEN 'Response Received' THEN 3 ELSE 4 END,
            n.ncr_date DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ncrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ncrs = [];
}

$statuses = ['Open', 'Supplier Notified', 'Response Received', 'Verification Pending', 'Closed', 'Escalated'];
$severities = ['Critical', 'Major', 'Minor'];

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier NCRs - QC</title>
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

        .ncr-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        .ncr-card.severity-critical { border-left-color: #c62828; }
        .ncr-card.severity-major { border-left-color: #f57c00; }
        .ncr-card.severity-minor { border-left-color: #1976d2; }

        .ncr-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .ncr-no { color: #667eea; font-weight: 600; }
        .ncr-title { font-size: 1em; color: #2c3e50; margin: 5px 0 0; }

        .ncr-meta {
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
        .status-open { background: #cce5ff; color: #004085; }
        .status-supplier-notified { background: #fff3cd; color: #856404; }
        .status-response-received { background: #d4edda; color: #155724; }
        .status-verification-pending { background: #e2e3e5; color: #383d41; }
        .status-closed { background: #d4edda; color: #155724; }
        .status-escalated { background: #f8d7da; color: #721c24; }

        .severity-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .severity-critical { background: #ffebee; color: #c62828; }
        .severity-major { background: #fff3e0; color: #e65100; }
        .severity-minor { background: #e3f2fd; color: #1565c0; }

        .ncr-actions {
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

        body.dark .ncr-card { background: #2c3e50; }
        body.dark .ncr-title { color: #ecf0f1; }
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
            <h1>Supplier NCRs</h1>
            <p style="color: #666; margin: 5px 0 0;">Non-Conformance Reports for supplier quality issues</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="ncr_add.php" class="btn btn-primary">+ New NCR</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label style="font-weight: 600; margin-right: 5px;">Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight: 600; margin-right: 5px;">Severity:</label>
                <select name="severity" onchange="this.form.submit()">
                    <option value="">All Severities</option>
                    <?php foreach ($severities as $sv): ?>
                        <option value="<?= $sv ?>" <?= $severity_filter === $sv ? 'selected' : '' ?>><?= $sv ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($status_filter || $severity_filter): ?>
                <a href="ncrs.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> NCR<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- NCR List -->
    <?php if (empty($ncrs)): ?>
        <div class="empty-state">
            <h3>No NCRs Found</h3>
            <p>No supplier non-conformances recorded yet.</p>
            <a href="ncr_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New NCR</a>
        </div>
    <?php else: ?>
        <?php foreach ($ncrs as $ncr): ?>
            <div class="ncr-card severity-<?= strtolower($ncr['severity']) ?>">
                <div class="ncr-header">
                    <div>
                        <span class="ncr-no"><?= htmlspecialchars($ncr['ncr_no']) ?></span>
                        <span class="severity-badge severity-<?= strtolower($ncr['severity']) ?>" style="margin-left: 10px;">
                            <?= $ncr['severity'] ?>
                        </span>
                        <p class="ncr-title"><strong><?= htmlspecialchars($ncr['supplier_name'] ?: 'Unknown Supplier') ?></strong> - <?= htmlspecialchars($ncr['defect_type']) ?></p>
                    </div>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ncr['status'])) ?>">
                        <?= $ncr['status'] ?>
                    </span>
                </div>

                <div class="ncr-meta">
                    <?php if ($ncr['part_no']): ?>
                        <span>Part: <strong><?= htmlspecialchars($ncr['part_no']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($ncr['po_no']): ?>
                        <span>PO: <?= htmlspecialchars($ncr['po_no']) ?></span>
                    <?php endif; ?>
                    <span>Date: <?= date('d M Y', strtotime($ncr['ncr_date'])) ?></span>
                    <?php if ($ncr['qty_affected'] > 0): ?>
                        <span>Qty Affected: <strong><?= $ncr['qty_affected'] ?></strong></span>
                    <?php endif; ?>
                    <?php if ($ncr['cost_impact'] > 0): ?>
                        <span style="color: #e74c3c;">Cost Impact: <strong>Rs. <?= number_format($ncr['cost_impact'], 0) ?></strong></span>
                    <?php endif; ?>
                </div>

                <?php if ($ncr['description']): ?>
                    <p style="margin: 10px 0 0; color: #666; font-size: 0.9em;">
                        <?= htmlspecialchars(substr($ncr['description'], 0, 200)) ?><?= strlen($ncr['description']) > 200 ? '...' : '' ?>
                    </p>
                <?php endif; ?>

                <div class="ncr-actions">
                    <a href="ncr_view.php?id=<?= $ncr['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                    <?php if ($ncr['status'] !== 'Closed'): ?>
                        <a href="ncr_edit.php?id=<?= $ncr['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php if ($ncr['status'] === 'Open'): ?>
                            <a href="ncr_notify.php?id=<?= $ncr['id'] ?>" class="btn btn-sm" style="background: #3498db; color: white;">Notify Supplier</a>
                        <?php elseif ($ncr['status'] === 'Response Received'): ?>
                            <a href="ncr_verify.php?id=<?= $ncr['id'] ?>" class="btn btn-sm" style="background: #27ae60; color: white;">Verify</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
