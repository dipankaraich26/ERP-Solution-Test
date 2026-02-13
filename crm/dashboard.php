<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

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

// CRM Stats
$stats = [];
$stats['leads_total'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads");
$stats['leads_hot'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'");
$stats['leads_warm'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'warm'");
$stats['leads_cold'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'cold'");
$stats['leads_converted'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'converted'");
$stats['leads_lost'] = safeCount($pdo, "SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'lost'");

// Customer stats
$stats['customers_total'] = safeCount($pdo, "SELECT COUNT(*) FROM customers");
$stats['customers_active'] = safeCount($pdo, "SELECT COUNT(*) FROM customers WHERE status = 'Active'");

// Quote stats
$stats['quotes_total'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master");
$stats['quotes_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'pending'");
$stats['quotes_approved'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'approved'");
$stats['quotes_rejected'] = safeCount($pdo, "SELECT COUNT(*) FROM quote_master WHERE status = 'rejected'");

// Sales Order stats
$stats['so_total'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders");
$stats['so_open'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'open'");
$stats['so_released'] = safeCount($pdo, "SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE status = 'released'");

// Invoice stats
$stats['invoices_total'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices");
$stats['invoices_pending'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'pending'");
$stats['invoices_paid'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'paid'");
$stats['invoices_overdue'] = safeCount($pdo, "SELECT COUNT(*) FROM invoices WHERE payment_status = 'pending' AND due_date < CURDATE()");

// Revenue stats (this month)
$stats['revenue_this_month'] = safeCount($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stats['pending_amount'] = safeCount($pdo, "SELECT COALESCE(SUM(amount_due), 0) FROM invoices WHERE payment_status = 'pending'");

// ===========================================
// SALES OVERVIEW - Revenue Value by Lead Status
// Chain: Lead (lead_no) → Quote/PI (reference) → quote_items (total_amount)
// ===========================================
$salesOverview = [
    'total' => 0,
    'converted' => 0,
    'hot' => 0,
    'warm' => 0,
    'cold' => 0
];

try {
    $salesByStatusStmt = $pdo->query("
        SELECT
            l.lead_status,
            COALESCE(SUM(qi.total_amount), 0) as total_value
        FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN quote_items qi ON qi.quote_id = q.id
        WHERE l.lead_status IN ('converted', 'hot', 'warm', 'cold')
        GROUP BY l.lead_status
    ");
    $salesByStatus = $salesByStatusStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($salesByStatus as $row) {
        $status = strtolower($row['lead_status']);
        $value = (float)$row['total_value'];
        if (isset($salesOverview[$status])) {
            $salesOverview[$status] = $value;
        }
        $salesOverview['total'] += $value;
    }
} catch (Exception $e) {
    // Tables may not exist or query error
}

// ===========================================
// PERFORMANCE MONITORING
// ===========================================

// Get selected month filter (default: current month)
$perf_month = $_GET['perf_month'] ?? date('Y-m');
$perf_year = substr($perf_month, 0, 4);
$perf_month_num = substr($perf_month, 5, 2);

// Generate list of months for dropdown (last 12 months + current)
$monthOptions = [];
for ($i = 0; $i <= 12; $i++) {
    $timestamp = strtotime("-$i months");
    $monthOptions[] = [
        'value' => date('Y-m', $timestamp),
        'label' => date('F Y', $timestamp)
    ];
}

// Performance by Lead Owner (with converted/sold value)
// Note: assigned_user_id in crm_leads references employees table, not users table
$perfByOwner = [];
try {
    $ownerStmt = $pdo->prepare("
        SELECT
            e.id as user_id,
            CONCAT(e.first_name, ' ', e.last_name) as owner_name,
            e.department,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN 1 ELSE 0 END) as hot_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'warm' THEN 1 ELSE 0 END) as warm_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'cold' THEN 1 ELSE 0 END) as cold_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN 1 ELSE 0 END) as converted_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'lost' THEN 1 ELSE 0 END) as lost_leads,
            COALESCE(SUM(sub.lead_value), 0) as total_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN sub.lead_value ELSE 0 END), 0) as converted_value
        FROM employees e
        INNER JOIN crm_leads l ON l.assigned_user_id = e.id
            AND YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
        LEFT JOIN (
            SELECT l2.id as lead_id, COALESCE(SUM(qi.total_amount), 0) as lead_value
            FROM crm_leads l2
            LEFT JOIN quote_master q ON q.reference = l2.lead_no
            LEFT JOIN quote_items qi ON qi.quote_id = q.id
            GROUP BY l2.id
        ) sub ON sub.lead_id = l.id
        WHERE e.status = 'Active'
        GROUP BY e.id, e.first_name, e.last_name, e.department
        HAVING total_leads > 0
        ORDER BY converted_value DESC, total_leads DESC
    ");
    $ownerStmt->execute([$perf_year, $perf_month_num]);
    $perfByOwner = $ownerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Query may fail if tables don't exist
}

// Performance by Lead Status (with month filter)
$perfByStatus = [];
try {
    $statusStmt = $pdo->prepare("
        SELECT
            l.lead_status,
            COUNT(l.id) as total_leads,
            COALESCE(SUM(qi.total_amount), 0) as total_value
        FROM crm_leads l
        LEFT JOIN quote_master q ON q.reference = l.lead_no
        LEFT JOIN quote_items qi ON qi.quote_id = q.id
        WHERE YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
        GROUP BY l.lead_status
        ORDER BY
            CASE l.lead_status
                WHEN 'converted' THEN 1
                WHEN 'hot' THEN 2
                WHEN 'warm' THEN 3
                WHEN 'cold' THEN 4
                WHEN 'lost' THEN 5
            END
    ");
    $statusStmt->execute([$perf_year, $perf_month_num]);
    $perfByStatus = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Query may fail
}

// Calculate totals for the selected month
$perfTotals = [
    'total_leads' => 0,
    'total_value' => 0,
    'converted_leads' => 0,
    'converted_value' => 0
];
foreach ($perfByStatus as $row) {
    $perfTotals['total_leads'] += (int)$row['total_leads'];
    $perfTotals['total_value'] += (float)$row['total_value'];
    if (strtolower($row['lead_status']) === 'converted') {
        $perfTotals['converted_leads'] = (int)$row['total_leads'];
        $perfTotals['converted_value'] = (float)$row['total_value'];
    }
}

// Calculate conversion rate
$perfTotals['conversion_rate'] = $perfTotals['total_leads'] > 0
    ? round(($perfTotals['converted_leads'] / $perfTotals['total_leads']) * 100, 1)
    : 0;

// B2B Dealer Performance (month-wise)
$perfByDealer = [];
try {
    $dealerStmt = $pdo->prepare("
        SELECT
            l.company_name as dealer_name,
            l.contact_person,
            l.city,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN 1 ELSE 0 END) as hot_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'warm' THEN 1 ELSE 0 END) as warm_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN 1 ELSE 0 END) as converted_leads,
            COALESCE(SUM(sub.lead_value), 0) as total_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN sub.lead_value ELSE 0 END), 0) as converted_value
        FROM crm_leads l
        LEFT JOIN (
            SELECT l2.id as lead_id, COALESCE(SUM(qi.total_amount), 0) as lead_value
            FROM crm_leads l2
            LEFT JOIN quote_master q ON q.reference = l2.lead_no
            LEFT JOIN quote_items qi ON qi.quote_id = q.id
            GROUP BY l2.id
        ) sub ON sub.lead_id = l.id
        WHERE l.customer_type = 'B2B'
          AND l.company_name IS NOT NULL
          AND l.company_name != ''
          AND YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
        GROUP BY l.company_name, l.contact_person, l.city
        HAVING total_leads > 0
        ORDER BY converted_value DESC, total_value DESC, total_leads DESC
        LIMIT 15
    ");
    $dealerStmt->execute([$perf_year, $perf_month_num]);
    $perfByDealer = $dealerStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Query may fail if tables don't exist
}

// B2B totals for the month
$b2bTotals = [
    'total_leads' => 0,
    'total_value' => 0,
    'converted_leads' => 0,
    'converted_value' => 0
];
foreach ($perfByDealer as $row) {
    $b2bTotals['total_leads'] += (int)$row['total_leads'];
    $b2bTotals['total_value'] += (float)$row['total_value'];
    $b2bTotals['converted_leads'] += (int)$row['converted_leads'];
    $b2bTotals['converted_value'] += (float)$row['converted_value'];
}

// Market Classification Performance (month-wise) - Sales by market classification with detailed breakdown
$perfByMarket = [];
$marketTotals = ['total_leads' => 0, 'total_value' => 0, 'converted_leads' => 0, 'converted_value' => 0, 'hot_leads' => 0, 'warm_leads' => 0, 'cold_leads' => 0, 'lost_leads' => 0];
try {
    $marketStmt = $pdo->prepare("
        SELECT
            COALESCE(NULLIF(l.market_classification, ''), 'Not Specified') as market_classification,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN 1 ELSE 0 END) as hot_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'warm' THEN 1 ELSE 0 END) as warm_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'cold' THEN 1 ELSE 0 END) as cold_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN 1 ELSE 0 END) as converted_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'lost' THEN 1 ELSE 0 END) as lost_leads,
            COALESCE(SUM(sub.lead_value), 0) as total_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN sub.lead_value ELSE 0 END), 0) as converted_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN sub.lead_value ELSE 0 END), 0) as hot_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'warm' THEN sub.lead_value ELSE 0 END), 0) as warm_value
        FROM crm_leads l
        LEFT JOIN (
            SELECT l2.id as lead_id, COALESCE(SUM(qi.total_amount), 0) as lead_value
            FROM crm_leads l2
            LEFT JOIN quote_master q ON q.reference = l2.lead_no
            LEFT JOIN quote_items qi ON qi.quote_id = q.id
            GROUP BY l2.id
        ) sub ON sub.lead_id = l.id
        WHERE YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
        GROUP BY COALESCE(NULLIF(l.market_classification, ''), 'Not Specified')
        ORDER BY converted_value DESC, total_value DESC
    ");
    $marketStmt->execute([$perf_year, $perf_month_num]);
    $perfByMarket = $marketStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    foreach ($perfByMarket as $row) {
        $marketTotals['total_leads'] += (int)$row['total_leads'];
        $marketTotals['total_value'] += (float)$row['total_value'];
        $marketTotals['converted_leads'] += (int)$row['converted_leads'];
        $marketTotals['converted_value'] += (float)$row['converted_value'];
        $marketTotals['hot_leads'] += (int)$row['hot_leads'];
        $marketTotals['warm_leads'] += (int)$row['warm_leads'];
        $marketTotals['cold_leads'] += (int)$row['cold_leads'];
        $marketTotals['lost_leads'] += (int)$row['lost_leads'];
    }
} catch (Exception $e) {
    // Query may fail if column doesn't exist
}

