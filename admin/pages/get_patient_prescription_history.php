<?php
require_once '../../connection/connection.php';
require_once 'auth_manager.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID required']);
    exit;
}

try {
    Database::setUpConnection();
    
    $patientId = (int)$_GET['id'];
    
    // Get last 5 prescriptions for this patient
    $query = "SELECT p.id, p.created_at, p.appointment_id,
              a.appointment_number
              FROM prescriptions p
              LEFT JOIN appointment a ON p.appointment_id = a.id
              WHERE p.patient_id = $patientId
              ORDER BY p.created_at DESC
              LIMIT 5";
    
    $result = Database::search($query);
    
    $prescriptions = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $prescriptions[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'prescriptions' => $prescriptions
    ]);
    
} catch (Exception $e) {
    error_log("Error getting prescription history: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>