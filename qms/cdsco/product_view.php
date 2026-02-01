<?php
include "../../db.php";
include "../../includes/sidebar.php";

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM qms_cdsco_products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php?error=notfound");
    exit;
}

$statusClass = 'status-' . strtolower($product['registration_status']);
$riskClass = 'risk-' . strtolower($product['risk_classification']);

// Check expiry status
$expiryWarning = '';
if ($product['expiry_date']) {
    $expiryDate = new DateTime($product['expiry_date']);
    $today = new DateTime();
    $diff = $today->diff($expiryDate);
    if ($expiryDate < $today) {
        $expiryWarning = 'expired';
    } elseif ($diff->days <= 90) {
        $expiryWarning = 'expiring';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($product['product_name']) ?> - CDSCO Product</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .product-header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .product-header h1 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-submitted { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #383d41; }
        .status-under { background: #d1ecf1; color: #0c5460; }

        .risk-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .risk-a { background: #d4edda; color: #155724; }
        .risk-b { background: #fff3cd; color: #856404; }
        .risk-c { background: #f8d7da; color: #721c24; }
        .risk-d { background: #721c24; color: white; }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .detail-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-card h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            color: #333;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #666; font-size: 14px; }
        .detail-value { font-weight: 500; color: #333; }

        .warning-banner {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning-expired {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #721c24;
        }
        .warning-expiring {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        .timeline-item {
            position: relative;
            padding: 10px 0;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 14px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #007bff;
        }
        .timeline-item.completed::before { background: #28a745; }
        .timeline-item.pending::before { background: #ffc107; }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="products.php" class="btn btn-secondary">← Back to Products</a>
        <div style="display: flex; gap: 10px;">
            <a href="product_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit Product</a>
        </div>
    </div>

    <?php if ($expiryWarning === 'expired'): ?>
    <div class="warning-banner warning-expired">
        <strong>Registration Expired!</strong> This product's CDSCO registration expired on <?= date('d-M-Y', strtotime($product['expiry_date'])) ?>.
        Immediate renewal action is required.
    </div>
    <?php elseif ($expiryWarning === 'expiring'): ?>
    <div class="warning-banner warning-expiring">
        <strong>Expiring Soon!</strong> This product's registration will expire on <?= date('d-M-Y', strtotime($product['expiry_date'])) ?>.
        Please initiate renewal process.
    </div>
    <?php endif; ?>

    <div class="product-header">
        <h1>
            <?= htmlspecialchars($product['product_name']) ?>
            <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($product['registration_status']) ?></span>
            <span class="risk-badge <?= $riskClass ?>">Class <?= htmlspecialchars($product['risk_classification']) ?></span>
        </h1>
        <p style="margin: 0; color: #666;">
            <?= htmlspecialchars($product['category']) ?>
            <?php if ($product['registration_no']): ?>
                | Registration No: <strong><?= htmlspecialchars($product['registration_no']) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <div class="detail-grid">
        <div class="detail-card">
            <h3>Product Information</h3>
            <div class="detail-row">
                <span class="detail-label">Product Name</span>
                <span class="detail-value"><?= htmlspecialchars($product['product_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value"><?= htmlspecialchars($product['category']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Risk Classification</span>
                <span class="detail-value">
                    <span class="risk-badge <?= $riskClass ?>">Class <?= htmlspecialchars($product['risk_classification']) ?></span>
                </span>
            </div>
            <?php if ($product['intended_use']): ?>
            <div style="margin-top: 15px;">
                <span class="detail-label">Intended Use / Indications</span>
                <p style="margin: 5px 0 0 0; color: #333; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($product['intended_use'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <h3>Registration Details</h3>
            <div class="detail-row">
                <span class="detail-label">Registration Number</span>
                <span class="detail-value"><?= htmlspecialchars($product['registration_no'] ?: 'Not assigned') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($product['registration_status']) ?></span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Application Date</span>
                <span class="detail-value"><?= $product['application_date'] ? date('d-M-Y', strtotime($product['application_date'])) : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Approval Date</span>
                <span class="detail-value"><?= $product['approval_date'] ? date('d-M-Y', strtotime($product['approval_date'])) : '-' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Expiry Date</span>
                <span class="detail-value" style="<?= $expiryWarning ? 'color: red; font-weight: bold;' : '' ?>">
                    <?= $product['expiry_date'] ? date('d-M-Y', strtotime($product['expiry_date'])) : '-' ?>
                </span>
            </div>
        </div>

        <div class="detail-card">
            <h3>Registration Timeline</h3>
            <div class="timeline">
                <div class="timeline-item <?= $product['application_date'] ? 'completed' : 'pending' ?>">
                    <strong>Application Submitted</strong>
                    <div style="color: #666; font-size: 13px;">
                        <?= $product['application_date'] ? date('d-M-Y', strtotime($product['application_date'])) : 'Pending' ?>
                    </div>
                </div>
                <div class="timeline-item <?= $product['registration_status'] === 'Approved' ? 'completed' : 'pending' ?>">
                    <strong>CDSCO Review</strong>
                    <div style="color: #666; font-size: 13px;">
                        <?= $product['registration_status'] ?>
                    </div>
                </div>
                <div class="timeline-item <?= $product['approval_date'] ? 'completed' : 'pending' ?>">
                    <strong>Approval Received</strong>
                    <div style="color: #666; font-size: 13px;">
                        <?= $product['approval_date'] ? date('d-M-Y', strtotime($product['approval_date'])) : 'Pending' ?>
                    </div>
                </div>
                <div class="timeline-item pending">
                    <strong>Renewal Due</strong>
                    <div style="color: #666; font-size: 13px;">
                        <?= $product['expiry_date'] ? date('d-M-Y', strtotime($product['expiry_date'])) : 'TBD' ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($product['remarks']): ?>
        <div class="detail-card">
            <h3>Remarks / Notes</h3>
            <p style="margin: 0; line-height: 1.6; color: #333;">
                <?= nl2br(htmlspecialchars($product['remarks'])) ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <div style="margin-top: 20px; color: #666; font-size: 13px;">
        Created: <?= date('d-M-Y H:i', strtotime($product['created_at'])) ?>
        <?php if ($product['updated_at']): ?>
        | Last Updated: <?= date('d-M-Y H:i', strtotime($product['updated_at'])) ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
