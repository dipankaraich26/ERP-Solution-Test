<?php
include "../db.php";

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, state_name, state_code
        FROM states
        WHERE is_active = 1
        ORDER BY state_name
    ");
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'states' => $states]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'states' => []]);
}
