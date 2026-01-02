<?php
// api/add_user.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$roleId = intval($_POST['role_id'] ?? 0);

$result = Auth::register($username, $password, $fullName, $email, $roleId);
echo json_encode($result);
?>