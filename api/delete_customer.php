<?php
// api/delete_customer.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$customerId = $data['customer_id'] ?? 0;

$conn = getDBConnection();

// Check if customer has sales
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete customer with existing sales records']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
}
?>