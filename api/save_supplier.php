<?php
// api/save_supplier.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$supplierId = $_POST['supplier_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$contactNo = trim($_POST['contact_no'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');

$conn = getDBConnection();

if ($supplierId > 0) {
    $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_no = ?, email = ?, address = ? WHERE supplier_id = ?");
    $stmt->bind_param("ssssi", $name, $contactNo, $email, $address, $supplierId);
    $message = 'Supplier updated successfully';
} else {
    // Get next display_id for new supplier
    $result = $conn->query("SELECT COALESCE(MAX(display_id), 0) + 1 as next_display_id FROM suppliers");
    $nextDisplayId = $result->fetch_assoc()['next_display_id'];
    
    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_no, email, address, display_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $contactNo, $email, $address, $nextDisplayId);
    $message = 'Supplier added successfully';
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save supplier']);
}
?>