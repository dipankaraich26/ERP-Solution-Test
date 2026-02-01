<?php
include "../db.php";

header('Content-Type: application/json');

$lead_id = $_GET['lead_id'] ?? null;

if (!$lead_id) {
    echo json_encode(['error' => 'Lead ID not specified']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, lead_no, company_name, contact_person, phone, email,
               address1, address2, city, state, pincode, country, lead_status
        FROM crm_leads
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        echo json_encode(['error' => 'Lead not found']);
        exit;
    }

    // Also try to find matching customer_id if it exists
    $customerStmt = $pdo->prepare("
        SELECT customer_id
        FROM customers
        WHERE contact = ?
        AND address1 = ?
        LIMIT 1
    ");
    $customerStmt->execute([
        $lead['phone'],
        $lead['address1']
    ]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    // Get product requirements for this lead
    $requirements = [];
    try {
        $reqStmt = $pdo->prepare("
            SELECT
                lr.id,
                lr.part_no,
                lr.product_name,
                lr.estimated_qty,
                lr.unit,
                lr.target_price,
                lr.priority,
                p.part_name,
                p.hsn_code,
                p.uom,
                p.rate,
                p.gst
            FROM crm_lead_requirements lr
            LEFT JOIN part_master p ON lr.part_no = p.part_no
            WHERE lr.lead_id = ?
            ORDER BY lr.id
        ");
        $reqStmt->execute([$lead_id]);
        $requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist - ignore error
    }

    // Return lead data with customer_id and requirements
    $response = array_merge($lead, [
        'customer_id' => $customer['customer_id'] ?? null,
        'requirements' => $requirements,
        'success' => true
    ]);
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
