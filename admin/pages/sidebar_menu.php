<?php
// sidebar_menu.php - Dynamic sidebar that shows all items but restricts access

require_once 'auth_manager.php';

// Get current user info
$currentUser = AuthManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Define all menu items with their access permissions
$menuItems = [
    [
        'title' => 'Dashboard',
        'url' => 'dashboard.php',
        'icon' => 'dashboard',
        'allowed_roles' => ['Admin'], // Only Admin can access
        'show_to_all' => true // Show to all users
    ],
    [
        'title' => 'Appointments',
        'url' => 'appointments.php',
        'icon' => 'calendar_today',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Book Appointment',
        'url' => 'book_appointments.php',
        'icon' => 'add_circle',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Patients',
        'url' => 'patients.php',
        'icon' => 'people',
        'allowed_roles' => ['Admin', 'Receptionist'],
        'show_to_all' => true
    ],
    [
        'title' => 'Bills',
        'url' => 'create_bill.php',
        'icon' => 'receipt',
        'allowed_roles' => ['Admin', 'Receptionist'], // Only Admin
        'show_to_all' => true
    ],
    [
        'title' => 'Prescriptions',
        'url' => 'prescription.php',
        'icon' => 'medication',
        'allowed_roles' => ['Admin', 'Receptionist'], // Only Admin
        'show_to_all' => true
    ],
    [
        'title' => 'OPD Treatments',
        'url' => 'opd.php',
        'icon' => 'local_hospital',
        'allowed_roles' => ['Admin', 'Receptionist'], // Only Admin
        'show_to_all' => true
    ]
];

function hasAccessToPage($allowedRoles) {
    if (!AuthManager::isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $allowedRoles);
}

function renderSidebarMenu($menuItems, $currentPage) {
    $currentRole = $_SESSION['role'] ?? 'Guest';
    
    foreach ($menuItems as $item) {
        $isActive = ($currentPage === $item['url']);
        $hasAccess = hasAccessToPage($item['allowed_roles']);
        
        // Determine link behavior
        if ($hasAccess) {
            $linkClass = $isActive ? 'nav-link active bg-gradient-dark text-white' : 'nav-link text-dark';
            $href = $item['url'];
            $onclick = '';
            $style = '';
            $tooltip = '';
        } else {
            // No access - show but make unclickable with visual indication
            $linkClass = 'nav-link text-muted';
            $href = '#';
            $onclick = 'event.preventDefault(); showAccessDenied(\'' . $item['title'] . '\');';
            $style = 'opacity: 0.6; cursor: default;';
            $tooltip = 'title="Access Restricted to Admin only" data-bs-toggle="tooltip"';
        }
        
        echo '<li class="nav-item mt-3">';
        echo '<a class="' . $linkClass . '" href="' . $href . '" onclick="' . $onclick . '" style="' . $style . '" ' . $tooltip . '>';
        echo '<i class="material-symbols-rounded opacity-5">' . $item['icon'] . '</i>';
        echo '<span class="nav-link-text ms-1">' . $item['title'];
        
        // Add lock icon for restricted items
        if (!$hasAccess) {
            echo ' <i class="fas fa-lock" style="font-size: 10px; margin-left: 5px;"></i>';
        }
        
        echo '</span>';
        echo '</a>';
        echo '</li>';
    }
}
?>

<!-- Sidebar HTML Structure -->
<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand px-4 py-3 m-0" href="<?php echo PageGuards::getHomePage(); ?>">
            <img src="../../img/logoblack.png" class="navbar-brand-img" width="40" height="50" alt="main_logo">
            <span class="ms-1 text-sm text-dark" style="font-weight: bold;">Erundeniya</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-2">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <?php renderSidebarMenu($menuItems, $currentPage); ?>
        </ul>
    </div>
    <div class="sidenav-footer">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link text-dark" href="#" onclick="logout(); return false;">
                    <i class="material-symbols-rounded opacity-5">logout</i>
                    <span class="nav-link-text ms-1">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<!-- JavaScript for Access Denied Alert -->
<script>
function showAccessDenied(pageName) {
    // Create toast notification
    const alertHTML = `
        <div class="alert alert-warning alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-lock me-2"></i>
                <div>
                    <strong>Access Denied</strong><br>
                    <small>You don't have permission to access <strong>${pageName}</strong>. Only Admin users can access this page.</small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Remove existing alerts
    document.querySelectorAll('.alert-warning').forEach(el => el.remove());
    
    // Add new alert
    document.body.insertAdjacentHTML('beforeend', alertHTML);
    
    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert-warning');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    }, 4000);
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '?logout=1';
    }
}

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
/* Hover effect for restricted items */
.nav-link.text-muted:hover {
    background-color: rgba(255, 193, 7, 0.1) !important;
    border-radius: 10px;
}

/* Active restricted item shouldn't look active */
.nav-link.text-muted.active {
    background: none !important;
    color: #6c757d !important;
}

/* Logout hover effect */
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
</style>