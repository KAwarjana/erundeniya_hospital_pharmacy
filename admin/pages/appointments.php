<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once '../../connection/connection.php';
require_once 'auth_manager.php';

// Get current user info
$currentUser = AuthManager::getCurrentUser();
$currentPageFile = basename($_SERVER['PHP_SELF']);

// Define menu items with access permissions
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

function renderSidebarMenu($menuItems, $currentPageFile)
{
    $currentRole = $_SESSION['role'] ?? 'Guest';

    foreach ($menuItems as $item) {
        $isActive = ($currentPageFile === $item['url']);
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

// Database connection and data fetching
try {
    Database::setUpConnection();

    // Get filter parameters
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

    // Pagination parameters
    $recordsPerPage = 10;
    $paginationPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $paginationPage = max(1, $paginationPage);
    $offset = ($paginationPage - 1) * $recordsPerPage;

    // Get total appointments count for display
    $totalAppointmentsQuery = "SELECT COUNT(*) as total FROM appointment";
    $totalAppointmentsResult = Database::search($totalAppointmentsQuery);
    $totalAppointmentsRow = $totalAppointmentsResult->fetch_assoc();
    $totalAppointmentsCount = $totalAppointmentsRow['total'] ?? 0;

    // Base query with COALESCE for multi-source patient data
    $countQuery = "SELECT COUNT(*) as total FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN appointment_requests ar ON a.request_id = ar.id
        WHERE 1=1";

    // Apply filters to count query
    if ($statusFilter !== 'all') {
        $escapedStatus = Database::$connection->real_escape_string($statusFilter);
        $countQuery .= " AND a.status = '$escapedStatus'";
    }

    if ($dateFilter) {
        $escapedDate = Database::$connection->real_escape_string($dateFilter);
        $countQuery .= " AND a.appointment_date = '$escapedDate'";
    }

    if ($searchTerm) {
        $escapedSearch = Database::$connection->real_escape_string($searchTerm);
        $countQuery .= " AND (a.appointment_number LIKE '%$escapedSearch%' OR 
                         COALESCE(p.name, ar.patient_name, a.temp_patient_name) LIKE '%$escapedSearch%' OR 
                         COALESCE(p.mobile, ar.mobile, a.temp_patient_mobile) LIKE '%$escapedSearch%')";
    }

    // Get total records
    $countResult = Database::search($countQuery);
    $countRow = $countResult->fetch_assoc();
    $totalRecords = $countRow['total'] ?? 0;
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Base query with COALESCE for multi-source patient data
    $baseQuery = "SELECT 
        a.*, 
        COALESCE(p.title, ar.title, a.temp_patient_title) as patient_title,
        COALESCE(p.name, ar.patient_name, a.temp_patient_name) as patient_name,
        COALESCE(p.mobile, ar.mobile, a.temp_patient_mobile) as patient_mobile,
        COALESCE(p.email, ar.email, a.temp_patient_email) as patient_email,
        COALESCE(ts.day_of_week, 
            CASE 
                WHEN DAYOFWEEK(a.appointment_date) IN (1,7) THEN 'Sunday'
                WHEN DAYOFWEEK(a.appointment_date) = 4 THEN 'Wednesday'
                ELSE DATE_FORMAT(a.appointment_date, '%W')
            END
        ) as day_of_week
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN appointment_requests ar ON a.request_id = ar.id
        LEFT JOIN time_slots ts ON a.slot_id = ts.id
        WHERE 1=1";

    // Apply filters
    if ($statusFilter !== 'all') {
        $escapedStatus = Database::$connection->real_escape_string($statusFilter);
        $baseQuery .= " AND a.status = '$escapedStatus'";
    }

    if ($dateFilter) {
        $escapedDate = Database::$connection->real_escape_string($dateFilter);
        $baseQuery .= " AND a.appointment_date = '$escapedDate'";
    }

    if ($searchTerm) {
        $escapedSearch = Database::$connection->real_escape_string($searchTerm);
        $baseQuery .= " AND (a.appointment_number LIKE '%$escapedSearch%' OR 
                         COALESCE(p.name, ar.patient_name, a.temp_patient_name) LIKE '%$escapedSearch%' OR 
                         COALESCE(p.mobile, ar.mobile, a.temp_patient_mobile) LIKE '%$escapedSearch%')";
    }

    $baseQuery .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $baseQuery .= " LIMIT $recordsPerPage OFFSET $offset";

    // Execute main query
    $appointmentsResult = Database::search($baseQuery);
    $appointments = [];
    while ($row = $appointmentsResult->fetch_assoc()) {
        $appointments[] = $row;
    }

    // Get statistics for cards
    $today = date('Y-m-d');

    $todayQuery = "SELECT COUNT(*) as count FROM appointment WHERE appointment_date = '$today'";
    $todayResult = Database::search($todayQuery);
    $todayRow = $todayResult->fetch_assoc();
    $todayCount = $todayRow['count'] ?? 0;

    $confirmedQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Confirmed'";
    $confirmedResult = Database::search($confirmedQuery);
    $confirmedRow = $confirmedResult->fetch_assoc();
    $confirmedCount = $confirmedRow['count'] ?? 0;

    $attendedQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Attended'";
    $attendedResult = Database::search($attendedQuery);
    $attendedRow = $attendedResult->fetch_assoc();
    $attendedCount = $attendedRow['count'] ?? 0;

    $noShowQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'No-Show'";
    $noShowResult = Database::search($noShowQuery);
    $noShowRow = $noShowResult->fetch_assoc();
    $noShowCount = $noShowRow['count'] ?? 0;

    $pendingQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Booked'";
    $pendingResult = Database::search($pendingQuery);
    $pendingRow = $pendingResult->fetch_assoc();
    $pendingCount = $pendingRow['count'] ?? 0;
} catch (Exception $e) {
    error_log("Appointments data error: " . $e->getMessage());
    $appointments = [];
    $todayCount = 0;
    $confirmedCount = 0;
    $attendedCount = 0;
    $noShowCount = 0;
    $pendingCount = 0;
    $totalRecords = 0;
    $totalPages = 1;
    $paginationPage = 1;
}