// Product-wise Performance (month-wise) - Sales from converted leads
$perfByProduct = [];
$productTotals = ['qty_sold' => 0, 'total_value' => 0, 'lead_count' => 0];
try {
    $productStmt = $pdo->prepare("
        SELECT
            qi.part_no,
            COALESCE(pm.description, qi.part_no) as product_name,
            pm.uom,
            COUNT(DISTINCT l.id) as lead_count,
            SUM(qi.qty) as qty_sold,
            SUM(qi.total_amount) as total_value
        FROM crm_leads l
        JOIN quote_master q ON q.reference = l.lead_no
        JOIN quote_items qi ON qi.quote_id = q.id
        LEFT JOIN part_master pm ON pm.part_no = qi.part_no
        WHERE LOWER(l.lead_status) = 'converted'
          AND YEAR(l.created_at) = ? AND MONTH(l.created_at) = ?
        GROUP BY qi.part_no, pm.description, pm.uom
        ORDER BY total_value DESC, qty_sold DESC
        LIMIT 20
    ");
    $productStmt->execute([$perf_year, $perf_month_num]);
    $perfByProduct = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    foreach ($perfByProduct as $row) {
        $productTotals['qty_sold'] += (float)$row['qty_sold'];
        $productTotals['total_value'] += (float)$row['total_value'];
        $productTotals['lead_count'] += (int)$row['lead_count'];
    }
} catch (Exception $e) {
    // Query may fail if tables don't exist
}

// ===========================================
// REGION-WISE & STATE-WISE LEADS
// ===========================================

// Define Indian regions by grouping states
$indiaRegions = [
    'North' => ['Delhi', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Ladakh', 'Punjab', 'Rajasthan', 'Uttarakhand', 'Uttar Pradesh', 'Chandigarh'],
    'South' => ['Andhra Pradesh', 'Karnataka', 'Kerala', 'Tamil Nadu', 'Telangana', 'Puducherry', 'Lakshadweep'],
    'East' => ['Bihar', 'Jharkhand', 'Odisha', 'West Bengal'],
    'West' => ['Goa', 'Gujarat', 'Maharashtra', 'Dadra and Nagar Haveli and Daman and Diu'],
    'Central' => ['Chhattisgarh', 'Madhya Pradesh'],
    'Northeast' => ['Arunachal Pradesh', 'Assam', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Sikkim', 'Tripura', 'Andaman and Nicobar Islands'],
];

// Build reverse lookup: state => region
$stateToRegion = [];
foreach ($indiaRegions as $region => $states) {
    foreach ($states as $state) {
        $stateToRegion[strtolower(trim($state))] = $region;
    }
}

// Region colors for visual display
$regionColors = [
    'North' => ['bg' => '#e3f2fd', 'border' => '#1565c0', 'icon' => '🏔️'],
    'South' => ['bg' => '#e8f5e9', 'border' => '#2e7d32', 'icon' => '🌴'],
    'East' => ['bg' => '#fff3e0', 'border' => '#ef6c00', 'icon' => '🌅'],
    'West' => ['bg' => '#fce4ec', 'border' => '#c62828', 'icon' => '🏖️'],
    'Central' => ['bg' => '#f3e5f5', 'border' => '#7b1fa2', 'icon' => '🏛️'],
    'Northeast' => ['bg' => '#e0f2f1', 'border' => '#00695c', 'icon' => '🍃'],
];

// Fetch leads grouped by state (all-time for overall view)
$leadsByState = [];
$leadsByRegion = [];
try {
    $stateStmt = $pdo->query("
        SELECT
            COALESCE(NULLIF(TRIM(l.state), ''), 'Not Specified') as state_name,
            COUNT(l.id) as total_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'hot' THEN 1 ELSE 0 END) as hot_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'warm' THEN 1 ELSE 0 END) as warm_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'cold' THEN 1 ELSE 0 END) as cold_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN 1 ELSE 0 END) as converted_leads,
            SUM(CASE WHEN LOWER(l.lead_status) = 'lost' THEN 1 ELSE 0 END) as lost_leads,
            COALESCE(SUM(sub.lead_value), 0) as total_value,
            COALESCE(SUM(CASE WHEN LOWER(l.lead_status) = 'converted' THEN sub.lead_value ELSE 0 END), 0) as converted_value
        FROM crm_leads l
        LEFT JOIN (
            SELECT l2.id as lead_id, COALESCE(SUM(qi.total_amount), 0) as lead_value
            FROM crm_leads l2
            LEFT JOIN quote_master q ON q.reference = l2.lead_no
            LEFT JOIN quote_items qi ON qi.quote_id = q.id
            GROUP BY l2.id
        ) sub ON sub.lead_id = l.id
        GROUP BY COALESCE(NULLIF(TRIM(l.state), ''), 'Not Specified')
        ORDER BY total_leads DESC
    ");
    $leadsByState = $stateStmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate into regions
    foreach ($leadsByState as $row) {
        $stateLower = strtolower(trim($row['state_name']));
        $region = $stateToRegion[$stateLower] ?? 'Other';

        if (!isset($leadsByRegion[$region])) {
            $leadsByRegion[$region] = [
                'region' => $region,
                'total_leads' => 0, 'hot_leads' => 0, 'warm_leads' => 0,
                'cold_leads' => 0, 'converted_leads' => 0, 'lost_leads' => 0,
                'total_value' => 0, 'converted_value' => 0, 'states' => []
            ];
        }
        $leadsByRegion[$region]['total_leads'] += (int)$row['total_leads'];
        $leadsByRegion[$region]['hot_leads'] += (int)$row['hot_leads'];
        $leadsByRegion[$region]['warm_leads'] += (int)$row['warm_leads'];
        $leadsByRegion[$region]['cold_leads'] += (int)$row['cold_leads'];
        $leadsByRegion[$region]['converted_leads'] += (int)$row['converted_leads'];
        $leadsByRegion[$region]['lost_leads'] += (int)$row['lost_leads'];
        $leadsByRegion[$region]['total_value'] += (float)$row['total_value'];
        $leadsByRegion[$region]['converted_value'] += (float)$row['converted_value'];
        if ($row['state_name'] !== 'Not Specified') {
            $leadsByRegion[$region]['states'][] = $row;
        }
    }

    // Sort regions by total leads descending
    uasort($leadsByRegion, function($a, $b) { return $b['total_leads'] - $a['total_leads']; });
} catch (Exception $e) {
    // Query may fail
}

