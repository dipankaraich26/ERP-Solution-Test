<?php
include "../db.php";

header('Content-Type: application/json');

$state_name = $_GET['state'] ?? null;

if (!$state_name) {
    echo json_encode(['error' => 'State not specified']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT ic.id, ic.city_name
        FROM india_cities ic
        JOIN india_states s ON ic.state_id = s.id
        WHERE s.state_name = ?
        ORDER BY ic.city_name
    ");
    $stmt->execute([$state_name]);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cities);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
