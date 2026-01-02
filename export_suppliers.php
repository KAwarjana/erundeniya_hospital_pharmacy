<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get search parameter from URL
$searchTerm = $_GET['search'] ?? '';

// Build query with search
$sql = "SELECT 
    s.*,
    COUNT(p.purchase_id) as total_purchases,
    COALESCE(SUM(p.total_amount), 0) as total_purchased
FROM suppliers s
LEFT JOIN purchases p ON s.supplier_id = p.supplier_id";

if (!empty($searchTerm)) {
    $sql .= " WHERE s.name LIKE ? OR s.contact_no LIKE ? OR s.email LIKE ?";
}

$sql .= " GROUP BY s.supplier_id ORDER BY s.name";

$stmt = $conn->prepare($sql);

if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
}

$stmt->execute();
$suppliers = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=suppliers_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Supplier ID',
    'Name',
    'Contact Number',
    'Email',
    'Address',
    'Total Purchases',
    'Total Amount (Rs.)'
]);

// Add data rows
while ($supplier = $suppliers->fetch_assoc()) {
    fputcsv($output, [
        $supplier['supplier_id'],
        $supplier['name'],
        $supplier['contact_no'] ?? '-',
        $supplier['email'] ?? '-',
        $supplier['address'] ?? '-',
        $supplier['total_purchases'],
        number_format($supplier['total_purchased'], 2)
    ]);
}

fclose($output);
exit;
?>