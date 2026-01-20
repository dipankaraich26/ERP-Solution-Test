<?php
include "../db.php";

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT id, catalog_code, catalog_name, model_no, brochure_path, status
        FROM marketing_catalogs
        WHERE status = 'Active' AND brochure_path IS NOT NULL AND brochure_path != ''
        ORDER BY catalog_name
    ");

    $catalogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($catalogs);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
