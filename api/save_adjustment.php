<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$batchId = $_POST['batch_id'] ?? 0;
$adjustmentType = $_POST['adjustment_type'] ?? '';
$quantity = intval($_POST['quantity'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$userInfo = Auth::getUserInfo();

// Validation
if (empty($batchId) || empty($adjustmentType) || $quantity <= 0 || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required and quantity must be greater than 0']);
    exit;
}

if (!in_array($adjustmentType, ['increase', 'decrease'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid adjustment type']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();
    
    // Get current stock
    $stmt = $conn->prepare("SELECT quantity_in_stock, product_id FROM product_batches WHERE batch_id = ?");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Batch not found');
    }
    
    $batch = $result->fetch_assoc();
    $currentStock = $batch['quantity_in_stock'];
    
    // Check if decrease would result in negative stock
    if ($adjustmentType === 'decrease' && $quantity > $currentStock) {
        throw new Exception('Cannot decrease stock by ' . $quantity . '. Current stock is only ' . $currentStock);
    }
    
    // Insert adjustment record
    $stmt = $conn->prepare("INSERT INTO stock_adjustments (batch_id, user_id, adjustment_type, quantity, reason) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisis", $batchId, $userInfo['user_id'], $adjustmentType, $quantity, $reason);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record adjustment');
    }
    
    // Update stock quantity
    if ($adjustmentType === 'increase') {
        $stmt = $conn->prepare("UPDATE product_batches SET quantity_in_stock = quantity_in_stock + ? WHERE batch_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE product_batches SET quantity_in_stock = quantity_in_stock - ? WHERE batch_id = ?");
    }
    
    $stmt->bind_param("ii", $quantity, $batchId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update stock');
    }
    
    // Get updated stock
    $stmt = $conn->prepare("SELECT quantity_in_stock FROM product_batches WHERE batch_id = ?");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $updatedStock = $stmt->get_result()->fetch_assoc()['quantity_in_stock'];
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Stock adjusted successfully. Previous: ' . $currentStock . ', New: ' . $updatedStock,
        'previous_stock' => $currentStock,
        'new_stock' => $updatedStock
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>