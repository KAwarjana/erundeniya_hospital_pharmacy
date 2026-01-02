<?php
require_once '../auth.php';
Auth::requireAuth();

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $batchId = $input['batch_id'] ?? null;
    
    if (!$batchId) {
        throw new Exception('Batch ID is required');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Check if batch exists
    $checkStmt = $conn->prepare("SELECT batch_id, quantity_in_stock FROM product_batches WHERE batch_id = ?");
    $checkStmt->bind_param("i", $batchId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Batch not found');
    }
    
    // Check if batch has been used in sales
    $salesCheck = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE batch_id = ?");
    $salesCheck->bind_param("i", $batchId);
    $salesCheck->execute();
    $salesResult = $salesCheck->get_result()->fetch_assoc();
    
    if ($salesResult['count'] > 0) {
        throw new Exception('Cannot delete batch: It has been used in sales transactions. You can only adjust stock to zero.');
    }
    
    // Check if batch has been used in purchases
    $purchaseCheck = $conn->prepare("SELECT COUNT(*) as count FROM purchase_items WHERE batch_id = ?");
    $purchaseCheck->bind_param("i", $batchId);
    $purchaseCheck->execute();
    $purchaseResult = $purchaseCheck->get_result()->fetch_assoc();
    
    if ($purchaseResult['count'] > 0) {
        throw new Exception('Cannot delete batch: It has been used in purchase transactions. You can only adjust stock to zero.');
    }
    
    // Check if batch has stock adjustments
    $adjustmentCheck = $conn->prepare("SELECT COUNT(*) as count FROM stock_adjustments WHERE batch_id = ?");
    $adjustmentCheck->bind_param("i", $batchId);
    $adjustmentCheck->execute();
    $adjustmentResult = $adjustmentCheck->get_result()->fetch_assoc();
    
    if ($adjustmentResult['count'] > 0) {
        // Delete stock adjustments first (they're just history records)
        $deleteAdjustments = $conn->prepare("DELETE FROM stock_adjustments WHERE batch_id = ?");
        $deleteAdjustments->bind_param("i", $batchId);
        $deleteAdjustments->execute();
    }
    
    // Now safe to delete the batch
    $deleteStmt = $conn->prepare("DELETE FROM product_batches WHERE batch_id = ?");
    $deleteStmt->bind_param("i", $batchId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete batch: ' . $conn->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Batch deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>