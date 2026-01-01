<?php
// get_current_user.php - API endpoint to get current user information
require_once 'auth_manager.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    if (AuthManager::isLoggedIn()) {
        $user = AuthManager::getCurrentUser();
        $permissions = AuthManager::getRolePermissions($user['role']);
        
        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'role' => $user['role'],
            'user_id' => $user['id'],
            'permissions' => $permissions,
            'session_active' => true,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'username' => null,
            'role' => null,
            'user_id' => null,
            'permissions' => [],
            'session_active' => false,
            'message' => 'No active session'
        ]);
    }
} catch (Exception $e) {
    // Log error but don't expose sensitive information
    error_log("Get current user error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'session_active' => false
    ]);
}
?>