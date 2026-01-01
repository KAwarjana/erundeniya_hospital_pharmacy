<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();

// ---------- Dynamic Sidebar (dashboard.php ekata daala) ----------
require_once 'auth_manager.php';

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

// Database connection for dynamic data
require_once '../../connection/connection.php';

// Handle AJAX requests for search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    try {
        Database::setUpConnection();

        if ($_POST['ajax_action'] === 'search_appointments') {
            $searchTerm = $_POST['search_term'] ?? '';

            if (strlen($searchTerm) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $searchTerm = Database::$connection->real_escape_string($searchTerm);

            $query = "SELECT 
                        a.id,
                        a.appointment_number,
                        a.appointment_date,
                        a.appointment_time,
                        p.title,
                        p.name as patient_name,
                        p.id as patient_id,
                        p.mobile as patient_mobile,
                        p.registration_number
                      FROM appointment a
                      INNER JOIN patient p ON a.patient_id = p.id
                      WHERE a.status = 'Attended'
                      AND (
                        a.appointment_number LIKE '%$searchTerm%' OR
                        p.name LIKE '%$searchTerm%' OR
                        p.registration_number LIKE '%$searchTerm%' OR
                        p.mobile LIKE '%$searchTerm%'
                      )
                      ORDER BY a.appointment_date DESC, a.appointment_time DESC
                      LIMIT 10";

            $result = Database::search($query);
            $appointments = [];

            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $appointments]);
            exit;
        }

        if ($_POST['ajax_action'] === 'search_patients') {
            $searchTerm = $_POST['search_term'] ?? '';

            if (strlen($searchTerm) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $searchTerm = Database::$connection->real_escape_string($searchTerm);

            $query = "SELECT 
                        id,
                        registration_number,
                        title,
                        name,
                        mobile
                      FROM patient
                      WHERE 
                        name LIKE '%$searchTerm%' OR
                        registration_number LIKE '%$searchTerm%' OR
                        mobile LIKE '%$searchTerm%'
                      ORDER BY name ASC
                      LIMIT 10";

            $result = Database::search($query);
            $patients = [];

            while ($row = $result->fetch_assoc()) {
                $patients[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $patients]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Function to get prescription statistics
function getPrescriptionStats()
{
    try {
        Database::setUpConnection();

        // Total prescriptions
        $totalResult = Database::search("SELECT COUNT(*) as total FROM prescriptions");
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['total'];

        // Today's prescriptions
        $today = date('Y-m-d');
        $todayResult = Database::search("SELECT COUNT(*) as today FROM prescriptions WHERE DATE(created_at) = '$today'");
        $todayRow = $todayResult->fetch_assoc();
        $todayCount = $todayRow['today'];

        // This week's prescriptions
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekResult = Database::search("SELECT COUNT(*) as week FROM prescriptions WHERE DATE(created_at) >= '$weekStart'");
        $weekRow = $weekResult->fetch_assoc();
        $weekCount = $weekRow['week'];

        // This month's prescriptions
        $monthStart = date('Y-m-01');
        $monthResult = Database::search("SELECT COUNT(*) as month FROM prescriptions WHERE DATE(created_at) >= '$monthStart'");
        $monthRow = $monthResult->fetch_assoc();
        $monthCount = $monthRow['month'];

        return [
            'total' => $total,
            'today' => $todayCount,
            'week' => $weekCount,
            'month' => $monthCount
        ];
    } catch (Exception $e) {
        error_log("Error getting prescription stats: " . $e->getMessage());
        return [
            'total' => 0,
            'today' => 0,
            'week' => 0,
            'month' => 0
        ];
    }
}

// Function to get all prescriptions with patient details
function getAllPrescriptions()
{
    try {
        Database::setUpConnection();

        $query = "SELECT p.*, a.appointment_number, a.appointment_date, a.appointment_time, 
                  pt.title, pt.name, pt.mobile, pt.registration_number 
                  FROM prescriptions p 
                  INNER JOIN appointment a ON p.appointment_id = a.id 
                  INNER JOIN patient pt ON a.patient_id = pt.id 
                  ORDER BY p.created_at DESC";

        $result = Database::search($query);
        return $result;
    } catch (Exception $e) {
        error_log("Error getting prescriptions: " . $e->getMessage());
        return false;
    }
}

// Function to get attended appointments for prescription creation
function getAttendedAppointments()
{
    try {
        Database::setUpConnection();

        $query = "SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time, 
                  pt.title, pt.name, pt.mobile, pt.id as patient_id, pt.registration_number 
                  FROM appointment a 
                  INNER JOIN patient pt ON a.patient_id = pt.id 
                  WHERE a.status = 'Attended' 
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC";

        $result = Database::search($query);
        return $result;
    } catch (Exception $e) {
        error_log("Error getting attended appointments: " . $e->getMessage());
        return false;
    }
}

// Function to get prescription by ID
function getPrescriptionById($id)
{
    try {
        Database::setUpConnection();

        $query = "SELECT p.*, a.appointment_number, a.appointment_date, a.appointment_time, 
                  pt.title, pt.name, pt.mobile, pt.registration_number 
                  FROM prescriptions p 
                  INNER JOIN appointment a ON p.appointment_id = a.id 
                  INNER JOIN patient pt ON a.patient_id = pt.id 
                  WHERE p.id = $id";

        $result = Database::search($query);
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error getting prescription by ID: " . $e->getMessage());
        return false;
    }
}

// Get all patients for walk-in functionality
function getAllPatients()
{
    try {
        Database::setUpConnection();

        $query = "SELECT id, registration_number, title, name, mobile 
                  FROM patient 
                  ORDER BY name ASC";

        $result = Database::search($query);
        return $result;
    } catch (Exception $e) {
        error_log("Error getting all patients: " . $e->getMessage());
        return false;
    }
}

// Get statistics for display
$stats = getPrescriptionStats();
$prescriptions = getAllPrescriptions();
$attendedAppointments = getAttendedAppointments();
$allPatients = getAllPatients();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <title>Prescriptions Management - Erundeniya Medical Center</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

    <style>
        /* Keep all your existing CSS styles here */
        .prescription-card {
            /* border: 2px solid #4CAF50; */
            border-radius: 15px;
            background: linear-gradient(45deg, #c5c5c5ff, #d1d1d1ff);
        }

        .prescription-header {
            background: linear-gradient(45deg, #000000ff, #292929ff);
            color: white;
            padding: 15px;
            border-radius: 13px 13px 0 0;
        }

        .prescription-area {
            min-height: 350px;
            resize: vertical;
            font-family: 'Courier New', monospace;
            line-height: 1.6;
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
            padding: 5px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 8px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            /* margin-left: 15px; */
        }

        .btn-secondary1 {
            background: #6c757d;
            color: white;
            padding: 8px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            /* margin-left: 15px; */
        }

        .print-btn {
            background: #000000ff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            line-height: 1.5;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: top;
            /* Ensures alignment with other buttons */
            margin: 0;
        }

        .print-btn1 {
            background: #000000ff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-outline-success,
        .btn-outline-primary,
        .print-btn {
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: top;
        }

        /* Action buttons container */
        .d-flex.gap-1 {
            align-items: center;
            /* Centers all buttons vertically */
            flex-wrap: nowrap;
        }

        .d-flex.gap-1>* {
            vertical-align: baseline;
            /* Ensures consistent baseline alignment */
            margin-bottom: 0;
            /* Remove any bottom margins that might cause misalignment */
        }

        td .d-flex {
            align-items: flex-start;
            /* Align to top of container */
        }

        .prescription-preview {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
        }

        .prescription-header-print {
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

        .prescription-content {
            min-height: 250px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-line;
        }

        .doctor-signature {
            text-align: right;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
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

        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .quick-templates {
            margin-bottom: 15px;
        }

        .template-btn {
            background: #ebffecff;
            border: 1px solid #4CAF50;
            color: #0a880eff;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
        }

        .template-btn:hover {
            background: #4CAF50;
            color: white;
        }

        .card--header--text {
            color: white;
        }

        /* Fix dropdown arrow alignment */
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg   ' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Align icons in labels */
        .form-group label i.material-symbols-rounded {
            vertical-align: middle;
            margin-right: 5px;
            font-size: 18px;
        }

        /* Button icon alignment */
        .btn-primary i.material-symbols-rounded,
        .print-btn1 i.material-symbols-rounded,
        .btn-secondary1 i.material-symbols-rounded {
            vertical-align: middle;
            margin-right: 5px;
            font-size: 18px;
        }

        /* Ensure consistent button heights */
        .btn-primary,
        .print-btn1,
        .btn-secondary1 {
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Template buttons alignment */
        .template-btn {
            display: inline-flex;
            align-items: center;
            vertical-align: top;
        }

        /* Add this CSS for the specific screen width range */
        @media (min-width: 992px) and (max-width: 1534px) {
            .prescription-buttons .col-lg-4 {
                flex: 0 0 50%;
                max-width: 50%;
            }

            .prescription-buttons .col-lg-4:nth-child(3) {
                flex: 0 0 100%;
                max-width: 100%;
                margin-top: 10px;
            }
        }

        /* For screens larger than 1534px, show all 3 buttons in one row */
        @media (min-width: 1535px) {
            .prescription-buttons .col-lg-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
        }

        /* Filter controls styling */
        .card-header .input-group-outline {
            margin-bottom: 0;
        }

        .card-header .form-control {
            border: 1px solid #d2d6da;
            border-radius: 8px;
            font-size: 14px;
            padding: 8px 12px;
        }

        .card-header .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        /* Responsive adjustments for filter bar */
        @media (max-width: 768px) {
            .card-header .row {
                row-gap: 10px;
            }

            .card-header .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 4px;
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal button improvements */
        .modal-body .btn-primary,
        .modal-body .btn-secondary,
        .modal-body .print-btn1 {
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .modal-body .row .col-md-4,
        .modal-body .row .col-md-6 {
            padding: 0 5px;
        }

        /* Responsive modal buttons */
        @media (max-width: 768px) {
            .modal-body .row [class*="col-md-"] {
                margin-bottom: 10px;
            }
        }

        /* Logout hover effect */
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

        /* Add to your existing CSS */
        select.form-control {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            transition: border-color 0.3s;
        }

        select.form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        select.form-control option {
            padding: 10px;
            font-size: 14px;
        }

        .appointment-tab {
            background-color: #fefefe;
        }

        .walkin-tab {
            background-color: #fefefe;
        }

        /* Date filter container styles */
        .date-filter-container {
            position: relative;
            width: 100%;
        }

        /* Custom clear button styles */
        .clear-date-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 2;
            /* Lower z-index than calendar icon */
            display: none;
            width: 20px;
            height: 20px;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }

        .date-clear-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 2;
            display: none;
            width: 18px;
            height: 18px;
            padding: 0;
            font-size: 12px;
        }

        .clear-date-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .date-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        /* Hide native calendar icon when clear button is visible */
        .date-filter-container.has-value #dateFilter::-webkit-calendar-picker-indicator {
            display: none;
        }

        /* For Firefox */
        #dateFilter {
            /* -moz-appearance: textfield; */
            width: 100%;
            padding-right: 30px;
            position: relative;
        }

        #dateFilter::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 25px;
            /* Position calendar icon */
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 1;
        }

        /* Firefox specific styles */
        #dateFilter::-moz-calendar-picker-indicator {
            position: absolute;
            right: 0;
            top: 0;
            width: 30px;
            height: 100%;
            cursor: pointer;
            background: transparent;
            z-index: 1;
        }

        /* Show clear button when date is selected */
        .date-input-wrapper.has-date .date-clear-btn {
            display: block;
            right: 25px;
        }

        .input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        /* Hide native calendar picker when clear button is visible */
        .input-wrapper.has-date .date-clear-btn {
            display: block;
        }

        .input-wrapper.has-date #dateFilter::-webkit-calendar-picker-indicator {
            opacity: 0;
            pointer-events: none;
        }

        /* For browsers that don't show calendar icon */
        @supports not selector(::-webkit-calendar-picker-indicator) {
            .date-input-wrapper.has-date .date-clear-btn {
                right: 5px;
            }
        }

        /*CSS to fix the responsive modal issue for screen widths 1200-1510px */

        /* Modal responsive adjustments for medium-large screens */
        @media (min-width: 1200px) and (max-width: 1510px) {

            /* Adjust modal positioning and sizing */
            .modal {
                padding-left: 0px !important;
                /* Account for sidebar width */
            }

            .modal-content {
                max-width: calc(100vw - 300px) !important;
                /* Responsive max-width */
                margin: 2% auto !important;
                width: 95% !important;
            }

            /* Ensure sidebar stays in place */
            .sidenav {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                height: 100vh !important;
                z-index: 1040 !important;
                /* Below modals but above content */
            }

            /* Prevent sidebar from moving down */
            .main-content {
                margin-left: 250px !important;
                /* Match sidebar width */
                width: calc(100% - 250px) !important;
            }

            /* Adjust modal header for better fit */
            .modal-header {
                padding: 15px 20px !important;
            }

            /* Ensure modal body scrolls properly */
            .modal-body {
                max-height: calc(90vh - 120px) !important;
                overflow-y: auto !important;
            }
        }

        /* Additional adjustments for better responsiveness */
        @media (min-width: 1200px) and (max-width: 1400px) {

            /* Reduce modal padding for smaller screens in this range */
            .modal-content {
                padding: 0 !important;
            }

            /* Adjust form elements */
            .form-group {
                margin-bottom: 15px !important;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 8px 10px !important;
                font-size: 13px !important;
            }

            /* Smaller buttons */
            .btn-primary,
            .btn-secondary,
            .print-btn {
                padding: 8px 20px !important;
                font-size: 14px !important;
                min-height: 40px !important;
            }
        }

        /* Ensure proper stacking order */
        .modal {
            z-index: 1050 !important;
            /* Higher than sidebar */
        }

        .sidenav {
            z-index: 1040 !important;
            /* Lower than modals */
        }

        /* Fix for modal backdrop */
        .modal-backdrop {
            z-index: 1049 !important;
            /* Between sidebar and modal */
        }

        /* Responsive adjustments for treatment cards in modals */
        @media (min-width: 1200px) and (max-width: 1510px) {
            .treatment-row {
                gap: 10px !important;
                padding: 10px !important;
            }

            .treatment-dropdown {
                flex: 1.5 !important;
                min-width: 180px !important;
            }

            .price-input {
                flex: 0.8 !important;
                min-width: 100px !important;
            }

            .quantity-treatment-input {
                flex: 0.4 !important;
                min-width: 60px !important;
            }

            .remove-btn {
                padding: 6px 8px !important;
            }
        }

        /* Ensure proper layout for discount section */
        @media (min-width: 1200px) and (max-width: 1510px) {
            .discount-row {
                gap: 10px !important;
            }

            .discount-col {
                flex: 1 !important;
            }

            .discount-section {
                padding: 15px !important;
            }

            .discount-display {
                padding: 10px 15px !important;
            }
        }

        /* Fix for table responsiveness in modals */
        @media (min-width: 1200px) and (max-width: 1510px) {
            .table-responsive {
                font-size: 13px !important;
            }

            .table th,
            .table td {
                padding: 8px 10px !important;
            }

            .btn-sm {
                padding: 4px 8px !important;
                font-size: 11px !important;
                min-height: 28px !important;
            }
        }

        /* Ensure modal doesn't push sidebar down */
        @media (min-width: 1200px) {
            body.modal-open {
                overflow: hidden !important;
            }

            body.modal-open .sidenav {
                position: fixed !important;
            }
        }

        /* Additional fix for modal positioning */
        .modal-dialog {
            margin: 1.75rem auto !important;
        }

        @media (min-width: 1200px) and (max-width: 1510px) {
            .modal-dialog {
                margin: 1rem auto !important;
                max-width: calc(100vw - 280px) !important;
            }
        }

        /* Search dropdown styles */
        .search-wrapper {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffff;
            border: 2px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .search-result {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .search-result:hover {
            background-color: #f5f5f5;
        }

        .search-result:last-child {
            border-bottom: none;
        }

        .search-result-number {
            font-weight: 600;
            color: #2196F3;
        }

        .search-result-patient {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        .search-result-date {
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
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-1 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.html">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm text-dark active">Prescriptions</li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                        <!--<div class="input-group input-group-outline">
                            <input type="text" class="form-control" placeholder="Search appointments..." id="globalSearch">
                        </div>-->
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
                                <img src="../../img/user.png" width="20" height="20"> &nbsp;<span class="d-none d-sm-inline">Admin</span>
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
                    <h3 class="mb-0 h4 font-weight-bolder">Prescriptions Management</h3>
                    <p class="mb-4">Create, manage and print patient prescriptions</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Total Prescriptions</p>
                                    <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">medication</i>
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
                                    <p class="text-sm mb-0 text-capitalize">Today's Prescriptions</p>
                                    <h4 class="mb-0"><?php echo $stats['today']; ?></h4>
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
                                    <h4 class="mb-0"><?php echo $stats['week']; ?></h4>
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
                                    <p class="text-sm mb-0 text-capitalize">This Month</p>
                                    <h4 class="mb-0"><?php echo $stats['month']; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">event_note</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row mt-4">
                <!-- Prescriptions List -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h6 class="mb-0">All Prescriptions</h6>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-outline" style="position: relative;">
                                        <input type="text" class="form-control" placeholder="Search prescriptions..." id="prescriptionSearch" onkeyup="searchPrescriptions()" style="padding-right: 35px;">
                                        <button type="button" onclick="clearPrescriptionSearch()" style="position: absolute; right: 8px; top: 60%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; z-index: 10; display: none; padding: 4px;">
                                            <i class="material-symbols-rounded" style="font-size: 20px; color: #66666681;">close</i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="date-input-wrapper" style="position: relative; display: inline-block; width: 100%;">
                                        <input type="date" class="form-control" id="dateFilter" onchange="filterByDate()" placeholder="Filter by date">
                                        <button type="button" onclick="clearDateFilter()" class="date-clear-btn" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; border-radius: 50%; cursor: pointer; z-index: 5; display: none; width: 20px; height: 20px; padding: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
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
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Prescription Details</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Patient</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="prescriptionsTableBody">
                                        <?php
                                        $recordsPerPage = 10;
                                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                                        $offset = ($page - 1) * $recordsPerPage;

                                        $base = "FROM prescriptions p
             INNER JOIN patient pt ON p.patient_id = pt.id
             LEFT  JOIN appointment a ON p.appointment_id = a.id";

                                        $totalRes = Database::search("SELECT COUNT(*) AS total $base");
                                        $totalRows = (int) $totalRes->fetch_assoc()['total'];
                                        $totalPages = ceil($totalRows / $recordsPerPage);

                                        $prescriptionsQuery = "SELECT p.*,
                          DATE(p.created_at) as prescription_date,
                          TIME(p.created_at) as prescription_time,
                          pt.title, pt.name, pt.mobile, pt.registration_number,
                          a.appointment_number, a.appointment_date, a.appointment_time
                   $base
                   ORDER BY p.created_at DESC
                   LIMIT $recordsPerPage OFFSET $offset";

                                        $prescriptions = Database::search($prescriptionsQuery);

                                        if ($prescriptions && $prescriptions->num_rows):
                                            while ($row = $prescriptions->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <h6 class="mb-0 text-sm font-weight-bold">
                                                                PRES<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?>
                                                            </h6>
                                                            <p class="text-xs text-secondary mb-0">
                                                                <?php if ($row['appointment_number']): ?>
                                                                    <i class="material-symbols-rounded" style="font-size:12px;vertical-align:middle;">
                                                                        calendar_today
                                                                    </i> <?= htmlspecialchars($row['appointment_number']) ?>
                                                                <?php else: ?>
                                                                    <i class="material-symbols-rounded" style="font-size:12px;vertical-align:middle;">
                                                                        person
                                                                    </i> Walk-in Patient
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="text-sm font-weight-bold">
                                                                <?= htmlspecialchars($row['title'] . ' ' . $row['name']) ?>
                                                            </span>
                                                            <span class="text-xs text-secondary">
                                                                <?= htmlspecialchars($row['registration_number']) ?>
                                                                | <?= htmlspecialchars($row['mobile']) ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="text-sm"><?= date('Y-m-d', strtotime($row['created_at'])) ?></span>
                                                            <span class="text-xs text-primary"><?= date('h:i A', strtotime($row['created_at'])) ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button class="btn btn-sm btn-outline-success"
                                                                onclick="viewPrescription(<?= $row['id'] ?>)">View</button>
                                                            <button class="btn btn-sm btn-outline-danger"
                                                                onclick="editPrescription(<?= $row['id'] ?>)">Edit</button>
                                                            <button class="print-btn btn-sm"
                                                                onclick="printPrescription(<?= $row['id'] ?>)">Print</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No prescriptions found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!--  pagination  -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Prescriptions pagination" class="mt-3">
                            <ul class="pagination justify-content-center flex-wrap">
                                <!-- Prev -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">
                                        <i class="material-symbols-rounded">chevron_left</i>
                                    </a>
                                </li>

                                <?php
                                // page numbers
                                $start = max(1, $page - 2);
                                $end   = min($totalPages, $start + 4);
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">
                                        <i class="material-symbols-rounded">chevron_right</i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <p class="text-muted text-sm text-center mb-0 mt-2">
                            Showing <?= min($offset + 1, $totalRows) ?> to <?= min($offset + $recordsPerPage, $totalRows) ?> of <?= $totalRows ?> prescriptions
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Create Prescription Panel -->
                <div class="col-lg-5 mt-5 mt-lg-0">
                    <div class="card prescription-card">
                        <div class="prescription-header">
                            <h5 class="mb-1 card--header--text">
                                <i class="material-symbols-rounded">prescriptions</i>
                                Create New Prescription
                            </h5>
                            <p class="mb-0 opacity-8">Write prescription for patients</p>
                        </div>
                        <div class="card-body">
                            <!-- Tab Selection -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="btn-group w-100" style="gap: 2%;" role="group">
                                        <input type="radio" class="btn-check" name="prescriptionMode" id="modeAppointment" value="appointment" checked autocomplete="off">
                                        <label class="btn btn-outline-success appointment-tab" for="modeAppointment" style="border-radius: 5px;">
                                            <i class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle;">calendar_today</i>
                                            Appointment
                                        </label>

                                        <input type="radio" class="btn-check" name="prescriptionMode" id="modeWalkin" value="walkin" autocomplete="off">
                                        <label class="btn btn-outline-success walkin-tab" for="modeWalkin" style="border-radius: 5px;">
                                            <i class="material-symbols-rounded" style="font-size: 18px; vertical-align: middle;">person</i>
                                            Walk-in Patient
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <form id="prescriptionForm">
                                <input type="hidden" id="selectedPatientId">
                                <input type="hidden" id="selectedAppointmentId">
                                <input type="hidden" id="currentMode" value="appointment">

                                <!-- Appointment Mode Section -->
                                <div id="appointmentModeSection">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group search-wrapper appointment-search-wrapper">
                                                <label>
                                                    <i class="material-symbols-rounded text-sm">search</i>
                                                    Appointment Number
                                                </label>
                                                <input type="text" id="appointmentNumberSearch" class="form-control bg-white" placeholder="Type to search appointment..." autocomplete="off">
                                                <div class="search-results" id="appointmentSearchResults"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Walk-in Mode Section -->
                                <div id="walkinModeSection" style="display: none;">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group search-wrapper patient-search-wrapper">
                                                <label>
                                                    <i class="material-symbols-rounded text-sm">person_search</i>
                                                    Search Patient
                                                </label>
                                                <input type="text" id="walkinPatientSearch" class="form-control bg-white" placeholder="Type to search patient..." autocomplete="off">
                                                <div class="search-results" id="patientSearchResults"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Prescription History Alert -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info" id="prescriptionHistoryAlert" style="display: none; padding: 10px;">
                                                <strong><i class="material-symbols-rounded" style="font-size: 16px; vertical-align: middle;">history</i> Previous Prescriptions:</strong>
                                                <div id="historyListContainer" style="margin-top: 5px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Common Patient Info Fields -->
                                <div class="row">
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <label>Patient Name</label>
                                            <input type="text" id="patientName" readonly style="background: #f5f5f5;">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-6">
                                        <div class="form-group">
                                            <label>Patient Mobile</label>
                                            <input type="text" id="patientMobile" readonly style="background: #f5f5f5;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Templates -->
                                <div class="quick-templates">
                                    <label>Quick Templates:</label>
                                    <div>
                                        <button type="button" class="template-btn" onclick="insertTemplate('common_cold')">Common Cold</button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('fever')">Fever</button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('headache')">Headache</button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('diabetes')">Diabetes</button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('hypertension')">Hypertension</button>
                                    </div>
                                </div>

                                <!-- Prescription Text Area -->
                                <div class="form-group">
                                    <label><i class="material-symbols-rounded text-sm">edit_note</i> Prescription Details *</label>
                                    <textarea id="prescriptionText" class="prescription-area" placeholder="Write prescription here...

Example:
1. Tab Paracetamol 500mg - 1 tab 3 times daily after meals for 5 days
2. Syrup Ambroxol 15ml - 5ml 2 times daily for 7 days  
3. Tab Omeprazole 20mg - 1 tab daily before breakfast for 10 days

Advice:
- Take complete rest
- Drink plenty of fluids
- Follow up if symptoms persist

Next visit: After 1 week" required></textarea>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row prescription-buttons">
                                    <div class="col-lg-4 col-md-12 mb-2">
                                        <button type="submit" class="btn-primary w-100">
                                            <i class="material-symbols-rounded">save</i> Save
                                        </button>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-2">
                                        <button type="button" class="print-btn1 w-100" onclick="saveAndPrint()">
                                            <i class="material-symbols-rounded">print</i> Save & Print
                                        </button>
                                    </div>
                                    <div class="col-lg-4 col-md-6 mb-2">
                                        <button type="button" class="btn-secondary1 w-100" onclick="previewPrescription()">
                                            <i class="material-symbols-rounded">visibility</i> Preview
                                        </button>
                                    </div>
                                </div>
                            </form>
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

    <!-- View/Edit Prescription Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card--header--text"><i class="material-symbols-rounded">medication</i> <span id="modalTitle">View Prescription</span></h4>
                <span class="close" onclick="closePrescriptionModal()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prescription ID</label>
                            <input type="text" id="modalPrescriptionId" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Patient Name</label>
                            <input type="text" id="modalPatientName" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <input type="text" id="modalPatientMobile" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="text" id="modalPrescriptionDate" readonly style="background: #f5f5f5;">
                        </div>
                        <!-- Add this hidden field for registration number -->
                        <input type="hidden" id="modalPatientRegNumber" value="">
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Prescription Details</label>
                            <textarea id="modalPrescriptionText" class="prescription-area" readonly style="background: #f5f5f5;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- First button row: Print and Close -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <button class="print-btn1 w-100" onclick="printModalPrescription()">
                            <i class="material-symbols-rounded">print</i> Print
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button class="btn-secondary w-100" onclick="closePrescriptionModal()">Close</button>
                    </div>
                </div>

                <!-- Second button row: Edit Prescription (full width) -->
                <div class="row mt-2">
                    <div class="col-12">
                        <button class="btn-primary w-100" onclick="enableEdit()" id="editBtn">
                            <i class="material-symbols-rounded">edit</i> Edit Prescription
                        </button>
                        <button class="btn-primary w-100" onclick="saveEditedPrescription()" id="saveBtn" style="display: none;">
                            <i class="material-symbols-rounded">save</i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Prescription Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="material-symbols-rounded">preview</i> Prescription Preview</h4>
                <span class="close" onclick="closePreviewModal()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div class="prescription-preview" id="prescriptionPreview">
                    <div class="prescription-header-print">
                        <h2>Erundeniya Ayurveda Hospital</h2>
                        <p>Specialized Ayurvedic Medical Consultation</p>
                        <p>Contact: +94 71 291 9408 | Email: info@erundeniyaayurveda.lk</p>
                    </div>

                    <div class="patient-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Patient:</strong> <span id="previewPatientName">-</span><br>
                                <strong>Mobile:</strong> <span id="previewPatientMobile">-</span><br>
                                <strong>Reg. No:</strong> <span id="previewPatientRegNumber">-</span>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Date:</strong> <span id="previewDate">-</span><br>
                                <strong>Prescription No:</strong> <span id="previewPrescriptionNo">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="prescription-content" id="previewPrescriptionContent">
                        Prescription content will appear here...
                    </div>

                    <div class="doctor-signature">
                        <div style="border-bottom: 1px solid #333; width: 200px; margin-left: auto;"></div>
                        <p class="mt-2 mb-0"><strong>Doctor's Signature</strong></p>
                        <p class="mb-0">Dr H.D.P Dharshani</p>
                        <p class="mb-0">REG NO 13467</p>
                        <p class="mb-0">Erundeniya Ayurveda Hospital</p>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <button class="btn-primary w-100" onclick="printPreview()">
                            <i class="material-symbols-rounded">print</i> Print Prescription
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button class="btn-secondary w-100" onclick="closePreviewModal()">Close</button>
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
        // Prescription templates
        const templates = {
            common_cold: `1. Tab Paracetamol 500mg - 1 tab 3 times daily after meals for 5 days
2. Syrup Dextromethorphan 10ml - 5ml 3 times daily for 7 days
3. Tab Cetirizine 10mg - 1 tab at bedtime for 5 days

Advice:
- Take complete rest
- Drink warm fluids
- Avoid cold beverages
- Use steam inhalation 2-3 times daily

Next visit: If symptoms persist after 5 days`,

            fever: `1. Tab Paracetamol 500mg - 1 tab 4 times daily for fever for 5 days
2. Tab Ibuprofen 400mg - 1 tab twice daily after meals for 3 days
3. ORS solution - As needed for dehydration

Advice:
- Complete bed rest
- Drink plenty of fluids
- Light diet
- Cold sponging if fever is high

Next visit: After 3 days or if fever persists`,

            headache: `1. Tab Paracetamol 500mg - 1 tab twice daily for 3 days
2. Tab Sumatriptan 50mg - 1 tab when required (max 2 per day)

Advice:
- Adequate rest in dark room
- Avoid bright lights and noise
- Regular meals
- Proper sleep pattern

Next visit: If headache persists or worsens`,

            diabetes: `1. Tab Metformin 500mg - 1 tab twice daily before meals
2. Tab Glimepiride 2mg - 1 tab daily before breakfast
3. Continue current insulin regime

Advice:
- Regular blood sugar monitoring
- Diabetic diet as advised
- Regular exercise
- Foot care

Next visit: After 1 month with reports`,

            hypertension: `1. Tab Amlodipine 5mg - 1 tab daily in morning
2. Tab Losartan 50mg - 1 tab daily in evening
3. Continue aspirin 75mg daily

Advice:
- Low salt diet
- Regular exercise
- Weight control
- Monitor BP regularly

Next visit: After 2 weeks with BP chart`
        };

        let searchTimeout = null;
        let currentMode = 'appointment';

        // ============================================
        // SEARCH FUNCTIONS
        // ============================================

        // Real-time appointment search
        function searchAppointments(searchTerm) {
            const resultsDiv = document.getElementById('appointmentSearchResults');

            if (searchTerm.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            resultsDiv.innerHTML = '<div class="loading-results">Searching...</div>';
            resultsDiv.style.display = 'block';

            const formData = new FormData();
            formData.append('ajax_action', 'search_appointments');
            formData.append('search_term', searchTerm);

            fetch('prescription.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAppointmentResults(data.data);
                    } else {
                        resultsDiv.innerHTML = '<div class="no-results">Error loading results</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = '<div class="no-results">Error loading results</div>';
                });
        }

        function displayAppointmentResults(appointments) {
            const resultsDiv = document.getElementById('appointmentSearchResults');

            if (appointments.length === 0) {
                resultsDiv.innerHTML = '<div class="no-results">No appointments found</div>';
                return;
            }

            let html = '';
            appointments.forEach(appointment => {
                html += `
            <div class="search-result" onclick="selectAppointment('${appointment.id}', '${appointment.appointment_number}', '${appointment.patient_id}', '${escapeHtml(appointment.title)} ${escapeHtml(appointment.patient_name)}', '${appointment.patient_mobile}', '${appointment.registration_number || ''}')">
                <div class="search-result-number">${appointment.appointment_number}</div>
                <div class="search-result-patient">${appointment.title} ${appointment.patient_name} - ${appointment.patient_mobile}</div>
                <div class="search-result-date">${appointment.appointment_date} ${appointment.appointment_time}</div>
            </div>
        `;
            });

            resultsDiv.innerHTML = html;
        }

        function selectAppointment(id, number, patientId, name, mobile, regNumber = '') {
            document.getElementById('selectedAppointmentId').value = id;
            document.getElementById('selectedPatientId').value = patientId;
            document.getElementById('appointmentNumberSearch').value = number;
            document.getElementById('patientName').value = name;
            document.getElementById('patientMobile').value = mobile;
            document.getElementById('appointmentSearchResults').style.display = 'none';

            // Store registration number in dataset for later use
            document.getElementById('appointmentNumberSearch').dataset.regNumber = regNumber;

            // Visual feedback
            document.getElementById('patientName').style.backgroundColor = '#e8f5e8';
            document.getElementById('patientMobile').style.backgroundColor = '#e8f5e8';

            setTimeout(() => {
                document.getElementById('patientName').style.backgroundColor = '#f5f5f5';
                document.getElementById('patientMobile').style.backgroundColor = '#f5f5f5';
            }, 1000);
        }

        // Real-time patient search
        function searchPatients(searchTerm) {
            const resultsDiv = document.getElementById('patientSearchResults');

            if (searchTerm.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            resultsDiv.innerHTML = '<div class="loading-results">Searching...</div>';
            resultsDiv.style.display = 'block';

            const formData = new FormData();
            formData.append('ajax_action', 'search_patients');
            formData.append('search_term', searchTerm);

            fetch('prescription.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPatientResults(data.data);
                    } else {
                        resultsDiv.innerHTML = '<div class="no-results">Error loading results</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = '<div class="no-results">Error loading results</div>';
                });
        }

        function displayPatientResults(patients) {
            const resultsDiv = document.getElementById('patientSearchResults');

            if (patients.length === 0) {
                resultsDiv.innerHTML = '<div class="no-results">No patients found</div>';
                return;
            }

            let html = '';
            patients.forEach(patient => {
                html += `
            <div class="search-result" onclick="selectPatient('${patient.id}', '${escapeHtml(patient.title)} ${escapeHtml(patient.name)}', '${patient.mobile}', '${patient.registration_number}')">
                <div class="search-result-number">${patient.registration_number}</div>
                <div class="search-result-patient">${patient.title} ${patient.name} - ${patient.mobile}</div>
            </div>
        `;
            });

            resultsDiv.innerHTML = html;
        }

        function selectPatient(id, name, mobile, regNumber) {
            document.getElementById('selectedPatientId').value = id;
            document.getElementById('selectedAppointmentId').value = '';
            document.getElementById('walkinPatientSearch').value = regNumber + ' - ' + name;
            document.getElementById('patientName').value = name;
            document.getElementById('patientMobile').value = mobile;
            document.getElementById('patientSearchResults').style.display = 'none';

            // Store registration number in dataset for later use
            document.getElementById('walkinPatientSearch').dataset.regNumber = regNumber;

            // Visual feedback
            document.getElementById('patientName').style.backgroundColor = '#e8f5e8';
            document.getElementById('patientMobile').style.backgroundColor = '#e8f5e8';

            setTimeout(() => {
                document.getElementById('patientName').style.backgroundColor = '#f5f5f5';
                document.getElementById('patientMobile').style.backgroundColor = '#f5f5f5';
            }, 1000);

            // Load prescription history
            loadPrescriptionHistory(id);
        }

        function loadPrescriptionHistory(patientId) {
            fetch('get_patient_prescription_history.php?id=' + patientId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.prescriptions && data.prescriptions.length > 0) {
                        let historyHTML = '<ul style="margin: 5px 0; padding-left: 20px; font-size: 12px;">';
                        data.prescriptions.forEach(pres => {
                            const date = new Date(pres.created_at).toLocaleDateString();
                            const type = pres.appointment_number ? pres.appointment_number : 'Walk-in';
                            historyHTML += `<li>${date} - ${type} <a href="#" onclick="viewPrescription(${pres.id}); return false;" style="font-size: 11px;">[View]</a></li>`;
                        });
                        historyHTML += '</ul>';

                        document.getElementById('historyListContainer').innerHTML = historyHTML;
                        document.getElementById('prescriptionHistoryAlert').style.display = 'block';
                    } else {
                        document.getElementById('prescriptionHistoryAlert').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading prescription history:', error);
                    document.getElementById('prescriptionHistoryAlert').style.display = 'none';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // TEMPLATE FUNCTIONS
        // ============================================

        // Insert template
        function insertTemplate(templateType) {
            const template = templates[templateType];
            if (template) {
                document.getElementById('prescriptionText').value = template;
            }
        }

        // ============================================
        // PRESCRIPTION SAVE FUNCTIONS
        // ============================================

        // Save prescription
        document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const patientId = document.getElementById('selectedPatientId').value;
            const appointmentId = document.getElementById('selectedAppointmentId').value;
            const prescriptionText = document.getElementById('prescriptionText').value;

            if (!patientId) {
                alert('Please select a patient or appointment');
                return;
            }

            if (!prescriptionText.trim()) {
                alert('Please enter prescription details');
                return;
            }

            const prescriptionData = {
                patient_id: patientId,
                appointment_id: appointmentId || null,
                prescription_text: prescriptionText,
                created_by: <?php echo $_SESSION['user_id'] ?? 1; ?>
            };

            fetch('save_prescription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(prescriptionData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Prescription ${data.prescription_number} saved successfully!`);
                        showNotification('Prescription saved successfully!', 'success');
                        this.reset();
                        clearPrescriptionFields();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Error saving prescription: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving prescription. Please try again.');
                });
        });

        // Save and print prescription
        function saveAndPrint() {
            const form = document.getElementById('prescriptionForm');
            if (form.checkValidity()) {
                const patientId = document.getElementById('selectedPatientId').value;
                const appointmentId = document.getElementById('selectedAppointmentId').value;
                const prescriptionText = document.getElementById('prescriptionText').value;

                if (!patientId) {
                    alert('Please select a patient or appointment');
                    return;
                }

                if (!prescriptionText.trim()) {
                    alert('Please enter prescription details');
                    return;
                }

                const prescriptionData = {
                    patient_id: patientId,
                    appointment_id: appointmentId || null,
                    prescription_text: prescriptionText,
                    created_by: <?php echo $_SESSION['user_id'] ?? 1; ?>
                };

                fetch('save_prescription.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(prescriptionData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Prescription ${data.prescription_number} saved and printing...`);
                            form.reset();
                            clearPrescriptionFields();
                            showNotification('Prescription saved and sent to printer!', 'success');
                            printPrescription(data.prescription_id);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            alert('Error saving prescription: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error saving prescription. Please try again.');
                    });
            } else {
                alert('Please fill all required fields');
            }
        }

        // Preview prescription
        function previewPrescription() {
            const patientId = document.getElementById('selectedPatientId').value;
            const patientName = document.getElementById('patientName').value;
            const patientMobile = document.getElementById('patientMobile').value;
            const prescriptionText = document.getElementById('prescriptionText').value;

            if (!patientId || !patientName || !prescriptionText) {
                alert('Please fill all required fields');
                return;
            }

            // Get registration number from the search input dataset
            let regNumber = 'N/A';
            if (currentMode === 'appointment') {
                regNumber = document.getElementById('appointmentNumberSearch').dataset.regNumber || 'N/A';
            } else {
                regNumber = document.getElementById('walkinPatientSearch').dataset.regNumber || 'N/A';
            }

            // Get current date and time
            const now = new Date();
            const formattedDate = now.toLocaleDateString();
            const formattedTime = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });

            document.getElementById('previewPatientName').textContent = patientName;
            document.getElementById('previewPatientMobile').textContent = patientMobile;
            document.getElementById('previewPatientRegNumber').textContent = regNumber;
            document.getElementById('previewDate').textContent = `${formattedDate} @ ${formattedTime}`;
            document.getElementById('previewPrescriptionNo').textContent = 'PRES-PREVIEW';
            document.getElementById('previewPrescriptionContent').textContent = prescriptionText;

            document.getElementById('previewModal').style.display = 'block';
        }

        // ============================================
        // VIEW/EDIT PRESCRIPTION FUNCTIONS
        // ============================================

        // View prescription
        function viewPrescription(prescriptionId) {
            fetch('get_prescription.php?id=' + prescriptionId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'View Prescription';
                        document.getElementById('modalPrescriptionId').value = 'PRES' + String(data.prescription.id).padStart(3, '0');
                        document.getElementById('modalPatientName').value = data.prescription.title + ' ' + data.prescription.name;
                        document.getElementById('modalPatientMobile').value = data.prescription.mobile;
                        
                        // Safely set registration number with fallback
                        const regNumberField = document.getElementById('modalPatientRegNumber');
                        if (regNumberField) {
                            regNumberField.value = data.prescription.registration_number || 'N/A';
                        }

                        // Format date and time
                        const dateTime = new Date(data.prescription.created_at);
                        const formattedDate = dateTime.toLocaleDateString();
                        const formattedTime = dateTime.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        document.getElementById('modalPrescriptionDate').value = `${formattedDate} @ ${formattedTime}`;
                        document.getElementById('modalPrescriptionText').value = data.prescription.prescription_text;
                        document.getElementById('modalPrescriptionText').readOnly = true;
                        document.getElementById('modalPrescriptionText').style.background = '#f5f5f5';

                        document.getElementById('editBtn').style.display = 'inline-block';
                        document.getElementById('saveBtn').style.display = 'none';

                        document.getElementById('prescriptionModal').style.display = 'block';
                    } else {
                        alert('Error loading prescription: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading prescription. Please try again.');
                });
        }

        // Edit prescription
        function editPrescription(prescriptionId) {
            viewPrescription(prescriptionId);
            enableEdit();
        }

        // Enable editing
        function enableEdit() {
            document.getElementById('modalTitle').textContent = 'Edit Prescription';
            document.getElementById('modalPrescriptionText').readOnly = false;
            document.getElementById('modalPrescriptionText').style.background = 'white';

            document.getElementById('editBtn').style.display = 'none';
            document.getElementById('saveBtn').style.display = 'inline-block';
        }

        // Save edited prescription
        function saveEditedPrescription() {
            const prescriptionId = document.getElementById('modalPrescriptionId').value.replace('PRES', '');
            const updatedText = document.getElementById('modalPrescriptionText').value;

            fetch('update_prescription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: prescriptionId,
                        prescription_text: updatedText
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Prescription updated successfully!');
                        closePrescriptionModal();
                        showNotification('Prescription updated successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Error updating prescription: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating prescription. Please try again.');
                });
        }

        // ============================================
        // PRINT FUNCTIONS
        // ============================================

        // Print prescription
        function printPrescription(prescriptionId) {
            fetch('get_prescription.php?id=' + prescriptionId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Format date and time
                        const dateTime = new Date(data.prescription.created_at);
                        const formattedDate = dateTime.toLocaleDateString();
                        const formattedTime = dateTime.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        const prescriptionContent = createPrintContent(
                            'PRES' + String(data.prescription.id).padStart(3, '0'),
                            data.prescription.title + ' ' + data.prescription.name,
                            data.prescription.mobile,
                            data.prescription.registration_number || 'N/A',
                            formattedDate,
                            formattedTime,
                            data.prescription.prescription_text
                        );
                        printContent(prescriptionContent);
                        showNotification(`Prescription sent to printer`, 'success');
                    } else {
                        alert('Error loading prescription for printing: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading prescription for printing. Please try again.');
                });
        }

        // Print from modal
        function printModalPrescription() {
            const dateTimeText = document.getElementById('modalPrescriptionDate').value;
            const parts = dateTimeText.split(' @ ');
            const date = parts[0] || dateTimeText;
            const time = parts[1] || '';

            // Get registration number from the patient data (safely)
            const patientName = document.getElementById('modalPatientName').value;
            const patientMobile = document.getElementById('modalPatientMobile').value;
            
            // Safely get registration number with fallback
            const regNumberField = document.getElementById('modalPatientRegNumber');
            const regNumber = regNumberField ? regNumberField.value : 'N/A';

            const prescriptionContent = createPrintContent(
                document.getElementById('modalPrescriptionId').value,
                patientName,
                patientMobile,
                regNumber,
                date,
                time,
                document.getElementById('modalPrescriptionText').value
            );

            printContent(prescriptionContent);
        }

        // Print preview
        function printPreview() {
            const prescriptionContent = document.getElementById('prescriptionPreview').innerHTML;
            printContent(prescriptionContent);
        }

        // Create print content
        function createPrintContent(id, patient, mobile, regNumber, date, time, text) {
            return `
        <div style="font-family: 'Times New Roman', serif; max-width: 600px; margin: 0 auto;">
            <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px;">
                <h2>Erundeniya Ayurveda Hospital</h2>
                <p>Specialized Ayurvedic Medical Consultation</p>
                <p>Contact: +94 71 291 9408 | Email: info@erundeniyaayurveda.lk</p>
            </div>
            
            <div style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>Patient:</strong> ${patient}<br>
                        <strong>Mobile:</strong> ${mobile}<br>
                        <strong>Reg. No:</strong> ${regNumber || 'N/A'}
                    </div>
                    <div>
                        <strong>Date:</strong> ${date}<br>
                        <strong>Time:</strong> ${time}<br>
                        <strong>Prescription No:</strong> ${id}
                    </div>
                </div>
            </div>
            
            <div style="min-height: 250px; border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; white-space: pre-line;">
                ${text}
            </div>
            
            <div style="text-align: right; margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px;">
                <div style="border-bottom: 1px dashed #333; width: 200px; margin-left: auto; margin-bottom: 10px;"></div>
                <p style="margin: 5px 0;"><strong>Doctor's Signature</strong></p>
                <p style="margin: 5px 0;">Dr.H.D.P. Darshani</p>
                <p style="margin: 5px 0;">Erundeniya Ayurveda Hospital</p>
            </div>
        </div>
    `;
        }

        // Print content
        function printContent(content) {
            const printWindow = window.open('', '', 'height=auto,width=800');
            printWindow.document.write(`
        <html>
        <head>
            <title>Print Prescription</title>
            <style>
                body { font-family: 'Times New Roman', serif; margin: 20px; }
                @media print {
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            ${content}
        </body>
        </html>
    `);
            printWindow.document.close();
            printWindow.print();
        }

        // ============================================
        // MODAL FUNCTIONS
        // ============================================

        function closePrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'none';
        }

        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        // ============================================
        // MODE SWITCHING
        // ============================================

        document.querySelectorAll('input[name="prescriptionMode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const mode = this.value;
                document.getElementById('currentMode').value = mode;

                if (mode === 'appointment') {
                    document.getElementById('appointmentModeSection').style.display = 'block';
                    document.getElementById('walkinModeSection').style.display = 'none';
                } else {
                    document.getElementById('appointmentModeSection').style.display = 'none';
                    document.getElementById('walkinModeSection').style.display = 'block';
                }

                clearPrescriptionFields();
            });
        });

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================

        // Clear prescription fields
        function clearPrescriptionFields() {
            document.getElementById('selectedPatientId').value = '';
            document.getElementById('selectedAppointmentId').value = '';
            document.getElementById('patientName').value = '';
            document.getElementById('patientMobile').value = '';
            document.getElementById('prescriptionText').value = '';
            document.getElementById('prescriptionHistoryAlert').style.display = 'none';
            document.getElementById('appointmentNumberSearch').value = '';
            document.getElementById('walkinPatientSearch').value = '';
            // Clear registration number datasets
            document.getElementById('appointmentNumberSearch').dataset.regNumber = '';
            document.getElementById('walkinPatientSearch').dataset.regNumber = '';
        }

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

        // ============================================
        // SEARCH AND FILTER FUNCTIONS
        // ============================================

        // Enhanced search functionality
        function searchPrescriptions() {
            const searchTerm = document.getElementById('prescriptionSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#prescriptionsTableBody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // If both search and date filter are active, apply both
            if (document.getElementById('dateFilter').value) {
                filterByDate();
            }
        }

        // Filter by date
        function filterByDate() {
            const selectedDate = document.getElementById('dateFilter').value;
            const searchTerm = document.getElementById('prescriptionSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#prescriptionsTableBody tr');

            rows.forEach(row => {
                const dateCell = row.querySelector('td:nth-child(3) span').textContent;
                const text = row.textContent.toLowerCase();

                const matchesDate = !selectedDate || dateCell === selectedDate;
                const matchesSearch = !searchTerm || text.includes(searchTerm);

                if (matchesDate && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Clear search function
        function clearPrescriptionSearch() {
            const searchInput = document.getElementById('prescriptionSearch');
            searchInput.value = '';
            searchInput.focus();
            searchPrescriptions();
        }

        // Clear date filter function
        function clearDateFilter() {
            const dateFilter = document.getElementById('dateFilter');
            dateFilter.value = '';
            filterByDate();
        }

        // Clear all filters function
        function clearAllFilters() {
            document.getElementById('prescriptionSearch').value = '';
            document.getElementById('dateFilter').value = '';
            searchPrescriptions();
        }

        function highlightSearchTerm(text, searchTerm) {
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        // ============================================
        // WINDOW AND MODAL EVENT LISTENERS
        // ============================================

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['prescriptionModal', 'previewModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // ============================================
        // DOCUMENT READY
        // ============================================

        document.addEventListener('DOMContentLoaded', function() {
            // Appointment search with debounce
            const appointmentSearchInput = document.getElementById('appointmentNumberSearch');
            if (appointmentSearchInput) {
                appointmentSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        searchAppointments(searchTerm);
                    }, 300);
                });
            }

            // Patient search with debounce
            const patientSearchInput = document.getElementById('walkinPatientSearch');
            if (patientSearchInput) {
                patientSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value;
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        searchPatients(searchTerm);
                    }, 300);
                });
            }

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                const appointmentWrapper = document.querySelector('.appointment-search-wrapper');
                const patientWrapper = document.querySelector('.patient-search-wrapper');

                if (appointmentWrapper && !appointmentWrapper.contains(e.target)) {
                    document.getElementById('appointmentSearchResults').style.display = 'none';
                }

                if (patientWrapper && !patientWrapper.contains(e.target)) {
                    document.getElementById('patientSearchResults').style.display = 'none';
                }
            });

            // Prescription search clear button
            document.getElementById('prescriptionSearch').addEventListener('input', function() {
                const clearBtn = this.nextElementSibling;
                if (this.value.length > 0) {
                    clearBtn.style.display = 'block';
                } else {
                    clearBtn.style.display = 'none';
                }
            });

            // Date filter with clear button
            document.getElementById('dateFilter').addEventListener('change', function() {
                const wrapper = this.parentElement;
                const clearBtn = wrapper.querySelector('.date-clear-btn');

                if (this.value) {
                    wrapper.classList.add('has-date');
                    clearBtn.style.display = 'flex';
                    clearBtn.style.alignItems = 'center';
                    clearBtn.style.justifyContent = 'center';

                    if (this.offsetWidth - this.clientWidth > 20) {
                        clearBtn.style.right = '25px';
                    } else {
                        clearBtn.style.right = '5px';
                    }
                } else {
                    wrapper.classList.remove('has-date');
                    clearBtn.style.display = 'none';
                }

                filterByDate();
            });
        });
    </script>
</body>

</html>