<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();
requirePermission('crm');
include "../includes/dialog.php";

// Get current user info
$currentUserId = getUserId();
$currentUserRole = getUserRole();
$isAdmin = ($currentUserRole === 'admin');

/* =========================
   FILTERS
========================= */
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_timeline = $_GET['timeline'] ?? '';
$filter_assigned = $_GET['assigned'] ?? '';
$filter_followup = $_GET['followup'] ?? '';
$filter_interaction_due = $_GET['interaction_due'] ?? '';
$search = $_GET['search'] ?? '';

/* =========================
   GET EMPLOYEES FOR ASSIGN TO FILTER
========================= */
$assignedEmployees = [];
try {
    $empStmt = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as employee_name, department
        FROM employees
        WHERE status = 'Active'
        ORDER BY first_name, last_name
    ");
    $assignedEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}

/* =========================
   PAGINATION SETUP
========================= */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 15;
$offset = ($page - 1) * $per_page;

/* =========================
   BUILD QUERY WITH FILTERS
========================= */
$where = [];
$params = [];

// All logged-in users can see all leads
// Note: assigned_user_id is from employees table, not users table

if ($filter_status) {
    $where[] = "lead_status = ?";
    $params[] = $filter_status;
}
if ($filter_type) {
    $where[] = "customer_type = ?";
    $params[] = $filter_type;
}
if ($filter_timeline) {
    $where[] = "buying_timeline = ?";
    $params[] = $filter_timeline;
}
if ($filter_assigned) {
    $where[] = "assigned_user_id = ?";
    $params[] = $filter_assigned;
}
if ($filter_followup === 'today') {
    $where[] = "next_followup_date = CURDATE()";
}
if ($filter_followup === 'overdue') {
    $where[] = "next_followup_date < CURDATE() AND lead_status NOT IN ('converted', 'lost')";
}
// Interaction Due filter: leads where last interaction exceeds the schedule
// Hot=1 day, Warm=3 days, Cold=5 days
if ($filter_interaction_due === 'all') {
    $where[] = "lead_status IN ('hot','warm','cold') AND (
        (lead_status = 'hot' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY)))
        OR (lead_status = 'warm' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)))
        OR (lead_status = 'cold' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 5 DAY)))
    )";
}
if ($filter_interaction_due === 'hot') {
    $where[] = "lead_status = 'hot' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY))";
}
if ($filter_interaction_due === 'warm') {
    $where[] = "lead_status = 'warm' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY))";
}
if ($filter_interaction_due === 'cold') {
    $where[] = "lead_status = 'cold' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 5 DAY))";
}
if ($search) {
    $where[] = "(company_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM crm_leads $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get leads
$sql = "
    SELECT l.*,
           (SELECT COUNT(*) FROM crm_lead_requirements WHERE lead_id = l.id) as req_count,
           (SELECT COUNT(*) FROM crm_lead_interactions WHERE lead_id = l.id) as interaction_count
    FROM crm_leads l
    $whereClause
    ORDER BY l.id DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   STATS FOR DASHBOARD
========================= */
$statsWhere = "";
$statsParams = [];
// All users see all leads stats

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN lead_status = 'hot' THEN 1 ELSE 0 END) as hot,
        SUM(CASE WHEN lead_status = 'warm' THEN 1 ELSE 0 END) as warm,
        SUM(CASE WHEN lead_status = 'cold' THEN 1 ELSE 0 END) as cold,
        SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) as lost,
        SUM(CASE WHEN next_followup_date = CURDATE() THEN 1 ELSE 0 END) as followup_today,
        SUM(CASE WHEN next_followup_date < CURDATE() AND lead_status NOT IN ('converted', 'lost') THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN lead_status IN ('hot','warm','cold') AND (
            (lead_status = 'hot' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 1 DAY)))
            OR (lead_status = 'warm' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)))
            OR (lead_status = 'cold' AND (last_contact_date IS NULL OR last_contact_date < DATE_SUB(CURDATE(), INTERVAL 5 DAY)))
        ) THEN 1 ELSE 0 END) as interaction_due
    FROM crm_leads $statsWhere
