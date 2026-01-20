<?php
$current = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>

<style>
.sidebar-group {
    margin-bottom: 8px;
    border-bottom: 1px solid #34495e;
}
.sidebar-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    color: #ecf0f1;
    cursor: pointer;
    transition: background 0.2s;
    font-weight: bold;
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
    font-size: 0.8em;
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
    padding: 12px 15px 12px 30px !important;
    font-size: 0.9em;
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

    <button id="themeToggle" class="btn btn-secondary">
    🌙 Dark Mode
    </button>

    <a id="sidebar-ERP-title" href="/">ERP</a>

    <!-- Sales -->
    <div class="sidebar-group <?= in_array($currentDir, ['crm', 'quotes', 'proforma', 'customer_po', 'sales_orders', 'invoices', 'customers']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['crm', 'quotes', 'proforma', 'customer_po', 'sales_orders', 'invoices', 'customers']) ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Sales</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/crm/index.php" class="<?= $currentDir === 'crm' ? 'active' : '' ?>">CRM</a>
            <a href="/customers/index.php" class="<?= $currentDir === 'customers' ? 'active' : '' ?>">Customers</a>
            <a href="/quotes/index.php" class="<?= $currentDir === 'quotes' ? 'active' : '' ?>">Quotations</a>
            <a href="/proforma/index.php" class="<?= $currentDir === 'proforma' ? 'active' : '' ?>">Proforma Invoice</a>
            <a href="/customer_po/index.php" class="<?= $currentDir === 'customer_po' ? 'active' : '' ?>">Customer PO</a>
            <a href="/sales_orders/index.php" class="<?= $currentDir === 'sales_orders' ? 'active' : '' ?>">Sales Orders</a>
            <a href="/invoices/index.php" class="<?= $currentDir === 'invoices' ? 'active' : '' ?>">Invoice</a>
        </div>
    </div>

    <!-- Purchase & Procurement -->
    <div class="sidebar-group <?= in_array($currentDir, ['suppliers', 'purchase', 'procurement']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['suppliers', 'purchase', 'procurement']) ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Purchase & SCM</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/suppliers/index.php" class="<?= $currentDir === 'suppliers' ? 'active' : '' ?>">Suppliers</a>
            <a href="/purchase/index.php" class="<?= $currentDir === 'purchase' ? 'active' : '' ?>">Purchase Orders</a>
            <a href="/procurement/index.php" class="<?= $currentDir === 'procurement' ? 'active' : '' ?>">Procurement Planning</a>
        </div>
    </div>

    <!-- Inventory -->
    <div class="sidebar-group <?= in_array($currentDir, ['part_master', 'stock_entry', 'depletion', 'reports', 'inventory']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['part_master', 'stock_entry', 'depletion', 'reports', 'inventory']) ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Inventory</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/part_master/list.php" class="<?= $currentDir === 'part_master' ? 'active' : '' ?>">Part Master</a>
            <a href="/stock_entry/index.php" class="<?= $currentDir === 'stock_entry' ? 'active' : '' ?>">Stock Entries</a>
            <a href="/depletion/index.php" class="<?= $currentDir === 'depletion' ? 'active' : '' ?>">Depletion</a>
            <a href="/reports/monthly.php" class="<?= $currentDir === 'reports' ? 'active' : '' ?>">Reports</a>
            <a href="/inventory/index.php" class="<?= $currentDir === 'inventory' ? 'active' : '' ?>">Current Stock</a>
        </div>
    </div>

    <!-- Operations -->
    <div class="sidebar-group <?= in_array($currentDir, ['bom', 'work_orders']) ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= in_array($currentDir, ['bom', 'work_orders']) ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Operations</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/bom/index.php" class="<?= $currentDir === 'bom' ? 'active' : '' ?>">Bill of Materials</a>
            <a href="/work_orders/index.php" class="<?= $currentDir === 'work_orders' ? 'active' : '' ?>">Work Orders</a>
        </div>
    </div>

    <!-- HR -->
    <div class="sidebar-group <?= $currentDir === 'hr' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'hr' ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>HR</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/hr/employees.php" class="<?= $currentDir === 'hr' && in_array($current, ['employees.php', 'employee_add.php', 'employee_view.php', 'employee_edit.php']) ? 'active' : '' ?>">Employees</a>
            <a href="/hr/attendance.php" class="<?= $currentDir === 'hr' && in_array($current, ['attendance.php', 'attendance_mark.php', 'holidays.php']) ? 'active' : '' ?>">Attendance</a>
            <a href="/hr/payroll.php" class="<?= $currentDir === 'hr' && in_array($current, ['payroll.php', 'payroll_generate.php', 'payroll_view.php', 'payroll_edit.php']) ? 'active' : '' ?>">Payroll</a>
        </div>
    </div>

    <!-- Marketing -->
    <div class="sidebar-group <?= $currentDir === 'marketing' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'marketing' ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Marketing</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/marketing/catalogs.php" class="<?= $currentDir === 'marketing' && in_array($current, ['catalogs.php', 'catalog_add.php', 'catalog_view.php', 'catalog_edit.php']) ? 'active' : '' ?>">Catalogs</a>
            <a href="/marketing/campaigns.php" class="<?= $currentDir === 'marketing' && in_array($current, ['campaigns.php', 'campaign_add.php', 'campaign_view.php', 'campaign_edit.php']) ? 'active' : '' ?>">Campaigns</a>
            <a href="/marketing/whatsapp.php" class="<?= $currentDir === 'marketing' && $current === 'whatsapp.php' ? 'active' : '' ?>">WhatsApp</a>
            <a href="/marketing/analytics.php" class="<?= $currentDir === 'marketing' && $current === 'analytics.php' ? 'active' : '' ?>">Analytics</a>
        </div>
    </div>

    <!-- Service -->
    <div class="sidebar-group <?= $currentDir === 'service' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'service' ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Service</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/service/complaints.php" class="<?= $currentDir === 'service' && in_array($current, ['complaints.php', 'complaint_add.php', 'complaint_view.php', 'complaint_edit.php']) ? 'active' : '' ?>">Complaints</a>
            <a href="/service/technicians.php" class="<?= $currentDir === 'service' && $current === 'technicians.php' ? 'active' : '' ?>">Technicians</a>
            <a href="/service/analytics.php" class="<?= $currentDir === 'service' && $current === 'analytics.php' ? 'active' : '' ?>">Analytics</a>
        </div>
    </div>

    <!-- Admin -->
    <div class="sidebar-group <?= $currentDir === 'admin' ? 'open' : '' ?>">
        <div class="sidebar-group-header <?= $currentDir === 'admin' ? 'active' : '' ?>" onclick="toggleGroup(this)">
            <span>Admin</span>
            <span class="arrow">▶</span>
        </div>
        <div class="sidebar-group-items">
            <a href="/admin/settings.php" class="<?= $currentDir === 'admin' && $current === 'settings.php' ? 'active' : '' ?>">Company Settings</a>
            <a href="/admin/users.php" class="<?= $currentDir === 'admin' && $current === 'users.php' ? 'active' : '' ?>">User Management</a>
            <a href="/admin/locations.php" class="<?= $currentDir === 'admin' && $current === 'locations.php' ? 'active' : '' ?>">Location Management</a>
        </div>
    </div>

</div>

<script>
function toggleGroup(header) {
    const group = header.parentElement;
    group.classList.toggle('open');

    // Save state to localStorage
    const groupName = header.querySelector('span').textContent;
    const isOpen = group.classList.contains('open');
    localStorage.setItem('sidebar_' + groupName, isOpen ? 'open' : 'closed');
}

// Restore saved states on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sidebar-group').forEach(group => {
        const groupName = group.querySelector('.sidebar-group-header span').textContent;
        const savedState = localStorage.getItem('sidebar_' + groupName);

        // If there's a saved state, use it (unless the group has an active item)
        if (savedState === 'open') {
            group.classList.add('open');
        } else if (savedState === 'closed' && !group.querySelector('.sidebar-group-items a.active')) {
            group.classList.remove('open');
        }
    });
});
</script>

