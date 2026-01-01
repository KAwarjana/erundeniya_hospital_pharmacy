<?php
/* ----------  guards & auth  ---------- */
require_once 'page_guards.php';
PageGuards::guardAppointments();
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

header('Content-Type: application/json');

try {
    Database::setUpConnection();
    $conn = Database::$connection;
    
    $currentUser = AuthManager::getCurrentUser();
    
    // Get the JSON data from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }
    
    $billId = $input['bill_id'] ?? null;
    $doctorFee = $input['doctor_fee'] ?? null;
    $medicineCost = $input['medicine_cost'] ?? null;
    $otherCharges = $input['other_charges'] ?? null;
    $discountAmount = $input['discount_amount'] ?? null;
    $discountPercentage = $input['discount_percentage'] ?? null;
    $discountReason = $input['discount_reason'] ?? '';
    $totalAmount = $input['total_amount'] ?? null;
    
    // Validation
    if (!$billId) {
        echo json_encode(['success' => false, 'message' => 'Bill ID is required']);
        exit;
    }
    
    if ($doctorFee === null || $totalAmount === null) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }
    
    // Check if bill exists
    $checkQuery = "SELECT id FROM bills WHERE id = '$billId'";
    $checkResult = Database::search($checkQuery);
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bill not found']);
        exit;
    }
    
    // Escape string values for safety
    $doctorFee = $conn->real_escape_string($doctorFee);
    $medicineCost = $conn->real_escape_string($medicineCost);
    $otherCharges = $conn->real_escape_string($otherCharges);
    $discountAmount = $conn->real_escape_string($discountAmount);
    $discountPercentage = $conn->real_escape_string($discountPercentage);
    $discountReason = $conn->real_escape_string($discountReason);
    $totalAmount = $conn->real_escape_string($totalAmount);
    
    // Update the bill
    $updateQuery = "UPDATE bills SET 
                    doctor_fee = '$doctorFee', 
                    medicine_cost = '$medicineCost', 
                    other_charges = '$otherCharges', 
                    discount_amount = '$discountAmount', 
                    discount_percentage = '$discountPercentage', 
                    discount_reason = '$discountReason', 
                    total_amount = '$totalAmount'
                    WHERE id = '$billId'";
    
    Database::iud($updateQuery);
    
    echo json_encode([
        'success' => true,
        'message' => 'Bill updated successfully',
        'bill_id' => $billId
    ]);
    
} catch (Exception $e) {
    error_log("Bill update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating bill: ' . $e->getMessage()]);
    exit;
}
?>