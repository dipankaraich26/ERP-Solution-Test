<?php
session_start();
include "../db.php";

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) { header("Location: logout.php"); exit; }

// Get all quotations for this customer with linked data
$quotations = [];
try {
    $quoteStmt = $pdo->prepare("
        SELECT q.id, q.quote_no, q.quote_date, q.validity_date, q.pi_no, q.status as quote_status,
               (SELECT SUM(total_amount) FROM quote_items WHERE quote_id = q.id) as total_value
        FROM quote_master q
        WHERE q.customer_id = ?
        ORDER BY q.quote_date DESC
    ");
    $quoteStmt->execute([$customer_id]);
    $quotations = $quoteStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// For each quotation, get linked SO and procurement plan data
foreach ($quotations as &$q) {
    $q['sales_orders'] = [];
    $q['procurement_plans'] = [];
    $q['pp_progress'] = 0;

    // Get linked sales orders
    try {
        $soStmt = $pdo->prepare("
            SELECT DISTINCT so_no, status, created_at
            FROM sales_orders
            WHERE linked_quote_id = ?
            ORDER BY created_at DESC
        ");
        $soStmt->execute([$q['id']]);
        $q['sales_orders'] = $soStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // For each SO, find procurement plans
    if (!empty($q['sales_orders'])) {
        foreach ($q['sales_orders'] as $so) {
            try {
                $ppStmt = $pdo->prepare("
                    SELECT pp.id, pp.plan_no, pp.status
                    FROM procurement_plans pp
                    WHERE FIND_IN_SET(?, REPLACE(pp.so_list, ' ', '')) > 0
                    AND pp.status NOT IN ('cancelled')
                    LIMIT 1
                ");
                $ppStmt->execute([$so['so_no']]);
                $pp = $ppStmt->fetch(PDO::FETCH_ASSOC);
                if ($pp) {
                    // Calculate progress for this plan
                    $progress = 0;
                    if ($pp['status'] === 'completed') {
                        $progress = 100;
                    } else {
                        try {
                            // Count total WO+PO items
                            $totalStmt = $pdo->prepare("
                                SELECT
                                    (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = ?) +
                                    (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = ?) as total
                            ");
                            $totalStmt->execute([$pp['id'], $pp['id']]);
                            $totalParts = (int)$totalStmt->fetchColumn();

                            if ($totalParts > 0) {
                                // Count completed/in-stock items
                                $doneStmt = $pdo->prepare("
                                    SELECT
                                        (SELECT COUNT(*) FROM procurement_plan_wo_items WHERE plan_id = ? AND (status IN ('completed', 'closed') OR (created_wo_id IS NULL AND shortage <= 0))) +
                                        (SELECT COUNT(*) FROM procurement_plan_po_items WHERE plan_id = ? AND (status IN ('received', 'closed') OR (created_po_id IS NULL AND shortage <= 0))) as done
                                ");
                                $doneStmt->execute([$pp['id'], $pp['id']]);
                                $doneParts = (int)$doneStmt->fetchColumn();
                                $progress = round(($doneParts / $totalParts) * 100);
                            }
                        } catch (Exception $e) {}
                    }
                    $pp['progress'] = $progress;
                    $q['procurement_plans'][] = $pp;
                    if ($progress > $q['pp_progress']) {
                        $q['pp_progress'] = $progress;
                    }
                }
            } catch (Exception $e) {}
        }
    }
}
unset($q);

$company_settings = null;
try { $company_settings = $pdo->query("SELECT logo_path, company_name, phone FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Customer Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .portal-navbar { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .portal-navbar .brand { display: flex; align-items: center; gap: 15px; color: white; }
        .portal-navbar .brand img { height: 40px; }
        .portal-navbar .user-info { display: flex; align-items: center; gap: 20px; color: white; }
        .portal-navbar .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; }
        .portal-content { max-width: 1400px; margin: 0 auto; padding: 30px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { color: #2c3e50; margin-top: 10px; }
        .back-link { color: #11998e; text-decoration: none; font-weight: 500; }

        .tracking-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .tracking-header {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
        }
        .tracking-header:hover { background: #f8f9fa; }
        .tracking-header .quote-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .tracking-header .quote-no {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
        }
        .tracking-header .quote-date {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .tracking-header .quote-value {
            font-weight: 700;
            color: #27ae60;
            font-size: 1.1em;
        }

        .pipeline-bar {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            gap: 0;
            overflow-x: auto;
        }
        .pipeline-step {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .step-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            font-weight: 700;
            flex-shrink: 0;
        }
        .step-icon.done { background: #d4edda; color: #155724; }
        .step-icon.active { background: #cce5ff; color: #004085; }
        .step-icon.pending { background: #f0f0f0; color: #999; }
        .step-info { min-width: 100px; }
        .step-label { font-weight: 600; color: #2c3e50; font-size: 0.9em; }
        .step-detail { font-size: 0.8em; color: #7f8c8d; margin-top: 2px; }
        .step-detail.done { color: #155724; }
        .step-detail.active { color: #004085; }

        .pipeline-connector {
            width: 50px;
            height: 3px;
            background: #e0e0e0;
            flex-shrink: 0;
            margin: 0 5px;
        }
        .pipeline-connector.done { background: #27ae60; }
        .pipeline-connector.active { background: linear-gradient(90deg, #27ae60, #3498db); }

        .tracking-details {
            display: none;
            padding: 0 25px 20px;
            border-top: 1px solid #f0f0f0;
        }
        .tracking-details.open { display: block; }

        .detail-section {
            margin-top: 15px;
        }
        .detail-section h4 {
            color: #2c3e50;
            font-size: 0.95em;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .detail-item {
            background: #f8f9fa;
            padding: 10px 14px;
            border-radius: 8px;
        }
        .detail-item .d-label { font-size: 0.8em; color: #7f8c8d; }
        .detail-item .d-value { font-weight: 600; color: #2c3e50; margin-top: 2px; }

        .progress-bar-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 24px;
            overflow: hidden;
            position: relative;
            margin-top: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: 700;
            min-width: 40px;
        }
        .progress-bar-fill.low { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .progress-bar-fill.medium { background: linear-gradient(90deg, #3498db, #2980b9); }
        .progress-bar-fill.high { background: linear-gradient(90deg, #27ae60, #2ecc71); }

        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.8em; font-weight: 600; }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-sent, .status-approved { background: #cce5ff; color: #004085; }
        .status-accepted, .status-completed, .status-released { background: #d4edda; color: #155724; }
        .status-pending, .status-open { background: #e2e3e5; color: #383d41; }
        .status-processing, .status-partiallyordered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled, .status-rejected { background: #f8d7da; color: #721c24; }

        .empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; }
        .empty-state .icon { font-size: 4em; margin-bottom: 15px; }

        .summary-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 40px; flex-wrap: wrap; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .summary-item .label { color: #7f8c8d; font-size: 0.9em; }

        @media (max-width: 768px) {
            .pipeline-bar { flex-wrap: wrap; gap: 10px; }
            .pipeline-connector { width: 20px; }
            .tracking-header .quote-info { gap: 10px; }
        }
    </style>
</head>
<body>
<nav class="portal-navbar">
    <div class="brand">
        <?php if ($company_settings && !empty($company_settings['logo_path'])): ?><img src="/<?= htmlspecialchars($company_settings['logo_path']) ?>" alt="Logo"><?php endif; ?>
        <h2>Customer Portal</h2>
    </div>
    <div class="user-info">
        <span><?= htmlspecialchars($customer['company_name'] ?: $customer['customer_name']) ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
<div class="portal-content">
    <div class="page-header">
        <a href="my_portal.php" class="back-link">&larr; Back to Portal</a>
        <h1>Order Tracking</h1>
    </div>

    <?php
    $totalQuotes = count($quotations);
    $withSO = 0; $withPP = 0; $completed = 0;
    foreach ($quotations as $q) {
        if (!empty($q['sales_orders'])) $withSO++;
        if (!empty($q['procurement_plans'])) $withPP++;
        if ($q['pp_progress'] >= 100) $completed++;
    }
    ?>
    <div class="summary-bar">
        <div class="summary-item"><div class="value"><?= $totalQuotes ?></div><div class="label">Total Quotations</div></div>
        <div class="summary-item"><div class="value" style="color: #9b59b6;"><?= $withSO ?></div><div class="label">With Sales Order</div></div>
        <div class="summary-item"><div class="value" style="color: #3498db;"><?= $withPP ?></div><div class="label">In Production</div></div>
        <div class="summary-item"><div class="value" style="color: #27ae60;"><?= $completed ?></div><div class="label">Completed</div></div>
    </div>

    <?php if (empty($quotations)): ?>
        <div class="tracking-card">
            <div class="empty-state">
                <div class="icon">📋</div>
                <h3>No Quotations Found</h3>
                <p style="margin-top: 10px;">Your quotations and order tracking will appear here</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($quotations as $q):
            $hasPI = !empty($q['pi_no']);
            $hasSO = !empty($q['sales_orders']);
            $hasPP = !empty($q['procurement_plans']);
            $ppProgress = $q['pp_progress'];
            $ppDone = $ppProgress >= 100;

            // Determine step states
            $quoteState = 'done'; // quote always exists
            $piState = $hasPI ? 'done' : 'pending';
            $soState = $hasSO ? 'done' : ($hasPI ? 'active' : 'pending');
            $ppState = $ppDone ? 'done' : ($hasPP ? 'active' : ($hasSO ? 'active' : 'pending'));

            // Connector states
            $conn1 = $hasPI ? 'done' : 'pending';
            $conn2 = $hasSO ? 'done' : ($hasPI ? 'active' : 'pending');
            $conn3 = $ppDone ? 'done' : ($hasPP ? 'active' : 'pending');

            // SO info
            $soNo = $hasSO ? $q['sales_orders'][0]['so_no'] : null;
            $soStatus = $hasSO ? $q['sales_orders'][0]['status'] : null;
            $soDate = $hasSO && $q['sales_orders'][0]['created_at'] ? date('d M Y', strtotime($q['sales_orders'][0]['created_at'])) : null;

            // PP info
            $ppNo = $hasPP ? $q['procurement_plans'][0]['plan_no'] : null;
            $ppStatus = $hasPP ? $q['procurement_plans'][0]['status'] : null;
        ?>
        <div class="tracking-card">
            <div class="tracking-header" onclick="toggleDetails(this)">
                <div class="quote-info">
                    <span class="quote-no"><?= htmlspecialchars($q['quote_no']) ?></span>
                    <span class="quote-date"><?= $q['quote_date'] ? date('d M Y', strtotime($q['quote_date'])) : '-' ?></span>
                    <span class="status-badge status-<?= strtolower($q['quote_status'] ?: 'draft') ?>"><?= htmlspecialchars(ucfirst($q['quote_status'] ?: 'Draft')) ?></span>
                </div>
                <span class="quote-value"><?= $q['total_value'] ? 'INR ' . number_format($q['total_value'], 2) : '' ?></span>
            </div>

            <div class="pipeline-bar">
                <!-- Step 1: Quotation -->
                <div class="pipeline-step">
                    <div class="step-icon <?= $quoteState ?>">1</div>
                    <div class="step-info">
                        <div class="step-label">Quotation</div>
                        <div class="step-detail done"><?= htmlspecialchars($q['quote_no']) ?></div>
                    </div>
                </div>

                <div class="pipeline-connector <?= $conn1 ?>"></div>

                <!-- Step 2: Proforma Invoice -->
                <div class="pipeline-step">
                    <div class="step-icon <?= $piState ?>">2</div>
                    <div class="step-info">
                        <div class="step-label">Proforma Invoice</div>
                        <?php if ($hasPI): ?>
                            <div class="step-detail done"><?= htmlspecialchars($q['pi_no']) ?></div>
                        <?php else: ?>
                            <div class="step-detail">Not yet created</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pipeline-connector <?= $conn2 ?>"></div>

                <!-- Step 3: Sales Order -->
                <div class="pipeline-step">
                    <div class="step-icon <?= $soState ?>">3</div>
                    <div class="step-info">
                        <div class="step-label">Sales Order</div>
                        <?php if ($hasSO): ?>
                            <div class="step-detail done"><?= htmlspecialchars($soNo) ?></div>
                        <?php else: ?>
                            <div class="step-detail">Not yet created</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pipeline-connector <?= $conn3 ?>"></div>

                <!-- Step 4: Production Progress -->
                <div class="pipeline-step">
                    <div class="step-icon <?= $ppState ?>"><?= $ppDone ? '&#10003;' : '4' ?></div>
                    <div class="step-info">
                        <div class="step-label">Production</div>
                        <?php if ($hasPP): ?>
                            <div class="step-detail <?= $ppDone ? 'done' : 'active' ?>"><?= $ppProgress ?>% complete</div>
                        <?php elseif ($hasSO): ?>
                            <div class="step-detail">Awaiting planning</div>
                        <?php else: ?>
                            <div class="step-detail">Not yet started</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Expandable Details -->
            <div class="tracking-details">
                <!-- Quotation Details -->
                <div class="detail-section">
                    <h4>Quotation Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="d-label">Quote No</div>
                            <div class="d-value"><?= htmlspecialchars($q['quote_no']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Date</div>
                            <div class="d-value"><?= $q['quote_date'] ? date('d M Y', strtotime($q['quote_date'])) : '-' ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Validity</div>
                            <div class="d-value"><?= $q['validity_date'] ? date('d M Y', strtotime($q['validity_date'])) : '-' ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Amount</div>
                            <div class="d-value"><?= $q['total_value'] ? 'INR ' . number_format($q['total_value'], 2) : '-' ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Status</div>
                            <div class="d-value"><span class="status-badge status-<?= strtolower($q['quote_status'] ?: 'draft') ?>"><?= htmlspecialchars(ucfirst($q['quote_status'] ?: 'Draft')) ?></span></div>
                        </div>
                    </div>
                </div>

                <?php if ($hasPI): ?>
                <div class="detail-section">
                    <h4>Proforma Invoice</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="d-label">PI No</div>
                            <div class="d-value"><?= htmlspecialchars($q['pi_no']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Status</div>
                            <div class="d-value"><span class="status-badge status-sent">Created</span></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasSO): ?>
                <div class="detail-section">
                    <h4>Sales Order<?= count($q['sales_orders']) > 1 ? 's' : '' ?></h4>
                    <div class="detail-grid">
                        <?php foreach ($q['sales_orders'] as $so): ?>
                        <div class="detail-item">
                            <div class="d-label">SO No</div>
                            <div class="d-value"><?= htmlspecialchars($so['so_no']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Date</div>
                            <div class="d-value"><?= $so['created_at'] ? date('d M Y', strtotime($so['created_at'])) : '-' ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Status</div>
                            <div class="d-value"><span class="status-badge status-<?= strtolower($so['status'] ?: 'pending') ?>"><?= htmlspecialchars(ucfirst($so['status'] ?: 'Pending')) ?></span></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasPP): ?>
                <div class="detail-section">
                    <h4>Production Progress</h4>
                    <?php foreach ($q['procurement_plans'] as $pp): ?>
                    <div class="detail-grid" style="margin-bottom: 10px;">
                        <div class="detail-item">
                            <div class="d-label">Plan No</div>
                            <div class="d-value"><?= htmlspecialchars($pp['plan_no']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="d-label">Status</div>
                            <div class="d-value"><span class="status-badge status-<?= strtolower($pp['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('partially', 'Partially ', $pp['status']))) ?></span></div>
                        </div>
                    </div>
                    <div style="margin-top: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-size: 0.85em; color: #7f8c8d;">Production Progress</span>
                            <span style="font-size: 0.85em; font-weight: 600; color: #2c3e50;"><?= $pp['progress'] ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill <?= $pp['progress'] >= 80 ? 'high' : ($pp['progress'] >= 40 ? 'medium' : 'low') ?>" style="width: <?= max($pp['progress'], 5) ?>%;">
                                <?= $pp['progress'] > 15 ? $pp['progress'] . '%' : '' ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleDetails(header) {
    const details = header.closest('.tracking-card').querySelector('.tracking-details');
    details.classList.toggle('open');
}
</script>
<?php include 'includes/whatsapp_button.php'; ?>
</body>
</html>
