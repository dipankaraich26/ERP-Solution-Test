<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch lead
$stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    header("Location: index.php");
    exit;
}

$errors = [];

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic info
    $customer_type = $_POST['customer_type'] ?? 'B2B';
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Address
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $country = trim($_POST['country'] ?? 'India');

    // Lead details
    $lead_status = $_POST['lead_status'] ?? 'cold';
    $lead_source = trim($_POST['lead_source'] ?? '');
    $industry = trim($_POST['industry'] ?? '');

    // Buying intent
    $buying_timeline = $_POST['buying_timeline'] ?? 'uncertain';
    $budget_range = trim($_POST['budget_range'] ?? '');
    $decision_maker = $_POST['decision_maker'] ?? 'no';

    // Follow-up & notes
    $next_followup_date = $_POST['next_followup_date'] ?? '';
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if ($contact_person === '') {
        $errors[] = "Contact person name is required";
    }
    if ($phone === '') {
        $errors[] = "Contact number is required";
    }
    if ($customer_type === 'B2B' && $company_name === '') {
        $errors[] = "Company name is required for B2B leads";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE crm_leads SET
                customer_type = ?, company_name = ?, contact_person = ?, designation = ?,
                phone = ?, email = ?,
                address1 = ?, address2 = ?, city = ?, state = ?, pincode = ?, country = ?,
                lead_status = ?, lead_source = ?, industry = ?,
                buying_timeline = ?, budget_range = ?, decision_maker = ?,
                next_followup_date = ?, assigned_to = ?, notes = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $customer_type, $company_name ?: null, $contact_person, $designation ?: null,
            $phone, $email ?: null,
            $address1 ?: null, $address2 ?: null, $city ?: null, $state ?: null, $pincode ?: null, $country,
            $lead_status, $lead_source ?: null, $industry ?: null,
            $buying_timeline, $budget_range ?: null, $decision_maker,
            $next_followup_date ?: null, $assigned_to ?: null, $notes ?: null,
            $id
        ]);

        setModal("Success", "Lead updated successfully!");
        header("Location: view.php?id=$id");
        exit;
    }
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Lead - <?= htmlspecialchars($lead['lead_no']) ?></title>
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
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }

        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }
        .radio-group input[type="radio"] { width: auto; }

        .status-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .status-options label {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: normal;
        }
        .status-options input[type="radio"] { display: none; }
        .status-options input[type="radio"]:checked + span { font-weight: bold; }
        .status-options label:has(input:checked) { border-color: #3498db; background: #ebf5fb; }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        .delete-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff5f5;
            border: 1px solid #ffcccc;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="content">
    <div class="form-container">
        <h1>Edit Lead: <?= htmlspecialchars($lead['lead_no']) ?></h1>

        <p>
            <a href="index.php" class="btn btn-secondary">Back to Leads</a>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">View Lead</a>
        </p>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">

            <!-- Customer Type -->
            <div class="form-section">
                <h3>Customer Type</h3>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="customer_type" value="B2B"
                               <?= $lead['customer_type'] === 'B2B' ? 'checked' : '' ?>>
                        <strong>B2B</strong> (Business to Business)
                    </label>
                    <label>
                        <input type="radio" name="customer_type" value="B2C"
                               <?= $lead['customer_type'] === 'B2C' ? 'checked' : '' ?>>
                        <strong>B2C</strong> (Business to Consumer)
                    </label>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" id="company_name"
                               value="<?= htmlspecialchars($lead['company_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Person *</label>
                        <input type="text" name="contact_person" required
                               value="<?= htmlspecialchars($lead['contact_person']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation"
                               value="<?= htmlspecialchars($lead['designation'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <input type="text" name="industry"
                               value="<?= htmlspecialchars($lead['industry'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="phone" required
                               value="<?= htmlspecialchars($lead['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email"
                               value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section">
                <h3>Address</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 1</label>
                        <input type="text" name="address1"
                               value="<?= htmlspecialchars($lead['address1'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Address Line 2</label>
                        <input type="text" name="address2"
                               value="<?= htmlspecialchars($lead['address2'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city"
                               value="<?= htmlspecialchars($lead['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" name="state"
                               value="<?= htmlspecialchars($lead['state'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode"
                               value="<?= htmlspecialchars($lead['pincode'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country"
                               value="<?= htmlspecialchars($lead['country'] ?? 'India') ?>">
                    </div>
                </div>
            </div>

            <!-- Lead Classification -->
            <div class="form-section">
                <h3>Lead Classification</h3>

                <div class="form-group">
                    <label>Lead Status</label>
                    <div class="status-options">
                        <?php foreach (['cold', 'warm', 'hot', 'converted', 'lost'] as $s): ?>
                        <label>
                            <input type="radio" name="lead_status" value="<?= $s ?>"
                                   <?= $lead['lead_status'] === $s ? 'checked' : '' ?>>
                            <span><?= ucfirst($s) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Lead Source</label>
                        <select name="lead_source">
                            <option value="">-- Select Source --</option>
                            <?php
                            $sources = ['Website', 'Referral', 'Cold Call', 'Trade Show', 'Social Media', 'Email Campaign', 'Walk-in', 'Other'];
                            foreach ($sources as $src):
                            ?>
                                <option value="<?= $src ?>" <?= $lead['lead_source'] === $src ? 'selected' : '' ?>>
                                    <?= $src ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Buying Intent -->
            <div class="form-section">
                <h3>Buying Intent</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Buying Timeline</label>
                        <select name="buying_timeline">
                            <?php
                            $timelines = [
                                'uncertain' => 'Uncertain',
                                'immediate' => 'Immediate',
                                '1_month' => 'Within 1 Month',
                                '3_months' => 'Within 3 Months',
                                '6_months' => 'Within 6 Months',
                                '1_year' => 'Within 1 Year'
                            ];
                            foreach ($timelines as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= $lead['buying_timeline'] === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget Range</label>
                        <input type="text" name="budget_range"
                               value="<?= htmlspecialchars($lead['budget_range'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Decision Maker?</label>
                        <select name="decision_maker">
                            <option value="no" <?= $lead['decision_maker'] === 'no' ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= $lead['decision_maker'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                            <option value="influencer" <?= $lead['decision_maker'] === 'influencer' ? 'selected' : '' ?>>Influencer</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Follow-up & Assignment -->
            <div class="form-section">
                <h3>Follow-up & Assignment</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Next Follow-up Date</label>
                        <input type="date" name="next_followup_date"
                               value="<?= htmlspecialchars($lead['next_followup_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <input type="text" name="assigned_to"
                               value="<?= htmlspecialchars($lead['assigned_to'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes"><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 30px; font-size: 1.1em;">
                    Update Lead
                </button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </div>

        </form>

        <!-- Delete Section -->
        <div class="delete-section">
            <h4 style="margin-top: 0; color: #c0392b;">Danger Zone</h4>
            <p>Deleting a lead will also remove all its requirements and interactions.</p>
            <form method="post" action="delete.php" onsubmit="return confirm('Are you sure you want to delete this lead? This action cannot be undone.');">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn" style="background: #e74c3c; color: #fff;">
                    Delete This Lead
                </button>
            </form>
        </div>

    </div>
</div>

</body>
</html>
