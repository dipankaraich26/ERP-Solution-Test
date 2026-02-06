<?php
require '../db.php';
require '../includes/auth.php';
requireLogin();

include "../includes/sidebar.php";

$success = '';
$error = '';

// Fetch all suppliers (no status filter - matches purchase/index.php pattern)
$suppliers = [];
try {
    $suppliers = $pdo->query("
        SELECT id, supplier_name, supplier_code, contact_person, phone, email,
               address1, address2, city, state, pincode, gstin
        FROM suppliers
        ORDER BY supplier_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
    $suppliers = [];
}

// Get unique Part IDs from part_master
$partIds = [];
try {
    $partIds = $pdo->query("
        SELECT DISTINCT part_id
        FROM part_master
        WHERE part_id IS NOT NULL AND part_id != '' AND status='active'
        ORDER BY part_id
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore
    $partIds = [];
}

// Fetch all active parts
$parts = [];
try {
    $parts = $pdo->query("
        SELECT part_no, part_name, part_id, hsn_code, uom, rate, gst
        FROM part_master
        WHERE status='active'
        ORDER BY part_no
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore
    $parts = [];
}

// Handle AJAX request to fetch existing supplier mappings
if (isset($_GET['action']) && $_GET['action'] === 'get_supplier_mappings') {
    header('Content-Type: application/json');
    $supplier_id = (int)($_GET['supplier_id'] ?? 0);

    if (!$supplier_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT psm.id, psm.part_no, psm.supplier_rate, psm.lead_time_days,
                   psm.min_order_qty, psm.is_preferred, psm.active,
                   pm.part_name, pm.part_id, pm.hsn_code, pm.uom, pm.rate as base_rate
            FROM part_supplier_mapping psm
            JOIN part_master pm ON psm.part_no = pm.part_no
            WHERE psm.supplier_id = ?
            ORDER BY psm.is_preferred DESC, pm.part_name ASC
        ");
        $stmt->execute([$supplier_id]);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'mappings' => $mappings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle update existing mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mapping') {
    header('Content-Type: application/json');
    $mapping_id = (int)($_POST['mapping_id'] ?? 0);
    $rate = (float)($_POST['rate'] ?? 0);
    $min_qty = (int)($_POST['min_qty'] ?? 1);
    $lead_days = (int)($_POST['lead_days'] ?? 5);
    $is_preferred = isset($_POST['is_preferred']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$mapping_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid mapping ID']);
        exit;
    }

    try {
        // If setting as preferred, unset other preferred for same part
        if ($is_preferred) {
            $getPartStmt = $pdo->prepare("SELECT part_no FROM part_supplier_mapping WHERE id = ?");
            $getPartStmt->execute([$mapping_id]);
            $partNo = $getPartStmt->fetchColumn();

            if ($partNo) {
                $unsetStmt = $pdo->prepare("UPDATE part_supplier_mapping SET is_preferred = 0 WHERE part_no = ? AND id != ?");
                $unsetStmt->execute([$partNo, $mapping_id]);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE part_supplier_mapping
            SET supplier_rate = ?, min_order_qty = ?, lead_time_days = ?, is_preferred = ?, active = ?
            WHERE id = ?
        ");
        $stmt->execute([$rate, $min_qty, $lead_days, $is_preferred, $active, $mapping_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle delete mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_mapping') {
    header('Content-Type: application/json');
    $mapping_id = (int)($_POST['mapping_id'] ?? 0);

    if (!$mapping_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid mapping ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM part_supplier_mapping WHERE id = ?");
        $stmt->execute([$mapping_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_supplier_pricing') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $part_nos = $_POST['part_nos'] ?? [];
    $rates = $_POST['rates'] ?? [];
    $min_qtys = $_POST['min_qtys'] ?? [];
    $lead_days = $_POST['lead_days'] ?? [];

    if (!$supplier_id) {
        $error = "Please select a supplier";
    } elseif (empty($part_nos)) {
        $error = "Please add at least one part";
    } else {
        $addedCount = 0;
        $updatedCount = 0;

        try {
            // Ensure part_supplier_mapping table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS part_supplier_mapping (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    part_no VARCHAR(50) NOT NULL,
                    supplier_id INT NOT NULL,
                    supplier_rate DECIMAL(15,2) DEFAULT 0,
                    lead_time_days INT DEFAULT 5,
                    min_order_qty INT DEFAULT 1,
                    supplier_sku VARCHAR(100),
                    is_preferred TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_part_supplier (part_no, supplier_id),
                    INDEX idx_part_no (part_no),
                    INDEX idx_supplier_id (supplier_id)
                )
            ");

            for ($i = 0; $i < count($part_nos); $i++) {
                $partNo = trim($part_nos[$i]);
                $rate = (float)($rates[$i] ?? 0);
                $minQty = (int)($min_qtys[$i] ?? 1);
                $leadDays = (int)($lead_days[$i] ?? 5);

                if (!$partNo || $rate <= 0) continue;

                // Check if mapping already exists
                $checkStmt = $pdo->prepare("
                    SELECT id FROM part_supplier_mapping
                    WHERE part_no = ? AND supplier_id = ?
                ");
                $checkStmt->execute([$partNo, $supplier_id]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    // Update existing
                    $updateStmt = $pdo->prepare("
                        UPDATE part_supplier_mapping
                        SET supplier_rate = ?, min_order_qty = ?, lead_time_days = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$rate, $minQty, $leadDays, $existing['id']]);
                    $updatedCount++;
                } else {
                    // Insert new
                    $insertStmt = $pdo->prepare("
                        INSERT INTO part_supplier_mapping (part_no, supplier_id, supplier_rate, min_order_qty, lead_time_days)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([$partNo, $supplier_id, $rate, $minQty, $leadDays]);
                    $addedCount++;
                }
            }

            $success = "Successfully saved! Added: $addedCount, Updated: $updatedCount";
        } catch (Exception $e) {
            $error = "Error saving: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier & Pricing Addition</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .form-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5em;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #495057;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #666;
        }

        /* Autocomplete styles */
        .autocomplete-wrapper {
            position: relative;
        }
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .autocomplete-results.show {
            display: block;
        }
        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .autocomplete-item:hover {
            background: #f0f4ff;
        }
        .autocomplete-item .item-main {
            font-weight: 600;
            color: #2c3e50;
        }
        .autocomplete-item .item-sub {
            font-size: 0.85em;
            color: #666;
        }

        /* Part info display */
        .part-info-box {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        .part-info-box.show {
            display: block;
        }
        .part-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        .part-info-item {
            background: white;
            padding: 8px 12px;
            border-radius: 5px;
        }
        .part-info-item label {
            font-size: 0.75em;
            color: #666;
            display: block;
            margin-bottom: 2px;
        }
        .part-info-item span {
            font-weight: 600;
            color: #2c3e50;
        }

        /* Entry table */
        .entry-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .entry-table th,
        .entry-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .entry-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .entry-table tr:hover {
            background: #f8f9fa;
        }
        .entry-table .remove-btn {
            color: #dc2626;
            cursor: pointer;
            font-size: 1.2em;
        }
        .entry-table .remove-btn:hover {
            color: #b91c1c;
        }

        .add-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
        }
        .add-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .save-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1em;
        }
        .save-btn:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }
        .save-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        /* Section header */
        .section-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #495057;
            margin: 20px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        body.dark .form-card {
            background: #2c3e50;
        }
        body.dark .form-header h1,
        body.dark .form-group label {
            color: #ecf0f1;
        }
        body.dark .autocomplete-results {
            background: #34495e;
            border-color: #4a5568;
        }
        body.dark .autocomplete-item:hover {
            background: #4a5568;
        }
        body.dark .part-info-box {
            background: #34495e;
            border-color: #4a5568;
        }
        body.dark .part-info-item {
            background: #2c3e50;
        }
        body.dark .entry-table th {
            background: #34495e;
            color: #ecf0f1;
        }

        /* Edit Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3em;
        }
        .modal-close {
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        .modal-close:hover {
            color: #333;
        }
        .modal-form .form-group {
            margin-bottom: 15px;
        }
        .modal-form .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .modal-form .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .modal-form .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        body.dark .modal-content {
            background: #2c3e50;
        }
        body.dark .modal-header h2 {
            color: #ecf0f1;
        }

        /* Badge styles */
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }
        .badge-secondary {
            background: #e9ecef;
            color: #6c757d;
        }

        .action-btn {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            margin-right: 5px;
        }
        .action-btn-edit {
            background: #667eea;
            color: white;
        }
        .action-btn-edit:hover {
            background: #5a67d8;
        }
        .action-btn-delete {
            background: #dc2626;
            color: white;
        }
        .action-btn-delete:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h1>Supplier & Pricing Addition</h1>
                <a href="index.php" class="btn btn-secondary">Back to Purchase</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Step 1: Select Supplier -->
            <div class="section-title">1. Select Supplier</div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 8px;">
                    <label>Supplier <span style="color: #dc2626;">*</span></label>
                    <!-- Simple dropdown as fallback -->
                    <select id="supplierDropdown" onchange="selectSupplierFromDropdown(this.value)" style="margin-bottom: 5px;">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?> (<?= htmlspecialchars($s['supplier_code'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <!-- OR use search box -->
                    <div class="autocomplete-wrapper">
                        <input type="text"
                               id="supplierSearch"
                               placeholder="Or type to search..."
                               autocomplete="off">
                        <div class="autocomplete-results" id="supplierResults"></div>
                    </div>
                    <input type="hidden" id="selectedSupplierId" name="supplier_id" value="">
                </div>
                <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 8px;">
                    <label>Code</label>
                    <input type="text" id="supplierCode" readonly placeholder="-">
                </div>
                <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 8px;">
                    <label>Contact</label>
                    <input type="text" id="supplierContact" readonly placeholder="-">
                </div>
                <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 8px;">
                    <label>Phone</label>
                    <input type="text" id="supplierPhone" readonly placeholder="-">
                </div>
                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 8px;">
                    <label>Email</label>
                    <input type="text" id="supplierEmail" readonly placeholder="-">
                </div>
                <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 8px;">
                    <label>GSTIN</label>
                    <input type="text" id="supplierGstin" readonly placeholder="-">
                </div>
                <div class="form-group" style="flex: 3; min-width: 250px; margin-bottom: 8px;">
                    <label>Address</label>
                    <input type="text" id="supplierAddress" readonly placeholder="-">
                </div>
            </div>

            <!-- Existing Supplier Pricing Section -->
            <div id="existingPricingSection" style="display: none; margin-top: 20px;">
                <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Existing Parts & Pricing</span>
                    <span id="existingCount" style="font-size: 0.85em; font-weight: normal; color: #666;"></span>
                </div>
                <div id="existingPricingContainer">
                    <div class="empty-state" id="noExistingPricing" style="display: none;">
                        <div class="icon">📋</div>
                        <p>No existing pricing found for this supplier</p>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="entry-table" id="existingPricingTable" style="display: none;">
                            <thead>
                                <tr>
                                    <th>Part No</th>
                                    <th>Part Name</th>
                                    <th>Part ID</th>
                                    <th>HSN</th>
                                    <th>Supplier Rate</th>
                                    <th>Min Qty</th>
                                    <th>Lead Days</th>
                                    <th>Preferred</th>
                                    <th>Status</th>
                                    <th style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="existingPricingBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Step 2: Select Parts -->
            <div class="section-title">2. Select Parts by ID</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Filter by Part ID</label>
                    <select id="partIdFilter">
                        <option value="">-- All Part IDs --</option>
                        <?php foreach ($partIds as $pid): ?>
                            <option value="<?= htmlspecialchars($pid) ?>"><?= htmlspecialchars($pid) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Select Part <span style="color: #dc2626;">*</span></label>
                    <!-- Simple dropdown as fallback -->
                    <select id="partDropdown" onchange="selectPartFromDropdown(this.value)" style="margin-bottom: 5px;">
                        <option value="">-- Select Part --</option>
                        <?php foreach ($parts as $p): ?>
                            <option value="<?= htmlspecialchars($p['part_no']) ?>"><?= htmlspecialchars($p['part_no']) ?> - <?= htmlspecialchars($p['part_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- OR use search box -->
                    <div class="autocomplete-wrapper">
                        <input type="text"
                               id="partSearch"
                               placeholder="Or type to search..."
                               autocomplete="off">
                        <div class="autocomplete-results" id="partResults"></div>
                    </div>
                </div>
            </div>

            <!-- Part Info Display -->
            <div class="part-info-box" id="partInfoBox">
                <div class="part-info-grid">
                    <div class="part-info-item">
                        <label>Part Number</label>
                        <span id="infoPartNo">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>Part Name</label>
                        <span id="infoPartName">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>Part ID</label>
                        <span id="infoPartId">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>HSN Code</label>
                        <span id="infoHsn">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>UOM</label>
                        <span id="infoUom">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>Base Rate</label>
                        <span id="infoRate">-</span>
                    </div>
                    <div class="part-info-item">
                        <label>GST %</label>
                        <span id="infoGst">-</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Add Pricing -->
            <div class="section-title">3. Add Pricing Details</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Supplier Rate <span style="color: #dc2626;">*</span></label>
                    <input type="number" id="inputRate" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Min Order Qty</label>
                    <input type="number" id="inputMinQty" min="1" value="1">
                </div>
                <div class="form-group">
                    <label>Lead Time (Days)</label>
                    <input type="number" id="inputLeadDays" min="1" value="5">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button" class="add-btn" onclick="addToTable()">
                        + Add to List
                    </button>
                </div>
            </div>
        </div>

        <!-- Added Parts Table -->
        <div class="form-card">
            <div class="section-title">4. Parts to Save</div>

            <form method="POST" id="saveForm">
                <input type="hidden" name="action" value="save_supplier_pricing">
                <input type="hidden" name="supplier_id" id="formSupplierId">

                <div id="tableContainer">
                    <div class="empty-state" id="emptyState">
                        <div class="icon">📦</div>
                        <p>No parts added yet. Select a part and click "Add to List"</p>
                    </div>

                    <table class="entry-table" id="entryTable" style="display: none;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Part ID</th>
                                <th>HSN</th>
                                <th>Rate</th>
                                <th>Min Qty</th>
                                <th>Lead Days</th>
                                <th style="width: 50px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="entryTableBody">
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="clearSelected()">Clear Selected</button>
                    <button type="submit" class="save-btn" id="saveBtn" disabled>Save All Pricing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Supplier Pricing</h2>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form class="modal-form" id="editMappingForm" onsubmit="saveMapping(event)">
            <input type="hidden" id="editMappingId" name="mapping_id">

            <div class="form-group">
                <label>Part Number</label>
                <input type="text" id="editPartNo" readonly>
            </div>
            <div class="form-group">
                <label>Part Name</label>
                <input type="text" id="editPartName" readonly>
            </div>
            <div class="form-group">
                <label>Supplier Rate <span style="color: #dc2626;">*</span></label>
                <input type="number" id="editRate" name="rate" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Min Order Qty</label>
                <input type="number" id="editMinQty" name="min_qty" min="1" value="1">
            </div>
            <div class="form-group">
                <label>Lead Time (Days)</label>
                <input type="number" id="editLeadDays" name="lead_days" min="1" value="5">
            </div>
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" id="editPreferred" name="is_preferred">
                    Preferred Supplier
                </label>
                <label>
                    <input type="checkbox" id="editActive" name="active" checked>
                    Active
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data from PHP
const suppliers = <?= json_encode($suppliers) ?>;
const allParts = <?= json_encode($parts) ?>;

// Debug: Log data counts
console.log('Suppliers loaded:', suppliers.length);
console.log('Parts loaded:', allParts.length);

let selectedSupplier = null;
let selectedPart = null;
let filteredParts = [...allParts];
let addedParts = [];

// Show data load status
document.addEventListener('DOMContentLoaded', function() {
    const supplierInput = document.getElementById('supplierSearch');
    const partInput = document.getElementById('partSearch');

    if (suppliers.length === 0) {
        supplierInput.placeholder = 'No suppliers found in database';
        supplierInput.style.borderColor = '#dc2626';
    } else {
        supplierInput.placeholder = `Type to search (${suppliers.length} suppliers available)`;
    }

    if (allParts.length === 0) {
        partInput.placeholder = 'No parts found in database';
        partInput.style.borderColor = '#dc2626';
    } else {
        partInput.placeholder = `Type part number or name (${allParts.length} parts available)`;
    }
});

// Supplier search
const supplierSearch = document.getElementById('supplierSearch');
const supplierResults = document.getElementById('supplierResults');

function showSupplierResults(query = '') {
    const searchQuery = (query || '').toLowerCase().trim();

    let filtered;
    if (searchQuery.length === 0) {
        // Show first 15 suppliers when no query
        filtered = suppliers.slice(0, 15);
    } else {
        filtered = suppliers.filter(s =>
            (s.supplier_name && s.supplier_name.toLowerCase().includes(searchQuery)) ||
            (s.supplier_code && s.supplier_code.toLowerCase().includes(searchQuery))
        ).slice(0, 15);
    }

    if (filtered.length === 0) {
        supplierResults.innerHTML = '<div class="autocomplete-item"><em>No suppliers found</em></div>';
    } else {
        supplierResults.innerHTML = filtered.map(s => `
            <div class="autocomplete-item" onclick="selectSupplier(${s.id})">
                <div class="item-main">${escapeHtml(s.supplier_name)}</div>
                <div class="item-sub">${escapeHtml(s.supplier_code || '')} | ${escapeHtml(s.contact_person || '-')}</div>
            </div>
        `).join('');
    }
    supplierResults.classList.add('show');
}

supplierSearch.addEventListener('input', function() {
    showSupplierResults(this.value);
});

supplierSearch.addEventListener('blur', () => setTimeout(() => supplierResults.classList.remove('show'), 200));
supplierSearch.addEventListener('focus', function() {
    // Show dropdown on focus even if empty
    showSupplierResults(this.value);
});

// Function for dropdown selection
function selectSupplierFromDropdown(id) {
    if (!id) return;
    selectSupplier(id);
}

function selectSupplier(id) {
    // Use == for loose comparison (id from JSON is string, from onclick is number)
    const supplier = suppliers.find(s => s.id == id);
    console.log('Selecting supplier with id:', id, 'Found:', supplier);
    if (supplier) {
        selectedSupplier = supplier;
        supplierSearch.value = supplier.supplier_name;
        document.getElementById('supplierDropdown').value = supplier.id;
        document.getElementById('selectedSupplierId').value = supplier.id;
        document.getElementById('formSupplierId').value = supplier.id;
        document.getElementById('supplierCode').value = supplier.supplier_code || '-';
        document.getElementById('supplierContact').value = supplier.contact_person || '-';

        // Populate additional fields
        document.getElementById('supplierPhone').value = supplier.phone || '-';
        document.getElementById('supplierEmail').value = supplier.email || '-';
        document.getElementById('supplierGstin').value = supplier.gstin || '-';

        // Build address string
        let addressParts = [];
        if (supplier.address1) addressParts.push(supplier.address1);
        if (supplier.address2) addressParts.push(supplier.address2);
        if (supplier.city) addressParts.push(supplier.city);
        if (supplier.state) addressParts.push(supplier.state);
        if (supplier.pincode) addressParts.push(supplier.pincode);
        document.getElementById('supplierAddress').value = addressParts.length > 0 ? addressParts.join(', ') : '-';

        // Load existing supplier mappings
        loadExistingMappings(supplier.id);
    } else {
        console.error('Supplier not found for id:', id);
    }
    supplierResults.classList.remove('show');
}

// Part ID filter
document.getElementById('partIdFilter').addEventListener('change', function() {
    const selectedId = this.value;
    if (selectedId) {
        filteredParts = allParts.filter(p => p.part_id === selectedId);
    } else {
        filteredParts = [...allParts];
    }

    // Update part dropdown with filtered parts
    const partDropdown = document.getElementById('partDropdown');
    partDropdown.innerHTML = '<option value="">-- Select Part --</option>';
    filteredParts.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.part_no;
        opt.textContent = p.part_no + ' - ' + p.part_name;
        partDropdown.appendChild(opt);
    });

    // Clear part selection
    document.getElementById('partSearch').value = '';
    selectedPart = null;
    document.getElementById('partInfoBox').classList.remove('show');
});

// Part search
const partSearch = document.getElementById('partSearch');
const partResults = document.getElementById('partResults');

function showPartResults(query = '') {
    const searchQuery = (query || '').toLowerCase().trim();

    let filtered;
    if (searchQuery.length === 0) {
        // Show first 20 parts when no query
        filtered = filteredParts.slice(0, 20);
    } else {
        filtered = filteredParts.filter(p =>
            (p.part_no && p.part_no.toLowerCase().includes(searchQuery)) ||
            (p.part_name && p.part_name.toLowerCase().includes(searchQuery))
        ).slice(0, 20);
    }

    if (filtered.length === 0) {
        partResults.innerHTML = '<div class="autocomplete-item"><em>No parts found</em></div>';
    } else {
        partResults.innerHTML = filtered.map(p => `
            <div class="autocomplete-item" onclick="selectPart('${escapeHtml(p.part_no)}')">
                <div class="item-main">${escapeHtml(p.part_no)} - ${escapeHtml(p.part_name)}</div>
                <div class="item-sub">ID: ${escapeHtml(p.part_id || '-')} | HSN: ${escapeHtml(p.hsn_code || '-')}</div>
            </div>
        `).join('');
    }
    partResults.classList.add('show');
}

partSearch.addEventListener('input', function() {
    showPartResults(this.value);
});

partSearch.addEventListener('blur', () => setTimeout(() => partResults.classList.remove('show'), 200));
partSearch.addEventListener('focus', function() {
    // Show dropdown on focus even if empty
    showPartResults(this.value);
});

// Function for part dropdown selection
function selectPartFromDropdown(partNo) {
    if (!partNo) return;
    selectPart(partNo);
}

function selectPart(partNo) {
    console.log('Selecting part:', partNo);
    const part = allParts.find(p => p.part_no === partNo);
    console.log('Found part:', part);
    if (part) {
        selectedPart = part;
        partSearch.value = part.part_no + ' - ' + part.part_name;
        document.getElementById('partDropdown').value = part.part_no;

        // Show part info
        document.getElementById('infoPartNo').textContent = part.part_no;
        document.getElementById('infoPartName').textContent = part.part_name;
        document.getElementById('infoPartId').textContent = part.part_id || '-';
        document.getElementById('infoHsn').textContent = part.hsn_code || '-';
        document.getElementById('infoUom').textContent = part.uom || '-';
        document.getElementById('infoRate').textContent = part.rate ? '₹' + parseFloat(part.rate).toFixed(2) : '-';
        document.getElementById('infoGst').textContent = part.gst ? part.gst + '%' : '-';
        document.getElementById('partInfoBox').classList.add('show');

        // Pre-fill rate if available
        if (part.rate && part.rate > 0) {
            document.getElementById('inputRate').value = parseFloat(part.rate).toFixed(2);
        }
    }
    partResults.classList.remove('show');
}

// Add to table
function addToTable() {
    if (!selectedSupplier) {
        alert('Please select a supplier first');
        return;
    }
    if (!selectedPart) {
        alert('Please select a part first');
        return;
    }

    const rate = parseFloat(document.getElementById('inputRate').value) || 0;
    if (rate <= 0) {
        alert('Please enter a valid rate');
        return;
    }

    const minQty = parseInt(document.getElementById('inputMinQty').value) || 1;
    const leadDays = parseInt(document.getElementById('inputLeadDays').value) || 5;

    // Check if already added
    if (addedParts.find(p => p.part_no === selectedPart.part_no)) {
        alert('This part is already in the list');
        return;
    }

    // Add to list
    addedParts.push({
        part_no: selectedPart.part_no,
        part_name: selectedPart.part_name,
        part_id: selectedPart.part_id || '-',
        hsn_code: selectedPart.hsn_code || '-',
        rate: rate,
        min_qty: minQty,
        lead_days: leadDays
    });

    renderTable();

    // Clear part selection for next entry
    selectedPart = null;
    partSearch.value = '';
    document.getElementById('partInfoBox').classList.remove('show');
    document.getElementById('inputRate').value = '';
    document.getElementById('inputMinQty').value = '1';
    document.getElementById('inputLeadDays').value = '5';
}

function renderTable() {
    const tbody = document.getElementById('entryTableBody');
    const table = document.getElementById('entryTable');
    const emptyState = document.getElementById('emptyState');
    const saveBtn = document.getElementById('saveBtn');

    if (addedParts.length === 0) {
        table.style.display = 'none';
        emptyState.style.display = 'block';
        saveBtn.disabled = true;
        return;
    }

    table.style.display = 'table';
    emptyState.style.display = 'none';
    saveBtn.disabled = false;

    tbody.innerHTML = addedParts.map((p, idx) => `
        <tr>
            <td><input type="checkbox" class="row-checkbox" data-index="${idx}"></td>
            <td>
                ${escapeHtml(p.part_no)}
                <input type="hidden" name="part_nos[]" value="${escapeHtml(p.part_no)}">
            </td>
            <td>${escapeHtml(p.part_name)}</td>
            <td>${escapeHtml(p.part_id)}</td>
            <td>${escapeHtml(p.hsn_code)}</td>
            <td>
                ₹${p.rate.toFixed(2)}
                <input type="hidden" name="rates[]" value="${p.rate}">
            </td>
            <td>
                ${p.min_qty}
                <input type="hidden" name="min_qtys[]" value="${p.min_qty}">
            </td>
            <td>
                ${p.lead_days}
                <input type="hidden" name="lead_days[]" value="${p.lead_days}">
            </td>
            <td>
                <span class="remove-btn" onclick="removePart(${idx})" title="Remove">✕</span>
            </td>
        </tr>
    `).join('');
}

function removePart(index) {
    addedParts.splice(index, 1);
    renderTable();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll').checked;
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = selectAll);
}

function clearSelected() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('No items selected');
        return;
    }

    // Get indices to remove (in reverse order to avoid index shifting)
    const indices = Array.from(checkboxes).map(cb => parseInt(cb.dataset.index)).sort((a, b) => b - a);
    indices.forEach(idx => addedParts.splice(idx, 1));

    document.getElementById('selectAll').checked = false;
    renderTable();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Form validation
document.getElementById('saveForm').addEventListener('submit', function(e) {
    if (addedParts.length === 0) {
        e.preventDefault();
        alert('Please add at least one part');
        return;
    }
    if (!selectedSupplier) {
        e.preventDefault();
        alert('Please select a supplier');
        return;
    }
});

// ========== EXISTING SUPPLIER PRICING FUNCTIONS ==========

let existingMappings = [];

// Load existing mappings when supplier is selected
function loadExistingMappings(supplierId) {
    if (!supplierId) {
        document.getElementById('existingPricingSection').style.display = 'none';
        return;
    }

    fetch(`?action=get_supplier_mappings&supplier_id=${supplierId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                existingMappings = data.mappings;
                renderExistingMappings();
            } else {
                console.error('Error loading mappings:', data.error);
            }
        })
        .catch(err => console.error('Fetch error:', err));
}

function renderExistingMappings() {
    const section = document.getElementById('existingPricingSection');
    const table = document.getElementById('existingPricingTable');
    const tbody = document.getElementById('existingPricingBody');
    const emptyState = document.getElementById('noExistingPricing');
    const countSpan = document.getElementById('existingCount');

    section.style.display = 'block';

    if (existingMappings.length === 0) {
        table.style.display = 'none';
        emptyState.style.display = 'block';
        countSpan.textContent = '';
        return;
    }

    table.style.display = 'table';
    emptyState.style.display = 'none';
    countSpan.textContent = `(${existingMappings.length} part${existingMappings.length > 1 ? 's' : ''})`;

    tbody.innerHTML = existingMappings.map(m => `
        <tr style="${m.active == 0 ? 'opacity: 0.6;' : ''}">
            <td>${escapeHtml(m.part_no)}</td>
            <td>${escapeHtml(m.part_name)}</td>
            <td>${escapeHtml(m.part_id || '-')}</td>
            <td>${escapeHtml(m.hsn_code || '-')}</td>
            <td style="font-weight: bold; color: #1e3a5f;">₹${parseFloat(m.supplier_rate).toFixed(2)}</td>
            <td>${m.min_order_qty || 1}</td>
            <td>${m.lead_time_days || 5}</td>
            <td>${m.is_preferred == 1 ? '<span class="badge badge-primary">★ Preferred</span>' : '-'}</td>
            <td>${m.active == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'}</td>
            <td>
                <button type="button" class="action-btn action-btn-edit" onclick="openEditModal(${m.id})">Edit</button>
                <button type="button" class="action-btn action-btn-delete" onclick="deleteMapping(${m.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

// Edit Modal functions
function openEditModal(mappingId) {
    const mapping = existingMappings.find(m => m.id == mappingId);
    if (!mapping) return;

    document.getElementById('editMappingId').value = mapping.id;
    document.getElementById('editPartNo').value = mapping.part_no;
    document.getElementById('editPartName').value = mapping.part_name;
    document.getElementById('editRate').value = parseFloat(mapping.supplier_rate).toFixed(2);
    document.getElementById('editMinQty').value = mapping.min_order_qty || 1;
    document.getElementById('editLeadDays').value = mapping.lead_time_days || 5;
    document.getElementById('editPreferred').checked = mapping.is_preferred == 1;
    document.getElementById('editActive').checked = mapping.active == 1;

    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function saveMapping(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'update_mapping');
    formData.append('mapping_id', document.getElementById('editMappingId').value);
    formData.append('rate', document.getElementById('editRate').value);
    formData.append('min_qty', document.getElementById('editMinQty').value);
    formData.append('lead_days', document.getElementById('editLeadDays').value);
    if (document.getElementById('editPreferred').checked) {
        formData.append('is_preferred', '1');
    }
    if (document.getElementById('editActive').checked) {
        formData.append('active', '1');
    }

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            // Reload mappings
            if (selectedSupplier) {
                loadExistingMappings(selectedSupplier.id);
            }
            alert('Pricing updated successfully!');
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('Error saving changes');
    });
}

function deleteMapping(mappingId) {
    if (!confirm('Are you sure you want to delete this supplier pricing?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_mapping');
    formData.append('mapping_id', mappingId);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload mappings
            if (selectedSupplier) {
                loadExistingMappings(selectedSupplier.id);
            }
            alert('Pricing deleted successfully!');
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('Error deleting pricing');
    });
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

</body>
</html>
