<?php
require_once 'auth.php';
require_once 'includes/pagination_helper.php'; // Add this line
Auth::requireAuth();

$conn = getDBConnection();

// Get search parameter
$searchTerm = $_GET['search'] ?? '';

// Pagination parameters
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 8;

// Build count query
$countSql = "SELECT COUNT(*) as total FROM customers c";

if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $countSql .= " WHERE c.display_id = ? OR c.name LIKE ? OR c.contact_no LIKE ?";
    } else {
        $countSql .= " WHERE c.name LIKE ? OR c.contact_no LIKE ?";
    }
}

$countStmt = $conn->prepare($countSql);

if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $searchParam = "%$searchTerm%";
        $countStmt->bind_param("iss", $searchTerm, $searchParam, $searchParam);
    } else {
        $searchParam = "%$searchTerm%";
        $countStmt->bind_param("ss", $searchParam, $searchParam);
    }
}

$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Calculate pagination
$pagination = calculatePagination($totalRecords, $recordsPerPage, $currentPage);

// Build main query with pagination
$sql = "SELECT c.*, LPAD(c.display_id, 2, '0') as formatted_display_id, COUNT(DISTINCT s.sale_id) as total_sales,
    COALESCE(SUM(s.net_amount), 0) as total_spent
FROM customers c
LEFT JOIN sales s ON c.customer_id = s.customer_id";

if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $sql .= " WHERE c.display_id = ? OR c.name LIKE ? OR c.contact_no LIKE ?";
    } else {
        $sql .= " WHERE c.name LIKE ? OR c.contact_no LIKE ?";
    }
}

$sql .= " GROUP BY c.customer_id ORDER BY c.display_id ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!empty($searchTerm)) {
    if (is_numeric($searchTerm)) {
        $searchParam = "%$searchTerm%";
        $stmt->bind_param("issii", $searchTerm, $searchParam, $searchParam, $pagination['limit'], $pagination['offset']);
    } else {
        $searchParam = "%$searchTerm%";
        $stmt->bind_param("ssii", $searchParam, $searchParam, $pagination['limit'], $pagination['offset']);
    }
} else {
    $stmt->bind_param("ii", $pagination['limit'], $pagination['offset']);
}

