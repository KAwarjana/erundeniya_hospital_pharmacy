<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get date range - empty by default to show all data
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Sales Report with dynamic date filter
$salesSQL = "SELECT 
    DATE(sale_date) as date,
    COUNT(*) as total_sales,
    SUM(total_amount) as gross_sales,
    SUM(discount) as total_discount,
    SUM(net_amount) as net_sales
FROM sales";

if (!empty($dateFrom) && !empty($dateTo)) {
    $salesSQL .= " WHERE DATE(sale_date) BETWEEN ? AND ?";
}

$salesSQL .= " GROUP BY DATE(sale_date) ORDER BY date DESC";

if (!empty($dateFrom) && !empty($dateTo)) {
    $salesReport = $conn->prepare($salesSQL);
    $salesReport->bind_param("ss", $dateFrom, $dateTo);
    $salesReport->execute();
    $salesData = $salesReport->get_result();
} else {
    $salesData = $conn->query($salesSQL);
}

// Top Selling Products with dynamic date filter
$topProductsSQL = "SELECT 
    p.product_name,
    SUM(si.quantity) as total_quantity,
    SUM(si.total_price) as total_revenue
FROM sale_items si
JOIN product_batches pb ON si.batch_id = pb.batch_id
JOIN products p ON pb.product_id = p.product_id
JOIN sales s ON si.sale_id = s.sale_id";

if (!empty($dateFrom) && !empty($dateTo)) {
    $topProductsSQL .= " WHERE DATE(s.sale_date) BETWEEN ? AND ?";
}

$topProductsSQL .= " GROUP BY p.product_id ORDER BY total_quantity DESC LIMIT 10";

if (!empty($dateFrom) && !empty($dateTo)) {
    $topProducts = $conn->prepare($topProductsSQL);
    $topProducts->bind_param("ss", $dateFrom, $dateTo);
    $topProducts->execute();
    $topProductsData = $topProducts->get_result();
} else {
    $topProductsData = $conn->query($topProductsSQL);
}

