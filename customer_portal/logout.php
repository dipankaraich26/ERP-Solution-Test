<?php
session_start();

// Clear customer session
unset($_SESSION['customer_id']);
unset($_SESSION['customer_code']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_email']);
unset($_SESSION['customer_logged_in']);

// Redirect to login
header("Location: login.php");
exit;
