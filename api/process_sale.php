<?php
// Disable all error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any accidental output
ob_start();

require_once '../config.php';
require_once '../auth.php';

// Clean any output that might have occurred
ob_clean();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userInfo = Auth::getUserInfo();

// Get raw input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

if (!$data || !isset($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items in cart']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();
    
    $customerId = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
    $customerName = isset($data['customer_name']) ? trim($data['customer_name']) : 'Walk-in Customer';
    $customerMobile = isset($data['customer_mobile']) ? trim($data['customer_mobile']) : '';
    $paymentType = isset($data['payment_type']) ? $data['payment_type'] : 'cash';
    $totalAmount = floatval($data['total_amount'] ?? 0);
    $discount = floatval($data['discount'] ?? 0);
    $netAmount = floatval($data['net_amount'] ?? 0);
    $userId = intval($userInfo['user_id']);
    
    // Insert sale record
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, user_id, payment_type, total_amount, discount, net_amount) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare sale statement: " . $conn->error);
    }
    
    $stmt->bind_param("iisddd", $customerId, $userId, $paymentType, $totalAmount, $discount, $netAmount);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create sale record: " . $stmt->error);
    }
    
    $saleId = $conn->insert_id;
    
    if (!$saleId) {
        throw new Exception("Failed to get sale ID");
    }
    
    // Insert sale items and update stock
    $stmtItem = $conn->prepare("INSERT INTO sale_items (sale_id, batch_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmtItem) {
        throw new Exception("Failed to prepare item statement: " . $conn->error);
    }
    
    $stmtStock = $conn->prepare("UPDATE product_batches SET quantity_in_stock = quantity_in_stock - ? WHERE batch_id = ?");
    
    if (!$stmtStock) {
        throw new Exception("Failed to prepare stock statement: " . $conn->error);
    }
    
    foreach ($data['items'] as $item) {
        $batchId = intval($item['batch_id']);
        $quantity = floatval($item['quantity']);
        $unit = isset($item['unit']) ? $item['unit'] : 'kg';
        $pricePerKg = floatval($item['price_per_kg'] ?? 0);
        
        // Calculate actual quantity in kg for stock deduction
        $quantityInKg = $quantity;
        if ($unit === 'g' || $unit === 'ml') {
            $quantityInKg = $quantity / 1000;
        } elseif ($unit === 'bottle') {
            $quantityInKg = $quantity; // Assume 1 bottle = 1 kg
        }
        
        // Calculate unit price based on unit
        $unitPrice = $pricePerKg;
        if ($unit === 'g' || $unit === 'ml') {
            $unitPrice = $pricePerKg / 1000;
        }
        
        $totalPrice = $quantity * $unitPrice;
        
        // Check if enough stock
        $checkStock = $conn->prepare("SELECT quantity_in_stock FROM product_batches WHERE batch_id = ?");
        if (!$checkStock) {
            throw new Exception("Failed to prepare stock check: " . $conn->error);
        }
        
        $checkStock->bind_param("i", $batchId);
        
        if (!$checkStock->execute()) {
            throw new Exception("Failed to check stock: " . $checkStock->error);
        }
        
        $stockResult = $checkStock->get_result();
        $stockRow = $stockResult->fetch_assoc();
        
        if (!$stockRow || floatval($stockRow['quantity_in_stock']) < $quantityInKg) {
            throw new Exception("Insufficient stock for batch ID: " . $batchId);
        }
        
        // Insert sale item
        $stmtItem->bind_param("iiddd", $saleId, $batchId, $quantity, $unitPrice, $totalPrice);
        
        if (!$stmtItem->execute()) {
            throw new Exception("Failed to add sale item: " . $stmtItem->error);
        }
        
        // Update stock (deduct in kg)
        $stmtStock->bind_param("di", $quantityInKg, $batchId);
        
        if (!$stmtStock->execute()) {
            throw new Exception("Failed to update stock: " . $stmtStock->error);
        }
    }
    
    $conn->commit();
    
    // Clear any remaining output buffer
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sale completed successfully',
        'sale_id' => $saleId
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Clear any remaining output buffer
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffering and send
ob_end_flush();
?>