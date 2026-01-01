<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

/* ----------  auth  ---------- */
if (!AuthManager::isLoggedIn() || !in_array($_SESSION['role'], ['Admin','Receptionist'])) {
    http_response_code(403);
    exit('Unauthorized');
}

/* ----------  collect filters  ---------- */
$status = $_GET['status'] ?? 'all';
$date   = $_GET['date']   ?? '';
$search = $_GET['search'] ?? '';

/* ----------  connect  ---------- */
try {
    Database::setUpConnection();
    $conn = Database::$connection;
} catch (Exception $e) {
    header('Content-Type: text/plain');
    die('Database connection failed: '.$e->getMessage());
}

/* ----------  build query  ---------- */
$sql = "SELECT
            a.appointment_number,
            CONCAT(p.title, '. ', p.name) AS patient_name,
            p.mobile,
            p.email,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.payment_status,
            a.total_amount,
            a.channeling_fee,
            a.discount,
            a.booking_type,
            a.note,
            a.created_at
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        WHERE 1=1";

// Apply filters based on status
if ($status !== 'all') {
    // Handle different status formats (lowercase with hyphen vs proper case)
    $statusMap = [
        'booked' => 'Booked',
        'confirmed' => 'Confirmed',
        'attended' => 'Attended',
        'no-show' => 'No-Show',
        'cancelled' => 'Cancelled'
    ];
    $mappedStatus = $statusMap[strtolower($status)] ?? $status;
    $s = $conn->real_escape_string($mappedStatus);
    $sql .= " AND a.status='$s'";
}

if ($date) {
    $d = $conn->real_escape_string($date);
    $sql .= " AND a.appointment_date='$d'";
}

if ($search) {
    $q = $conn->real_escape_string($search);
    $sql .= " AND (a.appointment_number LIKE '%$q%' OR p.name LIKE '%$q%' OR p.mobile LIKE '%$q%')";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$result = $conn->query($sql);
if (!$result) {
    header('Content-Type: text/plain');
    die('Query failed: '.$conn->error);
}

/* ----------  check if we have data  ---------- */
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

/* ----------  headers – force download as .xls  ---------- */
$filename = "Appointments_" . date('Y-m-d_H-i-s') . ".xls";
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

/* ----------  begin HTML table (Excel accepts this)  ---------- */
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #4F81BD; color: white; font-weight: bold; padding: 10px; border: 1px solid #000; text-align: center; }';
echo 'td { border: 1px solid #CCCCCC; padding: 8px; }';
echo '.currency { text-align: right; }';
echo '</style>';
echo '</head>';
echo '<body>';
echo '<table border="1">';

/* ----------  headers  ---------- */
$headers = [
    'Appointment Number','Patient Name','Mobile','Email',
    'Date','Time','Status','Payment Status',
    'Total Amount','Channeling Fee','Discount',
    'Booking Type','Notes','Created At'
];

echo '<thead><tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr></thead>';
echo '<tbody>';

/* ----------  data rows  ---------- */
if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo '<tr>';
        
        // Appointment Number
        echo '<td>' . htmlspecialchars($row['appointment_number'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Patient Name
        echo '<td>' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Mobile
        echo '<td>' . htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Email
        $email = $row['email'] ? htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') : 'N/A';
        echo '<td>' . $email . '</td>';
        
        // Date
        echo '<td>' . date('Y-m-d', strtotime($row['appointment_date'])) . '</td>';
        
        // Time
        echo '<td>' . date('h:i A', strtotime($row['appointment_time'])) . '</td>';
        
        // Status
        echo '<td>' . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Payment Status
        echo '<td>' . htmlspecialchars($row['payment_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Total Amount
        echo '<td class="currency">Rs. ' . number_format($row['total_amount'], 2) . '</td>';
        
        // Channeling Fee
        echo '<td class="currency">Rs. ' . number_format($row['channeling_fee'], 2) . '</td>';
        
        // Discount
        echo '<td class="currency">Rs. ' . number_format($row['discount'], 2) . '</td>';
        
        // Booking Type
        echo '<td>' . htmlspecialchars($row['booking_type'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        // Notes
        $notes = $row['note'] ? htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') : '';
        echo '<td>' . $notes . '</td>';
        
        // Created At
        echo '<td>' . htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
        
        echo '</tr>';
    }
} else {
    // No data message
    echo '<tr>';
    echo '<td colspan="14" style="text-align:center; padding:20px;">No appointments found for the selected filters.</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>