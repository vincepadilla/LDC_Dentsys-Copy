<?php
session_start();
include_once("../database/config.php");

$error = '';
$success = '';
$showSuccessAlert = false;

// Check if registration was successful (allow success alert even if session is cleared)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $showSuccessAlert = true;
    $success = 'Registration successful!';
    // Don't check for session if showing success alert
} else {
    // Check if OTP session exists (only if not showing success alert)
    if (!isset($_SESSION['temp_user']) || !isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        echo "<script>alert('No registration session found. Please register again.'); window.location.href='register.php';</script>";
        exit;
    }

    // Check if OTP expired on page load
    if (time() > $_SESSION['otp_expiry']) {
        unset($_SESSION['temp_user'], $_SESSION['otp'], $_SESSION['otp_expiry']);
        echo "<script>alert('OTP expired. Please register again.'); window.location.href='register.php';</script>";
        exit;
    }
}

if (isset($_POST['submit'])) {
    // Check if session still exists
    if (!isset($_SESSION['temp_user']) || !isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        $error = 'Session expired. Please register again.';
    } else {
        $entered_otp = trim($_POST['otp'] ?? '');

        // Validate OTP format
        if (empty($entered_otp)) {
            $error = 'Please enter the OTP code.';
        } elseif (!preg_match('/^\d{6}$/', $entered_otp)) {
            $error = 'Please enter a valid 6-digit OTP code.';
        } else {
        // Check if OTP expired
        if (time() > $_SESSION['otp_expiry']) {
            unset($_SESSION['temp_user'], $_SESSION['otp'], $_SESSION['otp_expiry']);
            echo "<script>alert('OTP expired. Please register again.'); window.location.href='register.php';</script>";
            exit;
        }

        // Verify OTP
        if ($entered_otp == $_SESSION['otp']) {
            $user_id = $_SESSION['temp_user']['user_id'];
            $username = $_SESSION['temp_user']['username'];
            $fname = $_SESSION['temp_user']['fname'];
            $lname = $_SESSION['temp_user']['lname'];
            $email = $_SESSION['temp_user']['email'];
            $phone = $_SESSION['temp_user']['phone'];
            $birthdate = $_SESSION['temp_user']['birthdate'];
            $gender = $_SESSION['temp_user']['gender'];
            $address = $_SESSION['temp_user']['address'];
            $password_hash = $_SESSION['temp_user']['password'];

            // Check for duplicate username or email before attempting insert
            $check_duplicate = $con->prepare("SELECT user_id, username, email FROM user_account WHERE username = ? OR email = ? OR user_id = ?");
            $check_duplicate->bind_param("sss", $username, $email, $user_id);
            $check_duplicate->execute();
            $duplicate_result = $check_duplicate->get_result();
            
            if ($duplicate_result->num_rows > 0) {
                $duplicate_row = $duplicate_result->fetch_assoc();
                if ($duplicate_row['username'] === $username) {
                    $error = 'This username is already taken. Please choose a different username.';
                } elseif ($duplicate_row['email'] === $email) {
                    $error = 'This email is already registered. Please use a different email or try logging in.';
                } elseif ($duplicate_row['user_id'] === $user_id) {
                    $error = 'User ID conflict. Please try registering again.';
                } else {
                    $error = 'This account information is already registered.';
                }
                $check_duplicate->close();
            } else {
                $check_duplicate->close();
                
                // Use transaction to ensure both inserts succeed
                mysqli_begin_transaction($con);

                try {
                    // Insert into user_account
                    $query1 = "INSERT INTO user_account 
                                (user_id, username, first_name, last_name, birthdate, gender, address, email, phone, password_hash, role, contactNumber_verify) 
                               VALUES 
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'patient', 'verified')";
                    $stmt1 = $con->prepare($query1);
                    $stmt1->bind_param("ssssssssss", $user_id, $username, $fname, $lname, $birthdate, $gender, $address, $email, $phone, $password_hash);
                    
                    if ($stmt1->execute()) {
                        // Commit the transaction
                        mysqli_commit($con);
                        $stmt1->close();
                        
                        // Clear session data
                        unset($_SESSION['temp_user'], $_SESSION['otp'], $_SESSION['otp_expiry']);
                        
                        // Set success flag for modal display
                        $_SESSION['registration_success'] = true;
                        
                        // Redirect to show success modal (reload page)
                        header("Location: otpVerification.php?success=1");
                        exit;
                    } else {
                        $error_code = $stmt1->errno;
                        $error_message = $stmt1->error;
                        $stmt1->close();
                        
                        // Handle specific MySQL error codes
                        if ($error_code == 1062) { // Duplicate entry
                            if (strpos($error_message, 'username') !== false) {
                                $error = 'This username is already taken. Please choose a different username.';
                            } elseif (strpos($error_message, 'email') !== false) {
                                $error = 'This email is already registered. Please use a different email or try logging in.';
                            } elseif (strpos($error_message, 'user_id') !== false) {
                                $error = 'User ID conflict. Please try registering again.';
                            } else {
                                $error = 'This information is already registered. Please check your details.';
                            }
                        } elseif ($error_code == 1048) { // NULL value in NOT NULL field
                            $error = 'Missing required information. Please ensure all fields are filled.';
                        } else {
                            $error = 'Database error: ' . htmlspecialchars($error_message) . ' (Error Code: ' . $error_code . ')';
                        }
                        
                        throw new Exception("Failed to insert user data: " . $error_message . " (Error Code: " . $error_code . ")");
                    }

                } catch (Exception $e) {
                    mysqli_rollback($con);
                    error_log('Database Transaction Error: ' . $e->getMessage());
                    // Error message already set above, or use generic one if not set
                    if (empty($error)) {
                        $error = 'Database error during registration. Please try again later.';
                    }
                }
            }

        } else {
            $error = 'Invalid OTP code. Please try again.';
        }
        }
    }
}

