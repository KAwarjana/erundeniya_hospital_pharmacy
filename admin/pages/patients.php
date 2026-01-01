<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();

// ---------- Dynamic Sidebar ----------
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
            $tooltip = 'title="Access Restricted" data-bs-toggle="tooltip"';
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

// Database connection
require_once '../../connection/connection.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $action = $_POST['action'];

        switch ($action) {
            case 'get_all_patients':
                getAllPatientsAjax();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Function to get all patients with pagination via AJAX
function getAllPatientsAjax()
{
    $searchTerm = $_POST['search'] ?? '';
    $provinceFilter = $_POST['province'] ?? '';
    $typeFilter = $_POST['type'] ?? '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $recordsPerPage = 6;
    $offset = ($page - 1) * $recordsPerPage;

    try {
        Database::setUpConnection();

        // Base query
        $query = "SELECT SQL_CALC_FOUND_ROWS p.*, 
                  (SELECT COUNT(*) FROM appointment WHERE patient_id = p.id) as total_visits,
                  (SELECT MAX(appointment_date) FROM appointment WHERE patient_id = p.id) as last_visit
                  FROM patient p 
                  WHERE 1=1";

        // Search filter
        if (!empty($searchTerm)) {
            $searchTerm = Database::$connection->real_escape_string($searchTerm);
            $query .= " AND (
                p.name LIKE '%$searchTerm%' OR 
                p.mobile LIKE '%$searchTerm%' OR 
                p.email LIKE '%$searchTerm%' OR 
                CONCAT(p.title, ' ', p.name) LIKE '%$searchTerm%' OR
                CONCAT('REG', LPAD(p.id, 5, '0')) LIKE '%$searchTerm%'
            )";
        }

        // Province filter
        if (!empty($provinceFilter)) {
            $provinceFilter = Database::$connection->real_escape_string($provinceFilter);
            $query .= " AND p.province = '$provinceFilter'";
        }

        // Type filter (new/old patients)
        if (!empty($typeFilter)) {
            if ($typeFilter === 'new') {
                $query .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($typeFilter === 'old') {
                $query .= " AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
        }

        $query .= " ORDER BY p.created_at DESC LIMIT $recordsPerPage OFFSET $offset";

        $result = Database::search($query);
        $patients = [];
        while ($row = $result->fetch_assoc()) {
            $patients[] = $row;
        }

        // Get total count
        $totalResult = Database::search("SELECT FOUND_ROWS() as total");
        $totalRows = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRows / $recordsPerPage);

        echo json_encode([
            'success' => true,
            'data' => $patients,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'total_records' => $totalRows
        ]);
    } catch (Exception $e) {
        error_log("Error getting patients: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to get patient statistics
function getPatientStats()
{
    try {
        Database::setUpConnection();

        // Total patients
        $totalResult = Database::search("SELECT COUNT(*) as total FROM patient");
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['total'];

        // New patients (registered in last 30 days)
        $newResult = Database::search("SELECT COUNT(*) as new_count FROM patient WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $newRow = $newResult->fetch_assoc();
        $newCount = $newRow['new_count'];

        // Patients with appointments this month
        $monthStart = date('Y-m-01');
        $activeResult = Database::search("SELECT COUNT(DISTINCT patient_id) as active FROM appointment WHERE appointment_date >= '$monthStart'");
        $activeRow = $activeResult->fetch_assoc();
        $activeCount = $activeRow['active'];

        return [
            'total' => $total,
            'new' => $newCount,
            'active' => $activeCount
        ];
    } catch (Exception $e) {
        error_log("Error getting patient stats: " . $e->getMessage());
        return [
            'total' => 0,
            'new' => 0,
            'active' => 0
        ];
    }
}

// Function to get illnesses from database
function getAllIllnesses()
{
    try {
        Database::setUpConnection();
        $result = Database::search("SELECT * FROM illness ORDER BY name ASC");
        $illnesses = [];
        while ($row = $result->fetch_assoc()) {
            $illnesses[] = $row;
        }
        return $illnesses;
    } catch (Exception $e) {
        error_log("Error getting illnesses: " . $e->getMessage());
        return [];
    }
}

// Get statistics and illnesses
$stats = getPatientStats();
$illnesses = getAllIllnesses();

// Sri Lankan Districts by Province
$sriLankaLocations = [
    'Western' => ['Colombo', 'Gampaha', 'Kalutara'],
    'Central' => ['Kandy', 'Matale', 'Nuwara Eliya'],
    'Southern' => ['Galle', 'Matara', 'Hambantota'],
    'Northern' => ['Jaffna', 'Kilinochchi', 'Mannar', 'Vavuniya', 'Mullaitivu'],
    'Eastern' => ['Batticaloa', 'Ampara', 'Trincomalee'],
    'North Western' => ['Kurunegala', 'Puttalam'],
    'North Central' => ['Anuradhapura', 'Polonnaruwa'],
    'Uva' => ['Badulla', 'Monaragala'],
    'Sabaragamuwa' => ['Ratnapura', 'Kegalle']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <title>Patient Management - Erundeniya Ayurveda Hospital</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

    <style>
        .patient-card {
            border-radius: 15px;
            background-color: #eafff3ff;
            box-shadow: 0 10px 30px rgba(46, 125, 50, 0.3);
        }

        .patient-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 20px;
            border-radius: 13px 13px 0 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            width: 95%;
            max-width: 1000px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close:hover {
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.6);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 14px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .illness-tag {
            display: inline-block;
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            color: white;
            padding: 6px 14px;
            margin: 3px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .patient-type-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .new-patient {
             background: #e8f5e8;
            color: #2e7d32;
        }

        .old-patient {
             background: #e3f2fd;
            color: #1976d2;
        }

        .illness-selector {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .illness-item {
            padding: 10px;
            margin: 4px 0;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .illness-item:hover {
            background-color: #e9ecef;
        }

        .illness-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
        }

        .illness-item label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .card--header--text {
            color: white;
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

        .modal-body {
            padding: 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .table-responsive {
            border-radius: 10px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            margin-bottom: 0;
            min-width: 800px;
        }

        .table thead th {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            color: white;
            border: none;
            padding: 15px 10px;
            white-space: nowrap;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-outline-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-outline-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 5px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: #2e7d32;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }

        .page-link:hover {
            background-color: #e8f5e9;
            border-color: #2e7d32;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
            color: white;
            border-color: #2e7d32;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #f8f9fa;
        }

        mark {
            background-color: #ffeb3b;
            color: #000;
            padding: 0 2px;
            border-radius: 2px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                margin: 5% auto;
            }

            .table {
                font-size: 12px;
            }

            .table thead th,
            .table tbody td {
                padding: 8px 5px;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }

            .card-body {
                padding: 15px;
            }
        }

        .d-flex.gap-1 {
            gap: 0.25rem;
        }

        /* Modal responsive adjustments for medium-large screens */
        @media (min-width: 1200px) and (max-width: 1510px) {

            /* Adjust modal positioning and sizing */
            .modal {
                padding-left: 0px !important;
            }

            .modal-content {
                max-width: calc(100vw - 300px) !important;
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
            }

            /* Prevent sidebar from moving down */
            .main-content {
                margin-left: 250px !important;
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
            .btn-secondary {
                padding: 8px 20px !important;
                font-size: 14px !important;
                min-height: 40px !important;
            }
        }

        /* Ensure proper stacking order */
        .modal {
            z-index: 1050 !important;
        }

        .sidenav {
            z-index: 1040 !important;
        }

        /* Fix for modal backdrop */
        .modal-backdrop {
            z-index: 1049 !important;
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
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm text-dark active">Patients</li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                        <!-- <div class="input-group input-group-outline">
                            <input type="text" class="form-control" placeholder="Search by name, mobile, register number..." id="globalSearch">
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
                                <span class="notification-badge">5</span>
                            </a>
                        </li>
                        <li class="nav-item d-flex align-items-center">
                            <a href="#" class="nav-link text-body font-weight-bold px-0">
                                <img src="../../img/user.png" width="20" height="20"> &nbsp;
                                <span class="d-none d-sm-inline"><?php echo $_SESSION['role'] ?? 'User'; ?></span>
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
                    <h3 class="mb-0 h4 font-weight-bolder">Patient Management</h3>
                    <p class="mb-4">Register and manage patient records efficiently</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Total Patients</p>
                                    <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">people</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">New Patients (30 days)</p>
                                    <h4 class="mb-0"><?php echo $stats['new']; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">person_add</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-sm-6">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Active This Month</p>
                                    <h4 class="mb-0"><?php echo $stats['active']; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">trending_up</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row mt-4">
                <!-- Patients List -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="row align-items-center mb-3">
                                <div class="col-md-6">
                                    <h6 class="mb-0">All Patients</h6>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-sm btn-primary" onclick="openAddPatientModal()">
                                        <i class="material-symbols-rounded text-sm">add</i> Add New Patient
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="input-group input-group-outline">
                                        <input type="text" class="form-control" placeholder="Search by name, mobile or register number..." id="patientSearch">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select class="form-control" id="provinceFilter">
                                        <option value="">All Provinces</option>
                                        <?php foreach (array_keys($sriLankaLocations) as $province): ?>
                                            <option value="<?php echo $province; ?>"><?php echo $province; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select class="form-control" id="typeFilter">
                                        <option value="">All Patients</option>
                                        <option value="new">New Patients</option>
                                        <option value="old">Regular Patients</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-xxs font-weight-bolder">Patient Details</th>
                                            <th class="text-uppercase text-xxs font-weight-bolder">Contact</th>
                                            <th class="text-uppercase text-xxs font-weight-bolder">Location</th>
                                            <th class="text-uppercase text-xxs font-weight-bolder">Type</th>
                                            <th class="text-uppercase text-xxs font-weight-bolder">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="patientsTableBody">
                                        <!-- Content loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div id="patientPagination" class="mt-3"></div>
                </div>

                <!-- Quick Add Patient Panel -->
                <div class="col-lg-4 mt-lg-0 mt-5">
                    <div class="card patient-card">
                        <div class="patient-header">
                            <h5 class="mb-1 card--header--text">
                                <i class="material-symbols-rounded">person_add</i>
                                Quick Register
                            </h5>
                            <p class="mb-0 opacity-8">Register new patient quickly</p>
                        </div>
                        <div class="card-body">
                            <form id="quickPatientForm" onsubmit="submitQuickPatient(event)">
                                <div class="form-group">
                                    <label>Title *</label>
                                    <select id="quickTitle" required>
                                        <option value="Rev.">Rev.</option>
                                        <option value="Mr.">Mr.</option>
                                        <option value="Mrs.">Mrs.</option>
                                        <option value="Miss.">Miss.</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" id="quickName" required placeholder="Enter full name">
                                </div>
                                <div class="form-group">
                                    <label>Mobile Number *</label>
                                    <input type="tel" id="quickMobile" required pattern="[0-9]{10}"
                                        placeholder="0771234567" maxlength="10">
                                </div>
                                <div class="form-group">
                                    <label>Gender *</label>
                                    <select id="quickGender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Age *</label>
                                    <input type="number" id="quickAge" required min="0" max="150" placeholder="Enter age">
                                </div>
                                <button type="submit" class="btn-primary w-100">
                                    <i class="material-symbols-rounded">save</i> Quick Register
                                </button>
                                <button type="button" class="btn-secondary w-100 mt-2" onclick="openAddPatientModal()">
                                    <i class="material-symbols-rounded">add</i> Full Registration
                                </button>
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

    <!-- Add/Edit Patient Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card--header--text">
                    <i class="material-symbols-rounded">person</i>
                    <span id="modalTitle">Add New Patient</span>
                </h4>
                <span class="close" onclick="closePatientModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="patientForm" onsubmit="submitPatientForm(event)">
                    <input type="hidden" id="patientId">

                    <!-- Personal Information -->
                    <h6 class="section-title">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Title *</label>
                                <select id="patientTitle" required>
                                    <option value="Rev.">Rev.</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Miss.">Miss.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" id="patientName" required placeholder="Enter full name">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Gender *</label>
                                <select id="patientGender" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Age *</label>
                                <input type="number" id="patientAge" required min="0" max="150" placeholder="Age">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="section-title">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mobile Number *</label>
                                <input type="tel" id="patientMobile" required pattern="[0-9]{10}"
                                    placeholder="0771234567" maxlength="10">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" id="patientEmail" placeholder="email@example.com">
                            </div>
                        </div>
                    </div>

                    <!-- Address Details -->
                    <h6 class="section-title">Address Details</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Address</label>
                                <textarea id="patientAddress" rows="2" placeholder="Enter full address"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Province *</label>
                                <select id="patientProvince" required onchange="updateDistricts()">
                                    <option value="">Select Province</option>
                                    <?php foreach (array_keys($sriLankaLocations) as $province): ?>
                                        <option value="<?php echo $province; ?>"><?php echo $province; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>District *</label>
                                <select id="patientDistrict" required>
                                    <option value="">Select Province First</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <h6 class="section-title">Medical Information</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Illnesses/Medical Conditions</label>
                                <div class="illness-selector" id="illnessSelector">
                                    <?php if (!empty($illnesses)): ?>
                                        <?php foreach ($illnesses as $illness): ?>
                                            <div class="illness-item">
                                                <input type="checkbox" name="illness" value="<?php echo htmlspecialchars($illness['name']); ?>" id="illness_<?php echo $illness['id']; ?>">
                                                <label for="illness_<?php echo $illness['id']; ?>"><?php echo htmlspecialchars($illness['name']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted">No illnesses found in database</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Additional Medical Notes</label>
                                <textarea id="medicalNotes" rows="3" placeholder="Any other medical conditions or notes..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <button type="submit" class="btn-primary w-100">
                                <i class="material-symbols-rounded">save</i> Save Patient
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn-secondary w-100" onclick="closePatientModal()">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Patient Modal -->
    <div id="viewPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card--header--text">
                    <i class="material-symbols-rounded">visibility</i> Patient Details
                </h4>
                <span class="close" onclick="closeViewPatientModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="section-title">Personal Information</h6>
                        <p><strong>Register Number:</strong> <span id="viewRegisterNumber"></span></p>
                        <p><strong>Name:</strong> <span id="viewName"></span></p>
                        <p><strong>Gender:</strong> <span id="viewGender"></span></p>
                        <p><strong>Age:</strong> <span id="viewAge"></span></p>
                        <p><strong>Registration Date:</strong> <span id="viewRegDate"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="section-title">Contact Information</h6>
                        <p><strong>Mobile:</strong> <span id="viewMobile"></span></p>
                        <p><strong>Email:</strong> <span id="viewEmail"></span></p>
                        <p><strong>Address:</strong> <span id="viewAddress"></span></p>
                        <p><strong>District:</strong> <span id="viewDistrict"></span></p>
                        <p><strong>Province:</strong> <span id="viewProvince"></span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="section-title">Medical Information</h6>
                        <p><strong>Medical Conditions:</strong></p>
                        <div id="viewIllnesses"></div>
                        <p class="mt-3"><strong>Additional Notes:</strong></p>
                        <p id="viewMedicalNotes" style="white-space: pre-line;"></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6 class="section-title">Visit History</h6>
                        <p><strong>Total Visits:</strong> <span id="viewTotalVisits"></span></p>
                        <p><strong>Last Visit:</strong> <span id="viewLastVisit"></span></p>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <button class="btn-primary w-100" onclick="editPatientFromView()">
                            <i class="material-symbols-rounded">edit</i> Edit Patient
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button class="btn-secondary w-100" onclick="closeViewPatientModal()">Close</button>
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
        // Sri Lanka locations data
        const sriLankaLocations = <?php echo json_encode($sriLankaLocations); ?>;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAllPatients();

            // Search functionality
            document.getElementById('patientSearch').addEventListener('input', function() {
                loadAllPatients(this.value);
            });

            document.getElementById('globalSearch').addEventListener('input', function() {
                const searchTerm = this.value;
                document.getElementById('patientSearch').value = searchTerm;
                loadAllPatients(searchTerm);
            });

            document.getElementById('provinceFilter').addEventListener('change', function() {
                loadAllPatients();
            });

            document.getElementById('typeFilter').addEventListener('change', function() {
                loadAllPatients();
            });
        });

        // Load all patients with pagination
        function loadAllPatients(searchTerm = '', page = 1) {
            searchTerm = searchTerm || document.getElementById('patientSearch').value;
            const provinceFilter = document.getElementById('provinceFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;

            fetch('patients.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_all_patients&search=' + encodeURIComponent(searchTerm) +
                        '&province=' + encodeURIComponent(provinceFilter) +
                        '&type=' + encodeURIComponent(typeFilter) +
                        '&page=' + page
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPatients(data.data, searchTerm, {
                            current_page: data.current_page,
                            total_pages: data.total_pages,
                            total_records: data.total_records
                        });
                    } else {
                        showNotification(data.message || 'Error loading patients', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading patients', 'error');
                });
        }

        // Highlight search term
        function highlightSearchTerm(text, searchTerm) {
            if (!searchTerm || !text) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        // Display patients in table
        function displayPatients(patients, searchTerm, pagination) {
            const tbody = document.getElementById('patientsTableBody');
            tbody.innerHTML = '';

            if (patients.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4">
            <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">person_off</i>
            <p class="text-muted mt-2">No patients found${searchTerm ? ' for "' + searchTerm + '"' : ''}</p>
        </td></tr>`;
                document.getElementById('patientPagination').innerHTML = '';
                return;
            }

            patients.forEach(function(patient) {
                const row = document.createElement('tr');
                const isNewPatient = new Date(patient.created_at) > new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);

                // Use the actual registration_number from database, or generate if null
                const registerNumber = patient.registration_number || ('REG' + String(patient.id).padStart(5, '0'));

                const highlightedName = highlightSearchTerm(patient.title + ' ' + patient.name, searchTerm);
                const highlightedRegNumber = highlightSearchTerm(registerNumber, searchTerm);
                const highlightedMobile = highlightSearchTerm(patient.mobile, searchTerm);
                const highlightedEmail = patient.email ? highlightSearchTerm(patient.email, searchTerm) : 'N/A';

                row.innerHTML = `<td>
            <div class="d-flex flex-column px-3">
                <h6 class="mb-0 text-sm font-weight-bold">${highlightedName}</h6>
                <p class="text-xs text-secondary mb-0">
                    ${highlightedRegNumber} | Age: ${patient.age || 'N/A'}
                </p>
                <p class="text-xs text-secondary mb-0">
                    Gender: ${patient.gender || 'N/A'}
                </p>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <span class="text-sm">${highlightedMobile}</span>
                <span class="text-xs text-secondary">${highlightedEmail}</span>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column">
                <span class="text-sm">${patient.district || 'N/A'}</span>
                <span class="text-xs text-secondary">${patient.province || 'N/A'}</span>
            </div>
        </td>
        <td>
            <span class="patient-type-badge ${isNewPatient ? 'new-patient' : 'old-patient'}">
                ${isNewPatient ? 'New' : 'Regular'}
            </span>
            <br>
            <span class="text-xs text-secondary">${patient.total_visits || '0'} visits</span>
        </td>
        <td>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-success" onclick="viewPatient(${patient.id})" title="View">
                    <i class="material-symbols-rounded text-sm">visibility</i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="editPatient(${patient.id})" title="Edit">
                    <i class="material-symbols-rounded text-sm">edit</i>
                </button>
            </div>
        </td>`;

                tbody.appendChild(row);
            });

            // Render pagination
            renderPagination(pagination);
        }

        // Render pagination
        function renderPagination(pagination) {
            let paginationHtml = '';
            const currentPage = parseInt(pagination.current_page);
            const totalPages = parseInt(pagination.total_pages);

            if (totalPages > 1) {
                paginationHtml += '<nav aria-label="Patient pagination"><ul class="pagination justify-content-center flex-wrap">';

                // Previous button
                paginationHtml += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadAllPatients('', ${currentPage - 1}); return false;">
                        <i class="material-symbols-rounded">chevron_left</i>
                    </a>
                </li>`;

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadAllPatients('', ${i}); return false;">${i}</a>
                    </li>`;
                }

                // Next button
                paginationHtml += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadAllPatients('', ${currentPage + 1}); return false;">
                        <i class="material-symbols-rounded">chevron_right</i>
                    </a>
                </li>`;

                paginationHtml += '</ul></nav>';
            }

            document.getElementById('patientPagination').innerHTML = paginationHtml;
        }

        // Update districts based on province selection
        function updateDistricts() {
            const province = document.getElementById('patientProvince').value;
            const districtSelect = document.getElementById('patientDistrict');

            districtSelect.innerHTML = '<option value="">Select District</option>';

            if (province && sriLankaLocations[province]) {
                sriLankaLocations[province].forEach(district => {
                    const option = document.createElement('option');
                    option.value = district;
                    option.textContent = district;
                    districtSelect.appendChild(option);
                });
            }
        }

        // Quick patient registration form
        function submitQuickPatient(event) {
            event.preventDefault();

            const patientData = {
                title: document.getElementById('quickTitle').value,
                name: document.getElementById('quickName').value,
                mobile: document.getElementById('quickMobile').value,
                gender: document.getElementById('quickGender').value,
                age: document.getElementById('quickAge').value
            };

            // Validate mobile number
            if (!/^[0-9]{10}$/.test(patientData.mobile)) {
                showNotification('Please enter a valid 10-digit mobile number', 'error');
                return;
            }

            fetch('save_patient.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(patientData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Patient registered successfully!', 'success');
                        document.getElementById('quickPatientForm').reset();
                        setTimeout(() => loadAllPatients(), 1500);
                    } else {
                        showNotification('Error: ' + (data.message || 'Failed to register patient'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error registering patient', 'error');
                });
        }

        // Full patient form submission
        function submitPatientForm(event) {
            event.preventDefault();

            const illnesses = [];
            document.querySelectorAll('#illnessSelector input[name="illness"]:checked').forEach(checkbox => {
                illnesses.push(checkbox.value);
            });

            const patientData = {
                id: document.getElementById('patientId')?.value || null,
                title: document.getElementById('patientTitle').value,
                name: document.getElementById('patientName').value,
                gender: document.getElementById('patientGender').value,
                age: document.getElementById('patientAge').value,
                mobile: document.getElementById('patientMobile').value,
                email: document.getElementById('patientEmail').value,
                address: document.getElementById('patientAddress').value,
                province: document.getElementById('patientProvince').value,
                district: document.getElementById('patientDistrict').value,
                illnesses: illnesses.join(','),
                medical_notes: document.getElementById('medicalNotes').value
            };

            // Validate mobile number
            if (!/^[0-9]{10}$/.test(patientData.mobile)) {
                showNotification('Please enter a valid 10-digit mobile number', 'error');
                return;
            }

            const url = patientData.id ? 'update_patient.php' : 'save_patient.php';

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(patientData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(
                            patientData.id ? 'Patient updated successfully!' : 'Patient registered successfully!',
                            'success'
                        );
                        closePatientModal();
                        setTimeout(() => loadAllPatients(), 1500);
                    } else {
                        showNotification('Error: ' + (data.message || 'Failed to save patient'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error saving patient', 'error');
                });
        }

        // Open add patient modal
        function openAddPatientModal() {
            document.getElementById('modalTitle').textContent = 'Add New Patient';
            document.getElementById('patientForm').reset();
            document.getElementById('patientId').value = '';
            document.querySelectorAll('#illnessSelector input[type="checkbox"]').forEach(cb => cb.checked = false);
            document.getElementById('patientModal').style.display = 'block';
        }

        function viewPatient(patientId) {
            window.location.href = `patient_view.php?id=${patientId}`;
        }

        // Edit patient
        function editPatient(patientId) {
            fetch('get_patient.php?id=' + patientId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const patient = data.patient;

                        document.getElementById('modalTitle').textContent = 'Edit Patient';
                        document.getElementById('patientId').value = patient.id;
                        document.getElementById('patientTitle').value = patient.title;
                        document.getElementById('patientName').value = patient.name;
                        document.getElementById('patientGender').value = patient.gender || '';
                        document.getElementById('patientAge').value = patient.age || '';
                        document.getElementById('patientMobile').value = patient.mobile;
                        document.getElementById('patientEmail').value = patient.email || '';
                        document.getElementById('patientAddress').value = patient.address || '';
                        document.getElementById('patientProvince').value = patient.province || '';

                        // Update districts and set district value
                        updateDistricts();
                        setTimeout(() => {
                            document.getElementById('patientDistrict').value = patient.district || '';
                        }, 100);

                        // Set illnesses
                        document.querySelectorAll('#illnessSelector input[type="checkbox"]').forEach(cb => cb.checked = false);
                        if (patient.illnesses) {
                            const illnessList = patient.illnesses.split(',');
                            illnessList.forEach(illness => {
                                const checkbox = document.querySelector(`#illnessSelector input[value="${illness.trim()}"]`);
                                if (checkbox) checkbox.checked = true;
                            });
                        }

                        document.getElementById('medicalNotes').value = patient.medical_notes || '';

                        document.getElementById('patientModal').style.display = 'block';
                    } else {
                        showNotification('Error loading patient details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading patient details', 'error');
                });
        }

        // Edit patient from view modal
        function editPatientFromView() {
            const patientId = document.getElementById('viewPatientModal').getAttribute('data-patient-id');
            closeViewPatientModal();
            editPatient(patientId);
        }

        // Close modals
        function closePatientModal() {
            document.getElementById('patientModal').style.display = 'none';
        }

        function closeViewPatientModal() {
            document.getElementById('viewPatientModal').style.display = 'none';
        }

        // Utility function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="material-symbols-rounded me-2">${type === 'success' ? 'check_circle' : 'error'}</i>
                    <strong>${message}</strong>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        function toggleNotifications() {
            showNotification('You have 5 new notifications!', 'success');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '?logout=1';
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const patientModal = document.getElementById('patientModal');
            const viewModal = document.getElementById('viewPatientModal');

            if (event.target === patientModal) {
                closePatientModal();
            }
            if (event.target === viewModal) {
                closeViewPatientModal();
            }
        });
    </script>
</body>

</html>