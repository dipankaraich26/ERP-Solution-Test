<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: campaigns.php");
    exit;
}

// Handle outcome update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_outcome'])) {
    $stmt = $pdo->prepare("
        UPDATE marketing_campaigns SET
            actual_attendees = ?, leads_generated = ?, enquiries_received = ?,
            orders_received = ?, revenue_generated = ?, actual_cost = ?,
            outcome_summary = ?, success_rating = ?, lessons_learned = ?,
            follow_up_required = ?, follow_up_notes = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['actual_attendees'] ?: 0,
        $_POST['leads_generated'] ?: 0,
        $_POST['enquiries_received'] ?: 0,
        $_POST['orders_received'] ?: 0,
        $_POST['revenue_generated'] ?: 0,
        $_POST['actual_cost'] ?: 0,
        $_POST['outcome_summary'] ?: null,
        $_POST['success_rating'] ?? 'Not Rated',
        $_POST['lessons_learned'] ?: null,
        isset($_POST['follow_up_required']) ? 1 : 0,
        $_POST['follow_up_notes'] ?: null,
        $_POST['status'] ?? 'Completed',
        $id
    ]);
    setModal("Success", "Campaign outcome updated!");
    header("Location: campaign_view.php?id=$id");
    exit;
}

// Fetch campaign
$stmt = $pdo->prepare("
    SELECT c.*, ct.name as type_name, s.state_name, s.region
    FROM marketing_campaigns c
    LEFT JOIN campaign_types ct ON c.campaign_type_id = ct.id
    LEFT JOIN india_states s ON c.state_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header("Location: campaigns.php");
    exit;
}

