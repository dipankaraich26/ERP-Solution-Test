<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "db.php";
include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>ERP Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="content">
    <h1>ERP Dashboard</h1>
    <p>Welcome to the ERP system.</p>

    <ul>
        <li><a href="part_master/list.php">Part Master</a></li>
        <li><a href="purchase/add.php">Purchase</a></li>
        <li><a href="depletion/add.php">Depletion</a></li>
        <li><a href="inventory/index.php">Inventory</a></li>
    </ul>
</div>

</body>
</html>
