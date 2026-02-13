<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('marketing_catalogs');

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

// Marketing Stats
$stats = [];

// Catalog stats
$stats['catalogs_total'] = safeCount($pdo, "SELECT COUNT(*) FROM catalogs");
$stats['catalogs_active'] = safeCount($pdo, "SELECT COUNT(*) FROM catalogs WHERE status = 'Active'");

// Campaign stats
$stats['campaigns_total'] = safeCount($pdo, "SELECT COUNT(*) FROM campaigns");
$stats['campaigns_active'] = safeCount($pdo, "SELECT COUNT(*) FROM campaigns WHERE status = 'Active'");
$stats['campaigns_scheduled'] = safeCount($pdo, "SELECT COUNT(*) FROM campaigns WHERE status = 'Scheduled'");
$stats['campaigns_completed'] = safeCount($pdo, "SELECT COUNT(*) FROM campaigns WHERE status = 'Completed'");

// Lead source stats (from CRM)
$stats['leads_from_marketing'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_source IN ('Website', 'Social Media', 'Email Campaign', 'WhatsApp')");
$stats['leads_this_month'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");

// Recent campaigns
$recent_campaigns = safeQuery($pdo, "
    SELECT id, campaign_name, campaign_type, status, start_date, end_date, created_at
    FROM campaigns
    ORDER BY created_at DESC
    LIMIT 10
");

// Active campaigns
$active_campaigns = safeQuery($pdo, "
    SELECT id, campaign_name, campaign_type, start_date, end_date
    FROM campaigns
    WHERE status = 'Active'
    ORDER BY start_date
    LIMIT 10
");

// Recent catalogs
$recent_catalogs = safeQuery($pdo, "
    SELECT id, catalog_name, category, status, created_at
    FROM catalogs
    ORDER BY created_at DESC
    LIMIT 10
");

// Lead sources breakdown
$lead_sources = safeQuery($pdo, "
    SELECT lead_source, COUNT(*) as count
    FROM crm_leads
    WHERE lead_source IS NOT NULL AND lead_source != ''
    GROUP BY lead_source
    ORDER BY count DESC
    LIMIT 8
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Marketing Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
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
        .module-header h1 { margin: 0; font-size: 1.8em; color: #7c2d12; }
        .module-header p { margin: 5px 0 0; opacity: 0.9; color: #7c2d12; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #ff6b6b;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.danger { border-left-color: #e74c3c; }

        .stat-icon { font-size: 2em; margin-bottom: 10px; }
        .stat-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }

        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-panel h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: #7c2d12;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }
        .quick-action-btn .action-icon { font-size: 1.6em; margin-bottom: 8px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .data-table tr:hover { background: #f8f9fa; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-scheduled { background: #e3f2fd; color: #1565c0; }
        .status-completed { background: #fafafa; color: #616161; }
        .status-draft { background: #fff3e0; color: #ef6c00; }

        .section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        body.dark .stat-card { background: #2c3e50; }
        body.dark .stat-value { color: #ecf0f1; }
        body.dark .dashboard-panel { background: #2c3e50; }
        body.dark .dashboard-panel h3 { color: #ecf0f1; }
        body.dark .data-table th { background: #34495e; color: #ecf0f1; }
        body.dark .data-table td { border-bottom-color: #34495e; }
        body.dark .data-table tr:hover { background: #34495e; }
    </style>
</head>
<body>

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

<div class="content">
    <!-- Module Header -->
    <div class="module-header">
        <?php if (!empty($settings['logo_path'])): ?>
            <?php
                $logo_path = $settings['logo_path'];
                if (!preg_match('~^(https?:|/)~', $logo_path)) {
                    $logo_path = '/' . $logo_path;
                }
            ?>
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" onerror="this.style.display='none'">
        <?php endif; ?>
        <div>
            <h1>Marketing Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/marketing/campaign_add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New Campaign
        </a>
        <a href="/marketing/catalog_add.php" class="quick-action-btn">
            <div class="action-icon">📚</div>
            New Catalog
        </a>
        <a href="/marketing/whatsapp.php" class="quick-action-btn">
            <div class="action-icon">💬</div>
            WhatsApp
        </a>
        <a href="/marketing/analytics.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Analytics
        </a>
    </div>

    <!-- Statistics -->
    <div class="section-title">Marketing Overview</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">📢</div>
            <div class="stat-value"><?= $stats['campaigns_total'] ?></div>
            <div class="stat-label">Total Campaigns</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">▶️</div>
            <div class="stat-value"><?= $stats['campaigns_active'] ?></div>
            <div class="stat-label">Active Campaigns</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?= $stats['campaigns_scheduled'] ?></div>
            <div class="stat-label">Scheduled</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-value"><?= $stats['catalogs_total'] ?></div>
            <div class="stat-label">Total Catalogs</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= $stats['catalogs_active'] ?></div>
            <div class="stat-label">Active Catalogs</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $stats['leads_this_month'] ?></div>
            <div class="stat-label">Leads (Month)</div>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Active Campaigns -->
        <div class="dashboard-panel">
            <h3>▶️ Active Campaigns</h3>
            <?php if (empty($active_campaigns)): ?>
                <p style="color: #7f8c8d;">No active campaigns.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Type</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_campaigns as $campaign): ?>
                        <tr>
                            <td><a href="/marketing/campaign_view.php?id=<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['campaign_name']) ?></a></td>
                            <td><?= htmlspecialchars($campaign['campaign_type'] ?? 'N/A') ?></td>
                            <td><?= $campaign['end_date'] ? date('d M Y', strtotime($campaign['end_date'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Lead Sources -->
        <div class="dashboard-panel">
            <h3>📊 Lead Sources</h3>
            <?php if (empty($lead_sources)): ?>
                <p style="color: #7f8c8d;">No lead source data available.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Leads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lead_sources as $source): ?>
                        <tr>
                            <td><?= htmlspecialchars($source['lead_source']) ?></td>
                            <td><?= $source['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Campaigns -->
        <div class="dashboard-panel">
            <h3>📢 Recent Campaigns</h3>
            <?php if (empty($recent_campaigns)): ?>
                <p style="color: #7f8c8d;">No campaigns found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_campaigns as $campaign): ?>
                        <tr>
                            <td><a href="/marketing/campaign_view.php?id=<?= $campaign['id'] ?>"><?= htmlspecialchars($campaign['campaign_name']) ?></a></td>
                            <td><?= htmlspecialchars($campaign['campaign_type'] ?? 'N/A') ?></td>
                            <td><span class="status-badge status-<?= strtolower($campaign['status']) ?>"><?= $campaign['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Catalogs -->
        <div class="dashboard-panel">
            <h3>📚 Recent Catalogs</h3>
            <?php if (empty($recent_catalogs)): ?>
                <p style="color: #7f8c8d;">No catalogs found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Catalog</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_catalogs as $catalog): ?>
                        <tr>
                            <td><a href="/marketing/catalog_view.php?id=<?= $catalog['id'] ?>"><?= htmlspecialchars($catalog['catalog_name']) ?></a></td>
                            <td><?= htmlspecialchars($catalog['category'] ?? 'N/A') ?></td>
                            <td><span class="status-badge status-<?= strtolower($catalog['status']) ?>"><?= $catalog['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="section-title">Navigate to</div>
    <div class="quick-actions-grid">
        <a href="/marketing/catalogs.php" class="quick-action-btn">
            <div class="action-icon">📚</div>
            All Catalogs
        </a>
        <a href="/marketing/campaigns.php" class="quick-action-btn">
            <div class="action-icon">📢</div>
            All Campaigns
        </a>
        <a href="/marketing/whatsapp.php" class="quick-action-btn">
            <div class="action-icon">💬</div>
            WhatsApp
        </a>
        <a href="/marketing/analytics.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            Analytics
        </a>
    </div>
</div>

</body>
</html>
