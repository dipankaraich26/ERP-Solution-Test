<?php
include "../db.php";
include "../includes/dialog.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    // Fetch lead info for message
    $stmt = $pdo->prepare("SELECT lead_no FROM crm_leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lead) {
        // Delete lead (cascade will handle requirements and interactions)
        $pdo->prepare("DELETE FROM crm_leads WHERE id = ?")->execute([$id]);

        setModal("Success", "Lead " . $lead['lead_no'] . " has been deleted.");
    } else {
        setModal("Error", "Lead not found.");
    }
}

header("Location: index.php");
exit;
