<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();

// ---------- Dynamic Sidebar (dashboard.php ekata daala) ----------
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

// Get current user info
$currentUser = AuthManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Define all menu items with their access permissions
$menuItems = [
    [
        'title' => 'Dashboard',
        'url' => 'dashboard.php',
        'icon' => 'dashboard',
        'allowed_roles' => ['Admin'],
        'show_to_all' => true
    ],
    [
        'title' => 'Appointments',
        'url' => 'appointments.php',
        'icon' => 'calendar_today',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Book Appointment',
        'url' => 'book_appointments.php',
        'icon' => 'add_circle',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Patients',
        'url' => 'patients.php',
        'icon' => 'people',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Bills',
        'url' => 'create_bill.php',
        'icon' => 'receipt',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Prescriptions',
        'url' => 'prescription.php',
        'icon' => 'medication',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'OPD Treatments',
        'url' => 'opd.php',
        'icon' => 'local_hospital',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Reports',
        'url' => 'reports.php',
        'icon' => 'assessment',
        'allowed_roles' => ['Admin'],
        'show_to_all' => true
    ]
];

function hasAccessToPage($allowedRoles)
{
    if (!AuthManager::isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $allowedRoles);
}

function renderSidebarMenu($menuItems, $currentPage)
{
    $currentRole = $_SESSION['role'] ?? 'Guest';

    foreach ($menuItems as $item) {
        $isActive = ($currentPage === $item['url']);
        $hasAccess = hasAccessToPage($item['allowed_roles']);

        if ($hasAccess) {
            $linkClass = $isActive ? 'nav-link active bg-gradient-dark text-white' : 'nav-link text-dark';
            $href = $item['url'];
            $onclick = '';
            $style = '';
            $tooltip = '';
        } else {
            $linkClass = 'nav-link text-muted';
            $href = '#';
            $onclick = 'event.preventDefault(); showAccessDenied(\'' . $item['title'] . '\');';
            $style = 'opacity: 0.6; cursor: default;';
            $tooltip = 'title="Access Restricted to Admin only" data-bs-toggle="tooltip"';
        }

        echo '<li class="nav-item mt-3">';
        echo '<a class="' . $linkClass . '" href="' . $href . '" onclick="' . $onclick . '" style="' . $style . '" ' . $tooltip . '>';
        echo '<i class="material-symbols-rounded opacity-5">' . $item['icon'] . '</i>';
        echo '<span class="nav-link-text ms-1">' . $item['title'];

        if (!$hasAccess) {
            echo ' <i class="fas fa-lock" style="font-size: 10px; margin-left: 5px;"></i>';
        }

        echo '</span>';
        echo '</a>';
        echo '</li>';
    }
}

