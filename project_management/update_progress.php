<?php
include "../db.php";
include "../includes/dialog.php";

$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$progress = isset($_POST['progress_percentage']) ? (int)$_POST['progress_percentage'] : 0;

if (!$project_id) {
    die("Invalid project ID");
}

// Validate progress
$progress = max(0, min(100, $progress));

try {
    // Check if progress_percentage column exists
    $hasColumn = true;
    try {
        $pdo->query("SELECT progress_percentage FROM projects LIMIT 1");
    } catch (PDOException $e) {
        $hasColumn = false;
    }

    if ($hasColumn) {
        $stmt = $pdo->prepare("UPDATE projects SET progress_percentage = ? WHERE id = ?");
        $stmt->execute([$progress, $project_id]);
        setModal("Success", "Progress updated to {$progress}%");
    } else {
        setModal("Error", "Progress column not found. Please run admin/setup_project_management.php first.");
    }
} catch (Exception $e) {
    setModal("Error", "Failed to update progress: " . $e->getMessage());
}

header("Location: view.php?id=" . $project_id);
exit;
?>
