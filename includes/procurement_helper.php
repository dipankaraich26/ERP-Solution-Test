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
    $stmt = $pdo->query("
        SELECT
            so.part_no,
            p.part_name,
            p.uom,
            SUM(so.qty) AS total_demand_qty,
            GROUP_CONCAT(DISTINCT so.so_no SEPARATOR ', ') AS so_list,
            GROUP_CONCAT(DISTINCT so.so_no) AS so_nos,
            COUNT(DISTINCT so.so_no) AS num_orders,
            COALESCE(i.qty, 0) AS current_stock,
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

    $placeholders = implode(',', array_fill(0, count($selectedSOs), '?'));

    $stmt = $pdo->prepare("
        SELECT
            so.part_no,
            p.part_name,
            p.uom,
            SUM(so.qty) AS total_demand_qty,
            GROUP_CONCAT(DISTINCT so.so_no SEPARATOR ', ') AS so_list,
            COUNT(DISTINCT so.so_no) AS num_orders,
            COALESCE(i.qty, 0) AS current_stock,
            COALESCE(pms.min_stock_qty, 0) AS min_stock_qty,
            COALESCE(pms.reorder_qty, 0) AS reorder_qty
        FROM sales_orders so
        JOIN part_master p ON so.part_no = p.part_no
        LEFT JOIN inventory i ON so.part_no = i.part_no
        LEFT JOIN part_min_stock pms ON so.part_no = pms.part_no
        WHERE so.so_no IN ($placeholders)
        AND so.status IN ('pending', 'open')
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

    $currentStock = (int)$part['current_stock'];
    $minStock = (int)$part['min_stock_qty'];
    $reorderQty = (int)$part['reorder_qty'];

    // Calculate shortage: demand minus current stock
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

            // Create PO line item
            $poStmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_no, part_no, qty, purchase_date, status, supplier_id)
                VALUES (?, ?, ?, ?, 'open', ?)
            ");
            $poStmt->execute([
                $poNo,
                $item['part_no'],
                $item['recommended_qty'],
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
            SUM(ppi.line_total) AS total_estimated_cost
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
 * Approve procurement plan
 */
function approveProcurementPlan($pdo, int $planId, int $userId): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plans
            SET status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$userId, $planId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Cancel procurement plan
 */
function cancelProcurementPlan($pdo, int $planId): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE procurement_plans
            SET status = 'cancelled'
            WHERE id = ? AND status = 'draft'
        ");
        return $stmt->execute([$planId]);
    } catch (Exception $e) {
        return false;
    }
}
