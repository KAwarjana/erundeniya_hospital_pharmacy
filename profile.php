<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();
$userInfo = Auth::getUserInfo();

// Get user details
$stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.role_id 
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $userInfo['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$stats = [];

// Total sales by this user
$salesStmt = $conn->prepare("SELECT COUNT(*) as total_sales, 
                              COALESCE(SUM(net_amount), 0) as total_revenue 
                              FROM sales WHERE user_id = ?");
$salesStmt->bind_param("i", $userInfo['user_id']);
$salesStmt->execute();
$stats = $salesStmt->get_result()->fetch_assoc();

// Sales today
$todaySalesStmt = $conn->prepare("SELECT COUNT(*) as today_sales, 
                                   COALESCE(SUM(net_amount), 0) as today_revenue 
                                   FROM sales 
                                   WHERE user_id = ? AND DATE(sale_date) = CURDATE()");
$todaySalesStmt->bind_param("i", $userInfo['user_id']);
$todaySalesStmt->execute();
$todayStats = $todaySalesStmt->get_result()->fetch_assoc();

// Last login (you can add a last_login field to users table later)
$joinedDate = date('M d, Y', strtotime($user['created_at']));

// Helper function to display email or N/A
$displayEmail = !empty($user['email']) ? htmlspecialchars($user['email']) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/images/logof1.png">
    <link rel="icon" type="image/png" href="assets/images/logof1.png">
    <title>Erundeniya Hospital Pharmacy</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />
    <link href="assets/css/fixes.css" rel="stylesheet" />

    <style>
        /* Profile Header - Dashboard Style */
        .profile-header {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
            padding: 40px 0;
            color: white;
            border-radius: 10px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .profile-header .container {
            position: relative;
            z-index: 2;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15), 0 8px 9px -5px rgba(20, 20, 20, 0.06);
            border: 4px solid rgba(255, 255, 255, 0.2);
        }

        /* Stats Cards - Dashboard Style */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15), 0 8px 9px -5px rgba(20, 20, 20, 0.06);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-icon.success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
        }

        .stat-icon.warning {
            background: linear-gradient(195deg, #FFA726 0%, #FB8C00 100%);
        }

        .stat-icon.danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%);
        }

        /* Info Cards - Dashboard Style */
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e9ecef;
            height: 100%;
        }

        .info-card h5 {
            color: #000;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #67748e;
            font-size: 0.875rem;
        }

        .info-value {
            color: #000;
            font-weight: 500;
        }

        .info-value.na-text {
            color: #999;
            font-style: italic;
        }

        /* Button Enhancements */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            background: linear-gradient(195deg, #343a40 0%, #121416 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15);
        }

        .btn-outline-primary {
            border: 1px solid #42424a;
            color: #42424a;
        }

        .btn-outline-primary:hover {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
            border-color: #42424a;
        }

        /* Modal Styling */
        .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        /* Form Enhancements */
        .form-control:focus,
        .form-select:focus {
            border-color: #42424a;
            box-shadow: 0 0 0 0.2rem rgba(66, 66, 74, 0.25);
        }

        /* Loading Screen */
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .simple-loader {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #42424a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Material Icons */
        .material-symbols-rounded {
            vertical-align: middle;
            font-size: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Mobile Responsive */
        @media (max-width: 576px) {
            .profile-header {
                padding: 30px 0;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                padding: 20px;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        /* Fix autocomplete background color */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #000 !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        input:-webkit-autofill {
            caret-color: #000;
        }

        /* Status Badge */
        .badge {
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
        }

        .bg-primary {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%) !important;
        }

        .bg-success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%) !important;
        }


        /* ============================================
   MAIN LAYOUT - Dashboard Style 
   ============================================ */

        .main-content {
            margin-left: 15rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            margin-top: 0 !important;
            padding-top: 1rem !important;
            position: relative;
            z-index: 1;
        }

        #navbarBlur {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        .container-fluid.py-2.mt-2 {
            padding-top: 0.5rem !important;
            margin-top: 0 !important;
        }

        /* ============================================
   SIDEBAR STYLING
   ============================================ */

        .sidenav {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 17.125rem;
            z-index: 1040;
            background: white;
            transform: translateX(0) !important;
            transition: transform 0.3s ease;
        }

        .navbar-main {
            position: relative;
            z-index: 1050 !important;
        }

        /* ============================================
   DASHBOARD HEADER
   ============================================ */

        .dashboard-header {
            padding-left: 2rem !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .dashboard-header h3 {
            margin-top: 0 !important;
            padding-top: 0 !important;
            margin-bottom: 0.25rem !important;
        }

        .dashboard-header p {
            margin-top: 0 !important;
            margin-bottom: 1rem !important;
        }

        /* ============================================
   MOBILE RESPONSIVE
   ============================================ */

        @media (max-width: 1199.98px) {
            .sidenav {
                transform: translateX(-100%) !important;
            }

            .sidenav.show {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .dashboard-header {
                padding-left: 1rem !important;
            }
        }

        /* ============================================
   SIDEBAR BACKDROP & MOBILE TOGGLE
   ============================================ */

        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1039;
            display: none;
        }

        .sidebar-backdrop.show {
            display: block;
        }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1050;
            background: #42424a;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem;
            cursor: pointer;
        }

        @media (max-width: 1199.98px) {
            .mobile-toggle {
                display: block;
            }
        }

        /* ============================================
   STATS GRID - Dashboard Cards
   ============================================ */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================
   ICON SHAPES
   ============================================ */

        .icon-shape {
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            min-height: 48px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            border-radius: 0.75rem !important;
            position: relative !important;
        }

        .icon-shape .material-symbols-rounded,
        .icon-shape i {
            font-size: 20px !important;
            line-height: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100% !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
        }

        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* ============================================
   GRADIENT BACKGROUNDS
   ============================================ */

        .bg-gradient-primary {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
        }

        .bg-gradient-info {
            background: linear-gradient(195deg, #49a3f1 0%, #1A73E8 100%);
        }

        .bg-gradient-warning {
            background: linear-gradient(195deg, #FFA726 0%, #FB8C00 100%);
        }

        .bg-gradient-danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%);
        }

        /* ============================================
   LOADING SCREEN
   ============================================ */

        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .simple-loader {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #42424a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* ============================================
   MOBILE TABLE & RESPONSIVE ELEMENTS
   ============================================ */

        .mobile-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .card-header-responsive {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .card-header-responsive {
                flex-direction: column;
                align-items: stretch;
            }

            .card-header-responsive .btn {
                width: 100%;
            }

            .mobile-table {
                font-size: 0.875rem;
            }
        }

        /* ============================================
   NAVBAR & DROPDOWN FIX
   ============================================ */

        .navbar .dropdown-menu {
            position: absolute;
            z-index: 1060 !important;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15);
        }

        .navbar {
            z-index: 1050 !important;
        }

        /* ============================================
   FILTER CARD
   ============================================ */

        .filter-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
        }

        .filter-card .card-body {
            padding: 1.5rem;
        }

        .profile--btn1 {
            background-color: #000 !important;
            color: #f3f3f3 !important;
        }

        .profile--btn1:hover {
            background-color: #ffffffff !important;
            color: #ffffffff !important;
        }

        .profile--btn2 {
            background-color: #ffffffff !important;
            color: #000000ff !important;
            border: #000 solid 1px !important;
        }

        .profile--btn2:hover {
            background-color: #000 !important;
            color: #f3f3f3 !important;
        }
    </style>
