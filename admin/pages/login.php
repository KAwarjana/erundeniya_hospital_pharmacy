<?php
// admin_login.php - Updated with role-based authentication
require_once 'auth_manager.php';

// Redirect if already logged in
if (AuthManager::isLoggedIn()) {
    $redirectUrl = AuthManager::getRolePermissions($_SESSION['role']);
    switch ($_SESSION['role']) {
        case 'Admin':
            header('Location: dashboard.php');
            break;
        case 'Receptionist':
            header('Location: appointments.php');
            break;
        case 'Pharmacist':
            header('Location: prescription.php');
            break;
        default:
            header('Location: unauthorized.php');
    }
    exit();
}

$error_message = '';
$success_message = '';

// Handle logout message
if (isset($_GET['logged_out'])) {
    $success_message = 'You have been logged out successfully.';
} elseif (isset($_GET['timeout'])) {
    $error_message = 'Your session has expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter username and password';
    } else {
        $result = AuthManager::login($username, $password);
        
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit();
        } else {
            $error_message = $result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="../../img/logof1.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #2E8B57, #228B22);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #2E8B57, #228B22);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            border-radius: 50%;
            background: white;
            padding: 10px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #2E8B57;
            box-shadow: 0 0 0 0.2rem rgba(46, 139, 87, 0.25);
        }

        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: #2E8B57;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #2E8B57;
        }

        .password-input-group {
            position: relative;
        }

        .password-input-group .form-control {
            padding-right: 50px;
            border-left: none;
            border-radius: 0 10px 10px 0 !important;
        }

        .btn-login {
            background: linear-gradient(135deg, #2E8B57, #228B22);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 139, 87, 0.3);
            color: white;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
        }

        .role-info {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .role-info h6 {
            color: #2E8B57;
            margin-bottom: 10px;
        }

        .role-info small {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <img src="../../img/logoblack.png" alt="Hospital Logo">
            <h4 class="mb-0">System Login</h4>
            <p class="mb-0 opacity-75">Erundeniya Ayurveda Hospital</p>
        </div>

        <div class="login-body">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" name="username" placeholder="Username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group password-input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <!-- <div class="role-info">
                <h6><i class="fas fa-info-circle me-2"></i>System Access Levels</h6>
                <small><strong>Admin:</strong> Full system access including dashboard and all features</small>
                <small><strong>Receptionist:</strong> Appointments and patient management only (No dashboard access)</small>
                <small class="text-muted"><strong>Note:</strong> Pharmacist access is currently disabled</small>
                <hr class="my-2">
                <small class="text-muted">
                    Demo Credentials: Admin/123, Receptionist/123
                </small>
            </div> -->
        </div>

        <div class="login-footer">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Erundeniya Ayurveda Hospital</p>
            <small>Secure System Access</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>

</html>