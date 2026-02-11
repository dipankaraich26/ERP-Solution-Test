<?php
include "../db.php";

echo "=== Finding part 82005502 in all plans ===\n";

// Check procurement_plan_po_items
$stmt = $pdo->query("
    SELECT ppi.plan_id, pp.plan_no, pp.so_list, pp.status as plan_status,
           ppi.part_no, ppi.required_qty, ppi.recommended_qty, ppi.status as item_status,
           ppi.created_po_id, ppi.created_po_no, ppi.ordered_qty
    FROM procurement_plan_po_items ppi
    JOIN procurement_plans pp ON pp.id = ppi.plan_id
    WHERE ppi.part_no = '82005502'
    ORDER BY ppi.plan_id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nPO Items with 82005502:\n";
if (empty($rows)) echo "  (none found)\n";
foreach ($rows as $r) {
    echo "  Plan: {$r['plan_no']} (id={$r['plan_id']}, status={$r['plan_status']}), "
       . "required={$r['required_qty']}, recommended={$r['recommended_qty']}, "
       . "item_status={$r['item_status']}, PO={$r['created_po_no']}, ordered={$r['ordered_qty']}\n";
    echo "  SO list: {$r['so_list']}\n";
}

// Check procurement_plan_items
$stmt2 = $pdo->query("
    SELECT ppi.plan_id, pp.plan_no, pp.status as plan_status,
           ppi.part_no, ppi.required_qty, ppi.recommended_qty
    FROM procurement_plan_items ppi
    JOIN procurement_plans pp ON pp.id = ppi.plan_id
    WHERE ppi.part_no = '82005502'
    ORDER BY ppi.plan_id
");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\nPlan Items with 82005502:\n";
if (empty($rows2)) echo "  (none found)\n";
foreach ($rows2 as $r) {
    echo "  Plan: {$r['plan_no']} (id={$r['plan_id']}, status={$r['plan_status']}), "
       . "required={$r['required_qty']}, recommended={$r['recommended_qty']}\n";
}

// Check BOM for 82005502
echo "\n=== BOM entries for 82005502 ===\n";
try {
    $bom1 = $pdo->query("SELECT * FROM bom_items WHERE part_no = '82005502' OR child_part_no = '82005502' LIMIT 20");
    $bomRows = $bom1->fetchAll(PDO::FETCH_ASSOC);
    if (empty($bomRows)) echo "  (none in bom_items)\n";
    foreach ($bomRows as $r) {
        print_r($r);
    }
} catch (Exception $e) {
    echo "  bom_items error: " . $e->getMessage() . "\n";
}

try {
    $bom2 = $pdo->query("SELECT * FROM bom_master WHERE part_no = '82005502' LIMIT 10");
    $bomRows2 = $bom2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($bomRows2)) echo "  (none in bom_master as parent)\n";
    foreach ($bomRows2 as $r) {
        print_r($r);
    }
} catch (Exception $e) {
    echo "  bom_master error: " . $e->getMessage() . "\n";
}

// Check PP-019 specifically
echo "\n=== PP-019 details ===\n";
$pp = $pdo->query("SELECT * FROM procurement_plans WHERE plan_no = 'PP-019'")->fetch(PDO::FETCH_ASSOC);
if ($pp) {
    echo "  ID: {$pp['id']}, Status: {$pp['status']}, SO list: {$pp['so_list']}\n";

    // All items in PP-019
    $allItems = $pdo->query("SELECT part_no, required_qty, recommended_qty FROM procurement_plan_po_items WHERE plan_id = {$pp['id']}")->fetchAll(PDO::FETCH_ASSOC);
    echo "  PO Items in PP-019:\n";
    if (empty($allItems)) echo "    (none)\n";
    foreach ($allItems as $ai) {
        echo "    {$ai['part_no']} - required: {$ai['required_qty']}, recommended: {$ai['recommended_qty']}\n";
    }
} else {
    echo "  PP-019 not found\n";
}

// Check all active plans and their SOs - what requires 82005502
echo "\n=== SOs that might need 82005502 via BOM ===\n";
$activePlans = $pdo->query("SELECT id, plan_no, so_list, status FROM procurement_plans WHERE status NOT IN ('cancelled') ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($activePlans as $ap) {
    echo "  {$ap['plan_no']} ({$ap['status']}): SOs = {$ap['so_list']}\n";
}

// Check where BOM explosion would produce 82005502 with qty 8
echo "\n=== Checking BOM explosion for qty=8 patterns ===\n";
// Find which parent parts have 82005502 as component
try {
    // Try different table structures
    $tables = $pdo->query("SHOW TABLES LIKE 'bom%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "BOM tables: " . implode(', ', $tables) . "\n";

    foreach ($tables as $t) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
        echo "  $t columns: " . implode(', ', $cols) . "\n";

        // Search for 82005502 in all text columns
        $found = $pdo->query("SELECT * FROM `$t` WHERE CONCAT_WS('|', " . implode(',', array_map(function($c) { return "`$c`"; }, $cols)) . ") LIKE '%82005502%' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        if ($found) {
            echo "  Found in $t:\n";
            foreach ($found as $f) print_r($f);
        }
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