</head>

<body class="g-sidenav-show bg-gray-100">
    <div id="loading">
        <div class="simple-loader">
            <div class="loader-body"></div>
        </div>
    </div>

    <!-- Sidebar Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <!-- Navbar -->
        <?php include 'includes/header.php'; ?>
        <!-- End Navbar -->

        <div class="container-fluid py-2 mt-0">
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-header">
                        <h3 class="mb-0 h4 font-weight-bolder mt-0">User Profile</h3>
                        <p class="mb-4">View and manage your profile information and activity.</p>
                    </div>
                </div>

                <!-- Profile Header - Dashboard Style -->
                <div class="col-12 mb-4">
                    <div class="profile-header">
                        <div class="container text-center">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <div class="text-center">
                                <h2 class="mb-2 text-white"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                                <p class="mb-1 text-white opacity-8">@<?php echo htmlspecialchars($user['username']); ?></p>
                                <span class="badge bg-success text-white"><?php echo htmlspecialchars($user['role_name']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards - Dashboard Style -->
                <div class="stats-grid w-100 mb-4">
                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Sales</p>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_sales']); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">point_of_sale</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo number_format($stats['total_sales']); ?></span> completed sales
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Revenue</p>
                                        <h4 class="mb-0">Rs. <?php echo number_format($stats['total_revenue'], 2); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">payments</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder">Total</span> earnings
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Today's Sales</p>
                                        <h4 class="mb-0"><?php echo number_format($todayStats['today_sales']); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">today</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-info font-weight-bolder"><?php echo $todayStats['today_sales']; ?> sales</span> today
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Today's Revenue</p>
                                        <h4 class="mb-0">Rs. <?php echo number_format($todayStats['today_revenue'], 2); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">trending_up</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-warning font-weight-bolder">Rs. <?php echo number_format($todayStats['today_revenue'], 2); ?></span> earned today
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Information - Dashboard Style -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="info-card">
                            <h5 class="mb-4">
                                <i class="material-symbols-rounded me-2">person</i>
                                Personal Information
                            </h5>
                            <div class="info-row">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Username</span>
                                <span class="info-value">@<?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value <?php echo $displayEmail === 'N/A' ? 'na-text' : ''; ?>">
                                    <?php echo $displayEmail; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Role</span>
                                <span class="info-value">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo $joinedDate; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="info-card">
                            <h5 class="mb-4">
                                <i class="material-symbols-rounded me-2">settings</i>
                                Account Settings
                            </h5>
                            <button class="btn btn-primary w-100 mb-3" onclick="showEditProfileModal()">
                                <i class="material-symbols-rounded me-2">edit</i>
                                Edit Profile
                            </button>

                            <h6 class="text-muted mb-3">Quick Actions</h6>
                            <a href="pos.php" class="btn btn-outline-primary w-100 mb-2 profile--btn1">
                                <i class="material-symbols-rounded me-2">point_of_sale</i>
                                New Sale
                            </a>
                            <?php if ($user['role_id'] == 3): ?>
                                <button class="btn btn-outline-secondary w-100" disabled title="Access denied for Cashiers">
                                    <i class="material-symbols-rounded me-2">history</i>
                                    View Sales History
                                </button>
                            <?php else: ?>
                                <a href="sales_history.php" class="btn btn-outline-primary w-100 profile--btn2">
                                    <i class="material-symbols-rounded me-2">history</i>
                                    View Sales History
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <!-- Edit Profile Modal - Dashboard Style -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-symbols-rounded me-2">edit</i>
                        Edit Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="profileForm">
                        <div class="mb-3">
                            <label for="fullName" class="form-label">
                                <i class="material-symbols-rounded me-1">person</i>
                                Full Name (Required)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="material-symbols-rounded">person</i></span>
                                <input type="text" class="form-control" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="material-symbols-rounded me-1">email</i>
                                Email (Optional)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="material-symbols-rounded">email</i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="abc@gmail.com">
                            </div>
                            <small class="text-muted">Leave empty if not available</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-symbols-rounded me-1">close</i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="updateProfile()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>

    <script>
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }

        // Mobile sidebar toggle with backdrop
        function toggleSidebar() {
            var sidenav = document.getElementById('sidenav-main');
            var backdrop = document.getElementById('sidebarBackdrop');
            var body = document.body;

            sidenav.classList.toggle('show');
            backdrop.classList.toggle('show');
            body.classList.toggle('sidebar-open');
        }

        // Close sidebar function
        function closeSidebar() {
            var sidenav = document.getElementById('sidenav-main');
            var backdrop = document.getElementById('sidebarBackdrop');
            var body = document.body;

            sidenav.classList.remove('show');
            backdrop.classList.remove('show');
            body.classList.remove('sidebar-open');
        }

        // Toggle button events
        var mobileToggle = document.getElementById('mobileToggle');
        var iconNavbarSidenav = document.getElementById('iconNavbarSidenav');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', toggleSidebar);
        }

        if (iconNavbarSidenav) {
            iconNavbarSidenav.addEventListener('click', toggleSidebar);
        }

        // Backdrop click to close
        var sidebarBackdrop = document.getElementById('sidebarBackdrop');
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking anywhere outside on mobile
        document.addEventListener('click', function(event) {
            var sidenav = document.getElementById('sidenav-main');
            var mobileToggle = document.getElementById('mobileToggle');
            var iconNavbarSidenav = document.getElementById('iconNavbarSidenav');

            // Check if sidebar is open and click is outside sidebar and toggle buttons
            if (window.innerWidth <= 1199.98 &&
                sidenav &&
                sidenav.classList.contains('show') &&
                !sidenav.contains(event.target) &&
                (!mobileToggle || event.target !== mobileToggle && !mobileToggle.contains(event.target)) &&
                (!iconNavbarSidenav || event.target !== iconNavbarSidenav && !iconNavbarSidenav.contains(event.target))) {
                closeSidebar();
            }
        });

        // Close sidebar on any navigation link click (mobile only)
        document.addEventListener('DOMContentLoaded', function() {
            var navLinks = document.querySelectorAll('#sidenav-main .nav-link');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 1199.98) {
                        closeSidebar();
                    }
                });
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1199.98) {
                closeSidebar();
            }
        });

        // Prevent dropdown from going behind cards
        document.querySelectorAll('.dropdown-toggle').forEach(function(dropdown) {
            dropdown.addEventListener('show.bs.dropdown', function() {
                var dropdownMenu = this.nextElementSibling;
                var rect = dropdownMenu.getBoundingClientRect();
                var viewportHeight = window.innerHeight;

                if (rect.bottom > viewportHeight) {
                    dropdownMenu.style.top = 'auto';
                    dropdownMenu.style.bottom = '100%';
                }
            });
        });

        // Hide loading when page is loaded
        window.addEventListener('load', function() {
            var loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        });

        // Fix for iOS Safari
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.body.classList.add('ios-device');
        }

        // Profile functions
        const editProfileModal = new bootstrap.Modal(document.getElementById('editProfileModal'));

        function showEditProfileModal() {
            editProfileModal.show();
        }

        function updateProfile() {
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!fullName) {
                Swal.fire('Error', 'Full name is required', 'error');
                return;
            }

            fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: fullName,
                        email: email || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', 'Profile updated successfully', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred', 'error');
                });
        }


        // ============================================
        // PERFECT SCROLLBAR 
        // ============================================
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }

        // ============================================
        // MOBILE SIDEBAR TOGGLE FUNCTIONS
        // ============================================

        function toggleSidebar() {
            var sidenav = document.getElementById('sidenav-main');
            var backdrop = document.getElementById('sidebarBackdrop');
            var body = document.body;

            sidenav.classList.toggle('show');
            backdrop.classList.toggle('show');
            body.classList.toggle('sidebar-open');
        }

        function closeSidebar() {
            var sidenav = document.getElementById('sidenav-main');
            var backdrop = document.getElementById('sidebarBackdrop');
            var body = document.body;

            sidenav.classList.remove('show');
            backdrop.classList.remove('show');
            body.classList.remove('sidebar-open');
        }

        // ============================================
        // TOGGLE BUTTON EVENTS
        // ============================================

        var mobileToggle = document.getElementById('mobileToggle');
        var iconNavbarSidenav = document.getElementById('iconNavbarSidenav');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', toggleSidebar);
        }

        if (iconNavbarSidenav) {
            iconNavbarSidenav.addEventListener('click', toggleSidebar);
        }

        // ============================================
        // BACKDROP CLICK TO CLOSE
        // ============================================

        var sidebarBackdrop = document.getElementById('sidebarBackdrop');
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        // ============================================
        // CLOSE SIDEBAR WHEN CLICKING OUTSIDE (Mobile)
        // ============================================

        document.addEventListener('click', function(event) {
            var sidenav = document.getElementById('sidenav-main');
            var mobileToggle = document.getElementById('mobileToggle');
            var iconNavbarSidenav = document.getElementById('iconNavbarSidenav');

            if (window.innerWidth <= 1199.98 &&
                sidenav &&
                sidenav.classList.contains('show') &&
                !sidenav.contains(event.target) &&
                (!mobileToggle || event.target !== mobileToggle && !mobileToggle.contains(event.target)) &&
                (!iconNavbarSidenav || event.target !== iconNavbarSidenav && !iconNavbarSidenav.contains(event.target))) {
                closeSidebar();
            }
        });

        // ============================================
        // CLOSE SIDEBAR ON NAVIGATION LINK CLICK (Mobile)
        // ============================================

        document.addEventListener('DOMContentLoaded', function() {
            var navLinks = document.querySelectorAll('#sidenav-main .nav-link');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 1199.98) {
                        closeSidebar();
                    }
                });
            });
        });

        // ============================================
        // HANDLE WINDOW RESIZE
        // ============================================

        window.addEventListener('resize', function() {
            if (window.innerWidth > 1199.98) {
                closeSidebar();
            }
        });

        // ============================================
        // DROPDOWN FIX 
        // ============================================

        document.querySelectorAll('.dropdown-toggle').forEach(function(dropdown) {
            dropdown.addEventListener('show.bs.dropdown', function() {
                var dropdownMenu = this.nextElementSibling;
                var rect = dropdownMenu.getBoundingClientRect();
                var viewportHeight = window.innerHeight;

                if (rect.bottom > viewportHeight) {
                    dropdownMenu.style.top = 'auto';
                    dropdownMenu.style.bottom = '100%';
                }
            });
        });

        // ============================================
        // HIDE LOADING SCREEN
        // ============================================

        window.addEventListener('load', function() {
            var loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        });

        // ============================================
        // RESPONSIVE TABLE IMPROVEMENTS
        // ============================================

        function makeTableResponsive() {
            var tables = document.querySelectorAll('.mobile-table table');
            tables.forEach(function(table) {
                if (window.innerWidth < 768) {
                    table.classList.add('table-sm');
                } else {
                    table.classList.remove('table-sm');
                }
            });
        }

        window.addEventListener('load', makeTableResponsive);
        window.addEventListener('resize', makeTableResponsive);

        // ============================================
        // FIX FOR iOS SAFARI
        // ============================================

        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.body.classList.add('ios-device');
        }
    </script>
</body>

</html>