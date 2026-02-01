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
    $task_name = trim($_POST['task_name'] ?? '');
    $task_start_date = trim($_POST['task_start_date'] ?? '');
    $task_end_date = trim($_POST['task_end_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $remark = trim($_POST['remark'] ?? '');

    $errors = [];

    if (empty($task_name)) {
        $errors[] = "Task name is required";
    }

    if (!empty($task_start_date) && !empty($task_end_date)) {
        if (strtotime($task_end_date) < strtotime($task_start_date)) {
            $errors[] = "End date must be after start date";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO project_tasks
                (project_id, task_name, task_start_date, task_end_date, status, assigned_to, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $project_id,
                $task_name,
                !empty($task_start_date) ? $task_start_date : null,
                !empty($task_end_date) ? $task_end_date : null,
                $status,
                !empty($assigned_to) ? $assigned_to : null,
                !empty($remark) ? $remark : null
            ]);

            setModal("Success", "Task added successfully");
            header("Location: view.php?id=" . $project_id);
            exit;
        } catch (Exception $e) {
            setModal("Error", "Failed to add task: " . $e->getMessage());
        }
    } else {
        setModal("Error", implode(", ", $errors));
        header("Location: view.php?id=" . $project_id);
        exit;
    }
} elseif ($action === 'delete') {
    $task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$task_id) {
        die("Invalid task ID");
    }

    // Verify task belongs to this project
    $task_check = $pdo->prepare("SELECT id FROM project_tasks WHERE id = ? AND project_id = ?");
    $task_check->execute([$task_id, $project_id]);
    if (!$task_check->fetch()) {
        die("Task not found");
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE id = ?");
        $stmt->execute([$task_id]);

        setModal("Success", "Task deleted successfully");
    } catch (Exception $e) {
        setModal("Error", "Failed to delete task: " . $e->getMessage());
    }

    header("Location: view.php?id=" . $project_id);
    exit;
}
?>
