<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$fullName = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$userInfo = Auth::getUserInfo();

// Validation
if (empty($fullName)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required']);
    exit;
}

// If email is provided, validate format
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Set email to NULL if empty
$emailValue = !empty($email) ? $email : null;

$conn = getDBConnection();
$stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
$stmt->bind_param("ssi", $fullName, $emailValue, $userInfo['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
?>