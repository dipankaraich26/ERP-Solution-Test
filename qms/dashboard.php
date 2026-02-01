<?php
/**
 * QMS (Quality Management System) Dashboard
 * Overview of CDSCO, ISO, and ICMED compliance status
 */
include "../db.php";
include "../includes/auth.php";

// Check if tables exist, redirect to installer if not
try {
    $pdo->query("SELECT 1 FROM qms_cdsco_products LIMIT 1");
} catch (PDOException $e) {
    header("Location: install.php");
    exit;
}

// Get company settings
$settings = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];

// Safe count function
function safeCount($pdo, $query) {
    try {
        return $pdo->query($query)->fetchColumn() ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Safe query function
function safeQuery($pdo, $query) {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// ============ CDSCO STATS ============
$cdsco_stats = [];
$cdsco_stats['total_products'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_products");
$cdsco_stats['approved'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_products WHERE status = 'Approved'");
$cdsco_stats['pending'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_products WHERE status IN ('Draft', 'Submitted', 'Under Review', 'Query Raised')");
$cdsco_stats['expiring_soon'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_products WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status = 'Approved'");

$cdsco_stats['total_licenses'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_licenses");
$cdsco_stats['active_licenses'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_licenses WHERE status = 'Approved'");
$cdsco_stats['license_expiring'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status = 'Approved'");

$cdsco_stats['open_adverse_events'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_cdsco_adverse_events WHERE status IN ('Open', 'Under Investigation')");

// ============ ISO STATS ============
$iso_stats = [];
$iso_stats['total_certs'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_certifications");
$iso_stats['certified'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_certifications WHERE status = 'Certified'");
$iso_stats['audit_due'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_certifications WHERE next_audit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");

$iso_stats['total_audits'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_audits");
$iso_stats['planned_audits'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_audits WHERE status = 'Planned'");
$iso_stats['completed_audits'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_iso_audits WHERE status = 'Completed'");

$iso_stats['open_ncr'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_ncr WHERE status NOT IN ('Closed')");
$iso_stats['major_nc'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_ncr WHERE nc_type = 'Major' AND status NOT IN ('Closed')");

$iso_stats['open_capa'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_capa WHERE status NOT IN ('Closed', 'Cancelled')");
$iso_stats['overdue_capa'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_capa WHERE target_date < CURDATE() AND status NOT IN ('Closed', 'Cancelled')");

$iso_stats['active_docs'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_documents WHERE status IN ('Approved', 'Effective')");
$iso_stats['review_due'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_documents WHERE review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Effective'");

// ============ ICMED STATS ============
$icmed_stats = [];
$icmed_stats['total_certs'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_icmed_certifications");
$icmed_stats['certified'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_icmed_certifications WHERE status = 'Certified'");
$icmed_stats['pending'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_icmed_certifications WHERE status NOT IN ('Certified', 'Withdrawn', 'Expired')");
$icmed_stats['expiring'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_icmed_certifications WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status = 'Certified'");
$icmed_stats['audit_scheduled'] = safeCount($pdo, "SELECT COUNT(*) FROM qms_icmed_audits WHERE status = 'Scheduled'");

// Recent activities
$recent_ncr = safeQuery($pdo, "SELECT * FROM qms_ncr ORDER BY created_at DESC LIMIT 5");
$recent_capa = safeQuery($pdo, "SELECT * FROM qms_capa ORDER BY created_at DESC LIMIT 5");
$upcoming_audits = safeQuery($pdo, "SELECT * FROM qms_iso_audits WHERE status = 'Planned' ORDER BY planned_date LIMIT 5");

// Expiring items
$expiring_products = safeQuery($pdo, "SELECT * FROM qms_cdsco_products WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status = 'Approved' ORDER BY expiry_date LIMIT 5");
$expiring_licenses = safeQuery($pdo, "SELECT * FROM qms_cdsco_licenses WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status = 'Approved' ORDER BY expiry_date LIMIT 5");

include "../includes/sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>QMS Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .module-header img {
            max-height: 60px;
            max-width: 150px;
            background: white;
            padding: 8px;
            border-radius: 8px;
            object-fit: contain;
        }
        .module-header h1 { margin: 0; font-size: 1.8em; }
        .module-header p { margin: 5px 0 0; opacity: 0.9; }

        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .compliance-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .compliance-card-header {
            padding: 15px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .compliance-card-header h3 {
            margin: 0;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .compliance-card-header .badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .compliance-card.cdsco .compliance-card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .compliance-card.iso .compliance-card-header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .compliance-card.icmed .compliance-card-header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .compliance-card-body {
            padding: 20px;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .stat-row:last-child { border-bottom: none; }
        .stat-row .label { color: #666; }
        .stat-row .value { font-weight: bold; color: #2c3e50; }
        .stat-row .value.success { color: #27ae60; }
        .stat-row .value.warning { color: #f39c12; }
        .stat-row .value.danger { color: #e74c3c; }

        .compliance-card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .compliance-card-footer a {
            padding: 6px 12px;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.85em;
            transition: all 0.2s;
        }
        .compliance-card-footer a:hover {
            background: #dee2e6;
        }

        .alerts-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .alerts-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .alert-item.danger {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .alert-item.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .alert-item .icon { font-size: 1.3em; margin-right: 15px; }
        .alert-item .message { flex: 1; color: #333; }
        .alert-item .action {
            padding: 5px 12px;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85em;
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            font-size: 1em;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #11998e;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .data-table th { font-weight: 600; color: #666; }
        .data-table tr:hover { background: #f9f9f9; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 15px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-weight: 600;
            font-size: 0.85em;
            min-height: 90px;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
        }
        .quick-action-btn .icon { font-size: 1.8em; margin-bottom: 8px; }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-certified { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-open { background: #f8d7da; color: #721c24; }
        .status-planned { background: #cce5ff; color: #004085; }

        @media (max-width: 1200px) {
            .compliance-grid { grid-template-columns: 1fr; }
            .dashboard-row { grid-template-columns: 1fr; }
        }

        body.dark .compliance-card { background: #2c3e50; }
        body.dark .compliance-card-body { color: #ecf0f1; }
        body.dark .stat-row .label { color: #bdc3c7; }
        body.dark .stat-row .value { color: #ecf0f1; }
        body.dark .compliance-card-footer { background: #34495e; }
        body.dark .compliance-card-footer a { background: #2c3e50; color: #ecf0f1; }
        body.dark .alerts-section, body.dark .dashboard-panel { background: #2c3e50; color: #ecf0f1; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { color: #bdc3c7; }
        body.dark .data-table td { border-bottom-color: #34495e; }
    </style>
</head>
<body>

<div class="content">
    <!-- Module Header -->
    <div class="module-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php $logo_path = $settings['logo_path']; if (!preg_match('~^(https?:|/)~', $logo_path)) { $logo_path = '/' . $logo_path; } ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1>Quality Management System</h1>
            <p>CDSCO | ISO | ICMED Compliance Management</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="cdsco/products.php" class="quick-action-btn">
            <span class="icon">📋</span>
            CDSCO Products
        </a>
        <a href="cdsco/licenses.php" class="quick-action-btn">
            <span class="icon">📜</span>
            Licenses
        </a>
        <a href="iso/audits.php" class="quick-action-btn">
            <span class="icon">🔍</span>
            ISO Audits
        </a>
        <a href="iso/ncr.php" class="quick-action-btn">
            <span class="icon">⚠️</span>
            NCR
        </a>
        <a href="iso/capa.php" class="quick-action-btn">
            <span class="icon">🔧</span>
            CAPA
        </a>
        <a href="iso/documents.php" class="quick-action-btn">
            <span class="icon">📁</span>
            Documents
        </a>
        <a href="icmed/certifications.php" class="quick-action-btn">
            <span class="icon">✅</span>
            ICMED Certs
        </a>
    </div>

    <!-- Alerts Section -->
    <?php if ($iso_stats['overdue_capa'] > 0 || $iso_stats['major_nc'] > 0 || $cdsco_stats['expiring_soon'] > 0 || $icmed_stats['expiring'] > 0): ?>
    <div class="alerts-section">
        <h3><span>🚨</span> Action Required</h3>

        <?php if ($iso_stats['overdue_capa'] > 0): ?>
        <div class="alert-item danger">
            <span class="icon">⏰</span>
            <span class="message"><strong><?= $iso_stats['overdue_capa'] ?> Overdue CAPA</strong> - Immediate attention required</span>
            <a href="iso/capa.php?filter=overdue" class="action">View</a>
        </div>
        <?php endif; ?>

        <?php if ($iso_stats['major_nc'] > 0): ?>
        <div class="alert-item danger">
            <span class="icon">❌</span>
            <span class="message"><strong><?= $iso_stats['major_nc'] ?> Major Non-Conformances</strong> - Open and pending closure</span>
            <a href="iso/ncr.php?filter=major" class="action">View</a>
        </div>
        <?php endif; ?>

        <?php if ($cdsco_stats['expiring_soon'] > 0): ?>
        <div class="alert-item">
            <span class="icon">📋</span>
            <span class="message"><strong><?= $cdsco_stats['expiring_soon'] ?> CDSCO Registrations</strong> expiring in next 90 days</span>
            <a href="cdsco/products.php?filter=expiring" class="action">View</a>
        </div>
        <?php endif; ?>

        <?php if ($cdsco_stats['license_expiring'] > 0): ?>
        <div class="alert-item">
            <span class="icon">📜</span>
            <span class="message"><strong><?= $cdsco_stats['license_expiring'] ?> Licenses</strong> expiring in next 90 days</span>
            <a href="cdsco/licenses.php?filter=expiring" class="action">View</a>
        </div>
        <?php endif; ?>

        <?php if ($icmed_stats['expiring'] > 0): ?>
        <div class="alert-item">
            <span class="icon">✅</span>
            <span class="message"><strong><?= $icmed_stats['expiring'] ?> ICMED Certifications</strong> expiring in next 90 days</span>
            <a href="icmed/certifications.php?filter=expiring" class="action">View</a>
        </div>
        <?php endif; ?>

        <?php if ($cdsco_stats['open_adverse_events'] > 0): ?>
        <div class="alert-item info">
            <span class="icon">⚕️</span>
            <span class="message"><strong><?= $cdsco_stats['open_adverse_events'] ?> Open Adverse Events</strong> - Under investigation</span>
            <a href="cdsco/adverse_events.php" class="action">View</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Compliance Cards -->
    <div class="compliance-grid">
        <!-- CDSCO Card -->
        <div class="compliance-card cdsco">
            <div class="compliance-card-header">
                <h3><span>🏛️</span> CDSCO</h3>
                <span class="badge">Regulatory</span>
            </div>
            <div class="compliance-card-body">
                <div class="stat-row">
                    <span class="label">Registered Products</span>
                    <span class="value"><?= $cdsco_stats['total_products'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Approved</span>
                    <span class="value success"><?= $cdsco_stats['approved'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Pending Approval</span>
                    <span class="value warning"><?= $cdsco_stats['pending'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Active Licenses</span>
                    <span class="value"><?= $cdsco_stats['active_licenses'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Expiring Soon</span>
                    <span class="value <?= $cdsco_stats['expiring_soon'] > 0 ? 'danger' : 'success' ?>"><?= $cdsco_stats['expiring_soon'] ?></span>
                </div>
            </div>
            <div class="compliance-card-footer">
                <a href="cdsco/products.php">Products</a>
                <a href="cdsco/licenses.php">Licenses</a>
                <a href="cdsco/adverse_events.php">Adverse Events</a>
                <a href="cdsco/add_product.php">+ Add Product</a>
            </div>
        </div>

        <!-- ISO Card -->
        <div class="compliance-card iso">
            <div class="compliance-card-header">
                <h3><span>🌍</span> ISO</h3>
                <span class="badge">International</span>
            </div>
            <div class="compliance-card-body">
                <div class="stat-row">
                    <span class="label">ISO Standards</span>
                    <span class="value"><?= $iso_stats['total_certs'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Certified</span>
                    <span class="value success"><?= $iso_stats['certified'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Open NCRs</span>
                    <span class="value <?= $iso_stats['open_ncr'] > 0 ? 'warning' : 'success' ?>"><?= $iso_stats['open_ncr'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Open CAPAs</span>
                    <span class="value <?= $iso_stats['open_capa'] > 0 ? 'warning' : 'success' ?>"><?= $iso_stats['open_capa'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Controlled Documents</span>
                    <span class="value"><?= $iso_stats['active_docs'] ?></span>
                </div>
            </div>
            <div class="compliance-card-footer">
                <a href="iso/certifications.php">Certifications</a>
                <a href="iso/audits.php">Audits</a>
                <a href="iso/ncr.php">NCR</a>
                <a href="iso/capa.php">CAPA</a>
                <a href="iso/documents.php">Documents</a>
            </div>
        </div>

        <!-- ICMED Card -->
        <div class="compliance-card icmed">
            <div class="compliance-card-header">
                <h3><span>🇮🇳</span> ICMED</h3>
                <span class="badge">India MDR</span>
            </div>
            <div class="compliance-card-body">
                <div class="stat-row">
                    <span class="label">Total Certifications</span>
                    <span class="value"><?= $icmed_stats['total_certs'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Certified</span>
                    <span class="value success"><?= $icmed_stats['certified'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">In Progress</span>
                    <span class="value warning"><?= $icmed_stats['pending'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Expiring Soon</span>
                    <span class="value <?= $icmed_stats['expiring'] > 0 ? 'danger' : 'success' ?>"><?= $icmed_stats['expiring'] ?></span>
                </div>
                <div class="stat-row">
                    <span class="label">Audits Scheduled</span>
                    <span class="value"><?= $icmed_stats['audit_scheduled'] ?></span>
                </div>
            </div>
            <div class="compliance-card-footer">
                <a href="icmed/certifications.php">Certifications</a>
                <a href="icmed/audits.php">Factory Audits</a>
                <a href="icmed/add_certification.php">+ New Application</a>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="dashboard-row">
        <!-- Open NCRs -->
        <div class="dashboard-panel">
            <h3>Recent Non-Conformances</h3>
            <?php if (empty($recent_ncr)): ?>
                <p style="color: #27ae60; text-align: center; padding: 20px;">No open non-conformances</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>NCR #</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_ncr as $ncr): ?>
                    <tr>
                        <td><a href="iso/ncr_view.php?id=<?= $ncr['id'] ?>"><?= htmlspecialchars($ncr['ncr_no']) ?></a></td>
                        <td><span class="status-badge status-<?= $ncr['nc_type'] == 'Major' ? 'open' : 'pending' ?>"><?= $ncr['nc_type'] ?></span></td>
                        <td><?= htmlspecialchars($ncr['source']) ?></td>
                        <td><?= $ncr['status'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Open CAPAs -->
        <div class="dashboard-panel">
            <h3>Recent CAPAs</h3>
            <?php if (empty($recent_capa)): ?>
                <p style="color: #27ae60; text-align: center; padding: 20px;">No open CAPAs</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CAPA #</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_capa as $capa): ?>
                    <tr>
                        <td><a href="iso/capa_view.php?id=<?= $capa['id'] ?>"><?= htmlspecialchars($capa['capa_no']) ?></a></td>
                        <td><?= $capa['capa_type'] ?></td>
                        <td><span class="status-badge status-<?= $capa['priority'] == 'Critical' || $capa['priority'] == 'High' ? 'open' : 'pending' ?>"><?= $capa['priority'] ?></span></td>
                        <td><?= $capa['status'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Upcoming Audits -->
        <div class="dashboard-panel">
            <h3>Upcoming Audits</h3>
            <?php if (empty($upcoming_audits)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No upcoming audits scheduled</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Audit #</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_audits as $audit): ?>
                    <tr>
                        <td><a href="iso/audit_view.php?id=<?= $audit['id'] ?>"><?= htmlspecialchars($audit['audit_no']) ?></a></td>
                        <td><?= $audit['audit_type'] ?></td>
                        <td><?= $audit['planned_date'] ? date('d M Y', strtotime($audit['planned_date'])) : '-' ?></td>
                        <td><span class="status-badge status-planned"><?= $audit['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Expiring Certifications -->
        <div class="dashboard-panel">
            <h3>Expiring Soon (90 Days)</h3>
            <?php if (empty($expiring_products) && empty($expiring_licenses)): ?>
                <p style="color: #27ae60; text-align: center; padding: 20px;">No items expiring soon</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring_products as $item): ?>
                    <tr>
                        <td><a href="cdsco/product_view.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['product_name']) ?></a></td>
                        <td>CDSCO Product</td>
                        <td style="color: #e74c3c;"><?= date('d M Y', strtotime($item['expiry_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($expiring_licenses as $item): ?>
                    <tr>
                        <td><a href="cdsco/license_view.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['facility_name']) ?></a></td>
                        <td><?= $item['license_type'] ?></td>
                        <td style="color: #e74c3c;"><?= date('d M Y', strtotime($item['expiry_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

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
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "☀️ Light Mode" : "🌙 Dark Mode";
    });
}
</script>

</body>
</html>
