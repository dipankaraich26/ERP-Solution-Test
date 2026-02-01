<?php
include "../db.php";

header('Content-Type: application/json');

$state_name = $_GET['state'] ?? null;
$state_id = $_GET['state_id'] ?? null;

if (!$state_name && !$state_id) {
    echo json_encode(['error' => 'State not specified', 'cities' => []]);
    exit;
}

try {
    if ($state_id) {
        // Get by state ID
        $stmt = $pdo->prepare("
            SELECT id, city_name
            FROM india_cities
            WHERE state_id = ?
            ORDER BY city_name
        ");
        $stmt->execute([$state_id]);
    } else {
        // Get by state name
        $stmt = $pdo->prepare("
            SELECT ic.id, ic.city_name
            FROM india_cities ic
            JOIN india_states is_table ON ic.state_id = is_table.id
            WHERE is_table.state_name = ?
            ORDER BY ic.city_name
        ");
        $stmt->execute([$state_name]);
    }

    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cities);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'cities' => []]);
}
