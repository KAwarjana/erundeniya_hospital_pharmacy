<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

// Check authentication
if (!AuthManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    // Get patient ID from query parameter
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Patient ID is required');
    }
    
    $patientId = intval($_GET['id']);
    
    // Database connection
    Database::setUpConnection();
    
    // Query to get patient details with visit information
    $query = "SELECT p.*, 
              (SELECT COUNT(*) FROM appointment WHERE patient_id = p.id) as total_visits,
              (SELECT MAX(appointment_date) FROM appointment WHERE patient_id = p.id) as last_visit
              FROM patient p 
              WHERE p.id = $patientId";
    
    $result = Database::search($query);
    
    if ($result->num_rows === 0) {
        throw new Exception('Patient not found');
    }
    
    $patient = $result->fetch_assoc();
    
    // Extract illnesses from medical_notes if stored there
    $illnesses = '';
    if ($patient['medical_notes']) {
        // Check if medical notes contain "Medical Conditions:" prefix
        if (strpos($patient['medical_notes'], 'Medical Conditions:') !== false) {
            $parts = explode("\n", $patient['medical_notes']);
            foreach ($parts as $part) {
                if (strpos($part, 'Medical Conditions:') !== false) {
                    $illnesses = str_replace('Medical Conditions:', '', $part);
                    $illnesses = trim($illnesses);
                    break;
                }
            }
            
            // Extract additional notes
            if (strpos($patient['medical_notes'], 'Additional Notes:') !== false) {
                $noteParts = explode('Additional Notes:', $patient['medical_notes']);
                if (isset($noteParts[1])) {
                    $patient['medical_notes'] = trim($noteParts[1]);
                }
            }
        }
    }
    
    // Add illnesses to patient data
    $patient['illnesses'] = $illnesses;
    
    // Format dates
    if ($patient['last_visit']) {
        $patient['last_visit'] = date('F j, Y', strtotime($patient['last_visit']));
    }
    
    if ($patient['created_at']) {
        $patient['created_at'] = date('Y-m-d H:i:s', strtotime($patient['created_at']));
    }
    
    echo json_encode([
        'success' => true,
        'patient' => $patient
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_patient.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>