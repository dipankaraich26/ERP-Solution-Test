<?php
include "../db.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Get all templates
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM whatsapp_templates ORDER BY template_name");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($templates);
    exit;
}

// Save new template
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? null;
    $content = $_POST['content'] ?? null;

    if (!$name || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Template name and content are required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_templates (template_name, template_content)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE template_content = ?, updated_at = NOW()
        ");
        $stmt->execute([$name, $content, $content]);
        echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save template: ' . $e->getMessage()]);
    }
    exit;
}

// Delete template
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Template ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM whatsapp_templates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete template: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
?>
