<?php
include "../db.php";
include "../includes/sidebar.php";

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['wo_id'])) {
    $action = $_POST['action'];
    $woId = (int)$_POST['wo_id'];

    try {
        switch ($action) {
            case 'release':
                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'released' WHERE id = ?");
                $stmt->execute([$woId]);

                // Auto-create task for assigned engineer
                $woData = $pdo->prepare("SELECT wo_no, part_no, qty, assigned_to FROM work_orders WHERE id = ?");
                $woData->execute([$woId]);
                $woInfo = $woData->fetch();
                if ($woInfo && !empty($woInfo['assigned_to'])) {
                    include_once "../includes/auto_task.php";
                    $pn = $pdo->prepare("SELECT part_name FROM part_master WHERE part_no = ?");
                    $pn->execute([$woInfo['part_no']]);
                    $woPartName = $pn->fetchColumn() ?: $woInfo['part_no'];
                    createAutoTask($pdo, [
                        'task_name' => "Work Order {$woInfo['wo_no']} - Production",
                        'task_description' => "Work Order {$woInfo['wo_no']} has been released. Complete production for Part: {$woInfo['part_no']} - $woPartName, Qty: {$woInfo['qty']}",
                        'priority' => 'High',
                        'assigned_to' => $woInfo['assigned_to'],
                        'start_date' => date('Y-m-d'),
                        'related_module' => 'Work Order',
                        'related_id' => $woId,
                        'related_reference' => $woInfo['wo_no'],
                        'created_by' => $_SESSION['user_id'] ?? null
                    ]);
                }

                $success = "Work Order released successfully!";
                break;
            case 'start':
                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'in_progress' WHERE id = ?");
                $stmt->execute([$woId]);
                $success = "Work Order started!";
                break;
            case 'complete':
                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'completed' WHERE id = ?");
                $stmt->execute([$woId]);
                $success = "Work Order completed!";
                break;
            case 'close':
                // Get work order details for stock entry
                $woStmt = $pdo->prepare("SELECT wo_no, part_no, qty, status FROM work_orders WHERE id = ?");
                $woStmt->execute([$woId]);
                $woData = $woStmt->fetch();

                // Must be in completed or qc_approval status to close
                if ($woData && !in_array($woData['status'], ['completed', 'qc_approval'])) {
                    $error = "Work Order must complete Quality Check & Approval before closing.";
                    break;
                }

                // Check approval exists
                $approvalCheck = $pdo->prepare("SELECT status FROM wo_closing_approvals WHERE work_order_id = ? ORDER BY id DESC LIMIT 1");
                $approvalCheck->execute([$woId]);
                $approvalData = $approvalCheck->fetch();

                if (!$approvalData || $approvalData['status'] !== 'Approved') {
                    $error = "Cannot close: Work Order closing has not been approved yet.";
                    break;
                }

                $pdo->beginTransaction();

                if ($woData) {
                    // Add produced quantity to inventory
                    $pdo->prepare("
                        INSERT INTO inventory (part_no, qty)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
                    ")->execute([$woData['part_no'], $woData['qty']]);

                    // Log the stock entry
                    $pdo->prepare("
                        INSERT INTO stock_entries (part_no, received_qty, invoice_no, status)
                        VALUES (?, ?, ?, 'posted')
                    ")->execute([
                        $woData['part_no'],
                        $woData['qty'],
                        'WO: ' . $woData['wo_no']
                    ]);
                }

                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'closed' WHERE id = ?");
                $stmt->execute([$woId]);
                $pdo->commit();
                $success = "Work Order closed! " . $woData['qty'] . " units added to inventory.";
                break;
            case 'cancel':
                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$woId]);
                $success = "Work Order cancelled!";
                break;
            case 'reopen':
                // Check current status
                $checkStmt = $pdo->prepare("SELECT wo_no, part_no, qty, status FROM work_orders WHERE id = ?");
                $checkStmt->execute([$woId]);
                $woData = $checkStmt->fetch();

                $pdo->beginTransaction();

                // If reopening from closed status, reverse stock
                if ($woData && $woData['status'] === 'closed') {
                    $pdo->prepare("
                        UPDATE inventory SET qty = qty - ? WHERE part_no = ?
                    ")->execute([$woData['qty'], $woData['part_no']]);

                    $pdo->prepare("
                        UPDATE stock_entries
                        SET status = 'reversed'
                        WHERE part_no = ? AND invoice_no = ? AND status = 'posted'
                        ORDER BY id DESC LIMIT 1
                    ")->execute([$woData['part_no'], 'WO: ' . $woData['wo_no']]);
                }

                $stmt = $pdo->prepare("UPDATE work_orders SET status = 'open' WHERE id = ?");
                $stmt->execute([$woId]);
                $pdo->commit();

                if ($woData && $woData['status'] === 'closed') {
                    $success = "Work Order reopened! Stock reversed.";
                } else {
                    $success = "Work Order reopened!";
                }
                break;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to update: " . $e->getMessage();
    }
}

// Filter parameters
$filter_part_no = isset($_GET['part_no']) ? trim($_GET['part_no']) : '';
$filter_part_id = isset($_GET['part_id']) ? trim($_GET['part_id']) : '';
$filter_assigned_to = isset($_GET['assigned_to']) ? trim($_GET['assigned_to']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE clause
$where = [];
$params = [];

if ($filter_part_no !== '') {
    $where[] = "w.part_no LIKE ?";
    $params[] = "%$filter_part_no%";
}
if ($filter_part_id !== '') {
    $where[] = "p.part_id LIKE ?";
    $params[] = "%$filter_part_id%";
}
if ($filter_assigned_to !== '') {
    $where[] = "w.assigned_to = ?";
    $params[] = $filter_assigned_to;
}
if ($filter_status !== '') {
    $where[] = "w.status = ?";
    $params[] = $filter_status;
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

// Fetch distinct employees and part numbers for filter dropdowns
$employees = $pdo->query("
    SELECT DISTINCT e.id, e.first_name, e.last_name
    FROM employees e
    JOIN work_orders w ON w.assigned_to = e.id
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count with filters
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    $whereSQL
");
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT w.id, w.wo_no, w.part_no, w.qty, w.status, w.created_at, w.assigned_to, w.plan_id,
           COALESCE(p.part_name, w.part_no) as part_name, p.part_id, b.bom_no,
           e.emp_id, e.first_name, e.last_name
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    LEFT JOIN bom_master b ON w.bom_id = b.id
    LEFT JOIN employees e ON w.assigned_to = e.id
    $whereSQL
    ORDER BY w.id DESC
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset
);
$stmt->execute($params);

// Build query string for pagination/export links
$filterQuery = http_build_query(array_filter([
    'part_no' => $filter_part_no,
    'part_id' => $filter_part_id,
    'assigned_to' => $filter_assigned_to,
    'status' => $filter_status,
], function($v) { return $v !== ''; }));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Work Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                padding: 10px !important;
                width: 100% !important;
            }
            body {
                background: white !important;
                color: black !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            table {
                border: 1px solid #000 !important;
                page-break-inside: avoid;
                width: 100% !important;
                font-size: 10px !important;
                border-collapse: collapse;
            }
            table th, table td {
                padding: 4px 6px !important;
                border: 1px solid #000 !important;
            }
            table th {
                background: #f0f0f0 !important;
                color: #000 !important;
            }
            h1 {
                font-size: 18px !important;
            }
            .filter-bar, form[style*="flex"] {
                display: none !important;
            }
        }
    </style>
</head>
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

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<body>

<div class="content">
<h1>Work Orders</h1>

<?php if ($success): ?>
    <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <a href="add.php" class="btn btn-primary">➕ Create Work Order</a>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
        <form method="GET" action="export_wo.php" style="display:inline;">
            <input type="hidden" name="part_no" value="<?= htmlspecialchars($filter_part_no) ?>">
            <input type="hidden" name="part_id" value="<?= htmlspecialchars($filter_part_id) ?>">
            <input type="hidden" name="assigned_to" value="<?= htmlspecialchars($filter_assigned_to) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
            <button type="submit" class="btn btn-secondary">📥 Export to Excel</button>
        </form>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
    <div>
        <label style="display: block; font-size: 0.85em; margin-bottom: 3px; font-weight: 500;">Part No</label>
        <input type="text" name="part_no" value="<?= htmlspecialchars($filter_part_no) ?>" placeholder="Search part no..." style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9em;">
    </div>
    <div>
        <label style="display: block; font-size: 0.85em; margin-bottom: 3px; font-weight: 500;">Part ID</label>
        <input type="text" name="part_id" value="<?= htmlspecialchars($filter_part_id) ?>" placeholder="Search part ID..." style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9em;">
    </div>
    <div>
        <label style="display: block; font-size: 0.85em; margin-bottom: 3px; font-weight: 500;">Assigned To</label>
        <select name="assigned_to" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9em;">
            <option value="">All</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $filter_assigned_to == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display: block; font-size: 0.85em; margin-bottom: 3px; font-weight: 500;">Status</label>
        <select name="status" style="padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.9em;">
            <option value="">All</option>
            <?php
            $statuses = ['open', 'created', 'released', 'in_progress', 'completed', 'qc_approval', 'closed', 'cancelled'];
            foreach ($statuses as $s):
            ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <button type="submit" class="btn btn-primary" style="padding: 6px 14px;">Filter</button>
        <a href="index.php" class="btn btn-secondary" style="padding: 6px 14px;">Clear</a>
    </div>
</form>

<div style="overflow-x: auto;">
<table border="1" cellpadding="8">
<tr>
    <th>WO No</th>
    <th>Part No</th>
    <th>Part ID</th>
    <th>Product</th>
    <th>BOM</th>
    <th>Qty</th>
    <th>Assigned To</th>
    <th>Status</th>
    <th>Source</th>
    <th>Date</th>
    <th class="no-print">Action</th>
</tr>

<?php while ($w = $stmt->fetch()): ?>
<tr>
    <td><strong><?= htmlspecialchars($w['wo_no']) ?></strong></td>
    <td><?= htmlspecialchars($w['part_no']) ?></td>
    <td><?= htmlspecialchars($w['part_id'] ?? '') ?></td>
    <td><?= htmlspecialchars($w['part_name']) ?></td>
    <td><?= $w['bom_no'] ? htmlspecialchars($w['bom_no']) : '<span style="color: #999;">-</span>' ?></td>
    <td><?= $w['qty'] ?></td>
    <td><?= $w['assigned_to'] ? htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) : '<span style="color: #999;">-</span>' ?></td>
    <td>
        <?php
        $statusColors = [
            'open' => '#f59e0b',
            'created' => '#3b82f6',
            'released' => '#6366f1',
            'in_progress' => '#d946ef',
            'completed' => '#16a34a',
            'qc_approval' => '#0891b2',
            'closed' => '#6b7280',
            'cancelled' => '#dc2626'
        ];
        $color = $statusColors[$w['status']] ?? '#6b7280';
        ?>
        <span style="display: inline-block; padding: 3px 8px; background: <?= $color ?>20; color: <?= $color ?>; border-radius: 4px; font-size: 0.85em; font-weight: 500;">
            <?= ucfirst(str_replace('_', ' ', $w['status'])) ?>
        </span>
    </td>
    <td>
        <?php if ($w['plan_id']): ?>
            <a href="/procurement/view.php?id=<?= $w['plan_id'] ?>" style="color: #6366f1; text-decoration: none; font-size: 0.85em;">
                Procurement
            </a>
        <?php else: ?>
            <span style="color: #999; font-size: 0.85em;">Manual</span>
        <?php endif; ?>
    </td>
    <td><?= date('Y-m-d', strtotime($w['created_at'])) ?></td>
    <td class="no-print" style="white-space: nowrap;">
        <a class="btn btn-secondary" href="view.php?id=<?= $w['id'] ?>">View</a>

        <?php if (in_array($w['status'], ['open', 'created'])): ?>
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="release">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #10b981; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Release this Work Order?');">Release</button>
            </form>
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #ef4444; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Cancel this Work Order?');">Cancel</button>
            </form>

        <?php elseif ($w['status'] === 'released'): ?>
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="start">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #d946ef; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Start production?');">Start</button>
            </form>

        <?php elseif ($w['status'] === 'in_progress'): ?>
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #16a34a; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Mark as completed?');">Complete</button>
            </form>

        <?php elseif ($w['status'] === 'completed'): ?>
            <a class="btn" href="view.php?id=<?= $w['id'] ?>#closing-workflow" style="background: #0891b2; color: white; padding: 4px 8px; font-size: 0.85em;">
                QC &amp; Approve
            </a>

        <?php elseif ($w['status'] === 'qc_approval'): ?>
            <a class="btn" href="view.php?id=<?= $w['id'] ?>#closing-workflow" style="background: #0891b2; color: white; padding: 4px 8px; font-size: 0.85em;">
                Review QC
            </a>

        <?php elseif (in_array($w['status'], ['closed', 'cancelled'])): ?>
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #f59e0b; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Reopen this Work Order?');">Reopen</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</table>
</div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <?php $paginationQuery = $filterQuery ? "&$filterQuery" : ''; ?>
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $paginationQuery ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $paginationQuery ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total work orders)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $paginationQuery ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $paginationQuery ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
