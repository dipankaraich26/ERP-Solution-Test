<?php
include "../db.php";
include "../includes/sidebar.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid Work Order ID");
}

/* --- Fetch Work Order Header --- */
$woStmt = $pdo->prepare("
    SELECT w.wo_no, w.qty, w.status,
           p.part_name,
           b.id AS bom_id, b.bom_no, b.description
    FROM work_orders w
    JOIN part_master p ON w.part_no = p.part_no
    JOIN bom_master b ON w.bom_id = b.id
    WHERE w.id = ?
");
$woStmt->execute([$id]);
$wo = $woStmt->fetch();

if (!$wo) {
    die("Work Order not found");
}

/* --- Fetch BOM Items for this WO --- */
$itemsStmt = $pdo->prepare("
    SELECT i.qty, p.part_name, p.part_no, COALESCE(inv.qty, 0) AS current_stock
    FROM bom_items i
    JOIN part_master p ON i.component_part_no = p.part_no
    LEFT JOIN inventory inv ON inv.part_no = p.part_no
    WHERE i.bom_id = ?
");
$itemsStmt->execute([$wo['bom_id']]);
$bomItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Work Order</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            table {
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }
            table th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            table td {
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;

if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "☀️ Light Mode";
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");

        if (body.classList.contains("dark")) {
            localStorage.setItem("theme", "dark");
            toggle.textContent = "☀️ Light Mode";
        } else {
            localStorage.setItem("theme", "light");
            toggle.textContent = "🌙 Dark Mode";
        }
    });
}
</script>

<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">Work Order <?= htmlspecialchars($wo['wo_no']) ?></h1>
        <div class="no-print" style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-primary">Print</button>
            <button onclick="exportToExcel()" class="btn btn-success">Export to Excel</button>
            <button onclick="shareToWhatsApp()" class="btn btn-secondary">Share via WhatsApp</button>
        </div>
    </div>

    <p><strong>Product:</strong> <?= htmlspecialchars($wo['part_name']) ?></p>
    <p><strong>BOM:</strong> <?= htmlspecialchars($wo['bom_no']) ?></p>
    <p><strong>Quantity:</strong> <?= htmlspecialchars($wo['qty']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($wo['status']) ?></p>
    <p><strong>Description:</strong>
    <?php if (!empty($wo['description'])): ?>
        <p><?= htmlspecialchars($wo['description']) ?></p>
    <?php endif; ?>
    </p>

    <h2>BOM Components</h2>

    <table border="1" cellpadding="8" id="woTable">
        <tr>
            <th>Part No</th>
            <th>Component</th>
            <th>Qty per Assembly</th>
            <th>Total Required</th>
            <th>Current Stock</th>
        </tr>

        <?php foreach ($bomItems as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i['part_no']) ?></td>
            <td><?= htmlspecialchars($i['part_name']) ?></td>
            <td><?= htmlspecialchars($i['qty']) ?></td>
            <td><?= htmlspecialchars($i['qty'] * $wo['qty']) ?></td>
            <td><?= $i['current_stock'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <a href="index.php" class="btn btn-secondary no-print">⬅ Back to Work Orders</a>
</div>

<script>
// Work order data for JavaScript functions
const woData = {
    woNo: <?= json_encode($wo['wo_no']) ?>,
    product: <?= json_encode($wo['part_name']) ?>,
    bom: <?= json_encode($wo['bom_no']) ?>,
    qty: <?= json_encode($wo['qty']) ?>,
    status: <?= json_encode($wo['status']) ?>,
    description: <?= json_encode($wo['description'] ?? '') ?>,
    components: <?= json_encode(array_map(function($i) use ($wo) {
        return [
            'part_no' => $i['part_no'],
            'part_name' => $i['part_name'],
            'qty_per_assembly' => $i['qty'],
            'total_required' => $i['qty'] * $wo['qty'],
            'current_stock' => $i['current_stock']
        ];
    }, $bomItems)) ?>
};

function exportToExcel() {
    const wb = XLSX.utils.book_new();

    // Header data
    const headerData = [
        ['Work Order', woData.woNo],
        ['Product', woData.product],
        ['BOM', woData.bom],
        ['Quantity', woData.qty],
        ['Status', woData.status],
        ['Description', woData.description || ''],
        [], // Empty row
        ['Part No', 'Component', 'Qty per Assembly', 'Total Required', 'Current Stock']
    ];

    // Table data
    const tableData = woData.components.map(comp => [
        comp.part_no,
        comp.part_name,
        comp.qty_per_assembly,
        comp.total_required,
        comp.current_stock
    ]);

    // Combine all data
    const wsData = [...headerData, ...tableData];

    // Create worksheet
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Set column widths
    ws['!cols'] = [
        { wch: 15 },
        { wch: 40 },
        { wch: 18 },
        { wch: 15 },
        { wch: 15 }
    ];

    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Work Order');

    // Generate filename
    const filename = 'WO_' + woData.woNo + '.xlsx';

    // Save file
    XLSX.writeFile(wb, filename);
}

function shareToWhatsApp() {
    // Build WhatsApp message
    let message = `*Work Order: ${woData.woNo}*\n\n`;
    message += `*Product:* ${woData.product}\n`;
    message += `*BOM:* ${woData.bom}\n`;
    message += `*Quantity:* ${woData.qty}\n`;
    message += `*Status:* ${woData.status}\n`;

    if (woData.description) {
        message += `*Description:* ${woData.description}\n`;
    }

    message += `\n*BOM Components:*\n`;
    message += `------------------------\n`;

    woData.components.forEach((comp, index) => {
        message += `${index + 1}. ${comp.part_no} - ${comp.part_name}\n`;
        message += `   Qty/Assembly: ${comp.qty_per_assembly}\n`;
        message += `   Total Required: ${comp.total_required}\n`;
        message += `   Current Stock: ${comp.current_stock}\n\n`;
    });

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
