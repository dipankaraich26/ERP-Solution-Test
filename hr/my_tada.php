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

// AJAX handler for claim detail
if (isset($_GET['ajax_claim_id'])) {
    header('Content-Type: application/json');
    $claimId = (int)$_GET['ajax_claim_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*,
                   CONCAT(a.first_name, ' ', a.last_name) as approver_name
            FROM tada_claims tc
            LEFT JOIN employees a ON tc.approved_by = a.id
            WHERE tc.id = ? AND tc.employee_id = ?
        ");
        $stmt->execute([$claimId, $empId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($claim) {
            echo json_encode(['success' => true, 'claim' => $claim]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Claim not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_tada'])) {
    $travel_date = $_POST['travel_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $from_location = trim($_POST['from_location'] ?? '');
    $to_location = trim($_POST['to_location'] ?? '');
    $travel_mode = $_POST['travel_mode'] ?? 'Bus';
    $purpose = trim($_POST['purpose'] ?? '');
    $travel_amount = floatval($_POST['travel_amount'] ?? 0);
    $da_amount = floatval($_POST['da_amount'] ?? 0);
    $accommodation_amount = floatval($_POST['accommodation_amount'] ?? 0);
    $other_amount = floatval($_POST['other_amount'] ?? 0);
    $total_amount = $travel_amount + $da_amount + $accommodation_amount + $other_amount;

    $errors = [];
    if (!$travel_date) $errors[] = "Travel date is required.";
    if (!$from_location) $errors[] = "From location is required.";
    if (!$to_location) $errors[] = "To location is required.";
    if (!$purpose) $errors[] = "Purpose is required.";
    if ($total_amount <= 0) $errors[] = "Total amount must be greater than zero.";

    // Receipt upload
    $receipt_path = null;
    if (!empty($_FILES['receipt']['name'])) {
        $file = $_FILES['receipt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $errors[] = "Receipt must be PDF, JPG, or PNG.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "Receipt file must be less than 5MB.";
        } else {
            $uploadDir = "../uploads/tada_receipts/" . $empCode . "/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $receipt_path = "uploads/tada_receipts/" . $empCode . "/" . $filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $lastId = $pdo->query("SELECT MAX(id) FROM tada_claims")->fetchColumn();
            $claimNo = 'TA-' . date('Y') . '-' . str_pad(($lastId + 1), 4, '0', STR_PAD_LEFT);

            $pdo->prepare("
                INSERT INTO tada_claims (
                    claim_no, employee_id, travel_date, return_date, from_location, to_location,
                    travel_mode, purpose, travel_amount, da_amount, accommodation_amount, other_amount,
                    total_amount, receipt_path, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
            ")->execute([
                $claimNo, $empId, $travel_date, $return_date ?: null, $from_location, $to_location,
                $travel_mode, $purpose, $travel_amount, $da_amount, $accommodation_amount, $other_amount,
                $total_amount, $receipt_path, $empId
            ]);
            $message = "TADA claim $claimNo submitted successfully!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Failed to submit claim. Please try again.";
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

// Fetch claims
$claims = [];
$myStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'total_amount' => 0];
try {
    $stmt = $pdo->prepare("SELECT * FROM tada_claims WHERE employee_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$empId]);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $s = $pdo->prepare("SELECT COUNT(*) FROM tada_claims WHERE employee_id = ?");
    $s->execute([$empId]); $myStats['total'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tada_claims WHERE employee_id = ? AND status = 'Pending'");
    $s->execute([$empId]); $myStats['pending'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM tada_claims WHERE employee_id = ? AND status IN ('Approved','Paid')");
    $s->execute([$empId]); $myStats['approved'] = $s->fetchColumn();

    $s = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM tada_claims WHERE employee_id = ? AND status = 'Paid'");
    $s->execute([$empId]); $myStats['total_amount'] = $s->fetchColumn();
} catch (PDOException $e) {}

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: attendance_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My TADA Claims - <?= htmlspecialchars($empName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#667eea">
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
        .stat-card.success { border-left: 4px solid #27ae60; }
        .stat-card.success .number { color: #27ae60; }

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
        .amount-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }

        .claim-list { display: flex; flex-direction: column; gap: 12px; }
        .claim-card {
            background: white; border-radius: 12px; padding: 18px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08); border-left: 4px solid #667eea;
        }
        .claim-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .claim-no { font-weight: 600; color: #2c3e50; }
        .claim-route { font-size: 0.9em; color: #666; margin-bottom: 5px; }
        .claim-meta { display: flex; gap: 15px; font-size: 0.85em; color: #7f8c8d; flex-wrap: wrap; }
        .claim-amount { font-weight: 700; color: #2c3e50; font-size: 1.1em; }

        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 0.75em; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d1ecf1; color: #0c5460; }

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
        .submit-btn:hover { opacity: 0.9; }

        /* Modal */
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
        .modal-close {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: #f5f5f5; font-size: 1.3em; cursor: pointer; color: #666;
        }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .modal-loading { text-align: center; padding: 30px; color: #999; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.95em; }
        .detail-label { color: #888; }
        .detail-value { color: #2c3e50; font-weight: 500; }
        .detail-section { margin-bottom: 15px; }
        .detail-section h4 { font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #f0f0f0; }

        .empty-state { background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 3px 15px rgba(0,0,0,0.08); }
        .empty-state h3 { color: #2c3e50; margin-bottom: 8px; }
        .empty-state p { color: #7f8c8d; }

        @media (max-width: 600px) {
            .portal-header { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .amount-grid { grid-template-columns: 1fr 1fr; }
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
        <a href="my_advance.php" class="header-btn">Advances</a>
        <a href="?logout=1" class="header-btn">Logout</a>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 20px; color: #2c3e50;">My TADA Claims</h2>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?= $myStats['total'] ?></div>
            <div class="label">Total Claims</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?= $myStats['pending'] ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="stat-card success">
            <div class="number"><?= $myStats['approved'] ?></div>
            <div class="label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= number_format($myStats['total_amount']) ?></div>
            <div class="label">Paid (Rs)</div>
        </div>
    </div>

    <!-- New Claim Form -->
    <div class="section-card">
        <div class="section-header" onclick="toggleSection('newClaimBody', this)">
            <h3>+ Submit New TADA Claim</h3>
            <span class="section-toggle">&#9660;</span>
        </div>
        <div class="section-body" id="newClaimBody">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Travel Date *</label>
                        <input type="date" name="travel_date" required>
                    </div>
                    <div class="form-group">
                        <label>Return Date</label>
                        <input type="date" name="return_date">
                    </div>
                    <div class="form-group">
                        <label>From Location *</label>
                        <input type="text" name="from_location" required placeholder="e.g. Mumbai">
                    </div>
                    <div class="form-group">
                        <label>To Location *</label>
                        <input type="text" name="to_location" required placeholder="e.g. Pune">
                    </div>
                    <div class="form-group">
                        <label>Travel Mode *</label>
                        <select name="travel_mode" required>
                            <option value="Bus">Bus</option>
                            <option value="Train">Train</option>
                            <option value="Flight">Flight</option>
                            <option value="Auto">Auto</option>
                            <option value="Own Vehicle">Own Vehicle</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Receipt (PDF/JPG/PNG)</label>
                        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="form-group full">
                        <label>Purpose *</label>
                        <input type="text" name="purpose" required placeholder="Purpose of travel">
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px; color: #555; font-size: 0.95em;">Amount Details (Rs)</h4>
                <div class="amount-grid">
                    <div class="form-group">
                        <label>Travel</label>
                        <input type="number" name="travel_amount" id="p_travel" step="0.01" min="0" value="0" onchange="pCalcTotal()">
                    </div>
                    <div class="form-group">
                        <label>DA</label>
                        <input type="number" name="da_amount" id="p_da" step="0.01" min="0" value="0" onchange="pCalcTotal()">
                    </div>
                    <div class="form-group">
                        <label>Accommodation</label>
                        <input type="number" name="accommodation_amount" id="p_accom" step="0.01" min="0" value="0" onchange="pCalcTotal()">
                    </div>
                    <div class="form-group">
                        <label>Other</label>
                        <input type="number" name="other_amount" id="p_other" step="0.01" min="0" value="0" onchange="pCalcTotal()">
                    </div>
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" id="p_total" readonly value="0.00" style="font-weight: 700; color: #27ae60; background: #f8f9fa;">
                    </div>
                </div>

                <button type="submit" name="submit_tada" class="submit-btn">Submit TADA Claim</button>
            </form>
        </div>
    </div>

    <!-- Claims List -->
    <?php if (empty($claims)): ?>
        <div class="empty-state">
            <h3>No TADA Claims</h3>
            <p>You haven't submitted any TADA claims yet. Click above to submit one.</p>
        </div>
    <?php else: ?>
        <div class="claim-list">
            <?php foreach ($claims as $c): ?>
            <div class="claim-card">
                <div class="claim-card-header">
                    <span class="claim-no"><?= htmlspecialchars($c['claim_no']) ?></span>
                    <span class="status-badge status-<?= strtolower($c['status']) ?>"><?= $c['status'] ?></span>
                </div>
                <div class="claim-route"><?= htmlspecialchars($c['from_location']) ?> &rarr; <?= htmlspecialchars($c['to_location']) ?></div>
                <div class="claim-meta">
                    <span><?= date('d M Y', strtotime($c['travel_date'])) ?></span>
                    <span><?= htmlspecialchars($c['travel_mode']) ?></span>
                    <span class="claim-amount">Rs <?= number_format($c['total_amount'], 2) ?></span>
                </div>
                <button class="view-btn" onclick="viewClaim(<?= $c['id'] ?>)">View Details</button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="claimModal" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">TADA Claim Details</h3>
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

function pCalcTotal() {
    var t = parseFloat(document.getElementById('p_travel').value) || 0;
    var d = parseFloat(document.getElementById('p_da').value) || 0;
    var a = parseFloat(document.getElementById('p_accom').value) || 0;
    var o = parseFloat(document.getElementById('p_other').value) || 0;
    document.getElementById('p_total').value = (t + d + a + o).toFixed(2);
}

function viewClaim(id) {
    var overlay = document.getElementById('claimModal');
    var loading = document.getElementById('modalLoading');
    var content = document.getElementById('modalContent');
    overlay.classList.add('active');
    loading.style.display = 'block';
    content.style.display = 'none';
    document.body.style.overflow = 'hidden';

    fetch('my_tada.php?ajax_claim_id=' + id)
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success) { content.innerHTML = '<p style="color:#e74c3c;">Could not load claim details.</p>'; content.style.display = 'block'; return; }
            var c = data.claim;
            document.getElementById('modalTitle').textContent = c.claim_no;

            var statusClass = 'status-' + c.status.toLowerCase();
            var html = '<div style="margin-bottom:15px;"><span class="status-badge ' + statusClass + '">' + esc(c.status) + '</span></div>';

            html += '<div class="detail-section"><h4>Travel Details</h4>';
            html += dRow('Travel Date', formatDate(c.travel_date));
            if (c.return_date) html += dRow('Return Date', formatDate(c.return_date));
            html += dRow('Route', esc(c.from_location) + ' &rarr; ' + esc(c.to_location));
            html += dRow('Mode', esc(c.travel_mode));
            html += dRow('Purpose', esc(c.purpose));
            html += '</div>';

            html += '<div class="detail-section"><h4>Amount Breakdown</h4>';
            html += dRow('Travel', 'Rs ' + parseFloat(c.travel_amount).toFixed(2));
            html += dRow('DA', 'Rs ' + parseFloat(c.da_amount).toFixed(2));
            html += dRow('Accommodation', 'Rs ' + parseFloat(c.accommodation_amount).toFixed(2));
            html += dRow('Other', 'Rs ' + parseFloat(c.other_amount).toFixed(2));
            html += '<div class="detail-row" style="font-weight:700;border-top:2px solid #667eea;padding-top:10px;"><span class="detail-label">Total</span><span class="detail-value" style="color:#27ae60;">Rs ' + parseFloat(c.total_amount).toFixed(2) + '</span></div>';
            html += '</div>';

            if (c.approver_name && c.status !== 'Pending') {
                html += '<div class="detail-section"><h4>Approval</h4>';
                html += dRow(c.status === 'Rejected' ? 'Rejected By' : 'Approved By', esc(c.approver_name));
                if (c.approval_date) html += dRow('Date', formatDateTime(c.approval_date));
                if (c.approval_remarks) html += dRow('Remarks', esc(c.approval_remarks));
                html += '</div>';
            }

            if (c.status === 'Paid') {
                html += '<div class="detail-section"><h4>Payment</h4>';
                if (c.payment_date) html += dRow('Payment Date', formatDate(c.payment_date));
                if (c.payment_mode) html += dRow('Mode', esc(c.payment_mode));
                if (c.transaction_ref) html += dRow('Reference', esc(c.transaction_ref));
                html += '</div>';
            }

            html += dRow('Submitted', formatDateTime(c.created_at));

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
    document.getElementById('claimModal').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

function dRow(l, v) { return '<div class="detail-row"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>'; }
function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatDate(d) { if (!d) return '-'; var dt = new Date(d); var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear(); }
function formatDateTime(d) { if (!d) return '-'; var dt = new Date(d); var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; var h = dt.getHours(), mn = dt.getMinutes(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return dt.getDate() + ' ' + m[dt.getMonth()] + ' ' + dt.getFullYear() + ', ' + h + ':' + (mn < 10 ? '0' : '') + mn + ' ' + ap; }
</script>
</body>
</html>
