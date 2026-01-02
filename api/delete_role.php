<?php
// api/delete_role.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roleId = $data['role_id'] ?? 0;

$conn = getDBConnection();

// Check if role is in use
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete role that is assigned to users']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
$stmt->bind_param("i", $roleId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete role']);
}
?>