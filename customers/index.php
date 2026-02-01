<?php
include "../db.php";
include "../includes/sidebar.php";

// Handle bulk delete request
$deleteSuccess = false;
$deleteError = '';
$deletedCount = 0;
$skippedCustomers = [];

if (isset($_POST['bulk_delete']) && isset($_POST['selected_customers']) && is_array($_POST['selected_customers'])) {
    $selectedIds = $_POST['selected_customers'];

    if (count($selectedIds) > 0) {
        try {
            $pdo->beginTransaction();

            // Check each customer for related records before deleting
            foreach ($selectedIds as $customerId) {
                // Get the internal id for this customer_id
                $idStmt = $pdo->prepare("SELECT id, company_name, customer_name FROM customers WHERE customer_id = ?");
                $idStmt->execute([$customerId]);
                $customer = $idStmt->fetch(PDO::FETCH_ASSOC);

                if (!$customer) continue;

                $internalId = $customer['id'];
                $customerName = $customer['company_name'] ?: $customer['customer_name'] ?: $customerId;

                // Check for related sales orders
                $checkSO = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE customer_id = ?");
                $checkSO->execute([$internalId]);
                if ($checkSO->fetchColumn() > 0) {
                    $skippedCustomers[] = "$customerName (has Sales Orders)";
                    continue;
                }

                // Check for related invoices
                $checkInv = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ?");
                $checkInv->execute([$internalId]);
                if ($checkInv->fetchColumn() > 0) {
                    $skippedCustomers[] = "$customerName (has Invoices)";
                    continue;
                }

                // Check for related quotations
                $checkQuote = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE customer_id = ?");
                $checkQuote->execute([$internalId]);
                if ($checkQuote->fetchColumn() > 0) {
                    $skippedCustomers[] = "$customerName (has Quotations)";
                    continue;
                }

                // Safe to delete this customer
                $deleteStmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
                $deleteStmt->execute([$customerId]);
                $deletedCount++;
            }

            $pdo->commit();

            if ($deletedCount > 0) {
                $deleteSuccess = true;
            }

            if (count($skippedCustomers) > 0 && $deletedCount == 0) {
                $deleteError = "Cannot delete customers with existing transactions. Skipped: " . implode(", ", $skippedCustomers);
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $deleteError = "Failed to delete customers: " . $e->getMessage();
        }
    }
}

// Ensure all required columns exist in customers table
$columnsToAdd = [
    "ALTER TABLE customers ADD COLUMN designation VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN secondary_designation VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN secondary_contact_name VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE customers ADD COLUMN customer_type VARCHAR(10) DEFAULT 'B2B'",
    "ALTER TABLE customers ADD COLUMN industry VARCHAR(100) DEFAULT NULL"
];
foreach ($columnsToAdd as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) {}
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with search
$whereClause = "";
$params = [];

if ($search !== '') {
    $whereClause = "WHERE company_name LIKE :search
                    OR customer_name LIKE :search
                    OR customer_id LIKE :search
                    OR contact LIKE :search
                    OR email LIKE :search
                    OR city LIKE :search
                    OR gstin LIKE :search
                    OR industry LIKE :search
                    OR secondary_contact_name LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

// Get total count
$countSql = "SELECT COUNT(*) FROM customers $whereClause";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total_count = $countStmt->fetchColumn();

$total_pages = ceil($total_count / $per_page);

// Fetch customers
$sql = "SELECT customer_id, company_name, designation, customer_name, contact, email,
               address1, address2, city, pincode, state, gstin, status,
               customer_type, industry, secondary_designation, secondary_contact_name
        FROM customers
        $whereClause
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt;
    $queryError = null;
} catch (PDOException $e) {
    $customers = null;
    $queryError = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customers</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 600;
        }
        .page-header .stats {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.95em;
        }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.9em;
        }
        .action-buttons .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .action-buttons .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .action-buttons .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        .action-buttons .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        }
        .action-buttons .btn-secondary {
            background: #f1f3f4;
            color: #5f6368;
        }
        .action-buttons .btn-secondary:hover {
            background: #e8eaed;
        }
        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box input {
            padding: 10px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            width: 280px;
            font-size: 0.95em;
            transition: border-color 0.2s;
        }
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
        }
        .search-box button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .search-results {
            background: #e7f3ff;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .error-box {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
            color: #333;
        }
        .data-table tbody tr {
            transition: background-color 0.2s;
        }
        .data-table tbody tr:hover {
            background-color: #f8f9ff;
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            display: inline-block;
        }
        .badge-b2b {
            background: #e3f2fd;
            color: #1565c0;
        }
        .badge-b2c {
            background: #fce4ec;
            color: #c2185b;
        }
        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-inactive {
            background: #ffebee;
            color: #c62828;
        }
        .customer-id {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #667eea;
        }
        .company-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .contact-info {
            color: #666;
        }
        .btn-edit {
            padding: 6px 14px;
            background: #f0f4ff;
            color: #667eea;
            border: 1px solid #667eea;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-edit:hover {
            background: #667eea;
            color: white;
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }
        .btn-danger:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        .selected-count {
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            display: none;
        }
        .selected-count.show {
            display: inline-block;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 25px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
        }
        .pagination a {
            background: #f1f3f4;
            color: #5f6368;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        .pagination .current-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        .pagination .page-info {
            color: #666;
            margin: 0 10px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h3 {
            margin: 0 0 10px;
            color: #333;
        }

        /* Dark mode */
        body.dark .action-bar,
        body.dark .table-container,
        body.dark .pagination {
            background: #2c3e50;
        }
        body.dark .data-table td {
            border-bottom-color: #3d566e;
            color: #ecf0f1;
        }
        body.dark .data-table tbody tr:hover {
            background-color: #34495e;
        }
        body.dark .company-name {
            color: #ecf0f1;
        }
        body.dark .search-box input {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .btn-edit {
            background: #34495e;
            border-color: #667eea;
        }
        body.dark .btn-edit:hover {
            background: #667eea;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }
            .page-header h1 {
                font-size: 1.4em;
            }
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                flex-direction: column;
            }
            .search-box input {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Customer Management</h1>
        <div class="stats"><?= $total_count ?> Total Customers</div>
    </div>

    <?php if ($deleteSuccess): ?>
    <div class="success-message">
        <strong>Success!</strong> <?= $deletedCount ?> customer(s) have been deleted successfully.
        <?php if (count($skippedCustomers) > 0): ?>
            <br><small style="opacity: 0.8;">Skipped (have transactions): <?= htmlspecialchars(implode(", ", $skippedCustomers)) ?></small>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($deleteError): ?>
    <div class="error-message">
        <strong>Error:</strong> <?= htmlspecialchars($deleteError) ?>
    </div>
    <?php endif; ?>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="action-buttons">
            <a href="add.php" class="btn btn-primary">+ Add Customer</a>
            <a href="import.php" class="btn btn-primary">Import Excel</a>
            <a href="download_customers.php" class="btn btn-success">Download Excel</a>
            <a href="download_template.php" class="btn btn-secondary">Template</a>
            <button type="button" id="deleteSelectedBtn" class="btn btn-danger" disabled onclick="confirmDelete()">Delete Selected</button>
            <span id="selectedCount" class="selected-count">0 selected</span>
        </div>

        <!-- Search Form -->
        <form method="get" class="search-box">
            <input type="text" name="search" placeholder="Search customers..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($search !== ''): ?>
    <div class="search-results">
        Showing results for: <strong>"<?= htmlspecialchars($search) ?>"</strong>
        (<?= $total_count ?> customer<?= $total_count != 1 ? 's' : '' ?> found)
    </div>
    <?php endif; ?>

    <?php if (isset($queryError) && $queryError): ?>
    <div class="error-box">
        <strong>Database Error:</strong> <?= htmlspecialchars($queryError) ?>
    </div>
    <?php endif; ?>

    <!-- Data Table -->
    <form id="bulkDeleteForm" method="post">
    <input type="hidden" name="bulk_delete" value="1">
    <div class="table-container" style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="checkbox-cell">
                        <input type="checkbox" id="selectAll" title="Select All">
                    </th>
                    <th>Customer ID</th>
                    <th>Type</th>
                    <th>Company</th>
                    <th>Customer Name</th>
                    <th>Designation</th>
                    <th>Industry</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>State</th>
                    <th>GSTIN</th>
                    <th>Secondary Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers && $total_count > 0): ?>
                    <?php while ($c = $customers->fetch()): ?>
                    <tr>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="selected_customers[]" value="<?= htmlspecialchars($c['customer_id']) ?>" class="customer-checkbox">
                        </td>
                        <td><span class="customer-id"><?= htmlspecialchars($c['customer_id']) ?></span></td>
                        <td>
                            <span class="badge <?= ($c['customer_type'] ?? 'B2B') === 'B2B' ? 'badge-b2b' : 'badge-b2c' ?>">
                                <?= htmlspecialchars($c['customer_type'] ?? 'B2B') ?>
                            </span>
                        </td>
                        <td><span class="company-name"><?= htmlspecialchars($c['company_name']) ?></span></td>
                        <td><?= htmlspecialchars($c['customer_name']) ?></td>
                        <td><?= htmlspecialchars($c['designation'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['industry'] ?? '-') ?></td>
                        <td class="contact-info"><?= htmlspecialchars($c['contact'] ?? '-') ?></td>
                        <td class="contact-info"><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['city'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['state'] ?? '-') ?></td>
                        <td style="font-family: monospace; font-size: 0.85em;"><?= htmlspecialchars($c['gstin'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['secondary_contact_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= ($c['status'] ?? 'active') === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= htmlspecialchars(ucfirst($c['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn-edit" href="edit.php?customer_id=<?= urlencode($c['customer_id']) ?>">Edit</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15">
                            <div class="empty-state">
                                <h3>No customers found</h3>
                                <p>Add your first customer or try a different search.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <?php $searchParam = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $searchParam ?>">First</a>
            <a href="?page=<?= $page - 1 ?><?= $searchParam ?>">Previous</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="current-page"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= $searchParam ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <span class="page-info">(<?= $total_count ?> total)</span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $searchParam ?>">Next</a>
            <a href="?page=<?= $total_pages ?><?= $searchParam ?>">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.customer-checkbox:checked').length;
        if (checkedCount > 0) {
            selectedCountSpan.textContent = checkedCount + ' selected';
            selectedCountSpan.classList.add('show');
            deleteBtn.disabled = false;
        } else {
            selectedCountSpan.classList.remove('show');
            deleteBtn.disabled = true;
        }

        // Update select all checkbox state
        if (checkboxes.length > 0) {
            selectAll.checked = checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
    }

    // Select All checkbox
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }

    // Individual checkboxes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
});

function confirmDelete() {
    const checkedCount = document.querySelectorAll('.customer-checkbox:checked').length;
    if (checkedCount === 0) {
        alert('Please select at least one customer to delete.');
        return;
    }

    const message = checkedCount === 1
        ? 'Are you sure you want to delete this customer? This action cannot be undone.'
        : 'Are you sure you want to delete ' + checkedCount + ' customers? This action cannot be undone.';

    if (confirm(message)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>

</body>
</html>
