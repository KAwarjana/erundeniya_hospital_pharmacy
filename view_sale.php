<?php
require_once 'auth.php';
Auth::requireAuth();

$saleId = $_GET['sale_id'] ?? 0;
$paidAmount = floatval($_GET['paid'] ?? 0);
$changeAmount = floatval($_GET['change'] ?? 0);

if ($saleId <= 0) {
    die('Invalid sale ID');
}

$conn = getDBConnection();

// Get sale details
$stmt = $conn->prepare("SELECT 
    s.*,
    c.name as customer_name,
    c.contact_no,
    c.address,
    u.full_name as user_name
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.customer_id
LEFT JOIN users u ON s.user_id = u.user_id
WHERE s.sale_id = ?");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$saleResult = $stmt->get_result();

if ($saleResult->num_rows === 0) {
    die('Sale not found');
}

$sale = $saleResult->fetch_assoc();

// Get sale items
$stmt = $conn->prepare("SELECT 
    si.*,
    p.product_name,
    p.generic_name,
    p.unit,
    pb.batch_no
FROM sale_items si
JOIN product_batches pb ON si.batch_id = pb.batch_id
JOIN products p ON pb.product_id = p.product_id
WHERE si.sale_id = ?
ORDER BY p.product_name");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$items = $stmt->get_result();

// Get sale statistics for header
$totalItems = $items->num_rows;
$totalQuantity = 0;
while ($item = $items->fetch_assoc()) {
    $totalQuantity += $item['quantity'];
}
$items->data_seek(0); // Reset the result pointer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sale - #<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?></title>
    
    <link rel="shortcut icon" href="assets/images/logof1.png">
    
    <!-- Fonts and icons -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>

    <!-- CSS Files -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet" />
    <link href="assets/css/fixes.css" rel="stylesheet" />

    <style>
        /* Dashboard matching styles */
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        
        .sale-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 0;
        }
        
        .sale-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .sale-header h2 {
            color: #000;
            margin: 0;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 0.5rem;
            border-left: 4px solid #000;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: #67748e;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #000;
            font-weight: 500;
        }
        
        .items-section {
            margin-top: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #000;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table {
            margin-bottom: 1.5rem;
        }
        
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
            padding: 0.75rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #67748e;
        }
        
        .table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .totals-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 1rem;
        }
        
        .total-row.grand-total {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 0.75rem;
            font-size: 1.25rem;
            font-weight: bold;
            color: #43A047;
        }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }
        
        .btn-custom {
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-cash {
            background: linear-gradient(195deg, #66BB6A 0%, #43A047 100%);
            color: white;
        }
        
        .badge-credit {
            background: linear-gradient(195deg, #ffa726 0%, #fb8c00 100%);
            color: white;
        }

        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
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

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .sale-container {
                padding: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-custom {
                width: 100%;
                justify-content: center;
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

        /* Header with statistics */
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #000;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #67748e;
            text-transform: uppercase;
            margin-top: 0.25rem;
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
<body>
    <div id="loading">
        <div class="simple-loader">
            <div class="loader-body"></div>
        </div>
    </div>

    <div class="sale-container">
        <!-- Header -->
        <div class="sale-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Sale Details</h2>
                    <p class="text-muted mb-0">Invoice #<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div>
                    <span class="status-badge badge-<?php echo $sale['payment_type']; ?>">
                        <i class="material-symbols-rounded me-1" style="font-size: 16px;">payment</i>
                        <?php echo strtoupper($sale['payment_type']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Sale Statistics -->
        <div class="sale-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalItems; ?></div>
                <div class="stat-label">Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQuantity; ?></div>
                <div class="stat-label">Total Quantity</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></div>
                <div class="stat-label">Date</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></div>
                <div class="stat-label">Time</div>
            </div>
        </div>

        <!-- Sale Information -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">Date & Time</div>
                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($sale['sale_date'])); ?></div>
            </div>
            
            <div class="info-card">
                <div class="info-label">Cashier</div>
                <div class="info-value"><?php echo htmlspecialchars($sale['user_name']); ?></div>
            </div>
            
            <div class="info-card">
                <div class="info-label">Customer</div>
                <div class="info-value"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></div>
                <?php if ($sale['contact_no']): ?>
                    <div class="text-muted" style="font-size: 0.875rem; margin-top: 0.25rem;">
                        <i class="material-symbols-rounded me-1" style="font-size: 16px;">phone</i>
                        <?php echo htmlspecialchars($sale['contact_no']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <div class="info-label">Payment Method</div>
                <div class="info-value"><?php echo ucfirst($sale['payment_type']); ?></div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="items-section">
            <h3 class="section-title">Items Purchased</h3>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 15%;">Batch No</th>
                            <th style="width: 12%;" class="text-center">Quantity</th>
                            <th style="width: 14%;" class="text-end">Unit Price</th>
                            <th style="width: 14%;" class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $itemNo = 1;
                        while ($item = $items->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $itemNo++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <?php if ($item['generic_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['generic_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['batch_no']); ?></td>
                                <td class="text-center">
                                    <strong><?php echo floatval($item['quantity']); ?></strong>
                                </td>
                                <td class="text-end">Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end"><strong>Rs. <?php echo number_format($item['total_price'], 2); ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals -->
        <div class="totals-card">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>Rs. <?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
            
            <?php if ($sale['discount'] > 0): ?>
                <div class="total-row">
                    <span>Discount:</span>
                    <span class="text-danger">- Rs. <?php echo number_format($sale['discount'], 2); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span>TOTAL AMOUNT:</span>
                <span>Rs. <?php echo number_format($sale['net_amount'], 2); ?></span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-success btn-custom" onclick="printSale()">
                <i class="material-symbols-rounded me-1">print</i>
                Print Receipt
            </button>
            <button class="btn btn-secondary btn-custom" onclick="window.close()">
                <i class="material-symbols-rounded me-1">close</i>
                Close
            </button>
        </div>
    </div>

    <!-- Core JS Files -->
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>

    <script>
        // Hide loading when page is loaded
        window.addEventListener('load', function() {
            var loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = 'none';
            }
        });

        function printSale() {
            window.open('print_receipt.php?sale_id=<?php echo $saleId; ?>', '_blank');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printSale();
            }
            // ESC to close
            if (e.key === 'Escape') {
                window.close();
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