<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();
$userInfo = Auth::getUserInfo();

// Get dashboard statistics
$stats = [];

// Total Products
$result = $conn->query("SELECT COUNT(DISTINCT product_id) as total FROM products");
$stats['total_products'] = intval($result->fetch_assoc()['total'] ?? 0);

// Total Stock Value
$result = $conn->query("SELECT SUM(pb.quantity_in_stock * pb.cost_price) as total_value 
                        FROM product_batches pb");
$stats['stock_value'] = floatval($result->fetch_assoc()['total_value'] ?? 0);

// Today's Sales
$result = $conn->query("SELECT COUNT(*) as total_sales, IFNULL(SUM(net_amount), 0) as total_amount 
                        FROM sales 
                        WHERE DATE(sale_date) = CURDATE()");
$todaySales = $result->fetch_assoc();
$stats['today_sales'] = intval($todaySales['total_sales'] ?? 0);
$stats['today_revenue'] = floatval($todaySales['total_amount'] ?? 0);

// Low Stock Products
$result = $conn->query("SELECT COUNT(*) as low_stock FROM (
                        SELECT p.product_id, p.reorder_level, SUM(pb.quantity_in_stock) as total_stock
                        FROM products p
                        LEFT JOIN product_batches pb ON p.product_id = pb.product_id
                        GROUP BY p.product_id, p.reorder_level
                        HAVING total_stock <= p.reorder_level
                        ) as low_stock_items");
$lowStockResult = $result->fetch_assoc();
$stats['low_stock'] = $lowStockResult['low_stock'] ?? 0;

// Recent Sales
$recentSales = $conn->query("SELECT s.sale_id, s.sale_date, c.name as customer_name, 
                             s.net_amount, s.payment_type 
                             FROM sales s
                             LEFT JOIN customers c ON s.customer_id = c.customer_id
                             ORDER BY s.sale_date DESC
                             LIMIT 5");

// Expiring Soon Products
$expiringSoon = $conn->query("SELECT p.product_name, pb.batch_no, pb.expiry_date, pb.quantity_in_stock
                              FROM product_batches pb
                              JOIN products p ON pb.product_id = p.product_id
                              WHERE pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                              AND pb.quantity_in_stock > 0
                              ORDER BY pb.expiry_date ASC
                              LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/images/logoblack.png">
    <link rel="icon" type="image/png" href="assets/images/logoblack.png">
    <title>Dashboard - E. W. D. Erundeniya</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />

    <style>
        /* FIX FOR UNWANTED TOP GAP */

        /* Remove excessive top margin from main content */
        .main-content {
            margin-left: 15rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            margin-top: 0 !important;
            padding-top: 1rem !important;
            position: relative;
            z-index: 1;
        }

        /* Remove default navbar margin */
        #navbarBlur {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        /* Fix container top padding */
        .container-fluid.py-2.mt-2 {
            padding-top: 0.5rem !important;
            margin-top: 0 !important;
        }

        /* Remove excessive margin from dashboard title */
        .col-12 h3 {
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }

        .col-12 p {
            margin-top: 0 !important;
            margin-bottom: 1rem !important;
        }

        /* Fix breadcrumb spacing */
        nav[aria-label="breadcrumb"] {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        .breadcrumb {
            margin-bottom: 0 !important;
        }

        /* Remove excessive card header padding */
        .card-header {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }

        /* CRITICAL SIDEBAR VISIBILITY FIXES */

        /* Force sidebar visible on desktop */
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

        /* Fix navbar z-index to stay above sidebar */
        .navbar-main {
            position: relative;
            z-index: 1050 !important;
        }

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

        /* Mobile sidebar - hidden by default */
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

        /* Sidebar backdrop for mobile */
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

        /* Mobile toggle button */
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

        /* Navbar dropdown z-index fix */
        .navbar .dropdown-menu {
            position: absolute;
            z-index: 1060 !important;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15);
        }

        .navbar {
            z-index: 1050 !important;
        }

        /* Card Responsive Grid */
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

        /* Table Mobile Responsive */
        .mobile-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Card Header Responsive */
        .card-header-responsive {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Icon Shape - FIXED FOR PROPER CENTERING */
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

        /* Timeline Responsive */
        .timeline-block {
            position: relative;
            padding-left: 35px;
        }

        .timeline-step {
            position: absolute;
            left: 0;
            top: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff;
            flex-shrink: 0;
        }

        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Gradient Backgrounds */
        .bg-gradient-primary {
            background: linear-gradient(195deg, #42424a 0%, #191919 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
        }

        .bg-gradient-info {
            background: linear-gradient(195deg, #49a3f1 0%, #1A73E8 100%);
        }

        .bg-gradient-danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%);
        }

        /* Status Colors */
        .status-cash {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-credit {
            background: #fff3e0;
            color: #f57c00;
        }

        /* Material Icons Alignment - FIXED */
        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Active Link Highlight */
        .nav-link.active {
            background-color: #344767 !important;
            color: white !important;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-link.active i {
            color: white !important;
        }

        /* Hover Effects */
        .nav-link:not(.active):not(.disabled):hover {
            background-color: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        /* Disabled Links */
        .nav-link.disabled {
            pointer-events: none;
            opacity: 0.5;
        }

        /* Logout Hover Effect */
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

        .footer {
            margin-top: auto !important;
            padding-top: 2rem !important;
            padding-bottom: 1rem !important;
        }

        /* Ensure dropdown stays above all cards and content */
        .dropdown-menu {
            z-index: 1060 !important;
        }

        /* NAVBAR FIXES - User Profile Icon Alignment */
        .navbar .nav-item .nav-link {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            padding: 0.5rem 0.75rem !important;
        }

        .navbar .nav-item .material-symbols-rounded {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
            font-size: 20px !important;
            line-height: 1 !important;
            margin: 0 !important;
        }

        /* BREADCRUMB FIXES - Dashboard Text Alignment */
        .breadcrumb {
            display: flex !important;
            align-items: center !important;
            margin-bottom: 0 !important;
        }

        .breadcrumb-item {
            display: flex !important;
            align-items: center !important;
        }

        .breadcrumb-item a {
            display: flex !important;
            align-items: center !important;
        }

        .breadcrumb-item .material-symbols-rounded {
            font-size: 20px !important;
            line-height: 1 !important;
            vertical-align: middle !important;
        }

        /* Fix card header content alignment */
        .card-header .d-flex {
            align-items: center !important;
        }

        /* Fix dropdown menu icon alignment */
        .dropdown-menu .material-symbols-rounded {
            vertical-align: middle !important;
            display: inline-flex !important;
            align-items: center !important;
        }

        /* Mobile Specific Styles */
        @media (max-width: 576px) {
            .ms-3 {
                margin-left: 0.5rem !important;
            }

            .h4 {
                font-size: 1.1rem;
            }

            .mb-4 {
                margin-bottom: 1rem !important;
            }

            .card-header {
                padding: 1rem !important;
            }

            .card-body {
                padding: 1rem !important;
            }

            .mobile-table {
                font-size: 0.875rem;
            }

            .status-badge {
                font-size: 0.75rem;
                padding: 2px 6px;
            }

            /* Fix dropdown positioning on mobile */
            .navbar .dropdown-menu {
                position: absolute !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
            }

            /* Add margin for mobile toggle button */
            .main-content {
                padding-top: 3rem;
            }

            /* Reduce mobile toggle size */
            .mobile-toggle {
                top: 0.5rem;
                left: 0.5rem;
                padding: 0.25rem;
            }

            /* Mobile icon fixes */
            .icon-shape {
                width: 40px !important;
                height: 40px !important;
                min-width: 40px !important;
                min-height: 40px !important;
            }

            .icon-shape .material-symbols-rounded,
            .icon-shape i {
                font-size: 20px !important;
            }

            .navbar .nav-item .material-symbols-rounded {
                font-size: 20px !important;
            }
        }

        /* Small Mobile Devices */
        @media (max-width: 375px) {
            .card-header h4 {
                font-size: 1rem;
            }

            .card-header p {
                font-size: 0.75rem;
            }

            .icon-shape {
                width: 36px !important;
                height: 36px !important;
                min-width: 36px !important;
                min-height: 36px !important;
            }

            .icon-shape i,
            .icon-shape .material-symbols-rounded {
                font-size: 20px !important;
            }

            .navbar .nav-item .material-symbols-rounded {
                font-size: 20px !important;
            }

            .mobile-table {
                font-size: 0.75rem;
            }

            .status-badge {
                font-size: 0.7rem;
            }

            .sidenav {
                width: 100%;
            }

            .mobile-toggle {
                top: 0.5rem;
                left: 0.5rem;
            }
        }

        /* Fix for iOS Safari issues */
        @supports (-webkit-touch-callout: none) {
            .sidenav {
                height: -webkit-fill-available;
            }
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

        /* Notification Badge */
        .navbar .badge {
            font-size: 0.65rem;
        }

        /* Dropdown Menu Styling */
        .dropdown-menu {
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15), 0 8px 9px -5px rgba(20, 20, 20, 0.06);
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item.text-danger:hover {
            background-color: #ffebee;
        }

        /* Mobile Toggle Icon */
        .sidenav-toggler-inner {
            width: 20px;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .sidenav-toggler-line {
            height: 2px;
            background-color: #344767;
            border-radius: 2px;
            transition: all 0.3s;
        }

        /* Breadcrumb */
        .breadcrumb-item+.breadcrumb-item::before {
            content: "›";
            font-size: 1.2rem;
        }

        /* Navbar Blur Effect */
        #navbarBlur {
            backdrop-filter: saturate(200%) blur(30px);
            background-color: rgba(255, 255, 255, 0.8) !important;
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
                <div class="col-12 dashboard-header">
                    <h3 class="mb-0 h4 font-weight-bolder mt-0">Dashboard</h3>
                    <p class="mb-4">Welcome back, <?php echo htmlspecialchars($userInfo['username']); ?>! Here's what's happening today.</p>
                </div>

                <!-- Statistics Cards - Responsive Grid -->
                <div class="stats-grid w-100">
                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Products</p>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_products']); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">inventory_2</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo number_format($stats['total_products']); ?></span> items in inventory
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Stock Value</p>
                                        <h4 class="mb-0">Rs. <?php echo number_format($stats['stock_value'], 2); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">payments</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder">Total value</span> of inventory
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
                                        <h4 class="mb-0">Rs. <?php echo number_format($stats['today_revenue'], 2); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">trending_up</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo $stats['today_sales']; ?> sales</span> completed today
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Low Stock Items</p>
                                        <h4 class="mb-0 text-danger"><?php echo number_format($stats['low_stock']); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-danger shadow-danger shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">warning</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-danger font-weight-bolder"><?php echo $stats['low_stock']; ?> items</span> need reorder
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sales and Expiring Soon - Responsive Layout -->
            <div class="row mt-4">
                <div class="col-lg-8 col-md-12 mb-lg-0 mb-4">
                    <div class="card">
                        <div class="card-header pb-0 card-header-responsive">
                            <div>
                                <h6>Recent Sales</h6>
                                <p class="text-sm mb-0">
                                    <i class="fa fa-check text-info" aria-hidden="true"></i>
                                    <span class="font-weight-bold ms-1"><?php echo $stats['today_sales']; ?></span> sales today
                                </p>
                            </div>
                            <div>
                                <a href="sales_history.php" class="btn btn-sm btn-dark mb-0">View All</a>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive mobile-table">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Sale ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Amount</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Payment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentSales->num_rows > 0): ?>
                                            <?php while ($sale = $recentSales->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm">#<?php echo str_pad($sale['sale_id'], 5, '0', STR_PAD_LEFT); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></p>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <span class="text-xs font-weight-bold">
                                                            <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                                            <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold">Rs. <?php echo number_format($sale['net_amount'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <span class="status-badge status-<?php echo strtolower($sale['payment_type']); ?>">
                                                            <?php echo ucfirst($sale['payment_type']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <p class="text-sm text-secondary mb-0">No sales recorded yet</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6>Expiring Soon</h6>
                            <p class="text-sm">
                                <i class="fa fa-info-circle text-warning" aria-hidden="true"></i>
                                <span class="font-weight-bold">Products expiring in 3 months</span>
                            </p>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($expiringSoon->num_rows > 0): ?>
                                <div class="timeline timeline-one-side">
                                    <?php while ($item = $expiringSoon->fetch_assoc()): ?>
                                        <?php
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $today = new DateTime();
                                        $daysLeft = $today->diff($expiryDate)->days;
                                        $iconColor = $daysLeft < 30 ? 'danger' : 'warning';
                                        ?>
                                        <div class="timeline-block mb-3">
                                            <span class="timeline-step">
                                                <i class="material-symbols-rounded text-<?php echo $iconColor; ?> text-gradient">schedule</i>
                                            </span>
                                            <div class="timeline-content">
                                                <h6 class="text-dark text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                                                    Batch: <?php echo htmlspecialchars($item['batch_no']); ?> •
                                                    <span class="text-<?php echo $iconColor; ?>"><?php echo $daysLeft; ?> days left</span>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted py-4">No items expiring soon</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

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

        // Responsive table improvements
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

        // Call on load and resize
        window.addEventListener('load', makeTableResponsive);
        window.addEventListener('resize', makeTableResponsive);

        // Fix for iOS Safari
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.body.classList.add('ios-device');
        }
    </script>
</body>

</html>