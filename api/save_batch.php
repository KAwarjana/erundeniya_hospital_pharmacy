<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$batchId = $_POST['batch_id'] ?? 0;
$productId = $_POST['product_id'] ?? 0;
$batchNo = trim($_POST['batch_no'] ?? '');
$expiryDate = $_POST['expiry_date'] ?? '';
$costPrice = floatval($_POST['cost_price'] ?? 0);
$sellingPrice = floatval($_POST['selling_price'] ?? 0);
$quantity = intval($_POST['quantity_in_stock'] ?? 0);

// Validation
if (empty($productId) || empty($batchNo) || empty($expiryDate) || $costPrice <= 0 || $sellingPrice <= 0 || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'All fields are required and prices must be greater than 0']);
    exit;
}

// Validate expiry date is not in the past for new batches
if ($batchId == 0) {
    $today = date('Y-m-d');
    if ($expiryDate < $today) {
        echo json_encode(['success' => false, 'message' => 'Expiry date cannot be in the past']);
        exit;
    }
}

// Validate selling price is greater than cost price
if ($sellingPrice <= $costPrice) {
    echo json_encode(['success' => false, 'message' => 'Selling price should be greater than cost price']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();
    
    // Check if batch number already exists for different batch
    if ($batchId > 0) {
        $stmt = $conn->prepare("SELECT batch_id FROM product_batches WHERE batch_no = ? AND product_id = ? AND batch_id != ?");
        $stmt->bind_param("sii", $batchNo, $productId, $batchId);
    } else {
        $stmt = $conn->prepare("SELECT batch_id FROM product_batches WHERE batch_no = ? AND product_id = ?");
        $stmt->bind_param("si", $batchNo, $productId);
    }
    
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Batch number already exists for this product']);
        exit;
    }
    
    if ($batchId > 0) {
        // Update existing batch - FIXED: Removed extra space in bind_param
        $stmt = $conn->prepare("UPDATE product_batches 
                               SET product_id = ?, batch_no = ?, expiry_date = ?, cost_price = ?, selling_price = ?, quantity_in_stock = ? 
                               WHERE batch_id = ?");
        $stmt->bind_param("issdiii", $productId, $batchNo, $expiryDate, $costPrice, $sellingPrice, $quantity, $batchId);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Batch updated successfully']);
        } else {
            throw new Exception('Failed to update batch');
        }
    } else {
        // Insert new batch
        $stmt = $conn->prepare("INSERT INTO product_batches (product_id, batch_no, expiry_date, cost_price, selling_price, quantity_in_stock) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddi", $productId, $batchNo, $expiryDate, $costPrice, $sellingPrice, $quantity);
        
        if ($stmt->execute()) {
            $newBatchId = $conn->insert_id;
            
            // Create a purchase record (optional - for tracking)
            // You can enhance this to create proper purchase records with supplier info
            $userInfo = Auth::getUserInfo();
            $totalAmount = $costPrice * $quantity;
            
            $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, user_id, invoice_no, total_amount) 
                                   VALUES (NULL, ?, ?, ?)");
            $invoiceNo = 'AUTO-' . date('YmdHis');
            $stmt->bind_param("isd", $userInfo['user_id'], $invoiceNo, $totalAmount);
            $stmt->execute();
            
            $purchaseId = $conn->insert_id;
            
            // Create purchase item
            $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, batch_id, quantity, cost_price, total_cost) 
                                   VALUES (?, ?, ?, ?, ?)");
            $totalCost = $costPrice * $quantity;
            $stmt->bind_param("iiidd", $purchaseId, $newBatchId, $quantity, $costPrice, $totalCost);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Batch added successfully', 'batch_id' => $newBatchId]);
        } else {
            throw new Exception('Failed to add batch');
        }
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>