// Total leads for percentage calculations
$regionGrandTotal = array_sum(array_column($leadsByRegion, 'total_leads'));

// Daily Sales Person Wise New Leads (Today)
$dailyLeadsBySalesPerson = [];
try {
    $dailyStmt = $pdo->query("
        SELECT
            e.id as user_id,
            CONCAT(e.first_name, ' ', e.last_name) as sales_person,
            e.department,
            COUNT(l.id) as today_leads,
            (SELECT COUNT(*) FROM crm_leads l2 WHERE l2.assigned_user_id = e.id) as cumulative_leads
        FROM employees e
        LEFT JOIN crm_leads l ON l.assigned_user_id = e.id
            AND DATE(l.created_at) = CURDATE()
        WHERE e.status = 'Active'
          AND e.department IN ('Sales', 'Marketing', 'Business Development')
        GROUP BY e.id, e.first_name, e.last_name, e.department
        ORDER BY today_leads DESC, cumulative_leads DESC
    ");
    $dailyLeadsBySalesPerson = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Query may fail
}

// Last 7 days leads by sales person (for trend)
$weeklyLeadsBySalesPerson = [];
try {
    $weeklyStmt = $pdo->query("
        SELECT
            e.id as user_id,
            CONCAT(e.first_name, ' ', e.last_name) as sales_person,
            DATE(l.created_at) as lead_date,
            COUNT(l.id) as lead_count
        FROM employees e
        INNER JOIN crm_leads l ON l.assigned_user_id = e.id
            AND l.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        WHERE e.status = 'Active'
        GROUP BY e.id, e.first_name, e.last_name, DATE(l.created_at)
        ORDER BY lead_date DESC, lead_count DESC
    ");
    $weeklyLeadsBySalesPerson = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Query may fail
}

// Organize weekly data by sales person
$weeklyDataByPerson = [];
foreach ($weeklyLeadsBySalesPerson as $row) {
    $personId = $row['user_id'];
    if (!isset($weeklyDataByPerson[$personId])) {
        $weeklyDataByPerson[$personId] = [
            'name' => $row['sales_person'],
            'dates' => []
        ];
    }
    $weeklyDataByPerson[$personId]['dates'][$row['lead_date']] = $row['lead_count'];
}

// Get last 7 days for header
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $last7Days[] = date('Y-m-d', strtotime("-$i days"));
}

// Upcoming follow-ups
$upcoming_followups = safeQuery($pdo, "
    SELECT lead_no, company_name, contact_person, next_followup_date, lead_status, phone, email
    FROM crm_leads
    WHERE next_followup_date IS NOT NULL
      AND next_followup_date >= CURDATE()
    ORDER BY next_followup_date
    LIMIT 10
");

// Recent leads
$recent_leads = safeQuery($pdo, "
    SELECT lead_no, company_name, contact_person, lead_status, created_at
    FROM crm_leads
    ORDER BY created_at DESC
    LIMIT 10
");

// Recent quotes
$recent_quotes = safeQuery($pdo, "
    SELECT quote_no, customer_name, total_amount, status, created_at
    FROM quote_master
    ORDER BY created_at DESC
    LIMIT 10
");

// Overdue invoices
$overdue_invoices = safeQuery($pdo, "
    SELECT invoice_no, customer_id, amount_due, due_date
    FROM invoices
    WHERE payment_status = 'pending' AND due_date < CURDATE()
    ORDER BY due_date
    LIMIT 10
");

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales & CRM Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .module-header h1 {
            margin: 0;
            font-size: 1.8em;
        }
        .module-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

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
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card.hot { border-left-color: #e74c3c; }
        .stat-card.warm { border-left-color: #f39c12; }
        .stat-card.cold { border-left-color: #3498db; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.info { border-left-color: #3498db; }
        .stat-card.warning { border-left-color: #f39c12; }

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
            border-bottom: 2px solid #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85em;
            font-weight: 600;
            min-height: 90px;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
        .data-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-hot { background: #ffebee; color: #c62828; }
        .status-warm { background: #fff3e0; color: #ef6c00; }
        .status-cold { background: #e3f2fd; color: #1565c0; }
        .status-qualified { background: #e8f5e9; color: #2e7d32; }
        .status-converted { background: #e0f2f1; color: #00695c; }
        .status-lost { background: #fafafa; color: #616161; }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .status-paid { background: #e8f5e9; color: #2e7d32; }
        .status-overdue { background: #ffebee; color: #c62828; }

        .alerts-panel {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alerts-panel h4 { margin: 0 0 10px 0; color: #856404; }
        .alerts-panel ul { list-style: none; padding: 0; margin: 0; }
        .alerts-panel li { padding: 5px 0; color: #856404; }
        .alerts-panel a { color: #004085; font-weight: 600; }

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

        /* Performance Monitoring Styles */
        .performance-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .month-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 8px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .month-filter label {
            font-weight: 600;
            color: #495057;
        }
        .month-filter select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            cursor: pointer;
            background: white;
        }
        .month-filter select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .perf-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .perf-summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .perf-summary-card:hover {
            transform: translateY(-3px);
        }
        .perf-summary-card.converted { border-left-color: #27ae60; }
        .perf-summary-card.value { border-left-color: #3498db; }
        .perf-summary-card.success { border-left-color: #f39c12; }
        .perf-icon { font-size: 1.8em; margin-bottom: 8px; }
        .perf-value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .perf-label { color: #495057; font-weight: 600; margin-top: 5px; }
        .perf-sublabel { color: #7f8c8d; font-size: 0.85em; margin-top: 3px; }

        .perf-panel {
            background: white !important;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .perf-table {
            width: 100%;
            min-width: 600px;
        }
        .perf-table th, .perf-table td {
            padding: 10px 8px !important;
            white-space: nowrap;
        }
        .perf-table .text-center { text-align: center; }
        .perf-table .text-right { text-align: right; }
        .perf-table .hot-col { color: #e74c3c; }
        .perf-table .warm-col { color: #f39c12; }
        .perf-table .cold-col { color: #3498db; }
        .perf-table .conv-col { color: #27ae60; }
        .perf-table .sold-col { color: #155724; background: #d4edda; }
        .badge-hot { background: #ffe0e0; color: #e74c3c; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .badge-warm { background: #fff3cd; color: #d68910; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .badge-converted { background: #d4edda; color: #27ae60; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .perf-table .total-row {
            background: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }
        .perf-table .total-row .sold-col {
            background: #c3e6cb;
        }

        /* Status Performance Cards */
        .status-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .status-perf-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #dee2e6;
        }
        .status-card-hot { border-left-color: #e74c3c; }
        .status-card-warm { border-left-color: #f39c12; }
        .status-card-cold { border-left-color: #3498db; }
        .status-card-converted { border-left-color: #27ae60; background: #e8f5e9; }
        .status-card-lost { border-left-color: #7f8c8d; }
        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .status-pct {
            font-weight: bold;
            color: #495057;
        }
        .status-metrics {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .status-metrics .metric {
            text-align: center;
        }
        .metric-value {
            display: block;
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .metric-label {
            font-size: 0.75em;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .status-bar {
            height: 6px;
            background: #dee2e6;
            border-radius: 3px;
            overflow: hidden;
        }
        .status-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .bar-hot { background: #e74c3c; }
        .bar-warm { background: #f39c12; }
        .bar-cold { background: #3498db; }
        .bar-converted { background: #27ae60; }
        .bar-lost { background: #7f8c8d; }

        /* Dark mode for performance section */
        body.dark .performance-section {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            border-color: #34495e;
        }
        body.dark .month-filter {
            background: #34495e;
        }
        body.dark .month-filter label { color: #ecf0f1; }
        body.dark .month-filter select {
            background: #2c3e50;
            border-color: #4a6278;
            color: #ecf0f1;
        }
        body.dark .perf-summary-card {
            background: #34495e;
        }
        body.dark .perf-value { color: #ecf0f1; }
        body.dark .perf-label { color: #bdc3c7; }
        body.dark .perf-table .sold-col { background: #1e5631; color: #a8e6cf; }
        body.dark .badge-hot { background: #5c2323; color: #f5b7b1; }
        body.dark .badge-warm { background: #4a3c00; color: #fdebd0; }
        body.dark .badge-converted { background: #1e5631; color: #a8e6cf; }
        body.dark .perf-table .total-row { background: #34495e; }
        body.dark .perf-table .total-row .sold-col { background: #155724; }
        body.dark .status-perf-card { background: #34495e; }
        body.dark .status-card-converted { background: #1e5631; }
        body.dark .metric-value { color: #ecf0f1; }
        body.dark .status-pct { color: #bdc3c7; }
        body.dark .status-bar { background: #4a6278; }

        /* Dark mode for region cards */
        body.dark .region-card {
            background: #34495e !important;
        }
        body.dark .region-card strong {
            color: #ecf0f1 !important;
        }
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

<div class="content" style="overflow-y: auto; height: 100vh;">
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
            <h1>Sales & CRM Dashboard</h1>
            <p><?= htmlspecialchars($settings['company_name'] ?? 'Enterprise Resource Planning') ?></p>
        </div>
    </div>

    <!-- Alerts Panel -->
    <?php if ($stats['invoices_overdue'] > 0 || $stats['leads_hot'] > 0): ?>
    <div class="alerts-panel">
        <h4>⚠️ Attention Required</h4>
        <ul>
            <?php if ($stats['invoices_overdue'] > 0): ?>
            <li><a href="/invoices/index.php"><?= $stats['invoices_overdue'] ?> Overdue Invoice<?= $stats['invoices_overdue'] > 1 ? 's' : '' ?></a> - Immediate follow-up needed</li>
            <?php endif; ?>
            <?php if ($stats['leads_hot'] > 0): ?>
            <li><a href="/crm/index.php"><?= $stats['leads_hot'] ?> Hot Lead<?= $stats['leads_hot'] > 1 ? 's' : '' ?></a> - High priority contacts</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
        <a href="/crm/add.php" class="quick-action-btn">
            <div class="action-icon">➕</div>
            New Lead
        </a>
        <a href="/customers/add.php" class="quick-action-btn">
            <div class="action-icon">👤</div>
            New Customer
        </a>
        <a href="/quotes/add.php" class="quick-action-btn">
            <div class="action-icon">📝</div>
            New Quote
        </a>
        <a href="/proforma/add.php" class="quick-action-btn">
            <div class="action-icon">📄</div>
            Proforma
        </a>
        <a href="/sales_orders/add.php" class="quick-action-btn">
            <div class="action-icon">📦</div>
            New SO
        </a>
        <a href="/invoices/add.php" class="quick-action-btn">
            <div class="action-icon">🧾</div>
            New Invoice
        </a>
    </div>

    <!-- Lead Statistics -->
    <div class="section-title">Lead Pipeline</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?= $stats['leads_total'] ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card hot">
            <div class="stat-icon">🔥</div>
            <div class="stat-value"><?= $stats['leads_hot'] ?></div>
            <div class="stat-label">Hot Leads</div>
        </div>
        <div class="stat-card warm">
            <div class="stat-icon">🌡️</div>
            <div class="stat-value"><?= $stats['leads_warm'] ?></div>
            <div class="stat-label">Warm Leads</div>
        </div>
        <div class="stat-card cold">
            <div class="stat-icon">❄️</div>
            <div class="stat-value"><?= $stats['leads_cold'] ?></div>
            <div class="stat-label">Cold Leads</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">🎉</div>
            <div class="stat-value"><?= $stats['leads_converted'] ?></div>
            <div class="stat-label">Converted</div>
        </div>
    </div>

    <!-- Daily Sales Person Wise Leads -->
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>👤 Daily Sales Person Wise New Leads (Today: <?= date('d M Y') ?>)</h3>
        <?php if (empty($dailyLeadsBySalesPerson)): ?>
            <p style="color: #7f8c8d; text-align: center; padding: 20px;">No sales persons found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sales Person</th>
                            <th>Department</th>
                            <th class="text-center" style="background: #e8f5e9;">Today's Leads</th>
                            <th class="text-center" style="background: #e3f2fd;">Cumulative Total</th>
                            <th class="text-center">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalToday = array_sum(array_column($dailyLeadsBySalesPerson, 'today_leads'));
                        $totalCumulative = array_sum(array_column($dailyLeadsBySalesPerson, 'cumulative_leads'));
                        $rank = 0;
                        foreach ($dailyLeadsBySalesPerson as $person):
                            $rank++;
                            $pct = $totalCumulative > 0 ? round(($person['cumulative_leads'] / $totalCumulative) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= $rank ?></td>
                            <td><strong><?= htmlspecialchars($person['sales_person']) ?></strong></td>
                            <td style="color: #6c757d;"><?= htmlspecialchars($person['department'] ?? '-') ?></td>
                            <td class="text-center" style="background: #e8f5e9;">
                                <?php if ($person['today_leads'] > 0): ?>
                                    <span style="background: #27ae60; color: white; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                                        +<?= $person['today_leads'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #adb5bd;">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="background: #e3f2fd;">
                                <strong style="color: #1565c0;"><?= number_format($person['cumulative_leads']) ?></strong>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                                    <div style="width: 60px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?= $pct ?>%; height: 100%; background: #667eea; border-radius: 4px;"></div>
                                    </div>
                                    <span style="font-size: 0.85em; color: #495057;"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="3">Total</td>
                            <td class="text-center" style="background: #c8e6c9;">
                                <span style="color: #2e7d32; font-size: 1.1em;">+<?= $totalToday ?></span>
                            </td>
                            <td class="text-center" style="background: #bbdefb;">
                                <span style="color: #1565c0; font-size: 1.1em;"><?= number_format($totalCumulative) ?></span>
                            </td>
                            <td class="text-center">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Weekly Leads Trend by Sales Person -->
    <?php if (!empty($weeklyDataByPerson)): ?>
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>📈 Last 7 Days - Leads by Sales Person</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sales Person</th>
                        <?php foreach ($last7Days as $date): ?>
                            <th class="text-center" style="font-size: 0.85em; <?= $date === date('Y-m-d') ? 'background: #fff3cd;' : '' ?>">
                                <?= date('D', strtotime($date)) ?><br>
                                <small><?= date('d/m', strtotime($date)) ?></small>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-center" style="background: #e8f5e9;">Week Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeklyDataByPerson as $personId => $personData):
                        $weekTotal = array_sum($personData['dates']);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($personData['name']) ?></strong></td>
                        <?php foreach ($last7Days as $date):
                            $count = $personData['dates'][$date] ?? 0;
                            $isToday = $date === date('Y-m-d');
                        ?>
                            <td class="text-center" style="<?= $isToday ? 'background: #fff3cd;' : '' ?>">
                                <?php if ($count > 0): ?>
                                    <span style="background: <?= $count >= 3 ? '#27ae60' : ($count >= 1 ? '#3498db' : '#95a5a6') ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.9em;">
                                        <?= $count ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #dee2e6;">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-center" style="background: #e8f5e9;">
                            <strong style="color: #27ae60;"><?= $weekTotal ?></strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sales Overview - Revenue by Lead Status -->
    <div class="section-title">Sales Overview (Pipeline Value)</div>
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: #2c3e50;">
            <div class="stat-icon">💵</div>
            <div class="stat-value" style="font-size: 1.4em;">₹<?= number_format($salesOverview['total'], 0) ?></div>
            <div class="stat-label">Total Pipeline Value</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-value" style="font-size: 1.4em;">₹<?= number_format($salesOverview['converted'], 0) ?></div>
            <div class="stat-label">Converted Value</div>
        </div>
        <div class="stat-card hot">
            <div class="stat-icon">🔥</div>
            <div class="stat-value" style="font-size: 1.4em;">₹<?= number_format($salesOverview['hot'], 0) ?></div>
            <div class="stat-label">Hot Leads Value</div>
        </div>
        <div class="stat-card warm">
            <div class="stat-icon">🌡️</div>
            <div class="stat-value" style="font-size: 1.4em;">₹<?= number_format($salesOverview['warm'], 0) ?></div>
            <div class="stat-label">Warm Leads Value</div>
        </div>
        <div class="stat-card cold">
            <div class="stat-icon">❄️</div>
            <div class="stat-value" style="font-size: 1.4em;">₹<?= number_format($salesOverview['cold'], 0) ?></div>
            <div class="stat-label">Cold Leads Value</div>
        </div>
    </div>

    <!-- Region-wise Leads -->
    <?php if (!empty($leadsByRegion)): ?>
    <div class="section-title">Region-wise Leads (India)</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <?php foreach ($leadsByRegion as $regionName => $regionData):
            $color = $regionColors[$regionName] ?? ['bg' => '#f5f5f5', 'border' => '#9e9e9e', 'icon' => '📍'];
            $pct = $regionGrandTotal > 0 ? round(($regionData['total_leads'] / $regionGrandTotal) * 100, 1) : 0;
            $convRate = $regionData['total_leads'] > 0 ? round(($regionData['converted_leads'] / $regionData['total_leads']) * 100, 1) : 0;
        ?>
        <div class="region-card" style="background: <?= $color['bg'] ?>; border-left: 5px solid <?= $color['border'] ?>; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.4em;"><?= $color['icon'] ?></span>
                    <strong style="color: #2c3e50; font-size: 1.1em;"><?= htmlspecialchars($regionName) ?></strong>
                </div>
                <span style="background: <?= $color['border'] ?>; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600;"><?= $pct ?>%</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 12px; text-align: center;">
                <div style="background: rgba(255,255,255,0.7); padding: 8px 4px; border-radius: 6px;">
                    <div style="font-size: 1.3em; font-weight: bold; color: #2c3e50;"><?= $regionData['total_leads'] ?></div>
                    <div style="font-size: 0.7em; color: #666; text-transform: uppercase;">Total</div>
                </div>
                <div style="background: rgba(231,76,60,0.15); padding: 8px 4px; border-radius: 6px;">
                    <div style="font-size: 1.3em; font-weight: bold; color: #e74c3c;"><?= $regionData['hot_leads'] ?></div>
                    <div style="font-size: 0.7em; color: #e74c3c; text-transform: uppercase;">Hot</div>
                </div>
                <div style="background: rgba(243,156,18,0.15); padding: 8px 4px; border-radius: 6px;">
                    <div style="font-size: 1.3em; font-weight: bold; color: #f39c12;"><?= $regionData['warm_leads'] ?></div>
                    <div style="font-size: 0.7em; color: #f39c12; text-transform: uppercase;">Warm</div>
                </div>
                <div style="background: rgba(52,152,219,0.15); padding: 8px 4px; border-radius: 6px;">
                    <div style="font-size: 1.3em; font-weight: bold; color: #3498db;"><?= $regionData['cold_leads'] ?></div>
                    <div style="font-size: 0.7em; color: #3498db; text-transform: uppercase;">Cold</div>
                </div>
                <div style="background: rgba(39,174,96,0.2); padding: 8px 4px; border-radius: 6px;">
                    <div style="font-size: 1.3em; font-weight: bold; color: #27ae60;"><?= $regionData['converted_leads'] ?></div>
                    <div style="font-size: 0.7em; color: #27ae60; text-transform: uppercase;">Won</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                <div style="background: rgba(255,255,255,0.7); padding: 8px; border-radius: 6px;">
                    <div style="font-size: 0.7em; color: #666; text-transform: uppercase;">Pipeline</div>
                    <div style="font-size: 1em; font-weight: bold; color: #3498db;">₹<?= number_format($regionData['total_value'], 0) ?></div>
                </div>
                <div style="background: rgba(39,174,96,0.2); padding: 8px; border-radius: 6px;">
                    <div style="font-size: 0.7em; color: #666; text-transform: uppercase;">Actual Sales</div>
                    <div style="font-size: 1em; font-weight: bold; color: #27ae60;">₹<?= number_format($regionData['converted_value'], 0) ?></div>
                </div>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8em; margin-bottom: 4px;">
                    <span style="color: #666;">Conversion</span>
                    <span style="font-weight: 600; color: <?= $convRate >= 30 ? '#27ae60' : ($convRate >= 15 ? '#f39c12' : '#e74c3c') ?>;"><?= $convRate ?>%</span>
                </div>
                <div style="height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; width: <?= min($convRate, 100) ?>%; background: <?= $convRate >= 30 ? '#27ae60' : ($convRate >= 15 ? '#f39c12' : '#e74c3c') ?>; border-radius: 3px;"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- State-wise Leads Table -->
    <?php if (!empty($leadsByState)): ?>
    <div class="dashboard-panel" style="margin-bottom: 25px;">
        <h3>📍 State-wise Leads Breakdown</h3>
        <div class="table-responsive">
            <table class="data-table perf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>State</th>
                        <th>Region</th>
                        <th class="text-center">Total</th>
                        <th class="text-center hot-col">Hot</th>
                        <th class="text-center warm-col">Warm</th>
                        <th class="text-center cold-col">Cold</th>
                        <th class="text-center conv-col">Won</th>
                        <th class="text-right">Pipeline</th>
                        <th class="text-right sold-col">Sales</th>
                        <th class="text-center">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stateRank = 0;
                    $stateTotals = ['total' => 0, 'hot' => 0, 'warm' => 0, 'cold' => 0, 'converted' => 0, 'lost' => 0, 'value' => 0, 'conv_value' => 0];
                    foreach ($leadsByState as $stateRow):
                        $stateRank++;
                        $stateLower = strtolower(trim($stateRow['state_name']));
                        $region = $stateToRegion[$stateLower] ?? 'Other';
                        $rColor = $regionColors[$region] ?? ['border' => '#9e9e9e'];
                        $sharePct = $regionGrandTotal > 0 ? round(($stateRow['total_leads'] / $regionGrandTotal) * 100, 1) : 0;
                        $stateTotals['total'] += (int)$stateRow['total_leads'];
                        $stateTotals['hot'] += (int)$stateRow['hot_leads'];
                        $stateTotals['warm'] += (int)$stateRow['warm_leads'];
                        $stateTotals['cold'] += (int)$stateRow['cold_leads'];
                        $stateTotals['converted'] += (int)$stateRow['converted_leads'];
                        $stateTotals['value'] += (float)$stateRow['total_value'];
                        $stateTotals['conv_value'] += (float)$stateRow['converted_value'];
                    ?>
                    <tr>
                        <td><?= $stateRank ?></td>
                        <td><strong><?= htmlspecialchars($stateRow['state_name']) ?></strong></td>
                        <td><span style="background: <?= $rColor['border'] ?>20; color: <?= $rColor['border'] ?>; padding: 2px 8px; border-radius: 10px; font-size: 0.8em; font-weight: 600;"><?= $region ?></span></td>
                        <td class="text-center"><strong><?= $stateRow['total_leads'] ?></strong></td>
                        <td class="text-center hot-col"><?= $stateRow['hot_leads'] ?: '-' ?></td>
                        <td class="text-center warm-col"><?= $stateRow['warm_leads'] ?: '-' ?></td>
                        <td class="text-center cold-col"><?= $stateRow['cold_leads'] ?: '-' ?></td>
                        <td class="text-center conv-col"><?= $stateRow['converted_leads'] ?: '-' ?></td>
                        <td class="text-right">₹<?= number_format($stateRow['total_value'], 0) ?></td>
                        <td class="text-right sold-col"><strong>₹<?= number_format($stateRow['converted_value'], 0) ?></strong></td>
                        <td class="text-center">
                            <div style="display: flex; align-items: center; gap: 6px; justify-content: center;">
                                <div style="width: 50px; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?= min($sharePct, 100) ?>%; height: 100%; background: <?= $rColor['border'] ?>; border-radius: 3px;"></div>
                                </div>
                                <span style="font-size: 0.8em; color: #495057;"><?= $sharePct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>Grand Total</strong></td>
                        <td class="text-center"><strong><?= $stateTotals['total'] ?></strong></td>
                        <td class="text-center hot-col"><?= $stateTotals['hot'] ?: '-' ?></td>
                        <td class="text-center warm-col"><?= $stateTotals['warm'] ?: '-' ?></td>
                        <td class="text-center cold-col"><?= $stateTotals['cold'] ?: '-' ?></td>
                        <td class="text-center conv-col"><?= $stateTotals['converted'] ?: '-' ?></td>
                        <td class="text-right"><strong>₹<?= number_format($stateTotals['value'], 0) ?></strong></td>
                        <td class="text-right sold-col"><strong>₹<?= number_format($stateTotals['conv_value'], 0) ?></strong></td>
                        <td class="text-center">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sales Activity Stats -->
    <div class="section-title">Sales Activity</div>
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon">🏢</div>
            <div class="stat-value"><?= $stats['customers_active'] ?></div>
            <div class="stat-label">Active Customers</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">📋</div>
            <div class="stat-value"><?= $stats['quotes_pending'] ?></div>
            <div class="stat-label">Pending Quotes</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['so_open'] ?></div>
            <div class="stat-label">Open Sales Orders</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= $stats['invoices_pending'] ?></div>
            <div class="stat-label">Pending Invoices</div>
        </div>
        <?php if ($stats['invoices_overdue'] > 0): ?>
        <div class="stat-card danger">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?= $stats['invoices_overdue'] ?></div>
            <div class="stat-label">Overdue Invoices</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Performance Monitoring Section -->
    <div class="performance-section">
        <div class="section-header">
            <div class="section-title" style="margin-bottom: 0;">Performance Monitoring</div>
            <form method="get" class="month-filter">
                <label for="perf_month">Month:</label>
                <select name="perf_month" id="perf_month" onchange="this.form.submit()">
                    <?php foreach ($monthOptions as $opt): ?>
                        <option value="<?= $opt['value'] ?>" <?= $perf_month === $opt['value'] ? 'selected' : '' ?>>
                            <?= $opt['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Month Summary Cards -->
        <div class="perf-summary-grid">
            <div class="perf-summary-card">
                <div class="perf-icon">📊</div>
                <div class="perf-value"><?= $perfTotals['total_leads'] ?></div>
                <div class="perf-label">Total Leads</div>
                <div class="perf-sublabel"><?= date('F Y', strtotime($perf_month . '-01')) ?></div>
            </div>
            <div class="perf-summary-card converted">
                <div class="perf-icon">✅</div>
                <div class="perf-value"><?= $perfTotals['converted_leads'] ?></div>
                <div class="perf-label">Converted</div>
                <div class="perf-sublabel"><?= $perfTotals['conversion_rate'] ?>% Conversion</div>
            </div>
            <div class="perf-summary-card value">
                <div class="perf-icon">💰</div>
                <div class="perf-value">₹<?= number_format($perfTotals['total_value'], 0) ?></div>
                <div class="perf-label">Total Pipeline Value</div>
                <div class="perf-sublabel">All leads this month</div>
            </div>
            <div class="perf-summary-card success">
                <div class="perf-icon">🎯</div>
                <div class="perf-value">₹<?= number_format($perfTotals['converted_value'], 0) ?></div>
                <div class="perf-label">Converted Value</div>
                <div class="perf-sublabel">Won deals this month</div>
            </div>
        </div>

        <!-- Performance by Lead Owner - Full Width -->
        <div class="dashboard-panel perf-panel" style="margin-bottom: 20px;">
            <h3>👤 Performance by Lead Owner</h3>
            <?php if (empty($perfByOwner)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No leads assigned in <?= date('F Y', strtotime($perf_month . '-01')) ?></p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table perf-table owner-table">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Lead Owner</th>
                            <th style="min-width: 100px;">Department</th>
                            <th class="text-center" style="width: 60px;">Total</th>
                            <th class="text-center hot-col" style="width: 50px;">Hot</th>
                            <th class="text-center warm-col" style="width: 55px;">Warm</th>
                            <th class="text-center cold-col" style="width: 50px;">Cold</th>
                            <th class="text-center conv-col" style="width: 55px;">Conv.</th>
                            <th class="text-right" style="min-width: 110px;">Pipeline</th>
                            <th class="text-right sold-col" style="min-width: 110px;">Actual Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalLeads = 0;
                        $totalHot = 0;
                        $totalWarm = 0;
                        $totalCold = 0;
                        $totalConverted = 0;
                        $totalPipeline = 0;
                        $totalSold = 0;
                        foreach ($perfByOwner as $owner):
                            $totalLeads += $owner['total_leads'];
                            $totalHot += $owner['hot_leads'];
                            $totalWarm += $owner['warm_leads'];
                            $totalCold += $owner['cold_leads'];
                            $totalConverted += $owner['converted_leads'];
                            $totalPipeline += $owner['total_value'];
                            $totalSold += $owner['converted_value'];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($owner['owner_name']) ?></strong></td>
                            <td style="color: #6b7280; font-size: 0.9em;"><?= htmlspecialchars($owner['department'] ?? '-') ?></td>
                            <td class="text-center"><strong><?= $owner['total_leads'] ?></strong></td>
                            <td class="text-center hot-col"><?= $owner['hot_leads'] ?: '-' ?></td>
                            <td class="text-center warm-col"><?= $owner['warm_leads'] ?: '-' ?></td>
                            <td class="text-center cold-col"><?= $owner['cold_leads'] ?: '-' ?></td>
                            <td class="text-center conv-col"><?= $owner['converted_leads'] ?: '-' ?></td>
                            <td class="text-right">₹<?= number_format($owner['total_value'], 0) ?></td>
                            <td class="text-right sold-col">
                                <strong>₹<?= number_format($owner['converted_value'], 0) ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total</strong></td>
                            <td class="text-center"><strong><?= $totalLeads ?></strong></td>
                            <td class="text-center hot-col"><?= $totalHot ?: '-' ?></td>
                            <td class="text-center warm-col"><?= $totalWarm ?: '-' ?></td>
                            <td class="text-center cold-col"><?= $totalCold ?: '-' ?></td>
                            <td class="text-center conv-col"><?= $totalConverted ?: '-' ?></td>
                            <td class="text-right"><strong>₹<?= number_format($totalPipeline, 0) ?></strong></td>
                            <td class="text-right sold-col"><strong>₹<?= number_format($totalSold, 0) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance by Lead Status - Full Width -->
        <div class="dashboard-panel perf-panel">
            <h3>📈 Performance by Lead Status</h3>
            <?php if (empty($perfByStatus)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No leads in <?= date('F Y', strtotime($perf_month . '-01')) ?></p>
            <?php else: ?>
                <div class="status-cards-grid">
                    <?php foreach ($perfByStatus as $status):
                        $pct = $perfTotals['total_leads'] > 0
                            ? round(($status['total_leads'] / $perfTotals['total_leads']) * 100, 1)
                            : 0;
                        $statusClass = strtolower($status['lead_status']);
                        $statusLabel = ucfirst($status['lead_status']);
                    ?>
                    <div class="status-perf-card status-card-<?= $statusClass ?>">
                        <div class="status-header">
                            <span class="status-badge status-<?= $statusClass ?>"><?= $statusLabel ?></span>
                            <span class="status-pct"><?= $pct ?>%</span>
                        </div>
                        <div class="status-metrics">
                            <div class="metric">
                                <span class="metric-value"><?= $status['total_leads'] ?></span>
                                <span class="metric-label">Leads</span>
                            </div>
                            <div class="metric">
                                <span class="metric-value">₹<?= number_format($status['total_value'], 0) ?></span>
                                <span class="metric-label">Value</span>
                            </div>
                        </div>
                        <div class="status-bar">
                            <div class="status-bar-fill bar-<?= $statusClass ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance by B2B Dealers -->
        <div class="perf-section">
            <h4>🏢 B2B Dealer Performance (Top 15)</h4>
            <?php if (empty($perfByDealer)): ?>
                <p style="color: #7f8c8d; padding: 15px;">No B2B dealer data for <?= date('F Y', strtotime($perf_month . '-01')) ?>.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th>Dealer / Company</th>
                                <th>Contact</th>
                                <th>City</th>
                                <th>Leads</th>
                                <th>Hot</th>
                                <th>Warm</th>
                                <th>Converted</th>
                                <th>Lead Value</th>
                                <th class="sold-col">Actual Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfByDealer as $dealer): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($dealer['dealer_name']) ?></strong></td>
                                <td><?= htmlspecialchars($dealer['contact_person'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($dealer['city'] ?? '-') ?></td>
                                <td><?= $dealer['total_leads'] ?></td>
                                <td><span class="badge-hot"><?= $dealer['hot_leads'] ?></span></td>
                                <td><span class="badge-warm"><?= $dealer['warm_leads'] ?></span></td>
                                <td><span class="badge-converted"><?= $dealer['converted_leads'] ?></span></td>
                                <td>₹<?= number_format($dealer['total_value'], 0) ?></td>
                                <td class="sold-col"><strong>₹<?= number_format($dealer['converted_value'], 0) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="3">B2B Total</td>
                                <td><?= $b2bTotals['total_leads'] ?></td>
                                <td colspan="2">-</td>
                                <td><?= $b2bTotals['converted_leads'] ?></td>
                                <td>₹<?= number_format($b2bTotals['total_value'], 0) ?></td>
                                <td class="sold-col">₹<?= number_format($b2bTotals['converted_value'], 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance by Market Classification - Enhanced -->
        <div class="dashboard-panel perf-panel" style="margin-top: 20px;">
            <h3>🏷️ Sales by Market Classification</h3>
            <?php if (empty($perfByMarket)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No market classification data for <?= date('F Y', strtotime($perf_month . '-01')) ?>.</p>
            <?php else: ?>
                <?php
                // Define colors for each market classification
                $marketColors = [
                    'GEMS or Tenders' => ['bg' => '#e8f5e9', 'border' => '#4caf50', 'icon' => '🏛️'],
                    'Export Orders' => ['bg' => '#e3f2fd', 'border' => '#2196f3', 'icon' => '🌍'],
                    'Corporate Customers' => ['bg' => '#fff3e0', 'border' => '#ff9800', 'icon' => '🏢'],
                    'Private Hospitals' => ['bg' => '#fce4ec', 'border' => '#e91e63', 'icon' => '🏥'],
                    'Medical Colleges' => ['bg' => '#f3e5f5', 'border' => '#9c27b0', 'icon' => '🎓'],
                    'NGO or Others' => ['bg' => '#e0f2f1', 'border' => '#009688', 'icon' => '🤝'],
                    'Not Specified' => ['bg' => '#f5f5f5', 'border' => '#9e9e9e', 'icon' => '📋']
                ];
                ?>

                <!-- Market Classification Visual Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 25px;">
                    <?php foreach ($perfByMarket as $market):
                        $mktName = $market['market_classification'];
                        $color = $marketColors[$mktName] ?? $marketColors['Not Specified'];
                        $pct = $marketTotals['total_leads'] > 0 ? round(($market['total_leads'] / $marketTotals['total_leads']) * 100, 1) : 0;
                        $convRate = $market['total_leads'] > 0 ? round(($market['converted_leads'] / $market['total_leads']) * 100, 1) : 0;
                        $valuePct = $marketTotals['total_value'] > 0 ? round(($market['total_value'] / $marketTotals['total_value']) * 100, 1) : 0;
                    ?>
                    <div style="background: <?= $color['bg'] ?>; border-left: 5px solid <?= $color['border'] ?>; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 1.4em;"><?= $color['icon'] ?></span>
                                <strong style="color: #2c3e50; font-size: 1em;"><?= htmlspecialchars($mktName) ?></strong>
                            </div>
                            <span style="background: <?= $color['border'] ?>; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600;"><?= $pct ?>%</span>
                        </div>

                        <!-- Lead Status Breakdown -->
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 12px; text-align: center;">
                            <div style="background: rgba(255,255,255,0.7); padding: 8px 4px; border-radius: 6px;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #2c3e50;"><?= $market['total_leads'] ?></div>
                                <div style="font-size: 0.7em; color: #666; text-transform: uppercase;">Total</div>
                            </div>
                            <div style="background: rgba(231,76,60,0.15); padding: 8px 4px; border-radius: 6px;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #e74c3c;"><?= $market['hot_leads'] ?></div>
                                <div style="font-size: 0.7em; color: #e74c3c; text-transform: uppercase;">Hot</div>
                            </div>
                            <div style="background: rgba(243,156,18,0.15); padding: 8px 4px; border-radius: 6px;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #f39c12;"><?= $market['warm_leads'] ?></div>
                                <div style="font-size: 0.7em; color: #f39c12; text-transform: uppercase;">Warm</div>
                            </div>
                            <div style="background: rgba(52,152,219,0.15); padding: 8px 4px; border-radius: 6px;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #3498db;"><?= $market['cold_leads'] ?></div>
                                <div style="font-size: 0.7em; color: #3498db; text-transform: uppercase;">Cold</div>
                            </div>
                            <div style="background: rgba(39,174,96,0.2); padding: 8px 4px; border-radius: 6px;">
                                <div style="font-size: 1.2em; font-weight: bold; color: #27ae60;"><?= $market['converted_leads'] ?></div>
                                <div style="font-size: 0.7em; color: #27ae60; text-transform: uppercase;">Won</div>
                            </div>
                        </div>

                        <!-- Value Metrics -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 6px;">
                                <div style="font-size: 0.75em; color: #666; text-transform: uppercase; margin-bottom: 3px;">Pipeline Value</div>
                                <div style="font-size: 1.1em; font-weight: bold; color: #3498db;">₹<?= number_format($market['total_value'], 0) ?></div>
                            </div>
                            <div style="background: rgba(39,174,96,0.2); padding: 10px; border-radius: 6px;">
                                <div style="font-size: 0.75em; color: #666; text-transform: uppercase; margin-bottom: 3px;">Actual Sales</div>
                                <div style="font-size: 1.1em; font-weight: bold; color: #27ae60;">₹<?= number_format($market['converted_value'], 0) ?></div>
                            </div>
                        </div>

                        <!-- Conversion Rate Progress -->
                        <div style="margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.8em; margin-bottom: 5px;">
                                <span style="color: #666;">Conversion Rate</span>
                                <span style="font-weight: 600; color: <?= $convRate >= 30 ? '#27ae60' : ($convRate >= 15 ? '#f39c12' : '#e74c3c') ?>;"><?= $convRate ?>%</span>
                            </div>
                            <div style="height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?= min($convRate, 100) ?>%; background: <?= $convRate >= 30 ? '#27ae60' : ($convRate >= 15 ? '#f39c12' : '#e74c3c') ?>; border-radius: 4px; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Market Classification Summary Table -->
                <div class="table-responsive">
                    <table class="data-table perf-table">
                        <thead>
                            <tr>
                                <th>Market Classification</th>
                                <th class="text-center">Total</th>
                                <th class="text-center hot-col">Hot</th>
                                <th class="text-center warm-col">Warm</th>
                                <th class="text-center cold-col">Cold</th>
                                <th class="text-center conv-col">Won</th>
                                <th class="text-center">Lost</th>
                                <th class="text-right">Pipeline</th>
                                <th class="text-right sold-col">Actual Sales</th>
                                <th class="text-center">Conv %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfByMarket as $market):
                                $convRate = $market['total_leads'] > 0 ? round(($market['converted_leads'] / $market['total_leads']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($market['market_classification']) ?></strong></td>
                                <td class="text-center"><strong><?= $market['total_leads'] ?></strong></td>
                                <td class="text-center hot-col"><?= $market['hot_leads'] ?: '-' ?></td>
                                <td class="text-center warm-col"><?= $market['warm_leads'] ?: '-' ?></td>
                                <td class="text-center cold-col"><?= $market['cold_leads'] ?: '-' ?></td>
                                <td class="text-center conv-col"><?= $market['converted_leads'] ?: '-' ?></td>
                                <td class="text-center" style="color: #7f8c8d;"><?= $market['lost_leads'] ?: '-' ?></td>
                                <td class="text-right">₹<?= number_format($market['total_value'], 0) ?></td>
                                <td class="text-right sold-col"><strong>₹<?= number_format($market['converted_value'], 0) ?></strong></td>
                                <td class="text-center">
                                    <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.85em; font-weight: 600; background: <?= $convRate >= 30 ? '#d4edda' : ($convRate >= 15 ? '#fff3cd' : '#f8d7da') ?>; color: <?= $convRate >= 30 ? '#155724' : ($convRate >= 15 ? '#856404' : '#721c24') ?>;">
                                        <?= $convRate ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>Total</strong></td>
                                <td class="text-center"><strong><?= $marketTotals['total_leads'] ?></strong></td>
                                <td class="text-center hot-col"><?= $marketTotals['hot_leads'] ?: '-' ?></td>
                                <td class="text-center warm-col"><?= $marketTotals['warm_leads'] ?: '-' ?></td>
                                <td class="text-center cold-col"><?= $marketTotals['cold_leads'] ?: '-' ?></td>
                                <td class="text-center conv-col"><?= $marketTotals['converted_leads'] ?: '-' ?></td>
                                <td class="text-center" style="color: #7f8c8d;"><?= $marketTotals['lost_leads'] ?: '-' ?></td>
                                <td class="text-right"><strong>₹<?= number_format($marketTotals['total_value'], 0) ?></strong></td>
                                <td class="text-right sold-col"><strong>₹<?= number_format($marketTotals['converted_value'], 0) ?></strong></td>
                                <td class="text-center">
                                    <?php $totalConvRate = $marketTotals['total_leads'] > 0 ? round(($marketTotals['converted_leads'] / $marketTotals['total_leads']) * 100, 1) : 0; ?>
                                    <span style="padding: 3px 8px; border-radius: 10px; font-size: 0.85em; font-weight: 600; background: #e9ecef; color: #495057;">
                                        <?= $totalConvRate ?>%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Performance by Product -->
        <div class="perf-section">
            <h4>📦 Product-wise Sales Performance (Top 20)</h4>
            <?php if (empty($perfByProduct)): ?>
                <p style="color: #7f8c8d; padding: 15px;">No product sales data for <?= date('F Y', strtotime($perf_month . '-01')) ?>.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th>Part No</th>
                                <th>Product / Description</th>
                                <th class="text-center">Leads</th>
                                <th class="text-center">Qty Sold</th>
                                <th>UOM</th>
                                <th class="text-right sold-col">Sales Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfByProduct as $product): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($product['part_no']) ?></strong></td>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td class="text-center"><span class="badge-converted"><?= $product['lead_count'] ?></span></td>
                                <td class="text-center"><strong><?= number_format($product['qty_sold'], 0) ?></strong></td>
                                <td><?= htmlspecialchars($product['uom'] ?? 'Nos') ?></td>
                                <td class="text-right sold-col"><strong>₹<?= number_format($product['total_value'], 0) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="2">Product Total (<?= count($perfByProduct) ?> products)</td>
                                <td class="text-center"><?= $productTotals['lead_count'] ?></td>
                                <td class="text-center"><?= number_format($productTotals['qty_sold'], 0) ?></td>
                                <td>-</td>
                                <td class="text-right sold-col">₹<?= number_format($productTotals['total_value'], 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Upcoming Follow-ups -->
        <div class="dashboard-panel">
            <h3>📅 Upcoming Follow-ups</h3>
            <?php if (empty($upcoming_followups)): ?>
                <p style="color: #7f8c8d;">No upcoming follow-ups scheduled.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_followups as $followup): ?>
                        <tr>
                            <td><?= date('d M', strtotime($followup['next_followup_date'])) ?></td>
                            <td><a href="/crm/view.php?lead_no=<?= urlencode($followup['lead_no']) ?>"><?= htmlspecialchars($followup['company_name']) ?></a></td>
                            <td><?= htmlspecialchars($followup['contact_person']) ?></td>
                            <td><span class="status-badge status-<?= $followup['lead_status'] ?>"><?= ucfirst($followup['lead_status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Recent Leads -->
        <div class="dashboard-panel">
            <h3>🆕 Recent Leads</h3>
            <?php if (empty($recent_leads)): ?>
                <p style="color: #7f8c8d;">No leads found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lead #</th>
                            <th>Company</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_leads as $lead): ?>
                        <tr>
                            <td><a href="/crm/view.php?lead_no=<?= urlencode($lead['lead_no']) ?>"><?= htmlspecialchars($lead['lead_no']) ?></a></td>
                            <td><?= htmlspecialchars($lead['company_name']) ?></td>
                            <td><span class="status-badge status-<?= $lead['lead_status'] ?>"><?= ucfirst($lead['lead_status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-row">
        <!-- Recent Quotes -->
        <div class="dashboard-panel">
            <h3>📝 Recent Quotations</h3>
            <?php if (empty($recent_quotes)): ?>
                <p style="color: #7f8c8d;">No quotes found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_quotes as $quote): ?>
                        <tr>
                            <td><a href="/quotes/view.php?quote_no=<?= urlencode($quote['quote_no']) ?>"><?= htmlspecialchars($quote['quote_no']) ?></a></td>
                            <td><?= htmlspecialchars($quote['customer_name']) ?></td>
                            <td>₹<?= number_format($quote['total_amount'], 2) ?></td>
                            <td><span class="status-badge status-<?= $quote['status'] ?>"><?= ucfirst($quote['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Overdue Invoices -->
        <div class="dashboard-panel">
            <h3>🚨 Overdue Invoices</h3>
            <?php if (empty($overdue_invoices)): ?>
                <p style="color: #27ae60;">No overdue invoices. Great job!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_invoices as $invoice): ?>
                        <tr>
                            <td><a href="/invoices/view.php?invoice_no=<?= urlencode($invoice['invoice_no']) ?>"><?= htmlspecialchars($invoice['invoice_no']) ?></a></td>
                            <td>₹<?= number_format($invoice['amount_due'], 2) ?></td>
                            <td style="color: #e74c3c;"><?= date('d M Y', strtotime($invoice['due_date'])) ?></td>
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
        <a href="/crm/index.php" class="quick-action-btn">
            <div class="action-icon">📊</div>
            All Leads
        </a>
        <a href="/customers/index.php" class="quick-action-btn">
            <div class="action-icon">👥</div>
            All Customers
        </a>
        <a href="/quotes/index.php" class="quick-action-btn">
            <div class="action-icon">📋</div>
            All Quotes
        </a>
        <a href="/proforma/index.php" class="quick-action-btn">
            <div class="action-icon">📄</div>
            Proforma Invoices
        </a>
        <a href="/customer_po/index.php" class="quick-action-btn">
            <div class="action-icon">📑</div>
            Customer PO
        </a>
        <a href="/sales_orders/index.php" class="quick-action-btn">
            <div class="action-icon">📦</div>
            Sales Orders
        </a>
        <a href="/invoices/index.php" class="quick-action-btn">
            <div class="action-icon">🧾</div>
            All Invoices
        </a>
    </div>
</div>

</body>
</html>
