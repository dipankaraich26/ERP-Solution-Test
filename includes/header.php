<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP System</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>

<body class="has-topbar">

<?php
$_topbar_current = basename($_SERVER['PHP_SELF']);
$_topbar_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="topbar">
    <a href="/proforma/index.php" class="<?= $_topbar_dir === 'proforma' ? 'active' : '' ?>">Proforma Invoice</a>
    <a href="/customer_po/index.php" class="<?= $_topbar_dir === 'customer_po' ? 'active' : '' ?>">Customers PO</a>
    <a href="/sales_orders/index.php" class="<?= $_topbar_dir === 'sales_orders' ? 'active' : '' ?>">Sales Orders</a>
    <a href="/invoices/index.php" class="<?= $_topbar_dir === 'invoices' ? 'active' : '' ?>">Invoice</a>
    <a href="/purchase/index.php" class="<?= $_topbar_dir === 'purchase' && $_topbar_current === 'index.php' ? 'active' : '' ?>">Supplier PO</a>
    <a href="/work_orders/index.php" class="<?= $_topbar_dir === 'work_orders' ? 'active' : '' ?>">Work Order</a>
    <a href="/procurement/index.php" class="<?= $_topbar_dir === 'procurement' ? 'active' : '' ?>">Supplier Purchase Order</a>
    <a href="/bom/index.php" class="<?= $_topbar_dir === 'bom' ? 'active' : '' ?>">BOM</a>
    <a href="/inventory/index.php" class="<?= $_topbar_dir === 'inventory' && $_topbar_current === 'index.php' ? 'active' : '' ?>">Current Stock</a>
</div>

<div class="app-container">
