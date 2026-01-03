<?php
require_once 'auth.php';
require_once 'includes/pagination_helper.php'; // Add this line
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters
$statusFilter = $_GET['status_filter'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$expiryFilter = $_GET['expiry_filter'] ?? '';

// Pagination parameters
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 8;

// Build count query
$countSql = "SELECT COUNT(*) as total FROM product_batches pb
JOIN products p ON pb.product_id = p.product_id
WHERE 1=1";

$params = [];
$types = "";

// Search filter
if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $countSql .= " AND (pb.display_id = ? OR p.product_name LIKE ? OR p.generic_name LIKE ? OR pb.batch_no LIKE ? OR p.product_id LIKE ?)";
        $params[] = $searchTerm;
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $types .= "issss";
    } else {
        $countSql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR pb.batch_no LIKE ? OR p.product_id LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $types .= "ssss";
    }
}

// Status filter
if ($statusFilter === 'out_of_stock') {
    $countSql .= " AND pb.quantity_in_stock = 0";
} elseif ($statusFilter === 'low_stock') {
    $countSql .= " AND pb.quantity_in_stock > 0 AND pb.quantity_in_stock <= 10";
} elseif ($statusFilter === 'in_stock') {
    $countSql .= " AND pb.quantity_in_stock > 10";
}

// Expiry filter
if ($expiryFilter === 'expired') {
    $countSql .= " AND pb.expiry_date < CURDATE()";
} elseif ($expiryFilter === 'expiring_soon') {
    $countSql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) <= 30";
} elseif ($expiryFilter === 'near_expiry') {
    $countSql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) BETWEEN 31 AND 90";
} elseif ($expiryFilter === 'good') {
    $countSql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) > 90";
}

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Calculate pagination
$pagination = calculatePagination($totalRecords, $recordsPerPage, $currentPage);

// Build main query with filters and pagination
$sql = "SELECT 
    pb.batch_id,
    pb.display_id,
    LPAD(pb.display_id, 2, '0') as formatted_display_id,
    pb.batch_no,
    pb.expiry_date,
    pb.cost_price,
    pb.selling_price,
    pb.quantity_in_stock,
    p.product_id,
    p.product_name,
    p.generic_name,
    p.unit,
    DATEDIFF(pb.expiry_date, CURDATE()) as days_to_expiry
FROM product_batches pb
JOIN products p ON pb.product_id = p.product_id
WHERE 1=1";

// Add same filters as count query
if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $sql .= " AND (pb.display_id = ? OR p.product_name LIKE ? OR p.generic_name LIKE ? OR pb.batch_no LIKE ? OR p.product_id LIKE ?)";
    } else {
        $sql .= " AND (p.product_name LIKE ? OR p.generic_name LIKE ? OR pb.batch_no LIKE ? OR p.product_id LIKE ?)";
    }
}

if ($statusFilter === 'out_of_stock') {
    $sql .= " AND pb.quantity_in_stock = 0";
} elseif ($statusFilter === 'low_stock') {
    $sql .= " AND pb.quantity_in_stock > 0 AND pb.quantity_in_stock <= 10";
} elseif ($statusFilter === 'in_stock') {
    $sql .= " AND pb.quantity_in_stock > 10";
}

if ($expiryFilter === 'expired') {
    $sql .= " AND pb.expiry_date < CURDATE()";
} elseif ($expiryFilter === 'expiring_soon') {
    $sql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) <= 30";
} elseif ($expiryFilter === 'near_expiry') {
    $sql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) BETWEEN 31 AND 90";
} elseif ($expiryFilter === 'good') {
    $sql .= " AND pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) > 90";
}

$sql .= " ORDER BY pb.display_id ASC, p.product_name, pb.expiry_date LIMIT ? OFFSET ?";

// Add limit and offset to params
$params[] = $pagination['limit'];
$params[] = $pagination['offset'];
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$batches = $stmt->get_result();

// Build pagination URL
$paginationUrl = buildPaginationUrl($_GET);

