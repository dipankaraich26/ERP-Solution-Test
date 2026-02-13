<?php
$current = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

/**
 * Check if current user can view a module.
 * Falls back to true if permission system is not available (backward compat).
 */
function _canView(string $module): bool {
    if (!function_exists('hasPermission')) return true;
    if (!function_exists('isLoggedIn') || !isLoggedIn()) return true;
    try {
        return hasPermission($module, 'view');
    } catch (Exception $e) {
        return true; // fail open if tables don't exist yet
    }
}

// Pre-compute group visibility (user can see group if they can see ANY module in it)
$_isAdmin = (function_exists('getUserRole') && getUserRole() === 'admin');

$showSales = $_isAdmin || _canView('crm') || _canView('customers') || _canView('quotes') || _canView('proforma') || _canView('customer_po') || _canView('sales_orders') || _canView('invoices') || _canView('installations');
$showPurchase = $_isAdmin || _canView('suppliers') || _canView('purchase') || _canView('procurement');
$showInventory = $_isAdmin || _canView('part_master') || _canView('stock_entry') || _canView('depletion') || _canView('inventory') || _canView('reports');
$showOperations = $_isAdmin || _canView('bom') || _canView('work_orders');
$showHR = $_isAdmin || _canView('hr_employees') || _canView('hr_attendance') || _canView('hr_payroll');
$showMarketing = $_isAdmin || _canView('marketing_catalogs') || _canView('marketing_campaigns') || _canView('marketing_whatsapp') || _canView('marketing_analytics');
$showService = $_isAdmin || _canView('service_complaints') || _canView('service_technicians') || _canView('service_analytics');
$showQMS = $_isAdmin || _canView('qms');
$showTasks = $_isAdmin || _canView('tasks');
$showEngineering = $_isAdmin || _canView('project_management');
$showQC = $_isAdmin || _canView('quality_control');
$showAccounts = $_isAdmin || _canView('accounts');
$showAdmin = $_isAdmin || _canView('admin_settings') || _canView('admin_users') || _canView('admin_locations');
$showPortal = $_isAdmin || _canView('customer_portal');
$showApprovals = $_isAdmin || _canView('approvals');
?>

<!-- PWA Meta Tags -->
<meta name="theme-color" content="#667eea">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ERP System">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/icons/icon.php?size=192">

