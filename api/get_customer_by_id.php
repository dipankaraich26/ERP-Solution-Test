<?php
include "../db.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(null);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, customer_id, company_name, customer_name, contact, email,
               address1, address2, city, pincode, state, gstin
        FROM customers
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($customer ?: null);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
