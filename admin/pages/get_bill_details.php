<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../connection/connection.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Only GET allowed']);
    exit;
}

try {
    // Get bill number from query parameter
    $billNumber = isset($_GET['bill_number']) ? trim($_GET['bill_number']) : '';

    if (empty($billNumber)) {
        throw new Exception('Bill number is required');
    }

    // Setup database connection
    Database::setUpConnection();

    if (!Database::$connection) {
        throw new Exception('Database connection failed');
    }

    // Prepare query to get bill details with user information
    $sql = "SELECT tb.*, 
                   u.user_name as created_by_name,
                   up.user_name as updated_by_name
            FROM treatment_bills tb 
            LEFT JOIN user u ON tb.created_by = u.id 
            LEFT JOIN user up ON tb.updated_by = up.id 
            WHERE tb.bill_number = ?";

    $stmt = Database::$connection->prepare($sql);

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . Database::$connection->error);
    }

    $stmt->bind_param('s', $billNumber);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Bill not found with number: ' . $billNumber);
    }

    $bill = $result->fetch_assoc();
    $stmt->close();

    // Decode treatments data
    $bill['treatments_data'] = json_decode($bill['treatments_data'], true);

    if (!$bill['treatments_data']) {
        $bill['treatments_data'] = [];
    }

    // Format dates for display
    if (isset($bill['created_at'])) {
        $bill['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($bill['created_at']));
    }

    if (isset($bill['updated_at']) && $bill['updated_at']) {
        $bill['updated_at_formatted'] = date('Y-m-d H:i:s', strtotime($bill['updated_at']));
    }

    // Clean output and send success response
    ob_clean();
    echo json_encode([
        'success' => true,
        'bill' => $bill
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
?>