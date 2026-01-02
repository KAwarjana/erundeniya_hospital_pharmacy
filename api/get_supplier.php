<?php
// api/get_supplier.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$supplierId = $_GET['id'] ?? 0;
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode(['success' => true, 'supplier' => $result->fetch_assoc()]);
?>