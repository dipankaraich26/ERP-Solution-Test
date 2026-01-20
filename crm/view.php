<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch lead
$stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    header("Location: index.php");
    exit;
}

// Fetch requirements
$reqStmt = $pdo->prepare("SELECT * FROM crm_lead_requirements WHERE lead_id = ? ORDER BY priority DESC, id");
$reqStmt->execute([$id]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch interactions
$intStmt = $pdo->prepare("SELECT * FROM crm_lead_interactions WHERE lead_id = ? ORDER BY interaction_date DESC");
$intStmt->execute([$id]);
$interactions = $intStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE ADD REQUIREMENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_requirement') {
        $stmt = $pdo->prepare("
            INSERT INTO crm_lead_requirements
            (lead_id, part_no, product_name, description, estimated_qty, unit, target_price, our_price, required_by, priority, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $_POST['part_no'] ?: null,
            $_POST['product_name'],
            $_POST['req_description'] ?: null,
            $_POST['estimated_qty'] ?: null,
            $_POST['unit'] ?: null,
            $_POST['target_price'] ?: null,
            $_POST['our_price'] ?: null,
            $_POST['required_by'] ?: null,
            $_POST['priority'] ?? 'medium',
            $_POST['req_notes'] ?: null
        ]);
        setModal("Success", "Requirement added");
        header("Location: view.php?id=$id#requirements");
        exit;
    }

    if ($_POST['action'] === 'add_interaction') {
        $stmt = $pdo->prepare("
            INSERT INTO crm_lead_interactions
            (lead_id, interaction_type, interaction_date, subject, description, outcome, next_action, next_action_date, handled_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id,
            $_POST['interaction_type'],
            $_POST['interaction_date'],
            $_POST['subject'] ?: null,
            $_POST['int_description'] ?: null,
            $_POST['outcome'] ?: null,
            $_POST['next_action'] ?: null,
            $_POST['next_action_date'] ?: null,
            $_POST['handled_by'] ?: null
        ]);

        // Update last contact date
        $pdo->prepare("UPDATE crm_leads SET last_contact_date = ? WHERE id = ?")
            ->execute([date('Y-m-d', strtotime($_POST['interaction_date'])), $id]);

        setModal("Success", "Interaction logged");
        header("Location: view.php?id=$id#interactions");
        exit;
    }

    if ($_POST['action'] === 'delete_requirement' && isset($_POST['req_id'])) {
        $pdo->prepare("DELETE FROM crm_lead_requirements WHERE id = ? AND lead_id = ?")
            ->execute([$_POST['req_id'], $id]);
        setModal("Success", "Requirement deleted");
        header("Location: view.php?id=$id#requirements");
        exit;
    }

    if ($_POST['action'] === 'update_status') {
        $new_status = $_POST['new_status'];

        // Update lead status
        $pdo->prepare("UPDATE crm_leads SET lead_status = ? WHERE id = ?")
            ->execute([$new_status, $id]);

        $message = "Status updated to " . ucfirst($new_status);

        // If status changed to HOT, convert to customer
        if ($new_status === 'hot') {
            // Check if already converted (by phone number in customers table)
            $checkStmt = $pdo->prepare("SELECT id, customer_id FROM customers WHERE contact = ?");
            $checkStmt->execute([$lead['phone']]);
            $existingCustomer = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingCustomer) {
                // Already exists - link to existing customer
                $pdo->prepare("UPDATE crm_leads SET converted_customer_id = ? WHERE id = ?")
                    ->execute([$existingCustomer['id'], $id]);
                $message .= "\n\nNote: Customer already exists in database as " . $existingCustomer['customer_id'];
            } else {
                // Create new customer
                $maxCustNo = $pdo->query("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(customer_id, 6) AS UNSIGNED)), 0)
                    FROM customers WHERE customer_id LIKE 'CUST-%'
                ")->fetchColumn();
                $new_customer_id = 'CUST-' . ($maxCustNo + 1);

                $custStmt = $pdo->prepare("
                    INSERT INTO customers
                    (customer_id, company_name, customer_name, contact, address1, address2, city, pincode, state, gstin, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $custStmt->execute([
                    $new_customer_id,
                    $lead['company_name'] ?: '',
                    $lead['contact_person'],
                    $lead['phone'],
                    $lead['address1'] ?: null,
                    $lead['address2'] ?: null,
                    $lead['city'] ?: null,
                    $lead['pincode'] ?: null,
                    $lead['state'] ?: null,
                    '', // gstin
                    'active' // status
                ]);

                $newCustId = $pdo->lastInsertId();

                // Link lead to the new customer
                $pdo->prepare("UPDATE crm_leads SET converted_customer_id = ? WHERE id = ?")
                    ->execute([$newCustId, $id]);

                $message .= "\n\nLead converted to Customer: " . $new_customer_id;
            }
        }

        setModal("Success", $message);
        header("Location: view.php?id=$id");
        exit;
    }
}

// Calculate total potential value
$totalPotential = 0;
foreach ($requirements as $req) {
    if ($req['our_price'] && $req['estimated_qty']) {
        $totalPotential += $req['our_price'] * $req['estimated_qty'];
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lead: <?= htmlspecialchars($lead['lead_no']) ?> - CRM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .lead-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .lead-title h1 { margin: 0 0 5px 0; }
        .lead-title .lead-no { color: #7f8c8d; font-size: 0.9em; }

        .lead-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-hot { background: #e74c3c; color: #fff; }
        .status-warm { background: #f39c12; color: #fff; }
        .status-cold { background: #bdc3c7; color: #2c3e50; }
        .status-converted { background: #27ae60; color: #fff; }
        .status-lost { background: #7f8c8d; color: #fff; }

        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        .type-b2b { background: #3498db; color: #fff; }
        .type-b2c { background: #9b59b6; color: #fff; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        .info-card h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 140px; color: #7f8c8d; font-weight: 500; }
        .info-value { flex: 1; color: #2c3e50; }

        .tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            font-weight: 500;
        }
        .tab:hover { background: #f8f9fa; }
        .tab.active {
            border-bottom-color: #3498db;
            color: #3498db;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-header h3 { margin: 0; }

        .req-card {
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid #3498db;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .req-card.priority-high { border-left-color: #e74c3c; }
        .req-card.priority-medium { border-left-color: #f39c12; }
        .req-card.priority-low { border-left-color: #95a5a6; }
        .req-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .req-product { font-weight: bold; font-size: 1.1em; }
        .req-details { display: flex; flex-wrap: wrap; gap: 20px; color: #666; font-size: 0.9em; }

        .int-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .int-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .int-type {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            background: #ecf0f1;
        }
        .int-date { color: #7f8c8d; }

        .modal-form {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-form.active { display: flex; }
        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-content h3 { margin-top: 0; }
        .modal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .modal-grid .full-width { grid-column: 1 / -1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .quick-status {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quick-status button {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
        }
        .quick-status .btn-cold { background: #bdc3c7; }
        .quick-status .btn-warm { background: #f39c12; color: #fff; }
        .quick-status .btn-hot { background: #e74c3c; color: #fff; }
        .quick-status .btn-converted { background: #27ae60; color: #fff; }
        .quick-status .btn-lost { background: #7f8c8d; color: #fff; }

        .potential-value {
            background: #27ae60;
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .potential-value .amount { font-size: 1.8em; font-weight: bold; }
        .potential-value .label { opacity: 0.9; }
    </style>
</head>
<body>

<div class="content">

    <!-- Lead Header -->
    <div class="lead-header">
        <div class="lead-title">
            <h1>
                <?= htmlspecialchars($lead['company_name'] ?: $lead['contact_person']) ?>
                <span class="type-badge type-<?= strtolower($lead['customer_type']) ?>">
                    <?= $lead['customer_type'] ?>
                </span>
            </h1>
            <div class="lead-no"><?= htmlspecialchars($lead['lead_no']) ?></div>
        </div>
        <div>
            <span class="lead-status status-<?= $lead['lead_status'] ?>">
                <?= ucfirst($lead['lead_status']) ?>
            </span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn btn-secondary">Back to Leads</a>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit Lead</a>
    </div>

    <!-- Quick Status Change -->
    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <strong style="margin-right: 15px;">Quick Status:</strong>
        <form method="post" style="display: inline;" class="quick-status">
            <input type="hidden" name="action" value="update_status">
            <?php foreach (['cold', 'warm', 'hot', 'converted', 'lost'] as $s): ?>
                <button type="submit" name="new_status" value="<?= $s ?>"
                        class="btn-<?= $s ?>"
                        <?= $lead['lead_status'] === $s ? 'disabled style="opacity:0.5"' : '' ?>>
                    <?= ucfirst($s) ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <!-- Potential Value -->
    <?php if ($totalPotential > 0): ?>
    <div class="potential-value">
        <div class="label">Total Potential Value</div>
        <div class="amount"><?= number_format($totalPotential, 2) ?></div>
    </div>
    <?php endif; ?>

    <!-- Info Grid -->
    <div class="info-grid">
        <!-- Contact Info -->
        <div class="info-card">
            <h3>Contact Information</h3>
            <div class="info-row">
                <span class="info-label">Contact Person</span>
                <span class="info-value"><?= htmlspecialchars($lead['contact_person']) ?></span>
            </div>
            <?php if ($lead['designation']): ?>
            <div class="info-row">
                <span class="info-label">Designation</span>
                <span class="info-value"><?= htmlspecialchars($lead['designation']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($lead['phone'] ?? '-') ?></span>
            </div>
            <?php if ($lead['alt_phone'] ?? null): ?>
            <div class="info-row">
                <span class="info-label">Alt Phone</span>
                <span class="info-value"><?= htmlspecialchars($lead['alt_phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($lead['email'] ?? '-') ?></span>
            </div>
            <?php if ($lead['website'] ?? null): ?>
            <div class="info-row">
                <span class="info-label">Website</span>
                <span class="info-value">
                    <a href="<?= htmlspecialchars($lead['website']) ?>" target="_blank">
                        <?= htmlspecialchars($lead['website']) ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Business Info -->
        <div class="info-card">
            <h3>Business Details</h3>
            <div class="info-row">
                <span class="info-label">Industry</span>
                <span class="info-value"><?= htmlspecialchars($lead['industry'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Annual Revenue</span>
                <span class="info-value"><?= htmlspecialchars($lead['annual_revenue'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Employees</span>
                <span class="info-value"><?= htmlspecialchars($lead['employee_count'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Lead Source</span>
                <span class="info-value"><?= htmlspecialchars($lead['lead_source'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Decision Maker</span>
                <span class="info-value"><?= ucfirst($lead['decision_maker']) ?></span>
            </div>
        </div>

        <!-- Buying Intent -->
        <div class="info-card">
            <h3>Buying Intent</h3>
            <div class="info-row">
                <span class="info-label">Timeline</span>
                <span class="info-value">
                    <strong><?= ucfirst(str_replace('_', ' ', $lead['buying_timeline'])) ?></strong>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Budget Range</span>
                <span class="info-value"><?= htmlspecialchars($lead['budget_range'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Next Follow-up</span>
                <span class="info-value">
                    <?= $lead['next_followup_date'] ? date('d M Y', strtotime($lead['next_followup_date'])) : '-' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Contact</span>
                <span class="info-value">
                    <?= $lead['last_contact_date'] ? date('d M Y', strtotime($lead['last_contact_date'])) : '-' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Assigned To</span>
                <span class="info-value"><?= htmlspecialchars($lead['assigned_to'] ?? '-') ?></span>
            </div>
        </div>

        <!-- Address -->
        <div class="info-card">
            <h3>Address</h3>
            <div class="info-value">
                <?php if ($lead['address1'] || $lead['city']): ?>
                    <?= htmlspecialchars($lead['address1'] ?? '') ?><br>
                    <?= htmlspecialchars($lead['address2'] ?? '') ?>
                    <?= $lead['address2'] ? '<br>' : '' ?>
                    <?= htmlspecialchars($lead['city'] ?? '') ?>
                    <?= $lead['pincode'] ? ' - ' . htmlspecialchars($lead['pincode']) : '' ?><br>
                    <?= htmlspecialchars($lead['state'] ?? '') ?>
                    <?= $lead['country'] ? ', ' . htmlspecialchars($lead['country']) : '' ?>
                <?php else: ?>
                    <span style="color: #999;">No address provided</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($lead['notes']): ?>
    <div class="info-card" style="margin-bottom: 25px;">
        <h3>Notes</h3>
        <p style="white-space: pre-wrap;"><?= htmlspecialchars($lead['notes']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="showTab('requirements')">
            Product Requirements (<?= count($requirements) ?>)
        </div>
        <div class="tab" onclick="showTab('interactions')">
            Interactions (<?= count($interactions) ?>)
        </div>
    </div>

    <!-- Requirements Tab -->
    <div id="requirements" class="tab-content active">
        <div class="section-header">
            <h3>Product Requirements</h3>
            <button class="btn btn-primary" onclick="openModal('reqModal')">+ Add Requirement</button>
        </div>

        <?php if (empty($requirements)): ?>
            <p style="color: #999; text-align: center; padding: 30px;">
                No product requirements added yet.
            </p>
        <?php else: ?>
            <?php foreach ($requirements as $req): ?>
            <div class="req-card priority-<?= $req['priority'] ?>">
                <div class="req-header">
                    <div class="req-product">
                        <?= htmlspecialchars($req['product_name']) ?>
                        <?php if ($req['part_no']): ?>
                            <span style="color: #666; font-weight: normal;">
                                (<?= htmlspecialchars($req['part_no']) ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_requirement">
                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                        <button type="submit" class="btn btn-secondary"
                                onclick="return confirm('Delete this requirement?')"
                                style="padding: 4px 10px; font-size: 0.85em;">
                            Delete
                        </button>
                    </form>
                </div>
                <?php if ($req['description']): ?>
                    <p style="margin: 0 0 10px 0; color: #666;"><?= htmlspecialchars($req['description']) ?></p>
                <?php endif; ?>
                <div class="req-details">
                    <span><strong>Qty:</strong> <?= $req['estimated_qty'] ? number_format($req['estimated_qty'], 2) . ' ' . ($req['unit'] ?? '') : '-' ?></span>
                    <span><strong>Target Price:</strong> <?= $req['target_price'] ? number_format($req['target_price'], 2) : '-' ?></span>
                    <span><strong>Our Price:</strong> <?= $req['our_price'] ? number_format($req['our_price'], 2) : '-' ?></span>
                    <span><strong>Required By:</strong> <?= $req['required_by'] ? date('d M Y', strtotime($req['required_by'])) : '-' ?></span>
                    <span><strong>Priority:</strong> <?= ucfirst($req['priority']) ?></span>
                </div>
                <?php if ($req['notes']): ?>
                    <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #666;">
                        <em><?= htmlspecialchars($req['notes']) ?></em>
                    </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Interactions Tab -->
    <div id="interactions" class="tab-content">
        <div class="section-header">
            <h3>Interaction History</h3>
            <button class="btn btn-primary" onclick="openModal('intModal')">+ Log Interaction</button>
        </div>

        <?php if (empty($interactions)): ?>
            <p style="color: #999; text-align: center; padding: 30px;">
                No interactions logged yet.
            </p>
        <?php else: ?>
            <?php foreach ($interactions as $int): ?>
            <div class="int-card">
                <div class="int-header">
                    <div>
                        <span class="int-type"><?= ucfirst(str_replace('_', ' ', $int['interaction_type'])) ?></span>
                        <?php if ($int['subject']): ?>
                            <strong style="margin-left: 10px;"><?= htmlspecialchars($int['subject']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <span class="int-date">
                        <?= date('d M Y, h:i A', strtotime($int['interaction_date'])) ?>
                    </span>
                </div>
                <?php if ($int['description']): ?>
                    <p style="margin: 10px 0; white-space: pre-wrap;"><?= htmlspecialchars($int['description']) ?></p>
                <?php endif; ?>
                <?php if ($int['outcome']): ?>
                    <p style="margin: 5px 0;"><strong>Outcome:</strong> <?= htmlspecialchars($int['outcome']) ?></p>
                <?php endif; ?>
                <?php if ($int['next_action']): ?>
                    <p style="margin: 5px 0; color: #3498db;">
                        <strong>Next Action:</strong> <?= htmlspecialchars($int['next_action']) ?>
                        <?= $int['next_action_date'] ? ' (by ' . date('d M Y', strtotime($int['next_action_date'])) . ')' : '' ?>
                    </p>
                <?php endif; ?>
                <?php if ($int['handled_by']): ?>
                    <p style="margin: 5px 0; color: #999; font-size: 0.9em;">
                        Handled by: <?= htmlspecialchars($int['handled_by']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Add Requirement Modal -->
<div id="reqModal" class="modal-form">
    <div class="modal-content">
        <h3>Add Product Requirement</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_requirement">
            <div class="modal-grid">
                <div class="form-group">
                    <label>Part No</label>
                    <input type="text" name="part_no" placeholder="If known">
                </div>
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required>
                </div>
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="req_description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Estimated Qty</label>
                    <input type="number" name="estimated_qty" step="0.001">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="unit" placeholder="pcs, kg, etc.">
                </div>
                <div class="form-group">
                    <label>Target Price (Customer's)</label>
                    <input type="number" name="target_price" step="0.01">
                </div>
                <div class="form-group">
                    <label>Our Price</label>
                    <input type="number" name="our_price" step="0.01">
                </div>
                <div class="form-group">
                    <label>Required By</label>
                    <input type="date" name="required_by">
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="req_notes" rows="2"></textarea>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="submit" class="btn btn-success">Add Requirement</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('reqModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Interaction Modal -->
<div id="intModal" class="modal-form">
    <div class="modal-content">
        <h3>Log Interaction</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_interaction">
            <div class="modal-grid">
                <div class="form-group">
                    <label>Interaction Type *</label>
                    <select name="interaction_type" required>
                        <option value="call">Phone Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                        <option value="site_visit">Site Visit</option>
                        <option value="demo">Demo</option>
                        <option value="quotation_sent">Quotation Sent</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date & Time *</label>
                    <input type="datetime-local" name="interaction_date" required
                           value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="form-group full-width">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="Brief subject line">
                </div>
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="int_description" rows="3" placeholder="Details of the interaction..."></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Outcome</label>
                    <input type="text" name="outcome" placeholder="What was the result?">
                </div>
                <div class="form-group">
                    <label>Next Action</label>
                    <input type="text" name="next_action" placeholder="What's next?">
                </div>
                <div class="form-group">
                    <label>Next Action Date</label>
                    <input type="date" name="next_action_date">
                </div>
                <div class="form-group full-width">
                    <label>Handled By</label>
                    <input type="text" name="handled_by" placeholder="Your name">
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button type="submit" class="btn btn-success">Log Interaction</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('intModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on outside click
document.querySelectorAll('.modal-form').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// Handle hash navigation for tabs
if (window.location.hash === '#interactions') {
    showTab('interactions');
    document.querySelector('.tab:nth-child(2)').classList.add('active');
    document.querySelector('.tab:nth-child(1)').classList.remove('active');
}
</script>

</body>
</html>
