<?php
/**
 * Procurement Planning Helper Functions
 * Shared utilities for the SCM module
 */

/**
 * Generate next procurement plan number
 */
function generateProcurementPlanNo($pdo): string {
    $maxNo = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(plan_no,4) AS UNSIGNED)),0)
        FROM procurement_plans WHERE plan_no LIKE 'PP-%'
    ")->fetchColumn();
    return 'PP-' . str_pad(((int)$maxNo + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * Get all open/pending sales orders grouped by part
 * @return array Array of parts with total demand and SO list
 */
function getOpenSalesOrdersByPart($pdo): array {
    ensureStockBlocksTable($pdo);
    $stmt = $pdo->query("
        SELECT
            so.part_no,
            p.part_name,
            p.uom,
            SUM(so.qty) AS total_demand_qty,
            GROUP_CONCAT(DISTINCT so.so_no SEPARATOR ', ') AS so_list,
            GROUP_CONCAT(DISTINCT so.so_no) AS so_nos,
            COUNT(DISTINCT so.so_no) AS num_orders,
            COALESCE(i.qty, 0) AS actual_stock,
            GREATEST(0, COALESCE(i.qty, 0) - COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0)) AS current_stock,
            COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0) AS blocked_qty,
            COALESCE(pms.min_stock_qty, 0) AS min_stock_qty,
            COALESCE(pms.reorder_qty, 0) AS reorder_qty
        FROM sales_orders so
        JOIN part_master p ON so.part_no = p.part_no
        LEFT JOIN inventory i ON so.part_no = i.part_no
        LEFT JOIN part_min_stock pms ON so.part_no = pms.part_no
        WHERE so.status IN ('pending', 'open')
        GROUP BY so.part_no, p.part_name, p.uom, i.qty, pms.min_stock_qty, pms.reorder_qty
        ORDER BY so.part_no
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get selected sales orders (for filtered plan generation)
 * @param array $selectedSOs List of SO numbers to include
 */
function getSelectedSalesOrdersByPart($pdo, array $selectedSOs): array {
    if (empty($selectedSOs)) {
        return [];
    }

    ensureStockBlocksTable($pdo);
    $placeholders = implode(',', array_fill(0, count($selectedSOs), '?'));

    $stmt = $pdo->prepare("
        SELECT
            so.part_no,
            p.part_name,
            p.uom,
            SUM(so.qty) AS total_demand_qty,
            GROUP_CONCAT(DISTINCT so.so_no SEPARATOR ', ') AS so_list,
            COUNT(DISTINCT so.so_no) AS num_orders,
            COALESCE(i.qty, 0) AS actual_stock,
            GREATEST(0, COALESCE(i.qty, 0) - COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0)) AS current_stock,
            COALESCE((SELECT SUM(sb.blocked_qty) FROM stock_blocks sb WHERE sb.part_no = so.part_no), 0) AS blocked_qty,
            COALESCE(pms.min_stock_qty, 0) AS min_stock_qty,
            COALESCE(pms.reorder_qty, 0) AS reorder_qty
        FROM sales_orders so
        JOIN part_master p ON so.part_no = p.part_no
        LEFT JOIN inventory i ON so.part_no = i.part_no
        LEFT JOIN part_min_stock pms ON so.part_no = pms.part_no
        WHERE so.so_no IN ($placeholders)
        AND so.status NOT IN ('cancelled', 'closed', 'completed')
        GROUP BY so.part_no, p.part_name, p.uom, i.qty, pms.min_stock_qty, pms.reorder_qty
        ORDER BY so.part_no
    ");

    $stmt->execute($selectedSOs);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get best supplier for a part based on cost
 * @return array|null Supplier details (id, name, rate, lead_time)
 */
function getBestSupplier($pdo, string $partNo): ?array {
    $stmt = $pdo->prepare("
        SELECT
            psm.supplier_id,
            s.supplier_name,
            s.supplier_code,
            psm.supplier_rate,
            psm.lead_time_days,
            psm.min_order_qty,
            psm.supplier_sku
        FROM part_supplier_mapping psm
        JOIN suppliers s ON psm.supplier_id = s.id
        WHERE psm.part_no = ?
        AND psm.active = TRUE
        ORDER BY psm.supplier_rate ASC, psm.lead_time_days ASC
        LIMIT 1
    ");
    $stmt->execute([$partNo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result !== false ? $result : null;
}

/**
 * Get all suppliers for a part
 */
function getPartSuppliers($pdo, string $partNo): array {
    $stmt = $pdo->prepare("
        SELECT
            psm.id,
            psm.supplier_id,
            s.supplier_name,
            s.supplier_code,
            psm.supplier_rate,
            psm.lead_time_days,
            psm.min_order_qty,
            psm.supplier_sku,
            psm.is_preferred,
            psm.active
        FROM part_supplier_mapping psm
        JOIN suppliers s ON psm.supplier_id = s.id
        WHERE psm.part_no = ?
        ORDER BY psm.is_preferred DESC, psm.supplier_rate ASC
    ");
    $stmt->execute([$partNo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate procurement recommendation for a part
 * @return array Recommendation with shortage and order quantity
 */
function calculateProcurementRecommendation($pdo, string $partNo, int $demandQty): array {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(i.qty, 0) AS current_stock,
            COALESCE(pms.min_stock_qty, 0) AS min_stock_qty,
            COALESCE(pms.reorder_qty, 0) AS reorder_qty,
            p.uom
        FROM part_master p
        LEFT JOIN inventory i ON p.part_no = i.part_no
        LEFT JOIN part_min_stock pms ON p.part_no = pms.part_no
        WHERE p.part_no = ?
    ");
    $stmt->execute([$partNo]);
    $part = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$part) {
        return ['error' => 'Part not found'];
    }

    $actualStock = (int)$part['current_stock'];
    $blockedQty = (int)getBlockedQtyForPart($pdo, $partNo);
    $currentStock = max(0, $actualStock - $blockedQty);
    $minStock = (int)$part['min_stock_qty'];
    $reorderQty = (int)$part['reorder_qty'];

    // Calculate shortage: demand minus available stock (actual - blocked)
    $shortage = max(0, $demandQty - $currentStock);

    // Recommended order quantity: max of shortage or reorder quantity
    // Ensures we always meet min stock requirements
    $recommendedQty = max($shortage, max(0, $minStock - $currentStock));

    // If reorder_qty is set, use it as minimum (for MOQ optimization)
    if ($reorderQty > 0 && $recommendedQty > 0) {
        $recommendedQty = max($recommendedQty, $reorderQty);
    }

    return [
        'current_stock' => $currentStock,
        'actual_stock' => $actualStock,
        'blocked_qty' => $blockedQty,
        'min_stock_qty' => $minStock,
        'reorder_qty' => $reorderQty,
        'demand_qty' => $demandQty,
        'shortage' => $shortage,
        'recommended_qty' => $recommendedQty,
        'uom' => $part['uom']
    ];
}

/**
 * Create procurement plan with line items
 * @param array $items Array of plan items (part_no, demand_qty, supplier_id, etc.)
 */
function createProcurementPlan($pdo, array $items, string $notes = ''): ?array {
    try {
        $planNo = generateProcurementPlanNo($pdo);
        $totalParts = count($items);
        $totalQty = 0;
        $totalCost = 0;

        // Calculate totals
        foreach ($items as $item) {
            $totalQty += (int)($item['recommended_qty'] ?? 0);
            $totalCost += ((int)($item['recommended_qty'] ?? 0) * (float)($item['suggested_rate'] ?? 0));
        }

        $pdo->beginTransaction();

        // Insert plan header
        $stmt = $pdo->prepare("
            INSERT INTO procurement_plans
            (plan_no, status, total_parts, total_items_to_order, total_estimated_cost, notes, created_by)
            VALUES (?, 'draft', ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$planNo, $totalParts, $totalQty, $totalCost, $notes]);
        $planId = $pdo->lastInsertId();

        // Insert plan items
        $itemStmt = $pdo->prepare("
            INSERT INTO procurement_plan_items
            (plan_id, part_no, current_stock, required_qty, recommended_qty, min_stock_threshold, supplier_id, suggested_rate, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $lineTotal = ((int)($item['recommended_qty'] ?? 0) * (float)($item['suggested_rate'] ?? 0));
            $itemStmt->execute([
                $planId,
                $item['part_no'],
                $item['current_stock'] ?? 0,
                $item['required_qty'] ?? 0,
                $item['recommended_qty'] ?? 0,
                $item['min_stock_threshold'] ?? 0,
                $item['supplier_id'],
                $item['suggested_rate'] ?? 0,
                $lineTotal
            ]);
        }

        $pdo->commit();

        return [
            'success' => true,
            'plan_id' => $planId,
            'plan_no' => $planNo,
            'total_parts' => $totalParts,
            'total_qty' => $totalQty,
            'total_cost' => $totalCost
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Convert procurement plan to purchase orders (grouped by supplier)
 * @return array Result with PO numbers created
 */
function convertPlanToPurchaseOrders($pdo, int $planId, string $purchaseDate): ?array {
    try {
        // Get plan details
        $planStmt = $pdo->prepare("SELECT * FROM procurement_plans WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            return ['success' => false, 'error' => 'Plan not found'];
        }

        if ($plan['status'] !== 'approved') {
            return ['success' => false, 'error' => 'Plan must be approved before conversion to PO'];
        }

        // Get plan items
        $itemsStmt = $pdo->prepare("
            SELECT * FROM procurement_plan_items
            WHERE plan_id = ? AND status = 'pending'
            ORDER BY supplier_id, part_no
        ");
        $itemsStmt->execute([$planId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return ['success' => false, 'error' => 'No pending items in plan'];
        }

        $pdo->beginTransaction();
        $createdPOs = [];
        $currentSupplier = null;
        $poNo = null;

        // Group items by supplier and create POs
        foreach ($items as $item) {
            if ($item['supplier_id'] !== $currentSupplier) {
                // New supplier: generate new PO number
                $maxNo = $pdo->query("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(po_no,4) AS UNSIGNED)),0)
                    FROM purchase_orders WHERE po_no LIKE 'PO-%'
                ")->fetchColumn();
                $poNo = 'PO-' . ((int)$maxNo + 1);
                $currentSupplier = $item['supplier_id'];
                $createdPOs[$poNo] = [
                    'supplier_id' => $item['supplier_id'],
                    'items' => []
                ];
            }

            // Get supplier rate for this part
            $rateStmt2 = $pdo->prepare("
                SELECT supplier_rate FROM part_supplier_mapping
                WHERE part_no = ? AND supplier_id = ? AND active = 1
                LIMIT 1
            ");
            $rateStmt2->execute([$item['part_no'], $item['supplier_id']]);
            $itemRate = $rateStmt2->fetchColumn() ?: 0;

            // Create PO line item
            $poStmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_no, part_no, qty, rate, purchase_date, status, supplier_id)
                VALUES (?, ?, ?, ?, ?, 'open', ?)
            ");
            $poStmt->execute([
                $poNo,
                $item['part_no'],
                $item['recommended_qty'],
                $itemRate,
                $purchaseDate,
                $item['supplier_id']
            ]);

            $poLineId = $pdo->lastInsertId();

            // Update plan item with PO reference
            $updateStmt = $pdo->prepare("
                UPDATE procurement_plan_items
                SET status = 'ordered', created_po_id = ?, created_po_line_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$poLineId, $poLineId, $item['id']]);

            $createdPOs[$poNo]['items'][] = [
                'part_no' => $item['part_no'],
                'qty' => $item['recommended_qty']
            ];
        }

        // Update plan status
        $updatePlanStmt = $pdo->prepare("
            UPDATE procurement_plans
            SET status = 'partiallyordered'
            WHERE id = ?
        ");
        $updatePlanStmt->execute([$planId]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => count($createdPOs) . ' purchase order(s) created',
            'created_pos' => array_keys($createdPOs),
            'details' => $createdPOs
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update minimum stock configuration for a part
 */
function updatePartMinStock($pdo, string $partNo, int $minStock, int $reorderQty): bool {
    try {
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM part_min_stock WHERE part_no = ?");
        $stmt->execute([$partNo]);
        $exists = $stmt->fetch();

        if ($exists) {
            $updateStmt = $pdo->prepare("
                UPDATE part_min_stock
                SET min_stock_qty = ?, reorder_qty = ?
                WHERE part_no = ?
            ");
            $updateStmt->execute([$minStock, $reorderQty, $partNo]);
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO part_min_stock (part_no, min_stock_qty, reorder_qty)
                VALUES (?, ?, ?)
            ");
            $insertStmt->execute([$partNo, $minStock, $reorderQty]);
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Add supplier to part
 */
function addSupplierToPart($pdo, string $partNo, int $supplierId, float $rate,
                           int $leadDays = 5, int $minOrderQty = 1,
                           string $supplierSku = '', bool $isPreferred = false): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO part_supplier_mapping
            (part_no, supplier_id, supplier_sku, supplier_rate, lead_time_days, min_order_qty, is_preferred)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            supplier_rate = ?, lead_time_days = ?, min_order_qty = ?, is_preferred = ?
        ");

        $stmt->execute([
            $partNo, $supplierId, $supplierSku, $rate, $leadDays, $minOrderQty, $isPreferred,
            $rate, $leadDays, $minOrderQty, $isPreferred
        ]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get procurement plan details with items
 */
function getProcurementPlanDetails($pdo, int $planId): ?array {
    $stmt = $pdo->prepare("
        SELECT
            pp.*,
            COUNT(ppi.id) AS item_count,
            SUM(ppi.recommended_qty) AS total_order_qty,
            SUM(ppi.line_total) AS total_estimated_cost,
            SUM(CASE WHEN ppi.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN ppi.status = 'ordered' THEN 1 ELSE 0 END) AS ordered_count,
            SUM(CASE WHEN ppi.status = 'received' THEN 1 ELSE 0 END) AS received_count,
            (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id) AS wo_total,
            (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id AND status IN ('completed', 'closed')) AS wo_done,
            (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = pp.id AND status = 'in_progress') AS wo_in_progress,
            (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = pp.id) AS po_total,
            (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = pp.id AND status IN ('received', 'closed')) AS po_done,
            (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = pp.id AND status = 'ordered') AS po_in_progress
        FROM procurement_plans pp
        LEFT JOIN procurement_plan_items ppi ON pp.id = ppi.plan_id
        WHERE pp.id = ?
        GROUP BY pp.id
    ");
    $stmt->execute([$planId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all items for a procurement plan
 */
function getProcurementPlanItems($pdo, int $planId): array {
    $stmt = $pdo->prepare("
        SELECT
            ppi.*,
            p.part_name,
            p.uom,
            s.supplier_name,
            s.supplier_code
        FROM procurement_plan_items ppi
        JOIN part_master p ON ppi.part_no = p.part_no
        JOIN suppliers s ON ppi.supplier_id = s.id
        WHERE ppi.plan_id = ?
        ORDER BY p.part_name
    ");
    $stmt->execute([$planId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ensure stock_blocks table exists (auto-create)
 */
function ensureStockBlocksTable($pdo): void {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'stock_blocks'")->fetch();
        if (!$check) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS stock_blocks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    plan_id INT NOT NULL,
                    part_no VARCHAR(100) NOT NULL,
                    blocked_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_part_no (part_no),
                    INDEX idx_plan_id (plan_id),
                    UNIQUE KEY unique_plan_part (plan_id, part_no)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    } catch (Exception $e) {}
}

/**
 * Get total blocked quantity for a part across all active plans
 */
function getBlockedQtyForPart($pdo, string $partNo): float {
    try {
        ensureStockBlocksTable($pdo);
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(blocked_qty), 0) FROM stock_blocks WHERE part_no = ?");
        $stmt->execute([$partNo]);
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get available stock (actual - blocked) for a part
 */
function getAvailableStock($pdo, string $partNo): float {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
        $stmt->execute([$partNo]);
        $actual = (float)$stmt->fetchColumn();
        $blocked = getBlockedQtyForPart($pdo, $partNo);
        return max(0, $actual - $blocked);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Block stock for an approved procurement plan
 * Blocks min(required_qty, available_stock) for each part in WO+PO items
 */
function blockStockForPlan($pdo, int $planId): bool {
    try {
        ensureStockBlocksTable($pdo);

        // Collect all parts and their required quantities from main items + WO + PO items
        $mainItems = getProcurementPlanItems($pdo, $planId);
        $woItems = getPlanWorkOrderItems($pdo, $planId);
        $poItems = getPlanPurchaseOrderItems($pdo, $planId);

        $partRequirements = [];
        // Parent SO parts (main plan items) - block SO demand qty from stock
        foreach ($mainItems as $item) {
            $key = $item['part_no'];
            if (!isset($partRequirements[$key])) {
                $partRequirements[$key] = 0;
            }
            $partRequirements[$key] += (float)$item['required_qty'];
        }
        // WO child parts (BOM explosion - internal production)
        foreach ($woItems as $item) {
            $key = $item['part_no'];
            if (!isset($partRequirements[$key])) {
                $partRequirements[$key] = 0;
            }
            $partRequirements[$key] += (float)$item['required_qty'];
        }
        // PO child parts (BOM explosion - external procurement)
        foreach ($poItems as $item) {
            $key = $item['part_no'];
            if (!isset($partRequirements[$key])) {
                $partRequirements[$key] = 0;
            }
            $partRequirements[$key] += (float)$item['required_qty'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO stock_blocks (plan_id, part_no, blocked_qty)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE blocked_qty = VALUES(blocked_qty)
        ");

        foreach ($partRequirements as $partNo => $requiredQty) {
            // Get actual stock
            $stockStmt = $pdo->prepare("SELECT COALESCE(qty, 0) FROM inventory WHERE part_no = ?");
            $stockStmt->execute([$partNo]);
            $actualStock = (float)$stockStmt->fetchColumn();

            // Get already blocked by OTHER plans
            $blockedStmt = $pdo->prepare("SELECT COALESCE(SUM(blocked_qty), 0) FROM stock_blocks WHERE part_no = ? AND plan_id != ?");
            $blockedStmt->execute([$partNo, $planId]);
            $alreadyBlocked = (float)$blockedStmt->fetchColumn();

            $available = max(0, $actualStock - $alreadyBlocked);
            $blockQty = min($requiredQty, $available);

            if ($blockQty > 0) {
                $stmt->execute([$planId, $partNo, $blockQty]);
            }
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Re-sync stock blocks for all active (approved/partiallyordered) plans.
 * Call this once on page load to fix any plans that were approved before the fix.
 */
function syncStockBlocksForActivePlans($pdo): void {
    try {
        ensureStockBlocksTable($pdo);
        $stmt = $pdo->query("SELECT id FROM procurement_plans WHERE status IN ('approved', 'partiallyordered')");
        $activePlans = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($activePlans as $planId) {
            blockStockForPlan($pdo, (int)$planId);
        }
    } catch (Exception $e) {}
}

/**
 * Unblock stock when a plan is cancelled or completed
 */
function unblockStockForPlan($pdo, int $planId): bool {
    try {
        ensureStockBlocksTable($pdo);
        $stmt = $pdo->prepare("DELETE FROM stock_blocks WHERE plan_id = ?");
        return $stmt->execute([$planId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Approve procurement plan
 */
function approveProcurementPlan($pdo, int $planId, int $userId): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plans
            SET status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$userId, $planId]);

        // Block stock for the approved plan
        if ($result) {
            blockStockForPlan($pdo, $planId);
        }

        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Cancel procurement plan (draft, approved, or partiallyordered)
 * Unblocks any reserved stock when cancelling
 */
function cancelProcurementPlan($pdo, int $planId): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plans
            SET status = 'cancelled'
            WHERE id = ? AND status IN ('draft', 'approved', 'partiallyordered')
        ");
        $result = $stmt->execute([$planId]);

        // Unblock any stock that was reserved by this plan
        if ($result) {
            unblockStockForPlan($pdo, $planId);
        }

        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Part IDs (from part_master.part_id column) that go to Work Order (internal production)
 * Any part with these part_id values is manufactured internally
 * These parts ALWAYS go to Work Order, regardless of whether they have a BOM or child parts
 * Rule: If part_id is in this list → Work Order (even without BOM)
 *       If part_id is NOT in this list → Purchase Order
 */
function getWorkOrderPartIds(): array {
    return ['YID', '99', '46', '91', '83', '44', '42', '52'];
}

/**
 * Check if a part should go to Work Order (internal) or Purchase Order (sublet)
 * @param string $partId The part_id (custom identifier column) of the part
 * @return bool True if part goes to Work Order, False if it goes to Purchase Order
 */
function isWorkOrderPart(string $partId): bool {
    return in_array($partId, getWorkOrderPartIds());
}

/**
 * Check if a part number has a BOM (is a parent assembly)
 * @param PDO $pdo Database connection
 * @param string $partNo Part number to check
 * @return bool True if part has a BOM
 */
function hasActiveBom($pdo, string $partNo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bom_master WHERE parent_part_no = ? AND status = 'active'");
    $stmt->execute([$partNo]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get child parts (components) from BOM for a parent part
 * @param PDO $pdo Database connection
 * @param string $parentPartNo Parent part number
 * @return array List of child parts with quantities
 */
function getBomChildParts($pdo, string $parentPartNo): array {
    $stmt = $pdo->prepare("
        SELECT
            bi.component_part_no AS part_no,
            bi.qty AS bom_qty,
            p.part_name,
            p.uom,
            p.id AS db_id,
            p.part_id AS part_id,
            bm.bom_no
        FROM bom_master bm
        JOIN bom_items bi ON bm.id = bi.bom_id
        JOIN part_master p ON bi.component_part_no = p.part_no
        WHERE bm.parent_part_no = ? AND bm.status = 'active'
        ORDER BY p.part_name
    ");
    $stmt->execute([$parentPartNo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Recursively explode BOM and collect all child parts at all levels
 * This handles multi-level BOMs where a child part may also have its own BOM
 *
 * @param PDO $pdo Database connection
 * @param string $parentPartNo Parent part number to explode
 * @param float $parentQty Quantity multiplier from parent
 * @param string $soNo Sales order number for tracking
 * @param string $rootPartNo Original parent part number (for tracking)
 * @param string $rootPartName Original parent part name (for tracking)
 * @param array &$workOrderParts Reference to work order parts array
 * @param array &$purchaseOrderParts Reference to purchase order parts array
 * @param array &$visited Track visited parts to prevent infinite loops
 * @param int $level Current BOM level (for source labeling)
 */
function explodeBomRecursive($pdo, string $parentPartNo, float $parentQty, string $soNo,
                              string $rootPartNo, string $rootPartName,
                              array &$workOrderParts, array &$purchaseOrderParts,
                              array &$visited, int $level = 1): void {
    // Prevent infinite loops from circular BOM references
    if (isset($visited[$parentPartNo])) {
        return;
    }
    $visited[$parentPartNo] = true;

    // Check if this part has a BOM
    if (!hasActiveBom($pdo, $parentPartNo)) {
        return;
    }

    // Get child parts from BOM
    $childParts = getBomChildParts($pdo, $parentPartNo);

    foreach ($childParts as $child) {
        $childPartId = $child['part_id'] ?? '';
        $childRequiredQty = $child['bom_qty'] * $parentQty;
        $childKey = $child['part_no'];
        $sourceLabel = $level === 1 ? 'BOM Child (L1)' : 'BOM Child (L' . $level . ')';

        // Check if child part goes to Work Order or Purchase Order (by part_id)
        if (isWorkOrderPart($childPartId)) {
            // Child part goes to WORK ORDER
            if (isset($workOrderParts[$childKey])) {
                $workOrderParts[$childKey]['total_required_qty'] += $childRequiredQty;
                if (strpos($workOrderParts[$childKey]['so_list'], $soNo) === false) {
                    $workOrderParts[$childKey]['so_list'] .= ', ' . $soNo;
                }
            } else {
                $workOrderParts[$childKey] = [
                    'part_no' => $child['part_no'],
                    'part_name' => $child['part_name'],
                    'part_id' => $childPartId,
                    'uom' => $child['uom'],
                    'total_required_qty' => $childRequiredQty,
                    'so_list' => $soNo,
                    'is_work_order' => true,
                    'source' => $sourceLabel,
                    'parent_part_no' => $parentPartNo,
                    'parent_part_name' => $child['part_name'] ?? $parentPartNo,
                    'root_part_no' => $rootPartNo,
                    'root_part_name' => $rootPartName
                ];
            }

            // RECURSIVE: If this work order part also has a BOM, explode it too
            explodeBomRecursive($pdo, $child['part_no'], $childRequiredQty, $soNo,
                               $rootPartNo, $rootPartName,
                               $workOrderParts, $purchaseOrderParts, $visited, $level + 1);
        } else {
            // Child part goes to PURCHASE ORDER
            if (isset($purchaseOrderParts[$childKey])) {
                $purchaseOrderParts[$childKey]['total_required_qty'] += $childRequiredQty;
                if (strpos($purchaseOrderParts[$childKey]['so_list'], $soNo) === false) {
                    $purchaseOrderParts[$childKey]['so_list'] .= ', ' . $soNo;
                }
                $purchaseOrderParts[$childKey]['parent_parts'][] = [
                    'part_no' => $parentPartNo,
                    'part_name' => $child['part_name'] ?? $parentPartNo,
                    'so_no' => $soNo,
                    'bom_qty' => $child['bom_qty']
                ];
            } else {
                $purchaseOrderParts[$childKey] = [
                    'part_no' => $child['part_no'],
                    'part_name' => $child['part_name'],
                    'part_id' => $childPartId,
                    'uom' => $child['uom'],
                    'total_required_qty' => $childRequiredQty,
                    'so_list' => $soNo,
                    'is_sublet' => true,
                    'source' => $sourceLabel,
                    'parent_parts' => [[
                        'part_no' => $parentPartNo,
                        'part_name' => $child['part_name'] ?? $parentPartNo,
                        'so_no' => $soNo,
                        'bom_qty' => $child['bom_qty']
                    ]],
                    'root_part_no' => $rootPartNo,
                    'root_part_name' => $rootPartName
                ];
            }

            // RECURSIVE: Even purchase order parts might have BOMs to explode
            explodeBomRecursive($pdo, $child['part_no'], $childRequiredQty, $soNo,
                               $rootPartNo, $rootPartName,
                               $workOrderParts, $purchaseOrderParts, $visited, $level + 1);
        }
    }
}

/**
 * Get ALL parts for selected sales orders - both direct SO parts AND their BOM children
 * Now supports MULTI-LEVEL BOM explosion - if a child part has a BOM, it will be exploded too
 *
 * Categorizes each part based on part_id (custom identifier column):
 *   - Part ID IN [YID, 99, 46, 91, 83, 44, 42, 52] → Work Order (internal production)
 *   - Part ID NOT IN those → Purchase Order
 *
 * IMPORTANT: Parts go to Work Order based ONLY on their part_id, regardless of:
 *   - Whether they have a BOM or not
 *   - Whether they have child parts or not
 *   - Whether they are direct SO parts or BOM children
 *
 * @param PDO $pdo Database connection
 * @param array $selectedSOs Selected sales order numbers
 * @return array ['work_order' => [...], 'purchase_order' => [...]]
 */
function getAllPartsForSalesOrders($pdo, array $selectedSOs): array {
    if (empty($selectedSOs)) {
        return ['work_order' => [], 'purchase_order' => []];
    }

    $placeholders_so = implode(',', array_fill(0, count($selectedSOs), '?'));

    // Get all sales order parts (direct parts)
    $stmt = $pdo->prepare("
        SELECT
            so.so_no,
            so.part_no,
            so.qty AS so_qty,
            p.part_name,
            p.id AS db_id,
            p.part_id AS part_id,
            p.uom,
            'direct' AS source
        FROM sales_orders so
        JOIN part_master p ON so.part_no = p.part_no
        WHERE so.so_no IN ($placeholders_so)
        AND so.status NOT IN ('cancelled', 'closed', 'completed')
    ");
    $stmt->execute($selectedSOs);
    $soParts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $workOrderParts = [];
    $purchaseOrderParts = [];

    foreach ($soParts as $soPart) {
        $partNo = $soPart['part_no'];
        $partId = $soPart['part_id'] ?? '';  // Custom part_id column
        $requiredQty = (float)$soPart['so_qty'];
        $key = $soPart['part_no'];

        // Check if this direct SO part goes to Work Order or Purchase Order (by part_id)
        if (isWorkOrderPart($partId)) {
            // Direct SO part goes to WORK ORDER
            if (isset($workOrderParts[$key])) {
                $workOrderParts[$key]['total_required_qty'] += $requiredQty;
                if (strpos($workOrderParts[$key]['so_list'], $soPart['so_no']) === false) {
                    $workOrderParts[$key]['so_list'] .= ', ' . $soPart['so_no'];
                }
            } else {
                $workOrderParts[$key] = [
                    'part_no' => $soPart['part_no'],
                    'part_name' => $soPart['part_name'],
                    'part_id' => $partId,
                    'uom' => $soPart['uom'],
                    'total_required_qty' => $requiredQty,
                    'so_list' => $soPart['so_no'],
                    'is_work_order' => true,
                    'source' => 'Direct SO Part',
                    'parent_part_no' => '-',
                    'parent_part_name' => '-'
                ];
            }
        } else {
            // Direct SO part goes to PURCHASE ORDER
            if (isset($purchaseOrderParts[$key])) {
                $purchaseOrderParts[$key]['total_required_qty'] += $requiredQty;
                if (strpos($purchaseOrderParts[$key]['so_list'], $soPart['so_no']) === false) {
                    $purchaseOrderParts[$key]['so_list'] .= ', ' . $soPart['so_no'];
                }
            } else {
                $purchaseOrderParts[$key] = [
                    'part_no' => $soPart['part_no'],
                    'part_name' => $soPart['part_name'],
                    'part_id' => $partId,
                    'uom' => $soPart['uom'],
                    'total_required_qty' => $requiredQty,
                    'so_list' => $soPart['so_no'],
                    'is_sublet' => true,
                    'source' => 'Direct SO Part',
                    'parent_parts' => []
                ];
            }
        }

        // RECURSIVE BOM EXPLOSION: Explode BOM at ALL levels
        // This will find all child parts, and if those children have BOMs, explode those too
        $visited = [];  // Track visited parts to prevent infinite loops
        explodeBomRecursive($pdo, $soPart['part_no'], $requiredQty, $soPart['so_no'],
                           $soPart['part_no'], $soPart['part_name'],
                           $workOrderParts, $purchaseOrderParts, $visited);
    }

    return [
        'work_order' => array_values($workOrderParts),
        'purchase_order' => array_values($purchaseOrderParts)
    ];
}

/**
 * Get all parts that should go to Purchase Order for selected sales orders
 * These are parts (direct or child) with part_no NOT IN Work Order list
 *
 * @param PDO $pdo Database connection
 * @param array $selectedSOs Selected sales order numbers
 * @return array List of parts for Purchase Order
 */
function getSubletPartsForSalesOrders($pdo, array $selectedSOs): array {
    $allParts = getAllPartsForSalesOrders($pdo, $selectedSOs);
    return $allParts['purchase_order'];
}

/**
 * Get all parts that should go to Work Order for selected sales orders
 * These are parts (direct or child) with part_no IN [YID-99, YID-52, YID-83, YID-44, YID-42, YID-81, YID-46]
 *
 * @param PDO $pdo Database connection
 * @param array $selectedSOs Selected sales order numbers
 * @return array List of parts for Work Order
 */
function getWorkOrderPartsForSalesOrders($pdo, array $selectedSOs): array {
    $allParts = getAllPartsForSalesOrders($pdo, $selectedSOs);
    return $allParts['work_order'];
}

/**
 * Create purchase orders for sublet parts
 * @param PDO $pdo Database connection
 * @param array $subletItems Array of sublet items with supplier info
 * @param string $purchaseDate Purchase date for PO
 * @param string $notes Optional notes (not used - table doesn't have notes column)
 * @return array Result with created PO numbers
 */
function createSubletPurchaseOrders($pdo, array $subletItems, string $purchaseDate, string $notes = ''): array {
    if (empty($subletItems)) {
        return ['success' => false, 'error' => 'No sublet items provided'];
    }

    try {
        $pdo->beginTransaction();
        $createdPOs = [];
        $currentSupplier = null;
        $poNo = null;

        // Sort by supplier to group items
        usort($subletItems, function($a, $b) {
            return ($a['supplier_id'] ?? 0) - ($b['supplier_id'] ?? 0);
        });

        foreach ($subletItems as $item) {
            if (empty($item['supplier_id']) || $item['qty'] <= 0) {
                continue;
            }

            if ($item['supplier_id'] !== $currentSupplier) {
                // Generate new PO number for new supplier
                $maxNo = $pdo->query("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(po_no,4) AS UNSIGNED)),0)
                    FROM purchase_orders WHERE po_no LIKE 'PO-%'
                ")->fetchColumn();
                $poNo = 'PO-' . ((int)$maxNo + 1);
                $currentSupplier = $item['supplier_id'];
                $createdPOs[$poNo] = [
                    'supplier_id' => $item['supplier_id'],
                    'items' => [],
                    'is_sublet' => true
                ];
            }

            // Get supplier rate for this part
            $rateStmt = $pdo->prepare("
                SELECT supplier_rate FROM part_supplier_mapping
                WHERE part_no = ? AND supplier_id = ?
            ");
            $rateStmt->execute([$item['part_no'], $item['supplier_id']]);
            $rate = $rateStmt->fetchColumn() ?: 0;

            // Create PO line item
            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_no, part_no, qty, rate, purchase_date, status, supplier_id)
                VALUES (?, ?, ?, ?, ?, 'open', ?)
            ");
            $stmt->execute([
                $poNo,
                $item['part_no'],
                $item['qty'],
                $rate,
                $purchaseDate,
                $item['supplier_id']
            ]);

            $createdPOs[$poNo]['items'][] = [
                'part_no' => $item['part_no'],
                'qty' => $item['qty'],
                'rate' => $rate
            ];
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => count($createdPOs) . ' sublet purchase order(s) created',
            'created_pos' => array_keys($createdPOs),
            'details' => $createdPOs
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Save Work Order items for tracking in a procurement plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param array $workOrderItems Array of work order items
 * @return bool Success status
 */
function savePlanWorkOrderItems($pdo, int $planId, array $workOrderItems): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO procurement_plan_wo_items
            (plan_id, part_no, part_name, part_id, so_list, required_qty, current_stock, shortage, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ON DUPLICATE KEY UPDATE
            part_name = VALUES(part_name),
            required_qty = VALUES(required_qty),
            current_stock = VALUES(current_stock),
            shortage = VALUES(shortage)
        ");

        foreach ($workOrderItems as $item) {
            $stmt->execute([
                $planId,
                $item['part_no'],
                $item['part_name'],
                $item['part_id'] ?? '',
                $item['so_list'],
                $item['demand_qty'] ?? $item['total_required_qty'] ?? 0,
                $item['current_stock'] ?? 0,
                $item['shortage'] ?? 0
            ]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Save Purchase Order (sublet) items for tracking in a procurement plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param array $subletItems Array of sublet items
 * @return bool Success status
 */
function savePlanPurchaseOrderItems($pdo, int $planId, array $subletItems): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO procurement_plan_po_items
            (plan_id, part_no, part_name, part_id, so_list, required_qty, current_stock, shortage, supplier_id, supplier_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ON DUPLICATE KEY UPDATE
            part_name = VALUES(part_name),
            required_qty = VALUES(required_qty),
            current_stock = VALUES(current_stock),
            shortage = VALUES(shortage),
            supplier_id = VALUES(supplier_id),
            supplier_name = VALUES(supplier_name)
        ");

        foreach ($subletItems as $item) {
            $stmt->execute([
                $planId,
                $item['part_no'],
                $item['part_name'],
                $item['part_id'] ?? '',
                $item['so_list'],
                $item['demand_qty'] ?? $item['total_required_qty'] ?? 0,
                $item['current_stock'] ?? 0,
                $item['shortage'] ?? 0,
                $item['supplier_id'] ?? null,
                $item['supplier_name'] ?? 'No Supplier'
            ]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get Work Order items tracking for a procurement plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @return array Array of WO items with their status
 */
function getPlanWorkOrderItems($pdo, int $planId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM procurement_plan_wo_items
            WHERE plan_id = ?
            ORDER BY part_no
        ");
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get Purchase Order items tracking for a procurement plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @return array Array of PO items with their status
 */
function getPlanPurchaseOrderItems($pdo, int $planId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM procurement_plan_po_items
            WHERE plan_id = ?
            ORDER BY part_no
        ");
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Update Work Order item status when WO is created
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $partNo Part number
 * @param int $woId Work Order ID
 * @param string $woNo Work Order number
 * @return bool Success status
 */
function updatePlanWoItemStatus($pdo, int $planId, string $partNo, int $woId, string $woNo): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plan_wo_items
            SET status = 'in_progress', created_wo_id = ?, created_wo_no = ?
            WHERE plan_id = ? AND part_no = ?
        ");
        return $stmt->execute([$woId, $woNo, $planId, $partNo]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sync Work Order status changes back to the procurement plan tracking
 * Call this whenever a WO status changes (release, start, complete, close, cancel, reopen)
 * @param PDO $pdo Database connection
 * @param int $woId Work Order ID
 * @param string $newWoStatus The new work order status
 * @return bool Success status
 */
function syncWoStatusToPlan($pdo, int $woId, string $newWoStatus): bool {
    try {
        // Map WO status to procurement tracking status
        $statusMap = [
            'open'        => 'in_progress',
            'created'     => 'in_progress',
            'released'    => 'in_progress',
            'in_progress' => 'in_progress',
            'completed'   => 'completed',
            'qc_approval' => 'completed',
            'closed'      => 'closed',
            'cancelled'   => 'cancelled'
        ];
        $planStatus = $statusMap[$newWoStatus] ?? 'in_progress';

        // Method 1: Use plan_id from work_orders table
        $woStmt = $pdo->prepare("SELECT plan_id, part_no FROM work_orders WHERE id = ?");
        $woStmt->execute([$woId]);
        $wo = $woStmt->fetch(PDO::FETCH_ASSOC);

        if ($wo && $wo['plan_id']) {
            $stmt = $pdo->prepare("
                UPDATE procurement_plan_wo_items
                SET status = ?
                WHERE plan_id = ? AND part_no = ?
            ");
            return $stmt->execute([$planStatus, $wo['plan_id'], $wo['part_no']]);
        }

        // Method 2: Fallback - find via procurement_plan_wo_items using created_wo_id
        $fallbackStmt = $pdo->prepare("
            UPDATE procurement_plan_wo_items
            SET status = ?
            WHERE created_wo_id = ?
        ");
        $fallbackStmt->execute([$planStatus, $woId]);
        return $fallbackStmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update Purchase Order item status when PO is created
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $partNo Part number
 * @param int $poId Purchase Order ID
 * @param string $poNo Purchase Order number
 * @param float $orderedQty Quantity ordered
 * @return bool Success status
 */
function updatePlanPoItemStatus($pdo, int $planId, string $partNo, int $poId, string $poNo, float $orderedQty): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plan_po_items
            SET status = 'ordered', created_po_id = ?, created_po_no = ?, ordered_qty = ?
            WHERE plan_id = ? AND part_no = ?
        ");
        return $stmt->execute([$poId, $poNo, $orderedQty, $planId, $partNo]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if a Work Order has already been created for a part in this plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $partNo Part number
 * @return array|null WO details if exists, null otherwise
 */
function getExistingWoForPlanItem($pdo, int $planId, string $partNo): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM procurement_plan_wo_items
            WHERE plan_id = ? AND part_no = ? AND created_wo_id IS NOT NULL
        ");
        $stmt->execute([$planId, $partNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if a Purchase Order has already been created for a part in this plan
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $partNo Part number
 * @return array|null PO details if exists, null otherwise
 */
function getExistingPoForPlanItem($pdo, int $planId, string $partNo): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM procurement_plan_po_items
            WHERE plan_id = ? AND part_no = ? AND created_po_id IS NOT NULL
        ");
        $stmt->execute([$planId, $partNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get or create a procurement plan for selected sales orders
 * Returns existing plan if one exists for the same SO combination, otherwise creates new
 * @param PDO $pdo Database connection
 * @param array $selectedSOs Array of selected SO numbers
 * @return array Plan details with id and plan_no
 */
function getOrCreatePlanForSOs($pdo, array $selectedSOs): array {
    sort($selectedSOs); // Sort for consistent comparison
    $soListStr = implode(',', $selectedSOs);

    try {
        // Check if plan exists for these SOs
        $stmt = $pdo->prepare("
            SELECT id, plan_no, status FROM procurement_plans
            WHERE so_list = ? AND status NOT IN ('cancelled', 'completed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$soListStr]);
        $existingPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingPlan) {
            return [
                'success' => true,
                'plan_id' => (int)$existingPlan['id'],
                'plan_no' => $existingPlan['plan_no'],
                'status' => $existingPlan['status'],
                'is_existing' => true
            ];
        }

        // Create new plan
        $planNo = generateProcurementPlanNo($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO procurement_plans (plan_no, so_list, status, created_by)
            VALUES (?, ?, 'draft', 1)
        ");
        $stmt->execute([$planNo, $soListStr]);
        $planId = $pdo->lastInsertId();

        return [
            'success' => true,
            'plan_id' => (int)$planId,
            'plan_no' => $planNo,
            'status' => 'draft',
            'is_existing' => false
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create sublet purchase orders with plan tracking
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID to link POs to
 * @param array $subletItems Array of sublet items with supplier info
 * @param string $purchaseDate Purchase date for PO
 * @return array Result with created PO numbers
 */
function createSubletPurchaseOrdersWithTracking($pdo, int $planId, array $subletItems, string $purchaseDate): array {
    if (empty($subletItems)) {
        return ['success' => false, 'error' => 'No sublet items provided'];
    }

    try {
        $pdo->beginTransaction();
        $createdPOs = [];
        $currentSupplier = null;
        $poNo = null;
        $poId = null;

        // Sort by supplier to group items
        usort($subletItems, function($a, $b) {
            return ($a['supplier_id'] ?? 0) - ($b['supplier_id'] ?? 0);
        });

        foreach ($subletItems as $item) {
            if (empty($item['supplier_id']) || $item['qty'] <= 0) {
                continue;
            }

            // Check if already ordered
            $existingPo = getExistingPoForPlanItem($pdo, $planId, $item['part_no']);
            if ($existingPo) {
                continue; // Skip already ordered items
            }

            if ($item['supplier_id'] !== $currentSupplier) {
                // Generate new PO number for new supplier
                $maxNo = $pdo->query("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(po_no,4) AS UNSIGNED)),0)
                    FROM purchase_orders WHERE po_no LIKE 'PO-%'
                ")->fetchColumn();
                $poNo = 'PO-' . ((int)$maxNo + 1);
                $currentSupplier = $item['supplier_id'];
                $createdPOs[$poNo] = [
                    'supplier_id' => $item['supplier_id'],
                    'items' => [],
                    'is_sublet' => true
                ];
            }

            // Get supplier rate for this part
            $rateStmt = $pdo->prepare("
                SELECT supplier_rate FROM part_supplier_mapping
                WHERE part_no = ? AND supplier_id = ?
            ");
            $rateStmt->execute([$item['part_no'], $item['supplier_id']]);
            $rate = $rateStmt->fetchColumn() ?: 0;

            // Create PO line item with plan_id
            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_no, part_no, qty, rate, purchase_date, status, supplier_id, plan_id)
                VALUES (?, ?, ?, ?, ?, 'open', ?, ?)
            ");
            $stmt->execute([
                $poNo,
                $item['part_no'],
                $item['qty'],
                $rate,
                $purchaseDate,
                $item['supplier_id'],
                $planId
            ]);

            $poId = $pdo->lastInsertId();

            // Update plan PO item status
            updatePlanPoItemStatus($pdo, $planId, $item['part_no'], $poId, $poNo, $item['qty']);

            $createdPOs[$poNo]['items'][] = [
                'part_no' => $item['part_no'],
                'qty' => $item['qty'],
                'rate' => $rate
            ];
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => count($createdPOs) . ' sublet purchase order(s) created',
            'created_pos' => array_keys($createdPOs),
            'details' => $createdPOs
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create work order with plan tracking
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID to link WO to
 * @param string $partNo Part number
 * @param float $qty Quantity
 * @param int|null $bomId BOM ID (optional)
 * @param int|null $assignedTo Employee ID (optional)
 * @return array Result with WO details
 */
function createWorkOrderWithTracking($pdo, int $planId, string $partNo, float $qty, ?int $bomId = null, ?int $assignedTo = null): array {
    try {
        // Check if already created
        $existingWo = getExistingWoForPlanItem($pdo, $planId, $partNo);
        if ($existingWo) {
            return [
                'success' => false,
                'error' => 'Work Order already exists for this item: ' . $existingWo['created_wo_no']
            ];
        }

        // If no BOM ID provided, look it up from the part
        if ($bomId === null) {
            $bomStmt = $pdo->prepare("
                SELECT id FROM bom_master
                WHERE parent_part_no = ? AND status = 'active'
                ORDER BY id DESC LIMIT 1
            ");
            $bomStmt->execute([$partNo]);
            $bomId = $bomStmt->fetchColumn();

            // If still no BOM found, try to find any active BOM for this part
            if (!$bomId) {
                $bomStmt2 = $pdo->prepare("
                    SELECT bm.id FROM bom_master bm
                    WHERE bm.parent_part_no = ? AND bm.status = 'active'
                    LIMIT 1
                ");
                $bomStmt2->execute([$partNo]);
                $bomId = $bomStmt2->fetchColumn();
            }
        }

        // Generate WO number
        $maxNo = $pdo->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(wo_no,4) AS UNSIGNED)),0)
            FROM work_orders WHERE wo_no LIKE 'WO-%'
        ")->fetchColumn();
        $woNo = 'WO-' . ((int)$maxNo + 1);

        // Create work order - handle bom_id being null/optional
        if ($bomId) {
            // With BOM ID
            $stmt = $pdo->prepare("
                INSERT INTO work_orders (wo_no, part_no, bom_id, qty, assigned_to, plan_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([
                $woNo,
                $partNo,
                $bomId,
                $qty,
                $assignedTo ?: null,
                $planId
            ]);
        } else {
            // Without BOM ID (for parts without BOM)
            $stmt = $pdo->prepare("
                INSERT INTO work_orders (wo_no, part_no, qty, assigned_to, plan_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([
                $woNo,
                $partNo,
                $qty,
                $assignedTo ?: null,
                $planId
            ]);
        }

        $woId = $pdo->lastInsertId();

        // Update plan WO item status
        updatePlanWoItemStatus($pdo, $planId, $partNo, $woId, $woNo);

        return [
            'success' => true,
            'wo_id' => $woId,
            'wo_no' => $woNo,
            'message' => "Work Order $woNo created successfully"
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create all Work Orders for a specific Sales Order
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $soNo Sales Order number to create WOs for
 * @param array $workOrderItems All work order items (will filter by SO)
 * @return array Result with created WO details
 */
function createAllWorkOrdersForSO($pdo, int $planId, string $soNo, array $workOrderItems): array {
    $createdWOs = [];
    $errors = [];
    $skipped = 0;

    foreach ($workOrderItems as $item) {
        // Check if this item belongs to the specified SO
        $soList = $item['so_list'] ?? '';
        if (strpos($soList, $soNo) === false) {
            continue; // Skip items not for this SO
        }

        // Skip if no shortage
        if (($item['shortage'] ?? 0) <= 0) {
            $skipped++;
            continue;
        }

        // Try to create WO
        $result = createWorkOrderWithTracking($pdo, $planId, $item['part_no'], $item['shortage']);

        if ($result['success']) {
            $createdWOs[] = $result['wo_no'];
        } else {
            // Check if it's a duplicate error (already created)
            if (strpos($result['error'], 'already exists') !== false) {
                $skipped++;
            } else {
                $errors[] = $item['part_no'] . ': ' . $result['error'];
            }
        }
    }

    if (count($createdWOs) > 0) {
        return [
            'success' => true,
            'message' => count($createdWOs) . ' Work Order(s) created for ' . $soNo,
            'created_wos' => $createdWOs,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    } elseif ($skipped > 0 && count($errors) == 0) {
        return [
            'success' => true,
            'message' => 'All Work Orders for ' . $soNo . ' already exist or have no shortage',
            'created_wos' => [],
            'skipped' => $skipped
        ];
    } else {
        return [
            'success' => false,
            'error' => 'No Work Orders created. ' . implode('; ', $errors)
        ];
    }
}

/**
 * Create all Purchase Orders for a specific Sales Order
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID
 * @param string $soNo Sales Order number to create POs for
 * @param array $subletItems All sublet items (will filter by SO)
 * @param string $purchaseDate Purchase date
 * @return array Result with created PO details
 */
function createAllPurchaseOrdersForSO($pdo, int $planId, string $soNo, array $subletItems, string $purchaseDate): array {
    $itemsForSO = [];

    foreach ($subletItems as $item) {
        // Check if this item belongs to the specified SO
        $soList = $item['so_list'] ?? '';
        if (strpos($soList, $soNo) === false) {
            continue; // Skip items not for this SO
        }

        // Skip if no shortage or no supplier
        if (($item['shortage'] ?? 0) <= 0 || empty($item['supplier_id'])) {
            continue;
        }

        // Check if already ordered
        $existingPo = getExistingPoForPlanItem($pdo, $planId, $item['part_no']);
        if ($existingPo) {
            continue; // Skip already ordered
        }

        $itemsForSO[] = [
            'part_no' => $item['part_no'],
            'qty' => $item['shortage'],
            'supplier_id' => $item['supplier_id']
        ];
    }

    if (empty($itemsForSO)) {
        return [
            'success' => true,
            'message' => 'All Purchase Orders for ' . $soNo . ' already exist, have no shortage, or no supplier configured',
            'created_pos' => []
        ];
    }

    return createSubletPurchaseOrdersWithTracking($pdo, $planId, $itemsForSO, $purchaseDate);
}

/**
 * Get Work Order items grouped by Sales Order
 * @param array $workOrderItems All work order items
 * @return array Items grouped by SO number
 */
function groupWorkOrderItemsBySO(array $workOrderItems): array {
    $grouped = [];

    foreach ($workOrderItems as $item) {
        $soList = $item['so_list'] ?? '';
        // Split by comma if multiple SOs
        $soNos = array_map('trim', explode(',', $soList));

        foreach ($soNos as $soNo) {
            if (empty($soNo)) continue;

            if (!isset($grouped[$soNo])) {
                $grouped[$soNo] = [];
            }
            // Avoid duplicates
            $found = false;
            foreach ($grouped[$soNo] as $existing) {
                if ($existing['part_no'] === $item['part_no']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $grouped[$soNo][] = $item;
            }
        }
    }

    ksort($grouped); // Sort by SO number
    return $grouped;
}

/**
 * Get Purchase Order items grouped by Sales Order
 * @param array $subletItems All sublet items
 * @return array Items grouped by SO number
 */
function groupPurchaseOrderItemsBySO(array $subletItems): array {
    $grouped = [];

    foreach ($subletItems as $item) {
        $soList = $item['so_list'] ?? '';
        // Split by comma if multiple SOs
        $soNos = array_map('trim', explode(',', $soList));

        foreach ($soNos as $soNo) {
            if (empty($soNo)) continue;

            if (!isset($grouped[$soNo])) {
                $grouped[$soNo] = [];
            }
            // Avoid duplicates
            $found = false;
            foreach ($grouped[$soNo] as $existing) {
                if ($existing['part_no'] === $item['part_no']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $grouped[$soNo][] = $item;
            }
        }
    }

    ksort($grouped); // Sort by SO number
    return $grouped;
}

/**
 * Check if all SOs in a procurement plan are released/closed/completed.
 * If so, auto-close the plan and mark all WO/PO items as closed.
 * @param PDO $pdo Database connection
 * @param int $planId Plan ID to check
 * @return bool True if plan was auto-closed
 */
function autoClosePlanIfAllSOsReleased($pdo, int $planId): bool {
    try {
        // Get plan details
        $planStmt = $pdo->prepare("SELECT id, so_list, status FROM procurement_plans WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan || in_array($plan['status'], ['completed', 'cancelled'])) {
            return false; // Already closed or cancelled
        }

        $soList = $plan['so_list'] ?? '';
        if (empty($soList)) {
            return false;
        }

        // Parse SO numbers from comma-separated list
        $soNumbers = array_map('trim', explode(',', $soList));
        $soNumbers = array_filter($soNumbers);

        if (empty($soNumbers)) {
            return false;
        }

        // Check if ALL SOs are released/closed/completed
        $placeholders = implode(',', array_fill(0, count($soNumbers), '?'));
        $checkStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT so_no) as total,
                   COUNT(DISTINCT CASE WHEN status IN ('released', 'closed', 'completed') THEN so_no END) as released_count
            FROM sales_orders
            WHERE so_no IN ($placeholders)
        ");
        $checkStmt->execute(array_values($soNumbers));
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['total'] == 0) {
            return false;
        }

        // All SOs must be released/closed/completed
        if ($result['released_count'] < $result['total']) {
            return false;
        }

        // All SOs are released - auto-close the procurement plan
        $pdo->prepare("UPDATE procurement_plans SET status = 'completed' WHERE id = ?")
             ->execute([$planId]);

        // Mark all WO items as closed
        $pdo->prepare("UPDATE procurement_plan_wo_items SET status = 'closed' WHERE plan_id = ?")
             ->execute([$planId]);

        // Mark all PO items as closed
        $pdo->prepare("UPDATE procurement_plan_po_items SET status = 'closed' WHERE plan_id = ?")
             ->execute([$planId]);

        // Unblock stock since plan is now completed
        unblockStockForPlan($pdo, $planId);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check all procurement plans that contain a given SO number and auto-close if needed.
 * Call this after an SO is released.
 * @param PDO $pdo Database connection
 * @param string $soNo The SO number that was just released
 * @return array List of plan IDs that were auto-closed
 */
function autoClosePlansForReleasedSO($pdo, string $soNo): array {
    $closedPlans = [];
    try {
        // Find all plans that contain this SO
        $stmt = $pdo->prepare("
            SELECT id FROM procurement_plans
            WHERE FIND_IN_SET(?, REPLACE(so_list, ' ', '')) > 0
            AND status NOT IN ('completed', 'cancelled')
        ");
        $stmt->execute([$soNo]);
        $plans = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($plans as $planId) {
            if (autoClosePlanIfAllSOsReleased($pdo, (int)$planId)) {
                $closedPlans[] = $planId;
            }
        }
    } catch (Exception $e) {}
    return $closedPlans;
}

/**
 * Refresh WO/PO items for a plan from latest BOM
 * Re-runs BOM explosion and updates tracking tables
 * Only works when plan is not completed (SOs not all released)
 *
 * @param PDO $pdo
 * @param int $planId
 * @return array ['success' => bool, 'message' => string, 'wo_count' => int, 'po_count' => int]
 */
function refreshPlanFromBOM($pdo, int $planId): array {
    try {
        // Get plan details
        $planStmt = $pdo->prepare("SELECT id, so_list, status FROM procurement_plans WHERE id = ?");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            return ['success' => false, 'message' => 'Plan not found'];
        }
        if ($plan['status'] === 'completed') {
            return ['success' => false, 'message' => 'Cannot refresh a completed plan'];
        }
        if ($plan['status'] === 'cancelled') {
            return ['success' => false, 'message' => 'Cannot refresh a cancelled plan'];
        }

        $soList = $plan['so_list'] ?? '';
        if (empty($soList)) {
            return ['success' => false, 'message' => 'No SOs linked to this plan'];
        }

        // Parse SO numbers
        $soNumbers = array_map('trim', explode(',', $soList));
        $soNumbers = array_filter($soNumbers);

        if (empty($soNumbers)) {
            return ['success' => false, 'message' => 'No valid SO numbers found'];
        }

        // Check which SOs are still active (not released/closed/completed)
        $activeSOs = [];
        foreach ($soNumbers as $soNo) {
            $soStmt = $pdo->prepare("SELECT DISTINCT status FROM sales_orders WHERE so_no = ?");
            $soStmt->execute([$soNo]);
            $soStatuses = $soStmt->fetchAll(PDO::FETCH_COLUMN);
            // Include SO if at least one line is not released/closed/completed
            $hasActive = false;
            foreach ($soStatuses as $st) {
                if (!in_array($st, ['released', 'closed', 'completed', 'cancelled'])) {
                    $hasActive = true;
                    break;
                }
            }
            if ($hasActive) {
                $activeSOs[] = $soNo;
            }
        }

        if (empty($activeSOs)) {
            return ['success' => false, 'message' => 'All linked SOs are already released'];
        }

        // Re-run BOM explosion for all linked SOs (including released ones for completeness)
        $allParts = getAllPartsForSalesOrders($pdo, $soNumbers);
        $workOrderParts = $allParts['work_order'] ?? [];
        $purchaseOrderParts = $allParts['purchase_order'] ?? [];

        // Prepare work order items with available stock (actual - blocked)
        $workOrderItems = [];
        foreach ($workOrderParts as $wp) {
            $currentStock = (int)getAvailableStock($pdo, $wp['part_no']);
            $shortage = max(0, $wp['total_required_qty'] - $currentStock);

            $workOrderItems[] = [
                'part_no' => $wp['part_no'],
                'part_name' => $wp['part_name'],
                'part_id' => $wp['part_id'] ?? '',
                'so_list' => $wp['so_list'],
                'demand_qty' => $wp['total_required_qty'],
                'current_stock' => $currentStock,
                'shortage' => $shortage,
            ];
        }

        // Prepare purchase order items with available stock and best supplier
        $subletItems = [];
        foreach ($purchaseOrderParts as $sp) {
            $currentStock = (int)getAvailableStock($pdo, $sp['part_no']);
            $shortage = max(0, $sp['total_required_qty'] - $currentStock);

            $bestSupplier = getBestSupplier($pdo, $sp['part_no']);

            $subletItems[] = [
                'part_no' => $sp['part_no'],
                'part_name' => $sp['part_name'],
                'part_id' => $sp['part_id'] ?? '',
                'so_list' => $sp['so_list'],
                'demand_qty' => $sp['total_required_qty'],
                'current_stock' => $currentStock,
                'shortage' => $shortage,
                'supplier_id' => $bestSupplier ? $bestSupplier['supplier_id'] : null,
                'supplier_name' => $bestSupplier ? $bestSupplier['supplier_name'] : 'No Supplier',
                'parent_parts' => $sp['parent_parts'] ?? [],
            ];
        }

        // Adjust PO items: if parent WO parts are "In Stock", child PO parts don't need ordering
        adjustPoItemsForInStockWoParents($subletItems, $workOrderItems);

        // Save updated items (ON DUPLICATE KEY UPDATE preserves created_wo_id/created_po_id)
        if (!empty($workOrderItems)) {
            savePlanWorkOrderItems($pdo, $planId, $workOrderItems);
        }
        if (!empty($subletItems)) {
            savePlanPurchaseOrderItems($pdo, $planId, $subletItems);
        }

        // Remove WO items no longer in BOM (only if they don't have a linked WO)
        $newWoPartNos = array_map(function($i) { return $i['part_no']; }, $workOrderItems);
        if (!empty($newWoPartNos)) {
            $woPlaceholders = implode(',', array_fill(0, count($newWoPartNos), '?'));
            $pdo->prepare("
                DELETE FROM procurement_plan_wo_items
                WHERE plan_id = ? AND part_no NOT IN ($woPlaceholders)
                AND (created_wo_id IS NULL OR created_wo_id = 0)
            ")->execute(array_merge([$planId], $newWoPartNos));
        }

        // Remove PO items no longer in BOM (only if they don't have a linked PO)
        $newPoPartNos = array_map(function($i) { return $i['part_no']; }, $subletItems);
        if (!empty($newPoPartNos)) {
            $poPlaceholders = implode(',', array_fill(0, count($newPoPartNos), '?'));
            $pdo->prepare("
                DELETE FROM procurement_plan_po_items
                WHERE plan_id = ? AND part_no NOT IN ($poPlaceholders)
                AND (created_po_id IS NULL OR created_po_id = 0)
            ")->execute(array_merge([$planId], $newPoPartNos));
        }

        return [
            'success' => true,
            'message' => 'BOM refreshed successfully. WO items: ' . count($workOrderItems) . ', PO items: ' . count($subletItems),
            'wo_count' => count($workOrderItems),
            'po_count' => count($subletItems)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error refreshing BOM: ' . $e->getMessage()];
    }
}

/**
 * Adjust PO items when their parent WO parts are "In Stock".
 * If a WO part has sufficient stock (shortage <= 0), it doesn't need production,
 * so its BOM child PO parts don't need procurement either.
 *
 * Only suppresses PO items where ALL parent parts are in-stock WO parts.
 * If a PO item has mixed parents (some in stock, some not), it stays as-is.
 *
 * @param array &$subletItems PO items (modified in-place)
 * @param array $workOrderItems WO items with shortage info
 */
function adjustPoItemsForInStockWoParents(array &$subletItems, array $workOrderItems): void {
    // Build map of WO part_no => shortage
    $woShortageMap = [];
    foreach ($workOrderItems as $wi) {
        $woShortageMap[$wi['part_no']] = $wi['shortage'] ?? 0;
    }

    foreach ($subletItems as &$si) {
        if (empty($si['parent_parts'])) {
            continue; // Direct SO part, no parent WO to check
        }

        // Check if ALL parents are in-stock WO parts (shortage <= 0)
        $allParentsInStock = true;
        foreach ($si['parent_parts'] as $pp) {
            $parentPartNo = $pp['part_no'];
            // Parent must be in WO items AND have shortage <= 0
            if (!isset($woShortageMap[$parentPartNo]) || $woShortageMap[$parentPartNo] > 0) {
                $allParentsInStock = false;
                break;
            }
        }

        if ($allParentsInStock) {
            $si['shortage'] = 0;
            $si['parent_in_stock'] = true;
        }
    }
    unset($si);
}
