<?php
require_once 'auth.php';
Auth::requireAdmin(); // Only Admin can access settings

$conn = getDBConnection();
$userInfo = Auth::getUserInfo();

// Get all roles for dropdown
$roles = $conn->query("SELECT * FROM roles ORDER BY role_name");

// Get all users
$users = $conn->query("SELECT u.*, r.role_name 
                       FROM users u 
                       LEFT JOIN roles r ON u.role_id = r.role_id 
                       ORDER BY u.full_name");
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        /* Dashboard matching styles */
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

        .nav-tabs {
            border-bottom: 1px solid #e9ecef;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #67748e;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: #000;
            background-color: #f8f9fa;
        }

        .nav-tabs .nav-link.active {
            color: #000;
            background-color: #fff;
            border-bottom: 2px solid #000;
        }

        .form-control, .form-select {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .form-control:focus, .form-select:focus {
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
        }

        .btn-primary {
            background-color: #000;
            border-color: #000;
        }

        .btn-primary:hover {
            background-color: #283148;
            border-color: #283148;
        }

        .btn-dark {
            background-color: #000;
            border-color: #000;
        }

        .btn-dark:hover {
            background-color: #283148;
            border-color: #283148;
        }

        .btn-warning {
            background-color: #fb8c00;
            border-color: #fb8c00;
        }

        .btn-warning:hover {
            background-color: #e07b00;
            border-color: #e07b00;
        }

        .btn-danger {
            background-color: #f5365c;
            border-color: #f5365c;
        }

        .btn-danger:hover {
            background-color: #e02a4e;
            border-color: #e02a4e;
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

        .badge {
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
        }

        .badge-primary {
            background: linear-gradient(195deg, #49a3f1 0%, #1A73E8 100%);
        }

        .material-symbols-rounded {
            vertical-align: middle !important;
            font-size: 20px;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .password-field-wrapper {
            position: relative;
        }
        
        .password-toggle-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
            color: #6c757d;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .password-toggle-icon:hover {
            color: #495057;
            background-color: #f8f9fa;
        }
        
        .password-field-wrapper input {
            padding-right: 45px;
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

            .table {
                font-size: 0.875rem;
            }

            .btn-sm {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
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
                <div class="col-12 dashboard-header">
                    <h3 class="mb-0 h4 font-weight-bolder mt-0">Settings</h3>
                    <p class="mb-4">Manage system settings and user accounts</p>
                </div>

                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                        <i class="material-symbols-rounded me-1">person</i>
                                        My Profile
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                                        <i class="material-symbols-rounded me-1">people</i>
                                        User Management
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab">
                                        <i class="material-symbols-rounded me-1">admin_panel_settings</i>
                                        Roles
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="settingsTabContent">
                                <!-- Profile Tab -->
                                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="mb-3">Update Profile</h5>
                                            <form id="profileForm">
                                                <div class="mb-3">
                                                    <label class="form-label">Full Name</label>
                                                    <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($userInfo['full_name']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($userInfo['email'] ?: 'N/A'); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Username</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userInfo['username']); ?>" disabled>
                                                </div>
                                                <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                                    <i class="material-symbols-rounded me-1">save</i>
                                                    Update Profile
                                                </button>
                                            </form>
                                        </div>

                                        <div class="col-md-6 mt-md-0 mt-sm-5 mt-5">
                                            <h5 class="mb-3">Change Password</h5>
                                            <form id="passwordForm">
                                                <div class="mb-3">
                                                    <label class="form-label">Current Password</label>
                                                    <div class="password-field-wrapper">
                                                        <input type="password" class="form-control" id="currentPassword" required>
                                                        <span class="password-toggle-icon" onclick="togglePassword('currentPassword', this)">
                                                            <i class="bi bi-eye"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">New Password</label>
                                                    <div class="password-field-wrapper">
                                                        <input type="password" class="form-control" id="newPassword" required>
                                                        <span class="password-toggle-icon" onclick="togglePassword('newPassword', this)">
                                                            <i class="bi bi-eye"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Confirm New Password</label>
                                                    <div class="password-field-wrapper">
                                                        <input type="password" class="form-control" id="confirmPassword" required>
                                                        <span class="password-toggle-icon" onclick="togglePassword('confirmPassword', this)">
                                                            <i class="bi bi-eye"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-warning" onclick="changePassword()">
                                                    <i class="material-symbols-rounded me-1">lock</i>
                                                    Change Password
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Users Tab -->
                                <div class="tab-pane fade" id="users" role="tabpanel">
                                    <div class="row align-items-center mb-3 g-2">
                                        <div class="col">
                                            <h5 class="mb-0">User Management</h5>
                                        </div>
                                        <div class="col-auto">
                                            <button class="btn btn-primary w-100" onclick="showAddUserModal()">
                                                <i class="material-symbols-rounded me-1">person_add</i>
                                                Add New User
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Username</th>
                                                    <th>Full Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Created At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($user = $users->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $user['user_id']; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                        <td><?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : 'N/A'; ?></td>
                                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?></span></td>
                                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                        <td>
                                                            <?php if ($user['user_id'] != $userInfo['user_id']): ?>
                                                                <button class="btn btn-sm btn-icon btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)" title="Delete User">
                                                                    <i class="material-symbols-rounded" style="font-size: 16px;">delete</i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted text-sm">Current User</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Roles Tab -->
                                <div class="tab-pane fade" id="roles" role="tabpanel">
                                    <div class="row align-items-center mb-3 g-2">
                                        <div class="col">
                                            <h5 class="mb-0">Role Management</h5>
                                        </div>
                                        <div class="col-auto">
                                            <button class="btn btn-primary w-100" onclick="showAddRoleModal()">
                                                <i class="material-symbols-rounded me-1">add_circle</i>
                                                Add New Role
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Role Name</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $roles->data_seek(0);
                                                while ($role = $roles->fetch_assoc()):
                                                ?>
                                                    <tr>
                                                        <td><?php echo $role['role_id']; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($role['role_name']); ?></strong></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-icon btn-danger" onclick="deleteRole(<?php echo $role['role_id']; ?>)" title="Delete Role">
                                                                <i class="material-symbols-rounded" style="font-size: 16px;">delete</i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="userFullName" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="userEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" id="roleId" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php
                                $roles->data_seek(0);
                                while ($role = $roles->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveUser()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="roleForm">
                        <div class="mb-3">
                            <label class="form-label">Role Name *</label>
                            <input type="text" class="form-control" id="roleName" name="role_name" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveRole()">
                        <i class="material-symbols-rounded me-1">save</i>
                        Save Role
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

        const userModal = new bootstrap.Modal(document.getElementById('userModal'));
        const roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

        function togglePassword(fieldId, iconElement) {
            const field = document.getElementById(fieldId);
            const icon = iconElement.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        function updateProfile() {
            const fullName = document.getElementById('fullName').value;
            const email = document.getElementById('email').value;

            fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: fullName,
                        email: email
                    })
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

        function changePassword() {
            const current = document.getElementById('currentPassword').value;
            const newPass = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (newPass !== confirm) {
                Swal.fire('Error', 'New passwords do not match', 'error');
                return;
            }

            fetch('api/change_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        current_password: current,
                        new_password: newPass
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', data.message, 'success');
                        document.getElementById('passwordForm').reset();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }

        function showAddUserModal() {
            document.getElementById('userForm').reset();
            userModal.show();
        }

        function saveUser() {
            const form = document.getElementById('userForm');
            const formData = new FormData(form);

            fetch('api/add_user.php', {
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

        function deleteUser(userId) {
            Swal.fire({
                title: 'Delete User?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/delete_user.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: userId
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

        function showAddRoleModal() {
            document.getElementById('roleForm').reset();
            roleModal.show();
        }

        function saveRole() {
            const roleName = document.getElementById('roleName').value;

            fetch('api/add_role.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        role_name: roleName
                    })
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

        function deleteRole(roleId) {
            Swal.fire({
                title: 'Delete Role?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/delete_role.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                role_id: roleId
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