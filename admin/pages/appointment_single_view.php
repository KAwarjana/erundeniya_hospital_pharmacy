<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();          // Admin / Receptionist
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

$currentUser = AuthManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

/* ----------  role-based sidebar  ---------- */
$menuItems = [
    ['title' => 'Dashboard',     'url' => 'dashboard.php',      'icon' => 'dashboard',        'allowed_roles' => ['Admin']],
    ['title' => 'Appointments',  'url' => 'appointments.php',   'icon' => 'calendar_today',   'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Book Appointment', 'url' => 'book_appointments.php', 'icon' => 'add_circle',   'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Patients',      'url' => 'patients.php',       'icon' => 'people',           'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Bills',         'url' => 'create_bill.php',    'icon' => 'receipt',          'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'Prescriptions', 'url' => 'prescription.php',   'icon' => 'medication',       'allowed_roles' => ['Admin', 'Receptionist']],
    ['title' => 'OPD Treatments', 'url' => 'opd.php',            'icon' => 'local_hospital',   'allowed_roles' => ['Admin', 'Receptionist']]
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

/* ----------  fetch appointment  ---------- */
try {
    Database::setUpConnection();
    $conn = Database::$connection;

    $aptNum = $_GET['appointment'] ?? '';
    if (!$aptNum) throw new Exception('Appointment number required');

    $sql = "SELECT a.*,
               CONCAT(p.title, p.name) AS patient_name,
               p.registration_number, p.mobile, p.email, p.address,
               TIMESTAMPDIFF(MONTH, p.created_at, CURDATE()) AS reg_months,
               ts.slot_time, ts.day_of_week
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.id
        LEFT JOIN time_slots ts ON a.slot_id = ts.id
        WHERE a.appointment_number = ?
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $aptNum);
    $stmt->execute();
    $apt = $stmt->get_result()->fetch_assoc();
    if (!$apt) throw new Exception('Appointment not found');

    /* visit history (last 5) */
    $hist = $conn->query(
        "SELECT appointment_date, appointment_time, status, note
           FROM appointment
          WHERE patient_id = {$apt['patient_id']} AND appointment_number != '{$apt['appointment_number']}'
          ORDER BY appointment_date DESC, appointment_time DESC
          LIMIT 5"
    )->fetch_all(MYSQLI_ASSOC);

    /* ---- notification count (unread) ---- */
    $pendingCount = $conn->query(
        "SELECT COUNT(*) AS c FROM notifications WHERE is_read=0 AND (user_id IS NULL OR user_id={$_SESSION['user_id']})"
    )->fetch_assoc()['c'] ?? 0;

    /* timeline (dummy until you add activity table) */
    $timeline = [
        ['title' => 'Appointment Booked', 'desc' => 'Patient booked appointment online', 'time' => $apt['created_at']],
        ['title' => 'Payment Confirmed',  'desc' => 'Online payment of ' . money($apt['total_amount']) . ' received', 'time' => $apt['created_at']]
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
?>

<!--  YOUR ORIGINAL HTML STARTS HERE – absolutely no class / css / style changes  -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Appointment Details - Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

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
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="appointments.php">Appointments</a></li>
                        <li class="breadcrumb-item text-sm text-dark active"><?= htmlspecialchars($apt['appointment_number']) ?></li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center searchbar--header">
                        <!-- <div class="input-group input-group-outline">
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

        <div class="container-fluid py-2 mt-2">
            <!-- page header -->
            <div class="page-header">
                <div>
                    <h3 class="mb-0 h4 font-weight-bolder">Appointment Details</h3>
                    <p class="mb-0">Complete information for appointment <?= htmlspecialchars($apt['appointment_number']) ?></p>
                </div>
                <div><span class="status-badge <?= statusCls($apt['status']) ?>"><?= htmlspecialchars($apt['status']) ?></span></div>
            </div>

            <div class="row">
                <!-- left column -->
                <div class="col-lg-8">
                    <!-- appointment overview -->
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle icon-primary"><i class="material-symbols-rounded">event</i></div>
                                <div class="ms-3 mb-3">
                                    <h6 class="mb-0">Appointment Overview</h6>
                                    <p class="text-sm mb-0">Basic appointment information</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <div class="info-row">
                                    <div class="info-label">Appointment Number</div>
                                    <div class="info-value"><?= htmlspecialchars($apt['appointment_number']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Booking Type</div>
                                    <div class="info-value"><?= ucfirst($apt['booking_type']) ?> Booking</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Appointment Date</div>
                                    <div class="info-value"><?= date('l, F j, Y', strtotime($apt['appointment_date'])) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Appointment Time</div>
                                    <div class="info-value"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Channeling Fee</div>
                                    <div class="info-value"><?= money($apt['channeling_fee']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Payment Status</div>
                                    <div class="info-value"><span class="payment-status payment-<?= strtolower($apt['payment_status']) ?>"><?= $apt['payment_status'] ?></span></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?= ucfirst($apt['payment_method']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Created At</div>
                                    <div class="info-value"><?= date('F j, Y - h:i A', strtotime($apt['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- patient information -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle icon-info"><i class="material-symbols-rounded">person</i></div>
                                <div class="ms-3 mb-3">
                                    <h6 class="mb-0">Patient Information</h6>
                                    <p class="text-sm mb-0">Details about the patient</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <div class="info-row">
                                    <div class="info-label">Patient Registration Number</div>
                                    <div class="info-value"><?= htmlspecialchars($apt['registration_number']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?= htmlspecialchars($apt['patient_name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Mobile Number</div>
                                    <div class="info-value"><?= htmlspecialchars($apt['mobile']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?= htmlspecialchars($apt['email'] ?? 'N/A') ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Patient Since</div>
                                    <div class="info-value"><span id="patientSince"><?= $apt['reg_months'] ?> months</span></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Previous Visits</div>
                                    <div class="info-value"><span style="cursor:pointer;color:#4CAF50;text-decoration:underline" onclick="toggleVisitHistory()"><?= count($hist) ?> Visits (View Details)</span></div>
                                </div>
                                <div class="info-row" id="visitHistoryRow" style="display:none;">
                                    <div class="info-label">Visit History</div>
                                    <div class="info-value">
                                        <div class="visit-history">
                                            <?php foreach ($hist as $v): ?>
                                                <div class="visit-item"><strong><?= date('M d, Y', strtotime($v['appointment_date'])) ?>:</strong> <?= htmlspecialchars($v['note'] ?: 'Consultation') ?> (<?= $v['status'] ?>)</div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-row full-width">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?= nl2br(htmlspecialchars($apt['address'] ?? 'N/A')) ?></div>
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
                        <div class="appointment-date"><?= date('l, F j, Y', strtotime($apt['appointment_date'])) ?></div>
                        <div class="days-until" id="daysUntil">0</div>
                        <div class="days-label" id="daysLabel">Days Until Appointment</div>
                    </div>

                    <!-- quick actions -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h6>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="markAttended()"><i class="material-symbols-rounded">check</i> Mark Attended</button>
                                <button class="btn btn-warning" onclick="markNoShow()"><i class="material-symbols-rounded">close</i> Mark No Show</button>
                                <button class="btn btn-secondary" onclick="sendEmail()"><i class="material-symbols-rounded">sms</i> Send Reminder</button>
                                <button class="btn btn-danger" onclick="cancelAppointment()"><i class="material-symbols-rounded">cancel</i> Cancel</button>
                            </div>
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
                                            <h6 class="text-sm fw-bold mb-1">Appointment Booked</h6>
                                            <p class="text-xs text-muted mb-0">Patient booked appointment online</p>
                                        </div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($apt['created_at'])) ?></small>
                                    </div>
                                </div>
                                <div class="timeline-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="text-sm fw-bold mb-1">Payment Confirmed</h6>
                                            <p class="text-xs text-muted mb-0">Online payment of <?= money($apt['total_amount']) ?> received</p>
                                        </div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($apt['created_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- related records -->
                    <div class="card mt-4">
                        <div class="card-header pb-0">
                            <h6>Related Records</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="create_bill.php?appointment=<?= urlencode($apt['appointment_number']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center"><i class="material-symbols-rounded text-primary me-2">receipt</i><span>Bill</span></div>
                                    <span class="badge bg-secondary rounded-pill"><?= $apt['payment_status'] === 'Paid' ? 'Created' : 'Pending' ?></span>
                                </a>
                                <a href="prescription.php?appointment=<?= urlencode($apt['appointment_number']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center"><i class="material-symbols-rounded text-success me-2">medication</i><span>Prescription</span></div>
                                    <span class="badge bg-secondary rounded-pill">Pending</span>
                                </a>
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
                const apt = new Date('<?= $apt['appointment_date'] ?>');
                const diff = Math.ceil((apt - today) / (1000 * 60 * 60 * 24));
                document.getElementById('currentDate').textContent = today.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                document.getElementById('daysUntil').textContent = Math.abs(diff);
                const label = document.getElementById('daysLabel');
                if (diff === 0) {
                    label.textContent = 'Appointment Today!';
                    document.querySelector('.date-info').style.background = 'linear-gradient(135deg,#4CAF50 0%,#45a049 100%)';
                } else if (diff > 0) {
                    label.textContent = (diff === 1 ? 'Day Until' : 'Days Until') + ' Appointment';
                } else {
                    label.textContent = (Math.abs(diff) === 1 ? 'Day Since' : 'Days Since') + ' Appointment';
                }
            }

            /* ---- actions ---- */
            function markAttended() {
                if (!confirm('Mark this appointment as attended?')) return;
                fetch('update_appointment_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            appointment_number: '<?= $apt['appointment_number'] ?>',
                            status: 'Attended'
                        })
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) {
                            location.reload();
                        } else {
                            alert(d.message);
                        }
                    }).catch(() => alert('Error'));
            }

            function markNoShow() {
                if (!confirm('Mark as No-Show?')) return;
                fetch('update_appointment_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            appointment_number: '<?= $apt['appointment_number'] ?>',
                            status: 'No-Show'
                        })
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) {
                            location.reload();
                        } else {
                            alert(d.message);
                        }
                    }).catch(() => alert('Error'));
            }

            function sendEmail() {
                const patientEmail = '<?= addslashes($apt['email'] ?? '') ?>';
                const patientName = '<?= addslashes($apt['patient_name']) ?>';
                const appDate = '<?= date('Y-m-d', strtotime($apt['appointment_date'])) ?>';
                const appTime = '<?= date('h:i A', strtotime($apt['appointment_time'])) ?>';

                if (!patientEmail || patientEmail === 'N/A') {
                    alert('No email address found for this patient. Please update patient information.');
                    return;
                }

                // subject & body
                const subject = encodeURIComponent('Appointment Reminder – ' + patientName);
                const body = encodeURIComponent(
                    `Dear ${patientName},\n\n` +
                    `This is a gentle reminder of your appointment scheduled on ${appDate} at ${appTime}.\n\n` +
                    `Please arrive 15 minutes early.\n\n` +
                    `Best regards,\nErundeniya Ayurveda Hospital`
                );

                // open default mail client
                window.location.href = `mailto:${patientEmail}?subject=${subject}&body=${body}`;
            }

            function cancelAppointment() {
                if (!confirm('Cancel this appointment?')) return;
                fetch('update_appointment_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            appointment_number: '<?= $apt['appointment_number'] ?>',
                            status: 'Cancelled'
                        })
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) {
                            location.reload();
                        } else {
                            alert(d.message);
                        }
                    }).catch(() => alert('Error'));
            }

            function toggleVisitHistory() {
                const row = document.getElementById('visitHistoryRow');
                row.style.display = row.style.display === 'none' ? 'flex' : 'none';
            }

            document.addEventListener('DOMContentLoaded', initDates);
        </script>
</body>

</html>