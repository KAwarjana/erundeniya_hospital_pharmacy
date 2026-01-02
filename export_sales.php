<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$paymentType = $_GET['payment_type'] ?? '';

$sql = "SELECT 
    s.sale_id,
    s.sale_date,
    s.payment_type,
    s.total_amount,
    s.discount,
    s.net_amount,
    c.name as customer_name,
    c.contact_no,
    u.full_name as user_name,
    COUNT(si.sale_item_id) as item_count
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.customer_id
LEFT JOIN users u ON s.user_id = u.user_id
LEFT JOIN sale_items si ON s.sale_id = si.sale_id
WHERE DATE(s.sale_date) BETWEEN ? AND ?";

$params = [$dateFrom, $dateTo];
$types = "ss";

if (!empty($paymentType)) {
    $sql .= " AND s.payment_type = ?";
    $params[] = $paymentType;
    $types .= "s";
}

$sql .= " GROUP BY s.sale_id ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();

// Set headers for CSV download
$filename = "sales_report_" . $dateFrom . "_to_" . $dateTo . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add header row
fputcsv($output, [
    'Sale ID',
    'Date',
    'Time',
    'Customer Name',
    'Contact Number',
    'Items Count',
    'Subtotal (Rs.)',
    'Discount (Rs.)',
    'Net Amount (Rs.)',
    'Payment Type',
    'Cashier'
]);

// Add data rows
$totalSales = 0;
$totalDiscount = 0;
$totalRevenue = 0;

while ($sale = $sales->fetch_assoc()) {
    $totalSales++;
    $totalDiscount += floatval($sale['discount']);
    $totalRevenue += floatval($sale['net_amount']);
    
    fputcsv($output, [
        str_pad($sale['sale_id'], 5, '0', STR_PAD_LEFT),
        date('Y-m-d', strtotime($sale['sale_date'])),
        date('h:i A', strtotime($sale['sale_date'])),
        $sale['customer_name'] ?? 'Walk-in Customer',
        $sale['contact_no'] ?? '-',
        $sale['item_count'],
        number_format($sale['total_amount'], 2, '.', ''),
        number_format($sale['discount'], 2, '.', ''),
        number_format($sale['net_amount'], 2, '.', ''),
        strtoupper($sale['payment_type']),
        $sale['user_name']
    ]);
}

// Add summary rows
fputcsv($output, []);
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Sales:', $totalSales]);
fputcsv($output, ['Total Discount:', 'Rs. ' . number_format($totalDiscount, 2)]);
fputcsv($output, ['Total Revenue:', 'Rs. ' . number_format($totalRevenue, 2)]);
fputcsv($output, []);
fputcsv($output, ['Report Generated:', date('Y-m-d h:i A')]);
fputcsv($output, ['Date Range:', $dateFrom . ' to ' . $dateTo]);

fclose($output);
exit;
?>