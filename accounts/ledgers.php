<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

showModal();

// Filters
$group_filter = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all groups for filter
try {
    $groups = $pdo->query("SELECT id, group_name, group_type FROM acc_account_groups ORDER BY group_type, group_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $groups = [];
}

// Build query
$where = ["l.is_active = 1"];
$params = [];

if ($group_filter > 0) {
    $where[] = "l.group_id = ?";
    $params[] = $group_filter;
}
if ($search) {
    $where[] = "(l.ledger_name LIKE ? OR l.ledger_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Get ledgers
try {
    $sql = "
        SELECT l.*, g.group_name, g.group_type, g.nature
        FROM acc_ledgers l
        INNER JOIN acc_account_groups g ON l.group_id = g.id
        $where_clause
        ORDER BY g.group_type, g.group_name, l.ledger_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ledgers = [];
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ledgers - Accounts</title>
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

        .group-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .group-assets { background: #e3f2fd; color: #1565c0; }
        .group-liabilities { background: #fce4ec; color: #c2185b; }
        .group-income { background: #e8f5e9; color: #2e7d32; }
        .group-expenses { background: #fff3e0; color: #e65100; }
        .group-equity { background: #f3e5f5; color: #7b1fa2; }

        .balance-dr { color: #1565c0; }
        .balance-cr { color: #c2185b; }

        .bank-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
        }

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
            <h1>Chart of Accounts / Ledgers</h1>
            <p style="color: #666; margin: 5px 0 0;">Manage account ledgers and groups</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="account_groups.php" class="btn btn-secondary">Account Groups</a>
            <a href="ledger_add.php" class="btn btn-primary">+ New Ledger</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label style="font-weight: 600; margin-right: 5px;">Group:</label>
                <select name="group" onchange="this.form.submit()">
                    <option value="0">All Groups</option>
                    <?php
                    $currentType = '';
                    foreach ($groups as $g):
                        if ($g['group_type'] !== $currentType):
                            if ($currentType) echo '</optgroup>';
                            echo '<optgroup label="' . $g['group_type'] . '">';
                            $currentType = $g['group_type'];
                        endif;
                    ?>
                        <option value="<?= $g['id'] ?>" <?= $group_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['group_name']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentType) echo '</optgroup>'; ?>
                </select>
            </div>
            <div>
                <input type="text" name="search" placeholder="Search ledger..." value="<?= htmlspecialchars($search) ?>" style="padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px;">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($group_filter || $search): ?>
                <a href="ledgers.php" class="btn btn-sm" style="background: #e74c3c; color: white;">Clear</a>
            <?php endif; ?>
        </form>
        <div style="margin-left: auto; color: #666;">
            <?= count($ledgers) ?> ledger<?= count($ledgers) != 1 ? 's' : '' ?>
        </div>
    </div>

    <!-- Ledgers Table -->
    <?php if (empty($ledgers)): ?>
        <div class="empty-state">
            <h3>No Ledgers Found</h3>
            <p>Create your first account ledger.</p>
            <a href="ledger_add.php" class="btn btn-primary" style="margin-top: 15px;">+ New Ledger</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Ledger Name</th>
                    <th>Account Group</th>
                    <th>Type</th>
                    <th>Opening Balance</th>
                    <th>Current Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledgers as $ledger): ?>
                    <tr>
                        <td><?= htmlspecialchars($ledger['ledger_code'] ?: '-') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($ledger['ledger_name']) ?></strong>
                            <?php if ($ledger['is_bank_account']): ?>
                                <span class="bank-badge">Bank</span>
                            <?php elseif ($ledger['is_cash_account']): ?>
                                <span class="bank-badge">Cash</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ledger['group_name']) ?></td>
                        <td>
                            <span class="group-badge group-<?= strtolower($ledger['group_type']) ?>">
                                <?= $ledger['group_type'] ?>
                            </span>
                        </td>
                        <td class="<?= $ledger['opening_balance_type'] === 'Debit' ? 'balance-dr' : 'balance-cr' ?>">
                            <?php if ($ledger['opening_balance'] != 0): ?>
                                Rs. <?= number_format(abs($ledger['opening_balance']), 2) ?>
                                <?= $ledger['opening_balance_type'] === 'Debit' ? 'Dr' : 'Cr' ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="<?= $ledger['balance_type'] === 'Debit' ? 'balance-dr' : 'balance-cr' ?>">
                            <?php if ($ledger['current_balance'] != 0): ?>
                                <strong>Rs. <?= number_format(abs($ledger['current_balance']), 2) ?></strong>
                                <?= $ledger['balance_type'] === 'Debit' ? 'Dr' : 'Cr' ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="ledger_view.php?id=<?= $ledger['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                            <a href="ledger_edit.php?id=<?= $ledger['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
