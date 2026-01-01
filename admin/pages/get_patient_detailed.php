<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

// Check authentication
if (!AuthManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Patient ID is required');
    }
    
    $patientId = intval($_GET['id']);
    Database::setUpConnection();
    
    // Get patient details with visit count and last visit
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
    
    // Parse medical notes and illnesses
    $illnesses = '';
    $medicalNotes = $patient['medical_notes'] ?? '';
    
    if ($medicalNotes) {
        if (strpos($medicalNotes, 'Medical Conditions:') !== false) {
            $parts = explode("\n", $medicalNotes);
            $notesArray = [];
            
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (strpos($trimmed, 'Medical Conditions:') !== false) {
                    $illnesses = str_replace('Medical Conditions:', '', $trimmed);
                    $illnesses = trim($illnesses);
                } elseif (strpos($trimmed, 'Additional Notes:') !== false || 
                         (!empty($notesArray) && !empty($trimmed))) {
                    if (strpos($trimmed, 'Additional Notes:') === false) {
                        $notesArray[] = $trimmed;
                    }
                }
            }
            $medicalNotes = implode("\n", $notesArray);
        }
    }
    
    $patient['illnesses'] = $illnesses;
    $patient['additional_notes'] = $medicalNotes;
    
    // Get appointment history (last 10)
    $appointmentsQuery = "SELECT id, appointment_number, appointment_date, appointment_time, 
                         status, payment_status, total_amount
                         FROM appointment 
                         WHERE patient_id = $patientId 
                         ORDER BY appointment_date DESC 
                         LIMIT 10";
    
    $appointmentsResult = Database::search($appointmentsQuery);
    $appointments = [];
    while ($row = $appointmentsResult->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    // Get prescription history (last 5)
    $prescriptionsQuery = "SELECT id, appointment_id, created_at, prescription_text
                          FROM prescriptions 
                          WHERE patient_id = $patientId 
                          ORDER BY created_at DESC 
                          LIMIT 5";
    
    $prescriptionsResult = Database::search($prescriptionsQuery);
    $prescriptions = [];
    while ($row = $prescriptionsResult->fetch_assoc()) {
        $prescriptions[] = $row;
    }
    
    // Get bills history (last 10)
    $billsQuery = "SELECT id, bill_number, created_at, total_amount, payment_status
                   FROM bills 
                   WHERE appointment_id IN (SELECT id FROM appointment WHERE patient_id = $patientId)
                   ORDER BY created_at DESC 
                   LIMIT 10";
    
    $billsResult = Database::search($billsQuery);
    $bills = [];
    while ($row = $billsResult->fetch_assoc()) {
        $bills[] = $row;
    }
    
    // Get treatment bills
    $treatmentBillsQuery = "SELECT id, bill_number, created_at, total_amount, final_amount, payment_status
                            FROM treatment_bills 
                            WHERE patient_id = $patientId 
                            ORDER BY created_at DESC 
                            LIMIT 10";
    
    $treatmentBillsResult = Database::search($treatmentBillsQuery);
    $treatmentBills = [];
    while ($row = $treatmentBillsResult->fetch_assoc()) {
        $treatmentBills[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'appointments' => $appointments,
        'prescriptions' => $prescriptions,
        'bills' => $bills,
        'treatment_bills' => $treatmentBills
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_patient_detailed.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>