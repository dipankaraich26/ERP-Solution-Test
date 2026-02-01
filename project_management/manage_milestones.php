<?php
include "../db.php";
include "../includes/dialog.php";

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : (isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0);

if (!$project_id) {
    die("Invalid project ID");
}

// Verify project exists
$project_check = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
$project_check->execute([$project_id]);
if (!$project_check->fetch()) {
    die("Project not found");
}

if ($action === 'add') {
    $milestone_name = trim($_POST['milestone_name'] ?? '');
    $target_date = trim($_POST['target_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');

    $errors = [];

    if (empty($milestone_name)) {
        $errors[] = "Milestone name is required";
    }

    if (empty($target_date)) {
        $errors[] = "Target date is required";
    }

    if (empty($errors)) {
        try {
            // Check if description column exists
            $hasDescription = true;
            try {
                $pdo->query("SELECT description FROM project_milestones LIMIT 1");
            } catch (PDOException $e) {
                $hasDescription = false;
            }

            if ($hasDescription) {
                $stmt = $pdo->prepare("
                    INSERT INTO project_milestones
                    (project_id, milestone_name, target_date, description, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $project_id,
                    $milestone_name,
                    $target_date,
                    !empty($description) ? $description : null,
                    $status
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO project_milestones
                    (project_id, milestone_name, target_date, status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $project_id,
                    $milestone_name,
                    $target_date,
                    $status
                ]);
            }

            setModal("Success", "Milestone added successfully");
        } catch (Exception $e) {
            setModal("Error", "Failed to add milestone: " . $e->getMessage());
        }
    } else {
        setModal("Error", implode(", ", $errors));
    }

    header("Location: view.php?id=" . $project_id);
    exit;

} elseif ($action === 'delete') {
    $milestone_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$milestone_id) {
        die("Invalid milestone ID");
    }

    // Verify milestone belongs to this project
    $milestone_check = $pdo->prepare("SELECT id FROM project_milestones WHERE id = ? AND project_id = ?");
    $milestone_check->execute([$milestone_id, $project_id]);
    if (!$milestone_check->fetch()) {
        die("Milestone not found");
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM project_milestones WHERE id = ?");
        $stmt->execute([$milestone_id]);

        setModal("Success", "Milestone deleted successfully");
    } catch (Exception $e) {
        setModal("Error", "Failed to delete milestone: " . $e->getMessage());
    }

    header("Location: view.php?id=" . $project_id);
    exit;

} elseif ($action === 'complete') {
    $milestone_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$milestone_id) {
        die("Invalid milestone ID");
    }

    try {
        $stmt = $pdo->prepare("UPDATE project_milestones SET status = 'Completed', completion_date = CURDATE() WHERE id = ? AND project_id = ?");
        $stmt->execute([$milestone_id, $project_id]);

        setModal("Success", "Milestone marked as completed");
    } catch (Exception $e) {
        setModal("Error", "Failed to update milestone: " . $e->getMessage());
    }

    header("Location: view.php?id=" . $project_id);
    exit;
}
?>
