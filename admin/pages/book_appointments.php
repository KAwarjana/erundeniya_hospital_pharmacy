<?php
require_once 'page_guards.php';
PageGuards::guardAppointments();

require_once 'auth_manager.php';
require_once '../../connection/connection.php';

Database::setUpConnection();

$currentUser = AuthManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

$menuItems = [
    ['title' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'dashboard', 'allowed_roles' => ['Admin'], 'show_to_all' => true],
    ['title' => 'Appointments', 'url' => 'appointments.php', 'icon' => 'calendar_today', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
    ['title' => 'Book Appointment', 'url' => 'book_appointments.php', 'icon' => 'add_circle', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
    ['title' => 'Patients', 'url' => 'patients.php', 'icon' => 'people', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
    ['title' => 'Bills', 'url' => 'create_bill.php', 'icon' => 'receipt', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
    ['title' => 'Prescriptions', 'url' => 'prescription.php', 'icon' => 'medication', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
    ['title' => 'OPD Treatments', 'url' => 'opd.php', 'icon' => 'local_hospital', 'allowed_roles' => ['Admin', 'Receptionist'], 'show_to_all' => true],
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
    if (!AuthManager::isLoggedIn()) return false;
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
        echo '</span></a></li>';
    }
}

Database::setUpConnection();

// Get pending appointments count for notification badge
try {
    $pendingQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Booked'";
    $pendingResult = Database::search($pendingQuery);
    $pendingCount = $pendingResult->fetch_assoc()['count'];
} catch (Exception $e) {
    error_log("Pending count error: " . $e->getMessage());
    $pendingCount = 0;
}

$currentUser = AuthManager::getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <title>Book Appointment - Admin</title>

    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }

        .slot-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fff;
        }

        .slot-card.available {
            border-color: #4CAF50;
            background: #f8fff8;
        }

        .slot-card.available:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .slot-card.booked {
            border-color: #f44336;
            background: #fff5f5;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .slot-card.blocked {
            border-color: #ff9800;
            background: #fff8e1;
        }

        .slot-card.selected {
            border-color: #2196F3;
            background: #e3f2fd;
            transform: scale(1.05);
        }

        .slot-time {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .slot-status {
            font-size: 11px;
            color: #666;
        }

        .action-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-block-action {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-block {
            background: #ff9800;
            color: white;
        }

        .btn-block:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .btn-unblock {
            background: #4CAF50;
            color: white;
        }

        .btn-unblock:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }

        .btn-clear {
            background: #9E9E9E;
            color: white;
        }

        .btn-book {
            background: #2196F3;
            color: white;
        }

        .stats-card {
            padding: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
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
            max-width: 600px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(45deg, #4CAF50, #2a8a2dff);
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
            opacity: 0.8;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 40px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
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
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-left: 15px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            gap: 8px;
        }

        .form-group label .material-symbols-rounded {
            font-size: 18px;
            color: #666;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .btn-with-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-with-icon .material-symbols-rounded {
            font-size: 18px;
            line-height: 1;
        }

        #consultationDate {
            cursor: pointer;
        }

        .book--appointment--input {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
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

        .table-sm th,
        .table-sm td {
            padding: 8px;
            vertical-align: middle;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-warning {
           background-color: #fff3e0 !important;
            color: #f57c00 !important;
            border-radius: 2rem;
        }

        .badge-info {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
            border-radius: 2rem;
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
        }

        .flatpickr-day.rescheduled-day {
            position: relative;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            font-weight: 600;
        }

        .rescheduled-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 8px;
            color: #667eea;
        }

        .flatpickr-day.rescheduled-day:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        }

        .rescheduled-notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <!-- Sidebar -->
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand px-4 py-3 m-0" href="dashboard.php">
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
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item text-sm text-dark active">Book Appointments</li>
                    </ol>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <div class="ms-md-auto pe-md-3 d-flex align-items-center searchbar--header">
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
                                <span class="notification-badge" id="notificationCount"><?php echo $pendingCount; ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end px-2 py-3" id="notificationDropdown">
                                <div id="notificationsList">
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
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h5>Book Appointment & Manage Slots</h5>
                            <p class="text-sm">Book appointments manually or block/unblock time slots</p>
                        </div>
                        <div class="card-body">
                            <!-- Date Selection -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                        <span class="material-symbols-rounded" style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">event</span>
                                        <span>Select Consultation Date</span>
                                    </label>
                                    <input type="text" id="consultationDate" class="form-control book--appointment--input" placeholder="Click to select date" readonly>
                                    <small class="text-muted">Only Wednesdays and Sundays are available</small>
                                </div>
                                <div class="col-md-6 mt-md-0 mt-sm-3">
                                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                        <span class="material-symbols-rounded" style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">edit_note</span>
                                        <span>Reason for Blocking (Optional)</span>
                                    </label>
                                    <input type="text" id="blockReason" class="form-control book--appointment--input" placeholder="e.g., Doctor unavailable">
                                </div>
                            </div>

                            <!-- Holiday Management Section -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header pb-0">
                                            <div class="row align-items-center g-2">
                                                <div class="col-12 col-sm-8 col-lg-9">
                                                    <h5 class="mb-1">Holiday & Consultation Day Management</h5>
                                                    <p class="text-sm mb-0">Manage holidays and reschedule consultation days</p>
                                                </div>
                                                <div class="col-12 col-sm-4 col-lg-3 text-sm-end mt-3 mt-sm-3">
                                                    <button class="btn btn-primary w-100" onclick="openAddHolidayModal()">
                                                        <i class="fas fa-plus"></i> Add Holiday
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Holidays List -->
                                            <div class="mb-4">
                                                <h6>Upcoming Holidays</h6>
                                                <div id="holidaysList" class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Day</th>
                                                                <th>Reason</th>
                                                                <th>Rescheduled To</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="holidaysTableBody">
                                                            <tr>
                                                                <td colspan="5" class="text-center">
                                                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                                                    Loading holidays...
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Temporary Consultation Days -->
                                            <div>
                                                <h6>Temporary Consultation Days</h6>
                                                <div id="tempDaysList" class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Day</th>
                                                                <th>Reason</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="tempDaysTableBody">
                                                            <tr>
                                                                <td colspan="3" class="text-center">
                                                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                                                    Loading temporary days...
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics -->
                            <div class="stats-card" id="statsCard" style="display: none;">
                                <h6>Slot Statistics</h6>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        <div class="stat-value" id="totalSlots">0</div>
                                        <div class="stat-label">Total Slots</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value text-success" id="availableSlots">0</div>
                                        <div class="stat-label">Available</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value text-danger" id="bookedSlots">0</div>
                                        <div class="stat-label">Booked</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value text-warning" id="blockedSlots">0</div>
                                        <div class="stat-label">Blocked</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="legend" id="legend" style="display: none;">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #f8fff8; border-color: #4CAF50;"></div>
                                    <span>Available</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fff5f5; border-color: #f44336;"></div>
                                    <span>Booked</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fff8e1; border-color: #ff9800;"></div>
                                    <span>Blocked</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #e3f2fd; border-color: #2196F3;"></div>
                                    <span>Selected</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons" id="actionButtons" style="display: none;">
                                <button class="btn-block-action btn-book" onclick="bookSelectedSlot()">
                                    <i class="fas fa-calendar-plus"></i> Book Selected Slot
                                </button>
                                <button class="btn-block-action btn-block" onclick="blockSelectedSlots()">
                                    <i class="fas fa-ban"></i> Block Selected Slots
                                </button>
                                <button class="btn-block-action btn-unblock" onclick="unblockSelectedSlots()">
                                    <i class="fas fa-check-circle"></i> Unblock Selected Slots
                                </button>
                                <button class="btn-block-action btn-clear" onclick="clearSelection()">
                                    <i class="fas fa-times"></i> Clear Selection
                                </button>
                            </div>

                            <!-- Slots Grid -->
                            <div class="slot-grid" id="slotsGrid">
                                <div style="text-align: center; padding: 40px; grid-column: 1/-1;">
                                    <p class="text-muted">Please select a consultation date to view available slots</p>
                                </div>
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
                            design and develop by <a href="#" class="font-weight-bold">Evon Technologies Software Solution (PVT) Ltd.</a>
                            All rights reserved.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- Add Holiday Modal -->
    <div id="addHolidayModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="material-symbols-rounded">event_busy</i>
                    <span>Add Holiday</span>
                </h4>
                <span class="close" onclick="closeAddHolidayModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addHolidayForm">
                    <div class="form-group">
                        <label>
                            <i class="material-symbols-rounded">event</i>
                            <span>Holiday Date *</span>
                        </label>
                        <input type="text" id="holidayDate" class="form-control book--appointment--input"
                            placeholder="Select consultation day" readonly required>
                        <small class="text-muted">Only Wednesday or Sunday can be marked as holiday</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="material-symbols-rounded">edit_note</i>
                            <span>Reason *</span>
                        </label>
                        <textarea id="holidayReason" class="form-control" rows="3"
                            placeholder="e.g., Poya Day, Christmas" required></textarea>
                    </div>

                    <div class="form-group">
                        <div class="form-check" style="padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                            <input class="form-check-input" type="checkbox" id="rescheduleNextDay"
                                style="width: 20px; height: 20px; margin-top: 0;" onchange="toggleRescheduleDatePicker()">
                            <label class="form-check-label ms-2" for="rescheduleNextDay" style="cursor: pointer;">
                                <strong style="color: #333;">
                                    <i class="fas fa-calendar-check text-success"></i>
                                    Reschedule consultation to another day
                                </strong>
                                <br>
                                <small class="text-muted" style="display: block; margin-top: 5px; margin-left: 28px;">
                                    Choose a replacement day for this consultation (within the same month)
                                </small>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="rescheduleDatePickerContainer" style="display: none;">
                        <label>
                            <i class="material-symbols-rounded">event_available</i>
                            <span>Select Replacement Consultation Date *</span>
                        </label>
                        <input type="text" id="rescheduleDate" class="form-control book--appointment--input"
                            placeholder="Click to select a date" readonly>
                        <small class="text-muted">
                            <strong>Available dates:</strong> Any day in the same month (excludes Wed/Sun and existing holidays)
                            <br>
                            <span style="color: #d32f2f;">⚠️ You must select a replacement date when rescheduling</span>
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-primary btn-with-icon">
                            <i class="material-symbols-rounded">check_circle</i>
                            <span>Add Holiday</span>
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeAddHolidayModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="material-symbols-rounded">event_available</i>
                    <span>Book Appointment</span>
                </h4>
                <span class="close" onclick="closeBookingModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="onlineBookingForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">person</i>
                                    <span>Title *</span>
                                </label>
                                <select id="bookingTitle" required>
                                    <option value="Rev.">Rev.</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Miss.">Miss.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">badge</i>
                                    <span>Full Name *</span>
                                </label>
                                <input type="text" id="bookingName" required placeholder="Enter your full name">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">phone</i>
                                    <span>Mobile Number *</span>
                                </label>
                                <input type="tel" id="bookingMobile" required placeholder="07X-XXXXXXX">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">email</i>
                                    <span>Email Address</span>
                                </label>
                                <input type="email" id="bookingEmail" placeholder="your@email.com">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="material-symbols-rounded">home</i>
                            <span>Address</span>
                        </label>
                        <textarea id="bookingAddress" rows="3" placeholder="Your address"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">event</i>
                                    <span>Selected Date & Time</span>
                                </label>
                                <input type="text" id="selectedDateTime" readonly style="background: #f5f5f5;">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>
                                    <i class="material-symbols-rounded">payments</i>
                                    <span>Channeling Fee</span>
                                </label>
                                <input type="text" value="Rs. 200.00" readonly style="background: #f5f5f5;">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary btn-with-icon">
                            <i class="material-symbols-rounded">check_circle</i>
                            <span>Book Appointment</span>
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeBookingModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3">Processing...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let selectedSlots = new Set();
        let currentDate = '';
        let slotsData = [];
        let selectedSlotData = null;
        let availableConsultationDates = [];
        let holidayPicker = null;
        let rescheduleDatePicker = null;
        let availableRescheduleDates = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableDatesForCalendar();
            initializeHolidayCalendar();
            loadHolidays();
            loadTempDays();
        });

        async function loadAvailableDatesForCalendar() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_all_available_dates');
                formData.append('days', '90');

                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.dates) {
                    availableConsultationDates = data.dates;
                    console.log('Available consultation dates loaded:', availableConsultationDates);
                    initializeMainCalendar();
                } else {
                    console.error('Failed to load available dates:', data.message || 'Unknown error');
                    availableConsultationDates = [];
                    initializeMainCalendar();
                }
            } catch (error) {
                console.error('Error loading available dates:', error);
                availableConsultationDates = [];
                initializeMainCalendar();
            }
        }

        function initializeMainCalendar() {
            const today = new Date();
            let nextDate = new Date(today);
            nextDate.setDate(nextDate.getDate() + 1);

            let attempts = 0;
            while (attempts < 90) {
                const dayOfWeek = nextDate.getDay();
                const isWeekendConsultation = (dayOfWeek === 0 || dayOfWeek === 3);
                const isInAvailableList = isDateInAvailableList(nextDate);

                if (isWeekendConsultation || isInAvailableList) {
                    break;
                }

                nextDate.setDate(nextDate.getDate() + 1);
                attempts++;
            }

            const consultationDatePicker = flatpickr("#consultationDate", {
                dateFormat: "Y-m-d",
                minDate: "today",
                defaultDate: nextDate,
                enable: [
                    function(date) {
                        const dayOfWeek = date.getDay();
                        const dateStr = formatDateForComparison(date);
                        const foundInList = availableConsultationDates.some(d => d.date === dateStr);
                        return (dayOfWeek === 0 || dayOfWeek === 3 || foundInList);
                    }
                ],
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    const dateStr = formatDateForComparison(dayElem.dateObj);
                    const availableDateInfo = availableConsultationDates.find(d => d.date === dateStr);

                    if (availableDateInfo && availableDateInfo.is_temporary) {
                        dayElem.innerHTML += '<span class="rescheduled-badge" title="Rescheduled consultation day">★</span>';
                        dayElem.classList.add('rescheduled-day');
                    }
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        loadSlotsForDate(dateStr);
                        checkAndShowRescheduledInfo(dateStr);
                    }
                }
            });

            const defaultDate = nextDate.toISOString().split('T')[0];
            consultationDatePicker.setDate(defaultDate);
            loadSlotsForDate(defaultDate);
        }

        function initializeHolidayCalendar() {
            holidayPicker = flatpickr("#holidayDate", {
                dateFormat: "Y-m-d",
                minDate: "today",
                enable: [
                    function(date) {
                        return (date.getDay() === 0 || date.getDay() === 3);
                    }
                ],
                onChange: async function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        await loadAvailableRescheduleDates(dateStr);
                    }
                }
            });
        }

        function toggleRescheduleDatePicker() {
            const checkbox = document.getElementById('rescheduleNextDay');
            const container = document.getElementById('rescheduleDatePickerContainer');

            if (checkbox.checked) {
                container.style.display = 'block';

                if (!rescheduleDatePicker) {
                    initializeRescheduleDatePicker();
                }

                const holidayDate = document.getElementById('holidayDate').value;
                if (holidayDate) {
                    loadAvailableRescheduleDates(holidayDate);
                }
            } else {
                container.style.display = 'none';
                if (rescheduleDatePicker) {
                    rescheduleDatePicker.clear();
                }
            }
        }

        function initializeRescheduleDatePicker() {
            rescheduleDatePicker = flatpickr("#rescheduleDate", {
                dateFormat: "Y-m-d",
                minDate: "today",
                enable: [],
                disable: [
                    function(date) {
                        const dayOfWeek = date.getDay();
                        return (dayOfWeek === 0 || dayOfWeek === 3);
                    }
                ]
            });
        }

        async function loadAvailableRescheduleDates(holidayDate) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_available_reschedule_dates');
                formData.append('holiday_date', holidayDate);

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.dates) {
                    availableRescheduleDates = data.dates;

                    if (rescheduleDatePicker) {
                        rescheduleDatePicker.set('enable', data.dates.map(d => d.date));
                    }

                    console.log('Available reschedule dates loaded:', data.dates.length);
                } else {
                    console.error('Failed to load available dates:', data.message);
                    availableRescheduleDates = [];
                }
            } catch (error) {
                console.error('Error loading available dates:', error);
                availableRescheduleDates = [];
            }
        }

        function isDateInAvailableList(date) {
            const dateStr = formatDateForComparison(date);
            return availableConsultationDates.some(d => d.date === dateStr);
        }

        function formatDateForComparison(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        async function checkAndShowRescheduledInfo(dateStr) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_date_info');
                formData.append('date', dateStr);

                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.is_temporary) {
                    showRescheduledDayNotification(data.reason);
                }
            } catch (error) {
                console.error('Error checking date info:', error);
            }
        }

        function showRescheduledDayNotification(reason) {
            const existingNotification = document.querySelector('.rescheduled-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'rescheduled-notification';
            notification.innerHTML = `
            <div style="background: linear-gradient(135deg, #66ea66ff 0%, #4ba252ff 100%); color: black; padding: 12px 20px; border-radius: 8px; margin: 15px 0; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                <i class="fas fa-star" style="font-size: 18px;"></i>
                <div style="flex: 1;">
                    <strong style="display: block; margin-bottom: 4px;">Special Consultation Day</strong>
                    <span style="font-size: 13px; opacity: 0.95;">${reason}</span>
                </div>
            </div>
        `;

            const dateContainer = document.querySelector('.row.mb-4');
            if (dateContainer) {
                dateContainer.insertAdjacentElement('afterend', notification);
            }
        }

        async function loadSlotsForDate(date) {
            if (!date) {
                document.getElementById('slotsGrid').innerHTML = `
                <div style="text-align: center; padding: 40px; grid-column: 1/-1;">
                    <p class="text-muted">Please select a consultation date</p>
                </div>
            `;
                hideControls();
                return;
            }

            currentDate = date;
            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'get_time_slots');
                formData.append('date', date);

                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    slotsData = data.slots;
                    renderSlots(data.slots);
                    updateStatistics(data.slots);
                    showControls();
                } else {
                    showError(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Failed to load slots');
            } finally {
                hideLoading();
            }
        }

        function renderSlots(slots) {
            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '';

            slots.forEach(slot => {
                const card = document.createElement('div');
                card.className = `slot-card ${slot.is_available ? 'available' : (slot.is_blocked ? 'blocked' : 'booked')}`;
                card.dataset.time = slot.time;
                card.dataset.status = slot.status;
                card.dataset.slotNumber = slot.slot_number;

                let cardContent = `
                <div style="font-size: 18px; font-weight: bold; color: #017e12ff; margin-bottom: 5px;">
                    #${slot.slot_number}
                </div>
                <div class="slot-time">${slot.display_time}</div>
                <div class="slot-status">${slot.status}</div>
            `;

                if (slot.appointment_number) {
                    cardContent += `<small style="color: #666;">${slot.appointment_number}</small>`;
                }

                card.innerHTML = cardContent;

                if (slot.is_available || slot.is_blocked) {
                    card.onclick = () => toggleSlot(slot.time, card);
                }

                grid.appendChild(card);
            });
        }

        function toggleSlot(time, element) {
            if (selectedSlots.has(time)) {
                selectedSlots.delete(time);
                element.classList.remove('selected');
            } else {
                selectedSlots.add(time);
                element.classList.add('selected');
            }
        }

        function updateStatistics(slots) {
            const total = slots.length;
            const available = slots.filter(s => s.is_available).length;
            const booked = slots.filter(s => !s.is_available && !s.is_blocked).length;
            const blocked = slots.filter(s => s.is_blocked).length;

            document.getElementById('totalSlots').textContent = total;
            document.getElementById('availableSlots').textContent = available;
            document.getElementById('bookedSlots').textContent = booked;
            document.getElementById('blockedSlots').textContent = blocked;
        }

        async function blockSelectedSlots() {
            if (selectedSlots.size === 0) {
                showError('Please select slots to block');
                return;
            }

            const reason = document.getElementById('blockReason').value;

            if (!confirm(`Block ${selectedSlots.size} slot(s)?`)) {
                return;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'block_slots');
                formData.append('date', currentDate);
                formData.append('times', JSON.stringify(Array.from(selectedSlots)));
                formData.append('reason', reason);

                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message);
                    selectedSlots.clear();
                    await loadSlotsForDate(currentDate);
                } else {
                    showError(data.message);
                }
            } catch (error) {
                showError('Failed to block slots');
            } finally {
                hideLoading();
            }
        }

        async function unblockSelectedSlots() {
            if (selectedSlots.size === 0) {
                showError('Please select slots to unblock');
                return;
            }

            if (!confirm(`Unblock ${selectedSlots.size} slot(s)?`)) {
                return;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'unblock_slots');
                formData.append('date', currentDate);
                formData.append('times', JSON.stringify(Array.from(selectedSlots)));

                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message);
                    selectedSlots.clear();
                    await loadSlotsForDate(currentDate);
                } else {
                    showError(data.message);
                }
            } catch (error) {
                showError('Failed to unblock slots');
            } finally {
                hideLoading();
            }
        }

        function bookSelectedSlot() {
            if (selectedSlots.size !== 1) {
                showError('Please select exactly one slot to book');
                return;
            }

            const time = Array.from(selectedSlots)[0];
            const slot = slotsData.find(s => s.time === time);

            if (!slot || !slot.is_available) {
                showError('Selected slot is not available');
                return;
            }

            selectedSlotData = {
                date: currentDate,
                time: time,
                displayTime: slot.display_time
            };

            const dateObj = new Date(currentDate);
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const displayDate = dateObj.toLocaleDateString('en-US', options);

            document.getElementById('selectedDateTime').value = `${displayDate} at ${slot.display_time}`;
            document.getElementById('bookingModal').style.display = 'block';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
            document.getElementById('onlineBookingForm').reset();
        }

        document.getElementById('onlineBookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!selectedSlotData) {
                alert('Please select a time slot');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'book_appointment');
            formData.append('date', selectedSlotData.date);
            formData.append('time', selectedSlotData.time);
            formData.append('title', document.getElementById('bookingTitle').value);
            formData.append('name', document.getElementById('bookingName').value);
            formData.append('mobile', document.getElementById('bookingMobile').value);
            formData.append('email', document.getElementById('bookingEmail').value);
            formData.append('address', document.getElementById('bookingAddress').value);

            showLoading();

            try {
                const response = await fetch('../../appointment_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeBookingModal();
                    showSuccess(`Appointment ${result.appointment_number} booked successfully!`);
                    selectedSlots.clear();
                    selectedSlotData = null;
                    await loadSlotsForDate(currentDate);
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                hideLoading();
            }
        });

        function clearSelection() {
            selectedSlots.clear();
            document.querySelectorAll('.slot-card.selected').forEach(card => {
                card.classList.remove('selected');
            });
        }

        function showControls() {
            document.getElementById('statsCard').style.display = 'block';
            document.getElementById('legend').style.display = 'flex';
            document.getElementById('actionButtons').style.display = 'flex';
        }

        function hideControls() {
            document.getElementById('statsCard').style.display = 'none';
            document.getElementById('legend').style.display = 'none';
            document.getElementById('actionButtons').style.display = 'none';
        }

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function showError(message) {
            alert('Error: ' + message);
        }

        function showSuccess(message) {
            alert('Success: ' + message);
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '?logout=1';
            }
        }

        function openAddHolidayModal() {
            document.getElementById('addHolidayModal').style.display = 'block';
        }

        function closeAddHolidayModal() {
            document.getElementById('addHolidayModal').style.display = 'none';
            document.getElementById('addHolidayForm').reset();
            document.getElementById('rescheduleDatePickerContainer').style.display = 'none';

            if (holidayPicker) {
                holidayPicker.clear();
            }
            if (rescheduleDatePicker) {
                rescheduleDatePicker.clear();
            }

            availableRescheduleDates = [];
        }

        async function loadHolidays() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_holidays');

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    renderHolidays(data.holidays);
                } else {
                    document.getElementById('holidaysTableBody').innerHTML =
                        '<tr><td colspan="5" class="text-center text-muted">No holidays found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading holidays:', error);
                document.getElementById('holidaysTableBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger">Error loading holidays</td></tr>';
            }
        }

        function renderHolidays(holidays) {
            const tbody = document.getElementById('holidaysTableBody');

            if (holidays.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No holidays scheduled</td></tr>';
                return;
            }

            tbody.innerHTML = holidays.map(holiday => {
                const date = new Date(holiday.holiday_date);
                let rescheduledInfo = 'Not rescheduled';

                return `
                <tr data-holiday-date="${holiday.holiday_date}">
                    <td>${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td><span class="badge badge-warning">${holiday.day_of_week}</span></td>
                    <td>${holiday.reason}</td>
                    <td id="reschedule-info-${holiday.holiday_date.replace(/-/g, '')}">${rescheduledInfo}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="removeHoliday('${holiday.holiday_date}')">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </td>
                </tr>`;
            }).join('');
            updateRescheduleInfo(holidays);
        }

        async function updateRescheduleInfo(holidays) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_temp_days');

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.days) {
                    holidays.forEach(holiday => {
                        const holidayDateStr = holiday.holiday_date;
                        const holidayDate = new Date(holidayDateStr);
                        const holidayMonth = holidayDate.getMonth();
                        const holidayYear = holidayDate.getFullYear();

                        const matchingTempDay = data.days.find(tempDay => {
                            const tempDate = new Date(tempDay.consultation_date);
                            const tempMonth = tempDate.getMonth();
                            const tempYear = tempDate.getFullYear();

                            return tempMonth === holidayMonth &&
                                tempYear === holidayYear &&
                                tempDay.reason.includes('Rescheduled from');
                        });

                        if (matchingTempDay) {
                            const tempDate = new Date(matchingTempDay.consultation_date);
                            const rescheduledText = tempDate.toLocaleDateString('en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric'
                            });

                            const cellId = 'reschedule-info-' + holidayDateStr.replace(/-/g, '');
                            const cell = document.getElementById(cellId);
                            if (cell) {
                                cell.innerHTML = `<span class="badge badge-info">${rescheduledText}</span>`;
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error updating reschedule info:', error);
            }
        }

        async function loadTempDays() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_temp_days');

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    renderTempDays(data.days);
                } else {
                    document.getElementById('tempDaysTableBody').innerHTML =
                        '<tr><td colspan="3" class="text-center text-muted">No temporary consultation days</td></tr>';
                }
            } catch (error) {
                console.error('Error loading temp days:', error);
                document.getElementById('tempDaysTableBody').innerHTML =
                    '<tr><td colspan="3" class="text-center text-danger">Error loading data</td></tr>';
            }
        }

        function renderTempDays(days) {
            const tbody = document.getElementById('tempDaysTableBody');

            if (days.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No temporary consultation days</td></tr>';
                return;
            }

            tbody.innerHTML = days.map(day => {
                const date = new Date(day.consultation_date);
                return `
                <tr>
                    <td>${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td><span class="badge badge-info">${day.day_of_week}</span></td>
                    <td><small>${day.reason}</small></td>
                </tr>
            `;
            }).join('');
        }

        document.getElementById('addHolidayForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const date = document.getElementById('holidayDate').value;
            const reason = document.getElementById('holidayReason').value;
            const reschedule = document.getElementById('rescheduleNextDay').checked;
            const customRescheduleDate = reschedule ? document.getElementById('rescheduleDate').value : null;

            if (!date || !reason) {
                alert('Please fill in all required fields');
                return;
            }

            if (reschedule && !customRescheduleDate) {
                alert('Please select a date to reschedule the consultation to');
                return;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'add_holiday');
                formData.append('date', date);
                formData.append('reason', reason);
                formData.append('reschedule', reschedule ? 'true' : 'false');

                if (customRescheduleDate) {
                    formData.append('custom_reschedule_date', customRescheduleDate);
                }

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    let message = 'Holiday added successfully!';
                    if (data.rescheduled_to) {
                        const reschDate = new Date(data.rescheduled_to);
                        message += `\n\nConsultation day rescheduled to: ${reschDate.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        month: 'long', 
                        day: 'numeric' 
                    })}`;
                    }
                    alert(message);
                    closeAddHolidayModal();
                    loadHolidays();
                    loadTempDays();

                    if (currentDate) {
                        await loadSlotsForDate(currentDate);
                    }

                    await loadAvailableDatesForCalendar();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error adding holiday:', error);
                alert('Failed to add holiday. Please try again.');
            } finally {
                hideLoading();
            }
        });

        async function removeHoliday(date) {
            if (!confirm('Are you sure you want to remove this holiday?\n\nThis will unblock all slots for that day (if not already booked).')) {
                return;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'remove_holiday');
                formData.append('date', date);

                const response = await fetch('holiday_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('Holiday removed successfully!');
                    loadHolidays();
                    loadTempDays();

                    if (currentDate === date) {
                        await loadSlotsForDate(currentDate);
                    }

                    await loadAvailableDatesForCalendar();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error removing holiday:', error);
                alert('Failed to remove holiday. Please try again.');
            } finally {
                hideLoading();
            }
        }

        window.addEventListener('click', function(event) {
            const bookingModal = document.getElementById('bookingModal');
            const holidayModal = document.getElementById('addHolidayModal');

            if (event.target === bookingModal) {
                closeBookingModal();
            }

            if (event.target === holidayModal) {
                closeAddHolidayModal();
            }
        });
    </script>

</body>

</html>