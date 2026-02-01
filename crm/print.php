<?php
include "../db.php";
include "../includes/auth.php";

// Get current user info
$currentUserId = getUserId();
$currentUserRole = getUserRole();
$isAdmin = ($currentUserRole === 'admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("Invalid lead ID");
}

// Fetch lead
$stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("Lead not found");
}

// Access control: Non-admin users can only view leads assigned to them
if (!$isAdmin && $lead['assigned_user_id'] != $currentUserId) {
    die("Access denied");
}

// Fetch requirements
$reqStmt = $pdo->prepare("SELECT * FROM crm_lead_requirements WHERE lead_id = ? ORDER BY priority DESC, id");
$reqStmt->execute([$id]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch interactions
$intStmt = $pdo->prepare("SELECT * FROM crm_lead_interactions WHERE lead_id = ? ORDER BY interaction_date DESC");
$intStmt->execute([$id]);
$interactions = $intStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total potential value
$totalPotential = 0;
foreach ($requirements as $req) {
    if ($req['our_price'] && $req['estimated_qty']) {
        $totalPotential += $req['our_price'] * $req['estimated_qty'];
    }
}

// Fetch company settings for header
$companySettings = [];
try {
    $companySettings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    // Table may not exist
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lead: <?= htmlspecialchars($lead['lead_no']) ?> - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
            max-width: 210mm;
            margin: 0 auto;
        }

        /* Header */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .company-info p {
            font-size: 11px;
            color: #666;
        }
        .lead-info-header {
            text-align: right;
        }
        .lead-info-header .lead-no {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .lead-info-header .lead-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            margin-top: 5px;
        }
        .status-hot { background: #e74c3c; color: #fff; }
        .status-warm { background: #f39c12; color: #fff; }
        .status-cold { background: #bdc3c7; color: #2c3e50; }
        .status-converted { background: #27ae60; color: #fff; }
        .status-lost { background: #7f8c8d; color: #fff; }

        .type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            margin-left: 8px;
        }
        .type-b2b { background: #3498db; color: #fff; }
        .type-b2c { background: #9b59b6; color: #fff; }

        /* Section */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #2c3e50;
            color: #fff;
            padding: 8px 15px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-box {
            border: 1px solid #ddd;
            padding: 12px;
            background: #fafafa;
        }
        .info-box h4 {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            padding: 4px 0;
            font-size: 11px;
        }
        .info-label {
            width: 120px;
            color: #666;
            font-weight: 500;
        }
        .info-value {
            flex: 1;
            color: #2c3e50;
        }

        /* Potential Value */
        .potential-value {
            background: #27ae60;
            color: #fff;
            padding: 12px 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .potential-value .amount {
            font-size: 20px;
            font-weight: bold;
        }
        .potential-value .label {
            font-size: 11px;
            opacity: 0.9;
        }

        /* Requirements Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table th {
            background: #34495e;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .priority-high { color: #e74c3c; font-weight: bold; }
        .priority-medium { color: #f39c12; }
        .priority-low { color: #95a5a6; }

        /* Interactions */
        .interaction-item {
            border: 1px solid #ddd;
            padding: 12px;
            margin-bottom: 10px;
            background: #fafafa;
            page-break-inside: avoid;
        }
        .interaction-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .interaction-type {
            background: #ecf0f1;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .interaction-date {
            color: #7f8c8d;
            font-size: 11px;
        }
        .interaction-content p {
            margin: 5px 0;
            font-size: 11px;
        }
        .interaction-content strong {
            color: #2c3e50;
        }

        /* Notes Section */
        .notes-box {
            background: #fffde7;
            border: 1px solid #fff59d;
            padding: 12px;
            font-size: 11px;
            white-space: pre-wrap;
        }

        /* Footer */
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        /* Print Controls */
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .print-controls button {
            padding: 10px 20px;
            margin-left: 10px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-print {
            background: #3498db;
            color: #fff;
        }
        .btn-back {
            background: #95a5a6;
            color: #fff;
        }

        @media print {
            .print-controls { display: none; }
            body { padding: 0; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<!-- Print Controls -->
<div class="print-controls">
    <button class="btn-back" onclick="window.location.href='view.php?id=<?= $id ?>'">Back</button>
    <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<!-- Header -->
<div class="print-header">
    <div class="company-info">
        <h1><?= htmlspecialchars($companySettings['company_name'] ?? 'Company Name') ?></h1>
        <?php if (!empty($companySettings['address'])): ?>
            <p><?= htmlspecialchars($companySettings['address']) ?></p>
        <?php endif; ?>
        <?php if (!empty($companySettings['phone']) || !empty($companySettings['email'])): ?>
            <p>
                <?= !empty($companySettings['phone']) ? 'Ph: ' . htmlspecialchars($companySettings['phone']) : '' ?>
                <?= !empty($companySettings['email']) ? ' | ' . htmlspecialchars($companySettings['email']) : '' ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="lead-info-header">
        <div class="lead-no">
            <?= htmlspecialchars($lead['lead_no']) ?>
            <span class="type-badge type-<?= strtolower($lead['customer_type']) ?>">
                <?= $lead['customer_type'] ?>
            </span>
        </div>
        <div>
            <span class="lead-status status-<?= $lead['lead_status'] ?>">
                <?= ucfirst($lead['lead_status']) ?>
            </span>
        </div>
        <div style="margin-top: 5px; font-size: 11px; color: #666;">
            Generated: <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</div>

<!-- Lead Name -->
<div style="margin-bottom: 20px;">
    <h2 style="font-size: 18px; color: #2c3e50;">
        <?= htmlspecialchars($lead['company_name'] ?: $lead['contact_person']) ?>
    </h2>
    <?php if ($lead['company_name'] && $lead['contact_person']): ?>
        <p style="color: #666;"><?= htmlspecialchars($lead['contact_person']) ?>
            <?= $lead['designation'] ? ' - ' . htmlspecialchars($lead['designation']) : '' ?>
        </p>
    <?php endif; ?>
</div>

<!-- Potential Value -->
<?php if ($totalPotential > 0): ?>
<div class="potential-value">
    <div class="label">Total Potential Value</div>
    <div class="amount"><?= number_format($totalPotential, 2) ?></div>
</div>
<?php endif; ?>

<!-- Lead Information Grid -->
<div class="section">
    <div class="section-title">Lead Information</div>
    <div class="info-grid">
        <!-- Contact Information -->
        <div class="info-box">
            <h4>Contact Information</h4>
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
                <span class="info-value"><?= htmlspecialchars($lead['website']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Business Details -->
        <div class="info-box">
            <h4>Business Details</h4>
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
        <div class="info-box">
            <h4>Buying Intent</h4>
            <div class="info-row">
                <span class="info-label">Timeline</span>
                <span class="info-value"><strong><?= ucfirst(str_replace('_', ' ', $lead['buying_timeline'])) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Budget Range</span>
                <span class="info-value"><?= htmlspecialchars($lead['budget_range'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Next Follow-up</span>
                <span class="info-value"><?= $lead['next_followup_date'] ? date('d M Y', strtotime($lead['next_followup_date'])) : '-' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Contact</span>
                <span class="info-value"><?= $lead['last_contact_date'] ? date('d M Y', strtotime($lead['last_contact_date'])) : '-' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Assigned To</span>
                <span class="info-value"><?= htmlspecialchars($lead['assigned_to'] ?? '-') ?></span>
            </div>
        </div>

        <!-- Address -->
        <div class="info-box">
            <h4>Address</h4>
            <div class="info-value" style="padding: 5px 0;">
                <?php if ($lead['address1'] || $lead['city']): ?>
                    <?= htmlspecialchars($lead['address1'] ?? '') ?><br>
                    <?= $lead['address2'] ? htmlspecialchars($lead['address2']) . '<br>' : '' ?>
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
</div>

<!-- Notes -->
<?php if ($lead['notes']): ?>
<div class="section">
    <div class="section-title">Notes</div>
    <div class="notes-box"><?= htmlspecialchars($lead['notes']) ?></div>
</div>
<?php endif; ?>

<!-- Product Requirements -->
<div class="section">
    <div class="section-title">Product Requirements (<?= count($requirements) ?>)</div>
    <?php if (empty($requirements)): ?>
        <p style="color: #999; padding: 15px; text-align: center; background: #f9f9f9;">No product requirements added.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Part No</th>
                    <th>Qty</th>
                    <th>Target Price</th>
                    <th>Our Price</th>
                    <th>Required By</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $req): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($req['product_name']) ?></strong>
                        <?php if ($req['description']): ?>
                            <br><small style="color: #666;"><?= htmlspecialchars($req['description']) ?></small>
                        <?php endif; ?>
                        <?php if ($req['notes']): ?>
                            <br><em style="color: #888; font-size: 10px;"><?= htmlspecialchars($req['notes']) ?></em>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($req['part_no'] ?? '-') ?></td>
                    <td><?= $req['estimated_qty'] ? number_format($req['estimated_qty'], 2) . ' ' . ($req['unit'] ?? '') : '-' ?></td>
                    <td><?= $req['target_price'] ? number_format($req['target_price'], 2) : '-' ?></td>
                    <td><?= $req['our_price'] ? number_format($req['our_price'], 2) : '-' ?></td>
                    <td><?= $req['required_by'] ? date('d M Y', strtotime($req['required_by'])) : '-' ?></td>
                    <td class="priority-<?= $req['priority'] ?>"><?= ucfirst($req['priority']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Interaction History -->
<div class="section">
    <div class="section-title">Interaction History (<?= count($interactions) ?>)</div>
    <?php if (empty($interactions)): ?>
        <p style="color: #999; padding: 15px; text-align: center; background: #f9f9f9;">No interactions logged.</p>
    <?php else: ?>
        <?php foreach ($interactions as $int): ?>
        <div class="interaction-item">
            <div class="interaction-header">
                <div>
                    <span class="interaction-type"><?= ucfirst(str_replace('_', ' ', $int['interaction_type'])) ?></span>
                    <?php if ($int['subject']): ?>
                        <strong style="margin-left: 10px;"><?= htmlspecialchars($int['subject']) ?></strong>
                    <?php endif; ?>
                </div>
                <span class="interaction-date"><?= date('d M Y, h:i A', strtotime($int['interaction_date'])) ?></span>
            </div>
            <div class="interaction-content">
                <?php if ($int['description']): ?>
                    <p><?= nl2br(htmlspecialchars($int['description'])) ?></p>
                <?php endif; ?>
                <?php if ($int['outcome']): ?>
                    <p><strong>Outcome:</strong> <?= htmlspecialchars($int['outcome']) ?></p>
                <?php endif; ?>
                <?php if ($int['next_action']): ?>
                    <p style="color: #3498db;">
                        <strong>Next Action:</strong> <?= htmlspecialchars($int['next_action']) ?>
                        <?= $int['next_action_date'] ? ' (by ' . date('d M Y', strtotime($int['next_action_date'])) . ')' : '' ?>
                    </p>
                <?php endif; ?>
                <?php if ($int['handled_by']): ?>
                    <p style="color: #999;"><strong>Handled by:</strong> <?= htmlspecialchars($int['handled_by']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="print-footer">
    <p>Lead Report: <?= htmlspecialchars($lead['lead_no']) ?> | Generated on <?= date('d M Y, h:i A') ?></p>
    <p>This is a system-generated document.</p>
</div>

</body>
</html>
