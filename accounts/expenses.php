<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$where = ["e.expense_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];

if ($category_filter) {
    $where[] = "e.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Get categories
try {
    $cat_stmt = $pdo->query("SELECT * FROM acc_expense_categories WHERE is_active = 1 ORDER BY name");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Get total count
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM acc_expenses e $where_clause");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
} catch (Exception $e) {
    $total_count = 0;
}

$total_pages = ceil($total_count / $per_page);

// Get expenses
try {
    $sql = "
        SELECT e.*, c.name as category_name, l.name as paid_from_name,
               u.username as created_by_name
        FROM acc_expenses e
        LEFT JOIN acc_expense_categories c ON e.category_id = c.id
        LEFT JOIN acc_ledgers l ON e.paid_from_ledger_id = l.id
        LEFT JOIN users u ON e.created_by = u.id
        $where_clause
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expenses = [];
}

// Get totals
try {
    $total_stmt = $pdo->prepare("SELECT SUM(total_amount) FROM acc_expenses e $where_clause");
    $total_stmt->execute($params);
    $total_expenses = $total_stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $total_expenses = 0;
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Expenses - Accounts</title>
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

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        .filter-group select, .filter-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
        }

        .summary-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .summary-card {
            background: white;
            padding: 20px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            flex: 1;
            min-width: 200px;
        }
        .summary-card .label { color: #666; font-size: 0.9em; }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: 700;
            color: #e74c3c;
            margin-top: 5px;
        }
        .summary-card .count { color: #666; font-size: 0.9em; margin-top: 5px; }

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

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
            background: #e3f2fd;
            color: #1565c0;
        }
        .payment-mode {
            font-size: 0.85em;
            color: #666;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        body.dark .filter-card, body.dark .summary-card, body.dark .data-table { background: #2c3e50; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="page-header">
        <div>
            <h1>Expenses</h1>
            <p style="color: #666; margin: 5px 0 0;">Track and manage all business expenses</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="expense_add.php" class="btn btn-primary">+ Add Expense</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <form method="get" class="filter-row">
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="<?= $from_date ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="to_date" value="<?= $to_date ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="expenses.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    <!-- Summary -->
    <div class="summary-row">
        <div class="summary-card">
            <div class="label">Total Expenses</div>
            <div class="value">₹<?= number_format($total_expenses, 2) ?></div>
            <div class="count"><?= $total_count ?> expense<?= $total_count != 1 ? 's' : '' ?> in period</div>
        </div>
    </div>

    <!-- Expenses Table -->
    <?php if (empty($expenses)): ?>
        <div style="background: white; padding: 60px; text-align: center; border-radius: 10px; color: #666;">
            <h3>No Expenses Found</h3>
            <p>No expenses recorded for the selected period.</p>
            <a href="expense_add.php" class="btn btn-primary" style="margin-top: 15px;">+ Add Expense</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Paid From</th>
                    <th>Payment Mode</th>
                    <th style="text-align: right;">Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($exp['description']) ?></strong>
                            <?php if ($exp['reference_no']): ?>
                                <div style="font-size: 0.85em; color: #666;">Ref: <?= htmlspecialchars($exp['reference_no']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="category-badge"><?= htmlspecialchars($exp['category_name'] ?: 'Uncategorized') ?></span></td>
                        <td><?= htmlspecialchars($exp['paid_from_name'] ?: '-') ?></td>
                        <td><span class="payment-mode"><?= htmlspecialchars($exp['payment_mode']) ?></span></td>
                        <td style="text-align: right; font-weight: 600; color: #e74c3c;">
                            ₹<?= number_format($exp['total_amount'], 2) ?>
                        </td>
                        <td>
                            <a href="expense_view.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="expense_edit.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&category=<?= $category_filter ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>">Prev</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&category=<?= $category_filter ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&category=<?= $category_filter ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
