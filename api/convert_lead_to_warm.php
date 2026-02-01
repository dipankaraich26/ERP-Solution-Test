<?php
/**
 * API: Convert Cold Lead to Warm
 * Used when creating a quotation for a cold lead
 */

header('Content-Type: application/json');

include "../db.php";

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$leadId = intval($input['lead_id'] ?? 0);

if (!$leadId) {
    echo json_encode(['success' => false, 'error' => 'Invalid lead ID']);
    exit;
}

try {
    // Check if lead exists and is cold
    $stmt = $pdo->prepare("SELECT id, lead_no, lead_status FROM crm_leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        echo json_encode(['success' => false, 'error' => 'Lead not found']);
        exit;
    }

    if (strtolower($lead['lead_status']) !== 'cold') {
        echo json_encode(['success' => false, 'error' => 'Lead is not in Cold status']);
        exit;
    }

    // Update lead status to warm
    $updateStmt = $pdo->prepare("UPDATE crm_leads SET lead_status = 'warm' WHERE id = ?");
    $updateStmt->execute([$leadId]);

    echo json_encode([
        'success' => true,
        'message' => 'Lead converted to Warm successfully',
        'lead_no' => $lead['lead_no']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
