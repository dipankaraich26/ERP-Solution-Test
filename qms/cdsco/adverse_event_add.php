<?php
include "../../db.php";
include "../../includes/sidebar.php";

$error = '';

// Get products for dropdown
$products = $pdo->query("SELECT id, product_name FROM qms_cdsco_products ORDER BY product_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $event_description = trim($_POST['event_description'] ?? '');
    $severity = trim($_POST['severity'] ?? 'Moderate');
    $patient_outcome = trim($_POST['patient_outcome'] ?? '');
    $root_cause = trim($_POST['root_cause'] ?? '');
    $corrective_action = trim($_POST['corrective_action'] ?? '');
    $reported_to_cdsco = isset($_POST['reported_to_cdsco']) ? 1 : 0;
    $report_date = trim($_POST['report_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Open');

    if (empty($product_name) || empty($event_date) || empty($event_description)) {
        $error = "Product, event date, and event description are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qms_cdsco_adverse_events
                (product_name, event_date, event_description, severity, patient_outcome,
                 root_cause, corrective_action, reported_to_cdsco, report_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $product_name,
                $event_date,
                $event_description,
                $severity,
                $patient_outcome,
                $root_cause,
                $corrective_action,
                $reported_to_cdsco,
                $report_date ?: null,
                $status
            ]);

            header("Location: adverse_events.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error reporting event: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report Adverse Event - CDSCO</title>
    <link rel="stylesheet" href="../../assets/style.css">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .required::after {
            content: ' *';
            color: red;
        }
        .info-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .severity-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Report Adverse Event (MDVR)</h1>
        <a href="adverse_events.php" class="btn btn-secondary">← Back to Adverse Events</a>
    </div>

    <?php if ($error): ?>
        <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Medical Device Vigilance Report (MDVR):</strong>
        As per CDSCO regulations, serious adverse events must be reported within 10 days of awareness.
        Life-threatening events or deaths must be reported within 72 hours.
    </div>

    <div class="form-container">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Product Name</label>
                    <select name="product_name" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= htmlspecialchars($p['product_name']) ?>"
                            <?= ($_POST['product_name'] ?? '') === $p['product_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['product_name']) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="Other">Other (specify in description)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Event Date</label>
                    <input type="date" name="event_date" value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Severity</label>
                    <select name="severity" required>
                        <option value="Minor">Minor - No patient harm</option>
                        <option value="Moderate" selected>Moderate - Non-serious injury</option>
                        <option value="Serious">Serious - Hospitalization/disability</option>
                        <option value="Critical">Critical - Life-threatening/death</option>
                    </select>
                    <div class="severity-info">
                        Critical/Serious events require immediate CDSCO notification
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Open">Open</option>
                        <option value="Investigating">Investigating</option>
                        <option value="Closed">Closed</option>
                        <option value="Reported">Reported to CDSCO</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="required">Event Description</label>
                <textarea name="event_description" required
                    placeholder="Describe the adverse event in detail: what happened, device involved, circumstances..."><?= htmlspecialchars($_POST['event_description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Patient Outcome</label>
                <textarea name="patient_outcome"
                    placeholder="Describe the patient outcome: injuries, treatment required, recovery status..."><?= htmlspecialchars($_POST['patient_outcome'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Root Cause Analysis</label>
                <textarea name="root_cause"
                    placeholder="Identified root cause(s) of the event..."><?= htmlspecialchars($_POST['root_cause'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Corrective Action Taken</label>
                <textarea name="corrective_action"
                    placeholder="Describe corrective and preventive actions taken or planned..."><?= htmlspecialchars($_POST['corrective_action'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="reported_to_cdsco" id="reported_to_cdsco" value="1">
                        <label for="reported_to_cdsco" style="margin-bottom: 0; font-weight: normal;">
                            Reported to CDSCO
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>CDSCO Report Date</label>
                    <input type="date" name="report_date" value="<?= htmlspecialchars($_POST['report_date'] ?? '') ?>">
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Submit Report</button>
                <a href="adverse_events.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
