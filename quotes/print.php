<?php
include "../db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch quotation
$stmt = $pdo->prepare("
    SELECT q.*, c.company_name, c.customer_name, c.contact, c.email,
           c.address1, c.address2, c.city, c.pincode, c.state, c.gstin,
           c.designation,
           pt.term_name as payment_term_name, pt.term_description as payment_term_description
    FROM quote_master q
    LEFT JOIN customers c ON q.customer_id = c.customer_id
    LEFT JOIN payment_terms pt ON q.payment_terms_id = pt.id
    WHERE q.id = ?
");
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header("Location: index.php");
    exit;
}

// Fetch items
$itemsStmt = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalTaxable = 0;
$totalCGST = 0;
$totalSGST = 0;
$totalIGST = 0;
$grandTotal = 0;
$isIGST = isset($quote['is_igst']) && $quote['is_igst'] == 1;

foreach ($items as $item) {
    $totalTaxable += $item['taxable_amount'];
    if ($isIGST) {
        $totalIGST += $item['igst_amount'] ?? 0;
    } else {
        $totalCGST += $item['cgst_amount'];
        $totalSGST += $item['sgst_amount'];
    }
    $grandTotal += $item['total_amount'];
}

// Function to convert number to words
function numberToWords($number) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number < 0) return 'Negative ' . numberToWords(abs($number));
    if ($number == 0) return 'Zero';

    $words = '';

    if (floor($number / 10000000) > 0) {
        $words .= numberToWords(floor($number / 10000000)) . ' Crore ';
        $number %= 10000000;
    }
    if (floor($number / 100000) > 0) {
        $words .= numberToWords(floor($number / 100000)) . ' Lakh ';
        $number %= 100000;
    }
    if (floor($number / 1000) > 0) {
        $words .= numberToWords(floor($number / 1000)) . ' Thousand ';
        $number %= 1000;
    }
    if (floor($number / 100) > 0) {
        $words .= numberToWords(floor($number / 100)) . ' Hundred ';
        $number %= 100;
    }
    if ($number > 0) {
        if ($number < 20) {
            $words .= $ones[$number];
        } else {
            $words .= $tens[floor($number / 10)];
            if ($number % 10 > 0) {
                $words .= ' ' . $ones[$number % 10];
            }
        }
    }
    return trim($words);
}

$amountInWords = numberToWords(floor($grandTotal)) . ' Rupees';
$paise = round(($grandTotal - floor($grandTotal)) * 100);
if ($paise > 0) {
    $amountInWords .= ' and ' . numberToWords($paise) . ' Paise';
}
$amountInWords .= ' Only';

