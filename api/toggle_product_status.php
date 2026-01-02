<?php
require_once '../auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? null;
    $status = $input['status'] ?? null;
    
    if (!$productId || !$status) {
        throw new Exception('Product ID and status are required');
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status value');
    }
    
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE product_id = ?");
    $checkStmt->bind_param("i", $productId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Product not found');
    }
    
    $product = $result->fetch_assoc();
    
    // Update product status
    $updateStmt = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
    $updateStmt->bind_param("si", $status, $productId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update product status: ' . $conn->error);
    }
    
    $statusText = $status === 'active' ? 'activated' : 'deactivated';
    
    echo json_encode([
        'success' => true,
        'message' => "Product '{$product['product_name']}' has been {$statusText} successfully"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>