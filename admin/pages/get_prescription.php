<?php
require_once '../../connection/connection.php';
require_once 'auth_manager.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Prescription ID required']);
    exit;
}

try {
    Database::setUpConnection();
    
    $id = (int)$_GET['id'];
    
    // Updated query to handle both appointment-based and direct patient prescriptions
    $query = "SELECT p.*, 
              a.appointment_number, a.appointment_date, a.appointment_time,
              pt.title, pt.name, pt.mobile, pt.registration_number
              FROM prescriptions p 
              INNER JOIN patient pt ON p.patient_id = pt.id 
              LEFT JOIN appointment a ON p.appointment_id = a.id 
              WHERE p.id = $id";
    
    $result = Database::search($query);
    
    if ($result && $result->num_rows > 0) {
        $prescription = $result->fetch_assoc();
        echo json_encode(['success' => true, 'prescription' => $prescription]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Prescription not found']);
    }
    
} catch (Exception $e) {
    error_log("Error getting prescription: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>