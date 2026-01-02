<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get all product batches with product info
$batches = $conn->query("SELECT 
    p.product_id,
    p.product_name,
    p.generic_name,
    p.unit,
    p.reorder_level,
    pb.batch_id,
    pb.batch_no,
    pb.expiry_date,
    pb.cost_price,
    pb.selling_price,
    pb.quantity_in_stock,
    DATEDIFF(pb.expiry_date, CURDATE()) as days_to_expiry
FROM product_batches pb
JOIN products p ON pb.product_id = p.product_id
ORDER BY p.product_name, pb.expiry_date");

// Set headers for CSV download
$filename = "stock_report_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add header row
fputcsv($output, [
    'Product ID',
    'Product Name',
    'Generic Name',
    'Unit',
    'Batch Number',
    'Expiry Date',
    'Days to Expiry',
    'Cost Price (Rs.)',
    'Selling Price (Rs.)',
    'Stock Quantity',
    'Reorder Level',
    'Stock Value (Rs.)',
    'Status'
]);

// Add data rows
$totalStockValue = 0;
$lowStockItems = 0;
$expiringSoon = 0;

while ($batch = $batches->fetch_assoc()) {
    $stockValue = floatval($batch['quantity_in_stock']) * floatval($batch['cost_price']);
    $totalStockValue += $stockValue;
    
    // Determine status
    $status = 'Good';
    if ($batch['quantity_in_stock'] == 0) {
        $status = 'Out of Stock';
    } elseif ($batch['quantity_in_stock'] <= $batch['reorder_level']) {
        $status = 'Low Stock';
        $lowStockItems++;
    }
    
    if ($batch['days_to_expiry'] < 0) {
        $status = 'Expired';
    } elseif ($batch['days_to_expiry'] <= 30) {
        $status = 'Expiring Soon';
        $expiringSoon++;
    } elseif ($batch['days_to_expiry'] <= 90) {
        $status = 'Near Expiry';
    }
    
    fputcsv($output, [
        $batch['product_id'],
        $batch['product_name'],
        $batch['generic_name'] ?? '-',
        $batch['unit'] ?? '-',
        $batch['batch_no'],
        date('Y-m-d', strtotime($batch['expiry_date'])),
        $batch['days_to_expiry'],
        number_format($batch['cost_price'], 2, '.', ''),
        number_format($batch['selling_price'], 2, '.', ''),
        $batch['quantity_in_stock'],
        $batch['reorder_level'],
        number_format($stockValue, 2, '.', ''),
        $status
    ]);
}

// Add summary rows
fputcsv($output, []);
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Stock Value:', 'Rs. ' . number_format($totalStockValue, 2)]);
fputcsv($output, ['Low Stock Items:', $lowStockItems]);
fputcsv($output, ['Items Expiring Soon (30 days):', $expiringSoon]);
fputcsv($output, []);
fputcsv($output, ['Report Generated:', date('Y-m-d h:i A')]);

fclose($output);
exit;
?>