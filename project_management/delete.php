<?php
include "../db.php";
include "../includes/dialog.php";

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    die("Invalid project ID");
}

// Verify project exists
$stmt = $pdo->prepare("SELECT project_no FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Project not found");
}

try {
    // Delete project (cascades to milestones and activities)
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);

    setModal("Success", "Project deleted successfully");
} catch (Exception $e) {
    setModal("Error", "Failed to delete project: " . $e->getMessage());
}

header("Location: index.php");
exit;