<!-- Top Navigation Bar -->
<style>
.topbar {
    position: fixed;
    top: 0;
    left: 220px;
    right: 0;
    z-index: 1000;
    background: var(--card, #ffffff);
    border-bottom: 1px solid var(--border, #cbd5e1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    padding: 0 8px;
    height: 48px;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    gap: 6px;
    scrollbar-width: thin;
    scrollbar-color: var(--border, #cbd5e1) transparent;
    transition: background-color 0.35s ease, border-color 0.35s ease;
}
.topbar::-webkit-scrollbar { height: 3px; }
.topbar::-webkit-scrollbar-thumb { background: var(--border, #cbd5e1); border-radius: 3px; }
.topbar a {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    color: #fff !important;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s ease;
    border: none;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.topbar a:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    filter: brightness(1.1);
}
.topbar a.active {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3), inset 0 -2px 0 rgba(255,255,255,0.3);
    filter: brightness(1.15);
}
/* Individual colors */
.tb-proforma   { background: #2563eb; }
.tb-custpo     { background: #7c3aed; }
.tb-sales      { background: #0891b2; }
.tb-invoice    { background: #059669; }
.tb-supplierpo { background: #d97706; }
.tb-wo         { background: #dc2626; }
.tb-procure    { background: #9333ea; }
.tb-bom        { background: #0d9488; }
.tb-stock      { background: #4f46e5; }
</style>
<div class="topbar">
    <?php if (_canView('proforma')): ?>
    <a href="/proforma/index.php" class="tb-proforma <?= $currentDir === 'proforma' ? 'active' : '' ?>">Proforma Invoice</a>
    <?php endif; ?>
    <?php if (_canView('customer_po')): ?>
    <a href="/customer_po/index.php" class="tb-custpo <?= $currentDir === 'customer_po' ? 'active' : '' ?>">Customers PO</a>
    <?php endif; ?>
    <?php if (_canView('sales_orders')): ?>
    <a href="/sales_orders/index.php" class="tb-sales <?= $currentDir === 'sales_orders' ? 'active' : '' ?>">Sales Orders</a>
    <?php endif; ?>
    <?php if (_canView('invoices')): ?>
    <a href="/invoices/index.php" class="tb-invoice <?= $currentDir === 'invoices' ? 'active' : '' ?>">Invoice</a>
    <?php endif; ?>
    <?php if (_canView('purchase')): ?>
    <a href="/purchase/index.php" class="tb-supplierpo <?= $currentDir === 'purchase' && $current === 'index.php' ? 'active' : '' ?>">Supplier PO</a>
    <?php endif; ?>
    <?php if (_canView('work_orders')): ?>
    <a href="/work_orders/index.php" class="tb-wo <?= $currentDir === 'work_orders' ? 'active' : '' ?>">Work Order</a>
    <?php endif; ?>
    <?php if (_canView('procurement')): ?>
    <a href="/procurement/index.php" class="tb-procure <?= $currentDir === 'procurement' ? 'active' : '' ?>">Supplier Purchase Order</a>
    <?php endif; ?>
    <?php if (_canView('bom')): ?>
    <a href="/bom/index.php" class="tb-bom <?= $currentDir === 'bom' ? 'active' : '' ?>">BOM</a>
    <?php endif; ?>
    <?php if (_canView('inventory')): ?>
    <a href="/inventory/index.php" class="tb-stock <?= $currentDir === 'inventory' && $current === 'index.php' ? 'active' : '' ?>">Current Stock</a>
    <?php endif; ?>
</div>

<style>
.sidebar-logo-section {
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid #34495e;
    margin-bottom: 5px;
}
.sidebar-logo-img {
    max-width: 100%;
    max-height: 45px;
    object-fit: contain;
    margin-bottom: 5px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}
.sidebar-company-name {
    font-size: 0.8em;
    color: #ecf0f1;
    font-weight: 600;
    word-wrap: break-word;
    line-height: 1.1;
}

.sidebar-group {
    margin-bottom: 0;
    border-bottom: 1px solid #34495e;
}
.sidebar-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    color: #ecf0f1;
    cursor: pointer;
    transition: background 0.2s;
    font-weight: bold;
    font-size: 0.9em;
    border-left: 3px solid transparent;
}
.sidebar-group-header:hover {
    background: #34495e;
}
.sidebar-group-header.active {
    background: #34495e;
    border-left-color: #3498db;
}
.sidebar-group-header .arrow {
    transition: transform 0.2s;
    font-size: 0.7em;
    cursor: pointer;
    padding: 3px;
}
.sidebar-group-header a.module-link {
    flex: 1;
    color: inherit;
    text-decoration: none;
}
.sidebar-group-header a.module-link:hover {
    text-decoration: underline;
}
.sidebar-group.open .arrow {
    transform: rotate(90deg);
}
.sidebar-group-items {
    display: none;
    background: #1a252f;
}
.sidebar-group.open .sidebar-group-items {
    display: block;
}
.sidebar-group-items a {
    display: block;
    padding: 6px 12px 6px 25px !important;
    font-size: 0.85em;
    border-left: 3px solid transparent;
    color: #bdc3c7;
}
.sidebar-group-items a:hover {
    background: #2c3e50;
}
.sidebar-group-items a.active {
    background: #2c3e50;
    border-left-color: #3498db;
}
</style>

<div class="sidebar">

    <button id="themeToggleBtn" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px; margin: 8px; width: calc(100% - 16px); text-align: center;">
    Light
    </button>

    <!-- Company Logo & Name Section -->
    <?php
    // Get company settings for logo
    $company_settings = null;
    try {
        $company_settings = $pdo->query("SELECT logo_path, company_name FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Settings table might not exist, continue without logo
    }
    ?>
    <div class="sidebar-logo-section">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?>
            <?php
                $logo_path = $company_settings['logo_path'];
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Company Logo" class="sidebar-logo-img" onerror="this.style.display='none'">
        <?php endif; ?>
        <?php if ($company_settings && !empty($company_settings['company_name'])): ?>
            <div class="sidebar-company-name"><?= htmlspecialchars($company_settings['company_name']) ?></div>
        <?php else: ?>
            <div class="sidebar-company-name">ERP System</div>
        <?php endif; ?>
    </div>

    <a id="sidebar-ERP-title" href="/">ERP</a>

    <!-- Executive Dashboard -->
    <a href="/ceo_dashboard.php" class="<?= $current === 'ceo_dashboard.php' ? 'active' : '' ?>" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; display: block; padding: 10px 12px; margin: 5px 8px; border-radius: 8px; text-align: center; text-decoration: none;">
        Executive Dashboard
    </a>

    <!-- My Approvals -->
    <?php if ($showApprovals): ?>
    <a href="/approvals/index.php" class="<?= $currentDir === 'approvals' ? 'active' : '' ?>" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; font-weight: bold; display: block; padding: 10px 12px; margin: 5px 8px; border-radius: 8px; text-align: center; text-decoration: none;">
        My Approvals
    </a>
    <?php endif; ?>

    <!-- Sales & CRM -->
    <?php if ($showSales): ?>
    <div class="sidebar-group <?= in_array($currentDir, ['crm', 'quotes', 'proforma', 'customer_po', 'sales_orders', 'invoices', 'customers', 'installations']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['crm', 'quotes', 'proforma', 'customer_po', 'sales_orders', 'invoices', 'customers', 'installations']) ? 'active' : '' ?>">
            <a href="/crm/dashboard.php" class="module-link">Sales & CRM</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('crm')): ?>
            <a href="/crm/index.php" class="<?= $currentDir === 'crm' ? 'active' : '' ?>">CRM</a>
            <?php endif; ?>
            <?php if (_canView('customers')): ?>
            <a href="/customers/index.php" class="<?= $currentDir === 'customers' ? 'active' : '' ?>">Customers</a>
            <?php endif; ?>
            <?php if (_canView('quotes')): ?>
            <a href="/quotes/index.php" class="<?= $currentDir === 'quotes' ? 'active' : '' ?>">Quotations</a>
            <?php endif; ?>
            <?php if (_canView('proforma')): ?>
            <a href="/proforma/index.php" class="<?= $currentDir === 'proforma' ? 'active' : '' ?>">Proforma Invoice</a>
            <?php endif; ?>
            <?php if (_canView('customer_po')): ?>
            <a href="/customer_po/index.php" class="<?= $currentDir === 'customer_po' ? 'active' : '' ?>">Customer PO</a>
            <?php endif; ?>
            <?php if (_canView('sales_orders')): ?>
            <a href="/sales_orders/index.php" class="<?= $currentDir === 'sales_orders' ? 'active' : '' ?>">Sales Orders</a>
            <?php endif; ?>
            <?php if (_canView('invoices')): ?>
            <a href="/invoices/index.php" class="<?= $currentDir === 'invoices' ? 'active' : '' ?>">Invoice</a>
            <?php endif; ?>
            <?php if (_canView('installations')): ?>
            <a href="/installations/index.php" class="<?= $currentDir === 'installations' ? 'active' : '' ?>">Installations</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Purchase & Procurement -->
    <?php if ($showPurchase): ?>
    <div class="sidebar-group <?= in_array($currentDir, ['suppliers', 'purchase', 'procurement']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['suppliers', 'purchase', 'procurement']) ? 'active' : '' ?>">
            <a href="/purchase/dashboard.php" class="module-link">Purchase & SCM</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('suppliers')): ?>
            <a href="/suppliers/index.php" class="<?= $currentDir === 'suppliers' ? 'active' : '' ?>">Suppliers</a>
            <?php endif; ?>
            <?php if (_canView('purchase')): ?>
            <a href="/purchase/supplier_pricing.php" class="<?= $currentDir === 'purchase' && $current === 'supplier_pricing.php' ? 'active' : '' ?>">Supplier Pricing</a>
            <a href="/purchase/index.php" class="<?= $currentDir === 'purchase' ? 'active' : '' ?>">Purchase Orders</a>
            <a href="/purchase/matrix.php" class="<?= $currentDir === 'purchase' && $current === 'matrix.php' ? 'active' : '' ?>">LT vs Value Matrix</a>
            <?php endif; ?>
            <?php if (_canView('procurement')): ?>
            <a href="/procurement/index.php" class="<?= $currentDir === 'procurement' ? 'active' : '' ?>">Procurement Planning</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Inventory -->
    <?php if ($showInventory): ?>
    <div class="sidebar-group <?= in_array($currentDir, ['part_master', 'stock_entry', 'depletion', 'reports', 'inventory']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['part_master', 'stock_entry', 'depletion', 'reports', 'inventory']) ? 'active' : '' ?>">
            <a href="/inventory/dashboard.php" class="module-link">Inventory</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('part_master')): ?>
            <a href="/part_master/list.php" class="<?= $currentDir === 'part_master' ? 'active' : '' ?>">Part Master</a>
            <?php endif; ?>
            <?php if (_canView('stock_entry')): ?>
            <a href="/stock_entry/index.php" class="<?= $currentDir === 'stock_entry' && $current === 'index.php' ? 'active' : '' ?>">Stock Entries</a>
            <a href="/stock_entry/shortage.php" class="<?= $currentDir === 'stock_entry' && $current === 'shortage.php' ? 'active' : '' ?>">Shortage Check</a>
            <?php endif; ?>
            <?php if (_canView('depletion')): ?>
            <a href="/depletion/stock_adjustment.php" class="<?= $currentDir === 'depletion' ? 'active' : '' ?>">Stock Adjustment</a>
            <?php endif; ?>
            <?php if (_canView('inventory')): ?>
            <a href="/inventory/bom_stock_entry.php" class="<?= $currentDir === 'inventory' && $current === 'bom_stock_entry.php' ? 'active' : '' ?>">BOM Stock Entry</a>
            <?php endif; ?>
            <?php if (_canView('reports')): ?>
            <a href="/reports/monthly.php" class="<?= $currentDir === 'reports' ? 'active' : '' ?>">Reports</a>
            <?php endif; ?>
            <?php if (_canView('inventory')): ?>
            <a href="/inventory/index.php" class="<?= $currentDir === 'inventory' ? 'active' : '' ?>">Current Stock</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Operations -->
    <?php if ($showOperations): ?>
    <div class="sidebar-group <?= in_array($currentDir, ['bom', 'work_orders']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['bom', 'work_orders']) ? 'active' : '' ?>">
            <a href="/bom/dashboard.php" class="module-link">Operations</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('bom')): ?>
            <a href="/bom/index.php" class="<?= $currentDir === 'bom' ? 'active' : '' ?>">Bill of Materials</a>
            <?php endif; ?>
            <?php if (_canView('work_orders')): ?>
            <a href="/work_orders/index.php" class="<?= $currentDir === 'work_orders' ? 'active' : '' ?>">Work Orders</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- HR -->
    <?php if ($showHR): ?>
    <div class="sidebar-group <?= $currentDir === 'hr' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'hr' ? 'active' : '' ?>">
            <a href="/hr/dashboard.php" class="module-link">HR</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('hr_employees')): ?>
            <a href="/hr/employees.php" class="<?= $currentDir === 'hr' && in_array($current, ['employees.php', 'employee_add.php', 'employee_view.php', 'employee_edit.php', 'employee_import.php']) ? 'active' : '' ?>">Employees</a>
            <a href="/hr/employee_documents.php" class="<?= $currentDir === 'hr' && $current === 'employee_documents.php' ? 'active' : '' ?>">Employee Documents</a>
            <a href="/hr/skills.php" class="<?= $currentDir === 'hr' && $current === 'skills.php' ? 'active' : '' ?>">Skills</a>
            <a href="/hr/org_structure.php" class="<?= $currentDir === 'hr' && $current === 'org_structure.php' ? 'active' : '' ?>">Org Structure</a>
            <?php endif; ?>
            <?php if (_canView('hr_attendance')): ?>
            <a href="/hr/attendance.php" class="<?= $currentDir === 'hr' && in_array($current, ['attendance.php', 'attendance_mark.php', 'holidays.php']) ? 'active' : '' ?>">Attendance</a>
            <a href="/hr/leaves.php" class="<?= $currentDir === 'hr' && in_array($current, ['leaves.php', 'leave_apply.php', 'leave_view.php', 'leave_balance.php', 'leave_types.php']) ? 'active' : '' ?>">Leave Management</a>
            <?php endif; ?>
            <?php if (_canView('hr_payroll')): ?>
            <a href="/hr/payroll.php" class="<?= $currentDir === 'hr' && in_array($current, ['payroll.php', 'payroll_generate.php', 'payroll_view.php', 'payroll_edit.php']) ? 'active' : '' ?>">Payroll</a>
            <a href="/hr/tada.php" class="<?= $currentDir === 'hr' && in_array($current, ['tada.php', 'tada_add.php', 'tada_view.php']) ? 'active' : '' ?>">TADA</a>
            <a href="/hr/advance_payment.php" class="<?= $currentDir === 'hr' && in_array($current, ['advance_payment.php', 'advance_add.php', 'advance_view.php']) ? 'active' : '' ?>">Advance Payment</a>
            <a href="/hr/appraisal_cycles.php" class="<?= $currentDir === 'hr' && in_array($current, ['appraisal_cycles.php', 'appraisals.php', 'appraisal_form.php', 'appraisal_criteria.php']) ? 'active' : '' ?>">Appraisals</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Marketing -->
    <?php if ($showMarketing): ?>
    <div class="sidebar-group <?= $currentDir === 'marketing' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'marketing' ? 'active' : '' ?>">
            <a href="/marketing/dashboard.php" class="module-link">Marketing</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('marketing_catalogs')): ?>
            <a href="/marketing/catalogs.php" class="<?= $currentDir === 'marketing' && in_array($current, ['catalogs.php', 'catalog_add.php', 'catalog_view.php', 'catalog_edit.php']) ? 'active' : '' ?>">Catalogs</a>
            <?php endif; ?>
            <?php if (_canView('marketing_campaigns')): ?>
            <a href="/marketing/campaigns.php" class="<?= $currentDir === 'marketing' && in_array($current, ['campaigns.php', 'campaign_add.php', 'campaign_view.php', 'campaign_edit.php']) ? 'active' : '' ?>">Campaigns</a>
            <?php endif; ?>
            <?php if (_canView('marketing_whatsapp')): ?>
            <a href="/marketing/whatsapp.php" class="<?= $currentDir === 'marketing' && $current === 'whatsapp.php' ? 'active' : '' ?>">WhatsApp</a>
            <?php endif; ?>
            <?php if (_canView('marketing_catalogs')): ?>
            <a href="/marketing/testimonials.php" class="<?= $currentDir === 'marketing' && in_array($current, ['testimonials.php', 'testimonial_add.php', 'testimonial_view.php', 'testimonial_edit.php']) ? 'active' : '' ?>">Testimonials</a>
            <?php endif; ?>
            <?php if (_canView('marketing_analytics')): ?>
            <a href="/marketing/analytics.php" class="<?= $currentDir === 'marketing' && $current === 'analytics.php' ? 'active' : '' ?>">Analytics</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Service -->
    <?php if ($showService): ?>
    <div class="sidebar-group <?= $currentDir === 'service' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'service' ? 'active' : '' ?>">
            <a href="/service/dashboard.php" class="module-link">Service</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('service_complaints')): ?>
            <a href="/service/complaints.php" class="<?= $currentDir === 'service' && in_array($current, ['complaints.php', 'complaint_add.php', 'complaint_view.php', 'complaint_edit.php']) ? 'active' : '' ?>">Complaints</a>
            <?php endif; ?>
            <?php if (_canView('service_technicians')): ?>
            <a href="/service/technicians.php" class="<?= $currentDir === 'service' && $current === 'technicians.php' ? 'active' : '' ?>">Technicians</a>
            <?php endif; ?>
            <?php if (_canView('service_analytics')): ?>
            <a href="/service/analytics.php" class="<?= $currentDir === 'service' && $current === 'analytics.php' ? 'active' : '' ?>">Analytics</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- QMS (Quality Management System) -->
    <?php if ($showQMS): ?>
    <?php
    $qmsParentDir = dirname($currentDir);
    $isQmsSection = ($currentDir === 'qms' || $qmsParentDir === 'qms' || in_array($currentDir, ['cdsco', 'iso', 'icmed']));
    ?>
    <div class="sidebar-group <?= $isQmsSection ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $isQmsSection ? 'active' : '' ?>">
            <a href="/qms/dashboard.php" class="module-link">QMS</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/qms/cdsco/products.php" class="<?= $currentDir === 'cdsco' ? 'active' : '' ?>">CDSCO</a>
            <a href="/qms/iso/certifications.php" class="<?= $currentDir === 'iso' ? 'active' : '' ?>">ISO</a>
            <a href="/qms/icmed/certifications.php" class="<?= $currentDir === 'icmed' ? 'active' : '' ?>">ICMED</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tasks -->
    <?php if ($showTasks): ?>
    <div class="sidebar-group <?= $currentDir === 'tasks' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'tasks' ? 'active' : '' ?>">
            <a href="/tasks/dashboard.php" class="module-link">Tasks</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/tasks/index.php" class="<?= $currentDir === 'tasks' && in_array($current, ['index.php', 'view.php', 'edit.php']) ? 'active' : '' ?>">All Tasks</a>
            <a href="/tasks/calendar.php" class="<?= $currentDir === 'tasks' && in_array($current, ['calendar.php', 'schedule_table.php']) ? 'active' : '' ?>">Task Calendar</a>
            <a href="/tasks/add.php" class="<?= $currentDir === 'tasks' && $current === 'add.php' ? 'active' : '' ?>">New Task</a>
            <a href="/tasks/categories.php" class="<?= $currentDir === 'tasks' && $current === 'categories.php' ? 'active' : '' ?>">Categories</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Product Engineering -->
    <?php if ($showEngineering): ?>
    <div class="sidebar-group <?= $currentDir === 'project_management' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'project_management' ? 'active' : '' ?>">
            <a href="/project_management/dashboard.php" class="module-link">Product Engineering</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/project_management/index.php" class="<?= $currentDir === 'project_management' && in_array($current, ['index.php', 'view.php', 'edit.php', 'add.php']) ? 'active' : '' ?>">Projects</a>
            <a href="/project_management/reviews.php" class="<?= $currentDir === 'project_management' && in_array($current, ['reviews.php', 'review_add.php', 'review_view.php', 'review_edit.php']) ? 'active' : '' ?>">Engineering Reviews</a>
            <a href="/project_management/change_requests.php" class="<?= $currentDir === 'project_management' && in_array($current, ['change_requests.php', 'eco_add.php', 'eco_view.php', 'eco_edit.php']) ? 'active' : '' ?>">Change Requests</a>
            <a href="/project_management/findings.php" class="<?= $currentDir === 'project_management' && $current === 'findings.php' ? 'active' : '' ?>">Review Findings</a>
            <a href="/project_management/part_id_series.php" class="<?= $currentDir === 'project_management' && $current === 'part_id_series.php' ? 'active' : '' ?>">Part ID Series</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quality Control -->
    <?php if ($showQC): ?>
    <div class="sidebar-group <?= $currentDir === 'quality_control' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'quality_control' ? 'active' : '' ?>">
            <a href="/quality_control/dashboard.php" class="module-link">Quality Control</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/quality_control/issues.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['issues.php', 'issue_add.php', 'issue_view.php', 'issue_edit.php']) ? 'active' : '' ?>">Quality Issues</a>
            <a href="/quality_control/checklists.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['checklists.php', 'checklist_add.php', 'checklist_view.php', 'checklist_edit.php']) ? 'active' : '' ?>">Checklists</a>
            <a href="/quality_control/inspections.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['inspections.php', 'inspection_add.php', 'inspection_view.php']) ? 'active' : '' ?>">Incoming Inspections</a>
            <a href="/quality_control/ppap.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['ppap.php', 'ppap_add.php', 'ppap_view.php', 'ppap_edit.php']) ? 'active' : '' ?>">PPAP</a>
            <a href="/quality_control/part_submissions.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['part_submissions.php', 'part_submission_add.php', 'part_submission_view.php']) ? 'active' : '' ?>">Part Submissions</a>
            <a href="/quality_control/ncrs.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['ncrs.php', 'ncr_add.php', 'ncr_view.php', 'ncr_edit.php']) ? 'active' : '' ?>">Supplier NCRs</a>
            <a href="/quality_control/supplier_ratings.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['supplier_ratings.php', 'rating_add.php']) ? 'active' : '' ?>">Supplier Ratings</a>
            <a href="/quality_control/audits.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['audits.php', 'audit_add.php', 'audit_view.php']) ? 'active' : '' ?>">Supplier Audits</a>
            <a href="/quality_control/calibration.php" class="<?= $currentDir === 'quality_control' && in_array($current, ['calibration.php', 'calibration_add.php']) ? 'active' : '' ?>">Calibration</a>
            <a href="/quality_control/wo_inspections.php" class="<?= $currentDir === 'quality_control' && $current === 'wo_inspections.php' ? 'active' : '' ?>">WO Inspections</a>
            <a href="/quality_control/process_mapping.php" class="<?= $currentDir === 'quality_control' && $current === 'process_mapping.php' ? 'active' : '' ?>">Process Mapping</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accounts & Finance -->
    <?php if ($showAccounts): ?>
    <div class="sidebar-group <?= $currentDir === 'accounts' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'accounts' ? 'active' : '' ?>">
            <a href="/accounts/dashboard.php" class="module-link">Accounts & Finance</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/accounts/ledgers.php" class="<?= $currentDir === 'accounts' && in_array($current, ['ledgers.php', 'ledger_add.php', 'ledger_edit.php']) ? 'active' : '' ?>">Chart of Accounts</a>
            <a href="/accounts/vouchers.php" class="<?= $currentDir === 'accounts' && in_array($current, ['vouchers.php', 'voucher_add.php', 'voucher_view.php']) ? 'active' : '' ?>">Vouchers</a>
            <a href="/accounts/expenses.php" class="<?= $currentDir === 'accounts' && in_array($current, ['expenses.php', 'expense_add.php', 'expense_view.php']) ? 'active' : '' ?>">Expenses</a>
            <a href="/accounts/bank_reconciliation.php" class="<?= $currentDir === 'accounts' && $current === 'bank_reconciliation.php' ? 'active' : '' ?>">Bank Reconciliation</a>
            <a href="/accounts/gst.php" class="<?= $currentDir === 'accounts' && in_array($current, ['gst.php', 'gstr1.php', 'gstr3b.php']) ? 'active' : '' ?>">GST</a>
            <a href="/accounts/tds.php" class="<?= $currentDir === 'accounts' && in_array($current, ['tds.php', 'tds_add.php', 'tds_view.php']) ? 'active' : '' ?>">TDS</a>
            <a href="/accounts/trial_balance.php" class="<?= $currentDir === 'accounts' && $current === 'trial_balance.php' ? 'active' : '' ?>">Trial Balance</a>
            <a href="/accounts/profit_loss.php" class="<?= $currentDir === 'accounts' && $current === 'profit_loss.php' ? 'active' : '' ?>">Profit & Loss</a>
            <a href="/accounts/balance_sheet.php" class="<?= $currentDir === 'accounts' && $current === 'balance_sheet.php' ? 'active' : '' ?>">Balance Sheet</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Admin -->
    <?php if ($showAdmin): ?>
    <div class="sidebar-group <?= $currentDir === 'admin' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'admin' ? 'active' : '' ?>">
            <a href="/admin/settings.php" class="module-link">Admin</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <?php if (_canView('admin_settings')): ?>
            <a href="/admin/settings.php" class="<?= $currentDir === 'admin' && $current === 'settings.php' ? 'active' : '' ?>">Company Settings</a>
            <?php endif; ?>
            <?php if (_canView('admin_users')): ?>
            <a href="/admin/users.php" class="<?= $currentDir === 'admin' && $current === 'users.php' ? 'active' : '' ?>">User Management</a>
            <a href="/admin/user_permissions.php" class="<?= $currentDir === 'admin' && $current === 'user_permissions.php' ? 'active' : '' ?>">User Permissions</a>
            <a href="/admin/login_logs.php" class="<?= $currentDir === 'admin' && $current === 'login_logs.php' ? 'active' : '' ?>">Login & Activity Logs</a>
            <?php endif; ?>
            <?php if (_canView('admin_locations')): ?>
            <a href="/admin/locations.php" class="<?= $currentDir === 'admin' && $current === 'locations.php' ? 'active' : '' ?>">Location Management</a>
            <a href="/admin/attendance_settings.php" class="<?= $currentDir === 'admin' && $current === 'attendance_settings.php' ? 'active' : '' ?>">Attendance Settings</a>
            <?php endif; ?>
            <?php if (_canView('admin_settings')): ?>
            <a href="/admin/wo_approvers.php" class="<?= $currentDir === 'admin' && $current === 'wo_approvers.php' ? 'active' : '' ?>">WO Approvers</a>
            <a href="/admin/po_inspection_approvers.php" class="<?= $currentDir === 'admin' && in_array($current, ['po_inspection_approvers.php', 'setup_po_inspection.php']) ? 'active' : '' ?>">PO Inspection Approvers</a>
            <a href="/admin/so_approvers.php" class="<?= $currentDir === 'admin' && $current === 'so_approvers.php' ? 'active' : '' ?>">SO Release Approvers</a>
            <a href="/admin/auto_task_rules.php" class="<?= $currentDir === 'admin' && $current === 'auto_task_rules.php' ? 'active' : '' ?>">Auto-Task Rules</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Customer Portal -->
    <?php if ($showPortal): ?>
    <div class="sidebar-group <?= $currentDir === 'customer_portal' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'customer_portal' ? 'active' : '' ?>" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 6px; margin: 5px 8px;">
            <a href="/customer_portal/index.php" class="module-link" style="color: white;">Customer Portal</a>
            <span class="arrow" onclick="toggleGroup(this.parentElement)" style="color: white;">&#9654;</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/customer_portal/admin_manage.php" class="<?= $currentDir === 'customer_portal' && $current === 'admin_manage.php' ? 'active' : '' ?>" style="color: #f39c12;">Manage Access</a>
            <a href="/customer_portal/index.php" class="<?= $currentDir === 'customer_portal' && $current === 'index.php' ? 'active' : '' ?>">View by Customer</a>
            <a href="/customer_portal/invoices.php" class="<?= $currentDir === 'customer_portal' && $current === 'invoices.php' ? 'active' : '' ?>">Invoices</a>
            <a href="/customer_portal/quotations.php" class="<?= $currentDir === 'customer_portal' && $current === 'quotations.php' ? 'active' : '' ?>">Quotations</a>
            <a href="/customer_portal/proforma.php" class="<?= $currentDir === 'customer_portal' && $current === 'proforma.php' ? 'active' : '' ?>">Proforma Invoice</a>
            <a href="/customer_portal/orders.php" class="<?= $currentDir === 'customer_portal' && $current === 'orders.php' ? 'active' : '' ?>">Order Status</a>
            <a href="/customer_portal/ledger.php" class="<?= $currentDir === 'customer_portal' && $current === 'ledger.php' ? 'active' : '' ?>">Account Ledger</a>
            <a href="/customer_portal/catalog.php" class="<?= $currentDir === 'customer_portal' && $current === 'catalog.php' ? 'active' : '' ?>">Catalog</a>
            <a href="/customer_portal/dockets.php" class="<?= $currentDir === 'customer_portal' && $current === 'dockets.php' ? 'active' : '' ?>">Docket Details</a>
            <a href="/customer_portal/eway_bills.php" class="<?= $currentDir === 'customer_portal' && $current === 'eway_bills.php' ? 'active' : '' ?>">E-Way Bills</a>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleGroup(header) {
    const group = header.parentElement;
    group.classList.toggle('open');

    // Save state to localStorage
    const moduleLink = header.querySelector('a.module-link');
    const groupName = moduleLink ? moduleLink.textContent : 'unknown';
    const isOpen = group.classList.contains('open');
    localStorage.setItem('sidebar_' + groupName, isOpen ? 'open' : 'closed');
}

// Restore saved states on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sidebar-group').forEach(group => {
        const moduleLink = group.querySelector('.sidebar-group-header a.module-link');
        const groupName = moduleLink ? moduleLink.textContent : 'unknown';
        const savedState = localStorage.getItem('sidebar_' + groupName);

        // If there's a saved state, use it (unless the group has an active item)
        if (savedState === 'open') {
            group.classList.add('open');
        } else if (savedState === 'closed' && !group.querySelector('.sidebar-group-items a.active')) {
            group.classList.remove('open');
        }
    });
});

// ===== THEME TOGGLE (Light / Mid / Dark) =====
const themeOrder = ['light', 'mid', 'dark'];
const themeLabels = { light: 'Light', mid: 'Dim', dark: 'Dark' };
const themeIcons = { light: '\u2600', mid: '\uD83C\uDF24', dark: '\uD83C\uDF19' };

function applyTheme(theme) {
    document.body.classList.remove('mid', 'dark');
    if (theme === 'mid') {
        document.body.classList.add('dark', 'mid');
    } else if (theme === 'dark') {
        document.body.classList.add('dark');
    }
    localStorage.setItem('theme', theme);

    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
        btn.textContent = themeIcons[theme] + ' ' + themeLabels[theme];
    }
}

function cycleTheme() {
    const current = localStorage.getItem('theme') || 'light';
    const idx = themeOrder.indexOf(current);
    const next = themeOrder[(idx + 1) % themeOrder.length];
    applyTheme(next);
}

// Apply saved theme on load
(function() {
    const saved = localStorage.getItem('theme') || 'light';
    applyTheme(saved);
    const btn = document.getElementById('themeToggleBtn');
    if (btn) btn.addEventListener('click', cycleTheme);
})();
</script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(reg) { console.log('SW registered:', reg.scope); })
            .catch(function(err) { console.log('SW failed:', err); });
    });
}
</script>