// Low Stock Products (not date dependent)
$lowStock = $conn->query("SELECT 
    p.product_name,
    p.reorder_level,
    SUM(pb.quantity_in_stock) as current_stock
FROM products p
JOIN product_batches pb ON p.product_id = pb.product_id
GROUP BY p.product_id
HAVING current_stock <= p.reorder_level
ORDER BY current_stock ASC");

// Expiring Products (not date dependent)
$expiringProducts = $conn->query("SELECT 
    p.product_name,
    pb.batch_no,
    pb.expiry_date,
    pb.quantity_in_stock,
    DATEDIFF(pb.expiry_date, CURDATE()) as days_left
FROM product_batches pb
JOIN products p ON pb.product_id = p.product_id
WHERE pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
AND pb.quantity_in_stock > 0
ORDER BY pb.expiry_date ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/images/logoblack.png">
    <link rel="icon" type="image/png" href="assets/images/logoblack.png">
    <title>Reports - E. W. D. Erundeniya</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />

    <style>
        /* Dashboard Style Enhancements */
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

        /* Print Styles */
        @media print {
            /* Hide elements that shouldn't be printed */
            #loading,
            .sidenav,
            .navbar-main,
            .no-print,
            .btn,
            button,
            .card-body form {
                display: none !important;
            }

            /* Adjust main content for print */
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            /* Card adjustments */
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                margin-bottom: 20px;
            }

            .card-header {
                background-color: #f8f9fa !important;
                border-bottom: 2px solid #000 !important;
                padding: 10px 15px !important;
            }

            /* Table styling for print */
            table {
                width: 100% !important;
                font-size: 12px !important;
            }

            table thead {
                background-color: #f0f0f0 !important;
            }

            table th,
            table td {
                padding: 8px !important;
                border: 1px solid #ddd !important;
            }

            @page {
                margin: 15mm;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }

        /* Print Header (only visible when printing) */
        .print-header {
            display: none;
        }

        @media print {
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #000;
                padding-bottom: 15px;
            }

            .print-header h1 {
                margin: 0;
                font-size: 24px;
                color: #000;
            }

            .print-header p {
                margin: 5px 0;
                font-size: 14px;
            }
        }

        /* Filter Card - Dashboard Style */
        .filter-card {
            background: linear-gradient(195deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
        }

        .filter-card .card-body {
            padding: 1.5rem;
        }

        /* Card Enhancements */
        .card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15), 0 8px 9px -5px rgba(20, 20, 20, 0.06);
            transform: translateY(-2px);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }

        .card-header h4,
        .card-header h6 {
            margin-bottom: 0;
            color: #344767;
            font-weight: 600;
        }

        /* Table Enhancements */
        .table thead th {
            border-top: none;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #67748e;
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            padding: 1rem 0.75rem;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        /* Button Enhancements */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-success:hover {
            background: linear-gradient(195deg, #5cb85c 0%, #3d8b40 100%);
            transform: translateY(-1px);
        }

        .btn-info {
            background: linear-gradient(195deg, #49a3f1 0%, #1A73E8 100%);
            border: none;
        }

        .btn-info:hover {
            background: linear-gradient(195deg, #3d8b40 0%, #1565c0 100%);
            transform: translateY(-1px);
        }

        /* Form Enhancements */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #42424a;
            box-shadow: 0 0 0 0.2rem rgba(66, 66, 74, 0.25);
        }

        /* Input Group Enhancements */
        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem 0 0 0.5rem;
        }

        /* Icon Shape */
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

        .icon-shape .material-symbols-rounded {
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

        /* Material Icons */
        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 576px) {
            .card-header .row {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
        }

        /* Status Badge */
        .badge {
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
        }

        .badge.bg-danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(195deg, #FFA726 0%, #FB8C00 100%) !important;
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

        .bg-gradient-warning {
            background: linear-gradient(195deg, #FFA726 0%, #FB8C00 100%);
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
                        <h3 class="mb-0 h4 font-weight-bolder mt-0">Sales Reports</h3>
                        <p class="mb-4">View comprehensive sales reports and business analytics.</p>
                    </div>
                </div>

                <!-- Print Header (only visible when printing) -->
                <div class="print-header">
                    <h1>Ayurvedic Pharmacy - Sales Report</h1>
                    <?php if (!empty($dateFrom) && !empty($dateTo)): ?>
                        <p>Report Period: <?php echo date('M d, Y', strtotime($dateFrom)); ?> to <?php echo date('M d, Y', strtotime($dateTo)); ?></p>
                    <?php else: ?>
                        <p>Report Period: All Time</p>
                    <?php endif; ?>
                    <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
                </div>

                <!-- Date Range Filter - Dashboard Style -->
                <div class="row no-print mb-4">
                    <div class="col-12">
                        <div class="card filter-card">
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label text-sm fw-bold">
                                            <i class="material-symbols-rounded me-1">calendar_today</i>
                                            Date From
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">event</i></span>
                                            <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-sm fw-bold">
                                            <i class="material-symbols-rounded me-1">calendar_today</i>
                                            Date To
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">event</i></span>
                                            <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-symbols-rounded me-1">filter_alt</i>
                                            Generate Report
                                        </button>
                                        <a href="reports.php" class="btn btn-secondary">
                                            <i class="material-symbols-rounded me-1">refresh</i>
                                            Reset
                                        </a>
                                        <button type="button" class="btn btn-success" onclick="window.print()">
                                            <i class="material-symbols-rounded me-1">print</i>
                                            Print Report
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="exportStockReport()">
                                            <i class="material-symbols-rounded me-1">download</i>
                                            Export Stock
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Sales Report - Dashboard Style -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Daily Sales Report</h6>
                                        <p class="text-sm mb-0">
                                            <i class="fa fa-chart-line text-info" aria-hidden="true"></i>
                                            <span class="font-weight-bold ms-1"><?php echo $salesData->num_rows; ?></span> days of sales data
                                        </p>
                                    </div>
                                    <div>
                                        <?php if (!empty($dateFrom) && !empty($dateTo)): ?>
                                            <span class="badge bg-info text-white">
                                                <?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">All Time</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body px-0 pb-2">
                                <div class="table-responsive px-3">
                                    <table class="table table-hover align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Sales</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Gross Sales</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Discount</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Net Sales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalSales = 0;
                                            $totalGross = 0;
                                            $totalDiscount = 0;
                                            $totalNet = 0;
                                            
                                            if ($salesData->num_rows > 0):
                                                while ($row = $salesData->fetch_assoc()):
                                                    $totalSales += $row['total_sales'];
                                                    $totalGross += $row['gross_sales'];
                                                    $totalDiscount += $row['total_discount'];
                                                    $totalNet += $row['net_sales'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm"><?php echo date('M d, Y', strtotime($row['date'])); ?></h6>
                                                                <p class="text-xs text-secondary mb-0"><?php echo date('l', strtotime($row['date'])); ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <span class="badge bg-primary"><?php echo $row['total_sales']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-normal">Rs. <?php echo number_format($row['gross_sales'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-normal">Rs. <?php echo number_format($row['total_discount'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold text-success">Rs. <?php echo number_format($row['net_sales'], 2); ?></span>
                                                    </td>
                                                </tr>
                                            <?php 
                                                endwhile;
                                            else:
                                            ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="material-symbols-rounded text-muted mb-3" style="font-size: 48px;">bar_chart</i>
                                                            <h6 class="text-muted mb-2">No sales data found</h6>
                                                            <p class="text-muted mb-3">
                                                                <?php if (!empty($dateFrom) || !empty($dateTo)): ?>
                                                                    There are no sales records for the selected date range.
                                                                <?php else: ?>
                                                                    There are no sales records in the system yet.
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <?php if ($salesData->num_rows > 0): ?>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">TOTAL</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7"><?php echo $totalSales; ?></th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rs. <?php echo number_format($totalGross, 2); ?></th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rs. <?php echo number_format($totalDiscount, 2); ?></th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Rs. <?php echo number_format($totalNet, 2); ?></th>
                                            </tr>
                                        </tfoot>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Grid - Dashboard Style -->
            <div class="row mt-4">
                <!-- Top Selling Products -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Top Selling Products</h6>
                                    <p class="text-sm mb-0">
                                        <i class="fa fa-star text-warning" aria-hidden="true"></i>
                                        Best performing products by quantity
                                    </p>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10 text-white">trending_up</i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive px-3">
                                <table class="table table-hover align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Product</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity Sold</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if ($topProductsData->num_rows > 0):
                                            while ($product = $topProductsData->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="badge bg-warning text-dark"><?php echo $product['total_quantity']; ?></span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-bold text-success">Rs. <?php echo number_format($product['total_revenue'], 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-muted mb-2" style="font-size: 32px;">star</i>
                                                        <p class="text-muted mb-0">No products sold yet</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Low Stock Alert</h6>
                                    <p class="text-sm mb-0">
                                        <i class="fa fa-exclamation-triangle text-danger" aria-hidden="true"></i>
                                        Products needing attention
                                    </p>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-danger shadow-danger text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10 text-white">warning</i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive px-3">
                                <table class="table table-hover align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Product</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Current Stock</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reorder Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if ($lowStock->num_rows > 0):
                                            while ($item = $lowStock->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="badge bg-danger"><?php echo $item['current_stock']; ?></span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-normal"><?php echo $item['reorder_level']; ?></span>
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-success mb-2" style="font-size: 32px;">check_circle</i>
                                                        <p class="text-success mb-0">All products have sufficient stock</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiring Products - Dashboard Style -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Products Expiring in Next 3 Months</h6>
                                    <p class="text-sm mb-0">
                                        <i class="fa fa-calendar-times text-warning" aria-hidden="true"></i>
                                        <span class="font-weight-bold ms-1"><?php echo $expiringProducts->num_rows; ?></span> products expiring soon
                                    </p>
                                </div>
                                <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning text-center border-radius-lg">
                                    <i class="material-symbols-rounded opacity-10 text-white">schedule</i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive px-3">
                                <table class="table table-hover align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Product</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Batch No</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Expiry Date</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Days Left</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if ($expiringProducts->num_rows > 0):
                                            while ($exp = $expiringProducts->fetch_assoc()): 
                                                $badgeClass = 'warning';
                                                if ($exp['days_left'] < 0) $badgeClass = 'danger';
                                                elseif ($exp['days_left'] <= 30) $badgeClass = 'warning';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($exp['product_name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-sm font-weight-normal"><?php echo htmlspecialchars($exp['batch_no']); ?></span>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="text-xs font-weight-bold">
                                                        <?php echo date('M d, Y', strtotime($exp['expiry_date'])); ?>
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                                        <?php echo abs($exp['days_left']); ?> days <?php echo $exp['days_left'] < 0 ? 'ago' : 'left'; ?>
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-normal"><?php echo $exp['quantity_in_stock']; ?></span>
                                                </td>
                                            </tr>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-success mb-3" style="font-size: 48px;">check_circle</i>
                                                        <h6 class="text-success mb-2">No products expiring soon</h6>
                                                        <p class="text-success mb-0">All products have sufficient shelf life</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include 'includes/footer.php'; ?>
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

        // Fix for iOS Safari
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.body.classList.add('ios-device');
        }

        // Export function
        function exportStockReport() {
            window.location.href = 'export_stock.php';
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