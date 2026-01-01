<?php
require_once '../../connection/connection.php';
require_once 'auth_manager.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['prescription_text'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    Database::setUpConnection();
    
    $id = (int)$input['id'];
    $prescription_text = Database::$connection->real_escape_string($input['prescription_text']);
    
    $query = "UPDATE prescriptions SET prescription_text = '$prescription_text' WHERE id = $id";
    
    $result = Database::iud($query);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update prescription']);
    }
    
} catch (Exception $e) {
    error_log("Error updating prescription: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>