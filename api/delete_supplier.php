<?php
// api/delete_supplier.php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$supplierId = $data['supplier_id'] ?? 0;

$conn = getDBConnection();

// Check if supplier has purchases
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM purchases WHERE supplier_id = ?");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete supplier with existing purchase records']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplierId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete supplier']);
}
?>