");
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>CRM - Lead Management</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #fff;
        }
        .stat-card .number { font-size: 2em; font-weight: bold; }
        .stat-card .label { font-size: 0.85em; opacity: 0.9; }
        .stat-total { background: #3498db; }
        .stat-hot { background: #e74c3c; }
        .stat-warm { background: #f39c12; }
        .stat-cold { background: #95a5a6; }
        .stat-converted { background: #27ae60; }
        .stat-lost { background: #7f8c8d; }
        .stat-followup { background: #9b59b6; }
        .stat-overdue { background: #c0392b; }
        .stat-interaction-due { background: #e67e22; }

        .interaction-due-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: bold;
            background: #e67e22;
            color: #fff;
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* Clickable stat cards */
        a.stat-card {
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        a.stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
            filter: brightness(1.1);
        }
        a.stat-card.active {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3), inset 0 0 0 3px rgba(255,255,255,0.5);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .lead-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-hot { background: #e74c3c; color: #fff; }
        .status-warm { background: #f39c12; color: #fff; }
        .status-cold { background: #bdc3c7; color: #2c3e50; }
        .status-converted { background: #27ae60; color: #fff; }
        .status-lost { background: #7f8c8d; color: #fff; }

        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .type-b2b { background: #3498db; color: #fff; }
        .type-b2c { background: #9b59b6; color: #fff; }

        .timeline-badge {
            font-size: 0.8em;
            color: #666;
        }
        .timeline-immediate { color: #e74c3c; font-weight: bold; }
        .timeline-1_month { color: #f39c12; }

        .followup-overdue { color: #e74c3c; font-weight: bold; }
        .followup-today { color: #27ae60; font-weight: bold; }
        .followup-upcoming { color: #3498db; }

        table { width: 100%; }
        table th { background: #2c3e50; color: #fff; }
    </style>
</head>
<body>

<div class="content">
    <h1>CRM - Lead Management</h1>


    <!-- Stats Dashboard (Clickable to filter) -->
    <div class="stats-grid">
        <a href="index.php" class="stat-card stat-total <?= empty($filter_status) && empty($filter_followup) ? 'active' : '' ?>">
            <div class="number"><?= $stats['total'] ?? 0 ?></div>
            <div class="label">Total Leads</div>
        </a>
        <a href="index.php?status=hot" class="stat-card stat-hot <?= $filter_status === 'hot' ? 'active' : '' ?>">
            <div class="number"><?= $stats['hot'] ?? 0 ?></div>
            <div class="label">Hot</div>
        </a>
        <a href="index.php?status=warm" class="stat-card stat-warm <?= $filter_status === 'warm' ? 'active' : '' ?>">
            <div class="number"><?= $stats['warm'] ?? 0 ?></div>
            <div class="label">Warm</div>
        </a>
        <a href="index.php?status=cold" class="stat-card stat-cold <?= $filter_status === 'cold' ? 'active' : '' ?>">
            <div class="number"><?= $stats['cold'] ?? 0 ?></div>
            <div class="label">Cold</div>
        </a>
        <a href="index.php?status=converted" class="stat-card stat-converted <?= $filter_status === 'converted' ? 'active' : '' ?>">
            <div class="number"><?= $stats['converted'] ?? 0 ?></div>
            <div class="label">Converted</div>
        </a>
        <a href="index.php?status=lost" class="stat-card stat-lost <?= $filter_status === 'lost' ? 'active' : '' ?>">
            <div class="number"><?= $stats['lost'] ?? 0 ?></div>
            <div class="label">Lost</div>
        </a>
        <a href="index.php?followup=today" class="stat-card stat-followup <?= $filter_followup === 'today' ? 'active' : '' ?>">
            <div class="number"><?= $stats['followup_today'] ?? 0 ?></div>
            <div class="label">Follow-up Today</div>
        </a>
        <a href="index.php?followup=overdue" class="stat-card stat-overdue <?= $filter_followup === 'overdue' ? 'active' : '' ?>">
            <div class="number"><?= $stats['overdue'] ?? 0 ?></div>
            <div class="label">Overdue</div>
        </a>
        <a href="index.php?interaction_due=all" class="stat-card stat-interaction-due <?= $filter_interaction_due === 'all' ? 'active' : '' ?>">
            <div class="number"><?= $stats['interaction_due'] ?? 0 ?></div>
            <div class="label">Interaction Due</div>
        </a>
    </div>

    <p>
        <a href="add.php" class="btn btn-primary">+ Add New Lead</a>
    </p>

    <!-- Filters -->
    <form method="get" class="filters">
        <input type="text" name="search" placeholder="Search name, phone, email..."
               value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">

        <select name="status">
            <option value="">All Status</option>
            <option value="hot" <?= $filter_status === 'hot' ? 'selected' : '' ?>>Hot</option>
            <option value="warm" <?= $filter_status === 'warm' ? 'selected' : '' ?>>Warm</option>
            <option value="cold" <?= $filter_status === 'cold' ? 'selected' : '' ?>>Cold</option>
            <option value="converted" <?= $filter_status === 'converted' ? 'selected' : '' ?>>Converted</option>
            <option value="lost" <?= $filter_status === 'lost' ? 'selected' : '' ?>>Lost</option>
        </select>

        <select name="type">
            <option value="">All Types</option>
            <option value="B2B" <?= $filter_type === 'B2B' ? 'selected' : '' ?>>B2B</option>
            <option value="B2C" <?= $filter_type === 'B2C' ? 'selected' : '' ?>>B2C</option>
        </select>

        <select name="timeline">
            <option value="">All Timelines</option>
            <option value="immediate" <?= $filter_timeline === 'immediate' ? 'selected' : '' ?>>Immediate</option>
            <option value="1_month" <?= $filter_timeline === '1_month' ? 'selected' : '' ?>>Within 1 Month</option>
            <option value="3_months" <?= $filter_timeline === '3_months' ? 'selected' : '' ?>>Within 3 Months</option>
            <option value="6_months" <?= $filter_timeline === '6_months' ? 'selected' : '' ?>>Within 6 Months</option>
            <option value="1_year" <?= $filter_timeline === '1_year' ? 'selected' : '' ?>>Within 1 Year</option>
            <option value="uncertain" <?= $filter_timeline === 'uncertain' ? 'selected' : '' ?>>Uncertain</option>
        </select>

        <?php if (!empty($assignedEmployees)): ?>
        <select name="assigned">
            <option value="">All Assigned To</option>
            <?php foreach ($assignedEmployees as $emp):
                $empName = htmlspecialchars($emp['employee_name']);
                if (!empty($emp['department'])) {
                    $empName .= ' (' . htmlspecialchars($emp['department']) . ')';
                }
            ?>
                <option value="<?= $emp['id'] ?>"
                    <?= $filter_assigned == $emp['id'] ? 'selected' : '' ?>>
                    <?= $empName ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="index.php" class="btn btn-secondary">Clear</a>
    </form>

    <!-- Interaction Due Schedule Info -->
    <?php if ($filter_interaction_due): ?>
    <div style="margin-bottom: 20px; padding: 15px 20px; background: #fef3e2; border: 1px solid #f39c12; border-radius: 8px;">
        <strong style="color: #e67e22;">Interaction Schedule:</strong>
        <span style="margin-left: 15px; padding: 4px 10px; background: #e74c3c; color: #fff; border-radius: 4px; font-size: 0.85em;">Hot = Every Day</span>
        <span style="margin-left: 10px; padding: 4px 10px; background: #f39c12; color: #fff; border-radius: 4px; font-size: 0.85em;">Warm = Every 3 Days</span>
        <span style="margin-left: 10px; padding: 4px 10px; background: #95a5a6; color: #fff; border-radius: 4px; font-size: 0.85em;">Cold = Every 5 Days</span>
        <?php if ($filter_interaction_due !== 'all'): ?>
            <span style="margin-left: 15px;">| Showing: <strong><?= ucfirst($filter_interaction_due) ?></strong> leads only</span>
        <?php endif; ?>
        <div style="margin-top: 8px; font-size: 0.85em; color: #666;">
            <a href="index.php?interaction_due=hot" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.85em; <?= $filter_interaction_due === 'hot' ? 'background: #e74c3c; color: #fff;' : '' ?>">Hot Due</a>
            <a href="index.php?interaction_due=warm" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.85em; <?= $filter_interaction_due === 'warm' ? 'background: #f39c12; color: #fff;' : '' ?>">Warm Due</a>
            <a href="index.php?interaction_due=cold" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.85em; <?= $filter_interaction_due === 'cold' ? 'background: #95a5a6; color: #fff;' : '' ?>">Cold Due</a>
            <a href="index.php?interaction_due=all" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.85em; <?= $filter_interaction_due === 'all' ? 'background: #e67e22; color: #fff;' : '' ?>">All Due</a>
            <a href="index.php" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.85em;">Clear</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leads Table -->
    <div style="overflow-x: auto;">
    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>Lead #</th>
                <th>Type</th>
                <th>Company / Contact</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Buying Timeline</th>
                <th>Last Contact</th>
                <th>Next Follow-up</th>
                <th>Assigned To</th>
                <th>Reqs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $lead): ?>
            <?php
                $followupClass = '';
                if ($lead['next_followup_date']) {
                    $fdate = strtotime($lead['next_followup_date']);
                    $today = strtotime(date('Y-m-d'));
                    if ($fdate < $today && !in_array($lead['lead_status'], ['converted', 'lost'])) {
                        $followupClass = 'followup-overdue';
                    } elseif ($fdate == $today) {
                        $followupClass = 'followup-today';
                    } else {
                        $followupClass = 'followup-upcoming';
                    }
                }

                // Check if interaction is due based on lead status
                $interactionDue = false;
                $dueLabel = '';
                $ls = strtolower($lead['lead_status']);
                $lastContact = $lead['last_contact_date'] ? strtotime($lead['last_contact_date']) : 0;
                $todayTs = strtotime(date('Y-m-d'));
                $daysSinceContact = $lastContact ? floor(($todayTs - $lastContact) / 86400) : 999;

                if ($ls === 'hot' && $daysSinceContact > 1) {
                    $interactionDue = true;
                    $dueLabel = 'Due (Daily)';
                } elseif ($ls === 'warm' && $daysSinceContact > 3) {
                    $interactionDue = true;
                    $dueLabel = 'Due (3 Days)';
                } elseif ($ls === 'cold' && $daysSinceContact > 5) {
                    $interactionDue = true;
                    $dueLabel = 'Due (5 Days)';
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($lead['lead_no']) ?></strong></td>
                <td>
                    <span class="type-badge type-<?= strtolower($lead['customer_type']) ?>">
                        <?= $lead['customer_type'] ?>
                    </span>
                </td>
                <td>
                    <?php if ($lead['company_name']): ?>
                        <strong><?= htmlspecialchars($lead['company_name']) ?></strong><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($lead['contact_person']) ?>
                    <?php if ($lead['designation']): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars($lead['designation']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= htmlspecialchars($lead['phone'] ?? '-') ?>
                    <?php if ($lead['email']): ?>
                        <br><small><?= htmlspecialchars($lead['email']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="lead-status status-<?= $lead['lead_status'] ?>">
                        <?= ucfirst($lead['lead_status']) ?>
                    </span>
                    <?php if ($interactionDue): ?>
                        <br><span class="interaction-due-badge"><?= $dueLabel ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="timeline-badge timeline-<?= $lead['buying_timeline'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $lead['buying_timeline'])) ?>
                    </span>
                    <?php if ($lead['budget_range']): ?>
                        <br><small>Budget: <?= htmlspecialchars($lead['budget_range']) ?></small>
                    <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                    <?php if ($lead['last_contact_date']): ?>
                        <?= date('d M', strtotime($lead['last_contact_date'])) ?>
                        <br><small style="color: <?= $interactionDue ? '#e74c3c' : '#27ae60' ?>;">
                            <?= $daysSinceContact ?> day<?= $daysSinceContact !== 1 ? 's' : '' ?> ago
                        </small>
                    <?php else: ?>
                        <span style="color: #e74c3c; font-weight: bold;">Never</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($lead['next_followup_date']): ?>
                        <span class="<?= $followupClass ?>">
                            <?= date('d M Y', strtotime($lead['next_followup_date'])) ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">Not set</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= htmlspecialchars($lead['assigned_to'] ?? '-') ?>
                </td>
                <td style="text-align: center;">
                    <?= $lead['req_count'] ?>
                </td>
                <td style="white-space: nowrap;">
                    <a class="btn btn-secondary" href="view.php?id=<?= $lead['id'] ?>">View</a>
                    <a class="btn btn-primary" href="edit.php?id=<?= $lead['id'] ?>">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($leads)): ?>
            <tr>
                <td colspan="11" style="text-align: center; padding: 30px;">
                    No leads found. <a href="add.php">Add your first lead</a>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $queryString = http_build_query($queryParams);
            $queryString = $queryString ? '&' . $queryString : '';
        ?>
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $queryString ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $queryString ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>

        <span style="margin: 0 10px;">
            Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> leads)
        </span>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $queryString ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
