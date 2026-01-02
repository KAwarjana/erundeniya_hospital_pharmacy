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
    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_no, email, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $contactNo, $email, $address);
    $message = 'Supplier added successfully';
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save supplier']);
}
?>