// Get catalogs for this campaign
$catalogList = [];
if ($campaign['catalog_ids']) {
    $ids = explode(',', $campaign['catalog_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $catStmt = $pdo->prepare("SELECT id, catalog_code, catalog_name FROM marketing_catalogs WHERE id IN ($placeholders)");
    $catStmt->execute($ids);
    $catalogList = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get leads for this campaign
$leads = $pdo->prepare("SELECT * FROM campaign_leads WHERE campaign_id = ? ORDER BY created_at DESC LIMIT 10");
$leads->execute([$id]);
$leads = $leads->fetchAll(PDO::FETCH_ASSOC);

// Get expenses
$expenses = $pdo->prepare("SELECT * FROM campaign_expenses WHERE campaign_id = ? ORDER BY expense_date");
$expenses->execute([$id]);
$expenses = $expenses->fetchAll(PDO::FETCH_ASSOC);
$totalExpenses = array_sum(array_column($expenses, 'amount'));

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Campaign - <?= htmlspecialchars($campaign['campaign_code']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .campaign-view { max-width: 1000px; }

        .campaign-header {
            background: linear-gradient(135deg, #9b59b6 0%, #3498db 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .campaign-header h1 { margin: 0 0 5px 0; }
        .campaign-header .code { opacity: 0.9; margin-bottom: 15px; }

        .header-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .header-meta .item { }
        .header-meta .label { opacity: 0.8; font-size: 0.85em; }
        .header-meta .value { font-size: 1.1em; font-weight: 500; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
        }

        .info-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .info-section h3 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .info-section .content-area { padding: 20px; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item label { display: block; color: #7f8c8d; font-size: 0.85em; }
        .info-item .value { font-weight: 500; }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .metric-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .metric-box .number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .metric-box .label { color: #7f8c8d; font-size: 0.9em; }
        .metric-box.success .number { color: #27ae60; }
        .metric-box.warning .number { color: #f39c12; }

        .rating-stars { font-size: 1.5em; }
        .rating-Excellent { color: #27ae60; }
        .rating-Good { color: #3498db; }
        .rating-Average { color: #f39c12; }
        .rating-Poor { color: #e74c3c; }

        .action-buttons { margin-bottom: 20px; }
        .action-buttons .btn { margin-right: 10px; }

        .outcome-form { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .outcome-form .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .outcome-form .form-group { margin-bottom: 10px; }
        .outcome-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .outcome-form input, .outcome-form select, .outcome-form textarea {
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="campaign-view">

        <div class="action-buttons">
            <a href="campaigns.php" class="btn btn-secondary">Back to Campaigns</a>
            <a href="campaign_edit.php?id=<?= $id ?>" class="btn btn-primary">Edit</a>
            <a href="campaign_leads.php?id=<?= $id ?>" class="btn btn-secondary">Manage Leads</a>
            <a href="campaign_expenses.php?id=<?= $id ?>" class="btn btn-secondary">Expenses</a>
        </div>

        <div class="campaign-header">
            <div class="code"><?= htmlspecialchars($campaign['campaign_code']) ?></div>
            <h1><?= htmlspecialchars($campaign['campaign_name']) ?></h1>
            <span class="status-badge"><?= $campaign['status'] ?></span>

            <div class="header-meta">
                <div class="item">
                    <div class="label">Type</div>
                    <div class="value"><?= htmlspecialchars($campaign['type_name'] ?? 'N/A') ?></div>
                </div>
                <div class="item">
                    <div class="label">Date</div>
                    <div class="value">
                        <?= date('d M Y', strtotime($campaign['start_date'])) ?>
                        <?php if ($campaign['end_date'] && $campaign['end_date'] !== $campaign['start_date']): ?>
                            - <?= date('d M Y', strtotime($campaign['end_date'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="item">
                    <div class="label">Location</div>
                    <div class="value"><?= htmlspecialchars(($campaign['city'] ?? '') . ', ' . ($campaign['state_name'] ?? '')) ?></div>
                </div>
                <div class="item">
                    <div class="label">Budget</div>
                    <div class="value">₹<?= number_format($campaign['budget'], 0) ?></div>
                </div>
            </div>
        </div>

        <!-- Metrics -->
        <div class="info-section">
            <h3>Campaign Metrics</h3>
            <div class="content-area">
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="number"><?= $campaign['expected_attendees'] ?></div>
                        <div class="label">Expected</div>
                    </div>
                    <div class="metric-box success">
                        <div class="number"><?= $campaign['actual_attendees'] ?></div>
                        <div class="label">Actual Attendees</div>
                    </div>
                    <div class="metric-box">
                        <div class="number"><?= $campaign['leads_generated'] ?></div>
                        <div class="label">Leads Generated</div>
                    </div>
                    <div class="metric-box">
                        <div class="number"><?= $campaign['enquiries_received'] ?></div>
                        <div class="label">Enquiries</div>
                    </div>
                    <div class="metric-box success">
                        <div class="number"><?= $campaign['orders_received'] ?></div>
                        <div class="label">Orders</div>
                    </div>
                    <div class="metric-box success">
                        <div class="number">₹<?= number_format($campaign['revenue_generated'], 0) ?></div>
                        <div class="label">Revenue</div>
                    </div>
                </div>

                <?php if ($campaign['success_rating'] !== 'Not Rated'): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <span class="rating-stars rating-<?= $campaign['success_rating'] ?>">
                        <?php
                        $stars = match($campaign['success_rating']) {
                            'Excellent' => '★★★★★',
                            'Good' => '★★★★☆',
                            'Average' => '★★★☆☆',
                            'Poor' => '★★☆☆☆',
                            default => '☆☆☆☆☆'
                        };
                        echo $stars;
                        ?>
                    </span>
                    <div style="color: #7f8c8d;"><?= $campaign['success_rating'] ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Details -->
        <div class="info-section">
            <h3>Campaign Details</h3>
            <div class="content-area">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Venue</label>
                        <div class="value"><?= htmlspecialchars($campaign['venue'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Target Audience</label>
                        <div class="value"><?= htmlspecialchars($campaign['target_audience'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Campaign Manager</label>
                        <div class="value"><?= htmlspecialchars($campaign['campaign_manager'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Actual Cost</label>
                        <div class="value">₹<?= number_format($campaign['actual_cost'] ?: $totalExpenses, 0) ?></div>
                    </div>
                </div>

                <?php if ($campaign['description']): ?>
                <div style="margin-top: 20px;">
                    <label style="color: #7f8c8d; font-size: 0.85em;">Description</label>
                    <p><?= nl2br(htmlspecialchars($campaign['description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products Marketed -->
        <?php if (!empty($catalogList)): ?>
        <div class="info-section">
            <h3>Products Marketed</h3>
            <div class="content-area">
                <?php foreach ($catalogList as $cat): ?>
                    <a href="catalog_view.php?id=<?= $cat['id'] ?>" style="display: inline-block; margin: 5px; padding: 8px 15px; background: #e3f2fd; border-radius: 20px; text-decoration: none; color: #1565c0;">
                        <?= htmlspecialchars($cat['catalog_code'] . ' - ' . $cat['catalog_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Outcome Summary -->
        <?php if ($campaign['outcome_summary'] || $campaign['lessons_learned']): ?>
        <div class="info-section">
            <h3>Outcome & Learnings</h3>
            <div class="content-area">
                <?php if ($campaign['outcome_summary']): ?>
                <div style="margin-bottom: 20px;">
                    <label style="color: #7f8c8d; font-size: 0.85em;">Outcome Summary</label>
                    <p><?= nl2br(htmlspecialchars($campaign['outcome_summary'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($campaign['lessons_learned']): ?>
                <div>
                    <label style="color: #7f8c8d; font-size: 0.85em;">Lessons Learned</label>
                    <p><?= nl2br(htmlspecialchars($campaign['lessons_learned'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Update Outcome Form -->
        <?php if ($campaign['status'] === 'Ongoing' || $campaign['status'] === 'Completed'): ?>
        <div class="info-section">
            <h3>Update Outcome</h3>
            <div class="content-area">
                <form method="post" class="outcome-form">
                    <input type="hidden" name="update_outcome" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Actual Attendees</label>
                            <input type="number" name="actual_attendees" value="<?= $campaign['actual_attendees'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Leads Generated</label>
                            <input type="number" name="leads_generated" value="<?= $campaign['leads_generated'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Enquiries Received</label>
                            <input type="number" name="enquiries_received" value="<?= $campaign['enquiries_received'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Orders Received</label>
                            <input type="number" name="orders_received" value="<?= $campaign['orders_received'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Revenue Generated (₹)</label>
                            <input type="number" name="revenue_generated" step="0.01" value="<?= $campaign['revenue_generated'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Actual Cost (₹)</label>
                            <input type="number" name="actual_cost" step="0.01" value="<?= $campaign['actual_cost'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Success Rating</label>
                            <select name="success_rating">
                                <?php foreach (['Not Rated', 'Excellent', 'Good', 'Average', 'Poor'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $campaign['success_rating'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <?php foreach (['Ongoing', 'Completed', 'Cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $campaign['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Outcome Summary</label>
                        <textarea name="outcome_summary" rows="3"><?= htmlspecialchars($campaign['outcome_summary'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Lessons Learned</label>
                        <textarea name="lessons_learned" rows="3"><?= htmlspecialchars($campaign['lessons_learned'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="follow_up_required" <?= $campaign['follow_up_required'] ? 'checked' : '' ?>>
                            Follow-up Required
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Follow-up Notes</label>
                        <textarea name="follow_up_notes" rows="2"><?= htmlspecialchars($campaign['follow_up_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Update Outcome</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
