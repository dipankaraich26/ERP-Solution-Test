<?php
/**
 * Authentication & Authorization Middleware
 * Include this file at the top of protected pages
 *
 * Usage:
 *   include "includes/auth.php";
 *   requireLogin();                    // Just require login
 *   requirePermission('quotes', 'view');  // Check specific permission
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function getUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user's ID
 */
function getUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's full name
 */
function getUserName(): string {
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
}

/**
 * Check if current user has a specific permission
 *
 * @param string $module Module name (e.g., 'quotes', 'customers')
 * @param string $action Action type: 'view', 'create', 'edit', 'delete'
 * @return bool
 */
function hasPermission(string $module, string $action = 'view'): bool {
    if (!isLoggedIn()) return false;

    $role = getUserRole();

    // Admin has all permissions
    if ($role === 'admin') return true;

    global $pdo;

    switch ($action) {
        case 'create':
            $column = 'can_create';
            break;
        case 'edit':
            $column = 'can_edit';
            break;
        case 'delete':
            $column = 'can_delete';
            break;
        default:
            $column = 'can_view';
    }

    $stmt = $pdo->prepare("
        SELECT $column FROM role_permissions
        WHERE role = ? AND module = ?
    ");
    $stmt->execute([$role, $module]);
    $result = $stmt->fetchColumn();

    return (bool)$result;
}

/**
 * Require a specific permission - show error or redirect if not authorized
 */
function requirePermission(string $module, string $action = 'view'): void {
    requireLogin();

    if (!hasPermission($module, $action)) {
        // Log unauthorized access attempt
        global $pdo;
        $pdo->prepare("
            INSERT INTO activity_log (user_id, action, module, details, ip_address)
            VALUES (?, 'unauthorized_access', ?, ?, ?)
        ")->execute([
            getUserId(),
            $module,
            "Attempted $action without permission",
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        http_response_code(403);
        include __DIR__ . "/403.php";
        exit;
    }
}

/**
 * Log an activity
 */
function logActivity(string $action, string $module, ?int $recordId = null, ?string $details = null): void {
    if (!isLoggedIn()) return;

    global $pdo;
    $pdo->prepare("
        INSERT INTO activity_log (user_id, action, module, record_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        getUserId(),
        $action,
        $module,
        $recordId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}

/**
 * Get company settings (cached in session for performance)
 */
function getCompanySettings(): array {
    if (!isset($_SESSION['company_settings'])) {
        global $pdo;
        $_SESSION['company_settings'] = $pdo->query(
            "SELECT * FROM company_settings WHERE id = 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $_SESSION['company_settings'];
}

/**
 * Clear cached company settings (call after updating settings)
 */
function clearCompanySettingsCache(): void {
    unset($_SESSION['company_settings']);
}
