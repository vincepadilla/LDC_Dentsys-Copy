<?php
session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Correct verification code
$correctCode = "nicole123";
$error = '';
$verified = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify'])) {
    $enteredCode = trim($_POST['code']);

    if ($enteredCode === $correctCode) {
        $_SESSION['admin_verified'] = true;
        $verified = true;

        // Redirect to admin selection page
        header("Refresh: 2; url=admin_selection.php");
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Verification | Landero Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/loginpagestyle.css?v=<?php echo time(); ?>">

    <style>
        /* Verification Overlay Styles */
        .verifying-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(28, 51, 68, 0.98);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            flex-direction: column;
            color: white;
            text-align: center;
        }

        .verifying-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
            max-width: 400px;
            padding: 40px;
        }

        .verifying-spinner {
            width: 80px;
            height: 80px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #63b3ed;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .success-icon {
            font-size: 80px;
            color: #68d391;
            animation: scaleIn 0.6s ease-out;
        }

        .verifying-text {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .verifying-subtext {
            font-size: 16px;
            color: #cbd5e0;
            margin: 0;
            line-height: 1.5;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes scaleIn {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            70% {
                transform: scale(1.1);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Custom styles for this page */
        .admin-verification-container {
            display: flex;
            height: 100vh;
            min-height: 700px;
        }

        .admin-left {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.8)), url('../assets/images/taytayclinic.png') no-repeat center center/cover;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-left-content {
            position: relative;
            color: #fff;
            text-align: left;
            max-width: 500px;
            padding: 40px;
            z-index: 2;
        }

        .admin-left-content h1 {
            font-size: 42px;
            line-height: 1.2;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .admin-left-content p {
            font-size: 20px;
            color: #e0e0e0;
            margin-bottom: 40px;
            font-weight: 300;
        }

        .admin-feature-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .admin-feature-item {
            display: flex;
            align-items: center;
            font-size: 16px;
            color: #f0f0f0;
        }

        .admin-feature-item i {
            margin-right: 12px;
            font-size: 18px;
            color: #4fc3f7;
            width: 20px;
            text-align: center;
        }

        .admin-right {
            flex: 1;
            background: linear-gradient(135deg, #1c3344 0%, #2d4a5e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .admin-form-container {
            width: 100%;
            max-width: 450px;
            color: white;
        }

        .admin-logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .admin-logo {
            width: 90px;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .admin-welcome-section {
            text-align: center;
            margin-bottom: 35px;
        }

        .admin-welcome-section h2 {
            font-size: 32px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .admin-welcome-section p {
            color: #e2e8f0;
            font-size: 16px;
            font-weight: 400;
        }

        .admin-input-group {
            margin-bottom: 25px;
        }

        .admin-input-group label {
            display: block;
            margin-bottom: 8px;
            color: #e2e8f0;
            font-weight: 500;
            font-size: 14px;
        }

        .admin-input-field {
            position: relative;
            transition: all 0.3s ease;
        }

        .admin-input-field input {
            width: 100%;
            padding: 14px 45px 14px 45px;
            border: 2px solid #4a5568;
            border-radius: 12px;
            font-size: 15px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .admin-input-field input::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }

        .admin-input-field input:focus {
            outline: none;
            border-color: #63b3ed;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.1);
        }

        .admin-input-field i {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #cbd5e0;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .admin-toggle-password {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #cbd5e0;
            cursor: pointer;
            padding: 4px;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .admin-toggle-password:hover {
            color: #63b3ed;
        }

        .admin-auth-btn {
            width: 100%;
            background: linear-gradient(135deg, #63b3ed 0%, #4299e1 100%);
            color: white;
            padding: 15px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .admin-auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 179, 237, 0.3);
        }

        .admin-auth-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #4a5568;
        }

        .admin-link-text {
            color: #cbd5e0;
            font-size: 14px;
        }

        .admin-link {
            color: #63b3ed;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .admin-link:hover {
            color: #90cdf4;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-verification-container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }

            .admin-left {
                flex: 0;
                min-height: 200px;
                padding: 30px 20px;
                justify-content: flex-start;
                align-items: flex-start;
            }

            .admin-left-content {
                max-width: 100%;
                padding: 20px 0;
                text-align: center;
                align-self: center;
            }

            .admin-left-content h1 {
                font-size: 28px;
                margin-bottom: 15px;
            }

            .admin-left-content p {
                font-size: 16px;
                margin-bottom: 20px;
            }

            .admin-feature-list {
                gap: 10px;
                align-items: center;
            }

            .admin-feature-item {
                font-size: 14px;
                justify-content: center;
            }

            .admin-right {
                flex: 1;
                padding: 30px 20px;
                min-height: auto;
            }

            .admin-form-container {
                max-width: 100%;
                width: 100%;
            }

            .admin-logo {
                width: 70px;
            }

            .admin-welcome-section h2 {
                font-size: 24px;
            }

            .admin-welcome-section p {
                font-size: 14px;
            }

            .admin-input-group {
                margin-bottom: 20px;
            }

            .admin-input-group label {
                font-size: 13px;
                margin-bottom: 6px;
            }

            .admin-input-field input {
                padding: 12px 40px 12px 40px;
                font-size: 14px;
            }

            .admin-auth-btn {
                padding: 13px 20px;
                font-size: 15px;
                margin-bottom: 20px;
            }

            .verifying-content {
                max-width: 90%;
                padding: 30px 20px;
                gap: 15px;
            }

            .verifying-spinner {
                width: 60px;
                height: 60px;
            }

            .success-icon {
                font-size: 60px;
            }

            .verifying-text {
                font-size: 18px;
            }

            .verifying-subtext {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .admin-left {
                min-height: 150px;
                padding: 20px;
            }

            .admin-left-content h1 {
                font-size: 22px;
                line-height: 1.3;
                margin-bottom: 10px;
            }

            .admin-left-content p {
                font-size: 14px;
                margin-bottom: 15px;
            }

            .admin-feature-item {
                font-size: 12px;
            }

            .admin-feature-item i {
                font-size: 16px;
                margin-right: 8px;
            }

            .admin-right {
                padding: 20px 16px;
            }

            .admin-form-container {
                width: 100%;
            }

            .admin-logo {
                width: 60px;
            }

            .admin-welcome-section {
                margin-bottom: 25px;
            }

            .admin-welcome-section h2 {
                font-size: 20px;
                margin-bottom: 6px;
            }

            .admin-welcome-section p {
                font-size: 13px;
            }

            .admin-input-group {
                margin-bottom: 15px;
            }

            .admin-input-group label {
                font-size: 12px;
            }

            .admin-input-field input {
                padding: 11px 35px 11px 35px;
                font-size: 13px;
            }

            .admin-input-field i {
                left: 12px;
            }

            .admin-toggle-password {
                right: 25px;
            }

            .admin-auth-btn {
                padding: 12px 18px;
                font-size: 14px;
                gap: 8px;
                margin-bottom: 15px;
            }

            .admin-auth-link {
                margin-top: 15px;
                padding-top: 15px;
            }

            .admin-link-text {
                font-size: 13px;
            }

            .error-message,
            .success-message {
                font-size: 13px;
                padding: 12px 15px;
            }

            .verifying-content {
                max-width: 100%;
                padding: 20px 16px;
            }

            .verifying-text {
                font-size: 16px;
            }

            .verifying-subtext {
                font-size: 13px;
            }
        }

        /* Touch-friendly adjustments */
        @media (hover: none) {
            .admin-auth-btn:active {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(99, 179, 237, 0.3);
            }
        }
    </style>
</head>
<body>

    <!-- Verification Overlay -->
    <div class="verifying-overlay" id="verifyingOverlay">
        <div class="verifying-content" id="verifyingContent">
            <div class="verifying-spinner" id="verifyingSpinner"></div>
            <div class="verifying-text">Verifying Admin Access...</div>
            <div class="verifying-subtext">Please wait while we verify your credentials</div>
        </div>
    </div>

    <div class="admin-verification-container">
        <!-- LEFT SIDE -->
        <div class="admin-left">
            <div class="admin-left-content">
                <h1>Admin Security<br>Verification</h1>
                <p>Secure Access to Admin Dashboard</p>
                <div class="admin-feature-list">
                    <div class="admin-feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Enhanced Security</span>
                    </div>
                    <div class="admin-feature-item">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin Privileges</span>
                    </div>
                    <div class="admin-feature-item">
                        <i class="fas fa-lock"></i>
                        <span>Protected Access</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="admin-right">
            <div class="admin-form-container">
                <div class="admin-logo-container">
                    <img src="../assets/images/landerologo.png" alt="Clinic Logo" class="admin-logo">
                </div>

                <div class="admin-welcome-section">
                    <h2>Admin Verification</h2>
                    <p>Enter the verification code to continue</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($verified): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span>Verification successful! Redirecting...</span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="verificationForm" class="auth-form">
                    <div class="admin-input-group">
                        <label for="code">Verification Code</label>
                        <div class="admin-input-field">
                            <i class="fas fa-key"></i>
                            <input type="password" name="code" id="code" required
                                   placeholder="Enter verification code"
                                   value="<?php echo isset($_POST['code']) ? htmlspecialchars($_POST['code']) : ''; ?>">
                            <button type="button" class="admin-toggle-password" id="toggleCode"></button>
                        </div>
                    </div>

                    <button type="submit" name="verify" class="admin-auth-btn" id="verifyBtn">
                        <span>Verify & Continue</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="admin-auth-link">
                        <p class="admin-link-text">
                            <a href="login.php" class="admin-link">← Back to Login</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const overlay = document.getElementById('verifyingOverlay');
        const verifyingSpinner = document.getElementById('verifyingSpinner');
        const verifyingContent = document.getElementById('verifyingContent');
        const verifyForm = document.getElementById('verificationForm');
        const toggleButton = document.getElementById('toggleCode');

        // Toggle password visibility
        toggleButton.addEventListener('click', function() {
            const codeInput = document.getElementById('code');
            const icon = this.querySelector('i');
            if (codeInput.type === 'password') {
                codeInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                codeInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Show overlay on form submit
        verifyForm.addEventListener('submit', function() {
            overlay.style.display = 'flex';
        });

        // Handle successful verification
        <?php if ($verified): ?>
            document.addEventListener('DOMContentLoaded', function() {
                overlay.style.display = 'flex';
                
                setTimeout(() => {
                    verifyingSpinner.style.display = 'none';
                    verifyingContent.innerHTML = `
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="verifying-text">Access Granted!</div>
                        <div class="verifying-subtext">Redirecting to selection page...</div>
                    `;
                }, 1000);
            });
        <?php endif; ?>

        // Add focus effects
        document.getElementById('code').addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        document.getElementById('code').addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });

        // Auto-focus on code input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('code').focus();
        });
    </script>

</body>
</html>