// Get summary statistics (same as before)
$totalProducts = $conn->query("SELECT COUNT(DISTINCT pb.product_id) as total FROM product_batches pb")->fetch_assoc()['total'];
$totalBatches = $conn->query("SELECT COUNT(*) as total FROM product_batches pb")->fetch_assoc()['total'];
$totalStockValue = $conn->query("SELECT SUM(pb.quantity_in_stock * pb.cost_price) as total FROM product_batches pb")->fetch_assoc()['total'];
$lowStockItems = $conn->query("SELECT COUNT(*) as total FROM product_batches pb WHERE pb.quantity_in_stock > 0 AND pb.quantity_in_stock <= 10")->fetch_assoc()['total'];
$outOfStockItems = $conn->query("SELECT COUNT(*) as total FROM product_batches pb WHERE pb.quantity_in_stock = 0")->fetch_assoc()['total'];
$expiringSoonItems = $conn->query("SELECT COUNT(*) as total FROM product_batches pb WHERE pb.expiry_date >= CURDATE() AND DATEDIFF(pb.expiry_date, CURDATE()) <= 30")->fetch_assoc()['total'];

// Get products for dropdown
$products = $conn->query("SELECT product_id, product_name, generic_name FROM products ORDER BY product_name");
?>
<!doctype html>
<html lang="en" dir="ltr" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Erundeniya Hospital Pharmacy</title>

    <link rel="shortcut icon" href="assets/images/logof1.png">

    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />
    <link href="assets/css/fixes.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.5/sweetalert2.min.css">

    <style>
        /* Dashboard matching styles */
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

        .card {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 0;
            border-radius: 0.75rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
            margin-bottom: 0;
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-control,
        .form-select {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #000;
            box-shadow: 0 0 0 0.2rem rgba(52, 71, 103, 0.15);
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #000;
            margin-bottom: 0.5rem;
        }

        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: #000;
            border-color: #000;
        }

        .btn-primary:hover {
            background-color: #283148;
            border-color: #283148;
        }

        .btn-success {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
            border: none;
        }

        .btn-success:hover {
            background: linear-gradient(195deg, #5cb85c 0%, #3d8b40 100%);
        }

        .btn-info {
            background: linear-gradient(195deg, #49a3f1 0%, #1A73E8 100%);
            border: none;
        }

        .btn-info:hover {
            background: linear-gradient(195deg, #3d8bf8 0%, #1557b0 100%);
        }

        .btn-warning {
            background: linear-gradient(195deg, #ffa726 0%, #fb8c00 100%);
            border: none;
        }

        .btn-warning:hover {
            background: linear-gradient(195deg, #ff9800 0%, #f57c00 100%);
        }

        .btn-danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%);
            border: none;
        }

        .btn-danger:hover {
            background: linear-gradient(195deg, #e53935 0%, #c62828 100%);
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
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
            background: linear-gradient(195deg, #ffa726 0%, #fb8c00 100%);
        }

        .bg-gradient-danger {
            background: linear-gradient(195deg, #EF5350 0%, #E53935 100%);
        }

        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .table thead th {
            border-top: none;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #67748e;
            padding: 0.75rem 1.5rem;
        }

        .table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-cash {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-credit {
            background: #fff3e0;
            color: #f57c00;
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

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .card-body {
                padding: 1rem !important;
            }

            .mobile-table {
                font-size: 0.875rem;
            }

            .status-badge {
                font-size: 0.7rem;
            }

            .btn-sm {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading screen */
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

        /* Dashboard header styling */
        .dashboard-header {
            padding-left: 2rem !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        @media (max-width: 1199.98px) {
            .dashboard-header {
                padding-left: 1rem !important;
            }
        }

        /* Modal styling to match dashboard */
        .modal-content {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15), 0 8px 9px -5px rgba(20, 20, 20, 0.06);
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            border-radius: 0.75rem 0.75rem 0 0;
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            color: #000;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.25rem 1.5rem;
        }

        .mobile-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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

        /* ============================================
   SEARCH BAR ICON POSITIONING FIX
   ============================================ */

        /* Fix for search icon positioning in input groups */
        .input-group .input-group-text {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 40px !important;
            padding: 0.5rem 0.75rem !important;
        }

        .input-group .input-group-text .material-symbols-rounded {
            font-size: 20px !important;
            line-height: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Ensure search icon is visible */
        .input-group-text i,
        .input-group-text .material-symbols-rounded {
            color: #6c757d !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* ============================================
   SEARCH FORM ALIGNMENT FIX
   ============================================ */

        /* Fix search form row alignment */
        .search-form .row.g-3,
        form[method="GET"] .row.g-3 {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: flex-end !important;
            margin-left: -0.5rem !important;
            margin-right: -0.5rem !important;
        }

        .search-form .col-md-3,
        form[method="GET"] .col-md-3 {
            display: flex !important;
            flex-direction: column !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            padding-bottom: 0 !important;
        }

        .search-form .col-md-3 {
            flex: 1 1 auto !important;
        }

        /* Last column with buttons */
        .search-form .col-md-3:last-child,
        form[method="GET"] .col-md-3:last-child {
            flex: 0 0 auto !important;
            width: auto !important;
            min-width: fit-content !important;
        }

        /* ============================================
   ACTION BUTTONS ALIGNMENT FIX
   ============================================ */

        /* Keep action buttons in same row */
        .table td.align-middle.text-center {
            white-space: nowrap !important;
            padding: 1rem 0.5rem !important;
        }

        .table .btn-group {
            display: inline-flex !important;
            gap: 0.25rem !important;
            flex-wrap: nowrap !important;
        }

        .table .btn-icon {
            flex-shrink: 0 !important;
            width: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* ============================================
   FILTER/SEARCH BUTTONS ALIGNMENT
   ============================================ */

        /* Ensure filter buttons stay horizontal */
        .col-md-3.d-flex.align-items-end.gap-2 {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-end !important;
            gap: 0.5rem !important;
            flex-wrap: nowrap !important;
            justify-content: flex-start !important;
        }

        .col-md-3.d-flex.align-items-end.gap-2 .btn {
            flex-shrink: 0 !important;
            margin-bottom: 0 !important;
            white-space: nowrap !important;
            flex: 0 0 auto !important;
        }

        /* Match button height with inputs */
        form .row .btn-sm {
            height: calc(1.5em + 1rem + 2px) !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.8125rem !important;
            line-height: 1.5 !important;
        }

        /* Remove any extra margin from buttons in forms */
        .filter-card .btn {
            margin-top: 0 !important;
        }

        /* ============================================
   HEADER TITLE ALIGNMENT FIX
   ============================================ */

        /* Fix "Product Batches X batches found" alignment */
        .card-header.pb-0.card-header-responsive>div:first-child {
            display: flex !important;
            flex-direction: row !important;
            align-items: baseline !important;
            gap: 0.5rem !important;
            flex-wrap: nowrap !important;
        }

        .card-header.pb-0.card-header-responsive h6 {
            margin-bottom: 0 !important;
            white-space: nowrap !important;
        }

        .card-header.pb-0.card-header-responsive p.text-sm.mb-0 {
            margin-bottom: 0 !important;
            margin-top: 0 !important;
            white-space: nowrap !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.25rem !important;
        }

        /* ============================================
   RESPONSIVE FIXES
   ============================================ */

        @media (max-width: 767px) {

            /* Stack elements on mobile */
            .search-form .row.g-3,
            form[method="GET"] .row.g-3 {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            .search-form .col-md-3,
            form[method="GET"] .col-md-3 {
                width: 100% !important;
                max-width: 100% !important;
            }

            .col-md-3.d-flex.align-items-end.gap-2 {
                flex-direction: column !important;
                width: 100% !important;
            }

            .col-md-3.d-flex.align-items-end.gap-2 .btn {
                width: 100% !important;
            }
        }

        @media (min-width: 768px) {

            /* Maintain horizontal layout on desktop */
            .search-form .col-md-3,
            form[method="GET"] .col-md-3 {
                flex: 0 0 25% !important;
                max-width: 25% !important;
            }

            .search-form .col-md-3:last-child,
            form[method="GET"] .col-md-3:last-child {
                flex: 0 0 auto !important;
                width: auto !important;
            }
        }

        /* ============================================
   TABLE CELL CONTENT ALIGNMENT
   ============================================ */

        /* Center align content in table cells */
        .table tbody td {
            vertical-align: middle !important;
        }

        /* Ensure text doesn't wrap unnecessarily */
        .table tbody td .text-sm {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            max-width: 200px !important;
        }

        /* Status badges alignment */
        .table .status-badge {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            padding: 4px 12px !important;
            border-radius: 8px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
        }

        /* ============================================
   INPUT GROUP SPECIFIC FIXES
   ============================================ */

        /* Fix input group with search icon */
        .input-group {
            display: flex !important;
            align-items: stretch !important;
            width: 100% !important;
        }

        .input-group .form-control {
            flex: 1 1 auto !important;
            border-left: 1px solid #e9ecef !important;
        }

        .input-group .form-control:focus {
            border-left: 1px solid #000 !important;
        }

        /* Ensure proper border radius */
        .input-group .input-group-text:first-child {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }

        .input-group .form-control:not(:first-child):not(:last-child) {
            border-radius: 0 !important;
        }

        .input-group .form-control:last-child {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }

        /* Status badge colors */
        .status-badge.status-success {
            background-color: #28a745;
            color: white;
        }

        .status-badge.status-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .status-badge.status-danger {
            background-color: #dc3545;
            color: white;
        }

        .status-badge.status-primary {
            background-color: #007bff;
            color: white;
        }

        .status-badge.status-secondary {
            background-color: #6c757d;
            color: white;
        }

        .status-badge.status-info {
            background-color: #17a2b8;
            color: white;
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
                    <h3 class="mb-0 h4 font-weight-bolder mt-0">Stock Management</h3>
                    <p class="mb-4">Manage product batches, stock levels, and expiry dates</p>
                </div>

                <!-- Statistics Cards - Dashboard Style -->
                <div class="stats-grid w-100">
                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Products</p>
                                        <h4 class="mb-0"><?php echo number_format($totalProducts); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">inventory_2</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo number_format($totalProducts); ?></span> products in system
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Batches</p>
                                        <h4 class="mb-0"><?php echo number_format($totalBatches); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">qr_code</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder"><?php echo number_format($totalBatches); ?></span> total batches
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
                                        <h4 class="mb-0">Rs. <?php echo number_format($totalStockValue, 2); ?></h4>
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
                                        <p class="text-sm mb-0 text-capitalize">Low Stock Items</p>
                                        <h4 class="mb-0 text-warning"><?php echo number_format($lowStockItems); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">warning</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-warning font-weight-bolder"><?php echo number_format($lowStockItems); ?></span> items need reorder
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
                                        <h4 class="mb-0 text-danger"><?php echo number_format($outOfStockItems); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-danger shadow-danger shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">inventory</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-danger font-weight-bolder"><?php echo number_format($outOfStockItems); ?></span> items out of stock
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Expiring Soon</p>
                                        <h4 class="mb-0 text-warning"><?php echo number_format($expiringSoonItems); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">schedule</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-warning font-weight-bolder"><?php echo number_format($expiringSoonItems); ?></span> items expiring soon
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Table - Dashboard Style -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 card-header-responsive">
                            <div>
                                <h6>Product Batches</h6>
                                <p class="text-sm mb-1">
                                    <i class="fa fa-check text-info" aria-hidden="true"></i>
                                    (<span class="font-weight-bold ms-1"><?php echo $batches->num_rows; ?></span> batches found&nbsp;)
                                </p>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-success mb-0" onclick="exportStock()">
                                    <i class="material-symbols-rounded me-1" style="font-size: 16px;">download</i>
                                    Export to CSV
                                </button>
                                <button type="button" class="btn btn-sm btn-primary mb-0" onclick="showAddBatchModal()">
                                    <i class="material-symbols-rounded me-1" style="font-size: 16px;">add_circle</i>
                                    Add New Batch
                                </button>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <!-- Filters - Dashboard Style -->
                            <form method="GET" class="row g-3 mb-4 px-3">
                                <div class="col-md-3">
                                    <label class="form-label text-sm">Search</label>
                                    <input type="text" class="form-control form-control-sm" name="search"
                                        placeholder="Display ID, Product ID, Name or Batch No"
                                        value="<?php echo htmlspecialchars($searchTerm); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-sm">Stock Status</label>
                                    <select class="form-select form-select-sm" name="status_filter">
                                        <option value="">All Status</option>
                                        <option value="in_stock" <?php echo $statusFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                        <option value="low_stock" <?php echo $statusFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                        <option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-sm">Expiry Status</label>
                                    <select class="form-select form-select-sm" name="expiry_filter">
                                        <option value="">All</option>
                                        <option value="good" <?php echo $expiryFilter === 'good' ? 'selected' : ''; ?>>Good (90+ days)</option>
                                        <option value="near_expiry" <?php echo $expiryFilter === 'near_expiry' ? 'selected' : ''; ?>>Near Expiry (31-90 days)</option>
                                        <option value="expiring_soon" <?php echo $expiryFilter === 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon (≤30 days)</option>
                                        <option value="expired" <?php echo $expiryFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="material-symbols-rounded me-1" style="font-size: 16px;">filter_alt</i>
                                        Filter
                                    </button>
                                    <a href="stock_management.php" class="btn btn-secondary btn-sm">
                                        <i class="material-symbols-rounded me-1" style="font-size: 16px;">refresh</i>
                                        Reset
                                    </a>
                                </div>
                            </form>

                            <div class="table-responsive mobile-table px-3">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Product Name</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Batch No</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Expiry Date</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cost Price</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Selling Price</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Stock</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($batch = $batches->fetch_assoc()): ?>
                                            <?php
                                            $statusBadge = 'success';
                                            $statusText = 'Good';

                                            if ($batch['days_to_expiry'] < 0) {
                                                $statusBadge = 'danger';
                                                $statusText = 'Expired';
                                            } elseif ($batch['days_to_expiry'] <= 30) {
                                                $statusBadge = 'warning';
                                                $statusText = 'Expiring Soon';
                                            } elseif ($batch['days_to_expiry'] <= 90) {
                                                $statusBadge = 'primary';
                                                $statusText = 'Near Expiry';
                                            }

                                            if ($batch['quantity_in_stock'] == 0) {
                                                $statusBadge = 'secondary';
                                                $statusText = 'Out of Stock';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?php echo sprintf('%02d', $batch['display_id']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($batch['product_name']); ?></h6>
                                                        <?php if ($batch['generic_name']): ?>
                                                            <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($batch['generic_name']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($batch['batch_no']); ?></p>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo date('M d, Y', strtotime($batch['expiry_date'])); ?></h6>
                                                        <p class="text-xs text-secondary mb-0"><?php echo abs($batch['days_to_expiry']); ?> days <?php echo $batch['days_to_expiry'] < 0 ? 'ago' : 'left'; ?></p>
                                                    </div>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-bold">
                                                        <?php
                                                        if ($batch['cost_price'] === null || $batch['cost_price'] == 0) {
                                                            echo '<span style="color: #999; font-style: italic;">N/A</span>';
                                                        } else {
                                                            echo 'Rs. ' . number_format($batch['cost_price'], 2);
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-bold">Rs. <?php echo number_format($batch['selling_price'], 2); ?></span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <span class="text-sm font-weight-bold"><?php echo $batch['quantity_in_stock']; ?></span>
                                                </td>
                                                <td class="align-middle text-center text-sm">
                                                    <span class="status-badge status-<?php echo $statusBadge; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td class="align-middle text-center">
                                                    <button class="btn btn-sm btn-icon btn-info" onclick="adjustStock(<?php echo $batch['batch_id']; ?>, '<?php echo htmlspecialchars($batch['product_name']); ?>', '<?php echo htmlspecialchars($batch['batch_no']); ?>', <?php echo $batch['quantity_in_stock']; ?>)" title="Adjust Stock">
                                                        <i class="material-symbols-rounded" style="font-size: 16px;">add_circle</i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-warning" onclick="editBatch(<?php echo $batch['batch_id']; ?>)" title="Edit">
                                                        <i class="material-symbols-rounded" style="font-size: 16px;">edit</i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-danger" onclick="deleteBatch(<?php echo $batch['batch_id']; ?>)" title="Delete">
                                                        <i class="material-symbols-rounded" style="font-size: 16px;">delete</i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- <p class="text-sm mb-1">
                                <i class="fa fa-check text-info" aria-hidden="true"></i>
                                (<span class="font-weight-bold ms-1"><?php echo getPaginationInfo($pagination, $batches->num_rows); ?></span>)
                            </p> -->

                            <!-- After closing </table> tag, add: -->
                            <?php if ($batches->num_rows > 0): ?>
                                <!-- Pagination Info -->
                                <div class="pagination-info px-3 mt-3">
                                    <?php echo getPaginationInfo($pagination, $batches->num_rows); ?>
                                </div>

                                <!-- Pagination -->
                                <div class="px-3">
                                    <?php echo generatePagination($pagination['currentPage'], $pagination['totalPages'], $paginationUrl); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
    </main>

    <!-- Add/Edit Batch Modal -->
    <div class="modal fade" id="batchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchModalTitle">Add New Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="batchForm">
                        <input type="hidden" id="batchId" name="batch_id">
                        <div class="mb-3">
                            <label for="productId" class="form-label">Product *</label>
                            <select class="form-select" id="productId" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php
                                $products->data_seek(0);
                                while ($product = $products->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                        <?php if ($product['generic_name']): ?>
                                            - <?php echo htmlspecialchars($product['generic_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="batchNo" class="form-label">Batch Number *</label>
                            <input type="text" class="form-control" id="batchNo" name="batch_no" required>
                        </div>
                        <div class="mb-3">
                            <label for="expiryDate" class="form-label">Expiry Date *</label>
                            <input type="date" class="form-control" id="expiryDate" name="expiry_date" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="costPrice" class="form-label">Cost Price *</label>
                                <input type="number" class="form-control" id="costPrice" name="cost_price" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sellingPrice" class="form-label">Selling Price *</label>
                                <input type="number" class="form-control" id="sellingPrice" name="selling_price" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity_in_stock" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveBatch()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save Batch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="adjustmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="adjustmentInfo" class="mb-3"></div>
                    <form id="adjustmentForm">
                        <input type="hidden" id="adjustBatchId" name="batch_id">
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type *</label>
                            <select class="form-select" id="adjustmentType" name="adjustment_type" required>
                                <option value="increase">Increase Stock</option>
                                <option value="decrease">Decrease Stock</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="adjustQuantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="adjustQuantity" name="quantity" required min="1">
                        </div>
                        <div class="mb-3">
                            <label for="adjustReason" class="form-label">Reason *</label>
                            <textarea class="form-control" id="adjustReason" name="reason" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAdjustment()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save Adjustment
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.5/sweetalert2.all.min.js"></script>

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

        // Hide loading when page is loaded
        window.addEventListener('load', function() {
            var loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        });

        const batchModal = new bootstrap.Modal(document.getElementById('batchModal'));
        const adjustmentModal = new bootstrap.Modal(document.getElementById('adjustmentModal'));

        function exportStock() {
            Swal.fire({
                title: 'Exporting...',
                text: 'Preparing your stock report',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = 'export_stockM.php?' + urlParams.toString();

            setTimeout(() => {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Stock report exported successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 1000);
        }

        function showAddBatchModal() {
            document.getElementById('batchModalTitle').textContent = 'Add New Batch';
            document.getElementById('batchForm').reset();
            document.getElementById('batchId').value = '';
            batchModal.show();
        }

        function editBatch(batchId) {
            fetch('api/get_batch.php?id=' + batchId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('batchModalTitle').textContent = 'Edit Batch';
                        document.getElementById('batchId').value = data.batch.batch_id;
                        document.getElementById('productId').value = data.batch.product_id;
                        document.getElementById('batchNo').value = data.batch.batch_no;
                        document.getElementById('expiryDate').value = data.batch.expiry_date;
                        document.getElementById('costPrice').value = data.batch.cost_price;
                        document.getElementById('sellingPrice').value = data.batch.selling_price;
                        document.getElementById('quantity').value = data.batch.quantity_in_stock;
                        batchModal.show();
                    } else {
                        Swal.fire('Error', 'Failed to load batch data', 'error');
                    }
                });
        }

        function saveBatch() {
            const form = document.getElementById('batchForm');
            const formData = new FormData(form);

            fetch('api/save_batch.php', {
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
                });
        }

        function deleteBatch(batchId) {
            Swal.fire({
                title: 'Delete Batch?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/delete_batch.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                batch_id: batchId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }

        function adjustStock(batchId, productName, batchNo, currentStock) {
            document.getElementById('adjustBatchId').value = batchId;
            document.getElementById('adjustmentInfo').innerHTML = `
                <div class="alert alert-info">
                    <strong>Product:</strong> ${productName}<br>
                    <strong>Batch:</strong> ${batchNo}<br>
                    <strong>Current Stock:</strong> ${currentStock}
                </div>
            `;
            document.getElementById('adjustmentForm').reset();
            adjustmentModal.show();
        }

        function saveAdjustment() {
            const form = document.getElementById('adjustmentForm');
            const formData = new FormData(form);

            fetch('api/save_adjustment.php', {
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