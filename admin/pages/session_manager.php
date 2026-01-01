<?php
// session_manager.php
session_start();
require_once 'connection.php';

class SessionManager {
    
    public static function login($username, $password) {
        try {
            Database::setUpConnection();
            
            $username = Database::$connection->real_escape_string($username);
            
            $query = "SELECT * FROM user WHERE user_name = '$username' AND status = 'Active'";
            $result = Database::search($query);
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['user_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    return ['success' => true, 'user' => $user];
                }
            }
            
            return ['success' => false, 'error' => 'Invalid credentials'];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Login failed'];
        }
    }
    
    public static function logout() {
        session_destroy();
        return true;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function requireRole($requiredRole) {
        self::requireLogin();
        
        if ($_SESSION['role'] !== $requiredRole && $_SESSION['role'] !== 'Admin') {
            header('Location: unauthorized.php');
            exit;
        }
    }
    
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
}

// Login page (login.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = SessionManager::login($username, $password);
    
    if ($result['success']) {
        // Check role permissions
        if ($result['user']['role'] === 'Admin') {
            header('Location: admin/dashboard.php');
        } elseif ($result['user']['role'] === 'Receptionist') {
            header('Location: admin/dashboard.php'); // Limited access
        } else {
            header('Location: unauthorized.php');
        }
        exit;
    } else {
        $error = $result['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Erundeniya Ayurveda Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: block;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-login {
            background: #28a745;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="img/logoblack.png" alt="Logo" class="logo">
        <h3 class="text-center mb-4">Admin Login</h3>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-login w-100 text-white">Login</button>
        </form>
        
        <div class="text-center mt-4">
            <small class="text-muted">
                Default credentials:<br>
                Admin: Admin / password<br>
                Receptionist: Receptionist / password
            </small>
        </div>
    </div>
</body>
</html>

<?php
// dashboard_auth.php - Include this at the top of dashboard pages
require_once '../session_manager.php';

// Check if user is logged in
SessionManager::requireLogin();

// For admin dashboard, only allow Admin and Receptionist roles
$allowedRoles = ['Admin', 'Receptionist'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header('Location: unauthorized.php');
    exit;
}

// Set user permissions based on role
$permissions = [];
if ($_SESSION['role'] === 'Admin') {
    $permissions = [
        'view_dashboard' => true,
        'manage_appointments' => true,
        'book_manual' => true,
        'view_patients' => true,
        'create_bills' => true,
        'create_prescriptions' => true,
        'view_all_data' => true
    ];
} elseif ($_SESSION['role'] === 'Receptionist') {
    $permissions = [
        'view_dashboard' => false, // Restricted access to dashboard
        'manage_appointments' => true,
        'book_manual' => true,
        'view_patients' => true,
        'create_bills' => false,
        'create_prescriptions' => false,
        'view_all_data' => false
    ];
} else {
    // Pharmacist has no access to this system
    header('Location: unauthorized.php');
    exit;
}
?>

<?php
// payhere_integration.php
class PayHereIntegration {
    
    private static $merchantId = "YOUR_MERCHANT_ID";
    private static $merchantSecret = "YOUR_MERCHANT_SECRET";
    private static $currency = "LKR";
    private static $sandbox = true; // Set to false for production
    
    public static function generatePaymentHash($orderId, $amount, $currency = null) {
        $currency = $currency ?? self::$currency;
        
        $hash = strtoupper(
            md5(
                self::$merchantId . 
                $orderId . 
                number_format($amount, 2, '.', '') . 
                $currency . 
                strtoupper(md5(self::$merchantSecret))
            )
        );
        
        return $hash;
    }
    
    public static function createPaymentData($appointmentData, $patientData) {
        $orderId = $appointmentData['appointment_number'];
        $amount = $appointmentData['total_amount'];
        
        $paymentData = [
            'sandbox' => self::$sandbox,
            'merchant_id' => self::$merchantId,
            'return_url' => 'http://localhost/erundeniya/payment_success.php',
            'cancel_url' => 'http://localhost/erundeniya/payment_cancel.php',
            'notify_url' => 'http://localhost/erundeniya/payment_notify.php',
            'order_id' => $orderId,
            'items' => 'Consultation Fee - Erundeniya Ayurveda',
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => self::$currency,
            'hash' => self::generatePaymentHash($orderId, $amount),
            'first_name' => $patientData['name'],
            'last_name' => '',
            'email' => $patientData['email'] ?? '',
            'phone' => $patientData['mobile'],
            'address' => $patientData['address'] ?? 'Colombo',
            'city' => 'Colombo',
            'country' => 'Sri Lanka'
        ];
        
        return $paymentData;
    }
}

// payment_notify.php - PayHere notification handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'connection.php';
    require_once 'payhere_integration.php';
    
    $merchantId = $_POST['merchant_id'];
    $orderId = $_POST['order_id'];
    $paymentId = $_POST['payment_id'];
    $amount = $_POST['payhere_amount'];
    $currency = $_POST['payhere_currency'];
    $statusCode = $_POST['status_code'];
    $md5sig = $_POST['md5sig'];
    
    // Verify the payment
    // $localMd5sig = strtoupper(
    //     md5(
    //         $merchantId . 
    //         $orderId . 
    //         $amount . 
    //         $currency . 
    //         $statusCode . 
    //         strtoupper(md5(PayHereIntegration::$merchantSecret))
    //     )
    // );
    
    if ($localMd5sig === $md5sig && $statusCode == 2) {
        // Payment successful
        try {
            Database::setUpConnection();
            
            $query = "UPDATE appointment 
                     SET payment_status = 'Paid', payment_id = '$paymentId', status = 'Confirmed'
                     WHERE appointment_number = '$orderId'";
            
            Database::iud($query);
            
            // Create notification
            $query = "INSERT INTO notifications (title, message, type) 
                     VALUES ('Payment Received', 'Payment confirmed for appointment $orderId', 'payment')";
            Database::iud($query);
            
            echo "OK";
        } catch (Exception $e) {
            error_log("Payment notification error: " . $e->getMessage());
            echo "ERROR";
        }
    } else {
        echo "INVALID";
    }
}
?>

<?php
// email_handler.php
class EmailHandler {
    
    public static function sendAppointmentConfirmation($to, $appointmentData) {
        $subject = "Appointment Confirmation - Erundeniya Ayurveda Hospital";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { background: #333; color: white; padding: 10px; text-align: center; }
                .appointment-details { background: white; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Appointment Confirmed</h2>
                </div>
                <div class='content'>
                    <p>Dear {$appointmentData['patient_name']},</p>
                    <p>Your appointment has been successfully booked at Erundeniya Ayurveda Hospital.</p>
                    
                    <div class='appointment-details'>
                        <h3>Appointment Details</h3>
                        <div class='detail-row'>
                            <strong>Appointment Number:</strong>
                            <span>{$appointmentData['appointment_number']}</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Date:</strong>
                            <span>{$appointmentData['appointment_date']}</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Time:</strong>
                            <span>{$appointmentData['appointment_time']}</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Amount Paid:</strong>
                            <span>Rs. {$appointmentData['total_amount']}</span>
                        </div>
                    </div>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>Please arrive 15 minutes before your scheduled time</li>
                        <li>Bring this confirmation email or note down your appointment number</li>
                        <li>Contact us at +94 71 291 9408 if you need to reschedule</li>
                    </ul>
                    
                    <p>We look forward to serving you.</p>
                    <p>Best regards,<br>Erundeniya Ayurveda Hospital Team</p>
                </div>
                <div class='footer'>
                    <p>A/55 Wedagedara, Erundeniya, Amithirigala, North<br>
                    Phone: +94 71 291 9408 | Email: info@erundeniyaayurveda.lk</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Erundeniya Ayurveda Hospital <info@erundeniyaayurveda.lk>\r\n";
        $headers .= "Reply-To: info@erundeniyaayurveda.lk\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    public static function sendAdminNotification($appointmentData) {
        $subject = "New Appointment Booked - {$appointmentData['appointment_number']}";
        
        $message = "
        <h3>New Appointment Notification</h3>
        <p>A new appointment has been booked:</p>
        <ul>
            <li><strong>Patient:</strong> {$appointmentData['patient_name']}</li>
            <li><strong>Mobile:</strong> {$appointmentData['mobile']}</li>
            <li><strong>Appointment Number:</strong> {$appointmentData['appointment_number']}</li>
            <li><strong>Date:</strong> {$appointmentData['appointment_date']}</li>
            <li><strong>Time:</strong> {$appointmentData['appointment_time']}</li>
            <li><strong>Amount:</strong> Rs. {$appointmentData['total_amount']}</li>
        </ul>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: System <noreply@erundeniyaayurveda.lk>\r\n";
        
        return mail("info@erundeniyaayurveda.lk", $subject, $message, $headers);
    }
}
?>

