<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: campaigns.php");
    exit;
}

// Fetch campaign
$stmt = $pdo->prepare("SELECT * FROM marketing_campaigns WHERE id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header("Location: campaigns.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_name = trim($_POST['campaign_name'] ?? '');
    $start_date = $_POST['start_date'] ?? '';

    if ($campaign_name === '') $errors[] = "Campaign name is required";
    if ($start_date === '') $errors[] = "Start date is required";

    if (empty($errors)) {
        // Handle catalog selection
        $catalog_ids = !empty($_POST['catalogs']) ? implode(',', $_POST['catalogs']) : null;

        $stmt = $pdo->prepare("
            UPDATE marketing_campaigns SET
                campaign_name = ?, campaign_type_id = ?, description = ?,
                state_id = ?, city = ?, venue = ?, venue_address = ?,
                start_date = ?, end_date = ?,
                target_audience = ?, expected_attendees = ?, budget = ?,
                catalog_ids = ?, campaign_manager = ?, team_members = ?, status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $campaign_name,
            $_POST['campaign_type_id'] ?: null,
            $_POST['description'] ?: null,
            $_POST['state_id'] ?: null,
            $_POST['city'] ?: null,
            $_POST['venue'] ?: null,
            $_POST['venue_address'] ?: null,
            $start_date,
            $_POST['end_date'] ?: null,
            $_POST['target_audience'] ?: null,
            $_POST['expected_attendees'] ?: 0,
            $_POST['budget'] ?: 0,
            $catalog_ids,
            $_POST['campaign_manager'] ?: null,
            $_POST['team_members'] ?: null,
            $_POST['status'] ?? 'Planned',
            $id
        ]);

        setModal("Success", "Campaign updated successfully!");
        header("Location: campaign_view.php?id=$id");
        exit;
    }
}

// Get options for dropdowns
$types = $pdo->query("SELECT id, name FROM campaign_types WHERE is_active = 1 ORDER BY name")->fetchAll();
$states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
$catalogs = $pdo->query("SELECT id, catalog_code, catalog_name FROM marketing_catalogs WHERE status = 'Active' ORDER BY catalog_name")->fetchAll();

// Get selected catalog IDs
$selectedCatalogs = $campaign['catalog_ids'] ? explode(',', $campaign['catalog_ids']) : [];

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Campaign - <?= htmlspecialchars($campaign['campaign_code']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .form-container { max-width: 900px; }
        .form-section {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #9b59b6;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group.full-width { grid-column: 1 / -1; }

        .error-box {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }

        .catalog-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background: white;
        }
        .catalog-checkboxes label {
            display: block;
            padding: 5px;
            cursor: pointer;
            font-weight: normal;
        }
        .catalog-checkboxes label:hover { background: #f5f5f5; }

        .campaign-code {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Campaign</h1>
        <div class="campaign-code">Code: <?= htmlspecialchars($campaign['campaign_code']) ?></div>
        <p>
            <a href="campaign_view.php?id=<?= $id ?>" class="btn btn-secondary">Back to Campaign</a>
            <a href="campaigns.php" class="btn btn-secondary">All Campaigns</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">

            <div class="form-section">
                <h3>Campaign Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Campaign Name *</label>
                        <input type="text" name="campaign_name" required value="<?= htmlspecialchars($campaign['campaign_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Campaign Type</label>
                        <select name="campaign_type_id">
                            <option value="">-- Select Type --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $campaign['campaign_type_id'] == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required value="<?= $campaign['start_date'] ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $campaign['end_date'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Planned" <?= $campaign['status'] === 'Planned' ? 'selected' : '' ?>>Planned</option>
                            <option value="Ongoing" <?= $campaign['status'] === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Completed" <?= $campaign['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= $campaign['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="Postponed" <?= $campaign['status'] === 'Postponed' ? 'selected' : '' ?>>Postponed</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Campaign objectives and details..."><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Location</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>State</label>
                        <select name="state_id">
                            <option value="">-- Select State --</option>
                            <?php foreach ($states as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $campaign['state_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($campaign['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Venue Name</label>
                        <input type="text" name="venue" value="<?= htmlspecialchars($campaign['venue'] ?? '') ?>" placeholder="e.g., Hotel Taj, Convention Center">
                    </div>
                    <div class="form-group full-width">
                        <label>Venue Address</label>
                        <textarea name="venue_address" rows="2"><?= htmlspecialchars($campaign['venue_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Target & Budget</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" value="<?= htmlspecialchars($campaign['target_audience'] ?? '') ?>" placeholder="e.g., Doctors, Lab Technicians">
                    </div>
                    <div class="form-group">
                        <label>Expected Attendees</label>
                        <input type="number" name="expected_attendees" min="0" value="<?= (int)$campaign['expected_attendees'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Budget (₹)</label>
                        <input type="number" name="budget" min="0" step="0.01" value="<?= number_format($campaign['budget'], 2, '.', '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Products to Market</h3>
                <div class="form-group">
                    <label>Select Catalogs/Products</label>
                    <div class="catalog-checkboxes">
                        <?php if (empty($catalogs)): ?>
                            <p style="color: #7f8c8d;">No active catalogs available. <a href="catalog_add.php">Add one</a></p>
                        <?php else: ?>
                            <?php foreach ($catalogs as $cat): ?>
                                <label>
                                    <input type="checkbox" name="catalogs[]" value="<?= $cat['id'] ?>"
                                           <?= in_array($cat['id'], $selectedCatalogs) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($cat['catalog_code'] . ' - ' . $cat['catalog_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Team</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Campaign Manager</label>
                        <input type="text" name="campaign_manager" value="<?= htmlspecialchars($campaign['campaign_manager'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Team Members</label>
                        <textarea name="team_members" rows="2" placeholder="Names of team members involved..."><?= htmlspecialchars($campaign['team_members'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Save Changes</button>
            <a href="campaign_view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
