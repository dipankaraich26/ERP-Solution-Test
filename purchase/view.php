<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
include "../includes/dialog.php";

$isAdmin = getUserRole() === 'admin';
$po_no = $_GET['po_no'] ?? null;
if (!$po_no) {
    die("Invalid Purchase Order");
}

/* --- Handle Receipt Details Update (Admin only) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_receipt') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $invoiceNo = trim($_POST['invoice_no'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($entryId > 0) {
            try {
                $pdo->prepare("UPDATE stock_entries SET invoice_no = ?, remarks = ? WHERE id = ?")
                     ->execute([$invoiceNo, $remarks, $entryId]);
                setModal('Updated', 'Receipt details updated successfully.');
            } catch (Exception $e) {
                setModal('Error', 'Failed to update: ' . $e->getMessage());
            }
            header("Location: view.php?po_no=" . urlencode($po_no));
            exit;
        }
    }

    if ($action === 'add_receipt_note') {
        $invoiceNo = trim($_POST['invoice_no'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($invoiceNo || $remarks) {
            // Get any PO line ID for this PO
            $firstLine = $pdo->prepare("SELECT id, part_no FROM purchase_orders WHERE po_no = ? LIMIT 1");
            $firstLine->execute([$po_no]);
            $line = $firstLine->fetch(PDO::FETCH_ASSOC);

            if ($line) {
                try {
                    $pdo->prepare("INSERT INTO stock_entries (po_id, part_no, received_qty, invoice_no, remarks, status) VALUES (?, ?, 0, ?, ?, 'posted')")
                         ->execute([$line['id'], $line['part_no'], $invoiceNo, $remarks]);
                    setModal('Added', 'Receipt note added successfully.');
                } catch (Exception $e) {
                    setModal('Error', 'Failed to add note: ' . $e->getMessage());
                }
            }
            header("Location: view.php?po_no=" . urlencode($po_no));
            exit;
        }
    }
}

include "../includes/sidebar.php";

/* --- Fetch Purchase Order Details --- */
$stmt = $pdo->prepare("
    SELECT
        po.po_no,
        po.purchase_date,
        s.supplier_name,
        s.contact_person,
        s.phone,
        s.email,
        s.address1,
        s.address2,
        s.city,
        s.pincode,
        s.state,
        s.gstin
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.po_no = ?
    LIMIT 1
");
$stmt->execute([$po_no]);
$po = $stmt->fetch();

if (!$po) {
    die("Purchase Order not found");
}

/* --- Fetch PO Line Items with HSN, Rate, GST --- */
/* Use po.rate (supplier rate stored on PO) with fallback to p.rate (base rate) */
$itemsStmt = $pdo->prepare("
    SELECT po.id, po.part_no, p.part_name, po.qty, po.status,
           COALESCE(inv.qty, 0) AS current_stock,
           p.hsn_code,
           CASE WHEN COALESCE(po.rate, 0) > 0 THEN po.rate ELSE p.rate END AS rate,
           p.gst
    FROM purchase_orders po
    JOIN part_master p ON p.part_no = po.part_no
    LEFT JOIN inventory inv ON inv.part_no = po.part_no
    WHERE po.po_no = ?
    ORDER BY po.id
");
$itemsStmt->execute([$po_no]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch stock receipt entries for this PO
$receiptStmt = $pdo->prepare("
    SELECT se.id, se.po_id, se.part_no, pm.part_name, se.received_qty, se.invoice_no, se.remarks, se.status, se.received_date
    FROM stock_entries se
    JOIN purchase_orders po ON se.po_id = po.id
    LEFT JOIN part_master pm ON pm.part_no = se.part_no
    WHERE po.po_no = ?
    ORDER BY se.received_date DESC, se.id DESC
");
$receiptStmt->execute([$po_no]);
$receipts = $receiptStmt->fetchAll(PDO::FETCH_ASSOC);

// Determine overall PO status
$poStatuses = array_unique(array_column($items, 'status'));
$allClosed = count($poStatuses) === 1 && $poStatuses[0] === 'closed';
$allCancelled = count($poStatuses) === 1 && $poStatuses[0] === 'cancelled';
$hasOpen = in_array('open', $poStatuses);
$hasPartial = in_array('partial', $poStatuses);

// Calculate totals for each item and grand totals
$grandTaxable = 0;
$grandCGST = 0;
$grandSGST = 0;
$grandTotal = 0;

foreach ($items as &$item) {
    $qty = (float)$item['qty'];
    $rate = (float)($item['rate'] ?? 0);
    $gst = (float)($item['gst'] ?? 0);

    $taxable = $qty * $rate;
    $cgstRate = $gst / 2;
    $sgstRate = $gst / 2;
    $cgstAmt = $taxable * ($cgstRate / 100);
    $sgstAmt = $taxable * ($sgstRate / 100);
    $total = $taxable + $cgstAmt + $sgstAmt;

    $item['taxable'] = $taxable;
    $item['cgst_rate'] = $cgstRate;
    $item['cgst_amt'] = $cgstAmt;
    $item['sgst_rate'] = $sgstRate;
    $item['sgst_amt'] = $sgstAmt;
    $item['total'] = $total;

    $grandTaxable += $taxable;
    $grandCGST += $cgstAmt;
    $grandSGST += $sgstAmt;
    $grandTotal += $total;
}
unset($item); // Break reference
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Purchase Order</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                padding: 10px !important;
                width: 100% !important;
            }
            body {
                background: white !important;
                color: black !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            table {
                border: 1px solid #000 !important;
                page-break-inside: avoid;
                width: 100% !important;
                font-size: 10px !important;
                border-collapse: collapse;
            }
            table th, table td {
                padding: 4px 6px !important;
                border: 1px solid #000 !important;
            }
            table th {
                background: #f0f0f0 !important;
                color: #000 !important;
            }
            .hide-print {
                display: none !important;
            }
            h1 {
                font-size: 18px !important;
            }
            h3 {
                font-size: 14px !important;
            }
            p {
                font-size: 11px !important;
                margin: 4px 0 !important;
            }
        }
        .text-right {
            text-align: right;
        }
        .totals-row {
            font-weight: bold;
            background: #f9f9f9;
        }
    </style>
</head>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "Dark Mode";
        }
    });
}
</script>

