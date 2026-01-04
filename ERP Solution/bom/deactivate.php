<?php
include "../db.php";

$pdo->prepare("
    UPDATE bom_master
    SET status='inactive'
    WHERE id=?
")->execute([$_GET['id']]);

header("Location: index.php");
exit;
