<?php
$userInfo = Auth::getUserInfo();
$roleId   = $userInfo['role_id'];
$isAdmin  = Auth::isAdmin();
$isManager = Auth::isManager();
$isCashier = Auth::isCashier();

/* ----------  ACTIVE PAGE IDENTIFIER  ---------- */
$current_page = basename($_SERVER['PHP_SELF']);   // e.g. pos.php
?>

<!-- Sidebar -->
<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand px-4 py-3 m-0" href="<?php echo $isCashier ? 'pos.php' : 'dashboard.php'; ?>">
            <img src="assets/images/logoblack.png" class="navbar-brand-img" width="40" height="50" alt="main_logo">
            <span class="ms-1 text-sm text-dark" style="font-weight: bold;">Erundeniya</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-2">
    
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            
            <!-- Dashboard -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="dashboard.php">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                <?php endif; ?>
            </li>

            <li class="nav-item mt-2">
                <hr class="horizontal dark">
            </li>
            <li class="nav-item">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-6">Sales</h6>
            </li>

            <!-- POS / New Sale -->
            <li class="nav-item mt-3">
                <a class="nav-link <?= ($current_page == 'pos.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="pos.php">
                    <i class="material-symbols-rounded opacity-5">point_of_sale</i>
                    <span class="nav-link-text ms-1">POS / New Sale</span>
                </a>
            </li>

            <!-- Sales History -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">history</i>
                        <span class="nav-link-text ms-1">Sales History</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'sales_history.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="sales_history.php">
                        <i class="material-symbols-rounded opacity-5">history</i>
                        <span class="nav-link-text ms-1">Sales History</span>
                    </a>
                <?php endif; ?>
            </li>

            <li class="nav-item mt-2">
                <hr class="horizontal dark">
            </li>
            <li class="nav-item">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-6">Inventory</h6>
            </li>

            <!-- Products -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">inventory_2</i>
                        <span class="nav-link-text ms-1">Products</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'products.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="products.php">
                        <i class="material-symbols-rounded opacity-5">inventory_2</i>
                        <span class="nav-link-text ms-1">Products</span>
                    </a>
                <?php endif; ?>
            </li>

            <!-- Stock Management -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">warehouse</i>
                        <span class="nav-link-text ms-1">Stock Management</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'stock_management.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="stock_management.php">
                        <i class="material-symbols-rounded opacity-5">warehouse</i>
                        <span class="nav-link-text ms-1">Stock Management</span>
                    </a>
                <?php endif; ?>
            </li>

            <!-- Purchases -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">shopping_cart</i>
                        <span class="nav-link-text ms-1">Purchases</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'purchases.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="purchases.php">
                        <i class="material-symbols-rounded opacity-5">shopping_cart</i>
                        <span class="nav-link-text ms-1">Purchases</span>
                    </a>
                <?php endif; ?>
            </li>

            <li class="nav-item mt-2">
                <hr class="horizontal dark">
            </li>
            <li class="nav-item">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-6">Management</h6>
            </li>

            <!-- Customers -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">people</i>
                        <span class="nav-link-text ms-1">Customers</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'customers.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="customers.php">
                        <i class="material-symbols-rounded opacity-5">people</i>
                        <span class="nav-link-text ms-1">Customers</span>
                    </a>
                <?php endif; ?>
            </li>

            <!-- Suppliers -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">local_shipping</i>
                        <span class="nav-link-text ms-1">Suppliers</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'suppliers.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="suppliers.php">
                        <i class="material-symbols-rounded opacity-5">local_shipping</i>
                        <span class="nav-link-text ms-1">Suppliers</span>
                    </a>
                <?php endif; ?>
            </li>

            <!-- Reports -->
            <li class="nav-item mt-3">
                <?php if ($isCashier): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Access Denied">
                        <i class="material-symbols-rounded opacity-5">assessment</i>
                        <span class="nav-link-text ms-1">Reports</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'reports.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="reports.php">
                        <i class="material-symbols-rounded opacity-5">assessment</i>
                        <span class="nav-link-text ms-1">Reports</span>
                    </a>
                <?php endif; ?>
            </li>

            <li class="nav-item mt-2">
                <hr class="horizontal dark">
            </li>

            <!-- Settings - Admin Only -->
            <li class="nav-item mt-3">
                <?php if (!$isAdmin): ?>
                    <a class="nav-link text-muted disabled" href="javascript:void(0)" style="opacity:0.5;cursor:not-allowed;" title="Admin Only">
                        <i class="material-symbols-rounded opacity-5">settings</i>
                        <span class="nav-link-text ms-1">Settings</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= ($current_page == 'settings.php') ? 'active bg-gradient-dark text-white' : 'text-dark' ?>" href="settings.php">
                        <i class="material-symbols-rounded opacity-5">settings</i>
                        <span class="nav-link-text ms-1">Settings</span>
                    </a>
                <?php endif; ?>
            </li>
            
        </ul>
    </div>
    
    <div class="sidenav-footer">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link text-dark" href="auth.php?action=logout">
                    <i class="material-symbols-rounded opacity-5">logout</i>
                    <span class="nav-link-text ms-1">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<style>
    /* Active Link Highlight */
    .nav-link.active {
        background-color: #000 !important;
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
    
    /* Section Headers */
    .navbar-nav h6 {
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
        letter-spacing: 0.5px;
    }
    
    /* Sidebar Scrollbar */
    .navbar-vertical .navbar-nav {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
    
    .navbar-vertical .navbar-nav::-webkit-scrollbar {
        width: 5px;
    }
    
    .navbar-vertical .navbar-nav::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .navbar-vertical .navbar-nav::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    
    .navbar-vertical .navbar-nav::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* Material Icons Alignment */
    .material-symbols-rounded {
        vertical-align: middle;
        font-size: 20px;
    }
    
    /* Responsive */
    @media (max-width: 1199.98px) {
        .sidenav {
            transform: translateX(-17.125rem);
        }
        
        .sidenav.show {
            transform: translateX(0);
        }
    }
</style>