<?php
include "../db.php";
include "../includes/dialog.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid task ID");
}

// Get task
$stmt = $pdo->prepare("SELECT task_no, task_name FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Task not found");
}

try {
    // Delete related records (will cascade due to foreign keys, but doing explicitly for safety)
    $pdo->prepare("DELETE FROM task_comments WHERE task_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM task_checklist WHERE task_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM task_attachments WHERE task_id = ?")->execute([$id]);

    // Delete task
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);

    setModal("Success", "Task {$task['task_no']} deleted successfully!");
} catch (Exception $e) {
    setModal("Error", "Failed to delete task: " . $e->getMessage());
}

header("Location: index.php");
exit;
