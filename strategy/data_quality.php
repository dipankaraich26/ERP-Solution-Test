<?php
/**
 * Data Quality Audit
 * Scans all major tables for data gaps and provides fix links
 */
include "../db.php";
include "../includes/auth.php";
requireLogin();

// ============ DATA QUALITY CHECKS ============
$checks = [];

// 1. Parts with Rs. 0 Rate
$count = 0; $items = [];
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM part_master WHERE (rate = 0 OR rate IS NULL) AND status = 'active'")->fetchColumn();
    $items = $pdo->query("SELECT part_no, part_name, category FROM part_master WHERE (rate = 0 OR rate IS NULL) AND status = 'active' ORDER BY part_name LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Parts with Rs. 0 Rate',
    'desc' => 'Active products without pricing - affects revenue analytics and quote accuracy',
    'count' => $count, 'items' => $items,
    'fix_url' => '/part_master/list.php',
    'severity' => $count > 0 ? 'danger' : 'success',
    'fields' => ['part_no', 'part_name']
];

// 2. Leads with No Source
$count = 0; $items = [];
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_source IS NULL OR lead_source = ''")->fetchColumn();
    $items = $pdo->query("SELECT lead_no, company_name FROM crm_leads WHERE lead_source IS NULL OR lead_source = '' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Leads with No Source',
    'desc' => 'Cannot measure marketing ROI without lead source tracking',
    'count' => $count, 'items' => $items,
    'fix_url' => '/crm/index.php',
    'severity' => $count > 5 ? 'warning' : ($count > 0 ? 'info' : 'success'),
    'fields' => ['lead_no', 'company_name']
];

// 3. Leads with No Market Classification
$count = 0; $items = [];
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM crm_leads WHERE market_classification IS NULL OR market_classification = ''")->fetchColumn();
    $items = $pdo->query("SELECT lead_no, company_name FROM crm_leads WHERE market_classification IS NULL OR market_classification = '' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Leads Missing Market Classification',
    'desc' => 'Cannot segment market without classification (Private Hospital, Corporate, etc.)',
    'count' => $count, 'items' => $items,
    'fix_url' => '/crm/index.php',
    'severity' => $count > 5 ? 'warning' : ($count > 0 ? 'info' : 'success'),
    'fields' => ['lead_no', 'company_name']
];

// 4. POs with Rs. 0 Amount
$count = 0;
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE rate = 0 OR rate IS NULL")->fetchColumn();
} catch (Exception $e) {}
$checks[] = [
    'label' => 'POs with Rs. 0 Amount',
    'desc' => 'Cannot calculate COGS or profit margins without PO pricing',
    'count' => $count, 'items' => [],
    'fix_url' => '/purchase/index.php',
    'severity' => $count > 0 ? 'warning' : 'success',
    'fields' => []
];

// 5. Inventory Without Min Stock Config
$count = 0; $items = [];
try {
    $count = (int)$pdo->query("
        SELECT COUNT(*) FROM inventory i
        LEFT JOIN part_min_stock pms ON pms.part_no = i.part_no
        WHERE pms.part_no IS NULL AND i.qty > 0
    ")->fetchColumn();
    $items = $pdo->query("
        SELECT i.part_no, p.part_name FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
        LEFT JOIN part_min_stock pms ON pms.part_no = i.part_no
        WHERE pms.part_no IS NULL AND i.qty > 0
        ORDER BY p.part_name LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Items Without Min Stock Config',
    'desc' => 'No reorder alerts - risk of stockouts and lost sales',
    'count' => $count, 'items' => $items,
    'fix_url' => '/part_master/list.php',
    'severity' => $count > 10 ? 'warning' : ($count > 0 ? 'info' : 'success'),
    'fields' => ['part_no', 'part_name']
];

// 6. Customers Missing Email
$count = 0;
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE (email IS NULL OR email = '') AND status = 'Active'")->fetchColumn();
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Customers Missing Email',
    'desc' => 'Cannot send quotes, invoices, or marketing emails',
    'count' => $count, 'items' => [],
    'fix_url' => '/customers/index.php',
    'severity' => $count > 5 ? 'warning' : ($count > 0 ? 'info' : 'success'),
    'fields' => []
];

// 7. Customers Missing Phone
$count = 0;
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE (contact IS NULL OR contact = '') AND status = 'Active'")->fetchColumn();
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Customers Missing Phone',
    'desc' => 'Cannot contact for follow-ups or urgent communication',
    'count' => $count, 'items' => [],
    'fix_url' => '/customers/index.php',
    'severity' => $count > 0 ? 'danger' : 'success',
    'fields' => []
];

// 8. Customers Missing GSTIN
$count = 0;
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE (gstin IS NULL OR gstin = '') AND status = 'Active'")->fetchColumn();
} catch (Exception $e) {}
$checks[] = [
    'label' => 'Customers Missing GSTIN',
    'desc' => 'Required for GST-compliant invoicing (B2B customers)',
    'count' => $count, 'items' => [],
    'fix_url' => '/customers/index.php',
    'severity' => $count > 10 ? 'info' : 'success',
    'fields' => []
];

// Calculate quality score
$totalChecks = count($checks);
$passedChecks = count(array_filter($checks, fn($c) => $c['severity'] === 'success'));
$qualityScore = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 100;
$scoreClass = $qualityScore >= 80 ? 'green' : ($qualityScore >= 50 ? 'orange' : 'red');

