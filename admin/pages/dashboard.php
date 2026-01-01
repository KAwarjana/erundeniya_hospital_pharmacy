<?php
require_once 'page_guards.php';
PageGuards::guardDashboard();

// Database connection and dynamic data retrieval
require_once '../../connection/connection.php';

// Get current user info
$currentUser = AuthManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Get dashboard statistics
try {
  // Today's appointments count
  $today = date('Y-m-d');
  $todayAppointmentsQuery = "SELECT COUNT(*) as count FROM appointment WHERE appointment_date = '$today'";
  $todayAppointmentsResult = Database::search($todayAppointmentsQuery);
  $todayAppointments = $todayAppointmentsResult->fetch_assoc()['count'];

  // Total patients count
  $totalPatientsQuery = "SELECT COUNT(*) as count FROM patient";
  $totalPatientsResult = Database::search($totalPatientsQuery);
  $totalPatients = $totalPatientsResult->fetch_assoc()['count'];

  // Today's revenue from appointments
  $todayRevenueQuery = "SELECT SUM(total_amount) as total FROM appointment WHERE appointment_date = '$today' AND payment_status = 'Paid'";
  $todayRevenueResult = Database::search($todayRevenueQuery);
  $todayRevenue = $todayRevenueResult->fetch_assoc()['total'] ?? 0;

  // Pending appointments count
  $pendingAppointmentsQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Booked'";
  $pendingAppointmentsResult = Database::search($pendingAppointmentsQuery);
  $pendingAppointments = $pendingAppointmentsResult->fetch_assoc()['count'];

  // Confirmed appointments count
  $confirmedAppointmentsQuery = "SELECT COUNT(*) as count FROM appointment WHERE status = 'Confirmed'";
  $confirmedAppointmentsResult = Database::search($confirmedAppointmentsQuery);
  $confirmedAppointments = $confirmedAppointmentsResult->fetch_assoc()['count'];

  // Recent appointments for the table
  $recentAppointmentsQuery = "SELECT a.*, p.name as patient_name, p.mobile as patient_mobile 
                               FROM appointment a 
                               JOIN patient p ON a.patient_id = p.id 
                               ORDER BY a.created_at DESC 
                               LIMIT 4";
  $recentAppointmentsResult = Database::search($recentAppointmentsQuery);

  // Weekly appointment data for charts
  $weeklyDataQuery = "SELECT 
                        DATE(appointment_date) as date,
                        COUNT(*) as count,
                        SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END) as revenue
                        FROM appointment 
                        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY DATE(appointment_date)
                        ORDER BY date";
  $weeklyDataResult = Database::search($weeklyDataQuery);

  $weeklyDates = [];
  $weeklyCounts = [];
  $weeklyRevenue = [];

  while ($row = $weeklyDataResult->fetch_assoc()) {
    $weeklyDates[] = date('M d', strtotime($row['date']));
    $weeklyCounts[] = $row['count'];
    $weeklyRevenue[] = $row['revenue'] ?? 0;
  }

  // Monthly data for line chart
  $monthlyDataQuery = "SELECT 
                         DATE_FORMAT(appointment_date, '%Y-%m') as month,
                         COUNT(*) as count,
                         SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END) as revenue
                         FROM appointment 
                         WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                         GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
                         ORDER BY month";
  $monthlyDataResult = Database::search($monthlyDataQuery);

  $monthlyLabels = [];
  $monthlyCounts = [];
  $monthlyRevenue = [];

  while ($row = $monthlyDataResult->fetch_assoc()) {
    $monthlyLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthlyCounts[] = $row['count'];
    $monthlyRevenue[] = $row['revenue'] ?? 0;
  }

  // Treatment statistics
  $treatmentStatsQuery = "SELECT 
                           t.treatment_name,
                           COUNT(tb.id) as usage_count,
                           SUM(tb.total_amount) as total_revenue
                           FROM treatment_bills tb
                           JOIN treatments t ON JSON_CONTAINS(tb.treatments_data, CAST(t.id AS JSON), '$')
                           GROUP BY t.id, t.treatment_name
                           ORDER BY usage_count DESC
                           LIMIT 5";
  $treatmentStatsResult = Database::search($treatmentStatsQuery);

  $treatmentNames = [];
  $treatmentCounts = [];

  while ($row = $treatmentStatsResult->fetch_assoc()) {
    $treatmentNames[] = $row['treatment_name'];
    $treatmentCounts[] = $row['usage_count'];
  }
} catch (Exception $e) {
  error_log("Dashboard data error: " . $e->getMessage());
  $todayAppointments = 0;
  $totalPatients = 0;
  $todayRevenue = 0;
  $pendingAppointments = 0;
  $confirmedAppointments = 0;
}

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../../img/logof1.png">
  <title>
    Dashboard - Erundeniya Ayurveda Hospital
  </title>
  <!--     Fonts and icons     -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

  <style>
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
    }

    .filter-btn {
      padding: 5px 16px;
      border: 1px solid #ddd;
      background: white;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
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

<body class="g-sidenav-show  bg-gray-100">

  <!-- Sidebar HTML (dashboard.php ekata daala) -->
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

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <!-- Navbar -->
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card" id="navbarBlur" data-scroll="true" style="background-color: white;">
      <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-transparent mb-1 pb-0 pt-1 px-0 me-sm-6 me-5">
            <!-- <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li> -->
            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Dashboard</li>
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
                <span class="notification-badge" id="notificationCount"><?php echo $pendingAppointments; ?></span>
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
    <!-- End Navbar -->
    <div class="container-fluid py-2 mt-2">
      <div class="row">
        <div class="ms-3">
          <h3 class="mb-0 h4 font-weight-bolder">Dashboard</h3>
          <p class="mb-4">
            Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's what's happening today.
          </p>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Today's Appointments</p>
                  <h4 class="mb-0"><?php echo $todayAppointments; ?></h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">today</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-sm">
                <?php if ($todayAppointments > 0): ?>
                  <span class="text-success font-weight-bolder"><?php echo $todayAppointments; ?> appointments </span>scheduled today
                <?php else: ?>
                  No appointments scheduled for today
                <?php endif; ?>
              </p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Total Patients</p>
                  <h4 class="mb-0"><?php echo $totalPatients; ?></h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">people</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-sm">
                <span class="text-success font-weight-bolder"><?php echo $totalPatients; ?> registered </span>patients in system
              </p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Today's Revenue</p>
                  <h4 class="mb-0">Rs. <?php echo number_format($todayRevenue, 2); ?></h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">payments</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-sm">
                <?php if ($todayRevenue > 0): ?>
                  <span class="text-success font-weight-bolder">Rs. <?php echo number_format($todayRevenue, 2); ?> </span>earned today
                <?php else: ?>
                  No revenue recorded for today
                <?php endif; ?>
              </p>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Pending Appointments</p>
                  <h4 class="mb-0"><?php echo $pendingAppointments; ?></h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-dark shadow-dark shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">pending_actions</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-sm">
                <span class="text-warning font-weight-bolder"><?php echo $pendingAppointments; ?> pending </span>appointments need confirmation
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-lg-4 col-md-6 mt-4 mb-4">
          <div class="card">
            <div class="card-body">
              <h6 class="mb-0 ">Weekly Appointments</h6>
              <p class="text-sm ">Last 7 days appointment trends</p>
              <div class="pe-2">
                <div class="chart">
                  <canvas id="chart-bars" class="chart-canvas" height="170"></canvas>
                </div>
              </div>
              <hr class="dark horizontal">
              <div class="d-flex ">
                <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
                <p class="mb-0 text-sm"> updated 4 min ago </p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6 mt-4 mb-4">
          <div class="card ">
            <div class="card-body">
              <h6 class="mb-0 "> Monthly Revenue </h6>
              <p class="text-sm "> Monthly revenue trends from appointments </p>
              <div class="pe-2">
                <div class="chart">
                  <canvas id="chart-line" class="chart-canvas" height="170"></canvas>
                </div>
              </div>
              <hr class="dark horizontal">
              <div class="d-flex ">
                <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
                <p class="mb-0 text-sm"> updated 4 min ago </p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 mt-4 mb-3">
          <div class="card">
            <div class="card-body">
              <h6 class="mb-0 ">Treatment Statistics</h6>
              <p class="text-sm ">Most popular treatments this month</p>
              <div class="pe-2">
                <div class="chart">
                  <canvas id="chart-line-tasks" class="chart-canvas" height="170"></canvas>
                </div>
              </div>
              <hr class="dark horizontal">
              <div class="d-flex ">
                <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
                <p class="mb-0 text-sm">just updated</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-lg-8 col-md-6 mb-md-0 mb-4">
          <div class="card">
            <div class="card-header pb-0">
              <div class="row">
                <div class="col-lg-6 col-7">
                  <h6>Recent Appointments</h6>
                  <p class="text-sm mb-0">
                    <i class="fa fa-check text-info" aria-hidden="true"></i>
                    <span class="font-weight-bold ms-1"><?php echo $todayAppointments; ?></span> appointments today
                  </p>
                </div>
                <div class="col-lg-6 col-5 my-auto text-end">
                  <div class="dropdown float-lg-end pe-4">
                    <a class="cursor-pointer" id="dropdownTable" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="fa fa-ellipsis-v text-secondary"></i>
                    </a>
                    <ul class="dropdown-menu px-2 py-3 ms-sm-n4 ms-n5" aria-labelledby="dropdownTable">
                      <li><a class="dropdown-item border-radius-md" href="appointments.php">View All Appointments</a></li>
                      <li><a class="dropdown-item border-radius-md" href="book_appointments.php">Book New Appointment</a></li>
                      <li><a class="dropdown-item border-radius-md" href="patients.php">View Patients</a></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-body px-0 pb-2">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Appointment No.</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Patient</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date & Time</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($appointment = $recentAppointmentsResult->fetch_assoc()): ?>
                      <tr>
                        <td>
                          <div class="d-flex px-2 py-1">
                            <div class="d-flex flex-column justify-content-center">
                              <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($appointment['appointment_number']); ?></h6>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($appointment['patient_name']); ?></h6>
                            <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($appointment['patient_mobile']); ?></p>
                          </div>
                        </td>
                        <td class="align-middle text-center text-sm">
                          <span class="text-xs font-weight-bold">
                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                          </span>
                        </td>
                        <td class="align-middle text-center text-sm">
                          <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                            <?php echo $appointment['status']; ?>
                          </span>
                        </td>
                        <td class="align-middle text-center text-sm">
                          <span class="text-xs font-weight-bold">Rs. <?php echo number_format($appointment['total_amount'], 2); ?></span>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="card h-100">
            <div class="card-header pb-0">
              <h6>Appointment Status Overview</h6>
              <p class="text-sm">
                <i class="fa fa-info-circle text-success" aria-hidden="true"></i>
                <span class="font-weight-bold">Current Status Distribution</span>
              </p>
            </div>
            <div class="card-body p-3">
              <div class="timeline timeline-one-side">
                <div class="timeline-block mb-3">
                  <span class="timeline-step">
                    <i class="material-symbols-rounded text-success text-gradient">check_circle</i>
                  </span>
                  <div class="timeline-content">
                    <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo $confirmedAppointments; ?> Confirmed Appointments</h6>
                    <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">Ready to attend</p>
                  </div>
                </div>
                <div class="timeline-block mb-3">
                  <span class="timeline-step">
                    <i class="material-symbols-rounded text-warning text-gradient">pending</i>
                  </span>
                  <div class="timeline-content">
                    <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo $pendingAppointments; ?> Pending Confirmations</h6>
                    <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">Need approval</p>
                  </div>
                </div>
                <div class="timeline-block mb-3">
                  <span class="timeline-step">
                    <i class="material-symbols-rounded text-info text-gradient">event</i>
                  </span>
                  <div class="timeline-content">
                    <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo $todayAppointments; ?> Today's Appointments</h6>
                    <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">Scheduled for today</p>
                  </div>
                </div>
                <div class="timeline-block">
                  <span class="timeline-step">
                    <i class="material-symbols-rounded text-primary text-gradient">people</i>
                  </span>
                  <div class="timeline-content">
                    <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo $totalPatients; ?> Total Patients</h6>
                    <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">Registered in system</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <footer class="footer py-4  ">
        <div class="container-fluid">
          <div class="row align-items-center justify-content-lg-between">
            <div class="mb-lg-0 mb-4">
              <div class="copyright text-center text-sm text-muted text-lg-start">
                © <script>
                  document.write(new Date().getFullYear())
                </script>,
                design and develop by
                <a href="https://www.creative-tim.com  " class="font-weight-bold" target="_blank">Evon Technologies Software Solution (PVT) Ltd.</a>
                All rights received.
              </div>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </main>

  <!--   Core JS Files   -->
  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/chartjs.min.js"></script>
  <script>
    // Weekly Appointments Chart
    var ctx = document.getElementById("chart-bars").getContext("2d");
    new Chart(ctx, {
      type: "bar",
      data: {
        labels: <?php echo json_encode($weeklyDates); ?>,
        datasets: [{
          label: "Appointments",
          tension: 0.4,
          borderWidth: 0,
          borderRadius: 4,
          borderSkipped: false,
          backgroundColor: "#43A047",
          data: <?php echo json_encode($weeklyCounts); ?>,
          barThickness: 'flex'
        }, ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          }
        },
        interaction: {
          intersect: false,
          mode: 'index',
        },
        scales: {
          y: {
            grid: {
              drawBorder: false,
              display: true,
              drawOnChartArea: true,
              drawTicks: false,
              borderDash: [5, 5],
              color: '#e5e5e5'
            },
            ticks: {
              suggestedMin: 0,
              suggestedMax: 500,
              beginAtZero: true,
              padding: 10,
              font: {
                size: 14,
                lineHeight: 2
              },
              color: "#737373"
            },
          },
          x: {
            grid: {
              drawBorder: false,
              display: false,
              drawOnChartArea: false,
              drawTicks: false,
              borderDash: [5, 5]
            },
            ticks: {
              display: true,
              color: '#737373',
              padding: 10,
              font: {
                size: 14,
                lineHeight: 2
              },
            }
          },
        },
      },
    });

    // Monthly Revenue Chart
    var ctx2 = document.getElementById("chart-line").getContext("2d");
    new Chart(ctx2, {
      type: "line",
      data: {
        labels: <?php echo json_encode($monthlyLabels); ?>,
        datasets: [{
          label: "Revenue",
          tension: 0,
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: "#43A047",
          pointBorderColor: "transparent",
          borderColor: "#43A047",
          backgroundColor: "transparent",
          fill: true,
          data: <?php echo json_encode($monthlyRevenue); ?>,
          maxBarThickness: 6
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Rs. ' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index',
        },
        scales: {
          y: {
            grid: {
              drawBorder: false,
              display: true,
              drawOnChartArea: true,
              drawTicks: false,
              borderDash: [4, 4],
              color: '#e5e5e5'
            },
            ticks: {
              display: true,
              color: '#737373',
              padding: 10,
              font: {
                size: 12,
                lineHeight: 2
              },
            }
          },
          x: {
            grid: {
              drawBorder: false,
              display: false,
              drawOnChartArea: false,
              drawTicks: false,
              borderDash: [5, 5]
            },
            ticks: {
              display: true,
              color: '#737373',
              padding: 10,
              font: {
                size: 12,
                lineHeight: 2
              },
            }
          },
        },
      },
    });

    // Treatment Statistics Chart
    var ctx3 = document.getElementById("chart-line-tasks").getContext("2d");
    new Chart(ctx3, {
      type: "doughnut",
      data: {
        labels: <?php echo json_encode($treatmentNames ?: ['No Data']); ?>,
        datasets: [{
          label: "Treatments",
          data: <?php echo json_encode($treatmentCounts ?: [1]); ?>,
          backgroundColor: [
            '#43A047',
            '#1E88E5',
            '#FFB74D',
            '#E53935',
            '#8E24AA'
          ],
          borderWidth: 0
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
          }
        },
      },
    });
  </script>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
  <script>
    function logout() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '?logout=1';
      }
    }
  </script>
  <!-- Github buttons -->
  <script async defer src="https://buttons.github.io/buttons.js  "></script>
  <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
  <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
  <script src="../assets/js/admin_notifications.js"></script>
</body>

</html>