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
            FROM cities
            WHERE state_id = ? AND is_active = 1
            ORDER BY city_name
        ");
        $stmt->execute([$state_id]);
    } else {
        // Get by state name
        $stmt = $pdo->prepare("
            SELECT c.id, c.city_name
            FROM cities c
            JOIN states s ON c.state_id = s.id
            WHERE s.state_name = ? AND c.is_active = 1
            ORDER BY c.city_name
        ");
        $stmt->execute([$state_name]);
    }

    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'cities' => $cities]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'cities' => []]);
}
