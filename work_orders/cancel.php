<?php
include "../db.php";

$pdo->prepare("
    UPDATE work_orders
    SET status='cancelled'
    WHERE id=? AND status IN ('created','open')
")->execute([$_GET['id']]);

header("Location: index.php");
exit;
