<?php
// page_guards.php - Specific authentication guards for each page

require_once 'auth_manager.php';

class PageGuards {
    
    // Dashboard Guard - ONLY Admin allowed (Receptionist blocked)
    public static function guardDashboard() {
        AuthManager::requireLogin();
        
        if ($_SESSION['role'] !== 'Admin') {
            // Log unauthorized access attempt
            try {
                Database::setUpConnection();
                $query = "INSERT INTO notifications (title, message, type, user_id) VALUES (?, ?, 'system', ?)";
                $stmt = Database::$connection->prepare($query);
                $title = "Unauthorized Access";
                $message = "Unauthorized dashboard access attempt by " . $_SESSION['role'] . " user: " . $_SESSION['username'];
                $userId = $_SESSION['user_id'];
                $stmt->bind_param('ssi', $title, $message, $userId);
                $stmt->execute();
            } catch (Exception $e) {
                error_log("Activity logging error: " . $e->getMessage());
            }
            
            // Redirect Receptionist to appointments page
            if ($_SESSION['role'] === 'Receptionist') {
                header('Location: appointments.php');
                exit();
            }
            
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Appointments Guard - Admin and Receptionist allowed
    public static function guardAppointments() {
        AuthManager::requireLogin();
        
        $allowedRoles = ['Admin', 'Receptionist'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Billing Guard - Admin and Receptionist allowed
    public static function guardBilling() {
        AuthManager::requireLogin();
        
        $allowedRoles = ['Admin', 'Receptionist'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Patients Guard - Admin and Receptionist allowed
    public static function guardPatients() {
        AuthManager::requireLogin();
        
        $allowedRoles = ['Admin', 'Receptionist'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Book Appointments Guard - Admin and Receptionist allowed
    public static function guardBookAppointments() {
        AuthManager::requireLogin();
        
        $allowedRoles = ['Admin', 'Receptionist'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Prescriptions Guard - Admin and Receptionist allowed
    public static function guardPrescriptions() {
        AuthManager::requireLogin();
        
        $allowedRoles = ['Admin', 'Receptionist'];
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // General page guard with custom roles
    public static function guardPage($allowedRoles) {
        AuthManager::requireLogin();
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            // Log unauthorized access
            if (isset($_SESSION['user_id'])) {
                try {
                    Database::setUpConnection();
                    $query = "INSERT INTO notifications (title, message, type, user_id) VALUES (?, ?, 'system', ?)";
                    $stmt = Database::$connection->prepare($query);
                    $title = "Unauthorized Access";
                    $message = "Unauthorized access attempt to restricted page by " . $_SESSION['role'];
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param('ssi', $title, $message, $userId);
                    $stmt->execute();
                } catch (Exception $e) {
                    error_log("Activity logging error: " . $e->getMessage());
                }
            }
            
            header('Location: unauthorized.php');
            exit();
        }
    }
    
    // Check if current user can access dashboard
    public static function canAccessDashboard() {
        return AuthManager::isLoggedIn() && $_SESSION['role'] === 'Admin';
    }
    
    // Check if user is Receptionist
    public static function isReceptionist() {
        return AuthManager::isLoggedIn() && $_SESSION['role'] === 'Receptionist';
    }
    
    // Redirect Receptionist away from dashboard
    public static function redirectReceptionistFromDashboard() {
        if (self::isReceptionist() && basename($_SERVER['PHP_SELF']) === 'dashboard.php') {
            header('Location: appointments.php');
            exit();
        }
    }
    
    // Get appropriate homepage for current user
    public static function getHomePage() {
        if (!AuthManager::isLoggedIn()) {
            return 'login.php';
        }
        
        switch ($_SESSION['role']) {
            case 'Admin':
                return 'dashboard.php';
            case 'Receptionist':
                return 'appointments.php'; // Receptionist goes to appointments, not dashboard
            default:
                return 'unauthorized.php';
        }
    }
}
?>