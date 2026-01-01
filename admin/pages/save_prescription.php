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

if (!$input || !isset($input['prescription_text']) || !isset($input['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data - Patient ID and prescription text are required']);
    exit;
}

try {
    Database::setUpConnection();
    
    $patient_id = (int)$input['patient_id'];
    $appointment_id = isset($input['appointment_id']) ? (int)$input['appointment_id'] : null;
    $prescription_text = Database::$connection->real_escape_string($input['prescription_text']);
    $created_by = (int)($input['created_by'] ?? $_SESSION['user_id'] ?? 1);
    
    // Validate patient exists
    $patientCheck = Database::search("SELECT id FROM patient WHERE id = $patient_id");
    if ($patientCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }
    
    // Generate prescription number
    $prescription_number = 'PRES' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Build query based on whether appointment_id is provided
    if ($appointment_id) {
        $query = "INSERT INTO prescriptions (appointment_id, patient_id, prescription_text, created_by, created_at) 
                  VALUES ($appointment_id, $patient_id, '$prescription_text', $created_by, NOW())";
    } else {
        $query = "INSERT INTO prescriptions (patient_id, prescription_text, created_by, created_at) 
                  VALUES ($patient_id, '$prescription_text', $created_by, NOW())";
    }
    
    $result = Database::iud($query);
    
    if ($result) {
        $prescription_id = Database::$connection->insert_id;
        echo json_encode([
            'success' => true, 
            'prescription_number' => $prescription_number,
            'prescription_id' => $prescription_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save prescription']);
    }
    
} catch (Exception $e) {
    error_log("Error saving prescription: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>