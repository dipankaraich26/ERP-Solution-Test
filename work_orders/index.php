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
                $woStmt = $pdo->prepare("SELECT wo_no, part_no, qty FROM work_orders WHERE id = ?");
                $woStmt->execute([$woId]);
                $woData = $woStmt->fetch();

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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("
    SELECT COUNT(*) FROM work_orders
")->fetchColumn();

$total_pages = ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT w.id, w.wo_no, w.part_no, w.qty, w.status, w.created_at, w.assigned_to, w.plan_id,
           COALESCE(p.part_name, w.part_no) as part_name, b.bom_no,
           e.emp_id, e.first_name, e.last_name
    FROM work_orders w
    LEFT JOIN part_master p ON w.part_no = p.part_no
    LEFT JOIN bom_master b ON w.bom_id = b.id
    LEFT JOIN employees e ON w.assigned_to = e.id
    ORDER BY w.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Work Orders</title>
    <link rel="stylesheet" href="../assets/style.css">
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

<a href="add.php" class="btn btn-primary">➕ Create Work Order</a>

<div style="overflow-x: auto;">
<table border="1" cellpadding="8">
<tr>
    <th>WO No</th>
    <th>Part No</th>
    <th>Product</th>
    <th>BOM</th>
    <th>Qty</th>
    <th>Assigned To</th>
    <th>Status</th>
    <th>Source</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while ($w = $stmt->fetch()): ?>
<tr>
    <td><strong><?= htmlspecialchars($w['wo_no']) ?></strong></td>
    <td><?= htmlspecialchars($w['part_no']) ?></td>
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
    <td style="white-space: nowrap;">
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
            <form method="post" style="display: inline; margin-left: 5px;">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="wo_id" value="<?= $w['id'] ?>">
                <button type="submit" class="btn" style="background: #6b7280; color: white; padding: 4px 8px; font-size: 0.85em;"
                        onclick="return confirm('Close this Work Order?');">Close</button>
            </form>

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
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> total work orders)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