<body>
<?php showModal(); ?>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">Purchase Order <?= htmlspecialchars($po['po_no']) ?></h1>
        <div class="no-print" style="display: flex; gap: 10px;">
            <a href="edit_po.php?po_no=<?= urlencode($po['po_no']) ?>" class="btn btn-secondary">Edit PO</a>
            <button onclick="window.print()" class="btn btn-primary">Print</button>
            <button onclick="shareToWhatsApp()" class="btn btn-success">Share via WhatsApp</button>
        </div>
    </div>

    <h3>Supplier Details</h3>
    <p><strong>Supplier:</strong> <?= htmlspecialchars($po['supplier_name']) ?></p>
    <?php if (!empty($po['contact_person'])): ?>
    <p><strong>Contact Person:</strong> <?= htmlspecialchars($po['contact_person']) ?></p>
    <?php endif; ?>
    <?php if (!empty($po['phone'])): ?>
    <p><strong>Phone:</strong> <?= htmlspecialchars($po['phone']) ?></p>
    <?php endif; ?>
    <?php if (!empty($po['email'])): ?>
    <p><strong>Email:</strong> <?= htmlspecialchars($po['email']) ?></p>
    <?php endif; ?>
    <?php
    $address_parts = array_filter([
        $po['address1'] ?? '',
        $po['address2'] ?? '',
        $po['city'] ?? '',
        $po['pincode'] ?? '',
        $po['state'] ?? ''
    ]);
    if (!empty($address_parts)):
    ?>
    <p><strong>Address:</strong> <?= htmlspecialchars(implode(', ', $address_parts)) ?></p>
    <?php endif; ?>
    <?php if (!empty($po['gstin'])): ?>
    <p><strong>GSTIN:</strong> <?= htmlspecialchars($po['gstin']) ?></p>
    <?php endif; ?>

    <hr>

    <p><strong>Purchase Date:</strong> <?= htmlspecialchars($po['purchase_date']) ?></p>

    <h3>Order Items</h3>

    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <tr>
            <th>Part No</th>
            <th>Part Name</th>
            <th>HSN Code</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Taxable Amt</th>
            <th>CGST %</th>
            <th>CGST Amt</th>
            <th>SGST %</th>
            <th>SGST Amt</th>
            <th>Total</th>
            <th class="hide-print">Current Stock</th>
            <th>Status</th>
        </tr>

        <?php foreach ($items as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i['part_no']) ?></td>
            <td><?= htmlspecialchars($i['part_name']) ?></td>
            <td><?= htmlspecialchars($i['hsn_code'] ?? '') ?></td>
            <td class="text-right"><?= htmlspecialchars($i['qty']) ?></td>
            <td class="text-right"><?= number_format($i['rate'] ?? 0, 2) ?></td>
            <td class="text-right"><?= number_format($i['taxable'], 2) ?></td>
            <td class="text-right"><?= number_format($i['cgst_rate'], 2) ?>%</td>
            <td class="text-right"><?= number_format($i['cgst_amt'], 2) ?></td>
            <td class="text-right"><?= number_format($i['sgst_rate'], 2) ?>%</td>
            <td class="text-right"><?= number_format($i['sgst_amt'], 2) ?></td>
            <td class="text-right"><?= number_format($i['total'], 2) ?></td>
            <td class="text-right hide-print"><?= $i['current_stock'] ?></td>
            <td>
                <?php
                $st = $i['status'];
                $stBg = match($st) {
                    'closed' => '#10b981',
                    'partial' => '#f59e0b',
                    'cancelled' => '#dc2626',
                    'open' => '#3b82f6',
                    default => '#6b7280'
                };
                ?>
                <span style="display: inline-block; padding: 2px 8px; background: <?= $stBg ?>; color: white; border-radius: 10px; font-size: 0.85em;">
                    <?= htmlspecialchars(ucfirst($st)) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>

        <!-- Grand Totals Row -->
        <tr class="totals-row">
            <td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
            <td class="text-right"><?= number_format($grandTaxable, 2) ?></td>
            <td></td>
            <td class="text-right"><?= number_format($grandCGST, 2) ?></td>
            <td></td>
            <td class="text-right"><?= number_format($grandSGST, 2) ?></td>
            <td class="text-right"><?= number_format($grandTotal, 2) ?></td>
            <td class="hide-print"></td>
            <td></td>
        </tr>
    </table>
    </div>

    <!-- Stock Receipt Details -->
    <div class="no-print" style="margin-top: 30px;">
        <h3 style="border-bottom: 2px solid #10b981; padding-bottom: 8px; color: #065f46;">
            Stock Receipt Details
            <?php if ($allClosed): ?>
                <span style="display: inline-block; padding: 3px 12px; background: #10b981; color: white; border-radius: 12px; font-size: 0.7em; vertical-align: middle; margin-left: 10px;">All Received</span>
            <?php elseif ($hasPartial): ?>
                <span style="display: inline-block; padding: 3px 12px; background: #f59e0b; color: white; border-radius: 12px; font-size: 0.7em; vertical-align: middle; margin-left: 10px;">Partially Received</span>
            <?php elseif ($allCancelled): ?>
                <span style="display: inline-block; padding: 3px 12px; background: #dc2626; color: white; border-radius: 12px; font-size: 0.7em; vertical-align: middle; margin-left: 10px;">Cancelled</span>
            <?php elseif ($hasOpen): ?>
                <span style="display: inline-block; padding: 3px 12px; background: #3b82f6; color: white; border-radius: 12px; font-size: 0.7em; vertical-align: middle; margin-left: 10px;">Awaiting Receipt</span>
            <?php endif; ?>
        </h3>

        <?php if (!empty($receipts)): ?>
        <div style="overflow-x: auto;">
        <table border="1" cellpadding="8" style="margin-top: 10px;">
            <tr>
                <th>#</th>
                <th>Part No</th>
                <th>Part Name</th>
                <th>Received Qty</th>
                <th>Invoice No</th>
                <th>Received Date</th>
                <th>Remarks</th>
                <?php if ($isAdmin): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($receipts as $ri => $r): ?>
            <tr id="receipt-row-<?= $r['id'] ?>">
                <td><?= $ri + 1 ?></td>
                <td><?= htmlspecialchars($r['part_no']) ?></td>
                <td><?= htmlspecialchars($r['part_name'] ?? '-') ?></td>
                <td class="text-right"><?= (float)$r['received_qty'] ?></td>
                <td>
                    <span id="inv-display-<?= $r['id'] ?>"><?= htmlspecialchars($r['invoice_no'] ?: '-') ?></span>
                </td>
                <td><?= $r['received_date'] ? date('d M Y', strtotime($r['received_date'])) : '-' ?></td>
                <td>
                    <span id="rem-display-<?= $r['id'] ?>"><?= htmlspecialchars($r['remarks'] ?: '-') ?></span>
                </td>
                <?php if ($isAdmin): ?>
                <td style="white-space: nowrap;">
                    <button type="button" class="btn btn-sm" style="background: #6366f1; color: white; padding: 4px 10px; font-size: 0.85em;"
                            onclick="editReceipt(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['invoice_no'] ?? '')) ?>, <?= htmlspecialchars(json_encode($r['remarks'] ?? '')) ?>)">
                        Edit
                    </button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <?php else: ?>
            <p style="color: #6b7280; padding: 15px 0;">No stock receipts recorded for this PO.</p>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <!-- Add Receipt Note (for missing invoice/remarks) -->
        <div style="margin-top: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px;">
            <h4 style="margin: 0 0 10px 0; color: #065f46;">Add Receipt Note</h4>
            <form method="post" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <input type="hidden" name="action" value="add_receipt_note">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.85em; font-weight: bold; margin-bottom: 4px; color: #374151;">Invoice No</label>
                    <input type="text" name="invoice_no" placeholder="e.g. INV-2026-001" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                <div style="flex: 2; min-width: 250px;">
                    <label style="display: block; font-size: 0.85em; font-weight: bold; margin-bottom: 4px; color: #374151;">Remarks</label>
                    <input type="text" name="remarks" placeholder="e.g. Received in good condition, GRN-123" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                <button type="submit" class="btn btn-success" style="padding: 8px 20px;">Add Note</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <br>
    <div class="no-print" style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-secondary">Back to Purchase Orders</a>
        <?php if ($hasOpen || $hasPartial): ?>
            <a href="../stock_entry/receive_all.php?po_no=<?= urlencode($po_no) ?>" class="btn btn-success">Receive Stock</a>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Receipt Modal -->
