<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters
$stockStatus = $_GET['stock_status'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build the query with filters
$sql = "SELECT 
    p.product_id,
    p.product_name,
    p.generic_name,
    p.unit,
    p.reorder_level,
    COALESCE(SUM(pb.quantity_in_stock), 0) as total_stock,
    COUNT(pb.batch_id) as batch_count,
    COALESCE(MIN(pb.cost_price), 0) as min_cost_price,
    COALESCE(MAX(pb.cost_price), 0) as max_cost_price,
    COALESCE(MIN(pb.selling_price), 0) as min_selling_price,
    COALESCE(MAX(pb.selling_price), 0) as max_selling_price,
    COALESCE(MIN(pb.expiry_date), NULL) as earliest_expiry
FROM products p
LEFT JOIN product_batches pb ON p.product_id = pb.product_id";

$whereClauses = [];
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $whereClauses[] = "(p.product_name LIKE ? OR p.generic_name LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY p.product_id";

// Apply stock status filter after grouping
if ($stockStatus === 'out_of_stock') {
    $sql .= " HAVING total_stock = 0";
} elseif ($stockStatus === 'low_stock') {
    $sql .= " HAVING total_stock > 0 AND total_stock <= p.reorder_level";
} elseif ($stockStatus === 'in_stock') {
    $sql .= " HAVING total_stock > p.reorder_level";
}

$sql .= " ORDER BY p.product_name";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Set headers for CSV download
$filename = "products_list_" . date('Y-m-d_His') . ".csv";
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
    'Total Stock',
    'Reorder Level',
    'Batch Count',
    'Min Cost Price (Rs.)',
    'Max Cost Price (Rs.)',
    'Min Selling Price (Rs.)',
    'Max Selling Price (Rs.)',
    'Earliest Expiry Date',
    'Stock Status'
]);

// Add data rows
$totalProducts = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

while ($product = $products->fetch_assoc()) {
    $totalProducts++;
    
    // Determine stock status
    $stockStatus = 'In Stock';
    if ($product['total_stock'] == 0) {
        $stockStatus = 'Out of Stock';
        $outOfStockCount++;
    } elseif ($product['total_stock'] <= $product['reorder_level']) {
        $stockStatus = 'Low Stock';
        $lowStockCount++;
    }
    
    fputcsv($output, [
        $product['product_id'],
        $product['product_name'],
        $product['generic_name'] ?? '-',
        $product['unit'] ?? '-',
        $product['total_stock'],
        $product['reorder_level'],
        $product['batch_count'],
        number_format($product['min_cost_price'], 2, '.', ''),
        number_format($product['max_cost_price'], 2, '.', ''),
        number_format($product['min_selling_price'], 2, '.', ''),
        number_format($product['max_selling_price'], 2, '.', ''),
        $product['earliest_expiry'] ? date('Y-m-d', strtotime($product['earliest_expiry'])) : '-',
        $stockStatus
    ]);
}

// Add summary rows
fputcsv($output, []);
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Products:', $totalProducts]);
fputcsv($output, ['In Stock:', ($totalProducts - $outOfStockCount)]);
fputcsv($output, ['Low Stock:', $lowStockCount]);
fputcsv($output, ['Out of Stock:', $outOfStockCount]);
fputcsv($output, []);
fputcsv($output, ['Report Generated:', date('Y-m-d h:i A')]);
if (!empty($searchTerm)) {
    fputcsv($output, ['Search Filter:', $searchTerm]);
}

fclose($output);
exit;
?>