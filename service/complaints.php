<?php
include "../db.php";
include "../includes/dialog.php";

// Filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category = $_GET['category'] ?? '';
$technician = $_GET['technician'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "c.status = ?";
    $params[] = $status;
}
if ($priority) {
    $where[] = "c.priority = ?";
    $params[] = $priority;
}
if ($category) {
    $where[] = "c.issue_category_id = ?";
    $params[] = $category;
}
if ($technician) {
    $where[] = "c.assigned_technician_id = ?";
    $params[] = $technician;
}
if ($search) {
    $where[] = "(c.complaint_no LIKE ? OR c.customer_name LIKE ? OR c.customer_phone LIKE ? OR c.product_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
if ($dateFrom) {
    $where[] = "DATE(c.registered_date) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(c.registered_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(" AND ", $where);

// Get complaints
$sql = "
    SELECT c.*,
           cat.name AS category_name,
           t.name AS technician_name,
           s.state_name
    FROM service_complaints c
    LEFT JOIN service_issue_categories cat ON c.issue_category_id = cat.id
    LEFT JOIN service_technicians t ON c.assigned_technician_id = t.id
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE $whereClause
    ORDER BY
        CASE c.priority
            WHEN 'Critical' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            ELSE 4
        END,
        c.registered_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$categories = $pdo->query("SELECT id, name FROM service_issue_categories WHERE is_active = 1 ORDER BY name")->fetchAll();
$technicians = $pdo->query("SELECT id, name FROM service_technicians WHERE status = 'Active' ORDER BY name")->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) AS assigned_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
        SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN priority = 'Critical' AND status NOT IN ('Resolved', 'Closed', 'Cancelled') THEN 1 ELSE 0 END) AS critical_pending
    FROM service_complaints
")->fetch(PDO::FETCH_ASSOC);

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Complaints</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            min-width: 120px;
        }
        .stat-card .number {
            font-size: 1.8em;
            font-weight: bold;
        }
        .stat-card .label {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        .stat-card.total { border-top: 4px solid #3498db; }
        .stat-card.open { border-top: 4px solid #e74c3c; }
        .stat-card.assigned { border-top: 4px solid #f39c12; }
        .stat-card.progress { border-top: 4px solid #9b59b6; }
        .stat-card.resolved { border-top: 4px solid #27ae60; }
        .stat-card.critical { border-top: 4px solid #c0392b; background: #fdf2f2; }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-row .form-group { margin: 0; }
        .filter-row select, .filter-row input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 140px;
        }

        .complaints-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .complaints-table th, .complaints-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .complaints-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        .complaints-table tr:hover { background: #fafafa; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-Open { background: #ffeaa7; color: #d68910; }
        .status-Assigned { background: #dfe6e9; color: #636e72; }
        .status-In-Progress { background: #e8daef; color: #8e44ad; }
        .status-On-Hold { background: #fad7a0; color: #d35400; }
        .status-Resolved { background: #d5f4e6; color: #27ae60; }
        .status-Closed { background: #d4e6f1; color: #2980b9; }
        .status-Cancelled { background: #fadbd8; color: #c0392b; }

        .priority-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
        }
        .priority-Critical { background: #e74c3c; color: white; }
        .priority-High { background: #e67e22; color: white; }
        .priority-Medium { background: #f1c40f; color: #333; }
        .priority-Low { background: #95a5a6; color: white; }

        .customer-info { font-size: 0.9em; }
        .customer-info .name { font-weight: bold; }
        .customer-info .phone { color: #7f8c8d; }

        .complaint-no {
            font-weight: bold;
            color: #3498db;
        }
        .complaint-no:hover { text-decoration: underline; }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Service Complaints</h1>

    <div class="stats-bar">
        <div class="stat-card total">
            <div class="number"><?= $stats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-card open">
            <div class="number"><?= $stats['open_count'] ?></div>
            <div class="label">Open</div>
        </div>
        <div class="stat-card assigned">
            <div class="number"><?= $stats['assigned_count'] ?></div>
            <div class="label">Assigned</div>
        </div>
        <div class="stat-card progress">
            <div class="number"><?= $stats['in_progress_count'] ?></div>
            <div class="label">In Progress</div>
        </div>
        <div class="stat-card resolved">
            <div class="number"><?= $stats['resolved_count'] ?></div>
            <div class="label">Resolved</div>
        </div>
        <?php if ($stats['critical_pending'] > 0): ?>
        <div class="stat-card critical">
            <div class="number"><?= $stats['critical_pending'] ?></div>
            <div class="label">Critical Pending</div>
        </div>
        <?php endif; ?>
    </div>

    <p>
        <a href="complaint_add.php" class="btn btn-success">+ New Complaint</a>
        <a href="technicians.php" class="btn btn-secondary">Technicians</a>
        <a href="analytics.php" class="btn btn-secondary">Analytics</a>
    </p>

    <div class="filter-section">
        <form method="get">
            <div class="filter-row">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Complaint #, Name, Phone...">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="Open" <?= $status === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="Assigned" <?= $status === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="On Hold" <?= $status === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="Resolved" <?= $status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="Closed" <?= $status === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="">All Priority</option>
                        <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Technician</label>
                    <select name="technician">
                        <option value="">All Technicians</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['id'] ?>" <?= $technician == $tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?= $dateFrom ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?= $dateTo ?>">
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="complaints.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <?php if (empty($complaints)): ?>
        <div class="no-data">
            <p>No complaints found.</p>
            <a href="complaint_add.php" class="btn btn-primary">Register First Complaint</a>
        </div>
    <?php else: ?>
        <table class="complaints-table">
            <thead>
                <tr>
                    <th>Complaint #</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Issue</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $c): ?>
                <tr>
                    <td>
                        <a href="complaint_view.php?id=<?= $c['id'] ?>" class="complaint-no"><?= htmlspecialchars($c['complaint_no']) ?></a>
                    </td>
                    <td class="customer-info">
                        <div class="name"><?= htmlspecialchars($c['customer_name']) ?></div>
                        <div class="phone"><?= htmlspecialchars($c['customer_phone']) ?></div>
                        <?php if ($c['city']): ?>
                            <div class="phone"><?= htmlspecialchars($c['city']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['product_name'] ?? '-') ?>
                        <?php if ($c['product_model']): ?>
                            <br><small style="color:#7f8c8d;"><?= htmlspecialchars($c['product_model']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['category_name'] ?? '-') ?></td>
                    <td><span class="priority-badge priority-<?= $c['priority'] ?>"><?= $c['priority'] ?></span></td>
                    <td><?= htmlspecialchars($c['technician_name'] ?? 'Unassigned') ?></td>
                    <td><span class="status-badge status-<?= str_replace(' ', '-', $c['status']) ?>"><?= $c['status'] ?></span></td>
                    <td><?= date('d M Y', strtotime($c['registered_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; color: #7f8c8d;">
            Showing <?= count($complaints) ?> complaint(s)
        </div>
    <?php endif; ?>
</div>

</body>
</html>
