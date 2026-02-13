<?php
include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";
requireLogin();

showModal();

// Get logged-in user's employee_id
$myEmployeeId = $_SESSION['employee_id'] ?? null;

// Auto-create SO approval tables if missing
try {
    $pdo->query("SELECT 1 FROM so_release_approvals LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_release_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            so_no VARCHAR(50) NOT NULL,
            requested_by INT DEFAULT NULL,
            approver_id INT NOT NULL,
            status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            remarks TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME DEFAULT NULL
        )
    ");
}
try {
    $pdo->query("SELECT 1 FROM so_approvers LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS so_approvers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL UNIQUE,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $myEmployeeId) {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $approval_id = (int)($_POST['approval_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($approval_id && in_array($action, ['approve', 'reject'])) {
        // Determine table and related updates based on type
        $allowed = false;

        if ($type === 'po_inspection') {
            $row = $pdo->prepare("SELECT * FROM po_inspection_approvals WHERE id = ? AND approver_id = ? AND status = 'Pending'");
            $row->execute([$approval_id, $myEmployeeId]);
            $record = $row->fetch();
            if ($record) {
                $allowed = true;
                if ($action === 'reject' && empty($remarks)) {
                    setModal("Error", "Please provide a reason for rejection.");
                } else {
                    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE po_inspection_approvals SET status = ?, approved_at = NOW(), remarks = ? WHERE id = ?")->execute([$newStatus, $remarks ?: null, $approval_id]);
                    if ($record['checklist_id']) {
                        $pdo->prepare("UPDATE po_inspection_checklists SET status = ? WHERE id = ?")->execute([$newStatus, $record['checklist_id']]);
                    }
                    $pdo->commit();
                    setModal("Success", "Incoming inspection " . strtolower($newStatus) . " successfully.");
                }
            }
        } elseif ($type === 'wo_closing') {
            $row = $pdo->prepare("SELECT * FROM wo_closing_approvals WHERE id = ? AND approver_id = ? AND status = 'Pending'");
            $row->execute([$approval_id, $myEmployeeId]);
            $record = $row->fetch();
            if ($record) {
                $allowed = true;
                if ($action === 'reject' && empty($remarks)) {
                    setModal("Error", "Please provide a reason for rejection.");
                } else {
                    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE wo_closing_approvals SET status = ?, approved_at = NOW(), remarks = ? WHERE id = ?")->execute([$newStatus, $remarks ?: null, $approval_id]);
                    if ($record['checklist_id']) {
                        $pdo->prepare("UPDATE wo_quality_checklists SET status = ? WHERE id = ?")->execute([$newStatus, $record['checklist_id']]);
                    }
                    $pdo->commit();
                    setModal("Success", "Work order closing " . strtolower($newStatus) . " successfully.");
                }
            }
        } elseif ($type === 'so_release') {
            $row = $pdo->prepare("SELECT * FROM so_release_approvals WHERE id = ? AND approver_id = ? AND status = 'Pending'");
            $row->execute([$approval_id, $myEmployeeId]);
            $record = $row->fetch();
            if ($record) {
                $allowed = true;
                if ($action === 'reject' && empty($remarks)) {
                    setModal("Error", "Please provide a reason for rejection.");
                } else {
                    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
                    $pdo->prepare("UPDATE so_release_approvals SET status = ?, approved_at = NOW(), remarks = ? WHERE id = ?")->execute([$newStatus, $remarks ?: null, $approval_id]);
                    setModal("Success", "Sales order release " . strtolower($newStatus) . " successfully.");
                }
            }
        }

        if (!$allowed) {
            setModal("Error", "You are not authorized to perform this action.");
        }

        header("Location: index.php");
        exit;
    }
}

// Filter
$tab = $_GET['tab'] ?? 'all';

// Fetch pending approvals for this approver
$poInspections = [];
$woClosings = [];
$soReleases = [];

