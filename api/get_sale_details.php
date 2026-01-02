<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$saleId = $_GET['id'] ?? 0;
$conn = getDBConnection();

// Get sale details
$stmt = $conn->prepare("SELECT 
    s.*,
    c.name as customer_name,
    c.contact_no,
    c.address,
    u.full_name as user_name
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.customer_id
LEFT JOIN users u ON s.user_id = u.user_id
WHERE s.sale_id = ?");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$saleResult = $stmt->get_result();

if ($saleResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found']);
    exit;
}

$sale = $saleResult->fetch_assoc();

// Get sale items
$stmt = $conn->prepare("SELECT 
    si.*,
    p.product_name,
    p.generic_name,
    pb.batch_no
FROM sale_items si
JOIN product_batches pb ON si.batch_id = pb.batch_id
JOIN products p ON pb.product_id = p.product_id
WHERE si.sale_id = ?");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$itemsResult = $stmt->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}

echo json_encode([
    'success' => true,
    'sale' => $sale,
    'items' => $items
]);
?>