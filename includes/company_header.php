<?php
/**
 * Company Header Component for Print Views
 *
 * Usage: include this file in quotations, proforma, invoices, etc.
 * Before including, make sure $pdo is available.
 *
 * Optional parameter: $document_title (e.g., "QUOTATION", "PROFORMA INVOICE")
 */

if (!isset($settings)) {
    $settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>

<style>
    .print-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #2c3e50;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .print-header .company-logo {
        max-width: 150px;
        max-height: 70px;
    }
    .print-header .company-info {
        flex: 1;
        padding-left: 20px;
    }
    .print-header .company-name {
        font-size: 1.5em;
        font-weight: bold;
        color: #2c3e50;
        margin: 0 0 5px 0;
    }
    .print-header .company-address {
        color: #555;
        font-size: 0.9em;
        line-height: 1.5;
        margin: 0;
    }
    .print-header .company-contact {
        color: #555;
        font-size: 0.9em;
        margin: 5px 0 0;
    }
    .print-header .company-tax {
        color: #666;
        font-size: 0.85em;
        margin: 5px 0 0;
    }
    .print-header .document-title {
        text-align: right;
        padding-left: 20px;
    }
    .print-header .document-title h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.3em;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    @media print {
        .print-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="print-header">
    <div style="display: flex; align-items: flex-start;">
        <?php if (!empty($settings['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($settings['logo_path']) ?>"
                 alt="Logo" class="company-logo">
        <?php endif; ?>
        <div class="company-info">
            <p class="company-name"><?= htmlspecialchars($settings['company_name'] ?? 'Company Name') ?></p>
            <p class="company-address">
                <?= htmlspecialchars($settings['address_line1'] ?? '') ?>
                <?php if (!empty($settings['address_line2'])): ?>
                    <br><?= htmlspecialchars($settings['address_line2']) ?>
                <?php endif; ?>
                <br>
                <?= htmlspecialchars(implode(', ', array_filter([
                    $settings['city'] ?? '',
                    $settings['state'] ?? '',
                    $settings['pincode'] ?? '',
                    $settings['country'] ?? ''
                ]))) ?>
            </p>
            <p class="company-contact">
                <?php if (!empty($settings['phone'])): ?>
                    Tel: <?= htmlspecialchars($settings['phone']) ?>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    | Email: <?= htmlspecialchars($settings['email']) ?>
                <?php endif; ?>
                <?php if (!empty($settings['website'])): ?>
                    <br>Web: <?= htmlspecialchars($settings['website']) ?>
                <?php endif; ?>
            </p>
            <p class="company-tax">
                <?php if (!empty($settings['gstin'])): ?>
                    GSTIN: <?= htmlspecialchars($settings['gstin']) ?>
                <?php endif; ?>
                <?php if (!empty($settings['pan'])): ?>
                    | PAN: <?= htmlspecialchars($settings['pan']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php if (!empty($document_title)): ?>
    <div class="document-title">
        <h2><?= htmlspecialchars($document_title) ?></h2>
    </div>
    <?php endif; ?>
</div>
