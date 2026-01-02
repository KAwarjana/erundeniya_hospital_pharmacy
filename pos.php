<?php
require_once 'auth.php';
Auth::requireRole([1, 2, 3]); // Admin, Manager, and Cashier can access POS

$conn = getDBConnection();
$userInfo = Auth::getUserInfo();

// Get all customers for dropdown
$customers = $conn->query("SELECT customer_id, name, contact_no FROM customers ORDER BY name");
?>
<!doctype html>
<html lang="en" dir="ltr" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>POS - E. W. D. Erundeniya</title>

    <!-- Material Dashboard CSS -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,600,700,900" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400,500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="shortcut icon" href="assets/images/logoblack.png">

    <style>
        /* Main Content - Same as Dashboard */
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

        .container-fluid.py-2.mt-0 {
            padding-top: 0.5rem !important;
            margin-top: 0 !important;
        }

        /* EXACT DASHBOARD HEADER ALIGNMENT */
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

        /* Breadcrumb alignment - exact dashboard style */
        nav[aria-label="breadcrumb"] {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
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

        /* Sidebar - Same as Dashboard */
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

        /* POS Container - Material Design */
        .pos-container {
            margin-top: 0;
            padding: 1rem;
        }

        /* Material Card Styling */
        .billing-card,
        .items-card,
        .invoice-card {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .billing-card .card-header,
        .items-card .card-header,
        .invoice-card .card-header {
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 0;
        }

        .billing-card .card-body,
        .items-card .card-body,
        .invoice-card .card-body {
            padding: 1.5rem;
        }

        /* Material Form Controls */
        .form-control,
        .form-select {
            border: 1px solid #e4e7ec;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #05880cff;
            background-color: transparent;
            transition: all 0.3s ease-in-out;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #05880cff;
            box-shadow: 0 0 0 2px rgba(73, 96, 255, 0.25);
        }

        /* Material Buttons */
        .btn-action {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-save {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
            color: white;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-save:hover {
            background: linear-gradient(195deg, #54a64a 0%, #368036 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* EXACT DASHBOARD TABLE STYLING */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            background-color: transparent;
        }

        .table thead th {
            border: none;
            border-bottom: 2px solid #05880cff;
            color: #344267;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.75rem 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }

        .table tbody td {
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* EXACT TABLE COLUMN ALIGNMENT LIKE DASHBOARD */
        .table thead th:nth-child(3),
        /* Unit Price column */
        .table tbody td:nth-child(3),
        .table thead th:nth-child(6),
        /* Net Amount column */
        .table tbody td:nth-child(6) {
            text-align: right !important;
        }

        /* Material Search Box */
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e4e7ec;
            border-radius: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .search-result-item {
            padding: 1.5rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        /* Material Invoice Preview */
        .invoice-preview {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #000;
        }

        .invoice-header h4 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .invoice-detail-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .invoice-totals {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.875rem;
        }

        .total-row.grand-total {
            font-size: 1.125rem;
            font-weight: bold;
            color: #28a745;
            padding-top: 0.75rem;
            border-top: 2px dashed #000;
            margin-top: 0.75rem;
        }

        /* Payment Section */
        .payment-section {
            margin-top: 1.5rem;
        }

        /* Material Inputs */
        .item-input {
            border: 1px solid #e4e7ec;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .item-input:focus {
            border-color: #05880cff;
            box-shadow: 0 0 0 2px rgba(73, 96, 255, 0.25);
        }

        .unit-select {
            border: 1px solid #e4e7ec;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .unit-select:focus {
            border-color: #05880cff;
            box-shadow: 0 0 0 2px rgba(73, 96, 255, 0.25);
        }

        /* Material Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
        }

        .badge.bg-primary {
            background-color: #05880cff;
        }

        /* Material Text */
        .text-muted {
            color: #67748e !important;
        }

        .text-primary {
            color: #05880cff !important;
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

        /* Sidebar Backdrop - Same as Dashboard */
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

        /* Mobile Table Responsive */
        .mobile-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* RESPONSIVE BREAKPOINTS - Same as Dashboard */
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

            .pos-container {
                padding: 0.5rem;
            }

            .dashboard-header {
                padding-left: 1rem !important;
            }
        }

        @media (max-width: 991.98px) {
            .invoice-card {
                margin-top: 1rem;
            }

            .billing-card .card-body .row,
            .items-card .card-body {
                padding: 1rem;
            }

            .table thead th {
                padding: 0.5rem 0.75rem;
                font-size: 0.7rem;
            }

            .table tbody td {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .pos-container {
                padding: 0.5rem;
            }

            .invoice-preview {
                margin-top: 1rem;
                padding: 1rem;
            }

            .invoice-details {
                grid-template-columns: 1fr;
            }

            .invoice-header h4 {
                font-size: 1.2rem;
            }

            .card-header {
                padding: 1rem 1rem 0 !important;
            }

            .card-body {
                padding: 1rem !important;
            }

            .billing-card .card-body,
            .items-card .card-body,
            .invoice-card .card-body {
                padding: 1rem !important;
            }

            .form-control,
            .form-select {
                font-size: 0.8rem;
                padding: 0.6rem 0.8rem;
            }

            .btn-action {
                font-size: 0.8rem;
                padding: 0.6rem;
            }

            .search-result-item {
                padding: 1rem;
                font-size: 0.85rem;
            }

            /* Compact table for mobile */
            .table thead th {
                font-size: 0.65rem;
                padding: 0.5rem 0.5rem;
            }

            .table tbody td {
                font-size: 0.75rem;
                padding: 0.5rem 0.5rem;
            }

            .item-input,
            .unit-select {
                font-size: 0.75rem;
                padding: 0.4rem;
            }

            .btn.btn-danger {
                padding: 3px 6px !important;
                font-size: 11px !important;
            }

            .btn.btn-danger svg {
                width: 12px !important;
                height: 12px !important;
            }
        }

        @media (max-width: 576px) {
            .pos-container {
                padding: 0.25rem;
            }

            .invoice-preview {
                padding: 0.75rem;
            }

            .invoice-header h4 {
                font-size: 1rem;
            }

            .invoice-header p {
                font-size: 0.7rem;
            }

            .card-header h5 {
                font-size: 1rem;
            }

            .total-row {
                font-size: 0.8rem;
            }

            .total-row.grand-total {
                font-size: 1rem;
            }

            /* Hide less important columns on very small screens */
            .table thead th:nth-child(1),
            .table tbody td:nth-child(1) {
                display: none;
            }

            .form-label {
                font-size: 0.8rem;
                margin-bottom: 0.3rem;
            }

            .mb-3 {
                margin-bottom: 0.75rem !important;
            }

            .row {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
            }

            .row>* {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
        }

        /* iOS Safari fixes */
        @supports (-webkit-touch-callout: none) {

            .form-control,
            .form-select {
                font-size: 16px;
                /* Prevent zoom on focus */
            }
        }

        /* Landscape orientation adjustments */
        @media (max-width: 768px) and (orientation: landscape) {
            .invoice-card {
                max-height: 80vh;
                overflow-y: auto;
            }
        }

        /* Print media query */
        @media print {

            .sidebar,
            .navbar,
            .btn-action,
            .search-box {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .invoice-preview {
                box-shadow: none;
            }
        }

        /* Delete icon alignment fixes */
        .table tbody td:last-child {
            text-align: center !important;
            vertical-align: middle !important;
        }

        .btn.btn-danger {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 8px 8px !important;
            margin: 0 auto !important;
        }

        .btn.btn-danger svg {
            width: 14px !important;
            height: 14px !important;
            display: block !important;
        }

        /* Ensure consistent vertical alignment */
        .table tbody td {
            vertical-align: middle !important;
        }

        /* Mobile adjustments for icon */
        @media (max-width: 768px) {
            .btn.btn-danger {
                padding: 6px 6px !important;
            }

            .btn.btn-danger svg {
                width: 12px !important;
                height: 12px !important;
            }
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
                    <h3 class="mb-0 h4 font-weight-bolder mt-0">Point of Sale</h3>
                    <p class="mb-4">Create new sales transactions</p>
                </div>

                <!-- POS Container -->
                <div class="pos-container">
                    <div class="row">
                        <!-- Left Side - Billing & Items -->
                        <div class="col-12 col-lg-8">
                            <!-- Billing Details Card -->
                            <div class="card billing-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Billing Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 mb-3">
                                            <label class="form-label"><strong>Billing From</strong></label>
                                            <input type="text" class="form-control" id="billingFrom" value="<?php echo htmlspecialchars($userInfo['full_name']); ?>" readonly>
                                        </div>
                                        <div class="col-12 col-md-6 mb-3">
                                            <label class="form-label"><strong>Customer Type</strong></label>
                                            <select class="form-select" id="customerType" onchange="toggleCustomerInput()">
                                                <option value="walkin">Walk-in Customer</option>
                                                <option value="existing">Existing Customer</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 col-md-6 mb-3">
                                            <label class="form-label"><strong>Customer Name</strong></label>
                                            <input type="text" class="form-control" id="customerName"
                                                placeholder="Enter customer name (optional)"
                                                style="display: block;">

                                            <select class="form-select" id="customerId" style="display: none;" onchange="loadCustomerDetails()">
                                                <option value="">Select Customer</option>
                                                <?php
                                                $customers->data_seek(0);
                                                while ($customer = $customers->fetch_assoc()):
                                                ?>
                                                    <option value="<?php echo $customer['customer_id']; ?>"
                                                        data-contact="<?php echo htmlspecialchars($customer['contact_no']); ?>">
                                                        <?php echo htmlspecialchars($customer['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-6 mb-3">
                                            <label class="form-label"><strong>Mobile Number</strong></label>
                                            <input type="text" class="form-control" id="customerMobile"
                                                placeholder="Enter mobile number (optional)">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Items Card -->
                            <div class="card items-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Items</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Search Box -->
                                    <div class="search-box">
                                        <input type="text" class="form-control" id="productSearch"
                                            placeholder="Search by name, ID, or barcode...">
                                        <div id="searchResults" class="search-results"></div>
                                    </div>

                                    <!-- Items Table -->
                                    <div class="table-responsive mobile-table">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th style="width: 5%;">#</th>
                                                    <th style="width: 35%;">ITEM NAME</th>
                                                    <th style="width: 15%;">UNIT PRICE</th>
                                                    <th style="width: 15%;">QUANTITY</th>
                                                    <th style="width: 10%;">UNIT</th>
                                                    <th style="width: 15%;">NET AMOUNT</th>
                                                    <th style="width: 5%;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="cartItems">
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        No items added yet. Search and add products above.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side - Invoice Preview -->
                        <div class="col-12 col-lg-4">
                            <div class="card invoice-card">
                                <div class="card-header">
                                    <div class="invoice-header">
                                        <h4>E. W. D. එරුන්දෙනිය හෙළ ඔසුසල</h4>
                                        <p style="margin: 5px 0; font-size: 12px;">A/55 වෙදගෙදර, එරුන්දෙනිය, ආමිතිරිගල, උතුර.</p>
                                        <p style="margin: 0; font-size: 12px;">Tel: +94 77 936 6908</p>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="invoice-details">
                                        <div>
                                            <div class="invoice-detail-group">
                                                <label>Invoice date</label>
                                                <div id="invoiceDate"><?php echo date('M jS, Y'); ?></div>
                                            </div>
                                            <div class="invoice-detail-group mt-3">
                                                <label>Invoice number</label>
                                                <div id="invoiceNumber">00000000</div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="invoice-detail-group">
                                                <label>User</label>
                                                <div id="invoiceUser"><?php echo htmlspecialchars($userInfo['full_name']); ?></div>
                                            </div>
                                            <div class="invoice-detail-group mt-3">
                                                <label>Time</label>
                                                <div id="invoiceTime"><?php echo date('h:i A'); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Invoice Items -->
                                    <div class="invoice-items">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Qty</th>
                                                    <th>Unit</th>
                                                    <th>Price</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="invoiceItemsList">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No items</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Totals -->
                                    <div class="invoice-totals">
                                        <div class="total-row">
                                            <span>Net total</span>
                                            <span id="netTotal">0.00</span>
                                        </div>
                                        <div class="total-row">
                                            <span>Discount</span>
                                            <span>
                                                <input type="number" id="discount" class="form-control form-control-sm d-inline-block" value="0" min="0" step="0.01"
                                                    style="width: 80px; padding: 4px; border: 1px solid #ddd; border-radius: 4px; text-align: right;"
                                                    onchange="updateTotals()">
                                            </span>
                                        </div>
                                        <div class="total-row grand-total">
                                            <span>TOTAL</span>
                                            <span id="grandTotal">0.00</span>
                                        </div>
                                    </div>

                                    <!-- Payment Section -->
                                    <div class="payment-section">
                                        <label class="form-label"><strong>Payment Method</strong></label>
                                        <select class="form-select mb-3" id="paymentMethod">
                                            <option value="cash">Cash</option>
                                            <option value="credit">Credit</option>
                                        </select>

                                        <div class="mb-3" id="cashPaymentSection">
                                            <label class="form-label"><strong>Paid</strong></label>
                                            <input type="number" class="form-control mb-2" id="paidAmount" value="0" min="0" step="0.01" onchange="calculateChange()">

                                            <label class="form-label"><strong>Change</strong></label>
                                            <input type="text" class="form-control" id="changeAmount" value="0.00" readonly>
                                        </div>

                                        <button class="btn-action btn-save btn btn-success mb-2" onclick="saveInvoice()">Save Invoice</button>
                                        <button class="btn-action btn-cancel btn btn-secondary" onclick="clearAll()">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
    </main>

    <!-- Core JS Files -->
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.5/sweetalert2.all.min.js"></script>

    <script>
        let cart = [];
        let searchTimeout;
        let invoiceCounter = 1;

        // Update invoice number
        document.getElementById('invoiceNumber').textContent = String(invoiceCounter).padStart(8, '0');

        // Update time every second
        setInterval(() => {
            document.getElementById('invoiceTime').textContent = new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }, 1000);

        // Mobile sidebar toggle - Same as Dashboard
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

        // Mobile toggle events
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

        // Close sidebar on click outside (mobile)
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

        // Close sidebar on navigation link click (mobile)
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

        // Toggle between walk-in and existing customer
        function toggleCustomerInput() {
            const customerType = document.getElementById('customerType').value;
            const customerNameInput = document.getElementById('customerName');
            const customerSelect = document.getElementById('customerId');
            const customerMobile = document.getElementById('customerMobile');

            if (customerType === 'walkin') {
                customerNameInput.style.display = 'block';
                customerSelect.style.display = 'none';
                customerSelect.value = '';
                customerMobile.value = '';
                customerMobile.readOnly = false;
            } else {
                customerNameInput.style.display = 'none';
                customerSelect.style.display = 'block';
                customerNameInput.value = '';
                customerMobile.value = '';
                customerMobile.readOnly = false;
            }
        }

        // Load customer details when selected from dropdown
        function loadCustomerDetails() {
            const select = document.getElementById('customerId');
            const selectedOption = select.options[select.selectedIndex];
            const contactNo = selectedOption.getAttribute('data-contact');

            if (contactNo) {
                document.getElementById('customerMobile').value = contactNo;
            } else {
                document.getElementById('customerMobile').value = '';
            }
        }

        // Product search
        document.getElementById('productSearch').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 1) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                searchProducts(query);
            }, 300);
        });

        function searchProducts(query) {
            fetch('api/search_products.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    displaySearchResults(data);
                })
                .catch(error => console.error('Error:', error));
        }

        function displaySearchResults(products) {
            const resultsDiv = document.getElementById('searchResults');

            if (products.length === 0) {
                resultsDiv.innerHTML = '<div class="search-result-item text-muted">No products found</div>';
                resultsDiv.style.display = 'block';
                return;
            }

            let html = '';
            products.forEach(product => {
                html += `
                    <div class="search-result-item" onclick='addToCart(${JSON.stringify(product).replace(/'/g, "&#39;")})'>
                        <strong>${product.product_name}</strong> <span class="badge bg-primary">ID: ${product.display_id}</span>
                        ${product.generic_name ? `<br><small class="text-muted">${product.generic_name}</small>` : ''}
                        <br><small>Stock: ${product.quantity_in_stock} | Rs. ${parseFloat(product.selling_price).toFixed(2)}/${product.unit || 'kg'}</small>
                    </div>
                `;
            });

            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        }

        function addToCart(product) {
            const existingItem = cart.find(item => item.batch_id === product.batch_id);

            if (existingItem) {
                if (existingItem.quantity < product.quantity_in_stock) {
                    existingItem.quantity++;
                } else {
                    Swal.fire('Error', 'Not enough stock available', 'error');
                    return;
                }
            } else {
                cart.push({
                    batch_id: product.batch_id,
                    product_id: product.product_id,
                    product_name: product.product_name,
                    batch_no: product.batch_no,
                    price_per_kg: parseFloat(product.selling_price),
                    quantity: 1,
                    unit: 'kg',
                    max_stock: product.quantity_in_stock
                });
            }

            updateCartDisplay();
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('productSearch').value = '';
        }

        // Calculate actual price based on unit
        function calculatePrice(item) {
            let pricePerUnit = item.price_per_kg;

            switch (item.unit) {
                case 'g':
                    pricePerUnit = item.price_per_kg / 1000;
                    break;
                case 'kg':
                    pricePerUnit = item.price_per_kg;
                    break;
                case 'ml':
                    pricePerUnit = item.price_per_kg / 1000;
                    break;
                case 'bottle':
                    pricePerUnit = item.price_per_kg;
                    break;
                case 'pills':
                    pricePerUnit = item.price_per_kg;
                    break;
            }

            return pricePerUnit * item.quantity;
        }

        // Get display price per unit
        function getUnitPrice(item) {
            switch (item.unit) {
                case 'g':
                    return item.price_per_kg / 1000;
                case 'kg':
                    return item.price_per_kg;
                case 'ml':
                    return item.price_per_kg / 1000;
                case 'bottle':
                    return item.price_per_kg;
                case 'pills':
                    return item.price_per_kg;
                default:
                    return item.price_per_kg;
            }
        }

        function updateCartDisplay() {
            const cartDiv = document.getElementById('cartItems');
            const invoiceItemsDiv = document.getElementById('invoiceItemsList');

            if (cart.length === 0) {
                cartDiv.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No items added yet.</td></tr>';
                invoiceItemsDiv.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No items</td></tr>';
                updateTotals();
                return;
            }

            let cartHtml = '';
            let invoiceHtml = '';

            cart.forEach((item, index) => {
                const unitPrice = getUnitPrice(item);
                const itemTotal = calculatePrice(item);

                let qtyPlaceholder = '';
                switch (item.unit) {
                    case 'g':
                        qtyPlaceholder = 'e.g., 250 (250g)';
                        break;
                    case 'kg':
                        qtyPlaceholder = 'e.g., 1.5 (1.5kg)';
                        break;
                    case 'ml':
                        qtyPlaceholder = 'e.g., 500 (500ml)';
                        break;
                    case 'bottle':
                        qtyPlaceholder = 'e.g., 2 (2 bottles)';
                        break;
                    case 'pills':
                        qtyPlaceholder = 'e.g., 10 (10 pills)';
                        break;
                }

                cartHtml += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>${item.product_name}</strong>
                            <br><small class="text-muted">Rs. ${item.price_per_kg.toFixed(2)}/${item.unit}</small>
                        </td>
                        <td>Rs. ${unitPrice.toFixed(2)}</td>
                        <td>
                            <input type="number" class="item-input form-control form-control-sm" value="${item.quantity}" 
                                   min="0.001" step="any"
                                   placeholder="${qtyPlaceholder}"
                                   onchange="updateQuantity(${index}, this.value)">
                        </td>
                        <td>
                            <select class="unit-select form-select form-select-sm" onchange="updateUnit(${index}, this.value)">
                                <option value="g" ${item.unit === 'g' ? 'selected' : ''}>g</option>
                                <option value="kg" ${item.unit === 'kg' ? 'selected' : ''}>kg</option>
                                <option value="ml" ${item.unit === 'ml' ? 'selected' : ''}>ml</option>
                                <option value="bottle" ${item.unit === 'bottle' ? 'selected' : ''}>bottle</option>
                                <option value="pills" ${item.unit === 'pills' ? 'selected' : ''}>pills</option>
                            </select>
                        </td>
                        <td>Rs. ${itemTotal.toFixed(2)}</td>
                        <td class="text-center">
    <button class="btn btn-danger btn-icon" onclick="removeFromCart(${index})" title="Remove item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M20.708 6.23975H3.75" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    </button>
</td>
                    </tr>
                `;

                invoiceHtml += `
                    <tr>
                        <td>${item.product_name}</td>
                        <td>${item.quantity}</td>
                        <td>${item.unit}</td>
                        <td>${unitPrice.toFixed(2)}</td>
                        <td>${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            });

            cartDiv.innerHTML = cartHtml;
            invoiceItemsDiv.innerHTML = invoiceHtml;
            updateTotals();
        }

        function updateQuantity(index, value) {
            const quantity = parseFloat(value);
            const item = cart[index];

            if (isNaN(quantity) || quantity <= 0) {
                Swal.fire('Error', 'Invalid quantity', 'error');
                updateCartDisplay();
                return;
            }

            let stockInSelectedUnit = item.max_stock;
            if (item.unit === 'g' || item.unit === 'ml') {
                stockInSelectedUnit = item.max_stock * 1000;
            } else if (item.unit === 'pills') {
                stockInSelectedUnit = item.max_stock;
            }

            if (quantity > stockInSelectedUnit) {
                Swal.fire('Error', `Not enough stock. Available: ${stockInSelectedUnit} ${item.unit}`, 'error');
                updateCartDisplay();
                return;
            }

            item.quantity = quantity;
            updateCartDisplay();
        }

        function updateUnit(index, unit) {
            const item = cart[index];
            const oldUnit = item.unit;

            if (oldUnit !== unit) {
                if (oldUnit === 'kg' && unit === 'g') {
                    item.quantity = item.quantity * 1000;
                } else if (oldUnit === 'g' && unit === 'kg') {
                    item.quantity = item.quantity / 1000;
                } else if (oldUnit === 'kg' && unit === 'ml') {
                    item.quantity = item.quantity * 1000;
                } else if (oldUnit === 'ml' && unit === 'kg') {
                    item.quantity = item.quantity / 1000;
                } else if (oldUnit === 'g' && unit === 'ml') {
                    // Keep same value
                } else if (oldUnit === 'ml' && unit === 'g') {
                    // Keep same value
                } else if (oldUnit === 'pills') {
                    item.quantity = item.quantity;
                } else if (unit === 'pills') {
                    item.quantity = item.quantity;
                }
            }

            item.unit = unit;
            updateCartDisplay();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + calculatePrice(item), 0);
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const total = subtotal - discount;

            document.getElementById('netTotal').textContent = subtotal.toFixed(2);
            document.getElementById('grandTotal').textContent = Math.max(0, total).toFixed(2);

            calculateChange();
        }

        function calculateChange() {
            const total = parseFloat(document.getElementById('grandTotal').textContent);
            const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
            const change = paid - total;

            document.getElementById('changeAmount').value = Math.max(0, change).toFixed(2);
        }

        // Payment method change
        document.getElementById('paymentMethod').addEventListener('change', function() {
            const cashSection = document.getElementById('cashPaymentSection');
            if (this.value === 'cash') {
                cashSection.style.display = 'block';
            } else {
                cashSection.style.display = 'none';
            }
        });

        function saveInvoice() {
            if (cart.length === 0) {
                Swal.fire('Error', 'Cart is empty', 'error');
                return;
            }

            const customerType = document.getElementById('customerType').value;
            let customerId = null;
            let customerName = 'Walk-in Customer';
            let customerMobile = document.getElementById('customerMobile').value.trim();

            if (customerType === 'walkin') {
                customerName = document.getElementById('customerName').value.trim() || 'Walk-in Customer';
            } else {
                customerId = document.getElementById('customerId').value;
                if (customerId) {
                    const select = document.getElementById('customerId');
                    customerName = select.options[select.selectedIndex].text;
                }
            }

            const paymentType = document.getElementById('paymentMethod').value;
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const subtotal = cart.reduce((sum, item) => sum + calculatePrice(item), 0);
            const total = subtotal - discount;

            Swal.fire({
                title: 'Save Invoice?',
                html: `
                    <div class="text-start">
                        <p><strong>Customer:</strong> ${customerName}</p>
                        ${customerMobile ? `<p><strong>Mobile:</strong> ${customerMobile}</p>` : ''}
                        <p><strong>Total Amount:</strong> Rs. ${total.toFixed(2)}</p>
                        <p><strong>Payment:</strong> ${paymentType.toUpperCase()}</p>
                        <p><strong>Items:</strong> ${cart.length}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Save & Print'
            }).then((result) => {
                if (result.isConfirmed) {
                    processSale(customerId, customerName, customerMobile, paymentType, discount, subtotal, total);
                }
            });
        }

        function processSale(customerId, customerName, customerMobile, paymentType, discount, subtotal, total) {
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
            const changeAmount = parseFloat(document.getElementById('changeAmount').value) || 0;

            const data = {
                customer_id: customerId || null,
                customer_name: customerName,
                customer_mobile: customerMobile,
                payment_type: paymentType,
                discount: discount,
                total_amount: subtotal,
                net_amount: total,
                paid_amount: paidAmount,
                change_amount: changeAmount,
                items: cart
            };

            fetch('api/process_sale.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Invoice saved successfully',
                            icon: 'success',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            const printUrl = 'print_receipt.php?sale_id=' + result.sale_id +
                                '&paid=' + paidAmount +
                                '&change=' + changeAmount;
                            window.open(printUrl, '_blank');

                            clearAll();
                            invoiceCounter++;
                            document.getElementById('invoiceNumber').textContent = String(invoiceCounter).padStart(8, '0');
                        });
                    } else {
                        Swal.fire('Error', result.message || 'Failed to save invoice', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred while processing the sale', 'error');
                });
        }

        function clearAll() {
            if (cart.length > 0) {
                Swal.fire({
                    title: 'Clear All?',
                    text: 'Are you sure you want to clear all items?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Yes, clear!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        cart = [];
                        document.getElementById('discount').value = '0';
                        document.getElementById('paidAmount').value = '0';
                        document.getElementById('customerType').value = 'walkin';
                        document.getElementById('customerName').value = '';
                        document.getElementById('customerMobile').value = '';
                        document.getElementById('customerId').value = '';
                        toggleCustomerInput();
                        updateCartDisplay();
                    }
                });
            } else {
                cart = [];
                document.getElementById('discount').value = '0';
                document.getElementById('paidAmount').value = '0';
                document.getElementById('customerType').value = 'walkin';
                document.getElementById('customerName').value = '';
                document.getElementById('customerMobile').value = '';
                document.getElementById('customerId').value = '';
                toggleCustomerInput();
                updateCartDisplay();
            }
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                document.getElementById('searchResults').style.display = 'none';
            }
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
    </script>
</body>

</html>