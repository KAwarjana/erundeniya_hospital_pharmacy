<?php
// api/add_role.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roleName = trim($data['role_name'] ?? '');

$conn = getDBConnection();
$stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
$stmt->bind_param("s", $roleName);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Role added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add role']);
}
?>