<?php
require_once 'auth.php';
require_once 'includes/pagination_helper.php'; // Add this line
Auth::requireAuth();

$conn = getDBConnection();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$supplierId = $_GET['supplier_id'] ?? '';

// Pagination parameters
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 8;

// Build count query
$countSql = "SELECT COUNT(*) as total FROM purchases p";

$whereConditions = [];
$params = [];
$types = "";

if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(p.purchase_date) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    $types .= "ss";
}

if (!empty($supplierId)) {
    $whereConditions[] = "p.supplier_id = ?";
    $params[] = $supplierId;
    $types .= "i";
}

if (!empty($whereConditions)) {
    $countSql .= " WHERE " . implode(" AND ", $whereConditions);
}

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Calculate pagination
$pagination = calculatePagination($totalRecords, $recordsPerPage, $currentPage);

// Build main query with pagination
$sql = "SELECT 
    p.*,
    p.display_id,
    LPAD(p.display_id, 2, '0') as formatted_display_id,
    s.name as supplier_name,
    u.full_name as user_name,
    COUNT(pi.purchase_item_id) as item_count
FROM purchases p
LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
LEFT JOIN users u ON p.user_id = u.user_id
LEFT JOIN purchase_items pi ON p.purchase_id = pi.purchase_item_id";

if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " GROUP BY p.purchase_id ORDER BY p.display_id ASC LIMIT ? OFFSET ?";

// Add limit and offset to params
$params[] = $pagination['limit'];
$params[] = $pagination['offset'];
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$purchases = $stmt->get_result();

// Get summary (same as before)
$summarySQL = "SELECT 
    COUNT(*) as total_purchases,
    SUM(total_amount) as total_amount
FROM purchases";

$summaryWhere = [];
$summaryParams = [];
$summaryTypes = "";

if (!empty($dateFrom) && !empty($dateTo)) {
    $summaryWhere[] = "DATE(purchase_date) BETWEEN ? AND ?";
    $summaryParams[] = $dateFrom;
    $summaryParams[] = $dateTo;
    $summaryTypes .= "ss";
}

if (!empty($supplierId)) {
    $summaryWhere[] = "supplier_id = ?";
    $summaryParams[] = $supplierId;
    $summaryTypes .= "i";
}

if (!empty($summaryWhere)) {
    $summarySQL .= " WHERE " . implode(" AND ", $summaryWhere);
}

$summaryStmt = $conn->prepare($summarySQL);
if (!empty($summaryParams)) {
    $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
}
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name");

