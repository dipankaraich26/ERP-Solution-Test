<?php
/**
 * Fix script to update lead status to "converted" for leads with released invoices
 *
 * This script finds leads where:
 * - Invoice has been released (status = 'released')
 * - But lead status is NOT 'converted'
 *
 * And updates them to 'converted' status.
 * Run this once to fix existing data.
 */
include "../db.php";

$messages = [];
$errors = [];
$fixedLeads = [];

try {
    // Find all leads with released invoices that are NOT in 'converted' status
    $findStmt = $pdo->query("
        SELECT DISTINCT
            l.id as lead_id,
            l.lead_no,
            l.lead_status,
            l.company_name,
            l.contact_person,
            im.invoice_no,
            im.invoice_date,
            im.released_at
        FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN sales_orders so ON so.linked_quote_id = q.id
        JOIN invoice_master im ON im.so_no = so.so_no
        WHERE im.status = 'released'
          AND LOWER(l.lead_status) != 'converted'
        ORDER BY l.lead_no
    ");

    $leadsToFix = $findStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leadsToFix)) {
        $messages[] = "No leads need fixing. All leads with released invoices are already 'converted'.";
    } else {
        // Update each lead
        $updateStmt = $pdo->prepare("UPDATE crm_leads SET lead_status = 'converted' WHERE id = ?");

        foreach ($leadsToFix as $lead) {
            $updateStmt->execute([$lead['lead_id']]);
            $fixedLeads[] = $lead;
        }

        $messages[] = "Successfully updated " . count($fixedLeads) . " lead(s) to 'Converted' status.";
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Lead Status for Released Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
        .info { background: #cce5ff; border: 1px solid #b8daff; padding: 15px; border-radius: 5px; margin: 10px 0; color: #004085; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .status-old { background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 3px; }
        .status-new { background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px; }
        a { color: #007bff; }
    </style>
</head>
<body>
    <h1>Fix Lead Status for Released Invoices</h1>

    <div class="info">
        <strong>Purpose:</strong> This script finds leads where an invoice has been released
        but the lead status is not 'Converted', and updates them to 'Converted' status.
        <br><br>
        <strong>Business Rule:</strong> When an invoice is released, the associated lead
        should automatically be marked as 'Converted'.
    </div>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <div class="error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($fixedLeads)): ?>
    <h3>Leads Updated:</h3>
    <table>
        <thead>
            <tr>
                <th>Lead No</th>
                <th>Company / Contact</th>
                <th>Previous Status</th>
                <th>New Status</th>
                <th>Invoice No</th>
                <th>Released At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fixedLeads as $lead): ?>
            <tr>
                <td>
                    <a href="/crm/view.php?id=<?= $lead['lead_id'] ?>" target="_blank">
                        <?= htmlspecialchars($lead['lead_no']) ?>
                    </a>
                </td>
                <td>
                    <?= htmlspecialchars($lead['company_name'] ?: $lead['contact_person']) ?>
                </td>
                <td>
                    <span class="status-old"><?= ucfirst(htmlspecialchars($lead['lead_status'])) ?></span>
                </td>
                <td>
                    <span class="status-new">Converted</span>
                </td>
                <td>
                    <a href="/invoices/view.php?id=<?= $lead['lead_id'] ?>" target="_blank">
                        <?= htmlspecialchars($lead['invoice_no']) ?>
                    </a>
                </td>
                <td><?= $lead['released_at'] ? date('Y-m-d H:i', strtotime($lead['released_at'])) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="/crm/index.php">Go to CRM Leads</a> |
        <a href="/invoices/index.php">Go to Invoices</a>
    </p>
</body>
</html>
