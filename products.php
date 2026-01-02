<?php
require_once 'auth.php';
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters
$stockStatus = $_GET['stock_status'] ?? '';
$productStatus = $_GET['product_status'] ?? 'active';
$searchTerm = $_GET['search'] ?? '';

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name");

// Build the query with filters
$sql = "SELECT 
    p.product_id,
    p.display_id,
    LPAD(p.display_id, 2, '0') as formatted_display_id,
    p.product_name,
    p.generic_name,
    p.unit,
    p.reorder_level,
    p.status,
    COALESCE(SUM(pb.quantity_in_stock), 0) as total_stock,
    COUNT(pb.batch_id) as batch_count
FROM products p
LEFT JOIN product_batches pb ON p.product_id = pb.product_id";

$whereClauses = [];
$params = [];
$types = "";

if ($productStatus !== 'all') {
    $whereClauses[] = "p.status = ?";
    $params[] = $productStatus;
    $types .= "s";
}

if (!empty($searchTerm)) {
    // Search by display_id, product_name, or generic_name
    if (is_numeric($searchTerm)) {
        $whereClauses[] = "(p.display_id = ? OR p.product_name LIKE ? OR p.generic_name LIKE ? OR p.product_id LIKE ?)";
        $params[] = $searchTerm;
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $types .= "isss";
    } else {
        $whereClauses[] = "(p.product_name LIKE ? OR p.generic_name LIKE ? OR p.product_id LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $types .= "sss";
    }
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " GROUP BY p.product_id";

if ($stockStatus === 'out_of_stock') {
    $sql .= " HAVING total_stock = 0";
} elseif ($stockStatus === 'low_stock') {
    $sql .= " HAVING total_stock > 0 AND total_stock <= p.reorder_level";
} elseif ($stockStatus === 'in_stock') {
    $sql .= " HAVING total_stock > p.reorder_level";
}

$sql .= " ORDER BY p.display_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/images/logoblack.png">
    <link rel="icon" type="image/png" href="assets/images/logoblack.png">
    <title>Products - E. W. D. Erundeniya</title>

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />

    <style>
        /* Custom styles to match dashboard */
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

        .card-header-responsive {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .mobile-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Filter form styling */
        .filter-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
        }

        .filter-card .card-body {
            padding: 1.5rem;
        }

        /* Table enhancements */
        .table thead th {
            border-top: none;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #67748e;
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        /* Button enhancements */
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
        }

        .btn-icon svg {
            width: 16px;
            height: 16px;
        }

        /* Modal styling */
        .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        /* Form enhancements */
        .form-control:focus,
        .form-select:focus {
            border-color: #42424a;
            box-shadow: 0 0 0 0.2rem rgba(66, 66, 74, 0.25);
        }

        /* Card header styling */
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.5rem;
        }

        .card-header h4,
        .card-header h6 {
            margin-bottom: 0;
        }

        /* Status colors */
        .bg-success-light {
            background-color: #d4edda !important;
            color: #155724 !important;
        }

        .bg-warning-light {
            background-color: #fff3cd !important;
            color: #856404 !important;
        }

        .bg-danger-light {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }

        .bg-secondary-light {
            background-color: #e2e3e5 !important;
            color: #383d41 !important;
        }

        /* Mobile specific */
        @media (max-width: 576px) {
            .card-header-responsive {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .mobile-table {
                font-size: 0.875rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Ensure dropdown stays above content */
        .dropdown-menu {
            z-index: 1060 !important;
        }

        /* Fix for iOS Safari */
        @supports (-webkit-touch-callout: none) {
            .sidenav {
                height: -webkit-fill-available;
            }
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
                        <h3 class="mb-0 h4 font-weight-bolder mt-0">Products Management</h3>
                        <p class="mb-4">Manage your inventory products and track stock levels.</p>
                    </div>
                </div>

                <!-- Statistics Cards - Dashboard Style -->
                <div class="stats-grid w-100 mb-4">
                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Products</p>
                                        <h4 class="mb-0"><?php echo number_format($products->num_rows); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">inventory_2</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo number_format($products->num_rows); ?></span> items in catalog
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Active Products</p>
                                        <h4 class="mb-0">
                                            <?php
                                            $products->data_seek(0);
                                            $activeCount = 0;
                                            while ($product = $products->fetch_assoc()) {
                                                if ($product['status'] === 'active') $activeCount++;
                                            }
                                            $products->data_seek(0);
                                            echo number_format($activeCount);
                                            ?>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">check_circle</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder">Available</span> for sales
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
                                        <h4 class="mb-0 text-warning">
                                            <?php
                                            $products->data_seek(0);
                                            $lowStockCount = 0;
                                            while ($product = $products->fetch_assoc()) {
                                                if ($product['total_stock'] > 0 && $product['total_stock'] <= $product['reorder_level']) {
                                                    $lowStockCount++;
                                                }
                                            }
                                            $products->data_seek(0);
                                            echo number_format($lowStockCount);
                                            ?>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">warning</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-warning font-weight-bolder"><?php echo $lowStockCount; ?> items</span> need attention
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Out of Stock</p>
                                        <h4 class="mb-0 text-danger">
                                            <?php
                                            $products->data_seek(0);
                                            $outOfStockCount = 0;
                                            while ($product = $products->fetch_assoc()) {
                                                if ($product['total_stock'] == 0) {
                                                    $outOfStockCount++;
                                                }
                                            }
                                            $products->data_seek(0);
                                            echo number_format($outOfStockCount);
                                            ?>
                                        </h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-danger shadow-danger shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">error</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-danger font-weight-bolder"><?php echo $outOfStockCount; ?> items</span> unavailable
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table Card - Dashboard Style -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 card-header-responsive">
                            <div>
                                <h6 class="mb-1">Products List</h6>
                                <p class="text-sm mb-0">
                                    <i class="fa fa-info-circle text-info" aria-hidden="true"></i>
                                    <span class="font-weight-bold ms-1"><?php echo $products->num_rows; ?></span> products found
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-dark mb-0" onclick="exportProducts()">
                                    <i class="material-symbols-rounded me-1">download</i>
                                    Export
                                </button>
                                <button class="btn btn-sm btn-primary mb-0" onclick="showAddProductModal()">
                                    <i class="material-symbols-rounded me-1">add</i>
                                    Add Product
                                </button>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <!-- Filters - Dashboard Style -->
                            <div class="card filter-card mx-3 mb-3">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label text-sm fw-bold">Search Product</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="material-symbols-rounded">search</i></span>
                                                <input type="text" class="form-control form-control-sm" name="search"
                                                    placeholder="Display ID, Product ID or Name"
                                                    value="<?php echo htmlspecialchars($searchTerm); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label text-sm fw-bold">Product Status</label>
                                            <select class="form-select form-select-sm" name="product_status">
                                                <option value="active" <?php echo $productStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                                                <option value="inactive" <?php echo $productStatus === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                                                <option value="all" <?php echo $productStatus === 'all' ? 'selected' : ''; ?>>All Products</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label text-sm fw-bold">Stock Status</label>
                                            <select class="form-select form-select-sm" name="stock_status">
                                                <option value="">All Status</option>
                                                <option value="in_stock" <?php echo $stockStatus === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                                <option value="low_stock" <?php echo $stockStatus === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                                <option value="out_of_stock" <?php echo $stockStatus === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="material-symbols-rounded me-1">filter_alt</i>
                                                Filter
                                            </button>
                                            <a href="products.php" class="btn btn-sm btn-secondary">
                                                <i class="material-symbols-rounded me-1">refresh</i>
                                                Reset
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Products Table -->
                            <div class="table-responsive mobile-table px-3">
                                <table class="table table-hover align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Product Name</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Generic Name</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Unit</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Stock</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Reorder Level</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Batches</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stock Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($products->num_rows > 0): ?>
                                            <?php while ($product = $products->fetch_assoc()): ?>
                                                <?php
                                                $stockStatusText = '';
                                                $stockBadge = '';
                                                if ($product['total_stock'] == 0) {
                                                    $stockStatusText = 'Out of Stock';
                                                    $stockBadge = 'danger';
                                                } elseif ($product['total_stock'] <= $product['reorder_level']) {
                                                    $stockStatusText = 'Low Stock';
                                                    $stockBadge = 'warning';
                                                } else {
                                                    $stockStatusText = 'In Stock';
                                                    $stockBadge = 'success';
                                                }

                                                $isActive = $product['status'] === 'active';
                                                ?>
                                                <tr class="<?php echo !$isActive ? 'table-secondary opacity-75' : ''; ?>">
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm font-weight-bold">#<?php echo sprintf('%02d', $product['display_id']); ?></h6>
                                                                <p class="text-xs text-secondary mb-0">ID: <?php echo $product['product_id']; ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                                <?php if (!$isActive): ?>
                                                                    <span class="badge bg-secondary mt-1">Inactive</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <p class="text-sm font-weight-normal mb-0"><?php echo htmlspecialchars($product['generic_name'] ?? '-'); ?></p>
                                                    </td>
                                                    <td>
                                                        <span class="text-sm font-weight-normal"><?php echo htmlspecialchars($product['unit'] ?? '-'); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold"><?php echo $product['total_stock']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-normal"><?php echo $product['reorder_level']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="badge bg-info text-white"><?php echo $product['batch_count']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <span class="badge bg-<?php echo $stockBadge; ?> text-white">
                                                            <?php echo $stockStatusText; ?>
                                                        </span>
                                                    </td>
                                                    <td class="align-middle text-center text-sm">
                                                        <span class="badge bg-<?php echo $isActive ? 'success' : 'secondary'; ?> text-white">
                                                            <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-icon btn-warning" onclick="editProduct(<?php echo $product['product_id']; ?>)" title="Edit">
                                                                <svg width="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M13.7476 20.4428H21.0002" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.78 3.79479C13.5557 2.86779 14.95 2.73186 15.8962 3.49173C15.9485 3.53296 17.6295 4.83879 17.6295 4.83879C18.669 5.46719 18.992 6.80311 18.3494 7.82259C18.3153 7.87718 8.81195 19.7645 8.81195 19.7645C8.49578 20.1589 8.01583 20.3918 7.50291 20.3973L3.86353 20.443L3.04353 16.9723C2.92866 16.4843 3.04353 15.9718 3.3597 15.5773L12.78 3.79479Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M11.021 6.00098L16.4732 10.1881" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg>
                                                            </button>
                                                            <?php if ($isActive): ?>
                                                                <button class="btn btn-sm btn-icon btn-danger" onclick="toggleProductStatus(<?php echo $product['product_id']; ?>, 'inactive')" title="Deactivate">
                                                                    <svg width="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M4.92896 4.92896L19.071 19.071" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                    </svg>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-icon btn-success" onclick="toggleProductStatus(<?php echo $product['product_id']; ?>, 'active')" title="Activate">
                                                                    <svg width="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 17.5228 7.02944 22 12 22Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                    </svg>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-muted" style="font-size: 48px;">inventory_2</i>
                                                        <p class="text-muted mt-2 mb-0">No products found matching your criteria</p>
                                                        <button class="btn btn-sm btn-primary mt-2" onclick="showAddProductModal()">
                                                            <i class="material-symbols-rounded me-1">add</i>
                                                            Add Your First Product
                                                        </button>
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

            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <!-- Add/Edit Product Modal - Dashboard Style -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" id="productId" name="product_id">

                        <!-- Product Information Section -->
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="text-primary mb-3">
                                <i class="material-symbols-rounded me-1">info</i>
                                Product Information
                            </h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="productName" class="form-label">Product Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">inventory_2</i></span>
                                        <input type="text" class="form-control" id="productName" name="product_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="genericName" class="form-label">Generic Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">description</i></span>
                                        <input type="text" class="form-control" id="genericName" name="generic_name">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="unit" class="form-label">Unit</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">scale</i></span>
                                        <input type="text" class="form-control" id="unit" name="unit" placeholder="e.g., Kg, L">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="reorderLevel" class="form-label">Reorder Level *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">notifications</i></span>
                                        <input type="number" class="form-control" id="reorderLevel" name="reorder_level" value="10" required>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="productStatus" class="form-label">Status *</label>
                                    <select class="form-select" id="productStatus" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="productSupplierId" class="form-label">Supplier (Optional)</label>
                                    <select class="form-select" id="productSupplierId" name="product_supplier_id">
                                        <option value="">-- None --</option>
                                        <?php
                                        $suppliers->data_seek(0);
                                        while ($supplier = $suppliers->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $supplier['supplier_id']; ?>">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Initial Stock Section (Only for new products) -->
                        <div id="initialStockSection">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-primary mb-0">
                                    <i class="material-symbols-rounded me-1">inventory</i>
                                    Initial Stock Details (Optional)
                                </h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="addInitialStock" onchange="toggleInitialStock()">
                                    <label class="form-check-label" for="addInitialStock">
                                        Add initial stock now
                                    </label>
                                </div>
                            </div>

                            <div id="stockFields" style="display: none;">
                                <div class="alert alert-info alert-sm">
                                    <div class="d-flex align-items-center">
                                        <i class="material-symbols-rounded me-2">info</i>
                                        <small>You can add stock details now or add them later from Stock Management page.</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="batchNo" class="form-label">Batch Number *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">tag</i></span>
                                            <input type="text" class="form-control" id="batchNo" name="batch_no">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expiryDate" class="form-label">Expiry Date *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">event</i></span>
                                            <input type="date" class="form-control" id="expiryDate" name="expiry_date">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="costPrice" class="form-label">Cost Price (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" class="form-control" id="costPrice" name="cost_price" step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="sellingPrice" class="form-label">Selling Price (Rs.) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rs.</span>
                                            <input type="number" class="form-control" id="sellingPrice" name="selling_price" step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="quantity" class="form-label">Quantity *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">numbers</i></span>
                                            <input type="number" class="form-control" id="quantity" name="quantity_in_stock">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-symbols-rounded me-1">close</i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveProduct()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save Product
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

        // Product functions
        const productModal = new bootstrap.Modal(document.getElementById('productModal'));

        function toggleInitialStock() {
            const checkbox = document.getElementById('addInitialStock');
            const stockFields = document.getElementById('stockFields');
            const fields = stockFields.querySelectorAll('input');

            if (checkbox.checked) {
                stockFields.style.display = 'block';
                fields.forEach(field => {
                    if (field.name !== 'batch_no' && field.name !== 'cost_price') {
                        field.setAttribute('required', 'required');
                    }
                });
            } else {
                stockFields.style.display = 'none';
                fields.forEach(field => {
                    field.removeAttribute('required');
                    field.value = '';
                });
            }
        }

        function showAddProductModal() {
            document.getElementById('productModalTitle').textContent = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productStatus').value = 'active';
            document.getElementById('initialStockSection').style.display = 'block';
            document.getElementById('addInitialStock').checked = false;
            document.getElementById('stockFields').style.display = 'none';
            productModal.show();
        }

        function exportProducts() {
            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = 'export_products.php?' + urlParams.toString();
        }

        function editProduct(productId) {
            fetch('api/get_product.php?id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('productModalTitle').textContent = 'Edit Product';
                        document.getElementById('productId').value = data.product.product_id;
                        document.getElementById('productName').value = data.product.product_name;
                        document.getElementById('genericName').value = data.product.generic_name || '';
                        document.getElementById('unit').value = data.product.unit || '';
                        document.getElementById('reorderLevel').value = data.product.reorder_level;
                        document.getElementById('productStatus').value = data.product.status;
                        document.getElementById('productSupplierId').value = data.product.supplier_id || '';
                        document.getElementById('initialStockSection').style.display = 'none';
                        productModal.show();
                    } else {
                        Swal.fire('Error', 'Failed to load product data', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred', 'error');
                });
        }

        function saveProduct() {
            const form = document.getElementById('productForm');
            const formData = new FormData(form);
            const addStock = document.getElementById('addInitialStock').checked;

            // Add flag to indicate if initial stock should be added
            formData.append('add_initial_stock', addStock ? '1' : '0');

            fetch('api/save_product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred', 'error');
                });
        }

        function toggleProductStatus(productId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            const actionText = newStatus === 'active' ? 'Activate' : 'Deactivate';

            Swal.fire({
                title: `${actionText} Product?`,
                text: newStatus === 'inactive' ?
                    'This product will not appear in POS and new batch additions. Existing stock will remain.' :
                    'This product will be available again in POS and for new batches.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: newStatus === 'active' ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/toggle_product_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                status: newStatus
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Success!', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('Error', 'An error occurred', 'error');
                        });
                }
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