<?php
include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

// Get current user info
$currentUserId = getUserId();
$currentUserRole = getUserRole();
$isAdmin = ($currentUserRole === 'admin');

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

// Access control: Any logged-in user can view leads
// Note: assigned_user_id is from employees table, not users table
// For stricter control, link users to employees via employee_id column in users table

// Check if status is locked (converted with released invoice OR hot with released PI)
$statusLocked = false;
$lockReason = '';
$lockDocNo = null;

// Scenario 1: Converted lead with released invoice
if (strtolower($lead['lead_status']) === 'converted') {
    try {
        $invoiceCheckStmt = $pdo->prepare("
            SELECT im.invoice_no
            FROM invoice_master im
            JOIN sales_orders so ON so.so_no = im.so_no
            JOIN quote_master q ON q.id = so.linked_quote_id
            WHERE q.reference = ? AND im.status = 'released'
            LIMIT 1
        ");
        $invoiceCheckStmt->execute([$lead['lead_no']]);
        $releasedInvoice = $invoiceCheckStmt->fetch(PDO::FETCH_ASSOC);
        if ($releasedInvoice) {
            $statusLocked = true;
            $lockReason = 'invoice';
            $lockDocNo = $releasedInvoice['invoice_no'];
        }
    } catch (Exception $e) {
        // Silently continue if query fails
    }
}

// Scenario 2: Hot lead with released PI
if (strtolower($lead['lead_status']) === 'hot') {
    try {
        $piCheckStmt = $pdo->prepare("
            SELECT pi_no
            FROM quote_master
            WHERE reference = ? AND status = 'released'
            LIMIT 1
        ");
        $piCheckStmt->execute([$lead['lead_no']]);
        $releasedPI = $piCheckStmt->fetch(PDO::FETCH_ASSOC);
        if ($releasedPI) {
            $statusLocked = true;
            $lockReason = 'pi';
            $lockDocNo = $releasedPI['pi_no'];
        }
    } catch (Exception $e) {
        // Silently continue if query fails
    }
}

// Fetch requirements
$reqStmt = $pdo->prepare("SELECT * FROM crm_lead_requirements WHERE lead_id = ? ORDER BY priority DESC, id");
$reqStmt->execute([$id]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch YID parts for product requirements dropdown
// Match parts where part_no OR part_id starts with 'YID'
$yidParts = [];
try {
    $yidParts = $pdo->query("
        SELECT part_no, part_name, part_id, description, hsn_code, uom, rate
        FROM part_master
        WHERE status = 'active'
          AND (UPPER(part_no) LIKE 'YID%' OR UPPER(part_id) LIKE 'YID%')
        ORDER BY part_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist
}

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

    // Note: Status updates are now automatic through the workflow:
    // Cold → Warm (Quotation creation) → Hot (PI release) → Converted (Invoice release)
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

        /* Searchable Part Dropdown Styles */
        .part-search-container { position: relative; }
        .part-search {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .part-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .part-option {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .part-option:hover { background: #e8f4fc; }
        .part-option.hidden { display: none; }
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
    <div style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
        <a href="index.php" class="btn btn-secondary">Back to Leads</a>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">Edit Lead</a>
        <a href="print.php?id=<?= $id ?>" class="btn btn-success" target="_blank">Print / PDF</a>
    </div>

    <!-- Status Workflow Info -->
    <div style="margin-bottom: 25px; padding: 15px 20px; background: #e8f4fd; border-radius: 8px; border: 1px solid #b8daff;">
        <strong style="display: block; margin-bottom: 8px; color: #004085;">Status Workflow (Automatic):</strong>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 0.9em;">
            <span style="padding: 5px 12px; background: #bdc3c7; border-radius: 4px;">Cold</span>
            <span style="color: #666;">→ Quotation →</span>
            <span style="padding: 5px 12px; background: #f39c12; color: #fff; border-radius: 4px;">Warm</span>
            <span style="color: #666;">→ PI Release →</span>
            <span style="padding: 5px 12px; background: #e74c3c; color: #fff; border-radius: 4px;">Hot</span>
            <span style="color: #666;">→ Invoice →</span>
            <span style="padding: 5px 12px; background: #27ae60; color: #fff; border-radius: 4px;">Converted</span>
        </div>
        <p style="margin: 10px 0 0; font-size: 0.85em; color: #666;">
            Current Status: <strong class="lead-status status-<?= strtolower($lead['lead_status']) ?>"><?= ucfirst($lead['lead_status']) ?></strong>
            <?php if (strtolower($lead['lead_status']) === 'converted'): ?>
                — Lead successfully converted!
            <?php elseif (strtolower($lead['lead_status']) === 'hot'): ?>
                — Release an Invoice to convert this lead
            <?php elseif (strtolower($lead['lead_status']) === 'warm'): ?>
                — Release a PI to move to Hot status
            <?php elseif (strtolower($lead['lead_status']) === 'cold'): ?>
                — Create a Quotation to move to Warm status
            <?php endif; ?>
        </p>
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
                <span class="info-label">Market</span>
                <span class="info-value"><?= htmlspecialchars($lead['market_classification'] ?? '-') ?></span>
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
                <div class="form-group part-search-container">
                    <label>Part No (YID Parts)</label>
                    <input type="text" id="reqPartSearch" class="part-search" placeholder="Search YID parts..." autocomplete="off"
                           onfocus="showReqPartDropdown()" oninput="filterReqParts()">
                    <input type="hidden" name="part_no" id="reqPartNoHidden">
                    <div id="reqPartDropdown" class="part-dropdown" style="display:none;">
                        <?php foreach ($yidParts as $p): ?>
                            <div class="part-option"
                                 data-part-no="<?= htmlspecialchars($p['part_no']) ?>"
                                 data-name="<?= htmlspecialchars($p['part_name']) ?>"
                                 data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                 data-uom="<?= htmlspecialchars($p['uom'] ?? '') ?>"
                                 data-rate="<?= $p['rate'] ?? 0 ?>"
                                 onclick="selectReqPart(this)">
                                <?= htmlspecialchars($p['part_no']) ?> - <?= htmlspecialchars($p['part_name']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($yidParts)): ?>
                            <div style="padding: 10px; color: #666;">No YID parts available</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" id="reqProductName" required>
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
                    <input type="text" name="unit" id="reqUnit" placeholder="pcs, kg, etc.">
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

// =============================
// Searchable Part Dropdown Functions
// =============================

function showReqPartDropdown() {
    document.getElementById('reqPartDropdown').style.display = 'block';
}

function filterReqParts() {
    const search = document.getElementById('reqPartSearch').value.toLowerCase();
    const dropdown = document.getElementById('reqPartDropdown');
    const options = dropdown.querySelectorAll('.part-option');

    dropdown.style.display = 'block';

    options.forEach(option => {
        const text = option.textContent.toLowerCase();
        if (text.includes(search)) {
            option.classList.remove('hidden');
        } else {
            option.classList.add('hidden');
        }
    });
}

function selectReqPart(option) {
    const partNo = option.getAttribute('data-part-no');
    const name = option.getAttribute('data-name');
    const description = option.getAttribute('data-description');
    const uom = option.getAttribute('data-uom');
    const rate = option.getAttribute('data-rate');

    // Set the search input to show selected part
    document.getElementById('reqPartSearch').value = partNo + ' - ' + name;

    // Set the hidden field with the actual part_no
    document.getElementById('reqPartNoHidden').value = partNo;

    // Auto-populate product name
    document.getElementById('reqProductName').value = name;

    // Auto-populate description if available
    const descField = document.querySelector('textarea[name="req_description"]');
    if (descField && description) {
        descField.value = description;
    }

    // Auto-populate unit if available
    if (uom) {
        document.getElementById('reqUnit').value = uom;
    }

    // Hide dropdown
    document.getElementById('reqPartDropdown').style.display = 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.querySelector('.part-search-container');
    if (container && !container.contains(e.target)) {
        document.getElementById('reqPartDropdown').style.display = 'none';
    }
});
</script>

</body>
</html>
