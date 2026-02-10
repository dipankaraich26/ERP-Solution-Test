<?php
include "../db.php";
include "../includes/auth.php";
requireLogin();

// Only admin can access
if (getUserRole() !== 'admin') {
    http_response_code(403);
    include __DIR__ . "/../includes/403.php";
    exit;
}

/* =========================
   FILTERS
========================= */
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$filter_module = isset($_GET['module']) ? trim($_GET['module']) : '';
$filter_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$filter_ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'logins';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Get all users for dropdown
$allUsers = $pdo->query("SELECT id, username, full_name, role FROM users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get distinct modules for dropdown
$allModules = [];
try {
    $allModules = $pdo->query("SELECT DISTINCT module FROM activity_log WHERE module IS NOT NULL AND module != '' ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

/* =========================
   TAB: LOGINS - Login/Logout history
========================= */
if ($tab === 'logins') {
    $where = ["a.action IN ('login', 'logout', 'login_failed')"];
    $params = [];

    if ($filter_user > 0) {
        $where[] = "a.user_id = :user_id";
        $params[':user_id'] = $filter_user;
    }
    if ($filter_date_from !== '') {
        $where[] = "DATE(a.created_at) >= :date_from";
        $params[':date_from'] = $filter_date_from;
    }
    if ($filter_date_to !== '') {
        $where[] = "DATE(a.created_at) <= :date_to";
        $params[':date_to'] = $filter_date_to;
    }
    if ($filter_ip !== '') {
        $where[] = "a.ip_address LIKE :ip";
        $params[':ip'] = '%' . $filter_ip . '%';
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log a $whereSQL");
    foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total_count = $countStmt->fetchColumn();
    $total_pages = max(1, ceil($total_count / $per_page));

    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.username, u.role
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        $whereSQL
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   TAB: MODULE ACCESS
========================= */
if ($tab === 'modules') {
    $where = ["a.action = 'module_access'"];
    $params = [];

    if ($filter_user > 0) {
        $where[] = "a.user_id = :user_id";
        $params[':user_id'] = $filter_user;
    }
    if ($filter_module !== '') {
        $where[] = "a.module = :module";
        $params[':module'] = $filter_module;
    }
    if ($filter_date_from !== '') {
        $where[] = "DATE(a.created_at) >= :date_from";
        $params[':date_from'] = $filter_date_from;
    }
    if ($filter_date_to !== '') {
        $where[] = "DATE(a.created_at) <= :date_to";
        $params[':date_to'] = $filter_date_to;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log a $whereSQL");
    foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total_count = $countStmt->fetchColumn();
    $total_pages = max(1, ceil($total_count / $per_page));

    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.username, u.role
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        $whereSQL
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   TAB: ACTIVE USERS (Today's activity summary)
========================= */
if ($tab === 'active') {
    $activeDate = $filter_date_from ?: date('Y-m-d');
    $activeUsers = $pdo->prepare("
        SELECT
            u.id, u.username, u.full_name, u.role, u.last_login,
            MIN(a.created_at) AS first_activity,
            MAX(a.created_at) AS last_activity,
            SUM(CASE WHEN a.action = 'login' THEN 1 ELSE 0 END) AS login_count,
            SUM(CASE WHEN a.action = 'module_access' THEN 1 ELSE 0 END) AS module_visits,
            GROUP_CONCAT(DISTINCT CASE WHEN a.action = 'module_access' THEN a.module END ORDER BY a.module SEPARATOR ', ') AS modules_visited,
            MAX(a.ip_address) AS ip_address
        FROM users u
        JOIN activity_log a ON a.user_id = u.id AND DATE(a.created_at) = :active_date
        GROUP BY u.id, u.username, u.full_name, u.role, u.last_login
        ORDER BY last_activity DESC
    ");
    $activeUsers->execute([':active_date' => $activeDate]);
    $activeUsersList = $activeUsers->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   TAB: SUMMARY STATS
========================= */
if ($tab === 'summary') {
    // Logins per day (last 30 days)
    $dailyLogins = $pdo->query("
        SELECT DATE(created_at) AS log_date, COUNT(*) AS login_count
        FROM activity_log
        WHERE action = 'login' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY log_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Most active users (last 30 days)
    $topUsers = $pdo->query("
        SELECT u.full_name, u.username, u.role,
            SUM(CASE WHEN a.action = 'login' THEN 1 ELSE 0 END) AS logins,
            SUM(CASE WHEN a.action = 'module_access' THEN 1 ELSE 0 END) AS module_visits,
            MAX(a.created_at) AS last_seen
        FROM activity_log a
        JOIN users u ON a.user_id = u.id
        WHERE a.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY u.id, u.full_name, u.username, u.role
        ORDER BY logins DESC, module_visits DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Most visited modules (last 30 days)
    $topModules = $pdo->query("
        SELECT module, COUNT(*) AS visits, COUNT(DISTINCT user_id) AS unique_users
        FROM activity_log
        WHERE action = 'module_access' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY module
        ORDER BY visits DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Failed login attempts (last 30 days)
    $failedLogins = $pdo->query("
        SELECT DATE(created_at) AS log_date, COUNT(*) AS fail_count, GROUP_CONCAT(DISTINCT ip_address SEPARATOR ', ') AS ips
        FROM activity_log
        WHERE action = 'login_failed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY log_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Quick stats for header
$todayLogins = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE action = 'login' AND DATE(created_at) = CURDATE()")->fetchColumn();
$todayActiveUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_log WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL")->fetchColumn();
$todayFailed = $pdo->query("SELECT COUNT(*) FROM activity_log WHERE action = 'login_failed' AND DATE(created_at) = CURDATE()")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Build filter query for pagination
$filterParams = array_filter([
    'tab' => $tab,
    'user_id' => $filter_user ?: '',
    'action' => $filter_action,
    'module' => $filter_module,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'ip' => $filter_ip,
]);
$filterQuery = http_build_query($filterParams);

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login & Activity Logs</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .value {
            font-size: 2em;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }

        .tab-nav {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .tab-nav a {
            padding: 10px 22px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-nav a:hover { color: #333; }
        .tab-nav a.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .filter-bar {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-bar form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar .fg {
            min-width: 140px;
        }
        .filter-bar label {
            display: block;
            font-size: 0.8em;
            font-weight: bold;
            color: #555;
            margin-bottom: 4px;
        }
        .filter-bar select, .filter-bar input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th, .log-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; font-size: 0.9em; }
        .log-table th { background: #f8f9fa; font-weight: 600; color: #555; position: sticky; top: 0; }
        .log-table tr:hover { background: #f8f9fa; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .badge-login { background: #d4edda; color: #155724; }
        .badge-logout { background: #d1ecf1; color: #0c5460; }
        .badge-failed { background: #f8d7da; color: #721c24; }
        .badge-access { background: #e2e3f1; color: #383d6e; }
        .badge-admin { background: #dc3545; color: white; }
        .badge-manager { background: #fd7e14; color: white; }
        .badge-user { background: #0dcaf0; color: #000; }
        .badge-viewer { background: #6c757d; color: white; }

        .user-agent-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.8em;
            color: #888;
        }

        .module-tag {
            display: inline-block;
            background: #e8f4fc;
            color: #2980b9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin: 1px 2px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 20px;
        }
        .summary-card h3 {
            margin: 0 0 15px 0;
            font-size: 1em;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }

        .bar-chart {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .bar-chart .bar-label {
            width: 100px;
            font-size: 0.85em;
            text-align: right;
            color: #555;
            flex-shrink: 0;
        }
        .bar-chart .bar-fill {
            height: 20px;
            border-radius: 3px;
            min-width: 2px;
            transition: width 0.3s;
        }
        .bar-chart .bar-value {
            font-size: 0.8em;
            color: #666;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="content">
    <h1>Login & Activity Logs</h1>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="value" style="color: #27ae60;"><?= $todayLogins ?></div>
            <div class="label">Logins Today</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color: #3498db;"><?= $todayActiveUsers ?></div>
            <div class="label">Active Users Today</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color: #e74c3c;"><?= $todayFailed ?></div>
            <div class="label">Failed Attempts Today</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color: #8e44ad;"><?= $totalUsers ?></div>
            <div class="label">Total Active Users</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <a href="?tab=logins" class="<?= $tab === 'logins' ? 'active' : '' ?>">Login History</a>
        <a href="?tab=modules" class="<?= $tab === 'modules' ? 'active' : '' ?>">Module Access</a>
        <a href="?tab=active" class="<?= $tab === 'active' ? 'active' : '' ?>">Active Users</a>
        <a href="?tab=summary" class="<?= $tab === 'summary' ? 'active' : '' ?>">Summary</a>
    </div>

    <!-- ===== LOGIN HISTORY TAB ===== -->
    <?php if ($tab === 'logins'): ?>
    <div class="filter-bar">
        <form method="get">
            <input type="hidden" name="tab" value="logins">
            <div class="fg">
                <label>User</label>
                <select name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['full_name']) ?> (<?= $u['role'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="fg">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="fg">
                <label>IP Address</label>
                <input type="text" name="ip" value="<?= htmlspecialchars($filter_ip) ?>" placeholder="e.g. 192.168...">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="padding: 7px 18px;">Filter</button>
                <a href="?tab=logins" class="btn btn-secondary" style="padding: 7px 18px; text-decoration: none;">Clear</a>
            </div>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table class="log-table">
        <thead>
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Date & Time</th>
                <th>IP Address</th>
                <th>Browser / Device</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">No login records found.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $idx => $log): ?>
            <tr>
                <td style="color: #aaa;"><?= $offset + $idx + 1 ?></td>
                <td>
                    <?php if ($log['user_id']): ?>
                        <strong><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></strong>
                        <br><small style="color: #888;"><?= htmlspecialchars($log['username'] ?? '') ?></small>
                    <?php else: ?>
                        <span style="color: #e74c3c;">
                            <?php
                            // Extract username from details for failed logins
                            $det = $log['details'] ?? '';
                            if (preg_match('/Username:\s*(\S+)/', $det, $m)) {
                                echo htmlspecialchars($m[1]);
                            } else {
                                echo 'Unknown';
                            }
                            ?>
                        </span>
                        <br><small style="color: #999;">Not authenticated</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($log['role']): ?>
                        <span class="badge badge-<?= $log['role'] ?>"><?= ucfirst($log['role']) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $actionClass = match($log['action']) {
                        'login' => 'badge-login',
                        'logout' => 'badge-logout',
                        'login_failed' => 'badge-failed',
                        default => 'badge-access',
                    };
                    $actionLabel = match($log['action']) {
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'login_failed' => 'Failed',
                        default => ucfirst($log['action']),
                    };
                    ?>
                    <span class="badge <?= $actionClass ?>"><?= $actionLabel ?></span>
                </td>
                <td>
                    <?= date('d M Y', strtotime($log['created_at'])) ?>
                    <br><small style="color: #888;"><?= date('h:i:s A', strtotime($log['created_at'])) ?></small>
                </td>
                <td><code style="font-size: 0.85em;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                <td class="user-agent-cell" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                    <?php
                    $ua = $log['details'] ?? '';
                    // For failed logins, strip the "Username: xxx | " prefix
                    if ($log['action'] === 'login_failed' && str_contains($ua, '|')) {
                        $ua = trim(substr($ua, strpos($ua, '|') + 1));
                    }
                    // Parse user agent into friendly name
                    $browser = 'Unknown';
                    if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
                    elseif (stripos($ua, 'Chrome/') !== false) $browser = 'Chrome';
                    elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
                    elseif (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome') === false) $browser = 'Safari';
                    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) $browser = 'IE';

                    $os = 'Unknown';
                    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
                    elseif (stripos($ua, 'Mac') !== false) $os = 'Mac';
                    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';
                    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
                    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';

                    echo $ua ? "$browser / $os" : '-';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php $pq = $filterQuery ? '&' . $filterQuery : ''; ?>
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $pq ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $pq ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> records)</span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $pq ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $pq ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== MODULE ACCESS TAB ===== -->
    <?php elseif ($tab === 'modules'): ?>
    <div class="filter-bar">
        <form method="get">
            <input type="hidden" name="tab" value="modules">
            <div class="fg">
                <label>User</label>
                <select name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Module</label>
                <select name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($allModules as $mod): ?>
                        <option value="<?= htmlspecialchars($mod) ?>" <?= $filter_module === $mod ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($mod)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="fg">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="padding: 7px 18px;">Filter</button>
                <a href="?tab=modules" class="btn btn-secondary" style="padding: 7px 18px; text-decoration: none;">Clear</a>
            </div>
        </form>
    </div>

    <div style="overflow-x: auto;">
    <table class="log-table">
        <thead>
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Module</th>
                <th>Page</th>
                <th>Date & Time</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">No module access records found. Module tracking starts from now.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $idx => $log): ?>
            <tr>
                <td style="color: #aaa;"><?= $offset + $idx + 1 ?></td>
                <td>
                    <strong><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></strong>
                    <br><small style="color: #888;"><?= htmlspecialchars($log['username'] ?? '') ?></small>
                </td>
                <td><span class="badge badge-<?= $log['role'] ?? 'user' ?>"><?= ucfirst($log['role'] ?? '-') ?></span></td>
                <td><span class="module-tag"><?= htmlspecialchars(ucfirst($log['module'])) ?></span></td>
                <td style="font-size: 0.85em; color: #666;"><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                <td>
                    <?= date('d M Y', strtotime($log['created_at'])) ?>
                    <br><small style="color: #888;"><?= date('h:i:s A', strtotime($log['created_at'])) ?></small>
                </td>
                <td><code style="font-size: 0.85em;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php $pq = $filterQuery ? '&' . $filterQuery : ''; ?>
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $pq ?>" class="btn btn-secondary">First</a>
            <a href="?page=<?= $page - 1 ?><?= $pq ?>" class="btn btn-secondary">Previous</a>
        <?php endif; ?>
        <span style="margin: 0 10px;">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_count ?> records)</span>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= $pq ?>" class="btn btn-secondary">Next</a>
            <a href="?page=<?= $total_pages ?><?= $pq ?>" class="btn btn-secondary">Last</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== ACTIVE USERS TAB ===== -->
    <?php elseif ($tab === 'active'): ?>
    <div class="filter-bar">
        <form method="get">
            <input type="hidden" name="tab" value="active">
            <div class="fg">
                <label>Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($activeDate) ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="padding: 7px 18px;">View</button>
            </div>
        </form>
    </div>

    <p style="color: #666; margin-bottom: 15px;">Showing user activity for <strong><?= date('d M Y', strtotime($activeDate)) ?></strong> (<?= count($activeUsersList) ?> users)</p>

    <div style="overflow-x: auto;">
    <table class="log-table">
        <thead>
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Logins</th>
                <th>Module Visits</th>
                <th>Modules Accessed</th>
                <th>First Activity</th>
                <th>Last Activity</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($activeUsersList)): ?>
            <tr><td colspan="9" style="text-align: center; padding: 30px; color: #999;">No user activity found for this date.</td></tr>
            <?php endif; ?>
            <?php foreach ($activeUsersList as $idx => $au): ?>
            <tr>
                <td style="color: #aaa;"><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($au['full_name']) ?></strong><br><small style="color: #888;"><?= htmlspecialchars($au['username']) ?></small></td>
                <td><span class="badge badge-<?= $au['role'] ?>"><?= ucfirst($au['role']) ?></span></td>
                <td style="text-align: center;">
                    <span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 10px; font-weight: bold;"><?= $au['login_count'] ?></span>
                </td>
                <td style="text-align: center;">
                    <span style="background: #e2e3f1; color: #383d6e; padding: 3px 10px; border-radius: 10px; font-weight: bold;"><?= $au['module_visits'] ?></span>
                </td>
                <td>
                    <?php
                    $mods = array_filter(explode(', ', $au['modules_visited'] ?? ''));
                    foreach ($mods as $m): ?>
                        <span class="module-tag"><?= htmlspecialchars(ucfirst($m)) ?></span>
                    <?php endforeach;
                    if (empty($mods)) echo '<span style="color: #ccc;">-</span>';
                    ?>
                </td>
                <td>
                    <?= date('h:i:s A', strtotime($au['first_activity'])) ?>
                </td>
                <td>
                    <?= date('h:i:s A', strtotime($au['last_activity'])) ?>
                </td>
                <td><code style="font-size: 0.85em;"><?= htmlspecialchars($au['ip_address'] ?? '-') ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- ===== SUMMARY TAB ===== -->
    <?php elseif ($tab === 'summary'): ?>
    <p style="color: #666; margin-bottom: 20px;">Activity summary for the last 30 days</p>

    <div class="summary-grid">
        <!-- Daily Logins Chart -->
        <div class="summary-card">
            <h3>Daily Logins (Last 30 Days)</h3>
            <?php
            $maxDaily = max(array_column($dailyLogins, 'login_count') ?: [1]);
            foreach (array_slice($dailyLogins, 0, 15) as $dl):
                $pct = ($dl['login_count'] / $maxDaily) * 100;
            ?>
            <div class="bar-chart">
                <div class="bar-label"><?= date('d M', strtotime($dl['log_date'])) ?></div>
                <div class="bar-fill" style="width: <?= $pct ?>%; background: #3498db;"></div>
                <div class="bar-value"><?= $dl['login_count'] ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($dailyLogins)): ?>
                <p style="text-align: center; color: #999;">No login data yet.</p>
            <?php endif; ?>
        </div>

        <!-- Most Active Users -->
        <div class="summary-card">
            <h3>Most Active Users</h3>
            <?php
            $maxUserLogins = max(array_column($topUsers, 'logins') ?: [1]);
            foreach ($topUsers as $tu):
                $pct = ($tu['logins'] / $maxUserLogins) * 100;
            ?>
            <div class="bar-chart">
                <div class="bar-label" title="<?= htmlspecialchars($tu['full_name']) ?>">
                    <?= htmlspecialchars(mb_substr($tu['full_name'], 0, 14)) ?>
                </div>
                <div class="bar-fill" style="width: <?= $pct ?>%; background: #27ae60;"></div>
                <div class="bar-value"><?= $tu['logins'] ?> logins, <?= $tu['module_visits'] ?> visits</div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topUsers)): ?>
                <p style="text-align: center; color: #999;">No user data yet.</p>
            <?php endif; ?>
        </div>

        <!-- Most Visited Modules -->
        <div class="summary-card">
            <h3>Most Visited Modules</h3>
            <?php
            $maxModVisits = max(array_column($topModules, 'visits') ?: [1]);
            foreach ($topModules as $tm):
                $pct = ($tm['visits'] / $maxModVisits) * 100;
            ?>
            <div class="bar-chart">
                <div class="bar-label"><?= htmlspecialchars(ucfirst($tm['module'])) ?></div>
                <div class="bar-fill" style="width: <?= $pct ?>%; background: #9b59b6;"></div>
                <div class="bar-value"><?= $tm['visits'] ?> (<?= $tm['unique_users'] ?> users)</div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topModules)): ?>
                <p style="text-align: center; color: #999;">No module access data yet. Data will appear as users browse modules.</p>
            <?php endif; ?>
        </div>

        <!-- Failed Login Attempts -->
        <div class="summary-card">
            <h3>Failed Login Attempts</h3>
            <?php if (!empty($failedLogins)): ?>
                <table class="log-table" style="font-size: 0.85em;">
                    <tr><th>Date</th><th>Failures</th><th>IPs</th></tr>
                    <?php foreach ($failedLogins as $fl): ?>
                    <tr>
                        <td><?= date('d M', strtotime($fl['log_date'])) ?></td>
                        <td><span class="badge badge-failed"><?= $fl['fail_count'] ?></span></td>
                        <td style="font-size: 0.85em; color: #666;"><?= htmlspecialchars($fl['ips']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #27ae60; padding: 20px;">No failed login attempts in the last 30 days.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