$stmt->execute();
$customers = $stmt->get_result();

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.5/sweetalert2.min.css">

    <style>
        /* Import all dashboard styles */
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

        .col-12 h3 {
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }

        .col-12 p {
            margin-top: 0 !important;
            margin-bottom: 1rem !important;
        }

        nav[aria-label="breadcrumb"] {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        .breadcrumb {
            margin-bottom: 0 !important;
        }

        .card-header {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }

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

        .navbar .dropdown-menu {
            position: absolute;
            z-index: 1060 !important;
            box-shadow: 0 8px 26px -4px rgba(20, 20, 20, 0.15);
        }

        .navbar {
            z-index: 1050 !important;
        }

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

        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

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

        .footer {
            margin-top: auto !important;
            padding-top: 2rem !important;
            padding-bottom: 1rem !important;
        }

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

        /* Search bar styling */
        .search-wrapper {
            margin-bottom: 1.5rem;
        }

        .input-group-text {
            background-color: white;
            border-right: 0;
        }

        .form-control {
            border-left: 0;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #d2d6da;
        }

        /* Action buttons */
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon svg {
            width: 16px;
            height: 16px;
        }

        /* Mobile responsive */
        @media (max-width: 576px) {
            .dashboard-header h3 {
                font-size: 1.25rem;
            }

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

            .btn-icon {
                width: 28px;
                height: 28px;
            }
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 27px 0 rgba(0, 0, 0, 0.05);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }

        /* Material Input Groups */
        .input-group-outline {
            position: relative;
            background-color: transparent;
            border-radius: 0.375rem;
            border: 1px solid #d2d6da;
            transition: border-color 0.15s ease-in-out;
        }

        .input-group-outline:focus-within {
            border-color: #0cb41aff;
        }

        .input-group-outline .form-label {
            position: absolute;
            top: 0.5rem;
            left: 0.75rem;
            padding: 0 0.25rem;
            background-color: white;
            color: #7b809a;
            font-size: 0.875rem;
            transition: all 0.2s ease-in-out;
            pointer-events: none;
            z-index: 1;
        }

        .input-group-outline .form-control {
            border: none;
            background-color: transparent;
            padding: 0.75rem;
            font-size: 0.875rem;
            color: #495057;
            height: auto;
        }

        .input-group-outline .form-control:focus {
            box-shadow: none;
            outline: none;
        }

        .input-group-outline textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        /* Active/Filled state */
        .input-group-outline.is-focused .form-label,
        .input-group-outline.is-filled .form-label {
            top: -0.5rem;
            font-size: 0.75rem;
            color: #e91e63;
        }

        /* Number input styling */
        .input-group-outline input[type="number"] {
            -moz-appearance: textfield;
        }

        .input-group-outline input[type="number"]::-webkit-outer-spin-button,
        .input-group-outline input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .modal-header {
            border-bottom: 1px solid #dee2e6;
            padding: 1.5rem;
            background-color: #0f1a0fff;
        }

        .modal-header .modal-title {
            color: #f8fffbff !important;
            font-size: 1.25rem;
        }

        .input-group {
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }

        .input-group-text {
            background: white;
            border: 1px solid #dee2e6;
            border-right: none;
            color: #6c757d;
        }

        .form-control {
            border: 1px solid #dee2e6;
            border-radius: 0.75rem !important;
            padding-left: 0.5rem;
        }

        .form-control:focus {
            border-color: #49a3f1;
            box-shadow: 0 0 0 0.2rem rgba(73, 163, 241, 0.25);
            padding-left: 0.5rem;
        }

        /* Dashboard-style modal - RESPONSIVE */
        .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* ALIGNMENT FIXES */
        /* Fix table cell alignment for customer name and contact */
        .table tbody tr td {
            vertical-align: middle !important;
        }

        .table tbody tr td p {
            margin-bottom: 0 !important;
            line-height: 1.5;
        }

        /* Fix search icon border alignment */
        .search-wrapper .input-group-outline .input-group-text {
            border-right: 1px solid #dee2e6 !important;
            border-radius: 0.75rem 0 0 0.75rem !important;
        }

        .search-wrapper .input-group-outline .form-control {
            border-left: none !important;
            border-radius: 0 0.75rem 0.75rem 0 !important;
        }

        /* Fix clear button text centering */
        .search-wrapper .btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
        }

        /* Fix action buttons styling and alignment */
        .btn-icon {
            width: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 6px !important;
            transition: all 0.2s ease !important;
        }

        .btn-icon:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-icon .material-symbols-rounded {
            font-size: 18px !important;
            line-height: 1 !important;
        }

        /* Ensure proper spacing in action buttons cell */
        td.align-middle.text-center .btn {
            margin: 0 2px !important;
        }

        /* Mobile responsive fixes for action buttons */
        @media (max-width: 576px) {
            .btn-icon {
                width: 28px !important;
                height: 28px !important;
            }

            .btn-icon .material-symbols-rounded {
                font-size: 16px !important;
            }
        }

        /* NEW IMPROVEMENTS */
        /* Move search icon slightly to the left */
        .search-wrapper .input-group-outline .input-group-text {
            padding-right: 8px !important;
            padding-left: 12px !important;
        }

        .search-wrapper .input-group-outline .form-control {
            padding-left: 8px !important;
        }

        /* BEAUTIFUL ACTION BUTTONS WITH SEPARATE BORDERS */
        .btn-icon {
            width: 40px !important;
            height: 40px !important;
            margin: 0 4px !important;
            border: 2px solid !important;
            border-radius: 8px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative !important;
            overflow: hidden !important;
        }

        /* Edit button - Warning theme with border */
        .btn-icon.text-warning {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%) !important;
            border-color: #ffb74d !important;
            color: #e65100 !important;
        }

        .btn-icon.text-warning:hover {
            background: linear-gradient(135deg, #ffe0b2 0%, #ffcc80 100%) !important;
            border-color: #ff9800 !important;
            color: #bf360c !important;
            transform: translateY(-2px) scale(1.05) !important;
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.3) !important;
        }

        .btn-icon.text-warning:active {
            transform: translateY(-1px) scale(1.02) !important;
        }

        /* Delete button - Danger theme with border */
        .btn-icon.text-danger {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%) !important;
            border-color: #ef5350 !important;
            color: #c62828 !important;
        }

        .btn-icon.text-danger:hover {
            background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%) !important;
            border-color: #f44336 !important;
            color: #b71c1c !important;
            transform: translateY(-2px) scale(1.05) !important;
            box-shadow: 0 8px 20px rgba(244, 67, 54, 0.3) !important;
        }

        .btn-icon.text-danger:active {
            transform: translateY(-1px) scale(1.02) !important;
        }

        /* Button shine effect on hover */
        .btn-icon::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent) !important;
            transition: left 0.5s !important;
        }

        .btn-icon:hover::before {
            left: 100% !important;
        }

        /* Button icon sizing */
        .btn-icon .material-symbols-rounded {
            font-size: 20px !important;
            position: relative !important;
            z-index: 1 !important;
        }

        /* Action buttons container */
        td.align-middle.text-center {
            white-space: nowrap !important;
        }

        /* Search input focus improvement */
        .search-wrapper .input-group-outline:focus-within .input-group-text {
            border-color: #49a3f1 !important;
            background-color: #f8f9fa !important;
        }

        /* Mobile responsive for new button sizes */
        @media (max-width: 576px) {
            .btn-icon {
                width: 36px !important;
                height: 36px !important;
                margin: 0 2px !important;
            }

            .btn-icon .material-symbols-rounded {
                font-size: 18px !important;
            }
        }

        /* Smooth transitions for all interactive elements */
        .btn,
        .form-control,
        .input-group-text {
            transition: all 0.3s ease !important;
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

        <div class="container-fluid py-2 mt-0">
            <div class="row">
                <div class="col-12 dashboard-header">
                    <h3 class="mb-0 h4 font-weight-bolder mt-0">Customers Management</h3>
                    <p class="mb-4">Manage your customer database and track their purchase history</p>
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 card-header-responsive">
                            <div>
                                <h6>Customer List</h6>
                                <p class="text-sm mb-0">
                                    <i class="fa fa-users text-info" aria-hidden="true"></i>
                                    <span class="font-weight-bold ms-1"><?php echo $customers->num_rows; ?></span> total customers
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-success" onclick="exportCustomers()">
                                    <i class="material-symbols-rounded text-sm">download</i>
                                    Export CSV
                                </button>
                                <button class="btn btn-sm btn-primary" style="background-color: #000;" onclick="showAddCustomerModal()">
                                    <i class="material-symbols-rounded text-sm">add_circle</i>
                                    Add Customer
                                </button>
                            </div>
                        </div>

                        <div class="card-body px-0 pb-2">
                            <!-- Search Bar -->
                            <div class="px-4 search-wrapper">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-10">
                                        <div class="input-group input-group-outline">
                                            <span class="input-group-text">
                                                <i class="material-symbols-rounded">search</i>
                                            </span>
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="search"
                                                placeholder="Search by ID, name or contact number..."
                                                value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary mb-0 flex-fill">Search</button>
                                        <?php if (!empty($searchTerm)): ?>
                                            <a href="customers.php" class="btn btn-outline-secondary mb-0 flex-fill">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Table -->
                            <div class="table-responsive mobile-table px-4">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Contact</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Address</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Credit Limit</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Sales</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Spent</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($customers->num_rows > 0): ?>
                                            <?php while ($customer = $customers->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex px-2 py-1">
                                                            <div class="d-flex flex-column justify-content-center">
                                                                <h6 class="mb-0 text-sm"><?php echo sprintf('%02d', $customer['display_id']); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($customer['name']); ?></p>
                                                    </td>
                                                    <td>
                                                        <p class="text-sm mb-0"><?php echo htmlspecialchars($customer['contact_no']); ?></p>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-xs"><?php echo htmlspecialchars($customer['address'] ?? '-'); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold">Rs. <?php echo number_format($customer['credit_limit'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm"><?php echo $customer['total_sales']; ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <span class="text-sm font-weight-bold">Rs. <?php echo number_format($customer['total_spent'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        <button class="btn btn-sm btn-icon btn-warning" onclick="editCustomer(<?php echo $customer['customer_id']; ?>)" title="Edit Customer">
                                                            <i class="material-symbols-rounded" style="font-size: 16px;">edit</i>
                                                        </button>
                                                        <button class="btn btn-sm btn-icon btn-danger" onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>)" title="Delete Customer">
                                                            <i class="material-symbols-rounded" style="font-size: 16px;">delete</i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5">
                                                    <div class="text-center py-4">
                                                        <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">group_off</i>
                                                        <p class="text-sm text-secondary mb-0 mt-2">
                                                            <strong>No customers found</strong><br>
                                                            <?php if (!empty($searchTerm)): ?>
                                                                No customers match your search "<?php echo htmlspecialchars($searchTerm); ?>"
                                                            <?php else: ?>
                                                                Click "Add Customer" to create your first customer
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($customers->num_rows > 0): ?>
                                <!-- Pagination Info -->
                                <div class="pagination-info px-3 mt-3">
                                    <?php echo getPaginationInfo($pagination, $customers->num_rows); ?>
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

            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="customerForm">
                        <input type="hidden" id="customerId" name="customer_id">
                        <div class="mb-3">
                            <label for="customerName" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="customerName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="contactNo" class="form-label">Contact Number *</label>
                            <input type="tel" class="form-control" id="contactNo" name="contact_no" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="creditLimit" class="form-label">Credit Limit (Rs.)</label>
                            <input type="number" class="form-control" id="creditLimit" name="credit_limit" value="0" step="0.01" min="0">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCustomer()">
                        <i class="material-symbols-rounded">save</i>
                        Save Customer
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
        // Sidebar toggle functionality (same as dashboard)
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

        var mobileToggle = document.getElementById('mobileToggle');
        var iconNavbarSidenav = document.getElementById('iconNavbarSidenav');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', toggleSidebar);
        }

        if (iconNavbarSidenav) {
            iconNavbarSidenav.addEventListener('click', toggleSidebar);
        }

        var sidebarBackdrop = document.getElementById('sidebarBackdrop');
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

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

        window.addEventListener('resize', function() {
            if (window.innerWidth > 1199.98) {
                closeSidebar();
            }
        });

        window.addEventListener('load', function() {
            var loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        });

        // Customer functions
        const customerModal = new bootstrap.Modal(document.getElementById('customerModal'));

        // Material input group focus handling
        document.addEventListener('DOMContentLoaded', function() {
            const inputGroups = document.querySelectorAll('.input-group-outline');

            inputGroups.forEach(function(group) {
                const input = group.querySelector('.form-control');

                if (input) {
                    // Focus event
                    input.addEventListener('focus', function() {
                        group.classList.add('is-focused');
                    });

                    // Blur event
                    input.addEventListener('blur', function() {
                        group.classList.remove('is-focused');
                        if (input.value.trim() !== '') {
                            group.classList.add('is-filled');
                        } else {
                            group.classList.remove('is-filled');
                        }
                    });

                    // Check if already has value
                    if (input.value.trim() !== '') {
                        group.classList.add('is-filled');
                    }
                }
            });
        });

        function exportCustomers() {
            Swal.fire({
                title: 'Exporting...',
                text: 'Preparing your customers report',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            window.location.href = 'export_customers.php?' + urlParams.toString();

            setTimeout(() => {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Customers report exported successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 1000);
        }

        function showAddCustomerModal() {
            document.getElementById('customerModalTitle').textContent = 'Add New Customer';
            document.getElementById('customerForm').reset();
            document.getElementById('customerId').value = '';

            // Reset input group states
            document.querySelectorAll('.input-group-outline').forEach(function(group) {
                group.classList.remove('is-focused', 'is-filled');
            });

            customerModal.show();
        }

        function editCustomer(customerId) {
            fetch('api/get_customer.php?id=' + customerId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('customerModalTitle').textContent = 'Edit Customer';
                        document.getElementById('customerId').value = data.customer.customer_id;
                        document.getElementById('customerName').value = data.customer.name;
                        document.getElementById('contactNo').value = data.customer.contact_no;
                        document.getElementById('address').value = data.customer.address || '';
                        document.getElementById('creditLimit').value = data.customer.credit_limit;

                        // Update input group states
                        document.querySelectorAll('.input-group-outline').forEach(function(group) {
                            const input = group.querySelector('.form-control');
                            if (input && input.value.trim() !== '') {
                                group.classList.add('is-filled');
                            }
                        });

                        customerModal.show();
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Failed to load customer data', 'error');
                });
        }

        function saveCustomer() {
            const form = document.getElementById('customerForm');
            const formData = new FormData(form);

            fetch('api/save_customer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }

        function deleteCustomer(customerId) {
            Swal.fire({
                title: 'Delete Customer?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/delete_customer.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                customer_id: customerId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }
    </script>
</body>

</html>