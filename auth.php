<?php
require_once 'config.php';

class Auth {
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    // Require authentication
    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
    
    // Check if user has specific role
    public static function hasRole($roleId) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        // Add safety check for role_id
        $userRoleId = $_SESSION['role_id'] ?? null;
        
        if ($userRoleId === null) {
            return false;
        }
        
        if (is_array($roleId)) {
            return in_array($userRoleId, $roleId);
        }
        
        return $userRoleId == $roleId;
    }
    
    // Require specific role (redirect if not authorized)
    public static function requireRole($roleId, $redirectTo = 'dashBoard.php') {
        self::requireAuth();
        
        if (is_array($roleId)) {
            if (!in_array($_SESSION['role_id'] ?? null, $roleId)) {
                header("Location: " . $redirectTo);
                exit();
            }
        } else {
            if (($_SESSION['role_id'] ?? null) != $roleId) {
                header("Location: " . $redirectTo);
                exit();
            }
        }
    }
    
    // Check if user is admin
    public static function isAdmin() {
        return self::hasRole(1);
    }
    
    // Check if user is manager
    public static function isManager() {
        return self::hasRole(2);
    }
    
    // Check if user is cashier
    public static function isCashier() {
        return self::hasRole(3);
    }
    
    // Require admin access
    public static function requireAdmin() {
        self::requireRole(1, 'dashBoard.php');
    }
    
    // Login user
    public static function login($username, $password) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password_hash, u.full_name, u.email, u.role_id, r.role_name 
                                FROM users u 
                                LEFT JOIN roles r ON u.role_id = r.role_id 
                                WHERE u.username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if password is hashed or plain text
            // If it starts with $2y$, it's a bcrypt hash
            if (strpos($user['password_hash'], '$2y$') === 0) {
                // Verify hashed password
                $passwordValid = password_verify($password, $user['password_hash']);
            } else {
                // Compare plain text password directly
                $passwordValid = ($password === $user['password_hash']);
            }
            
            if ($passwordValid) {
                // Set session variables with null coalescing for safety
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role_id'] = $user['role_id'] ?? 0;
                $_SESSION['role_name'] = $user['role_name'] ?? 'User';
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                return true;
            }
        }
        
        return false;
    }
    
    // Logout user
    public static function logout() {
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
        
        header("Location: login.php");
        exit();
    }
    
    // Get current user info
    public static function getUserInfo() {
        if (self::isLoggedIn()) {
            return [
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'full_name' => $_SESSION['full_name'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'role_id' => $_SESSION['role_id'] ?? null,
                'role_name' => $_SESSION['role_name'] ?? 'User'
            ];
        }
        return null;
    }
    
    // Register new user (admin only)
    public static function register($username, $password, $full_name, $email, $role_id) {
        $conn = getDBConnection();
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $username, $password_hash, $full_name, $email, $role_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'User registered successfully'];
        } else {
            return ['success' => false, 'message' => 'Registration failed: ' . $stmt->error];
        }
    }
    
    // Update user password
    public static function updatePassword($userId, $newPassword) {
        $conn = getDBConnection();
        
        $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $password_hash, $userId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Password update failed'];
        }
    }
    
    // Update user profile
    public static function updateProfile($userId, $full_name, $email) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $userId);
        
        if ($stmt->execute()) {
            // Update session variables
            if (self::isLoggedIn() && $_SESSION['user_id'] == $userId) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
            }
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Profile update failed'];
        }
    }
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
}

// Function to require authentication (backwards compatibility)
function requireAuth() {
    Auth::requireAuth();
}
?>