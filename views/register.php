<?php 
session_start();
include_once("../database/config.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/PhpMailer/src/PHPMailer.php';
require '../libraries/PhpMailer/src/SMTP.php';

$error = '';
$success = '';
$username = '';
$fname = '';
$lname = '';
$email = '';
$phone = '';
$birthdate = '';
$gender = '';
$address = '';
$password = '';
$confirm_password = '';
$terms_checked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_checked = isset($_POST['terms']);

    if (
        empty($username) || empty($fname) || empty($lname) || empty($email) ||
        empty($phone) || empty($password) || empty($confirm_password) ||
        empty($birthdate) || empty($gender) || empty($address) || !$terms_checked
    ) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation do not match.';
    } elseif (!empty($password)) {
        $passwordErrors = [];
        
        if (strlen($password) < 6) {
            $passwordErrors[] = 'at least 6 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $passwordErrors[] = 'one capital letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $passwordErrors[] = 'one number';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $passwordErrors[] = 'one special character';
        }
        
        if (!empty($passwordErrors)) {
            $error = 'Password must contain: ' . implode(', ', $passwordErrors) . '.';
        }
    } elseif (!preg_match('/^\d{11}$/', $phone)) {
        $error = 'Invalid phone number. It must be exactly 11 digits.';
    }

    if (empty($error)) {
        $usernameEscaped = mysqli_real_escape_string($con, $username);
        $check_username = mysqli_query($con, "SELECT username FROM user_account WHERE username='$usernameEscaped'");
        if ($check_username && mysqli_num_rows($check_username) > 0) {
            $error = 'The username is already taken. Please choose a different username.';
        }
    }

    if (empty($error)) {
        $emailEscaped = mysqli_real_escape_string($con, $email);
        $check_email = mysqli_query($con, "SELECT email FROM user_account WHERE email='$emailEscaped'");
        if ($check_email && mysqli_num_rows($check_email) > 0) {
            $error = 'The email is already registered.';
        }
    }

    if (empty($error)) {
        $result = mysqli_query($con, "SELECT user_id FROM user_account ORDER BY user_id DESC LIMIT 1");
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $lastID = intval(substr($row['user_id'], 1)) + 1;
            $user_id = "U" . str_pad($lastID, 4, "0", STR_PAD_LEFT);
        } else {
            $user_id = "U0001";
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $otp = rand(100000, 999999);

        $_SESSION['temp_user'] = [
            'user_id' => $user_id,
            'username' => $username,
            'fname' => $fname,
            'lname' => $lname,
            'birthdate' => $birthdate,
            'gender' => $gender,
            'address' => $address,
            'email' => $email,
            'phone' => $phone,
            'password' => $hashed_password
        ];
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 600;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mlanderodentalclinic@gmail.com';
            $mail->Password = 'xrfp cpvv ckdv jmht';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
            $mail->addAddress($email, $fname . ' ' . $lname);
            $mail->Subject = 'OTP Verification';
            $mail->Body = "Hello $fname,\n\nYour OTP for account verification is: $otp\n\nThis code will expire in 10 minutes.\n\nThank you!";

            $mail->send();

            header("Location: otpVerification.php");
            exit();
        } catch (Exception $e) {
            unset($_SESSION['temp_user'], $_SESSION['otp'], $_SESSION['otp_expiry']);
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            $error = 'Mailer Error: Unable to send OTP at this time. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Landero Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/registerstyle.css?v=<?php echo time(); ?>">
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
                <h1>Your Journey to<br>Confidence Begins Here</h1>
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

        <!-- RIGHT SIDE - COMPACT DESIGN -->
        <div class="login-right">
            <div class="right-content compact-form">

                <div class="welcome-section">
                    <h2>Create your account to get started</h2>
                    
                </div>

                <?php if (!empty($error)) { ?>
                    <div class="error-message compact-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php } ?>
                
                <?php if (!empty($success)) { ?>
                    <div class="success-message compact-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php } ?>

                <form action="register.php" method="post" class="auth-form compact-auth-form">
                    <div class="form-row compact-row">
                        <div class="form-group compact-group half-width">
                            <label for="fname">First Name</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-user"></i>
                                <input type="text" name="fname" id="fname" placeholder="First name" value="<?php echo htmlspecialchars($fname, ENT_QUOTES); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group compact-group half-width">
                            <label for="lname">Last Name</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-user"></i>
                                <input type="text" name="lname" id="lname" placeholder="Last name" value="<?php echo htmlspecialchars($lname, ENT_QUOTES); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group compact-group">
                        <label for="username">Username</label>
                        <div class="input-field compact-input">
                            <i class="fas fa-at"></i>
                            <input type="text" name="username" id="username" placeholder="Choose username" value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>" required>
                        </div>
                    </div>

                    <div class="form-group compact-group">
                        <label for="email">Email</label>
                        <div class="input-field compact-input">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required>
                        </div>
                    </div>

                    <div class="form-group compact-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-field compact-input">
                            <i class="fas fa-phone"></i>
                            <input type="text" name="phone" id="phone" placeholder="11-digit phone number" maxlength="11" pattern="\d{11}" value="<?php echo htmlspecialchars($phone, ENT_QUOTES); ?>" required>
                        </div>
                    </div>

                    <!-- NEW FIELDS: Birthdate, Gender, Address -->
                    <div class="form-row compact-row">
                        <div class="form-group compact-group half-width">
                            <label for="birthdate">Birthdate</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-calendar"></i>
                                <input type="date" name="birthdate" id="birthdate" value="<?php echo htmlspecialchars($birthdate, ENT_QUOTES); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group compact-group half-width">
                            <label for="gender">Gender</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-venus-mars"></i>
                                <select name="gender" id="gender" required>
                                    <option value="" disabled <?php echo $gender === '' ? 'selected' : ''; ?>>Select gender</option>
                                    <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group compact-group">
                        <label for="address">Address</label>
                        <div class="input-field compact-input">
                            <i class="fas fa-home"></i>
                            <input type="text" name="address" id="address" placeholder="Enter your complete address" value="<?php echo htmlspecialchars($address, ENT_QUOTES); ?>" required>
                        </div>
                    </div>

                    <div class="form-row compact-row">
                        <div class="form-group compact-group half-width">
                            <label for="password">Password</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password" placeholder="Create password" value="<?php echo htmlspecialchars($password, ENT_QUOTES); ?>" required>
                                <button type="button" class="toggle-password compact-toggle" id="togglePassword"></button>
                            </div>
                        </div>

                        <div class="form-group compact-group half-width">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-field compact-input">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" value="<?php echo htmlspecialchars($confirm_password, ENT_QUOTES); ?>" required>
                                <button type="button" class="toggle-password compact-toggle" id="toggleConfirmPassword"></button>
                            </div>
                        </div>
                    </div>

                    <div class="terms-agreement compact-terms">
                        <input type="checkbox" id="terms" name="terms" <?php echo $terms_checked ? 'checked' : ''; ?> required>
                        <label for="terms">I agree to the <a href="#" class="terms-link">Terms</a> and <a href="#" class="terms-link">Privacy Policy</a></label>
                    </div>

                    <button type="submit" name="submit" class="auth-btn compact-btn">
                        <span>Create Account</span>
                        <i class="fas fa-user-plus"></i>
                    </button>

                    <div class="auth-link compact-link">
                        <p class="link-text">
                            Already have an account? <a href="login.php" class="link">Sign in</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
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

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Add focus effects
        document.querySelectorAll('.input-field input, .input-field select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Set max date for birthdate (minimum age 18 years)
        const birthdateInput = document.getElementById('birthdate');
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
        birthdateInput.max = maxDate.toISOString().split('T')[0];
    </script>
</body>
</html>