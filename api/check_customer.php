<?php
include "../db.php";

header('Content-Type: application/json');

$contact = $_GET['contact'] ?? null;

if (!$contact) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT customer_id, customer_name FROM customers WHERE contact = ?");
    $stmt->execute([$contact]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        echo json_encode([
            'exists' => true,
            'customer_id' => $customer['customer_id'],
            'customer_name' => $customer['customer_name']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