// Summary counts
$dangerCount = count(array_filter($checks, fn($c) => $c['severity'] === 'danger'));
$warningCount = count(array_filter($checks, fn($c) => $c['severity'] === 'warning'));
$infoCount = count(array_filter($checks, fn($c) => $c['severity'] === 'info'));

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Quality Audit - ERP System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .strategy-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 3px solid #10b981;
        }
        .strategy-header h1 { margin: 0; font-size: 1.6em; color: var(--text); }
        .strategy-header .subtitle { font-size: 0.85em; color: var(--muted-text); }

        .quality-score { text-align: center; margin: 30px 0; }
        .score-circle {
            width: 150px; height: 150px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 2.5em; font-weight: 700; color: var(--text);
            border: 6px solid; margin-bottom: 10px;
            background: var(--card);
        }
        .score-circle.green { border-color: #10b981; }
        .score-circle.orange { border-color: #f59e0b; }
        .score-circle.red { border-color: #ef4444; }
        .score-label { font-size: 1em; color: var(--muted-text); font-weight: 600; }

        .summary-badges {
            display: flex; justify-content: center; gap: 15px;
            margin: 15px 0 25px 0;
        }
        .summary-badge {
            padding: 6px 16px; border-radius: 20px; font-size: 0.85em; font-weight: 600;
        }
        .summary-badge.danger { background: #fef2f2; color: #dc2626; }
        .summary-badge.warning { background: #fffbeb; color: #d97706; }
        .summary-badge.info { background: #eff6ff; color: #2563eb; }
        .summary-badge.success { background: #f0fdf4; color: #16a34a; }

        .checks-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 15px; margin-top: 20px;
        }
        .check-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px; border-left: 4px solid transparent;
        }
        .check-card.danger { border-left-color: #ef4444; }
        .check-card.warning { border-left-color: #f59e0b; }
        .check-card.success { border-left-color: #10b981; }
        .check-card.info { border-left-color: #3b82f6; }

        .check-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .check-label { font-weight: 600; font-size: 0.92em; color: var(--text); }
        .check-desc { font-size: 0.8em; color: var(--muted-text); margin-bottom: 10px; }
        .check-count { font-size: 1.6em; font-weight: 700; line-height: 1; }
        .check-count.danger { color: #ef4444; }
        .check-count.warning { color: #f59e0b; }
        .check-count.success { color: #10b981; }
        .check-count.info { color: #3b82f6; }

        .check-items { list-style: none; padding: 0; margin: 8px 0; max-height: 120px; overflow-y: auto; }
        .check-items li {
            font-size: 0.8em; color: var(--muted-text); padding: 4px 0;
            border-bottom: 1px dashed var(--border);
            display: flex; gap: 8px;
        }
        .check-items li .item-id { font-weight: 600; color: var(--text); min-width: 80px; }

        .btn-fix {
            display: inline-block; padding: 6px 14px; font-size: 0.82em;
            border-radius: 6px; text-decoration: none; font-weight: 600;
            background: var(--primary, #2563eb); color: white; transition: opacity 0.2s;
            margin-top: 8px;
        }
        .btn-fix:hover { opacity: 0.85; }
        .btn-fix.success-btn { background: #10b981; }

        .back-link { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; font-size: 0.9em; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="content">

    <a href="/strategy/index.php" class="back-link">&larr; Back to Strategy Dashboard</a>

    <div class="strategy-header">
        <div>
            <h1>Data Quality Audit</h1>
            <div class="subtitle">Identify and fix data gaps across all modules</div>
        </div>
        <div class="subtitle"><?= date('F j, Y') ?></div>
    </div>

    <!-- Quality Score -->
    <div class="quality-score">
        <div class="score-circle <?= $scoreClass ?>"><?= $qualityScore ?>%</div>
        <div class="score-label">Overall Data Quality Score</div>
    </div>

    <!-- Summary Badges -->
    <div class="summary-badges">
        <?php if ($dangerCount > 0): ?>
        <span class="summary-badge danger"><?= $dangerCount ?> Critical</span>
        <?php endif; ?>
        <?php if ($warningCount > 0): ?>
        <span class="summary-badge warning"><?= $warningCount ?> Warnings</span>
        <?php endif; ?>
        <?php if ($infoCount > 0): ?>
        <span class="summary-badge info"><?= $infoCount ?> Info</span>
        <?php endif; ?>
        <span class="summary-badge success"><?= $passedChecks ?> Passed</span>
    </div>

    <!-- Quality Checks Grid -->
    <div class="checks-grid">
        <?php foreach ($checks as $check): ?>
        <div class="check-card <?= $check['severity'] ?>">
            <div class="check-header">
                <div class="check-label"><?= htmlspecialchars($check['label']) ?></div>
                <div class="check-count <?= $check['severity'] ?>">
                    <?php if ($check['severity'] === 'success'): ?>
                        &#10003;
                    <?php else: ?>
                        <?= $check['count'] ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="check-desc"><?= htmlspecialchars($check['desc']) ?></div>

            <?php if (!empty($check['items'])): ?>
            <ul class="check-items">
                <?php foreach (array_slice($check['items'], 0, 5) as $item): ?>
                <li>
                    <?php if (!empty($check['fields'])): ?>
                        <span class="item-id"><?= htmlspecialchars($item[$check['fields'][0]] ?? '') ?></span>
                        <span><?= htmlspecialchars($item[$check['fields'][1]] ?? '') ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if (count($check['items']) > 5): ?>
                <li style="color: var(--primary); font-style: italic;">... and <?= $check['count'] - 5 ?> more</li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>

            <?php if ($check['severity'] !== 'success'): ?>
            <a href="<?= $check['fix_url'] ?>" class="btn-fix">Fix Now &rarr;</a>
            <?php else: ?>
            <span class="btn-fix success-btn" style="cursor: default;">All Good</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>