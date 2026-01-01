<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Unauthorized Access - Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="../../img/logof1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .error-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 60px 30px;
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .error-body {
            padding: 40px 30px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2E8B57, #228B22);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 139, 87, 0.3);
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            margin-left: 10px;
        }

        .role-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .role-info h6 {
            color: #495057;
            margin-bottom: 10px;
        }

        .role-info small {
            display: block;
            margin-bottom: 5px;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-header">
            <i class="fas fa-ban error-icon"></i>
            <h2 class="mb-0">Access Denied</h2>
            <p class="mb-0 opacity-75">You don't have permission to access this page</p>
        </div>

        <div class="error-body">
            <h4 class="text-danger mb-3">Unauthorized Access</h4>
            <p class="text-muted mb-4">
                Your current user role doesn't have the necessary permissions to view this page. 
                Please contact your system administrator if you believe this is an error.
            </p>

            <div class="role-info">
                <h6><i class="fas fa-info-circle me-2"></i>System Access Levels</h6>
                <small><strong>Admin:</strong> Full system access including dashboard, reports, and settings</small>
                <small><strong>Receptionist:</strong> Appointments and patient management only</small>
                <small><strong>Pharmacist:</strong> Prescription management only</small>
            </div>

            <div class="mt-4">
                <button onclick="goBack()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Go Back
                </button>
                <a href="login.php?logout=1" class="btn btn-primary">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout & Login as Different User
                </a>
            </div>

            <div class="mt-4">
                <small class="text-muted">
                    Current User: <strong id="currentUser">Loading...</strong><br>
                    Role: <strong id="currentRole">Loading...</strong>
                </small>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'login.php';
            }
        }

        // Display current user info if available
        document.addEventListener('DOMContentLoaded', function() {
            // This would normally be populated by PHP session data
            fetch('get_current_user.php')
                .then(response => response.json())
                .then(data => {
                    if (data.username) {
                        document.getElementById('currentUser').textContent = data.username;
                        document.getElementById('currentRole').textContent = data.role;
                    }
                })
                .catch(() => {
                    document.getElementById('currentUser').textContent = 'Unknown';
                    document.getElementById('currentRole').textContent = 'Unknown';
                });
        });
    </script>
</body>
</html>