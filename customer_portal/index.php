<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('customer_portal');

// Get customer selection
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Fetch all customers for selection
$customers = $pdo->query("SELECT id, company_name, customer_name, gstin FROM customers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);

// Get customer details if selected
$customer = null;
$stats = [
    'total_invoices' => 0,
    'total_orders' => 0,
    'total_quotations' => 0,
    'total_proforma' => 0,
    'outstanding_amount' => 0,
    'paid_amount' => 0
];

if ($customer_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Get invoice stats
        try {
            $invStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT i.id) as count
                FROM invoice_master i
                JOIN sales_orders so ON so.so_no = i.so_no
                WHERE so.customer_id = ?
            ");
            $invStmt->execute([$customer_id]);
            $stats['total_invoices'] = $invStmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}

        // Get sales orders count
        try {
            $soStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE customer_id = ?");
            $soStmt->execute([$customer_id]);
            $stats['total_orders'] = $soStmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}

        // Get quotations count
        try {
            $quoteStmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE customer_id = ?");
            $quoteStmt->execute([$customer['customer_id']]);
            $stats['total_quotations'] = $quoteStmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}

        // Get proforma count
        try {
            $piStmt = $pdo->prepare("SELECT COUNT(*) FROM quote_master WHERE customer_id = ? AND pi_no IS NOT NULL");
            $piStmt->execute([$customer['customer_id']]);
            $stats['total_proforma'] = $piStmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .portal-header h1 { margin: 0 0 10px 0; }
        .portal-header p { margin: 0; opacity: 0.9; }

        .customer-selector {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .customer-selector label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #2c3e50;
        }
        .customer-selector select {
            width: 100%;
            max-width: 500px;
        }

        .customer-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .customer-info h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item label {
            display: block;
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 3px;
        }
        .info-item span {
            font-weight: 600;
            color: #2c3e50;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #3498db;
            text-align: center;
        }
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .stat-card.invoices { border-left-color: #27ae60; }
        .stat-card.orders { border-left-color: #3498db; }
        .stat-card.quotations { border-left-color: #f39c12; }
        .stat-card.proforma { border-left-color: #9b59b6; }

        .portal-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .portal-menu-item {
            background: white;
            padding: 25px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-decoration: none;
            color: #2c3e50;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .portal-menu-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        .portal-menu-item .icon {
            font-size: 2.5em;
            margin-bottom: 12px;
        }
        .portal-menu-item .title {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        .portal-menu-item .desc {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .select2-container { width: 100% !important; max-width: 500px; }
        .select2-container .select2-selection--single { height: 42px; padding: 5px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 30px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    </style>
</head>
<body>

<div class="content">
    <div class="portal-header">
        <h1>Customer Portal</h1>
        <p>Access invoices, orders, quotations, ledger and more for your customers</p>
    </div>

    <div class="customer-selector">
        <label for="customer_select">Select Customer</label>
        <form method="get" id="customerForm">
            <select name="customer_id" id="customer_select" onchange="this.form.submit()">
                <option value="">-- Select a Customer --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?>
                        <?= $c['customer_name'] ? '(' . htmlspecialchars($c['customer_name']) . ')' : '' ?>
                        <?= $c['gstin'] ? ' - ' . htmlspecialchars($c['gstin']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($customer): ?>
        <div class="customer-info">
            <h3><?= htmlspecialchars($customer['company_name']) ?></h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Contact Person</label>
                    <span><?= htmlspecialchars($customer['customer_name'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <span><?= htmlspecialchars($customer['email'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <span><?= htmlspecialchars($customer['contact'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label>GST No</label>
                    <span><?= htmlspecialchars($customer['gstin'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label>Address</label>
                    <span><?= htmlspecialchars($customer['address1'] ?: '-') ?></span>
                </div>
                <div class="info-item">
                    <label>City / State</label>
                    <span><?= htmlspecialchars(($customer['city'] ?: '') . ($customer['state'] ? ', ' . $customer['state'] : '')) ?: '-' ?></span>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card invoices">
                <div class="value"><?= $stats['total_invoices'] ?></div>
                <div class="label">Total Invoices</div>
            </div>
            <div class="stat-card orders">
                <div class="value"><?= $stats['total_orders'] ?></div>
                <div class="label">Sales Orders</div>
            </div>
            <div class="stat-card quotations">
                <div class="value"><?= $stats['total_quotations'] ?></div>
                <div class="label">Quotations</div>
            </div>
            <div class="stat-card proforma">
                <div class="value"><?= $stats['total_proforma'] ?></div>
                <div class="label">Proforma Invoices</div>
            </div>
        </div>

        <h3 style="color: #2c3e50; margin-bottom: 20px;">Quick Access</h3>
        <div class="portal-menu">
            <a href="invoices.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">🧾</div>
                <div class="title">Invoices</div>
                <div class="desc">View all tax invoices</div>
            </a>
            <a href="quotations.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📋</div>
                <div class="title">Quotations</div>
                <div class="desc">View quotations sent</div>
            </a>
            <a href="proforma.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📄</div>
                <div class="title">Proforma Invoice</div>
                <div class="desc">View proforma invoices</div>
            </a>
            <a href="orders.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📦</div>
                <div class="title">Order Status</div>
                <div class="desc">Track order progress</div>
            </a>
            <a href="ledger.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📒</div>
                <div class="title">Account Ledger</div>
                <div class="desc">View account statement</div>
            </a>
            <a href="catalog.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📚</div>
                <div class="title">Catalog</div>
                <div class="desc">Browse product catalog</div>
            </a>
            <a href="dockets.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">🚚</div>
                <div class="title">Docket Details</div>
                <div class="desc">View delivery dockets</div>
            </a>
            <a href="eway_bills.php?customer_id=<?= $customer_id ?>" class="portal-menu-item">
                <div class="icon">📃</div>
                <div class="title">E-Way Bills</div>
                <div class="desc">View e-way bills</div>
            </a>
        </div>

    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
            <div style="font-size: 4em; margin-bottom: 20px;">👆</div>
            <h3 style="color: #2c3e50; margin-bottom: 10px;">Select a Customer</h3>
            <p style="color: #7f8c8d;">Please select a customer from the dropdown above to view their portal</p>
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#customer_select').select2({
        placeholder: '-- Select a Customer --',
        allowClear: true
    });
});
</script>

</body>
</html>
