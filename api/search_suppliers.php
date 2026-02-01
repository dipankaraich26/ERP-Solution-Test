<?php
/**
 * API: Search Suppliers
 * Returns suppliers matching the search query
 */

header('Content-Type: application/json');

include "../db.php";

$query = trim($_GET['q'] ?? '');
$limit = min(20, max(5, intval($_GET['limit'] ?? 15)));

try {
    if ($query === '') {
        // Return first N suppliers when no query
        $stmt = $pdo->prepare("
            SELECT id, supplier_code, supplier_name, city, state, phone
            FROM suppliers
            ORDER BY supplier_name
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Search by code, name, city
        $searchTerm = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT id, supplier_code, supplier_name, city, state, phone
            FROM suppliers
            WHERE supplier_code LIKE :q1
               OR supplier_name LIKE :q2
               OR city LIKE :q3
            ORDER BY
                CASE
                    WHEN supplier_code LIKE :exact1 THEN 1
                    WHEN supplier_name LIKE :exact2 THEN 2
                    ELSE 3
                END,
                supplier_name
            LIMIT :limit
        ");
        $exactTerm = $query . '%';
        $stmt->bindValue(':q1', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':q2', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':q3', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':exact1', $exactTerm, PDO::PARAM_STR);
        $stmt->bindValue(':exact2', $exactTerm, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers,
        'count' => count($suppliers)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
