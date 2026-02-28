<?php
session_start();
include_once("../database/config.php");

// Redirect if not logged in or not admin
if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if admin is verified
if (empty($_SESSION['admin_verified'])) {
    header("Location: admin_verify.php");
    exit();
}

$user_id = $_SESSION['userID'];
$adminInfo = null;
$userAccount = null;

// Get admin/dentist info from multidisciplinary_dental_team
$query = "SELECT * FROM multidisciplinary_dental_team WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $adminInfo = $result->fetch_assoc();
}
$stmt->close();

// Get user account info
$userQuery = "SELECT email, username FROM user_account WHERE user_id = ?";
$userStmt = $con->prepare($userQuery);
$userStmt->bind_param("s", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult && $userResult->num_rows > 0) {
    $userAccount = $userResult->fetch_assoc();
}
$userStmt->close();

// Display success/error messages
$success_msg = '';
$error_msg = '';

if (isset($_SESSION['admin_account_success'])) {
    $success_msg = $_SESSION['admin_account_success'];
    unset($_SESSION['admin_account_success']);
}

if (isset($_SESSION['admin_account_error'])) {
    $error_msg = $_SESSION['admin_account_error'];
    unset($_SESSION['admin_account_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account | Landero Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .account-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .account-header {
            background: linear-gradient(135deg, #63b3ed 0%, #4299e1 100%);
            padding: 30px 40px;
            border-radius: 16px 16px 0 0;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .account-header h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .account-content {
            background: white;
            border-radius: 0 0 16px 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c3344;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 16px;
            color: #1c3344;
            font-weight: 500;
        }

        .form-section {
            margin-top: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.1);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        .password-toggle-btn:hover {
            color: #63b3ed;
        }

        .btn {
            background: linear-gradient(135deg, #63b3ed 0%, #4299e1 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 179, 237, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
            box-shadow: 0 8px 20px rgba(107, 114, 128, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 24px;
            color: #1c3344;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            color: #6b7280;
            cursor: pointer;
            line-height: 1;
        }

        .close-modal:hover {
            color: #1c3344;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .account-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .account-content {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="account-container">
        <div class="account-header">
            <h1><i class="fas fa-user-circle"></i> Admin Account</h1>
            <a href="admin_selection.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Selection
            </a>
        </div>

        <div class="account-content">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Account Information Section -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Account Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars(($adminInfo['first_name'] ?? '') . ' ' . ($adminInfo['last_name'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Specialization</div>
                        <div class="info-value"><?php echo htmlspecialchars($adminInfo['specialization'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($adminInfo['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($adminInfo['phone'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Update Email Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-envelope"></i>
                    Update Email
                </div>
                <form id="updateEmailForm" action="../controllers/updateAdminEmail.php" method="POST">
                    <div class="form-group">
                        <label for="new_email">New Email Address</label>
                        <input type="email" name="new_email" id="new_email" 
                               value="<?php echo htmlspecialchars($adminInfo['email'] ?? ''); ?>" 
                               required>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Email
                    </button>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </div>
                <form id="changePasswordForm" action="../controllers/updateAdminPassword.php" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-toggle">
                            <input type="password" name="current_password" id="current_password" required>
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-toggle">
                            <input type="password" name="new_password" id="new_password" required minlength="8">
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-toggle">
                            <input type="password" name="confirm_password" id="confirm_password" required>
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Forgot Password Link -->
            <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb; text-align: center;">
                <p style="color: #6b7280; margin-bottom: 15px;">Forgot your password?</p>
                <button type="button" class="btn btn-secondary" onclick="openForgotPasswordModal()">
                    <i class="fas fa-question-circle"></i> Reset Password via Email
                </button>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reset Password</h2>
                <button class="close-modal" onclick="closeForgotPasswordModal()">&times;</button>
            </div>
            <p style="color: #6b7280; margin-bottom: 20px;">Enter your registered email address and we'll send you a new password.</p>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="forgotEmail">Email Address</label>
                    <input type="email" name="email" id="forgotEmail" 
                           value="<?php echo htmlspecialchars($adminInfo['email'] ?? ''); ?>" 
                           required>
                </div>
                <div id="forgotPasswordMessage" style="margin: 15px 0; padding: 10px; border-radius: 5px; display: none;"></div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Send Password
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('show');
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('show');
            document.getElementById('forgotPasswordForm').reset();
            document.getElementById('forgotPasswordMessage').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('forgotPasswordModal');
            if (event.target === modal) {
                closeForgotPasswordModal();
            }
        }

        // Handle forgot password form submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('forgotEmail').value;
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonHTML = submitButton.innerHTML;
            const messageDiv = document.getElementById('forgotPasswordMessage');
            
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            messageDiv.style.display = 'none';
            
            fetch('../controllers/forgotPassword.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                
                if (data.success) {
                    messageDiv.style.backgroundColor = '#d4edda';
                    messageDiv.style.color = '#155724';
                    messageDiv.style.border = '1px solid #c3e6cb';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    
                    setTimeout(function() {
                        closeForgotPasswordModal();
                    }, 3000);
                } else {
                    messageDiv.style.backgroundColor = '#f8d7da';
                    messageDiv.style.color = '#721c24';
                    messageDiv.style.border = '1px solid #f5c6cb';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                }
                
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonHTML;
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonHTML;
            });
        });
    </script>
</body>
</html>
