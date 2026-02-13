<?php
/**
 * CEO / Chairman Dashboard
 * High-level executive overview of all business modules
 */
include "db.php";
include "includes/auth.php";

// Date ranges
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$thisMonthEnd = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$thisYearStart = date('Y-01-01');
$thisQuarterStart = date('Y-m-01', strtotime('first day of -' . ((date('n') - 1) % 3) . ' months'));

// ============ SALES METRICS ============
// Revenue this month (from released invoices - calculated via quote_items)
try {
    $revenueThisMonth = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $revenueThisMonth->execute([$thisMonthStart, $thisMonthEnd]);
    $salesThisMonth = $revenueThisMonth->fetchColumn();
} catch (Exception $e) {
    $salesThisMonth = 0;
}

// Revenue last month
try {
    $revenueLastMonth = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
    ");
    $revenueLastMonth->execute([$lastMonthStart, $lastMonthEnd]);
    $salesLastMonth = $revenueLastMonth->fetchColumn();
} catch (Exception $e) {
    $salesLastMonth = 0;
}

// Revenue YTD
try {
    $revenueYTD = $pdo->prepare("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
    ");
    $revenueYTD->execute([$thisYearStart]);
    $salesYTD = $revenueYTD->fetchColumn();
} catch (Exception $e) {
    $salesYTD = 0;
}

// Sales growth percentage
$salesGrowth = $salesLastMonth > 0 ? round((($salesThisMonth - $salesLastMonth) / $salesLastMonth) * 100, 1) : 0;

// Pending invoices value
try {
    $pendingInvoices = $pdo->query("
        SELECT
            COUNT(DISTINCT im.id) as count,
            COALESCE(SUM(qi.total_amount), 0) as value
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'pending' OR im.status = 'draft'
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingInvoices = ['count' => 0, 'value' => 0];
}

// Total Sales Orders this month
try {
    $ordersThisMonth = $pdo->prepare("
        SELECT COUNT(DISTINCT so_no) FROM sales_orders WHERE sales_date BETWEEN ? AND ?
    ");
    $ordersThisMonth->execute([$thisMonthStart, $thisMonthEnd]);
    $totalOrdersThisMonth = $ordersThisMonth->fetchColumn();
} catch (Exception $e) {
    $totalOrdersThisMonth = 0;
}

// Quote to Order Conversion Rate
// A quote is "converted" when it's linked to a sales order (has a linked_quote_id in sales_orders)
try {
    $quotesThisMonth = $pdo->prepare("
        SELECT COUNT(*) FROM quote_master WHERE created_at >= ?
    ");
    $quotesThisMonth->execute([$thisMonthStart]);
    $totalQuotes = $quotesThisMonth->fetchColumn();

    // Count quotes that have been converted to sales orders
    $convertedQuotes = $pdo->prepare("
        SELECT COUNT(DISTINCT qm.id)
        FROM quote_master qm
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        WHERE qm.created_at >= ?
    ");
    $convertedQuotes->execute([$thisMonthStart]);
    $convertedCount = $convertedQuotes->fetchColumn();
} catch (Exception $e) {
    $totalQuotes = 0;
    $convertedCount = 0;
}
$conversionRate = $totalQuotes > 0 ? round(($convertedCount / $totalQuotes) * 100, 1) : 0;

// ============ CRM METRICS ============
// Total active customers
try {
    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
} catch (Exception $e) {
    $totalCustomers = 0;
}

// New customers this month
try {
    $newCustomersMonth = $pdo->prepare("
        SELECT COUNT(*) FROM customers WHERE created_at >= ?
    ");
    $newCustomersMonth->execute([$thisMonthStart]);
    $newCustomers = $newCustomersMonth->fetchColumn();
} catch (Exception $e) {
    $newCustomers = 0;
}

// Total leads (from crm_leads table)
try {
    $totalLeads = $pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
} catch (Exception $e) {
    $totalLeads = 0;
}

// Hot leads
try {
    $hotLeads = $pdo->query("SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'hot'")->fetchColumn();
} catch (Exception $e) {
    $hotLeads = 0;
}

// Lead conversion this month
try {
    $convertedLeads = $pdo->prepare("
        SELECT COUNT(*) FROM crm_leads WHERE lead_status = 'converted' AND updated_at >= ?
    ");
    $convertedLeads->execute([$thisMonthStart]);
    $leadsConverted = $convertedLeads->fetchColumn();
} catch (Exception $e) {
    $leadsConverted = 0;
}

// ============ SERVICE METRICS ============
// Open complaints
try {
    $openComplaints = $pdo->query("
        SELECT COUNT(*) FROM service_complaints WHERE status IN ('open', 'in_progress', 'pending_parts')
    ")->fetchColumn();
} catch (Exception $e) {
    $openComplaints = 0;
}

// Resolved this month
try {
    $resolvedMonth = $pdo->prepare("
        SELECT COUNT(*) FROM service_complaints WHERE status = 'resolved' AND resolved_date >= ?
    ");
    $resolvedMonth->execute([$thisMonthStart]);
    $complaintsResolved = $resolvedMonth->fetchColumn();
} catch (Exception $e) {
    $complaintsResolved = 0;
}

// Average resolution time (days)
try {
    $avgResolution = $pdo->query("
        SELECT AVG(DATEDIFF(resolved_date, complaint_date))
        FROM service_complaints
        WHERE status = 'resolved' AND resolved_date IS NOT NULL
    ")->fetchColumn();
    $avgResolutionDays = $avgResolution ? round($avgResolution, 1) : 0;
} catch (Exception $e) {
    $avgResolutionDays = 0;
}

// Pending installations
try {
    $pendingInstallations = $pdo->query("
        SELECT COUNT(*) FROM installations WHERE status IN ('scheduled', 'in_progress')
    ")->fetchColumn();
} catch (Exception $e) {
    $pendingInstallations = 0;
}

// ============ INVENTORY METRICS ============
// Total stock value (from inventory table joined with part_master for prices)
// Note: part_master uses 'rate' column for price
try {
    $stockValue = $pdo->query("
        SELECT COALESCE(SUM(i.qty * p.rate), 0)
        FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
    ")->fetchColumn();
} catch (Exception $e) {
    $stockValue = 0;
}

// Low stock alerts (parts below minimum stock level) - matches inventory dashboard
try {
    $lowStockItems = $pdo->query("
        SELECT COUNT(*) FROM inventory i
        JOIN part_master p ON i.part_no = p.part_no
        WHERE i.qty < COALESCE(p.min_stock, 10)
    ")->fetchColumn();
} catch (Exception $e) {
    $lowStockItems = 0;
}

// Out of stock items - matches inventory dashboard
try {
    $outOfStock = $pdo->query("
        SELECT COUNT(*) FROM inventory WHERE qty = 0
    ")->fetchColumn();
} catch (Exception $e) {
    $outOfStock = 0;
}

// ============ PURCHASE METRICS ============
// Pending POs (status = 'open' or 'pending' or 'Pending')
// Note: purchase_orders has qty and rate columns, total = qty * rate
try {
    $pendingPOs = $pdo->query("
        SELECT COUNT(DISTINCT po_no), COALESCE(SUM(qty * rate), 0)
        FROM purchase_orders WHERE status IN ('open', 'pending', 'Pending', 'Approved')
    ")->fetch(PDO::FETCH_NUM);
    $pendingPOCount = $pendingPOs[0] ?? 0;
    $pendingPOValue = $pendingPOs[1] ?? 0;
} catch (Exception $e) {
    $pendingPOCount = 0;
    $pendingPOValue = 0;
}

// Purchase this month (received orders, using purchase_date column)
try {
    $purchaseMonth = $pdo->prepare("
        SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders
        WHERE status IN ('received', 'Received', 'closed', 'Closed') AND purchase_date BETWEEN ? AND ?
    ");
    $purchaseMonth->execute([$thisMonthStart, $thisMonthEnd]);
    $purchaseThisMonth = $purchaseMonth->fetchColumn();
} catch (Exception $e) {
    $purchaseThisMonth = 0;
}

// ============ WORK ORDERS METRICS ============
// Active work orders
try {
    $activeWO = $pdo->query("
        SELECT COUNT(*) FROM work_orders WHERE status IN ('pending', 'in_progress', 'Pending', 'In Progress')
    ")->fetchColumn();
} catch (Exception $e) {
    $activeWO = 0;
}

// Completed this month
try {
    $completedWO = $pdo->prepare("
        SELECT COUNT(*) FROM work_orders WHERE (status = 'completed' OR status = 'Completed') AND updated_at >= ?
    ");
    $completedWO->execute([$thisMonthStart]);
    $woCompleted = $completedWO->fetchColumn();
} catch (Exception $e) {
    $woCompleted = 0;
}

// ============ HR METRICS ============
// Total employees
try {
    $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
} catch (Exception $e) {
    $totalEmployees = 0;
}

// Today's attendance
try {
    $todayAttendance = $pdo->prepare("
        SELECT COUNT(*) FROM attendance WHERE date = ? AND status = 'Present'
    ");
    $todayAttendance->execute([$today]);
    $presentToday = $todayAttendance->fetchColumn();
} catch (Exception $e) {
    $presentToday = 0;
}
$attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;

// ============ MARKETING METRICS ============
// Active campaigns
try {
    $activeCampaigns = $pdo->query("
        SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'active'
    ")->fetchColumn();
} catch (Exception $e) {
    $activeCampaigns = 0;
}

// ============ TASKS METRICS ============
$pendingTasks = 0;
$overdueTasks = 0;
$tasksCompletedMonth = 0;
$highPriorityTasks = 0;
try {
    $pendingTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('pending', 'in_progress')")->fetchColumn();
} catch (Exception $e) {}
try {
    $overdueTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('pending', 'in_progress') AND due_date < CURDATE()")->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND updated_at >= ?");
    $stmt->execute([$thisMonthStart]);
    $tasksCompletedMonth = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $highPriorityTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE priority = 'high' AND status IN ('pending', 'in_progress')")->fetchColumn();
} catch (Exception $e) {}

// ============ QUALITY CONTROL METRICS ============
$openNCRs = 0;
$calibrationDue = 0;
$pendingPPAP = 0;
$pendingInspections = 0;
try {
    $openNCRs = $pdo->query("SELECT COUNT(*) FROM supplier_ncrs WHERE status NOT IN ('closed', 'Closed')")->fetchColumn();
} catch (Exception $e) {}
try {
    $calibrationDue = $pdo->query("SELECT COUNT(*) FROM calibration_records WHERE next_calibration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'")->fetchColumn();
} catch (Exception $e) {}
try {
    $pendingPPAP = $pdo->query("SELECT COUNT(*) FROM ppap_submissions WHERE status IN ('pending', 'in_review', 'Pending', 'In Review')")->fetchColumn();
} catch (Exception $e) {}
try {
    $pendingInspections = $pdo->query("SELECT COUNT(*) FROM po_inspection_checklists WHERE status IN ('Draft', 'Submitted')")->fetchColumn();
} catch (Exception $e) {}

// ============ ACCOUNTS & FINANCE METRICS ============
$totalReceivables = 0;
$totalPayables = 0;
$expensesThisMonth = 0;
$pendingVouchers = 0;
try {
    // Receivables from unpaid/partial invoices
    $totalReceivables = $pdo->query("
        SELECT COALESCE(SUM(qi.total_amount), 0)
        FROM invoice_master im
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status IN ('released', 'pending') AND (im.payment_status IS NULL OR im.payment_status != 'paid')
    ")->fetchColumn();
} catch (Exception $e) {}
try {
    // Payables from open purchase orders
    $totalPayables = $pdo->query("
        SELECT COALESCE(SUM(qty * rate), 0) FROM purchase_orders WHERE status IN ('open', 'pending', 'partial')
    ")->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$thisMonthStart, $thisMonthEnd]);
    $expensesThisMonth = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $pendingVouchers = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {}

// ============ LEAVE MANAGEMENT METRICS ============
$pendingLeaveRequests = 0;
$employeesOnLeave = 0;
$leaveRequestsMonth = 0;
try {
    $pendingLeaveRequests = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM leave_requests WHERE status = 'Approved' AND start_date <= ? AND end_date >= ?");
    $stmt->execute([$today, $today]);
    $employeesOnLeave = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE created_at >= ?");
    $stmt->execute([$thisMonthStart]);
    $leaveRequestsMonth = $stmt->fetchColumn();
} catch (Exception $e) {}

// ============ PROJECT ENGINEERING METRICS ============
$activeProjects = 0;
$pendingECOs = 0;
$openFindings = 0;
$reviewsThisMonth = 0;
try {
    $activeProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('active', 'in_progress', 'Active', 'In Progress')")->fetchColumn();
} catch (Exception $e) {}
try {
    $pendingECOs = $pdo->query("SELECT COUNT(*) FROM engineering_change_orders WHERE status IN ('pending', 'review', 'Pending', 'Review')")->fetchColumn();
} catch (Exception $e) {}
try {
    $openFindings = $pdo->query("SELECT COUNT(*) FROM review_findings WHERE status NOT IN ('closed', 'Closed', 'resolved', 'Resolved')")->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM engineering_reviews WHERE review_date >= ?");
    $stmt->execute([$thisMonthStart]);
    $reviewsThisMonth = $stmt->fetchColumn();
} catch (Exception $e) {}

// ============ GOOGLE REVIEWS ============
$googleReviews = [
    'rating' => null,
    'count' => 0,
    'url' => null,
    'updated_at' => null
];
try {
    $reviewData = $pdo->query("
        SELECT google_review_rating, google_review_count, google_review_url, google_review_updated_at
        FROM company_settings WHERE id = 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($reviewData && $reviewData['google_review_rating']) {
        $googleReviews = [
            'rating' => $reviewData['google_review_rating'],
            'count' => $reviewData['google_review_count'] ?? 0,
            'url' => $reviewData['google_review_url'],
            'updated_at' => $reviewData['google_review_updated_at']
        ];
    }
} catch (Exception $e) {
    // Columns may not exist yet
}

// ============ MARKET CLASSIFICATION SUMMARY ============
$marketClassificationData = [];
$marketTotalValue = 0;
$marketTotalLeads = 0;
try {
    $marketStmt = $pdo->query("
        SELECT
            COALESCE(NULLIF(l.market_classification, ''), 'Not Specified') as market_classification,
            COUNT(l.id) as total_leads,
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
        GROUP BY COALESCE(NULLIF(l.market_classification, ''), 'Not Specified')
        ORDER BY converted_value DESC, total_value DESC
    ");
    $marketClassificationData = $marketStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($marketClassificationData as $row) {
        $marketTotalValue += (float)$row['converted_value'];
        $marketTotalLeads += (int)$row['total_leads'];
    }
} catch (Exception $e) {
    // Column may not exist
}

// ============ TOP PERFORMERS ============
// Top 5 customers by revenue this year
try {
    $topCustomers = $pdo->prepare("
        SELECT c.company_name, c.customer_name, COALESCE(SUM(qi.total_amount), 0) as revenue
        FROM customers c
        JOIN invoice_master im ON c.id = im.customer_id
        LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
        LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
        WHERE im.status = 'released' AND im.invoice_date >= ?
        GROUP BY c.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $topCustomers->execute([$thisYearStart]);
    $topCustomersList = $topCustomers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topCustomersList = [];
}

// Top 5 products by sales (from quotes that have been converted to sales orders)
try {
    $topProducts = $pdo->prepare("
        SELECT qi.part_name, SUM(qi.qty) as qty_sold, SUM(qi.total_amount) as revenue
        FROM quote_items qi
        JOIN quote_master qm ON qi.quote_id = qm.id
        INNER JOIN sales_orders so ON so.linked_quote_id = qm.id
        WHERE qm.created_at >= ?
        GROUP BY qi.part_no, qi.part_name
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $topProducts->execute([$thisYearStart]);
    $topProductsList = $topProducts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topProductsList = [];
}

// ============ MONTHLY TREND DATA ============
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthName = date('M', strtotime("-$i months"));

    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(qi.total_amount), 0)
            FROM invoice_master im
            LEFT JOIN (SELECT DISTINCT so_no, linked_quote_id FROM sales_orders) so ON so.so_no = im.so_no
            LEFT JOIN quote_items qi ON qi.quote_id = so.linked_quote_id
            WHERE im.status = 'released' AND im.invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$monthStart, $monthEnd]);
        $revenue = (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        $revenue = 0;
    }
    $monthlyRevenue[] = [
        'month' => $monthName,
        'revenue' => $revenue
    ];
}

// ============ ALERTS / ACTION ITEMS ============
$alerts = [];

if ($lowStockItems > 0) {
    $alerts[] = ['type' => 'warning', 'icon' => '📦', 'message' => "$lowStockItems items below minimum stock level", 'link' => '/part_master/min_stock.php'];
}
if ($openComplaints > 5) {
    $alerts[] = ['type' => 'danger', 'icon' => '🔧', 'message' => "$openComplaints open service complaints pending", 'link' => '/service/complaints.php'];
}
if ($pendingPOCount > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '📋', 'message' => "$pendingPOCount purchase orders awaiting approval", 'link' => '/purchase/index.php'];
}
if ($hotLeads > 0) {
    $alerts[] = ['type' => 'success', 'icon' => '🔥', 'message' => "$hotLeads hot leads require immediate follow-up", 'link' => '/crm/index.php?status=hot'];
}
if ($pendingInstallations > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '🔨', 'message' => "$pendingInstallations installations scheduled/in progress", 'link' => '/installations/index.php'];
}
if ($overdueTasks > 0) {
    $alerts[] = ['type' => 'danger', 'icon' => '⏰', 'message' => "$overdueTasks tasks are overdue", 'link' => '/tasks/index.php'];
}
if ($pendingLeaveRequests > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '🏖️', 'message' => "$pendingLeaveRequests leave requests pending approval", 'link' => '/hr/leaves.php'];
}
if ($openNCRs > 0) {
    $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'message' => "$openNCRs open supplier NCRs require attention", 'link' => '/quality_control/ncrs.php'];
}
if ($calibrationDue > 0) {
    $alerts[] = ['type' => 'warning', 'icon' => '🔧', 'message' => "$calibrationDue instruments due for calibration", 'link' => '/quality_control/calibration.php'];
}
if ($pendingECOs > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '📝', 'message' => "$pendingECOs engineering change orders pending review", 'link' => '/project_management/change_requests.php'];
}
if ($pendingInspections > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '🔍', 'message' => "$pendingInspections PO inspections pending approval", 'link' => '/stock_entry/index.php'];
}

include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Executive Dashboard - Chairman View</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ceo-dashboard {
            padding: 20px;
            padding-top: calc(48px + 20px);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e74c3c;
        }
        .dashboard-header h1 {
            margin: 0;
            color: var(--text, #2c3e50);
            font-size: 1.8em;
        }
        .dashboard-header .header-subtitle {
            color: var(--muted-text, #7f8c8d);
        }
        .dashboard-header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }
        .dashboard-header .date-info {
            color: var(--text, #2c3e50);
            font-size: 0.95em;
            text-align: right;
        }
        .dashboard-header .date-info small {
            color: var(--muted-text, #7f8c8d);
        }

        /* KPI Cards Row */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .kpi-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .kpi-card.blue { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }
        .kpi-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .kpi-card.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .kpi-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }

        .kpi-card .kpi-label {
            font-size: 0.85em;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .kpi-card .kpi-value {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-card .kpi-sub {
            font-size: 0.8em;
            opacity: 0.85;
        }
        .kpi-card .kpi-trend {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            margin-top: 8px;
        }
        .kpi-trend.up { background: rgba(255,255,255,0.3); }
        .kpi-trend.down { background: rgba(255,0,0,0.3); }

        /* Section Containers */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .dashboard-section {
            background: var(--card, white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .section-title {
            font-size: 1.1em;
            color: var(--text, #2c3e50);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border, #eee);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title .icon {
            font-size: 1.3em;
        }

        /* Module Summary Cards */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .module-card {
            background: var(--card, white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #3498db;
        }
        .module-card.sales { border-left-color: #27ae60; }
        .module-card.service { border-left-color: #e74c3c; }
        .module-card.inventory { border-left-color: #f39c12; }
        .module-card.purchase { border-left-color: #9b59b6; }
        .module-card.hr { border-left-color: #1abc9c; }
        .module-card.crm { border-left-color: #3498db; }

        .module-card h3 {
            margin: 0 0 15px 0;
            color: var(--text, #2c3e50);
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .module-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .module-stat {
            text-align: center;
            padding: 10px;
            background: var(--bg, #f8f9fa);
            border-radius: 8px;
        }
        .module-stat .stat-value {
            font-size: 1.4em;
            font-weight: bold;
            color: var(--text, #2c3e50);
        }
        .module-stat .stat-label {
            font-size: 0.75em;
            color: var(--muted-text, #7f8c8d);
            margin-top: 3px;
        }

        /* Alerts Section */
        .alerts-container {
            margin-bottom: 25px;
        }
        .alert-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            background: var(--card, white);
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .alert-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .alert-item.warning { border-left: 4px solid #f39c12; }
        .alert-item.danger { border-left: 4px solid #e74c3c; }
        .alert-item.info { border-left: 4px solid #3498db; }
        .alert-item.success { border-left: 4px solid #27ae60; }
        .alert-icon { font-size: 1.5em; }
        .alert-message { flex: 1; color: var(--text, #2c3e50); }
        .alert-arrow { color: var(--muted-text, #bdc3c7); }

        /* Top Performers Tables */
        .performers-table {
            width: 100%;
            border-collapse: collapse;
        }
        .performers-table th, .performers-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--border, #eee);
            color: var(--text, #2c3e50);
        }
        .performers-table th {
            font-weight: 600;
            color: var(--muted-text, #7f8c8d);
            font-size: 0.85em;
        }
        .performers-table tr:hover {
            background: var(--bg, #f8f9fa);
        }
        .rank-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            font-size: 0.8em;
            font-weight: bold;
        }
        .rank-1 { background: #ffd700; color: #000; }
        .rank-2 { background: #c0c0c0; color: #000; }
        .rank-3 { background: #cd7f32; color: #fff; }
        .rank-4, .rank-5 { background: #e0e0e0; color: var(--muted-text, #666); }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
        }

        /* Quick Stats Row */
        .quick-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .quick-stat {
            flex: 1;
            min-width: 100px;
            text-align: center;
        }
        .quick-stat .value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--text, #2c3e50);
        }
        .quick-stat .label {
            font-size: 0.8em;
            color: var(--muted-text, #7f8c8d);
        }

        /* Responsive */
        /* Print PDF Button */
        .btn-print-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95em;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-print-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .kpi-row {
                grid-template-columns: 1fr 1fr;
            }
            .module-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .dashboard-header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<div class="content ceo-dashboard" style="overflow-y: auto; height: 100vh;">
    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h1>Executive Dashboard</h1>
            <span class="header-subtitle">Chairman's Business Overview</span>
        </div>
        <div class="dashboard-header-actions">
            <a href="ceo_dashboard_print.php" class="btn-print-pdf">
                📄 Print PDF
            </a>
            <div class="date-info">
                <strong><?= date('l, d F Y') ?></strong><br>
                <small>Data as of <?= date('h:i A') ?></small>
            </div>
        </div>
    </div>

    <!-- Main KPIs -->
    <div class="kpi-row">
        <div class="kpi-card green">
            <div class="kpi-label">Revenue This Month</div>
            <div class="kpi-value">₹<?= number_format($salesThisMonth, 0) ?></div>
            <div class="kpi-sub">vs ₹<?= number_format($salesLastMonth, 0) ?> last month</div>
            <span class="kpi-trend <?= $salesGrowth >= 0 ? 'up' : 'down' ?>">
                <?= $salesGrowth >= 0 ? '↑' : '↓' ?> <?= abs($salesGrowth) ?>%
            </span>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-label">YTD Revenue</div>
            <div class="kpi-value">₹<?= number_format($salesYTD, 0) ?></div>
            <div class="kpi-sub">Since <?= date('M Y', strtotime($thisYearStart)) ?></div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-label">Quote Conversion</div>
            <div class="kpi-value"><?= $conversionRate ?>%</div>
            <div class="kpi-sub"><?= $convertedCount ?> of <?= $totalQuotes ?> quotes converted</div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-label">Active Customers</div>
            <div class="kpi-value"><?= number_format($totalCustomers) ?></div>
            <div class="kpi-sub">+<?= $newCustomers ?> new this month</div>
        </div>
        <div class="kpi-card red">
            <div class="kpi-label">Open Issues</div>
            <div class="kpi-value"><?= $openComplaints ?></div>
            <div class="kpi-sub">Service complaints pending</div>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if (!empty($alerts)): ?>
    <div class="alerts-container">
        <h3 style="margin-bottom: 15px; color: var(--text, #2c3e50);">Action Required</h3>
        <?php foreach ($alerts as $alert): ?>
            <a href="<?= $alert['link'] ?>" class="alert-item <?= $alert['type'] ?>">
                <span class="alert-icon"><?= $alert['icon'] ?></span>
                <span class="alert-message"><?= $alert['message'] ?></span>
                <span class="alert-arrow">→</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Module Cards -->
    <div class="module-grid">
        <!-- Sales Module -->
        <div class="module-card sales">
            <h3><span>💰</span> Sales & Revenue</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $totalOrdersThisMonth ?></div>
                    <div class="stat-label">Orders This Month</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingInvoices['count'] ?></div>
                    <div class="stat-label">Pending Invoices</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value">₹<?= number_format($pendingInvoices['value'] / 100000, 1) ?>L</div>
                    <div class="stat-label">Pending Value</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $totalQuotes ?></div>
                    <div class="stat-label">Quotes Generated</div>
                </div>
            </div>
        </div>

        <!-- CRM Module -->
        <div class="module-card crm">
            <h3><span>👥</span> CRM & Leads</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $totalLeads ?></div>
                    <div class="stat-label">Total Leads</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: #e74c3c;"><?= $hotLeads ?></div>
                    <div class="stat-label">Hot Leads</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $leadsConverted ?></div>
                    <div class="stat-label">Converted (Month)</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $newCustomers ?></div>
                    <div class="stat-label">New Customers</div>
                </div>
            </div>
        </div>

        <!-- Service Module -->
        <div class="module-card service">
            <h3><span>🔧</span> Service & Support</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $openComplaints > 10 ? '#e74c3c' : '#27ae60' ?>;"><?= $openComplaints ?></div>
                    <div class="stat-label">Open Complaints</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $complaintsResolved ?></div>
                    <div class="stat-label">Resolved (Month)</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $avgResolutionDays ?> days</div>
                    <div class="stat-label">Avg Resolution</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingInstallations ?></div>
                    <div class="stat-label">Pending Install</div>
                </div>
            </div>
        </div>

        <!-- Inventory Module -->
        <div class="module-card inventory">
            <h3><span>📦</span> Inventory</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value">₹<?= number_format($stockValue / 100000, 1) ?>L</div>
                    <div class="stat-label">Stock Value</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $lowStockItems > 0 ? '#f39c12' : '#27ae60' ?>;"><?= $lowStockItems ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $outOfStock > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $outOfStock ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $activeWO ?></div>
                    <div class="stat-label">Active Work Orders</div>
                </div>
            </div>
        </div>

        <!-- Purchase Module -->
        <div class="module-card purchase">
            <h3><span>🛒</span> Purchase</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingPOCount ?></div>
                    <div class="stat-label">Pending POs</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value">₹<?= number_format($pendingPOValue / 100000, 1) ?>L</div>
                    <div class="stat-label">Pending Value</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value">₹<?= number_format($purchaseThisMonth / 100000, 1) ?>L</div>
                    <div class="stat-label">Purchased (Month)</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $woCompleted ?></div>
                    <div class="stat-label">WO Completed</div>
                </div>
            </div>
        </div>

        <!-- HR Module -->
        <div class="module-card hr">
            <h3><span>👔</span> Human Resources</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $totalEmployees ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $presentToday ?></div>
                    <div class="stat-label">Present Today</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $attendanceRate ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $employeesOnLeave > 0 ? '#f39c12' : '#27ae60' ?>;"><?= $employeesOnLeave ?></div>
                    <div class="stat-label">On Leave Today</div>
                </div>
            </div>
        </div>

        <!-- Tasks Module -->
        <div class="module-card" style="border-left-color: #e67e22;">
            <h3><span>✅</span> Tasks & Activities</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingTasks ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $overdueTasks > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $overdueTasks ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: #e74c3c;"><?= $highPriorityTasks ?></div>
                    <div class="stat-label">High Priority</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: #27ae60;"><?= $tasksCompletedMonth ?></div>
                    <div class="stat-label">Done (Month)</div>
                </div>
            </div>
        </div>

        <!-- Quality Control Module -->
        <div class="module-card" style="border-left-color: #16a085;">
            <h3><span>🔬</span> Quality Control</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $openNCRs > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $openNCRs ?></div>
                    <div class="stat-label">Open NCRs</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $calibrationDue > 0 ? '#f39c12' : '#27ae60' ?>;"><?= $calibrationDue ?></div>
                    <div class="stat-label">Calibration Due</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingPPAP ?></div>
                    <div class="stat-label">Pending PPAP</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingInspections ?></div>
                    <div class="stat-label">PO Inspections</div>
                </div>
            </div>
        </div>

        <!-- Accounts & Finance Module -->
        <div class="module-card" style="border-left-color: #2980b9;">
            <h3><span>💳</span> Accounts & Finance</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value" style="color: #27ae60;">₹<?= number_format($totalReceivables / 100000, 1) ?>L</div>
                    <div class="stat-label">Receivables</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: #e74c3c;">₹<?= number_format($totalPayables / 100000, 1) ?>L</div>
                    <div class="stat-label">Payables</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value">₹<?= number_format($expensesThisMonth / 100000, 1) ?>L</div>
                    <div class="stat-label">Expenses (Month)</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingVouchers ?></div>
                    <div class="stat-label">Pending Vouchers</div>
                </div>
            </div>
        </div>

        <!-- Project Engineering Module -->
        <div class="module-card" style="border-left-color: #8e44ad;">
            <h3><span>🛠️</span> Product Engineering</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $activeProjects ?></div>
                    <div class="stat-label">Active Projects</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $pendingECOs > 0 ? '#f39c12' : '#27ae60' ?>;"><?= $pendingECOs ?></div>
                    <div class="stat-label">Pending ECOs</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value" style="color: <?= $openFindings > 0 ? '#e74c3c' : '#27ae60' ?>;"><?= $openFindings ?></div>
                    <div class="stat-label">Open Findings</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $reviewsThisMonth ?></div>
                    <div class="stat-label">Reviews (Month)</div>
                </div>
            </div>
        </div>

        <!-- Marketing Module -->
        <div class="module-card" style="border-left-color: #d35400;">
            <h3><span>📢</span> Marketing</h3>
            <div class="module-stats">
                <div class="module-stat">
                    <div class="stat-value"><?= $activeCampaigns ?></div>
                    <div class="stat-label">Active Campaigns</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $pendingLeaveRequests ?></div>
                    <div class="stat-label">Leave Requests</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $leaveRequestsMonth ?></div>
                    <div class="stat-label">Leave (Month)</div>
                </div>
                <div class="module-stat">
                    <div class="stat-value"><?= $employeesOnLeave ?></div>
                    <div class="stat-label">On Leave</div>
                </div>
            </div>
        </div>

        <!-- Google Reviews Widget -->
        <?php if ($googleReviews['rating']): ?>
        <div class="module-card" style="border-left-color: #fbbc04; background: linear-gradient(135deg, #fff 0%, #fffbeb 100%);">
            <h3><span>⭐</span> Google Reviews</h3>
            <div style="display: flex; align-items: center; gap: 20px; padding: 10px 0;">
                <div style="text-align: center;">
                    <div style="font-size: 2.5em; font-weight: bold; color: var(--text, #2c3e50);">
                        <?= number_format($googleReviews['rating'], 1) ?>
                    </div>
                    <div style="color: #fbbc04; font-size: 1.4em; letter-spacing: 2px;">
                        <?php
                        $rating = $googleReviews['rating'];
                        $fullStars = floor($rating);
                        $halfStar = ($rating - $fullStars) >= 0.5;
                        for ($i = 0; $i < $fullStars; $i++) echo '★';
                        if ($halfStar) echo '★';
                        for ($i = $fullStars + ($halfStar ? 1 : 0); $i < 5; $i++) echo '☆';
                        ?>
                    </div>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 1.1em; color: var(--text, #2c3e50); font-weight: 600;">
                        <?= number_format($googleReviews['count']) ?> Reviews
                    </div>
                    <div style="font-size: 0.8em; color: var(--muted-text, #666); margin-top: 5px;">
                        on Google
                    </div>
                    <?php if ($googleReviews['url']): ?>
                    <a href="<?= htmlspecialchars($googleReviews['url']) ?>" target="_blank"
                       style="display: inline-block; margin-top: 10px; font-size: 0.85em; color: #4285f4; text-decoration: none;">
                        View Reviews →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($googleReviews['updated_at']): ?>
            <div style="font-size: 0.75em; color: var(--muted-text, #999); margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border, #eee);">
                Last updated: <?= date('d M Y', strtotime($googleReviews['updated_at'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="module-card" style="border-left-color: #fbbc04;">
            <h3><span>⭐</span> Google Reviews</h3>
            <div style="padding: 15px 0; color: var(--muted-text, #666); text-align: center;">
                <p style="margin: 0 0 10px 0;">No Google Reviews data configured</p>
                <a href="/admin/settings.php#google-reviews" class="btn btn-secondary" style="font-size: 0.85em;">
                    Configure Now
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Market Classification Summary -->
    <?php if (!empty($marketClassificationData)): ?>
    <div class="dashboard-section" style="margin-bottom: 25px;">
        <div class="section-title">
            <span class="icon">🏷️</span>
            Sales by Market Classification
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
            <?php
            $marketColors = [
                'GEMS or Tenders' => '#9b59b6',
                'Export Orders' => '#3498db',
                'Corporate Customers' => '#27ae60',
                'Private Hospitals' => '#e74c3c',
                'Medical Colleges' => '#f39c12',
                'NGO or Others' => '#1abc9c',
                'Not Specified' => '#95a5a6'
            ];
            foreach ($marketClassificationData as $market):
                $color = $marketColors[$market['market_classification']] ?? '#7f8c8d';
                $percentage = $marketTotalValue > 0 ? round(($market['converted_value'] / $marketTotalValue) * 100, 1) : 0;
            ?>
            <div style="background: var(--card, white); border-radius: 10px; padding: 15px; border-left: 4px solid <?= $color ?>; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div style="font-weight: 600; color: var(--text, #2c3e50); font-size: 0.95em; margin-bottom: 10px;">
                    <?= htmlspecialchars($market['market_classification']) ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                    <div>
                        <div style="font-size: 1.4em; font-weight: bold; color: <?= $color ?>;">
                            ₹<?= number_format($market['converted_value'] / 100000, 1) ?>L
                        </div>
                        <div style="font-size: 0.75em; color: var(--muted-text, #7f8c8d);">Actual Sales</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.1em; font-weight: 600; color: var(--text, #2c3e50);">
                            <?= $market['total_leads'] ?>
                        </div>
                        <div style="font-size: 0.75em; color: var(--muted-text, #7f8c8d);">Leads</div>
                    </div>
                </div>
                <div style="margin-top: 10px; background: var(--border, #f0f0f0); border-radius: 4px; height: 6px; overflow: hidden;">
                    <div style="width: <?= $percentage ?>%; height: 100%; background: <?= $color ?>; border-radius: 4px;"></div>
                </div>
                <div style="font-size: 0.7em; color: var(--muted-text, #999); margin-top: 5px; text-align: right;">
                    <?= $percentage ?>% of total sales
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border, #eee); display: flex; justify-content: space-between; align-items: center;">
            <div style="color: var(--muted-text, #7f8c8d); font-size: 0.9em;">
                Total: <strong style="color: var(--text, #2c3e50);"><?= $marketTotalLeads ?> Leads</strong>
            </div>
            <div style="font-size: 1.2em; font-weight: bold; color: #27ae60;">
                Total Sales: ₹<?= number_format($marketTotalValue, 0) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts and Tables Row -->
    <div class="dashboard-grid">
        <!-- Revenue Trend Chart -->
        <div class="dashboard-section">
            <div class="section-title">
                <span class="icon">📈</span>
                Revenue Trend (Last 6 Months)
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="dashboard-section">
            <div class="section-title">
                <span class="icon">🏆</span>
                Top Customers (YTD)
            </div>
            <table class="performers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCustomersList as $i => $cust): ?>
                    <tr>
                        <td><span class="rank-badge rank-<?= $i + 1 ?>"><?= $i + 1 ?></span></td>
                        <td><?= htmlspecialchars($cust['company_name'] ?: $cust['customer_name']) ?></td>
                        <td><strong>₹<?= number_format($cust['revenue'], 0) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topCustomersList)): ?>
                    <tr><td colspan="3" style="text-align: center; color: var(--muted-text, #999);">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Second Charts Row -->
    <div class="dashboard-grid">
        <!-- Top Products -->
        <div class="dashboard-section">
            <div class="section-title">
                <span class="icon">📦</span>
                Top Selling Products (YTD)
            </div>
            <table class="performers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProductsList as $i => $prod): ?>
                    <tr>
                        <td><span class="rank-badge rank-<?= $i + 1 ?>"><?= $i + 1 ?></span></td>
                        <td><?= htmlspecialchars($prod['part_name']) ?></td>
                        <td><?= number_format($prod['qty_sold']) ?></td>
                        <td><strong>₹<?= number_format($prod['revenue'], 0) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topProductsList)): ?>
                    <tr><td colspan="4" style="text-align: center; color: var(--muted-text, #999);">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Business Health Indicators -->
        <div class="dashboard-section">
            <div class="section-title">
                <span class="icon">❤️</span>
                Business Health Indicators
            </div>
            <div style="padding: 10px 0;">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Customer Satisfaction</span>
                        <span><strong><?= $openComplaints < 5 ? 'Excellent' : ($openComplaints < 15 ? 'Good' : 'Needs Attention') ?></strong></span>
                    </div>
                    <div style="background: var(--border, #eee); border-radius: 10px; height: 10px; overflow: hidden;">
                        <div style="background: <?= $openComplaints < 5 ? '#27ae60' : ($openComplaints < 15 ? '#f39c12' : '#e74c3c') ?>; height: 100%; width: <?= max(20, 100 - ($openComplaints * 5)) ?>%;"></div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Inventory Health</span>
                        <span><strong><?= $lowStockItems == 0 ? 'Optimal' : ($lowStockItems < 10 ? 'Moderate' : 'Critical') ?></strong></span>
                    </div>
                    <div style="background: var(--border, #eee); border-radius: 10px; height: 10px; overflow: hidden;">
                        <div style="background: <?= $lowStockItems == 0 ? '#27ae60' : ($lowStockItems < 10 ? '#f39c12' : '#e74c3c') ?>; height: 100%; width: <?= max(20, 100 - ($lowStockItems * 3)) ?>%;"></div>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Sales Pipeline</span>
                        <span><strong><?= $hotLeads > 5 ? 'Strong' : ($hotLeads > 0 ? 'Moderate' : 'Weak') ?></strong></span>
                    </div>
                    <div style="background: var(--border, #eee); border-radius: 10px; height: 10px; overflow: hidden;">
                        <div style="background: <?= $hotLeads > 5 ? '#27ae60' : ($hotLeads > 0 ? '#f39c12' : '#e74c3c') ?>; height: 100%; width: <?= min(100, $hotLeads * 10 + 30) ?>%;"></div>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Workforce Availability</span>
                        <span><strong><?= $attendanceRate ?>%</strong></span>
                    </div>
                    <div style="background: var(--border, #eee); border-radius: 10px; height: 10px; overflow: hidden;">
                        <div style="background: <?= $attendanceRate >= 90 ? '#27ae60' : ($attendanceRate >= 70 ? '#f39c12' : '#e74c3c') ?>; height: 100%; width: <?= $attendanceRate ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation -->
    <div class="dashboard-section" style="margin-top: 20px;">
        <div class="section-title">
            <span class="icon">🚀</span>
            Quick Navigation
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <!-- Sales & CRM -->
            <a href="/crm/dashboard.php" class="btn btn-primary">CRM Dashboard</a>
            <a href="/invoices/index.php" class="btn btn-primary">Invoices</a>
            <a href="/sales_orders/index.php" class="btn btn-primary">Sales Orders</a>
            <a href="/quotes/index.php" class="btn btn-primary">Quotations</a>

            <!-- Operations -->
            <a href="/purchase/dashboard.php" class="btn btn-primary">Purchase</a>
            <a href="/inventory/dashboard.php" class="btn btn-primary">Inventory</a>
            <a href="/work_orders/index.php" class="btn btn-primary">Work Orders</a>

            <!-- Quality & Engineering -->
            <a href="/quality_control/dashboard.php" class="btn btn-primary">Quality Control</a>
            <a href="/project_management/dashboard.php" class="btn btn-primary">Engineering</a>

            <!-- Service & Support -->
            <a href="/service/dashboard.php" class="btn btn-primary">Service</a>
            <a href="/installations/index.php" class="btn btn-primary">Installations</a>

            <!-- HR & Admin -->
            <a href="/hr/dashboard.php" class="btn btn-primary">HR Dashboard</a>
            <a href="/hr/leaves.php" class="btn btn-primary">Leave Requests</a>
            <a href="/tasks/dashboard.php" class="btn btn-primary">Tasks</a>

            <!-- Finance -->
            <a href="/accounts/dashboard.php" class="btn btn-primary">Accounts</a>

            <!-- Marketing -->
            <a href="/marketing/dashboard.php" class="btn btn-primary">Marketing</a>

            <!-- Reports -->
            <a href="/reports/monthly.php" class="btn btn-secondary">Monthly Reports</a>
        </div>
    </div>
</div>

<script>
// Revenue Trend Chart
const ctx = document.getElementById('revenueChart').getContext('2d');
const monthlyData = <?= json_encode($monthlyRevenue) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
            label: 'Revenue',
            data: monthlyData.map(d => d.revenue),
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '₹' + context.raw.toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 100000) {
                            return '₹' + (value / 100000).toFixed(1) + 'L';
                        }
                        return '₹' + value.toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