// Fetch active signatories
$signatories = [];
try {
    $signatories = $pdo->query("SELECT * FROM signatories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
}

$document_title = $quote['pi_no'] ? 'PROFORMA INVOICE' : 'QUOTATION';
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $document_title ?> - <?= htmlspecialchars($quote['quote_no']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1f2937;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }

        .print-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            border-radius: 12px;
            overflow: hidden;
        }

        /* Header Section */
        .document-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%);
            color: white;
            padding: 30px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .document-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .company-logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 12px;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .company-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .company-logo-placeholder {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 700;
        }

        .company-info h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .company-info p {
            font-size: 10px;
            opacity: 0.85;
            margin: 2px 0;
        }

        .document-type {
            text-align: right;
            position: relative;
            z-index: 1;
        }

        .document-type h2 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 3px;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .document-type .doc-number {
            font-size: 14px;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        /* Sub Header */
        .sub-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 18px 35px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .sub-header-item {
            text-align: center;
            padding: 8px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            min-width: 120px;
        }

        .sub-header-item label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 1.5px;
            font-weight: 600;
        }

        .sub-header-item span {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e3a5f;
            margin-top: 4px;
        }

        /* Content */
        .content {
            padding: 30px 35px;
        }

        /* Info Boxes */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-box {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s;
        }

        .info-box:hover {
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08);
        }

        .info-box-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: white;
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box-header::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #22d3ee;
            border-radius: 50%;
        }

        .info-box-content {
            padding: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .info-box-content .company-name {
            font-size: 15px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 6px;
        }

        .info-box-content .contact-name {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-box-content .contact-name::before {
            content: '•';
            font-size: 11px;
        }

        .info-box-content p {
            font-size: 11px;
            color: #64748b;
            margin: 4px 0;
            line-height: 1.6;
        }

        .info-box-content .gstin {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e1;
            font-weight: 600;
            color: #334155;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            margin-left: -18px;
            margin-right: -18px;
            margin-bottom: -18px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }

        /* Items Table */
        .items-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 15px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #0ea5e9;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::before {
            content: '•';
            font-size: 14px;
        }

        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 10px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07);
        }

        .items-table th {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%);
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.8px;
        }

        .items-table th:first-child { border-top-left-radius: 10px; }
        .items-table th:last-child { border-top-right-radius: 10px; }

        .items-table th.center { text-align: center; }
        .items-table th.right { text-align: right; }

        .items-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            background: white;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .items-table tbody tr:hover td {
            background: #f0f9ff;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .items-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        .items-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .items-table .center { text-align: center; }
        .items-table .right { text-align: right; }

        .items-table .part-no {
            font-weight: 700;
            color: #0369a1;
            font-size: 11px;
        }

        .items-table .part-name {
            font-weight: 500;
            color: #1e3a5f;
        }

        .items-table .hsn {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            color: #64748b;
            background: #f1f5f9;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .items-table .amount {
            font-weight: 700;
            font-family: 'Courier New', monospace;
            color: #1e3a5f;
        }

        .items-table .total-col {
            background: #f0fdf4 !important;
            color: #166534;
            font-weight: 700;
        }

        /* Summary Section */
        .summary-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 30px;
            align-items: flex-start;
        }

        .amount-words {
            flex: 1;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 18px 20px;
            border-radius: 12px;
            border-left: 5px solid #f59e0b;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.15);
        }

        .amount-words label {
            font-size: 10px;
            text-transform: uppercase;
            color: #92400e;
            letter-spacing: 1.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .amount-words label::before {
            content: '₹';
            font-size: 12px;
        }

        .amount-words p {
            font-size: 13px;
            font-weight: 600;
            color: #78350f;
            margin-top: 8px;
            font-style: italic;
            line-height: 1.5;
        }

        .summary-table-wrapper {
            width: 300px;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
        }

        .summary-table td {
            padding: 12px 16px;
            font-size: 11px;
            background: white;
        }

        .summary-table tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-table tr:last-child td {
            border-bottom: none;
        }

        .summary-table .label {
            text-align: right;
            color: #64748b;
            font-weight: 600;
        }

        .summary-table .value {
            text-align: right;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            width: 110px;
            color: #1e3a5f;
        }

        .summary-table .grand-total {
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
            color: white !important;
        }

        .summary-table .grand-total td {
            padding: 16px;
            font-size: 15px;
            font-weight: 700;
            background: transparent !important;
            color: white !important;
        }

        .summary-table .grand-total td.label {
            color: white !important;
        }

        .summary-table .grand-total td.value {
            color: white !important;
            font-size: 16px;
        }

        /* Additional Sections */
        .additional-section {
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .additional-section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .additional-section-content {
            padding: 18px;
            font-size: 11px;
            color: #64748b;
            background: white;
        }

        .payment-terms {
            border-left: 5px solid #10b981;
            border-color: #10b981;
        }

        .payment-terms .additional-section-header {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .payment-terms .additional-section-header::before {
            content: '✓';
            font-size: 12px;
        }

        .bank-section .additional-section-header::before {
            content: '▪';
            font-size: 12px;
        }

        .terms-section .additional-section-header::before {
            content: '▪';
            font-size: 12px;
        }

        .bank-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .bank-detail-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .bank-detail-item label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .bank-detail-item span {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #1e3a5f;
            margin-top: 4px;
            font-family: 'Courier New', monospace;
        }

        .terms-content {
            white-space: pre-wrap;
            line-height: 1.9;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #cbd5e1;
        }

        /* Signature Section */
        .signature-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 50px;
            padding: 30px 0;
            border-top: 2px dashed #e2e8f0;
        }

        .signature-box {
            text-align: center;
        }

        .signature-area {
            height: 70px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            margin-bottom: 10px;
        }

        .signature-line {
            border-top: 2px solid #1e3a5f;
            padding-top: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .signature-box.company .signature-line {
            border-color: #059669;
            color: #065f46;
        }

        .signature-subtitle {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Footer */
        .document-footer {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%);
            padding: 25px 35px;
            text-align: center;
            color: white;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-left {
            text-align: left;
        }

        .footer-left p {
            font-size: 10px;
            opacity: 0.8;
            margin: 2px 0;
        }

        .footer-center {
            text-align: center;
        }

        .footer-thanks {
            font-size: 14px;
            font-weight: 600;
            color: #22d3ee;
            margin-bottom: 5px;
        }

        .footer-note {
            font-size: 9px;
            opacity: 0.7;
        }

        .footer-right {
            text-align: right;
        }

        .footer-right p {
            font-size: 10px;
            opacity: 0.8;
            margin: 2px 0;
        }

        /* Print Button */
        .print-actions {
            position: fixed;
            top: 30px;
            right: 30px;
            display: flex;
            gap: 12px;
            z-index: 100;
        }

        .print-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn::before {
            content: '🖨';
            font-size: 16px;
        }

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.5);
        }

        .back-btn {
            padding: 14px 28px;
            background: white;
            color: #475569;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn::before {
            content: '←';
            font-size: 16px;
        }

        .back-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body {
                background: white;
                padding: 0;
                min-height: auto;
                font-size: 9px;
            }
            .print-container {
                box-shadow: none;
                max-width: 100%;
                border-radius: 0;
            }
            .print-actions {
                display: none;
            }

            /* Reduce header size */
            .document-header {
                padding: 15px 20px;
            }
            .company-logo {
                width: 45px;
                height: 45px;
            }
            .company-info h1 {
                font-size: 18px;
                margin-bottom: 3px;
            }
            .company-info p {
                font-size: 8px;
                margin: 1px 0;
            }
            .document-type h2 {
                font-size: 20px;
                margin-bottom: 5px;
            }
            .document-type .doc-number {
                font-size: 11px;
                padding: 5px 12px;
            }

            /* Reduce sub-header size */
            .sub-header {
                padding: 10px 20px;
                gap: 10px;
            }
            .sub-header-item {
                padding: 5px 12px;
                min-width: 90px;
            }
            .sub-header-item label {
                font-size: 7px;
            }
            .sub-header-item span {
                font-size: 10px;
                margin-top: 2px;
            }

            /* Reduce content padding */
            .content {
                padding: 15px 20px;
            }

            /* Reduce info grid spacing */
            .info-grid {
                gap: 15px;
                margin-bottom: 15px;
            }
            .info-box-header {
                padding: 8px 12px;
                font-size: 9px;
            }
            .info-box-content {
                padding: 12px;
            }
            .info-box-content .company-name {
                font-size: 12px;
                margin-bottom: 3px;
            }
            .info-box-content .contact-name {
                font-size: 10px;
                margin-bottom: 6px;
            }
            .info-box-content p {
                font-size: 9px;
                margin: 2px 0;
            }
            .info-box-content .gstin {
                margin-top: 8px;
                padding: 6px;
                font-size: 9px;
            }

            /* Reduce items table size */
            .items-section {
                margin-bottom: 15px;
            }
            .section-title {
                font-size: 11px;
                margin-bottom: 10px;
                padding: 6px 12px;
            }
            .items-table {
                font-size: 8px;
            }
            .items-table th {
                padding: 8px 6px;
                font-size: 7px;
            }
            .items-table td {
                padding: 8px 6px;
            }
            .items-table .part-no {
                font-size: 9px;
            }
            .items-table .hsn {
                font-size: 8px;
                padding: 2px 4px;
            }

            /* Reduce summary section */
            .summary-section {
                margin-bottom: 15px;
                gap: 15px;
            }
            .amount-words {
                padding: 12px 15px;
            }
            .amount-words label {
                font-size: 8px;
            }
            .amount-words p {
                font-size: 11px;
                margin-top: 5px;
            }
            .summary-table td {
                padding: 8px 12px;
                font-size: 9px;
            }
            .summary-table .grand-total td {
                padding: 12px;
                font-size: 12px;
            }
            .summary-table .grand-total td.value {
                font-size: 13px;
            }

            /* Reduce additional sections */
            .additional-section {
                margin-bottom: 12px;
            }
            .additional-section-header {
                padding: 8px 12px;
                font-size: 9px;
            }
            .additional-section-content {
                padding: 12px;
                font-size: 9px;
            }
            .bank-details-grid {
                gap: 10px;
            }
            .bank-detail-item {
                padding: 8px;
            }
            .bank-detail-item label {
                font-size: 7px;
            }
            .bank-detail-item span {
                font-size: 10px;
            }
            .terms-content {
                padding: 8px;
                line-height: 1.6;
            }

            /* Reduce signature section */
            .signature-section {
                margin-top: 25px;
                padding: 15px 0;
            }
            .signature-area {
                height: 50px;
                background: white;
            }
            .signature-line {
                padding-top: 8px;
                font-size: 9px;
            }
            .signature-subtitle {
                font-size: 7px;
                margin-top: 2px;
            }

            /* Reduce footer */
            .document-footer {
                padding: 15px 20px;
            }
            .footer-left p, .footer-right p {
                font-size: 8px;
            }
            .footer-thanks {
                font-size: 11px;
            }
            .footer-note {
                font-size: 7px;
            }

            .items-table tbody tr:hover td {
                background: inherit;
            }
            .info-box:hover {
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .document-header::before {
                display: none;
            }

            /* Ensure grand total text is visible in print */
            .summary-table .grand-total {
                background: #059669 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .summary-table .grand-total td,
            .summary-table .grand-total td.label,
            .summary-table .grand-total td.value {
                background: transparent !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Page break control */
            .items-section {
                page-break-inside: avoid;
            }
            .signature-section {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            body { padding: 15px; }
            .print-actions { top: 15px; right: 15px; flex-direction: column; }
            .info-grid { grid-template-columns: 1fr; }
            .summary-section { flex-direction: column; }
            .summary-table-wrapper { width: 100%; }
            .footer-content { flex-direction: column; text-align: center; }
            .footer-left, .footer-right { text-align: center; }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <a href="view.php?id=<?= $id ?>" class="back-btn">Back</a>
    <button class="print-btn" onclick="window.print()">Print / Download PDF</button>
</div>

<div class="print-container">
    <!-- Header -->
    <div class="document-header">
        <div class="header-left">
            <div class="company-logo">
                <?php if (!empty($settings['logo_path'])): ?>
                    <?php
                        $logo_path = $settings['logo_path'];
                        if (!preg_match('~^(https?:|/)~', $logo_path)) {
                            $logo_path = '/' . $logo_path;
                        }
                    ?>
                    <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.parentElement.innerHTML='<div class=\'company-logo-placeholder\'><?= strtoupper(substr($settings['company_name'] ?? 'C', 0, 1)) ?></div>'">
                <?php else: ?>
                    <div class="company-logo-placeholder"><?= strtoupper(substr($settings['company_name'] ?? 'C', 0, 1)) ?></div>
                <?php endif; ?>
            </div>
            <div class="company-info">
                <h1><?= htmlspecialchars($settings['company_name'] ?? 'Company Name') ?></h1>
                <p><?= htmlspecialchars($settings['address1'] ?? '') ?></p>
                <?php if (!empty($settings['address2'])): ?>
                    <p><?= htmlspecialchars($settings['address2']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars(implode(', ', array_filter([
                    $settings['city'] ?? '',
                    $settings['state'] ?? '',
                    $settings['pincode'] ?? ''
                ]))) ?></p>
                <?php if (!empty($settings['gstin'])): ?>
                    <p style="margin-top: 4px; font-weight: 600;">GSTIN: <?= htmlspecialchars($settings['gstin']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="document-type">
            <h2><?= $document_title ?></h2>
            <span class="doc-number"><?= htmlspecialchars($quote['quote_no']) ?></span>
        </div>
    </div>

    <!-- Sub Header -->
    <div class="sub-header">
        <div class="sub-header-item">
            <label>Date</label>
            <span><?= date('d M Y', strtotime($quote['quote_date'])) ?></span>
        </div>
        <?php if ($quote['pi_no']): ?>
        <div class="sub-header-item">
            <label>PI Number</label>
            <span><?= htmlspecialchars($quote['pi_no']) ?></span>
        </div>
        <?php endif; ?>
        <div class="sub-header-item">
            <label>Valid Until</label>
            <span><?= $quote['validity_date'] ? date('d M Y', strtotime($quote['validity_date'])) : '-' ?></span>
        </div>
        <?php if ($quote['reference']): ?>
        <div class="sub-header-item">
            <label>Reference</label>
            <span><?= htmlspecialchars($quote['reference']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Customer Info -->
        <div class="info-grid">
            <div class="info-box">
                <div class="info-box-header">Bill To</div>
                <div class="info-box-content">
                    <div class="company-name"><?= htmlspecialchars($quote['company_name'] ?? '') ?></div>
                    <?php if ($quote['customer_name']): ?>
                        <div class="contact-name">
                            <?= htmlspecialchars(($quote['designation'] ?? '') . ' ' . $quote['customer_name']) ?>
                        </div>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($quote['address1'] ?? '') ?></p>
                    <?php if ($quote['address2']): ?>
                        <p><?= htmlspecialchars($quote['address2']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars(implode(', ', array_filter([
                        $quote['city'] ?? '',
                        $quote['state'] ?? '',
                        $quote['pincode'] ?? ''
                    ]))) ?></p>
                    <?php if ($quote['gstin']): ?>
                        <p class="gstin">GSTIN: <?= htmlspecialchars($quote['gstin']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-header">Contact Details</div>
                <div class="info-box-content">
                    <?php if ($quote['contact']): ?>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($quote['contact']) ?></p>
                    <?php endif; ?>
                    <?php if ($quote['email']): ?>
                        <p><strong>Email:</strong> <?= htmlspecialchars($quote['email']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone'])): ?>
                        <p style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #dee2e6;">
                            <strong>Our Contact:</strong> <?= htmlspecialchars($settings['phone']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($settings['email'])): ?>
                        <p><strong>Our Email:</strong> <?= htmlspecialchars($settings['email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="items-section">
            <div class="section-title">Item Details</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30px;" class="center">#</th>
                        <th style="width: 90px;">Part No</th>
                        <th style="width: 120px;">Product Name</th>
                        <th>Description</th>
                        <th style="width: 70px;">HSN/SAC</th>
                        <th style="width: 45px;" class="center">Qty</th>
                        <th style="width: 35px;" class="center">Unit</th>
                        <th style="width: 70px;" class="right">Rate</th>
                        <th style="width: 80px;" class="right">Taxable Amt</th>
                        <th style="width: 55px;" class="right"><?= $isIGST ? 'IGST' : 'GST' ?></th>
                        <th style="width: 80px;" class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td class="center"><?= $i + 1 ?></td>
                        <td class="part-no"><?= htmlspecialchars($item['part_no']) ?></td>
                        <td class="part-name"><?= htmlspecialchars($item['part_name']) ?></td>
                        <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                        <td><span class="hsn"><?= htmlspecialchars($item['hsn_code'] ?? '-') ?></span></td>
                        <td class="center"><?= number_format($item['qty'], 2) ?></td>
                        <td class="center"><?= htmlspecialchars($item['unit'] ?? 'Nos') ?></td>
                        <td class="right amount"><?= number_format($item['rate'], 2) ?></td>
                        <td class="right amount"><?= number_format($item['taxable_amount'], 2) ?></td>
                        <td class="right"><?= $isIGST ? number_format($item['igst_amount'] ?? 0, 2) : number_format($item['cgst_amount'] + $item['sgst_amount'], 2) ?></td>
                        <td class="right amount total-col"><?= number_format($item['total_amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="summary-section">
            <div class="amount-words">
                <label>Amount in Words</label>
                <p><?= $amountInWords ?></p>
            </div>
            <div class="summary-table-wrapper">
                <table class="summary-table">
                    <tr>
                        <td class="label">Taxable Amount</td>
                        <td class="value"><?= number_format($totalTaxable, 2) ?></td>
                    </tr>
                    <?php if ($isIGST): ?>
                    <tr>
                        <td class="label">IGST</td>
                        <td class="value"><?= number_format($totalIGST, 2) ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td class="label">CGST</td>
                        <td class="value"><?= number_format($totalCGST, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="label">SGST</td>
                        <td class="value"><?= number_format($totalSGST, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="grand-total">
                        <td class="label">Grand Total</td>
                        <td class="value"><?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Terms -->
        <?php if ($quote['payment_term_name'] ?? null): ?>
        <div class="additional-section payment-terms">
            <div class="additional-section-header">Payment Terms</div>
            <div class="additional-section-content">
                <strong><?= htmlspecialchars($quote['payment_term_name']) ?></strong>
                <?php if ($quote['payment_term_description']): ?>
                    <p style="margin-top: 5px;"><?= htmlspecialchars($quote['payment_term_description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bank Details -->
        <?php if (!empty($settings['bank_name'])): ?>
        <div class="additional-section bank-section">
            <div class="additional-section-header">Bank Details for Payment</div>
            <div class="additional-section-content">
                <div class="bank-details-grid">
                    <div class="bank-detail-item">
                        <label>Bank Name</label>
                        <span><?= htmlspecialchars($settings['bank_name']) ?></span>
                    </div>
                    <div class="bank-detail-item">
                        <label>Account Number</label>
                        <span><?= htmlspecialchars($settings['bank_account'] ?? '-') ?></span>
                    </div>
                    <div class="bank-detail-item">
                        <label>IFSC Code</label>
                        <span><?= htmlspecialchars($settings['bank_ifsc'] ?? '-') ?></span>
                    </div>
                    <?php if (!empty($settings['bank_branch'])): ?>
                    <div class="bank-detail-item">
                        <label>Branch</label>
                        <span><?= htmlspecialchars($settings['bank_branch']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Terms & Conditions -->
        <?php if ($quote['terms_conditions'] || !empty($settings['terms_conditions'])): ?>
        <div class="additional-section terms-section">
            <div class="additional-section-header">Terms & Conditions</div>
            <div class="additional-section-content">
                <p class="terms-content"><?= nl2br(htmlspecialchars($quote['terms_conditions'] ?: $settings['terms_conditions'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signature Section -->
        <div class="signature-section">
            <!-- Customer Signature -->
            <div class="signature-box">
                <div class="signature-area"></div>
                <div class="signature-line">Customer Acceptance</div>
                <div class="signature-subtitle">Signature & Company Seal</div>
            </div>

            <!-- Company Signatories -->
            <?php if (!empty($signatories)): ?>
                <?php foreach ($signatories as $signatory): ?>
                <div class="signature-box company">
                    <div class="signature-area"></div>
                    <div class="signature-line"><?= htmlspecialchars($signatory['name']) ?></div>
                    <div class="signature-subtitle">
                        <?= htmlspecialchars($signatory['designation'] ?: 'Authorized Signatory') ?>
                        <?php if ($signatory['department']): ?>
                            | <?= htmlspecialchars($signatory['department']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback if no signatories defined -->
                <div class="signature-box company">
                    <div class="signature-area"></div>
                    <div class="signature-line">For <?= htmlspecialchars($settings['company_name'] ?? 'Company') ?></div>
                    <div class="signature-subtitle">Authorized Signatory</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="document-footer">
        <div class="footer-content">
            <div class="footer-left">
                <?php if (!empty($settings['phone'])): ?>
                    <p>Phone: <?= htmlspecialchars($settings['phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    <p>Email: <?= htmlspecialchars($settings['email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="footer-center">
                <div class="footer-thanks">Thank you for your business!</div>
                <div class="footer-note">This is a computer generated document</div>
            </div>
            <div class="footer-right">
                <?php if (!empty($settings['website'])): ?>
                    <p><?= htmlspecialchars($settings['website']) ?></p>
                <?php endif; ?>
                <p><?= htmlspecialchars($settings['company_name'] ?? '') ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['download'])): ?>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
<?php endif; ?>
</body>
</html>