<?php if ($isAdmin): ?>
<div id="editReceiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 25px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h3 style="margin: 0 0 20px 0; color: #1f2937;">Edit Receipt Details</h3>
        <form method="post" id="editReceiptForm">
            <input type="hidden" name="action" value="update_receipt">
            <input type="hidden" name="entry_id" id="editEntryId">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #374151;">Invoice No</label>
                <input type="text" name="invoice_no" id="editInvoiceNo" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1em;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #374151;">Remarks</label>
                <textarea name="remarks" id="editRemarks" rows="3" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1em; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="padding: 8px 20px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editReceipt(entryId, invoiceNo, remarks) {
    document.getElementById('editEntryId').value = entryId;
    document.getElementById('editInvoiceNo').value = invoiceNo || '';
    document.getElementById('editRemarks').value = remarks || '';
    document.getElementById('editReceiptModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editReceiptModal').style.display = 'none';
}

document.getElementById('editReceiptModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
<?php endif; ?>

<script>
function shareToWhatsApp() {
    const poNo = <?= json_encode($po['po_no']) ?>;
    const supplier = <?= json_encode($po['supplier_name']) ?>;
    const contactPerson = <?= json_encode($po['contact_person'] ?? '') ?>;
    const phone = <?= json_encode($po['phone'] ?? '') ?>;
    const email = <?= json_encode($po['email'] ?? '') ?>;
    const purchaseDate = <?= json_encode($po['purchase_date']) ?>;

    // Get items and totals
    const items = <?= json_encode($items) ?>;
    const grandTaxable = <?= json_encode($grandTaxable) ?>;
    const grandCGST = <?= json_encode($grandCGST) ?>;
    const grandSGST = <?= json_encode($grandSGST) ?>;
    const grandTotal = <?= json_encode($grandTotal) ?>;

    // Build WhatsApp message
    let message = `*Purchase Order: ${poNo}*\n\n`;
    message += `*Supplier Details:*\n`;
    message += `Company: ${supplier}\n`;
    if (contactPerson) message += `Contact: ${contactPerson}\n`;
    if (phone) message += `Phone: ${phone}\n`;
    if (email) message += `Email: ${email}\n`;
    message += `\n*Purchase Date:* ${purchaseDate}\n`;

    message += `\n*Order Items:*\n`;
    message += `------------------------\n`;

    items.forEach((item, index) => {
        message += `${index + 1}. ${item.part_no} - ${item.part_name}\n`;
        message += `   HSN: ${item.hsn_code || 'N/A'}\n`;
        message += `   Qty: ${item.qty} x Rate: ${parseFloat(item.rate || 0).toFixed(2)}\n`;
        message += `   Taxable: ${parseFloat(item.taxable).toFixed(2)}\n`;
        message += `   CGST (${parseFloat(item.cgst_rate).toFixed(2)}%): ${parseFloat(item.cgst_amt).toFixed(2)}\n`;
        message += `   SGST (${parseFloat(item.sgst_rate).toFixed(2)}%): ${parseFloat(item.sgst_amt).toFixed(2)}\n`;
        message += `   *Total: ${parseFloat(item.total).toFixed(2)}*\n\n`;
    });

    message += `------------------------\n`;
    message += `*GRAND TOTALS:*\n`;
    message += `Taxable: ${parseFloat(grandTaxable).toFixed(2)}\n`;
    message += `CGST: ${parseFloat(grandCGST).toFixed(2)}\n`;
    message += `SGST: ${parseFloat(grandSGST).toFixed(2)}\n`;
    message += `*TOTAL: ${parseFloat(grandTotal).toFixed(2)}*\n`;

    message += `\n_Generated from ERP System_`;

    // Encode message for URL
    const encodedMessage = encodeURIComponent(message);

    // Open WhatsApp with the message
    const whatsappURL = `https://wa.me/?text=${encodedMessage}`;
    window.open(whatsappURL, '_blank');
}
</script>

</body>
</html>
