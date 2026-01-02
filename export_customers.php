<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get search parameter from URL
$searchTerm = $_GET['search'] ?? '';

// Build query with search
$sql = "SELECT 
    c.*,
    COUNT(DISTINCT s.sale_id) as total_sales,
    COALESCE(SUM(s.net_amount), 0) as total_spent
FROM customers c
LEFT JOIN sales s ON c.customer_id = s.customer_id";

if (!empty($searchTerm)) {
    $sql .= " WHERE c.name LIKE ? OR c.contact_no LIKE ?";
}

$sql .= " GROUP BY c.customer_id ORDER BY c.name";

$stmt = $conn->prepare($sql);

if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
}

$stmt->execute();
$customers = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=customers_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Customer ID',
    'Name',
    'Contact Number',
    'Address',
    'Credit Limit (Rs.)',
    'Total Sales',
    'Total Spent (Rs.)'
]);

// Add data rows
while ($customer = $customers->fetch_assoc()) {
    fputcsv($output, [
        $customer['customer_id'],
        $customer['name'],
        $customer['contact_no'],
        $customer['address'] ?? '-',
        number_format($customer['credit_limit'], 2),
        $customer['total_sales'],
        number_format($customer['total_spent'], 2)
    ]);
}

fclose($output);
exit;
?>