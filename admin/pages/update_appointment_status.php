<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

header('Content-Type: application/json');

// Check if user is logged in and has proper role
if (!AuthManager::isLoggedIn() || !in_array($_SESSION['role'], ['Admin', 'Receptionist'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['appointment_number']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$appointmentNumber = $data['appointment_number'];
$newStatus = $data['status'];

// Validate status
$validStatuses = ['Booked', 'Confirmed', 'Attended', 'No-Show', 'Cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    Database::setUpConnection();
    
    // FIXED: Removed updated_at column that doesn't exist in the table
    $updateQuery = "UPDATE appointment SET status = ? WHERE appointment_number = ?";
    
    $stmt = Database::$connection->prepare($updateQuery);
    $stmt->bind_param("ss", $newStatus, $appointmentNumber);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Log the status change
            $userId = $_SESSION['user_id'] ?? null;
            $logQuery = "INSERT INTO notifications (title, message, type, user_id, created_at) VALUES (?, ?, 'system', ?, NOW())";
            $logStmt = Database::$connection->prepare($logQuery);
            
            $title = "Appointment Status Updated";
            $message = "Appointment $appointmentNumber status changed to $newStatus by " . $_SESSION['username'];
            $logStmt->bind_param("ssi", $title, $message, $userId);
            $logStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Appointment not found or no changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Appointment status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>