// Build pagination URL
$paginationUrl = buildPaginationUrl($_GET);
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

        .supplier-search-wrapper {
            position: relative;
        }

        .supplier-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            background: white;
            border: 1px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15);
            z-index: 1000;
            display: none;
        }

        .supplier-dropdown.show {
            display: block;
        }

        .supplier-dropdown-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .supplier-dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(2px);
        }

        .supplier-dropdown-item:last-child {
            border-bottom: none;
        }

        .supplier-dropdown-item.selected {
            background-color: #e3f2fd;
            font-weight: 500;
            color: #1976d2;
        }

        .supplier-dropdown-item.selected::before {
            content: '✓';
            margin-right: 8px;
            color: #1976d2;
        }

        .no-results {
            padding: 16px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        /* Card Enhancements */
        .card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
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
            color: #000;
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

        .btn-secondary {
            background: linear-gradient(195deg, #6c757d 0%, #545b62 100%);
            border: none;
        }

        /* Form Enhancements */
        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #42424a;
            box-shadow: 0 0 0 0.2rem rgba(66, 66, 74, 0.25);
        }

        /* Alert Enhancements */
        .alert {
            border-radius: 0.5rem;
            border: none;
        }

        .alert-info {
            background: linear-gradient(195deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

        /* Status Colors */
        .text-success {
            color: #43A047 !important;
        }

        .text-info {
            color: #1A73E8 !important;
        }

        .text-warning {
            color: #FB8C00 !important;
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
   FILTER FORM ALIGNMENT FIX
   ============================================ */

        /* Fix filter form row alignment */
        #filterForm.row.g-3 {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: flex-end !important;
            margin-left: -0.5rem !important;
            margin-right: -0.5rem !important;
        }

        #filterForm .col-md-3 {
            display: flex !important;
            flex-direction: column !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            padding-bottom: 0 !important;
        }

        #filterForm .col-md-3 {
            flex: 1 1 auto !important;
        }

        /* Last column with buttons */
        #filterForm .col-md-3:last-child {
            flex: 0 0 auto !important;
            width: auto !important;
            min-width: fit-content !important;
        }

        /* ============================================
   FILTER/RESET BUTTONS ALIGNMENT
   ============================================ */

        /* Ensure filter buttons stay horizontal */
        #filterForm .col-md-3.d-flex.align-items-end.gap-2 {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-end !important;
            gap: 0.5rem !important;
            flex-wrap: nowrap !important;
            justify-content: flex-start !important;
        }

        #filterForm .col-md-3.d-flex.align-items-end.gap-2 .btn {
            flex-shrink: 0 !important;
            margin-bottom: 0 !important;
            white-space: nowrap !important;
            flex: 0 0 auto !important;
        }

        /* Match button height with inputs */
        #filterForm .row .btn-sm {
            height: calc(1.5em + 1rem + 2px) !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.8125rem !important;
            line-height: 1.5 !important;
        }

        /* ============================================
   SUPPLIER DROPDOWN ALIGNMENT
   ============================================ */

        /* Ensure supplier dropdown stays properly positioned */
        .supplier-search-wrapper {
            position: relative !important;
            width: 100% !important;
        }

        .supplier-dropdown {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            margin-top: 0.25rem !important;
            z-index: 1000 !important;
        }

        /* ============================================
   HEADER TITLE ALIGNMENT FIX
   ============================================ */

        /* Fix "Purchase History X purchase records found" alignment */
        .card-header.pb-0 .d-flex justify-content-between align-items-center>div:first-child {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.25rem !important;
        }

        .card-header.pb-0 h6 {
            margin-bottom: 0.25rem !important;
        }

        .card-header.pb-0 p.text-sm.mb-0 {
            margin-bottom: 0 !important;
            margin-top: 0 !important;
        }

        /* ============================================
   RESPONSIVE FIXES
   ============================================ */

        @media (max-width: 767px) {

            /* Stack elements on mobile */
            #filterForm.row.g-3 {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            #filterForm .col-md-3 {
                width: 100% !important;
                max-width: 100% !important;
            }

            #filterForm .col-md-3.d-flex.align-items-end.gap-2 {
                flex-direction: column !important;
                width: 100% !important;
            }

            #filterForm .col-md-3.d-flex.align-items-end.gap-2 .btn {
                width: 100% !important;
            }
        }

        @media (min-width: 768px) {

            /* Maintain horizontal layout on desktop */
            #filterForm .col-md-3 {
                flex: 0 0 25% !important;
                max-width: 25% !important;
            }

            #filterForm .col-md-3:last-child {
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

        /* ============================================
   FORM LABEL ALIGNMENT
   ============================================ */

        /* Ensure form labels are properly aligned */
        .form-label.text-sm.fw-bold {
            display: block !important;
            margin-bottom: 0.5rem !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
        }

        /* ============================================
   QUICK GUIDE CARD STYLING
   ============================================ */

        /* Ensure the info card looks good */
        .card.bg-gradient-info .card-body {
            padding: 1.5rem !important;
        }

        .card.bg-gradient-info .d-flex.align-items-center.mb-3 {
            margin-bottom: 1rem !important;
        }

        .card.bg-gradient-info h5.card-title.text-white.mb-1 {
            margin-bottom: 0.25rem !important;
        }

        .card.bg-gradient-info .card-text.text-white.opacity-8.mb-0 {
            margin-bottom: 0 !important;
        }

        .card.bg-gradient-info ol.text-white.opacity-9.mb-3 {
            margin-bottom: 1rem !important;
        }

        .card.bg-gradient-info .mt-3 {
            margin-top: 1rem !important;
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
                        <h3 class="mb-0 h4 font-weight-bolder mt-0">Purchase History</h3>
                        <p class="mb-4">View and manage your purchase records and supplier transactions.</p>
                    </div>
                </div>

                <!-- Summary Cards - Dashboard Style -->
                <div class="stats-grid w-100 mb-4">
                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Purchases</p>
                                        <h4 class="mb-0"><?php echo number_format(intval($summary['total_purchases'] ?? 0)); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">shopping_cart</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-primary font-weight-bolder"><?php echo number_format(intval($summary['total_purchases'] ?? 0)); ?></span> purchase records
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card h-100">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Amount</p>
                                        <h4 class="mb-0 text-success">Rs. <?php echo number_format(floatval($summary['total_amount'] ?? 0), 2); ?></h4>
                                    </div>
                                    <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                                        <i class="material-symbols-rounded opacity-10 text-white">payments</i>
                                    </div>
                                </div>
                            </div>
                            <hr class="dark horizontal my-0">
                            <div class="card-footer p-2 ps-3">
                                <p class="mb-0 text-sm">
                                    <span class="text-success font-weight-bolder">Total</span> purchase value
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchase History Table - Dashboard Style -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Purchase History</h6>
                                    <p class="text-sm mb-0">
                                        <i class="fa fa-info-circle text-info" aria-hidden="true"></i>
                                        <span class="font-weight-bold ms-1"><?php echo $purchases->num_rows; ?></span> purchase records found
                                    </p>
                                    <small class="text-muted">
                                        <i>Note: New purchases are created through Stock Management by adding new batches.</i>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-dark mb-0" onclick="exportPurchases()">
                                        <i class="material-symbols-rounded me-1">download</i>
                                        Export to CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-3 pb-2">
                            <!-- Filters - Dashboard Style -->
                            <form method="GET" id="filterForm" class="row g-3 mx-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label text-sm fw-bold">Date From</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">calendar_today</i></span>
                                        <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo $dateFrom; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-sm fw-bold">Date To</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="material-symbols-rounded">calendar_today</i></span>
                                        <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo $dateTo; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-sm fw-bold">Supplier</label>
                                    <div class="supplier-search-wrapper">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="material-symbols-rounded">local_shipping</i></span>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                id="supplierSearchInput"
                                                placeholder="Type to search supplier..."
                                                autocomplete="off">
                                        </div>
                                        <input type="hidden" name="supplier_id" id="supplierIdInput" value="<?php echo $supplierId; ?>">
                                        <div class="supplier-dropdown" id="supplierDropdown"></div>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="material-symbols-rounded me-1">filter_alt</i>
                                        Filter
                                    </button>
                                    <a href="purchases.php" class="btn btn-sm btn-secondary">
                                        <i class="material-symbols-rounded me-1">refresh</i>
                                        Reset
                                    </a>
                                </div>
                            </form>

                            <!-- Purchase Table -->
                            <div class="table-responsive px-3">
                                <table class="table table-hover align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Purchase ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Invoice No</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Supplier</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Items</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Amount</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Purchased By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($purchases->num_rows > 0): ?>
                                            <?php while ($purchase = $purchases->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm font-weight-bold">#<?php echo sprintf('%02d', $purchase['display_id']); ?></h6>
                                                                <p class="text-xs text-secondary mb-0">ID: <?php echo $purchase['purchase_id']; ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-sm font-weight-normal"><?php echo htmlspecialchars($purchase['invoice_no'] ?? '-'); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="text-sm font-weight-normal">
                                                                <?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?>
                                                            </span>
                                                            <span class="text-xs text-secondary">
                                                                <?php echo date('h:i A', strtotime($purchase['purchase_date'])); ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($purchase['supplier_name'] ?? 'Unknown'); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="badge bg-info text-white"><?php echo $purchase['item_count']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold text-success">Rs. <?php echo number_format($purchase['total_amount'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-normal"><?php echo htmlspecialchars($purchase['user_name']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="material-symbols-rounded text-muted mb-3" style="font-size: 48px;">shopping_cart</i>
                                                        <h6 class="text-muted mb-2">No purchases found</h6>
                                                        <p class="text-muted mb-3">
                                                            <?php if (!empty($dateFrom) || !empty($dateTo) || !empty($supplierId)): ?>
                                                                There are no purchase records for the selected filters. Try adjusting your search criteria.
                                                            <?php else: ?>
                                                                There are no purchase records in the system yet. Add new product batches in Stock Management to create purchases.
                                                            <?php endif; ?>
                                                        </p>
                                                        <a href="stock_management.php" class="btn btn-sm btn-primary">
                                                            <i class="material-symbols-rounded me-1">inventory</i>
                                                            Go to Stock Management
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($purchases->num_rows > 0): ?>
                                <!-- Pagination Info -->
                                <div class="pagination-info px-3 mt-3">
                                    <?php echo getPaginationInfo($pagination, $purchases->num_rows); ?>
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

            <!-- Quick Guide Card - Dashboard Style -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card bg-gradient-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon icon-md icon-shape bg-white text-info shadow text-center border-radius-lg me-3">
                                    <i class="material-symbols-rounded opacity-10" style="color: #1976d2;">info</i>
                                </div>
                                <div>
                                    <h5 class="card-title text-white mb-1">How to Record Purchases</h5>
                                    <p class="card-text text-white opacity-8 mb-0">To record a new purchase in the system:</p>
                                </div>
                            </div>
                            <ol class="text-white opacity-9 mb-3">
                                <li>Go to <strong>Stock Management</strong> page</li>
                                <li>Click <strong>"Add New Batch"</strong> button</li>
                                <li>Select the product and enter batch details (Batch No, Expiry Date, Cost Price, Selling Price, Quantity)</li>
                                <li>The system will automatically create a purchase record when you add a new batch</li>
                            </ol>
                            <div class="mt-3">
                                <a href="stock_management.php" class="btn btn-light text-info">
                                    <i class="material-symbols-rounded me-2">inventory</i>
                                    Go to Stock Management
                                </a>
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
        function exportPurchases() {
            Swal.fire({
                title: 'Exporting...',
                text: 'Preparing your purchases report',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = 'export_purchases.php?' + urlParams.toString();

            setTimeout(() => {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Purchases report exported successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 1000);
        }

        // Supplier data from PHP
        const suppliers = [{
                id: '',
                name: 'All Suppliers'
            },
            <?php
            $suppliers->data_seek(0);
            while ($supplier = $suppliers->fetch_assoc()):
            ?>, {
                    id: <?php echo $supplier['supplier_id']; ?>,
                    name: '<?php echo addslashes($supplier['name']); ?>'
                },
            <?php endwhile; ?>
        ];

        const searchInput = document.getElementById('supplierSearchInput');
        const dropdown = document.getElementById('supplierDropdown');
        const hiddenInput = document.getElementById('supplierIdInput');

        // Set initial value if supplier is selected
        const selectedSupplierId = '<?php echo $supplierId; ?>';
        if (selectedSupplierId) {
            const selectedSupplier = suppliers.find(s => s.id == selectedSupplierId);
            if (selectedSupplier) {
                searchInput.value = selectedSupplier.name;
            }
        } else {
            searchInput.value = 'All Suppliers';
        }

        // Filter and display suppliers
        function filterSuppliers(searchTerm) {
            const filtered = suppliers.filter(supplier =>
                supplier.name.toLowerCase().includes(searchTerm.toLowerCase())
            );

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="no-results">No suppliers found</div>';
            } else {
                dropdown.innerHTML = filtered.map(supplier => {
                    const isSelected = supplier.id == hiddenInput.value;
                    return `
                        <div class="supplier-dropdown-item ${isSelected ? 'selected' : ''}" 
                             data-id="${supplier.id}" 
                             data-name="${supplier.name}">
                            <i class="material-symbols-rounded me-2">local_shipping</i>
                            ${supplier.name}
                        </div>
                    `;
                }).join('');
            }

            dropdown.classList.add('show');
        }

        // Show all suppliers on focus
        searchInput.addEventListener('focus', function() {
            this.select();
            filterSuppliers('');
        });

        // Filter as user types
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            filterSuppliers(searchTerm);
        });

        // Handle supplier selection
        dropdown.addEventListener('click', function(e) {
            const item = e.target.closest('.supplier-dropdown-item');
            if (item) {
                const supplierId = item.dataset.id;
                const supplierName = item.dataset.name;

                searchInput.value = supplierName;
                hiddenInput.value = supplierId;
                dropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.classList.remove('show');
            }
        });



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