// Fetch treatments from database
try {
    $treatments = [];
    $treatmentQuery = "SELECT id, treatment_name, price FROM treatments WHERE is_active = 1 ORDER BY treatment_name";
    $treatmentResult = Database::search($treatmentQuery);

    while ($row = $treatmentResult->fetch_assoc()) {
        $treatments[] = [
            'id' => $row['id'],
            'name' => $row['treatment_name'],
            'price' => floatval($row['price'])
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching treatments: " . $e->getMessage());
    $treatments = []; // Fallback to empty array
}

// Fetch patients for dropdown
try {
    $patients = [];
    $patientQuery = "SELECT id, registration_number, name, mobile FROM patient ORDER BY name";
    $patientResult = Database::search($patientQuery);

    while ($row = $patientResult->fetch_assoc()) {
        $patients[] = [
            'id' => $row['id'],
            'registration_number' => $row['registration_number'],
            'name' => $row['name'],
            'mobile' => $row['mobile']
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['action'] === 'save_bill') {
            $patientId = $_POST['patient_id'] ?? null;
            $patientName = $_POST['patient_name'] ?? '';
            $patientMobile = $_POST['patient_mobile'] ?? '';
            $treatmentsData = $_POST['treatments'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $totalAmount = $_POST['total_amount'] ?? 0;
            $discountPercentage = $_POST['discount_percentage'] ?? 0;
            $discountReason = $_POST['discount_reason'] ?? '';

            if (empty($patientName) || empty($patientMobile) || empty($treatmentsData)) {
                throw new Exception("Please fill all required fields");
            }

            // Decode treatments JSON
            $treatmentsArray = json_decode($treatmentsData, true);
            if (!$treatmentsArray || !is_array($treatmentsArray)) {
                throw new Exception("Invalid treatments data");
            }

            // Calculate discount
            $discountAmount = ($totalAmount * $discountPercentage) / 100;
            $finalAmount = $totalAmount - $discountAmount;

            // Generate bill number
            $billNumber = 'BILL' . date('YmdHis');

            // Prepare treatments data as JSON
            $treatmentsJson = json_encode($treatmentsArray);

            // Collect payment status
            $paymentStatus = $_POST['payment_status'] ?? 'Pending';

            // Insert bill
            $insertQuery = "INSERT INTO treatment_bills (
    bill_number, patient_id, patient_name, patient_mobile, 
    treatments_data, total_amount, discount_percentage, 
    discount_amount, discount_reason, final_amount, 
    notes, created_by, payment_status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = Database::$connection->prepare($insertQuery);
            $userId = $_SESSION['user_id'] ?? 1;
            $stmt->bind_param(
                "ssssssddsssis",
                $billNumber,
                $patientId,
                $patientName,
                $patientMobile,
                $treatmentsJson,
                $totalAmount,
                $discountPercentage,
                $discountAmount,
                $discountReason,
                $finalAmount,
                $notes,
                $userId,
                $paymentStatus
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to save bill: " . $stmt->error);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Treatment bill saved successfully!',
                'bill_number' => $billNumber
            ]);
        } elseif ($_POST['action'] === 'update_bill') {
            $billId = $_POST['bill_id'] ?? null;
            $patientId = $_POST['patient_id'] ?? null;
            $patientName = $_POST['patient_name'] ?? '';
            $patientMobile = $_POST['patient_mobile'] ?? '';
            $treatmentsData = $_POST['treatments'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $totalAmount = $_POST['total_amount'] ?? 0;
            $discountPercentage = $_POST['discount_percentage'] ?? 0;
            $discountReason = $_POST['discount_reason'] ?? '';

            if (!$billId || empty($patientName) || empty($patientMobile) || empty($treatmentsData)) {
                throw new Exception("Please fill all required fields");
            }

            // Decode treatments JSON
            $treatmentsArray = json_decode($treatmentsData, true);
            if (!$treatmentsArray || !is_array($treatmentsArray)) {
                throw new Exception("Invalid treatments data");
            }

            // Calculate discount
            $discountAmount = ($totalAmount * $discountPercentage) / 100;
            $finalAmount = $totalAmount - $discountAmount;

            // Prepare treatments data as JSON
            $treatmentsJson = json_encode($treatmentsArray);

            // Update bill
            $updateQuery = "UPDATE treatment_bills SET 
                patient_id = ?, patient_name = ?, patient_mobile = ?, 
                treatments_data = ?, total_amount = ?, discount_percentage = ?, 
                discount_amount = ?, discount_reason = ?, final_amount = ?, 
                notes = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?";

            $stmt = Database::$connection->prepare($updateQuery);
            $userId = $_SESSION['user_id'] ?? 1;
            $stmt->bind_param(
                "ssssddssdsii",
                $patientId,
                $patientName,
                $patientMobile,
                $treatmentsJson,
                $totalAmount,
                $discountPercentage,
                $discountAmount,
                $discountReason,
                $finalAmount,
                $notes,
                $userId,
                $billId
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update bill: " . $stmt->error);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Treatment bill updated successfully!'
            ]);
        } elseif ($_POST['action'] === 'delete_bill') {
            $billId = $_POST['bill_id'] ?? null;

            if (!$billId) {
                throw new Exception("Bill ID is required");
            }

            $deleteQuery = "DELETE FROM treatment_bills WHERE id = ?";
            $stmt = Database::$connection->prepare($deleteQuery);
            $stmt->bind_param("i", $billId);

            if (!$stmt->execute()) {
                throw new Exception("Failed to delete bill: " . $stmt->error);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Treatment bill deleted successfully!'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle GET requests for bill details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_bill') {
    header('Content-Type: application/json');

    try {
        if (!isset($_GET['bill_number'])) {
            throw new Exception("Bill number is required");
        }

        $billNumber = $_GET['bill_number'];
        $billQuery = "SELECT tb.*, u.user_name as created_by_name, 
                     up.user_name as updated_by_name
                     FROM treatment_bills tb 
                     LEFT JOIN user u ON tb.created_by = u.id 
                     LEFT JOIN user up ON tb.updated_by = up.id 
                     WHERE tb.bill_number = ?";

        $stmt = Database::$connection->prepare($billQuery);
        $stmt->bind_param("s", $billNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Bill not found");
        }

        $bill = $result->fetch_assoc();
        $bill['treatments_data'] = json_decode($bill['treatments_data'], true);

        echo json_encode([
            'success' => true,
            'bill' => $bill
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Enhanced patient search
try {
    $patients_search = [];
    $patientQuery = "SELECT id, registration_number, name, mobile FROM patient ORDER BY name";
    $patientResult = Database::search($patientQuery);

    while ($row = $patientResult->fetch_assoc()) {
        $patients_search[] = [
            'id' => $row['id'],
            'registration_number' => $row['registration_number'],
            'name' => $row['name'],
            'mobile' => $row['mobile']
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching patients for search: " . $e->getMessage());
    $patients_search = [];
}

// Fetch statistics - DYNAMIC
try {
    // Total treatments
    $totalQuery = "SELECT COUNT(*) as total FROM treatment_bills";
    $totalResult = Database::search($totalQuery);
    $totalTreatments = $totalResult->fetch_assoc()['total'];

    // Today's treatments
    $todayQuery = "SELECT COUNT(*) as today FROM treatment_bills WHERE DATE(created_at) = CURDATE()";
    $todayResult = Database::search($todayQuery);
    $todayTreatments = $todayResult->fetch_assoc()['today'];

    // This week's treatments
    $weekQuery = "SELECT COUNT(*) as week FROM treatment_bills WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $weekResult = Database::search($weekQuery);
    $weekTreatments = $weekResult->fetch_assoc()['week'];

    // This today's revenue
    $revenueQuery = "SELECT COALESCE(SUM(final_amount), 0) as revenue FROM treatment_bills WHERE DATE(created_at) = CURDATE()";
    $revenueResult = Database::search($revenueQuery);
    $monthRevenue = $revenueResult->fetch_assoc()['revenue'];
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $totalTreatments = 0;
    $todayTreatments = 0;
    $weekTreatments = 0;
    $monthRevenue = 0;
}

// Fetch existing bills - DYNAMIC
try {
    $billsQuery = "SELECT tb.*, 
                   DATE(tb.created_at) as bill_date,
                   TIME(tb.created_at) as bill_time,
                   u.user_name as created_by_name 
                   FROM treatment_bills tb 
                   LEFT JOIN user u ON tb.created_by = u.id 
                   ORDER BY tb.created_at DESC 
                   LIMIT 20";
    $billsResult = Database::search($billsQuery);
    $bills = [];

    while ($row = $billsResult->fetch_assoc()) {
        $row['treatments_data'] = json_decode($row['treatments_data'], true);
        $bills[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching bills: " . $e->getMessage());
    $bills = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <title>OPD Treatments Management - Erundeniya Ayurveda Hospital</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

    <style>
        .treatment-card {
            border-radius: 15px;
            background: linear-gradient(45deg, #c5c5c5ff, #d1d1d1ff);
        }

        .treatment-header {
            background: linear-gradient(45deg, #000000ff, #292929ff);
            color: white;
            padding: 15px;
            border-radius: 13px 13px 0 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            width: 95%;
            max-width: 900px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(45deg, #4CAF50, #3c8d40ff);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #ffb300);
            color: #212529;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(45deg, #ffb300, #ffa000);
            color: #212529;
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.4);
            transform: translateY(-1px);
        }

        .print-btn {
            background: #000000ff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .card--header--text {
            color: white;
        }

        .treatment-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .treatment-item.selected {
            border-color: #4CAF50;
            background: #f8fff8;
        }

        .treatment-info {
            flex: 1;
        }

        .treatment-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .treatment-price {
            color: #666;
            font-size: 14px;
        }

        .custom-price-input {
            width: 120px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }

        .bill-preview {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
        }

        .bill-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .patient-info {
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .treatment-list {
            min-height: 200px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .total-section {
            text-align: right;
            border-top: 2px solid #333;
            padding-top: 15px;
            margin-top: 20px;
            font-weight: bold;
            font-size: 18px;
        }

        .notification-badge {
            position: relative;
            background: #f44336;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            margin-top: -30px;
            margin-left: 10px;
            display: flex;
            flex-direction: row;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            margin: 0 5px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .selected-treatments {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            background: #fafafa;
        }

        .selected-treatment {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            background: white;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .selected-treatment:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .treatment-search {
            position: relative;
            margin-bottom: 15px;
        }

        .treatment-search input {
            padding-right: 40px;
        }

        .treatment-search .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .treatment-selection {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .treatment-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .treatment-row:last-child {
            margin-bottom: 0;
        }

        .treatment-dropdown {
            flex: 2;
            min-width: 200px;
        }

        .price-input {
            flex: 1;
            min-width: 120px;
        }

        .quantity-treatment-input {
            flex: 0.5;
            min-width: 80px;
            text-align: center;
        }

        .remove-btn {
            flex: 0;
            background: #dc3545;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }

        .add-treatment-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .price-input[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .custom-price-treatment {
            background-color: white !important;
            color: #212529 !important;
        }

        .discount-section {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 2px solid #81c784;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(129, 199, 132, 0.15);
        }

        .discount-row {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }

        .discount-col {
            flex: 1;
        }

        .discount-col label {
            color: #1b5e20;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .discount-col label i {
            font-size: 16px;
        }

        .discount-display {
            background: linear-gradient(135deg, #ffffff, #f1f8e9);
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 15px 20px;
            margin-top: 15px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .discount-badge,
        .final-amount-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
        }

        .discount-badge {
            background: linear-gradient(135deg, #ffeb3b, #ffc107);
            color: #795548;
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
        }

        .discount-badge i {
            font-size: 20px;
        }

        .final-amount-badge {
            background: linear-gradient(135deg, #4caf50, #388e3c);
            color: white;
            box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
        }

        .final-amount-badge i {
            font-size: 20px;
        }

        .discount-arrow {
            color: #4caf50;
            font-size: 24px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.7;
            }

            50% {
                transform: scale(1.1);
                opacity: 1;
            }
        }

        .discount-section input {
            background: white;
            border: 2px solid #a5d6a7;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .discount-section input:focus {
            outline: none;
            border-color: #4caf50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
            background: #ffffff;
        }

        .discount-section input:hover {
            border-color: #66bb6a;
        }

        #discountPercentage,
        #discountAmountInput {
            font-weight: 600;
            color: #2e7d32;
        }

        #discountReason {
            color: #424242;
        }

        .discount-section h6 {
            color: #1b5e20;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .discount-section h6 i {
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .treatment-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .treatment-item button {
                margin-top: 10px;
                width: 100%;
            }

            .treatment-row {
                flex-direction: column;
                align-items: stretch;
            }

            .treatment-dropdown,
            .price-input,
            .quantity-treatment-input {
                flex: none;
                width: 100%;
                margin-bottom: 10px;
            }

            .discount-row {
                flex-direction: column;
                gap: 10px;
            }

            .discount-col {
                width: 100%;
            }
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg  ' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .form-group label i.material-symbols-rounded {
            vertical-align: middle;
            margin-right: 5px;
            font-size: 18px;
        }

        .sidenav-footer .nav-link:hover {
            background-color: #ff001910 !important;
            color: #dc3545 !important;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidenav-footer .nav-link:hover .material-symbols-rounded,
        .sidenav-footer .nav-link:hover .nav-link-text {
            color: #dc3545 !important;
            opacity: 1 !important;
        }

        .date-input-wrapper-opd {
            border: 1px solid #d1d1d1ff;
            padding: 0 10px;
            border-radius: 5px;
        }

        .badge-success {
            background-color: #e8f5e8 !important;
            color: #2e7d32 !important;
            border-radius: 2rem;
        }

        .badge-warning {
            background-color: #fff3e0 !important;
            color: #f57c00 !important;
            border-radius: 2rem;
        }

        .badge-secondary {
            color: #f44336 !important;
            background-color: #ffebee;
            border-radius: 2rem;
        }

        /* NEW: Patient search dropdown styling - matches appointment search */
        .appointment-search-wrapper {
            position: relative;
        }

        #patientDropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .appointment-search-result {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .appointment-search-result:hover {
            background-color: #f5f5f5;
        }

        .appointment-search-result:last-child {
            border-bottom: none;
        }

        .appointment-result-number {
            font-weight: 600;
            color: #2196F3;
        }

        .appointment-result-patient {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        .appointment-result-date {
            font-size: 12px;
            color: #999;
            margin-top: 2px;
        }

        .no-results {
            padding: 12px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        .loading-results {
            padding: 12px;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #388e3c 0%, #2e7d32 100%);
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="<?php echo PageGuards::getHomePage(); ?>">
                <img src="../../img/logoblack.png" class="navbar-brand-img" width="40" height="50" alt="main_logo">
                <span class="ms-1 text-sm text-dark" style="font-weight: bold;">Erundeniya</span>
            </a>
        </div>
        <hr class="horizontal dark mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <?php renderSidebarMenu($menuItems, $currentPage); ?>
            </ul>
        </div>
        <div class="sidenav-footer">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="#" onclick="logout(); return false;">
                        <i class="material-symbols-rounded opacity-5">logout</i>
                        <span class="nav-link-text ms-1">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card" id="navbarBlur" data-scroll="true" style="background-color: white;">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-1 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.html">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm text-dark active">OPD Treatments</li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                        <!-- <div class="input-group input-group-outline">
                            <input type="text" class="form-control" placeholder="Search appointments..." id="globalSearch">
                        </div> -->
                    </div>
                    <ul class="navbar-nav d-flex align-items-center justify-content-end">
                        <li class="nav-item d-xl-none ps-3 d-flex align-items-center mt-1 me-3">
                            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                                <div class="sidenav-toggler-inner">
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item dropdown pe-3 d-flex align-items-center">
                            <a href="#" class="nav-link text-body p-0" onclick="toggleNotifications()">
                                <img src="../../img/bell.png" width="20" height="20">
                                <span class="notification-badge">3</span>
                            </a>
                        </li>
                        <li class="nav-item d-flex align-items-center">
                            <a href="#" class="nav-link text-body font-weight-bold px-0">
                                <img src="../../img/user.png" width="20" height="20">
                                &nbsp;<span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid py-2 mt-2">
            <div class="row">
                <div class="ms-3">
                    <h3 class="mb-0 h4 font-weight-bolder">OPD Treatments Management</h3>
                    <p class="mb-4">Manage patient treatments and generate treatment bills with discount options</p>
                </div>
            </div>

            <!-- Statistics Cards - DYNAMIC -->
            <div class="row">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Total Treatments</p>
                                    <h4 class="mb-0"><?php echo $totalTreatments; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">local_hospital</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Today's Treatments</p>
                                    <h4 class="mb-0"><?php echo $todayTreatments; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">today</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">This Week</p>
                                    <h4 class="mb-0"><?php echo $weekTreatments; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">calendar_month</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Revenue Today</p>
                                    <h4 class="mb-0">Rs. <?php echo number_format($monthRevenue, 2); ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">payments</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <!-- Create Treatment Bill Panel -->
                <div class="col-lg-12">
                    <div class="card treatment-card">
                        <div class="treatment-header">
                            <h5 class="mb-1 card--header--text">
                                <i class="material-symbols-rounded">local_hospital</i>
                                Create Treatment Bill
                            </h5>
                            <p class="mb-0 opacity-8">Generate bill for patient treatments with discount options</p>
                        </div>
                        <div class="card-body">
                            <form id="treatmentForm">
                                <input type="hidden" id="billId" value="">

                                <!-- Patient Selection -->
                                <div class="row">
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group appointment-search-wrapper">
                                            <label>
                                                <i class="material-symbols-rounded text-sm">search</i>
                                                Search Patient (Optional)
                                            </label>
                                            <div style="position: relative;">
                                                <input type="text"
                                                    id="patientSearch"
                                                    placeholder="Type patient name, mobile or registration number..."
                                                    oninput="searchPatients()"
                                                    onfocus="showPatientDropdown()"
                                                    autocomplete="off">
                                                <input type="hidden" id="patientSelect" value="">
                                                <div id="patientDropdown" class="appointment-search-results" style="display: none;">
                                                    <!-- Patient results will appear here -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <i class="material-symbols-rounded text-sm">person</i>
                                                Patient Name *
                                            </label>
                                            <input type="text" id="patientName" required placeholder="Enter patient name">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <i class="material-symbols-rounded text-sm">phone</i>
                                                Mobile Number *
                                            </label>
                                            <input type="text" id="patientMobile" required placeholder="Enter mobile number">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <label>
                                                <i class="material-symbols-rounded text-sm">payments</i>
                                                Payment Status
                                            </label>
                                            <select id="paymentStatus">
                                                <option value="Pending">Pending</option>
                                                <option value="Paid">Paid</option>
                                                <option value="Partial">Partial</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Treatment Selection with Dropdown System -->
                                <div class="form-group">
                                    <label>
                                        <i class="material-symbols-rounded text-sm">medical_services</i>
                                        Select Treatments
                                    </label>
                                    <div class="treatment-selection" id="treatmentSelection">
                                        <!-- Treatment rows will be added here -->
                                    </div>
                                    <button type="button" class="add-treatment-btn" onclick="addTreatmentRow()">
                                        <i class="material-symbols-rounded">add</i>
                                        Add Treatment
                                    </button>
                                </div>

                                <!-- Discount Section -->
                                <div class="discount-section">
                                    <h6><i class="material-symbols-rounded">local_offer</i> Discount Options</h6>
                                    <div class="discount-row">
                                        <div class="discount-col">
                                            <label><i class="material-symbols-rounded text-sm">payments</i> Discount Amount (Rs.)</label>
                                            <input type="number" id="discountAmountInput" min="0" step="0.01" value="0" oninput="calculateFromAmount()">
                                        </div>
                                        <div class="discount-col">
                                            <label><i class="material-symbols-rounded text-sm">percent</i> Discount Percentage (%)</label>
                                            <input type="number" id="discountPercentage" min="0" max="100" step="0.01" value="0" oninput="calculateFromPercentage()">
                                        </div>
                                        <div class="discount-col">
                                            <label><i class="material-symbols-rounded text-sm">description</i> Discount Reason</label>
                                            <input type="text" id="discountReason" placeholder="Enter discount reason">
                                        </div>
                                    </div>
                                    <div class="discount-display" id="discountDisplay" style="display: none;">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <div class="discount-badge">
                                                    <i class="material-symbols-rounded">sell</i>
                                                    <span id="discountAmountDisplay">Rs. 0.00</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <div class="discount-arrow">
                                                    <i class="material-symbols-rounded">arrow_forward</i>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="final-amount-badge">
                                                    <i class="material-symbols-rounded">check_circle</i>
                                                    <span id="finalAmount">Rs. 0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="form-group">
                                    <label>
                                        <i class="material-symbols-rounded text-sm">note</i>
                                        Notes (Optional)
                                    </label>
                                    <textarea id="treatmentNotes" rows="3" placeholder="Add any additional notes..."></textarea>
                                </div>

                                <!-- Total Amount Display -->
                                <div class="form-group">
                                    <div class="d-flex justify-content-between align-items-center p-3" style="background: #f8f9fa; border-radius: 8px;">
                                        <h6 class="mb-0">Total Amount:</h6>
                                        <h5 class="mb-0 text-success" id="totalAmount">Rs. 0.00</h5>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-6 mb-2">
                                        <button type="submit" class="btn-primary w-100" id="saveBtn">
                                            <i class="material-symbols-rounded">save</i>&nbsp;&nbsp;Save Bill
                                        </button>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-2">
                                        <button type="button" class="btn-primary w-100" id="updateBtn" style="display: none;" onclick="updateBill()">
                                            <i class="material-symbols-rounded">update</i>&nbsp;&nbsp;Update Bill
                                        </button>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-2">
                                        <button type="button" class="print-btn w-100" style="background: #000; min-height: 45px;" onclick="saveAndPrint()">
                                            <i class="material-symbols-rounded">print</i>&nbsp;&nbsp;Save & Print
                                        </button>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-2">
                                        <button type="button" class="btn-secondary w-100" onclick="previewBill()">
                                            <i class="material-symbols-rounded">visibility</i>&nbsp;&nbsp;Preview
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row mt-4">
                <!-- Treatment Bills List - DYNAMIC -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-0">Treatment Bills</h6>
                                </div>
                                <div class="col-md-3 mt-md-0 mt-3">
                                    <div class="input-group input-group-outline" style="position: relative;">
                                        <input type="text" class="form-control" placeholder="Search bills..." id="billSearch" style="padding-right: 35px;">
                                        <button type="button" onclick="clearBillSearch()" class="search-clear-btn-opd" style="position: absolute; right: 8px; top: 60%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; z-index: 10; display: none; padding: 4px;">
                                            <i class="material-symbols-rounded" style="font-size: 20px; color: #66666681;">close</i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-md-0 mt-2">
                                    <div class="date-input-wrapper-opd" style="position: relative; display: inline-block; width: 100%;">
                                        <input type="date" class="form-control" id="dateFilterOPD" onchange="filterByDateOPD()" placeholder="Filter by date">
                                        <button type="button" onclick="clearDateFilterOPD()" class="date-clear-btn-opd" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; border-radius: 50%; cursor: pointer; z-index: 5; display: none; width: 20px; height: 20px; padding: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                                            <i class="material-symbols-rounded" style="font-size: 14px; color: #666;">close</i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Bill Details</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Patient</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Amount</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Discount</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Final</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="billsTableBody">
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <h6 class="mb-0 text-sm font-weight-bold"><?php echo htmlspecialchars($bill['bill_number']); ?></h6>
                                                        <p class="text-xs text-secondary mb-0">
                                                            <?php echo date('Y-m-d', strtotime($bill['created_at'])); ?>
                                                            <span class="text-primary">@ <?php echo date('h:i A', strtotime($bill['created_at'])); ?></span>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-sm font-weight-bold"><?php echo htmlspecialchars($bill['patient_name']); ?></span>
                                                        <span class="text-xs text-secondary"><?php echo htmlspecialchars($bill['patient_mobile']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-sm font-weight-bold">Rs. <?php echo number_format($bill['total_amount'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($bill['discount_percentage'] > 0): ?>
                                                        <span class="text-sm text-success"><?php echo $bill['discount_percentage']; ?>%</span>
                                                        <br><small class="text-xs">Rs. <?php echo number_format($bill['discount_amount'], 2); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-sm text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="text-sm font-weight-bold text-success">Rs. <?php echo number_format($bill['final_amount'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($bill['payment_status']) {
                                                        case 'Paid':
                                                            $statusClass = 'badge badge-success';
                                                            break;
                                                        case 'Partial':
                                                            $statusClass = 'badge badge-warning';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge badge-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="<?php echo $statusClass; ?>"><?php echo $bill['payment_status']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-outline-success" onclick="viewBill('<?php echo $bill['bill_number']; ?>')">View</button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="editBill('<?php echo $bill['bill_number']; ?>')">Edit</button>
                                                        <button class="btn btn-sm btn-dark" onclick="printBill('<?php echo $bill['bill_number']; ?>')">Print</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <footer class="footer py-4">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-lg-between">
                    <div class="mb-lg-0 mb-4">
                        <div class="copyright text-center text-sm text-muted text-lg-start">
                            © <script>
                                document.write(new Date().getFullYear())
                            </script>,
                            design and develop by
                            <a href="#" class="font-weight-bold">Evon Technologies Software Solution (PVT) Ltd.</a>
                            All rights reserved.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- View Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card--header--text"><i class="material-symbols-rounded">receipt</i> <span id="modalTitle">View Treatment Bill</span></h4>
                <span class="close" onclick="closeBillModal()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div class="bill-preview" id="billPreview">
                    <div class="bill-header">
                        <h2>Erundeniya Ayurveda Hospital</h2>
                        <p>OPD Treatment Bill</p>
                        <p>Contact: +94 71 291 9408 | Email: info@erundeniyaayurveda.lk</p>
                    </div>

                    <div class="patient-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Patient:</strong> <span id="previewPatientName">-</span><br>
                                <strong>Mobile:</strong> <span id="previewPatientMobile">-</span>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Date:</strong> <span id="previewDate">-</span><br>
                                <strong>Bill No:</strong> <span id="previewBillNo">BILL-PREVIEW</span>
                            </div>
                        </div>
                    </div>

                    <div class="treatment-list">
                        <h6>Treatments:</h6>
                        <div id="previewTreatmentList">
                            Treatment details will appear here...
                        </div>
                    </div>

                    <div id="previewDiscountSection" style="margin-bottom: 20px; display: none;">
                        <h6>Discount Details:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Discount:</strong> <span id="previewDiscountPercentage">0%</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Discount Amount:</strong> <span id="previewDiscountAmount">Rs. 0.00</span>
                            </div>
                        </div>
                        <div id="previewDiscountReason" style="margin-top: 5px;"></div>
                    </div>

                    <div id="previewNotesSection" style="margin-bottom: 20px; display: none;">
                        <h6>Notes:</h6>
                        <p id="previewNotes"></p>
                    </div>

                    <div class="total-section">
                        <div style="font-size: 16px;">Total Amount: Rs. <span id="previewTotalAmount">0.00</span></div>
                        <div>Final Amount: Rs. <span id="previewFinalAmount">0.00</span></div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <button class="btn-primary w-100" onclick="printBillModal()">
                            <i class="material-symbols-rounded">print</i> Print Bill
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button class="btn-secondary w-100" onclick="closeBillModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>

    <script>
        // Treatments data from PHP
        const treatments = <?php echo json_encode($treatments); ?>;
        const patients = <?php echo json_encode($patients); ?>;

        let treatmentRowCounter = 0;
        let currentEditingBill = null;
        let searchTimeout;

        // Add patients data for search
        const patients_search = <?php echo json_encode($patients_search); ?>;

        // Patient search functions
        function searchPatients() {
            const searchInput = document.getElementById('patientSearch');
            const dropdown = document.getElementById('patientDropdown');
            const searchTerm = searchInput.value.toLowerCase().trim();

            if (searchTerm.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            dropdown.innerHTML = '<div class="loading-results">Searching...</div>';
            dropdown.style.display = 'block';

            const filteredPatients = patients_search.filter(patient => {
                const name = patient.name.toLowerCase();
                const mobile = patient.mobile.toLowerCase();
                const regNumber = patient.registration_number.toLowerCase();

                return name.includes(searchTerm) ||
                    mobile.includes(searchTerm) ||
                    regNumber.includes(searchTerm);
            });

            displayPatientResults(filteredPatients);
        }

        function displayPatientResults(filteredPatients) {
            const dropdown = document.getElementById('patientDropdown');

            if (filteredPatients.length === 0) {
                dropdown.innerHTML = '<div class="no-results">No patients found</div>';
                return;
            }

            let html = '';
            filteredPatients.forEach(patient => {
                html += `
            <div class="appointment-search-result" onclick="selectPatient(${patient.id}, '${patient.registration_number}', '${patient.name}', '${patient.mobile}')">
                <div class="appointment-result-number">${patient.registration_number}</div>
                <div class="appointment-result-patient">${patient.name} - ${patient.mobile}</div>
            </div>
        `;
            });

            dropdown.innerHTML = html;
        }

        function showPatientDropdown() {
            const searchInput = document.getElementById('patientSearch');
            const searchTerm = searchInput.value.trim();

            if (searchTerm.length > 0) {
                searchPatients();
            } else {
                displayPatientResults(patients_search);
            }
        }

        function selectPatient(patientId, regNumber, name, mobile) {
            document.getElementById('patientSelect').value = patientId;
            document.getElementById('patientSearch').value = `${regNumber} - ${name}`;
            document.getElementById('patientName').value = name;
            document.getElementById('patientMobile').value = mobile;
            document.getElementById('patientDropdown').style.display = 'none';
        }

        // Add new treatment row
        function addTreatmentRow() {
            treatmentRowCounter++;
            const treatmentSelection = document.getElementById('treatmentSelection');

            if (!treatmentSelection) {
                console.error('Treatment selection container not found');
                return;
            }

            const treatmentRow = document.createElement('div');
            treatmentRow.className = 'treatment-row';
            treatmentRow.id = `treatment-row-${treatmentRowCounter}`;

            // Build treatment options
            let treatmentOptions = '<option value="">Select Treatment</option>';
            treatments.forEach(t => {
                treatmentOptions += `<option value="${t.id}" data-price="${t.price}">${t.name}</option>`;
            });

            treatmentRow.innerHTML = `
        <div class="treatment-dropdown">
            <select onchange="updatePrice(${treatmentRowCounter})" id="treatment-select-${treatmentRowCounter}">
                ${treatmentOptions}
            </select>
        </div>
        <div class="price-input">
            <input type="number" step="0.01" min="0" placeholder="0.00" readonly 
                   onchange="calculateTotal()" id="price-${treatmentRowCounter}">
        </div>
        <div class="quantity-treatment-input">
            <input type="number" min="1" value="1" 
                   onchange="calculateTotal()" id="quantity-${treatmentRowCounter}">
        </div>
        <button type="button" class="remove-btn" onclick="removeTreatmentRow(${treatmentRowCounter})">
            <i class="material-symbols-rounded mt-2">delete</i>
        </button>
    `;

            treatmentSelection.appendChild(treatmentRow);
            console.log('Treatment row added:', treatmentRowCounter);
        }

        // Remove treatment row
        function removeTreatmentRow(rowId) {
            const row = document.getElementById(`treatment-row-${rowId}`);
            if (row) {
                row.remove();
                calculateTotal();
                console.log('Treatment row removed:', rowId);
            }
        }

        // Update price when treatment is selected
        function updatePrice(rowId) {
            const select = document.getElementById(`treatment-select-${rowId}`);
            const priceInput = document.getElementById(`price-${rowId}`);

            if (!select || !priceInput) {
                console.error('Select or price input not found for row:', rowId);
                return;
            }

            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;

                if (price === 0) {
                    // For treatments with 0 price, enable price input for custom entry
                    priceInput.value = '';
                    priceInput.readOnly = false;
                    priceInput.className = 'custom-price-treatment';
                    priceInput.placeholder = 'Enter custom price';
                    priceInput.focus();
                } else {
                    // For predefined treatments, set price and make readonly
                    priceInput.value = price.toFixed(2);
                    priceInput.readOnly = true;
                    priceInput.className = '';
                }
                calculateTotal();
            } else {
                priceInput.value = '';
                priceInput.readOnly = true;
                priceInput.className = '';
                calculateTotal();
            }
        }

        // Calculate total amount with discount
        function calculateTotal() {
            let total = 0;
            const treatmentRows = document.querySelectorAll('.treatment-row');

            treatmentRows.forEach(row => {
                const priceInput = row.querySelector('input[type="number"][step]');
                const quantityInput = row.querySelector('input[type="number"][min="1"]');

                if (priceInput && quantityInput) {
                    const price = parseFloat(priceInput.value) || 0;
                    const quantity = parseInt(quantityInput.value) || 0;
                    total += price * quantity;
                }
            });

            const discountPercentage = parseFloat(document.getElementById('discountPercentage')?.value) || 0;
            const discountAmount = (total * discountPercentage) / 100;
            const finalAmount = total - discountAmount;

            const totalAmountEl = document.getElementById('totalAmount');
            const discountAmountInputEl = document.getElementById('discountAmountInput');
            const discountAmountDisplayEl = document.getElementById('discountAmountDisplay');
            const finalAmountEl = document.getElementById('finalAmount');

            if (totalAmountEl) totalAmountEl.textContent = `Rs. ${total.toFixed(2)}`;
            if (discountAmountInputEl) discountAmountInputEl.value = discountAmount.toFixed(2);
            if (discountAmountDisplayEl) discountAmountDisplayEl.textContent = `Rs. ${discountAmount.toFixed(2)}`;
            if (finalAmountEl) finalAmountEl.textContent = `Rs. ${finalAmount.toFixed(2)}`;

            // Show/hide discount display
            const discountDisplay = document.getElementById('discountDisplay');
            if (discountDisplay) {
                discountDisplay.style.display = (discountPercentage > 0 || discountAmount > 0) ? 'block' : 'none';
            }
        }

        // Calculate discount from percentage
        function calculateFromPercentage() {
            const totalAmountEl = document.getElementById('totalAmount');
            const total = parseFloat(totalAmountEl?.textContent.replace('Rs. ', '')) || 0;
            const discountPercentageEl = document.getElementById('discountPercentage');
            const discountPercentage = parseFloat(discountPercentageEl?.value) || 0;

            if (discountPercentage > 100) {
                if (discountPercentageEl) discountPercentageEl.value = 100;
                return;
            }

            const discountAmount = (total * discountPercentage) / 100;
            const finalAmount = total - discountAmount;

            const discountAmountInputEl = document.getElementById('discountAmountInput');
            const discountAmountDisplayEl = document.getElementById('discountAmountDisplay');
            const finalAmountEl = document.getElementById('finalAmount');

            if (discountAmountInputEl) discountAmountInputEl.value = discountAmount.toFixed(2);
            if (discountAmountDisplayEl) discountAmountDisplayEl.textContent = `Rs. ${discountAmount.toFixed(2)}`;
            if (finalAmountEl) finalAmountEl.textContent = `Rs. ${finalAmount.toFixed(2)}`;

            const discountDisplay = document.getElementById('discountDisplay');
            if (discountDisplay) {
                discountDisplay.style.display = discountPercentage > 0 ? 'block' : 'none';
            }
        }

        // Calculate discount from amount
        function calculateFromAmount() {
            const totalAmountEl = document.getElementById('totalAmount');
            const total = parseFloat(totalAmountEl?.textContent.replace('Rs. ', '')) || 0;
            const discountAmountInputEl = document.getElementById('discountAmountInput');
            const discountAmount = parseFloat(discountAmountInputEl?.value) || 0;

            if (discountAmount > total) {
                alert('Discount amount cannot exceed total amount');
                if (discountAmountInputEl) discountAmountInputEl.value = total.toFixed(2);
                return;
            }

            const discountPercentage = total > 0 ? (discountAmount / total) * 100 : 0;
            const finalAmount = total - discountAmount;

            const discountPercentageEl = document.getElementById('discountPercentage');
            const discountAmountDisplayEl = document.getElementById('discountAmountDisplay');
            const finalAmountEl = document.getElementById('finalAmount');

            if (discountPercentageEl) discountPercentageEl.value = discountPercentage.toFixed(2);
            if (discountAmountDisplayEl) discountAmountDisplayEl.textContent = `Rs. ${discountAmount.toFixed(2)}`;
            if (finalAmountEl) finalAmountEl.textContent = `Rs. ${finalAmount.toFixed(2)}`;

            const discountDisplay = document.getElementById('discountDisplay');
            if (discountDisplay) {
                discountDisplay.style.display = discountAmount > 0 ? 'block' : 'none';
            }
        }

        // Form submission
        const treatmentForm = document.getElementById('treatmentForm');
        if (treatmentForm) {
            treatmentForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const patientName = document.getElementById('patientName')?.value;
                const patientMobile = document.getElementById('patientMobile')?.value;
                const notes = document.getElementById('treatmentNotes')?.value || '';
                const paymentStatus = document.getElementById('paymentStatus')?.value || 'Pending'; // ADDED THIS LINE

                if (!patientName || !patientMobile) {
                    alert('Please enter patient name and mobile number');
                    return;
                }

                // Collect selected treatments
                const selectedTreatments = [];
                const treatmentRows = document.querySelectorAll('.treatment-row');

                treatmentRows.forEach(row => {
                    const select = row.querySelector('select');
                    const priceInput = row.querySelector('input[type="number"][step]');
                    const quantityInput = row.querySelector('input[type="number"][min="1"]');

                    if (select?.value && priceInput?.value && quantityInput?.value) {
                        const treatment = treatments.find(t => t.id == select.value);
                        if (treatment) {
                            selectedTreatments.push({
                                id: select.value,
                                name: treatment.name,
                                price: parseFloat(priceInput.value),
                                quantity: parseInt(quantityInput.value)
                            });
                        }
                    }
                });

                if (selectedTreatments.length === 0) {
                    alert('Please select at least one treatment');
                    return;
                }

                // Get discount values
                const discountPercentage = parseFloat(document.getElementById('discountPercentage')?.value) || 0;
                const totalAmountEl = document.getElementById('totalAmount');
                const totalAmount = parseFloat(totalAmountEl?.textContent.replace('Rs. ', ''));

                // Send data to server
                fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'save_bill',
                            patient_id: document.getElementById('patientSelect')?.value || '',
                            patient_name: patientName,
                            patient_mobile: patientMobile,
                            treatments: JSON.stringify(selectedTreatments),
                            notes: notes,
                            total_amount: totalAmount,
                            discount_percentage: discountPercentage,
                            discount_reason: document.getElementById('discountReason')?.value || '',
                            payment_status: paymentStatus // ADDED THIS LINE
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Treatment bill ${data.bill_number} saved successfully!`);
                            resetForm();
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error saving bill. Please try again.');
                    });
            });
        }

        // Update existing bill
        function updateBill() {
            if (!currentEditingBill) {
                alert('No bill is being edited');
                return;
            }

            const patientName = document.getElementById('patientName')?.value.trim();
            const patientMobile = document.getElementById('patientMobile')?.value.trim();
            const paymentStatus = document.getElementById('paymentStatus')?.value || 'Pending'; // ADDED THIS LINE

            if (!patientName || !patientMobile) {
                alert('Please enter patient name and mobile number');
                return;
            }

            // Collect treatments
            const selectedTreatments = [];
            document.querySelectorAll('.treatment-row').forEach(row => {
                const select = row.querySelector('select');
                const priceInput = row.querySelector('input[type="number"][step]');
                const quantityInput = row.querySelector('input[type="number"][min="1"]');

                if (select?.value && priceInput?.value && quantityInput?.value) {
                    const treatment = treatments.find(t => t.id == select.value);
                    if (treatment) {
                        selectedTreatments.push({
                            id: select.value,
                            name: treatment.name,
                            price: parseFloat(priceInput.value),
                            quantity: parseInt(quantityInput.value)
                        });
                    }
                }
            });

            if (selectedTreatments.length === 0) {
                alert('Please select at least one treatment');
                return;
            }

            const discountPercentage = parseFloat(document.getElementById('discountPercentage')?.value) || 0;
            const totalAmountEl = document.getElementById('totalAmount');
            const totalAmount = parseFloat(totalAmountEl?.textContent.replace('Rs. ', ''));

            // ADDED payment_status to the params
            const params = new URLSearchParams({
                bill_id: currentEditingBill,
                patient_id: document.getElementById('patientSelect')?.value || '',
                patient_name: patientName,
                patient_mobile: patientMobile,
                treatments: JSON.stringify(selectedTreatments),
                notes: document.getElementById('treatmentNotes')?.value || '',
                total_amount: totalAmount,
                discount_percentage: discountPercentage,
                discount_reason: document.getElementById('discountReason')?.value || '',
                payment_status: paymentStatus // ADDED THIS LINE
            });

            fetch('update_treatment_bill.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Treatment bill updated successfully!');
                        resetForm();
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Update failed: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error updating bill: ' + err.message);
                });
        }

        // Reset form
        function resetForm() {
            const form = document.getElementById('treatmentForm');
            if (form) form.reset();

            const treatmentSelection = document.getElementById('treatmentSelection');
            if (treatmentSelection) treatmentSelection.innerHTML = '';

            const totalAmountEl = document.getElementById('totalAmount');
            if (totalAmountEl) totalAmountEl.textContent = 'Rs. 0.00';

            const discountDisplay = document.getElementById('discountDisplay');
            if (discountDisplay) discountDisplay.style.display = 'none';

            const discountPercentageEl = document.getElementById('discountPercentage');
            if (discountPercentageEl) discountPercentageEl.value = '0';

            const discountAmountInputEl = document.getElementById('discountAmountInput');
            if (discountAmountInputEl) discountAmountInputEl.value = '0';

            const discountReasonEl = document.getElementById('discountReason');
            if (discountReasonEl) discountReasonEl.value = '';

            const billIdEl = document.getElementById('billId');
            if (billIdEl) billIdEl.value = '';

            const saveBtn = document.getElementById('saveBtn');
            const updateBtn = document.getElementById('updateBtn');
            if (saveBtn) saveBtn.style.display = 'flex';
            if (updateBtn) updateBtn.style.display = 'none';

            currentEditingBill = null;
            treatmentRowCounter = 0;
            addTreatmentRow();
        }

        // Edit bill
        function editBill(billNumber) {
            fetch(`?action=get_bill&bill_number=${encodeURIComponent(billNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const bill = data.bill;
                        currentEditingBill = bill.id;

                        // Populate form fields
                        const patientNameEl = document.getElementById('patientName');
                        const patientMobileEl = document.getElementById('patientMobile');
                        const treatmentNotesEl = document.getElementById('treatmentNotes');
                        const discountPercentageEl = document.getElementById('discountPercentage');
                        const discountReasonEl = document.getElementById('discountReason');
                        const paymentStatusEl = document.getElementById('paymentStatus'); // ADDED THIS LINE
                        const billIdEl = document.getElementById('billId');

                        if (patientNameEl) patientNameEl.value = bill.patient_name;
                        if (patientMobileEl) patientMobileEl.value = bill.patient_mobile;
                        if (treatmentNotesEl) treatmentNotesEl.value = bill.notes || '';
                        if (discountPercentageEl) discountPercentageEl.value = bill.discount_percentage || 0;
                        if (discountReasonEl) discountReasonEl.value = bill.discount_reason || '';
                        if (paymentStatusEl) paymentStatusEl.value = bill.payment_status || 'Pending'; // ADDED THIS LINE
                        if (billIdEl) billIdEl.value = bill.id;

                        const discountAmt = parseFloat(bill.discount_amount) || 0;
                        const discountAmountInputEl = document.getElementById('discountAmountInput');
                        if (discountAmountInputEl) discountAmountInputEl.value = discountAmt.toFixed(2);

                        // Set patient selection if patient_id exists
                        if (bill.patient_id) {
                            const patientSelectEl = document.getElementById('patientSelect');
                            if (patientSelectEl) patientSelectEl.value = bill.patient_id;
                        }

                        // Clear existing treatment rows
                        const treatmentSelection = document.getElementById('treatmentSelection');
                        if (treatmentSelection) treatmentSelection.innerHTML = '';
                        treatmentRowCounter = 0;

                        // Add treatment rows from bill data
                        bill.treatments_data.forEach((treatment, index) => {
                            addTreatmentRow();
                            const rowId = treatmentRowCounter;

                            const select = document.querySelector(`#treatment-row-${rowId} select`);
                            const priceInput = document.getElementById(`price-${rowId}`);
                            const quantityInput = document.getElementById(`quantity-${rowId}`);

                            if (select) select.value = treatment.id;
                            if (priceInput) {
                                priceInput.value = treatment.price;
                                if (treatment.price === 0) {
                                    priceInput.readOnly = false;
                                    priceInput.className = 'custom-price-treatment';
                                }
                            }
                            if (quantityInput) quantityInput.value = treatment.quantity;
                        });

                        calculateTotal();

                        const saveBtn = document.getElementById('saveBtn');
                        const updateBtn = document.getElementById('updateBtn');
                        if (saveBtn) saveBtn.style.display = 'none';
                        if (updateBtn) updateBtn.style.display = 'flex';

                        document.querySelector('.treatment-card')?.scrollIntoView({
                            behavior: 'smooth'
                        });

                        showNotification('Bill loaded for editing', 'success');
                    } else {
                        alert('Error loading bill: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading bill for editing. Please try again.');
                });
        }

        // Save and print
        function saveAndPrint() {
            const patientName = document.getElementById('patientName')?.value;
            const patientMobile = document.getElementById('patientMobile')?.value;

            if (!patientName || !patientMobile) {
                alert('Please enter patient name and mobile number');
                return;
            }

            // Collect selected treatments
            const selectedTreatments = [];
            const treatmentRows = document.querySelectorAll('.treatment-row');

            treatmentRows.forEach(row => {
                const select = row.querySelector('select');
                const priceInput = row.querySelector('input[type="number"][step]');
                const quantityInput = row.querySelector('input[type="number"][min="1"]');

                if (select?.value && priceInput?.value && quantityInput?.value) {
                    const treatment = treatments.find(t => t.id == select.value);
                    if (treatment) {
                        selectedTreatments.push({
                            id: select.value,
                            name: treatment.name,
                            price: parseFloat(priceInput.value),
                            quantity: parseInt(quantityInput.value)
                        });
                    }
                }
            });

            if (selectedTreatments.length === 0) {
                alert('Please select at least one treatment');
                return;
            }

            const discountPercentage = parseFloat(document.getElementById('discountPercentage')?.value) || 0;
            const totalAmountEl = document.getElementById('totalAmount');
            const totalAmount = parseFloat(totalAmountEl?.textContent.replace('Rs. ', ''));

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'save_bill',
                        patient_id: document.getElementById('patientSelect')?.value || '',
                        patient_name: patientName,
                        patient_mobile: patientMobile,
                        treatments: JSON.stringify(selectedTreatments),
                        notes: document.getElementById('treatmentNotes')?.value || '',
                        total_amount: totalAmount,
                        discount_percentage: discountPercentage,
                        discount_reason: document.getElementById('discountReason')?.value || '',
                        payment_status: document.getElementById('paymentStatus')?.value || 'Pending' // ADDED THIS LINE
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Treatment bill saved successfully! Opening print preview...', 'success');

                        fetch(`get_bill_details.php?bill_number=${data.bill_number}`)
                            .then(response => response.json())
                            .then(billData => {
                                if (billData.success) {
                                    printBillContent(billData.bill);
                                    resetForm();
                                    setTimeout(() => location.reload(), 2000);
                                } else {
                                    alert('Bill saved but error loading for print: ' + billData.message);
                                    setTimeout(() => location.reload(), 1500);
                                }
                            })
                            .catch(error => {
                                console.error('Error loading bill for print:', error);
                                alert('Bill saved but error opening print preview');
                                setTimeout(() => location.reload(), 1500);
                            });
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving bill. Please try again.');
                });
        }

        // Preview bill
        function previewBill() {
            const patientName = document.getElementById('patientName')?.value;
            const patientMobile = document.getElementById('patientMobile')?.value;
            const notes = document.getElementById('treatmentNotes')?.value || '';

            if (!patientName || !patientMobile) {
                alert('Please enter patient name and mobile number');
                return;
            }

            const selectedTreatments = [];
            const treatmentRows = document.querySelectorAll('.treatment-row');

            treatmentRows.forEach(row => {
                const select = row.querySelector('select');
                const priceInput = row.querySelector('input[type="number"][step]');
                const quantityInput = row.querySelector('input[type="number"][min="1"]');

                if (select?.value && priceInput?.value && quantityInput?.value) {
                    const treatment = treatments.find(t => t.id == select.value);
                    if (treatment) {
                        selectedTreatments.push({
                            id: select.value,
                            name: treatment.name,
                            price: parseFloat(priceInput.value),
                            quantity: parseInt(quantityInput.value)
                        });
                    }
                }
            });

            if (selectedTreatments.length === 0) {
                alert('Please select at least one treatment');
                return;
            }

            const modalTitle = document.getElementById('modalTitle');
            if (modalTitle) modalTitle.textContent = 'Preview Treatment Bill';

            const previewPatientName = document.getElementById('previewPatientName');
            const previewPatientMobile = document.getElementById('previewPatientMobile');
            const previewDate = document.getElementById('previewDate').textContent = `${formattedDate} @ ${formattedTime}`;
            const previewBillNo = document.getElementById('previewBillNo');

            if (previewPatientName) previewPatientName.textContent = patientName;
            if (previewPatientMobile) previewPatientMobile.textContent = patientMobile;
            if (previewDate) previewDate.textContent = new Date().toISOString().split('T')[0];
            if (previewBillNo) previewBillNo.textContent = 'PREVIEW';

            const totalAmountEl = document.getElementById('totalAmount');
            const currentTotal = parseFloat(totalAmountEl?.textContent.replace('Rs. ', ''));
            const previewDiscountPercentage = parseFloat(document.getElementById('discountPercentage')?.value) || 0;
            const previewDiscountAmount = parseFloat(document.getElementById('discountAmountInput')?.value) || 0;
            const currentFinal = currentTotal - previewDiscountAmount;

            const previewTotalAmount = document.getElementById('previewTotalAmount');
            const previewFinalAmount = document.getElementById('previewFinalAmount');
            if (previewTotalAmount) previewTotalAmount.textContent = currentTotal.toFixed(2);
            if (previewFinalAmount) previewFinalAmount.textContent = currentFinal.toFixed(2);

            // Build treatment list
            let treatmentListHtml = '<table style="width: 100%; border-collapse: collapse;">';
            treatmentListHtml += '<tr style="border-bottom: 1px solid #ddd;"><th style="text-align: left; padding: 8px;">Treatment</th><th style="text-align: center; padding: 8px;">Qty</th><th style="text-align: right; padding: 8px;">Price</th><th style="text-align: right; padding: 8px;">Total</th></tr>';

            selectedTreatments.forEach(treatment => {
                treatmentListHtml += `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px;">${treatment.name}</td>
                <td style="text-align: center; padding: 8px;">${treatment.quantity}</td>
                <td style="text-align: right; padding: 8px;">Rs. ${treatment.price.toFixed(2)}</td>
                <td style="text-align: right; padding: 8px;">Rs. ${(treatment.price * treatment.quantity).toFixed(2)}</td>
            </tr>
        `;
            });
            treatmentListHtml += '</table>';

            const previewTreatmentList = document.getElementById('previewTreatmentList');
            if (previewTreatmentList) previewTreatmentList.innerHTML = treatmentListHtml;

            // Show/hide discount section
            const previewDiscountSection = document.getElementById('previewDiscountSection');
            if (previewDiscountPercentage > 0 || previewDiscountAmount > 0) {
                if (previewDiscountSection) previewDiscountSection.style.display = 'block';

                const previewDiscountPercentageEl = document.getElementById('previewDiscountPercentage');
                const previewDiscountAmountEl = document.getElementById('previewDiscountAmount');

                if (previewDiscountPercentageEl) previewDiscountPercentageEl.textContent = previewDiscountPercentage + '%';
                if (previewDiscountAmountEl) previewDiscountAmountEl.textContent = 'Rs. ' + previewDiscountAmount.toFixed(2);

                const previewDiscountReason = document.getElementById('discountReason')?.value;
                const previewDiscountReasonEl = document.getElementById('previewDiscountReason');
                if (previewDiscountReason && previewDiscountReasonEl) {
                    previewDiscountReasonEl.innerHTML = '<small><strong>Reason:</strong> ' + previewDiscountReason + '</small>';
                } else if (previewDiscountReasonEl) {
                    previewDiscountReasonEl.innerHTML = '';
                }
            } else {
                if (previewDiscountSection) previewDiscountSection.style.display = 'none';
            }

            // Show/hide notes section
            const previewNotesSection = document.getElementById('previewNotesSection');
            const previewNotesEl = document.getElementById('previewNotes');
            if (notes.trim()) {
                if (previewNotesSection) previewNotesSection.style.display = 'block';
                if (previewNotesEl) previewNotesEl.textContent = notes;
            } else {
                if (previewNotesSection) previewNotesSection.style.display = 'none';
            }

            // Show the modal
            const billModal = document.getElementById('billModal');
            if (billModal) billModal.style.display = 'block';
        }

        // View bill details
        function viewBill(billNumber) {
            console.log('Viewing bill:', billNumber);
            const modal = document.getElementById('billModal');

            if (!modal) {
                console.error('Bill modal not found');
                return;
            }

            const modalTitle = document.getElementById('modalTitle');
            if (modalTitle) modalTitle.textContent = 'Loading...';
            modal.style.display = 'block';

            fetch(`get_bill_details.php?bill_number=${encodeURIComponent(billNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const bill = data.bill;

                        if (modalTitle) modalTitle.textContent = 'View Treatment Bill';

                        const previewPatientName = document.getElementById('previewPatientName');
                        const previewPatientMobile = document.getElementById('previewPatientMobile');
                        const previewBillNo = document.getElementById('previewBillNo');

                        if (previewPatientName) previewPatientName.textContent = bill.patient_name;
                        if (previewPatientMobile) previewPatientMobile.textContent = bill.patient_mobile;

                        // Add time to the display
                        const dateTime = new Date(bill.created_at);
                        const formattedDate = dateTime.toLocaleDateString();
                        const formattedTime = dateTime.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        // ✅ Now safe to use
                        const previewDate = document.getElementById('previewDate');
                        if (previewDate) previewDate.textContent = `${formattedDate} @ ${formattedTime}`;
                        if (previewBillNo) previewBillNo.textContent = bill.bill_number;

                        // Build treatment list table
                        let treatmentListHtml = '<table style="width: 100%; border-collapse: collapse;">';
                        treatmentListHtml += '<tr style="border-bottom: 1px solid #ddd;"><th style="text-align: left; padding: 8px;">Treatment</th><th style="text-align: center; padding: 8px;">Qty</th><th style="text-align: right; padding: 8px;">Price</th><th style="text-align: right; padding: 8px;">Total</th></tr>';

                        bill.treatments_data.forEach(treatment => {
                            const price = parseFloat(treatment.price);
                            const quantity = parseInt(treatment.quantity);
                            const total = price * quantity;

                            treatmentListHtml += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px;">${treatment.name}</td>
                            <td style="text-align: center; padding: 8px;">${quantity}</td>
                            <td style="text-align: right; padding: 8px;">Rs. ${price.toFixed(2)}</td>
                            <td style="text-align: right; padding: 8px;">Rs. ${total.toFixed(2)}</td>
                        </tr>
                    `;
                        });
                        treatmentListHtml += '</table>';

                        const previewTreatmentList = document.getElementById('previewTreatmentList');
                        if (previewTreatmentList) previewTreatmentList.innerHTML = treatmentListHtml;

                        // Update amounts
                        const previewTotalAmount = document.getElementById('previewTotalAmount');
                        const previewFinalAmount = document.getElementById('previewFinalAmount');
                        if (previewTotalAmount) previewTotalAmount.textContent = parseFloat(bill.total_amount).toFixed(2);
                        if (previewFinalAmount) previewFinalAmount.textContent = parseFloat(bill.final_amount).toFixed(2);

                        // Show/hide discount section
                        const discountSection = document.getElementById('previewDiscountSection');
                        if (bill.discount_percentage > 0 || bill.discount_amount > 0) {
                            if (discountSection) discountSection.style.display = 'block';

                            const previewDiscountPercentage = document.getElementById('previewDiscountPercentage');
                            const previewDiscountAmount = document.getElementById('previewDiscountAmount');
                            const previewDiscountReason = document.getElementById('previewDiscountReason');

                            if (previewDiscountPercentage) previewDiscountPercentage.textContent = bill.discount_percentage + '%';
                            if (previewDiscountAmount) previewDiscountAmount.textContent = 'Rs. ' + parseFloat(bill.discount_amount).toFixed(2);

                            if (bill.discount_reason && bill.discount_reason.trim() && previewDiscountReason) {
                                previewDiscountReason.innerHTML = '<small><strong>Reason:</strong> ' + bill.discount_reason + '</small>';
                            } else if (previewDiscountReason) {
                                previewDiscountReason.innerHTML = '';
                            }
                        } else {
                            if (discountSection) discountSection.style.display = 'none';
                        }

                        // Show/hide notes section
                        const notesSection = document.getElementById('previewNotesSection');
                        const previewNotes = document.getElementById('previewNotes');
                        if (bill.notes && bill.notes.trim()) {
                            if (notesSection) notesSection.style.display = 'block';
                            if (previewNotes) previewNotes.textContent = bill.notes;
                        } else {
                            if (notesSection) notesSection.style.display = 'none';
                        }

                    } else {
                        closeBillModal();
                        alert('Error loading bill details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    closeBillModal();
                    alert('Error loading bill details. Please try again.');
                });
        }

        // Search bills
        function searchBills() {
            const searchTerm = document.getElementById('billSearch')?.value.toLowerCase() || '';
            const rows = document.querySelectorAll('#billsTableBody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Print functions
        function printBill(billId) {
            fetch(`?action=get_bill&bill_number=${billId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        printBillContent(data.bill);
                    } else {
                        alert('Error loading bill for printing: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading bill for printing. Please try again.');
                });
        }

        function printBillModal() {
    const billPreview = document.getElementById('billPreview');
    if (!billPreview) return;

    // Get bill data from preview
    const billNumber = document.getElementById('previewBillNo')?.textContent || 'PREVIEW';
    const patientName = document.getElementById('previewPatientName')?.textContent || '-';
    const patientMobile = document.getElementById('previewPatientMobile')?.textContent || '-';
    const dateText = document.getElementById('previewDate')?.textContent || '';
    
    // Parse date and time
    const [dateStr, timeStr] = dateText.split(' @ ');
    
    // Get treatments
    const treatmentRows = document.querySelectorAll('#previewTreatmentList table tr');
    const treatments = [];
    
    treatmentRows.forEach((row, index) => {
        if (index === 0) return; // Skip header
        const cells = row.querySelectorAll('td');
        if (cells.length >= 4) {
            treatments.push({
                name: cells[0].textContent,
                quantity: parseInt(cells[1].textContent),
                price: parseFloat(cells[2].textContent.replace('Rs. ', '')),
            });
        }
    });
    
    // Get amounts
    const totalAmount = parseFloat(document.getElementById('previewTotalAmount')?.textContent || '0');
    const finalAmount = parseFloat(document.getElementById('previewFinalAmount')?.textContent || '0');
    const discountAmount = totalAmount - finalAmount;
    const discountPercentage = totalAmount > 0 ? (discountAmount / totalAmount) * 100 : 0;
    
    // Get discount reason if visible
    const discountSection = document.getElementById('previewDiscountSection');
    let discountReason = '';
    if (discountSection && discountSection.style.display !== 'none') {
        const reasonElement = document.getElementById('previewDiscountReason');
        if (reasonElement) {
            discountReason = reasonElement.textContent.replace('Reason:', '').trim();
        }
    }
    
    // Get notes
    const notesSection = document.getElementById('previewNotesSection');
    let notes = '';
    if (notesSection && notesSection.style.display !== 'none') {
        notes = document.getElementById('previewNotes')?.textContent || '';
    }
    
    // Build bill object
    const billData = {
        bill_number: billNumber,
        patient_name: patientName,
        patient_mobile: patientMobile,
        created_at: new Date().toISOString(),
        treatments_data: treatments,
        total_amount: totalAmount,
        final_amount: finalAmount,
        discount_amount: discountAmount,
        discount_percentage: discountPercentage,
        discount_reason: discountReason,
        notes: notes,
        payment_status: 'Paid'
    };
    
    printBillContent(billData);
}

        function printPreview() {
            const billContent = document.getElementById('treatmentBillPreview')?.innerHTML;
            if (billContent) {
                printContent(billContent);
            }
        }

        function printBillContent(bill) {
    // Format date and time
    const dateTime = new Date(bill.created_at);
    const formattedDate = dateTime.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
    const formattedTime = dateTime.toLocaleString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });

    // Build treatment items dynamically
    let treatmentItemsHtml = '';
    let itemCount = 0;
    
    if (bill.treatments_data && Array.isArray(bill.treatments_data)) {
        bill.treatments_data.forEach(treatment => {
            itemCount++;
            const price = parseFloat(treatment.price);
            const quantity = parseInt(treatment.quantity);
            const itemTotal = price * quantity;
            
            treatmentItemsHtml += '<tr>' +
                '<td class="item">' + treatment.name + '</td>' +
                '<td class="qty">' + quantity + '</td>' +
                '<td class="amount">' + price.toFixed(2) + '</td>' +
                '<td class="amount">' + itemTotal.toFixed(2) + '</td>' +
                '</tr>';
        });
    }

    // Discount section
    const discountSection = bill.discount_percentage > 0 ? 
        '<tr>' +
            '<td colspan="3">Discount (' + parseFloat(bill.discount_percentage).toFixed(2) + '%):</td>' +
            '<td class="amount">- Rs. ' + parseFloat(bill.discount_amount).toFixed(2) + '</td>' +
        '</tr>' +
        (bill.discount_reason ? '<tr><td colspan="4" style="font-size:10px; font-style:italic; padding: 2px 0;">Reason: ' + bill.discount_reason + '</td></tr>' : '')
        : '';

    // Notes section
    const notesSection = bill.notes && bill.notes.trim() ? 
        '<div class="divider"></div>' +
        '<div class="notes-section">' +
            '<strong>Notes:</strong>' +
            '<div style="margin-top: 5px; font-size: 11px; line-height: 1.4;">' + bill.notes + '</div>' +
        '</div>'
        : '';

    // Payment status badge
    let statusColor = '#4CAF50';
    let statusText = 'PAID';
    if (bill.payment_status === 'Pending') {
        statusColor = '#f57c00';
        statusText = 'PENDING';
    } else if (bill.payment_status === 'Partial') {
        statusColor = '#1976d2';
        statusText = 'PARTIAL';
    }

    // Get cashier name - using session username
    const cashierName = '<?php echo htmlspecialchars($_SESSION["username"] ?? "Cashier"); ?>';

    const printHtml = '<!DOCTYPE html>' +
        '<html>' +
        '<head>' +
            '<title>Print OPD Bill - ' + bill.bill_number + '</title>' +
            '<style>' +
                '@page { size: 80mm auto; margin: 0; }' +
                'body { font-family: "Courier New", monospace; font-size: 12px; margin: 0 auto; padding: 10px; width: 80mm; max-width: 80mm; }' +
                '.receipt { width: 100%; margin: 0 auto; }' +
                '.header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 8px; }' +
                '.header h2 { margin: 5px 0; font-size: 16px; font-weight: bold; }' +
                '.header p { margin: 2px 0; font-size: 10px; }' +
                '.info-section { margin: 10px 0; font-size: 11px; }' +
                '.info-row { display: flex; justify-content: space-between; margin: 3px 0; }' +
                '.divider { border-top: 1px dashed #000; margin: 8px 0; }' +
                '.items-table { width: 100%; margin: 10px 0; border-collapse: collapse; }' +
                '.items-table td { padding: 5px 2px; font-size: 11px; }' +
                '.items-table th { padding: 5px 2px; text-align: left; border-bottom: 1px solid #000; font-size: 11px; }' +
                '.items-table .item { text-align: left; }' +
                '.items-table .qty { text-align: center; padding: 0 5px; }' +
                '.items-table .amount { text-align: right; }' +
                '.subtotal-section { margin-top: 10px; width: 100%; }' +
                '.subtotal-section td { padding: 3px 0; font-size: 11px; }' +
                '.subtotal-section td:first-child { text-align: left; }' +
                '.subtotal-section td:last-child { text-align: right; }' +
                '.total-row { border-top: 2px solid #000; font-weight: bold; font-size: 13px; }' +
                '.total-row td { padding: 8px 0 5px 0; }' +
                '.status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-weight: bold; font-size: 11px; background-color: ' + statusColor + '; color: white; }' +
                '.notes-section { margin: 10px 0; font-size: 11px; line-height: 1.4; padding: 8px; background: #f5f5f5; border-radius: 4px; }' +
                '.footer { text-align: center; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #000; font-size: 10px; }' +
                '@media print { body { width: 80mm; margin: 0 auto; } }' +
            '</style>' +
        '</head>' +
        '<body>' +
            '<div class="receipt">' +
                '<div class="header">' +
                    '<h2>Erundeniya Ayurveda Hospital</h2>' +
                    '<p>A/55 Wedagedara, Erundeniya,</p>' +
                    '<p>Amithirigala, North.</p>' +
                    '<p>Tel: +94 71 291 9408</p>' +
                    '<p>Email: info@erundeniyaayurveda.lk</p>' +
                    '<p style="margin-top: 5px; font-weight: bold;">OPD TREATMENT BILL</p>' +
                '</div>' +
                '<div class="divider"></div>' +
                '<div class="info-section">' +
                    '<div class="info-row"><span>Bill No:</span><span>#' + bill.bill_number + '</span></div>' +
                    '<div class="info-row"><span>Date:</span><span>' + formattedDate + '</span></div>' +
                    '<div class="info-row"><span>Time:</span><span>' + formattedTime + '</span></div>' +
                    '<div class="info-row"><span>Patient:</span><span>' + bill.patient_name + '</span></div>' +
                    '<div class="info-row"><span>Mobile:</span><span>' + bill.patient_mobile + '</span></div>' +
                    '<div class="info-row"><span>Cashier:</span><span>' + cashierName + '</span></div>' +
                    '<div class="info-row"><span>Status:</span><span>' + statusText + '</span></div>' +
                '</div>' +
                '<div class="divider"></div>' +
                '<table class="items-table">' +
                    '<tr>' +
                        '<th>Treatment</th>' +
                        '<th class="qty">Qty</th>' +
                        '<th class="amount">Price</th>' +
                        '<th class="amount">Total</th>' +
                    '</tr>' +
                    treatmentItemsHtml +
                '</table>' +
                '<div class="divider"></div>' +
                '<table class="items-table subtotal-section">' +
                    '<tr>' +
                        '<td colspan="3">Subtotal:</td>' +
                        '<td class="amount">Rs. ' + parseFloat(bill.total_amount).toFixed(2) + '</td>' +
                    '</tr>' +
                    discountSection +
                    '<tr class="total-row">' +
                        '<td colspan="3">TOTAL:</td>' +
                        '<td class="amount">Rs. ' + parseFloat(bill.final_amount).toFixed(2) + '</td>' +
                    '</tr>' +
                '</table>' +
                notesSection +
                '<div class="divider"></div>' +
                '<div style="text-align:center; margin: 10px 0;">Total Treatment(s): ' + itemCount + '</div>' +
                '<div style="text-align:center; margin: 10px 0; font-size:16px; font-weight:bold;">*' + bill.bill_number + '*</div>' +
                '<div class="footer">' +
                    '<p>Thank You for Your Visit!</p>' +
                    '<span>For inquiries: info@erundeniyaayurveda.lk</span>' +
                    '<br/>' +
                    '<span>Tel: +94 71 291 9408</span>' +
                    '<div style="margin-top:10px; font-size:9px;">' +
                        '<span>© 2025 Erundeniya Ayurveda Hospital</span>' +
                        '<br/>' +
                        '<span>All rights reserved</span>' +
                        '<br/>' +
                        '<span>All payments made to Erundeniya Ayurveda Hospital are non-refundable.</span>' +
                        '<br/>' +
                        '<br/>' +
                        '<span>Powered By <strong>www.evotech.lk</strong></span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<script>' +
                'window.onload = function() {' +
                    'window.print();' +
                    'window.onafterprint = function() { window.close(); }' +
                '}' +
            '<\/script>' +
        '</body>' +
        '</html>';

    const printWindow = window.open('', '', 'height=auto,width=400');
    printWindow.document.write(printHtml);
    printWindow.document.close();
}

        function printContent(content) {
    const printWindow = window.open('', '', 'height=auto,width=400');
    printWindow.document.write(content);
    printWindow.document.close();
}

        // Modal functions
        function closeBillModal() {
            const modal = document.getElementById('billModal');
            if (modal) modal.style.display = 'none';
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) modal.style.display = 'none';
        }

        // Utility functions
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="material-symbols-rounded me-2">${type === 'success' ? 'check_circle' : 'info'}</i>
            ${message}
        </div>
    `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        function toggleNotifications() {
            showNotification('Notifications feature coming soon!', 'info');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '?logout=1';
            }
        }

        // Global search
        const globalSearchEl = document.getElementById('globalSearch');
        if (globalSearchEl) {
            globalSearchEl.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#billsTableBody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Enhanced search and date filter for OPD bills
        function searchBillsEnhanced() {
            applyCombinedFilters();
        }

        function filterByDateOPD() {
            applyCombinedFilters();
        }

        function clearDateFilterOPD() {
            const dateFilter = document.getElementById('dateFilterOPD');
            if (dateFilter) {
                const wrapper = dateFilter.parentElement;
                const clearBtn = wrapper.querySelector('.date-clear-btn-opd');

                dateFilter.value = '';
                wrapper.classList.remove('has-date');
                if (clearBtn) clearBtn.style.display = 'none';

                applyCombinedFilters();
                dateFilter.focus();
            }
        }

        // Combined filter - searches both text and date
        function applyCombinedFilters() {
            const searchTerm = document.getElementById('billSearch')?.value.toLowerCase() || '';
            const selectedDate = document.getElementById('dateFilterOPD')?.value || '';
            const rows = document.querySelectorAll('#billsTableBody tr');

            rows.forEach(row => {
                const billNumber = row.querySelector('td:nth-child(1) h6')?.textContent.toLowerCase() || '';
                const patientName = row.querySelector('td:nth-child(2) span:first-child')?.textContent.toLowerCase() || '';
                const patientMobile = row.querySelector('td:nth-child(2) span:last-child')?.textContent.toLowerCase() || '';
                const billDate = row.querySelector('td:nth-child(1) p')?.textContent.toLowerCase() || '';
                const dateCell = row.querySelector('td:nth-child(1) p')?.textContent.trim() || '';

                const matchesSearch = billNumber.includes(searchTerm) ||
                    patientName.includes(searchTerm) ||
                    patientMobile.includes(searchTerm) ||
                    billDate.includes(searchTerm);

                const matchesDate = !selectedDate || dateCell === selectedDate;

                row.style.display = (matchesSearch && matchesDate) ? '' : 'none';
            });
        }

        function toggleSearchClearButton() {
            const searchInput = document.getElementById('billSearch');
            const clearBtn = searchInput?.parentElement.querySelector('.search-clear-btn-opd');

            if (searchInput && clearBtn) {
                clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
            }
        }

        function clearBillSearch() {
            const searchInput = document.getElementById('billSearch');
            if (searchInput) {
                searchInput.value = '';

                const clearBtn = searchInput.parentElement.querySelector('.search-clear-btn-opd');
                if (clearBtn) clearBtn.style.display = 'none';

                applyCombinedFilters();
                searchInput.focus();
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['billModal', 'previewModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('OPD page initializing...');
            console.log('Treatments available:', treatments.length);
            console.log('Patients available:', patients.length);

            // Add one treatment row by default
            addTreatmentRow();

            // Setup search listeners
            const billSearchInput = document.getElementById('billSearch');
            if (billSearchInput) {
                billSearchInput.addEventListener('input', function() {
                    searchBillsEnhanced();
                    toggleSearchClearButton();
                });
                toggleSearchClearButton();
            }

            // Setup date filter listener
            const dateFilter = document.getElementById('dateFilterOPD');
            if (dateFilter) {
                dateFilter.addEventListener('change', function() {
                    const wrapper = this.parentElement;
                    const clearBtn = wrapper.querySelector('.date-clear-btn-opd');

                    if (this.value) {
                        wrapper.classList.add('has-date');
                        if (clearBtn) {
                            clearBtn.style.display = 'flex';
                            clearBtn.style.alignItems = 'center';
                            clearBtn.style.justifyContent = 'center';
                        }
                    } else {
                        wrapper.classList.remove('has-date');
                        if (clearBtn) clearBtn.style.display = 'none';
                    }
                    applyCombinedFilters();
                });
            }

            console.log('OPD page initialized successfully');
        });
    </script>

</body>

</html>