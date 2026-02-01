<?php
/**
 * Appraisal Form
 * Self-review, Manager review, and HR review process
 */

include "../db.php";
include "../includes/auth.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("Location: appraisals.php");
    exit;
}

// Check if tables exist
$tableExists = $pdo->query("SHOW TABLES LIKE 'appraisals'")->fetch();
if (!$tableExists) {
    header("Location: /admin/setup_hr_appraisal.php");
    exit;
}

$message = '';
$error = '';

// Fetch appraisal with details
$stmt = $pdo->prepare("
    SELECT a.*, e.emp_id, e.first_name, e.last_name, e.department, e.designation, e.date_of_joining,
           ac.cycle_name, ac.cycle_type, ac.start_date, ac.end_date,
           r.first_name as reviewer_first, r.last_name as reviewer_last, r.emp_id as reviewer_emp_id
    FROM appraisals a
    JOIN employees e ON a.employee_id = e.id
    JOIN appraisal_cycles ac ON a.cycle_id = ac.id
    LEFT JOIN employees r ON a.reviewer_id = r.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$appraisal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appraisal) {
    header("Location: appraisals.php");
    exit;
}

// Fetch criteria
$criteria = $pdo->query("
    SELECT * FROM appraisal_criteria WHERE is_active = 1 ORDER BY sort_order
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing ratings
$ratingsStmt = $pdo->prepare("SELECT * FROM appraisal_ratings WHERE appraisal_id = ?");
$ratingsStmt->execute([$id]);
$existingRatings = [];
while ($r = $ratingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $existingRatings[$r['criteria_id']] = $r;
}

// Fetch goals
$goalsStmt = $pdo->prepare("SELECT * FROM appraisal_goals WHERE appraisal_id = ?");
$goalsStmt->execute([$id]);
$goals = $goalsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviewers (managers) for assignment
$reviewers = $pdo->query("
    SELECT id, emp_id, first_name, last_name, designation
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($action === 'save_self_review') {
            // Save self ratings
            $selfOverall = 0;
            $totalWeight = 0;

            foreach ($_POST['self_rating'] ?? [] as $criteriaId => $rating) {
                $comment = $_POST['self_comment'][$criteriaId] ?? '';

                $pdo->prepare("
                    INSERT INTO appraisal_ratings (appraisal_id, criteria_id, self_rating, self_comments)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE self_rating = VALUES(self_rating), self_comments = VALUES(self_comments)
                ")->execute([$id, $criteriaId, $rating, $comment]);

                // Calculate weighted average
                $crit = array_filter($criteria, fn($c) => $c['id'] == $criteriaId);
                $crit = reset($crit);
                if ($crit) {
                    $selfOverall += ($rating / $crit['max_rating']) * $crit['weightage'];
                    $totalWeight += $crit['weightage'];
                }
            }

            $selfOverallRating = $totalWeight > 0 ? ($selfOverall / $totalWeight) * 5 : 0;

            // Update appraisal
            $pdo->prepare("
                UPDATE appraisals SET
                    self_review_date = NOW(),
                    self_overall_rating = ?,
                    self_strengths = ?,
                    self_improvements = ?,
                    self_goals = ?,
                    self_training_needs = ?,
                    status = 'Self Review'
                WHERE id = ?
            ")->execute([
                $selfOverallRating,
                $_POST['self_strengths'] ?? '',
                $_POST['self_improvements'] ?? '',
                $_POST['self_goals'] ?? '',
                $_POST['self_training_needs'] ?? '',
                $id
            ]);

            $message = "Self review saved successfully!";
        }

        if ($action === 'submit_self_review') {
            // Submit to manager
            $pdo->prepare("UPDATE appraisals SET status = 'Manager Review' WHERE id = ?")->execute([$id]);
            $message = "Self review submitted for manager review!";
        }

        if ($action === 'assign_reviewer') {
            $reviewerId = intval($_POST['reviewer_id']);
            $pdo->prepare("UPDATE appraisals SET reviewer_id = ? WHERE id = ?")->execute([$reviewerId, $id]);
            $message = "Reviewer assigned successfully!";
        }

        if ($action === 'save_manager_review') {
            $managerOverall = 0;
            $totalWeight = 0;

            foreach ($_POST['manager_rating'] ?? [] as $criteriaId => $rating) {
                $comment = $_POST['manager_comment'][$criteriaId] ?? '';

                $pdo->prepare("
                    INSERT INTO appraisal_ratings (appraisal_id, criteria_id, manager_rating, manager_comments)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE manager_rating = VALUES(manager_rating), manager_comments = VALUES(manager_comments)
                ")->execute([$id, $criteriaId, $rating, $comment]);

                $crit = array_filter($criteria, fn($c) => $c['id'] == $criteriaId);
                $crit = reset($crit);
                if ($crit) {
                    $managerOverall += ($rating / $crit['max_rating']) * $crit['weightage'];
                    $totalWeight += $crit['weightage'];
                }
            }

            $managerOverallRating = $totalWeight > 0 ? ($managerOverall / $totalWeight) * 5 : 0;

            $pdo->prepare("
                UPDATE appraisals SET
                    manager_review_date = NOW(),
                    manager_overall_rating = ?,
                    manager_strengths = ?,
                    manager_improvements = ?,
                    manager_recommendations = ?,
                    promotion_recommendation = ?,
                    salary_increment_recommendation = ?,
                    status = 'Manager Review'
                WHERE id = ?
            ")->execute([
                $managerOverallRating,
                $_POST['manager_strengths'] ?? '',
                $_POST['manager_improvements'] ?? '',
                $_POST['manager_recommendations'] ?? '',
                $_POST['promotion_recommendation'] ?? 'No',
                $_POST['salary_increment_recommendation'] ?? 0,
                $id
            ]);

            $message = "Manager review saved!";
        }

        if ($action === 'submit_manager_review') {
            $pdo->prepare("UPDATE appraisals SET status = 'HR Review' WHERE id = ?")->execute([$id]);
            $message = "Submitted for HR review!";
        }

        if ($action === 'complete_review') {
            // Calculate final rating (average of self and manager or just manager)
            $finalRating = $appraisal['manager_overall_rating'] ?? $appraisal['self_overall_rating'] ?? 0;
            $finalGrade = '';

            if ($finalRating >= 4.5) $finalGrade = 'A+';
            elseif ($finalRating >= 4) $finalGrade = 'A';
            elseif ($finalRating >= 3.5) $finalGrade = 'B+';
            elseif ($finalRating >= 3) $finalGrade = 'B';
            elseif ($finalRating >= 2.5) $finalGrade = 'C+';
            elseif ($finalRating >= 2) $finalGrade = 'C';
            else $finalGrade = 'D';

            $pdo->prepare("
                UPDATE appraisals SET
                    hr_review_date = NOW(),
                    hr_comments = ?,
                    final_rating = ?,
                    final_grade = ?,
                    status = 'Completed'
                WHERE id = ?
            ")->execute([
                $_POST['hr_comments'] ?? '',
                $finalRating,
                $finalGrade,
                $id
            ]);

            $message = "Appraisal completed!";
        }

        if ($action === 'acknowledge') {
            $pdo->prepare("
                UPDATE appraisals SET
                    acknowledged_at = NOW(),
                    employee_comments = ?,
                    status = 'Acknowledged'
                WHERE id = ?
            ")->execute([$_POST['employee_comments'] ?? '', $id]);

            $message = "Appraisal acknowledged!";
        }

        $pdo->commit();

        // Refresh data
        header("Location: appraisal_form.php?id=$id&msg=" . urlencode($message));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Re-fetch appraisal after updates
$stmt->execute([$id]);
$appraisal = $stmt->fetch(PDO::FETCH_ASSOC);

// Re-fetch ratings
$ratingsStmt->execute([$id]);
$existingRatings = [];
while ($r = $ratingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $existingRatings[$r['criteria_id']] = $r;
}

include "../includes/sidebar.php";
showModal();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appraisal Form - <?= htmlspecialchars($appraisal['appraisal_no']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .appraisal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .appraisal-header h1 {
            margin: 0 0 10px 0;
        }
        .header-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .header-info-item {
            opacity: 0.9;
        }
        .header-info-item strong {
            display: block;
            opacity: 0.7;
            font-size: 0.85em;
        }
        .status-flow {
            display: flex;
            justify-content: space-between;
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .flow-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .flow-step::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: 0;
        }
        .flow-step:last-child::after {
            display: none;
        }
        .flow-step.completed::after {
            background: #28a745;
        }
        .flow-step.active::after {
            background: linear-gradient(90deg, #28a745 50%, #ddd 50%);
        }
        .flow-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            font-size: 0.9em;
        }
        .flow-step.completed .flow-icon {
            background: #28a745;
            color: white;
        }
        .flow-step.active .flow-icon {
            background: #3498db;
            color: white;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.5); }
            50% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
        }
        .flow-label {
            margin-top: 8px;
            font-size: 0.85em;
            color: #666;
        }
        .flow-step.active .flow-label {
            color: #3498db;
            font-weight: bold;
        }
        .section-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-card h3 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        .rating-table {
            width: 100%;
            border-collapse: collapse;
        }
        .rating-table th, .rating-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .rating-table th {
            background: #f8f9fa;
            font-weight: 500;
        }
        .criteria-name {
            font-weight: 500;
        }
        .criteria-desc {
            font-size: 0.85em;
            color: #666;
        }
        .criteria-weight {
            font-size: 0.85em;
            color: #3498db;
        }
        .star-rating {
            display: flex;
            gap: 5px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 1.5em;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        .star-rating:hover label {
            color: #ddd;
        }
        .star-rating:hover label:hover,
        .star-rating:hover label:hover ~ label {
            color: #ffc107;
        }
        .rating-select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 100px;
        }
        .comment-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }
        .text-area {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .overall-rating {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        .overall-rating .rating-value {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
        }
        .overall-rating .grade {
            display: inline-block;
            padding: 5px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            margin-left: 10px;
        }
        .readonly-field {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="content">
    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="appraisal-header">
        <h1><?= htmlspecialchars($appraisal['appraisal_no']) ?></h1>
        <div class="header-info">
            <div class="header-info-item">
                <strong>Employee</strong>
                <?= htmlspecialchars($appraisal['first_name'] . ' ' . $appraisal['last_name']) ?>
                (<?= htmlspecialchars($appraisal['emp_id']) ?>)
            </div>
            <div class="header-info-item">
                <strong>Department</strong>
                <?= htmlspecialchars($appraisal['department'] ?: 'N/A') ?>
            </div>
            <div class="header-info-item">
                <strong>Designation</strong>
                <?= htmlspecialchars($appraisal['designation'] ?: 'N/A') ?>
            </div>
            <div class="header-info-item">
                <strong>Appraisal Cycle</strong>
                <?= htmlspecialchars($appraisal['cycle_name']) ?>
            </div>
            <div class="header-info-item">
                <strong>Period</strong>
                <?= date('d M Y', strtotime($appraisal['start_date'])) ?> -
                <?= date('d M Y', strtotime($appraisal['end_date'])) ?>
            </div>
        </div>
    </div>

    <!-- Status Flow -->
    <div class="status-flow">
        <?php
        $steps = ['Draft', 'Self Review', 'Manager Review', 'HR Review', 'Completed', 'Acknowledged'];
        $currentIdx = array_search($appraisal['status'], $steps);
        foreach ($steps as $idx => $step):
            $isCompleted = $idx < $currentIdx;
            $isActive = $idx == $currentIdx;
            $stepClass = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
        ?>
        <div class="flow-step <?= $stepClass ?>">
            <div class="flow-icon"><?= $isCompleted ? '✓' : ($idx + 1) ?></div>
            <div class="flow-label"><?= $step ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Self Review Section -->
    <?php if (in_array($appraisal['status'], ['Draft', 'Self Review'])): ?>
    <div class="section-card">
        <h3>Self Review</h3>
        <form method="post">
            <input type="hidden" name="action" value="save_self_review" id="selfReviewAction">

            <table class="rating-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Criteria</th>
                        <th style="width: 15%;">Weight</th>
                        <th style="width: 20%;">Rating (1-5)</th>
                        <th style="width: 30%;">Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criteria as $c):
                        $existing = $existingRatings[$c['id']] ?? [];
                    ?>
                    <tr>
                        <td>
                            <div class="criteria-name"><?= htmlspecialchars($c['criteria_name']) ?></div>
                            <div class="criteria-desc"><?= htmlspecialchars($c['description']) ?></div>
                        </td>
                        <td><span class="criteria-weight"><?= $c['weightage'] ?>%</span></td>
                        <td>
                            <select name="self_rating[<?= $c['id'] ?>]" class="rating-select" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= $c['max_rating']; $i++): ?>
                                <option value="<?= $i ?>" <?= ($existing['self_rating'] ?? '') == $i ? 'selected' : '' ?>>
                                    <?= $i ?> - <?= ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1] ?? '' ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="self_comment[<?= $c['id'] ?>]" class="comment-input"
                                   value="<?= htmlspecialchars($existing['self_comments'] ?? '') ?>"
                                   placeholder="Optional comments...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 25px;">
                <div class="form-group">
                    <label>Key Strengths</label>
                    <textarea name="self_strengths" class="text-area" placeholder="List your key strengths..."><?= htmlspecialchars($appraisal['self_strengths'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Areas for Improvement</label>
                    <textarea name="self_improvements" class="text-area" placeholder="Areas where you can improve..."><?= htmlspecialchars($appraisal['self_improvements'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Goals for Next Period</label>
                    <textarea name="self_goals" class="text-area" placeholder="Your goals..."><?= htmlspecialchars($appraisal['self_goals'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Training Needs</label>
                    <textarea name="self_training_needs" class="text-area" placeholder="Training you would like..."><?= htmlspecialchars($appraisal['self_training_needs'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Save Self Review</button>
                <button type="submit" class="btn btn-success" onclick="document.getElementById('selfReviewAction').value='submit_self_review'; return confirm('Submit for Manager Review?');">
                    Submit for Manager Review
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Manager Review Section -->
    <?php if (in_array($appraisal['status'], ['Manager Review', 'HR Review', 'Completed', 'Acknowledged'])): ?>
    <div class="section-card">
        <h3>Manager Review</h3>

        <?php if (!$appraisal['reviewer_id'] && $appraisal['status'] === 'Manager Review'): ?>
        <form method="post" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
            <input type="hidden" name="action" value="assign_reviewer">
            <label style="font-weight: 500;">Assign Reviewer:</label>
            <select name="reviewer_id" required style="padding: 8px; margin: 0 10px;">
                <option value="">-- Select Manager --</option>
                <?php foreach ($reviewers as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?> (<?= $r['emp_id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Assign</button>
        </form>
        <?php endif; ?>

        <?php if ($appraisal['reviewer_id']): ?>
        <p style="margin-bottom: 20px;">
            <strong>Reviewer:</strong>
            <?= htmlspecialchars($appraisal['reviewer_first'] . ' ' . $appraisal['reviewer_last']) ?>
            (<?= htmlspecialchars($appraisal['reviewer_emp_id']) ?>)
        </p>
        <?php endif; ?>

        <?php if ($appraisal['status'] === 'Manager Review' && $appraisal['reviewer_id']): ?>
        <form method="post">
            <input type="hidden" name="action" value="save_manager_review" id="managerReviewAction">

            <table class="rating-table">
                <thead>
                    <tr>
                        <th>Criteria</th>
                        <th>Self Rating</th>
                        <th>Manager Rating</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criteria as $c):
                        $existing = $existingRatings[$c['id']] ?? [];
                    ?>
                    <tr>
                        <td>
                            <div class="criteria-name"><?= htmlspecialchars($c['criteria_name']) ?></div>
                            <div class="criteria-weight"><?= $c['weightage'] ?>%</div>
                        </td>
                        <td>
                            <strong><?= $existing['self_rating'] ?? '-' ?></strong>
                            <?php if ($existing['self_comments'] ?? ''): ?>
                            <div style="font-size: 0.85em; color: #666;"><?= htmlspecialchars($existing['self_comments']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="manager_rating[<?= $c['id'] ?>]" class="rating-select" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= $c['max_rating']; $i++): ?>
                                <option value="<?= $i ?>" <?= ($existing['manager_rating'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="manager_comment[<?= $c['id'] ?>]" class="comment-input"
                                   value="<?= htmlspecialchars($existing['manager_comments'] ?? '') ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 25px;">
                <div class="form-group">
                    <label>Key Strengths Observed</label>
                    <textarea name="manager_strengths" class="text-area"><?= htmlspecialchars($appraisal['manager_strengths'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Areas for Improvement</label>
                    <textarea name="manager_improvements" class="text-area"><?= htmlspecialchars($appraisal['manager_improvements'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Recommendations</label>
                    <textarea name="manager_recommendations" class="text-area"><?= htmlspecialchars($appraisal['manager_recommendations'] ?? '') ?></textarea>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div class="form-group">
                        <label>Promotion Recommendation</label>
                        <select name="promotion_recommendation" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="No" <?= $appraisal['promotion_recommendation'] === 'No' ? 'selected' : '' ?>>No</option>
                            <option value="Maybe" <?= $appraisal['promotion_recommendation'] === 'Maybe' ? 'selected' : '' ?>>Maybe</option>
                            <option value="Yes" <?= $appraisal['promotion_recommendation'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Salary Increment % (Recommended)</label>
                        <input type="number" name="salary_increment_recommendation" step="0.5" min="0" max="50"
                               value="<?= $appraisal['salary_increment_recommendation'] ?? 0 ?>"
                               style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100px;">
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Save Manager Review</button>
                <button type="submit" class="btn btn-success"
                        onclick="document.getElementById('managerReviewAction').value='submit_manager_review'; return confirm('Submit for HR Review?');">
                    Submit for HR Review
                </button>
            </div>
        </form>
        <?php endif; ?>

        <?php if (in_array($appraisal['status'], ['HR Review', 'Completed', 'Acknowledged']) && $appraisal['manager_review_date']): ?>
        <div class="readonly-field" style="margin-top: 15px;">
            <p><strong>Manager Rating:</strong> <?= number_format($appraisal['manager_overall_rating'], 2) ?>/5</p>
            <p><strong>Reviewed on:</strong> <?= date('d M Y', strtotime($appraisal['manager_review_date'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- HR Review Section -->
    <?php if ($appraisal['status'] === 'HR Review'): ?>
    <div class="section-card">
        <h3>HR Review & Completion</h3>
        <form method="post">
            <input type="hidden" name="action" value="complete_review">

            <div class="form-group">
                <label>HR Comments</label>
                <textarea name="hr_comments" class="text-area" placeholder="Final comments from HR..."><?= htmlspecialchars($appraisal['hr_comments'] ?? '') ?></textarea>
            </div>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h4 style="margin-top: 0;">Rating Summary</h4>
                <p><strong>Self Rating:</strong> <?= number_format($appraisal['self_overall_rating'] ?? 0, 2) ?>/5</p>
                <p><strong>Manager Rating:</strong> <?= number_format($appraisal['manager_overall_rating'] ?? 0, 2) ?>/5</p>
                <p><strong>Promotion Recommended:</strong> <?= $appraisal['promotion_recommendation'] ?? 'No' ?></p>
                <p><strong>Increment Recommended:</strong> <?= $appraisal['salary_increment_recommendation'] ?? 0 ?>%</p>
            </div>

            <button type="submit" class="btn btn-success" onclick="return confirm('Complete this appraisal?');">
                Complete Appraisal
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Completed/Acknowledged View -->
    <?php if (in_array($appraisal['status'], ['Completed', 'Acknowledged'])): ?>
    <div class="section-card">
        <h3>Final Appraisal</h3>

        <div class="overall-rating">
            <div>
                <span class="rating-value"><?= number_format($appraisal['final_rating'], 2) ?></span>
                <span class="grade"><?= htmlspecialchars($appraisal['final_grade']) ?></span>
            </div>
            <div style="margin-top: 10px; color: #666;">Final Rating</div>
        </div>

        <?php if ($appraisal['status'] === 'Completed'): ?>
        <div style="margin-top: 30px;">
            <h4>Employee Acknowledgement</h4>
            <form method="post">
                <input type="hidden" name="action" value="acknowledge">
                <div class="form-group">
                    <label>Your Comments (Optional)</label>
                    <textarea name="employee_comments" class="text-area" placeholder="Any comments or feedback..."></textarea>
                </div>
                <button type="submit" class="btn btn-success" onclick="return confirm('Acknowledge this appraisal?');">
                    Acknowledge Appraisal
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($appraisal['status'] === 'Acknowledged'): ?>
        <div class="readonly-field" style="margin-top: 20px;">
            <p><strong>Acknowledged on:</strong> <?= date('d M Y H:i', strtotime($appraisal['acknowledged_at'])) ?></p>
            <?php if ($appraisal['employee_comments']): ?>
            <p><strong>Employee Comments:</strong> <?= htmlspecialchars($appraisal['employee_comments']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="appraisals.php?cycle_id=<?= $appraisal['cycle_id'] ?>" class="btn btn-secondary">Back to Appraisals</a>
        <a href="javascript:window.print()" class="btn btn-secondary">Print</a>
    </div>
</div>

</body>
</html>
