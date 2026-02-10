<?php
session_start();
include "../db.php";
include "../includes/procurement_helper.php";
header('Content-Type: text/plain; charset=utf-8');

echo "=== MY ORDERS DEBUG ===\n\n";

// Check session
echo "--- Session ---\n";
echo "customer_logged_in: " . (isset($_SESSION['customer_logged_in']) ? var_export($_SESSION['customer_logged_in'], true) : 'NOT SET') . "\n";
echo "customer_id: " . ($_SESSION['customer_id'] ?? 'NOT SET') . "\n";
$customer_id = $_SESSION['customer_id'] ?? null;
if (!$customer_id) {
    echo "ERROR: No customer_id in session. Login first.\n";
    exit;
}

// Check customer
echo "\n--- Customer lookup ---\n";
try {
    $stmt = $pdo->prepare("SELECT id, customer_id, customer_name, company_name FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        print_r($customer);
    } else {
        echo "NO customer found for id=$customer_id\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Check tables exist
echo "\n--- Table checks ---\n";
$tables = ['sales_orders', 'customer_po', 'quote_items', 'invoice_master', 'procurement_plans', 'procurement_plan_wo_items', 'procurement_plan_po_items', 'inventory'];
foreach ($tables as $t) {
    try {
        $r = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
        echo "  $t: " . ($r ? "EXISTS" : "MISSING") . "\n";
    } catch (Throwable $e) {
        echo "  $t: ERROR - " . $e->getMessage() . "\n";
    }
}

// Check sales_orders columns
echo "\n--- sales_orders columns ---\n";
try {
    $cols = $pdo->query("DESCRIBE sales_orders")->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(', ', $cols) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Main query
echo "\n--- Main orders query ---\n";
try {
    $orderStmt = $pdo->prepare("
        SELECT so.id, so.so_no, so.created_at, so.status, cp.po_no as customer_po_no,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = so.linked_quote_id) as total_value,
               (SELECT COUNT(*) FROM invoice_master WHERE so_no = so.so_no) as invoice_count
        FROM sales_orders so
        LEFT JOIN customer_po cp ON cp.id = so.customer_po_id
        WHERE so.customer_id = ? GROUP BY so.so_no ORDER BY so.created_at DESC
    ");
    $orderStmt->execute([$customer_id]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Orders found: " . count($orders) . "\n";
    foreach ($orders as $o) {
        echo "  so_no={$o['so_no']} status={$o['status']} value={$o['total_value']} invoices={$o['invoice_count']}\n";
    }
} catch (Throwable $e) {
    echo "QUERY ERROR: " . $e->getMessage() . "\n";
    $orders = [];
}

// PP lookup for each order
echo "\n--- PP lookup per order ---\n";
foreach ($orders as $o) {
    echo "\n  SO: {$o['so_no']}\n";
    try {
        $ppStmt = $pdo->prepare("
            SELECT pp.id, pp.plan_no, pp.status, pp.so_list
            FROM procurement_plans pp
            WHERE FIND_IN_SET(?, REPLACE(pp.so_list, ' ', '')) > 0
            AND pp.status NOT IN ('cancelled')
            LIMIT 1
        ");
        $ppStmt->execute([$o['so_no']]);
        $pp = $ppStmt->fetch(PDO::FETCH_ASSOC);
        if ($pp) {
            echo "    PP found: {$pp['plan_no']} (id={$pp['id']}) status={$pp['status']} so_list={$pp['so_list']}\n";

            // Test calculatePlanProgress
            try {
                $progress = calculatePlanProgress($pdo, (int)$pp['id'], $pp['status']);
                echo "    Progress: " . print_r($progress, true);
            } catch (Throwable $e) {
                echo "    calculatePlanProgress ERROR: " . $e->getMessage() . "\n";
                echo "    File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
            }
        } else {
            echo "    No PP found for this SO\n";
        }
    } catch (Throwable $e) {
        echo "    PP lookup ERROR: " . $e->getMessage() . "\n";
    }

    // Stock check
    echo "    Stock check:\n";
    try {
        $soLines = $pdo->prepare("SELECT part_no, qty FROM sales_orders WHERE so_no = ? AND status NOT IN ('cancelled')");
        $soLines->execute([$o['so_no']]);
        $lines = $soLines->fetchAll(PDO::FETCH_ASSOC);
        echo "    SO lines: " . count($lines) . "\n";
        $allOk = true;
        foreach ($lines as $line) {
            $stkQ = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stkQ->execute([$line['part_no']]);
            $stock = (int)$stkQ->fetchColumn();
            $needed = (int)$line['qty'];
            $ok = $stock >= $needed;
            echo "      part={$line['part_no']} need=$needed stock=$stock " . ($ok ? 'OK' : 'SHORT') . "\n";
            if (!$ok) $allOk = false;
        }
        echo "    All stock OK: " . ($allOk ? 'YES' : 'NO') . "\n";
    } catch (Throwable $e) {
        echo "    Stock check ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== END DEBUG ===\n";
