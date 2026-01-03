<?php
// api/save_customer.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$customerId = $_POST['customer_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$contactNo = trim($_POST['contact_no'] ?? '');
$address = trim($_POST['address'] ?? '');
$creditLimit = floatval($_POST['credit_limit'] ?? 0);

$conn = getDBConnection();

if ($customerId > 0) {
    $stmt = $conn->prepare("UPDATE customers SET name = ?, contact_no = ?, address = ?, credit_limit = ? WHERE customer_id = ?");
    $stmt->bind_param("sssdi", $name, $contactNo, $address, $creditLimit, $customerId);
    $message = 'Customer updated successfully';
} else {
    // Get next display_id for new customer
    $result = $conn->query("SELECT COALESCE(MAX(display_id), 0) + 1 as next_display_id FROM customers");
    $nextDisplayId = $result->fetch_assoc()['next_display_id'];
    
    $stmt = $conn->prepare("INSERT INTO customers (name, contact_no, address, credit_limit, display_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdi", $name, $contactNo, $address, $creditLimit, $nextDisplayId);
    $message = 'Customer added successfully';
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save customer']);
}
?>