<?php
require 'db.php';
header('Content-Type: text/html');

echo "<pre style='font-family: monospace; padding: 20px;'>";

// Check work_orders table structure
$cols = $pdo->query("DESCRIBE work_orders")->fetchAll(PDO::FETCH_ASSOC);
echo "Work Orders Table Columns:\n";
foreach($cols as $c) {
    echo "  - " . $c['Field'] . " (" . $c['Type'] . ")\n";
}
echo "\n";

// Check some work orders and their status values
$wos = $pdo->query("SELECT id, wo_no, part_no, status FROM work_orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "Recent Work Orders:\n";
foreach($wos as $wo) {
    echo "  ID: " . $wo['id'] . " | WO: " . $wo['wo_no'] . " | Part: " . $wo['part_no'] . " | Status: [" . $wo['status'] . "]\n";
}

// Test the view query
echo "\n\nTesting view.php query for first WO:\n";
if (!empty($wos)) {
    $testId = $wos[0]['id'];
    $woStmt = $pdo->prepare("
        SELECT w.wo_no, w.part_no, w.qty, w.status, w.assigned_to, w.bom_id, w.plan_id, w.created_at,
               COALESCE(p.part_name, w.part_no) as part_name,
               b.id AS bom_master_id, b.bom_no, b.description,
               e.emp_id, e.first_name, e.last_name, e.department, e.designation
        FROM work_orders w
        LEFT JOIN part_master p ON w.part_no = p.part_no
        LEFT JOIN bom_master b ON w.bom_id = b.id
        LEFT JOIN employees e ON w.assigned_to = e.id
        WHERE w.id = ?
    ");
    $woStmt->execute([$testId]);
    $wo = $woStmt->fetch();

    if ($wo) {
        echo "Query SUCCESS for ID $testId:\n";
        echo "  WO No: " . $wo['wo_no'] . "\n";
        echo "  Part No: " . $wo['part_no'] . "\n";
        echo "  Part Name: " . $wo['part_name'] . "\n";
        echo "  Status: [" . $wo['status'] . "]\n";
        echo "  BOM: " . ($wo['bom_no'] ?? 'NULL') . "\n";
    } else {
        echo "Query returned NO RESULTS for ID $testId\n";
    }
} else {
    echo "No work orders found in database.\n";
}

echo "</pre>";