// Calculate remaining time for the timer (only if session exists)
$remaining_time = 0;
if (!$showSuccessAlert && isset($_SESSION['otp_expiry'])) {
    $remaining_time = $_SESSION['otp_expiry'] - time();
    if ($remaining_time < 0) {
        $remaining_time = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | Landero Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/loginpagestyle.css?v=<?php echo time(); ?>">
    <style>
        /* Success Alert Animation Styles */
        .success-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 320px;
            max-width: 450px;
            transform: translateX(500px);
            opacity: 0;
            animation: slideInRight 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes slideInRight {
            0% {
                transform: translateX(500px);
                opacity: 0;
            }
            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            0% {
                transform: translateX(0);
                opacity: 1;
            }
            100% {
                transform: translateX(500px);
                opacity: 0;
            }
        }

        .success-alert.slide-out {
            animation: slideOutRight 0.4s ease-in forwards;
        }

        .success-alert-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }

        .success-alert-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.5;
            }
        }

        .success-alert-icon i {
            font-size: 24px;
            position: relative;
            z-index: 1;
        }

        .success-alert-content {
            flex: 1;
        }

        .success-alert-content h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 700;
        }

        .success-alert-content p {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.4;
        }

        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Notification Styles */
        .otp-notification {
            position: relative;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .otp-notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .otp-notification.success {
            background: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        .otp-notification.error {
            background: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .otp-notification i {
            font-size: 16px;
        }

        /* OTP input complete state */
        .otp-input-field.complete {
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .otp-input-field.complete .otp-icon {
            color: #10B981;
        }

        /* Resend button improvements */
        .resend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .resend-btn.active {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .resend-btn.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        @media (max-width: 600px) {
            .success-alert {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
                padding: 16px 20px;
            }

            .success-alert-icon {
                width: 40px;
                height: 40px;
            }

            .success-alert-icon i {
                font-size: 20px;
            }

            .success-alert-content h3 {
                font-size: 16px;
            }

            .success-alert-content p {
                font-size: 13px;
            }

            .otp-notification {
                font-size: 13px;
                padding: 10px 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- LEFT SIDE -->
        <div class="login-left">
            <div class="overlay"></div>
            <div class="left-content">
                <h1>Secure Your Account</h1>
                <p class="subtitle">One-Time Password Verification</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Enhanced Security</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Valid for 10 minutes</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Sent to your phone</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="login-right">
            <div class="right-content">
                <div class="logo-container">
                    <img src="../assets/images/landerologo.png" alt="Clinic Logo" class="clinic-logo">
                </div>

                <div class="welcome-section">
                    <h2>OTP Verification</h2>
                    <p>Enter the 6-digit code sent to your Email</p>
                    <p class="phone-number"><?php echo (isset($_SESSION['temp_user']['email']) && !$showSuccessAlert) ? ' ' . htmlspecialchars($_SESSION['temp_user']['email']) : ''; ?></p>
                </div>

                <?php if (!empty($error)) { ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php } ?>

                <?php if ($showSuccessAlert): ?>
                    <div id="successAlert" class="success-alert">
                        <div class="success-alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="success-alert-content">
                            <h3>Registration Successful!</h3>
                            <p>Your account has been created successfully. Redirecting to login...</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$showSuccessAlert): ?>
                <form action="" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="otp">Enter OTP Code</label>
                        <div class="otp-container">
                            <div class="otp-input-field">
                                <input type="text" name="otp" id="otp" maxlength="6" pattern="\d{6}" autocomplete="off" required 
                                       placeholder="Enter 6-digit code" class="otp-input" value="<?php echo isset($_POST['otp']) ? htmlspecialchars($_POST['otp']) : ''; ?>">
                                <i class="fas fa-key otp-icon"></i>
                            </div>
                        </div>
                        <div class="otp-info">
                            <p><i class="fas fa-info-circle"></i> Check your phone for the OTP code</p>
                        </div>
                    </div>

                    <div class="otp-timer">
                        <i class="fas fa-clock"></i>
                        <span id="timer"><?php echo sprintf('%02d:%02d', floor($remaining_time / 60), $remaining_time % 60); ?></span> remaining
                    </div>

                    <button type="submit" name="submit" class="auth-btn">
                        <span>Verify OTP</span>
                        <i class="fas fa-check-circle"></i>
                    </button>

                    <div class="otp-actions">
                        <button type="button" class="resend-btn <?php echo $remaining_time <= 0 ? 'active' : ''; ?>" id="resendOtp" <?php echo $remaining_time > 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-redo"></i>
                            <span>Resend OTP</span>
                        </button>
                    </div>

                    <div class="auth-link">
                        <p class="link-text">
                            <a href="register.php" class="link">← Back to Registration</a>
                        </p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // OTP Timer Countdown - using actual remaining time from PHP
        let timeLeft = <?php echo $remaining_time; ?>;
        const timerElement = document.getElementById('timer');
        const resendButton = document.getElementById('resendOtp');

        function updateTimer() {
            if (!timerElement) return;
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft > 0) {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                if (resendButton) {
                    resendButton.disabled = false;
                    resendButton.innerHTML = '<i class="fas fa-redo"></i><span>Resend OTP</span>';
                    resendButton.classList.add('active');
                }
                timerElement.textContent = '00:00';
                timerElement.style.color = '#f56565';
            }
        }

        // Start the timer if there's time left and timer element exists
        if (timeLeft > 0 && timerElement) {
            updateTimer();
        } else if (timerElement && resendButton) {
            // Timer expired, enable resend button
            resendButton.disabled = false;
            resendButton.classList.add('active');
        }

        // Resend OTP functionality
        if (resendButton) {
            resendButton.addEventListener('click', function() {
                if (!this.disabled && timeLeft <= 0) {
                    // Show loading state
                    this.disabled = true;
                    this.classList.remove('active');
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Sending...</span>';
                    
                    // AJAX call to resend OTP
                    fetch('resend_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'resend_otp=true'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.innerHTML = '<i class="fas fa-check"></i><span>OTP Sent!</span>';
                            this.style.color = '#10B981';
                            
                            // Reset timer with new expiry if provided
                            if (data.expiry) {
                                const now = Math.floor(Date.now() / 1000);
                                timeLeft = data.expiry - now;
                                if (timeLeft < 0) timeLeft = 0;
                            } else {
                                timeLeft = 600; // 10 minutes default
                            }
                            
                            // Reset timer display
                            timerElement.style.color = '';
                            updateTimer();
                            
                            // Show success message
                            showNotification('New OTP has been sent to your email!', 'success');
                            
                            setTimeout(() => {
                                this.innerHTML = '<i class="fas fa-redo"></i><span>Resend OTP</span>';
                                this.disabled = true;
                                this.classList.remove('active');
                                this.style.color = '';
                            }, 3000);
                        } else {
                            this.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Failed</span>';
                            this.style.color = '#f56565';
                            
                            // Show error message
                            showNotification(data.message || 'Failed to send OTP. Please try again.', 'error');
                            
                            setTimeout(() => {
                                this.innerHTML = '<i class="fas fa-redo"></i><span>Resend OTP</span>';
                                this.disabled = false;
                                this.classList.add('active');
                                this.style.color = '';
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Error</span>';
                        this.style.color = '#f56565';
                        
                        showNotification('An error occurred. Please try again later.', 'error');
                        
                        setTimeout(() => {
                            this.innerHTML = '<i class="fas fa-redo"></i><span>Resend OTP</span>';
                            this.disabled = false;
                            this.classList.add('active');
                            this.style.color = '';
                        }, 3000);
                    });
                }
            });
        }

        // Auto-format OTP input
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                // Limit to 6 digits
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
                // Visual feedback when complete
                if (this.value.length === 6) {
                    this.parentElement.classList.add('complete');
                    // Optional: auto-submit (commented out for user control)
                    // this.form.submit();
                } else {
                    this.parentElement.classList.remove('complete');
                }
            });

            // Prevent paste of non-numeric content
            otpInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const numericOnly = paste.replace(/[^0-9]/g, '').slice(0, 6);
                this.value = numericOnly;
                if (numericOnly.length === 6) {
                    this.parentElement.classList.add('complete');
                }
                this.dispatchEvent(new Event('input'));
            });
        }

        // Remove duplicate input listener - already handled above

        // Add focus effects
        if (otpInput) {
            otpInput.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            otpInput.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });

            // Form validation before submit
            const form = otpInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const otpValue = otpInput.value.trim();
                    if (otpValue.length !== 6) {
                        e.preventDefault();
                        showNotification('Please enter a complete 6-digit OTP code.', 'error');
                        otpInput.focus();
                        return false;
                    }
                    if (!/^\d{6}$/.test(otpValue)) {
                        e.preventDefault();
                        showNotification('OTP must contain only numbers.', 'error');
                        otpInput.focus();
                        return false;
                    }
                });
            }
        }

        // Focus on OTP input when page loads (only if not showing success alert)
        document.addEventListener('DOMContentLoaded', function() {
            if (otpInput && !<?php echo $showSuccessAlert ? 'true' : 'false'; ?>) {
                otpInput.focus();
            }
        });

        // Redirect to login function
        function redirectToLogin() {
            window.location.href = 'login.php';
        }

        // Notification system
        function showNotification(message, type) {
            // Remove existing notification if any
            const existing = document.querySelector('.otp-notification');
            if (existing) {
                existing.remove();
            }

            const notification = document.createElement('div');
            notification.className = `otp-notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            const authForm = document.querySelector('.auth-form');
            if (authForm) {
                document.querySelector('.right-content').insertBefore(notification, authForm);
            } else {
                document.querySelector('.right-content').appendChild(notification);
            }
            
            // Animate in
            setTimeout(() => notification.classList.add('show'), 10);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Auto-redirect after 3 seconds if success alert is shown
        <?php if ($showSuccessAlert): ?>
        setTimeout(function() {
            const alert = document.getElementById('successAlert');
            if (alert) {
                // Slide out animation
                alert.classList.add('slide-out');
                // Redirect after animation completes
                setTimeout(function() {
                    redirectToLogin();
                }, 400);
            } else {
                redirectToLogin();
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>