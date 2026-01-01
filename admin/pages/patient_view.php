<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();          // Admin / Receptionist
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

$currentUser = AuthManager::getCurrentUser();
$currentPage  = basename($_SERVER['PHP_SELF']);

/* ----------  role-based sidebar  ---------- */
$menuItems = [
    ['title' => 'Dashboard',     'url' => 'dashboard.php',     'icon' => 'dashboard',        'allowed_roles' => ['Admin']],
    ['title' => 'Appointments',  'url' => 'appointments.php',  'icon' => 'calendar_today',   'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Book Appointment', 'url' => 'book_appointments.php', 'icon' => 'add_circle', 'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Patients',      'url' => 'patients.php',      'icon' => 'people',           'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Bills',         'url' => 'create_bill.php',   'icon' => 'receipt',          'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Prescriptions', 'url' => 'prescription.php',  'icon' => 'medication',       'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'OPD Treatments', 'url' => 'opd.php',           'icon' => 'local_hospital',   'allowed_roles' => ['Admin', 'Receptionist']]
];

function hasAccessToPage($allowedRoles)
{
    if (!AuthManager::isLoggedIn()) return false;
    return in_array($_SESSION['role'], $allowedRoles);
}

function renderSidebarMenu($menuItems, $currentPage)
{
    $role = $_SESSION['role'] ?? 'Guest';
    foreach ($menuItems as $it) {
        $active = ($currentPage === $it['url']);
        $access = hasAccessToPage($it['allowed_roles']);
        $cls = $access ? ($active ? 'nav-link active bg-gradient-dark text-white' : 'nav-link text-dark')
            : 'nav-link text-muted';
        $lock = $access ? '' : ' <i class="fas fa-lock" style="font-size:10px;margin-left:5px;"></i>';
        echo '<li class="nav-item mt-3">
                <a class="' . $cls . '" href="' . ($access ? $it['url'] : '#') . '">
                  <i class="material-symbols-rounded opacity-10">' . $it['icon'] . '</i>
                  <span class="nav-link-text ms-1">' . $it['title'] . $lock . '</span>
                </a>
              </li>';
    }
}

/* ----------  fetch patient  ---------- */
try {
    Database::setUpConnection();
    $conn = Database::$connection;

    $patientId = $_GET['id'] ?? '';
    if (!$patientId) throw new Exception('Patient ID required');

    $sql = "SELECT p.*,
                   CONCAT(p.title, '. ', p.name) AS full_name,
                   TIMESTAMPDIFF(MONTH, p.created_at, CURDATE()) AS reg_months,
                   (SELECT COUNT(*) FROM appointment WHERE patient_id = p.id) AS total_visits,
                   (SELECT MAX(appointment_date) FROM appointment WHERE patient_id = p.id) AS last_visit
            FROM patient p
            WHERE p.id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    if (!$patient) throw new Exception('Patient not found');

    /* unread notification count */
    $pendingCount = $conn->query(
        "SELECT COUNT(*) AS c FROM notifications WHERE is_read=0 AND (user_id IS NULL OR user_id={$_SESSION['user_id']})"
    )->fetch_assoc()['c'] ?? 0;

    /* timeline dummy (until activity table) */
    $timeline = [
        ['title' => 'Patient Registered', 'desc' => 'Patient record created', 'time' => $patient['created_at']]
    ];
} catch (Exception $e) {
    die('<div class="alert alert-danger m-4">' . $e->getMessage() . '</div>');
}

/* ----------  helpers  ---------- */
function money($v)
{
    return 'Rs ' . number_format($v, 2);
}
function statusCls($s)
{
    return match ($s) {
        'Booked' => 'status-booked',
        'Confirmed' => 'status-confirmed',
        'Attended' => 'status-attended',
        'No-Show' => 'status-no-show',
        'Cancelled' => 'status-cancelled',
        default => 'status-booked'
    };
}
function payCls($s)
{
    return $s === 'Paid' ? 'payment-paid' : 'payment-pending';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Patient Details - Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

    <!-- Flatpickr CSS for Calendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
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

        .info-card {
            background: linear-gradient(45deg, #f8f9fa, #ffffff);
            border: 1px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .info-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            flex: 0 0 40%;
            margin-bottom: 0;
        }

        .info-value {
            font-size: 16px;
            color: #212529;
            flex: 1;
            text-align: right;
            word-wrap: break-word;
        }

        .info-row.full-width {
            display: block;
        }

        .info-row.full-width .info-label {
            flex: none;
            margin-bottom: 5px;
        }

        .info-row.full-width .info-value {
            text-align: left;
            flex: none;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            border: none;
            color: #fff;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            border: none;
            color: #fff;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-warning {
            background: linear-gradient(45deg, #ffc107, #e0a800);
            border: none;
            color: #fff;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: #fff;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .visit-history {
            text-align: left;
            width: 100%;
        }

        .visit-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
            font-size: 14px;
            transition: all .2s ease;
        }

        .visit-item:hover {
            background: #e9ecef;
            border-color: #4CAF50;
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

        .footer {
            margin-top: auto;
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

        .payment-status {
            padding: 2px 18px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .payment-paid {
            background: #d4edda;
            color: #155724;
        }

        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }

        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .icon-primary {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }

        .icon-info {
            background: linear-gradient(45deg, #2196F3, #1976d2);
            color: white;
        }

        /* Header alignment improvements */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 0 15px;
        }

        .page-header h3 {
            margin: 0;
            font-weight: 700;
        }

        .page-header p {
            margin: 5px 0 0 0;
            color: #6c757d;
        }

        /* Date display section */
        .date-info {
            background: linear-gradient(135deg, #4CAF50 0%, #227225ff 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .current-date {
            font-size: 14px;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .appointment-date {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .days-until {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .days-label {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .info-card {
                padding: 15px;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-label {
                flex: none;
                margin-bottom: 5px;
            }

            .info-value {
                text-align: left;
                flex: none;
            }

            .action-buttons {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .btn-primary,
            .btn-secondary,
            .btn-warning,
            .btn-danger {
                padding: 15px 20px;
                font-size: 14px;
                border-radius: 30px;
            }
        }

        @media (min-width: 407px) and (max-width: 650px) {
            .action-buttons {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
        }

        @media (min-width: 651px) and (max-width: 795px) {
            .action-buttons {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
        }

        @media (min-width: 796px) and (max-width: 991px) {
            .action-buttons {
                grid-template-columns: 1fr 1fr 1fr 1fr;
                gap: 8px;
            }
        }

        @media (min-width: 992px) and (max-width: 1520px) {
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        @media (min-width: 1521px) {
            .action-buttons {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
        }

        /* Fixed footer styles */
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

        /* List group improvements */
        .list-group-item {
            border-radius: 10px !important;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }

        /* Visit history styles */
        .visit-history {
            text-align: left;
            width: 100%;
        }

        .visit-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .visit-item:hover {
            background: #e9ecef;
            border-color: #4CAF50;
        }

        .visit-item:last-child {
            margin-bottom: 0;
        }

        .visit-item strong {
            color: #495057;
        }

        /* Animation for visit history toggle */
        #visitHistoryRow {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        #visitHistoryRow.show {
            opacity: 1;
            max-height: 400px;
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

        /* =========  collapsible related records  ========= */
        .rel-section {
            cursor: pointer
        }

        .rel-body {
            display: none;
            padding: .5rem 0 0 1.25rem
        }

        .rel-body.show {
            display: block
        }

        /* =====  NEW: clickable items  ===== */
        .visit-item.clickable {
            cursor: pointer;
            transition: all .2s ease
        }

        .visit-item.clickable:hover {
            background: #f0f8ff;
            transform: translateX(2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1)
        }

        .visit-item.clickable:active {
            transform: translateX(0)
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">

    <!-- ==========  sidebar  (unchanged)  ========== -->
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="<?= PageGuards::getHomePage() ?>">
                <img src="../../img/logoblack.png" class="navbar-brand-img" width="40" height="50" alt="main_logo">
                <span class="ms-1 text-sm text-dark fw-bold">Erundeniya</span>
            </a>
        </div>
        <hr class="dark horizontal mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav"><?= renderSidebarMenu($menuItems, $currentPage) ?></ul>
        </div>
        <div class="sidenav-footer">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="#" onclick="if(confirm('Logout?'))location='?logout=1'">
                        <i class="material-symbols-rounded opacity-5">logout</i><span class="nav-link-text ms-1">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ==========  main  ========== -->
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card" id="navbarBlur" data-scroll="true" style="background-color: white;">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="patients.php">Patients</a></li>
                        <li class="breadcrumb-item text-sm text-dark active"><?= htmlspecialchars($patient['registration_number']) ?></li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center searchbar--header">
                        <!-- <div class="input-group input-group-outline">
                            <input type="text" class="form-control" placeholder="Search patients..." id="globalSearch">
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

        <div class="container-fluid py-2 mt-2">
            <!-- page header -->
            <div class="page-header">
                <div>
                    <h3 class="mb-0 h4 font-weight-bolder">Patient Details</h3>
                    <p class="mb-0">Complete information for <?= htmlspecialchars($patient['full_name']) ?></p>
                </div>
                <div>
                    <span class="status-badge status-attended"><?= htmlspecialchars($patient['registration_number']) ?></span>
                </div>
            </div>

            <div class="row">
                <!-- left column -->
                <div class="col-lg-8">
                    <!-- patient overview -->
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle icon-primary"><i class="material-symbols-rounded">person</i></div>
                                <div class="ms-3 mb-3">
                                    <h6 class="mb-0">Patient Overview</h6>
                                    <p class="text-sm mb-0">Basic patient information</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <div class="info-row">
                                    <div class="info-label">Registration Number</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['registration_number']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['full_name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Gender</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Age</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['age'] ?? 'N/A') ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Mobile Number</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['mobile']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Patient Since</div>
                                    <div class="info-value"><?= $patient['reg_months'] ?> months</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Total Visits</div>
                                    <div class="info-value"><?= $patient['total_visits'] ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Last Visit</div>
                                    <div class="info-value"><?= $patient['last_visit'] ? date('l, F j, Y', strtotime($patient['last_visit'])) : 'No visits yet' ?></div>
                                </div>
                                <div class="info-row full-width">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?= nl2br(htmlspecialchars($patient['address'] ?? 'N/A')) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">District</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['district'] ?? 'N/A') ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Province</div>
                                    <div class="info-value"><?= htmlspecialchars($patient['province'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- medical information -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle icon-info"><i class="material-symbols-rounded">local_hospital</i></div>
                                <div class="ms-3 mb-3">
                                    <h6 class="mb-0">Medical Information</h6>
                                    <p class="text-sm mb-0">Medical conditions and notes</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <div class="info-row full-width">
                                    <div class="info-label">Medical Conditions / Notes</div>
                                    <div class="info-value"><?= nl2br(htmlspecialchars($patient['medical_notes'] ?? 'No conditions recorded')) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- right column -->
                <div class="col-lg-4">
                    <!-- date info -->
                    <div class="date-info">
                        <div class="current-date">Today: <span id="currentDate"></span></div>
                        <div class="appointment-date">Registered Since</div>
                        <div class="days-until"><?= date('F j, Y', strtotime($patient['created_at'])) ?></div>
                        <div class="days-label"><?= $patient['reg_months'] ?> months ago</div>
                    </div>

                    <!-- quick actions -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h6>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="bookAppointment()">
                                    <i class="material-symbols-rounded">add</i> Book Appointment
                                </button>
                                <button class="btn btn-secondary" onclick="editPatient()">
                                    <i class="material-symbols-rounded">edit</i> Edit Patient
                                </button>
                                <button class="btn btn-warning" onclick="sendEmail()">
                                    <i class="material-symbols-rounded">sms</i> Send Email
                                </button>
                                <button class="btn btn-danger" onclick="deletePatient()">
                                    <i class="material-symbols-rounded">delete</i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RELATED RECORDS  (collapsible) -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h6>Related Records</h6>
                        </div>

                        <!-- Appointments -->
                        <div class="card-body rel-section border-bottom py-2" onclick="loadRelated('appointments')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="material-symbols-rounded text-primary me-2">calendar_today</i>
                                    <span>Appointments</span>
                                </div>
                                <span class="badge bg-secondary rounded-pill" id="aptCount">-</span>
                            </div>
                            <div id="appointmentsList" class="rel-body"></div>
                        </div>

                        <!-- Prescriptions -->
                        <div class="card-body rel-section border-bottom py-2" onclick="loadRelated('prescriptions')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="material-symbols-rounded text-success me-2">medication</i>
                                    <span>Prescriptions</span>
                                </div>
                                <span class="badge bg-secondary rounded-pill" id="rxCount">-</span>
                            </div>
                            <div id="prescriptionsList" class="rel-body"></div>
                        </div>

                        <!-- Bills -->
                        <div class="card-body rel-section py-2" onclick="loadRelated('bills')">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="material-symbols-rounded text-warning me-2">receipt</i>
                                    <span>Bills</span>
                                </div>
                                <span class="badge bg-secondary rounded-pill" id="billCount">-</span>
                            </div>
                            <div id="billsList" class="rel-body"></div>
                        </div>
                    </div>

                    <!-- timeline (static dummy until you add activity table) -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h6>Activity Timeline</h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="text-sm fw-bold mb-1">Patient Registered</h6>
                                            <p class="text-xs text-muted mb-0">Patient record created</p>
                                        </div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($patient['created_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- footer -->
            <footer class="footer py-4">
                <div class="container-fluid">
                    <div class="row align-items-center justify-content-lg-between">
                        <div class="col-lg-6 mb-lg-0 mb-4">
                            <div class="copyright text-center text-sm text-muted text-lg-start">© <script>
                                    document.write(new Date().getFullYear())
                                </script>, design & develop by <a href="https://www.creative-tim.com" class="font-weight-bold" target="_blank">Evon Technologies Software Solution (PVT) Ltd.</a> All rights reserved.</div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <!-- scripts -->
        <script src="../assets/js/core/popper.min.js"></script>
        <script src="../assets/js/core/bootstrap.min.js"></script>
        <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
        <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
        <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>

        <script>
            /* ---- date calc ---- */
            function initDates() {
                const today = new Date();
                document.getElementById('currentDate').textContent = today.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            /* ---- actions ---- */
            function bookAppointment() {
                window.location.href = 'book_appointments.php?patient=<?= $patient['id'] ?>';
            }

            function editPatient() {
                window.location.href = 'patients.php?edit=<?= $patient['id'] ?>';
            }

            function sendEmail() {
                const patientEmail = '<?= addslashes($patient['email'] ?? '') ?>';
                const patientName = '<?= addslashes($patient['full_name']) ?>';

                if (!patientEmail || patientEmail === 'N/A') {
                    alert('No email address found for this patient. Please update patient information.');
                    return;
                }

                // Subject & body
                const subject = encodeURIComponent('Patient Communication – ' + patientName);
                const body = encodeURIComponent(
                    `Dear ${patientName},\n\n` +
                    `Please find the attached information or instructions from Erundeniya Ayurveda Hospital.\n\n` +
                    `If you have any questions, feel free to contact us.\n\n` +
                    `Best regards,\nErundeniya Ayurveda Hospital`
                );

                // Open default mail client
                window.location.href = `mailto:${patientEmail}?subject=${subject}&body=${body}`;
            }

            function deletePatient() {
                if (!confirm('Delete this patient? This action cannot be undone.')) return;
                fetch('delete_patient.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: <?= $patient['id'] ?>
                        })
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) {
                            alert('Patient deleted');
                            window.location.href = 'patients.php';
                        } else {
                            alert(d.message || 'Error');
                        }
                    }).catch(() => alert('Error'));
            }

            /* ----------  collapsible related records  ---------- */
            const PATIENT_ID = <?= (int)$patient['id'] ?>;

            function loadRelated(type) {
                const container = document.getElementById(type + 'List');
                const counter = document.getElementById(
                    type === 'appointments' ? 'aptCount' :
                    type === 'prescriptions' ? 'rxCount' : 'billCount'
                );
                if (container.dataset.loaded) { // toggle only
                    container.classList.toggle('show');
                    return;
                }
                fetch(`get_patient_related.php?patient=${PATIENT_ID}&type=${type}`)
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) throw new Error(d.message || 'Failed');
                        let html = '';
                        if (d.data.length === 0) {
                            html = '<div class="text-muted small">No records</div>';
                        } else {
                            d.data.forEach(r => {
                                if (type === 'appointments') {
                                    html += `
                                      <div class="visit-item clickable" onclick="window.open('${r.url}', '_blank')">
                                        <strong>${r.appointment_number}</strong> ·
                                        ${new Date(r.appointment_date).toLocaleDateString()} at ${r.appointment_time.slice(0,5)}
                                        <span class="badge ${statusCls(r.status)} ms-1">${r.status}</span>
                                      </div>`;
                                } else if (type === 'prescriptions') {
                                    html += `
                                      <div class="visit-item clickable" onclick="window.open('${r.url}', '_blank')">
                                      <strong>${r.prescription_number}</strong> ·
                                        ${new Date(r.created_at).toLocaleDateString()}
                                        <br><small>${r.prescription_text.substring(0,60)}…</small>
                                      </div>`;
                                } else { // bills
                                    html += `
                                      <div class="visit-item clickable" onclick="window.open('${r.url}', '_blank')">
                                        <strong>${r.bill_number}</strong> ·
                                        ${new Date(r.created_at).toLocaleDateString()}
                                        <br>Amount: <b>Rs ${parseFloat(r.final_amount||r.total_amount).toFixed(2)}</b>
                                        <span class="badge ${payCls(r.payment_status)} ms-1">${r.payment_status}</span>
                                      </div>`;
                                }
                            });
                        }
                        container.innerHTML = html;
                        container.dataset.loaded = '1';
                        counter.textContent = d.data.length;
                        container.classList.add('show');
                    })
                    .catch(err => {
                        container.innerHTML = `<div class="text-danger small">Error loading ${type}</div>`;
                        container.classList.add('show');
                    });
            }
            /* helpers (same colours as appointment_single_view.php) */
            function statusCls(s) {
                return {
                    'Booked': 'status-booked',
                    'Confirmed': 'status-confirmed',
                    'Attended': 'status-attended',
                    'No-Show': 'status-no-show',
                    'Cancelled': 'status-cancelled'
                } [s] || 'status-booked';
            }

            function payCls(s) {
                return s === 'Paid' ? 'payment-paid' : 'payment-pending';
            }

            document.addEventListener('DOMContentLoaded', initDates);
        </script>
</body>

</html>