if ($myEmployeeId) {
    // PO Incoming Inspections
    try {
        $poInspections = $pdo->prepare("
            SELECT a.*, pic.checklist_no, pic.overall_result, pic.inspector_name, pic.inspection_date,
                   MAX(s.supplier_name) as supplier_name, u.full_name as requested_by_name
            FROM po_inspection_approvals a
            LEFT JOIN po_inspection_checklists pic ON a.checklist_id = pic.id
            LEFT JOIN purchase_orders po ON po.po_no = a.po_no
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN users u ON u.id = a.requested_by
            WHERE a.approver_id = ?
            GROUP BY a.id
            ORDER BY a.status = 'Pending' DESC, a.id DESC
        ");
        $poInspections->execute([$myEmployeeId]);
        $poInspections = $poInspections->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $poInspections = []; $poError = $e->getMessage(); }

    // WO Closing Approvals
    try {
        $woClosings = $pdo->prepare("
            SELECT a.*, qc.checklist_no, qc.overall_result, qc.inspector_name,
                   w.wo_no, w.part_no, pm.part_name, w.qty,
                   u.full_name as requested_by_name
            FROM wo_closing_approvals a
            LEFT JOIN wo_quality_checklists qc ON a.checklist_id = qc.id
            LEFT JOIN work_orders w ON a.work_order_id = w.id
            LEFT JOIN part_master pm ON w.part_no = pm.part_no
            LEFT JOIN users u ON u.id = a.requested_by
            WHERE a.approver_id = ?
            GROUP BY a.id
            ORDER BY a.status = 'Pending' DESC, a.id DESC
        ");
        $woClosings->execute([$myEmployeeId]);
        $woClosings = $woClosings->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $woClosings = []; $woError = $e->getMessage(); }

    // SO Release Approvals
    try {
        $soReleases = $pdo->prepare("
            SELECT a.*,
                   c.company_name, c.customer_name,
                   u.full_name as requested_by_name
            FROM so_release_approvals a
            LEFT JOIN sales_orders so2 ON so2.so_no = a.so_no
            LEFT JOIN customers c ON c.id = so2.customer_id
            LEFT JOIN users u ON u.id = a.requested_by
            WHERE a.approver_id = ?
            GROUP BY a.id
            ORDER BY a.status = 'Pending' DESC, a.id DESC
        ");
        $soReleases->execute([$myEmployeeId]);
        $soReleases = $soReleases->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $soReleases = []; $soError = $e->getMessage(); }
}

// Counts
$pendingPO = count(array_filter($poInspections, fn($r) => $r['status'] === 'Pending'));
$pendingWO = count(array_filter($woClosings, fn($r) => $r['status'] === 'Pending'));
$pendingSO = count(array_filter($soReleases, fn($r) => $r['status'] === 'Pending'));
$totalPending = $pendingPO + $pendingWO + $pendingSO;

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Approvals</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .stats-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 140px; padding: 18px 20px; border-radius: 10px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; color: inherit; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.12); }
        .stat-card.active-tab { border: 2px solid #667eea; }
        .stat-card .stat-value { font-size: 2em; font-weight: 700; margin-bottom: 4px; }
        .stat-card .stat-label { font-size: 0.85em; color: #666; }

        .no-employee-notice { background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 10px; margin-bottom: 25px; color: #856404; }

        .approval-section { margin-bottom: 30px; }
        .section-title { font-size: 1.1em; font-weight: 700; color: #2c3e50; margin-bottom: 12px; padding: 10px 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
        .section-title .badge { background: #e74c3c; color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.8em; margin-left: 10px; }

        .approval-card { background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
        .approval-card.pending { border-left: 4px solid #f59e0b; }
        .approval-card.approved { border-left: 4px solid #10b981; opacity: 0.7; }
        .approval-card.rejected { border-left: 4px solid #ef4444; opacity: 0.7; }

        .approval-info { flex: 1; min-width: 250px; }
        .approval-info .ref-no { font-weight: 700; font-size: 1.1em; color: #2c3e50; }
        .approval-info .ref-no a { color: #667eea; text-decoration: none; }
        .approval-info .ref-no a:hover { text-decoration: underline; }
        .approval-info .meta { color: #666; font-size: 0.9em; margin-top: 5px; line-height: 1.6; }

        .approval-actions { display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; }
        .approval-actions form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .approval-actions input[type="text"] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 200px; font-size: 0.9em; }

        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        .result-pass { background: #d1fae5; color: #065f46; }
        .result-fail { background: #fee2e2; color: #991b1b; }
        .result-pending { background: #e2e3e5; color: #383d41; }
        .result-conditional { background: #fff3cd; color: #856404; }

        .empty-state { text-align: center; padding: 40px; color: #9ca3af; background: white; border-radius: 10px; border: 1px dashed #d1d5db; }

        body.dark .approval-card { background: #2c3e50; border-color: #4a5568; }
        body.dark .section-title { background: #34495e; color: #ecf0f1; }
        body.dark .stat-card { background: #34495e; color: #ecf0f1; }
        body.dark .stat-card .stat-label { color: #bdc3c7; }
        body.dark .approval-info .ref-no { color: #ecf0f1; }
        body.dark .approval-info .meta { color: #bdc3c7; }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <div>
            <h1>My Approvals</h1>
            <p style="color: #666; margin: 5px 0 0;">Pending approvals assigned to you</p>
        </div>
    </div>

    <?php if (!$myEmployeeId): ?>
    <div class="no-employee-notice">
        <strong>Employee Not Linked</strong><br>
        Your user account is not linked to an employee record. Please ask an administrator to link your account in
        <a href="/admin/users.php">User Management</a> so approvals can be assigned to you.
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <a href="?tab=all" class="stat-card <?= $tab === 'all' ? 'active-tab' : '' ?>">
            <div class="stat-value" style="color: <?= $totalPending > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $totalPending ?></div>
            <div class="stat-label">All Pending</div>
        </a>
        <a href="?tab=po" class="stat-card <?= $tab === 'po' ? 'active-tab' : '' ?>">
            <div class="stat-value" style="color: #3498db;"><?= $pendingPO ?></div>
            <div class="stat-label">Incoming Inspections</div>
        </a>
        <a href="?tab=wo" class="stat-card <?= $tab === 'wo' ? 'active-tab' : '' ?>">
            <div class="stat-value" style="color: #8e44ad;"><?= $pendingWO ?></div>
            <div class="stat-label">Work Orders</div>
        </a>
        <a href="?tab=so" class="stat-card <?= $tab === 'so' ? 'active-tab' : '' ?>">
            <div class="stat-value" style="color: #e67e22;"><?= $pendingSO ?></div>
            <div class="stat-label">Sales Orders</div>
        </a>
    </div>

    <?php if ($myEmployeeId): ?>

    <?php
    // Show any query errors so issues aren't silently hidden
    $queryErrors = [];
    if (!empty($poError)) $queryErrors[] = "PO Inspections: " . $poError;
    if (!empty($woError)) $queryErrors[] = "WO Closings: " . $woError;
    if (!empty($soError)) $queryErrors[] = "SO Releases: " . $soError;
    if (!empty($queryErrors)): ?>
    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <strong>Query Error:</strong> Some approvals could not be loaded.<br>
        <?= implode('<br>', array_map('htmlspecialchars', $queryErrors)) ?>
    </div>
    <?php endif; ?>

    <!-- PO Incoming Inspections -->
    <?php if ($tab === 'all' || $tab === 'po'): ?>
    <div class="approval-section">
        <div class="section-title">
            Incoming Inspection Approvals
            <?php if ($pendingPO > 0): ?><span class="badge"><?= $pendingPO ?> pending</span><?php endif; ?>
        </div>

        <?php
        $filteredPO = $tab === 'po' ? $poInspections : array_filter($poInspections, fn($r) => $r['status'] === 'Pending');
        if (empty($filteredPO)): ?>
            <div class="empty-state">No <?= $tab === 'po' ? '' : 'pending ' ?>incoming inspection approvals</div>
        <?php else: foreach ($filteredPO as $item): ?>
            <div class="approval-card <?= strtolower($item['status']) ?>">
                <div class="approval-info">
                    <div class="ref-no">
                        <a href="/stock_entry/inspection_checklist.php?po_no=<?= urlencode($item['po_no']) ?>"><?= htmlspecialchars($item['checklist_no'] ?? 'N/A') ?></a>
                        &nbsp; <span class="status-badge status-<?= strtolower($item['status']) ?>"><?= $item['status'] ?></span>
                        <?php if ($item['overall_result']): ?>
                            <span class="status-badge result-<?= strtolower($item['overall_result']) ?>"><?= $item['overall_result'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="meta">
                        PO: <strong><?= htmlspecialchars($item['po_no']) ?></strong>
                        <?php if ($item['supplier_name']): ?> | Supplier: <?= htmlspecialchars($item['supplier_name']) ?><?php endif; ?>
                        <?php if ($item['inspector_name']): ?> | Inspector: <?= htmlspecialchars($item['inspector_name']) ?><?php endif; ?>
                        <br>Requested by: <?= htmlspecialchars($item['requested_by_name'] ?? '-') ?>
                        | <?= date('d M Y H:i', strtotime($item['requested_at'])) ?>
                        <?php if ($item['remarks']): ?><br>Remarks: <?= htmlspecialchars($item['remarks']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($item['status'] === 'Pending'): ?>
                <div class="approval-actions">
                    <form method="post">
                        <input type="hidden" name="type" value="po_inspection">
                        <input type="hidden" name="approval_id" value="<?= $item['id'] ?>">
                        <input type="text" name="remarks" placeholder="Remarks (required for reject)">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" onclick="return confirm('Approve this inspection?')">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm" style="background:#ef4444;color:white;" onclick="return confirm('Reject this inspection?')">Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <!-- WO Closing Approvals -->
    <?php if ($tab === 'all' || $tab === 'wo'): ?>
    <div class="approval-section">
        <div class="section-title">
            Work Order Closing Approvals
            <?php if ($pendingWO > 0): ?><span class="badge"><?= $pendingWO ?> pending</span><?php endif; ?>
        </div>

        <?php
        $filteredWO = $tab === 'wo' ? $woClosings : array_filter($woClosings, fn($r) => $r['status'] === 'Pending');
        if (empty($filteredWO)): ?>
            <div class="empty-state">No <?= $tab === 'wo' ? '' : 'pending ' ?>work order approvals</div>
        <?php else: foreach ($filteredWO as $item): ?>
            <div class="approval-card <?= strtolower($item['status']) ?>">
                <div class="approval-info">
                    <div class="ref-no">
                        <a href="/work_orders/quality_checklist.php?id=<?= $item['work_order_id'] ?>"><?= htmlspecialchars($item['checklist_no'] ?? 'N/A') ?></a>
                        &nbsp; <span class="status-badge status-<?= strtolower($item['status']) ?>"><?= $item['status'] ?></span>
                        <?php if ($item['overall_result']): ?>
                            <span class="status-badge result-<?= strtolower($item['overall_result']) ?>"><?= $item['overall_result'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="meta">
                        WO: <strong><?= htmlspecialchars($item['wo_no'] ?? '-') ?></strong>
                        | Part: <?= htmlspecialchars($item['part_no'] ?? '') ?> - <?= htmlspecialchars($item['part_name'] ?? '') ?>
                        | Qty: <?= $item['qty'] ?? '-' ?>
                        <?php if ($item['inspector_name']): ?> | Inspector: <?= htmlspecialchars($item['inspector_name']) ?><?php endif; ?>
                        <br>Requested by: <?= htmlspecialchars($item['requested_by_name'] ?? '-') ?>
                        | <?= date('d M Y H:i', strtotime($item['requested_at'])) ?>
                        <?php if ($item['remarks']): ?><br>Remarks: <?= htmlspecialchars($item['remarks']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($item['status'] === 'Pending'): ?>
                <div class="approval-actions">
                    <form method="post">
                        <input type="hidden" name="type" value="wo_closing">
                        <input type="hidden" name="approval_id" value="<?= $item['id'] ?>">
                        <input type="text" name="remarks" placeholder="Remarks (required for reject)">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" onclick="return confirm('Approve this WO closing?')">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm" style="background:#ef4444;color:white;" onclick="return confirm('Reject this WO closing?')">Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <!-- SO Release Approvals -->
    <?php if ($tab === 'all' || $tab === 'so'): ?>
    <div class="approval-section">
        <div class="section-title">
            Sales Order Release Approvals
            <?php if ($pendingSO > 0): ?><span class="badge"><?= $pendingSO ?> pending</span><?php endif; ?>
        </div>

        <?php
        $filteredSO = $tab === 'so' ? $soReleases : array_filter($soReleases, fn($r) => $r['status'] === 'Pending');
        if (empty($filteredSO)): ?>
            <div class="empty-state">No <?= $tab === 'so' ? '' : 'pending ' ?>sales order approvals</div>
        <?php else: foreach ($filteredSO as $item): ?>
            <div class="approval-card <?= strtolower($item['status']) ?>">
                <div class="approval-info">
                    <div class="ref-no">
                        <a href="/sales_orders/view.php?so_no=<?= urlencode($item['so_no']) ?>"><?= htmlspecialchars($item['so_no']) ?></a>
                        &nbsp; <span class="status-badge status-<?= strtolower($item['status']) ?>"><?= $item['status'] ?></span>
                    </div>
                    <div class="meta">
                        Customer: <strong><?= htmlspecialchars($item['company_name'] ?? $item['customer_name'] ?? '-') ?></strong>
                        <br>Requested by: <?= htmlspecialchars($item['requested_by_name'] ?? '-') ?>
                        | <?= date('d M Y H:i', strtotime($item['requested_at'])) ?>
                        <?php if ($item['remarks']): ?><br>Remarks: <?= htmlspecialchars($item['remarks']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($item['status'] === 'Pending'): ?>
                <div class="approval-actions">
                    <form method="post">
                        <input type="hidden" name="type" value="so_release">
                        <input type="hidden" name="approval_id" value="<?= $item['id'] ?>">
                        <input type="text" name="remarks" placeholder="Remarks (required for reject)">
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" onclick="return confirm('Approve this SO release?')">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-sm" style="background:#ef4444;color:white;" onclick="return confirm('Reject this SO release?')">Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
