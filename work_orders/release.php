<?php
include "../db.php";

$id = $_GET['id'];

$pdo->beginTransaction();

/* Fetch WO */
$wo = $pdo->query("
    SELECT * FROM work_orders WHERE id=$id AND status IN ('created','open')
")->fetch();
if (!$wo) die("Invalid WO");

/* Fetch BOM items */
$items = $pdo->query("
    SELECT component_part_no, qty
    FROM bom_items WHERE bom_id={$wo['bom_id']}
")->fetchAll();

/* Validate inventory */
foreach ($items as $i) {
    $need = $i['qty'] * $wo['qty'];

    $inv = $pdo->prepare("
        SELECT qty FROM inventory WHERE part_no=?
    ");
    $inv->execute([$i['component_part_no']]);
    $stock = $inv->fetchColumn();

    if ($stock === false || $stock < $need) {
        $pdo->rollBack();
        die("Insufficient stock for ".$i['component_part_no']);
    }
}

/* Issue stock */
foreach ($items as $i) {
    $issueQty = $i['qty'] * $wo['qty'];

    $pdo->prepare("
        INSERT INTO depletion
        (part_no, qty, issue_date, reason, status, issue_no)
        VALUES (?, ?, NOW(), 'Work Order', 'issued', ?)
    ")->execute([
        $i['component_part_no'],
        $issueQty,
        $wo['wo_no']
    ]);

    $depId = $pdo->lastInsertId();

    $pdo->prepare("
        UPDATE inventory SET qty = qty - ?
        WHERE part_no=?
    ")->execute([$issueQty, $i['component_part_no']]);

    $pdo->prepare("
        INSERT INTO work_order_issues (work_order_id, depletion_id)
        VALUES (?, ?)
    ")->execute([$id, $depId]);
}

/* Update WO */
$pdo->prepare("
    UPDATE work_orders SET status='released' WHERE id=?
")->execute([$id]);

/* Auto-create task for assigned engineer */
if (!empty($wo['assigned_to'])) {
    include_once "../includes/auto_task.php";
    $partName = $pdo->prepare("SELECT part_name FROM part_master WHERE part_no = ?");
    $partName->execute([$wo['part_no']]);
    $woPartName = $partName->fetchColumn() ?: $wo['part_no'];
    createAutoTask($pdo, [
        'task_name' => "Work Order {$wo['wo_no']} - Production",
        'task_description' => "Work Order {$wo['wo_no']} has been released. Complete production for Part: {$wo['part_no']} - $woPartName, Qty: {$wo['qty']}",
        'priority' => 'High',
        'assigned_to' => $wo['assigned_to'],
        'start_date' => date('Y-m-d'),
        'related_module' => 'Work Order',
        'related_id' => $id,
        'related_reference' => $wo['wo_no'],
        'created_by' => $_SESSION['user_id'] ?? null
    ]);
}

/* Add parent part to inventory */
$pdo->prepare("
    INSERT INTO inventory (part_no, qty)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
")->execute([$wo['part_no'], $wo['qty']]);

$pdo->commit();

header("Location: index.php");
exit;
