<?php
// Start output buffering to catch any errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require connection file
$connectionPath = '../../connection/connection.php';
if (!file_exists($connectionPath)) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection file not found']);
    exit;
}

require_once $connectionPath;

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    // Get POST data
    $billId = isset($_POST['bill_id']) ? intval($_POST['bill_id']) : 0;
    $patientId = !empty($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
    $patientName = isset($_POST['patient_name']) ? trim($_POST['patient_name']) : '';
    $patientMobile = isset($_POST['patient_mobile']) ? trim($_POST['patient_mobile']) : '';
    $treatmentsJson = isset($_POST['treatments']) ? $_POST['treatments'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $totalAmount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $discountPercentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
    $discountReason = isset($_POST['discount_reason']) ? trim($_POST['discount_reason']) : '';
    $paymentStatus = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'Pending';
    
    // Debug log
    error_log("Payment Status received: " . $paymentStatus);

    // Validate required fields
    if ($billId <= 0) {
        throw new Exception('Invalid bill ID');
    }

    if (empty($patientName)) {
        throw new Exception('Patient name required');
    }

    if (empty($patientMobile)) {
        throw new Exception('Patient mobile required');
    }

    if (empty($treatmentsJson)) {
        throw new Exception('Treatments data required');
    }
    
    // Validate payment status
    $validStatuses = ['Pending', 'Paid', 'Partial'];
    if (!in_array($paymentStatus, $validStatuses)) {
        $paymentStatus = 'Pending';
    }

    // Validate treatments JSON
    $treatments = json_decode($treatmentsJson, true);
    if (!is_array($treatments) || count($treatments) === 0) {
        throw new Exception('Invalid treatments data');
    }

    // Calculate amounts
    $discountAmount = ($totalAmount * $discountPercentage) / 100;
    $finalAmount = $totalAmount - $discountAmount;

    // Setup database connection
    Database::setUpConnection();

    if (!Database::$connection) {
        throw new Exception('Database connection failed');
    }

    // Build and execute update query
    $sql = "UPDATE treatment_bills 
            SET patient_id = ?, 
                patient_name = ?, 
                patient_mobile = ?, 
                treatments_data = ?, 
                total_amount = ?, 
                discount_percentage = ?, 
                discount_amount = ?, 
                discount_reason = ?, 
                final_amount = ?, 
                notes = ?,
                payment_status = ?, 
                updated_by = ?, 
                updated_at = NOW() 
            WHERE id = ?";

    $stmt = Database::$connection->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . Database::$connection->error);
    }

    // Get user ID
    $userId = intval($_SESSION['user_id']);

    // Bind parameters (now 13 parameters with payment_status)
    $stmt->bind_param(
        'isssdddsdssii',
        $patientId,
        $patientName,
        $patientMobile,
        $treatmentsJson,
        $totalAmount,
        $discountPercentage,
        $discountAmount,
        $discountReason,
        $finalAmount,
        $notes,
        $paymentStatus,
        $userId,
        $billId
    );

    // Execute
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    // Clear any buffered output and send success
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Bill updated successfully',
        'affected_rows' => $affectedRows
    ]);

} catch (Exception $e) {
    // Clear buffer and send error
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Flush output
ob_end_flush();
?>