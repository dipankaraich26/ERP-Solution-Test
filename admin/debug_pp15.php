<?php
include "../db.php";
header('Content-Type: text/plain');

$planId = 16; // PP-015 (actual database ID)

echo "=== PP-015 DEBUG ===\n\n";

// 1. Plan details
echo "--- procurement_plans ---\n";
$stmt = $pdo->prepare("SELECT id, plan_no, status, so_list FROM procurement_plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($plan);

// 2. procurement_plan_items (old table)
echo "\n--- procurement_plan_items (old table) ---\n";
try {
    $stmt = $pdo->prepare("SELECT id, plan_id, part_no, status, created_po_id, created_po_line_id FROM procurement_plan_items WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($items) . "\n";
    foreach ($items as $i) {
        echo "  part_no={$i['part_no']} status={$i['status']} created_po_id={$i['created_po_id']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. procurement_plan_po_items (new table)
echo "\n--- procurement_plan_po_items (new table) ---\n";
try {
    $stmt = $pdo->prepare("SELECT id, plan_id, part_no, part_name, status, created_po_id, created_po_no, ordered_qty, supplier_name FROM procurement_plan_po_items WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $poItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($poItems) . "\n";
    foreach ($poItems as $i) {
        echo "  part_no={$i['part_no']} status={$i['status']} created_po_id=" . ($i['created_po_id'] ?? 'NULL') . " created_po_no=" . ($i['created_po_no'] ?? 'NULL') . " ordered_qty=" . ($i['ordered_qty'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. PO-31 in purchase_orders
echo "\n--- purchase_orders WHERE po_no = 'PO-31' ---\n";
try {
    $stmt = $pdo->query("SELECT id, po_no, part_no, qty, status, plan_id, supplier_id FROM purchase_orders WHERE po_no = 'PO-31'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  id={$r['id']} part_no={$r['part_no']} qty={$r['qty']} status={$r['status']} plan_id=" . ($r['plan_id'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 5. Check which PO numbers are linked to this plan via old table
echo "\n--- PO numbers linked to PP-015 via old procurement_plan_items ---\n";
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT po.po_no, po.status as po_status
        FROM procurement_plan_items ppi
        JOIN purchase_orders po ON po.id = ppi.created_po_id
        WHERE ppi.plan_id = ? AND ppi.created_po_id IS NOT NULL
    ");
    $stmt->execute([$planId]);
    $linkedPOs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($linkedPOs) . "\n";
    foreach ($linkedPOs as $l) {
        echo "  po_no={$l['po_no']} status={$l['po_status']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 6. Check POs with plan_id = 16 (PP-015)
echo "\n--- purchase_orders WHERE plan_id = 16 ---\n";
try {
    $stmt = $pdo->prepare("SELECT id, po_no, part_no, qty, status FROM purchase_orders WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $directPOs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($directPOs) . "\n";
    foreach ($directPOs as $d) {
        echo "  id={$d['id']} po_no={$d['po_no']} part_no={$d['part_no']} status={$d['status']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 7. Cross-check: PO-31 parts vs procurement_plan_po_items parts
echo "\n--- Part matching: PO-31 parts vs PP-015 po_items parts ---\n";
try {
    $po31Parts = $pdo->query("SELECT part_no FROM purchase_orders WHERE po_no = 'PO-31'")->fetchAll(PDO::FETCH_COLUMN);
    $ppPoParts = $pdo->prepare("SELECT part_no FROM procurement_plan_po_items WHERE plan_id = ?");
    $ppPoParts->execute([$planId]);
    $ppPoParts = $ppPoParts->fetchAll(PDO::FETCH_COLUMN);

    echo "PO-31 parts: " . implode(', ', $po31Parts) . "\n";
    echo "PP-015 po_items parts: " . implode(', ', $ppPoParts) . "\n";

    $matching = array_intersect($po31Parts, $ppPoParts);
    $onlyInPO = array_diff($po31Parts, $ppPoParts);
    $onlyInPP = array_diff($ppPoParts, $po31Parts);

    echo "Matching: " . (empty($matching) ? 'NONE' : implode(', ', $matching)) . "\n";
    echo "Only in PO-31: " . (empty($onlyInPO) ? 'NONE' : implode(', ', $onlyInPO)) . "\n";
    echo "Only in PP-015 po_items: " . (empty($onlyInPP) ? 'NONE' : implode(', ', $onlyInPP)) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 8. Check actual_po_status for each linked po_item
echo "\n--- Actual PO status for each linked procurement_plan_po_items ---\n";
try {
    $stmt = $pdo->prepare("
        SELECT ppi.part_no, ppi.created_po_id, ppi.created_po_no, ppi.status as pp_status,
               po.status as actual_po_status, po.part_no as po_part_no, po.qty as po_qty
        FROM procurement_plan_po_items ppi
        LEFT JOIN purchase_orders po ON po.id = ppi.created_po_id
        WHERE ppi.plan_id = ? AND ppi.created_po_id IS NOT NULL
    ");
    $stmt->execute([$planId]);
    $linked = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($linked) . "\n";
    foreach ($linked as $l) {
        echo "  pp_part={$l['part_no']} pp_status={$l['pp_status']} -> po_id={$l['created_po_id']} po_no={$l['created_po_no']} po_part={$l['po_part_no']} po_status={$l['actual_po_status']} po_qty={$l['po_qty']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
