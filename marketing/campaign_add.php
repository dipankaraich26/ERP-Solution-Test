<?php
include "../db.php";
include "../includes/dialog.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_name = trim($_POST['campaign_name'] ?? '');
    $start_date = $_POST['start_date'] ?? '';

    if ($campaign_name === '') $errors[] = "Campaign name is required";
    if ($start_date === '') $errors[] = "Start date is required";

    if (empty($errors)) {
        // Generate campaign code
        $year = date('Y');
        $maxCode = $pdo->query("SELECT MAX(CAST(SUBSTRING(campaign_code, 5) AS UNSIGNED)) FROM marketing_campaigns WHERE campaign_code LIKE 'MKT-%'")->fetchColumn();
        $campaign_code = 'MKT-' . str_pad(($maxCode ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        // Handle catalog selection
        $catalog_ids = !empty($_POST['catalogs']) ? implode(',', $_POST['catalogs']) : null;

        $stmt = $pdo->prepare("
            INSERT INTO marketing_campaigns (
                campaign_code, campaign_name, campaign_type_id, description,
                state_id, city, venue, venue_address,
                start_date, end_date,
                target_audience, expected_attendees, budget,
                catalog_ids, campaign_manager, team_members, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $campaign_code,
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
            $_POST['status'] ?? 'Planned'
        ]);

        $newId = $pdo->lastInsertId();
        setModal("Success", "Campaign '$campaign_code' created successfully!");
        header("Location: campaign_view.php?id=$newId");
        exit;
    }
}

// Get options for dropdowns
$types = $pdo->query("SELECT id, name FROM campaign_types WHERE is_active = 1 ORDER BY name")->fetchAll();
$states = $pdo->query("SELECT id, state_name FROM india_states ORDER BY state_name")->fetchAll();
$catalogs = $pdo->query("SELECT id, catalog_code, catalog_name FROM marketing_catalogs WHERE status = 'Active' ORDER BY catalog_name")->fetchAll();

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Campaign - Marketing</title>
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
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>New Marketing Campaign</h1>
        <p><a href="campaigns.php" class="btn btn-secondary">Back to Campaigns</a></p>

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
                        <input type="text" name="campaign_name" required>
                    </div>
                    <div class="form-group">
                        <label>Campaign Type</label>
                        <select name="campaign_type_id">
                            <option value="">-- Select Type --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Planned">Planned</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Postponed">Postponed</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Campaign objectives and details..."></textarea>
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
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['state_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city">
                    </div>
                    <div class="form-group">
                        <label>Venue Name</label>
                        <input type="text" name="venue" placeholder="e.g., Hotel Taj, Convention Center">
                    </div>
                    <div class="form-group full-width">
                        <label>Venue Address</label>
                        <textarea name="venue_address" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Target & Budget</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" placeholder="e.g., Doctors, Lab Technicians">
                    </div>
                    <div class="form-group">
                        <label>Expected Attendees</label>
                        <input type="number" name="expected_attendees" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Budget (₹)</label>
                        <input type="number" name="budget" min="0" step="0.01" value="0">
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
                                    <input type="checkbox" name="catalogs[]" value="<?= $cat['id'] ?>">
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
                        <input type="text" name="campaign_manager">
                    </div>
                    <div class="form-group full-width">
                        <label>Team Members</label>
                        <textarea name="team_members" rows="2" placeholder="Names of team members involved..."></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="padding: 12px 30px;">Create Campaign</button>
            <a href="campaigns.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>

        </form>
    </div>
</div>

</body>
</html>
