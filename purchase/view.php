<?php
include "../db.php";
include "../includes/sidebar.php";

$po_no = $_GET['po_no'] ?? null;
if (!$po_no) {
    die("Invalid Purchase Order");
}

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
            <td><?= htmlspecialchars($i['status']) ?></td>
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

    <br>
    <a href="index.php" class="btn btn-secondary no-print">Back to Purchase Orders</a>
</div>

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
