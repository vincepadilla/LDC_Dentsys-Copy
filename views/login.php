<?php
session_start();
include_once("../database/config.php");

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    header("Location: ../controllers/logout.php");
    exit();
}

// Check for account_not_found error from account.php redirect
if (isset($_GET['error']) && $_GET['error'] === 'account_not_found') {
    $error = 'Your account was not found. Please contact the administrator or try logging in again.';
}

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($con, trim($_POST['username']));
    $password = mysqli_real_escape_string($con, trim($_POST['password']));

    if (!empty($name) && !empty($password)) {

        // ✅ Check if user exists in user_account
        $query = "SELECT * FROM user_account WHERE username = ?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // ✅ Check if user account is blocked
            $userStatus = isset($row['status']) ? strtolower($row['status']) : 'active';
            if ($userStatus === 'blocked') {
                $error = "Your account has been blocked. Please contact the administrator.";
            } else {
                // ✅ Verify password
                if (password_verify($password, $row['password_hash'])) {
                    // ✅ Update last login timestamp
                    $updateLastLogin = "UPDATE user_account SET last_login = NOW() WHERE user_id = ?";
                    $updateStmt = $con->prepare($updateLastLogin);
                    $updateStmt->bind_param("s", $row['user_id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // ✅ If user is a dentist/admin, update their status to active in multidisciplinary_dental_team
                    $checkDentist = $con->prepare("SELECT team_id FROM multidisciplinary_dental_team WHERE user_id = ?");
                    $checkDentist->bind_param("s", $row['user_id']);
                    $checkDentist->execute();
                    $dentistResult = $checkDentist->get_result();
                    
                    if ($dentistResult && $dentistResult->num_rows > 0) {
                        $dentist = $dentistResult->fetch_assoc();
                        $team_id = $dentist['team_id'];
                        
                        // Update status to active
                        $updateStatus = $con->prepare("UPDATE multidisciplinary_dental_team SET status = 'active', last_active = NOW() WHERE team_id = ?");
                        $updateStatus->bind_param("s", $team_id);
                        $updateStatus->execute();
                        $updateStatus->close();
                    }
                    $checkDentist->close();
                    
                    // ✅ Set session variables (after successful login)
                    $_SESSION['userID'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['first_name'] = $row['first_name'] ?? '';
                    $_SESSION['last_name'] = $row['last_name'] ?? '';
                    $_SESSION['email'] = $row['email'] ?? '';
                    $_SESSION['phone'] = $row['phone'] ?? '';
                    $_SESSION['role'] = $row['role'] ?? 'user';
                    $_SESSION['valid'] = true;

                    $role = strtolower($row['role'] ?? 'user');

                    if ($role === 'super-admin' || $role === 'super_admin') {
                        header("Location: super_admin_portal.php");
                        exit();
                    } elseif ($role === 'admin') {
                        header("Location: admin_verify.php");
                        exit();
                    } else {
                        header("Location: index.php");
                        exit();
                    }
                } else {
                    $error = "Incorrect password. Please try again.";
                }
            }
        } else {
            $error = "No account found with that username.";
        }

    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Clinic Portal</title>
    <link rel="stylesheet" href="../assets/css/loginpagestyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">

        <!-- Back to Home Button -->
        <div class="back-home">
                <a href="index.php" class="back-home-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
        </div>
        <!-- LEFT SIDE -->
        <div class="login-left">
            <div class="overlay"></div>
            <div class="left-content">
                <h1>Smiles Made Simple<br>Start Yours Today</h1>
                <p class="subtitle">Landero Dental Clinic</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-tooth"></i>
                        <span>Expert Dental Care</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Easy Online Booking</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-star"></i>
                        <span>5-Star Rated Service</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE - NO CONTAINER BOX -->
        <div class="login-right">
            <div class="right-content">
                <div class="logo-container">
                    <img src="../assets/images/landerologo.png" alt="Clinic Logo" class="clinic-logo">
                </div>

                <div class="welcome-section">
                    <h2>Sign In to Your Account</h2>
                  
                </div>

                <?php if (isset($error)) { ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php } ?>

                <?php if (isset($_GET['forgot_password_success'])) { ?>
                    <div class="success-message" style="background-color:rgb(198, 198, 198); color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        <i class="fas fa-check-circle"></i>
                        <span>Password reset email sent! Please check your inbox.</span>
                    </div>
                <?php } ?>

                <form action="" method="post" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-field">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" id="username" placeholder="Enter your username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password"></button>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="remember-me">
                            <input type="checkbox" id="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                    </div>

                    <button type="submit" name="submit" class="auth-btn">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="auth-link">
                        <p class="link-text">
                            Don't have an account? <a href="/views/register.php" class="link">Sign up now</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #166088;">Forgot Password</h2>
                <span class="close-modal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px;">&times;</span>
            </div>
            <p style="color: #666; margin-bottom: 20px;">Enter your registered email address and we'll send you a new password.</p>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="forgotEmail" style="color: #333;">Email Address</label>
                    <div class="input-field">
                        <i class="fas fa-envelope" style="color: #666;"></i>
                        <input type="email" name="email" id="forgotEmail" placeholder="Enter your registered email" required style="color: #000 !important; background: #fff !important; border: 2px solid #ddd !important;">
                    </div>
                </div>
                <div id="forgotPasswordMessage" style="margin: 15px 0; padding: 10px; border-radius: 5px; display: none;"></div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="auth-btn" style="flex: 1;">
                        <span>Send Password</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <button type="button" class="close-modal" style="flex: 1; background-color: #6c757d; padding: 15px 24px; border: none; border-radius: 12px; color: white; cursor: pointer; font-size: 16px; font-weight: 600; display: flex; justify-content: center; align-items: center; gap: 10px; transition: all 0.3s ease;">
                        <span>Cancel</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Add focus effects
        document.querySelectorAll('.input-field input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Forgot Password Modal Functionality
        const modal = document.getElementById('forgotPasswordModal');
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const forgotPasswordMessage = document.getElementById('forgotPasswordMessage');

        // Open modal
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'block';
            forgotPasswordMessage.style.display = 'none';
            forgotPasswordForm.reset();
        });

        // Close modal
        closeModalButtons.forEach(button => {
            button.addEventListener('click', function() {
                modal.style.display = 'none';
                forgotPasswordMessage.style.display = 'none';
                forgotPasswordForm.reset();
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                forgotPasswordMessage.style.display = 'none';
                forgotPasswordForm.reset();
            }
        });

        // Handle forgot password form submission
        forgotPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('forgotEmail').value;
            const submitButton = forgotPasswordForm.querySelector('button[type="submit"]');
            const originalButtonHTML = submitButton.innerHTML;
            
            // Disable button and show loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>Sending...</span><i class="fas fa-spinner fa-spin"></i>';
            forgotPasswordMessage.style.display = 'none';
            
            // Send AJAX request
            fetch('../controllers/forgotPassword.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                forgotPasswordMessage.style.display = 'block';
                
                if (data.success) {
                    forgotPasswordMessage.style.backgroundColor = '#d4edda';
                    forgotPasswordMessage.style.color = '#155724';
                    forgotPasswordMessage.style.border = '1px solid #c3e6cb';
                    forgotPasswordMessage.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    
                    // Close modal after 3 seconds
                    setTimeout(function() {
                        modal.style.display = 'none';
                        forgotPasswordForm.reset();
                    }, 3000);
                } else {
                    forgotPasswordMessage.style.backgroundColor = '#f8d7da';
                    forgotPasswordMessage.style.color = '#721c24';
                    forgotPasswordMessage.style.border = '1px solid #f5c6cb';
                    forgotPasswordMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                }
                
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonHTML;
            })
            .catch(error => {
                forgotPasswordMessage.style.display = 'block';
                forgotPasswordMessage.style.backgroundColor = '#f8d7da';
                forgotPasswordMessage.style.color = '#721c24';
                forgotPasswordMessage.style.border = '1px solid #f5c6cb';
                forgotPasswordMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonHTML;
            });
        });
    </script>
</body>
</html>
