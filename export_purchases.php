<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters from URL
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$supplierId = $_GET['supplier_id'] ?? '';

// Build query with filters
$sql = "SELECT 
    p.purchase_id,
    p.invoice_no,
    p.purchase_date,
    s.name as supplier_name,
    u.full_name as user_name,
    p.total_amount,
    COUNT(pi.purchase_item_id) as item_count
FROM purchases p
LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
LEFT JOIN users u ON p.user_id = u.user_id
LEFT JOIN purchase_items pi ON p.purchase_id = pi.purchase_id";

$whereConditions = [];
$params = [];
$types = "";

// Add date filter only if both dates are provided
if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(p.purchase_date) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    $types .= "ss";
}

if (!empty($supplierId)) {
    $whereConditions[] = "p.supplier_id = ?";
    $params[] = $supplierId;
    $types .= "i";
}

// Add WHERE clause if there are conditions
if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " GROUP BY p.purchase_id ORDER BY p.purchase_date DESC";

$stmt = $conn->prepare($sql);

// Bind parameters only if there are any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$purchases = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=purchases_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Purchase ID',
    'Invoice No',
    'Purchase Date',
    'Supplier',
    'Items Count',
    'Total Amount (Rs.)',
    'Purchased By'
]);

// Add data rows
while ($purchase = $purchases->fetch_assoc()) {
    fputcsv($output, [
        str_pad($purchase['purchase_id'], 5, '0', STR_PAD_LEFT),
        $purchase['invoice_no'] ?? '-',
        date('M d, Y h:i A', strtotime($purchase['purchase_date'])),
        $purchase['supplier_name'] ?? 'Unknown',
        $purchase['item_count'],
        number_format($purchase['total_amount'], 2),
        $purchase['user_name']
    ]);
}

fclose($output);
exit;
?>