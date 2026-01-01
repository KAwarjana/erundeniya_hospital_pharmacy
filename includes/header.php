<?php
$userInfo = Auth::getUserInfo();
$isAdmin = Auth::isAdmin();

// Redirect to login if not authenticated
if ($userInfo === null) {
    header("Location: login.php");
    exit();
}

// Get current page for breadcrumb
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_title = ucfirst(str_replace('_', ' ', $current_page));
?>

<!-- Navbar -->
<nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl mt-3 card" id="navbarBlur" data-scroll="true" style="background-color: white;">
    <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-1 pb-0 pt-1 px-0 me-sm-6 me-5">
                <li class="breadcrumb-item text-sm">
                    <a class="opacity-5 text-dark" href="dashboard.php">
                        <i class="material-symbols-rounded opacity-5">home</i>
                    </a>
                </li>
                <li class="breadcrumb-item text-sm text-dark active" aria-current="page"><?php echo $page_title; ?></li>
            </ol>
        </nav>
        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
            <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                <!-- Optional: Add search bar here if needed -->
            </div>
            <ul class="navbar-nav d-flex align-items-center justify-content-end">
                <!-- Mobile Toggle -->
                <li class="nav-item d-xl-none ps-3 d-flex align-items-center mt-1 me-3">
                    <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                        <div class="sidenav-toggler-inner">
                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>
                        </div>
                    </a>
                </li>

                <!-- Notifications (Optional) -->
                <li class="nav-item dropdown pe-2 d-flex align-items-center">
                    <a href="javascript:;" class="nav-link text-body p-0" id="mobileToggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="material-symbols-rounded">notifications</i>
                        <span class="position-absolute top-5 start-100 translate-middle badge rounded-pill bg-danger border border-white small py-1 px-2">
                            <span class="small">3</span>
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="mobileToggle">
                        <li class="mb-2">
                            <a class="dropdown-item border-radius-md" href="javascript:;">
                                <div class="d-flex py-1">
                                    <div class="my-auto">
                                        <i class="material-symbols-rounded text-warning me-3">inventory_2</i>
                                    </div>
                                    <div class="d-flex flex-column justify-content-center">
                                        <h6 class="text-sm font-weight-normal mb-1">
                                            <span class="font-weight-bold">Low Stock Alert</span>
                                        </h6>
                                        <p class="text-xs text-secondary mb-0">
                                            5 products need reorder
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a class="dropdown-item border-radius-md" href="javascript:;">
                                <div class="d-flex py-1">
                                    <div class="my-auto">
                                        <i class="material-symbols-rounded text-danger me-3">schedule</i>
                                    </div>
                                    <div class="d-flex flex-column justify-content-center">
                                        <h6 class="text-sm font-weight-normal mb-1">
                                            <span class="font-weight-bold">Expiring Soon</span>
                                        </h6>
                                        <p class="text-xs text-secondary mb-0">
                                            3 products expiring this month
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item border-radius-md" href="javascript:;">
                                <div class="d-flex py-1">
                                    <div class="my-auto">
                                        <i class="material-symbols-rounded text-success me-3">trending_up</i>
                                    </div>
                                    <div class="d-flex flex-column justify-content-center">
                                        <h6 class="text-sm font-weight-normal mb-1">
                                            <span class="font-weight-bold">Sales Update</span>
                                        </h6>
                                        <p class="text-xs text-secondary mb-0">
                                            Today's sales: Rs. 25,000
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- User Profile Dropdown -->
                <li class="nav-item dropdown d-flex align-items-center">
                    <a href="#" class="nav-link text-body font-weight-bold px-0 dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="material-symbols-rounded me-sm-1">account_circle</i>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($userInfo['username'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4" aria-labelledby="userDropdown">
                        <li class="mb-2">
                            <div class="dropdown-item border-radius-md">
                                <div class="d-flex flex-column">
                                    <h6 class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($userInfo['full_name'] ?? 'Guest User'); ?></h6>
                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($userInfo['role_name'] ?? 'User'); ?></p>
                                </div>
                            </div>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item border-radius-md" href="profile.php">
                                <i class="material-symbols-rounded text-sm me-2">person</i>
                                Profile
                            </a>
                        </li>
                        <?php if ($isAdmin): ?>
                        <li>
                            <a class="dropdown-item border-radius-md" href="settings.php">
                                <i class="material-symbols-rounded text-sm me-2">settings</i>
                                Settings
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item border-radius-md text-danger" href="auth.php?action=logout">
                                <i class="material-symbols-rounded text-sm me-2">logout</i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navbar -->

<style>
    /* Notification Badge */
    .navbar .badge {
        font-size: 0.65rem;
    }
    
    /* Dropdown Menu Styling */
    .dropdown-menu {
        box-shadow: 0 8px 26px -4px rgba(20,20,20,0.15), 0 8px 9px -5px rgba(20,20,20,0.06);
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .dropdown-item.text-danger:hover {
        background-color: #ffebee;
    }
    
    /* Mobile Toggle */
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
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.2rem;
    }
    
    /* Navbar Blur Effect */
    #navbarBlur {
        backdrop-filter: saturate(200%) blur(30px);
        background-color: rgba(255, 255, 255, 0.8) !important;
    }
</style>