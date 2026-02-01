<?php
/**
 * API: Search employees for user management
 * Returns employees with their full names for autocomplete/search
 */
header('Content-Type: application/json');
require_once '../db.php';

$search = trim($_GET['q'] ?? '');

try {
    if (strlen($search) < 1) {
        // Return first 20 employees if no search term
        $stmt = $pdo->query("
            SELECT id, emp_id, first_name, last_name, email, phone, department
            FROM employees
            WHERE status = 'Active'
            ORDER BY first_name, last_name
            LIMIT 20
        ");
    } else {
        // Search by name or emp_id
        $stmt = $pdo->prepare("
            SELECT id, emp_id, first_name, last_name, email, phone, department
            FROM employees
            WHERE status = 'Active'
              AND (
                  CONCAT(first_name, ' ', last_name) LIKE ?
                  OR first_name LIKE ?
                  OR last_name LIKE ?
                  OR emp_id LIKE ?
              )
            ORDER BY first_name, last_name
            LIMIT 20
        ");
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $results = [];
    foreach ($employees as $emp) {
        $results[] = [
            'id' => $emp['id'],
            'emp_id' => $emp['emp_id'],
            'full_name' => trim($emp['first_name'] . ' ' . $emp['last_name']),
            'first_name' => $emp['first_name'],
            'last_name' => $emp['last_name'],
            'email' => $emp['email'],
            'phone' => $emp['phone'],
            'department' => $emp['department'],
            'display' => $emp['emp_id'] . ' - ' . trim($emp['first_name'] . ' ' . $emp['last_name']) . ($emp['department'] ? ' (' . $emp['department'] . ')' : '')
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
