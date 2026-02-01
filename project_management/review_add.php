<?php
include "../db.php";
include "../includes/dialog.php";
include "../includes/auth.php";
requireLogin();

$errors = [];

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_no, project_name FROM projects WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);

// Get users for dropdown
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get employees for review leader dropdown
$employees = [];
try {
    $employees = $pdo->query("SELECT id, emp_name FROM employees WHERE status = 'Active' ORDER BY emp_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // employees table may not exist, use users as fallback
}

// Predefined review locations
$review_locations = [
    'Conference Room A',
    'Conference Room B',
    'Conference Room C',
    'Board Room',
    'Training Room',
    'Virtual / Online',
    'Client Location',
    'Factory Floor',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $review_type = trim($_POST['review_type'] ?? '');
    $review_title = trim($_POST['review_title'] ?? '');
    $review_date = trim($_POST['review_date'] ?? '');
    $review_location = trim($_POST['review_location'] ?? '');
    $review_leader = trim($_POST['review_leader'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');
    $scope = trim($_POST['scope'] ?? '');
    $participants = trim($_POST['participants'] ?? '');

    // Validation
    if (empty($review_type)) $errors[] = "Review type is required";
    if (empty($review_title)) $errors[] = "Review title is required";
    if (empty($review_date)) $errors[] = "Review date is required";

    if (empty($errors)) {
        // Generate review number
        $year = date('Y');
        $maxNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(review_no, 5) AS UNSIGNED)) FROM engineering_reviews WHERE review_no LIKE 'REV-$year%'")->fetchColumn();
        $review_no = 'REV-' . $year . '-' . str_pad(($maxNo ?: 0) + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO engineering_reviews (
                review_no, project_id, review_type, review_title, review_date,
                review_location, review_leader, description, objectives, scope,
                participants, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)
        ");

        $stmt->execute([
            $review_no, $project_id, $review_type, $review_title, $review_date,
            $review_location ?: null, $review_leader ?: null,
            $description ?: null, $objectives ?: null, $scope ?: null,
            $participants ?: null, $_SESSION['user_id'] ?? null
        ]);

        $newId = $pdo->lastInsertId();
        setModal("Success", "Engineering Review '$review_no' scheduled successfully!");
        header("Location: review_view.php?id=$newId");
        exit;
    }
}

include "../includes/sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Schedule Engineering Review - Product Engineering</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .page-header h1 { margin: 0; color: #2c3e50; }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 900px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #667eea;
            font-size: 1.1em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #495057;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.85em;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c62828;
        }
        .error-box ul { margin: 0; padding-left: 20px; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .review-type-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9em;
            display: none;
        }

        body.dark .form-container { background: #2c3e50; }
        body.dark .form-section h3 { color: #667eea; }
        body.dark .form-group label { color: #ecf0f1; }
        body.dark .form-group input, body.dark .form-group select, body.dark .form-group textarea {
            background: #34495e;
            border-color: #4a6278;
            color: #ecf0f1;
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<script>
const toggle = document.getElementById("themeToggle");
const body = document.body;
if (toggle) {
    if (localStorage.getItem("theme") === "dark") {
        body.classList.add("dark");
        toggle.textContent = "Light Mode";
    }
    toggle.addEventListener("click", () => {
        body.classList.toggle("dark");
        localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
        toggle.textContent = body.classList.contains("dark") ? "Light Mode" : "Dark Mode";
    });
}
</script>

<div class="content" style="overflow-y: auto; height: 100vh;">
    <div class="page-header">
        <div>
            <h1>Schedule Engineering Review</h1>
            <p style="color: #666; margin: 5px 0 0;">Create a new design or engineering review</p>
        </div>
        <a href="reviews.php" class="btn btn-secondary">Back to Reviews</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="post">
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Review Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Review Type <span class="required">*</span></label>
                        <select name="review_type" id="review_type" required onchange="showTypeInfo()">
                            <option value="">-- Select Review Type --</option>
                            <option value="Concept Review" <?= ($_POST['review_type'] ?? '') === 'Concept Review' ? 'selected' : '' ?>>Concept Review</option>
                            <option value="Preliminary Design Review" <?= ($_POST['review_type'] ?? '') === 'Preliminary Design Review' ? 'selected' : '' ?>>Preliminary Design Review (PDR)</option>
                            <option value="Critical Design Review" <?= ($_POST['review_type'] ?? '') === 'Critical Design Review' ? 'selected' : '' ?>>Critical Design Review (CDR)</option>
                            <option value="Production Readiness Review" <?= ($_POST['review_type'] ?? '') === 'Production Readiness Review' ? 'selected' : '' ?>>Production Readiness Review (PRR)</option>
                            <option value="Post-Production Review" <?= ($_POST['review_type'] ?? '') === 'Post-Production Review' ? 'selected' : '' ?>>Post-Production Review</option>
                            <option value="Other" <?= ($_POST['review_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <div class="review-type-info" id="type-info"></div>
                    </div>

                    <div class="form-group">
                        <label>Project (Optional)</label>
                        <select name="project_id">
                            <option value="">-- Not linked to a project --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>" <?= ($_POST['project_id'] ?? '') == $proj['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proj['project_no'] . ' - ' . $proj['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Review Title <span class="required">*</span></label>
                        <input type="text" name="review_title" value="<?= htmlspecialchars($_POST['review_title'] ?? '') ?>" required placeholder="e.g., Product X - Preliminary Design Review">
                    </div>

                    <div class="form-group">
                        <label>Review Date <span class="required">*</span></label>
                        <input type="date" name="review_date" value="<?= htmlspecialchars($_POST['review_date'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Review Location</label>
                        <select name="review_location" id="review_location">
                            <option value="">-- Select Location --</option>
                            <?php foreach ($review_locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= ($_POST['review_location'] ?? '') === $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Review Leader / Chairperson</label>
                        <select name="review_leader" id="review_leader">
                            <option value="">-- Select Review Leader --</option>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= htmlspecialchars($emp['emp_name']) ?>" <?= ($_POST['review_leader'] ?? '') === $emp['emp_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['emp_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['full_name']) ?>" <?= ($_POST['review_leader'] ?? '') === $user['full_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Review Details -->
            <div class="form-section">
                <h3>Review Details</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Brief description of this review..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Objectives</label>
                        <textarea name="objectives" rows="3" placeholder="What are the objectives of this review?"><?= htmlspecialchars($_POST['objectives'] ?? '') ?></textarea>
                        <small>Define what this review should accomplish</small>
                    </div>

                    <div class="form-group full-width">
                        <label>Scope</label>
                        <textarea name="scope" rows="3" placeholder="What is in scope for this review?"><?= htmlspecialchars($_POST['scope'] ?? '') ?></textarea>
                        <small>Define the boundaries of what will be reviewed</small>
                    </div>

                    <div class="form-group full-width">
                        <label>Participants / Attendees</label>
                        <textarea name="participants" rows="3" placeholder="List of participants (one per line or comma-separated)..."><?= htmlspecialchars($_POST['participants'] ?? '') ?></textarea>
                        <small>Enter names of review participants</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Schedule Review</button>
                <a href="reviews.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const typeInfo = {
    'Concept Review': 'Early-stage review to evaluate the feasibility and initial concept of a product or design. Focuses on requirements, market fit, and high-level technical approach.',
    'Preliminary Design Review': 'PDR evaluates the preliminary design to ensure it meets requirements and is ready to proceed to detailed design. Reviews specifications, interfaces, and risk assessments.',
    'Critical Design Review': 'CDR evaluates the detailed design before manufacturing. Ensures the design is complete, meets all requirements, and is producible. Reviews drawings, BOM, test plans.',
    'Production Readiness Review': 'PRR confirms that manufacturing processes, tooling, and supply chain are ready for production. Reviews process capability, quality plans, and supplier readiness.',
    'Post-Production Review': 'Review conducted after initial production to assess performance, identify issues, and capture lessons learned for continuous improvement.',
    'Other': 'Custom review type for specific needs not covered by standard review types.'
};

function showTypeInfo() {
    const select = document.getElementById('review_type');
    const infoDiv = document.getElementById('type-info');
    const selectedType = select.value;

    if (selectedType && typeInfo[selectedType]) {
        infoDiv.textContent = typeInfo[selectedType];
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

// Show info on page load if type is selected
showTypeInfo();
</script>

</body>
</html>
