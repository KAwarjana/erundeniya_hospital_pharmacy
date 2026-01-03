<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erundeniya Ayurveda Hospital</title>
    <link rel="icon" type="image/png" href="assets/images/logoblack.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Professional background pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url(assets/images/main_bg.jpeg);
            background-repeat: no-repeat;
            background-size: cover;
            z-index: 0;
            pointer-events: none;
        }

        /* Subtle animated gradient overlay */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
            background: linear-gradient(45deg, transparent 30%, rgba(102, 126, 234, 0.05) 50%, transparent 70%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 60px;
            animation: fadeInDown 0.8s ease;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .logo {
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 4px 6px rgba(0, 0, 0, 0.07),
                0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .logo img {
            width: 65px;
            height: 65px;
            object-fit: contain;
        }

        .header h1 {
            color: #1a202c;
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            color: #718096;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .header .subtitle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 10px 20px;
            border-radius: 50px;
            margin-top: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .header .subtitle .material-symbols-rounded {
            color: #667eea;
            font-size: 18px;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            padding: 20px;
        }

        .section-card {
            background: white;
            border-radius: 20px;
            padding: 45px 35px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 4px 6px rgba(0, 0, 0, 0.07),
                0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }

        .section-card.pharmacy::before {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .section-card.reception::before {
            background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
        }

        .section-card:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 20px 25px rgba(0, 0, 0, 0.1),
                0 10px 10px rgba(0, 0, 0, 0.04);
            border-color: rgba(0, 0, 0, 0.08);
        }

        .section-card:hover::before {
            height: 6px;
        }

        .section-card.pharmacy {
            animation-delay: 0.2s;
        }

        .section-card.reception {
            animation-delay: 0.4s;
        }

        .section-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .pharmacy .section-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.25);
        }

        .reception .section-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.25);
        }

        .section-card:hover .section-icon {
            transform: scale(1.05);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.35);
        }

        .reception.section-card:hover .section-icon {
            box-shadow: 0 12px 28px rgba(240, 147, 251, 0.35);
        }

        .section-icon .material-symbols-rounded {
            color: white;
            font-size: 52px;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a202c;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .section-description {
            font-size: 0.95rem;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .section-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .pharmacy .section-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .pharmacy .section-button:hover {
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .reception .section-button {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);
        }

        .reception .section-button:hover {
            box-shadow: 0 6px 16px rgba(240, 147, 251, 0.4);
            transform: translateY(-2px);
        }

        .section-button .material-symbols-rounded {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .section-button:hover .material-symbols-rounded {
            transform: translateX(3px);
        }

        .features-list {
            list-style: none;
            margin: 20px 0 25px;
            padding: 0;
            position: relative;
            z-index: 1;
            text-align: left;
        }

        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 0;
            color: #4a5568;
            font-size: 0.9rem;
        }

        .features-list li .material-symbols-rounded {
            font-size: 18px;
            color: #667eea;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .reception .features-list li .material-symbols-rounded {
            color: #f5576c;
        }

        .footer {
            text-align: center;
            margin-top: 60px;
            color: #718096;
            animation: fadeIn 1s ease 0.6s both;
        }

        .footer p {
            font-size: 0.875rem;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.875rem;
            }

            .header p {
                font-size: 0.95rem;
            }

            .logo {
                width: 75px;
                height: 75px;
            }

            .logo img {
                width: 55px;
                height: 55px;
            }

            .sections-grid {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 10px;
            }

            .section-card {
                padding: 35px 25px;
            }

            .section-icon {
                width: 85px;
                height: 85px;
            }

            .section-icon .material-symbols-rounded {
                font-size: 44px;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .section-description {
                font-size: 0.9rem;
            }

            .section-button {
                padding: 12px 28px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .header h1 {
                font-size: 1.625rem;
            }

            .logo {
                width: 70px;
                height: 70px;
            }

            .logo img {
                width: 50px;
                height: 50px;
            }

            .section-card {
                padding: 30px 20px;
            }

            .section-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }

            .section-title {
                font-size: 1.375rem;
            }
        }

        /* Loading animation */
        .section-card.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .section-card.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: url(assets/images/main_bg.jpeg);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            to {
                left: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <div class="logo">
                    <img src="assets/images/logoblack.png" alt="Logo">
                </div>
            </div>
            <h1>Erundeniya Ayurveda Hospital</h1>
            <p style="color: white;">Healthcare Management System</p>
            <div class="subtitle">
                <span class="material-symbols-rounded">shield</span>
                <span>Secure Login Portal</span>
            </div>
        </div>

        <!-- Sections Grid -->
        <div class="sections-grid">
            <!-- Pharmacy Section -->
            <div class="section-card pharmacy" onclick="navigateTo('pharmacy')">
                <div class="section-icon">
                    <span class="material-symbols-rounded">medication</span>
                </div>
                <h2 class="section-title">Pharmacy</h2>
                <p class="section-description">
                    Access pharmacy management system for inventory, sales, and prescriptions
                </p>
                <ul class="features-list">
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Manage medications & inventory</span>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Process sales & billing</span>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Track stock & expiry dates</span>
                    </li>
                </ul>
                <button class="section-button">
                    <span>Go to Pharmacy</span>
                    <span class="material-symbols-rounded">arrow_forward</span>
                </button>
            </div>

            <!-- Reception Section -->
            <div class="section-card reception" onclick="navigateTo('reception')">
                <div class="section-icon">
                    <span class="material-symbols-rounded">hotel</span>
                </div>
                <h2 class="section-title">Reception</h2>
                <p class="section-description">
                    Access reception system for patient management and appointments
                </p>
                <ul class="features-list">
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Manage patient registrations</span>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Schedule appointments</span>
                    </li>
                    <li>
                        <span class="material-symbols-rounded">check_circle</span>
                        <span>Handle inquiries & billing</span>
                    </li>
                </ul>
                <button class="section-button">
                    <span>Go to Reception</span>
                    <span class="material-symbols-rounded">arrow_forward</span>
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <script>document.write(new Date().getFullYear())</script> Erundeniya Ayurveda Hospital. All rights reserved.</p>
        </div>
    </div>

    <script>
        function navigateTo(section) {
            const card = event.currentTarget;
            card.classList.add('loading');
            
            // Add slight delay for animation effect
            setTimeout(() => {
                if (section === 'pharmacy') {
                    window.location.href = 'login.php'; // Your pharmacy login page
                } else if (section === 'reception') {
                    window.location.href = 'reception_login.php'; // Your reception login page
                }
            }, 300);
        }

        // Prevent accidental double-clicks
        let isNavigating = false;
        document.querySelectorAll('.section-card').forEach(card => {
            card.addEventListener('click', function() {
                if (isNavigating) return;
                isNavigating = true;
            });
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === '1') {
                document.querySelector('.pharmacy').click();
            } else if (e.key === '2') {
                document.querySelector('.reception').click();
            }
        });

        // Add touch feedback for mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.section-card').forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                card.addEventListener('touchend', function() {
                    this.style.transform = '';
                });
            });
        }
    </script>
</body>
</html>