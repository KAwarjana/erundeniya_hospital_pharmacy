<?php
require_once 'page_guards.php';
PageGuards::guardDashboard();

require_once '../../connection/connection.php';

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../../img/logof1.png">
  <title>Reports - Erundeniya Ayurveda Hospital</title>

  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

  <!-- SheetJS for Excel Export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <style>
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

    .report-card {
      transition: all 0.3s ease;
      cursor: pointer;
      border: 2px solid transparent;
    }

    .report-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
      border-color: #4CAF50;
    }

    .report-card.active {
      border-color: #4CAF50;
      background: #f8f9fa;
    }

    .filter-section {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .date-filter-btn {
      padding: 8px 20px;
      border: 1px solid #ddd;
      background: white;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
      margin: 5px;
    }

    .date-filter-btn:hover {
      background: #f5f5f5;
    }

    .date-filter-btn.active {
      background: #4CAF50;
      color: white;
      border-color: #4CAF50;
    }

    .results-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }

    .export-btn {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .export-btn:hover {
      background: #45a049;
    }

    .summary-card {
      background: linear-gradient(135deg, #38e64fff 0%, #0b9133ff 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
    }

    .status-badge {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 500;
    }

    .status-paid {
      background: #e8f5e8;
      color: #2e7d32;
    }

    .status-pending {
      background: #fff3e0;
      color: #f57c00;
    }

    .status-partial {
      background: #e3f2fd;
      color: #1976d2;
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

    .status-cancelled {
      background: #ffebee;
      color: #f44336;
    }

    .status-no-show {
      background: #fff3e0;
      color: #f57c00;
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

    .loading-spinner {
      display: none;
      text-align: center;
      padding: 20px;
    }

    .loading-spinner.active {
      display: block;
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

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <!-- Navbar -->
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card" id="navbarBlur" data-scroll="true" style="background-color: white;">
      <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-transparent mb-1 pb-0 pt-1 px-0 me-sm-6 me-5">
            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Reports</li>
          </ol>
        </nav>
        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
          <div class="ms-md-auto pe-md-3 d-flex align-items-center"></div>
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
                <img src="../../img/user.png" width="20" height="20">
                &nbsp;<span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="container-fluid py-2 mt-2">
      <div class="row">
        <div class="col-12">
          <h3 class="mb-0 h4 font-weight-bolder">Reports & Analytics</h3>
          <p class="mb-4">Generate and export comprehensive reports for your hospital</p>
        </div>
      </div>

      <!-- Report Type Selection -->
      <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card report-card" onclick="selectReport('appointments')">
            <div class="card-body text-center">
              <i class="material-symbols-rounded text-primary" style="font-size: 48px;">calendar_today</i>
              <h6 class="mt-2">Appointments Report</h6>
              <p class="text-sm text-muted mb-0">View all appointments data</p>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card report-card" onclick="selectReport('bills')">
            <div class="card-body text-center">
              <i class="material-symbols-rounded text-success" style="font-size: 48px;">receipt</i>
              <h6 class="mt-2">Bills Report</h6>
              <p class="text-sm text-muted mb-0">View billing records</p>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card report-card" onclick="selectReport('patients')">
            <div class="card-body text-center">
              <i class="material-symbols-rounded text-info" style="font-size: 48px;">people</i>
              <h6 class="mt-2">Patients Report</h6>
              <p class="text-sm text-muted mb-0">View patient records</p>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card report-card" onclick="selectReport('treatments')">
            <div class="card-body text-center">
              <i class="material-symbols-rounded text-warning" style="font-size: 48px;">local_hospital</i>
              <h6 class="mt-2">Treatment Bills Report</h6>
              <p class="text-sm text-muted mb-0">View treatment billing data</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section" id="filterSection" style="display: none;">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h6 class="mb-3">Select Date Range</h6>
            <div class="d-flex flex-wrap">
              <button class="date-filter-btn" onclick="setDateFilter('today')">Today</button>
              <button class="date-filter-btn" onclick="setDateFilter('yesterday')">Yesterday</button>
              <button class="date-filter-btn" onclick="setDateFilter('thisWeek')">This Week</button>
              <button class="date-filter-btn" onclick="setDateFilter('lastWeek')">Last Week</button>
              <button class="date-filter-btn" onclick="setDateFilter('thisMonth')">This Month</button>
              <button class="date-filter-btn" onclick="setDateFilter('lastMonth')">Last Month</button>
              <button class="date-filter-btn active" onclick="setDateFilter('all')">All Time</button>
            </div>
            <div class="mt-3">
              <label class="text-sm">Custom Date Range:</label>
              <div class="row">
                <div class="col-md-5">
                  <input type="date" class="form-control" id="startDate" onchange="setDateFilter('custom')">
                </div>
                <div class="col-md-5">
                  <input type="date" class="form-control" id="endDate" onchange="setDateFilter('custom')">
                </div>
                <div class="col-md-2">
                  <button class="btn btn-primary w-100" onclick="generateReport()">Apply</button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4 text-end">
            <button class="export-btn" onclick="exportToExcel()">
              <i class="fas fa-file-excel me-2"></i>Export to Excel
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Section -->
      <div id="summarySection" style="display: none;">
        <div class="summary-card">
          <div class="row">
            <div class="col-md-3">
              <h6 class="text-white opacity-7">Total Records</h6>
              <h3 class="text-white mb-0" id="totalRecords">0</h3>
            </div>
            <div class="col-md-3" id="totalAmountSection" style="display: none;">
              <h6 class="text-white opacity-7">Total Amount</h6>
              <h3 class="text-white mb-0" id="totalAmount">Rs. 0.00</h3>
            </div>
            <div class="col-md-3" id="paidAmountSection" style="display: none;">
              <h6 class="text-white opacity-7">Paid Amount</h6>
              <h3 class="text-white mb-0" id="paidAmount">Rs. 0.00</h3>
            </div>
            <div class="col-md-3" id="pendingAmountSection" style="display: none;">
              <h6 class="text-white opacity-7">Pending Amount</h6>
              <h3 class="text-white mb-0" id="pendingAmount">Rs. 0.00</h3>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading Spinner -->
      <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Generating report...</p>
      </div>

      <!-- Results Table -->
      <div class="results-table" id="resultsSection" style="display: none;">
        <div class="card">
          <div class="card-header pb-0">
            <h6 id="reportTitle">Report Results</h6>
          </div>
          <div class="card-body px-0 pb-2">
            <div class="table-responsive">
              <table class="table align-items-center mb-0" id="reportTable">
                <thead id="tableHead"></thead>
                <tbody id="tableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <footer class="footer py-4">
      <div class="container-fluid">
        <div class="row align-items-center justify-content-lg-between">
          <div class="mb-lg-0 mb-4">
            <div class="copyright text-center text-sm text-muted text-lg-start">
              © <script>
                document.write(new Date().getFullYear())
              </script>,
              design and develop by
              <a href="https://www.creative-tim.com" class="font-weight-bold" target="_blank">Evon Technologies Software Solution (PVT) Ltd.</a>
              All rights received.
            </div>
          </div>
        </div>
      </div>
    </footer>
  </main>

  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>

  <script>
    let currentReportType = null;
    let currentDateFilter = 'all';
    let reportData = [];

    function selectReport(type) {
      currentReportType = type;

      // Update UI
      document.querySelectorAll('.report-card').forEach(card => {
        card.classList.remove('active');
      });
      event.currentTarget.classList.add('active');

      // Show filter section
      document.getElementById('filterSection').style.display = 'block';

      // Reset filters
      setDateFilter('all');

      // Generate report
      generateReport();
    }

    function setDateFilter(filter) {
      currentDateFilter = filter;

      // Update button states
      document.querySelectorAll('.date-filter-btn').forEach(btn => {
        btn.classList.remove('active');
      });

      if (filter !== 'custom') {
        event.target.classList.add('active');
        generateReport();
      }
    }

    async function generateReport() {
      if (!currentReportType) {
        alert('Please select a report type first');
        return;
      }

      // Show loading
      document.getElementById('loadingSpinner').classList.add('active');
      document.getElementById('resultsSection').style.display = 'none';
      document.getElementById('summarySection').style.display = 'none';

      try {
        const formData = new FormData();
        formData.append('action', 'generateReport');
        formData.append('reportType', currentReportType);
        formData.append('dateFilter', currentDateFilter);
        formData.append('startDate', document.getElementById('startDate').value);
        formData.append('endDate', document.getElementById('endDate').value);

        const response = await fetch('report_handler.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          reportData = data.data;
          displayReport(data);
        } else {
          alert('Error: ' + data.message);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Failed to generate report');
      } finally {
        document.getElementById('loadingSpinner').classList.remove('active');
      }
    }

    function displayReport(data) {
      const reportTitle = document.getElementById('reportTitle');
      const tableHead = document.getElementById('tableHead');
      const tableBody = document.getElementById('tableBody');
      const summarySection = document.getElementById('summarySection');
      const resultsSection = document.getElementById('resultsSection');

      // Update title
      reportTitle.textContent = data.title;

      // Build table header
      let headerHTML = '<tr>';
      data.headers.forEach(header => {
        headerHTML += `<th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">${header}</th>`;
      });
      headerHTML += '</tr>';
      tableHead.innerHTML = headerHTML;

      // Build table body
      let bodyHTML = '';
      data.data.forEach(row => {
        bodyHTML += '<tr>';
        Object.values(row).forEach(value => {
          bodyHTML += `<td class="text-sm">${value}</td>`;
        });
        bodyHTML += '</tr>';
      });
      tableBody.innerHTML = bodyHTML;

      // Update summary
      document.getElementById('totalRecords').textContent = data.summary.totalRecords;

      if (data.summary.totalAmount !== undefined) {
        document.getElementById('totalAmountSection').style.display = 'block';
        document.getElementById('totalAmount').textContent = 'Rs. ' + parseFloat(data.summary.totalAmount).toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      } else {
        document.getElementById('totalAmountSection').style.display = 'none';
      }

      if (data.summary.paidAmount !== undefined) {
        document.getElementById('paidAmountSection').style.display = 'block';
        document.getElementById('paidAmount').textContent = 'Rs. ' + parseFloat(data.summary.paidAmount).toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      } else {
        document.getElementById('paidAmountSection').style.display = 'none';
      }

      if (data.summary.pendingAmount !== undefined) {
        document.getElementById('pendingAmountSection').style.display = 'block';
        document.getElementById('pendingAmount').textContent = 'Rs. ' + parseFloat(data.summary.pendingAmount).toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      } else {
        document.getElementById('pendingAmountSection').style.display = 'none';
      }

      // Show sections
      summarySection.style.display = 'block';
      resultsSection.style.display = 'block';
    }

    function exportToExcel() {
      if (!reportData || reportData.length === 0) {
        alert('No data to export');
        return;
      }

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(reportData);

      XLSX.utils.book_append_sheet(wb, ws, currentReportType);

      const fileName = `${currentReportType}_report_${new Date().toISOString().split('T')[0]}.xlsx`;
      XLSX.writeFile(wb, fileName);
    }

    function logout() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '?logout=1';
      }
    }

    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
</body>

</html>