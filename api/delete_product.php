<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();
    
    // Check if product is used in any sales
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sale_items si 
                           JOIN product_batches pb ON si.batch_id = pb.batch_id 
                           WHERE pb.product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete product with existing sales records']);
        exit;
    }
    
    // Check if product is used in any purchases
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM purchase_items pi 
                           JOIN product_batches pb ON pi.batch_id = pb.batch_id 
                           WHERE pb.product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete product with existing purchase records']);
        exit;
    }
    
    // Get all batch IDs for this product
    $stmt = $conn->prepare("SELECT batch_id FROM product_batches WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $batchesResult = $stmt->get_result();
    
    $batchIds = [];
    while ($row = $batchesResult->fetch_assoc()) {
        $batchIds[] = $row['batch_id'];
    }
    
    // Delete stock adjustments for all batches
    if (!empty($batchIds)) {
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $stmt = $conn->prepare("DELETE FROM stock_adjustments WHERE batch_id IN ($placeholders)");
        $types = str_repeat('i', count($batchIds));
        $stmt->bind_param($types, ...$batchIds);
        $stmt->execute();
    }
    
    // Delete product batches
    $stmt = $conn->prepare("DELETE FROM product_batches WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    
    // Delete the product
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    
    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Product and all associated batches deleted successfully']);
    } else {
        throw new Exception('Failed to delete product');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>