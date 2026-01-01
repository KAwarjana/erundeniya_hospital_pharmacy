<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

// Set header for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, log them instead

// Check if user is logged in and has proper role
if (!AuthManager::isLoggedIn() || !in_array($_SESSION['role'], ['Admin', 'Receptionist'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON data from request
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Log request for debugging
error_log("Search request received: " . $rawData);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

try {
    Database::setUpConnection();
    
    if (!Database::$connection) {
        throw new Exception('Database connection failed');
    }
    
    // Sanitize input
    $searchTerm = isset($data['search']) ? Database::$connection->real_escape_string(trim($data['search'])) : '';
    $statusFilter = isset($data['status']) && $data['status'] !== 'all' 
        ? Database::$connection->real_escape_string($data['status']) 
        : '';
    $dateFilter = isset($data['date']) && !empty($data['date']) 
        ? Database::$connection->real_escape_string($data['date']) 
        : '';
    
    // Pagination parameters
    $recordsPerPage = 10;
    $currentPage = isset($data['page']) ? (int)$data['page'] : 1;
    $currentPage = max(1, $currentPage); // Ensure page is at least 1
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    // Build the base query conditions
    $baseConditions = " WHERE 1=1";
    
    // Add search term if provided
    if ($searchTerm) {
        $baseConditions .= " AND (
            a.appointment_number LIKE '%$searchTerm%' 
            OR p.name LIKE '%$searchTerm%' 
            OR p.mobile LIKE '%$searchTerm%'
        )";
    }
    
    // Add status filter if provided
    if ($statusFilter) {
        $baseConditions .= " AND a.status = '$statusFilter'";
    }
    
    // Add date filter if provided
    if ($dateFilter) {
        $baseConditions .= " AND ts.slot_date = '$dateFilter'";
    }
    
    // First, get total count for pagination
    $countQuery = "SELECT COUNT(*) as total 
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN time_slots ts ON a.slot_id = ts.id" . $baseConditions;
    
    $countResult = Database::search($countQuery);
    $countRow = $countResult->fetch_assoc();
    $totalRecords = $countRow['total'] ?? 0;
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Build the main search query with pagination
    $query = "SELECT 
        a.*, 
        p.title as patient_title, 
        p.name as patient_name, 
        p.mobile as patient_mobile, 
        p.email as patient_email,
        ts.slot_time,
        ts.slot_date,
        ts.day_of_week
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN time_slots ts ON a.slot_id = ts.id" . $baseConditions;
    
    $query .= " ORDER BY ts.slot_date DESC, ts.slot_time DESC";
    $query .= " LIMIT $recordsPerPage OFFSET $offset";
    
    // Execute query
    $result = Database::search($query);
    
    if (!$result) {
        throw new Exception('Query execution failed: ' . Database::$connection->error);
    }
    
    // Fetch appointments
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    // Get statistics for the filtered results
    $today = date('Y-m-d');
    
    $statsQuery = "SELECT 
        COUNT(CASE WHEN ts.slot_date = '$today' THEN 1 END) as today_count,
        COUNT(CASE WHEN a.status = 'Confirmed' THEN 1 END) as confirmed_count,
        COUNT(CASE WHEN a.status = 'Attended' THEN 1 END) as attended_count,
        COUNT(CASE WHEN a.status = 'No-Show' THEN 1 END) as no_show_count,
        COUNT(CASE WHEN a.status = 'Booked' THEN 1 END) as pending_count
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN time_slots ts ON a.slot_id = ts.id" . $baseConditions;
    
    $statsResult = Database::search($statsQuery);
    $statsRow = $statsResult ? $statsResult->fetch_assoc() : [];
    
    // Prepare response
    $response = [
        'success' => true,
        'appointments' => $appointments,
        'statistics' => [
            'today_count' => $statsRow['today_count'] ?? 0,
            'confirmed_count' => $statsRow['confirmed_count'] ?? 0,
            'attended_count' => $statsRow['attended_count'] ?? 0,
            'no_show_count' => $statsRow['no_show_count'] ?? 0,
            'pending_count' => $statsRow['pending_count'] ?? 0
        ],
        'pagination' => [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $recordsPerPage,
            'offset' => $offset
        ],
        'search_term' => $searchTerm,
        'result_count' => count($appointments),
        'total_records' => $totalRecords
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Search failed: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}
?>