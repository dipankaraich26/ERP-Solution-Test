<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoice_master WHERE id = ? AND status = 'draft'");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    setModal("Error", "Invoice not found or already released");
    header("Location: index.php");
    exit;
}

// ===========================================
// GET LINKED LEAD - Will auto-convert when invoice is released
// Chain: Invoice → Sales Order → Quote/PI → Lead
// ===========================================
$leadCheckStmt = $pdo->prepare("
    SELECT l.id as lead_id, l.lead_no, l.lead_status, l.company_name
    FROM sales_orders so
    LEFT JOIN quote_master q ON q.id = so.linked_quote_id
    LEFT JOIN crm_leads l ON l.lead_no = q.reference
    WHERE so.so_no = ?
    LIMIT 1
");
$leadCheckStmt->execute([$invoice['so_no']]);
$linkedLead = $leadCheckStmt->fetch(PDO::FETCH_ASSOC);

// ===========================================
// CHECK LEAD STATUS - Must be "hot" or "converted" to release invoice
// Lead will be auto-converted to "converted" when invoice is released
// ===========================================
$leadStatusForRelease = strtolower($linkedLead['lead_status'] ?? '');
if ($linkedLead && $linkedLead['lead_id']) {
    if (!in_array($leadStatusForRelease, ['hot', 'converted'])) {
        setModal("Cannot Release Invoice", "Lead status must be 'HOT' or 'Converted' to release an invoice. Current lead status: '" . ucfirst($linkedLead['lead_status']) . "'. Please ensure the workflow is followed (PI must be released first).");
        header("Location: view.php?id=$id");
        exit;
    }
}

// ===========================================
// CHECK E-WAY BILL - Required only if invoice value >= 50,000
// ===========================================
// Get the invoice total from linked PI
$totalStmt = $pdo->prepare("
    SELECT COALESCE(SUM(qi.total_amount), 0) as grand_total
    FROM sales_orders so
    JOIN quote_master q ON q.id = so.linked_quote_id
    JOIN quote_items qi ON qi.quote_id = q.id
    WHERE so.so_no = ?
");
$totalStmt->execute([$invoice['so_no']]);
$invoiceTotal = (float)$totalStmt->fetchColumn();

// E-Way Bill is mandatory only if invoice >= 50,000
if ($invoiceTotal >= 50000) {
    if (empty($invoice['eway_bill_no'])) {
        setModal("Cannot Release Invoice", "E-Way Bill Number is required for invoices ≥ ₹50,000 (Current: ₹" . number_format($invoiceTotal, 2) . "). Please add the E-Way Bill details first.");
        header("Location: view.php?id=$id");
        exit;
    }

    if (empty($invoice['eway_bill_attachment'])) {
        setModal("Cannot Release Invoice", "E-Way Bill Attachment is required for invoices ≥ ₹50,000 (Current: ₹" . number_format($invoiceTotal, 2) . "). Please upload the E-Way Bill document.");
        header("Location: view.php?id=$id");
        exit;
    }

    // Verify the E-Way Bill file exists
    if (!file_exists('../' . $invoice['eway_bill_attachment'])) {
        setModal("Cannot Release Invoice", "E-Way Bill file not found on server. Please re-upload the document.");
        header("Location: view.php?id=$id");
        exit;
    }
}

// Begin transaction for atomic operation
// NOTE: Inventory is already deducted when the Sales Order is released.
// Invoice release only updates status — no further stock deduction.
$pdo->beginTransaction();
try {
    // Update invoice status and released_at timestamp
    $releaseStmt = $pdo->prepare("
        UPDATE invoice_master
        SET status = 'released', released_at = NOW()
        WHERE id = ?
    ");
    $releaseStmt->execute([$id]);

    // Also update sales order status to closed if it exists
    $soUpdateStmt = $pdo->prepare("
        UPDATE sales_orders
        SET status = 'closed'
        WHERE so_no = ?
    ");
    $soUpdateStmt->execute([$invoice['so_no']]);

    // AUTO-UPDATE: When Invoice is released, update lead status to CONVERTED
    $leadConverted = false;
    if ($linkedLead && $linkedLead['lead_id'] && $leadStatusForRelease === 'hot') {
        $leadConvertStmt = $pdo->prepare("
            UPDATE crm_leads
            SET lead_status = 'converted'
            WHERE id = ? AND LOWER(lead_status) = 'hot'
        ");
        $leadConvertStmt->execute([$linkedLead['lead_id']]);
        if ($leadConvertStmt->rowCount() > 0) {
            $leadConverted = true;
        }
    }

    $pdo->commit();

    // Prepare success message
    $message = "Invoice " . $invoice['invoice_no'] . " released successfully.";
    if ($leadConverted) {
        $message .= "\n\nLead " . $linkedLead['lead_no'] . " has been automatically converted!";
    }

    setModal("Success", $message);
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    setModal("Error", "Failed to release invoice: " . $e->getMessage());
    header("Location: index.php");
    exit;
}
