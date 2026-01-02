<?php
// api/get_customer.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$customerId = $_GET['id'] ?? 0;
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode(['success' => true, 'customer' => $result->fetch_assoc()]);
?>