<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include "../db.php";

if (!isset($_SESSION['emp_attendance_id'])) {
    header("Location: attendance_login.php");
    exit;
}

$empId = $_SESSION['emp_attendance_id'];
$empName = $_SESSION['emp_attendance_name'];
$empDept = $_SESSION['emp_attendance_dept'];
$empDesignation = $_SESSION['emp_attendance_designation'];
$empPhoto = $_SESSION['emp_attendance_photo'];
$empCode = $_SESSION['emp_attendance_emp_id'];

// AJAX handler for advance detail
if (isset($_GET['ajax_advance_id'])) {
    header('Content-Type: application/json');
    $advId = (int)$_GET['ajax_advance_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT ap.*,
                   CONCAT(a.first_name, ' ', a.last_name) as approver_name
            FROM advance_payments ap
            LEFT JOIN employees a ON ap.approved_by = a.id
            WHERE ap.id = ? AND ap.employee_id = ?
        ");
        $stmt->execute([$advId, $empId]);
        $adv = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($adv) {
            // Get repayments
            $repStmt = $pdo->prepare("SELECT * FROM advance_repayments WHERE advance_id = ? ORDER BY repayment_date DESC");
            $repStmt->execute([$advId]);
            $adv['repayments'] = $repStmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'advance' => $adv]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Advance not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_advance'])) {
    $advance_type = $_POST['advance_type'] ?? 'Salary';
    $amount = floatval($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $repayment_months = intval($_POST['repayment_months'] ?? 1);

    $errors = [];
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";
    if (!$purpose) $errors[] = "Purpose is required.";
    if ($repayment_months < 1 || $repayment_months > 24) $errors[] = "Repayment months must be 1-24.";

    // Check existing active advance
    $existing = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ? AND status IN ('Pending','Approved','Disbursed')");
    $existing->execute([$empId]);
    if ($existing->fetchColumn() > 0) {
        $errors[] = "You already have an active advance. Please wait until it's closed.";
    }

    if (empty($errors)) {
        try {
            $monthly_deduction = round($amount / $repayment_months, 2);
            $lastId = $pdo->query("SELECT MAX(id) FROM advance_payments")->fetchColumn();
            $advanceNo = 'ADV-' . date('Y') . '-' . str_pad(($lastId + 1), 4, '0', STR_PAD_LEFT);

            $pdo->prepare("
                INSERT INTO advance_payments (
                    advance_no, employee_id, advance_type, amount, purpose,
                    repayment_months, monthly_deduction, balance_remaining,
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
            ")->execute([
                $advanceNo, $empId, $advance_type, $amount, $purpose,
                $repayment_months, $monthly_deduction, $amount, $empId
            ]);
            $message = "Advance request $advanceNo submitted successfully!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Failed to submit request. Please try again.";
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

// Fetch advances
$advances = [];
$myStats = ['total' => 0, 'pending' => 0, 'active' => 0, 'balance' => 0];
try {
    $stmt = $pdo->prepare("SELECT * FROM advance_payments WHERE employee_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$empId]);
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $s = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ?");
    $s->execute([$empId]); $myStats['total'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ? AND status = 'Pending'");
    $s->execute([$empId]); $myStats['pending'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM advance_payments WHERE employee_id = ? AND status = 'Disbursed'");
    $s->execute([$empId]); $myStats['active'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COALESCE(SUM(balance_remaining), 0) FROM advance_payments WHERE employee_id = ? AND status = 'Disbursed'");
    $s->execute([$empId]); $myStats['balance'] = $s->fetchColumn();
} catch (PDOException $e) {}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: attendance_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Advances - <?= htmlspecialchars($empName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <?php include 'includes/pwa_head.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; min-height: 100vh; }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-photo {
            width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2em; border: 3px solid rgba(255,255,255,0.3); overflow: hidden;
        }
        .user-photo img { width: 100%; height: 100%; object-fit: cover; }
        .user-details h2 { font-size: 1.2em; margin-bottom: 2px; }
        .user-details p { opacity: 0.9; font-size: 0.85em; }
        .header-links { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .header-btn {
            background: rgba(255,255,255,0.2); color: white; border: none;
            padding: 10px 18px; border-radius: 8px; cursor: pointer;
            font-size: 0.9em; text-decoration: none; display: inline-block;
        }
        .header-btn:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card {
            background: white; padding: 18px; border-radius: 12px;
            text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        .stat-card .number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .stat-card .label { color: #7f8c8d; font-size: 0.9em; margin-top: 5px; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.warning .number { color: #f39c12; }
        .stat-card.info { border-left: 4px solid #3498db; }
        .stat-card.info .number { color: #3498db; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .stat-card.danger .number { color: #e74c3c; }

        .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }

        .section-card {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08); margin-bottom: 20px;
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; padding: 5px 0;
        }
        .section-header h3 { color: #2c3e50; font-size: 1.1em; }
        .section-toggle { font-size: 1.2em; color: #667eea; transition: transform 0.3s; }
        .section-body { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .section-body.open { display: block; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { margin-bottom: 10px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; font-weight: 600; color: #555; margin-bottom: 4px; font-size: 0.85em; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95em;
        }
        .form-group textarea { resize: vertical; min-height: 60px; }

        .repayment-preview {
            background: #e8f5e9; padding: 12px; border-radius: 8px; margin-top: 10px;
            border-left: 4px solid #27ae60; font-size: 0.95em;
        }
        .repayment-preview .amt { font-size: 1.3em; font-weight: 700; color: #27ae60; }

        .adv-list { display: flex; flex-direction: column; gap: 12px; }
        .adv-card {
            background: white; border-radius: 12px; padding: 18px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08); border-left: 4px solid #667eea;
        }
        .adv-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .adv-no { font-weight: 600; color: #2c3e50; }
        .adv-meta { display: flex; gap: 15px; font-size: 0.85em; color: #7f8c8d; flex-wrap: wrap; align-items: center; }
        .adv-amount { font-weight: 700; color: #2c3e50; font-size: 1.1em; }

        .type-badge { padding: 3px 10px; border-radius: 10px; font-size: 0.75em; font-weight: 600; color: white; }
        .type-salary { background: #2e7d32; }
        .type-travel { background: #1565c0; }
        .type-project { background: #ef6c00; }
        .type-medical { background: #c62828; }
        .type-other { background: #7b1fa2; }

        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 0.75em; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-disbursed { background: #d1ecf1; color: #0c5460; }
        .status-closed { background: #e9ecef; color: #495057; }

        .progress-bar { background: #e0e0e0; border-radius: 8px; height: 8px; width: 100%; margin: 8px 0; }
        .progress-fill { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #27ae60, #2ecc71); }

        .view-btn {
            display: inline-block; padding: 5px 14px; border-radius: 6px;
            background: #667eea; color: white; font-size: 0.8em; font-weight: 600;
            cursor: pointer; border: none; margin-top: 8px;
        }
        .view-btn:hover { background: #5a6fd6; }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 30px; border-radius: 8px;
            font-size: 1em; font-weight: 600; cursor: pointer; margin-top: 10px;
        }

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
            justify-content: center; align-items: flex-start; padding: 30px 15px; overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white; border-radius: 16px; width: 100%; max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header {
            padding: 20px 25px; border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; color: #2c3e50; font-size: 1.1em; }
        .modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: #f5f5f5; font-size: 1.3em; cursor: pointer; color: #666; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .modal-loading { text-align: center; padding: 30px; color: #999; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.95em; }
        .detail-label { color: #888; }
        .detail-value { color: #2c3e50; font-weight: 500; }
        .detail-section { margin-bottom: 15px; }
        .detail-section h4 { font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #f0f0f0; }

        .rep-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .rep-table th, .rep-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.85em; }
        .rep-table th { background: #f8f9fa; font-weight: 600; color: #555; }

        .empty-state { background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08); }
        .empty-state h3 { color: #2c3e50; margin-bottom: 8px; }
        .empty-state p { color: #7f8c8d; }

        @media (max-width: 600px) {
            .portal-header { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="portal-header">
    <div class="user-info">
        <div class="user-photo">
            <?php if ($empPhoto): ?><img src="../<?= htmlspecialchars($empPhoto) ?>" alt="">
            <?php else: ?><?= strtoupper(substr($empName, 0, 2)) ?><?php endif; ?>
        </div>
        <div class="user-details">
            <h2><?= htmlspecialchars($empName) ?></h2>
            <p><?= htmlspecialchars($empCode) ?> | <?= htmlspecialchars($empDesignation ?: $empDept) ?></p>
        </div>
    </div>
    <div class="header-links">
        <a href="attendance_portal.php" class="header-btn">Attendance</a>
        <a href="my_tasks.php" class="header-btn">Tasks</a>
        <a href="my_payslip.php" class="header-btn">Payslips</a>
        <a href="my_tada.php" class="header-btn">TADA</a>
        <a href="?logout=1" class="header-btn">Logout</a>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 20px; color: #2c3e50;">My Advances</h2>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= $myStats['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?= $myStats['pending'] ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="stat-card info">
            <div class="number"><?= $myStats['active'] ?></div>
            <div class="label">Active</div>
        </div>
        <div class="stat-card danger">
            <div class="number"><?= number_format($myStats['balance']) ?></div>
            <div class="label">Balance (Rs)</div>
        </div>
    </div>

    <!-- New Advance Form -->
    <div class="section-card">
        <div class="section-header" onclick="toggleSection('newAdvBody', this)">
            <h3>+ Request New Advance</h3>
            <span class="section-toggle">&#9660;</span>
        </div>
        <div class="section-body" id="newAdvBody">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Advance Type *</label>
                        <select name="advance_type" required>
                            <option value="Salary">Salary Advance</option>
                            <option value="Travel">Travel Advance</option>
                            <option value="Project">Project Advance</option>
                            <option value="Medical">Medical Advance</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (Rs) *</label>
                        <input type="number" name="amount" id="adv_amt" step="0.01" min="1" required onchange="calcDed()">
                    </div>
                    <div class="form-group full">
                        <label>Purpose / Reason *</label>
                        <textarea name="purpose" required placeholder="Explain the reason for this advance"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Repayment Months (1-24) *</label>
                        <input type="number" name="repayment_months" id="rep_months" min="1" max="24" value="1" required onchange="calcDed()">
                    </div>
                    <div class="form-group">
                        <label>Monthly Deduction (Rs)</label>
                        <input type="text" id="ded_display" readonly value="0.00" style="font-weight: 700; color: #27ae60; background: #f8f9fa;">
                    </div>
                </div>

                <div class="repayment-preview" id="repPreview" style="display:none;">
                    Repayment: Rs <span class="amt" id="prevAmt">0</span>/month for <span id="prevMon">0</span> months
                </div>

                <button type="submit" name="submit_advance" class="submit-btn">Submit Advance Request</button>
            </form>
        </div>
    </div>

    <!-- Advances List -->
    <?php if (empty($advances)): ?>
        <div class="empty-state">
            <h3>No Advances</h3>
            <p>You haven't requested any advances yet. Click above to request one.</p>
        </div>
    <?php else: ?>
        <div class="adv-list">
            <?php foreach ($advances as $a):
                $repaid = $a['amount'] - $a['balance_remaining'];
                $pct = $a['amount'] > 0 ? min(100, round(($repaid / $a['amount']) * 100)) : 0;
            ?>
            <div class="adv-card">
                <div class="adv-card-header">
                    <div>
                        <span class="adv-no"><?= htmlspecialchars($a['advance_no']) ?></span>
                        <span class="type-badge type-<?= strtolower($a['advance_type']) ?>"><?= $a['advance_type'] ?></span>
                    </div>
                    <span class="status-badge status-<?= strtolower($a['status']) ?>"><?= $a['status'] ?></span>
                </div>
                <div class="adv-meta">
                    <span class="adv-amount">Rs <?= number_format($a['amount'], 2) ?></span>
                    <span>Ded: Rs <?= number_format($a['monthly_deduction'], 2) ?>/mo</span>
                    <?php if ($a['status'] === 'Disbursed'): ?>
                        <span style="color: #e74c3c;">Balance: Rs <?= number_format($a['balance_remaining'], 2) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (in_array($a['status'], ['Disbursed', 'Closed'])): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $pct ?>%"></div>
                </div>
                <div style="font-size: 0.8em; color: #27ae60;"><?= $pct ?>% repaid</div>
                <?php endif; ?>
                <button class="view-btn" onclick="viewAdvance(<?= $a['id'] ?>)">View Details</button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="advModal" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">Advance Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-loading" id="modalLoading">Loading...</div>
            <div id="modalContent" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
function toggleSection(id, header) {
    var body = document.getElementById(id);
    body.classList.toggle('open');
    var arrow = header.querySelector('.section-toggle');
    arrow.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : '';
}

function calcDed() {
    var amt = parseFloat(document.getElementById('adv_amt').value) || 0;
    var mon = parseInt(document.getElementById('rep_months').value) || 1;
    var ded = mon > 0 ? (amt / mon).toFixed(2) : '0.00';
    document.getElementById('ded_display').value = ded;
    var prev = document.getElementById('repPreview');
    if (amt > 0) {
        document.getElementById('prevAmt').textContent = ded;
        document.getElementById('prevMon').textContent = mon;
        prev.style.display = 'block';
    } else {
        prev.style.display = 'none';
    }
}

function viewAdvance(id) {
    var overlay = document.getElementById('advModal');
    var loading = document.getElementById('modalLoading');
    var content = document.getElementById('modalContent');
    overlay.classList.add('active');
    loading.style.display = 'block';
    content.style.display = 'none';
    document.body.style.overflow = 'hidden';

    fetch('my_advance.php?ajax_advance_id=' + id)
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success) { content.innerHTML = '<p style="color:#e74c3c;">Could not load.</p>'; content.style.display = 'block'; return; }
            var a = data.advance;
            document.getElementById('modalTitle').textContent = a.advance_no;

            var statusClass = 'status-' + a.status.toLowerCase();
            var typeClass = 'type-' + a.advance_type.toLowerCase();
            var html = '<div style="margin-bottom:15px;display:flex;gap:8px;flex-wrap:wrap;">';
            html += '<span class="status-badge ' + statusClass + '">' + esc(a.status) + '</span>';
            html += '<span class="type-badge ' + typeClass + '">' + esc(a.advance_type) + '</span>';
            html += '</div>';

            html += '<div class="detail-section"><h4>Advance Details</h4>';
            html += dRow('Amount', 'Rs ' + parseFloat(a.amount).toFixed(2));
            html += dRow('Purpose', esc(a.purpose));
            html += dRow('Monthly Deduction', 'Rs ' + parseFloat(a.monthly_deduction).toFixed(2));
            html += dRow('Repayment Months', a.repayment_months);
            html += dRow('Balance Remaining', 'Rs ' + parseFloat(a.balance_remaining).toFixed(2));

            // Progress bar
            var repaid = parseFloat(a.amount) - parseFloat(a.balance_remaining);
            var pct = a.amount > 0 ? Math.min(100, Math.round((repaid / a.amount) * 100)) : 0;
            if (a.status === 'Disbursed' || a.status === 'Closed') {
                html += '<div style="padding:8px 0;"><div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div>';
                html += '<div style="font-size:0.85em;color:#27ae60;">' + pct + '% repaid (Rs ' + repaid.toFixed(2) + ')</div></div>';
            }
            html += '</div>';

            if (a.approver_name && a.status !== 'Pending') {
                html += '<div class="detail-section"><h4>Approval</h4>';
                html += dRow(a.status === 'Rejected' ? 'Rejected By' : 'Approved By', esc(a.approver_name));
                if (a.approval_date) html += dRow('Date', formatDateTime(a.approval_date));
                if (a.approval_remarks) html += dRow('Remarks', esc(a.approval_remarks));
                html += '</div>';
            }

            if (a.disbursement_date) {
                html += '<div class="detail-section"><h4>Disbursement</h4>';
                html += dRow('Date', formatDate(a.disbursement_date));
                if (a.payment_mode) html += dRow('Mode', esc(a.payment_mode));
                if (a.transaction_ref) html += dRow('Reference', esc(a.transaction_ref));
                html += '</div>';
            }

            // Repayments
            if (a.repayments && a.repayments.length > 0) {
                html += '<div class="detail-section"><h4>Repayment History</h4>';
                html += '<table class="rep-table"><thead><tr><th>Date</th><th>Amount</th><th>Remarks</th></tr></thead><tbody>';
                var totalRep = 0;
                a.repayments.forEach(function(r) {
                    totalRep += parseFloat(r.amount);
                    html += '<tr><td>' + formatDate(r.repayment_date) + '</td><td style="color:#27ae60;font-weight:600;">Rs ' + parseFloat(r.amount).toFixed(2) + '</td><td>' + esc(r.remarks || '-') + '</td></tr>';
                });
                html += '<tr style="font-weight:700;background:#f8f9fa;"><td>Total</td><td style="color:#27ae60;">Rs ' + totalRep.toFixed(2) + '</td><td></td></tr>';
                html += '</tbody></table></div>';
            }

            html += dRow('Submitted', formatDateTime(a.created_at));

            content.innerHTML = html;
            content.style.display = 'block';
        })
        .catch(function() {
            loading.style.display = 'none';
            content.innerHTML = '<p style="color:#e74c3c;">Failed to load.</p>';
            content.style.display = 'block';
        });
}

function closeModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('advModal').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

function dRow(l, v) { return '<div class="detail-row"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>'; }
function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatDate(d) { if (!d) return '-'; var dt = new Date(d); var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear(); }
function formatDateTime(d) { if (!d) return '-'; var dt = new Date(d); var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; var h = dt.getHours(), mn = dt.getMinutes(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear() + ', ' + h + ':' + (mn < 10 ? '0' : '') + mn + ' ' + ap; }
</script>
<?php include 'includes/pwa_sw.php'; ?>
</body>
</html>