// Helper functions
function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'Booked':
            return 'status-booked';
        case 'Confirmed':
            return 'status-confirmed';
        case 'Attended':
            return 'status-attended';
        case 'No-Show':
            return 'status-no-show';
        case 'Cancelled':
            return 'status-cancelled';
        default:
            return 'status-booked';
    }
}

function formatCurrency($amount)
{
    return 'Rs. ' . number_format($amount, 2);
}

function getPaymentStatusColor($status)
{
    switch ($status) {
        case 'Paid':
            return 'text-success';
        case 'Pending':
            return 'text-warning';
        case 'Failed':
            return 'text-danger';
        case 'Refunded':
            return 'text-info';
        default:
            return 'text-secondary';
    }
}

// Helper function to generate pagination URLs
function getPageUrl($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Appointments - Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <!-- CSS Files -->
     <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* All your existing styles remain the same */
        .search-container {
            position: relative;
            width: 100%;
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            padding-right: 40px !important;
            width: 100%;
        }

        .clear-search-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            display: none;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .clear-search-btn:hover {
            background-color: #e9ecef;
            color: #dc3545;
        }

        .clear-search-btn.show {
            display: flex;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            color: #6c757d;
            pointer-events: none;
        }

        .search-input-with-icon {
            padding-left: 40px !important;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-booked {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-confirmed {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-attended {
            background: #e8f5e8;
            color: #4CAF50;
        }

        .status-no-show {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-cancelled {
            background: #ffebee;
            color: #f44336;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 5px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .filter-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .filter-btn:hover {
            background: #f5f5f5;
        }

        .filter-btn.active:hover {
            background: #45a049;
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

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
        }

        .stats-cards {
            margin-bottom: 30px;
        }

        .appointment-details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner.show {
            display: block;
        }

        .pending-registration-badge {
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
            width: fit-content;
        }

        @media (max-width: 768px) {
            .filter-buttons {
                justify-content: center;
            }

            .filter-btn {
                font-size: 12px;
                padding: 6px 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }

            .btn-sm {
                font-size: 10px;
                padding: 3px 6px;
            }

            .table-responsive {
                font-size: 12px;
            }

            .text-sm {
                font-size: 11px !important;
            }

            .text-xs {
                font-size: 10px !important;
            }

            .card-header h6 {
                font-size: 14px;
            }

            .breadcrumb-item {
                font-size: 11px !important;
            }

            .table th {
                font-size: 9px !important;
                padding: 8px 4px !important;
            }

            .table td {
                padding: 8px 4px !important;
                vertical-align: middle;
            }

            .table .d-none-mobile {
                display: none !important;
            }

            .col-xl-3.col-sm-6 {
                margin-bottom: 15px;
            }

            .icon.icon-md {
                width: 40px !important;
                height: 40px !important;
            }

            .card-header p-2 {
                padding: 15px !important;
            }

            .btn.bg-gradient-success {
                font-size: 12px;
                padding: 8px 12px;
            }

            .btn.bg-gradient-dark {
                font-size: 12px;
                padding: 8px 12px;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            .card {
                margin-bottom: 15px;
            }

            .ms-3 {
                margin-left: 10px !important;
            }

            h3.h4 {
                font-size: 18px !important;
            }

            .mb-4 p {
                font-size: 12px;
            }

            .table {
                font-size: 10px;
            }

            .status-badge {
                font-size: 9px;
                padding: 2px 4px;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .action-buttons .btn {
                width: 100%;
                margin-bottom: 2px;
            }

            .navbar-main {
                flex-wrap: wrap;
            }

            .searchbar--header {
                margin-top: 10px;
                width: 100%;
            }

            .navbar-nav {
                margin-top: 10px;
            }
        }

        @media (max-width: 992px) {
            .table-responsive {
                border: none;
            }

            .table {
                margin-bottom: 0;
            }
        }

        @media (max-width: 400px) {
            .btn {
                font-size: 11px;
                padding: 6px 8px;
            }

            .material-symbols-rounded {
                font-size: 16px !important;
            }
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .container-fluid {
            flex: 1;
        }

        .footer {
            margin-top: auto;
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

        .input-outline {
            background: none;
            border: 1px solid #d2d6da;
            border-radius: 0.375rem;
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
                <?php renderSidebarMenu($menuItems, $currentPageFile); ?>
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm text-dark active">Appointments</li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center searchbar--header">
                        <!-- <div class="input-group input-outline">
                            <input type="text" class="form-control" placeholder="Search appointments..." id="globalSearch">
                        </div> -->
                    </div>
                    <ul class="navbar-nav d-flex align-items-center  justify-content-end">
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
                                <span class="notification-badge" id="notificationCount"><?php echo $pendingCount; ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end px-2 py-3" id="notificationDropdown">
                                <div id="notificationsList">
                                    <!-- Notifications will be loaded here -->
                                </div>
                            </div>
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
                    <h3 class="mb-0 h4 font-weight-bolder">Appointments Management</h3>
                    <p class="mb-4">Manage all patient appointments and attendance tracking</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row stats-cards">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-header p-2 ps-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="text-sm mb-0 text-capitalize">Today's Appointments</p>
                                    <h4 class="mb-0" id="todayCount"><?php echo $todayCount; ?></h4>
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
                                    <p class="text-sm mb-0 text-capitalize">Confirmed</p>
                                    <h4 class="mb-0" id="confirmedCount"><?php echo $confirmedCount; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">check_circle</i>
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
                                    <p class="text-sm mb-0 text-capitalize">Attended</p>
                                    <h4 class="mb-0" id="attendedCount"><?php echo $attendedCount; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">person_check</i>
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
                                    <p class="text-sm mb-0 text-capitalize">No Show</p>
                                    <h4 class="mb-0" id="noShowCount"><?php echo $noShowCount; ?></h4>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10">person_off</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row">
                <div class="col-12">
                    <div class="filter-buttons">
                        <a href="?status=all" class="filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=booked" class="filter-btn <?php echo $statusFilter === 'booked' ? 'active' : ''; ?>">Booked</a>
                        <a href="?status=confirmed" class="filter-btn <?php echo $statusFilter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                        <a href="?status=attended" class="filter-btn <?php echo $statusFilter === 'attended' ? 'active' : ''; ?>">Attended</a>
                        <a href="?status=no-show" class="filter-btn <?php echo $statusFilter === 'no-show' ? 'active' : ''; ?>">No Show</a>
                        <a href="?status=cancelled" class="filter-btn <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                    </div>
                </div>
            </div>

            <!-- Search and Date Filter -->
            <div class="row search-filters-row">
                <div class="col-lg-6 col-md-12">
                    <form method="GET" action="appointments.php" id="searchForm">
                        <div class="search-container">
                            <div class="search-input-wrapper input-outline">
                                <i class="material-symbols-rounded search-icon">search</i>
                                <input type="text" class="form-control search-input search-input-with-icon" name="search" id="searchInput" placeholder="Search by appointment number, patient name, or mobile..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <button type="button" class="clear-search-btn" id="clearSearchBtn" title="Clear search">
                                    <i class="material-symbols-rounded" style="font-size: 16px;">close</i>
                                </button>
                            </div>
                        </div>
                        <?php if ($statusFilter !== 'all'): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <?php endif; ?>
                        <?php if ($dateFilter): ?>
                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-lg-3 col-md-6">
                    <form method="GET" action="appointments.php" id="dateForm">
                        <div class="input-group input-group-outline mb-3">
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>" onchange="this.form.submit()">
                        </div>
                        <?php if ($statusFilter !== 'all'): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <?php endif; ?>
                        <?php if ($searchTerm): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-lg-2 col-md-6">
                    <button class="btn bg-gradient-dark w-100" id="btnExportExcel">
                        <i class="material-symbols-rounded text-sm">download</i>
                        <span class="d-none d-xl-inline">Export Excel</span>
                    </button>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                            <h6 class="mb-2 mb-md-0">All Appointments (<?php echo $totalAppointmentsCount; ?>)</h6>
                            <a href="book_appointments.php" class="btn bg-gradient-success">
                                <i class="material-symbols-rounded">add</i> <span class="d-none d-sm-inline">New Appointment</span>
                            </a>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="loading-spinner" id="loadingSpinner">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Searching appointments...</p>
                                </div>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0" id="appointmentsTable">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Appointment</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Patient</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 d-none d-md-table-cell">Schedule</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 d-none d-lg-table-cell">Payment</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="appointmentsTableBody">
                                        <?php if (empty($appointments)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-muted" style="font-size: 48px;">event_busy</i>
                                                        <p class="text-muted mt-2">No appointments found</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr data-status="<?php echo strtolower($appointment['status']); ?>">
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm font-weight-bold"><?php echo htmlspecialchars($appointment['appointment_number']); ?></h6>
                                                                <p class="text-xs text-secondary mb-0 d-md-none">
                                                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>,
                                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                                </p>
                                                                <p class="text-xs text-secondary mb-0">
                                                                    <?php echo ucfirst($appointment['booking_type']); ?> Booking
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="text-sm font-weight-bold">
                                                                <?php echo htmlspecialchars($appointment['patient_title'] . ' ' . $appointment['patient_name']); ?>
                                                            </span>
                                                            <span class="text-xs text-secondary"><?php echo htmlspecialchars($appointment['patient_mobile']); ?></span>
                                                            <?php if ($appointment['patient_email']): ?>
                                                                <span class="text-xs text-secondary d-none d-lg-inline"><?php echo htmlspecialchars($appointment['patient_email']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (empty($appointment['patient_id']) && !empty($appointment['request_id'])): ?>
                                                                <span class="pending-registration-badge">
                                                                    <i class="material-symbols-rounded text-xs">pending</i> Pending Registration
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <div class="d-flex flex-column">
                                                            <span class="text-sm font-weight-bold">
                                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                            </span>
                                                            <span class="text-xs text-secondary">
                                                                <?php echo $appointment['day_of_week']; ?>
                                                            </span>
                                                            <span class="text-xs text-info">
                                                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                                        </span>
                                                        <div class="text-xs text-secondary d-lg-none mt-1">
                                                            <?php echo formatCurrency($appointment['total_amount']); ?> - <?php echo $appointment['payment_status']; ?>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-lg-table-cell">
                                                        <span class="text-sm font-weight-bold <?php echo getPaymentStatusColor($appointment['payment_status']); ?>">
                                                            <i class="material-symbols-rounded text-sm">
                                                                <?php echo $appointment['payment_status'] === 'Paid' ? 'check_circle' : 'pending'; ?>
                                                            </i>
                                                            <?php echo htmlspecialchars($appointment['payment_status']); ?>
                                                        </span>
                                                        <div class="text-xs text-secondary">
                                                            <?php echo formatCurrency($appointment['total_amount']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <?php if ($appointment['status'] === 'Booked' || $appointment['status'] === 'Confirmed'): ?>
                                                                <button class="btn btn-sm btn-outline-success" onclick="markAttendance('<?php echo $appointment['appointment_number']; ?>', 'Attended')">
                                                                    <i class="material-symbols-rounded text-sm">check</i>
                                                                    <span class="d-none d-xl-inline">Attended</span>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-warning" onclick="markAttendance('<?php echo $appointment['appointment_number']; ?>', 'No-Show')">
                                                                    <i class="material-symbols-rounded text-sm">close</i>
                                                                    <span class="d-none d-xl-inline">No Show</span>
                                                                </button>
                                                                <?php if ($appointment['status'] === 'Booked'): ?>
                                                                    <button class="btn btn-sm btn-outline-danger d-none d-md-inline-block" onclick="cancelAppointment('<?php echo $appointment['appointment_number']; ?>')">
                                                                        <i class="material-symbols-rounded text-sm">cancel</i>
                                                                        <span class="d-none d-xl-inline">Cancel</span>
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php elseif ($appointment['status'] === 'Attended'): ?>
                                                                <button class="btn btn-sm btn-dark" onclick="createBill('<?php echo $appointment['appointment_number']; ?>')">
                                                                    <i class="material-symbols-rounded text-sm">receipt</i>
                                                                    <span class="d-none d-xl-inline">Create Bill</span>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-info" onclick="viewDetails('<?php echo $appointment['appointment_number']; ?>')">
                                                                    <i class="material-symbols-rounded text-sm">visibility</i>
                                                                    <span class="d-none d-xl-inline">View</span>
                                                                </button>
                                                            <?php elseif ($appointment['status'] === 'No-Show'): ?>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="rescheduleAppointment('<?php echo $appointment['appointment_number']; ?>')">
                                                                    <i class="material-symbols-rounded text-sm">schedule</i>
                                                                    <span class="d-none d-xl-inline">Reschedule</span>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-info" onclick="viewDetails('<?php echo $appointment['appointment_number']; ?>')">
                                                                    <i class="material-symbols-rounded text-sm">visibility</i>
                                                                    <span class="d-none d-xl-inline">View</span>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-info" onclick="viewDetails('<?php echo $appointment['appointment_number']; ?>')">
                                                                    <i class="material-symbols-rounded text-sm">visibility</i>
                                                                    <span class="d-none d-xl-inline">View</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Appointments pagination">
                            <ul class="pagination justify-content-center flex-wrap">
                                <!-- Previous button -->
                                <li class="page-item <?php echo $paginationPage <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo getPageUrl($paginationPage - 1); ?>" tabindex="-1">
                                        <i class="material-symbols-rounded">chevron_left</i>
                                    </a>
                                </li>

                                <?php
                                // Calculate page range to display
                                $maxPagesToShow = 5;
                                $startPage = max(1, $paginationPage - floor($maxPagesToShow / 2));
                                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                $startPage = max(1, $endPage - $maxPagesToShow + 1);

                                // Show first page and ellipsis if needed
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPageUrl(1); ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $paginationPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPageUrl($i); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPageUrl($totalPages); ?>">
                                            <?php echo $totalPages; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next button -->
                                <li class="page-item <?php echo $paginationPage >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo getPageUrl($paginationPage + 1); ?>">
                                        <i class="material-symbols-rounded">chevron_right</i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 text-center">
                        <p class="text-muted text-sm mt-2">
                            Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> appointments
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="footer py-4  ">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-lg-between">
                    <div class="mb-lg-0 mb-4">
                        <div class="copyright text-center text-sm text-muted text-lg-start">
                            © <script>
                                document.write(new Date().getFullYear())
                            </script>,
                            design and develop by
                            <a href="https://www.creative-tim.com         " class="font-weight-bold" target="_blank">Evon Technologies Software Solution (PVT) Ltd.</a>
                            All rights received.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>

    <script>

let searchTimeout;
let isRealTimeSearch = true;

const searchInput = document.getElementById('searchInput');
const clearSearchBtn = document.getElementById('clearSearchBtn');
const loadingSpinner = document.getElementById('loadingSpinner');
const appointmentsTableBody = document.getElementById('appointmentsTableBody');
const appointmentsTable = document.getElementById('appointmentsTable');

function initializeSearch() {
    if (!searchInput || !clearSearchBtn) {
        console.error('Search elements not found');
        return;
    }
    
    searchInput.addEventListener('input', handleSearchInput);
    searchInput.addEventListener('keypress', handleSearchKeypress);
    clearSearchBtn.addEventListener('click', clearSearch);
    searchInput.addEventListener('input', toggleClearButton);
    toggleClearButton();
}

function handleSearchInput(e) {
    const searchTerm = e.target.value.trim();
    toggleClearButton();

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        if (isRealTimeSearch && searchTerm.length > 0) {
            performRealTimeSearch(searchTerm);
        } else if (searchTerm.length === 0) {
            reloadCurrentPage();
        }
    }, 300);
}

function handleSearchKeypress(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const searchTerm = e.target.value.trim();
        if (searchTerm.length > 0) {
            performRealTimeSearch(searchTerm);
        } else {
            reloadCurrentPage();
        }
    }
}

function toggleClearButton() {
    if (searchInput && clearSearchBtn) {
        if (searchInput.value.trim().length > 0) {
            clearSearchBtn.classList.add('show');
        } else {
            clearSearchBtn.classList.remove('show');
        }
    }
}

function clearSearch() {
    if (searchInput) {
        searchInput.value = '';
    }
    if (clearSearchBtn) {
        clearSearchBtn.classList.remove('show');
    }
    reloadCurrentPage();
}

function reloadCurrentPage() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete('search');
    window.location.href = currentUrl.toString();
}

function performRealTimeSearch(searchTerm) {
    const currentUrl = new URL(window.location.href);
    const status = currentUrl.searchParams.get('status') || 'all';
    const date = currentUrl.searchParams.get('date') || '';
    const page = currentUrl.searchParams.get('page') || '1';

    showLoading();

    fetch('search_appointments_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                search: searchTerm,
                status: status,
                date: date,
                page: page
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAppointmentsTable(data.appointments);
                updateStatistics(data.statistics);
                if (typeof updatePagination === 'function') {
                    updatePagination(data.pagination);
                }
                showNotification(`Found ${data.total_records} appointment(s)`, 'success');
            } else {
                showNotification('Search failed: ' + data.message, 'error');
                displayNoResults();
            }
            hideLoading();
        })
        .catch(error => {
            console.error('Search error:', error);
            showNotification('Search request failed', 'error');
            displayNoResults();
            hideLoading();
        });
}

function showLoading() {
    if (appointmentsTable) appointmentsTable.style.display = 'none';
    if (loadingSpinner) loadingSpinner.classList.add('show');
}

function hideLoading() {
    if (loadingSpinner) loadingSpinner.classList.remove('show');
    if (appointmentsTable) appointmentsTable.style.display = 'table';
}

function displayNoResults() {
    if (!appointmentsTableBody) return;
    
    appointmentsTableBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center py-4">
                <div class="d-flex flex-column align-items-center">
                    <i class="material-symbols-rounded text-muted" style="font-size: 48px;">search_off</i>
                    <p class="text-muted mt-2">No appointments found for your search</p>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="clearSearch()">
                        <i class="material-symbols-rounded" style="font-size: 14px;">refresh</i> Clear Search
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function updateAppointmentsTable(appointments) {
    if (!appointmentsTableBody) return;
    
    if (appointments.length === 0) {
        displayNoResults();
        return;
    }

    let html = '';
    appointments.forEach(appointment => {
        const statusClass = getStatusBadgeClass(appointment.status);
        const paymentColor = getPaymentStatusColor(appointment.payment_status);
        const formattedDate = formatDate(appointment.appointment_date);
        const formattedTime = formatTime(appointment.appointment_time);

        html += `
            <tr data-status="${appointment.status.toLowerCase()}">
                <td>
                    <div class="d-flex px-2 py-1">
                        <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm font-weight-bold">${escapeHtml(appointment.appointment_number)}</h6>
                            <p class="text-xs text-secondary mb-0 d-md-none">
                                ${formattedDate}, ${formattedTime}
                            </p>
                            <p class="text-xs text-secondary mb-0">
                                ${capitalizeFirst(appointment.booking_type)} Booking
                            </p>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-sm font-weight-bold">
                            ${escapeHtml(appointment.patient_title + ' ' + appointment.patient_name)}
                        </span>
                        <span class="text-xs text-secondary">${escapeHtml(appointment.patient_mobile)}</span>
                        ${appointment.patient_email ? `<span class="text-xs text-secondary d-none d-lg-inline">${escapeHtml(appointment.patient_email)}</span>` : ''}
                        ${(!appointment.patient_id && appointment.request_id) ? `
                            <span class="pending-registration-badge">
                                <i class="material-symbols-rounded text-xs">pending</i> Pending Registration
                            </span>
                        ` : ''}
                    </div>
                </td>
                <td class="d-none d-md-table-cell">
                    <div class="d-flex flex-column">
                        <span class="text-sm font-weight-bold">${formattedDate}</span>
                        <span class="text-xs text-secondary">${appointment.day_of_week}</span>
                        <span class="text-xs text-info">${formattedTime}</span>
                    </div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${escapeHtml(appointment.status)}
                    </span>
                    <div class="text-xs text-secondary d-lg-none mt-1">
                        ${formatCurrency(appointment.total_amount)} - ${appointment.payment_status}
                    </div>
                </td>
                <td class="d-none d-lg-table-cell">
                    <span class="text-sm font-weight-bold ${paymentColor}">
                        <i class="material-symbols-rounded text-sm">
                            ${appointment.payment_status === 'Paid' ? 'check_circle' : 'pending'}
                        </i>
                        ${escapeHtml(appointment.payment_status)}
                    </span>
                    <div class="text-xs text-secondary">
                        ${formatCurrency(appointment.total_amount)}
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        ${getActionButtons(appointment)}
                    </div>
                </td>
            </tr>
        `;
    });

    appointmentsTableBody.innerHTML = html;
}

function updateStatistics(statistics) {
    const updates = {
        'todayCount': statistics.today_count,
        'confirmedCount': statistics.confirmed_count,
        'attendedCount': statistics.attended_count,
        'noShowCount': statistics.no_show_count,
        'notificationCount': statistics.pending_count
    };

    Object.keys(updates).forEach(id => {
        const element = document.getElementById(id);
        if (element) element.textContent = updates[id];
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function getStatusBadgeClass(status) {
    const classes = {
        'Booked': 'status-booked',
        'Confirmed': 'status-confirmed',
        'Attended': 'status-attended',
        'No-Show': 'status-no-show',
        'Cancelled': 'status-cancelled'
    };
    return classes[status] || 'status-booked';
}

function getPaymentStatusColor(status) {
    const colors = {
        'Paid': 'text-success',
        'Pending': 'text-warning',
        'Failed': 'text-danger',
        'Refunded': 'text-info'
    };
    return colors[status] || 'text-secondary';
}

function getActionButtons(appointment) {
    let buttons = '';

    if (appointment.status === 'Booked' || appointment.status === 'Confirmed') {
        buttons += `
            <button class="btn btn-sm btn-outline-success" onclick="markAttendance('${appointment.appointment_number}', 'Attended')">
                <i class="material-symbols-rounded text-sm">check</i>
                <span class="d-none d-xl-inline">Attended</span>
            </button>
            <button class="btn btn-sm btn-outline-warning" onclick="markAttendance('${appointment.appointment_number}', 'No-Show')">
                <i class="material-symbols-rounded text-sm">close</i>
                <span class="d-none d-xl-inline">No Show</span>
            </button>
        `;
        if (appointment.status === 'Booked') {
            buttons += `
                <button class="btn btn-sm btn-outline-danger d-none d-md-inline-block" onclick="cancelAppointment('${appointment.appointment_number}')">
                    <i class="material-symbols-rounded text-sm">cancel</i>
                    <span class="d-none d-xl-inline">Cancel</span>
                </button>
            `;
        }
    } else if (appointment.status === 'Attended') {
        buttons += `
            <button class="btn btn-sm btn-dark" onclick="createBill('${appointment.appointment_number}')">
                <i class="material-symbols-rounded text-sm">receipt</i>
                <span class="d-none d-xl-inline">Create Bill</span>
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="viewDetails('${appointment.appointment_number}')">
                <i class="material-symbols-rounded text-sm">visibility</i>
                <span class="d-none d-xl-inline">View</span>
            </button>
        `;
    } else if (appointment.status === 'No-Show') {
        buttons += `
            <button class="btn btn-sm btn-outline-primary" onclick="rescheduleAppointment('${appointment.appointment_number}')">
                <i class="material-symbols-rounded text-sm">schedule</i>
                <span class="d-none d-xl-inline">Reschedule</span>
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="viewDetails('${appointment.appointment_number}')">
                <i class="material-symbols-rounded text-sm">visibility</i>
                <span class="d-none d-xl-inline">View</span>
            </button>
        `;
    } else {
        buttons += `
            <button class="btn btn-sm btn-outline-info" onclick="viewDetails('${appointment.appointment_number}')">
                <i class="material-symbols-rounded text-sm">visibility</i>
                <span class="d-none d-xl-inline">View</span>
            </button>
        `;
    }

    return buttons;
}

function markAttendance(appointmentId, status) {
    if (confirm(`Mark ${appointmentId} as ${status}?`)) {
        fetch('update_appointment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_number: appointmentId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`Appointment ${appointmentId} marked as ${status}`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to update appointment status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            });
    }
}

// UPDATED: Create Bill function now registers patient first
function createBill(appointmentId) {
    // Show loading
    showNotification('Registering patient and preparing bill...', 'info');
    
    // First register the patient
    fetch('register_patient_from_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            appointment_number: appointmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Redirect to create bill page
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showNotification('Registration failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred during patient registration', 'error');
    });
}

function cancelAppointment(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        markAttendance(appointmentId, 'Cancelled');
    }
}

function rescheduleAppointment(appointmentId) {
    window.location.href = `book_appointments.php?reschedule=${appointmentId}`;
}

function viewDetails(appointmentId) {
    window.location.href = `appointment_single_view.php?appointment=${appointmentId}`;
}

function exportAppointments() {
    const u = new URL(window.location.href);
    const params = new URLSearchParams(u.search);
    const exportUrl = `export_appointments.php?${params.toString()}`;

    showNotification('Preparing Excel file...', 'info');

    const a = document.createElement('a');
    a.href = exportUrl;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    setTimeout(() => showNotification('Excel file downloaded!', 'success'), 1500);
}

function showNotification(message, type = 'info') {
    const colors = {
        success: '#4caf50',
        info: '#2196f3',
        warning: '#ff9800',
        error: '#f44336'
    };

    const icons = {
        success: 'check_circle',
        info: 'info',
        warning: 'warning',
        error: 'error'
    };

    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    toast.style.cssText = `position: fixed; top: 20px; right: 20px; background: ${colors[type]}; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; display: flex; align-items: center; gap: 12px; min-width: 300px; max-width: 500px; animation: slideIn 0.3s ease-out; font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 500;`;

    const iconElement = document.createElement('span');
    iconElement.className = 'material-symbols-rounded';
    iconElement.style.cssText = 'font-size: 24px; flex-shrink: 0;';
    iconElement.textContent = icons[type] || icons.info;

    const messageElement = document.createElement('span');
    messageElement.style.cssText = 'flex: 1; line-height: 1.4;';
    messageElement.textContent = message;

    toast.appendChild(iconElement);
    toast.appendChild(messageElement);

    if (!document.getElementById('toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } } @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } } .custom-toast:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.2); transform: translateY(-2px); transition: all 0.2s ease; }`;
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }, 3000);

    toast.addEventListener('click', () => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    });
}

function toggleNotifications() {
    showNotification('Notifications feature coming soon!', 'info');
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '?logout=1';
    }
}

function filterAppointments(status) {
    const currentUrl = new URL(window.location.href);
    if (status === 'all') {
        currentUrl.searchParams.delete('status');
    } else {
        currentUrl.searchParams.set('status', status);
    }
    window.location.href = currentUrl.toString();
}

function getPageUrl(page) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('page', page);
    return currentUrl.toString();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing...');
    
    // Initialize search functionality
    initializeSearch();
    
    // Export button - with null check
    const exportBtn = document.getElementById('btnExportExcel');
    if (exportBtn) {
        console.log('Export button found, attaching listener');
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            exportAppointments();
        });
    } else {
        console.warn('Export button not found');
    }
    
    // Global search - only if element exists (this is in navbar)
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        console.log('Global search found');
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            globalSearch.addEventListener('input', function() {
                searchInput.value = this.value;
                if (this.value.trim().length > 2 || this.value.trim().length === 0) {
                    performRealTimeSearch(this.value.trim());
                }
            });
        }
    }
    
    console.log('Initialization complete');
});
    </script>

</body>

</html>