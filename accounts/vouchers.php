<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

showModal();

// Filters
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get voucher types
try {
    $types = $pdo->query("SELECT id, type_name, type_code FROM acc_voucher_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $types = [];
}

// Build query
$where = ["v.is_cancelled = 0"];
$params = [];

if ($type_filter > 0) {
    $where[] = "v.voucher_type_id = ?";
    $params[] = $type_filter;
}
if ($date_from) {
    $where[] = "v.voucher_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where[] = "v.voucher_date <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Get total count
try {
    $count_sql = "SELECT COUNT(*) FROM acc_vouchers v $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get vouchers
try {
    $sql = "
        SELECT v.*, vt.type_name, vt.type_code, l.ledger_name as party_name
        FROM acc_vouchers v
        INNER JOIN acc_voucher_types vt ON v.voucher_type_id = vt.id
        LEFT JOIN acc_ledgers l ON v.party_ledger_id = l.id
        $where_clause
        ORDER BY v.voucher_date DESC, v.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $vouchers = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vouchers - Accounts</title>
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

        .quick-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .quick-btn {
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s;
        }
        .quick-btn:hover { transform: translateY(-2px); }
        .quick-btn.payment { background: #e74c3c; color: white; }
        .quick-btn.receipt { background: #27ae60; color: white; }
        .quick-btn.contra { background: #3498db; color: white; }
        .quick-btn.journal { background: #9b59b6; color: white; }

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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .type-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .type-pmt { background: #ffebee; color: #c62828; }
        .type-rct { background: #e8f5e9; color: #2e7d32; }
        .type-cnt { background: #e3f2fd; color: #1565c0; }
        .type-jrn { background: #f3e5f5; color: #7b1fa2; }
        .type-sal { background: #e8f5e9; color: #2e7d32; }
        .type-pur { background: #fff3e0; color: #e65100; }
        .type-cn { background: #fce4ec; color: #c2185b; }
        .type-dn { background: #fff8e1; color: #f57f17; }
        .type-exp { background: #ffebee; color: #c62828; }

        .amount { font-weight: 600; text-align: right; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
        }
        .pagination a { background: #f8f9fa; color: #495057; }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination span { background: #667eea; color: white; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
        }

        body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
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
            <h1>Voucher Entry</h1>
            <p style="color: #666; margin: 5px 0 0;">All accounting transactions</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
    </div>

    <!-- Quick Entry Buttons -->
    <div class="quick-buttons">
        <a href="voucher_add.php?type=1" class="quick-btn payment">+ Payment</a>
        <a href="voucher_add.php?type=2" class="quick-btn receipt">+ Receipt</a>
        <a href="voucher_add.php?type=3" class="quick-btn contra">+ Contra</a>
        <a href="voucher_add.php?type=4" class="quick-btn journal">+ Journal</a>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label style="font-weight: 600; margin-right: 5px;">Type:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="0">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $type_filter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight: 600; margin-right: 5px;">From:</label>
                <input type="date" name="date_from" value="<?= $date_from ?>" onchange="this.form.submit()">
            </div>
            <div>
                <label style="font-weight: 600; margin-right: 5px;">To:</label>
                <input type="date" name="date_to" value="<?= $date_to ?>" onchange="this.form.submit()">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="vouchers.php" class="btn btn-sm btn-secondary">Reset</a>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= $total_count ?> voucher<?= $total_count != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Vouchers Table -->
    <?php if (empty($vouchers)): ?>
        <div class="empty-state">
            <h3>No Vouchers Found</h3>
            <p>Create your first voucher entry.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher No</th>
                    <th>Type</th>
                    <th>Party / Particulars</th>
                    <th>Reference</th>
                    <th class="amount">Amount (Rs.)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $v): ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($v['voucher_date'])) ?></td>
                        <td><a href="voucher_view.php?id=<?= $v['id'] ?>"><strong><?= htmlspecialchars($v['voucher_no']) ?></strong></a></td>
                        <td>
                            <span class="type-badge type-<?= strtolower($v['type_code']) ?>">
                                <?= $v['type_name'] ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($v['party_name'] ?: '-') ?>
                            <?php if ($v['narration']): ?>
                                <br><small style="color: #666;"><?= htmlspecialchars(substr($v['narration'], 0, 50)) ?><?= strlen($v['narration']) > 50 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($v['reference_no'] ?: '-') ?></td>
                        <td class="amount">Rs. <?= number_format($v['total_amount'], 2) ?></td>
                        <td>
                            <a href="voucher_view.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="voucher_print.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">Print</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = http_build_query(array_filter([
                'type' => $type_filter,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]));
            ?>
            <?php if ($page > 1): ?>
                <a href="?page=1&<?= $query_params ?>">First</a>
                <a href="?page=<?= $page - 1 ?>&<?= $query_params ?>">Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&<?= $query_params ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= $query_params ?>">Next</a>
                <a href="?page=<?= $total_pages ?>&<?= $query_params ?>">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
