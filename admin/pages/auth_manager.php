<?php
// auth_manager.php - Complete authentication management system
session_start();
require_once '../../connection/connection.php';

class AuthManager
{

    // Check if user is logged in
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }

    // Login user and redirect based on role
    public static function login($username, $password)
    {
        try {
            Database::setUpConnection();

            $username = Database::$connection->real_escape_string($username);

            $query = "SELECT id, user_name, password, role, status FROM user WHERE user_name = ? AND status = 'Active'";
            $stmt = Database::$connection->prepare($query);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Block Pharmacist from logging in
                if ($user['role'] === 'Pharmacist') {
                    return ['success' => false, 'error' => 'Pharmacist access is not allowed in this system'];
                }

                // Verify password from database
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['user_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();

                    // Log login activity
                    self::logActivity($user['id'], "User logged in from IP: " . $_SERVER['REMOTE_ADDR']);

                    return [
                        'success' => true,
                        'user' => $user,
                        'redirect' => self::getRedirectUrl($user['role'])
                    ];
                }
            }

            return ['success' => false, 'error' => 'Invalid username or password'];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Login system error. Please try again.'];
        }
    }

    // Get redirect URL based on user role
    private static function getRedirectUrl($role)
    {
        switch ($role) {
            case 'Admin':
                return 'dashboard.php';
            case 'Receptionist':
                return 'appointments.php'; // Receptionist cannot access dashboard
            default:
                return 'unauthorized.php';
        }
    }

    // Check session timeout (30 minutes)
    public static function checkSessionTimeout()
    {
        $timeout_duration = 1800; // 30 minutes

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
            self::logout();
            header('Location: login.php?timeout=1');
            exit();
        }

        $_SESSION['last_activity'] = time();
    }

    // Require login for any page
    public static function requireLogin()
    {
        self::checkSessionTimeout();

        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    // Check if user has access to specific page
    public static function checkPageAccess($page)
    {
        self::requireLogin();

        $role = $_SESSION['role'];
        $permissions = self::getRolePermissions($role);

        if (!isset($permissions[$page]) || !$permissions[$page]) {
            header('Location: unauthorized.php');
            exit();
        }
    }

    // Get role-based permissions
    // Get role-based permissions
    public static function getRolePermissions($role)
    {
        $permissions = [
            'Admin' => [
                'dashboard' => true,
                'appointments' => true,
                'patients' => true,
                'billing' => true,
                'prescriptions' => true,
                'time_slots' => true,
                'manual_booking' => true,
                'reports' => true,
                'settings' => true
            ],
            'Receptionist' => [
                'dashboard' => false,  // CHANGE: Now Receptionist can access dashboard
                'appointments' => true,
                'patients' => true,
                'billing' => true,    // CHANGE: Now Receptionist can access billing
                'prescriptions' => true, // CHANGE: Now Receptionist can access prescriptions
                'time_slots' => true, // CHANGE: Now Receptionist can access time slots
                'manual_booking' => true,
                'reports' => true,    // CHANGE: Now Receptionist can access reports
                'settings' => false   // Receptionist cannot access settings
            ],
            'Pharmacist' => [
                'dashboard' => false,
                'appointments' => false,
                'patients' => false,
                'billing' => false,
                'prescriptions' => true,
                'time_slots' => false,
                'manual_booking' => false,
                'reports' => false,
                'settings' => false
            ]
        ];

        return $permissions[$role] ?? [];
    }

    // Check if user has specific permission
    public static function hasPermission($permission)
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $permissions = self::getRolePermissions($_SESSION['role']);
        return $permissions[$permission] ?? false;
    }

    // Log user activity
    private static function logActivity($userId, $message)
    {
        try {
            Database::setUpConnection(); // Ensure connection is established

            // Double-check connection is valid
            if (!Database::$connection || Database::$connection->connect_error) {
                error_log("Database connection failed in logActivity");
                return;
            }

            $query = "INSERT INTO notifications (title, message, type, user_id) VALUES (?, ?, 'system', ?)";
            $stmt = Database::$connection->prepare($query);

            if (!$stmt) {
                error_log("Failed to prepare statement: " . Database::$connection->error);
                return;
            }

            $title = "User Activity";
            $stmt->bind_param('ssi', $title, $message, $userId);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }

    // Logout user
    public static function logout()
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            try {
                self::logActivity($userId, "User logged out");
            } catch (Exception $e) {
                // Silently fail logging, continue with logout
                error_log("Logout logging failed: " . $e->getMessage());
            }
        }

        session_unset();
        session_destroy();

        header('Location: login.php?logged_out=1');
        exit();
    }

    // Get current user info
    public static function getCurrentUser()
    {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }

    // Generate navigation menu based on role
    public static function getNavigationMenu()
    {
        if (!self::isLoggedIn()) {
            return [];
        }

        $role = $_SESSION['role'];
        $permissions = self::getRolePermissions($role);

        $menu = [];

        if ($permissions['dashboard']) {
            $menu[] = [
                'title' => 'Dashboard',
                'url' => 'dashboard.php',
                'icon' => 'dashboard'
            ];
        }

        if ($permissions['appointments']) {
            $menu[] = [
                'title' => 'Appointments',
                'url' => 'appointments.php',
                'icon' => 'calendar_today'
            ];
        }

        if ($permissions['manual_booking']) {
            $menu[] = [
                'title' => 'Book Appointment',
                'url' => 'book_appointments.php',
                'icon' => 'add_circle'
            ];
        }

        if ($permissions['patients']) {
            $menu[] = [
                'title' => 'Patients',
                'url' => 'patients.php',
                'icon' => 'people'
            ];
        }

        if ($permissions['billing']) {
            $menu[] = [
                'title' => 'Bills',
                'url' => 'create_bill.php',
                'icon' => 'receipt'
            ];
        }

        if ($permissions['prescriptions']) {
            $menu[] = [
                'title' => 'Prescriptions',
                'url' => 'prescription.php',
                'icon' => 'medication'
            ];
        }

        return $menu;
    }
}

// Page-specific authentication functions
class PageAuth
{

    // Dashboard page authentication
    public static function requireDashboardAccess()
    {
        AuthManager::checkPageAccess('dashboard');

        // Only Admin can access dashboard
        if ($_SESSION['role'] !== 'Admin') {
            header('Location: unauthorized.php');
            exit();
        }
    }

    // Appointments page authentication
    public static function requireAppointmentsAccess()
    {
        AuthManager::checkPageAccess('appointments');

        // Admin and Receptionist can access
        if (!in_array($_SESSION['role'], ['Admin', 'Receptionist'])) {
            header('Location: unauthorized.php');
            exit();
        }
    }

    // Prescriptions page authentication
    public static function requirePrescriptionsAccess()
    {
        AuthManager::checkPageAccess('prescriptions');

        // Admin and Pharmacist can access
        if (!in_array($_SESSION['role'], ['Admin', 'Pharmacist'])) {
            header('Location: unauthorized.php');
            exit();
        }
    }
}

// Handle logout request
if (isset($_GET['logout'])) {
    AuthManager::logout();
}
